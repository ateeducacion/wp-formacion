<?php
/**
 * Tests for the educational centres catalogue and the venue of an action.
 *
 * @package Fmc
 */

use Fmc\Centre\CentreCatalogue;
use Fmc\Centre\CentreCatalogueSync;
use Fmc\Centre\CentreSettings;
use Fmc\Meta\ActionMetaKeys as A;
use Fmc\Meta\MetaRegistration;
use Fmc\PostType\PostTypes;
use Fmc\PublicFront\Editor;
use Fmc\PublicFront\Fields;
use Fmc\PublicFront\Lists;

/**
 * El centro donde se imparte una acción se elige por su código, de una copia
 * local del catálogo de centros que se pone al día desde su fuente (ADR-0021).
 */
class Test_Centres extends WP_UnitTestCase {

	/**
	 * Manifest URL of the tests.
	 */
	private const MANIFEST = 'https://centros.example.org/manifest.json';

	/**
	 * Requests the fake source has answered.
	 *
	 * @var string[]
	 */
	private $requests = array();

	/**
	 * Roles, caps and meta in place; no catalogue.
	 */
	public function set_up() {
		parent::set_up();
		fmc_register_roles();
		PostTypes::grant_caps_to_roles();
		MetaRegistration::register_meta();
		$this->requests = array();
		foreach ( array( CentreCatalogue::OPTION_CATALOGUE, CentreCatalogue::OPTION_STATUS, CentreCatalogueSync::OPTION_MANIFEST_URL, CentreCatalogueSync::OPTION_CATALOGUE_URL, CentreCatalogueSync::OPTION_LOCK ) as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * Undo the fake source.
	 */
	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'fmc_centres_manifest_url' );
		wp_clear_scheduled_hook( CentreCatalogueSync::CRON_HOOK );
		parent::tear_down();
	}

	/**
	 * Centres of the tests: two active, one closed.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function centres(): array {
		return array(
			array(
				'code'         => '90000102',
				'name'         => 'IES Ejemplo',
				'island'       => 'Zona Sur',
				'municipality' => 'Municipio 2',
				'type'         => 'IES',
				'active'       => true,
			),
			array(
				'code'         => '90000101',
				'name'         => 'CEIP Ejemplo',
				'island'       => 'Zona Norte',
				'municipality' => 'Municipio 1',
				'type'         => 'CEIP',
				'active'       => true,
			),
			array(
				'code'         => '90000103',
				'name'         => 'CEO Cerrado',
				'island'       => 'Zona Norte',
				'municipality' => 'Municipio 1',
				'type'         => 'CEO',
				'active'       => false,
			),
		);
	}

	/**
	 * Serve a catalogue from the fake source.
	 *
	 * @param string|array<int, mixed>|WP_Error $catalogue Records, a raw body or an error.
	 * @param array<string, mixed>              $manifest  Manifest overrides.
	 * @param int                               $code      HTTP status of the catalogue.
	 * @return void
	 */
	private function serve( $catalogue, array $manifest = array(), int $code = 200 ): void {
		$body = is_array( $catalogue ) ? (string) wp_json_encode( $catalogue ) : $catalogue;
		// Lo que dice el caso sustituye solo esa clave del manifest.
		$manifest = array_replace_recursive(
			array(
				'schema_version'       => 1,
				'catalogue_updated_at' => '2026-10-20T10:00:00Z',
				'files'                => array(
					'centros.min.json' => array(
						'sha256'  => is_string( $body ) ? hash( 'sha256', $body ) : str_repeat( 'a', 64 ),
						'records' => is_array( $catalogue ) ? count( $catalogue ) : 0,
					),
				),
			),
			$manifest
		);
		update_option( CentreCatalogueSync::OPTION_MANIFEST_URL, self::MANIFEST );
		remove_all_filters( 'pre_http_request' );
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $body, $manifest, $code ) {
				unset( $pre, $args );
				$this->requests[] = basename( $url );
				if ( 'manifest.json' === basename( $url ) ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => (string) wp_json_encode( $manifest ),
					);
				}
				return is_wp_error( $body ) ? $body : array(
					'response' => array( 'code' => $code ),
					'body'     => $body,
				);
			},
			10,
			3
		);
	}

	/**
	 * Sync and return the error message, or '' if it went well.
	 *
	 * @return string
	 */
	private function sync_error(): string {
		try {
			CentreCatalogueSync::sync( true );
			return '';
		} catch ( RuntimeException $e ) {
			return $e->getMessage();
		}
	}

	/**
	 * A logged-in user with a role.
	 *
	 * @param string $role Role.
	 * @return int
	 */
	private function as_role( string $role ): int {
		$id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $id );
		return $id;
	}

	/**
	 * Valid action fields, overridable.
	 *
	 * @param array<string, mixed> $over Overrides.
	 * @return array<string, mixed>
	 */
	private function action_fields( array $over = array() ): array {
		return array_merge(
			array(
				A::DESIGN_ID    => (string) self::factory()->post->create(
					array(
						'post_type'   => PostTypes::DESIGN,
						'post_status' => 'publish',
					)
				),
				'tax:fmc_scope' => array( (string) self::factory()->term->create( array( 'taxonomy' => 'fmc_scope' ) ) ),
				A::PLACES       => '20',
				A::START        => '2026-10-01',
			),
			$over
		);
	}

	/**
	 * Un catálogo bueno se guarda entero, indexado por código, con su estado;
	 * la lista ofrece solo los activos, por orden alfabético.
	 */
	public function test_a_valid_catalogue_is_stored() {
		$this->serve( $this->centres() );

		$result = CentreCatalogueSync::sync( true );

		$this->assertSame( 'updated', $result['status'] );
		$this->assertSame( 3, CentreCatalogue::total() );
		$this->assertSame( 2, CentreCatalogue::active_count() );
		$this->assertSame(
			array(
				'90000101' => 'CEIP Ejemplo',
				'90000102' => 'IES Ejemplo',
			),
			CentreCatalogue::active_options()
		);
		$this->assertTrue( CentreCatalogue::is_active( '90000101' ) );
		$this->assertFalse( CentreCatalogue::is_active( '90000103' ), 'un centro cerrado se guarda, pero no está activo' );
		$this->assertSame( 'CEO Cerrado', CentreCatalogue::find( '90000103' )['name'] );
		$this->assertSame( '2026-10-20T10:00:00Z', CentreCatalogue::status()['catalogue_updated_at'] );
		$this->assertSame( '', CentreCatalogue::status()['last_error'] );
		$this->assertSame( array( 'manifest.json', 'centros.min.json' ), $this->requests, 'el catálogo se busca junto al manifest' );
	}

	/**
	 * Lo que llega mal no se guarda: se sigue con la última copia buena y el
	 * motivo queda en el estado.
	 */
	public function test_a_bad_download_keeps_the_last_good_catalogue() {
		$this->serve( $this->centres() );
		CentreCatalogueSync::sync( true );

		$twice   = $this->centres();
		$twice[] = $twice[0];
		$no_name = $this->centres();

		$no_name[1]['name'] = '';
		$cases              = array(
			'el hash no cuadra'     => array( '[]', array( 'files' => array( 'centros.min.json' => array( 'sha256' => str_repeat( 'a', 64 ) ) ) ), 200, 'SHA-256' ),
			'no es JSON'            => array( 'no es json', array(), 200, 'JSON' ),
			'un código repetido'    => array( $twice, array(), 200, 'duplicado' ),
			'un centro sin nombre'  => array( $no_name, array(), 200, 'Denominación vacía' ),
			'otro esquema'          => array( $this->centres(), array( 'schema_version' => 2 ), 200, 'esquema' ),
			'el servidor falla'     => array( $this->centres(), array(), 500, 'HTTP 500' ),
			'no hay red'            => array( new WP_Error( 'http_request_failed', 'sin conexión' ), array(), 200, 'sin conexión' ),
			'faltan registros'      => array( array_slice( $this->centres(), 0, 2 ), array( 'files' => array( 'centros.min.json' => array( 'records' => 3 ) ) ), 200, 'número de registros' ),
			'un código de 7 cifras' => array( array( array( 'code' => '9000010' ) + $this->centres()[0] ), array(), 200, '8 dígitos' ),
			'activo no es booleano' => array( array( array( 'active' => 'sí' ) + $this->centres()[0] ), array(), 200, 'booleano' ),
		);
		foreach ( $cases as $name => list( $catalogue, $manifest, $code, $message ) ) {
			$this->serve( $catalogue, $manifest, $code );
			$this->assertStringContainsString( $message, $this->sync_error(), $name );
			$this->assertSame( 3, CentreCatalogue::total(), $name );
			$this->assertStringContainsString( $message, CentreCatalogue::status()['last_error'], $name );
		}
	}

	/**
	 * Si el hash del manifest no ha cambiado, la copia diaria no descarga el
	 * catálogo otra vez; forzarla, sí.
	 */
	public function test_the_same_catalogue_is_not_downloaded_again() {
		$this->serve( $this->centres() );
		CentreCatalogueSync::sync( true );
		$this->requests = array();

		$this->assertSame( 'unchanged', CentreCatalogueSync::sync( false )['status'] );
		$this->assertSame( array( 'manifest.json' ), $this->requests );

		$this->assertSame( 'updated', CentreCatalogueSync::sync( true )['status'] );
	}

	/**
	 * Solo se descarga por https, y sin dirección no se descarga nada.
	 */
	public function test_only_https_sources() {
		$this->assertTrue( CentreCatalogueSync::is_valid_https_url( self::MANIFEST ) );
		$this->assertFalse( CentreCatalogueSync::is_valid_https_url( 'http://centros.example.org/manifest.json' ) );
		$this->assertFalse( CentreCatalogueSync::is_valid_https_url( 'https://' ) );
		$this->assertFalse( CentreCatalogueSync::is_valid_https_url( '' ) );

		$this->assertSame( '', CentreCatalogueSync::manifest_url(), 'no hay dirección por defecto' );
		$this->assertStringContainsString( 'HTTPS', $this->sync_error() );

		add_filter( 'fmc_centres_manifest_url', static fn(): string => 'http://centros.example.org/manifest.json' );
		$this->assertStringContainsString( 'HTTPS', $this->sync_error() );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * Una sincronización a la vez; un candado olvidado caduca.
	 */
	public function test_one_sync_at_a_time() {
		$this->serve( $this->centres() );
		$token = CentreCatalogueSync::acquire_lock();
		$this->assertIsString( $token );

		$this->assertStringContainsString( 'otra sincronización', $this->sync_error() );
		$this->assertFalse( CentreCatalogueSync::release_lock( 'otro' ), 'solo lo suelta quien lo tiene' );
		$this->assertTrue( CentreCatalogueSync::release_lock( $token ) );
		$this->assertSame( '', $this->sync_error() );

		update_option(
			CentreCatalogueSync::OPTION_LOCK,
			array(
				'token' => 'viejo',
				'time'  => time() - CentreCatalogueSync::LOCK_TTL - 1,
			),
			false
		);
		$this->assertSame( '', $this->sync_error(), 'un candado caducado no bloquea' );
	}

	/**
	 * La copia se pone al día una vez al día, y un fallo en la tarea no rompe
	 * nada: queda en el estado.
	 */
	public function test_the_catalogue_is_updated_daily() {
		CentreCatalogueSync::register_cron();
		$this->assertNotFalse( wp_next_scheduled( CentreCatalogueSync::CRON_HOOK ) );
		$this->assertSame( 'daily', wp_get_schedule( CentreCatalogueSync::CRON_HOOK ) );

		CentreCatalogueSync::cron_sync();
		$this->assertStringContainsString( 'HTTPS', CentreCatalogue::status()['last_error'] );
	}

	/**
	 * La administración pone la dirección y actualiza el catálogo desde
	 * «Ajustes → Centros educativos»; nadie más entra.
	 */
	public function test_the_administration_sets_the_source_and_updates() {
		$this->as_role( 'administrator' );
		$this->assertSame( 'no_source', CentreSettings::submit( '' ) );
		$this->assertSame( 'bad_url', CentreSettings::submit( 'http://centros.example.org/manifest.json' ) );
		$this->assertSame( '', get_option( CentreCatalogueSync::OPTION_MANIFEST_URL, '' ), 'una dirección sin https no se guarda' );

		$this->serve( $this->centres() );
		delete_option( CentreCatalogueSync::OPTION_MANIFEST_URL );
		$this->assertSame( 'updated', CentreSettings::submit( self::MANIFEST ) );
		$this->assertSame( self::MANIFEST, get_option( CentreCatalogueSync::OPTION_MANIFEST_URL ) );
		$this->assertSame( 3, CentreCatalogue::total() );

		$this->serve( 'no es json' );
		$this->assertSame( 'error', CentreSettings::submit( null ), 'sin dirección en el formulario, se queda la que había' );
		$this->assertSame( 3, CentreCatalogue::total() );

		ob_start();
		CentreSettings::render();
		$page = ob_get_clean();
		$this->assertStringContainsString( '3, de ellos 2 activos', $page );
		$this->assertStringContainsString( 'JSON', $page, 'el último error se ve' );

		$this->as_role( 'fmc_curator' );
		$this->expectException( WPDieException::class );
		CentreSettings::render();
	}

	/**
	 * La acción guarda el código y, al lado, el nombre del centro; uno que
	 * no está activo no se elige, pero el que ya tenía se conserva.
	 */
	public function test_an_action_saves_the_code_and_the_name() {
		$this->serve( $this->centres() );
		CentreCatalogueSync::sync( true );
		$this->as_role( 'fmc_curator' );

		$saved = Editor::apply( PostTypes::ACTION, 0, $this->action_fields( array( A::VENUE_CODE => '90000101' ) ), 'publish', 0 );
		$id    = $saved['id'];
		$this->assertSame( 'created', $saved['outcome'] );
		$this->assertSame( '90000101', get_post_meta( $id, A::VENUE_CODE, true ) );
		$this->assertSame( 'CEIP Ejemplo', get_post_meta( $id, A::VENUE_CENTRE, true ) );

		foreach ( array( '90000103', '12345678', 'Un centro' ) as $code ) {
			$wrong = Editor::apply( PostTypes::ACTION, $id, $this->action_fields( array( A::VENUE_CODE => $code ) ), '', 0 );
			$this->assertSame( 'invalid', $wrong['outcome'], $code );
			$this->assertArrayHasKey( A::VENUE_CODE, $wrong['errors'], $code );
		}

		// El centro cierra: la acción lo conserva, con su nombre.
		$catalogue                       = get_option( CentreCatalogue::OPTION_CATALOGUE );
		$catalogue['90000101']['active'] = false;
		$catalogue['90000101']['name']   = 'CEIP Ejemplo (cerrado)';
		update_option( CentreCatalogue::OPTION_CATALOGUE, $catalogue );
		$again = Editor::apply( PostTypes::ACTION, $id, $this->action_fields( array( A::VENUE_CODE => '90000101' ) ), '', 0 );
		$this->assertSame( 'saved', $again['outcome'] );
		$this->assertSame( '90000101', get_post_meta( $id, A::VENUE_CODE, true ) );
		$this->assertStringContainsString( '>CEIP Ejemplo (cerrado) (90000101) — ya no está activo</option>', Editor::screen( $id, '' )['body'] );

		Editor::apply( PostTypes::ACTION, $id, $this->action_fields( array( A::VENUE_CODE => '' ) ), '', 0 );
		$this->assertSame( '', get_post_meta( $id, A::VENUE_CODE, true ) );
		$this->assertSame( '', get_post_meta( $id, A::VENUE_CENTRE, true ), 'quitar el centro quita su nombre' );
	}

	/**
	 * Lo escrito a mano en el sistema anterior se queda y se enseña hasta que
	 * se elige un centro de la lista.
	 */
	public function test_a_handwritten_venue_stays_until_a_centre_is_chosen() {
		$this->serve( $this->centres() );
		CentreCatalogueSync::sync( true );
		$this->as_role( 'fmc_curator' );
		$id = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::ACTION,
				'post_status' => 'publish',
			)
		);
		update_post_meta( $id, A::VENUE_CENTRE, 'Centro de formación Norte' );

		$body = Editor::screen( $id, '' )['body'];
		$this->assertStringContainsString( 'Escrito a mano en el sistema anterior: «Centro de formación Norte».', $body );
		$this->assertStringContainsString( '>CEIP Ejemplo (90000101)</option>', $body );
		$this->assertStringNotContainsString( 'CEO Cerrado', $body, 'un centro cerrado no se ofrece' );
		$this->assertSame( 'Centro de formación Norte', Lists::plain( get_post( $id ), A::VENUE_CODE, Fields::all( PostTypes::ACTION )[ A::VENUE_CODE ] ) );

		Editor::apply( PostTypes::ACTION, $id, $this->action_fields(), '', 0 );
		$this->assertSame( 'Centro de formación Norte', get_post_meta( $id, A::VENUE_CENTRE, true ), 'guardar sin centro no lo borra' );

		Editor::apply( PostTypes::ACTION, $id, $this->action_fields( array( A::VENUE_CODE => '90000102' ) ), '', 0 );
		$this->assertSame( 'IES Ejemplo', get_post_meta( $id, A::VENUE_CENTRE, true ) );
		$this->assertSame( 'IES Ejemplo (90000102)', Lists::plain( get_post( $id ), A::VENUE_CODE, Fields::all( PostTypes::ACTION )[ A::VENUE_CODE ] ) );
		$this->assertStringNotContainsString( 'Escrito a mano', Editor::screen( $id, '' )['body'] );
	}

	/**
	 * Con el catálogo vacío, el campo dice dónde se actualiza.
	 */
	public function test_an_empty_catalogue_says_where_to_update_it() {
		$this->as_role( 'fmc_curator' );
		$this->assertStringContainsString( 'la administración lo actualiza en «Ajustes → Centros educativos»', Editor::screen( 0, PostTypes::ACTION )['body'] );
	}
}

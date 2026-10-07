<?php
/**
 * Tests for meta sanitising and field-level write permissions.
 *
 * @package Fmc
 */

use Fmc\Meta\ActionMetaKeys;
use Fmc\Meta\DesignMetaKeys;
use Fmc\Meta\IncidentMetaKeys;
use Fmc\Meta\MetaRegistration;
use Fmc\Meta\MetaTypes;
use Fmc\PostType\PostTypes;

/**
 * Un campo que no te toca no se guarda aunque lo mandes (ADR-0006).
 */
class Test_Meta extends WP_UnitTestCase {

	/**
	 * Roles and caps in place.
	 */
	public function set_up() {
		parent::set_up();
		fmc_register_roles();
		PostTypes::grant_caps_to_roles();
		// La suite limpia las metas registradas entre test y test.
		MetaRegistration::register_meta();
	}

	/**
	 * Cada tipo sanea lo suyo, y una fecha imposible se queda vacía.
	 */
	public function test_each_type_sanitises_its_values() {
		$this->assertSame( '2026-02-28', MetaTypes::sanitize( MetaTypes::DATE, '2026-02-28' ) );
		$this->assertSame( '', MetaTypes::sanitize( MetaTypes::DATE, '2026-02-30' ) );
		$this->assertSame( '', MetaTypes::sanitize( MetaTypes::DATE, '30/09/2026' ) );
		$this->assertSame( 2.5, MetaTypes::sanitize( MetaTypes::NUMBER, '2,5' ) );
		$this->assertSame( 0, MetaTypes::sanitize( MetaTypes::INT, '-3' ) );
		$this->assertSame( array( 3, 7 ), MetaTypes::sanitize( MetaTypes::IDS, array( '3', 'x', 7, 3 ) ) );
		$this->assertSame( '', MetaTypes::sanitize( MetaTypes::URL, 'javascript:alert(1)' ) );
		$this->assertStringNotContainsString( '<script', MetaTypes::sanitize( MetaTypes::HTML, '<p>a</p><script>x</script>' ) );
	}

	/**
	 * Un valor fuera de su lista cerrada se guarda vacío.
	 */
	public function test_closed_lists_reject_unknown_values() {
		$this->assertSame( 'blended', MetaRegistration::sanitize( PostTypes::DESIGN, DesignMetaKeys::MODALITY, 'blended' ) );
		$this->assertSame( '', MetaRegistration::sanitize( PostTypes::DESIGN, DesignMetaKeys::MODALITY, 'mixta' ) );
		$this->assertSame( '', MetaRegistration::sanitize( PostTypes::ACTION, ActionMetaKeys::SITUATION, 'lo que sea' ) );
	}

	/**
	 * La dueña de la acción no escribe el número de expediente; el servicio sí.
	 */
	public function test_the_file_number_is_only_written_by_the_training_service() {
		$owner  = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$action = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::ACTION,
				'post_author' => $owner,
			)
		);

		wp_set_current_user( $owner );
		$this->assertTrue( MetaRegistration::can_write( PostTypes::ACTION, ActionMetaKeys::PLACES, $action ) );
		$this->assertFalse( MetaRegistration::can_write( PostTypes::ACTION, ActionMetaKeys::FILE_NUMBER, $action ) );
		$this->assertFalse( current_user_can( 'edit_post_meta', $action, ActionMetaKeys::FILE_NUMBER ) );

		// El servicio no edita la acción, así que el `edit_post_meta` del núcleo
		// —que exige primero `edit_post`— le dice que no: su pantalla pregunta a
		// `can_write()`, que es la regla del campo (ADR-0006).
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'fmc_training_service' ) ) );
		$this->assertTrue( MetaRegistration::can_write( PostTypes::ACTION, ActionMetaKeys::FILE_NUMBER, $action ) );
	}

	/**
	 * Quien abre una incidencia no se la aprueba, y una vez enviada ya no la edita.
	 */
	public function test_an_adviser_cannot_resolve_an_incident() {
		$adviser  = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$incident = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::INCIDENT,
				'post_status' => 'draft',
				'post_author' => $adviser,
			)
		);

		wp_set_current_user( $adviser );
		$this->assertTrue( MetaRegistration::can_write( PostTypes::INCIDENT, IncidentMetaKeys::REASON, $incident ) );
		$this->assertFalse( MetaRegistration::can_write( PostTypes::INCIDENT, IncidentMetaKeys::RESOLUTION, $incident ) );

		wp_publish_post( $incident );
		$this->assertFalse( MetaRegistration::can_write( PostTypes::INCIDENT, IncidentMetaKeys::REASON, $incident ) );
	}

	/**
	 * Todas las claves llevan el prefijo y están registradas.
	 */
	public function test_every_key_is_prefixed_and_registered() {
		foreach ( MetaRegistration::maps() as $type => $map ) {
			foreach ( array_keys( $map ) as $key ) {
				$this->assertStringStartsWith( 'fmc_', $key );
				$this->assertTrue( registered_meta_key_exists( 'post', $key, $type ), $type . ' ' . $key );
			}
		}
	}

	/**
	 * Los tipos que no son texto se registran con su tipo de WordPress.
	 */
	public function test_each_value_type_maps_to_a_wordpress_type() {
		$this->assertSame( 'integer', MetaTypes::wp_type( MetaTypes::INT ) );
		$this->assertSame( 'number', MetaTypes::wp_type( MetaTypes::NUMBER ) );
		$this->assertSame( 'boolean', MetaTypes::wp_type( MetaTypes::BOOL ) );
		$this->assertSame( 'array', MetaTypes::wp_type( MetaTypes::IDS ) );
		$this->assertSame( 'array', MetaTypes::wp_type( MetaTypes::LIST ) );
		foreach ( array( MetaTypes::STRING, MetaTypes::TEXT, MetaTypes::HTML, MetaTypes::DATE, MetaTypes::URL, MetaTypes::EMAIL ) as $type ) {
			$this->assertSame( 'string', MetaTypes::wp_type( $type ), $type );
		}
	}

	/**
	 * Fechas: solo `AAAA-MM-DD` que exista en el calendario.
	 */
	public function test_dates_must_exist() {
		$this->assertSame( '2024-02-29', MetaTypes::sanitize( MetaTypes::DATE, '2024-02-29' ), 'bisiesto' );
		$this->assertSame( '', MetaTypes::sanitize( MetaTypes::DATE, '2026-02-29' ) );
		$this->assertSame( '', MetaTypes::sanitize( MetaTypes::DATE, '2026-13-01' ) );
		$this->assertSame( '', MetaTypes::sanitize( MetaTypes::DATE, '2026-04-31' ) );
		$this->assertSame( '', MetaTypes::sanitize( MetaTypes::DATE, '2026-4-1' ) );
		$this->assertSame( '', MetaTypes::sanitize( MetaTypes::DATE, '2026-04-01T10:00' ) );
		$this->assertSame( '2026-04-01', MetaTypes::sanitize( MetaTypes::DATE, ' 2026-04-01 ' ) );
		$this->assertSame( '', MetaTypes::sanitize( MetaTypes::DATE, '' ) );
	}

	/**
	 * Números: coma decimal, dos decimales y nunca negativos.
	 */
	public function test_numbers_are_non_negative() {
		$this->assertSame( 1.01, MetaTypes::sanitize( MetaTypes::NUMBER, '1,006' ) );
		$this->assertSame( 0.0, MetaTypes::sanitize( MetaTypes::NUMBER, '-4,5' ) );
		$this->assertSame( 0.0, MetaTypes::sanitize( MetaTypes::NUMBER, 'muchas' ) );
		$this->assertSame( 12, MetaTypes::sanitize( MetaTypes::INT, '12 plazas' ) );
		$this->assertSame( 0, MetaTypes::sanitize( MetaTypes::INT, 'x' ) );
	}

	/**
	 * Sí/no: lo que una casilla o un formulario mandan.
	 */
	public function test_booleans() {
		foreach ( array( '1', 'on', 'yes', 'true', true, 1 ) as $yes ) {
			$this->assertTrue( MetaTypes::sanitize( MetaTypes::BOOL, $yes ), (string) wp_json_encode( $yes ) );
		}
		foreach ( array( '0', 'off', 'no', '', 'quizá', false, null ) as $no ) {
			$this->assertFalse( MetaTypes::sanitize( MetaTypes::BOOL, $no ), (string) wp_json_encode( $no ) );
		}
	}

	/**
	 * Texto: una línea sin etiquetas; texto largo, con sus saltos de línea.
	 */
	public function test_texts_lose_their_tags() {
		$this->assertSame( 'Hola mundo', MetaTypes::sanitize( MetaTypes::STRING, "  Hola <b>mundo</b>\n" ) );
		$this->assertSame( "Línea uno\nLínea dos", MetaTypes::sanitize( MetaTypes::TEXT, "Línea uno\n<script>x</script>Línea dos" ) );
		$this->assertSame( 'sin tipo', MetaTypes::sanitize( 'desconocido', '<i>sin tipo</i>' ), 'un tipo desconocido se trata como texto' );
		$this->assertSame( '<p><strong>a</strong></p>', MetaTypes::sanitize( MetaTypes::HTML, '<p onclick="x()"><strong>a</strong></p>' ) );
	}

	/**
	 * Correo y URL: o válidos o vacíos.
	 */
	public function test_emails_and_urls() {
		$this->assertSame( 'ana@example.org', MetaTypes::sanitize( MetaTypes::EMAIL, ' ana@example.org ' ) );
		$this->assertSame( '', MetaTypes::sanitize( MetaTypes::EMAIL, 'ana(at)example' ) );
		$this->assertSame( 'https://example.org/matricula?a=1', MetaTypes::sanitize( MetaTypes::URL, 'https://example.org/matricula?a=1' ) );
		$this->assertSame( '', MetaTypes::sanitize( MetaTypes::URL, 'ftp://example.org/x' ) );
	}

	/**
	 * Listas: claves saneadas, sin vacíos ni repetidos, y un escalar cuenta como lista de uno.
	 */
	public function test_lists_and_ids() {
		$this->assertSame( array( 'plan', 'seminar' ), MetaTypes::sanitize( MetaTypes::LIST, array( 'Plan', 'plan', '', 'semi nar', 'seminar' ) ) );
		$this->assertSame( array( 'plan' ), MetaTypes::sanitize( MetaTypes::LIST, 'plan' ) );
		$this->assertSame( array(), MetaTypes::sanitize( MetaTypes::IDS, array( '0', '-0', 'abc' ) ) );
		$this->assertSame( array( 5 ), MetaTypes::sanitize( MetaTypes::IDS, '5' ) );
	}

	/**
	 * En una lista cerrada de varios valores se quedan solo los conocidos.
	 */
	public function test_multi_value_closed_lists_keep_only_known_values() {
		$this->assertSame(
			array( 'plan', 'seminar' ),
			MetaRegistration::sanitize( PostTypes::ACTION, ActionMetaKeys::TRAINING_PLANS, array( 'plan', 'inventado', 'seminar', 'plan' ) )
		);
		$this->assertSame( array(), MetaRegistration::sanitize( PostTypes::ACTION, ActionMetaKeys::TRAINING_PLANS, array( 'inventado' ) ) );
		$this->assertSame( 'running', MetaRegistration::sanitize( PostTypes::ACTION, ActionMetaKeys::SITUATION, 'running' ) );
		$this->assertSame( 'libre', MetaRegistration::sanitize( PostTypes::ACTION, 'fmc_no_registrada', '<b>libre</b>' ), 'una clave sin registrar se sanea como texto' );
	}

	/**
	 * El saneado registrado actúa también al guardar por la API de metas.
	 */
	public function test_the_registered_sanitiser_runs_on_save() {
		$action = self::factory()->post->create( array( 'post_type' => PostTypes::ACTION ) );

		update_post_meta( $action, ActionMetaKeys::SITUATION, 'lo que sea' );
		$this->assertSame( '', get_post_meta( $action, ActionMetaKeys::SITUATION, true ) );

		update_post_meta( $action, ActionMetaKeys::SITUATION, 'done' );
		$this->assertSame( 'done', get_post_meta( $action, ActionMetaKeys::SITUATION, true ) );
	}

	/**
	 * El permiso registrado por campo: la curaduría escribe los campos del servicio, la asesoría no.
	 */
	public function test_the_registered_auth_callback_guards_service_fields() {
		$owner  = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$action = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::ACTION,
				'post_author' => $owner,
			)
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'fmc_curator' ) ) );
		foreach ( array_keys( MetaRegistration::guarded()[ PostTypes::ACTION ] ) as $key ) {
			$this->assertTrue( current_user_can( 'edit_post_meta', $action, $key ), $key );
		}

		wp_set_current_user( $owner );
		foreach ( array_keys( MetaRegistration::guarded()[ PostTypes::ACTION ] ) as $key ) {
			$this->assertFalse( current_user_can( 'edit_post_meta', $action, $key ), $key );
		}
		$this->assertTrue( current_user_can( 'edit_post_meta', $action, ActionMetaKeys::PLACES ) );
	}

	/**
	 * Las metas se registran en `init`, después de los tipos.
	 */
	public function test_register_hooks_the_meta_after_the_post_types() {
		MetaRegistration::register();
		$this->assertSame( 12, has_action( 'init', array( MetaRegistration::class, 'register_meta' ) ) );
	}
}

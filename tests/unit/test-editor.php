<?php
/**
 * Tests for the edit screen: who opens what, and what a save really stores.
 *
 * @package Fmc
 */

use Fmc\Meta\ActionMetaKeys;
use Fmc\Meta\IncidentMetaKeys;
use Fmc\Meta\MetaRegistration;
use Fmc\PostType\PostTypes;
use Fmc\Meta\DesignMetaKeys;
use Fmc\PublicFront\Calendar;
use Fmc\PublicFront\Documents;
use Fmc\PublicFront\Editor;
use Fmc\PublicFront\Fields;
use Fmc\PublicFront\Lists;
use Fmc\PublicFront\Screen;

/**
 * Deshabilitar un campo en la pantalla no protege nada: lo que cuenta es lo
 * que `Editor::apply()` guarda cuando alguien manda el campo a mano (ADR-0006).
 */
class Test_Editor extends WP_UnitTestCase {

	/**
	 * Roles, caps and meta in place.
	 */
	public function set_up() {
		parent::set_up();
		fmc_register_roles();
		PostTypes::grant_caps_to_roles();
		MetaRegistration::register_meta();
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
	 * An action owned by a user.
	 *
	 * @param int $author Author.
	 * @return int
	 */
	private function action( int $author ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => PostTypes::ACTION,
				'post_author' => $author,
				'post_title'  => 'Acción',
			)
		);
	}

	/**
	 * Cada campo del editor es una meta registrada de su tipo, o un campo del post.
	 */
	public function test_every_editor_field_is_registered() {
		foreach ( array_keys( PostTypes::definitions() ) as $type ) {
			foreach ( array_keys( Fields::all( $type ) ) as $key ) {
				if ( in_array( $key, array( 'post_title', 'post_content', 'docs' ), true ) ) {
					continue;
				}
				if ( 0 === strpos( $key, 'tax:' ) ) {
					$this->assertTrue( is_object_in_taxonomy( $type, substr( $key, 4 ) ), $type . ' ' . $key );
					continue;
				}
				$this->assertArrayHasKey( $key, MetaRegistration::maps()[ $type ], $type . ' ' . $key );
			}
		}
	}

	/**
	 * La asesoría que manda el expediente a mano en su propia acción no lo guarda.
	 */
	public function test_an_adviser_cannot_smuggle_the_file_number() {
		$adviser = $this->as_role( 'fmc_adviser' );
		$action  = $this->action( $adviser );

		$result = Editor::apply(
			PostTypes::ACTION,
			$action,
			array(
				ActionMetaKeys::DESIGN_ID   => (string) self::factory()->post->create( array( 'post_type' => PostTypes::DESIGN ) ),
				'tax:fmc_scope'             => array(),
				ActionMetaKeys::PLACES      => '20',
				ActionMetaKeys::START       => '2026-10-01',
				ActionMetaKeys::FILE_NUMBER => 'INVENTADO',
				ActionMetaKeys::SITUATION   => 'done',
			),
			'',
			0
		);

		$this->assertSame( 'invalid', $result['outcome'], 'sin ámbito no se guarda' );
		$this->assertArrayNotHasKey( ActionMetaKeys::FILE_NUMBER, $result['values'] );
		$this->assertArrayNotHasKey( ActionMetaKeys::SITUATION, $result['values'] );
	}

	/**
	 * El servicio guarda el expediente de una acción ajena, y nada más.
	 */
	public function test_the_training_service_saves_only_its_fields() {
		$owner  = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$action = $this->action( $owner );
		$this->as_role( 'fmc_training_service' );

		$result = Editor::apply(
			PostTypes::ACTION,
			$action,
			array(
				'post_title'                => 'Cambiado',
				ActionMetaKeys::PLACES      => '99',
				ActionMetaKeys::FILE_NUMBER => 'EXP-1',
			),
			'',
			0
		);

		$this->assertSame( 'saved', $result['outcome'] );
		$this->assertSame( 'EXP-1', get_post_meta( $action, ActionMetaKeys::FILE_NUMBER, true ) );
		$this->assertSame( '', get_post_meta( $action, ActionMetaKeys::PLACES, true ) );
		$this->assertSame( 'Acción', get_the_title( $action ) );
	}

	/**
	 * La asesoría pulsa «finalizar» a mano: el diseño se queda como estaba.
	 */
	public function test_an_adviser_cannot_finalise_a_design_by_hand() {
		$adviser = $this->as_role( 'fmc_adviser' );
		$design  = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::DESIGN,
				'post_status' => 'pending',
				'post_author' => $adviser,
			)
		);

		$this->assertSame( 'pending', Editor::next_status( PostTypes::DESIGN, get_post( $design ), 'publish' ) );

		$this->as_role( 'fmc_curator' );
		$this->assertSame( 'publish', Editor::next_status( PostTypes::DESIGN, get_post( $design ), 'publish' ) );
	}

	/**
	 * Una incidencia sobre la acción de otra persona no se crea.
	 */
	public function test_an_incident_on_someone_elses_action_is_denied() {
		$other = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$this->as_role( 'fmc_adviser' );

		$result = Editor::apply(
			PostTypes::INCIDENT,
			0,
			array(
				IncidentMetaKeys::REASON   => 'Motivo',
				IncidentMetaKeys::PROPOSAL => 'Cambio',
			),
			'publish',
			$this->action( $other )
		);

		$this->assertSame( 'denied', $result['outcome'] );
	}

	/**
	 * Sobre la suya sí, cuelga de ella y nace pendiente aunque mande «aprobada».
	 */
	public function test_an_incident_hangs_from_its_action_and_starts_pending() {
		$adviser = $this->as_role( 'fmc_adviser' );
		$action  = $this->action( $adviser );

		$result = Editor::apply(
			PostTypes::INCIDENT,
			0,
			array(
				IncidentMetaKeys::REASON     => 'Motivo',
				IncidentMetaKeys::PROPOSAL   => 'Cambio',
				IncidentMetaKeys::RESOLUTION => 'approved',
			),
			'publish',
			$action
		);

		$this->assertSame( 'created', $result['outcome'] );
		$this->assertSame( $action, (int) get_post_field( 'post_parent', $result['id'] ) );
		$this->assertSame( 'pending', get_post_meta( $result['id'], IncidentMetaKeys::RESOLUTION, true ) );
		$this->assertSame( 'Acción', get_the_title( $result['id'] ), 'la incidencia se llama como su acción' );
	}

	/**
	 * Los ponentes son datos personales: el servicio no los abre.
	 */
	public function test_the_training_service_cannot_open_a_speaker() {
		$speaker = self::factory()->post->create( array( 'post_type' => PostTypes::SPEAKER ) );
		$this->as_role( 'fmc_training_service' );

		$this->assertFalse( Editor::can_view( get_post( $speaker ) ) );
	}

	/**
	 * La rejilla del mes empieza en lunes y cierra la última semana.
	 */
	public function test_the_month_grid_starts_on_monday() {
		$weeks = Calendar::weeks( '2025-11-01' );

		$this->assertSame( array( '', '', '', '', '', '2025-11-01', '2025-11-02' ), $weeks[0] );
		$this->assertCount( 5, $weeks );
		$this->assertSame( '2025-11-30', $weeks[4][6] );

		// Febrero de 2026 empieza en domingo y acaba en sábado: rellena el domingo.
		$feb = Calendar::weeks( '2026-02-01' );
		$this->assertSame( '2026-02-01', $feb[0][6] );
		$this->assertSame( '', end( $feb )[6] );
	}

	/**
	 * Fechas y horas como se leen en el listado.
	 */
	public function test_dates_and_hours_read_well() {
		$this->assertSame( '10/03/2026 – 24/03/2026', Lists::dates( '2026-03-10', '2026-03-24' ) );
		$this->assertSame( '10/03/2026', Lists::dates( '2026-03-10', '2026-03-10' ) );
		$this->assertSame( '9 + 3 h', Lists::hours( '9', '3' ) );
		$this->assertSame( '3 h', Lists::hours( '', '3' ) );
		$this->assertSame( '', Lists::hours( '', '' ) );
	}

	/**
	 * Borrar es solo de administración, ni siquiera de la curaduría.
	 */
	public function test_only_administration_can_delete() {
		$speaker = get_post( self::factory()->post->create( array( 'post_type' => PostTypes::SPEAKER ) ) );

		$this->as_role( 'fmc_curator' );
		$this->assertFalse( Screen::can_delete( $speaker ) );
		$this->assertStringNotContainsString( 'fmc_delete', Lists::row_buttons( $speaker ) );

		$this->as_role( 'administrator' );
		$this->assertTrue( Screen::can_delete( $speaker ) );
		$form = Screen::delete_form( $speaker );
		$this->assertStringContainsString( 'data-fmc-confirm=', $form );
		$this->assertStringContainsString( 'name="_fmc_nonce"', $form );
	}

	/**
	 * El correo del ponente es obligatorio.
	 */
	public function test_a_speaker_needs_an_email() {
		$this->as_role( 'fmc_adviser' );
		$result = Editor::apply(
			PostTypes::SPEAKER,
			0,
			array(
				'fmc_first_name' => 'Ana',
				'fmc_last_name'  => 'Pérez',
			),
			'',
			0
		);
		$this->assertSame( 'invalid', $result['outcome'] );
		$this->assertArrayHasKey( 'fmc_email', $result['errors'] );
	}

	/**
	 * Dentro del panel lateral se navega sin salir de él, y la ficha llega como fragmento.
	 */
	public function test_links_stay_inside_the_side_panel() {
		$this->assertStringNotContainsString( 'fmc_marco', Screen::url( array( Screen::ARG_EDIT => 3 ) ) );

		$_REQUEST[ Screen::ARG_FRAME ] = '1';
		$this->assertStringContainsString( 'fmc_marco=1', Screen::url( array( Screen::ARG_EDIT => 3 ) ) );
		$this->assertStringNotContainsString( 'fmc_marco', Screen::url( array( Screen::ARG_FRAME => '' ) ) );

		$this->as_role( 'administrator' );
		$speaker = get_post( self::factory()->post->create( array( 'post_type' => PostTypes::SPEAKER ) ) );
		$this->assertStringNotContainsString( 'fmc_marco', Screen::delete_form( $speaker ) );
		$fragment = Screen::document(
			array(
				'tab'   => 'speakers',
				'title' => 'Ficha',
				'body'  => '<p>cuerpo</p>',
			)
		);
		$this->assertStringStartsWith( '<div class="fmc-fragment" data-fmc-title="Ficha">', $fragment );
		$this->assertStringNotContainsString( '<html', $fragment );
		unset( $_REQUEST[ Screen::ARG_FRAME ] );
	}

	/**
	 * A design owned by a user.
	 *
	 * @param int    $author Author.
	 * @param string $status Status.
	 * @return int
	 */
	private function design( int $author, string $status = 'draft' ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => PostTypes::DESIGN,
				'post_author' => $author,
				'post_status' => $status,
				'post_title'  => 'Diseño ' . wp_generate_password( 6, false ),
			)
		);
	}

	/**
	 * Valid design fields, overridable.
	 *
	 * @param array<string, string> $over Overrides.
	 * @return array<string, string>
	 */
	private function design_fields( array $over = array() ): array {
		return array_merge(
			array(
				'post_title'                 => 'Robótica en el aula',
				'post_content'               => 'Descripción',
				DesignMetaKeys::TYPE         => 'course',
				DesignMetaKeys::MODALITY     => 'online',
				DesignMetaKeys::HOURS_ONSITE => '10',
				DesignMetaKeys::HOURS_ONLINE => '20',
			),
			$over
		);
	}

	/**
	 * En línea, las horas presenciales no cuentan y se guardan vacías.
	 */
	public function test_hidden_fields_are_stored_empty() {
		$adviser = $this->as_role( 'fmc_adviser' );
		$id      = $this->design( $adviser );

		$result = Editor::apply( PostTypes::DESIGN, $id, $this->design_fields(), 'draft', 0 );

		$this->assertSame( 'saved', $result['outcome'] );
		$this->assertSame( '', get_post_meta( $id, DesignMetaKeys::HOURS_ONSITE, true ) );
		$this->assertEquals( 20, get_post_meta( $id, DesignMetaKeys::HOURS_ONLINE, true ) );
	}

	/**
	 * Fuera de rango no se guarda: 300 horas no es un diseño, es una errata.
	 */
	public function test_out_of_range_numbers_are_rejected() {
		$adviser = $this->as_role( 'fmc_adviser' );
		$result  = Editor::apply( PostTypes::DESIGN, $this->design( $adviser ), $this->design_fields( array( DesignMetaKeys::HOURS_ONLINE => '300' ) ), 'draft', 0 );

		$this->assertSame( 'invalid', $result['outcome'] );
		$this->assertStringContainsString( 'entre 0 y 200', $result['errors'][ DesignMetaKeys::HOURS_ONLINE ] );
	}

	/**
	 * Dos diseños no se llaman igual; y guardar el mismo no choca consigo.
	 */
	public function test_design_titles_are_unique() {
		$adviser = $this->as_role( 'fmc_adviser' );
		$first   = $this->design( $adviser );
		$this->assertSame( 'saved', Editor::apply( PostTypes::DESIGN, $first, $this->design_fields(), 'draft', 0 )['outcome'] );
		$this->assertSame( 'saved', Editor::apply( PostTypes::DESIGN, $first, $this->design_fields(), 'draft', 0 )['outcome'] );

		$result = Editor::apply( PostTypes::DESIGN, $this->design( $adviser ), $this->design_fields(), 'draft', 0 );
		$this->assertSame( 'invalid', $result['outcome'] );
		$this->assertArrayHasKey( 'post_title', $result['errors'] );
	}

	/**
	 * A temporary file with some bytes.
	 *
	 * @param string $name  File name.
	 * @param string $bytes Contents.
	 * @return array<string, mixed>
	 */
	private function upload( string $name, string $bytes ): array {
		$tmp = wp_tempnam( $name );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fichero de prueba.
		file_put_contents( $tmp, $bytes );
		return array(
			'name'     => $name,
			'type'     => '',
			'tmp_name' => $tmp,
			'error'    => 0,
			'size'     => strlen( $bytes ),
		);
	}

	/**
	 * Subir, rechazar lo que no es del formato, cambiar y quitar.
	 */
	public function test_design_documents_upload_replace_and_remove() {
		$adviser = $this->as_role( 'fmc_adviser' );
		$id      = $this->design( $adviser );
		$pdf     = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";

		$problems = Documents::apply(
			$id,
			array(
				'design' => array( $this->upload( 'diseno.pdf', $pdf ) ),
				'extra'  => array( $this->upload( 'falso.pdf', 'esto es texto' ) ),
			),
			array()
		);
		$this->assertCount( 1, $problems, 'el texto con extensión .pdf no entra' );
		$docs = Documents::of( $id );
		$this->assertCount( 1, $docs['design'] );
		$this->assertCount( 0, $docs['extra'] );

		$old = $docs['design'][0]->ID;
		Documents::apply( $id, array( 'replace' => array( $old => $this->upload( 'diseno-v2.pdf', $pdf ) ) ), array() );
		$docs = Documents::of( $id );
		$this->assertCount( 1, $docs['design'] );
		$this->assertNotSame( $old, $docs['design'][0]->ID );
		$this->assertNull( get_post( $old ) );

		Documents::apply( $id, array(), array( $docs['design'][0]->ID ) );
		$this->assertCount( 0, Documents::of( $id )['design'] );
	}

	/**
	 * Finalizado, la asesoría ya no toca sus documentos; y nadie quita los de otro diseño.
	 */
	public function test_documents_follow_the_design_permissions() {
		$adviser = $this->as_role( 'fmc_adviser' );
		$done    = $this->design( $adviser, 'publish' );
		$this->assertFalse( Documents::can_manage( $done ) );
		$this->assertNotEmpty( Documents::apply( $done, array(), array() ) );

		$mine    = $this->design( $adviser );
		$foreign = self::factory()->attachment->create( array( 'post_parent' => $done ) );
		Documents::apply( $mine, array(), array( $foreign ) );
		$this->assertNotNull( get_post( $foreign ), 'un adjunto de otro diseño no se borra desde este' );
	}

	/**
	 * Lo que la acción deja en blanco se toma de su diseño.
	 */
	public function test_an_action_inherits_modality_and_hours_from_its_design() {
		$adviser = $this->as_role( 'fmc_adviser' );
		$design  = $this->design( $adviser, 'publish' );
		update_post_meta( $design, DesignMetaKeys::MODALITY, 'blended' );
		update_post_meta( $design, DesignMetaKeys::HOURS_ONSITE, 9 );
		update_post_meta( $design, DesignMetaKeys::HOURS_ONLINE, 3 );
		$scope = self::factory()->term->create( array( 'taxonomy' => 'fmc_scope' ) );

		$result = Editor::apply(
			PostTypes::ACTION,
			0,
			array(
				ActionMetaKeys::DESIGN_ID    => (string) $design,
				'tax:fmc_scope'              => array( (string) $scope ),
				ActionMetaKeys::PLACES       => '20',
				ActionMetaKeys::START        => '2026-11-10',
				ActionMetaKeys::MODALITY     => '',
				ActionMetaKeys::HOURS_ONSITE => '',
				ActionMetaKeys::HOURS_ONLINE => '',
			),
			'',
			0
		);

		$this->assertSame( 'created', $result['outcome'], implode( ' ', $result['errors'] ) );
		$this->assertSame( 'blended', get_post_meta( $result['id'], ActionMetaKeys::MODALITY, true ) );
		$this->assertEquals( 9, get_post_meta( $result['id'], ActionMetaKeys::HOURS_ONSITE, true ) );
		$this->assertSame( get_the_title( $design ), get_the_title( $result['id'] ) );
	}

	/**
	 * Filtrar acciones por ponente y por el tipo de su diseño.
	 */
	public function test_actions_filter_by_speaker_and_design_type() {
		$this->as_role( 'fmc_curator' );
		$speaker = self::factory()->post->create( array( 'post_type' => PostTypes::SPEAKER ) );
		$course  = self::factory()->post->create( array( 'post_type' => PostTypes::DESIGN ) );
		update_post_meta( $course, DesignMetaKeys::TYPE, 'course' );
		$with    = $this->action( get_current_user_id() );
		$without = $this->action( get_current_user_id() );
		update_post_meta( $with, ActionMetaKeys::SPEAKERS, array( $speaker ) );
		update_post_meta( $with, ActionMetaKeys::DESIGN_ID, $course );
		update_post_meta( $without, ActionMetaKeys::SPEAKERS, array( $speaker + 1000 ) );

		$ids = static function (): array {
			return wp_list_pluck( get_posts( Lists::query_args( PostTypes::ACTION ) + array( 'suppress_filters' => false ) ), 'ID' );
		};

		$_GET['fmc_speaker'] = (string) $speaker;
		$this->assertSame( array( $with ), $ids() );
		unset( $_GET['fmc_speaker'] );

		$_GET['fmc_type'] = 'course';
		$this->assertSame( array( $with ), $ids() );
		$_GET['fmc_type'] = 'one_off';
		$this->assertSame( array(), $ids() );
		unset( $_GET['fmc_type'] );
	}

	/**
	 * En el CSV van las etiquetas, no las claves.
	 */
	public function test_csv_values_are_readable() {
		$this->as_role( 'fmc_curator' );
		$action = $this->action( get_current_user_id() );
		update_post_meta( $action, ActionMetaKeys::SITUATION, 'done' );
		update_post_meta( $action, ActionMetaKeys::VIDEOCONFERENCE, true );
		$fields = Fields::all( PostTypes::ACTION );
		$post   = get_post( $action );

		$this->assertSame( 'Realizada', Lists::plain( $post, ActionMetaKeys::SITUATION, $fields[ ActionMetaKeys::SITUATION ] ) );
		$this->assertSame( 'Sí', Lists::plain( $post, ActionMetaKeys::VIDEOCONFERENCE, $fields[ ActionMetaKeys::VIDEOCONFERENCE ] ) );

		// Sin ponentes, la columna sale vacía: nada de `get_the_title( 0 )`,
		// que devuelve el título de la entrada global.
		$GLOBALS['post'] = self::factory()->post->create_and_get( array( 'post_title' => 'Entrada global' ) );
		$this->assertSame( '', Lists::columns( PostTypes::ACTION )['Ponentes']( $post ) );
	}
}

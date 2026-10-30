<?php
/**
 * Tests for what a save stores: Editor::apply(), Editor::save() and the design cycle.
 *
 * @package Fmc
 */

use Fmc\Meta\ActionMetaKeys as A;
use Fmc\Meta\DesignMetaKeys as D;
use Fmc\Meta\IncidentMetaKeys as I;
use Fmc\Meta\MetaRegistration;
use Fmc\Meta\SpeakerMetaKeys as S;
use Fmc\PostType\PostTypes;
use Fmc\PublicFront\Documents;
use Fmc\PublicFront\Editor;

/**
 * Lo que manda el formulario se vuelve a comprobar campo a campo en el
 * servidor: reglas de Fields::rules(), permiso por campo (ADR-0006) y ciclo
 * del diseño (ADR-0004).
 */
class Test_Editor_Save extends WP_UnitTestCase {

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
	 * Undo the faked request.
	 */
	public function tear_down() {
		$_POST    = array();
		$_REQUEST = array();
		remove_all_filters( 'wp_redirect' );
		parent::tear_down();
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
	 * A post of a type.
	 *
	 * @param string $type   Post type.
	 * @param int    $author Author.
	 * @param string $status Status.
	 * @return int
	 */
	private function post( string $type, int $author, string $status = 'publish' ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => $type,
				'post_author' => $author,
				'post_status' => $status,
				'post_title'  => 'Título ' . wp_generate_password( 6, false ),
			)
		);
	}

	/**
	 * How many posts of a type exist, in any status.
	 *
	 * @param string $type Post type.
	 * @return int
	 */
	private function count_of( string $type ): int {
		return count(
			get_posts(
				array(
					'post_type'   => $type,
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			)
		);
	}

	/**
	 * Valid design fields, overridable.
	 *
	 * @param array<string, mixed> $over Overrides.
	 * @return array<string, mixed>
	 */
	private function design_fields( array $over = array() ): array {
		return array_merge(
			array(
				'post_title'    => 'Diseño ' . wp_generate_password( 6, false ),
				'post_content'  => 'Descripción',
				D::TYPE         => 'course',
				D::MODALITY     => 'blended',
				D::HOURS_ONSITE => '10',
				D::HOURS_ONLINE => '20',
			),
			$over
		);
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
				A::DESIGN_ID    => (string) $this->post( PostTypes::DESIGN, 1 ),
				'tax:fmc_scope' => array( (string) self::factory()->term->create( array( 'taxonomy' => 'fmc_scope' ) ) ),
				A::START        => '2026-10-01',
			),
			$over
		);
	}

	/**
	 * La asesoría no escribe diseños: ni nuevos ni los suyos de antes (ADR-0017).
	 */
	public function test_an_adviser_does_not_write_designs() {
		$adviser = $this->as_role( 'fmc_adviser' );

		$this->assertSame( 'denied', Editor::apply( PostTypes::DESIGN, 0, $this->design_fields(), 'draft', 0 )['outcome'] );
		$this->assertSame( 0, $this->count_of( PostTypes::DESIGN ) );

		// Ni el borrador que escribió antes de que la asesoría dejara de escribirlos.
		$old = $this->post( PostTypes::DESIGN, $adviser, 'draft' );
		$this->assertSame( 'denied', Editor::apply( PostTypes::DESIGN, $old, $this->design_fields(), 'pending', 0 )['outcome'] );
		$this->assertSame( 'draft', get_post_status( $old ) );

		// Sin `publish_fmc_designs`, ni finalizar ni pasar a histórico.
		$this->assertSame( 'draft', Editor::next_status( PostTypes::DESIGN, get_post( $old ), 'publish' ) );
		$this->assertSame( 'draft', Editor::next_status( PostTypes::DESIGN, get_post( $old ), PostTypes::ARCHIVED ) );
		$this->assertSame( 'draft', Editor::next_status( PostTypes::DESIGN, null, 'publish' ), 'uno nuevo se queda en borrador' );
	}

	/**
	 * La coordinación finaliza y devuelve a borrador; un estado inventado no cambia nada.
	 */
	public function test_a_curator_finalises_and_returns_to_draft() {
		$adviser = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$design  = $this->post( PostTypes::DESIGN, $adviser, 'pending' );
		$this->as_role( 'fmc_curator' );

		Editor::apply( PostTypes::DESIGN, $design, $this->design_fields(), 'publish', 0 );
		$this->assertSame( 'publish', get_post_status( $design ) );

		Editor::apply( PostTypes::DESIGN, $design, $this->design_fields(), 'trash', 0 );
		$this->assertSame( 'publish', get_post_status( $design ) );

		Editor::apply( PostTypes::DESIGN, $design, $this->design_fields(), 'draft', 0 );
		$this->assertSame( 'draft', get_post_status( $design ) );

		$this->assertSame( 'draft', Editor::next_status( PostTypes::DESIGN, null, '' ) );

		// Lo que ya no se imparte pasa a histórico, se guarda en él y se recupera (ADR-0016).
		Editor::apply( PostTypes::DESIGN, $design, $this->design_fields(), PostTypes::ARCHIVED, 0 );
		$this->assertSame( PostTypes::ARCHIVED, get_post_status( $design ) );
		$this->assertSame( 'saved', Editor::apply( PostTypes::DESIGN, $design, $this->design_fields( array( 'post_title' => 'Corregido' ) ), PostTypes::ARCHIVED, 0 )['outcome'] );
		$this->assertSame( PostTypes::ARCHIVED, get_post_status( $design ) );
		Editor::apply( PostTypes::DESIGN, $design, $this->design_fields(), 'publish', 0 );
		$this->assertSame( 'publish', get_post_status( $design ) );
	}

	/**
	 * Los demás tipos nacen publicados si se puede publicar, y no cambian de estado al guardar.
	 */
	public function test_other_types_keep_their_status() {
		$this->as_role( 'fmc_adviser' );
		$this->assertSame( 'publish', Editor::next_status( PostTypes::SPEAKER, null, 'draft' ) );

		$draft = get_post( $this->post( PostTypes::SPEAKER, get_current_user_id(), 'draft' ) );
		$this->assertSame( 'draft', Editor::next_status( PostTypes::SPEAKER, $draft, 'publish' ) );

		$this->as_role( 'fmc_training_service' );
		$this->assertSame( 'draft', Editor::next_status( PostTypes::ACTION, null, 'publish' ) );
	}

	/**
	 * Lo obligatorio que falta se dice campo a campo, y no se guarda nada.
	 */
	public function test_required_fields_are_reported_and_nothing_is_stored() {
		$this->as_role( 'fmc_curator' );

		$result = Editor::apply(
			PostTypes::DESIGN,
			0,
			$this->design_fields(
				array(
					'post_title' => '',
					D::TYPE      => '',
				)
			),
			'draft',
			0
		);

		$this->assertSame( 'invalid', $result['outcome'] );
		$this->assertSame( 'Falta rellenar «Título».', $result['errors']['post_title'] );
		$this->assertSame( 'Falta rellenar «Tipo de formación».', $result['errors'][ D::TYPE ] );
		$this->assertArrayNotHasKey( D::MODALITY, $result['errors'] );
		$this->assertSame( 0, $result['values']['_parent'] );
		$this->assertSame( '10', $result['values'][ D::HOURS_ONSITE ], 'lo tecleado vuelve para no perderlo' );
		$this->assertSame( 0, $this->count_of( PostTypes::DESIGN ) );
	}

	/**
	 * Un número fuera de sus límites no se guarda, y se dice cuáles son.
	 */
	public function test_design_numbers_have_bounds() {
		$this->as_role( 'fmc_curator' );

		$result = Editor::apply(
			PostTypes::DESIGN,
			0,
			$this->design_fields( array( D::HOURS_ONSITE => '201' ) ),
			'draft',
			0
		);

		$this->assertSame( 'invalid', $result['outcome'] );
		$this->assertSame( '«Horas presenciales» tiene que estar entre 0 y 200.', $result['errors'][ D::HOURS_ONSITE ] );

		$ok = Editor::apply( PostTypes::DESIGN, 0, $this->design_fields( array( D::HOURS_ONSITE => '200' ) ), 'draft', 0 );
		$this->assertSame( 'created', $ok['outcome'] );
		$this->assertEquals( 200, get_post_meta( $ok['id'], D::HOURS_ONSITE, true ) );
	}

	/**
	 * La modalidad y las horas son las del diseño: en la acción no se teclean,
	 * y lo que llegue a mano en esos campos no cuenta (ADR-0006).
	 */
	public function test_the_modality_and_the_hours_come_from_the_design() {
		$this->as_role( 'fmc_adviser' );
		$design = $this->post( PostTypes::DESIGN, 1 );
		update_post_meta( $design, D::MODALITY, 'blended' );
		update_post_meta( $design, D::HOURS_ONSITE, 9 );
		update_post_meta( $design, D::HOURS_ONLINE, 3 );

		$result = Editor::apply(
			PostTypes::ACTION,
			0,
			$this->action_fields(
				array(
					A::DESIGN_ID    => (string) $design,
					A::MODALITY     => 'onsite',
					A::HOURS_ONSITE => '999',
				)
			),
			'',
			0
		);

		$this->assertSame( 'created', $result['outcome'], implode( ' ', $result['errors'] ) );
		$this->assertSame( 'blended', get_post_meta( $result['id'], A::MODALITY, true ) );
		$this->assertEquals( 9, get_post_meta( $result['id'], A::HOURS_ONSITE, true ) );
		$this->assertEquals( 3, get_post_meta( $result['id'], A::HOURS_ONLINE, true ) );
	}

	/**
	 * El expediente de formación solo cuenta si la acción va en un plan o seminario.
	 */
	public function test_training_file_depends_on_the_training_plans() {
		$this->as_role( 'fmc_adviser' );

		$with = Editor::apply(
			PostTypes::ACTION,
			0,
			$this->action_fields(
				array(
					A::TRAINING_PLANS => 'seminar',
					A::TRAINING_FILE  => 'EF-7',
				)
			),
			'',
			0
		);
		$this->assertSame( array( 'seminar' ), get_post_meta( $with['id'], A::TRAINING_PLANS, true ), 'uno solo, guardado como lista' );
		$this->assertSame( 'EF-7', get_post_meta( $with['id'], A::TRAINING_FILE, true ) );

		// «No está incluida en ninguno»: el expediente del plan deja de contar.
		Editor::apply(
			PostTypes::ACTION,
			$with['id'],
			$this->action_fields(
				array(
					A::TRAINING_PLANS => '',
					A::TRAINING_FILE  => 'EF-7',
				)
			),
			'',
			0
		);
		$this->assertSame( '', get_post_meta( $with['id'], A::TRAINING_PLANS, true ) );
		$this->assertSame( '', get_post_meta( $with['id'], A::TRAINING_FILE, true ) );
	}

	/**
	 * Una casilla que no llega es «no»: se puede desmarcar.
	 */
	public function test_an_unticked_checkbox_is_stored_as_off() {
		$this->as_role( 'fmc_adviser' );
		$created = Editor::apply( PostTypes::ACTION, 0, $this->action_fields( array( A::VIDEOCONFERENCE => '1' ) ), '', 0 );
		$this->assertTrue( (bool) get_post_meta( $created['id'], A::VIDEOCONFERENCE, true ) );

		Editor::apply( PostTypes::ACTION, $created['id'], $this->action_fields(), '', 0 );
		$this->assertFalse( (bool) get_post_meta( $created['id'], A::VIDEOCONFERENCE, true ) );
	}

	/**
	 * Dos acciones no comparten expediente.
	 */
	public function test_file_numbers_are_unique() {
		$adviser = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$first   = $this->post( PostTypes::ACTION, $adviser );
		$second  = $this->post( PostTypes::ACTION, $adviser );
		$this->as_role( 'fmc_training_service' );

		$this->assertSame( 'saved', Editor::apply( PostTypes::ACTION, $first, array( A::FILE_NUMBER => 'EXP-9' ), '', 0 )['outcome'] );
		$this->assertSame( 'saved', Editor::apply( PostTypes::ACTION, $first, array( A::FILE_NUMBER => 'EXP-9' ), '', 0 )['outcome'], 'consigo misma no choca' );

		$result = Editor::apply( PostTypes::ACTION, $second, array( A::FILE_NUMBER => 'EXP-9' ), '', 0 );
		$this->assertSame( 'invalid', $result['outcome'] );
		$this->assertSame( 'Ya hay otro con ese valor en «Expediente».', $result['errors'][ A::FILE_NUMBER ] );
		$this->assertSame( '', get_post_meta( $second, A::FILE_NUMBER, true ) );
		$this->assertTrue( Editor::taken( PostTypes::ACTION, A::FILE_NUMBER, 'EXP-9', 0 ) );
		$this->assertFalse( Editor::taken( PostTypes::ACTION, A::FILE_NUMBER, 'EXP-10', 0 ) );
	}

	/**
	 * El ponente se llama «Apellidos, Nombre» y su documento no se repite.
	 */
	public function test_a_speaker_is_named_by_surname_and_has_a_unique_id_number() {
		$this->as_role( 'fmc_adviser' );
		$fields = array(
			S::FIRST_NAME => 'Ana',
			S::LAST_NAME  => 'Pérez Díaz',
			S::EMAIL      => 'ana@example.org',
			S::ID_NUMBER  => '12345678Z',
		);

		$created = Editor::apply( PostTypes::SPEAKER, 0, $fields, '', 0 );
		$this->assertSame( 'created', $created['outcome'] );
		$this->assertSame( 'Pérez Díaz, Ana', get_the_title( $created['id'] ) );
		$this->assertSame( 'publish', get_post_status( $created['id'] ) );

		$again = Editor::apply( PostTypes::SPEAKER, 0, array( S::FIRST_NAME => 'Otra' ) + $fields, '', 0 );
		$this->assertSame( 'invalid', $again['outcome'] );
		$this->assertArrayHasKey( S::ID_NUMBER, $again['errors'] );
	}

	/**
	 * La descripción se guarda sin guiones aunque quien la escriba tenga HTML sin filtrar.
	 */
	public function test_the_description_is_filtered() {
		$this->as_role( 'administrator' );

		$result = Editor::apply( PostTypes::DESIGN, 0, $this->design_fields( array( 'post_content' => '<p>Bien</p><script>alert(1)</script>' ) ), 'draft', 0 );

		$content = get_post_field( 'post_content', $result['id'] );
		$this->assertStringContainsString( '<p>Bien</p>', $content );
		$this->assertStringNotContainsString( '<script', $content );
	}

	/**
	 * Las taxonomías se guardan como términos, y vaciar la lista los quita.
	 */
	public function test_terms_are_set_and_cleared() {
		$this->as_role( 'fmc_curator' );
		$programme = self::factory()->term->create( array( 'taxonomy' => 'fmc_programme' ) );
		$result    = Editor::apply( PostTypes::DESIGN, 0, $this->design_fields( array( 'tax:fmc_programme' => array( (string) $programme ) ) ), 'draft', 0 );

		$this->assertSame( array( $programme ), Editor::value( get_post( $result['id'] ), 'tax:fmc_programme' ) );

		Editor::apply( PostTypes::DESIGN, $result['id'], $this->design_fields( array( 'tax:fmc_programme' => array( '' ) ) ), 'draft', 0 );
		$this->assertSame( array(), Editor::value( get_post( $result['id'] ), 'tax:fmc_programme' ) );
	}

	/**
	 * La acción lleva el título del diseño, aunque se mande otro; cambia si
	 * cambia el diseño, y la antigua con título propio lo conserva (ADR-0009).
	 */
	public function test_an_action_always_takes_the_title_of_its_design() {
		$this->as_role( 'fmc_adviser' );
		$fields = $this->action_fields( array( 'post_title' => 'Edición de otoño' ) );
		$design = (int) $fields[ A::DESIGN_ID ];

		$own = Editor::apply( PostTypes::ACTION, 0, $fields, '', 0 );
		$this->assertSame( get_the_title( $design ), get_the_title( $own['id'] ) );

		// Una antigua, con el título cambiado a mano: se queda como está.
		wp_update_post(
			array(
				'ID'         => $own['id'],
				'post_title' => 'Título de antes',
			)
		);
		Editor::apply( PostTypes::ACTION, $own['id'], array( A::SUBTITLE => 'Edición de otoño' ) + $fields, '', 0 );
		$this->assertSame( 'Título de antes', get_the_title( $own['id'] ) );
		$this->assertSame( 'Edición de otoño', get_post_meta( $own['id'], A::SUBTITLE, true ) );

		// Con otro diseño, el título de ese diseño.
		$other = self::factory()->post->create(
			array(
				'post_type'  => PostTypes::DESIGN,
				'post_title' => 'Otro diseño',
			)
		);
		Editor::apply( PostTypes::ACTION, $own['id'], array( A::DESIGN_ID => (string) $other ) + $fields, '', 0 );
		$this->assertSame( 'Otro diseño', get_the_title( $own['id'] ) );
	}

	/**
	 * El código del diseño lo pone el aplicativo: el siguiente al mayor, con su prefijo.
	 */
	public function test_the_design_code_is_the_next_free_one() {
		$this->as_role( 'fmc_curator' );
		$old = self::factory()->post->create( array( 'post_type' => PostTypes::DESIGN ) );
		update_post_meta( $old, D::CODE, 'A247' );
		$this->assertSame( 'A248', Editor::next_code() );

		$result = Editor::apply( PostTypes::DESIGN, 0, $this->design_fields( array( D::CODE => 'Z1' ) ), 'draft', 0 );
		$this->assertSame( 'created', $result['outcome'] );
		$this->assertSame( 'A248', get_post_meta( $result['id'], D::CODE, true ), 'el que se teclee no cuenta' );

		Editor::apply( PostTypes::DESIGN, $result['id'], $this->design_fields(), 'draft', 0 );
		$this->assertSame( 'A248', get_post_meta( $result['id'], D::CODE, true ), 'y no cambia al volver a guardar' );
		$this->assertSame( 'A249', Editor::next_code() );
	}

	/**
	 * Tres cifras siempre, y tras el `A999`, el `B001`: la letra manda sobre el número.
	 */
	public function test_the_design_code_moves_to_the_next_letter() {
		$code = static function ( string $value ): void {
			update_post_meta( self::factory()->post->create( array( 'post_type' => PostTypes::DESIGN ) ), D::CODE, $value );
		};
		$this->assertSame( 'A001', Editor::next_code(), 'el primero' );

		$code( 'A7' );
		$code( 'A122B' );
		$this->assertSame( 'A008', Editor::next_code(), 'las variantes con letra detrás no cuentan' );

		$code( 'A999' );
		$this->assertSame( 'B001', Editor::next_code() );

		$code( 'B001' );
		$code( 'a998' );
		$this->assertSame( 'B002', Editor::next_code(), 'un B va después de cualquier A' );
	}

	/**
	 * Las horas de cada parte son obligatorias cuando la modalidad la tiene: una mixta pide las dos.
	 */
	public function test_the_hours_of_each_part_are_required() {
		$this->as_role( 'fmc_curator' );

		$blended = Editor::apply( PostTypes::DESIGN, 0, $this->design_fields( array( D::HOURS_ONLINE => '' ) ), 'draft', 0 );
		$this->assertSame( 'invalid', $blended['outcome'] );
		$this->assertSame( array( D::HOURS_ONLINE => 'Falta rellenar «Horas en línea».' ), $blended['errors'] );

		$online = Editor::apply(
			PostTypes::DESIGN,
			0,
			$this->design_fields(
				array(
					D::MODALITY     => 'online',
					D::HOURS_ONSITE => '',
					D::HOURS_ONLINE => '',
				)
			),
			'draft',
			0
		);
		$this->assertSame( array( D::HOURS_ONLINE ), array_keys( $online['errors'] ), 'en línea no pide las presenciales' );

		$onsite = Editor::apply(
			PostTypes::DESIGN,
			0,
			$this->design_fields(
				array(
					D::MODALITY     => 'onsite',
					D::HOURS_ONLINE => '',
				)
			),
			'draft',
			0
		);
		$this->assertSame( 'created', $onsite['outcome'] );
		$this->assertSame( '10', get_post_meta( $onsite['id'], D::HOURS_ONSITE, true ) );
	}

	/**
	 * Al finalizar un diseño, los que sustituye pasan a histórico; mientras es
	 * borrador siguen en uso, y uno recuperado a mano no vuelve a él (ADR-0016).
	 */
	public function test_finalising_a_design_archives_the_ones_it_replaces() {
		$curator  = $this->as_role( 'fmc_curator' );
		$old      = $this->post( PostTypes::DESIGN, $curator );
		$older    = $this->post( PostTypes::DESIGN, $curator, 'draft' );
		$replaces = array( D::REPLACES => array( (string) $old, (string) $older ) );

		$new = Editor::apply( PostTypes::DESIGN, 0, $this->design_fields( $replaces ), 'draft', 0 );
		$this->assertSame( 'publish', get_post_status( $old ), 'el nuevo aún es un borrador' );

		$fields = $this->design_fields( $replaces );
		Editor::apply( PostTypes::DESIGN, $new['id'], $fields, 'publish', 0 );
		$this->assertSame( PostTypes::ARCHIVED, get_post_status( $old ) );
		$this->assertSame( PostTypes::ARCHIVED, get_post_status( $older ) );

		wp_update_post(
			array(
				'ID'          => $old,
				'post_status' => 'publish',
			)
		);
		Editor::apply( PostTypes::DESIGN, $new['id'], $fields, 'publish', 0 );
		$this->assertSame( 'publish', get_post_status( $old ), 'recuperado a mano, se queda' );

		$third                   = $this->post( PostTypes::DESIGN, $curator );
		$fields[ D::REPLACES ][] = (string) $third;
		Editor::apply( PostTypes::DESIGN, $new['id'], $fields, 'publish', 0 );
		$this->assertSame( PostTypes::ARCHIVED, get_post_status( $third ), 'el que se añade después, sí' );
		$this->assertSame( 'publish', get_post_status( $old ) );
	}

	/**
	 * El servicio de perfeccionamiento resuelve la incidencia, pero no reescribe lo que se pidió.
	 */
	public function test_the_training_service_resolves_an_incident() {
		$adviser  = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$incident = $this->post( PostTypes::INCIDENT, $adviser );
		update_post_meta( $incident, I::REASON, 'Motivo original' );
		$this->as_role( 'fmc_training_service' );

		$result = Editor::apply(
			PostTypes::INCIDENT,
			$incident,
			array(
				I::REASON           => 'Reescrito',
				I::RESOLUTION       => 'approved',
				I::RESOLUTION_NOTES => 'Adelante',
			),
			'',
			0
		);

		$this->assertSame( 'saved', $result['outcome'] );
		$this->assertSame( 'approved', get_post_meta( $incident, I::RESOLUTION, true ) );
		$this->assertSame( 'Adelante', get_post_meta( $incident, I::RESOLUTION_NOTES, true ) );
		$this->assertSame( 'Motivo original', get_post_meta( $incident, I::REASON, true ) );

		// Vuelta a pendiente: las observaciones de la resolución ya no cuentan.
		Editor::apply(
			PostTypes::INCIDENT,
			$incident,
			array(
				I::RESOLUTION       => 'pending',
				I::RESOLUTION_NOTES => 'Adelante',
			),
			'',
			0
		);
		$this->assertSame( '', get_post_meta( $incident, I::RESOLUTION_NOTES, true ) );
	}

	/**
	 * Enviada, la incidencia ya no la cambia quien la abrió: se abre otra.
	 */
	public function test_a_sent_incident_is_final_for_its_author() {
		$adviser  = $this->as_role( 'fmc_adviser' );
		$incident = $this->post( PostTypes::INCIDENT, $adviser );

		$this->assertSame( 'denied', Editor::apply( PostTypes::INCIDENT, $incident, array( I::REASON => 'Otro' ), '', 0 )['outcome'] );
	}

	/**
	 * Una incidencia nueva tiene que colgar de una acción de verdad.
	 */
	public function test_an_incident_needs_an_action_as_parent() {
		$adviser = $this->as_role( 'fmc_adviser' );
		$design  = $this->post( PostTypes::DESIGN, $adviser, 'draft' );
		$fields  = array(
			I::REASON   => 'Motivo',
			I::PROPOSAL => 'Cambio',
		);

		$this->assertSame( 'denied', Editor::apply( PostTypes::INCIDENT, 0, $fields, 'publish', 0 )['outcome'] );
		$this->assertSame( 'denied', Editor::apply( PostTypes::INCIDENT, 0, $fields, 'publish', $design )['outcome'] );
	}

	/**
	 * Tipo desconocido, ficha que no existe o de otro tipo, o nada que se pueda escribir: denegado.
	 */
	public function test_requests_that_are_denied() {
		$admin = $this->as_role( 'administrator' );
		$this->assertSame( 'denied', Editor::apply( 'post', 0, array( 'post_title' => 'x' ), '', 0 )['outcome'] );
		$this->assertSame( 'denied', Editor::apply( PostTypes::DESIGN, 999999, $this->design_fields(), '', 0 )['outcome'] );
		$speaker = $this->post( PostTypes::SPEAKER, $admin );
		$this->assertSame( 'denied', Editor::apply( PostTypes::DESIGN, $speaker, $this->design_fields(), '', 0 )['outcome'] );

		$this->as_role( 'fmc_training_service' );
		$denied = Editor::apply( PostTypes::ACTION, 0, $this->action_fields( array( A::FILE_NUMBER => 'EXP-1' ) ), '', 0 );
		$this->assertSame( 'denied', $denied['outcome'], 'el servicio no crea acciones' );
		$this->assertSame( array(), $denied['values'] );
	}

	/**
	 * La asesoría no edita la acción de otra persona, ni campo a campo.
	 */
	public function test_an_adviser_cannot_touch_someone_elses_action() {
		$other  = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$action = $this->post( PostTypes::ACTION, $other );
		update_post_meta( $action, A::PLACES, 15 );
		$this->as_role( 'fmc_adviser' );

		$this->assertSame( 'denied', Editor::apply( PostTypes::ACTION, $action, $this->action_fields( array( A::PLACES => '99' ) ), '', 0 )['outcome'] );
		$this->assertEquals( 15, get_post_meta( $action, A::PLACES, true ) );
	}

	/**
	 * Los documentos viajan con el guardado del diseño, y lo que no entra se cuenta.
	 */
	public function test_a_design_save_applies_its_documents() {
		$this->as_role( 'fmc_curator' );
		$tmp = wp_tempnam( 'falso.pdf' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fichero de prueba.
		file_put_contents( $tmp, 'texto' );
		$files = array(
			'design' => array(
				array(
					'name'     => 'falso.pdf',
					'type'     => '',
					'tmp_name' => $tmp,
					'error'    => 0,
					'size'     => 5,
				),
			),
		);

		$result = Editor::apply( PostTypes::DESIGN, 0, $this->design_fields(), 'draft', 0, $files );

		$this->assertSame( 'created', $result['outcome'] );
		$this->assertCount( 1, $result['problems'] );
		$this->assertStringContainsString( '«falso.pdf» no es de un formato admitido', $result['problems'][0] );
		$this->assertSame( array(), Documents::of( $result['id'] )['design'] );
	}

	/**
	 * Install a redirect catcher: the redirect is thrown instead of followed.
	 */
	private function catch_redirects(): void {
		add_filter(
			'wp_redirect',
			static function ( $location ) {
				throw new RuntimeException( esc_url_raw( $location ) );
			}
		);
	}

	/**
	 * Fake the POST of the edit screen.
	 *
	 * @param array<string, mixed> $post  POST fields.
	 * @param string               $nonce Nonce; the right one when null.
	 */
	private function submit( array $post, ?string $nonce = null ): void {
		$post['_fmc_nonce'] = $nonce ?? wp_create_nonce( Editor::nonce_action( $post['fmc_type'], (int) $post['fmc_id'] ) );
		$_POST              = wp_slash( $post );
		$_REQUEST           = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- se finge la petición.
	}

	/**
	 * Run save() and return where it redirected.
	 *
	 * @return string
	 */
	private function redirect_of_save(): string {
		try {
			Editor::save();
		} catch ( RuntimeException $redirect ) {
			return $redirect->getMessage();
		}
		$this->fail( 'save() no ha redirigido' );
	}

	/**
	 * Guardar redirige a la ficha guardada, con su aviso.
	 */
	public function test_save_redirects_to_the_new_record() {
		$this->as_role( 'fmc_adviser' );
		$this->catch_redirects();
		$this->submit(
			array(
				'fmc_type' => PostTypes::SPEAKER,
				'fmc_id'   => '0',
				'f'        => array(
					S::FIRST_NAME => 'Luis',
					S::LAST_NAME  => "O'Neill",
					S::EMAIL      => 'luis@example.org',
				),
			)
		);

		wp_parse_str( (string) wp_parse_url( $this->redirect_of_save(), PHP_URL_QUERY ), $args );

		$this->assertSame( 'created', $args['fmc_notice'] );
		$this->assertSame( PostTypes::SPEAKER, get_post_type( (int) $args['fmc_edit'] ) );
		$this->assertSame( "O'Neill, Luis", get_post_field( 'post_title', (int) $args['fmc_edit'] ), 'las comillas llegan sin barras' );
	}

	/**
	 * Un tipo desconocido o algo que no se puede escribir vuelve con «denegado».
	 */
	public function test_save_denies_unknown_types_and_forbidden_writes() {
		$this->as_role( 'fmc_training_service' );
		$this->catch_redirects();

		$this->submit(
			array(
				'fmc_type' => 'page',
				'fmc_id'   => '0',
			)
		);
		$this->assertStringContainsString( 'fmc_notice=denied', $this->redirect_of_save() );

		$this->submit(
			array(
				'fmc_type' => PostTypes::SPEAKER,
				'fmc_id'   => '0',
				'f'        => array( S::FIRST_NAME => 'Luis' ),
			)
		);
		$this->assertStringContainsString( 'fmc_notice=denied', $this->redirect_of_save() );
		$this->assertSame( 0, $this->count_of( PostTypes::SPEAKER ) );
	}

	/**
	 * Sin el nonce de esa ficha no se guarda nada.
	 */
	public function test_save_needs_the_nonce_of_that_record() {
		$this->as_role( 'fmc_adviser' );
		$this->submit(
			array(
				'fmc_type' => PostTypes::SPEAKER,
				'fmc_id'   => '0',
				'f'        => array(
					S::FIRST_NAME => 'Luis',
					S::LAST_NAME  => 'Gómez',
					S::EMAIL      => 'luis@example.org',
				),
			),
			wp_create_nonce( Editor::nonce_action( PostTypes::SPEAKER, 5 ) )
		);

		try {
			Editor::save();
			$this->fail( 'se ha guardado sin nonce válido' );
		} catch ( WPDieException $e ) {
			$this->assertSame( 0, $this->count_of( PostTypes::SPEAKER ) );
		}
	}

	/**
	 * Una acción solo se agenda con un diseño finalizado, aunque el formulario
	 * llegue con otro; la que ya tenía uno histórico lo conserva (ADR-0016).
	 */
	public function test_an_action_needs_a_finalised_design() {
		$curator = self::factory()->user->create( array( 'role' => 'fmc_curator' ) );
		$draft   = $this->post( PostTypes::DESIGN, $curator, 'draft' );
		$old     = $this->post( PostTypes::DESIGN, $curator, PostTypes::ARCHIVED );
		$adviser = $this->as_role( 'fmc_adviser' );

		$result = Editor::apply( PostTypes::ACTION, 0, $this->action_fields( array( A::DESIGN_ID => (string) $draft ) ), '', 0 );
		$this->assertSame( 'invalid', $result['outcome'] );
		$this->assertStringContainsString( 'no está finalizado', $result['errors'][ A::DESIGN_ID ] );
		$this->assertSame( 0, $this->count_of( PostTypes::ACTION ) );
		$this->assertSame( 'invalid', Editor::apply( PostTypes::ACTION, 0, $this->action_fields( array( A::DESIGN_ID => (string) $this->post( PostTypes::SPEAKER, $adviser ) ) ), '', 0 )['outcome'], 'ni otra cosa que un diseño' );

		$action = $this->post( PostTypes::ACTION, $adviser, 'draft' );
		update_post_meta( $action, A::DESIGN_ID, $old );
		$this->assertSame( 'saved', Editor::apply( PostTypes::ACTION, $action, $this->action_fields( array( A::DESIGN_ID => (string) $old ) ), '', 0 )['outcome'] );
		$this->assertSame( 'invalid', Editor::apply( PostTypes::ACTION, $action, $this->action_fields( array( A::DESIGN_ID => (string) $draft ) ), '', 0 )['outcome'], 'cambiarlo a un borrador, no' );
	}

	/**
	 * «Recomendado para» guarda solo diseños finalizados, más los que ya tenía;
	 * lo demás que llegue en el formulario se descarta.
	 */
	public function test_recommended_designs_are_checked_on_save() {
		$curator = self::factory()->user->create( array( 'role' => 'fmc_curator' ) );
		$final   = $this->post( PostTypes::DESIGN, $curator );
		$draft   = $this->post( PostTypes::DESIGN, $curator, 'draft' );
		$old     = $this->post( PostTypes::DESIGN, $curator, PostTypes::ARCHIVED );
		$adviser = $this->as_role( 'fmc_adviser' );
		$speaker = $this->post( PostTypes::SPEAKER, $adviser );
		update_post_meta( $speaker, S::FIRST_NAME, 'Ana' );
		update_post_meta( $speaker, S::LAST_NAME, 'Pérez' );
		update_post_meta( $speaker, S::EMAIL, 'ana@example.org' );
		update_post_meta( $speaker, S::RECOMMENDED, array( $old ) );
		$other = $this->post( PostTypes::ACTION, $adviser );

		$fields = array(
			S::FIRST_NAME  => 'Ana',
			S::LAST_NAME   => 'Pérez',
			S::EMAIL       => 'ana@example.org',
			S::RECOMMENDED => array( (string) $final, (string) $draft, (string) $old, (string) $other ),
		);
		$this->assertSame( 'saved', Editor::apply( PostTypes::SPEAKER, $speaker, $fields, '', 0 )['outcome'] );
		$this->assertSame( array( $final, $old ), array_map( 'intval', (array) get_post_meta( $speaker, S::RECOMMENDED, true ) ) );
	}

	/**
	 * El número de un diseño borrado para siempre no vuelve a darse.
	 */
	public function test_a_deleted_design_code_is_not_given_again() {
		$this->as_role( 'fmc_curator' );
		$first = Editor::apply( PostTypes::DESIGN, 0, $this->design_fields(), 'draft', 0 );
		$this->assertSame( 'A001', get_post_meta( $first['id'], D::CODE, true ) );

		wp_delete_post( $first['id'], true );

		$this->assertSame( 'A002', Editor::next_code() );
		$second = Editor::apply( PostTypes::DESIGN, 0, $this->design_fields(), 'draft', 0 );
		$this->assertSame( 'A002', get_post_meta( $second['id'], D::CODE, true ) );
	}

	/**
	 * Un diseño nuevo puede llamarse como el que sustituye, y el histórico se
	 * sigue guardando aunque su sucesor lleve su título. Lo que pasa a
	 * histórico no se completa: no pide lo obligatorio.
	 */
	public function test_the_archive_does_not_compete_for_titles_nor_requires_fields() {
		$curator = $this->as_role( 'fmc_curator' );
		$old     = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::DESIGN,
				'post_title'  => 'Robótica',
				'post_author' => $curator,
			)
		);

		$fields = $this->design_fields(
			array(
				'post_title' => 'Robótica',
				D::REPLACES  => array( (string) $old ),
			)
		);
		$new    = Editor::apply( PostTypes::DESIGN, 0, $fields, 'publish', 0 );
		$this->assertSame( 'created', $new['outcome'], 'el que sustituye puede llevar su título' );
		$this->assertSame( PostTypes::ARCHIVED, get_post_status( $old ) );

		$this->assertSame( 'saved', Editor::apply( PostTypes::DESIGN, $old, $this->design_fields( array( 'post_title' => 'Robótica' ) ), PostTypes::ARCHIVED, 0 )['outcome'] );
		$this->assertSame( 'invalid', Editor::apply( PostTypes::DESIGN, 0, $this->design_fields( array( 'post_title' => 'Robótica' ) ), 'draft', 0 )['outcome'], 'otro que no lo sustituye, no' );

		$incomplete = $this->post( PostTypes::DESIGN, $curator );
		$this->assertSame( 'saved', Editor::apply( PostTypes::DESIGN, $incomplete, array( 'post_title' => 'Sin descripción' ), PostTypes::ARCHIVED, 0 )['outcome'] );
		$this->assertSame( PostTypes::ARCHIVED, get_post_status( $incomplete ) );
	}

	/**
	 * Cero horas no son horas: una mixta pide las dos de verdad.
	 */
	public function test_zero_hours_count_as_missing() {
		$this->as_role( 'fmc_curator' );

		$result = Editor::apply( PostTypes::DESIGN, 0, $this->design_fields( array( D::HOURS_ONSITE => '0' ) ), 'draft', 0 );

		$this->assertSame( array( D::HOURS_ONSITE => 'Falta rellenar «Horas presenciales».' ), $result['errors'] );
	}

	/**
	 * Cada diseño sustituido pasa a histórico una sola vez: recuperado a mano,
	 * no vuelve a él aunque el nuevo se devuelva a borrador y se finalice otra vez.
	 */
	public function test_a_replaced_design_is_archived_once() {
		$curator = $this->as_role( 'fmc_curator' );
		$old     = $this->post( PostTypes::DESIGN, $curator );
		$fields  = $this->design_fields( array( D::REPLACES => array( (string) $old ) ) );
		$new     = Editor::apply( PostTypes::DESIGN, 0, $fields, 'publish', 0 );
		$this->assertSame( PostTypes::ARCHIVED, get_post_status( $old ) );

		Editor::apply( PostTypes::DESIGN, $old, $this->design_fields(), 'publish', 0 );
		Editor::apply( PostTypes::DESIGN, $new['id'], $fields, 'draft', 0 );
		Editor::apply( PostTypes::DESIGN, $new['id'], $fields, 'publish', 0 );
		$this->assertSame( 'publish', get_post_status( $old ) );
		$this->assertSame( array( $old ), get_post_meta( $new['id'], Editor::ARCHIVED_META, true ) );
	}

	/**
	 * Sacar un diseño del histórico es de quien finaliza.
	 */
	public function test_only_who_finalises_takes_a_design_out_of_the_archive() {
		$old = get_post( $this->post( PostTypes::DESIGN, 1, PostTypes::ARCHIVED ) );

		$this->as_role( 'fmc_adviser' );
		$this->assertSame( PostTypes::ARCHIVED, Editor::next_status( PostTypes::DESIGN, $old, 'draft' ) );
		$this->assertSame( PostTypes::ARCHIVED, Editor::next_status( PostTypes::DESIGN, $old, 'pending' ) );

		$this->as_role( 'fmc_curator' );
		$this->assertSame( 'draft', Editor::next_status( PostTypes::DESIGN, $old, 'draft' ) );
	}
}

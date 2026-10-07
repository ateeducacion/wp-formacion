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
				A::PLACES       => '20',
				A::START        => '2026-10-01',
			),
			$over
		);
	}

	/**
	 * La asesoría guarda en borrador o manda a revisión, pero no publica ni a mano.
	 */
	public function test_an_adviser_drafts_and_sends_to_review_but_never_publishes() {
		$this->as_role( 'fmc_adviser' );

		$draft = Editor::apply( PostTypes::DESIGN, 0, $this->design_fields(), 'draft', 0 );
		$this->assertSame( 'created', $draft['outcome'] );
		$this->assertSame( 'draft', get_post_status( $draft['id'] ) );

		$this->assertSame( 'saved', Editor::apply( PostTypes::DESIGN, $draft['id'], $this->design_fields(), 'pending', 0 )['outcome'] );
		$this->assertSame( 'pending', get_post_status( $draft['id'] ) );

		Editor::apply( PostTypes::DESIGN, $draft['id'], $this->design_fields(), 'publish', 0 );
		$this->assertSame( 'pending', get_post_status( $draft['id'] ), 'pulsar «finalizar» a mano no lo publica' );

		$new = Editor::apply( PostTypes::DESIGN, 0, $this->design_fields(), 'publish', 0 );
		$this->assertSame( 'draft', get_post_status( $new['id'] ), 'uno nuevo se queda en borrador' );
	}

	/**
	 * La curaduría finaliza y devuelve a borrador; un estado inventado no cambia nada.
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
		$this->as_role( 'fmc_adviser' );

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
		$this->assertSame( 'Falta rellenar «Título del curso».', $result['errors']['post_title'] );
		$this->assertSame( 'Falta rellenar «Tipo de formación».', $result['errors'][ D::TYPE ] );
		$this->assertArrayNotHasKey( D::MODALITY, $result['errors'] );
		$this->assertSame( 0, $result['values']['_parent'] );
		$this->assertSame( '10', $result['values'][ D::HOURS_ONSITE ], 'lo tecleado vuelve para no perderlo' );
		$this->assertSame( 0, $this->count_of( PostTypes::DESIGN ) );
	}

	/**
	 * Mínimos y máximos de la acción: cero plazas o diez desdobles no valen.
	 */
	public function test_action_numbers_have_bounds() {
		$this->as_role( 'fmc_adviser' );

		$result = Editor::apply(
			PostTypes::ACTION,
			0,
			$this->action_fields(
				array(
					A::PLACES   => '0',
					A::REPLICAS => '11',
				)
			),
			'',
			0
		);

		$this->assertSame( 'invalid', $result['outcome'] );
		$this->assertSame( '«Plazas» tiene que estar entre 1 y 10000.', $result['errors'][ A::PLACES ] );
		$this->assertSame( '«Desdoblar» tiene que estar entre 1 y 10.', $result['errors'][ A::REPLICAS ] );

		$ok = Editor::apply( PostTypes::ACTION, 0, $this->action_fields( array( A::REPLICAS => '10' ) ), '', 0 );
		$this->assertSame( 'created', $ok['outcome'] );
		$this->assertEquals( 10, get_post_meta( $ok['id'], A::REPLICAS, true ) );
	}

	/**
	 * Las horas cuentan según la modalidad: en una acción presencial, las de en línea se vacían.
	 */
	public function test_hours_follow_the_modality() {
		$this->as_role( 'fmc_adviser' );

		$result = Editor::apply(
			PostTypes::ACTION,
			0,
			$this->action_fields(
				array(
					A::MODALITY     => 'onsite',
					A::HOURS_ONSITE => '12',
					A::HOURS_ONLINE => '999',
				)
			),
			'',
			0
		);

		$this->assertSame( 'created', $result['outcome'], implode( ' ', $result['errors'] ) );
		$this->assertEquals( 12, get_post_meta( $result['id'], A::HOURS_ONSITE, true ) );
		$this->assertFalse( metadata_exists( 'post', $result['id'], A::HOURS_ONLINE ), 'oculto no cuenta, ni para el máximo' );
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
					A::TRAINING_PLANS => array( '', 'seminar', 'inventado' ),
					A::TRAINING_FILE  => 'EF-7',
				)
			),
			'',
			0
		);
		$this->assertSame( array( 'seminar' ), get_post_meta( $with['id'], A::TRAINING_PLANS, true ), 'el vacío y lo que no está en la lista se van' );
		$this->assertSame( 'EF-7', get_post_meta( $with['id'], A::TRAINING_FILE, true ) );

		Editor::apply(
			PostTypes::ACTION,
			$with['id'],
			$this->action_fields(
				array(
					A::TRAINING_PLANS => array( 'itinerary' ),
					A::TRAINING_FILE  => 'EF-7',
				)
			),
			'',
			0
		);
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
	 * Sin título, la acción toma el del diseño; con título propio, se queda el suyo.
	 */
	public function test_an_action_takes_its_title_from_the_design_only_when_blank() {
		$this->as_role( 'fmc_adviser' );
		$fields = $this->action_fields( array( 'post_title' => 'Edición de otoño' ) );

		$own = Editor::apply( PostTypes::ACTION, 0, $fields, '', 0 );
		$this->assertSame( 'Edición de otoño', get_the_title( $own['id'] ) );

		Editor::apply( PostTypes::ACTION, $own['id'], array( 'post_title' => '  ' ) + $fields, '', 0 );
		$this->assertSame( get_the_title( (int) $fields[ A::DESIGN_ID ] ), get_the_title( $own['id'] ) );
	}

	/**
	 * El servicio de formación resuelve la incidencia, pero no reescribe lo que se pidió.
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
		$this->as_role( 'fmc_adviser' );
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
}

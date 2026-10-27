<?php
/**
 * Tests for delivering an action and for what an incident does to it.
 *
 * @package Fmc
 */

use Fmc\Meta\ActionMetaKeys as A;
use Fmc\Meta\IncidentMetaKeys as I;
use Fmc\Meta\MetaRegistration;
use Fmc\PostType\PostTypes;
use Fmc\PublicFront\Editor;
use Fmc\PublicFront\Speakers;

/**
 * La acción se guarda o se entrega; entregada, los cambios se piden con una
 * incidencia, que la aplaza o la cancela y avisa por correo (ADR-0020).
 */
class Test_Incidents extends WP_UnitTestCase {

	/**
	 * Roles, caps and meta in place.
	 */
	public function set_up() {
		parent::set_up();
		fmc_register_roles();
		PostTypes::grant_caps_to_roles();
		MetaRegistration::register_meta();
		reset_phpmailer_instance();
	}

	/**
	 * Undo the filters.
	 */
	public function tear_down() {
		remove_all_filters( 'fmc_incident_recipients' );
		remove_all_filters( 'wp_mail_from' );
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
	 * An action of an author.
	 *
	 * @param int                  $author Author.
	 * @param string               $status Status.
	 * @param array<string, mixed> $meta   Meta.
	 * @return int
	 */
	private function action( int $author, string $status = 'publish', array $meta = array() ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::ACTION,
				'post_status' => $status,
				'post_author' => $author,
				'post_title'  => 'Robótica',
			)
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		return $id;
	}

	/**
	 * Send an incident on an action.
	 *
	 * @param int    $action  Action.
	 * @param string $request What it asks for.
	 * @param string $start   New start, when postponing.
	 * @return array<string, mixed>
	 */
	private function incident( int $action, string $request, string $start = '' ): array {
		return Editor::apply(
			PostTypes::INCIDENT,
			0,
			array(
				I::REQUEST   => $request,
				I::NEW_START => $start,
				I::REASON    => 'El centro cierra',
				I::PROPOSAL  => 'Moverla',
			),
			'publish',
			$action
		);
	}

	/**
	 * La asesoría guarda en borrador o entrega; entregada no la vuelve a
	 * borrador ni la edita, y la validación sí.
	 */
	public function test_an_action_is_saved_as_draft_or_delivered() {
		$adviser = $this->as_role( 'fmc_adviser' );
		$draft   = get_post( $this->action( $adviser, 'draft' ) );

		$this->assertSame( 'draft', Editor::next_status( PostTypes::ACTION, null, '' ), 'una nueva, en borrador' );
		$this->assertSame( 'draft', Editor::next_status( PostTypes::ACTION, $draft, 'draft' ) );
		$this->assertSame( 'publish', Editor::next_status( PostTypes::ACTION, $draft, 'publish' ) );
		$this->assertSame( 'draft', Editor::next_status( PostTypes::ACTION, $draft, '' ) );

		$delivered = get_post( $this->action( $adviser ) );
		$this->assertSame( 'publish', Editor::next_status( PostTypes::ACTION, $delivered, 'draft' ), 'lo entregado no vuelve a borrador' );
		$this->assertFalse( current_user_can( 'edit_post', $delivered->ID ) );

		$this->as_role( 'fmc_curator' );
		$this->assertSame( 'draft', Editor::next_status( PostTypes::ACTION, $delivered, 'draft' ) );
		$this->assertTrue( current_user_can( 'edit_post', $delivered->ID ) );
	}

	/**
	 * Abre incidencias quien entregó la acción o quien la puede editar; ni
	 * otra asesoría ni el servicio.
	 */
	public function test_who_opens_an_incident() {
		$owner  = $this->as_role( 'fmc_adviser' );
		$action = $this->action( $owner );
		$this->assertTrue( Editor::can_open_incident( $action ) );
		$this->assertFalse( Editor::can_open_incident( self::factory()->post->create() ), 'solo sobre una acción' );

		$this->as_role( 'fmc_adviser' );
		$this->assertFalse( Editor::can_open_incident( $action ) );
		$this->as_role( 'fmc_training_service' );
		$this->assertFalse( Editor::can_open_incident( $action ) );
		$this->as_role( 'fmc_curator' );
		$this->assertTrue( Editor::can_open_incident( $action ) );
	}

	/**
	 * Pedir aplazarla o cancelarla la deja así al enviarla; un cambio, no. Lo
	 * que se pide es obligatorio.
	 */
	public function test_the_request_sets_the_situation() {
		$owner     = $this->as_role( 'fmc_adviser' );
		$postponed = $this->action( $owner, 'publish', array( A::START => '2020-03-01' ) );
		$cancelled = $this->action( $owner, 'publish', array( A::START => '2099-03-01' ) );
		$changed   = $this->action( $owner, 'publish', array( A::START => '2020-03-01' ) );

		$this->assertSame( 'created', $this->incident( $postponed, 'postpone', '2027-01-10' )['outcome'] );
		$this->assertSame( 'created', $this->incident( $cancelled, 'cancel' )['outcome'] );
		$this->assertSame( 'created', $this->incident( $changed, 'change' )['outcome'] );
		$this->assertArrayHasKey( I::REQUEST, $this->incident( $changed, '' )['errors'] );

		$this->assertSame( 'postponed', A::situation( $postponed ) );
		$this->assertSame( 'cancelled', A::situation( $cancelled ) );
		$this->assertSame( 'done', A::situation( $changed ) );
	}

	/**
	 * Con las fechas nuevas, la aplazada vuelve a ir por sus fechas.
	 */
	public function test_a_postponed_action_follows_its_new_dates() {
		$owner  = $this->as_role( 'fmc_adviser' );
		$action = $this->action( $owner, 'publish', array( A::START => '2020-03-01' ) );
		$this->incident( $action, 'postpone' );
		$this->assertSame( 'postponed', A::situation( $action ) );

		$this->as_role( 'fmc_curator' );
		$fields = array(
			A::DESIGN_ID    => (string) self::factory()->post->create(
				array(
					'post_type'   => PostTypes::DESIGN,
					'post_status' => 'publish',
				)
			),
			'tax:fmc_scope' => array( (string) self::factory()->term->create( array( 'taxonomy' => 'fmc_scope' ) ) ),
			A::PLACES       => '20',
			A::START        => '2020-03-01',
		);
		Editor::apply( PostTypes::ACTION, $action, $fields, '', 0 );
		$this->assertSame( 'postponed', A::situation( $action ), 'guardarla sin cambiar la fecha no la saca' );

		$fields[ A::START ] = '2099-01-10';
		Editor::apply( PostTypes::ACTION, $action, $fields, '', 0 );
		$this->assertSame( '', A::situation( $action ), 'con la fecha nueva, aún no ha empezado' );
	}

	/**
	 * Al abrirla se manda un correo a quien dice el despliegue; sin nadie, no.
	 */
	public function test_a_new_incident_is_mailed_to_who_follows_them() {
		$owner  = $this->as_role( 'fmc_adviser' );
		$action = $this->action( $owner, 'publish', array( A::FILE_NUMBER => 'EXP-7' ) );

		$this->incident( $action, 'cancel' );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent(), 'sin destinatarios, nada' );

		add_filter( 'fmc_incident_recipients', static fn(): array => array( 'equipo@example.org', 'no es un correo' ) );
		// En los tests el sitio es `localhost`, y `wordpress@localhost` no es una dirección válida.
		add_filter( 'wp_mail_from', static fn(): string => 'aplicativo@example.org' );
		$this->assertSame( 'created', $this->incident( $action, 'postpone', '2027-01-10' )['outcome'] );
		$mail = tests_retrieve_phpmailer_instance()->get_sent();

		$this->assertSame( 'equipo@example.org', $mail->to[0][0] );
		$this->assertCount( 1, $mail->to );
		$this->assertSame( 'Incidencia: Robótica', $mail->subject );
		$this->assertStringContainsString( 'Expediente: EXP-7', $mail->body );
		$this->assertStringContainsString( 'Qué se pide: Aplazarla', $mail->body );
		$this->assertStringContainsString( 'Nuevas fechas: 10/01/2027', $mail->body );
		$this->assertStringContainsString( 'El centro cierra', $mail->body );
	}

	/**
	 * Una cancelada no cuenta horas: ni en la ficha ni en lo impartido del ponente.
	 */
	public function test_a_cancelled_action_counts_no_hours() {
		$owner   = $this->as_role( 'fmc_curator' );
		$speaker = self::factory()->post->create( array( 'post_type' => PostTypes::SPEAKER ) );
		$action  = $this->action(
			$owner,
			'publish',
			array(
				A::START        => '2020-03-01',
				A::HOURS_ONSITE => 10,
				A::SPEAKERS     => array( $speaker ),
			)
		);
		$this->assertSame( 10.0, Speakers::stats()[ $speaker ]['hours'] );

		$this->incident( $action, 'cancel' );

		$this->assertArrayNotHasKey( $speaker, Speakers::stats() );
		$this->assertStringContainsString( '<strong>Cancelada</strong><span>Horas totales</span>', Editor::screen( $action, '' )['body'] );
	}

	/**
	 * El expediente lo escribe el servicio de formación, y la validación ya no;
	 * en un sitio ya desplegado se le quita.
	 */
	public function test_only_the_service_writes_the_file_number() {
		get_role( 'fmc_curator' )->add_cap( PostTypes::CAP_SERVICE_FIELDS );
		PostTypes::grant_caps_to_roles();

		$this->as_role( 'fmc_curator' );
		$this->assertFalse( Editor::can_write( PostTypes::ACTION, A::FILE_NUMBER, $this->action( get_current_user_id() ) ) );
		$this->assertTrue( current_user_can( PostTypes::CAP_RESOLVE ), 'sigue resolviendo incidencias' );

		$this->as_role( 'fmc_training_service' );
		$this->assertTrue( Editor::can_write( PostTypes::ACTION, A::FILE_NUMBER, $this->action( 1 ) ) );
	}
}

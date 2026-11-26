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
use Fmc\PublicFront\Lists;
use Fmc\PublicFront\Screen;
use Fmc\PublicFront\Speakers;

/**
 * La acción se guarda o se entrega; entregada, los cambios se dejan en una
 * incidencia, que la cancela, la mueve de fechas o la aplaza, y avisa por
 * correo. Nadie la aprueba (ADR-0020, ADR-0029).
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
		$_GET = array();
		remove_all_filters( 'fmc_incident_recipients' );
		remove_all_filters( 'fmc_incident_reminder' );
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
	 * @param string $start   New start, when changing the dates.
	 * @param string $end     New end.
	 * @return array<string, mixed>
	 */
	private function incident( int $action, string $request, string $start = '', string $end = '' ): array {
		return Editor::apply(
			PostTypes::INCIDENT,
			0,
			array(
				I::REQUEST   => $request,
				I::NEW_START => $start,
				I::NEW_END   => $end,
				I::REASON    => 'El centro cierra',
			),
			'publish',
			$action
		);
	}

	/**
	 * La asesoría guarda en borrador o entrega; entregada no la vuelve a
	 * borrador ni la edita, y la coordinación sí.
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
	 * Cancelarla la deja cancelada al enviarla; cambiar las fechas sin decir
	 * cuáles, aplazada; un cambio de ponente u otro, solo apuntado. Lo que se
	 * pide es obligatorio, y con la justificación basta.
	 */
	public function test_the_request_sets_the_situation() {
		$owner     = $this->as_role( 'fmc_adviser' );
		$postponed = $this->action( $owner, 'publish', array( A::START => '2020-03-01' ) );
		$cancelled = $this->action( $owner, 'publish', array( A::START => '2099-03-01' ) );
		$changed   = $this->action( $owner, 'publish', array( A::START => '2020-03-01' ) );

		$this->assertSame( 'created', $this->incident( $postponed, 'postpone' )['outcome'] );
		$this->assertSame( 'created', $this->incident( $cancelled, 'cancel' )['outcome'] );
		$this->assertSame( 'created', $this->incident( $changed, 'speaker' )['outcome'] );
		$this->assertSame( 'created', $this->incident( $changed, 'change' )['outcome'] );
		$this->assertArrayHasKey( I::REQUEST, $this->incident( $changed, '' )['errors'] );
		$this->assertSame( array( 'postpone', 'speaker', 'cancel', 'change' ), array_keys( I::KINDS ) );

		$this->assertSame( 'postponed', A::situation( $postponed ) );
		$this->assertSame( '2020-03-01', get_post_meta( $postponed, A::START, true ), 'sin fecha nueva, las suyas' );
		$this->assertSame( 'cancelled', A::situation( $cancelled ) );
		$this->assertSame( 'done', A::situation( $changed ) );

		// Sin justificación no se envía.
		$bare = Editor::apply( PostTypes::INCIDENT, 0, array( I::REQUEST => 'speaker' ), 'publish', $changed );
		$this->assertSame( array( I::REASON => 'Falta rellenar «Justificación».' ), $bare['errors'] );
	}

	/**
	 * Un cambio de fechas con la fecha nueva mueve la acción al enviarla: sus
	 * fechas, y sus sesiones los mismos días. La incidencia se queda con las
	 * que tenía, que es lo que deja constancia.
	 */
	public function test_a_change_of_dates_moves_the_action_and_its_sessions() {
		$owner    = $this->as_role( 'fmc_adviser' );
		$sessions = array(
			array(
				'day'  => '2026-03-03',
				'from' => '16:00',
				'to'   => '19:00',
			),
			array(
				'day'  => '2026-03-10',
				'from' => '16:00',
				'to'   => '20:00',
			),
		);
		$meta     = array(
			A::START    => '2026-03-03',
			A::END      => '2026-03-10',
			A::SESSIONS => $sessions,
		);

		// Solo la fecha de inicio: dura lo que duraba, cinco semanas después.
		$action = $this->action( $owner, 'publish', $meta );
		$moved  = $this->incident( $action, 'postpone', '2026-04-07' );
		$this->assertSame( 'created', $moved['outcome'] );
		$this->assertSame( '2026-04-07', get_post_meta( $action, A::START, true ) );
		$this->assertSame( '2026-04-14', get_post_meta( $action, A::END, true ) );
		$this->assertSame(
			array(
				array(
					'day'  => '2026-04-07',
					'from' => '16:00',
					'to'   => '19:00',
				),
				array(
					'day'  => '2026-04-14',
					'from' => '16:00',
					'to'   => '20:00',
				),
			),
			get_post_meta( $action, A::SESSIONS, true )
		);
		$this->assertSame( '2026-03-03', get_post_meta( $moved['id'], I::OLD_START, true ) );
		$this->assertSame( '2026-03-10', get_post_meta( $moved['id'], I::OLD_END, true ) );
		$this->assertNotSame( 'postponed', get_post_meta( $action, A::SITUATION, true ), 'no queda aplazada: tiene fechas' );

		// Antes en el calendario también vale, y con su fecha de fin.
		$earlier = $this->action( $owner, 'publish', $meta );
		$this->incident( $earlier, 'postpone', '2026-02-24', '2026-03-05' );
		$this->assertSame( '2026-02-24', get_post_meta( $earlier, A::START, true ) );
		$this->assertSame( '2026-03-05', get_post_meta( $earlier, A::END, true ) );
		$this->assertSame( array( '2026-02-24', '2026-03-03' ), wp_list_pluck( get_post_meta( $earlier, A::SESSIONS, true ), 'day' ) );

		// Las sesiones que no caben en las fechas nuevas se quitan: no pueden
		// quedarse fuera de las fechas de la acción.
		$short = $this->action( $owner, 'publish', $meta );
		$this->incident( $short, 'postpone', '2026-04-07', '2026-04-08' );
		$this->assertSame( '2026-04-08', get_post_meta( $short, A::END, true ) );
		$this->assertSame( '', get_post_meta( $short, A::SESSIONS, true ) );

		// Una que estaba aplazada sin fecha vuelve a ir por sus fechas.
		$waiting = $this->action( $owner, 'publish', array( A::START => '2020-03-01' ) );
		$this->incident( $waiting, 'postpone' );
		$this->assertSame( 'postponed', A::situation( $waiting ) );
		$this->incident( $waiting, 'postpone', '2099-01-10' );
		$this->assertSame( '', A::situation( $waiting ) );
		$this->assertSame( '', get_post_meta( $waiting, A::END, true ), 'sin fecha de fin sigue sin ella' );

		// Una cancelada no vuelve a la vida por cambiarle las fechas.
		$gone = $this->action( $owner, 'publish', $meta );
		$this->incident( $gone, 'cancel' );
		$this->incident( $gone, 'postpone', '2099-01-10' );
		$this->assertSame( 'cancelled', A::situation( $gone ) );

		// La fecha de fin, con la de inicio y no antes que ella.
		$this->assertSame( array( I::NEW_END ), array_keys( $this->incident( $action, 'postpone', '2026-05-10', '2026-05-01' )['errors'] ) );
		$this->assertSame( array( I::NEW_START ), array_keys( $this->incident( $action, 'postpone', '', '2026-05-01' )['errors'] ) );
		$this->assertSame( '2026-04-07', get_post_meta( $action, A::START, true ), 'una incidencia que no se envía no mueve nada' );
		// Las fechas solo cuentan al cambiar las fechas.
		$this->incident( $action, 'speaker', '2030-01-01' );
		$this->assertSame( '2026-04-07', get_post_meta( $action, A::START, true ) );
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
		$this->assertSame( 'Incidencia (cambio de fechas): Robótica', $mail->subject );
		$this->assertStringContainsString( 'Expediente de perfeccionamiento: EXP-7', $mail->body );
		$this->assertStringNotContainsString( 'Expediente interno:', $mail->body, 'lo que no tiene no se escribe' );
		$this->assertStringContainsString( 'Tipo: Cambio de fechas', $mail->body );
		$this->assertStringContainsString( 'Fechas nuevas: 10/01/2027', $mail->body );
		$this->assertMatchesRegularExpression( '/Justificación:\R+El centro cierra/u', $mail->body );
		$this->assertStringNotContainsString( 'Coste', $mail->body );
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
	 * El expediente lo escribe el servicio de perfeccionamiento, y la coordinación ya no;
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

	/**
	 * The document of a list screen, where the chrome is.
	 *
	 * @return string
	 */
	private static function chrome(): string {
		return Screen::document(
			array(
				'tab'   => 'incidents',
				'title' => 'Incidencias',
				'body'  => '',
			)
		);
	}

	/**
	 * La lista de incidencias las trae todas, se filtra por lo que piden y las
	 * cuenta por clase. Las de antes, que no lo dicen, salen solo en el total.
	 */
	public function test_the_incident_list_filters_by_what_is_asked() {
		$this->as_role( 'fmc_curator' );
		$action = $this->action( get_current_user_id(), 'publish', array( A::START => '2026-03-03' ) );
		$cancel = $this->incident( $action, 'cancel' )['id'];
		$moved  = $this->incident( $action, 'postpone', '2026-04-07' )['id'];
		$old    = $this->incident( $action, 'change' )['id'];
		delete_post_meta( $old, I::REQUEST );
		update_post_meta( $old, I::RESOLUTION, 'approved' );
		$ids = static fn(): array => wp_list_pluck( ( new WP_Query( Lists::query_args( PostTypes::INCIDENT ) ) )->posts, 'ID' );

		$this->assertEqualsCanonicalizing( array( $cancel, $moved, $old ), $ids(), 'sin filtro, todas: ya no hay pendientes' );
		$_GET['fmc_request'] = 'cancel';
		$this->assertSame( array( $cancel ), $ids() );
		$_GET['fmc_request'] = 'inventado';
		$this->assertCount( 3, $ids() );
		unset( $_GET['fmc_request'] );

		$this->assertSame(
			array(
				'En total'          => 3,
				'Cambio de fechas'  => 1,
				'Cambio de ponente' => 0,
				'Cancelación'       => 1,
				'Otro cambio'       => 0,
			),
			array_column( Lists::counters( PostTypes::INCIDENT ), 'n', 'label' )
		);

		$body = Lists::screen( 'incidents', PostTypes::INCIDENT )['body'];
		$this->assertStringContainsString( '<th scope="col">Tipo</th>', $body );
		$this->assertStringContainsString( '<td>Cambio de fechas<div class="small text-secondary">03/03/2026 → 07/04/2026</div></td>', $body );
		$this->assertStringContainsString( '<td>Cancelación</td>', $body );
		$this->assertStringContainsString( '<th scope="col">Justificación</th>', $body );
		foreach ( array( 'Resolución', 'Coste', 'Pendiente', 'Aprobada' ) as $gone ) {
			$this->assertStringNotContainsString( $gone, $body );
		}
	}

	/**
	 * Nadie aprueba ni deniega una incidencia: ni la ficha lo pide, ni se
	 * guarda una resolución, ni hay una cola que vaciar en la cabecera.
	 */
	public function test_nobody_resolves_an_incident() {
		$this->as_role( 'fmc_adviser' );
		$action = $this->action( get_current_user_id() );
		$id     = $this->incident( $action, 'speaker' )['id'];

		$this->assertSame( '', get_post_meta( $id, I::RESOLUTION, true ) );
		$this->assertSame( array( I::REQUEST, I::NEW_START, I::NEW_END, I::REASON ), array_keys( \Fmc\PublicFront\Fields::all( PostTypes::INCIDENT ) ) );

		$this->as_role( 'fmc_training_service' );
		$this->assertStringNotContainsString( 'fmc-bell', self::chrome() );
		// Aunque lo mande a mano, no hay campo que lo reciba.
		Editor::apply( PostTypes::INCIDENT, $id, array( I::RESOLUTION => 'approved' ), '', 0 );
		$this->assertSame( '', get_post_meta( $id, I::RESOLUTION, true ) );

		// Las que ya estaban resueltas conservan lo que tenían.
		update_post_meta( $id, I::RESOLUTION, 'denied' );
		$this->assertSame( 'denied', get_post_meta( $id, I::RESOLUTION, true ) );
		$this->assertArrayHasKey( I::RESOLUTION, I::map() );
	}

	/**
	 * Enviar una incidencia no tiene vuelta atrás: siempre se avisa y se pide
	 * confirmar. Ahí mismo, en su párrafo, se recuerda el trámite que hay que
	 * hacer fuera de aquí; el despliegue lo dice con sus palabras, o lo quita.
	 * Sin guion, va escrito en la ficha.
	 */
	public function test_sending_an_incident_warns_and_reminds_the_procedure_outside() {
		$this->as_role( 'fmc_adviser' );
		$action          = $this->action( get_current_user_id() );
		$_GET['fmc_new'] = PostTypes::INCIDENT;
		$new             = static fn(): string => Editor::render( PostTypes::INCIDENT, null, array( '_parent' => $action ), array() )['body'];
		$asked           = static fn( string $text ): string => 'class="fmc-form" data-fmc-confirm="¿Enviar la incidencia?" data-fmc-confirm-icon="warning" data-fmc-confirm-text="' . $text . '" data-fmc-confirm-button="Enviar la incidencia">';

		// De serie se recuerda el trámite sin nombrar la herramienta de nadie.
		$this->assertSame( Editor::INCIDENT_REMINDER, Editor::incident_reminder() );
		$this->assertStringContainsString( 'servicio de perfeccionamiento', Editor::INCIDENT_REMINDER );
		$body = $new();
		$this->assertStringContainsString( $asked( Editor::INCIDENT_WARNING . "\n\n" . Editor::INCIDENT_REMINDER ), $body );
		$this->assertStringContainsString( '<noscript><div class="alert alert-warning" role="note">' . Editor::INCIDENT_WARNING . "<br />\n<br />\n" . Editor::INCIDENT_REMINDER . '</div></noscript>', $body );

		// El despliegue lo dice con sus palabras.
		add_filter( 'fmc_incident_reminder', static fn(): string => 'Tramítelo también en <b>el otro sistema</b>.' );
		$this->assertSame( 'Tramítelo también en el otro sistema.', Editor::incident_reminder() );
		$body = $new();
		$this->assertStringContainsString( $asked( Editor::INCIDENT_WARNING . "\n\nTramítelo también en el otro sistema." ), $body );
		$this->assertStringNotContainsString( Editor::INCIDENT_REMINDER, $body );

		// O lo quita: el aviso de que no tiene vuelta atrás sale igual.
		remove_all_filters( 'fmc_incident_reminder' );
		add_filter( 'fmc_incident_reminder', '__return_empty_string' );
		$this->assertStringContainsString( $asked( Editor::INCIDENT_WARNING ), $new() );
		remove_all_filters( 'fmc_incident_reminder' );

		// Una incidencia ya enviada no se vuelve a enviar: no hay nada que avisar.
		$sent = $this->incident( $action, 'cancel' )['id'];
		$this->assertStringNotContainsString( 'data-fmc-confirm', Editor::render( PostTypes::INCIDENT, get_post( $sent ), array(), array() )['body'] );
	}
}

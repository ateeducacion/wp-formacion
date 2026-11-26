<?php
/**
 * Tests for the edit screen as each profile sees it.
 *
 * @package Fmc
 */

use Fmc\Meta\ActionMetaKeys as A;
use Fmc\Meta\DesignMetaKeys as D;
use Fmc\Meta\IncidentMetaKeys as I;
use Fmc\Meta\MetaRegistration;
use Fmc\Meta\SpeakerMetaKeys;
use Fmc\PostType\PostTypes;
use Fmc\Taxonomy\Taxonomies;
use Fmc\PublicFront\Documents;
use Fmc\PublicFront\Editor;
use Fmc\PublicFront\Screen;

/**
 * La ficha pinta deshabilitado lo que no se puede escribir, y solo ofrece los
 * botones que el perfil puede usar.
 */
class Test_Editor_Render extends WP_UnitTestCase {

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
		$_GET     = array();
		$_REQUEST = array();
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
	 * @param string $title  Title.
	 * @return int
	 */
	private function post( string $type, int $author, string $status = 'publish', string $title = '' ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => $type,
				'post_author' => $author,
				'post_status' => $status,
				'post_title'  => '' !== $title ? $title : 'Título ' . wp_generate_password( 6, false ),
			)
		);
	}

	/**
	 * The screen body of a post.
	 *
	 * @param int $id Post ID.
	 * @return array<string, string>
	 */
	private function screen_of( int $id ): array {
		return Editor::screen( $id, '' );
	}

	/**
	 * Query an HTML fragment.
	 *
	 * @param string $html  HTML.
	 * @param string $xpath XPath expression.
	 * @return DOMNodeList
	 */
	private function find( string $html, string $xpath ): DOMNodeList {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8"><body>' . $html . '</body>' );
		libxml_clear_errors();
		return ( new DOMXPath( $dom ) )->query( $xpath );
	}

	/**
	 * Whether the input of a field is disabled.
	 *
	 * @param string $html HTML.
	 * @param string $key  Field key.
	 * @return bool
	 */
	private function disabled( string $html, string $key ): bool {
		$nodes = $this->find( $html, '//*[@name="f[' . $key . ']"][not(@type="hidden")]' );
		$this->assertGreaterThan( 0, $nodes->length, 'falta el campo ' . $key );
		return $nodes->item( 0 )->hasAttribute( 'disabled' );
	}

	/**
	 * The labels of the save buttons.
	 *
	 * @param string $html HTML.
	 * @return string[]
	 */
	private function buttons( string $html ): array {
		$out = array();
		foreach ( $this->find( $html, '//button[@name="fmc_status"]' ) as $button ) {
			$out[ $button->textContent ] = $button->getAttribute( 'value' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM.
		}
		return $out;
	}

	/**
	 * Lo que no existe, no se puede ver o no se puede crear sale como «No disponible».
	 */
	public function test_screens_that_are_not_available() {
		$this->as_role( 'fmc_training_service' );
		$speaker = $this->post( PostTypes::SPEAKER, 1 );
		$draft   = $this->post( PostTypes::ACTION, 1, 'draft' );

		$this->assertSame( 'No disponible', Editor::screen( 999999, '' )['title'] );
		$this->assertSame( 'No disponible', Editor::screen( $speaker, '' )['title'] );
		$this->assertSame( 'No disponible', Editor::screen( $draft, '' )['title'] );
		$this->assertSame( 'No disponible', Editor::screen( self::factory()->post->create(), '' )['title'] );
		$this->assertSame( 'No disponible', Editor::screen( 0, 'page' )['title'] );
		$this->assertSame( 'No disponible', Editor::screen( 0, PostTypes::ACTION )['title'], 'el servicio no crea acciones' );

		$this->as_role( 'fmc_adviser' );
		$this->assertSame( 'No disponible', Editor::screen( 0, PostTypes::INCIDENT )['title'], 'sin acción no hay incidencia' );
		$_GET[ Screen::ARG_PARENT ] = (string) $this->post( PostTypes::ACTION, 1 );
		$this->assertSame( 'No disponible', Editor::screen( 0, PostTypes::INCIDENT )['title'], 'ni sobre la acción de otra persona' );
	}

	/**
	 * Quién abre qué: el servicio lee acciones publicadas e incidencias, no borradores.
	 */
	public function test_who_can_view_what() {
		$adviser  = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$incident = get_post( $this->post( PostTypes::INCIDENT, $adviser ) );
		$this->as_role( 'fmc_training_service' );

		$this->assertTrue( Editor::can_view( get_post( $this->post( PostTypes::ACTION, $adviser ) ) ) );
		$this->assertFalse( Editor::can_view( get_post( $this->post( PostTypes::ACTION, $adviser, 'draft' ) ) ) );
		$this->assertTrue( Editor::can_view( $incident ) );
		$this->assertFalse( Editor::can_view( get_post( self::factory()->post->create() ) ) );

		$this->as_role( 'fmc_adviser' );
		$this->assertTrue( Editor::can_view( get_post( $this->post( PostTypes::ACTION, $adviser ) ) ), 'la acción del calendario, aunque sea de otra' );
		$this->assertFalse( Editor::can_view( get_post( $this->post( PostTypes::ACTION, $adviser, 'draft' ) ) ), 'su borrador, no' );
		$this->assertTrue( Editor::can_view( get_post( $this->post( PostTypes::DESIGN, $adviser ) ) ), 'un diseño finalizado lo lee cualquiera' );
		$this->assertFalse( Editor::can_view( get_post( $this->post( PostTypes::DESIGN, $adviser, 'draft' ) ) ), 'el borrador de otra, no' );
		// Ni su propio borrador ni un histórico, aunque sepa la dirección (ADR-0017).
		$this->assertFalse( Editor::can_view( get_post( $this->post( PostTypes::DESIGN, get_current_user_id(), 'draft' ) ) ), 'ni el suyo' );
		$this->assertFalse( Editor::can_view( get_post( $this->post( PostTypes::DESIGN, $adviser, PostTypes::ARCHIVED ) ) ), 'ni un histórico' );
		$this->assertFalse( Editor::can_view( $incident ) );
	}

	/**
	 * La asesoría no da de alta diseños: los consulta (ADR-0017).
	 */
	public function test_an_adviser_cannot_open_a_new_design() {
		$this->as_role( 'fmc_adviser' );

		$screen = Editor::screen( 0, PostTypes::DESIGN );

		$this->assertSame( 'No disponible', $screen['title'] );
		$this->assertSame( array(), $this->buttons( $screen['body'] ) );
	}

	/**
	 * La coordinación, en un diseño nuevo: borrador o finalizado. No hay
	 * «mandar a revisión» (adenda de ADR-0004).
	 */
	public function test_a_new_design_for_a_curator() {
		$this->as_role( 'fmc_curator' );

		$screen = Editor::screen( 0, PostTypes::DESIGN );

		$this->assertSame( 'designs', $screen['tab'] );
		$this->assertSame( 'Nuevo diseño de acción formativa', $screen['title'] );
		$this->assertSame(
			array(
				'Guardar borrador'       => 'draft',
				'Marcar como finalizado' => 'publish',
			),
			$this->buttons( $screen['body'] )
		);
		$this->assertSame( '', $screen['actions'] );
		$this->assertSame( 1, $this->find( $screen['body'], '//input[@name="_fmc_nonce"]' )->length );
		$this->assertTrue( wp_verify_nonce( $this->find( $screen['body'], '//input[@name="_fmc_nonce"]' )->item( 0 )->getAttribute( 'value' ), Editor::nonce_action( PostTypes::DESIGN, 0 ) ) > 0 );
		$this->assertFalse( $this->disabled( $screen['body'], 'post_title' ) );
		$this->assertCount( count( Documents::kinds() ), iterator_to_array( $this->find( $screen['body'], '//input[@type="file"][starts-with(@name, "fmc_doc[")]' ) ) );
		$this->assertSame( 1, $this->find( $screen['body'], '//input[@name="fmc_doc[image][]"][not(@multiple)]' )->length, 'una sola imagen' );
		$this->assertStringContainsString( 'Elija un fichero o arrástrelo aquí', $this->find( $screen['body'], '//label[@for="fmc-add-image"]' )->item( 0 )->textContent );
	}

	/**
	 * La coordinación finaliza; en uno finalizado, guarda, lo devuelve a borrador
	 * o lo pasa a histórico; y uno histórico lo recupera (ADR-0016).
	 */
	public function test_design_buttons_for_a_curator() {
		$adviser = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$pending = $this->post( PostTypes::DESIGN, $adviser, 'pending' );
		$done    = $this->post( PostTypes::DESIGN, $adviser );
		$this->as_role( 'fmc_curator' );

		// Uno que se quedó «en revisión» se trata como un borrador.
		$this->assertSame(
			array(
				'Guardar borrador'       => 'draft',
				'Marcar como finalizado' => 'publish',
			),
			$this->buttons( $this->screen_of( $pending )['body'] )
		);
		$this->assertStringContainsString( 'Borrador', $this->screen_of( $pending )['badges'] );

		$screen = $this->screen_of( $done );
		$this->assertSame(
			array(
				'Guardar'           => 'publish',
				'Volver a borrador' => 'draft',
				'Pasar a histórico' => PostTypes::ARCHIVED,
			),
			$this->buttons( $screen['body'] )
		);
		$old = $this->screen_of( $this->post( PostTypes::DESIGN, $adviser, PostTypes::ARCHIVED ) );
		$this->assertSame(
			array(
				'Guardar'                 => PostTypes::ARCHIVED,
				'Recuperar del histórico' => 'publish',
			),
			$this->buttons( $old['body'] )
		);
		$this->assertStringContainsString( 'Histórico', $old['badges'] );
		$this->assertStringNotContainsString( 'Agendar una acción', $old['actions'], 'un histórico ya no se imparte' );
		$this->assertStringContainsString( 'Finalizado', $screen['badges'] );
		$this->assertStringContainsString( 'Agendar una acción con este diseño', $screen['actions'] );
		$this->assertStringContainsString( Screen::ARG_DESIGN . '=' . $done, $screen['actions'] );
		// Finalizado pero fuera del catálogo: no tiene ficha pública que ver.
		$this->assertStringNotContainsString( 'Ver en el catálogo', $screen['actions'] );
		update_post_meta( $done, D::IN_CATALOGUE, '1' );
		$this->assertStringContainsString( 'fmc_curso=' . $done . '" target="_blank" rel="noopener">Ver en el catálogo</a>', $this->screen_of( $done )['actions'] );
		$this->assertStringNotContainsString( 'fmc_delete', $screen['actions'], 'la coordinación no borra' );

		$this->as_role( 'administrator' );
		$this->assertStringContainsString( 'name="_fmc_nonce"', $this->screen_of( $done )['actions'], 'la administración, sí' );
	}

	/**
	 * Un diseño finalizado de otra persona, para la asesoría: todo de solo lectura.
	 */
	public function test_someone_elses_design_is_read_only() {
		$other  = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$design = $this->post( PostTypes::DESIGN, $other );
		$this->as_role( 'fmc_adviser' );

		$screen = $this->screen_of( $design );

		$this->assertTrue( $this->disabled( $screen['body'], 'post_title' ) );
		$this->assertTrue( $this->disabled( $screen['body'], D::TYPE ) );
		$this->assertTrue( $this->disabled( $screen['body'], D::IN_CATALOGUE ) );
		$this->assertSame( array(), $this->buttons( $screen['body'] ) );
		$this->assertStringContainsString( 'Esta ficha es de solo lectura.', $screen['body'] );
		$this->assertSame( 0, $this->find( $screen['body'], '//input[@type="file"]' )->length );
		$this->assertStringContainsString( 'Agendar una acción', $screen['actions'], 'sí puede impartirlo' );

		$this->as_role( 'fmc_training_service' );
		$this->assertStringNotContainsString( 'Agendar una acción', $this->screen_of( $design )['actions'], 'el servicio no crea acciones' );
	}

	/**
	 * En la acción, la asesoría no escribe los campos del servicio, y el servicio solo esos.
	 */
	public function test_action_fields_are_split_between_adviser_and_service() {
		$adviser = $this->as_role( 'fmc_adviser' );
		$action  = $this->post( PostTypes::ACTION, $adviser, 'draft' );

		$body = $this->screen_of( $action )['body'];
		$this->assertTrue( $this->disabled( $body, A::FILE_NUMBER ) );
		$this->assertSame( 0, $this->find( $body, '//*[@name="f[' . A::SITUATION . ']"]' )->length, 'la situación no se teclea' );
		$this->assertSame( 0, $this->find( $body, '//*[@name="f[' . A::PROCESS . ']"]' )->length, 'ni el proceso' );
		$this->assertFalse( $this->disabled( $body, A::VIDEOCONFERENCE ) );
		$this->assertSame( 0, $this->find( $body, '//*[@name="f[' . A::SUBTITLE . ']"]' )->length, 'ni el subtítulo' );
		$this->assertTrue( $this->disabled( $body, A::INTERNAL_REF ), 'el expediente interno lo pone el aplicativo' );
		$this->assertStringContainsString( 'Lo rellena el servicio de perfeccionamiento.', $body );
		$this->assertStringContainsString( '¿No está en la lista?', $body, 'puede crear ponentes' );
		$this->assertSame(
			array(
				'Guardar borrador' => 'draft',
				'Entregar'         => 'publish',
			),
			$this->buttons( $body )
		);
		$this->assertStringContainsString( 'Una vez entregada no podrá cambiarla', $body );

		// Entregada, la asesoría ya no la cambia, pero abre incidencias sobre ella (ADR-0020).
		wp_publish_post( $action );
		$screen = $this->screen_of( $action );
		$this->assertTrue( $this->disabled( $screen['body'], A::VIDEOCONFERENCE ) );
		$this->assertSame( array(), $this->buttons( $screen['body'] ) );
		$this->assertStringContainsString( 'Abrir una incidencia', $screen['body'] );

		$this->as_role( 'fmc_training_service' );
		$body = $this->screen_of( $action )['body'];
		$this->assertFalse( $this->disabled( $body, A::FILE_NUMBER ) );
		$this->assertTrue( $this->disabled( $body, A::VIDEOCONFERENCE ) );
		$this->assertTrue( $this->disabled( $body, A::INTERNAL_REF ) );
		$this->assertStringContainsString( 'Sin sesiones.', $body, 'las sesiones, de solo lectura' );
		$this->assertStringNotContainsString( 'data-fmc-session-add', $body );
		$this->assertStringNotContainsString( 'Lo rellena el servicio de perfeccionamiento.', $body );
		$this->assertStringNotContainsString( '¿No está en la lista?', $body );
		$this->assertSame( array( 'Guardar' => '' ), $this->buttons( $body ), 'tiene algo que guardar' );
	}

	/**
	 * La acción enseña sus horas calculadas y su curso escolar; las cifras de
	 * matrícula ya no (ADR-0010).
	 */
	public function test_an_action_shows_its_computed_totals() {
		$adviser = $this->as_role( 'fmc_adviser' );
		$action  = $this->post( PostTypes::ACTION, $adviser );
		foreach (
			array(
				A::START          => '2026-10-01',
				A::HOURS_ONSITE   => 9,
				A::HOURS_ONLINE   => 3,
				A::REPLICAS       => 2,
				A::ENROLLED_WOMEN => 12,
				A::SITUATION      => 'done',
			) as $key => $value
		) {
			update_post_meta( $action, $key, $value );
		}

		$screen   = $this->screen_of( $action );
		$counters = array();
		foreach ( $this->find( $screen['body'], '//div[@class="fmc-counter"]' ) as $counter ) {
			$counters[ $counter->getElementsByTagName( 'span' )->item( 0 )->textContent ] = $counter->getElementsByTagName( 'strong' )->item( 0 )->textContent;
		}

		$this->assertSame( '2026-2027', $counters['Curso escolar'] );
		$this->assertSame( '12 h (24 h con el desdoble)', $counters['Horas totales'] );
		$this->assertArrayNotHasKey( 'Matriculados', $counters );
		$this->assertStringNotContainsString( A::ENROLLED_WOMEN, $screen['body'] );
		$this->assertStringContainsString( 'Realizada', $screen['badges'] );

		delete_post_meta( $action, A::HOURS_ONSITE );
		delete_post_meta( $action, A::HOURS_ONLINE );
		delete_post_meta( $action, A::START );
		$this->assertStringContainsString( '<strong>—</strong><span>Horas totales</span>', $this->screen_of( $action )['body'] );
	}

	/**
	 * Las incidencias de la acción, con su resolución, y el botón para abrir otra.
	 */
	public function test_an_action_lists_its_incidents() {
		$adviser = $this->as_role( 'fmc_adviser' );
		$action  = $this->post( PostTypes::ACTION, $adviser );

		$body = $this->screen_of( $action )['body'];
		$this->assertStringContainsString( 'Ninguna.', $body );
		$this->assertStringContainsString( 'Abrir una incidencia', $body );

		$approved = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::INCIDENT,
				'post_parent' => $action,
				'post_status' => 'publish',
			)
		);
		// Una de antes, que estaba resuelta, y una de ahora, que dice qué pide.
		update_post_meta( $approved, I::RESOLUTION, 'approved' );
		update_post_meta( $approved, I::REASON, 'Cambio de ponente por enfermedad' );
		$cancel = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::INCIDENT,
				'post_parent' => $action,
				'post_status' => 'publish',
			)
		);
		update_post_meta( $cancel, I::REQUEST, 'cancel' );

		$items = $this->find( $this->screen_of( $action )['body'], '//section//li' );
		$this->assertSame( 2, $items->length );
		$text = $items->item( 0 )->textContent . $items->item( 1 )->textContent;
		$this->assertStringContainsString( 'Cambio de ponente por enfermedad', $text );
		$this->assertStringContainsString( 'Cancelación', $text, 'dice qué se pidió' );
		// Nadie las resuelve: ni pendientes ni aprobadas (ADR-0029).
		$this->assertStringNotContainsString( 'Aprobada', $text );
		$this->assertStringNotContainsString( 'Pendiente', $text );

		$this->as_role( 'fmc_training_service' );
		$this->assertStringNotContainsString( 'Abrir una incidencia', $this->screen_of( $action )['body'] );
	}

	/**
	 * Una acción nueva desde un diseño se abre con él elegido. Su modalidad y
	 * sus horas no se teclean: son las del diseño y no están en la ficha.
	 */
	public function test_a_new_action_from_a_design_opens_with_it_chosen() {
		$this->as_role( 'fmc_adviser' );
		$design = $this->post( PostTypes::DESIGN, 1 );
		update_post_meta( $design, D::MODALITY, 'blended' );
		$_GET[ Screen::ARG_DESIGN ] = (string) $design;

		$body = Editor::screen( 0, PostTypes::ACTION )['body'];

		$this->assertSame( (string) $design, $this->find( $body, '//select[@name="f[fmc_design_id]"]/option[@selected]' )->item( 0 )->getAttribute( 'value' ) );
		$this->assertSame( 0, $this->find( $body, '//*[@name="f[' . A::MODALITY . ']"]' )->length, 'la modalidad es del diseño' );
		$this->assertSame( 0, $this->find( $body, '//*[@name="f[' . A::HOURS_ONSITE . ']"]' )->length, 'y las horas' );
		$this->assertSame(
			array(
				'Guardar borrador' => 'draft',
				'Entregar'         => 'publish',
			),
			$this->buttons( $body )
		);
	}

	/**
	 * Las sesiones: una fila por sesión, una vacía para añadir, su plantilla y
	 * el total; lo escrito antes a mano, debajo y de solo lectura (ADR-0027).
	 */
	public function test_an_action_lists_its_sessions_and_keeps_the_old_text() {
		$adviser = $this->as_role( 'fmc_adviser' );
		$action  = $this->post( PostTypes::ACTION, $adviser, 'draft' );
		update_post_meta(
			$action,
			A::SESSIONS,
			array(
				array(
					'day'  => '2027-03-20',
					'from' => '16:00',
					'to'   => '20:00',
				),
				array(
					'day'  => '2027-03-27',
					'from' => '16:00',
					'to'   => '18:30',
				),
			)
		);

		$body = $this->screen_of( $action )['body'];

		$this->assertSame( 3, $this->find( $body, '//div[@data-fmc-sessions]/table/tbody/tr[@data-fmc-session]' )->length, 'dos sesiones y una fila vacía' );
		$this->assertSame( '2027-03-27', $this->find( $body, '//input[@name="f[' . A::SESSIONS . '][1][day]"]' )->item( 0 )->getAttribute( 'value' ) );
		$this->assertSame( 'time', $this->find( $body, '//input[@name="f[' . A::SESSIONS . '][1][from]"]' )->item( 0 )->getAttribute( 'type' ) );
		$this->assertSame( 1, $this->find( $body, '//template[@data-fmc-session-template]' )->length );
		$this->assertStringContainsString( '<strong data-fmc-sessions-total>6.5 h</strong>', $body );
		$this->assertStringNotContainsString( 'escrito antes de las sesiones', $body, 'sin texto antiguo no sale' );

		update_post_meta( $action, A::SCHEDULE, "Martes y jueves\npor la tarde & noche" );
		$body = $this->screen_of( $action )['body'];
		$this->assertStringContainsString( '<div class="fmc-legacy">Martes y jueves<br />' . "\n" . 'por la tarde &amp; noche</div>', $body );
		$this->assertSame( 0, $this->find( $body, '//*[@name="f[' . A::SCHEDULE . ']"]' )->length, 'no se puede cambiar' );
	}

	/**
	 * Al elegir el diseño de una acción se ve su tipo, su modalidad y sus
	 * horas, y un enlace a su ficha que no se queda en el panel (#57).
	 */
	public function test_the_design_of_an_action_shows_what_it_brings() {
		$this->as_role( 'fmc_adviser' );
		$blended = $this->post( PostTypes::DESIGN, 1 );
		update_post_meta( $blended, D::TYPE, 'course' );
		update_post_meta( $blended, D::MODALITY, 'blended' );
		update_post_meta( $blended, D::HOURS_ONSITE, 8 );
		update_post_meta( $blended, D::HOURS_ONLINE, 12 );
		$onsite = $this->post( PostTypes::DESIGN, 1 );
		update_post_meta( $onsite, D::TYPE, 'one_off' );
		update_post_meta( $onsite, D::MODALITY, 'onsite' );
		update_post_meta( $onsite, D::HOURS_ONSITE, 3 );
		$_GET[ Screen::ARG_DESIGN ] = (string) $blended;

		$body   = Editor::screen( 0, PostTypes::ACTION )['body'];
		$option = function ( int $id ) use ( $body ) {
			return $this->find( $body, '//select[@name="f[fmc_design_id]"]/option[@value="' . $id . '"]' )->item( 0 );
		};

		$this->assertSame( 'Curso · Mixta · 20 h (8 presenciales + 12 en línea)', $option( $blended )->getAttribute( 'data-fmc-hint' ) );
		$this->assertSame( 'Acción puntual · Presencial · 3 h', $option( $onsite )->getAttribute( 'data-fmc-hint' ) );
		$url = $option( $blended )->getAttribute( 'data-fmc-hint-url' );
		$this->assertStringContainsString( Screen::ARG_EDIT . '=' . $blended, $url );
		$this->assertStringNotContainsString( Screen::ARG_FRAME, $url, 'la ficha del diseño se abre aparte, no en el panel' );

		// El elegido ya lo dice al abrir la ficha, sin esperar al guion.
		$box = $this->find( $body, '//div[contains(@class,"fmc-hint")]' )->item( 0 );
		$this->assertStringStartsWith( 'Curso · Mixta · 20 h', trim( $box->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM.
		$link = $this->find( $body, '//div[contains(@class,"fmc-hint")]/a' )->item( 0 );
		$this->assertSame( '_blank', $link->getAttribute( 'target' ) );
		$this->assertSame( 'Ver la ficha del diseño', $link->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM.
	}

	/**
	 * El diseño de una acción se elige entre los finalizados, con código y tipo:
	 * un borrador aún no se imparte y un histórico ya no (ADR-0016). La acción
	 * antigua conserva el suyo.
	 *
	 * @dataProvider design_choosers
	 *
	 * @param string $role Who schedules.
	 */
	public function test_an_action_chooses_among_finalised_designs( string $role ) {
		$curator = self::factory()->user->create( array( 'role' => 'fmc_curator' ) );
		$public  = $this->post( PostTypes::DESIGN, $curator, 'publish', 'Robótica' );
		update_post_meta( $public, D::CODE, 'A247' );
		update_post_meta( $public, D::TYPE, 'course' );
		$this->post( PostTypes::DESIGN, $curator, 'draft', 'Un borrador' );
		$old = $this->post( PostTypes::DESIGN, $curator, PostTypes::ARCHIVED, 'Pizarra digital' );
		update_post_meta( $old, D::CODE, 'A007' );
		$me     = $this->as_role( $role );
		$action = $this->post( PostTypes::ACTION, $me );
		update_post_meta( $action, A::DESIGN_ID, $old );

		$options = function ( string $body ) {
			$out = array();
			foreach ( $this->find( $body, '//select[@name="f[fmc_design_id]"]/option[@value!=""]' ) as $option ) {
				$out[ (int) $option->getAttribute( 'value' ) ] = $option->textContent; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM.
			}
			return $out;
		};

		$this->assertSame( array( $public => 'A247 · Robótica (Curso)' ), $options( Editor::screen( 0, PostTypes::ACTION )['body'] ) );
		$this->assertSame(
			array(
				$public => 'A247 · Robótica (Curso)',
				$old    => 'A007 · Pizarra digital (Histórico)',
			),
			$options( $this->screen_of( $action )['body'] ),
			'la acción antigua no pierde su diseño'
		);

		// Del histórico que no puede abrir, ni pista ni enlace a su ficha.
		update_post_meta( $old, D::TYPE, 'course' );
		$kept = $this->find( $this->screen_of( $action )['body'], '//select[@name="f[fmc_design_id]"]/option[@value="' . $old . '"]' )->item( 0 );
		$this->assertSame( Editor::can_view( get_post( $old ) ), $kept->hasAttribute( 'data-fmc-hint-url' ) );
		$this->assertFalse( Editor::can_view( get_post( $old ) ) && ! $kept->hasAttribute( 'data-fmc-hint' ), 'si puede abrirlo, la pista sale' );
	}

	/**
	 * Quién agenda acciones.
	 *
	 * @return array<string, array{string}>
	 */
	public function design_choosers(): array {
		return array(
			'asesoría'     => array( 'fmc_adviser' ),
			'coordinación' => array( 'fmc_curator' ),
		);
	}

	/**
	 * Sin cuentas de asesoría, la lista vacía dice por qué.
	 */
	public function test_an_empty_adviser_list_says_why() {
		$this->as_role( 'administrator' );
		foreach ( get_users( array( 'role__in' => array( 'fmc_adviser', 'fmc_curator' ) ) ) as $user ) {
			wp_delete_user( $user->ID );
		}

		$this->assertStringContainsString( 'Todavía no hay ninguna asesoría con cuenta', Editor::screen( 0, PostTypes::ACTION )['body'] );

		// La coordinación ya no es asesoría (ADR-0024): el aviso sigue saliendo
		// hasta que haya alguna cuenta de asesoría.
		$this->as_role( 'fmc_adviser' );
		$this->assertStringNotContainsString( 'Aún no hay ninguna asesoría', Editor::screen( 0, PostTypes::ACTION )['body'] );
	}

	/**
	 * Las asesorías salen para elegir; las listas largas llevan buscador.
	 */
	public function test_adviser_and_term_choices() {
		$adviser = $this->as_role( 'fmc_adviser' );
		$this->factory()->user->create( array( 'role' => 'fmc_training_service' ) );
		$this->factory()->user->create( array( 'role' => 'fmc_curator' ) );
		for ( $i = 0; $i < 11; $i++ ) {
			self::factory()->term->create( array( 'taxonomy' => 'fmc_scope' ) );
		}

		$body = Editor::screen( 0, PostTypes::ACTION )['body'];

		$advisers = $this->find( $body, '//select[@name="f[fmc_adviser]"]/option[@value!=""]' );
		$this->assertSame( 1, $advisers->length, 'ni el servicio de perfeccionamiento ni la coordinación son asesoría' );
		$this->assertSame( (string) $adviser, $advisers->item( 0 )->getAttribute( 'value' ) );
		$this->assertFalse( $this->find( $body, '//select[@name="f[fmc_adviser]"]' )->item( 0 )->hasAttribute( 'data-fmc-ts' ) );

		$scopes = $this->find( $body, '//select[@name="f[tax:fmc_scope]"]' )->item( 0 );
		$this->assertTrue( $scopes->hasAttribute( 'data-fmc-ts' ) );
		$this->assertSame( 12, $scopes->getElementsByTagName( 'option' )->length );
	}

	/**
	 * Lo que depende de otro campo lleva su condición, y se esconde si no se cumple.
	 */
	public function test_conditional_fields_carry_their_rule() {
		$curator = $this->as_role( 'fmc_curator' );
		$design  = $this->post( PostTypes::DESIGN, $curator, 'draft' );
		update_post_meta( $design, D::MODALITY, 'online' );

		$body   = $this->screen_of( $design )['body'];
		$onsite = $this->find( $body, '//input[@name="f[fmc_hours_onsite]"]/..' )->item( 0 );
		$online = $this->find( $body, '//input[@name="f[fmc_hours_online]"]/..' )->item( 0 );

		$this->assertSame( 'f[fmc_modality]', $onsite->getAttribute( 'data-fmc-show' ) );
		$this->assertSame( 'onsite,blended', $onsite->getAttribute( 'data-fmc-show-values' ) );
		$this->assertStringContainsString( 'd-none', $onsite->getAttribute( 'class' ) );
		$this->assertStringNotContainsString( 'd-none', $online->getAttribute( 'class' ) );
		$this->assertSame( '200', $this->find( $body, '//input[@name="f[fmc_hours_online]"]' )->item( 0 )->getAttribute( 'max' ) );
	}

	/**
	 * Lo que no se guardó vuelve con su motivo y el campo marcado.
	 */
	public function test_errors_are_listed_and_marked() {
		$this->as_role( 'fmc_adviser' );

		$screen = Editor::render(
			PostTypes::DESIGN,
			null,
			array( 'post_title' => 'Lo tecleado' ),
			array( 'post_title' => 'Falta rellenar «Título».' )
		);

		$this->assertSame( 'Falta rellenar «Título».', $this->find( $screen['body'], '//div[contains(@class,"alert-danger")]//li' )->item( 0 )->textContent );
		$title = $this->find( $screen['body'], '//input[@name="f[post_title]"]' )->item( 0 );
		$this->assertStringContainsString( 'is-invalid', $title->getAttribute( 'class' ) );
		$this->assertSame( 'Lo tecleado', $title->getAttribute( 'value' ) );
	}

	/**
	 * Una incidencia nueva: sobre qué acción, a qué acción se ata y un solo botón.
	 */
	public function test_a_new_incident_screen() {
		$adviser                    = $this->as_role( 'fmc_adviser' );
		$action                     = $this->post( PostTypes::ACTION, $adviser, 'publish', 'Robótica de otoño' );
		$_GET[ Screen::ARG_PARENT ] = (string) $action;

		$body = Editor::screen( 0, PostTypes::INCIDENT )['body'];

		$this->assertStringContainsString( 'Sobre la acción: <a', $body );
		$this->assertStringContainsString( 'Robótica de otoño', $body );
		$this->assertSame( (string) $action, $this->find( $body, '//input[@name="fmc_parent"]' )->item( 0 )->getAttribute( 'value' ) );
		$this->assertSame( array( 'Enviar la incidencia' => 'publish' ), $this->buttons( $body ) );
		// Qué se pide, las fechas nuevas y un solo cuadro: la justificación.
		$this->assertSame( 4, $this->find( $body, '//input[@type="radio"][@name="f[fmc_request]"]' )->length );
		$this->assertSame( 1, $this->find( $body, '//textarea' )->length );
		$this->assertSame( 'f[' . I::REASON . ']', $this->find( $body, '//textarea' )->item( 0 )->getAttribute( 'name' ) );
		foreach ( array( I::RESOLUTION, I::RESOLUTION_NOTES, I::PROPOSAL, I::HAS_COST ) as $gone ) {
			$this->assertSame( 0, $this->find( $body, '//*[@name="f[' . $gone . ']"]' )->length, $gone );
		}
		$this->assertStringNotContainsString( 'Resolución', $body );
	}

	/**
	 * Dentro del panel lateral, el formulario se manda como fragmento.
	 */
	public function test_the_form_stays_in_the_side_panel() {
		$this->as_role( 'fmc_adviser' );
		$this->assertSame( 0, $this->find( Editor::screen( 0, PostTypes::SPEAKER )['body'], '//input[@name="fmc_marco"]' )->length );

		$_REQUEST[ Screen::ARG_FRAME ] = '1';
		$this->assertSame( 1, $this->find( Editor::screen( 0, PostTypes::SPEAKER )['body'], '//form/input[@name="fmc_marco"][@value="1"]' )->length );
	}

	/**
	 * El límite de un documento no depende de la cuota de la biblioteca de medios.
	 *
	 * En un multisitio con la cuota de subida agotada, `wp_max_upload_size()`
	 * devuelve 0, y la zona decía «hasta 0 B cada uno». Los documentos no van a
	 * la biblioteca: lo que los limita es el tope de su clase y el de PHP.
	 */
	public function test_the_document_limit_ignores_the_media_quota() {
		$curator = $this->as_role( 'fmc_curator' );
		$design  = $this->post( PostTypes::DESIGN, $curator, 'draft' );
		$no_room = static function () {
			return 0;
		};
		add_filter( 'upload_size_limit', $no_room );
		$body = $this->screen_of( $design )['body'];
		remove_filter( 'upload_size_limit', $no_room );

		$this->assertStringNotContainsString( 'hasta 0 B', $body );
		$slides = $this->find( $body, '//input[@id="fmc-add-slides"]' )->item( 0 );
		$this->assertGreaterThan( 0, (int) $slides->getAttribute( 'data-fmc-max' ) );
	}

	/**
	 * Los documentos de un diseño: cada uno se cambia o se quita; los del sistema anterior, como enlace.
	 */
	public function test_the_documents_section_of_a_design() {
		$curator = $this->as_role( 'fmc_curator' );
		$design  = $this->post( PostTypes::DESIGN, $curator, 'draft' );
		$doc     = self::factory()->attachment->create(
			array(
				'post_parent' => $design,
				'file'        => '2026/10/diseno.pdf',
			)
		);
		update_post_meta( $doc, Documents::KIND_META, 'design' );
		$image = self::factory()->attachment->create(
			array(
				'post_parent' => $design,
				'file'        => '2026/10/foto.png',
			)
		);
		update_post_meta( $image, Documents::KIND_META, 'image' );
		update_post_meta( $design, D::SUPPORT_FILE, 'https://example.org/files/apoyo%20curso.zip' );

		$body = $this->screen_of( $design )['body'];

		$this->assertStringContainsString( '>diseno.pdf</a>', $body );
		$this->assertSame( 1, $this->find( $body, '//input[@type="file"][@name="fmc_replace[' . $doc . ']"]' )->length );
		$this->assertSame( 1, $this->find( $body, '//input[@name="fmc_remove[]"][@value="' . $doc . '"]' )->length );
		// La imagen es de un documento: la que hay se cambia con su «Reemplazar»,
		// y la zona para añadir no sale hasta que se quite.
		$this->assertSame( 0, $this->find( $body, '//input[@name="fmc_doc[image][]"]' )->length );
		// El diseño es uno por formato: junto al PDF se puede añadir el editable.
		$this->assertSame( 1, $this->find( $body, '//input[@name="fmc_doc[design][]"][@multiple]' )->length );
		$this->assertStringContainsString( 'Uno por formato, la versión vigente', $body );
		$this->assertStringContainsString( 'DOC, DOCX, ODT o PDF · hasta', $body );
		$this->assertStringContainsString( 'Sistema anterior', $body );
		$this->assertStringContainsString( '>apoyo curso.zip</a>', $body );
		// Cada documento es una ficha: su tipo, su nombre, «Reemplazar» y el aspa,
		// que es una etiqueta de la casilla `fmc_remove[]` igual que «Deshacer»:
		// sin guion, quitar y deshacer funcionan.
		$this->assertSame( 'Quitar: diseno.pdf', $this->find( $body, '//input[@name="fmc_remove[]"][@value="' . $doc . '"]' )->item( 0 )->getAttribute( 'aria-label' ) );
		$this->assertSame( 'PDF', trim( $this->find( $body, '//li[.//input[@value="' . $doc . '"]]//span[contains(@class, "fmc-doc-type")]' )->item( 0 )->textContent ) );
		$this->assertSame( 1, $this->find( $body, '//label[contains(@class, "fmc-doc-x")][@for="fmc-remove-' . $doc . '"]' )->length );
		$this->assertSame( 1, $this->find( $body, '//label[contains(@class, "fmc-doc-undo")][@for="fmc-remove-' . $doc . '"]' )->length );
		$this->assertSame( 1, $this->find( $body, '//label[@for="fmc-replace-' . $doc . '"][contains(., "Reemplazar")]' )->length );
		$this->assertSame( 1, $this->find( $body, '//*[@data-fmc-picked="fmc-replace-' . $doc . '"][@hidden]' )->length );
		// La imagen representativa se ve en miniatura, servida por el aplicativo.
		$this->assertSame( Documents::url( $image ), $this->find( $body, '//img[contains(@class, "fmc-doc-thumb")]' )->item( 0 )->getAttribute( 'src' ) );
		// Las clases vacías se llenan en su zona: el campo de fichero dentro de su etiqueta, con lo que el servidor admite.
		$this->assertSame( 0, substr_count( $body, 'Ninguno.' ), 'la zona para añadir ya dice que no hay nada' );
		$zone = $this->find( $body, '//label[contains(@class, "fmc-drop")][@for="fmc-add-slides"]' );
		$this->assertSame( 1, $zone->length );
		$this->assertSame( 1, $this->find( $body, '//label[@for="fmc-add-slides"]/input[@type="file"][@id="fmc-add-slides"][@multiple]' )->length );
		$this->assertStringContainsString( 'Elija ficheros o arrástrelos aquí', $zone->item( 0 )->textContent );
		$this->assertStringContainsString( 'PPT, PPTX u ODP · hasta ', $zone->item( 0 )->textContent );
		$this->assertStringContainsString( ' cada uno', $zone->item( 0 )->textContent );
		$this->assertSame( 1, $this->find( $body, '//ul[@data-fmc-picked="fmc-add-slides"]' )->length );
		$this->assertStringContainsString( 'Presentaciones:', $this->find( $body, '//label[@for="fmc-add-slides"]//span[contains(@class, "visually-hidden")]' )->item( 0 )->textContent, 'el campo dice de qué clase es' );
		// El navegador comprueba lo mismo que el servidor: formatos, tamaño y el total de un envío.
		$slides = $this->find( $body, '//input[@id="fmc-add-slides"]' )->item( 0 );
		$this->assertSame( 'ppt,pptx,odp', $slides->getAttribute( 'data-fmc-exts' ) );
		$php_max = (int) wp_convert_hr_to_bytes( (string) ini_get( 'upload_max_filesize' ) );
		$this->assertSame( (string) ( $php_max > 0 ? min( 50 * MB_IN_BYTES, $php_max ) : 50 * MB_IN_BYTES ), $slides->getAttribute( 'data-fmc-max' ) );
		$this->assertSame( 'doc,docx,odt,pdf', $this->find( $body, '//input[@name="fmc_replace[' . $doc . ']"]' )->item( 0 )->getAttribute( 'data-fmc-exts' ) );
		$post_max = (int) wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) );
		if ( $post_max ) {
			$this->assertSame( (string) $post_max, $this->find( $body, '//div[contains(@class, "fmc-docs")]' )->item( 0 )->getAttribute( 'data-fmc-post-max' ) );
			$this->assertStringContainsString( 'entre todos los ficheros, hasta ' . size_format( $post_max ), $body );
		}
		$this->assertSame( 1, $this->find( $body, '//*[@data-fmc-doc-alert][@hidden]' )->length );
		// La casilla de quitar va después de la ficha: el tabulador sigue el orden que se ve.
		$this->assertSame( 1, $this->find( $body, '//div[contains(@class, "fmc-doc-card")][.//input[@name="fmc_replace[' . $doc . ']"]]/following-sibling::input[@name="fmc_remove[]"]' )->length );
		$this->assertSame( '.doc,.docx,.odt', $this->find( $body, '//input[@name="fmc_doc[sessions][]"]' )->item( 0 )->getAttribute( 'accept' ) );
		$this->assertSame( '.ppt,.pptx,.odp', $this->find( $body, '//input[@name="fmc_doc[slides][]"]' )->item( 0 )->getAttribute( 'accept' ) );
		$this->assertSame( '.pdf', $this->find( $body, '//input[@name="fmc_doc[support][]"]' )->item( 0 )->getAttribute( 'accept' ) );

		// La asesoría, en el diseño finalizado, abre los materiales y no cambia nada (ADR-0017).
		wp_publish_post( $design );
		$this->as_role( 'fmc_adviser' );
		$body = $this->screen_of( $design )['body'];
		$this->assertStringContainsString( '>diseno.pdf</a>', $body );
		$this->assertSame( 0, $this->find( $body, '//input[@type="file"]' )->length );
		$this->assertSame( 0, $this->find( $body, '//input[@name="fmc_remove[]"]' )->length );
		$this->assertSame( 0, $this->find( $body, '//label[contains(@class, "fmc-drop")]' )->length );
		$this->assertSame( 0, $this->find( $body, '//*[@data-fmc-doc-alert]' )->length );
		$this->assertSame( 3, substr_count( $body, 'Ninguno.' ), 'escaleta, presentaciones y otros, vacíos' );
		$this->assertStringNotContainsString( 'Uno solo, la versión vigente', $body, 'lo que se hace al subir no lo lee quien no sube' );
	}

	/**
	 * Subir documentos a un diseño nuevo pide poder subir ficheros y crear diseños.
	 */
	public function test_who_can_add_documents_to_a_new_design() {
		$this->as_role( 'fmc_curator' );
		$this->assertTrue( Editor::can_write( PostTypes::DESIGN, 'docs', 0 ) );

		$this->as_role( 'fmc_adviser' );
		$this->assertFalse( Editor::can_write( PostTypes::DESIGN, 'docs', 0 ), 'la asesoría no da de alta diseños' );

		$this->as_role( 'fmc_training_service' );
		$this->assertFalse( Editor::can_write( PostTypes::DESIGN, 'docs', 0 ) );
		$this->assertFalse( Editor::can_write( PostTypes::ACTION, A::FILE_NUMBER, 0 ), 'sin crear acciones, ni su campo' );
	}

	/**
	 * El valor de cada clase de campo: del post, de la meta o de los términos.
	 */
	public function test_values_come_from_where_they_live() {
		$this->as_role( 'fmc_curator' );
		$design = $this->post( PostTypes::DESIGN, 1, 'publish', 'Robótica' );
		$topic  = self::factory()->term->create( array( 'taxonomy' => 'fmc_topic' ) );
		wp_set_object_terms( $design, array( $topic ), 'fmc_topic' );
		update_post_meta( $design, D::CODE, 'A1' );
		$post = get_post( $design );

		$this->assertSame( '', Editor::value( null, 'post_title' ) );
		$this->assertSame( 'Robótica', Editor::value( $post, 'post_title' ) );
		$this->assertSame( array( $topic ), Editor::value( $post, 'tax:fmc_topic' ) );
		$this->assertSame( 'A1', Editor::value( $post, D::CODE ) );
		$this->assertSame( (string) $topic, $this->find( $this->screen_of( $design )['body'], '//select[@name="f[tax:fmc_topic]"]/option[@selected]' )->item( 0 )->getAttribute( 'value' ) );
	}

	/**
	 * El diseño nuevo enseña el código que le tocará, y nadie lo escribe.
	 */
	public function test_a_new_design_shows_its_code_read_only() {
		$this->as_role( 'fmc_curator' );
		update_post_meta( $this->post( PostTypes::DESIGN, 0 ), D::CODE, 'A41' );

		$body = Editor::screen( 0, PostTypes::DESIGN )['body'];
		$this->assertTrue( $this->disabled( $body, D::CODE ) );
		$this->assertSame( 'A042', $this->find( $body, '//input[@name="f[' . D::CODE . ']"]' )->item( 0 )->getAttribute( 'value' ) );
		$this->assertStringNotContainsString( 'name="f[tax:fmc_competence]', $body, 'las áreas antiguas ya no se rellenan' );
	}

	/**
	 * El perfil de competencia digital se ve dentro de la ficha como la tabla
	 * que genera elidd, y se elige en elidd sin salir de ella.
	 */
	public function test_the_digital_competence_profile_is_summarised() {
		$curator = $this->as_role( 'fmc_curator' );
		$design  = $this->post( PostTypes::DESIGN, $curator );
		update_post_meta( $design, D::DIGCOMP_URL, 'https://herramienta.example/?c=1.1.A1.1|1.1.A1.2|1.1.B1.1|3.1.B1.1' );

		$body = $this->screen_of( $design )['body'];
		$this->assertStringContainsString( 'Resumen del perfil', $body );
		$this->assertStringContainsString( '<th scope="colgroup" colspan="5" class="fmc-area-1">1. Compromiso profesional</th>', $body );
		$this->assertStringContainsString( '<th scope="col" class="fmc-area-1" title="Comunicación organizativa">1.1</th>', $body );
		$this->assertSame( 23, substr_count( $body, '<td class="fmc-area-' ), 'una celda por competencia' );
		// El nivel más alto; al pasar el ratón, todos, con sus indicadores.
		$this->assertStringContainsString( '<td class="fmc-area-1" title="Comunicación organizativa: A1 (2), B1 (1) indicador(es)">B1</td>', $body );
		$this->assertStringContainsString( '<td class="fmc-area-3" title="Enseñanza: B1 (1) indicador(es)">B1</td>', $body );
		$this->assertStringContainsString( '<td class="fmc-area-2"></td>', $body );
		$this->assertStringContainsString( 'href="https://elidd.ernesto.es/?c=1.1.A1.1|1.1.A1.2|1.1.B1.1|3.1.B1.1" target="_blank" rel="noopener" data-fmc-tool-view>Abrir en elidd</a>', $body, 'el enlace va a la herramienta, no a donde se guardó, y el guion lo abre en una ventana' );
		$this->assertStringContainsString( 'data-fmc-tool="f-' . D::DIGCOMP_URL . '" data-fmc-tool-url="https://elidd.ernesto.es/">Elegir los indicadores en elidd</button>', $body );

		// Quien despliega puede usar su propia copia de elidd.
		$copy = static fn() => 'https://copia.example/elidd/';
		add_filter( 'fmc_digcomp_tool', $copy );
		$body = $this->screen_of( $design )['body'];
		$this->assertStringContainsString( 'data-fmc-tool-url="https://copia.example/elidd/"', $body );
		$this->assertStringContainsString( 'href="https://copia.example/elidd/?c=1.1.A1.1|1.1.A1.2|1.1.B1.1|3.1.B1.1"', $body );
		remove_filter( 'fmc_digcomp_tool', $copy );
	}

	/**
	 * Al elegir ponentes: su isla, lo que han dado, una estrella si se les
	 * recomienda para el diseño de la acción, y estos primero; quien no está
	 * disponible, al final y marcado, pero se puede elegir.
	 */
	public function test_the_speaker_choices_help_to_choose() {
		$curator = $this->as_role( 'fmc_curator' );
		$design  = $this->post( PostTypes::DESIGN, $curator );
		$zoe     = $this->post( PostTypes::SPEAKER, $curator, 'publish', 'Zoe' );
		$ana     = $this->post( PostTypes::SPEAKER, $curator, 'publish', 'Ana' );
		$bea     = $this->post( PostTypes::SPEAKER, $curator, 'publish', 'Bea' );
		update_post_meta( $zoe, SpeakerMetaKeys::RECOMMENDED, array( $design ) );
		wp_set_object_terms( $ana, 'Norte', Taxonomies::ZONE );
		update_post_meta( $bea, SpeakerMetaKeys::UNAVAILABLE, true );
		update_post_meta( $bea, SpeakerMetaKeys::NOTES, 'De baja hasta junio' );
		$action = $this->post( PostTypes::ACTION, $curator );
		update_post_meta( $action, A::DESIGN_ID, $design );
		update_post_meta( $action, A::SPEAKERS, array( $ana ) );

		$body    = $this->screen_of( $action )['body'];
		$options = array();
		foreach ( $this->find( $body, '//select[@name="f[' . A::SPEAKERS . '][]"]/option' ) as $option ) {
			$options[] = $option->textContent; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM.
		}
		$this->assertSame( array( '★ Zoe', 'Ana · Norte · 1 acción', 'Bea · No disponible' ), $options );

		// Lo que lee fmc-app.js: el diseño del que depende la estrella, para
		// qué diseños se recomienda a cada cual, quién no está y sus observaciones.
		$this->assertCount( 1, $this->find( $body, '//select[@data-fmc-speakers="f[' . A::DESIGN_ID . ']"]' ) );
		$this->assertCount( 1, $this->find( $body, '//option[@value="' . $zoe . '"][@data-fmc-for="' . $design . '"]' ) );
		$this->assertCount( 1, $this->find( $body, '//option[@value="' . $bea . '"][@data-fmc-off="1"][@data-fmc-note="De baja hasta junio"]' ) );
		$this->assertCount( 1, $this->find( $body, '//option[@value="' . $ana . '"][@data-fmc-name="Ana"][@data-fmc-zone="Norte"][@data-fmc-given="1 acción"]' ) );
		$this->assertCount( 0, $this->find( $body, '//option[@value="' . $ana . '"][@data-fmc-off or @data-fmc-note or @data-fmc-for]' ) );
	}

	/**
	 * La ficha del ponente: el cargo es una lista cerrada y la isla, un término.
	 */
	public function test_the_speaker_position_and_zone_are_chosen_from_a_list() {
		$curator = $this->as_role( 'fmc_curator' );
		wp_insert_term( 'Norte', Taxonomies::ZONE );
		$speaker = $this->post( PostTypes::SPEAKER, $curator, 'publish', 'Ana' );
		update_post_meta( $speaker, SpeakerMetaKeys::PROFESSION, 'retired_teacher' );

		$body = $this->screen_of( $speaker )['body'];
		$this->assertCount( 1, $this->find( $body, '//select[@name="f[' . SpeakerMetaKeys::PROFESSION . ']"]/option[@value="retired_teacher"][@selected]' ) );
		$this->assertSame( 'Docente jubilado', SpeakerMetaKeys::PROFESSIONS['retired_teacher'] );
		$this->assertCount( 1, $this->find( $body, '//select[@name="f[tax:' . Taxonomies::ZONE . ']"]/option[.="Norte"]' ) );
	}

	/**
	 * «Recomendado para» solo ofrece diseños finalizados, pero no pierde lo ya elegido.
	 */
	public function test_recommended_designs_are_the_finalised_ones() {
		$curator = $this->as_role( 'fmc_curator' );
		$offered = $this->post( PostTypes::DESIGN, $curator, 'publish', 'En el catálogo' );
		$this->post( PostTypes::DESIGN, $curator, 'publish', 'Finalizado fuera del catálogo' );
		$old = $this->post( PostTypes::DESIGN, $curator, PostTypes::ARCHIVED, 'Histórico' );
		$this->post( PostTypes::DESIGN, $curator, PostTypes::ARCHIVED, 'Otro histórico' );
		$this->post( PostTypes::DESIGN, $curator, 'draft', 'Borrador' );
		update_post_meta( $offered, D::IN_CATALOGUE, '1' );
		$speaker = $this->post( PostTypes::SPEAKER, $curator );
		update_post_meta( $speaker, SpeakerMetaKeys::RECOMMENDED, array( $old ) );

		$options = array();
		foreach ( $this->find( $this->screen_of( $speaker )['body'], '//select[@name="f[' . SpeakerMetaKeys::RECOMMENDED . '][]"]/option' ) as $option ) {
			$options[] = $option->textContent; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM.
		}
		$this->assertSame( array( 'En el catálogo', 'Finalizado fuera del catálogo', 'Histórico (Histórico)' ), $options, 'el recomendado que ya era histórico se conserva' );
	}

	/**
	 * `?fmc_diseno=` solo precarga un diseño finalizado: de un borrador o un
	 * histórico no sale ni el título.
	 */
	public function test_only_a_finalised_design_is_prefilled() {
		$curator = self::factory()->user->create( array( 'role' => 'fmc_curator' ) );
		$draft   = $this->post( PostTypes::DESIGN, $curator, 'draft', 'Borrador secreto' );
		$old     = $this->post( PostTypes::DESIGN, $curator, PostTypes::ARCHIVED, 'Histórico secreto' );
		$this->as_role( 'fmc_adviser' );

		foreach ( array( $draft, $old ) as $design ) {
			$_GET[ Screen::ARG_DESIGN ] = (string) $design;
			$body                       = Editor::screen( 0, PostTypes::ACTION )['body'];
			$this->assertSame( 0, $this->find( $body, '//select[@name="f[fmc_design_id]"]/option[@selected]' )->length );
			$this->assertStringNotContainsString( 'secreto', $body );
		}
	}

	/**
	 * «Sustituye a» no ofrece el propio diseño, y a quien solo consulta no le
	 * dice el título de lo que ya no se ofrece.
	 */
	public function test_the_replaces_field_skips_itself_and_hides_what_the_reader_cannot_read() {
		$curator = $this->as_role( 'fmc_curator' );
		$design  = $this->post( PostTypes::DESIGN, $curator, 'publish', 'El nuevo' );
		$old     = $this->post( PostTypes::DESIGN, $curator, PostTypes::ARCHIVED, 'El viejo' );
		update_post_meta( $design, D::REPLACES, array( $old ) );

		$options = function () use ( $design ): array {
			$out = array();
			foreach ( $this->find( $this->screen_of( $design )['body'], '//select[@name="f[' . D::REPLACES . '][]"]/option' ) as $option ) {
				$out[ (int) $option->getAttribute( 'value' ) ] = $option->textContent; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM.
			}
			return $out;
		};

		$this->assertArrayNotHasKey( $design, $options() );
		$this->assertSame( 'El viejo (Histórico)', $options()[ $old ] );

		$this->as_role( 'fmc_adviser' );
		$this->assertSame( array( $old => 'Un diseño que ya no se ofrece' ), $options() );
	}

	/**
	 * Cada temática dice lo que engloba: al pasar por la opción, y debajo de la elegida.
	 */
	public function test_a_topic_says_what_it_covers() {
		$curator = $this->as_role( 'fmc_curator' );
		$robots  = self::factory()->term->create(
			array(
				'taxonomy'    => Taxonomies::TOPIC,
				'name'        => 'Robótica',
				'description' => '<p>Programación y <strong>robots</strong>.</p>',
			)
		);
		self::factory()->term->create(
			array(
				'taxonomy'    => Taxonomies::TOPIC,
				'name'        => 'Sin descripción',
				'description' => '',
			)
		);
		$design = $this->post( PostTypes::DESIGN, $curator, 'draft' );
		wp_set_object_terms( $design, array( $robots ), Taxonomies::TOPIC );

		$body = $this->screen_of( $design )['body'];

		$this->assertTrue( $this->find( $body, '//select[@name="f[tax:fmc_topic]"]' )->item( 0 )->hasAttribute( 'data-fmc-hints' ) );
		$this->assertSame( 'Programación y robots.', $this->find( $body, '//option[.="Robótica"]' )->item( 0 )->getAttribute( 'data-fmc-hint' ) );
		$this->assertFalse( $this->find( $body, '//option[.="Sin descripción"]' )->item( 0 )->hasAttribute( 'data-fmc-hint' ) );
		$this->assertSame( 'Programación y robots.', $this->find( $body, '//div[@data-fmc-hint-for="f-tax-fmc_topic"]' )->item( 0 )->textContent );
		$this->assertSame( 0, $this->find( $this->screen_of( $this->post( PostTypes::SPEAKER, $curator ) )['body'], '//select[@data-fmc-hints]' )->length, 'sin descripciones, nada' );
	}
}

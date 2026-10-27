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
	 * La validación, en un diseño nuevo: borrador, revisión o finalizado.
	 */
	public function test_a_new_design_for_a_curator() {
		$this->as_role( 'fmc_curator' );

		$screen = Editor::screen( 0, PostTypes::DESIGN );

		$this->assertSame( 'designs', $screen['tab'] );
		$this->assertSame( 'Nuevo diseño de acción formativa', $screen['title'] );
		$this->assertSame(
			array(
				'Guardar borrador'   => 'draft',
				'Mandar a revisión'  => 'pending',
				'Dar por finalizado' => 'publish',
			),
			$this->buttons( $screen['body'] )
		);
		$this->assertSame( '', $screen['actions'] );
		$this->assertSame( 1, $this->find( $screen['body'], '//input[@name="_fmc_nonce"]' )->length );
		$this->assertTrue( wp_verify_nonce( $this->find( $screen['body'], '//input[@name="_fmc_nonce"]' )->item( 0 )->getAttribute( 'value' ), Editor::nonce_action( PostTypes::DESIGN, 0 ) ) > 0 );
		$this->assertFalse( $this->disabled( $screen['body'], 'post_title' ) );
		$this->assertCount( count( Documents::kinds() ), iterator_to_array( $this->find( $screen['body'], '//input[@type="file"][starts-with(@name, "fmc_doc[")]' ) ) );
		$this->assertSame( 1, $this->find( $screen['body'], '//input[@name="fmc_doc[image][]"][not(@multiple)]' )->length, 'una sola imagen' );
	}

	/**
	 * La validación finaliza; en uno finalizado, guarda, lo devuelve a borrador
	 * o lo pasa a histórico; y uno histórico lo recupera (ADR-0016).
	 */
	public function test_design_buttons_for_a_curator() {
		$adviser = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$pending = $this->post( PostTypes::DESIGN, $adviser, 'pending' );
		$done    = $this->post( PostTypes::DESIGN, $adviser );
		$this->as_role( 'fmc_curator' );

		$this->assertSame(
			array(
				'Guardar borrador'   => 'draft',
				'Mandar a revisión'  => 'pending',
				'Dar por finalizado' => 'publish',
			),
			$this->buttons( $this->screen_of( $pending )['body'] )
		);

		$screen = $this->screen_of( $done );
		$this->assertSame(
			array(
				'Guardar'             => 'publish',
				'Devolver a borrador' => 'draft',
				'Pasar a histórico'   => PostTypes::ARCHIVED,
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
		$this->assertStringContainsString( 'fmc_design=' . $done, $screen['actions'] );
		// Finalizado pero fuera del catálogo: no tiene ficha pública que ver.
		$this->assertStringNotContainsString( 'Ver en el catálogo', $screen['actions'] );
		update_post_meta( $done, D::IN_CATALOGUE, '1' );
		$this->assertStringContainsString( 'fmc_curso=' . $done . '" target="_blank" rel="noopener">Ver en el catálogo</a>', $this->screen_of( $done )['actions'] );
		$this->assertStringNotContainsString( 'fmc_delete', $screen['actions'], 'la validación no borra' );

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
		$this->assertStringContainsString( 'Solo lectura', $screen['body'] );
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
		$action  = $this->post( PostTypes::ACTION, $adviser );

		$body = $this->screen_of( $action )['body'];
		$this->assertTrue( $this->disabled( $body, A::FILE_NUMBER ) );
		$this->assertTrue( $this->disabled( $body, A::SITUATION ) );
		$this->assertFalse( $this->disabled( $body, A::PLACES ) );
		$this->assertStringContainsString( 'Lo rellena el servicio de formación.', $body );
		$this->assertStringContainsString( '¿No está en la lista?', $body, 'puede crear ponentes' );

		$this->as_role( 'fmc_training_service' );
		$body = $this->screen_of( $action )['body'];
		$this->assertFalse( $this->disabled( $body, A::FILE_NUMBER ) );
		$this->assertTrue( $this->disabled( $body, A::PLACES ) );
		$this->assertTrue( $this->disabled( $body, A::SUBTITLE ) );
		$this->assertStringNotContainsString( 'Lo rellena el servicio de formación.', $body );
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
		update_post_meta( $approved, I::RESOLUTION, 'approved' );
		update_post_meta( $approved, I::REASON, 'Cambio de ponente por enfermedad' );
		self::factory()->post->create(
			array(
				'post_type'   => PostTypes::INCIDENT,
				'post_parent' => $action,
				'post_status' => 'publish',
			)
		);

		$items = $this->find( $this->screen_of( $action )['body'], '//section//li' );
		$this->assertSame( 2, $items->length );
		$text = $items->item( 0 )->textContent . $items->item( 1 )->textContent;
		$this->assertStringContainsString( 'Cambio de ponente por enfermedad', $text );
		$this->assertStringContainsString( 'Aprobada', $text );
		$this->assertStringContainsString( 'Pendiente', $text );

		$this->as_role( 'fmc_training_service' );
		$this->assertStringNotContainsString( 'Abrir una incidencia', $this->screen_of( $action )['body'] );
	}

	/**
	 * Una acción nueva desde un diseño trae su modalidad y sus horas.
	 */
	public function test_a_new_action_from_a_design_is_prefilled() {
		$this->as_role( 'fmc_adviser' );
		$design = $this->post( PostTypes::DESIGN, 1 );
		update_post_meta( $design, D::MODALITY, 'blended' );
		update_post_meta( $design, D::HOURS_ONSITE, 9 );
		update_post_meta( $design, D::HOURS_ONLINE, 3 );
		$_GET['fmc_design'] = (string) $design;

		$body = Editor::screen( 0, PostTypes::ACTION )['body'];

		$this->assertSame( (string) $design, $this->find( $body, '//select[@name="f[fmc_design_id]"]/option[@selected]' )->item( 0 )->getAttribute( 'value' ) );
		$this->assertSame( 'blended', $this->find( $body, '//select[@name="f[fmc_modality]"]/option[@selected]' )->item( 0 )->getAttribute( 'value' ) );
		$this->assertSame( '9', $this->find( $body, '//input[@name="f[fmc_hours_onsite]"]' )->item( 0 )->getAttribute( 'value' ) );
		$this->assertSame( array( 'Guardar' => '' ), $this->buttons( $body ) );
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
	}

	/**
	 * Quién agenda acciones.
	 *
	 * @return array<string, array{string}>
	 */
	public function design_choosers(): array {
		return array(
			'asesoría'   => array( 'fmc_adviser' ),
			'validación' => array( 'fmc_curator' ),
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

		$this->assertStringContainsString( 'Aún no hay ninguna asesoría con cuenta en el aplicativo', Editor::screen( 0, PostTypes::ACTION )['body'] );

		$this->as_role( 'fmc_curator' );
		$this->assertStringNotContainsString( 'Aún no hay ninguna asesoría', Editor::screen( 0, PostTypes::ACTION )['body'] );
	}

	/**
	 * Las asesorías salen para elegir; las listas largas llevan buscador.
	 */
	public function test_adviser_and_term_choices() {
		$curator = $this->as_role( 'fmc_curator' );
		$this->factory()->user->create( array( 'role' => 'fmc_training_service' ) );
		for ( $i = 0; $i < 11; $i++ ) {
			self::factory()->term->create( array( 'taxonomy' => 'fmc_scope' ) );
		}

		$body = Editor::screen( 0, PostTypes::ACTION )['body'];

		$advisers = $this->find( $body, '//select[@name="f[fmc_adviser]"]/option[@value!=""]' );
		$this->assertSame( 1, $advisers->length, 'el servicio de formación no es asesoría' );
		$this->assertSame( (string) $curator, $advisers->item( 0 )->getAttribute( 'value' ) );
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
		$this->assertTrue( $this->disabled( $body, I::RESOLUTION ), 'la resolución es del servicio' );
		$this->assertSame( 3, $this->find( $body, '//input[@type="radio"][@name="f[fmc_resolution]"]' )->length );
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
		$this->assertStringContainsString( 'Cambiar la imagen', $body );
		$this->assertStringContainsString( 'Sistema anterior', $body );
		$this->assertStringContainsString( '>apoyo curso.zip</a>', $body );
		$this->assertSame( 2, substr_count( $body, 'Ninguno.' ), 'minutaje y otros, vacíos' );
		$this->assertSame( '.pdf,.doc,.docx,.odt', $this->find( $body, '//input[@name="fmc_doc[design][]"]' )->item( 0 )->getAttribute( 'accept' ) );

		// La asesoría, en el diseño finalizado, abre los materiales y no cambia nada (ADR-0017).
		wp_publish_post( $design );
		$this->as_role( 'fmc_adviser' );
		$body = $this->screen_of( $design )['body'];
		$this->assertStringContainsString( '>diseno.pdf</a>', $body );
		$this->assertSame( 0, $this->find( $body, '//input[@type="file"]' )->length );
		$this->assertSame( 0, $this->find( $body, '//input[@name="fmc_remove[]"]' )->length );
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
		$this->assertStringContainsString( '>Abrir en elidd</a>', $body );
		$this->assertStringContainsString( 'data-fmc-tool="f-' . D::DIGCOMP_URL . '" data-fmc-tool-url="https://elidd.ernesto.es/">Elegir los indicadores en elidd</button>', $body );

		// Quien despliega puede usar su propia copia de elidd.
		$copy = static fn() => 'https://copia.example/elidd/';
		add_filter( 'fmc_digcomp_tool', $copy );
		$this->assertStringContainsString( 'data-fmc-tool-url="https://copia.example/elidd/"', $this->screen_of( $design )['body'] );
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
		$this->assertSame( array( '★ Zoe', 'Ana · Norte · 1 acción(es)', 'Bea · No disponible' ), $options );

		// Lo que lee fmc-app.js: el diseño del que depende la estrella, para
		// qué diseños se recomienda a cada cual, quién no está y sus observaciones.
		$this->assertCount( 1, $this->find( $body, '//select[@data-fmc-speakers="f[' . A::DESIGN_ID . ']"]' ) );
		$this->assertCount( 1, $this->find( $body, '//option[@value="' . $zoe . '"][@data-fmc-for="' . $design . '"]' ) );
		$this->assertCount( 1, $this->find( $body, '//option[@value="' . $bea . '"][@data-fmc-off="1"][@data-fmc-note="De baja hasta junio"]' ) );
		$this->assertCount( 1, $this->find( $body, '//option[@value="' . $ana . '"][@data-fmc-name="Ana"][@data-fmc-zone="Norte"][@data-fmc-given="1 acción(es)"]' ) );
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
	 * `?fmc_design=` solo precarga un diseño finalizado: de un borrador o un
	 * histórico no sale ni el título.
	 */
	public function test_only_a_finalised_design_is_prefilled() {
		$curator = self::factory()->user->create( array( 'role' => 'fmc_curator' ) );
		$draft   = $this->post( PostTypes::DESIGN, $curator, 'draft', 'Borrador secreto' );
		$old     = $this->post( PostTypes::DESIGN, $curator, PostTypes::ARCHIVED, 'Histórico secreto' );
		$this->as_role( 'fmc_adviser' );

		foreach ( array( $draft, $old ) as $design ) {
			$_GET['fmc_design'] = (string) $design;
			$body               = Editor::screen( 0, PostTypes::ACTION )['body'];
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

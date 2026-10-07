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
use Fmc\PostType\PostTypes;
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
		$this->assertTrue( Editor::can_view( get_post( $this->post( PostTypes::DESIGN, $adviser ) ) ), 'un diseño finalizado lo lee cualquiera' );
		$this->assertFalse( Editor::can_view( get_post( $this->post( PostTypes::DESIGN, $adviser, 'draft' ) ) ), 'el borrador de otra, no' );
		$this->assertFalse( Editor::can_view( $incident ) );
	}

	/**
	 * La asesoría, en un diseño nuevo: borrador y revisión, sin «finalizar».
	 */
	public function test_a_new_design_for_an_adviser() {
		$this->as_role( 'fmc_adviser' );

		$screen = Editor::screen( 0, PostTypes::DESIGN );

		$this->assertSame( 'designs', $screen['tab'] );
		$this->assertSame( 'Nuevo: ' . mb_strtolower( get_post_type_object( PostTypes::DESIGN )->labels->singular_name ), $screen['title'] );
		$this->assertSame(
			array(
				'Guardar borrador'  => 'draft',
				'Mandar a revisión' => 'pending',
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
	 * La curaduría finaliza; y en uno finalizado, guarda o lo devuelve a borrador.
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
			),
			$this->buttons( $screen['body'] )
		);
		$this->assertStringContainsString( 'Finalizado', $screen['badges'] );
		$this->assertStringContainsString( 'Crear una acción con este diseño', $screen['actions'] );
		$this->assertStringContainsString( 'fmc_design=' . $done, $screen['actions'] );
		$this->assertStringContainsString( 'Ver la ficha pública', $screen['actions'] );
		$this->assertStringNotContainsString( 'fmc_delete', $screen['actions'], 'la curaduría no borra' );

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
		$this->assertStringContainsString( 'Crear una acción', $screen['actions'], 'sí puede impartirlo' );

		$this->as_role( 'fmc_training_service' );
		$this->assertStringNotContainsString( 'Crear una acción', $this->screen_of( $design )['actions'], 'el servicio no crea acciones' );
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
		$this->assertTrue( $this->disabled( $body, 'post_title' ) );
		$this->assertStringNotContainsString( 'Lo rellena el servicio de formación.', $body );
		$this->assertStringNotContainsString( '¿No está en la lista?', $body );
		$this->assertSame( array( 'Guardar' => '' ), $this->buttons( $body ), 'tiene algo que guardar' );
	}

	/**
	 * La acción enseña sus totales calculados y su curso escolar.
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
				A::ENROLLED_MEN   => 10,
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
		$this->assertSame( '22', $counters['Matriculados'] );
		$this->assertSame( '0', $counters['Asistentes'] );
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
	 * La lista de diseños para elegir: los finalizados y los propios, con código, tipo y estado.
	 */
	public function test_the_design_choices_of_an_adviser() {
		$other   = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$adviser = $this->as_role( 'fmc_adviser' );
		$public  = $this->post( PostTypes::DESIGN, $other, 'publish', 'Robótica' );
		update_post_meta( $public, D::CODE, 'A247' );
		update_post_meta( $public, D::TYPE, 'course' );
		$mine = $this->post( PostTypes::DESIGN, $adviser, 'draft', 'Mi borrador' );
		$this->post( PostTypes::DESIGN, $other, 'draft', 'Borrador ajeno' );

		$options = array();
		foreach ( $this->find( Editor::screen( 0, PostTypes::ACTION )['body'], '//select[@name="f[fmc_design_id]"]/option[@value!=""]' ) as $option ) {
			$options[ (int) $option->getAttribute( 'value' ) ] = $option->textContent; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM.
		}

		$this->assertSame(
			array(
				$mine   => 'Mi borrador (Borrador)',
				$public => 'A247 · Robótica (Curso)',
			),
			$options
		);
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
		$adviser = $this->as_role( 'fmc_adviser' );
		$design  = $this->post( PostTypes::DESIGN, $adviser, 'draft' );
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
			array( 'post_title' => 'Falta rellenar «Título del curso».' )
		);

		$this->assertSame( 'Falta rellenar «Título del curso».', $this->find( $screen['body'], '//div[contains(@class,"alert-danger")]//li' )->item( 0 )->textContent );
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
		$adviser = $this->as_role( 'fmc_adviser' );
		$design  = $this->post( PostTypes::DESIGN, $adviser, 'draft' );
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
	}

	/**
	 * Subir documentos a un diseño nuevo pide poder subir ficheros y crear diseños.
	 */
	public function test_who_can_add_documents_to_a_new_design() {
		$this->as_role( 'fmc_adviser' );
		$this->assertTrue( Editor::can_write( PostTypes::DESIGN, 'docs', 0 ) );

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
}

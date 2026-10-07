<?php
/**
 * Tests for the front page routing, the document and its chrome.
 *
 * @package Fmc
 */

use Fmc\PostType\PostTypes;
use Fmc\PublicFront\Assets;
use Fmc\PublicFront\Screen;

/**
 * La portada del sitio es el aplicativo: quién entra, qué pantalla ve y qué
 * documento recibe.
 *
 * `Screen::route()` acaba en `exit`: los tests lo paran justo antes, con un
 * filtro que lanza una excepción —en la redirección, o al escapar el título
 * del documento, que es lo último que pasa antes del `echo`—.
 */
class Test_Screen extends WP_UnitTestCase {

	/**
	 * Roles and caps in place, as a provisioned site has them.
	 */
	public function set_up() {
		parent::set_up();
		fmc_register_roles();
		PostTypes::grant_caps_to_roles();
	}

	/**
	 * Clean request and user.
	 */
	public function tear_down() {
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * A user with a role, logged in.
	 *
	 * @param string $role Role slug.
	 * @param array  $args Extra user fields.
	 * @return int
	 */
	private function as_role( string $role, array $args = array() ): int {
		$id = self::factory()->user->create( array( 'role' => $role ) + $args );
		wp_set_current_user( $id );
		return $id;
	}

	/**
	 * Visit the front page with a query string.
	 *
	 * @param array<string, scalar> $args Query args.
	 * @return void
	 */
	private function visit( array $args = array() ): void {
		$this->go_to( add_query_arg( $args, home_url( '/' ) ) );
	}

	/**
	 * Run the router and report where it stopped.
	 *
	 * Devuelve `redirect:<url>`, `document:<título escapado>`, `csv`, o
	 * `returned` si el enrutador no se ha quedado con la petición.
	 *
	 * @return string
	 */
	private function route(): string {
		$redirect = static function ( $location ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- lo lee el test, no se pinta.
			throw new RuntimeException( 'redirect:' . $location );
		};
		$document = static function ( $safe ) {
			if ( str_ends_with( (string) $safe, ' · Formación' ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- lo lee el test, no se pinta.
				throw new RuntimeException( 'document:' . substr( $safe, 0, -strlen( ' · Formación' ) ) );
			}
			return $safe;
		};
		add_filter( 'wp_redirect', $redirect );
		add_filter( 'esc_html', $document );
		try {
			Screen::route();
			$stop = 'returned';
		} catch ( PHPUnit\Framework\Error\Warning $e ) {
			// En los tests la salida ya ha empezado: el primer `header()` de la
			// descarga en CSV avisa, y es justo ahí donde se para.
			$stop = str_starts_with( $e->getMessage(), 'Cannot modify header information' ) ? 'csv' : 'warning:' . $e->getMessage();
		} catch ( RuntimeException $e ) {
			$stop = $e->getMessage();
		} finally {
			remove_filter( 'wp_redirect', $redirect );
			remove_filter( 'esc_html', $document );
		}
		return $stop;
	}

	/**
	 * The query args of a URL.
	 *
	 * @param string $url URL.
	 * @return array<string, string>
	 */
	private function query_of( string $url ): array {
		$query = array();
		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		return $query;
	}

	/**
	 * Run the router, expect a redirect and return its query args.
	 *
	 * @return array<string, string>
	 */
	private function redirect_query(): array {
		$stop = $this->route();
		$this->assertStringStartsWith( 'redirect:', $stop );
		return $this->query_of( substr( $stop, strlen( 'redirect:' ) ) );
	}

	/**
	 * A minimal screen.
	 *
	 * @param array<string, string> $extra Extra keys.
	 * @return array<string, string>
	 */
	private function screen( array $extra = array() ): array {
		return $extra + array(
			'tab'   => 'designs',
			'title' => 'Diseños de curso',
			'body'  => '<p id="cuerpo">Cuerpo</p>',
		);
	}

	/**
	 * The router hooks early on template_redirect, before any theme.
	 */
	public function test_register_hooks_the_router_and_the_login_redirect() {
		Screen::register();
		$this->assertSame( 1, has_action( 'template_redirect', array( Screen::class, 'route' ) ) );
		$this->assertSame( 10, has_filter( 'login_redirect', array( Screen::class, 'login_redirect' ) ) );
	}

	/**
	 * Sin sesión, la portada manda al acceso y vuelve a la portada después.
	 */
	public function test_the_front_page_without_a_session_redirects_to_login() {
		$this->visit();
		$stop = $this->route();

		$this->assertStringStartsWith( 'redirect:' . wp_login_url(), $stop );
		$this->assertSame( home_url( '/' ), $this->query_of( substr( $stop, strlen( 'redirect:' ) ) )['redirect_to'] );
	}

	/**
	 * El aplicativo solo es dueño de la portada: el resto del sitio sigue al tema.
	 */
	public function test_the_router_leaves_other_pages_alone() {
		$design = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::DESIGN,
				'post_status' => 'publish',
			)
		);
		$this->go_to( get_permalink( $design ) );

		$this->assertSame( 'returned', $this->route() );
	}

	/**
	 * Con sesión y sin pestaña, se abre la primera que le toca: el calendario a la curaduría.
	 */
	public function test_without_a_tab_the_first_allowed_tab_opens() {
		$this->as_role( 'fmc_curator' );
		$this->visit();

		$this->assertStringStartsWith( 'document:Calendario de ', $this->route() );
	}

	/**
	 * Cada pestaña abre su listado.
	 */
	public function test_each_tab_opens_its_own_list() {
		$this->as_role( 'fmc_curator' );
		$expected = array(
			'actions'   => 'Acciones formativas',
			'designs'   => 'Diseños de curso',
			'speakers'  => 'Ponentes',
			'incidents' => 'Incidencias',
		);
		foreach ( $expected as $tab => $title ) {
			$this->visit( array( Screen::ARG_TAB => $tab ) );
			$this->assertSame( 'document:' . $title, $this->route(), $tab );
		}
	}

	/**
	 * Una pestaña desconocida, o que no le toca, cae en la primera que sí.
	 */
	public function test_an_unknown_or_forbidden_tab_falls_back_to_the_first_allowed() {
		$this->as_role( 'fmc_curator' );
		$this->visit( array( Screen::ARG_TAB => 'no-existe' ) );
		$this->assertStringStartsWith( 'document:Calendario de ', $this->route() );

		// Sin acciones ni ponentes, la única pestaña es la del catálogo.
		$this->as_role( 'subscriber' );
		$this->visit( array( Screen::ARG_TAB => 'speakers' ) );
		$this->assertSame( 'document:Diseños de curso', $this->route() );
	}

	/**
	 * `?fmc_edit` abre la ficha; `?fmc_new`, una ficha vacía; lo que no existe, el aviso.
	 */
	public function test_edit_and_new_open_the_editor() {
		$this->as_role( 'fmc_curator' );
		$design = self::factory()->post->create(
			array(
				'post_type'  => PostTypes::DESIGN,
				'post_title' => 'Robótica en el aula',
			)
		);

		$this->visit( array( Screen::ARG_EDIT => $design ) );
		$this->assertSame( 'document:Robótica en el aula', $this->route() );

		$this->visit( array( Screen::ARG_NEW => PostTypes::DESIGN ) );
		$this->assertSame( 'document:Nuevo: diseño de curso', $this->route() );

		$this->visit( array( Screen::ARG_EDIT => $design + 1000 ) );
		$this->assertSame( 'document:No disponible', $this->route() );
	}

	/**
	 * La descarga en CSV sale de una pestaña que le toca, y de ninguna otra.
	 */
	public function test_the_csv_export_needs_an_allowed_tab() {
		$this->as_role( 'fmc_curator' );
		$this->visit(
			array(
				Screen::ARG_TAB => 'designs',
				'fmc_csv'       => '1',
			)
		);
		$this->assertSame( 'csv', $this->route() );

		$this->visit(
			array(
				Screen::ARG_TAB => 'no-existe',
				'fmc_csv'       => '1',
			)
		);
		$this->assertStringStartsWith( 'document:Calendario de ', $this->route() );
	}

	/**
	 * Un guardado con un tipo inventado no llega a ningún sitio.
	 */
	public function test_a_save_with_an_unknown_type_is_denied() {
		$this->as_role( 'fmc_curator' );
		$this->visit();
		$_POST['fmc_save'] = '1';
		$_POST['fmc_type'] = 'post';

		$this->assertSame( 'denied', $this->redirect_query()[ Screen::ARG_NOTICE ] ?? '' );
	}

	/**
	 * La administración envía a la papelera y vuelve al listado del tipo, fuera del panel.
	 */
	public function test_administration_trashes_a_post_and_returns_to_its_list() {
		$this->as_role( 'administrator' );
		$action = self::factory()->post->create( array( 'post_type' => PostTypes::ACTION ) );
		$this->visit();
		$_POST['fmc_delete']    = (string) $action;
		$_REQUEST['_fmc_nonce'] = wp_create_nonce( 'fmc_delete_' . $action );
		$_REQUEST['fmc_marco']  = '1';

		$query = $this->redirect_query();
		$this->assertSame( 'actions', $query[ Screen::ARG_TAB ] );
		$this->assertSame( 'trashed', $query[ Screen::ARG_NOTICE ] );
		$this->assertArrayNotHasKey( Screen::ARG_FRAME, $query, 'borrar recarga la página entera' );
		$this->assertSame( 'trash', get_post_status( $action ) );
	}

	/**
	 * La curaduría puede borrar por capacidad, pero borrar es solo de la administración.
	 */
	public function test_only_administration_may_delete() {
		$this->as_role( 'fmc_curator' );
		$design = self::factory()->post->create( array( 'post_type' => PostTypes::DESIGN ) );
		$this->assertTrue( current_user_can( 'delete_post', $design ) );
		$this->assertFalse( Screen::can_delete( get_post( $design ) ) );

		$this->visit();
		$_POST['fmc_delete']    = (string) $design;
		$_REQUEST['_fmc_nonce'] = wp_create_nonce( 'fmc_delete_' . $design );

		$this->assertSame( 'denied', $this->redirect_query()[ Screen::ARG_NOTICE ] ?? '' );
		$this->assertSame( 'publish', get_post_status( $design ) );
	}

	/**
	 * Ni la administración borra desde aquí lo que no es del aplicativo.
	 */
	public function test_a_post_outside_the_application_is_not_deleted() {
		$this->as_role( 'administrator' );
		$post = self::factory()->post->create();
		$this->visit();
		$_POST['fmc_delete']    = (string) $post;
		$_REQUEST['_fmc_nonce'] = wp_create_nonce( 'fmc_delete_' . $post );

		$this->assertSame( 'denied', $this->redirect_query()[ Screen::ARG_NOTICE ] ?? '' );
		$this->assertSame( 'publish', get_post_status( $post ) );
	}

	/**
	 * Sin un nonce válido no se borra nada.
	 */
	public function test_deleting_without_a_valid_nonce_dies() {
		$this->as_role( 'administrator' );
		$design = self::factory()->post->create( array( 'post_type' => PostTypes::DESIGN ) );
		$this->visit();
		$_POST['fmc_delete']    = (string) $design;
		$_REQUEST['_fmc_nonce'] = wp_create_nonce( 'fmc_delete_' . ( $design + 1 ) );

		try {
			$this->route();
			$this->fail( 'Se esperaba wp_die().' );
		} catch ( WPDieException $e ) {
			$this->assertSame( 'publish', get_post_status( $design ) );
		}
	}

	/**
	 * El botón de borrar lleva su nonce, el ID y la pregunta, escapada.
	 */
	public function test_the_delete_form_carries_its_nonce_and_an_escaped_question() {
		$this->as_role( 'administrator' );
		$design = self::factory()->post->create(
			array(
				'post_type'  => PostTypes::DESIGN,
				'post_title' => 'Uso "seguro" <de> redes',
			)
		);

		$html = Screen::delete_form( get_post( $design ), 'btn-sm' );

		$this->assertSame( 1, preg_match( '/name="_fmc_nonce" value="([^"]+)"/', $html, $m ) );
		$this->assertSame( 1, wp_verify_nonce( $m[1], 'fmc_delete_' . $design ) );
		$this->assertStringContainsString( 'name="fmc_delete" value="' . $design . '"', $html );
		$this->assertStringContainsString( 'method="post"', $html );
		$this->assertStringContainsString( 'btn-outline-danger btn-sm', $html );
		$this->assertStringNotContainsString( '<de>', $html );
		$this->assertStringContainsString( '&lt;de&gt;', $html );
	}

	/**
	 * Las pestañas dependen del rol, y siempre en el mismo orden.
	 */
	public function test_tabs_depend_on_the_role() {
		$expected = array(
			'administrator'        => array( 'calendar', 'actions', 'designs', 'speakers', 'incidents' ),
			'fmc_curator'          => array( 'calendar', 'actions', 'designs', 'speakers', 'incidents' ),
			'fmc_adviser'          => array( 'calendar', 'actions', 'designs', 'speakers', 'incidents' ),
			// El servicio lee acciones e incidencias, pero los ponentes no son cosa suya.
			'fmc_training_service' => array( 'calendar', 'actions', 'designs', 'incidents' ),
			'subscriber'           => array( 'designs' ),
		);
		foreach ( $expected as $role => $tabs ) {
			$this->as_role( $role );
			$this->assertSame( $tabs, array_keys( Screen::tabs() ), $role );
		}
	}

	/**
	 * Cada tipo vuelve a su pestaña; lo desconocido, al catálogo.
	 */
	public function test_each_type_belongs_to_a_tab() {
		$this->assertSame( 'actions', Screen::tab_of( PostTypes::ACTION ) );
		$this->assertSame( 'designs', Screen::tab_of( PostTypes::DESIGN ) );
		$this->assertSame( 'speakers', Screen::tab_of( PostTypes::SPEAKER ) );
		$this->assertSame( 'incidents', Screen::tab_of( PostTypes::INCIDENT ) );
		$this->assertSame( 'designs', Screen::tab_of( 'post' ) );
	}

	/**
	 * Las URL cuelgan de la portada, sin argumentos vacíos, y dentro del panel siguen en él.
	 */
	public function test_urls_drop_empty_args_and_keep_the_frame() {
		$url = Screen::url(
			array(
				Screen::ARG_TAB  => 'designs',
				Screen::ARG_EDIT => '',
			)
		);
		$this->assertSame( add_query_arg( Screen::ARG_TAB, 'designs', home_url( '/' ) ), $url );
		$this->assertFalse( Screen::framed() );

		$_REQUEST[ Screen::ARG_FRAME ] = '1';
		$this->assertTrue( Screen::framed() );
		$this->assertSame( '1', $this->query_of( Screen::url( array( Screen::ARG_TAB => 'designs' ) ) )[ Screen::ARG_FRAME ] ?? '' );
		$this->assertArrayNotHasKey( Screen::ARG_FRAME, $this->query_of( Screen::url( array( Screen::ARG_FRAME => '' ) ) ) );

		$_REQUEST[ Screen::ARG_FRAME ] = 'true';
		$this->assertFalse( Screen::framed() );
	}

	/**
	 * Tras entrar sin destino, o con el escritorio por destino, se aterriza en el aplicativo.
	 */
	public function test_login_without_a_destination_lands_on_the_application() {
		$this->assertSame( home_url( '/' ), Screen::login_redirect( admin_url(), '' ) );
		$this->assertSame( home_url( '/' ), Screen::login_redirect( admin_url(), admin_url() ) );
		$this->assertSame( home_url( '/?fmc_tab=actions' ), Screen::login_redirect( home_url( '/?fmc_tab=actions' ), home_url( '/?fmc_tab=actions' ) ) );
	}

	/**
	 * El rótulo del rol del aplicativo, o nada.
	 */
	public function test_role_label() {
		$label = static fn( string $role ) => Screen::role_label( get_userdata( self::factory()->user->create( array( 'role' => $role ) ) ) );

		$this->assertSame( 'Administración', $label( 'administrator' ) );
		$this->assertSame( 'Curaduría de formación', $label( 'fmc_curator' ) );
		$this->assertSame( 'Servicio de formación', $label( 'fmc_training_service' ) );
		$this->assertSame( '', $label( 'subscriber' ) );
	}

	/**
	 * El documento trae las librerías de jsDelivr con SRI, en la versión de `package.json`.
	 */
	public function test_the_document_loads_its_libraries_from_jsdelivr_with_sri() {
		$this->as_role( 'fmc_curator' );
		$html = Screen::document( $this->screen() );

		$this->assertStringStartsWith( '<!doctype html><html lang="es">', $html );
		$this->assertStringEndsWith( '</body></html>', $html );

		$this->assertSame( 4, preg_match_all( '/<(?:script|link)\b[^>]*cdn\.jsdelivr\.net[^>]*>/', $html, $tags ) );
		foreach ( $tags[0] as $tag ) {
			$this->assertMatchesRegularExpression( '/ integrity="sha384-[A-Za-z0-9+\/]{64}"/', $tag );
			$this->assertStringContainsString( ' crossorigin="anonymous"', $tag );
		}
		// Cada librería del CDN está en `package.json` con la misma versión exacta:
		// es la que se instala en desarrollo y la que se prueba (ADR-0002).
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichero del repositorio.
		$package  = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/package.json' ), true );
		$versions = ( $package['dependencies'] ?? array() ) + ( $package['devDependencies'] ?? array() );
		preg_match_all( '~cdn\.jsdelivr\.net/npm/([^@/]+)@([^/]+)/~', $html, $libraries, PREG_SET_ORDER );
		$this->assertSame( array( 'bootstrap', 'tom-select', 'sweetalert2' ), array_values( array_unique( array_column( $libraries, 1 ) ) ) );
		foreach ( $libraries as list( , $library, $version ) ) {
			$this->assertSame( $version, $versions[ $library ] ?? null, $library . ' no tiene en package.json la versión del CDN' );
		}
	}

	/**
	 * Lo que añade quien despliega llega por `fmc_chrome`, que de serie no añade nada.
	 */
	public function test_the_deployment_chrome_is_empty_unless_filtered() {
		$this->as_role( 'fmc_curator' );
		$plain = Screen::document( $this->screen() );
		$this->assertStringContainsString( '</style></head>', $plain );
		$this->assertStringContainsString( '<div class="container">Aplicativo de formación</div></footer>', $plain );

		$chrome = static fn() => array(
			'head'   => '<meta name="fmc-analitica" content="ejemplo">',
			'footer' => ' · <a href="https://example.org/aviso-legal">Aviso legal</a>',
		);
		add_filter( 'fmc_chrome', $chrome );
		$html   = Screen::document( $this->screen() );
		remove_filter( 'fmc_chrome', $chrome );

		$this->assertStringContainsString( '<meta name="fmc-analitica" content="ejemplo"></head>', $html );
		$this->assertStringContainsString( 'Aplicativo de formación · <a href="https://example.org/aviso-legal">Aviso legal</a></div></footer>', $html );

		// Un filtro que devuelve solo una de las dos piezas no rompe la otra.
		$partial = static fn() => array( 'footer' => 'Pie' );
		add_filter( 'fmc_chrome', $partial );
		$this->assertSame(
			array(
				'head'   => '',
				'footer' => 'Pie',
			),
			Screen::deployment_chrome()
		);
		remove_filter( 'fmc_chrome', $partial );
	}

	/**
	 * La hoja de estilos y el guion del aplicativo van dentro del documento.
	 */
	public function test_the_document_inlines_the_application_assets() {
		$this->as_role( 'fmc_curator' );
		$html = Screen::document( $this->screen() );

		$this->assertNotSame( '', Assets::css() );
		$this->assertStringContainsString( '<style>' . Assets::css() . '</style>', $html );
		$this->assertStringContainsString( '<script>' . Assets::contents( 'js/fmc-app.js' ) . '</script>', $html );
		$this->assertStringContainsString( includes_url( 'js/tinymce/tinymce.min.js' ), $html );
	}

	/**
	 * Cabecera con el nombre y el rol, pestañas del rol y la activa marcada.
	 */
	public function test_the_document_has_the_header_and_the_role_tabs() {
		$this->as_role( 'fmc_training_service' );
		// WordPress limpia el nombre al guardarlo; aquí se mira que la cabecera lo escape igualmente.
		wp_get_current_user()->display_name = 'ana <Ruiz>';
		$html                               = Screen::document( $this->screen( array( 'tab' => 'actions' ) ) );

		$this->assertStringContainsString( '<header class="fmc-top">', $html );
		$this->assertStringContainsString( '<strong>ana &lt;Ruiz&gt;</strong>', $html );
		$this->assertStringNotContainsString( '<Ruiz>', $html );
		$this->assertStringContainsString( '>A</span>', $html );
		$this->assertStringContainsString( 'Servicio de formación', $html );
		$this->assertStringContainsString( esc_url( wp_logout_url( home_url( '/' ) ) ), $html );
		$this->assertStringNotContainsString( '>Escritorio</a>', $html, 'el escritorio es solo de la administración' );

		$this->assertSame( 4, substr_count( $html, 'class="nav-item"' ) );
		$this->assertStringNotContainsString( '>Ponentes</a>', $html );
		$this->assertSame( 1, substr_count( $html, 'aria-current="page"' ) );
		$this->assertStringContainsString( 'nav-link active" aria-current="page" href="' . esc_url( Screen::url( array( Screen::ARG_TAB => 'actions' ) ) ) . '">Acciones formativas</a>', $html );
	}

	/**
	 * La administración ve el enlace al escritorio.
	 */
	public function test_administration_sees_the_dashboard_link() {
		$this->as_role( 'administrator' );
		$html = Screen::document( $this->screen() );

		$this->assertStringContainsString( '<a href="' . esc_url( admin_url() ) . '">Escritorio</a>', $html );
		$this->assertStringContainsString( 'Administración', $html );
	}

	/**
	 * Título y subtítulo se escapan; el cuerpo, ya construido escapado, entra tal cual.
	 */
	public function test_the_document_escapes_title_and_subtitle() {
		$this->as_role( 'fmc_curator' );
		$html = Screen::document(
			$this->screen(
				array(
					'title'    => '<script>alert(1)</script>',
					'subtitle' => 'A & <b>B</b>',
				)
			)
		);

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '<title>&lt;script&gt;alert(1)&lt;/script&gt; · Formación</title>', $html );
		$this->assertStringContainsString( '<p class="text-secondary mb-0">A &amp; &lt;b&gt;B&lt;/b&gt;</p>', $html );
		$this->assertStringContainsString( '<main class="container py-4"><p id="cuerpo">Cuerpo</p></main>', $html );

		$this->assertStringNotContainsString( 'text-secondary mb-0', Screen::document( $this->screen() ), 'sin subtítulo no hay párrafo' );
	}

	/**
	 * `fmc_marco=1` devuelve la ficha sola: sin documento, sin cabecera, sin librerías.
	 */
	public function test_the_side_panel_gets_a_bare_fragment() {
		$this->as_role( 'fmc_curator' );
		$_REQUEST[ Screen::ARG_FRAME ] = '1';

		$html = Screen::document( $this->screen( array( 'title' => 'Ficha "uno"' ) ) );

		$this->assertSame( '<div class="fmc-fragment" data-fmc-title="Ficha &quot;uno&quot;"><p id="cuerpo">Cuerpo</p></div>', $html );

		$html = Screen::document(
			$this->screen(
				array(
					'badges'  => '<span class="badge">Borrador</span>',
					'actions' => '<a id="accion">Ver</a>',
				)
			)
		);
		$this->assertStringContainsString( '<span class="badge">Borrador</span><span class="ms-auto"><a id="accion">Ver</a></span>', $html );
		$this->assertStringNotContainsString( 'fmc-top', $html );
		$this->assertStringNotContainsString( 'cdn.jsdelivr.net', $html );
	}

	/**
	 * El aviso que deja una redirección sale una vez, y solo si es uno de los conocidos.
	 */
	public function test_notices_after_a_redirect() {
		$this->as_role( 'fmc_curator' );

		$_GET[ Screen::ARG_NOTICE ] = 'denied';
		$this->assertStringContainsString( '<div class="alert alert-danger" role="status">No tiene permiso para hacer eso.</div>', Screen::document( $this->screen() ) );

		$_GET[ Screen::ARG_NOTICE ] = 'saved';
		$this->assertStringContainsString( '<div class="alert alert-success" role="status">Guardado.</div>', Screen::fragment( $this->screen() ) );

		$_GET[ Screen::ARG_NOTICE ] = '<b>inventado</b>';
		$this->assertStringNotContainsString( 'class="alert', Screen::document( $this->screen() ) );
	}

	/**
	 * Los documentos que no se subieron se dicen una vez, escapados.
	 */
	public function test_upload_problems_are_shown_once_and_escaped() {
		$user = $this->as_role( 'fmc_adviser' );
		set_transient( 'fmc_problems_' . $user, array( '<b>guion.exe</b>: tipo no permitido' ), 60 );

		$html = Screen::fragment( $this->screen() );

		$this->assertStringContainsString( '<div class="alert alert-warning" role="alert">', $html );
		$this->assertStringContainsString( '<li>&lt;b&gt;guion.exe&lt;/b&gt;: tipo no permitido</li>', $html );
		$this->assertFalse( get_transient( 'fmc_problems_' . $user ) );
		$this->assertStringNotContainsString( 'alert-warning', Screen::fragment( $this->screen() ) );
	}
}

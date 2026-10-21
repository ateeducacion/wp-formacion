<?php
/**
 * The application lives on the site front page: routing and chrome.
 *
 * @package Fmc
 */

namespace Fmc\PublicFront;

use Fmc\Meta\SpeakerMetaKeys;
use Fmc\PostType\PostTypes;

/**
 * La portada del sitio es el aplicativo.
 *
 * Sin sesión, al acceso. Con sesión, un documento propio —no el del tema—,
 * con la cabecera y las pestañas del aplicativo de eventos. Cada pantalla es
 * un parámetro de la portada (`?fmc_tab=calendar`, `?fmc_edit=123`): no hay
 * páginas que crear ni que se puedan borrar por error.
 */
final class Screen {

	public const ARG_TAB    = 'fmc_tab';
	public const ARG_EDIT   = 'fmc_edit';
	public const ARG_NEW    = 'fmc_new';
	public const ARG_PARENT = 'fmc_parent';
	public const ARG_NOTICE = 'fmc_notice';

	/**
	 * `fmc_marco=1`: the side panel asks for the screen as a bare fragment.
	 */
	public const ARG_FRAME = 'fmc_marco';

	/**
	 * Bootstrap from jsDelivr, pinned with SRI, same version as `package.json`.
	 */
	public const BOOTSTRAP = array(
		'css'     => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css',
		'css_sri' => 'sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB',
	);

	/**
	 * SweetAlert2 from jsDelivr, pinned with SRI, same version as eventos and `package.json`.
	 */
	public const SWEETALERT = array(
		'js'     => 'https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.all.min.js',
		'js_sri' => 'sha384-nLoOnA/BDh8A/jxqtckg4DumuCGOBYUnNJLZdQz/zfYNp3wcjGSoWTAzgko06G/2',
	);

	/**
	 * Tom Select from jsDelivr, pinned with SRI (ADR-0002). Same version as
	 * `package.json`; SRI computed with
	 * `curl -s <url> | openssl dgst -sha384 -binary | openssl base64 -A`.
	 */
	public const TOM_SELECT = array(
		'js'      => 'https://cdn.jsdelivr.net/npm/tom-select@2.6.2/dist/js/tom-select.complete.min.js',
		'js_sri'  => 'sha384-1mYKSrq1Nu5YJmWrIU9cvwWQlUyyukJJM9XMkAxY03nb/T69CK+Sn7rjFxVU3SSM',
		'css'     => 'https://cdn.jsdelivr.net/npm/tom-select@2.6.2/dist/css/tom-select.bootstrap5.min.css',
		'css_sri' => 'sha384-qNqaCnsmyTrYVwmqv4/4PcwMK8ZFAQnYPpVjWor+6cX6rKsPhebzu8vO2J3s+VZg',
	);

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'route' ), 1 );
		add_filter( 'login_redirect', array( self::class, 'login_redirect' ), 10, 2 );
		add_filter( 'show_admin_bar', array( self::class, 'show_admin_bar' ), 100 );
	}

	/**
	 * The WordPress admin bar: administration, and an administrator who switched into someone.
	 *
	 * Como en los aplicativos hermanos: la barra es por donde la administración
	 * vuelve al escritorio y, con WPFront User Role Editor, se cambia a otro
	 * usuario para probar el aplicativo con su perfil. A quien suplanta se le
	 * deja para que pueda volver a su cuenta. Solo decide si se ve; no concede
	 * nada al usuario suplantado. Nunca en el panel lateral.
	 *
	 * @param bool $show Whether WordPress would show it.
	 * @return bool
	 */
	public static function show_admin_bar( $show = false ): bool {
		unset( $show );
		if ( self::framed() || ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$key = 'wpfront_ure_user_switching_stack_' . COOKIEHASH;
		if ( ! class_exists( '\\WPFront\\URE\\WPFront_User_Role_Editor_Utils' ) || ! isset( $_COOKIE[ $key ] ) || ! is_string( $_COOKIE[ $key ] ) ) {
			return false;
		}
		// La pila de WPFront: «COOKIEHASH-hora-id,id,…-recordar», cifrada; el primero es quien empezó.
		$stack = explode( '-', (string) \WPFront\URE\WPFront_User_Role_Editor_Utils::decrypt( sanitize_text_field( wp_unslash( $_COOKIE[ $key ] ) ) ), 4 );
		if ( count( $stack ) < 3 || COOKIEHASH !== $stack[0] || ! ctype_digit( $stack[1] ) ) {
			return false;
		}
		$age   = time() - (int) $stack[1];
		$users = explode( ',', $stack[2] );
		return $age >= 0 && $age <= 12 * HOUR_IN_SECONDS && ctype_digit( $users[0] ) && user_can( (int) $users[0], 'manage_options' );
	}

	/**
	 * After logging in without a destination, land on the application.
	 *
	 * @param string $redirect_to Destination.
	 * @param string $requested   Destination the login form asked for.
	 * @return string
	 */
	public static function login_redirect( $redirect_to, $requested ) {
		if ( '' === (string) $requested || admin_url() === (string) $requested ) {
			return home_url( '/' );
		}
		return (string) $redirect_to;
	}

	/**
	 * Tabs the current user has something to do in, in order.
	 *
	 * @return array<string, array{label:string, type:string}>
	 */
	public static function tabs(): array {
		$tabs    = array();
		$actions = current_user_can( 'edit_fmc_actions' ) || current_user_can( 'read_private_fmc_actions' );
		if ( $actions ) {
			$tabs['calendar'] = array(
				'label' => 'Calendario',
				'type'  => PostTypes::ACTION,
			);
			$tabs['actions']  = array(
				'label' => 'Acciones formativas',
				'type'  => PostTypes::ACTION,
			);
		}
		$tabs['designs'] = array(
			'label' => 'Diseños de curso',
			'type'  => PostTypes::DESIGN,
		);
		if ( current_user_can( 'edit_fmc_speakers' ) ) {
			$tabs['speakers'] = array(
				'label' => 'Ponentes',
				'type'  => PostTypes::SPEAKER,
			);
		}
		if ( current_user_can( 'edit_fmc_incidents' ) || current_user_can( 'read_private_fmc_incidents' ) ) {
			$tabs['incidents'] = array(
				'label' => 'Incidencias',
				'type'  => PostTypes::INCIDENT,
			);
		}
		return $tabs;
	}

	/**
	 * The tab that owns a post type.
	 *
	 * @param string $type Post type.
	 * @return string
	 */
	public static function tab_of( string $type ): string {
		$map = array(
			PostTypes::ACTION   => 'actions',
			PostTypes::DESIGN   => 'designs',
			PostTypes::SPEAKER  => 'speakers',
			PostTypes::INCIDENT => 'incidents',
		);
		return $map[ $type ] ?? 'designs';
	}

	/**
	 * URL of a screen.
	 *
	 * @param array<string, scalar> $args Query args.
	 * @return string
	 */
	public static function url( array $args = array() ): string {
		// Dentro del panel lateral, se navega sin salir de él.
		if ( self::framed() && ! array_key_exists( self::ARG_FRAME, $args ) ) {
			$args[ self::ARG_FRAME ] = '1';
		}
		return add_query_arg( array_filter( $args, static fn( $v ) => '' !== $v && null !== $v ), home_url( '/' ) );
	}

	/**
	 * Whether this request is the side panel's frame.
	 *
	 * @return bool
	 */
	public static function framed(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification -- solo decide si se pinta la cabecera.
		return isset( $_REQUEST[ self::ARG_FRAME ] ) && '1' === $_REQUEST[ self::ARG_FRAME ];
	}

	/**
	 * Route the front page.
	 *
	 * @return void
	 */
	public static function route(): void {
		if ( ! is_front_page() ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( '/' ) ) );
			exit;
		}
		nocache_headers();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- delete() verifies the nonce.
		if ( isset( $_POST['fmc_delete'] ) ) {
			self::delete( absint( $_POST['fmc_delete'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Editor::save() verifies the nonce.
		if ( isset( $_POST['fmc_save'] ) ) {
			Editor::save();
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navegación de solo lectura.
		$edit = isset( $_GET[ self::ARG_EDIT ] ) ? absint( $_GET[ self::ARG_EDIT ] ) : 0;
		$new  = isset( $_GET[ self::ARG_NEW ] ) ? sanitize_key( wp_unslash( $_GET[ self::ARG_NEW ] ) ) : '';
		$tab  = isset( $_GET[ self::ARG_TAB ] ) ? sanitize_key( wp_unslash( $_GET[ self::ARG_TAB ] ) ) : '';
		// phpcs:enable

		$tabs = self::tabs();
		// La descarga en CSV de un listado, con sus filtros; el calendario exporta
		// acciones. Los ponentes no se exportan: son datos personales (ADR-0011).
		if ( '' !== Lists::arg( 'fmc_csv' ) && isset( $tabs[ $tab ] ) && PostTypes::SPEAKER !== $tabs[ $tab ]['type'] ) {
			Lists::csv( $tabs[ $tab ]['type'] );
			exit;
		}
		if ( $edit || '' !== $new ) {
			$screen = Editor::screen( $edit, $new );
		} else {
			if ( ! isset( $tabs[ $tab ] ) ) {
				$tab = (string) array_key_first( $tabs );
			}
			$screen = 'calendar' === $tab ? Calendar::screen() : Lists::screen( $tab, $tabs[ $tab ]['type'] );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- document() escapes, and every screen body is built escaped.
		echo self::document( $screen );
		exit;
	}

	/**
	 * What the deployment adds around the app: `head` (analytics, cookie
	 * notice), `brand` (logo and organisation, before the app name) and
	 * `footer` (legal links, the organisation's name).
	 *
	 * Lo que dice de quién es el despliegue no se versiona (ADR-0002): llega por
	 * el filtro `fmc_chrome`, vacío por defecto, desde un snippet de quien
	 * despliega. Es HTML de quien administra el código, así que no se escapa.
	 *
	 * @return array{head:string, brand:string, footer:string}
	 */
	public static function deployment_chrome(): array {
		$chrome = (array) apply_filters(
			'fmc_chrome',
			array(
				'head'   => '',
				'brand'  => '',
				'footer' => '',
			)
		);
		return array(
			'head'   => (string) ( $chrome['head'] ?? '' ),
			'brand'  => (string) ( $chrome['brand'] ?? '' ),
			'footer' => (string) ( $chrome['footer'] ?? '' ),
		);
	}

	/**
	 * The whole HTML document around a screen.
	 *
	 * @param array{tab:string, title:string, subtitle?:string, actions?:string, body:string} $screen Screen.
	 * @return string
	 */
	public static function document( array $screen ): string {
		if ( self::framed() ) {
			return self::fragment( $screen );
		}
		$chrome = self::deployment_chrome();
		$out    = '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
		$out   .= '<title>' . esc_html( $screen['title'] . ' · Formación' ) . '</title>';
		// Bootstrap desde jsDelivr con SRI (ADR-0002).
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- documento propio, fuera del tema.
		$out .= '<link rel="stylesheet" href="' . esc_url( self::BOOTSTRAP['css'] ) . '" integrity="' . esc_attr( self::BOOTSTRAP['css_sri'] ) . '" crossorigin="anonymous">';
		// Tom Select para las listas con buscador y las de varios elegidos, como en eventos.
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- documento propio, fuera del tema.
		$out .= '<link rel="stylesheet" href="' . esc_url( self::TOM_SELECT['css'] ) . '" integrity="' . esc_attr( self::TOM_SELECT['css_sri'] ) . '" crossorigin="anonymous">';
		$bar  = self::admin_bar();
		$out .= '<style>' . Assets::css() . '</style>' . $bar['head'] . $chrome['head'] . '</head><body class="fmc' . ( '' !== $bar['body'] ? ' admin-bar' : '' ) . '">' . $bar['body'];
		$out .= self::chrome( $screen['tab'], $chrome['brand'] );

		$out .= '<div class="fmc-head"><div class="container d-flex flex-wrap align-items-start gap-3">';
		$out .= '<div class="me-auto">' . ( $screen['back'] ?? '' ) . '<h1 class="fmc-h1">' . esc_html( $screen['title'] ) . ( $screen['badges'] ?? '' ) . '</h1>';
		if ( ! empty( $screen['subtitle'] ) ) {
			$out .= '<p class="fmc-sub">' . esc_html( $screen['subtitle'] ) . '</p>';
		}
		$out .= '</div>' . ( $screen['actions'] ?? '' ) . '</div></div>';

		$out .= '<main class="container py-4">' . self::notice() . $screen['body'] . '</main>';
		$out .= '<footer class="fmc-foot"><div class="container">Aplicativo de formación' . $chrome['footer'] . '</div></footer>';
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- documento propio, fuera del tema.
		$out .= '<script src="' . esc_url( self::TOM_SELECT['js'] ) . '" integrity="' . esc_attr( self::TOM_SELECT['js_sri'] ) . '" crossorigin="anonymous"></script>';
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- documento propio, fuera del tema.
		$out .= '<script src="' . esc_url( self::SWEETALERT['js'] ) . '" integrity="' . esc_attr( self::SWEETALERT['js_sri'] ) . '" crossorigin="anonymous"></script>';
		// El editor enriquecido es el TinyMCE que trae WordPress: sin CDN ni versión que mantener.
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- documento propio, fuera del tema.
		$out .= '<script src="' . esc_url( includes_url( 'js/tinymce/tinymce.min.js' ) ) . '"></script>';
		$out .= '<script>window.fmcTinymceBase = ' . wp_json_encode( includes_url( 'js/tinymce' ) ) . ';</script>';
		$out .= '<script>' . Assets::contents( 'js/fmc-app.js' ) . '</script>';
		$out .= $bar['foot'] . '</body></html>';
		return $out;
	}

	/**
	 * The admin bar, printed by hand: this document skips `wp_head()` and
	 * `wp_footer()` so the theme stays out.
	 *
	 * WordPress ya la ha preparado en `template_redirect` (prioridad 0), antes
	 * que el aplicativo; aquí solo se pintan su hoja, el hueco de arriba y la
	 * propia barra con sus nodos —el «Cambiar a…» y el «Volver» de WPFront van
	 * dentro—.
	 *
	 * @return array{head:string, body:string, foot:string}
	 */
	private static function admin_bar(): array {
		global $wp_admin_bar;
		$none = array(
			'head' => '',
			'body' => '',
			'foot' => '',
		);
		if ( ! is_admin_bar_showing() || ! is_object( $wp_admin_bar ) ) {
			return $none;
		}
		ob_start();
		wp_print_styles( array( 'admin-bar' ) );
		$head = (string) ob_get_clean() . '<style>html{margin-top:32px!important}@media screen and (max-width:782px){html{margin-top:46px!important}}</style>';
		ob_start();
		wp_admin_bar_render();
		$body = (string) ob_get_clean();
		ob_start();
		wp_print_scripts( array( 'admin-bar' ) );
		return array(
			'head' => $head,
			'body' => $body,
			'foot' => (string) ob_get_clean(),
		);
	}

	/**
	 * A screen as a fragment, for the side panel: no document, no chrome.
	 *
	 * El panel lateral pide la ficha con `fmc_marco=1` y la pinta dentro de sí
	 * mismo; el título va en un atributo para su cabecera.
	 *
	 * @param array<string, string> $screen Screen.
	 * @return string
	 */
	public static function fragment( array $screen ): string {
		$out = '<div class="fmc-fragment" data-fmc-title="' . esc_attr( $screen['title'] ) . '">';
		if ( ! empty( $screen['badges'] ) || ! empty( $screen['actions'] ) ) {
			$out .= '<div class="d-flex flex-wrap align-items-center gap-2 mb-3">' . ( $screen['badges'] ?? '' ) . '<span class="ms-auto">' . ( $screen['actions'] ?? '' ) . '</span></div>';
		}
		return $out . self::notice() . $screen['body'] . '</div>';
	}

	/**
	 * Header and tabs of the application, as in the sibling apps.
	 *
	 * El menú de la cuenta es un `details` nativo: sin guion, y se cierra solo
	 * al navegar. Lo que dice de quién es el despliegue —logotipo, organización—
	 * llega en `$brand` por `fmc_chrome` (ADR-0002).
	 *
	 * @param string $current Active tab.
	 * @param string $brand   Deployment HTML before the app name.
	 * @return string
	 */
	private static function chrome( string $current, string $brand = '' ): string {
		$user = wp_get_current_user();
		$name = (string) $user->display_name;
		$out  = '<header class="fmc-top"><div class="container fmc-top-in">' . $brand;
		$out .= '<a class="fmc-brand" href="' . esc_url( home_url( '/' ) ) . '">Formación</a>';
		$out .= '<details class="fmc-yo"><summary><span class="fmc-avatar" aria-hidden="true">' . esc_html( strtoupper( mb_substr( $name, 0, 1 ) ) ) . '</span>';
		$out .= '<span class="fmc-yo-txt"><strong>' . esc_html( $name ) . '</strong><small>' . esc_html( self::role_label( $user ) ) . '</small></span></summary><div class="fmc-yo-menu">';
		if ( current_user_can( 'manage_options' ) ) {
			$out .= '<a href="' . esc_url( admin_url() ) . '">Escritorio</a>';
		}
		// Con WPFront, la administración prueba el aplicativo con el perfil de otro desde «Usuarios».
		if ( current_user_can( 'switch_users' ) ) {
			$out .= '<a href="' . esc_url( admin_url( 'users.php' ) ) . '">Ver como otro usuario</a>';
		}
		$out .= '<a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">Salir</a></div></details>';
		$out .= '</div></header>';

		$out .= '<nav class="fmc-nav" aria-label="Secciones"><div class="container"><ul class="nav">';
		foreach ( self::tabs() as $key => $tab ) {
			$active = $key === $current;
			$out   .= '<li class="nav-item"><a class="nav-link' . ( $active ? ' active' : '' ) . '"' . ( $active ? ' aria-current="page"' : '' ) . ' href="' . esc_url( self::url( array( self::ARG_TAB => $key ) ) ) . '">' . esc_html( $tab['label'] ) . '</a></li>';
		}
		$out .= '</ul></div></nav>';
		return $out;
	}

	/**
	 * Label of the user's application role.
	 *
	 * @param \WP_User $user User.
	 * @return string
	 */
	public static function role_label( \WP_User $user ): string {
		$names = wp_roles()->role_names;
		foreach ( array( 'administrator', 'fmc_curator', 'fmc_adviser', 'fmc_training_service' ) as $role ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				return 'administrator' === $role ? 'Administración' : translate_user_role( $names[ $role ] ?? $role );
			}
		}
		return '';
	}

	/**
	 * The notice a redirect left behind.
	 *
	 * @return string
	 */
	private static function notice(): string {
		$texts = array(
			'saved'   => array( 'success', 'Guardado.' ),
			'created' => array( 'success', 'Creado.' ),
			'denied'  => array( 'danger', 'No tiene permiso para hacer eso.' ),
			'trashed' => array( 'success', 'Enviado a la papelera. Se puede recuperar desde el escritorio.' ),
			'reason'  => array( 'warning', 'Para dar de baja a un ponente hay que escribir el motivo.' ),
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo elige un texto fijo.
		$key = isset( $_GET[ self::ARG_NOTICE ] ) ? sanitize_key( wp_unslash( $_GET[ self::ARG_NOTICE ] ) ) : '';
		$out = isset( $texts[ $key ] ) ? '<div class="alert alert-' . esc_attr( $texts[ $key ][0] ) . '" role="status">' . esc_html( $texts[ $key ][1] ) . '</div>' : '';

		// Lo que no entró de lo subido: se guardó lo demás, pero hay que decirlo.
		$problems = get_transient( 'fmc_problems_' . get_current_user_id() );
		if ( is_array( $problems ) && $problems ) {
			delete_transient( 'fmc_problems_' . get_current_user_id() );
			$out .= '<div class="alert alert-warning" role="alert"><strong>Algunos documentos no se han subido:</strong><ul class="mb-0">';
			foreach ( $problems as $problem ) {
				$out .= '<li>' . esc_html( (string) $problem ) . '</li>';
			}
			$out .= '</ul></div>';
		}
		return $out;
	}

	/**
	 * Whether the current user may delete a post.
	 *
	 * Borrar es de administración. Los ponentes, además, los puede dar de baja
	 * quien los gestiona, con motivo: es como se atiende a quien pide que se
	 * borren sus datos (ADR-0011).
	 *
	 * @param \WP_Post $post Post.
	 * @return bool
	 */
	public static function can_delete( \WP_Post $post ): bool {
		if ( PostTypes::SPEAKER === $post->post_type && 'trash' !== $post->post_status ) {
			return current_user_can( 'delete_post', $post->ID );
		}
		return current_user_can( 'manage_options' ) && current_user_can( 'delete_post', $post->ID );
	}

	/**
	 * The speaker's leave form: a reason is mandatory.
	 *
	 * @param \WP_Post $post Speaker.
	 * @return string
	 */
	public static function trash_form( \WP_Post $post ): string {
		return '<form class="card fmc-section mt-3" method="post" action="' . esc_url( home_url( '/' ) ) . '" data-fmc-confirm="' . esc_attr( '¿Dar de baja a «' . get_the_title( $post ) . '»?' ) . '" data-fmc-confirm-text="Su ficha pasa a la papelera con el motivo y deja de verse en el aplicativo." data-fmc-confirm-button="Dar de baja"><div class="card-body">'
			. '<h2 class="h6">Dar de baja</h2><p class="text-secondary small">Por ejemplo, si la persona pide que se borren sus datos. La curaduría ve en la papelera quién la dio de baja, cuándo y por qué.</p>'
			. wp_nonce_field( 'fmc_delete_' . $post->ID, '_fmc_nonce', true, false )
			. '<input type="hidden" name="fmc_delete" value="' . esc_attr( (string) $post->ID ) . '">'
			. '<label class="form-label fw-semibold" for="fmc-reason">Motivo <span class="text-danger" aria-hidden="true">*</span></label><div class="d-flex gap-2"><input class="form-control" id="fmc-reason" name="fmc_reason" required maxlength="500">'
			. '<button class="btn btn-outline-danger text-nowrap" type="submit">Dar de baja</button></div></div></form>';
	}

	/**
	 * The delete button: a POST form with its nonce and its question.
	 *
	 * @param \WP_Post $post Post.
	 * @param string   $size Extra button class.
	 * @return string
	 */
	public static function delete_form( \WP_Post $post, string $size = '' ): string {
		$title = get_the_title( $post );
		// Desde el panel lateral, borrar es un envío normal: recarga la página entera.
		return '<form class="d-inline" method="post" action="' . esc_url( home_url( '/' ) ) . '" data-fmc-confirm="' . esc_attr( '¿Enviar «' . $title . '» a la papelera?' ) . '" data-fmc-confirm-text="Dejará de verse en el aplicativo. Se puede recuperar desde el escritorio." data-fmc-confirm-button="Enviar a la papelera">'
			. wp_nonce_field( 'fmc_delete_' . $post->ID, '_fmc_nonce', true, false )
			. '<input type="hidden" name="fmc_delete" value="' . esc_attr( (string) $post->ID ) . '">'
			. '<button class="btn btn-outline-danger ' . esc_attr( $size ) . '" type="submit">Borrar</button></form>';
	}

	/**
	 * Send a post to the trash, if administration asks.
	 *
	 * @param int $id Post ID.
	 * @return void
	 */
	public static function delete( int $id ): void {
		check_admin_referer( 'fmc_delete_' . $id, '_fmc_nonce' );
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || ! isset( PostTypes::definitions()[ $post->post_type ] ) || ! self::can_delete( $post ) ) {
			self::leave( self::url( array( self::ARG_NOTICE => 'denied' ) ) );
		}
		if ( PostTypes::SPEAKER === $post->post_type ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
			$reason = isset( $_POST['fmc_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fmc_reason'] ) ) : '';
			if ( '' === trim( $reason ) ) {
				self::leave(
					self::url(
						array(
							self::ARG_EDIT   => $id,
							self::ARG_NOTICE => 'reason',
						)
					)
				);
			}
			update_post_meta( $id, SpeakerMetaKeys::TRASH_REASON, $reason );
			update_post_meta( $id, SpeakerMetaKeys::TRASHED_BY, get_current_user_id() );
			update_post_meta( $id, SpeakerMetaKeys::TRASHED_AT, current_time( 'mysql' ) );
		}
		wp_trash_post( $id );
		self::leave(
			self::url(
				array(
					self::ARG_FRAME  => '',
					self::ARG_TAB    => self::tab_of( $post->post_type ),
					self::ARG_NOTICE => 'trashed',
				)
			)
		);
	}

	/**
	 * Leave to another screen and stop.
	 *
	 * @param string $url Destination.
	 * @return void
	 */
	public static function leave( string $url ): void {
		wp_safe_redirect( $url );
		exit;
	}
}

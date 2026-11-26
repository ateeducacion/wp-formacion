<?php
/**
 * The public catalogue: the courses on offer, for anyone.
 *
 * @package Fmc
 */

namespace Fmc\PublicFront;

use Fmc\Domain\DigComp;
use Fmc\Meta\DesignMetaKeys as D;
use Fmc\PostType\PostTypes;

/**
 * El catálogo público de formación (REQ-0001, RF-4 y RF-6).
 *
 * Sin sesión y en la portada, como el resto del aplicativo: `?fmc_catalogo=1`
 * es la lista y `?fmc_curso=ID`, la ficha de un curso. Solo salen los diseños
 * finalizados que la coordinación ha puesto en el catálogo, y de cada uno solo lo
 * que se publica; lo de trabajo —metodología, observaciones, autoría y los
 * documentos que no son la ficha técnica— se queda dentro (ADR-0025).
 */
final class Catalogue {

	/**
	 * `?fmc_catalogo=1`: the list.
	 */
	public const ARG_LIST = 'fmc_catalogo';

	/**
	 * `?fmc_curso=ID`: the sheet of one course.
	 */
	public const ARG_SHEET = 'fmc_curso';

	/**
	 * Courses per page.
	 */
	public const PER_PAGE = 24;

	/**
	 * Fields of the design that the sheet publishes, in order.
	 */
	public const PUBLIC_FIELDS = array( 'post_content', D::AUDIENCE, D::OBJECTIVES, D::CONTENTS );

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'redirect_single' ) );
	}

	/**
	 * The theme's page of a design goes to its catalogue sheet.
	 *
	 * El diseño es público para que tenga dirección, pero la página del tema lo
	 * enseñaba aunque no estuviera en el catálogo. La ficha del catálogo sabe
	 * qué se publica y a quién no se le enseña nada.
	 *
	 * @return void
	 */
	public static function redirect_single(): void {
		if ( is_singular( PostTypes::DESIGN ) ) {
			wp_safe_redirect( self::url( (int) get_queried_object_id() ), 301 );
			exit;
		}
	}

	/**
	 * URL of the catalogue, or of one course in it.
	 *
	 * @param int $design Design ID; 0 for the list.
	 * @return string
	 */
	public static function url( int $design = 0 ): string {
		return add_query_arg( $design ? array( self::ARG_SHEET => $design ) : array( self::ARG_LIST => '1' ), home_url( '/' ) );
	}

	/**
	 * Where people are sent to see the catalogue, or one course in it.
	 *
	 * El catálogo puede enseñarse en otra web, que pinta los datos de
	 * {@see CatalogueFeed}: entonces «Ver en el catálogo» tiene que llevar
	 * allí, que es donde lo ve el profesorado. Esa dirección la da el
	 * despliegue con el filtro `fmc_catalogue_url`, que recibe la del catálogo
	 * propio y el diseño (0 para la lista). Sin filtro, es la propia.
	 *
	 * @param int $design Design ID; 0 for the list.
	 * @return string
	 */
	public static function public_url( int $design = 0 ): string {
		$own = self::url( $design );
		$url = esc_url_raw( (string) apply_filters( 'fmc_catalogue_url', $own, $design ) );
		return '' !== $url ? $url : $own;
	}

	/**
	 * The courses of the catalogue that match some filters.
	 *
	 * La única regla de qué sale: finalizado y puesto en el catálogo. La usan
	 * la pantalla y los datos que piden otras webs, para que no puedan decir
	 * cosas distintas.
	 *
	 * @param array<string, mixed> $filters `s`, `type`, `modality`, `topic` and `programme` (slugs, one or several),
	 *                                      `order` (`title` or `recent`), `page` and `per_page`.
	 * @return \WP_Query
	 */
	public static function query( array $filters ): \WP_Query {
		$recent = 'recent' === ( $filters['order'] ?? '' );
		$args   = array(
			'post_type'      => PostTypes::DESIGN,
			'post_status'    => 'publish',
			'posts_per_page' => min( 100, max( 1, (int) ( $filters['per_page'] ?? self::PER_PAGE ) ) ),
			'paged'          => max( 1, (int) ( $filters['page'] ?? 1 ) ),
			'orderby'        => $recent ? 'date' : 'title',
			'order'          => $recent ? 'DESC' : 'ASC',
			// Por el título o por el código: `Lists::where()`.
			'fmc_text'       => (string) ( $filters['s'] ?? '' ),
			'fmc_text_keys'  => array( D::CODE ),
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- solo lo que está en el catálogo.
			'meta_query'     => array(
				array(
					'key'   => D::IN_CATALOGUE,
					'value' => '1',
				),
			),
		);
		foreach ( array(
			'type'     => array( D::TYPE, D::TYPES ),
			'modality' => array( D::MODALITY, D::MODALITIES ),
		) as $filter => list( $key, $choices ) ) {
			$value = (string) ( $filters[ $filter ] ?? '' );
			if ( isset( $choices[ $value ] ) ) {
				$args['meta_query'][] = array(
					'key'   => $key,
					'value' => $value,
				);
			}
		}
		foreach ( array(
			'topic'     => 'fmc_topic',
			'programme' => 'fmc_programme',
		) as $filter => $taxonomy ) {
			$slugs = array_values( array_filter( array_map( 'sanitize_title', (array) ( $filters[ $filter ] ?? array() ) ), 'strlen' ) );
			if ( $slugs ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- filtro por temática o por programa.
				$args['tax_query'][] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $slugs,
				);
			}
		}
		return new \WP_Query( $args );
	}

	/**
	 * URL of the image of a course, or nothing when it has none.
	 *
	 * @param int $design Design ID.
	 * @return string
	 */
	public static function image( int $design ): string {
		$images = Documents::of( $design )['image'];
		return $images ? Documents::url( $images[0]->ID ) : '';
	}

	/**
	 * Whether the request is for the catalogue.
	 *
	 * @return bool
	 */
	public static function asked(): bool {
		return '' !== Lists::arg( self::ARG_LIST ) || '' !== Lists::arg( self::ARG_SHEET );
	}

	/**
	 * Whether a post is a finished design in the catalogue.
	 *
	 * @param \WP_Post $post Post.
	 * @return bool
	 */
	public static function is_listed( \WP_Post $post ): bool {
		return PostTypes::DESIGN === $post->post_type && 'publish' === $post->post_status && (bool) get_post_meta( $post->ID, D::IN_CATALOGUE, true );
	}

	/**
	 * The catalogue screen asked for: a course, or the list.
	 *
	 * @return array<string, mixed>
	 */
	public static function screen(): array {
		$id = absint( Lists::arg( self::ARG_SHEET ) );
		return ( $id ? self::sheet( $id ) : self::list_screen() ) + array(
			'public' => true,
			'tab'    => '',
		);
	}

	/**
	 * The list, with its search and filters.
	 *
	 * @return array<string, string>
	 */
	private static function list_screen(): array {
		$page = max( 1, (int) Lists::arg( 'fmc_page' ) );
		$q    = self::query(
			array(
				's'        => Lists::arg( 'fmc_s' ),
				'type'     => Lists::arg( 'fmc_type' ),
				'modality' => Lists::arg( 'fmc_modality' ),
				'topic'    => Lists::arg( 'fmc_topic' ),
				'page'     => $page,
			)
		);

		$body = self::filters_html() . '<p class="fmc-found">' . esc_html( Screen::counted( (int) $q->found_posts, 'curso', 'cursos' ) ) . '</p>';
		if ( ! $q->posts ) {
			$body .= '<div class="card card-body text-secondary">No hay cursos con estos filtros.</div>';
		}
		$body .= '<div class="fmc-courses">';
		foreach ( $q->posts as $post ) {
			$body .= self::card( $post );
		}
		$body .= '</div>' . self::pager( $page, (int) $q->max_num_pages );

		return array(
			'title'    => 'Catálogo de formación',
			'subtitle' => 'Abra un curso para ver de qué trata, a quién va dirigido y qué competencias trabaja.',
			'body'     => $body,
		);
	}

	/**
	 * Search and filters of the list.
	 *
	 * @return string
	 */
	private static function filters_html(): string {
		$out  = '<form class="card card-body mb-3 fmc-filters" method="get" action="' . esc_url( home_url( '/' ) ) . '"><input type="hidden" name="' . esc_attr( self::ARG_LIST ) . '" value="1"><div class="row g-2 align-items-end">';
		$out .= '<div class="col-12 col-md-4"><label class="form-label small fw-semibold" for="fmc_s">Buscar</label><div class="input-group"><input class="form-control" id="fmc_s" name="fmc_s" type="search" value="' . esc_attr( Lists::arg( 'fmc_s' ) ) . '" placeholder="Título o código…">' . Lists::SEARCH_BUTTON . '</div></div>';
		$out .= Lists::select( 'fmc_type', 'Tipo', D::TYPES, 'Todos' );
		$out .= Lists::select( 'fmc_modality', 'Modalidad', D::MODALITIES, 'Todas' );
		$out .= Lists::select( 'fmc_topic', 'Temática', Lists::term_choices( 'fmc_topic' ), 'Todas' );
		$out .= '<div class="col-auto"><a class="btn btn-link" href="' . esc_url( self::url() ) . '">Quitar filtros</a></div>';
		return $out . '</div></form>';
	}

	/**
	 * Code, type, modality and hours of a design, joined.
	 *
	 * @param \WP_Post $post Design.
	 * @return string
	 */
	private static function facts( \WP_Post $post ): string {
		$m = static fn( string $key ): string => (string) get_post_meta( $post->ID, $key, true );
		return implode(
			' · ',
			array_filter(
				array(
					$m( D::CODE ),
					D::TYPES[ $m( D::TYPE ) ] ?? '',
					D::MODALITIES[ $m( D::MODALITY ) ] ?? '',
					Lists::hours( $m( D::HOURS_ONSITE ), $m( D::HOURS_ONLINE ) ),
				),
				'strlen'
			)
		);
	}

	/**
	 * One course in the list.
	 *
	 * @param \WP_Post $post Design.
	 * @return string
	 */
	private static function card( \WP_Post $post ): string {
		$topics = wp_get_post_terms( $post->ID, 'fmc_topic', array( 'fields' => 'names' ) );
		$image  = self::image( $post->ID );
		// La imagen es un adorno de la tarjeta: el enlace que se lee es el título.
		$out  = '<article class="card fmc-course">' . ( '' !== $image ? '<a class="fmc-course-img" href="' . esc_url( self::url( $post->ID ) ) . '" tabindex="-1" aria-hidden="true"><img src="' . esc_url( $image ) . '" alt="" loading="lazy"></a>' : '' );
		$out .= '<div class="card-body"><h2 class="h6 mb-1"><a href="' . esc_url( self::url( $post->ID ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h2>';
		$out .= '<p class="small text-secondary mb-2">' . esc_html( self::facts( $post ) ) . '</p>';
		$out .= '<p class="small mb-2">' . esc_html( wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 ) ) . '</p>';
		foreach ( is_array( $topics ) ? $topics : array() as $name ) {
			$out .= '<span class="fmc-chip">' . esc_html( (string) $name ) . '</span>';
		}
		return $out . '</div></article>';
	}

	/**
	 * Previous and next pages, keeping the filters.
	 *
	 * @param int $page  Page shown.
	 * @param int $pages Pages.
	 * @return string
	 */
	private static function pager( int $page, int $pages ): string {
		if ( $pages < 2 ) {
			return '';
		}
		$out = '<nav class="d-flex justify-content-center align-items-center gap-3 mt-3" aria-label="Páginas">';
		if ( $page > 1 ) {
			$out .= '<a class="btn btn-outline-secondary" href="' . esc_url( add_query_arg( 'fmc_page', $page - 1 ) ) . '">‹ Anterior</a>';
		}
		$out .= '<span class="small text-secondary">' . esc_html( sprintf( 'Página %1$d de %2$d', $page, $pages ) ) . '</span>';
		if ( $page < $pages ) {
			$out .= '<a class="btn btn-outline-secondary" href="' . esc_url( add_query_arg( 'fmc_page', $page + 1 ) ) . '">Siguiente ›</a>';
		}
		return $out . '</nav>';
	}

	/**
	 * The documents of a course that are public: its technical sheet.
	 *
	 * Como en el catálogo anterior, la ficha técnica se descarga desde la ficha
	 * del curso, sin sesión. El enlace es el del aplicativo, que vuelve a
	 * comprobar que el curso sigue en el catálogo (ADR-0025).
	 *
	 * @param int $design Design ID.
	 * @return string
	 */
	private static function sheets( int $design ): string {
		$docs = Documents::of( $design );
		$out  = '';
		foreach ( Documents::PUBLIC_KINDS as $kind ) {
			if ( ! $docs[ $kind ] ) {
				continue;
			}
			$out .= '<section class="card fmc-section mb-3"><div class="card-body"><h2 class="h5">' . esc_html( Documents::kinds()[ $kind ]['label'] ) . '</h2><ul class="list-unstyled mb-0">';
			foreach ( $docs[ $kind ] as $doc ) {
				$out .= '<li><a href="' . esc_url( Documents::url( $doc->ID ) ) . '">' . esc_html( Documents::name( $doc->ID ) ) . '</a></li>';
			}
			$out .= '</ul></div></section>';
		}
		return $out;
	}

	/**
	 * The sheet of one course, or a not-found page.
	 *
	 * @param int $id Design ID.
	 * @return array<string, string>
	 */
	private static function sheet( int $id ): array {
		$back = '<a class="small" href="' . esc_url( self::url() ) . '">← Catálogo de formación</a>';
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || ! self::is_listed( $post ) ) {
			status_header( 404 );
			return array(
				'back'  => $back,
				'title' => 'Este curso no está en el catálogo',
				'body'  => '<div class="card card-body">Puede que ya no se ofrezca o que la dirección no sea correcta. <a href="' . esc_url( self::url() ) . '">Ver todos los cursos</a>.</div>',
			);
		}

		$terms = static fn( string $taxonomy ): string => implode( ', ', (array) wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'names' ) ) );
		$about = array_filter(
			array(
				'Programas' => $terms( 'fmc_programme' ),
				'Temática'  => $terms( 'fmc_topic' ),
			),
			'strlen'
		);
		$body  = $about ? '<dl class="fmc-about card card-body mb-3">' : '';
		foreach ( $about as $label => $value ) {
			$body .= '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
		}
		$body  .= $about ? '</dl>' : '';
		$fields = Fields::all( PostTypes::DESIGN );
		foreach ( self::PUBLIC_FIELDS as $key ) {
			$value = 'post_content' === $key ? $post->post_content : (string) get_post_meta( $post->ID, $key, true );
			if ( '' !== trim( $value ) ) {
				$body .= '<section class="card fmc-section mb-3"><div class="card-body"><h2 class="h5">' . esc_html( $fields[ $key ]['label'] ) . '</h2>' . wp_kses_post( wpautop( $value ) ) . '</div></section>';
			}
		}
		$url = (string) get_post_meta( $post->ID, D::DIGCOMP_URL, true );
		if ( '' !== $url && array() !== DigComp::summary( $url ) ) {
			$body .= '<section class="card fmc-section mb-3"><div class="card-body"><h2 class="h5">Competencia digital</h2>' . Editor::digcomp( $url ) . '</div></section>';
		}

		$body .= self::sheets( $post->ID );

		return array(
			'back'     => $back,
			'title'    => get_the_title( $post ),
			'subtitle' => self::facts( $post ),
			'body'     => $body,
		);
	}
}

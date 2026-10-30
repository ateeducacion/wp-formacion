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
 * que se publica; lo de trabajo —metodología, observaciones, autoría,
 * documentos— se queda dentro.
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
		$args = array(
			'post_type'      => PostTypes::DESIGN,
			'post_status'    => 'publish',
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
			// Por el título o por el código: `Lists::where()`.
			'fmc_text'       => Lists::arg( 'fmc_s' ),
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
			'fmc_type'     => array( D::TYPE, D::TYPES ),
			'fmc_modality' => array( D::MODALITY, D::MODALITIES ),
		) as $arg => list( $key, $choices ) ) {
			if ( isset( $choices[ Lists::arg( $arg ) ] ) ) {
				$args['meta_query'][] = array(
					'key'   => $key,
					'value' => Lists::arg( $arg ),
				);
			}
		}
		if ( '' !== Lists::arg( 'fmc_topic' ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- filtro por temática.
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'fmc_topic',
					'field'    => 'slug',
					'terms'    => Lists::arg( 'fmc_topic' ),
				),
			);
		}
		$q = new \WP_Query( $args );

		$body = self::filters_html() . '<p class="fmc-found">' . esc_html( sprintf( '%s curso(s)', number_format_i18n( (int) $q->found_posts ) ) ) . '</p>';
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
			'subtitle' => 'Los cursos que se ofrecen. Abra uno para ver de qué trata, a quién va dirigido y qué competencias trabaja.',
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
		$out .= '<div class="col-12 col-md-4"><label class="form-label small fw-semibold" for="fmc_s">Buscar</label><input class="form-control" id="fmc_s" name="fmc_s" type="search" value="' . esc_attr( Lists::arg( 'fmc_s' ) ) . '" placeholder="Título o código…"></div>';
		$out .= Lists::select( 'fmc_type', 'Tipo', D::TYPES, 'Todos' );
		$out .= Lists::select( 'fmc_modality', 'Modalidad', D::MODALITIES, 'Todas' );
		$out .= Lists::select( 'fmc_topic', 'Temática', Lists::term_choices( 'fmc_topic' ), 'Todas' );
		$out .= '<div class="col-auto"><button class="btn btn-outline-dark" type="submit">Filtrar</button> <a class="btn btn-link" href="' . esc_url( self::url() ) . '">Quitar filtros</a></div>';
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
		$out    = '<article class="card fmc-course"><div class="card-body"><h2 class="h6 mb-1"><a href="' . esc_url( self::url( $post->ID ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h2>';
		$out   .= '<p class="small text-secondary mb-2">' . esc_html( self::facts( $post ) ) . '</p>';
		$out   .= '<p class="small mb-2">' . esc_html( wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 ) ) . '</p>';
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
				'body'  => '<div class="card card-body">Puede que ya no se ofrezca, o que la dirección no sea correcta. <a href="' . esc_url( self::url() ) . '">Vea los cursos que hay</a>.</div>',
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

		return array(
			'back'     => $back,
			'title'    => get_the_title( $post ),
			'subtitle' => self::facts( $post ),
			'body'     => $body,
		);
	}
}

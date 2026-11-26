<?php
/**
 * The public catalogue as data, for the sites that show it in their own pages.
 *
 * @package Fmc
 */

namespace Fmc\PublicFront;

use Fmc\Meta\DesignMetaKeys as D;
use Fmc\PostType\PostTypes;

/**
 * El catálogo, como datos (ADR-0028).
 *
 * Otras webs enseñan el catálogo dentro de sus páginas, con su aspecto. En vez
 * de leer la base de datos de este sitio o de copiar el catálogo, lo piden
 * aquí: `GET fmc/v1/catalogue` es la lista, con sus filtros y sus páginas, y
 * `GET fmc/v1/catalogue/<id>`, un curso entero.
 *
 * Es público y de solo lectura, y no dice nada que no diga ya la pantalla del
 * catálogo: la lista sale de {@see Catalogue::query()} y la ficha, de
 * {@see Catalogue::PUBLIC_FIELDS}. Lo de trabajo —metodología, observaciones,
 * autoría— no sale, y de los documentos, solo la ficha técnica (ADR-0025).
 */
final class CatalogueFeed {

	/**
	 * REST namespace of the app.
	 */
	public const REST_NAMESPACE = 'fmc/v1';

	/**
	 * Seconds a response may be kept by whoever asks.
	 */
	public const MAX_AGE = 300;

	/**
	 * Characters of the description a card carries.
	 */
	public const EXCERPT = 200;

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * The two routes.
	 *
	 * @return void
	 */
	public static function routes(): void {
		$text = array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/catalogue',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'index' ),
				// Es el catálogo público: lo ve cualquiera, con sesión o sin ella.
				'permission_callback' => '__return_true',
				'args'                => array(
					's'         => $text,
					'type'      => $text,
					'modality'  => $text,
					// Una temática o varias, separadas por comas.
					'topic'     => $text,
					'programme' => $text,
					'order'     => $text,
					'page'      => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page'  => array(
						'type'              => 'integer',
						'default'           => Catalogue::PER_PAGE,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/catalogue/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'show' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * The list: the courses of a page, how many there are and what filters them.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function index( \WP_REST_Request $request ): \WP_REST_Response {
		$slugs = static fn( string $value ): array => array_values( array_filter( array_map( 'trim', explode( ',', $value ) ), 'strlen' ) );
		$query = Catalogue::query(
			array(
				's'         => (string) $request['s'],
				'type'      => (string) $request['type'],
				'modality'  => (string) $request['modality'],
				'topic'     => $slugs( (string) $request['topic'] ),
				'programme' => $slugs( (string) $request['programme'] ),
				'order'     => (string) $request['order'],
				'page'      => (int) $request['page'],
				'per_page'  => (int) $request['per_page'],
			)
		);
		return self::cached(
			array(
				'total'    => (int) $query->found_posts,
				'pages'    => (int) $query->max_num_pages,
				'page'     => max( 1, (int) $request['page'] ),
				'courses'  => array_map( static fn( \WP_Post $post ): array => self::course( $post ), $query->posts ),
				'filters'  => array(
					'types'      => D::TYPES,
					'modalities' => D::MODALITIES,
					'topics'     => Lists::term_choices( 'fmc_topic' ),
					'programmes' => Lists::term_choices( 'fmc_programme' ),
				),
				'homepage' => Catalogue::url(),
			)
		);
	}

	/**
	 * One course, whole; a course outside the catalogue does not exist.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function show( \WP_REST_Request $request ) {
		$post = get_post( absint( $request['id'] ) );
		if ( ! $post instanceof \WP_Post || ! Catalogue::is_listed( $post ) ) {
			return new \WP_Error( 'fmc_not_in_catalogue', 'Este curso no está en el catálogo.', array( 'status' => 404 ) );
		}
		return self::cached( self::course( $post, true ) );
	}

	/**
	 * A response that whoever asks may keep for a few minutes.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return \WP_REST_Response
	 */
	private static function cached( array $data ): \WP_REST_Response {
		$response = new \WP_REST_Response( $data );
		$response->header( 'Cache-Control', 'public, max-age=' . self::MAX_AGE );
		return $response;
	}

	/**
	 * The start of a text, as plain text: no tags, no entities, one line.
	 *
	 * El editor guarda las tildes y los espacios duros como entidades. Quien
	 * recibe los datos espera texto, y lo escapa a su manera.
	 *
	 * @param string $html Text with its formatting.
	 * @return string
	 */
	public static function excerpt( string $html ): string {
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = trim( (string) preg_replace( '/[\s\x{00A0}]+/u', ' ', $text ) );
		return mb_strlen( $text ) > self::EXCERPT ? rtrim( mb_substr( $text, 0, self::EXCERPT ) ) . '…' : $text;
	}

	/**
	 * A course as data: what its card says and, when asked whole, its sheet.
	 *
	 * @param \WP_Post $post Design in the catalogue.
	 * @param bool     $full Whether to add what the sheet publishes.
	 * @return array<string, mixed>
	 */
	public static function course( \WP_Post $post, bool $full = false ): array {
		$m      = static fn( string $key ): string => (string) get_post_meta( $post->ID, $key, true );
		$names  = static function ( string $taxonomy ) use ( $post ): array {
			$terms = wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'names' ) );
			return is_array( $terms ) ? array_values( array_map( 'strval', $terms ) ) : array();
		};
		$course = array(
			'id'             => $post->ID,
			'code'           => $m( D::CODE ),
			'title'          => get_the_title( $post ),
			'url'            => Catalogue::url( $post->ID ),
			'image'          => Catalogue::image( $post->ID ),
			'excerpt'        => self::excerpt( $post->post_content ),
			'date'           => mysql2date( 'Y-m-d', $post->post_date ),
			'type'           => $m( D::TYPE ),
			'type_label'     => D::TYPES[ $m( D::TYPE ) ] ?? '',
			'modality'       => $m( D::MODALITY ),
			'modality_label' => D::MODALITIES[ $m( D::MODALITY ) ] ?? '',
			'hours_onsite'   => (float) $m( D::HOURS_ONSITE ),
			'hours_online'   => (float) $m( D::HOURS_ONLINE ),
			'topics'         => $names( 'fmc_topic' ),
			'programmes'     => $names( 'fmc_programme' ),
		);
		if ( ! $full ) {
			return $course;
		}
		// Lo mismo que publica la ficha del catálogo, y con sus nombres.
		$keys = array(
			'post_content' => 'description',
			D::AUDIENCE    => 'audience',
			D::OBJECTIVES  => 'objectives',
			D::CONTENTS    => 'contents',
		);
		foreach ( Catalogue::PUBLIC_FIELDS as $key ) {
			$value                   = 'post_content' === $key ? $post->post_content : $m( $key );
			$course[ $keys[ $key ] ] = '' !== trim( $value ) ? wp_kses_post( wpautop( $value ) ) : '';
		}
		$course['digcomp_url'] = esc_url_raw( $m( D::DIGCOMP_URL ) );
		$course['documents']   = array();
		$documents             = Documents::of( $post->ID );
		foreach ( Documents::PUBLIC_KINDS as $kind ) {
			foreach ( $documents[ $kind ] as $doc ) {
				$course['documents'][] = array(
					'kind' => Documents::kinds()[ $kind ]['label'],
					'name' => Documents::name( $doc->ID ),
					'url'  => Documents::url( $doc->ID ),
				);
			}
		}
		return $course;
	}
}

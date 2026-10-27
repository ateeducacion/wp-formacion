<?php
/**
 * Register the taxonomies of the application.
 *
 * @package Fmc
 */

namespace Fmc\Taxonomy;

use Fmc\Meta\ActionMetaKeys;
use Fmc\PostType\PostTypes;

/**
 * One taxonomy per dimension, never one list mixing several.
 *
 * El sistema anterior tenía una casilla «Opciones» del diseño que mezclaba si
 * está en el catálogo, si está finalizado y a qué programa pertenece, y tres
 * listas de «quién lo asume» que repetían los mismos programas. Aquí cada cosa
 * va a su sitio (ADR-0003): el estado es el `post_status`, el programa es un
 * término, quién paga es una meta con lista cerrada.
 */
final class Taxonomies {

	public const PROGRAMME  = 'fmc_programme';
	public const TOPIC      = 'fmc_topic';
	public const COMPETENCE = 'fmc_competence';
	public const SCOPE      = 'fmc_scope';
	public const ZONE       = 'fmc_zone';

	/**
	 * Term meta of a programme: whether it is active now, set by hand (ADR-0018).
	 */
	public const ACTIVE = 'fmc_active';

	/**
	 * Taxonomy => [labels, post types, hierarchical].
	 *
	 * @return array<string, array{singular:string, plural:string, types:string[], hierarchical:bool}>
	 */
	public static function definitions(): array {
		return array(
			self::PROGRAMME  => array(
				'singular'     => 'Programa',
				'plural'       => 'Programas',
				'types'        => array( PostTypes::DESIGN, PostTypes::ACTION ),
				'hierarchical' => false,
			),
			self::TOPIC      => array(
				'singular'     => 'Temática',
				'plural'       => 'Temáticas',
				'types'        => array( PostTypes::DESIGN ),
				'hierarchical' => false,
			),
			self::COMPETENCE => array(
				'singular'     => 'Área de competencia digital',
				'plural'       => 'Áreas de competencia digital',
				'types'        => array( PostTypes::DESIGN ),
				'hierarchical' => false,
			),
			// El ámbito organizativo que gestiona la acción: acota quién la ve y
			// quién la edita, como el área en eventos (ADR-0002).
			self::SCOPE      => array(
				'singular'     => 'Ámbito',
				'plural'       => 'Ámbitos',
				'types'        => array( PostTypes::ACTION ),
				'hierarchical' => true,
			),
			// Dónde está el ponente. Las islas o zonas las crea cada instalación,
			// como los programas: escritas aquí dirían de quién es el despliegue
			// (ADR-0015).
			self::ZONE       => array(
				'singular'     => 'Isla o zona',
				'plural'       => 'Islas o zonas',
				'types'        => array( PostTypes::SPEAKER ),
				'hierarchical' => false,
			),
		);
	}

	/**
	 * Register the taxonomies.
	 *
	 * @return void
	 */
	public static function register(): void {
		foreach ( self::definitions() as $slug => $def ) {
			register_taxonomy(
				$slug,
				$def['types'],
				array(
					'labels'            => array(
						'name'          => $def['plural'],
						'singular_name' => $def['singular'],
					),
					'public'            => false,
					'show_ui'           => true,
					'show_admin_column' => true,
					'show_in_rest'      => false,
					'hierarchical'      => $def['hierarchical'],
					'rewrite'           => false,
					'capabilities'      => array(
						'manage_terms' => 'fmc_manage_app',
						'edit_terms'   => 'fmc_manage_app',
						'delete_terms' => 'fmc_manage_app',
						'assign_terms' => 'read',
					),
				)
			);
		}

		register_term_meta(
			self::PROGRAMME,
			self::ACTIVE,
			array(
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => static fn( $value ): bool => (bool) $value,
				'auth_callback'     => static fn(): bool => current_user_can( 'fmc_manage_app' ),
			)
		);
	}

	/**
	 * Hook registration: the «Activo» box of a programme in the dashboard.
	 *
	 * Los programas los gestiona la administración en el escritorio, como los
	 * demás vocabularios; ahí lleva la casilla.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		add_action( self::PROGRAMME . '_add_form_fields', array( self::class, 'add_active_field' ) );
		add_action( self::PROGRAMME . '_edit_form_fields', array( self::class, 'edit_active_field' ) );
		add_action( 'created_' . self::PROGRAMME, array( self::class, 'save_active' ) );
		add_action( 'edited_' . self::PROGRAMME, array( self::class, 'save_active' ) );
		add_filter( 'manage_edit-' . self::PROGRAMME . '_columns', array( self::class, 'active_column' ) );
		add_filter( 'manage_' . self::PROGRAMME . '_custom_column', array( self::class, 'active_cell' ), 10, 3 );
	}

	/**
	 * Programmes marked active.
	 *
	 * @return int[]
	 */
	public static function active_programmes(): array {
		$ids = get_terms(
			array(
				'taxonomy'   => self::PROGRAMME,
				'hide_empty' => false,
				'fields'     => 'ids',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- unos pocos programas.
					array(
						'key'   => self::ACTIVE,
						'value' => '1',
					),
				),
			)
		);
		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	/**
	 * The «Activo» box when adding a programme.
	 *
	 * @return void
	 */
	public static function add_active_field(): void {
		wp_nonce_field( 'fmc_programme_active', '_fmc_programme_nonce' );
		echo '<div class="form-field"><label><input type="checkbox" name="fmc_active" value="1"> Activo</label><p>' . esc_html( self::active_help() ) . '</p></div>';
	}

	/**
	 * The «Activo» box when editing a programme.
	 *
	 * @param \WP_Term $term Programme.
	 * @return void
	 */
	public static function edit_active_field( $term ): void {
		echo '<tr class="form-field"><th scope="row">Activo</th><td>';
		wp_nonce_field( 'fmc_programme_active', '_fmc_programme_nonce' );
		echo '<label><input type="checkbox" name="fmc_active" value="1"' . checked( (bool) get_term_meta( $term->term_id, self::ACTIVE, true ), true, false ) . '> Activo</label><p class="description">' . esc_html( self::active_help() ) . '</p></td></tr>';
	}

	/**
	 * What the box means, under it, with the deployment's name for who pays.
	 *
	 * @return string
	 */
	private static function active_help(): string {
		return sprintf( 'Mientras está activo, las acciones de los diseños de este programa que asuma «%s» se le imputan solas.', ActionMetaKeys::funders()[ ActionMetaKeys::PROGRAMME_FUNDER ] );
	}

	/**
	 * Save the «Activo» box, if who sends it may manage programmes.
	 *
	 * @param int $term_id Programme.
	 * @return void
	 */
	public static function save_active( $term_id ): void {
		if ( ! isset( $_POST['_fmc_programme_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_fmc_programme_nonce'] ) ), 'fmc_programme_active' ) || ! current_user_can( 'fmc_manage_app' ) ) {
			return;
		}
		if ( ! empty( $_POST['fmc_active'] ) ) {
			update_term_meta( (int) $term_id, self::ACTIVE, '1' );
		} else {
			delete_term_meta( (int) $term_id, self::ACTIVE );
		}
	}

	/**
	 * The «Activo» column of the programmes list.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function active_column( $columns ): array {
		$columns['fmc_active'] = 'Activo';
		return (array) $columns;
	}

	/**
	 * A cell of the «Activo» column.
	 *
	 * @param string $content Content so far.
	 * @param string $column  Column.
	 * @param int    $term_id Programme.
	 * @return string
	 */
	public static function active_cell( $content, $column, $term_id ): string {
		if ( 'fmc_active' !== $column ) {
			return (string) $content;
		}
		return get_term_meta( (int) $term_id, self::ACTIVE, true ) ? 'Sí' : '—';
	}
}

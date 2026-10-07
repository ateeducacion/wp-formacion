<?php
/**
 * Register the taxonomies of the application.
 *
 * @package Fmc
 */

namespace Fmc\Taxonomy;

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
	}
}

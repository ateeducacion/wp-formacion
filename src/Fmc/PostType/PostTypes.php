<?php
/**
 * Register the four post types of the application and their capabilities.
 *
 * @package Fmc
 */

namespace Fmc\PostType;

/**
 * Designs, actions, speakers and incidents.
 *
 * Cada tipo tiene sus propias capacidades (`edit_fmc_designs`, …), así que
 * quién hace qué se decide aquí, en un mapa, y no en cada pantalla. El ciclo
 * del diseño sale de las capacidades nativas (ADR-0004): la asesoría crea y
 * manda a revisión, pero no tiene `publish_fmc_designs` ni
 * `edit_published_fmc_designs`, así que ni lo da por finalizado ni lo toca
 * después. Eso lo hace la curaduría.
 */
final class PostTypes {

	public const DESIGN   = 'fmc_design';
	public const ACTION   = 'fmc_action';
	public const SPEAKER  = 'fmc_speaker';
	public const INCIDENT = 'fmc_incident';

	/**
	 * Custom capability: write the fields the training service owns —the file
	 * number, the situation and its own officers—.
	 */
	public const CAP_SERVICE_FIELDS = 'fmc_edit_service_fields';

	/**
	 * Custom capability: approve or deny an incident.
	 */
	public const CAP_RESOLVE = 'fmc_resolve_incidents';

	/**
	 * Labels and arguments that differ between the four types.
	 *
	 * @return array<string, array{singular:string, plural:string, public:bool, icon:string}>
	 */
	public static function definitions(): array {
		return array(
			self::DESIGN   => array(
				'singular' => 'Diseño de curso',
				'plural'   => 'Diseños de curso',
				// El catálogo es público: los diseños finalizados se enseñan.
				'public'   => true,
				'icon'     => 'dashicons-welcome-learn-more',
			),
			self::ACTION   => array(
				'singular' => 'Acción formativa',
				'plural'   => 'Acciones formativas',
				'public'   => false,
				'icon'     => 'dashicons-calendar-alt',
			),
			self::SPEAKER  => array(
				'singular' => 'Ponente',
				'plural'   => 'Ponentes',
				// Datos personales: ni públicos ni en REST.
				'public'   => false,
				'icon'     => 'dashicons-groups',
			),
			self::INCIDENT => array(
				'singular' => 'Incidencia',
				'plural'   => 'Incidencias',
				'public'   => false,
				'icon'     => 'dashicons-flag',
			),
		);
	}

	/**
	 * Register the post types.
	 *
	 * @return void
	 */
	public static function register(): void {
		foreach ( self::definitions() as $type => $def ) {
			register_post_type(
				$type,
				array(
					'labels'          => array(
						'name'          => $def['plural'],
						'singular_name' => $def['singular'],
						'menu_name'     => $def['plural'],
					),
					'public'          => $def['public'],
					'show_ui'         => true,
					'show_in_rest'    => false,
					'menu_icon'       => $def['icon'],
					'supports'        => array( 'title', 'editor', 'author', 'revisions' ),
					'has_archive'     => false,
					'capability_type' => array( $type, $type . 's' ),
					'map_meta_cap'    => true,
				)
			);
		}
	}

	/**
	 * Capabilities each role holds on each type.
	 *
	 * Las primitivas de WordPress por tipo, sin el sufijo: `edit` es
	 * `edit_fmc_designs`, `publish` es `publish_fmc_designs`, etc.
	 *
	 * @return array<string, array<string, string[]>> role => type => primitives.
	 */
	public static function role_caps(): array {
		$all = array( 'edit', 'edit_others', 'edit_private', 'edit_published', 'publish', 'read_private', 'delete', 'delete_others', 'delete_private', 'delete_published' );
		$own = array( 'edit', 'edit_published', 'publish', 'delete' );

		return array(
			'administrator'        => array_fill_keys( array_keys( self::definitions() ), $all ),
			'fmc_curator'          => array_fill_keys( array_keys( self::definitions() ), $all ),
			'fmc_adviser'          => array(
				// Borrador y revisión, nada más: sin publicar ni editar publicados.
				self::DESIGN   => array( 'edit', 'delete' ),
				self::ACTION   => $own,
				self::SPEAKER  => $own,
				self::INCIDENT => array( 'edit', 'publish' ),
			),
			// El servicio de formación no edita nada por la vía general: lee y
			// tiene sus dos capacidades propias (ADR-0006).
			'fmc_training_service' => array(
				self::ACTION   => array( 'read_private' ),
				self::INCIDENT => array( 'read_private' ),
			),
		);
	}

	/**
	 * Grant the type capabilities to the roles that exist (idempotent, additive).
	 *
	 * @return void
	 */
	public static function grant_caps_to_roles(): void {
		foreach ( self::role_caps() as $slug => $types ) {
			$role = get_role( $slug );
			if ( ! $role ) {
				continue;
			}
			foreach ( $types as $type => $primitives ) {
				foreach ( $primitives as $primitive ) {
					$cap = $primitive . '_' . $type . 's';
					if ( ! $role->has_cap( $cap ) ) {
						$role->add_cap( $cap );
					}
				}
			}
		}

		$extra = array(
			'administrator'        => array( self::CAP_SERVICE_FIELDS, self::CAP_RESOLVE ),
			'fmc_curator'          => array( self::CAP_SERVICE_FIELDS, self::CAP_RESOLVE ),
			'fmc_training_service' => array( self::CAP_SERVICE_FIELDS, self::CAP_RESOLVE ),
		);
		foreach ( $extra as $slug => $caps ) {
			$role = get_role( $slug );
			foreach ( $role ? $caps : array() as $cap ) {
				if ( ! $role->has_cap( $cap ) ) {
					$role->add_cap( $cap );
				}
			}
		}
	}
}

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
 * del diseño sale de los estados nativos (ADR-0004) más uno propio,
 * «Histórico», para lo que ya no se imparte (ADR-0016). Los diseños los
 * escribe y los finaliza la validación; la asesoría los consulta, y solo los
 * finalizados (ADR-0017).
 */
final class PostTypes {

	public const DESIGN   = 'fmc_design';
	public const ACTION   = 'fmc_action';
	public const SPEAKER  = 'fmc_speaker';
	public const INCIDENT = 'fmc_incident';

	/**
	 * Design status for what is no longer given: kept, but out of every choice (ADR-0016).
	 */
	public const ARCHIVED = 'fmc_archived';

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
	 * @return array<string, array{singular:string, plural:string, new:string, public:bool, icon:string}>
	 */
	public static function definitions(): array {
		return array(
			self::DESIGN   => array(
				// No todo diseño es un curso: también hay acciones puntuales.
				'singular' => 'Diseño de acción formativa',
				'plural'   => 'Diseños de acciones formativas',
				'new'      => 'Nuevo diseño de acción formativa',
				// El catálogo es público: los diseños finalizados se enseñan.
				'public'   => true,
				'icon'     => 'dashicons-welcome-learn-more',
			),
			self::ACTION   => array(
				'singular' => 'Acción formativa',
				'plural'   => 'Acciones formativas',
				'new'      => 'Nueva acción formativa',
				'public'   => false,
				'icon'     => 'dashicons-calendar-alt',
			),
			self::SPEAKER  => array(
				'singular' => 'Ponente',
				'plural'   => 'Ponentes',
				'new'      => 'Nuevo ponente',
				// Datos personales: ni públicos ni en REST.
				'public'   => false,
				'icon'     => 'dashicons-groups',
			),
			self::INCIDENT => array(
				'singular' => 'Incidencia',
				'plural'   => 'Incidencias',
				'new'      => 'Nueva incidencia',
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
						'new_item'      => $def['new'],
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

		// No es interno: entra en las consultas `any` y en los listados del
		// escritorio, porque un histórico se sigue consultando.
		register_post_status(
			self::ARCHIVED,
			array(
				'label'                     => 'Histórico',
				'label_count'               => array(
					0          => 'Histórico <span class="count">(%s)</span>',
					1          => 'Históricos <span class="count">(%s)</span>',
					'singular' => 'Histórico <span class="count">(%s)</span>',
					'plural'   => 'Históricos <span class="count">(%s)</span>',
					'context'  => null,
					'domain'   => null,
				),
				'public'                    => false,
				'protected'                 => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
			)
		);
	}

	/**
	 * A design in the archive is locked like a finalised one.
	 *
	 * WordPress solo protege `publish` y `future`: un histórico lo podría editar
	 * o borrar su autor con `edit_fmc_designs`. Aquí pide lo mismo que un
	 * finalizado (ADR-0016).
	 *
	 * @param string[] $caps    Primitive caps required.
	 * @param string   $cap     Meta cap asked for.
	 * @param int      $user_id User.
	 * @param mixed[]  $args    Post ID first.
	 * @return string[]
	 */
	public static function lock_archived( $caps, $cap, $user_id, $args ) {
		unset( $user_id );
		if ( ! in_array( $cap, array( 'edit_post', 'delete_post' ), true ) || empty( $args[0] ) ) {
			return $caps;
		}
		$post = get_post( $args[0] );
		if ( ! $post instanceof \WP_Post || self::DESIGN !== $post->post_type || self::ARCHIVED !== $post->post_status ) {
			return $caps;
		}
		$caps[] = 'edit_post' === $cap ? 'edit_published_fmc_designs' : 'delete_published_fmc_designs';
		return array_values( array_unique( $caps ) );
	}

	/**
	 * A design that is not finalised is read only by who may edit it.
	 *
	 * WordPress deja al autor leer lo suyo aunque no sea público: una asesoría
	 * que escribió un borrador antes de que dejaran de escribirlos lo seguiría
	 * leyendo, y con él el título que enseñan la mediateca o un selector. Aquí,
	 * leer un borrador, uno en revisión o un histórico pide poder editarlo
	 * (ADR-0016, ADR-0017).
	 *
	 * @param string[] $caps    Primitive caps required.
	 * @param string   $cap     Meta cap asked for.
	 * @param int      $user_id User.
	 * @param mixed[]  $args    Post first.
	 * @return string[]
	 */
	public static function hide_unfinished( $caps, $cap, $user_id, $args ) {
		if ( 'read_post' !== $cap || empty( $args[0] ) ) {
			return $caps;
		}
		$post = get_post( $args[0] );
		if ( ! $post instanceof \WP_Post || self::DESIGN !== $post->post_type || in_array( $post->post_status, array( 'publish', 'trash' ), true ) ) {
			return $caps;
		}
		return map_meta_cap( 'edit_post', (int) $user_id, $post->ID );
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
				// Los diseños los consulta, finalizados, y no los escribe (ADR-0017).
				self::DESIGN   => array(),
				self::ACTION   => $own,
				// Los ponentes son un directorio compartido: edita los suyos, pero
				// cualquier ficha se puede dar de baja con motivo (ADR-0011).
				self::SPEAKER  => array_merge( $own, array( 'delete_others', 'delete_published' ) ),
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
	 * Capabilities this map granted once and no longer does, per role.
	 *
	 * El reparto es aditivo y no quita nada, así que lo que un rol deja de
	 * tener se dice aquí: si no, un sitio ya desplegado lo seguiría teniendo.
	 *
	 * @return array<string, string[]> role => capabilities.
	 */
	public static function revoked_caps(): array {
		return array(
			// La asesoría ya no escribe diseños (ADR-0017).
			'fmc_adviser' => array( 'edit_fmc_designs', 'delete_fmc_designs' ),
		);
	}

	/**
	 * Grant the type capabilities to the roles that exist (idempotent, additive).
	 *
	 * Lo único que quita es lo de {@see revoked_caps()}.
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

		foreach ( self::revoked_caps() as $slug => $caps ) {
			$role = get_role( $slug );
			foreach ( $role ? $caps : array() as $cap ) {
				if ( isset( $role->capabilities[ $cap ] ) ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}
}

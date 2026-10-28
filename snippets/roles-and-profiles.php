<?php
/**
 * Snippet Name: FMC — Roles
 * Description: Registra los tres roles del aplicativo de formación —coordinación, asesoría y servicio de perfeccionamiento— y la capacidad de administrarlo. Las capacidades de los tipos de contenido las reparte el aplicativo.
 * Scope: global
 * Priority: 5
 *
 * @package Fmc
 */

// Code Snippets evalúa esto, no lo incluye como fichero; la guarda va igual
// por si el código acaba algún día en un fichero servido.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'fmc_role_definitions' ) ) {
	/**
	 * Role labels and the capabilities this snippet owns.
	 *
	 * Solo las capacidades generales. Las de los tipos de contenido
	 * (`edit_fmc_designs`, …) y las dos propias del servicio las reparte
	 * `Fmc\PostType\PostTypes::grant_caps_to_roles()` en `init` 11, para no
	 * tener el mismo mapa escrito en dos sitios (ADR-0006).
	 *
	 * Los rótulos son genéricos a propósito: cómo se llama cada perfil en el
	 * organigrama de quien despliega es configuración y no se versiona
	 * (ADR-0002, adenda). El filtro `fmc_role_labels` los cambia, uno a uno y
	 * por su slug; las capacidades no salen de aquí.
	 *
	 * @return array<string, array{label:string, caps:string[]}>
	 */
	function fmc_role_definitions(): array {
		$roles = array(
			'fmc_curator'          => array(
				'label' => 'Coordinación de formación',
				'caps'  => array( 'read', 'upload_files' ),
			),
			// Sin `upload_files`: ya no sube documentos de diseño (ADR-0017), y con
			// él vería en la mediateca los de los borradores y del histórico.
			'fmc_adviser'          => array(
				'label' => 'Asesoría de formación',
				'caps'  => array( 'read' ),
			),
			'fmc_training_service' => array(
				'label' => 'Servicio de Perfeccionamiento',
				'caps'  => array( 'read' ),
			),
		);

		/**
		 * Filters the label of each product role.
		 *
		 * @param array<string, string> $labels Role slug => label. Vacío por defecto.
		 */
		$labels = (array) apply_filters( 'fmc_role_labels', array() );
		foreach ( $roles as $slug => $def ) {
			$label = isset( $labels[ $slug ] ) ? trim( (string) $labels[ $slug ] ) : '';
			if ( '' !== $label ) {
				$roles[ $slug ]['label'] = $label;
			}
		}
		return $roles;
	}
}

if ( ! function_exists( 'fmc_role_slugs' ) ) {
	/**
	 * Return the product role slugs.
	 *
	 * @return string[]
	 */
	function fmc_role_slugs(): array {
		return array_keys( fmc_role_definitions() );
	}
}

if ( ! function_exists( 'fmc_revoked_role_caps' ) ) {
	/**
	 * Capabilities this snippet granted once and no longer does, per role.
	 *
	 * El reparto es aditivo: lo que un rol deja de tener se dice aquí, o un
	 * sitio ya desplegado lo conservaría. Es lo único que se quita.
	 *
	 * @return array<string, string[]>
	 */
	function fmc_revoked_role_caps(): array {
		return array(
			'fmc_adviser' => array( 'upload_files' ),
		);
	}
}

if ( ! function_exists( 'fmc_forbidden_role_caps' ) ) {
	/**
	 * Capabilities no product role may ever hold.
	 *
	 * Administrar el aplicativo —sus ajustes y sus vocabularios— es de la
	 * administración, y `unfiltered_html` no se concede a nadie.
	 *
	 * @return string[]
	 */
	function fmc_forbidden_role_caps(): array {
		return array( 'fmc_manage_app', 'unfiltered_html' );
	}
}

if ( ! function_exists( 'fmc_register_roles' ) ) {
	/**
	 * Idempotently register product roles and grant the app cap to administrators.
	 *
	 * Aditiva: crea el rol si falta y añade la capacidad si falta. Solo quita
	 * lo de {@see fmc_revoked_role_caps()}; lo demás que se conceda a mano en el
	 * editor de roles sigue ahí en la siguiente carga. El rótulo sí es el del código: si cambia, el rol ya creado lo
	 * coge, o un sitio desplegado seguiría con el nombre viejo.
	 *
	 * @return void
	 */
	function fmc_register_roles(): void {
		foreach ( fmc_role_definitions() as $slug => $def ) {
			$role = get_role( $slug );
			if ( ! $role ) {
				add_role( $slug, $def['label'], array() );
				$role = get_role( $slug );
			}
			$roles = wp_roles();
			if ( isset( $roles->roles[ $slug ] ) && $def['label'] !== $roles->roles[ $slug ]['name'] ) {
				$roles->roles[ $slug ]['name'] = $def['label'];
				$roles->role_names[ $slug ]    = $def['label'];
				if ( $roles->use_db ) {
					update_option( $roles->role_key, $roles->roles );
				}
			}
			foreach ( $role ? $def['caps'] : array() as $cap ) {
				if ( ! $role->has_cap( $cap ) ) {
					$role->add_cap( $cap );
				}
			}
			foreach ( $role ? ( fmc_revoked_role_caps()[ $slug ] ?? array() ) : array() as $cap ) {
				if ( isset( $role->capabilities[ $cap ] ) ) {
					$role->remove_cap( $cap );
				}
			}
		}

		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( 'fmc_manage_app' ) ) {
			$admin->add_cap( 'fmc_manage_app' );
		}
	}
}

if ( ! function_exists( 'fmc_roles_status' ) ) {
	/**
	 * Forbidden capabilities someone granted by hand to a product role.
	 *
	 * Este snippet no quita capacidades salvo las que ya no concede, así que
	 * avisa y quien administra decide.
	 *
	 * @return array<string, string[]> role => forbidden caps it holds.
	 */
	function fmc_roles_status(): array {
		$out = array();
		foreach ( fmc_role_slugs() as $slug ) {
			$role = get_role( $slug );
			if ( ! $role ) {
				continue;
			}
			$bad = array_values( array_filter( fmc_forbidden_role_caps(), array( $role, 'has_cap' ) ) );
			if ( $bad ) {
				$out[ $slug ] = $bad;
			}
		}
		return $out;
	}
}

add_action( 'init', 'fmc_register_roles', 5 );

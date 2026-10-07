<?php
/**
 * Snippet Name: FMC — Roles
 * Description: Registra los tres roles del aplicativo de formación —curaduría, asesoría y servicio de formación— y la capacidad de administrarlo. Las capacidades de los tipos de contenido las reparte el aplicativo.
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
	 * (`edit_fmc_designs`, …) y las dos propias del servicio de formación las
	 * reparte `Fmc\PostType\PostTypes::grant_caps_to_roles()` en `init` 11,
	 * para no tener el mismo mapa escrito en dos sitios (ADR-0006).
	 *
	 * @return array<string, array{label:string, caps:string[]}>
	 */
	function fmc_role_definitions(): array {
		return array(
			'fmc_curator'          => array(
				'label' => 'Curaduría de formación',
				'caps'  => array( 'read', 'upload_files' ),
			),
			'fmc_adviser'          => array(
				'label' => 'Asesoría de formación',
				'caps'  => array( 'read', 'upload_files' ),
			),
			'fmc_training_service' => array(
				'label' => 'Servicio de formación',
				'caps'  => array( 'read' ),
			),
		);
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
	 * Aditiva: crea el rol si falta y añade la capacidad si falta, nunca quita.
	 * Lo que se conceda a mano en el editor de roles sigue ahí en la siguiente
	 * carga.
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
			foreach ( $role ? $def['caps'] : array() as $cap ) {
				if ( ! $role->has_cap( $cap ) ) {
					$role->add_cap( $cap );
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
	 * Este snippet nunca quita capacidades, así que avisa y quien administra
	 * decide.
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

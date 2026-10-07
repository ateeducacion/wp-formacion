<?php
/**
 * Idempotent role registration for local provision (wp eval-file).
 *
 * Relies on the roles snippet functions after Code Snippets has activated
 * them, or loads the versioned file directly if functions are missing.
 *
 * @package Fmc
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fmc_snippet = dirname( __DIR__ ) . '/snippets/roles-and-profiles.php';
if ( ! function_exists( 'fmc_register_roles' ) && is_readable( $fmc_snippet ) ) {
	require_once $fmc_snippet;
}

if ( ! function_exists( 'fmc_register_roles' ) ) {
	throw new RuntimeException( 'No está disponible fmc_register_roles().' );
}

fmc_register_roles();

// Las capacidades de los cuatro tipos de contenido las reparte el aplicativo en
// `init` prioridad 11. En la primera provisión los roles todavía no existían
// cuando pasó ese `init`, así que se vuelve a llamar aquí: es idempotente y
// aditiva, y así los roles salen completos en la misma ejecución.
if ( class_exists( '\\Fmc\\PostType\\PostTypes' ) ) {
	\Fmc\PostType\PostTypes::grant_caps_to_roles();
} else {
	echo "AVISO: el bundle del aplicativo no está cargado; los roles quedan sin las capacidades de los tipos de contenido.\n";
}

$fmc_slugs = function_exists( 'fmc_role_slugs' ) ? fmc_role_slugs() : array();
echo esc_html( 'Roles FMC asegurados: ' . implode( ', ', $fmc_slugs ) ) . "\n";
foreach ( $fmc_slugs as $fmc_slug ) {
	$fmc_role = get_role( $fmc_slug );
	if ( ! $fmc_role ) {
		throw new RuntimeException( esc_html( 'No se pudo crear el rol ' . $fmc_slug . '.' ) );
	}
	// translators: 1: role slug, 2: capability count.
	echo esc_html( sprintf( '- %s: OK (%d caps)', $fmc_slug, count( $fmc_role->capabilities ) ) ) . "\n";
}

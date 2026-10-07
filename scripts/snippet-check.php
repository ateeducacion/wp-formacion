<?php
/**
 * Run Code Snippets' own save-time validation over every FMC snippet.
 *
 * Saving an active snippet makes the plugin evaluate its code a second time
 * in the same request. A snippet without the double-eval guard either gets
 * disabled in silence («Cannot redeclare class») or kills the request. This
 * reproduces that check and fails loudly instead.
 *
 * Usage (Docker):
 *   npx wp-env run cli wp eval-file wp-content/fmc-dev/scripts/snippet-check.php
 *
 * Runs under `wp eval-file`; never depends on WP_CLI. Idempotent: reads only.
 *
 * @package Fmc
 */

if ( ! function_exists( 'Code_Snippets\get_snippets' ) || ! function_exists( 'Code_Snippets\test_snippet_code' ) ) {
	echo "AVISO: Code Snippets no está activo; no hay nada que comprobar.\n";
	return;
}

$fmc_fallos = 0;

// First pass: the snippets that ran on this request must have actually loaded
// (a guard that trips too early leaves the classes declared but nothing booted).
// Lo que se comprueba es el efecto observable de cada snippet, no que exista
// una clase: el bundle registra el CPT en `init`, y el snippet de roles define
// sus funciones al cargarse.
$fmc_cargado = array(
	'el aplicativo (CPT fmc_design registrado)'   => post_type_exists( 'fmc_design' ),
	'las taxonomías (fmc_scope registrada)'       => taxonomy_exists( 'fmc_scope' ),
	'los roles (fmc_register_roles() disponible)' => function_exists( 'fmc_register_roles' ),
);
foreach ( $fmc_cargado as $fmc_que => $fmc_ok ) {
	if ( ! $fmc_ok ) {
		++$fmc_fallos;
	}
	echo esc_html( sprintf( '%s Primera pasada: %s', $fmc_ok ? '✓' : '✗', $fmc_que ) ) . "\n";
}

// Second pass: what the plugin does when an active snippet is saved.
foreach ( Code_Snippets\get_snippets() as $fmc_snippet ) {
	if ( 0 !== strpos( (string) $fmc_snippet->name, 'FMC' ) ) {
		continue;
	}
	// 3.10.x leaves the verdict in `code_error` (message, line); a fatal in the
	// second eval() would not even get here, which is a verdict too.
	$fmc_snippet->code_error = null;
	Code_Snippets\test_snippet_code( $fmc_snippet );
	if ( empty( $fmc_snippet->code_error ) ) {
		echo esc_html( sprintf( '✓ %s', $fmc_snippet->name ) ) . "\n";
		continue;
	}
	++$fmc_fallos;
	echo esc_html( sprintf( '✗ %s: %s', $fmc_snippet->name, wp_json_encode( $fmc_snippet->code_error, JSON_UNESCAPED_UNICODE ) ) ) . "\n";
}

if ( $fmc_fallos > 0 ) {
	echo esc_html( sprintf( '%d snippet(s) no sobreviven al guardado de Code Snippets.', $fmc_fallos ) ) . "\n";
	exit( 1 );
}

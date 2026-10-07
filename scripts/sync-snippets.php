<?php
/**
 * Sync the versioned snippets/*.php files into the Code Snippets plugin.
 *
 * Runs under `wp eval-file` (Docker) and under Playground `runPHP`/require —
 * it must never depend on WP_CLI. Idempotent: existing snippets (matched by
 * exact name) are updated, never duplicated.
 *
 * Usage:
 *   npx wp-env run cli wp eval-file wp-content/fmc-dev/scripts/sync-snippets.php
 *
 * @package Fmc
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/lib/snippet-sync.php';

if ( ! fmc_code_snippets_is_active() ) {
	throw new RuntimeException( 'El plugin Code Snippets no está activo; no se pueden sincronizar los snippets.' );
}

$fmc_snippets_dir = dirname( __DIR__ ) . '/snippets';

echo esc_html( sprintf( 'Sincronizando snippets desde %s ...', $fmc_snippets_dir ) ) . "\n";

$fmc_results = fmc_sync_snippets_from_dir( $fmc_snippets_dir );

if ( empty( $fmc_results ) ) {
	throw new RuntimeException( 'No se ha encontrado ningún fichero de snippet que sincronizar.' );
}

$fmc_created   = 0;
$fmc_updated   = 0;
$fmc_unchanged = 0;
$fmc_errors    = 0;

foreach ( $fmc_results as $fmc_file => $fmc_result ) {
	if ( 'error' === $fmc_result['status'] ) {
		++$fmc_errors;
		echo esc_html( sprintf( '- %s: ERROR — %s', $fmc_file, $fmc_result['error'] ) ) . "\n";
		continue;
	}

	switch ( $fmc_result['status'] ) {
		case 'created':
			++$fmc_created;
			$fmc_action = 'creado';
			break;
		case 'updated':
			++$fmc_updated;
			$fmc_action = 'actualizado';
			break;
		case 'unchanged':
			++$fmc_unchanged;
			$fmc_action = $fmc_result['reactivated'] ? 'sin cambios, reactivado' : 'sin cambios';
			break;
		default:
			++$fmc_errors;
			echo esc_html( sprintf( '- %s: ERROR — estado de sincronización inesperado: %s', $fmc_file, $fmc_result['status'] ) ) . "\n";
			continue 2;
	}

	if ( $fmc_result['active'] ) {
		$fmc_state = '' === $fmc_result['error']
			? 'activo'
			: sprintf( 'activo con AVISO — %s', $fmc_result['error'] );
	} else {
		++$fmc_errors;
		$fmc_state = sprintf( 'ERROR al activar: %s', $fmc_result['error'] );
	}

	echo esc_html(
		sprintf(
			'- %1$s → «%2$s» (ID %3$d): %4$s, %5$s',
			$fmc_file,
			$fmc_result['name'],
			$fmc_result['id'],
			$fmc_action,
			$fmc_state
		)
	) . "\n";
}

echo esc_html(
	sprintf(
		'Resumen: %1$d creado(s), %2$d actualizado(s), %3$d sin cambios, %4$d error(es).',
		$fmc_created,
		$fmc_updated,
		$fmc_unchanged,
		$fmc_errors
	)
) . "\n";

if ( $fmc_errors > 0 ) {
	throw new RuntimeException( 'La sincronización de snippets terminó con errores; revise el resumen anterior.' );
}

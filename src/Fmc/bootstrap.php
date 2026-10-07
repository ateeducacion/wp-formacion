<?php
/**
 * Load modular formación application classes (development includes).
 *
 * The load order lives in load-order.php, shared with build/pack-snippet.php.
 * The Code Snippets bundle inlines the same files in that same order.
 *
 * @package Fmc
 */

if ( ! defined( 'FMC_SRC_DIR' ) ) {
	define( 'FMC_SRC_DIR', __DIR__ );
}

// When the Code Snippets bundle already defined the classes, skip file loads
// (require_once is path-based; the bundle is a different file and would redeclare).
if ( ! class_exists( \Fmc\App::class, false ) ) {
	$fmc_app_files = require __DIR__ . '/load-order.php';

	foreach ( $fmc_app_files as $fmc_app_file ) {
		$fmc_app_path = FMC_SRC_DIR . '/' . $fmc_app_file;
		if ( is_readable( $fmc_app_path ) ) {
			require_once $fmc_app_path;
		}
	}
}

if ( class_exists( \Fmc\App::class ) ) {
	\Fmc\App::boot();
}

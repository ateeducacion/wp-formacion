<?php
/**
 * Move the documents of the designs into the private store (wp eval-file).
 *
 * Idempotente: lo que ya está dentro solo se vuelve a cerrar. Se pasa después
 * de cada importación de la migración y en un sitio que tenga documentos
 * subidos antes de ADR-0025. Se cae al primer fallo, con su motivo.
 *
 * @package Fmc
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\\Fmc\\PublicFront\\Documents' ) ) {
	throw new RuntimeException( 'El bundle del aplicativo no está cargado.' );
}

$fmc_ids = get_posts(
	array(
		'post_type'      => 'attachment',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- solo los documentos de los diseños.
		'meta_key'       => \Fmc\PublicFront\Documents::KIND_META,
	)
);
foreach ( $fmc_ids as $fmc_id ) {
	$fmc_problem = \Fmc\PublicFront\DocumentStore::protect( (int) $fmc_id );
	if ( '' !== $fmc_problem ) {
		throw new RuntimeException( esc_html( $fmc_problem ) );
	}
}
echo esc_html( sprintf( 'Documentos en el almacén privado: %d.', count( $fmc_ids ) ) ) . "\n";

<?php
/**
 * Snippet Name: FMC — Vocabulario base del aplicativo (una sola vez)
 * Description: Deja creados los términos de las cuatro taxonomías del aplicativo: ámbitos, programas, temáticas y áreas de competencia digital. Idempotente: crea el término que falta por su slug y no toca el que ya está. En producción se pega en Code Snippets como snippet de ejecución única; en local lo llama `make provision`.
 *
 * Tiene que existir antes que nada `fmc_scope`: es el ámbito que acota quién
 * ve y quién edita cada acción, y sin términos nadie puede tener ámbito.
 *
 * Los términos de aquí son **de ejemplo**: sirven para que el entorno local
 * tenga con qué trabajar. Cada instalación crea los suyos, y la migración los
 * casa con los que ya existan en su sitio (ADR-0002: el organigrama de una
 * organización no se versiona).
 *
 * @package Fmc
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base vocabulary of the four taxonomies: taxonomy => (slug => name).
 *
 * @return array<string, array<string, string>>
 */
function fmc_base_vocabulary(): array {
	return array(
		// Árbol ficticio; los nombres reales se configuran fuera del código.
		'fmc_scope'      => array(
			'ambito-general' => 'Ámbito general',
			'ambito-1'       => array(
				'name'   => 'Ámbito 1',
				'parent' => 'ambito-general',
			),
			'ambito-2'       => array(
				'name'   => 'Ámbito 2',
				'parent' => 'ambito-general',
			),
		),
		'fmc_programme'  => array(
			'programa-a' => 'Programa A',
			'programa-b' => 'Programa B',
		),
		'fmc_topic'      => array(
			'inteligencia-artificial'   => 'Inteligencia artificial',
			'pensamiento-computacional' => 'Pensamiento computacional',
			'creacion-de-contenidos'    => 'Creación de contenidos',
			'seguridad'                 => 'Seguridad',
		),
		// Las cinco áreas de un marco de competencia digital docente.
		'fmc_competence' => array(
			'informacion'  => 'Información y alfabetización informacional',
			'comunicacion' => 'Comunicación y colaboración',
			'contenidos'   => 'Creación de contenidos digitales',
			'seguridad'    => 'Seguridad',
			'problemas'    => 'Resolución de problemas',
		),
	);
}

/**
 * Create the missing terms of the four taxonomies.
 *
 * @return void
 * @throws RuntimeException If a taxonomy is not registered or a term cannot be created.
 */
function fmc_setup_vocabulary(): void {
	$fmc_creados = 0;
	$fmc_habia   = 0;

	foreach ( fmc_base_vocabulary() as $fmc_taxonomy => $fmc_terms ) {
		if ( ! taxonomy_exists( $fmc_taxonomy ) ) {
			throw new RuntimeException( esc_html( "La taxonomía «{$fmc_taxonomy}» no está registrada: el aplicativo no está cargado. Ejecute antes `make bundle && make sync-snippets`." ) );
		}

		foreach ( $fmc_terms as $fmc_slug => $fmc_data ) {
			$fmc_name   = is_array( $fmc_data ) ? $fmc_data['name'] : $fmc_data;
			$fmc_parent = is_array( $fmc_data ) ? get_term_by( 'slug', $fmc_data['parent'], $fmc_taxonomy ) : false;
			if ( is_array( $fmc_data ) && ! ( $fmc_parent instanceof WP_Term ) ) {
				throw new RuntimeException( esc_html( "Falta el ámbito padre de «{$fmc_name}»." ) );
			}
			$fmc_term = get_term_by( 'slug', $fmc_slug, $fmc_taxonomy );
			if ( $fmc_term instanceof WP_Term ) {
				++$fmc_habia;
				continue;
			}

			$fmc_created = wp_insert_term(
				$fmc_name,
				$fmc_taxonomy,
				array(
					'slug'   => $fmc_slug,
					'parent' => $fmc_parent instanceof WP_Term ? $fmc_parent->term_id : 0,
				)
			);
			if ( is_wp_error( $fmc_created ) ) {
				throw new RuntimeException( esc_html( "No se pudo crear «{$fmc_name}» en {$fmc_taxonomy}: " . $fmc_created->get_error_message() ) );
			}

			echo esc_html( "Creado «{$fmc_name}» ({$fmc_taxonomy}/{$fmc_slug})." ) . "\n";
			++$fmc_creados;
		}
	}

	echo esc_html( sprintf( 'Vocabulario del aplicativo: %d término(s) creado(s), %d ya estaban.', $fmc_creados, $fmc_habia ) ) . "\n";
}

// En Code Snippets esto corre antes de `init`, y las taxonomías se registran
// en `init` prioridad 9: hay que esperar. Con `wp eval-file` init ya pasó, así
// que se ejecuta al momento.
if ( did_action( 'init' ) ) {
	fmc_setup_vocabulary();
} else {
	add_action( 'init', 'fmc_setup_vocabulary', 20 );
}

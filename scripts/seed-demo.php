<?php
/**
 * Demo users and content for the local environment (wp eval-file / Playground).
 *
 * Idempotente: el usuario que ya existe se deja, y el contenido de
 * demostración se crea una vez (se reconoce por su slug). Todo inventado.
 *
 * @package Fmc
 */

use Fmc\Meta\ActionMetaKeys;
use Fmc\Meta\DesignMetaKeys;
use Fmc\PostType\PostTypes;
use Fmc\Taxonomy\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( PostTypes::class ) ) {
	throw new RuntimeException( 'El aplicativo no está cargado: ejecute antes `make bundle && make sync-snippets`.' );
}

/**
 * Ensure a demo user exists with a role.
 *
 * @param string $login Login.
 * @param string $role  Role slug.
 * @param string $name  Display name.
 * @return int User ID.
 * @throws RuntimeException If the user cannot be created.
 */
function fmc_seed_user( string $login, string $role, string $name ): int {
	$user = get_user_by( 'login', $login );
	if ( $user ) {
		return (int) $user->ID;
	}
	$id = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_pass'    => 'password',
			'user_email'   => $login . '@example.org',
			'display_name' => $name,
			'role'         => $role,
		)
	);
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( esc_html( $id->get_error_message() ) );
	}
	echo esc_html( "Usuario {$login} ({$role})." ) . "\n";
	return (int) $id;
}

/**
 * Ensure a demo post exists, by slug.
 *
 * @param array<string, mixed>  $post  Post fields; `post_name` is the key.
 * @param array<string, mixed>  $meta  Meta.
 * @param array<string, string> $terms Taxonomy => term slug.
 * @return int Post ID.
 */
function fmc_seed_post( array $post, array $meta, array $terms = array() ): int {
	$found = get_page_by_path( (string) $post['post_name'], OBJECT, (string) $post['post_type'] );
	if ( $found ) {
		return (int) $found->ID;
	}
	$id = (int) wp_insert_post( $post + array( 'meta_input' => $meta ) );
	foreach ( $terms as $taxonomy => $slug ) {
		wp_set_object_terms( $id, $slug, $taxonomy );
	}
	echo esc_html( "Creado «{$post['post_title']}»." ) . "\n";
	return $id;
}

fmc_seed_user( 'curaduria', 'fmc_curator', 'Curaduría de ejemplo' );
$fmc_adviser = fmc_seed_user( 'asesoria', 'fmc_adviser', 'Asesoría de ejemplo' );
fmc_seed_user( 'asesoria2', 'fmc_adviser', 'Asesoría de ejemplo 2' );
fmc_seed_user( 'servicio', 'fmc_training_service', 'Servicio de formación de ejemplo' );

$fmc_design = fmc_seed_post(
	array(
		'post_type'    => PostTypes::DESIGN,
		'post_name'    => 'demo-diseno-ia-en-el-aula',
		'post_title'   => 'La inteligencia artificial en el aula',
		'post_content' => '<p>Diseño de demostración.</p>',
		'post_status'  => 'publish',
		'post_author'  => $fmc_adviser,
	),
	array(
		DesignMetaKeys::CODE         => 'A1',
		DesignMetaKeys::TYPE         => 'course',
		DesignMetaKeys::MODALITY     => 'blended',
		DesignMetaKeys::HOURS_ONSITE => 8,
		DesignMetaKeys::HOURS_ONLINE => 12,
		DesignMetaKeys::IN_CATALOGUE => true,
	),
	array(
		Taxonomies::TOPIC     => 'inteligencia-artificial',
		Taxonomies::PROGRAMME => 'programa-a',
	)
);

fmc_seed_post(
	array(
		'post_type'   => PostTypes::DESIGN,
		'post_name'   => 'demo-diseno-borrador',
		'post_title'  => 'Robótica educativa (borrador)',
		'post_status' => 'pending',
		'post_author' => $fmc_adviser,
	),
	array(
		DesignMetaKeys::TYPE     => 'one_off',
		DesignMetaKeys::MODALITY => 'onsite',
	)
);

fmc_seed_post(
	array(
		'post_type'   => PostTypes::ACTION,
		'post_name'   => 'demo-accion-ia-ambito-1',
		'post_title'  => 'La inteligencia artificial en el aula — Ámbito 1',
		'post_status' => 'publish',
		'post_author' => $fmc_adviser,
	),
	array(
		ActionMetaKeys::DESIGN_ID    => $fmc_design,
		ActionMetaKeys::PLACES       => 25,
		ActionMetaKeys::MODALITY     => 'blended',
		ActionMetaKeys::HOURS_ONSITE => 8,
		ActionMetaKeys::HOURS_ONLINE => 12,
		ActionMetaKeys::START        => gmdate( 'Y-m-d', strtotime( '+2 weeks' ) ),
		ActionMetaKeys::END          => gmdate( 'Y-m-d', strtotime( '+6 weeks' ) ),
		ActionMetaKeys::PROCESS      => 'draft',
		ActionMetaKeys::SITUATION    => 'managing',
		ActionMetaKeys::ADVISER      => $fmc_adviser,
		ActionMetaKeys::FUNDED_BY    => 'centre',
	),
	array( Taxonomies::SCOPE => 'ambito-1' )
);

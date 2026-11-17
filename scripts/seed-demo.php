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
use Fmc\Meta\IncidentMetaKeys;
use Fmc\Meta\SpeakerMetaKeys;
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

$fmc_curator = fmc_seed_user( 'coordinacion', 'fmc_curator', 'Coordinación de ejemplo' );
$fmc_adviser = fmc_seed_user( 'asesoria', 'fmc_adviser', 'Asesoría de ejemplo' );
// El tipo de la asesoría, en su perfil y por sitio (ADR-0019).
update_user_option( $fmc_adviser, 'fmc_adviser_kind', 'ict' );
fmc_seed_user( 'asesoria2', 'fmc_adviser', 'Asesoría de ejemplo 2' );
fmc_seed_user( 'servicio', 'fmc_training_service', 'Servicio de Perfeccionamiento de ejemplo' );

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
		DesignMetaKeys::CODE         => 'A001',
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
		'post_author' => $fmc_curator,
	),
	array(
		DesignMetaKeys::TYPE     => 'one_off',
		DesignMetaKeys::MODALITY => 'onsite',
	)
);

// Lo que ya no se imparte se queda, como histórico (ADR-0016).
fmc_seed_post(
	array(
		'post_type'    => PostTypes::DESIGN,
		'post_name'    => 'demo-diseno-historico',
		'post_title'   => 'Pizarra digital en el aula',
		'post_content' => '<p>Diseño de demostración que ya no se imparte.</p>',
		'post_status'  => PostTypes::ARCHIVED,
		'post_author'  => $fmc_curator,
	),
	array(
		DesignMetaKeys::CODE         => 'A002',
		DesignMetaKeys::TYPE         => 'course',
		DesignMetaKeys::MODALITY     => 'onsite',
		DesignMetaKeys::HOURS_ONSITE => 10,
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

/**
 * A weekday, counted from today: the calendar only shows Monday to Friday.
 *
 * @param int $days Days from today; negative goes back.
 * @return string Y-m-d.
 */
function fmc_seed_weekday( int $days ): string {
	$time = strtotime( $days . ' days', strtotime( gmdate( 'Y-m-d' ) ) );
	while ( (int) gmdate( 'N', $time ) > 5 ) {
		$time = strtotime( ( $days < 0 ? '-1' : '+1' ) . ' day', $time );
	}
	return gmdate( 'Y-m-d', $time );
}

// Ponentes, acciones e incidencia para enseñar el aplicativo con algo dentro:
// las capturas de la ayuda salen de aquí (`make capturas`). Todo inventado.
$fmc_speaker_a = fmc_seed_post(
	array(
		'post_type'   => PostTypes::SPEAKER,
		'post_name'   => 'demo-ponente-ana',
		'post_title'  => 'Pérez Ejemplo, Ana',
		'post_status' => 'publish',
		'post_author' => $fmc_adviser,
	),
	array(
		SpeakerMetaKeys::FIRST_NAME  => 'Ana',
		SpeakerMetaKeys::LAST_NAME   => 'Pérez Ejemplo',
		SpeakerMetaKeys::PROFESSION  => 'secondary_teacher',
		SpeakerMetaKeys::EMAIL       => 'ana@example.org',
		SpeakerMetaKeys::RECOMMENDED => array( $fmc_design ),
	),
	array( Taxonomies::ZONE => 'zona-1' )
);

fmc_seed_post(
	array(
		'post_type'   => PostTypes::SPEAKER,
		'post_name'   => 'demo-ponente-luis',
		'post_title'  => 'Martín Ejemplo, Luis',
		'post_status' => 'publish',
		'post_author' => $fmc_adviser,
	),
	array(
		SpeakerMetaKeys::FIRST_NAME => 'Luis',
		SpeakerMetaKeys::LAST_NAME  => 'Martín Ejemplo',
		SpeakerMetaKeys::PROFESSION => 'external',
		SpeakerMetaKeys::EMAIL      => 'luis@example.org',
		SpeakerMetaKeys::NOTES      => 'Prefiere sesiones de tarde.',
	),
	array( Taxonomies::ZONE => 'zona-2' )
);

fmc_seed_post(
	array(
		'post_type'   => PostTypes::SPEAKER,
		'post_name'   => 'demo-ponente-marta',
		'post_title'  => 'Ruiz Ejemplo, Marta',
		'post_status' => 'publish',
		'post_author' => $fmc_curator,
	),
	array(
		SpeakerMetaKeys::FIRST_NAME  => 'Marta',
		SpeakerMetaKeys::LAST_NAME   => 'Ruiz Ejemplo',
		SpeakerMetaKeys::PROFESSION  => 'primary_teacher',
		SpeakerMetaKeys::UNAVAILABLE => true,
	),
	array( Taxonomies::ZONE => 'zona-1' )
);

$fmc_delivered = fmc_seed_post(
	array(
		'post_type'   => PostTypes::ACTION,
		'post_name'   => 'demo-accion-ia-ambito-2',
		'post_title'  => 'La inteligencia artificial en el aula',
		'post_status' => 'publish',
		'post_author' => $fmc_adviser,
	),
	array(
		ActionMetaKeys::DESIGN_ID    => $fmc_design,
		ActionMetaKeys::SUBTITLE     => 'Segunda edición',
		ActionMetaKeys::FILE_NUMBER  => 'EXP-0001/2026',
		ActionMetaKeys::MODALITY     => 'blended',
		ActionMetaKeys::HOURS_ONSITE => 8,
		ActionMetaKeys::HOURS_ONLINE => 12,
		ActionMetaKeys::START        => fmc_seed_weekday( 2 ),
		ActionMetaKeys::END          => fmc_seed_weekday( 23 ),
		ActionMetaKeys::SESSIONS     => array_map(
			static fn( $d ) => array(
				'day'  => fmc_seed_weekday( $d ),
				'from' => '16:00',
				'to'   => '19:00',
			),
			array( 2, 9, 16, 23 )
		),
		ActionMetaKeys::ADVISER      => $fmc_adviser,
		ActionMetaKeys::SPEAKERS     => array( $fmc_speaker_a ),
		ActionMetaKeys::FUNDED_BY    => 'service',
	),
	array(
		Taxonomies::SCOPE     => 'ambito-2',
		Taxonomies::PROGRAMME => 'programa-a',
	)
);

fmc_seed_post(
	array(
		'post_type'   => PostTypes::ACTION,
		'post_name'   => 'demo-accion-ia-realizada',
		'post_title'  => 'La inteligencia artificial en el aula',
		'post_status' => 'publish',
		'post_author' => $fmc_adviser,
	),
	array(
		ActionMetaKeys::DESIGN_ID    => $fmc_design,
		ActionMetaKeys::SUBTITLE     => 'Primera edición',
		// Como llega lo migrado: «Días y horas» escrito a mano, sin sesiones.
		ActionMetaKeys::SCHEDULE     => 'Lunes y miércoles, de 17:00 a 20:00.',
		ActionMetaKeys::MODALITY     => 'blended',
		ActionMetaKeys::HOURS_ONSITE => 8,
		ActionMetaKeys::HOURS_ONLINE => 12,
		ActionMetaKeys::START        => fmc_seed_weekday( -9 ),
		ActionMetaKeys::END          => fmc_seed_weekday( -1 ),
		ActionMetaKeys::ADVISER      => $fmc_adviser,
		ActionMetaKeys::FUNDED_BY    => 'area',
	),
	array( Taxonomies::SCOPE => 'ambito-1' )
);

fmc_seed_post(
	array(
		'post_type'   => PostTypes::ACTION,
		'post_name'   => 'demo-accion-borrador',
		'post_title'  => 'La inteligencia artificial en el aula',
		'post_status' => 'draft',
		'post_author' => $fmc_adviser,
	),
	array(
		ActionMetaKeys::DESIGN_ID    => $fmc_design,
		ActionMetaKeys::SUBTITLE     => 'Edición de tarde',
		ActionMetaKeys::MODALITY     => 'blended',
		ActionMetaKeys::HOURS_ONSITE => 8,
		ActionMetaKeys::HOURS_ONLINE => 12,
		ActionMetaKeys::START        => fmc_seed_weekday( 8 ),
		ActionMetaKeys::ADVISER      => $fmc_adviser,
		ActionMetaKeys::FUNDED_BY    => 'centre',
	),
	array( Taxonomies::SCOPE => 'ambito-1' )
);

// Una incidencia sin resolver: la campana del servicio tiene algo que contar.
fmc_seed_post(
	array(
		'post_type'   => PostTypes::INCIDENT,
		'post_name'   => 'demo-incidencia-aplazar',
		'post_title'  => 'La inteligencia artificial en el aula',
		'post_status' => 'publish',
		'post_author' => $fmc_adviser,
		'post_parent' => $fmc_delivered,
	),
	array(
		IncidentMetaKeys::REQUEST    => 'postpone',
		IncidentMetaKeys::REASON     => 'La ponente no puede en las fechas previstas.',
		IncidentMetaKeys::PROPOSAL   => 'Retrasar la acción dos semanas.',
		IncidentMetaKeys::RESOLUTION => 'pending',
	)
);

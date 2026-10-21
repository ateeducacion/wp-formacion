<?php
/**
 * Snippet Name: FMC — Aplicativo de formación (CPT)
 * Description: Diseños de curso, acciones formativas, ponentes e incidencias, sus taxonomías y los permisos por campo. Código generado desde src/Fmc — no editar a mano; ejecutar php build/pack-snippet.php.
 * Scope: global
 * Priority: 15
 *
 * @package Fmc
 * @version 0.0.3
 */

// phpcs:disable








namespace Fmc\Meta;





defined( 'ABSPATH' ) || exit;




if ( \defined( 'FMC_BUNDLE_LOADED' ) ) {
	return;
}
\define( 'FMC_BUNDLE_LOADED', true );









final class MetaTypes {

	public const STRING = 'string';
	public const TEXT   = 'text';
	public const HTML   = 'html';
	public const INT    = 'int';
	public const NUMBER = 'number';
	public const BOOL   = 'bool';
	public const DATE   = 'date';
	public const URL    = 'url';
	public const EMAIL  = 'email';
	public const IDS    = 'ids';
	public const LIST   = 'list';







	public static function wp_type( string $type ): string {
		switch ( $type ) {
			case self::INT:
				return 'integer';
			case self::NUMBER:
				return 'number';
			case self::BOOL:
				return 'boolean';
			case self::IDS:
			case self::LIST:
				return 'array';
			default:
				return 'string';
		}
	}











	public static function sanitize( string $type, $value ) {
		switch ( $type ) {
			case self::TEXT:
				return sanitize_textarea_field( (string) $value );
			case self::HTML:
				return wp_kses_post( (string) $value );
			case self::INT:
				return max( 0, (int) $value );
			case self::NUMBER:
				return max( 0.0, round( (float) str_replace( ',', '.', (string) $value ), 2 ) );
			case self::BOOL:
				return (bool) filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			case self::DATE:
				return self::ymd( (string) $value );
			case self::URL:
				return esc_url_raw( (string) $value, array( 'http', 'https' ) );
			case self::EMAIL:
				return (string) sanitize_email( (string) $value );
			case self::IDS:
				return array_values( array_unique( array_filter( array_map( 'absint', (array) $value ) ) ) );
			case self::LIST:
				return array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $value ) ) ) );
			default:
				return sanitize_text_field( (string) $value );
		}
	}







	private static function ymd( string $value ): string {
		$value = trim( $value );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return '';
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : '';
	}
}








namespace Fmc\Meta;








final class DesignMetaKeys {

	public const CODE          = 'fmc_code';
	public const TYPE          = 'fmc_type';
	public const MODALITY      = 'fmc_modality';
	public const HOURS_ONSITE  = 'fmc_hours_onsite';
	public const HOURS_ONLINE  = 'fmc_hours_online';
	public const AUDIENCE      = 'fmc_audience';
	public const OBJECTIVES    = 'fmc_objectives';
	public const CONTENTS      = 'fmc_contents';
	public const METHODOLOGY   = 'fmc_methodology';
	public const PRACTICE      = 'fmc_practice';
	public const TIMING        = 'fmc_timing';
	public const NOTES         = 'fmc_notes';
	public const AUTHORSHIP    = 'fmc_authorship';
	public const DIGCOMP_URL   = 'fmc_digcomp_url';
	public const IMAGE         = 'fmc_image';
	public const DESIGN_FILE   = 'fmc_design_file';
	public const SESSIONS_FILE = 'fmc_sessions_file';
	public const SUPPORT_FILE  = 'fmc_support_file';
	public const IN_CATALOGUE  = 'fmc_in_catalogue';
	public const REPLACES      = 'fmc_replaces';




	public const CODE_PREFIX = 'A';




	public const TYPES = array(
		'course'       => 'Curso',
		'one_off'      => 'Acción puntual',
		'e_learning'   => 'Teleformación',
		'self_paced'   => 'Autodirigido',
		'in_classroom' => 'Intervención en aula',
	);




	public const MODALITIES = array(
		'onsite'  => 'Presencial',
		'online'  => 'En línea',
		'blended' => 'Mixta',
	);






	public static function map(): array {
		return array(
			self::CODE          => MetaTypes::STRING,
			self::TYPE          => MetaTypes::STRING,
			self::MODALITY      => MetaTypes::STRING,
			self::HOURS_ONSITE  => MetaTypes::NUMBER,
			self::HOURS_ONLINE  => MetaTypes::NUMBER,
			self::AUDIENCE      => MetaTypes::HTML,
			self::OBJECTIVES    => MetaTypes::HTML,
			self::CONTENTS      => MetaTypes::HTML,
			self::METHODOLOGY   => MetaTypes::HTML,
			self::PRACTICE      => MetaTypes::HTML,
			self::TIMING        => MetaTypes::HTML,
			self::NOTES         => MetaTypes::HTML,
			self::AUTHORSHIP    => MetaTypes::STRING,
			self::DIGCOMP_URL   => MetaTypes::URL,

			self::IMAGE         => MetaTypes::URL,
			self::DESIGN_FILE   => MetaTypes::URL,
			self::SESSIONS_FILE => MetaTypes::URL,
			self::SUPPORT_FILE  => MetaTypes::URL,
			self::IN_CATALOGUE  => MetaTypes::BOOL,
			self::REPLACES      => MetaTypes::IDS,
		);
	}
}








namespace Fmc\Meta;








final class ActionMetaKeys {

	public const DESIGN_ID        = 'fmc_design_id';
	public const FILE_NUMBER      = 'fmc_file_number';
	public const INTERNAL_REF     = 'fmc_internal_ref';
	public const TRAINING_FILE    = 'fmc_training_file';
	public const TRAINING_PLANS   = 'fmc_training_plans';
	public const ITINERARY        = 'fmc_itinerary';
	public const STRATEGIC_LINE   = 'fmc_strategic_line';
	public const TRAVEL_HOURS     = 'fmc_travel_hours';
	public const ONLINE_ENROL     = 'fmc_online_enrolment';
	public const SERVICE_REQUEST  = 'fmc_service_request';
	public const SERVICE_OFFICER  = 'fmc_service_officer';
	public const SERVICE_MANAGER  = 'fmc_service_manager';
	public const COMPANY_TECH     = 'fmc_company_technician';
	public const DOCS_FILE        = 'fmc_docs_file';
	public const SUBTITLE         = 'fmc_subtitle';
	public const FUNDED_BY        = 'fmc_funded_by';
	public const VIDEOCONFERENCE  = 'fmc_videoconference';
	public const VENUE_CENTRE     = 'fmc_venue_centre';
	public const PLACES           = 'fmc_places';
	public const MODALITY         = 'fmc_modality';
	public const HOURS_ONSITE     = 'fmc_hours_onsite';
	public const HOURS_ONLINE     = 'fmc_hours_online';
	public const REPLICAS         = 'fmc_replicas';
	public const ENROL_START      = 'fmc_enrol_start';
	public const ENROL_END        = 'fmc_enrol_end';
	public const ENROL_URL        = 'fmc_enrol_url';
	public const PROVISIONAL_LIST = 'fmc_provisional_list';
	public const CLAIMS_START     = 'fmc_claims_start';
	public const CLAIMS_END       = 'fmc_claims_end';
	public const FINAL_LIST       = 'fmc_final_list';
	public const START            = 'fmc_start';
	public const END              = 'fmc_end';
	public const SCHEDULE         = 'fmc_schedule';
	public const CERTIFICATION    = 'fmc_certification';
	public const COURSE_SPACE_URL = 'fmc_course_space_url';
	public const NOTES            = 'fmc_notes';
	public const PROCESS          = 'fmc_process';
	public const SITUATION        = 'fmc_situation';
	public const ADVISER          = 'fmc_adviser';
	public const SPEAKERS         = 'fmc_speakers';
	public const ENROLLED_MEN     = 'fmc_enrolled_men';
	public const ENROLLED_WOMEN   = 'fmc_enrolled_women';
	public const ATTENDED_MEN     = 'fmc_attended_men';
	public const ATTENDED_WOMEN   = 'fmc_attended_women';
	public const CERTIFIED_MEN    = 'fmc_certified_men';
	public const CERTIFIED_WOMEN  = 'fmc_certified_women';
	public const DOCS_DELIVERED   = 'fmc_docs_delivered';
	public const COMPLETED        = 'fmc_completed';




	public const TRAINING_PLAN_KINDS = array(
		'plan'      => 'Incluida en su plan de formación',
		'itinerary' => 'Incluida en un itinerario formativo',
		'seminar'   => 'Incluida en un seminario o grupo de trabajo',
	);




	public const FUNDERS = array(
		'none'    => 'Sin coste',
		'centre'  => 'Centro de formación',
		'area'    => 'Área',
		'service' => 'Servicio de formación',
	);




	public const PROCESSES = array(
		'draft'     => 'En borrador',
		'paid'      => 'Pagada por el centro',
		'delivered' => 'Entregada al área',
		'closed'    => 'Cerrada y entregada al servicio',
	);




	public const SITUATIONS = array(
		'managing'  => 'Gestionándose',
		'running'   => 'Impartiéndose',
		'done'      => 'Realizada',
		'postponed' => 'Aplazada',
		'cancelled' => 'Cancelada',
	);






	public static function map(): array {
		return array(
			self::DESIGN_ID        => MetaTypes::INT,
			self::FILE_NUMBER      => MetaTypes::STRING,
			self::INTERNAL_REF     => MetaTypes::STRING,
			self::TRAINING_FILE    => MetaTypes::STRING,
			self::TRAINING_PLANS   => MetaTypes::LIST,
			self::ITINERARY        => MetaTypes::STRING,
			self::STRATEGIC_LINE   => MetaTypes::STRING,
			self::TRAVEL_HOURS     => MetaTypes::BOOL,
			self::ONLINE_ENROL     => MetaTypes::BOOL,
			self::SERVICE_REQUEST  => MetaTypes::BOOL,
			self::SERVICE_OFFICER  => MetaTypes::STRING,
			self::SERVICE_MANAGER  => MetaTypes::STRING,
			self::COMPANY_TECH     => MetaTypes::STRING,
			self::DOCS_FILE        => MetaTypes::URL,
			self::SUBTITLE         => MetaTypes::STRING,
			self::FUNDED_BY        => MetaTypes::STRING,
			self::VIDEOCONFERENCE  => MetaTypes::BOOL,
			self::VENUE_CENTRE     => MetaTypes::STRING,
			self::PLACES           => MetaTypes::INT,
			self::MODALITY         => MetaTypes::STRING,
			self::HOURS_ONSITE     => MetaTypes::NUMBER,
			self::HOURS_ONLINE     => MetaTypes::NUMBER,
			self::REPLICAS         => MetaTypes::INT,
			self::ENROL_START      => MetaTypes::DATE,
			self::ENROL_END        => MetaTypes::DATE,
			self::ENROL_URL        => MetaTypes::URL,
			self::PROVISIONAL_LIST => MetaTypes::DATE,
			self::CLAIMS_START     => MetaTypes::DATE,
			self::CLAIMS_END       => MetaTypes::DATE,
			self::FINAL_LIST       => MetaTypes::DATE,
			self::START            => MetaTypes::DATE,
			self::END              => MetaTypes::DATE,
			self::SCHEDULE         => MetaTypes::TEXT,
			self::CERTIFICATION    => MetaTypes::HTML,
			self::COURSE_SPACE_URL => MetaTypes::URL,
			self::NOTES            => MetaTypes::HTML,
			self::PROCESS          => MetaTypes::STRING,
			self::SITUATION        => MetaTypes::STRING,
			self::ADVISER          => MetaTypes::INT,
			self::SPEAKERS         => MetaTypes::IDS,
			self::ENROLLED_MEN     => MetaTypes::INT,
			self::ENROLLED_WOMEN   => MetaTypes::INT,
			self::ATTENDED_MEN     => MetaTypes::INT,
			self::ATTENDED_WOMEN   => MetaTypes::INT,
			self::CERTIFIED_MEN    => MetaTypes::INT,
			self::CERTIFIED_WOMEN  => MetaTypes::INT,
			self::DOCS_DELIVERED   => MetaTypes::DATE,
			self::COMPLETED        => MetaTypes::DATE,
		);
	}
}








namespace Fmc\Meta;







final class SpeakerMetaKeys {

	public const FIRST_NAME  = 'fmc_first_name';
	public const LAST_NAME   = 'fmc_last_name';
	public const ID_NUMBER   = 'fmc_id_number';
	public const PHONE       = 'fmc_phone';
	public const PROFESSION  = 'fmc_profession';
	public const ZONE        = 'fmc_zone';
	public const EMAIL       = 'fmc_email';
	public const EMAIL_ALT   = 'fmc_email_alt';
	public const WEBSITE     = 'fmc_website';
	public const ADDRESS     = 'fmc_address';
	public const UNAVAILABLE = 'fmc_unavailable';
	public const NOTES       = 'fmc_notes';
	public const RECOMMENDED = 'fmc_recommended_designs';




	public const TRASH_REASON = 'fmc_trash_reason';
	public const TRASHED_BY   = 'fmc_trashed_by';
	public const TRASHED_AT   = 'fmc_trashed_at';






	public static function map(): array {
		return array(
			self::FIRST_NAME   => MetaTypes::STRING,
			self::LAST_NAME    => MetaTypes::STRING,
			self::ID_NUMBER    => MetaTypes::STRING,
			self::PHONE        => MetaTypes::STRING,
			self::PROFESSION   => MetaTypes::STRING,
			self::ZONE         => MetaTypes::STRING,
			self::EMAIL        => MetaTypes::EMAIL,
			self::EMAIL_ALT    => MetaTypes::EMAIL,
			self::WEBSITE      => MetaTypes::URL,
			self::ADDRESS      => MetaTypes::TEXT,
			self::UNAVAILABLE  => MetaTypes::BOOL,
			self::NOTES        => MetaTypes::TEXT,
			self::RECOMMENDED  => MetaTypes::IDS,
			self::TRASH_REASON => MetaTypes::TEXT,
			self::TRASHED_BY   => MetaTypes::INT,
			self::TRASHED_AT   => MetaTypes::STRING,
		);
	}
}








namespace Fmc\Meta;








final class IncidentMetaKeys {

	public const REASON           = 'fmc_reason';
	public const PROPOSAL         = 'fmc_proposal';
	public const HAS_COST         = 'fmc_has_cost';
	public const RESOLUTION       = 'fmc_resolution';
	public const RESOLUTION_NOTES = 'fmc_resolution_notes';




	public const RESOLUTIONS = array(
		'pending'  => 'Pendiente',
		'approved' => 'Aprobada',
		'denied'   => 'Denegada',
	);






	public static function map(): array {
		return array(
			self::REASON           => MetaTypes::TEXT,
			self::PROPOSAL         => MetaTypes::TEXT,
			self::HAS_COST         => MetaTypes::BOOL,
			self::RESOLUTION       => MetaTypes::STRING,
			self::RESOLUTION_NOTES => MetaTypes::TEXT,
		);
	}
}








namespace Fmc\Meta;

use Fmc\PostType\PostTypes;









final class MetaRegistration {






	public static function register(): void {
		add_action( 'init', array( self::class, 'register_meta' ), 12 );
	}






	public static function maps(): array {
		return array(
			PostTypes::DESIGN   => DesignMetaKeys::map(),
			PostTypes::ACTION   => ActionMetaKeys::map(),
			PostTypes::SPEAKER  => SpeakerMetaKeys::map(),
			PostTypes::INCIDENT => IncidentMetaKeys::map(),
		);
	}






	public static function enums(): array {
		return array(
			PostTypes::DESIGN   => array(
				DesignMetaKeys::TYPE     => DesignMetaKeys::TYPES,
				DesignMetaKeys::MODALITY => DesignMetaKeys::MODALITIES,
			),
			PostTypes::ACTION   => array(
				ActionMetaKeys::MODALITY       => DesignMetaKeys::MODALITIES,
				ActionMetaKeys::FUNDED_BY      => ActionMetaKeys::FUNDERS,
				ActionMetaKeys::PROCESS        => ActionMetaKeys::PROCESSES,
				ActionMetaKeys::SITUATION      => ActionMetaKeys::SITUATIONS,
				ActionMetaKeys::TRAINING_PLANS => ActionMetaKeys::TRAINING_PLAN_KINDS,
			),
			PostTypes::INCIDENT => array(
				IncidentMetaKeys::RESOLUTION => IncidentMetaKeys::RESOLUTIONS,
			),
		);
	}






	public static function guarded(): array {
		return array(
			PostTypes::ACTION   => array(
				ActionMetaKeys::FILE_NUMBER     => PostTypes::CAP_SERVICE_FIELDS,
				ActionMetaKeys::SITUATION       => PostTypes::CAP_SERVICE_FIELDS,
				ActionMetaKeys::SERVICE_OFFICER => PostTypes::CAP_SERVICE_FIELDS,
				ActionMetaKeys::SERVICE_MANAGER => PostTypes::CAP_SERVICE_FIELDS,
			),
			PostTypes::INCIDENT => array(
				IncidentMetaKeys::RESOLUTION       => PostTypes::CAP_RESOLVE,
				IncidentMetaKeys::RESOLUTION_NOTES => PostTypes::CAP_RESOLVE,
			),
		);
	}






	public static function register_meta(): void {
		foreach ( self::maps() as $type => $map ) {
			foreach ( $map as $key => $value_type ) {
				register_post_meta(
					$type,
					$key,
					array(
						'type'              => MetaTypes::wp_type( $value_type ),
						'single'            => true,
						'show_in_rest'      => false,
						'sanitize_callback' => static function ( $value ) use ( $type, $key ) {
							return self::sanitize( $type, $key, $value );
						},
						'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) use ( $type ) {
							unset( $allowed );
							return self::can_write( $type, (string) $meta_key, (int) $post_id );
						},
					)
				);
			}
		}
	}









	public static function sanitize( string $type, string $key, $value ) {
		$value_type = self::maps()[ $type ][ $key ] ?? MetaTypes::STRING;
		$clean      = MetaTypes::sanitize( $value_type, $value );

		$list = self::enums()[ $type ][ $key ] ?? null;
		if ( null === $list ) {
			return $clean;
		}
		if ( is_array( $clean ) ) {
			return array_values( array_intersect( $clean, array_keys( $list ) ) );
		}
		return array_key_exists( (string) $clean, $list ) ? $clean : '';
	}









	public static function can_write( string $type, string $key, int $post_id ): bool {
		$cap = self::guarded()[ $type ][ $key ] ?? '';
		if ( '' !== $cap ) {
			return current_user_can( $cap );
		}
		return current_user_can( 'edit_post', $post_id );
	}
}








namespace Fmc\Domain;







final class SchoolYear {




	public const FIRST_MONTH = 9;







	public static function of( string $date ): string {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-\d{2}$/', $date, $m ) ) {
			return '';
		}
		$start = (int) $m[2] >= self::FIRST_MONTH ? (int) $m[1] : (int) $m[1] - 1;
		return $start . '-' . ( $start + 1 );
	}
}








namespace Fmc\Domain;










final class DigComp {




	public const COMPETENCES = array(
		'1.1' => 'Comunicación organizativa',
		'1.2' => 'Participación, colaboración y coordinación profesional',
		'1.3' => 'Práctica reflexiva',
		'1.4' => 'Desarrollo profesional digital continuo',
		'1.5' => 'Protección de datos personales, privacidad, seguridad y bienestar digital',
		'2.1' => 'Búsqueda y selección de contenidos digitales',
		'2.2' => 'Creación y modificación de contenidos digitales',
		'2.3' => 'Protección, gestión y compartición de contenidos digitales',
		'3.1' => 'Enseñanza',
		'3.2' => 'Orientación y apoyo en el aprendizaje',
		'3.3' => 'Aprendizaje entre iguales',
		'3.4' => 'Aprendizaje autorregulado',
		'4.1' => 'Estrategias de evaluación',
		'4.2' => 'Analíticas y evidencias de aprendizaje',
		'4.3' => 'Retroalimentación, programación y toma de decisiones',
		'5.1' => 'Accesibilidad e inclusión',
		'5.2' => 'Atención a las diferencias personales en el aprendizaje',
		'5.3' => 'Compromiso activo del alumnado con su propio aprendizaje',
		'6.1' => 'Alfabetización mediática y en el tratamiento de la información y de los datos',
		'6.2' => 'Comunicación, colaboración y ciudadanía digital',
		'6.3' => 'Creación de contenidos digitales',
		'6.4' => 'Uso responsable y bienestar digital',
		'6.5' => 'Resolución de problemas',
	);







	public static function summary( string $url ): array {
		$query = (string) parse_url( $url, PHP_URL_QUERY ); 
		parse_str( $query, $args );
		$out = array();
		foreach ( explode( '|', is_string( $args['c'] ?? null ) ? $args['c'] : '' ) as $item ) {
			if ( preg_match( '/^(\d\.\d)\.([ABC][12])\.\d+$/', trim( $item ), $m ) ) {
				$out[ $m[1] ][ $m[2] ] = ( $out[ $m[1] ][ $m[2] ] ?? 0 ) + 1;
			}
		}
		ksort( $out );
		foreach ( $out as &$levels ) {
			ksort( $levels );
		}
		return $out;
	}
}








namespace Fmc\PostType;











final class PostTypes {

	public const DESIGN   = 'fmc_design';
	public const ACTION   = 'fmc_action';
	public const SPEAKER  = 'fmc_speaker';
	public const INCIDENT = 'fmc_incident';





	public const CAP_SERVICE_FIELDS = 'fmc_edit_service_fields';




	public const CAP_RESOLVE = 'fmc_resolve_incidents';






	public static function definitions(): array {
		return array(
			self::DESIGN   => array(
				'singular' => 'Diseño de curso',
				'plural'   => 'Diseños de curso',

				'public'   => true,
				'icon'     => 'dashicons-welcome-learn-more',
			),
			self::ACTION   => array(
				'singular' => 'Acción formativa',
				'plural'   => 'Acciones formativas',
				'public'   => false,
				'icon'     => 'dashicons-calendar-alt',
			),
			self::SPEAKER  => array(
				'singular' => 'Ponente',
				'plural'   => 'Ponentes',

				'public'   => false,
				'icon'     => 'dashicons-groups',
			),
			self::INCIDENT => array(
				'singular' => 'Incidencia',
				'plural'   => 'Incidencias',
				'public'   => false,
				'icon'     => 'dashicons-flag',
			),
		);
	}






	public static function register(): void {
		foreach ( self::definitions() as $type => $def ) {
			register_post_type(
				$type,
				array(
					'labels'          => array(
						'name'          => $def['plural'],
						'singular_name' => $def['singular'],
						'menu_name'     => $def['plural'],
					),
					'public'          => $def['public'],
					'show_ui'         => true,
					'show_in_rest'    => false,
					'menu_icon'       => $def['icon'],
					'supports'        => array( 'title', 'editor', 'author', 'revisions' ),
					'has_archive'     => false,
					'capability_type' => array( $type, $type . 's' ),
					'map_meta_cap'    => true,
				)
			);
		}
	}









	public static function role_caps(): array {
		$all = array( 'edit', 'edit_others', 'edit_private', 'edit_published', 'publish', 'read_private', 'delete', 'delete_others', 'delete_private', 'delete_published' );
		$own = array( 'edit', 'edit_published', 'publish', 'delete' );

		return array(
			'administrator'        => array_fill_keys( array_keys( self::definitions() ), $all ),
			'fmc_curator'          => array_fill_keys( array_keys( self::definitions() ), $all ),
			'fmc_adviser'          => array(

				self::DESIGN   => array( 'edit', 'delete' ),
				self::ACTION   => $own,


				self::SPEAKER  => array_merge( $own, array( 'delete_others', 'delete_published' ) ),
				self::INCIDENT => array( 'edit', 'publish' ),
			),


			'fmc_training_service' => array(
				self::ACTION   => array( 'read_private' ),
				self::INCIDENT => array( 'read_private' ),
			),
		);
	}






	public static function grant_caps_to_roles(): void {
		foreach ( self::role_caps() as $slug => $types ) {
			$role = get_role( $slug );
			if ( ! $role ) {
				continue;
			}
			foreach ( $types as $type => $primitives ) {
				foreach ( $primitives as $primitive ) {
					$cap = $primitive . '_' . $type . 's';
					if ( ! $role->has_cap( $cap ) ) {
						$role->add_cap( $cap );
					}
				}
			}
		}

		$extra = array(
			'administrator'        => array( self::CAP_SERVICE_FIELDS, self::CAP_RESOLVE ),
			'fmc_curator'          => array( self::CAP_SERVICE_FIELDS, self::CAP_RESOLVE ),
			'fmc_training_service' => array( self::CAP_SERVICE_FIELDS, self::CAP_RESOLVE ),
		);
		foreach ( $extra as $slug => $caps ) {
			$role = get_role( $slug );
			foreach ( $role ? $caps : array() as $cap ) {
				if ( ! $role->has_cap( $cap ) ) {
					$role->add_cap( $cap );
				}
			}
		}
	}
}








namespace Fmc\Taxonomy;

use Fmc\PostType\PostTypes;










final class Taxonomies {

	public const PROGRAMME  = 'fmc_programme';
	public const TOPIC      = 'fmc_topic';
	public const COMPETENCE = 'fmc_competence';
	public const SCOPE      = 'fmc_scope';






	public static function definitions(): array {
		return array(
			self::PROGRAMME  => array(
				'singular'     => 'Programa',
				'plural'       => 'Programas',
				'types'        => array( PostTypes::DESIGN, PostTypes::ACTION ),
				'hierarchical' => false,
			),
			self::TOPIC      => array(
				'singular'     => 'Temática',
				'plural'       => 'Temáticas',
				'types'        => array( PostTypes::DESIGN ),
				'hierarchical' => false,
			),
			self::COMPETENCE => array(
				'singular'     => 'Área de competencia digital',
				'plural'       => 'Áreas de competencia digital',
				'types'        => array( PostTypes::DESIGN ),
				'hierarchical' => false,
			),


			self::SCOPE      => array(
				'singular'     => 'Ámbito',
				'plural'       => 'Ámbitos',
				'types'        => array( PostTypes::ACTION ),
				'hierarchical' => true,
			),
		);
	}






	public static function register(): void {
		foreach ( self::definitions() as $slug => $def ) {
			register_taxonomy(
				$slug,
				$def['types'],
				array(
					'labels'            => array(
						'name'          => $def['plural'],
						'singular_name' => $def['singular'],
					),
					'public'            => false,
					'show_ui'           => true,
					'show_admin_column' => true,
					'show_in_rest'      => false,
					'hierarchical'      => $def['hierarchical'],
					'rewrite'           => false,
					'capabilities'      => array(
						'manage_terms' => 'fmc_manage_app',
						'edit_terms'   => 'fmc_manage_app',
						'delete_terms' => 'fmc_manage_app',
						'assign_terms' => 'read',
					),
				)
			);
		}
	}
}








namespace Fmc\PublicFront;






final class Assets {






	private static $inline = array();







	public static function set_inline( array $assets ): void {
		self::$inline = $assets;
	}







	public static function contents( string $rel ): string {
		if ( isset( self::$inline[ $rel ] ) ) {
			return self::$inline[ $rel ];
		}
		$path = defined( 'FMC_SRC_DIR' ) ? dirname( FMC_SRC_DIR, 2 ) . '/assets/' . $rel : '';

		return '' !== $path && is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	}






	public static function css(): string {
		return self::contents( 'css/fmc-app.css' );
	}
}








namespace Fmc\PublicFront;

use Fmc\Meta\ActionMetaKeys as A;
use Fmc\Meta\DesignMetaKeys as D;
use Fmc\Meta\IncidentMetaKeys as I;
use Fmc\Meta\SpeakerMetaKeys as S;
use Fmc\PostType\PostTypes;


















final class Fields {







	public static function sections( string $type ): array {
		$builders = array(
			PostTypes::DESIGN   => 'design',
			PostTypes::ACTION   => 'action',
			PostTypes::SPEAKER  => 'speaker',
			PostTypes::INCIDENT => 'incident',
		);
		if ( ! isset( $builders[ $type ] ) ) {
			return array();
		}
		$sections = call_user_func( array( self::class, $builders[ $type ] ) );
		$rules    = self::rules()[ $type ] ?? array();
		foreach ( $sections as &$section ) {
			foreach ( $section['fields'] as $key => &$field ) {
				$field = array_merge( $field, $rules[ $key ] ?? array() );
			}
		}
		return $sections;
	}






	public static function rules(): array {
		$onsite = array( 'onsite', 'blended' );
		$online = array( 'online', 'blended' );


		$rich = array( 'w' => 'rich' );
		return array(
			PostTypes::DESIGN   => array(
				'post_title'    => array( 'unique' => true ),
				'post_content'  => $rich,
				D::AUDIENCE     => $rich,
				D::OBJECTIVES   => $rich,
				D::CONTENTS     => $rich,
				D::METHODOLOGY  => $rich,
				D::PRACTICE     => $rich,
				D::TIMING       => $rich,
				D::NOTES        => $rich,
				D::HOURS_ONSITE => array(
					'max'  => 200,
					'show' => array( D::MODALITY => $onsite ),
				),
				D::HOURS_ONLINE => array(
					'max'  => 200,
					'show' => array( D::MODALITY => $online ),
				),
			),
			PostTypes::ACTION   => array(
				A::FILE_NUMBER   => array( 'unique' => true ),
				A::PLACES        => array(
					'min' => 1,
					'max' => 10000,
				),
				A::HOURS_ONSITE  => array(
					'max'  => 500,
					'show' => array( A::MODALITY => $onsite ),
				),
				A::HOURS_ONLINE  => array(
					'max'  => 500,
					'show' => array( A::MODALITY => $online ),
				),
				A::REPLICAS      => array(
					'min' => 1,
					'max' => 10,
				),
				A::TRAINING_FILE => array( 'show' => array( A::TRAINING_PLANS => array( 'plan', 'seminar' ) ) ),
				A::CERTIFICATION => array(
					'w'    => 'rich',
					'show' => array( A::MODALITY => $online ),
				),
				A::NOTES         => $rich,
				A::SPEAKERS      => array( 'add' => PostTypes::SPEAKER ),
			),
			PostTypes::SPEAKER  => array(
				S::ID_NUMBER   => array( 'unique' => true ),

				S::RECOMMENDED => array( 'catalogue' => true ),
			),
			PostTypes::INCIDENT => array(
				I::RESOLUTION_NOTES => array( 'show' => array( I::RESOLUTION => array( 'approved', 'denied' ) ) ),
			),
		);
	}







	public static function all( string $type ): array {
		$out = array();
		foreach ( self::sections( $type ) as $section ) {
			$out += $section['fields'];
		}
		return $out;
	}






	private static function design(): array {
		return array(
			array(
				'title'  => 'Identidad',
				'help'   => 'Cómo se llama el diseño y de qué tipo es. El código lo asigna el aplicativo y no cambia.',
				'fields' => array(
					'post_title'    => array(
						'label' => 'Título del curso',
						'w'     => 'text',
						'req'   => true,
						'help'  => 'Tal y como sale en el catálogo. Empiece por «Curso:» o «APU:» si así se viene haciendo.',
					),
					D::CODE         => array(
						'label' => 'Código',
						'w'     => 'text',
						'auto'  => true,
						'col'   => 4,
						'help'  => 'El siguiente libre; se asigna al guardar por primera vez.',
					),
					D::TYPE         => array(
						'label'   => 'Tipo de formación',
						'w'       => 'select',
						'choices' => D::TYPES,
						'col'     => 4,
						'req'     => true,
					),
					D::MODALITY     => array(
						'label'   => 'Modalidad',
						'w'       => 'select',
						'choices' => D::MODALITIES,
						'col'     => 4,
						'req'     => true,
					),
					D::HOURS_ONSITE => array(
						'label' => 'Horas presenciales',
						'w'     => 'number',
						'col'   => 4,
					),
					D::HOURS_ONLINE => array(
						'label' => 'Horas en línea',
						'w'     => 'number',
						'col'   => 4,
					),
					D::IN_CATALOGUE => array(
						'label' => 'Sale en el catálogo público',
						'w'     => 'checkbox',
						'col'   => 4,
						'help'  => 'Aparte de estar finalizado. Fuera del catálogo, es histórico: ya no se recomienda a ponentes.',
					),
				),
			),
			array(
				'title'  => 'Clasificación',
				'help'   => 'Con qué se busca y se agrupa el diseño.',
				'fields' => array(
					'tax:fmc_programme' => array(
						'label' => 'Programas',
						'w'     => 'terms',
						'help'  => 'El programa o proyecto para el que se crea. Puede ser más de uno.',
					),
					'tax:fmc_topic'     => array(
						'label' => 'Temática',
						'w'     => 'term',
						'col'   => 6,
					),
					D::REPLACES         => array(
						'label' => 'Sustituye a',
						'w'     => 'designs',
						'col'   => 6,
						'help'  => 'Los diseños que este deja en histórico: sus ponentes recomendados pasan a serlo de este.',
					),


					D::DIGCOMP_URL      => array(
						'label' => 'Perfil de competencia digital',
						'w'     => 'url',
						'help'  => 'Elija las competencias en la herramienta de competencia digital, copie la dirección que genera al compartir y péguela aquí. Al guardar se ve el resumen.',
					),
				),
			),
			array(
				'title'  => 'Contenido del diseño',
				'help'   => 'Lo que se publica en la ficha del curso.',
				'fields' => array(
					'post_content' => array(
						'label' => 'Descripción',
						'w'     => 'textarea',
						'req'   => true,
					),
					D::AUDIENCE    => array(
						'label' => 'Destinatarios',
						'w'     => 'textarea',
					),
					D::OBJECTIVES  => array(
						'label' => 'Objetivos',
						'w'     => 'textarea',
					),
					D::CONTENTS    => array(
						'label' => 'Contenidos',
						'w'     => 'textarea',
					),
					D::METHODOLOGY => array(
						'label' => 'Metodología',
						'w'     => 'textarea',
					),
					D::PRACTICE    => array(
						'label' => 'Fase práctica',
						'w'     => 'textarea',
					),
					D::TIMING      => array(
						'label' => 'Temporalización',
						'w'     => 'textarea',
					),
					D::NOTES       => array(
						'label' => 'Observaciones',
						'w'     => 'textarea',
					),
					D::AUTHORSHIP  => array(
						'label' => 'Autoría del diseño',
						'w'     => 'text',
					),
				),
			),
			array(
				'title'  => 'Documentos',
				'help'   => 'El diseño, el minutaje y el material de apoyo. Cada documento se puede cambiar por otro o quitar; los cambios se guardan con el diseño.',
				'fields' => array(
					'docs' => array(
						'label' => 'Documentos',
						'w'     => 'docs',
					),
				),
			),
		);
	}






	private static function action(): array {
		return array(
			array(
				'title'  => 'La acción',
				'help'   => 'De qué diseño sale y quién la gestiona. El título es siempre el del diseño; la modalidad y las horas se toman de él si se dejan en blanco.',
				'fields' => array(
					A::DESIGN_ID       => array(
						'label' => 'Diseño',
						'w'     => 'design',
						'req'   => true,
						'help'  => 'La acción lleva el título del diseño, y no se cambia.',
					),
					A::SUBTITLE        => array(
						'label' => 'Subtítulo',
						'w'     => 'text',
						'help'  => 'Un añadido que sale debajo del título, por ejemplo la edición o el lugar. No sustituye al título.',
					),
					'tax:fmc_scope'    => array(
						'label' => 'Ámbito que la gestiona',
						'w'     => 'term',
						'col'   => 6,
						'req'   => true,
					),
					A::ADVISER         => array(
						'label' => 'Asesoría responsable',
						'w'     => 'adviser',
						'col'   => 6,
					),
					A::VENUE_CENTRE    => array(
						'label' => 'Centro donde se imparte',
						'w'     => 'text',
						'col'   => 8,
					),
					A::VIDEOCONFERENCE => array(
						'label' => 'Por videoconferencia',
						'w'     => 'checkbox',
						'col'   => 4,
					),
					A::SPEAKERS        => array(
						'label' => 'Ponentes',
						'w'     => 'speakers',
						'help'  => 'Escriba para buscar y añadir; la «×» de cada uno lo quita. Al lado de cada nombre, su isla y las acciones que ha impartido; «★» si se le recomienda para este diseño.',
					),
				),
			),
			array(
				'title'  => 'Financiación',
				'help'   => 'Quién la paga y con cargo a qué programa. Si la paga el servicio de formación con fondos de un programa, elija el servicio y el programa: es lo que le dice qué expediente ponerle.',
				'fields' => array(
					A::FUNDED_BY        => array(
						'label'   => 'Asumida por',
						'w'       => 'select',
						'choices' => A::FUNDERS,
						'col'     => 6,
					),
					'tax:fmc_programme' => array(
						'label' => 'Con cargo al programa',
						'w'     => 'terms',
						'col'   => 6,
					),
					A::STRATEGIC_LINE   => array(
						'label' => 'Línea estratégica',
						'w'     => 'text',
						'col'   => 6,
					),
					A::TRAINING_FILE    => array(
						'label' => 'Expediente de formación',
						'w'     => 'text',
						'col'   => 6,
						'help'  => 'Número del expediente en el que se incluye esta acción.',
					),
					A::TRAINING_PLANS   => array(
						'label'   => 'Planes de formación de los centros',
						'w'       => 'checks',
						'choices' => A::TRAINING_PLAN_KINDS,
					),
					A::ITINERARY        => array(
						'label' => 'Itinerario formativo',
						'w'     => 'text',
					),
				),
			),
			array(
				'title'  => 'Plazas, modalidad y horas',
				'help'   => 'Las horas totales y las del desdoble se calculan.',
				'fields' => array(
					A::PLACES       => array(
						'label' => 'Plazas',
						'w'     => 'number',
						'col'   => 3,
						'req'   => true,
					),
					A::MODALITY     => array(
						'label'   => 'Modalidad',
						'w'       => 'select',
						'choices' => D::MODALITIES,
						'col'     => 3,
					),
					A::HOURS_ONSITE => array(
						'label' => 'Horas presenciales',
						'w'     => 'number',
						'col'   => 3,
					),
					A::HOURS_ONLINE => array(
						'label' => 'Horas en línea',
						'w'     => 'number',
						'col'   => 3,
					),
					A::REPLICAS     => array(
						'label' => 'Desdoblar',
						'w'     => 'number',
						'col'   => 3,
						'help'  => 'Veces que se repite con el mismo expediente.',
					),
					A::TRAVEL_HOURS => array(
						'label' => 'Horas con desplazamiento',
						'w'     => 'checkbox',
						'col'   => 3,
					),
					A::ONLINE_ENROL => array(
						'label' => 'Matrícula en línea',
						'w'     => 'checkbox',
						'col'   => 3,
					),
				),
			),
			array(
				'title'  => 'Fechas y plazos',
				'help'   => 'De la fecha de inicio sale el curso escolar y el sitio en el calendario.',
				'fields' => array(
					A::START            => array(
						'label' => 'Fecha de inicio',
						'w'     => 'date',
						'col'   => 6,
						'req'   => true,
					),
					A::END              => array(
						'label' => 'Fecha de fin',
						'w'     => 'date',
						'col'   => 6,
					),
					A::SCHEDULE         => array(
						'label' => 'Días y horas',
						'w'     => 'textarea',
						'help'  => 'Por ejemplo: 20 y 27 de marzo y 3 y 24 de abril, de 16:00 a 20:00.',
					),
					A::ENROL_START      => array(
						'label' => 'Inicio de matrícula',
						'w'     => 'date',
						'col'   => 4,
					),
					A::ENROL_END        => array(
						'label' => 'Fin de matrícula',
						'w'     => 'date',
						'col'   => 4,
					),
					A::ENROL_URL        => array(
						'label' => 'Enlace de matrícula',
						'w'     => 'url',
						'col'   => 4,
					),
					A::PROVISIONAL_LIST => array(
						'label' => 'Lista provisional',
						'w'     => 'date',
						'col'   => 3,
					),
					A::CLAIMS_START     => array(
						'label' => 'Inicio de reclamaciones',
						'w'     => 'date',
						'col'   => 3,
					),
					A::CLAIMS_END       => array(
						'label' => 'Fin de reclamaciones',
						'w'     => 'date',
						'col'   => 3,
					),
					A::FINAL_LIST       => array(
						'label' => 'Lista definitiva',
						'w'     => 'date',
						'col'   => 3,
					),
				),
			),
			array(
				'title'  => 'Desarrollo',
				'help'   => '',
				'fields' => array(
					A::CERTIFICATION    => array(
						'label' => 'Criterios de certificación',
						'w'     => 'textarea',
					),
					A::COURSE_SPACE_URL => array(
						'label' => 'Espacio del curso en la plataforma',
						'w'     => 'url',
					),
					A::NOTES            => array(
						'label' => 'Observaciones',
						'w'     => 'textarea',
					),
				),
			),
			array(
				'title'  => 'Estado',
				'help'   => 'El proceso lo lleva quien gestiona la acción; la situación, el expediente y el personal del servicio, el servicio de formación.',
				'fields' => array(
					A::PROCESS         => array(
						'label'   => 'Proceso',
						'w'       => 'select',
						'choices' => A::PROCESSES,
						'col'     => 6,
					),
					A::SITUATION       => array(
						'label'   => 'Situación',
						'w'       => 'select',
						'choices' => A::SITUATIONS,
						'col'     => 6,
					),
					A::FILE_NUMBER     => array(
						'label' => 'Expediente',
						'w'     => 'text',
						'col'   => 4,
						'help'  => 'Solo cuando se haya creado en el sistema de gestión.',
					),
					A::SERVICE_OFFICER => array(
						'label' => 'Negociado del servicio',
						'w'     => 'text',
						'col'   => 4,
					),
					A::SERVICE_MANAGER => array(
						'label' => 'Responsable del servicio',
						'w'     => 'text',
						'col'   => 4,
					),
					A::COMPANY_TECH    => array(
						'label' => 'Técnico de la empresa',
						'w'     => 'text',
						'col'   => 6,
					),
					A::SERVICE_REQUEST => array(
						'label' => 'Petición de servicio',
						'w'     => 'checkbox',
						'col'   => 6,
					),
					A::INTERNAL_REF    => array(
						'label' => 'Referencia interna',
						'w'     => 'text',
						'col'   => 6,
						'help'  => 'La del sistema anterior; solo para buscar.',
					),
				),
			),
		);
	}






	private static function speaker(): array {
		return array(
			array(
				'title'  => 'Datos personales',
				'help'   => 'Los ve quien gestiona acciones formativas; cada asesoría edita las fichas que ha dado de alta.',
				'fields' => array(
					S::FIRST_NAME => array(
						'label' => 'Nombre',
						'w'     => 'text',
						'col'   => 6,
						'req'   => true,
					),
					S::LAST_NAME  => array(
						'label' => 'Apellidos',
						'w'     => 'text',
						'col'   => 6,
						'req'   => true,
					),
					S::ID_NUMBER  => array(
						'label' => 'Documento de identidad',
						'w'     => 'text',
						'col'   => 4,
					),
					S::PHONE      => array(
						'label' => 'Teléfono',
						'w'     => 'text',
						'col'   => 4,
					),
					S::ZONE       => array(
						'label' => 'Isla o zona',
						'w'     => 'text',
						'col'   => 4,
					),
					S::EMAIL      => array(
						'label' => 'Correo electrónico',
						'w'     => 'email',
						'col'   => 6,
						'req'   => true,
					),
					S::EMAIL_ALT  => array(
						'label' => 'Correo alternativo',
						'w'     => 'email',
						'col'   => 6,
					),
					S::PROFESSION => array(
						'label' => 'Profesión',
						'w'     => 'text',
						'col'   => 6,
					),
					S::WEBSITE    => array(
						'label' => 'Sitio web',
						'w'     => 'url',
						'col'   => 6,
					),
					S::ADDRESS    => array(
						'label' => 'Dirección',
						'w'     => 'textarea',
					),
				),
			),
			array(
				'title'  => 'Disponibilidad',
				'help'   => '',
				'fields' => array(
					S::UNAVAILABLE => array(
						'label' => 'No disponible',
						'w'     => 'checkbox',
					),
					S::RECOMMENDED => array(
						'label' => 'Recomendado para los diseños',
						'w'     => 'designs',
						'help'  => 'Escriba para buscar y añadir; la «×» de cada uno lo quita. Solo se ofrecen los diseños del catálogo.',
					),
					S::NOTES       => array(
						'label' => 'Observaciones',
						'w'     => 'textarea',
					),
				),
			),
		);
	}






	private static function incident(): array {
		return array(
			array(
				'title'  => 'La incidencia',
				'help'   => 'Una vez enviada ya no se puede cambiar: si hay que corregirla, se abre otra.',
				'fields' => array(
					I::REASON   => array(
						'label' => 'Motivos',
						'w'     => 'textarea',
						'req'   => true,
					),
					I::PROPOSAL => array(
						'label' => 'Cambios que se proponen',
						'w'     => 'textarea',
						'req'   => true,
					),
					I::HAS_COST => array(
						'label' => 'El cambio conlleva coste',
						'w'     => 'checkbox',
						'help'  => 'Con coste es, por ejemplo, duplicar el curso; sin coste, cambiar ponente, fechas o número de plazas.',
					),
				),
			),
			array(
				'title'  => 'Resolución',
				'help'   => 'La resuelve el servicio de formación.',
				'fields' => array(
					I::RESOLUTION       => array(
						'label'   => 'Resolución',
						'w'       => 'radio',
						'choices' => I::RESOLUTIONS,
					),
					I::RESOLUTION_NOTES => array(
						'label' => 'Observaciones a la resolución',
						'w'     => 'textarea',
					),
				),
			),
		);
	}
}








namespace Fmc\PublicFront;

use Fmc\PostType\PostTypes;













final class Documents {




	public const KIND_META = '_fmc_kind';






	public static function kinds(): array {
		$texts = array(
			'pdf'  => 'application/pdf',
			'doc'  => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'odt'  => 'application/vnd.oasis.opendocument.text',
		);
		return array(
			'image'    => array(
				'label'    => 'Imagen representativa',
				'help'     => 'JPG, PNG o GIF, de hasta 3 MB. Sale en el catálogo.',
				'mimes'    => array(
					'jpg|jpeg' => 'image/jpeg',
					'png'      => 'image/png',
					'gif'      => 'image/gif',
				),
				'multiple' => false,
				'max_mb'   => 3,
			),
			'design'   => array(
				'label'    => 'Diseño del curso',
				'help'     => 'PDF, DOC, DOCX u ODT.',
				'mimes'    => $texts,
				'multiple' => true,
				'max_mb'   => 20,
			),
			'sessions' => array(
				'label'    => 'Minutaje detallado de las sesiones',
				'help'     => 'PDF, DOC, DOCX u ODT.',
				'mimes'    => $texts,
				'multiple' => true,
				'max_mb'   => 20,
			),
			'support'  => array(
				'label'    => 'Documentos de apoyo',
				'help'     => 'PDF, DOC, DOCX o ZIP.',
				'mimes'    => array_merge( array_slice( $texts, 0, 3, true ), array( 'zip' => 'application/zip' ) ),
				'multiple' => true,
				'max_mb'   => 50,
			),
			'extra'    => array(
				'label'    => 'Otros documentos',
				'help'     => 'Cualquier otro documento del diseño: PDF, DOC, DOCX, ODT o ZIP.',
				'mimes'    => array_merge( $texts, array( 'zip' => 'application/zip' ) ),
				'multiple' => true,
				'max_mb'   => 50,
			),
		);
	}







	public static function of( int $post_id ): array {
		$out = array_fill_keys( array_keys( self::kinds() ), array() );
		if ( ! $post_id ) {
			return $out;
		}
		foreach ( get_children(
			array(
				'post_parent' => $post_id,
				'post_type'   => 'attachment',
				'orderby'     => 'menu_order date',
				'order'       => 'ASC',
			)
		) as $attachment ) {
			$kind = (string) get_post_meta( $attachment->ID, self::KIND_META, true );
			if ( isset( $out[ $kind ] ) ) {
				$out[ $kind ][] = $attachment;
			}
		}
		return $out;
	}







	public static function can_manage( int $post_id ): bool {
		return current_user_can( 'upload_files' ) && current_user_can( 'edit_post', $post_id );
	}








	public static function check( string $kind, array $file ): string {
		$def = self::kinds()[ $kind ] ?? null;
		if ( ! $def ) {
			return 'Clase de documento desconocida.';
		}
		if ( ! empty( $file['error'] ) ) {
			return 'No se pudo subir «' . $file['name'] . '».';
		}
		if ( (int) $file['size'] > $def['max_mb'] * MB_IN_BYTES ) {
			return '«' . $file['name'] . '» pasa de ' . $def['max_mb'] . ' MB.';
		}
		$type = wp_check_filetype_and_ext( (string) $file['tmp_name'], (string) $file['name'], $def['mimes'] );
		if ( empty( $type['type'] ) || ! in_array( $type['type'], $def['mimes'], true ) ) {
			return '«' . $file['name'] . '» no es de un formato admitido aquí: ' . $def['help'];
		}
		return '';
	}









	public static function apply( int $post_id, array $files, array $remove ): array {
		if ( PostTypes::DESIGN !== get_post_type( $post_id ) || ! self::can_manage( $post_id ) ) {
			return array( 'No tiene permiso para cambiar los documentos.' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$problems = array();
		$mine     = static function ( int $attachment_id ) use ( $post_id ): bool {
			return 'attachment' === get_post_type( $attachment_id ) && (int) get_post_field( 'post_parent', $attachment_id ) === $post_id;
		};

		foreach ( $remove as $attachment_id ) {
			if ( $mine( (int) $attachment_id ) ) {
				wp_delete_attachment( (int) $attachment_id, true );
			}
		}

		foreach ( (array) ( $files['replace'] ?? array() ) as $attachment_id => $file ) {
			$attachment_id = (int) $attachment_id;
			if ( ! $mine( $attachment_id ) ) {
				continue;
			}
			$kind  = (string) get_post_meta( $attachment_id, self::KIND_META, true );
			$error = self::check( $kind, $file );
			if ( '' !== $error ) {
				$problems[] = $error;
				continue;
			}
			$new = self::store( $post_id, $kind, $file );
			if ( is_string( $new ) ) {
				$problems[] = $new;
				continue;
			}

			wp_update_post(
				array(
					'ID'         => $new,
					'menu_order' => (int) get_post_field( 'menu_order', $attachment_id ),
				)
			);
			wp_delete_attachment( $attachment_id, true );
		}

		foreach ( self::kinds() as $kind => $def ) {
			$list = array_values( (array) ( $files[ $kind ] ?? array() ) );
			if ( ! $def['multiple'] ) {
				$list = array_slice( $list, 0, 1 );
			}
			foreach ( $list as $file ) {
				$error = self::check( $kind, $file );
				if ( '' !== $error ) {
					$problems[] = $error;
					continue;
				}
				if ( ! $def['multiple'] ) {
					foreach ( self::of( $post_id )[ $kind ] as $old ) {
						wp_delete_attachment( $old->ID, true );
					}
				}
				$new = self::store( $post_id, $kind, $file );
				if ( is_string( $new ) ) {
					$problems[] = $new;
				}
			}
		}

		$image = self::of( $post_id )['image'];
		if ( $image ) {
			set_post_thumbnail( $post_id, $image[0]->ID );
		} else {
			delete_post_thumbnail( $post_id );
		}
		return $problems;
	}









	private static function store( int $post_id, string $kind, array $file ) {
		$overrides = array(
			'test_form' => false,
			'mimes'     => self::kinds()[ $kind ]['mimes'],
		);


		$id = media_handle_sideload( $file, $post_id, null, $overrides );
		if ( is_wp_error( $id ) ) {
			return '«' . $file['name'] . '»: ' . $id->get_error_message();
		}
		update_post_meta( (int) $id, self::KIND_META, $kind );
		return (int) $id;
	}










	public static function from_request( array $raw ): array {
		$out = array();
		foreach ( array( 'fmc_doc', 'fmc_replace' ) as $field ) {
			if ( empty( $raw[ $field ]['name'] ) || ! is_array( $raw[ $field ]['name'] ) ) {
				continue;
			}
			foreach ( $raw[ $field ]['name'] as $key => $names ) {
				foreach ( (array) $names as $i => $name ) {
					if ( '' === (string) $name ) {
						continue;
					}
					$file = array();
					foreach ( array( 'name', 'type', 'tmp_name', 'error', 'size' ) as $attr ) {
						$value         = $raw[ $field ][ $attr ][ $key ];
						$file[ $attr ] = is_array( $value ) ? $value[ $i ] : $value;
					}

					if ( ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
						continue;
					}
					if ( 'fmc_doc' === $field ) {
						$out[ sanitize_key( (string) $key ) ][] = $file;
					} else {
						$out['replace'][ absint( $key ) ] = $file;
					}
				}
			}
		}
		return $out;
	}
}








namespace Fmc\PublicFront;

use Fmc\Meta\ActionMetaKeys as A;
use Fmc\Meta\DesignMetaKeys as D;
use Fmc\Meta\SpeakerMetaKeys as S;
use Fmc\PostType\PostTypes;









final class Speakers {










	private static function actions(): array {
		static $cache = array();
		$changed      = wp_cache_get_last_changed( 'posts' );
		if ( isset( $cache[ $changed ] ) ) {
			return $cache[ $changed ];
		}
		global $wpdb;
		$keys = array(
			'speakers'  => A::SPEAKERS,
			'subtitle'  => A::SUBTITLE,
			'file'      => A::FILE_NUMBER,
			'start'     => A::START,
			'onsite'    => A::HOURS_ONSITE,
			'online'    => A::HOURS_ONLINE,
			'replicas'  => A::REPLICAS,
			'situation' => A::SITUATION,
		);
		$join = '';
		foreach ( $keys as $alias => $key ) {
			$join .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} AS m_{$alias} ON ( m_{$alias}.post_id = p.ID AND m_{$alias}.meta_key = %s )", $key ); 
		}
		$cols = implode( ', ', array_map( static fn( $alias ) => "m_{$alias}.meta_value AS {$alias}", array_keys( $keys ) ) );

		$found = $wpdb->get_results( $wpdb->prepare( "SELECT p.post_title AS title, {$cols} FROM {$wpdb->posts} p {$join} WHERE p.post_type = %s AND p.post_status = 'publish' AND m_speakers.meta_value IS NOT NULL", PostTypes::ACTION ), ARRAY_A );

		$rows = array();
		foreach ( (array) $found as $r ) {

			if ( 'cancelled' === $r['situation'] ) {
				continue;
			}
			$rows[] = array(
				'text'     => self::fold( $r['title'] . ' ' . $r['subtitle'] ),
				'file'     => (string) $r['file'],
				'start'    => (string) $r['start'],
				'hours'    => ( (float) $r['onsite'] + (float) $r['online'] ) * max( 1, (int) $r['replicas'] ),
				'speakers' => array_map( 'intval', (array) maybe_unserialize( $r['speakers'] ) ),
			);
		}
		$cache = array( $changed => $rows );
		return $rows;
	}







	public static function fold( string $text ): string {
		return mb_strtolower( remove_accents( $text ) );
	}









	public static function stats( string $given = '', string $file = '', string $from = '' ): array {
		$words = array_filter( explode( ' ', self::fold( $given ) ), 'strlen' );
		$file  = self::fold( $file );
		$out   = array();
		foreach ( self::actions() as $row ) {
			if ( '' !== $file && 0 !== strpos( self::fold( $row['file'] ), $file ) ) {
				continue;
			}
			if ( '' !== $from && $row['start'] < $from ) {
				continue;
			}
			foreach ( $words as $word ) {
				if ( false === strpos( $row['text'], $word ) ) {
					continue 2;
				}
			}
			foreach ( array_unique( $row['speakers'] ) as $id ) {
				$out[ $id ]['actions'] = ( $out[ $id ]['actions'] ?? 0 ) + 1;
				$out[ $id ]['hours']   = ( $out[ $id ]['hours'] ?? 0.0 ) + $row['hours'];
			}
		}
		return $out;
	}







	public static function lineage( int $design ): array {
		$seen = array();
		$todo = array( $design );
		while ( $todo ) {
			$id = (int) array_shift( $todo );
			if ( $id <= 0 || isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$todo        = array_merge( $todo, array_map( 'intval', (array) get_post_meta( $id, D::REPLACES, true ) ) );
		}
		return array_keys( $seen );
	}







	public static function recommended_for( int $design ): array {
		if ( $design <= 0 ) {
			return array();
		}
		return get_posts(
			array(
				'post_type'      => PostTypes::SPEAKER,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => self::recommended_clause( $design ), 
			)
		);
	}









	public static function recommended_clause( int $design ): array {
		$clause = array( 'relation' => 'OR' );
		foreach ( self::lineage( $design ) as $id ) {
			$clause[] = array(
				'key'     => S::RECOMMENDED,
				'value'   => 'i:' . $id . ';',
				'compare' => 'LIKE',
			);
		}
		return $clause;
	}
}








namespace Fmc\PublicFront;

use Fmc\Domain\SchoolYear;
use Fmc\Meta\ActionMetaKeys as A;
use Fmc\Meta\DesignMetaKeys as D;
use Fmc\Meta\IncidentMetaKeys as I;
use Fmc\Meta\SpeakerMetaKeys as S;
use Fmc\PostType\PostTypes;







final class Lists {

	public const PER_PAGE = 50;




	public const SITUATION_COLOURS = array(
		'managing'  => 'secondary',
		'running'   => 'primary',
		'done'      => 'success',
		'postponed' => 'warning',
		'cancelled' => 'danger',
	);




	public const DESIGN_STATUSES = array(
		'draft'   => array( 'Borrador', 'secondary' ),
		'pending' => array( 'En revisión', 'warning' ),
		'publish' => array( 'Finalizado', 'success' ),
	);






	public static function register(): void {
		add_filter( 'posts_where', array( self::class, 'visible_where' ), 10, 2 );
		add_filter( 'posts_where', array( self::class, 'text_where' ), 10, 2 );
		add_filter( 'posts_clauses', array( self::class, 'order_by_start' ), 10, 2 );
	}












	public static function order_by_start( $clauses, $query ) {
		if ( ! $query instanceof \WP_Query || ! $query->get( 'fmc_order_start' ) ) {
			return $clauses;
		}
		global $wpdb;
		$clauses['join']   .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} AS fmc_start ON ( fmc_start.post_id = {$wpdb->posts}.ID AND fmc_start.meta_key = %s )", A::START );
		$clauses['orderby'] = "fmc_start.meta_value IS NULL ASC, fmc_start.meta_value DESC, {$wpdb->posts}.ID DESC";
		return $clauses;
	}








	public static function visible_where( $where, $query ) {
		if ( ! $query instanceof \WP_Query || ! $query->get( 'fmc_visible' ) ) {
			return $where;
		}
		global $wpdb;
		return $where . $wpdb->prepare( " AND ( {$wpdb->posts}.post_status = 'publish' OR {$wpdb->posts}.post_author = %d )", get_current_user_id() );
	}












	public static function text_where( $where, $query ) {
		if ( ! $query instanceof \WP_Query || '' === (string) $query->get( 'fmc_text' ) ) {
			return $where;
		}
		global $wpdb;
		$like = '%' . $wpdb->esc_like( (string) $query->get( 'fmc_text' ) ) . '%';
		$keys = array_map( 'strval', (array) $query->get( 'fmc_text_keys' ) );
		$in   = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );

		return $where . $wpdb->prepare( " AND ( {$wpdb->posts}.post_title LIKE %s OR EXISTS ( SELECT 1 FROM {$wpdb->postmeta} fmc_t WHERE fmc_t.post_id = {$wpdb->posts}.ID AND fmc_t.meta_key IN ( {$in} ) AND fmc_t.meta_value LIKE %s ) )", array_merge( array( $like ), $keys, array( $like ) ) );
	}






	private static function incidents(): array {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT p.post_parent AS action, COUNT(*) AS total, SUM( COALESCE( r.meta_value, 'pending' ) = 'pending' ) AS pending FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} r ON ( r.post_id = p.ID AND r.meta_key = %s ) WHERE p.post_type = %s AND p.post_status = 'publish' AND p.post_parent > 0 GROUP BY p.post_parent", I::RESOLUTION, PostTypes::INCIDENT ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r['action'] ] = array(
				'total'   => (int) $r['total'],
				'pending' => (int) $r['pending'],
			);
		}
		return $out;
	}







	public static function arg( string $key ): string {

		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
	}








	public static function query_args( string $type, bool $with_state = true ): array {
		$args = array(
			'post_type'      => $type,
			'post_status'    => array( 'publish', 'pending', 'draft' ),
			'posts_per_page' => self::PER_PAGE,
			'orderby'        => 'title',
			'order'          => 'ASC',
			's'              => self::arg( 'fmc_s' ),
			'meta_query'     => array(), 
			'tax_query'      => array(), 
		);
		if ( ! current_user_can( 'edit_others_' . $type . 's' ) ) {
			$args['fmc_visible'] = true;
		}

		$meta = static function ( string $key, string $value ) use ( &$args ): void {
			if ( '' !== $value ) {
				$args['meta_query'][] = array(
					'key'   => $key,
					'value' => $value,
				);
			}
		};
		$term = static function ( string $taxonomy, string $slug ) use ( &$args ): void {
			if ( '' !== $slug ) {
				$args['tax_query'][] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $slug,
				);
			}
		};

		switch ( $type ) {
			case PostTypes::ACTION:




				$args['fmc_order_start'] = true;
				$args                    = self::action_search( $args );
				$term( 'fmc_scope', self::arg( 'fmc_scope' ) );
				$term( 'fmc_programme', self::arg( 'fmc_programme' ) );
				$meta( A::MODALITY, self::arg( 'fmc_modality' ) );
				$meta( A::PROCESS, self::arg( 'fmc_process' ) );

				if ( isset( D::TYPES[ self::arg( 'fmc_type' ) ] ) ) {
					$designs              = get_posts(
						array(
							'post_type'      => PostTypes::DESIGN,
							'post_status'    => 'any',
							'posts_per_page' => -1,
							'fields'         => 'ids',
							'meta_key'       => D::TYPE, 
							'meta_value'     => self::arg( 'fmc_type' ), 
						)
					);
					$args['meta_query'][] = array(
						'key'     => A::DESIGN_ID,
						'value'   => $designs ? $designs : array( 0 ),
						'compare' => 'IN',
					);
				}

				if ( absint( self::arg( 'fmc_speaker' ) ) ) {
					$args['meta_query'][] = array(
						'key'     => A::SPEAKERS,
						'value'   => 'i:' . absint( self::arg( 'fmc_speaker' ) ) . ';',
						'compare' => 'LIKE',
					);
				}
				foreach ( array(
					'fmc_from' => '>=',
					'fmc_to'   => '<=',
				) as $arg => $compare ) {
					if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', self::arg( $arg ) ) ) {
						$args['meta_query'][] = array(
							'key'     => A::START,
							'value'   => self::arg( $arg ),
							'compare' => $compare,
						);
					}
				}
				if ( $with_state ) {
					$meta( A::SITUATION, self::arg( 'fmc_situation' ) );
				}
				$year = self::arg( 'fmc_year' );
				if ( preg_match( '/^(\d{4})-\d{4}$/', $year, $m ) ) {
					$args['meta_query'][] = array(
						'key'     => A::START,
						'value'   => array( $m[1] . '-09-01', ( (int) $m[1] + 1 ) . '-08-31' ),
						'compare' => 'BETWEEN',
					);
				}
				break;
			case PostTypes::DESIGN:
				$term( 'fmc_programme', self::arg( 'fmc_programme' ) );
				$term( 'fmc_topic', self::arg( 'fmc_topic' ) );
				$meta( D::TYPE, self::arg( 'fmc_type' ) );
				$meta( D::MODALITY, self::arg( 'fmc_modality' ) );
				if ( '1' === self::arg( 'fmc_catalogue' ) ) {
					$meta( D::IN_CATALOGUE, '1' );
				}
				if ( $with_state && isset( self::DESIGN_STATUSES[ self::arg( 'fmc_status' ) ] ) ) {
					$args['post_status'] = self::arg( 'fmc_status' );
				}
				break;
			case PostTypes::SPEAKER:
				$args = self::speaker_args( $args, $with_state );
				break;
			case PostTypes::INCIDENT:
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				if ( $with_state ) {
					$meta( I::RESOLUTION, self::arg( 'fmc_resolution' ) );
				}
				break;
		}
		return $args;
	}








	private static function speaker_args( array $args, bool $with_state ): array {
		if ( '' !== self::arg( 'fmc_zone' ) ) {
			$args['meta_query'][] = array(
				'key'   => S::ZONE,
				'value' => self::arg( 'fmc_zone' ),
			);
		}

		if ( absint( self::arg( 'fmc_recommended' ) ) ) {
			$args['meta_query'][] = Speakers::recommended_clause( absint( self::arg( 'fmc_recommended' ) ) );
		}
			$args = self::speaker_order( $args );

		if ( $with_state && 'trash' === self::arg( 'fmc_status' ) && current_user_can( 'edit_others_fmc_speakers' ) ) {
			$args['post_status'] = 'trash';
		}
		if ( $with_state && '' !== self::arg( 'fmc_available' ) ) {
			$args['meta_query'][] = '1' === self::arg( 'fmc_available' )
				? array(
					'key'     => S::UNAVAILABLE,
					'compare' => 'NOT EXISTS',
				)
				: array(
					'key'   => S::UNAVAILABLE,
					'value' => '1',
				);
		}
		return $args;
	}







	private static function action_search( array $args ): array {

		$args['fmc_text']      = $args['s'];
		$args['fmc_text_keys'] = array( A::FILE_NUMBER, A::INTERNAL_REF, A::SUBTITLE );
		$args['s']             = '';

		if ( '' !== self::arg( 'fmc_file' ) ) {
			$args['meta_query'][] = array(
				'key'     => A::FILE_NUMBER,
				'value'   => '^' . preg_quote( self::arg( 'fmc_file' ), '' ),
				'compare' => 'REGEXP',
			);
		}
		if ( '' !== self::arg( 'fmc_incident' ) ) {
			$with             = array_filter( self::incidents(), static fn( $c ) => 'pending' !== self::arg( 'fmc_incident' ) || $c['pending'] > 0 );
			$args['post__in'] = $with ? array_keys( $with ) : array( 0 );
		}
		return $args;
	}











	private static function speaker_order( array $args ): array {
		$sort     = self::arg( 'fmc_sort' );
		$filtered = '' !== self::arg( 'fmc_given' ) . self::arg( 'fmc_file' ) . self::arg( 'fmc_from' );
		if ( ! $filtered && ! in_array( $sort, array( 'actions', 'hours', 'zone' ), true ) ) {
			return $args;
		}
		$stats = self::speaker_stats();
		$ids   = $filtered ? array_keys( $stats ) : get_posts(
			array(
				'post_type'      => PostTypes::SPEAKER,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$key   = static function ( int $id ) use ( $stats, $sort ) {
			if ( 'zone' === $sort ) {
				return (string) get_post_meta( $id, S::ZONE, true );
			}
			return - (float) ( $stats[ $id ][ 'hours' === $sort ? 'hours' : 'actions' ] ?? 0 );
		};

		if ( 'name' !== $sort ) {
			usort(
				$ids,
				static function ( $a, $b ) use ( $key ) {
					$order = $key( (int) $a ) <=> $key( (int) $b );
					return 0 !== $order ? $order : $a <=> $b;
				}
			);
			$args['orderby'] = 'post__in';
		}
		$args['post__in'] = $ids ? array_map( 'intval', $ids ) : array( 0 );
		return $args;
	}






	private static function speaker_stats(): array {
		return Speakers::stats( self::arg( 'fmc_given' ), self::arg( 'fmc_file' ), self::arg( 'fmc_from' ) );
	}







	private static function total( array $args ): int {
		$args['posts_per_page'] = 1;
		$args['fields']         = 'ids';
		$q                      = new \WP_Query( $args );
		return (int) $q->found_posts;
	}







	public static function counters( string $type ): array {
		$base = self::query_args( $type, false );
		$out  = array(
			array(
				'label' => 'En total',
				'n'     => self::total( $base ),
				'arg'   => '',
				'value' => '',
			),
		);
		$by   = array();
		switch ( $type ) {
			case PostTypes::ACTION:
				foreach ( A::SITUATIONS as $key => $label ) {
					$by[] = array(
						$label,
						'fmc_situation',
						$key,
						array(
							'meta_query' => array_merge( 
								$base['meta_query'],
								array(
									array(
										'key'   => A::SITUATION,
										'value' => $key,
									),
								)
							),
						),
					); 
				}
				break;
			case PostTypes::DESIGN:
				foreach ( self::DESIGN_STATUSES as $key => $status ) {
					$by[] = array( $status[0], 'fmc_status', $key, array( 'post_status' => $key ) );
				}
				break;
			case PostTypes::SPEAKER:
				if ( current_user_can( 'edit_others_fmc_speakers' ) ) {
					$by[] = array( 'En la papelera', 'fmc_status', 'trash', array( 'post_status' => 'trash' ) );
				}
				break;
			case PostTypes::INCIDENT:
				foreach ( I::RESOLUTIONS as $key => $label ) {
					$by[] = array(
						$label,
						'fmc_resolution',
						$key,
						array(
							'meta_query' => array_merge( 
								$base['meta_query'],
								array(
									array(
										'key'   => I::RESOLUTION,
										'value' => $key,
									),
								)
							),
						),
					); 
				}
				break;
		}
		foreach ( $by as list( $label, $arg, $value, $extra ) ) {
			$out[] = array(
				'label' => $label,
				'n'     => self::total( array_merge( $base, $extra ) ),
				'arg'   => $arg,
				'value' => $value,
			);
		}
		return $out;
	}








	public static function screen( string $tab, string $type ): array {
		$page = max( 1, (int) self::arg( 'fmc_page' ) );
		$args = self::query_args( $type ) + array( 'paged' => $page );
		$q    = new \WP_Query( $args );

		$object  = get_post_type_object( $type );
		$actions = current_user_can( $object->cap->create_posts ) && PostTypes::INCIDENT !== $type
			? '<a class="btn btn-primary fmc-abre-panel" href="' . esc_url( Screen::url( array( Screen::ARG_NEW => $type ) ) ) . '">+ Añadir ' . esc_html( mb_strtolower( $object->labels->singular_name ) ) . '</a>'
			: '';



		if ( PostTypes::SPEAKER !== $type ) {
			$actions .= ' <a class="btn btn-outline-secondary" href="' . esc_url(
				Screen::url(
					array_merge(
						self::current_filters(),
						array(
							Screen::ARG_TAB => $tab,
							'fmc_csv'       => '1',
						)
					)
				)
			) . '">Exportar CSV</a>';
		}

		$body  = self::counters_html( $tab, self::counters( $type ) );
		$body .= self::filters_html( $tab, $type );
		$body .= self::table_html( $type, $q->posts );
		$body .= self::pager_html( $page, (int) $q->max_num_pages );

		$subtitles = array(
			PostTypes::ACTION   => 'Cada edición de un diseño: dónde, cuándo, para cuántos y cómo fue.',
			PostTypes::DESIGN   => 'El catálogo y la mesa de trabajo: los borradores se escriben aquí hasta que la curaduría los da por finalizados.',
			PostTypes::SPEAKER  => 'Datos personales: solo para quien gestiona acciones. Busque por lo que han impartido, con sus acciones y sus horas.',
			PostTypes::INCIDENT => 'Cambios pedidos sobre una acción. Se abren desde la propia acción.',
		);

		return array(
			'tab'      => $tab,
			'title'    => $object->labels->name,
			'subtitle' => $subtitles[ $type ],
			'actions'  => $actions,
			'body'     => $body,
		);
	}








	private static function counters_html( string $tab, array $counters ): string {
		$out = '<div class="fmc-counters mb-3">';
		foreach ( $counters as $c ) {
			$args   = array_merge( self::current_filters(), array( Screen::ARG_TAB => $tab ) );
			$active = '' !== $c['arg'] ? self::arg( (string) $c['arg'] ) === $c['value'] : '' === self::arg( 'fmc_situation' ) . self::arg( 'fmc_status' ) . self::arg( 'fmc_resolution' );
			unset( $args['fmc_situation'], $args['fmc_status'], $args['fmc_resolution'], $args['fmc_page'] );
			if ( '' !== $c['arg'] ) {
				$args[ (string) $c['arg'] ] = $c['value'];
			}
			$out .= '<a class="fmc-counter' . ( $active ? ' is-active' : '' ) . '" href="' . esc_url( Screen::url( $args ) ) . '"><strong>' . esc_html( number_format_i18n( (int) $c['n'] ) ) . '</strong><span>' . esc_html( (string) $c['label'] ) . '</span></a>';
		}
		return $out . '</div>';
	}






	private static function current_filters(): array {
		$out = array();
		foreach ( array( 'fmc_s', 'fmc_scope', 'fmc_programme', 'fmc_topic', 'fmc_modality', 'fmc_process', 'fmc_situation', 'fmc_year', 'fmc_type', 'fmc_status', 'fmc_zone', 'fmc_available', 'fmc_resolution', 'fmc_month', 'fmc_speaker', 'fmc_from', 'fmc_to', 'fmc_catalogue', 'fmc_recommended', 'fmc_file', 'fmc_incident', 'fmc_given', 'fmc_sort' ) as $key ) {
			if ( '' !== self::arg( $key ) ) {
				$out[ $key ] = self::arg( $key );
			}
		}
		return $out;
	}








	public static function filters_html( string $tab, string $type ): string {
		$placeholders = array(
			PostTypes::ACTION  => 'Título, subtítulo o expediente…',
			PostTypes::SPEAKER => 'Nombre…',
		);
		$fields       = array( self::search_field( $placeholders[ $type ] ?? 'Título…' ) );
		switch ( $type ) {
			case PostTypes::ACTION:
				$fields[] = self::text_field( 'fmc_file', 'Expediente empieza por', 'Prefijo' );
				$fields[] = self::select( 'fmc_scope', 'Ámbito', self::term_choices( 'fmc_scope' ), 'Todos' );
				$fields[] = self::select( 'fmc_year', 'Curso escolar', self::school_years(), 'Todos' );
				$fields[] = self::select( 'fmc_programme', 'Programa', self::term_choices( 'fmc_programme' ), 'Todos' );
				$fields[] = self::select( 'fmc_modality', 'Modalidad', D::MODALITIES, 'Todas' );
				$fields[] = self::select( 'fmc_type', 'Tipo', D::TYPES, 'Todos' );
				$fields[] = self::select( 'fmc_process', 'Estado', A::PROCESSES, 'Todos' );
				$fields[] = self::select( 'fmc_situation', 'Situación', A::SITUATIONS, 'Todas' );
				$fields[] = self::select( 'fmc_speaker', 'Ponente', self::post_choices( PostTypes::SPEAKER ), 'Todos', true );
				$fields[] = self::select(
					'fmc_incident',
					'Incidencias',
					array(
						'pending' => 'Con alguna pendiente',
						'any'     => 'Con alguna',
					),
					'Todas'
				);
				$fields[] = self::date_field( 'fmc_from', 'Empieza desde' );
				$fields[] = self::date_field( 'fmc_to', 'hasta' );
				break;
			case PostTypes::DESIGN:
				$fields[] = self::select( 'fmc_type', 'Tipo', D::TYPES, 'Todos' );
				$fields[] = self::select( 'fmc_modality', 'Modalidad', D::MODALITIES, 'Todas' );
				$fields[] = self::select( 'fmc_programme', 'Programa', self::term_choices( 'fmc_programme' ), 'Todos' );
				$fields[] = self::select( 'fmc_topic', 'Temática', self::term_choices( 'fmc_topic' ), 'Todas' );
				$fields[] = self::select( 'fmc_status', 'Estado', array_map( static fn( $s ) => $s[0], self::DESIGN_STATUSES ), 'Todos' );
				$fields[] = self::select( 'fmc_catalogue', 'Catálogo', array( '1' => 'En el catálogo público' ), 'Todos' );
				break;
			case PostTypes::SPEAKER:
				$fields[] = self::select( 'fmc_zone', 'Isla o zona', self::meta_choices( S::ZONE, PostTypes::SPEAKER ), 'Todas' );
				$fields[] = self::select(
					'fmc_available',
					'Disponibilidad',
					array(
						'1' => 'Disponibles',
						'0' => 'No disponibles',
					),
					'Todos'
				);
				$fields[] = self::select( 'fmc_recommended', 'Recomendado para', self::post_choices( PostTypes::DESIGN ), 'Cualquier diseño', true );

				$fields[] = self::text_field( 'fmc_given', 'Ha impartido', 'Palabras del título' );
				$fields[] = self::text_field( 'fmc_file', 'Expediente empieza por', 'Prefijo' );
				$fields[] = self::date_field( 'fmc_from', 'Acciones desde' );
				$fields[] = self::select(
					'fmc_sort',
					'Ordenar por',
					array(
						'name'    => 'Nombre',
						'actions' => 'Acciones impartidas',
						'hours'   => 'Horas impartidas',
						'zone'    => 'Isla o zona',
					),
					'Lo filtrado'
				);
				break;
			case PostTypes::INCIDENT:
				$fields[] = self::select( 'fmc_resolution', 'Resolución', I::RESOLUTIONS, 'Todas' );
				break;
		}

		$out  = '<form class="card card-body mb-3 fmc-filters" method="get" action="' . esc_url( home_url( '/' ) ) . '">';
		$out .= '<input type="hidden" name="' . esc_attr( Screen::ARG_TAB ) . '" value="' . esc_attr( $tab ) . '">';
		if ( '' !== self::arg( 'fmc_month' ) ) {
			$out .= '<input type="hidden" name="fmc_month" value="' . esc_attr( self::arg( 'fmc_month' ) ) . '">';
		}
		$out .= '<div class="row g-2 align-items-end">' . implode( '', $fields );
		$out .= '<div class="col-auto"><button class="btn btn-outline-dark" type="submit">Filtrar</button> <a class="btn btn-link" href="' . esc_url( Screen::url( array( Screen::ARG_TAB => $tab ) ) ) . '">Quitar filtros</a></div>';
		return $out . '</div></form>';
	}







	private static function search_field( string $placeholder ): string {
		return '<div class="col-12 col-md-3"><label class="form-label small fw-semibold" for="fmc_s">Buscar</label><input class="form-control" id="fmc_s" name="fmc_s" type="search" value="' . esc_attr( self::arg( 'fmc_s' ) ) . '" placeholder="' . esc_attr( $placeholder ) . '"></div>';
	}









	private static function text_field( string $name, string $label, string $placeholder ): string {
		return '<div class="col-6 col-md-2"><label class="form-label small fw-semibold" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label><input class="form-control" type="text" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( self::arg( $name ) ) . '" placeholder="' . esc_attr( $placeholder ) . '"></div>';
	}











	public static function select( string $name, string $label, array $choices, string $all, bool $search = false ): string {
		$wide = $search ? 'col-12 col-md-3' : 'col-6 col-md';
		$out  = '<div class="' . $wide . '"><label class="form-label small fw-semibold" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label><select class="form-select" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"' . ( $search ? ' data-fmc-ts data-placeholder="' . esc_attr( $all ) . '"' : '' ) . '><option value="">' . esc_html( $all ) . '</option>';
		foreach ( $choices as $value => $text ) {
			$out .= '<option value="' . esc_attr( (string) $value ) . '"' . selected( self::arg( $name ), (string) $value, false ) . '>' . esc_html( $text ) . '</option>';
		}
		return $out . '</select></div>';
	}








	private static function date_field( string $name, string $label ): string {
		return '<div class="col-6 col-md-2"><label class="form-label small fw-semibold" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label><input class="form-control" type="date" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( self::arg( $name ) ) . '"></div>';
	}







	public static function post_choices( string $type ): array {
		$out = array();
		foreach ( get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => PostTypes::DESIGN === $type ? array( 'publish', 'pending', 'draft' ) : 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		) as $p ) {
			$out[ $p->ID ] = get_the_title( $p );
		}
		return $out;
	}







	public static function term_choices( string $taxonomy ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		$out   = array();
		foreach ( is_array( $terms ) ? $terms : array() as $t ) {
			$out[ $t->slug ] = $t->name;
		}
		return $out;
	}








	private static function meta_choices( string $key, string $type ): array {
		global $wpdb;

		$values = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = %s AND pm.meta_value <> '' ORDER BY pm.meta_value", $key, $type ) );
		return array_combine( $values, $values );
	}






	public static function school_years(): array {
		$out  = array();
		$year = (int) gmdate( 'Y' ) + ( (int) gmdate( 'n' ) >= 9 ? 1 : 0 );
		for ( $y = $year; $y >= 2013; $y-- ) {
			$out[ ( $y - 1 ) . '-' . $y ] = ( $y - 1 ) . '-' . $y;
		}
		return $out;
	}








	public static function table_html( string $type, array $posts ): string {
		if ( array() === $posts ) {
			return '<div class="card card-body text-secondary">No hay nada que mostrar con estos filtros.</div>';
		}
		$columns = self::columns( $type );
		$out     = '<div class="card fmc-table"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th scope="col">' . esc_html( get_post_type_object( $type )->labels->singular_name ) . '</th>';
		foreach ( array_keys( $columns ) as $h ) {
			$out .= '<th scope="col">' . esc_html( $h ) . '</th>';
		}
		$out .= '<th scope="col" class="text-end"><span class="visually-hidden">Acciones</span></th></tr></thead><tbody>';
		foreach ( $posts as $post ) {
			$thumb = PostTypes::DESIGN === $type && has_post_thumbnail( $post ) ? get_the_post_thumbnail( $post, array( 48, 48 ), array( 'class' => 'fmc-thumb' ) ) : '';

			$name = 'trash' === $post->post_status ? '<span class="fw-semibold">' . esc_html( self::title( $post ) ) . '</span>' : '<a class="fw-semibold fmc-abre-panel" href="' . esc_url( Screen::url( array( Screen::ARG_EDIT => $post->ID ) ) ) . '">' . esc_html( self::title( $post ) ) . '</a>';
			$link = $thumb . $name . self::badge( $post ) . self::subtitle( $post );
			$out .= '<tr><td class="fmc-col-title">' . $link . '</td>';
			foreach ( $columns as $cell ) {
				$lines = explode( "\n", (string) $cell( $post ) );
				$out  .= '<td>' . esc_html( array_shift( $lines ) );
				foreach ( array_filter( $lines, 'strlen' ) as $line ) {
					$out .= '<div class="small text-secondary">' . esc_html( $line ) . '</div>';
				}
				$out .= '</td>';
			}
			$out .= '<td class="text-end text-nowrap">' . self::row_buttons( $post ) . '</td></tr>';
		}
		return $out . '</tbody></table></div></div>';
	}












	public static function csv( string $type ): void {

		if ( PostTypes::SPEAKER === $type ) {
			return;
		}
		$args                   = self::query_args( $type );
		$args['posts_per_page'] = 5000; 
		$args['no_found_rows']  = true;
		$fields                 = array_filter( Fields::all( $type ), static fn( $f ) => 'docs' !== $f['w'] );


		$titled = ! isset( $fields['post_title'] );
		$head   = array_merge( array( 'ID', 'Estado de la ficha' ), $titled ? array( 'Título' ) : array() );
		foreach ( $fields as $f ) {
			$head[] = $f['label'];
		}
		if ( PostTypes::ACTION === $type ) {
			$head = array_merge( $head, array( 'Tipo de formación', 'Curso escolar', 'Horas totales' ) );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( 'formacion-' . Screen::tab_of( $type ) . '-' . gmdate( 'Y-m-d' ) . '.csv' ) . '"' );
		$out = fopen( 'php://output', 'w' ); 
		fwrite( $out, "\xEF\xBB\xBF" ); 
		fputcsv( $out, $head, ';' );
		foreach ( get_posts( $args + array( 'suppress_filters' => false ) ) as $post ) {
			$row = array_merge( array( $post->ID, get_post_status_object( $post->post_status )->label ?? $post->post_status ), $titled ? array( get_the_title( $post ) ) : array() );
			foreach ( $fields as $key => $f ) {
				$row[] = self::plain( $post, $key, $f );
			}
			if ( PostTypes::ACTION === $type ) {
				$n     = static fn( string $k ): float => (float) get_post_meta( $post->ID, $k, true );
				$row[] = D::TYPES[ (string) get_post_meta( (int) get_post_meta( $post->ID, A::DESIGN_ID, true ), D::TYPE, true ) ] ?? '';
				$row[] = SchoolYear::of( (string) get_post_meta( $post->ID, A::START, true ) );
				$row[] = ( $n( A::HOURS_ONSITE ) + $n( A::HOURS_ONLINE ) ) * max( 1, (int) $n( A::REPLICAS ) );
			}
			fputcsv( $out, $row, ';' );
		}
		fclose( $out ); 
	}









	public static function plain( \WP_Post $post, string $key, array $f ): string {
		$value = Editor::value( $post, $key );
		switch ( $f['w'] ) {
			case 'checkbox':
				return $value ? 'Sí' : 'No';
			case 'select':
			case 'radio':
				return (string) ( $f['choices'][ (string) $value ] ?? $value );
			case 'checks':
				return implode( ', ', array_map( static fn( $v ) => $f['choices'][ $v ] ?? $v, (array) $value ) );
			case 'term':
			case 'terms':
				return implode( ', ', array_map( static fn( $id ) => get_term( (int) $id )->name ?? '', (array) $value ) );
			case 'design':
			case 'designs':
			case 'speakers':
				return implode( ', ', array_map( 'get_the_title', array_filter( array_map( 'intval', (array) $value ) ) ) );
			case 'adviser':
				return $value ? (string) get_the_author_meta( 'display_name', (int) $value ) : '';
			case 'rich':
			case 'textarea':
				return trim( wp_strip_all_tags( (string) $value ) );
			default:
				return is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		}
	}










	public static function columns( string $type ): array {
		$m = static fn( \WP_Post $p, string $key ): string => (string) get_post_meta( $p->ID, $key, true );
		switch ( $type ) {
			case PostTypes::ACTION:
				return array(
					'Expediente'   => static fn( $p ) => $m( $p, A::FILE_NUMBER ),
					'Ámbito'       => static fn( $p ) => self::terms( $p->ID, 'fmc_scope' ) . "\n" . $m( $p, A::VENUE_CENTRE ),
					'Ponentes'     => static fn( $p ) => implode( "\n", array_map( 'get_the_title', array_filter( array_map( 'intval', (array) get_post_meta( $p->ID, A::SPEAKERS, true ) ) ) ) ),
					'Tipo y horas' => static fn( $p ) => ( D::TYPES[ (string) get_post_meta( (int) $m( $p, A::DESIGN_ID ), D::TYPE, true ) ] ?? '' ) . "\n" . trim( ( D::MODALITIES[ $m( $p, A::MODALITY ) ] ?? '' ) . ' · ' . self::hours( $m( $p, A::HOURS_ONSITE ), $m( $p, A::HOURS_ONLINE ) ), ' ·' ),
					'Fechas'       => static fn( $p ) => self::dates( $m( $p, A::START ), $m( $p, A::END ) ) . "\n" . wp_trim_words( $m( $p, A::SCHEDULE ), 12 ),
					'Estado'       => static fn( $p ) => ( A::PROCESSES[ $m( $p, A::PROCESS ) ] ?? '' ) . "\n" . SchoolYear::of( $m( $p, A::START ) ),
				);
			case PostTypes::DESIGN:
				return array(
					'Código'    => static fn( $p ) => $m( $p, D::CODE ) . ( $m( $p, D::IN_CATALOGUE ) ? "\nEn el catálogo" : '' ),
					'Tipo'      => static fn( $p ) => ( D::TYPES[ $m( $p, D::TYPE ) ] ?? '' ) . "\n" . ( D::MODALITIES[ $m( $p, D::MODALITY ) ] ?? '' ),
					'Horas'     => static fn( $p ) => self::hours( $m( $p, D::HOURS_ONSITE ), $m( $p, D::HOURS_ONLINE ) ),
					'Temática'  => static fn( $p ) => self::terms( $p->ID, 'fmc_topic' ),
					'Programas' => static fn( $p ) => self::terms( $p->ID, 'fmc_programme' ),
				);
			case PostTypes::SPEAKER:
				if ( 'trash' === self::arg( 'fmc_status' ) ) {
					return array(
						'Baja'   => static fn( $p ) => $m( $p, S::TRASHED_AT ) . "\n" . get_the_author_meta( 'display_name', (int) $m( $p, S::TRASHED_BY ) ),
						'Motivo' => static fn( $p ) => $m( $p, S::TRASH_REASON ),
					);
				}
				$stats = self::speaker_stats();
				return array(
					'Contacto'         => static fn( $p ) => $m( $p, S::EMAIL ) . "\n" . $m( $p, S::PHONE ),
					'Isla o zona'      => static fn( $p ) => $m( $p, S::ZONE ),
					'Ha impartido'     => static fn( $p ) => isset( $stats[ $p->ID ] ) ? sprintf( '%d acción(es)', $stats[ $p->ID ]['actions'] ) . "\n" . ( $stats[ $p->ID ]['hours'] + 0 ) . ' h' : '—',
					'Recomendado para' => static fn( $p ) => implode( "\n", array_map( 'get_the_title', array_slice( array_filter( array_map( 'intval', (array) get_post_meta( $p->ID, S::RECOMMENDED, true ) ) ), 0, 3 ) ) ),
				);
			default:
				return array(
					'Expediente' => static fn( $p ) => ( $p->post_parent ? (string) get_post_meta( $p->post_parent, A::FILE_NUMBER, true ) . "\n" . self::terms( $p->post_parent, 'fmc_scope' ) : '' ),
					'Creada'     => static fn( $p ) => get_the_date( 'd/m/Y', $p ) . "\n" . get_the_author_meta( 'display_name', (int) $p->post_author ),
					'Motivos'    => static fn( $p ) => wp_trim_words( $m( $p, I::REASON ), 18 ),
					'Coste'      => static fn( $p ) => $m( $p, I::HAS_COST ) ? 'Con coste' : 'Sin coste',
					'Resolución' => static fn( $p ) => ( I::RESOLUTIONS[ $m( $p, I::RESOLUTION ) ] ?? 'Pendiente' ) . "\n" . wp_trim_words( $m( $p, I::RESOLUTION_NOTES ), 10 ),
				);
		}
	}












	public static function row_buttons( \WP_Post $post ): string {
		if ( 'trash' === $post->post_status ) {
			return '<a class="btn btn-sm btn-outline-secondary" href="' . esc_url( admin_url( 'edit.php?post_status=trash&post_type=' . $post->post_type ) ) . '">Recuperar en el escritorio</a>';
		}
		$edit = current_user_can( 'edit_post', $post->ID );
		$out  = '<a class="btn btn-sm btn-outline-primary fmc-abre-panel" title="' . esc_attr( self::title( $post ) ) . '" href="' . esc_url( Screen::url( array( Screen::ARG_EDIT => $post->ID ) ) ) . '">' . ( $edit ? 'Editar' : 'Ver' ) . '</a>';

		if ( PostTypes::ACTION === $post->post_type && $edit && current_user_can( 'edit_fmc_incidents' ) ) {
			$out .= ' <a class="btn btn-sm btn-outline-secondary fmc-abre-panel" title="Abrir una incidencia" href="' . esc_url(
				Screen::url(
					array(
						Screen::ARG_NEW    => PostTypes::INCIDENT,
						Screen::ARG_PARENT => $post->ID,
					)
				)
			) . '">Incidencia</a>';
		}
		if ( PostTypes::SPEAKER !== $post->post_type && Screen::can_delete( $post ) ) {
			$out .= ' ' . Screen::delete_form( $post, 'btn-sm' );
		}
		return $out;
	}







	public static function title( \WP_Post $post ): string {
		$title = get_the_title( $post );
		return '' !== $title ? $title : '(sin título)';
	}







	public static function badge( \WP_Post $post ): string {
		if ( PostTypes::ACTION === $post->post_type ) {
			$s = (string) get_post_meta( $post->ID, A::SITUATION, true );
			return ( isset( A::SITUATIONS[ $s ] ) ? ' <span class="badge rounded-pill text-bg-' . esc_attr( self::SITUATION_COLOURS[ $s ] ) . '">' . esc_html( A::SITUATIONS[ $s ] ) . '</span>' : '' ) . self::incident_badge( $post->ID );
		}
		if ( PostTypes::DESIGN === $post->post_type && isset( self::DESIGN_STATUSES[ $post->post_status ] ) ) {
			list( $label, $colour ) = self::DESIGN_STATUSES[ $post->post_status ];
			return ' <span class="badge rounded-pill text-bg-' . esc_attr( $colour ) . '">' . esc_html( $label ) . '</span>';
		}
		if ( PostTypes::SPEAKER === $post->post_type && get_post_meta( $post->ID, S::UNAVAILABLE, true ) ) {
			return ' <span class="badge rounded-pill text-bg-secondary">No disponible</span>';
		}
		return '';
	}










	public static function incident_badge( int $action ): string {
		static $cache = array();
		$changed      = wp_cache_get_last_changed( 'posts' );
		$cache        = isset( $cache[ $changed ] ) ? $cache : array( $changed => self::incidents() );
		$c            = $cache[ $changed ][ $action ] ?? null;
		if ( ! $c ) {
			return '';
		}
		return $c['pending'] > 0
			? ' <span class="badge rounded-pill fmc-inc is-pending">' . esc_html( sprintf( 'Incidencia pendiente (%d)', $c['pending'] ) ) . '</span>'
			: ' <span class="badge rounded-pill fmc-inc is-done">Incidencias resueltas</span>';
	}







	public static function subtitle( \WP_Post $post ): string {
		$sub = PostTypes::ACTION === $post->post_type ? (string) get_post_meta( $post->ID, A::SUBTITLE, true ) : '';
		return '' !== $sub ? '<div class="small text-secondary">' . esc_html( $sub ) . '</div>' : '';
	}








	private static function pager_html( int $page, int $pages ): string {
		if ( $pages < 2 ) {
			return '';
		}
		$link = static function ( int $p, string $text, bool $disabled = false ): string {
			$args = array_merge(
				self::current_filters(),
				array(
					Screen::ARG_TAB => self::arg( Screen::ARG_TAB ),
					'fmc_page'      => $p,
				)
			);
			return '<li class="page-item' . ( $disabled ? ' disabled' : '' ) . '"><a class="page-link" href="' . esc_url( Screen::url( $args ) ) . '">' . esc_html( $text ) . '</a></li>';
		};
		$out  = '<nav class="mt-3" aria-label="Páginas"><ul class="pagination justify-content-center">';
		$out .= $link( max( 1, $page - 1 ), '‹ Anterior', 1 === $page );
		$out .= '<li class="page-item disabled"><span class="page-link">' . esc_html( sprintf( 'Página %1$d de %2$d', $page, $pages ) ) . '</span></li>';
		$out .= $link( min( $pages, $page + 1 ), 'Siguiente ›', $page === $pages );
		return $out . '</ul></nav>';
	}








	public static function dates( string $start, string $end ): string {
		$fmt = static function ( string $d ): string {
			return preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m ) ? $m[3] . '/' . $m[2] . '/' . $m[1] : '';
		};
		return trim( $fmt( $start ) . ( '' !== $end && $end !== $start ? ' – ' . $fmt( $end ) : '' ) );
	}








	public static function hours( string $onsite, string $online ): string {
		$a = (float) $onsite;
		$b = (float) $online;
		if ( $a > 0 && $b > 0 ) {
			return ( $a + 0 ) . ' + ' . ( $b + 0 ) . ' h';
		}
		return ( $a + $b ) > 0 ? ( $a + $b + 0 ) . ' h' : '';
	}








	public static function terms( int $post_id, string $taxonomy ): string {
		$names = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
		return is_array( $names ) ? implode( ', ', $names ) : '';
	}
}








namespace Fmc\PublicFront;

use Fmc\Meta\ActionMetaKeys as A;
use Fmc\PostType\PostTypes;











final class Calendar {




	public const FUNDER_COLOURS = array(
		'centre'  => '#d63384',
		'area'    => '#6f42c1',
		'service' => '#0d6efd',
		'none'    => '#adb5bd',
	);




	public const MONTHS = array( '', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre' );




	public const WEEKDAYS = array( 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo' );






	public static function month(): string {
		$m = Lists::arg( 'fmc_month' );
		return preg_match( '/^(\d{4})-(0[1-9]|1[0-2])$/', $m ) ? $m . '-01' : gmdate( 'Y-m-01', (int) current_time( 'timestamp' ) ); 
	}




	public const VIEWS = array(
		'month' => 'Mes',
		'week'  => 'Semana',
		'day'   => 'Día',
		'list'  => 'Lista',
	);






	public static function view(): string {
		$view = Lists::arg( 'fmc_view' );
		return isset( self::VIEWS[ $view ] ) ? $view : 'month';
	}






	public static function day(): string {
		$d = Lists::arg( 'fmc_day' );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) && strtotime( $d ) ? $d : gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) ); 
	}







	public static function by_day( string $first ): array {
		return self::by_range( $first, gmdate( 'Y-m-t', (int) strtotime( $first ) ) );
	}







	private static function actions( array $clause ): array {
		$args                   = Lists::query_args( PostTypes::ACTION );
		$args['posts_per_page'] = 500; 
		$args['orderby']        = 'title';
		$args['order']          = 'ASC';
		unset( $args['fmc_order_start'] );

		$args['meta_query']   = array_values(
			array_filter(
				$args['meta_query'],
				static fn( $c ) => ( $c['key'] ?? '' ) !== A::START
			)
		);
		$args['meta_query'][] = $clause;


		return get_posts( $args + array( 'suppress_filters' => false ) );
	}








	public static function by_range( string $from, string $to ): array {
		$out = array();
		foreach ( self::actions(
			array(
				'key'     => A::START,
				'value'   => array( $from, $to ),
				'compare' => 'BETWEEN',
			)
		) as $post ) {
			$out[ (string) get_post_meta( $post->ID, A::START, true ) ][] = $post;
		}
		ksort( $out );
		return $out;
	}







	public static function ongoing( string $day ): array {
		return self::actions(
			array(
				'relation' => 'AND',
				array(
					'key'     => A::START,
					'value'   => $day,
					'compare' => '<',
				),
				array(
					'key'     => A::END,
					'value'   => $day,
					'compare' => '>=',
				),
			)
		);
	}







	public static function weeks( string $first ): array {
		$ts    = (int) strtotime( $first );
		$days  = (int) gmdate( 't', $ts );
		$blank = (int) gmdate( 'N', $ts ) - 1;
		$cells = array_merge( array_fill( 0, $blank, '' ), array_map( static fn( $d ) => gmdate( 'Y-m-', $ts ) . sprintf( '%02d', $d ), range( 1, $days ) ) );
		$pad   = ( 7 - count( $cells ) % 7 ) % 7;
		$cells = array_merge( $cells, array_fill( 0, $pad, '' ) );
		return array_chunk( $cells, 7 );
	}






	public static function screen(): array {
		$view = self::view();
		if ( 'week' === $view || 'day' === $view ) {
			$screen = self::days_screen( $view );
		} else {
			$screen = self::month_screen( $view );
		}
		$screen['actions'] = self::views_html( $view ) . ' ' . $screen['actions'] . ( current_user_can( 'edit_fmc_actions' ) ? ' <a class="btn btn-primary ms-2 fmc-abre-panel" href="' . esc_url( Screen::url( array( Screen::ARG_NEW => PostTypes::ACTION ) ) ) . '">+ Añadir acción</a>' : '' );
		$screen['body']    = Lists::filters_html( 'calendar', PostTypes::ACTION ) . self::legend() . $screen['body'];
		return $screen + array( 'tab' => 'calendar' );
	}









	private static function nav_link( array $args, string $text, string $css = 'btn btn-outline-secondary' ): string {
		return '<a class="' . esc_attr( $css ) . '" href="' . esc_url( Screen::url( array_merge( self::filters(), array( Screen::ARG_TAB => 'calendar' ), $args ) ) ) . '">' . esc_html( $text ) . '</a>';
	}







	private static function views_html( string $current ): string {
		$out = '<div class="btn-group me-2" role="group" aria-label="Vista">';
		foreach ( self::VIEWS as $view => $label ) {
			$anchor = 'week' === $view || 'day' === $view ? array( 'fmc_day' => self::day() ) : array( 'fmc_month' => substr( self::month(), 0, 7 ) );
			$out   .= self::nav_link( array( 'fmc_view' => 'month' === $view ? '' : $view ) + $anchor, $label, 'btn ' . ( $view === $current ? 'btn-secondary' : 'btn-outline-secondary' ) );
		}
		return $out . '</div>';
	}







	private static function month_screen( string $view ): array {
		$first = self::month();
		$ts    = (int) strtotime( $first );
		$title = self::MONTHS[ (int) gmdate( 'n', $ts ) ] . ' de ' . gmdate( 'Y', $ts );
		$keep  = 'list' === $view ? array( 'fmc_view' => 'list' ) : array();
		$nav   = '<div class="btn-group">' . self::nav_link( $keep + array( 'fmc_month' => gmdate( 'Y-m', (int) strtotime( '-1 month', $ts ) ) ), '‹' ) . self::nav_link( $keep + array( 'fmc_month' => gmdate( 'Y-m' ) ), 'Hoy' ) . self::nav_link( $keep + array( 'fmc_month' => gmdate( 'Y-m', (int) strtotime( '+1 month', $ts ) ) ), '›' ) . '</div>';

		$by_day = self::by_day( $first );
		$count  = array_sum( array_map( 'count', $by_day ) );
		if ( 'list' === $view ) {
			$body = Lists::table_html( PostTypes::ACTION, array_merge( array(), ...array_values( $by_day ) ) );
		} else {
			$body = self::grid( $first, $by_day );
		}
		return array(
			'title'    => 'Calendario de ' . $title,
			'subtitle' => sprintf( '%d acción(es) empiezan este mes.', $count ),
			'actions'  => $nav,
			'body'     => $body,
		);
	}








	private static function grid( string $first, array $by_day ): string {


		$weekend = array() !== array_filter( array_keys( $by_day ), static fn( $d ) => (int) gmdate( 'N', (int) strtotime( $d ) ) > 5 );
		$body    = '<div class="fmc-cal card' . ( $weekend ? '' : ' no-weekend' ) . '"><div class="fmc-cal-head">';
		foreach ( self::WEEKDAYS as $i => $d ) {
			$body .= '<div' . ( $i > 4 ? ' class="is-weekend"' : '' ) . '>' . esc_html( $d ) . '</div>';
		}
		$body .= '</div>';
		$today = gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) ); 
		foreach ( self::weeks( $first ) as $week ) {
			$body .= '<div class="fmc-cal-week">';
			foreach ( $week as $i => $day ) {
				$class = 'fmc-cal-day' . ( $i > 4 ? ' is-weekend' : '' ) . ( '' === $day ? ' is-out' : '' ) . ( $day === $today ? ' is-today' : '' );
				$body .= '<div class="' . esc_attr( $class ) . '">';
				if ( '' !== $day ) {
					$body .= '<div class="fmc-cal-num">' . esc_html( (string) (int) substr( $day, 8 ) ) . '</div>';
					foreach ( $by_day[ $day ] ?? array() as $post ) {
						$body .= self::card( $post );
					}
				}
				$body .= '</div>';
			}
			$body .= '</div>';
		}
		return $body . '</div>';
	}









	private static function day_html( string $day, array $actions, bool $today ): string {
		$weekday = self::WEEKDAYS[ (int) gmdate( 'N', (int) strtotime( $day ) ) - 1 ];
		$out     = '<section class="fmc-days-col' . ( $today ? ' is-today' : '' ) . '"><h2 class="fmc-days-h">' . self::nav_link(
			array(
				'fmc_view' => 'day',
				'fmc_day'  => $day,
			),
			$weekday . ' ' . (int) substr( $day, 8 ),
			'fmc-days-a'
		) . '</h2>';
		foreach ( $actions as $post ) {
			$out .= self::card( $post );
		}
		return $out . ( $actions ? '' : '<p class="small text-secondary mb-0">Ninguna empieza.</p>' ) . '</section>';
	}







	private static function running_html( array $running ): string {
		if ( ! $running ) {
			return '';
		}
		$out = '<h2 class="h6 mt-4">Empezaron antes y siguen en curso</h2><div class="fmc-running">';
		foreach ( $running as $post ) {
			$out .= self::card( $post );
		}
		return $out . '</div>';
	}








	public static function days_of( string $view, string $day ): array {
		if ( 'week' !== $view ) {
			return array( $day );
		}
		$monday = (int) strtotime( '-' . ( (int) gmdate( 'N', (int) strtotime( $day ) ) - 1 ) . ' days', (int) strtotime( $day ) );
		return array_map( static fn( $i ) => gmdate( 'Y-m-d', (int) strtotime( "+{$i} days", $monday ) ), range( 0, 6 ) );
	}







	private static function days_screen( string $view ): array {
		$day   = self::day();
		$ts    = (int) strtotime( $day );
		$days  = self::days_of( $view, $day );
		$from  = $days[0];
		$step  = 'week' === $view ? '7 days' : '1 day';
		$keep  = array( 'fmc_view' => $view );
		$nav   = '<div class="btn-group">' . self::nav_link( $keep + array( 'fmc_day' => gmdate( 'Y-m-d', (int) strtotime( '-' . $step, $ts ) ) ), '‹' ) . self::nav_link( $keep + array( 'fmc_day' => gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) ) ), 'Hoy' ) . self::nav_link( $keep + array( 'fmc_day' => gmdate( 'Y-m-d', (int) strtotime( '+' . $step, $ts ) ) ), '›' ) . '</div>'; 
		$by    = self::by_range( $days[0], end( $days ) );
		$today = gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) ); 

		$body = '<div class="fmc-days card' . ( 'day' === $view ? ' is-one' : '' ) . '">';
		foreach ( $days as $d ) {
			$body .= self::day_html( $d, $by[ $d ] ?? array(), $d === $today );
		}
		$body   .= '</div>';
		$running = self::ongoing( $days[0] );
		$body   .= self::running_html( $running );

		$label = (int) substr( $from, 8 ) . ' de ' . self::MONTHS[ (int) gmdate( 'n', (int) strtotime( $from ) ) ] . ' de ' . gmdate( 'Y', (int) strtotime( $from ) );
		return array(
			'title'    => 'week' === $view ? 'Calendario de la semana del ' . $label : 'Calendario del ' . mb_strtolower( self::WEEKDAYS[ (int) gmdate( 'N', $ts ) - 1 ] ) . ' ' . $label,
			'subtitle' => sprintf( '%1$d acción(es) empiezan; %2$d siguen en curso.', array_sum( array_map( 'count', $by ) ), count( $running ) ),
			'actions'  => $nav,
			'body'     => $body,
		);
	}






	private static function filters(): array {
		$out = array();
		foreach ( array( 'fmc_s', 'fmc_scope', 'fmc_programme', 'fmc_modality', 'fmc_process', 'fmc_situation', 'fmc_file', 'fmc_incident', 'fmc_type', 'fmc_speaker' ) as $key ) {
			if ( '' !== Lists::arg( $key ) ) {
				$out[ $key ] = Lists::arg( $key );
			}
		}
		return $out;
	}






	private static function legend(): string {
		$out = '<div class="fmc-legend mb-3"><strong class="small me-2">Leyenda:</strong>';
		foreach ( A::SITUATIONS as $key => $label ) {
			$out .= '<span class="fmc-chip fmc-sit-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</span>';
		}
		foreach ( A::FUNDERS as $key => $label ) {
			if ( 'none' === $key ) {
				continue;
			}
			$out .= '<span class="fmc-chip"><i class="fmc-dot" style="background:' . esc_attr( self::FUNDER_COLOURS[ $key ] ) . '"></i>Asume: ' . esc_html( mb_strtolower( $label ) ) . '</span>';
		}
		$out .= '<span class="fmc-chip"><span class="fmc-ribbon">×2</span> Desdoble</span><span class="fmc-chip"><span class="fmc-ribbon is-warn">!</span> Incompleta</span>';
		return $out . '</div>';
	}







	public static function card( \WP_Post $post ): string {
		$m          = static fn( string $key ): string => (string) get_post_meta( $post->ID, $key, true );
		$situation  = $m( A::SITUATION );
		$funded     = $m( A::FUNDED_BY );
		$replicas   = (int) $m( A::REPLICAS );
		$incomplete = ! $m( A::DESIGN_ID ) || ! $m( A::PLACES ) || ! $m( A::MODALITY );

		$dot = isset( self::FUNDER_COLOURS[ $funded ] ) && 'none' !== $funded ? '<i class="fmc-dot" style="background:' . esc_attr( self::FUNDER_COLOURS[ $funded ] ) . '" title="' . esc_attr( 'Asume: ' . A::FUNDERS[ $funded ] ) . '"></i>' : '';
		$out = '<a class="fmc-ev fmc-abre-panel fmc-sit-' . esc_attr( '' !== $situation ? $situation : 'none' ) . '" href="' . esc_url( Screen::url( array( Screen::ARG_EDIT => $post->ID ) ) ) . '" title="' . esc_attr( get_the_title( $post ) ) . '">';
		if ( $replicas > 1 ) {
			$out .= '<span class="fmc-ribbon">×' . esc_html( (string) $replicas ) . '</span>';
		}
		if ( $incomplete ) {
			$out .= '<span class="fmc-ribbon is-warn" title="Datos incompletos">!</span>';
		}
		$out .= '<span class="fmc-ev-title">' . esc_html( get_the_title( $post ) ) . '</span>';
		$sub  = $m( A::SUBTITLE );
		if ( '' !== $sub ) {
			$out .= '<span class="fmc-ev-meta">' . esc_html( $sub ) . '</span>';
		}
		$out  .= '<span class="fmc-ev-meta">' . esc_html( trim( $m( A::FILE_NUMBER ) . ' · ' . Lists::terms( $post->ID, 'fmc_scope' ), ' ·' ) ) . '</span>';
		$venue = $m( A::VENUE_CENTRE );
		if ( '' !== $venue && false === strpos( $venue, Lists::terms( $post->ID, 'fmc_scope' ) ) ) {
			$out .= '<span class="fmc-ev-meta">En: ' . esc_html( $venue ) . '</span>';
		}


		$programmes = Lists::terms( $post->ID, 'fmc_programme' );
		if ( '' !== $dot || '' !== $programmes ) {
			$out .= '<span class="fmc-ev-meta">' . $dot . esc_html( trim( ( A::FUNDERS[ $funded ] ?? '' ) . ( '' !== $programmes ? ' · ' . $programmes : '' ), ' ·' ) ) . '</span>';
		}
		return $out . Lists::incident_badge( $post->ID ) . '</a>';
	}
}








namespace Fmc\PublicFront;

use Fmc\Meta\ActionMetaKeys as A;
use Fmc\Meta\DesignMetaKeys as D;
use Fmc\Domain\DigComp;
use Fmc\Meta\IncidentMetaKeys as I;
use Fmc\Meta\MetaRegistration;
use Fmc\Meta\SpeakerMetaKeys as S;
use Fmc\PostType\PostTypes;










final class Editor {








	public static function nonce_action( string $type, int $id ): string {
		return 'fmc_save_' . $type . '_' . $id;
	}











	public static function can_view( \WP_Post $post ): bool {
		if ( current_user_can( 'edit_post', $post->ID ) ) {
			return true;
		}
		switch ( $post->post_type ) {
			case PostTypes::DESIGN:
				return current_user_can( 'read_post', $post->ID );
			case PostTypes::ACTION:

				return 'publish' === $post->post_status && ( current_user_can( 'read_private_fmc_actions' ) || current_user_can( 'edit_fmc_actions' ) );
			case PostTypes::INCIDENT:
				return current_user_can( 'read_private_fmc_incidents' ) || current_user_can( PostTypes::CAP_RESOLVE );
			case PostTypes::SPEAKER:
				return 'publish' === $post->post_status && current_user_can( 'edit_fmc_speakers' );
		}
		return false;
	}









	public static function can_write( string $type, string $key, int $id ): bool {
		if ( 'docs' === $key ) {
			return $id ? Documents::can_manage( $id ) : current_user_can( 'upload_files' ) && current_user_can( get_post_type_object( $type )->cap->create_posts );
		}
		if ( ! empty( Fields::all( $type )[ $key ]['auto'] ) ) {
			return false;
		}
		$post_field = 'post_title' === $key || 'post_content' === $key || 0 === strpos( $key, 'tax:' );
		if ( 0 === $id ) {
			if ( ! current_user_can( get_post_type_object( $type )->cap->create_posts ) ) {
				return false;
			}
			$cap = MetaRegistration::guarded()[ $type ][ $key ] ?? '';
			return $post_field || '' === $cap || current_user_can( $cap );
		}
		if ( $post_field ) {
			return current_user_can( 'edit_post', $id );
		}
		return MetaRegistration::can_write( $type, $key, $id );
	}








	public static function screen( int $id, string $new_type ): array {
		if ( $id ) {
			$post = get_post( $id );
			if ( ! $post instanceof \WP_Post || ! isset( PostTypes::definitions()[ $post->post_type ] ) || ! self::can_view( $post ) ) {
				return self::denied();
			}
			return self::render( $post->post_type, $post, array(), array() );
		}
		if ( ! isset( PostTypes::definitions()[ $new_type ] ) || ! current_user_can( get_post_type_object( $new_type )->cap->create_posts ) ) {
			return self::denied();
		}
		$parent = absint( Lists::arg( Screen::ARG_PARENT ) );
		if ( PostTypes::INCIDENT === $new_type && ( PostTypes::ACTION !== get_post_type( $parent ) || ! current_user_can( 'edit_post', $parent ) ) ) {
			return self::denied();
		}
		return self::render( $new_type, null, array(), array() );
	}






	private static function denied(): array {
		return array(
			'tab'   => '',
			'title' => 'No disponible',
			'body'  => '<div class="alert alert-warning">No existe o no tiene permiso para verlo.</div>',
		);
	}








	public static function value( ?\WP_Post $post, string $key ) {
		if ( ! $post ) {
			return '';
		}
		if ( 'post_title' === $key || 'post_content' === $key ) {
			return $post->$key;
		}
		if ( 0 === strpos( $key, 'tax:' ) ) {
			$ids = wp_get_object_terms( $post->ID, substr( $key, 4 ), array( 'fields' => 'ids' ) );
			return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
		}
		return get_post_meta( $post->ID, $key, true );
	}










	public static function render( string $type, ?\WP_Post $post, array $values, array $errors ): array {
		$id       = $post ? $post->ID : 0;
		$object   = get_post_type_object( $type );
		$tab      = Screen::tab_of( $type );
		$writable = false;
		$parent   = $post ? (int) $post->post_parent : absint( $values['_parent'] ?? Lists::arg( Screen::ARG_PARENT ) );


		$from = absint( Lists::arg( 'fmc_design' ) );
		if ( ! $post && PostTypes::ACTION === $type && $from && PostTypes::DESIGN === get_post_type( $from ) ) {
			$values += array(
				A::DESIGN_ID    => $from,
				A::MODALITY     => get_post_meta( $from, D::MODALITY, true ),
				A::HOURS_ONSITE => get_post_meta( $from, D::HOURS_ONSITE, true ),
				A::HOURS_ONLINE => get_post_meta( $from, D::HOURS_ONLINE, true ),
			);
		}


		if ( ! $post && PostTypes::DESIGN === $type ) {
			$values += array( D::CODE => self::next_code() );
		}

		$body = '';
		if ( PostTypes::SPEAKER === $type ) {
			$body .= self::privacy_notice();
		}
		if ( $errors ) {
			$body .= '<div class="alert alert-danger" role="alert"><strong>No se ha guardado.</strong><ul class="mb-0">';
			foreach ( $errors as $message ) {
				$body .= '<li>' . esc_html( $message ) . '</li>';
			}
			$body .= '</ul></div>';
		}
		if ( PostTypes::INCIDENT === $type && $parent ) {
			$body .= '<div class="alert alert-light border">Sobre la acción: <a href="' . esc_url( Screen::url( array( Screen::ARG_EDIT => $parent ) ) ) . '">' . esc_html( get_the_title( $parent ) ) . '</a></div>';
		}
		if ( PostTypes::ACTION === $type && $post ) {
			$body .= self::action_summary( $post );
		}

		$body .= '<form method="post" enctype="multipart/form-data" action="' . esc_url( home_url( '/' ) ) . '" class="fmc-form">';
		$body .= wp_nonce_field( self::nonce_action( $type, $id ), '_fmc_nonce', true, false );
		$body .= Screen::framed() ? '<input type="hidden" name="' . esc_attr( Screen::ARG_FRAME ) . '" value="1">' : '';
		$body .= '<input type="hidden" name="fmc_save" value="1"><input type="hidden" name="fmc_type" value="' . esc_attr( $type ) . '"><input type="hidden" name="fmc_id" value="' . esc_attr( (string) $id ) . '">';
		if ( ! $post && $parent ) {
			$body .= '<input type="hidden" name="fmc_parent" value="' . esc_attr( (string) $parent ) . '">';
		}

		foreach ( Fields::sections( $type ) as $section ) {
			$body .= '<fieldset class="card fmc-section mb-4"><legend class="fmc-section-title">' . esc_html( $section['title'] ) . '</legend><div class="card-body">';
			if ( '' !== $section['help'] ) {
				$body .= '<p class="text-secondary small">' . esc_html( $section['help'] ) . '</p>';
			}
			$body .= '<div class="row g-3">';
			foreach ( $section['fields'] as $key => $field ) {
				$can      = self::can_write( $type, $key, $id );
				$writable = $writable || $can;
				$value    = array_key_exists( $key, $values ) ? $values[ $key ] : self::value( $post, $key );
				$shown    = self::shown( $field, static fn( $k ) => array_key_exists( $k, $values ) ? $values[ $k ] : self::value( $post, $k ) );
				$field   += array( 'design' => (int) ( array_key_exists( A::DESIGN_ID, $values ) ? $values[ A::DESIGN_ID ] : self::value( $post, A::DESIGN_ID ) ) );
				$body    .= 'docs' === $field['w'] ? self::docs( $post, $can ) : self::field( $type, $key, $field, $value, $can, isset( $errors[ $key ] ), $shown );
			}
			$body .= '</div></div></fieldset>';
		}

		if ( $writable ) {
			$body .= '<div class="fmc-savebar">' . self::buttons( $type, $post ) . '</div>';
		} else {
			$body .= '<p class="text-secondary">Solo lectura: no tiene nada que cambiar aquí.</p>';
		}
		$body .= '</form>';

		if ( PostTypes::ACTION === $type && $post ) {
			$body .= self::incidents( $post );
		}
		if ( PostTypes::SPEAKER === $type && $post && Screen::can_delete( $post ) ) {
			$body .= Screen::trash_form( $post );
		}

		$badges = $post ? Lists::badge( $post ) : '';
		return array(
			'tab'      => $tab,
			'back'     => '<a class="small" href="' . esc_url( Screen::url( array( Screen::ARG_TAB => $tab ) ) ) . '">← ' . esc_html( $object->labels->name ) . '</a>',
			'title'    => $post ? Lists::title( $post ) : 'Nuevo: ' . mb_strtolower( $object->labels->singular_name ),

			'subtitle' => $post && PostTypes::ACTION === $type ? (string) get_post_meta( $post->ID, A::SUBTITLE, true ) : '',
			'badges'   => $badges,
			'actions'  => self::header_actions( $type, $post ),
			'body'     => $body,
		);
	}










	public static function privacy_notice(): string {
		$text = (string) apply_filters(
			'fmc_privacy_notice',
			'<strong>Datos personales.</strong> Registre solo a quien imparte o va a impartir formación, y solo los datos que se piden aquí. Quien los registra se asegura de que la persona sabe que constan en este aplicativo para gestionar la formación. Si pide que se borren, dé de baja su ficha indicando el motivo.'
		);
		return '' !== $text ? '<div class="alert alert-info fmc-privacy" role="note">' . wp_kses_post( $text ) . '</div>' : '';
	}






	public static function next_code(): string {


		global $wpdb;

		$codes  = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", D::CODE ) );
		$prefix = D::CODE_PREFIX;
		$max    = 0;
		foreach ( $codes as $code ) {
			if ( preg_match( '/^([A-Za-z]*)(\d+)$/', trim( (string) $code ), $m ) && (int) $m[2] > $max ) {
				$max    = (int) $m[2];
				$prefix = $m[1];
			}
		}
		return $prefix . ( $max + 1 );
	}








	private static function header_actions( string $type, ?\WP_Post $post ): string {
		if ( ! $post ) {
			return '';
		}
		$out = '';
		if ( PostTypes::DESIGN === $type && 'publish' === $post->post_status ) {
			if ( current_user_can( 'edit_fmc_actions' ) ) {
				$out .= '<a class="btn btn-primary btn-sm fmc-abre-panel" title="Nueva acción formativa" href="' . esc_url(
					Screen::url(
						array(
							Screen::ARG_NEW => PostTypes::ACTION,
							'fmc_design'    => $post->ID,
						)
					)
				) . '">Crear una acción con este diseño</a> ';
			}
			$out .= '<a class="btn btn-outline-primary btn-sm" href="' . esc_url( get_permalink( $post ) ) . '" target="_blank" rel="noopener">Ver la ficha pública</a> ';
		}

		if ( PostTypes::SPEAKER !== $type && Screen::can_delete( $post ) ) {
			$out .= Screen::delete_form( $post, 'btn-sm' );
		}
		return $out;
	}








	private static function buttons( string $type, ?\WP_Post $post ): string {
		$button = static fn( string $status, string $label, string $css ): string => '<button class="btn ' . $css . '" type="submit" name="fmc_status" value="' . esc_attr( $status ) . '">' . esc_html( $label ) . '</button> ';
		if ( PostTypes::INCIDENT === $type && ! $post ) {
			return $button( 'publish', 'Enviar la incidencia', 'btn-primary' );
		}
		if ( PostTypes::DESIGN !== $type ) {
			return $button( '', 'Guardar', 'btn-primary' );
		}
		$status = $post ? $post->post_status : 'draft';
		$out    = '';
		if ( 'publish' !== $status ) {
			$out .= $button( 'draft', 'Guardar borrador', 'btn-outline-primary' );
			$out .= $button( 'pending', 'Mandar a revisión', 'pending' === $status ? 'btn-outline-warning' : 'btn-warning' );
		}
		if ( current_user_can( 'publish_fmc_designs' ) ) {
			$out .= 'publish' === $status
				? $button( 'publish', 'Guardar', 'btn-primary' ) . $button( 'draft', 'Devolver a borrador', 'btn-outline-secondary' )
				: $button( 'publish', 'Dar por finalizado', 'btn-success' );
		}
		return $out;
	}













	private static function field( string $type, string $key, array $f, $value, bool $can, bool $error, bool $shown = true ): string {
		$id       = 'f-' . sanitize_html_class( str_replace( ':', '-', $key ) );
		$name     = 'f[' . $key . ']';
		$disabled = $can ? '' : ' disabled';
		$invalid  = $error ? ' is-invalid' : '';
		$label    = '<label class="form-label fw-semibold" for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . ( ! empty( $f['req'] ) ? ' <span class="text-danger" aria-hidden="true">*</span>' : '' ) . '</label>';
		$help     = ! empty( $f['help'] ) ? '<div class="form-text">' . esc_html( $f['help'] ) . '</div>' : '';
		if ( ! $can && isset( MetaRegistration::guarded()[ $type ][ $key ] ) ) {
			$help .= '<div class="form-text fst-italic">Lo rellena el servicio de formación.</div>';
		}


		if ( $can && ! empty( $f['add'] ) && current_user_can( get_post_type_object( $f['add'] )->cap->create_posts ) ) {
			$help .= '<div class="form-text"><a href="' . esc_url(
				Screen::url(
					array(
						Screen::ARG_NEW   => $f['add'],
						Screen::ARG_FRAME => '',
					)
				)
			) . '" target="_blank" rel="noopener">¿No está en la lista? Añadirlo en otra pestaña</a> y volver a abrir esta ficha.</div>';
		}

		switch ( $f['w'] ) {
			case 'textarea':
			case 'rich':
				$rich  = 'rich' === $f['w'] ? ' data-fmc-rich' : '';
				$input = '<textarea class="form-control' . $invalid . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="' . ( $rich ? 6 : 4 ) . '"' . $rich . $disabled . '>' . esc_textarea( (string) $value ) . '</textarea>';
				break;
			case 'checkbox':
				$input = '<div class="form-check mt-2"><input type="hidden" name="' . esc_attr( $name ) . '" value="0"' . $disabled . '><input class="form-check-input" type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( (bool) $value, true, false ) . $disabled . '><label class="form-check-label" for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . '</label></div>';
				$label = '';
				break;
			case 'select':
			case 'term':
			case 'design':
			case 'adviser':
				$choices = self::choices( $f, $key );
				$current = is_array( $value ) ? (string) ( $value[0] ?? '' ) : (string) $value;

				$search = count( $choices ) > 10 ? ' data-fmc-ts data-placeholder="Escriba para buscar…"' : '';
				$input  = '<select class="form-select' . $invalid . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $search . $disabled . '><option value="">—</option>';
				foreach ( $choices as $v => $text ) {
					$input .= '<option value="' . esc_attr( (string) $v ) . '"' . selected( $current, (string) $v, false ) . '>' . esc_html( $text ) . '</option>';
				}
				$input .= '</select>';
				break;
			case 'speakers':
			case 'designs':
			case 'terms':



				$choices = self::choices( $f, $key );
				$current = array_map( 'strval', array_filter( (array) $value ) );

				foreach ( array_diff( $current, array_map( 'strval', array_keys( $choices ) ) ) as $kept ) {
					$choices[ $kept ] = get_the_title( (int) $kept ) . ' (fuera del catálogo)';
				}
				$input = '<input type="hidden" name="' . esc_attr( $name ) . '[]" value=""' . $disabled . '><select class="form-select" multiple data-fmc-ts data-placeholder="Escriba para añadir…" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '[]"' . $disabled . '>';
				foreach ( $choices as $v => $text ) {
					$input .= '<option value="' . esc_attr( (string) $v ) . '"' . ( in_array( (string) $v, $current, true ) ? ' selected' : '' ) . '>' . esc_html( $text ) . '</option>';
				}
				$input .= '</select>';
				break;
			case 'radio':
			case 'checks':
				$choices = self::choices( $f, $key );
				$multi   = 'radio' !== $f['w'];
				$current = array_map( 'strval', (array) $value );
				$input   = '<div class="fmc-checks' . ( count( $choices ) > 8 ? ' is-long' : '' ) . '">' . ( $multi ? '<input type="hidden" name="' . esc_attr( $name ) . '[]" value=""' . $disabled . '>' : '' );
				foreach ( $choices as $v => $text ) {
					$cid    = $id . '-' . sanitize_html_class( (string) $v );
					$input .= '<div class="form-check"><input class="form-check-input" type="' . ( $multi ? 'checkbox' : 'radio' ) . '" id="' . esc_attr( $cid ) . '" name="' . esc_attr( $name . ( $multi ? '[]' : '' ) ) . '" value="' . esc_attr( (string) $v ) . '"' . ( in_array( (string) $v, $current, true ) ? ' checked' : '' ) . $disabled . '><label class="form-check-label" for="' . esc_attr( $cid ) . '">' . esc_html( $text ) . '</label></div>';
				}
				$input .= '</div>';
				$label  = '<span class="form-label fw-semibold d-block">' . esc_html( $f['label'] ) . '</span>';
				break;
			default:
				$types = array(
					'number' => 'number',
					'date'   => 'date',
					'url'    => 'url',
					'email'  => 'email',
				);
				$range = 'number' === $f['w'] ? ' step="any" min="' . (int) ( $f['min'] ?? 0 ) . '"' . ( isset( $f['max'] ) ? ' max="' . (int) $f['max'] . '"' : '' ) : '';
				$input = '<input class="form-control' . $invalid . '" type="' . ( $types[ $f['w'] ] ?? 'text' ) . '"' . $range . ' id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_array( $value ) ? '' : (string) $value ) . '"' . $disabled . '>';
		}
		if ( D::DIGCOMP_URL === $key && is_string( $value ) && '' !== $value ) {
			$input .= self::digcomp( $value );
		}
		$show = '';
		foreach ( (array) ( $f['show'] ?? array() ) as $other => $wanted ) {
			$show = ' data-fmc-show="' . esc_attr( 'f[' . $other . ']' ) . '" data-fmc-show-values="' . esc_attr( implode( ',', $wanted ) ) . '"';
		}
		return '<div class="col-12 col-md-' . (int) ( $f['col'] ?? 12 ) . ( $shown ? '' : ' d-none' ) . '"' . $show . '>' . $label . $input . $help . '</div>';
	}








	public static function shown( array $f, callable $read ): bool {
		foreach ( (array) ( $f['show'] ?? array() ) as $other => $wanted ) {
			if ( ! array_intersect( array_map( 'strval', (array) $read( $other ) ), $wanted ) ) {
				return false;
			}
		}
		return true;
	}













	private static function docs( ?\WP_Post $post, bool $can ): string {
		$current = Documents::of( $post ? $post->ID : 0 );
		$legacy  = array(
			'image'    => D::IMAGE,
			'design'   => D::DESIGN_FILE,
			'sessions' => D::SESSIONS_FILE,
			'support'  => D::SUPPORT_FILE,
		);
		$out     = '<div class="col-12 fmc-docs">';
		foreach ( Documents::kinds() as $kind => $def ) {
			$accept = implode( ',', array_map( static fn( $ext ) => '.' . str_replace( '|', ',.', $ext ), array_keys( $def['mimes'] ) ) );
			$out   .= '<div class="fmc-doc-kind"><div class="d-flex align-items-baseline gap-2 mb-1"><span class="fw-semibold">' . esc_html( $def['label'] ) . '</span><span class="form-text m-0">' . esc_html( $def['help'] ) . '</span></div><ul class="list-group mb-2">';
			foreach ( $current[ $kind ] as $doc ) {
				$file = get_attached_file( $doc->ID );
				$size = $file && file_exists( $file ) ? size_format( (int) filesize( $file ) ) : '';
				$out .= '<li class="list-group-item d-flex align-items-center gap-2 flex-wrap"><a class="me-auto text-break" href="' . esc_url( (string) wp_get_attachment_url( $doc->ID ) ) . '" target="_blank" rel="noopener">' . esc_html( basename( (string) $file ) ) . '</a><span class="text-secondary small">' . esc_html( $size ) . '</span>';
				if ( $can ) {
					$rid  = 'fmc-replace-' . $doc->ID;
					$out .= '<label class="btn btn-sm btn-outline-primary mb-0" for="' . esc_attr( $rid ) . '">Cambiar</label><input class="visually-hidden fmc-file" type="file" id="' . esc_attr( $rid ) . '" name="fmc_replace[' . esc_attr( (string) $doc->ID ) . ']" accept="' . esc_attr( $accept ) . '">';
					$out .= '<input class="btn-check" type="checkbox" id="fmc-remove-' . esc_attr( (string) $doc->ID ) . '" name="fmc_remove[]" value="' . esc_attr( (string) $doc->ID ) . '"><label class="btn btn-sm btn-outline-danger mb-0" for="fmc-remove-' . esc_attr( (string) $doc->ID ) . '">Quitar</label>';
					$out .= '<span class="fmc-file-name small text-primary w-100" data-for="' . esc_attr( $rid ) . '"></span>';
				}
				$out .= '</li>';
			}
			$old = isset( $legacy[ $kind ] ) && $post ? (string) get_post_meta( $post->ID, $legacy[ $kind ], true ) : '';
			if ( '' !== $old ) {
				$out .= '<li class="list-group-item small"><span class="badge text-bg-light me-1">Sistema anterior</span><a href="' . esc_url( $old ) . '" target="_blank" rel="noopener">' . esc_html( rawurldecode( basename( (string) wp_parse_url( $old, PHP_URL_PATH ) ) ) ) . '</a></li>';
			}
			if ( ! $current[ $kind ] && '' === $old ) {
				$out .= '<li class="list-group-item small text-secondary">Ninguno.</li>';
			}
			$out .= '</ul>';
			if ( $can ) {
				$aid  = 'fmc-add-' . $kind;
				$out .= '<div class="mb-3"><label class="btn btn-sm btn-outline-secondary mb-0" for="' . esc_attr( $aid ) . '">' . esc_html( $def['multiple'] ? '+ Añadir' : ( $current[ $kind ] ? 'Cambiar la imagen' : '+ Añadir' ) ) . '</label><input class="visually-hidden fmc-file" type="file" id="' . esc_attr( $aid ) . '" name="fmc_doc[' . esc_attr( $kind ) . '][]" accept="' . esc_attr( $accept ) . '"' . ( $def['multiple'] ? ' multiple' : '' ) . '> <span class="fmc-file-name small text-primary" data-for="' . esc_attr( $aid ) . '"></span></div>';
			}
			$out .= '</div>';
		}
		return $out . '</div>';
	}








	private static function choices( array $f, string $key ): array {
		if ( isset( $f['choices'] ) ) {
			return $f['choices'];
		}
		if ( 0 === strpos( $key, 'tax:' ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => substr( $key, 4 ),
					'hide_empty' => false,
				)
			);
			$out   = array();
			foreach ( is_array( $terms ) ? $terms : array() as $t ) {
				$out[ $t->term_id ] = $t->name;
			}
			return $out;
		}
		if ( 'adviser' === $f['w'] ) {
			return self::advisers();
		}
		$type  = 'speakers' === $f['w'] ? PostTypes::SPEAKER : PostTypes::DESIGN;
		$query = self::posts_query( $type, ! empty( $f['catalogue'] ) );
		if ( PostTypes::SPEAKER === $type ) {
			return self::speaker_choices( get_posts( $query ), (int) ( $f['design'] ?? 0 ) );
		}
		$out = array();
		foreach ( get_posts( $query ) as $p ) {
			$code          = (string) get_post_meta( $p->ID, D::CODE, true );
			$kind          = D::TYPES[ (string) get_post_meta( $p->ID, D::TYPE, true ) ] ?? '';
			$notes         = array_filter( array( $kind, 'publish' !== $p->post_status ? Lists::DESIGN_STATUSES[ $p->post_status ][0] ?? '' : '' ) );
			$out[ $p->ID ] = ( '' !== $code ? $code . ' · ' : '' ) . get_the_title( $p ) . ( $notes ? ' (' . implode( ', ', $notes ) . ')' : '' );
		}
		return $out;
	}






	private static function advisers(): array {
		$out = array();
		foreach ( get_users(
			array(
				'role__in' => array( 'fmc_adviser', 'fmc_curator' ),
				'orderby'  => 'display_name',
				'fields'   => array( 'ID', 'display_name' ),
			)
		) as $u ) {
			$out[ (int) $u->ID ] = $u->display_name;
		}
		return $out;
	}








	private static function posts_query( string $type, bool $catalogue ): array {
		$query = array(
			'post_type'        => $type,
			'post_status'      => PostTypes::DESIGN === $type && ! $catalogue ? array( 'publish', 'pending', 'draft' ) : 'publish',
			'posts_per_page'   => -1,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'fmc_visible'      => ! current_user_can( 'edit_others_' . $type . 's' ),
			'suppress_filters' => false,
			'no_found_rows'    => true,
		);
		if ( $catalogue ) {

			$query['meta_query'] = array(
				array(
					'key'   => D::IN_CATALOGUE,
					'value' => '1',
				),
			);
		}
		return $query;
	}









	private static function speaker_choices( array $speakers, int $design ): array {
		$stats       = Speakers::stats();
		$recommended = array_flip( Speakers::recommended_for( $design ) );
		$out         = array();
		foreach ( $speakers as $p ) {
			$given         = (int) ( $stats[ $p->ID ]['actions'] ?? 0 );
			$details       = array_filter( array( (string) get_post_meta( $p->ID, S::ZONE, true ), $given ? sprintf( '%d acción(es)', $given ) : '' ) );
			$out[ $p->ID ] = ( isset( $recommended[ $p->ID ] ) ? '★ ' : '' ) . get_the_title( $p ) . ( $details ? ' · ' . implode( ' · ', $details ) : '' );
		}

		uksort(
			$out,
			static function ( $a, $b ) use ( $out, $recommended ) {
				$first = isset( $recommended[ $b ] ) <=> isset( $recommended[ $a ] );
				return 0 !== $first ? $first : strcmp( $out[ $a ], $out[ $b ] );
			}
		);
		return $out;
	}







	private static function digcomp( string $url ): string {
		$summary = DigComp::summary( $url );
		if ( array() === $summary ) {
			return '<div class="form-text text-warning">La dirección no trae ninguna competencia que se pueda leer.</div>';
		}
		$out = '<div class="fmc-digcomp card card-body mt-2"><div class="d-flex align-items-center mb-2"><strong class="me-auto">Resumen del perfil</strong><a class="btn btn-sm btn-outline-primary" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">Abrir en la herramienta</a></div><table class="table table-sm mb-0"><thead><tr><th scope="col">Competencia</th><th scope="col">Niveles (indicadores)</th></tr></thead><tbody>';
		foreach ( $summary as $code => $levels ) {
			$cells = array();
			foreach ( $levels as $level => $n ) {
				$cells[] = '<span class="badge text-bg-light border">' . esc_html( $level ) . ' · ' . (int) $n . '</span>';
			}
			$out .= '<tr><td><strong>' . esc_html( $code ) . '</strong> ' . esc_html( DigComp::COMPETENCES[ $code ] ?? '' ) . '</td><td>' . implode( ' ', $cells ) . '</td></tr>';
		}
		return $out . '</tbody></table></div>';
	}







	private static function action_summary( \WP_Post $post ): string {
		$m        = static fn( string $k ): float => (float) get_post_meta( $post->ID, $k, true );
		$hours    = $m( A::HOURS_ONSITE ) + $m( A::HOURS_ONLINE );
		$replicas = max( 1, (int) $m( A::REPLICAS ) );

		$items = array(
			'Curso escolar' => \Fmc\Domain\SchoolYear::of( (string) get_post_meta( $post->ID, A::START, true ) ),
			'Horas totales' => $hours ? ( $hours + 0 ) . ' h' . ( $replicas > 1 ? ' (' . ( $hours * $replicas + 0 ) . ' h con el desdoble)' : '' ) : '',
			'Ponentes'      => (string) count( array_filter( (array) get_post_meta( $post->ID, A::SPEAKERS, true ) ) ),
		);
		$out   = '<div class="fmc-counters mb-4">';
		foreach ( $items as $label => $value ) {
			$out .= '<div class="fmc-counter"><strong>' . esc_html( '' !== $value ? $value : '—' ) . '</strong><span>' . esc_html( $label ) . '</span></div>';
		}
		return $out . '</div>';
	}







	private static function incidents( \WP_Post $post ): string {
		$list = get_posts(
			array(
				'post_type'      => PostTypes::INCIDENT,
				'post_parent'    => $post->ID,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$out  = '<section class="card fmc-section mt-2"><div class="card-body"><div class="d-flex align-items-center mb-2"><h2 class="h5 me-auto mb-0">Incidencias</h2>';
		if ( current_user_can( 'edit_post', $post->ID ) && current_user_can( 'edit_fmc_incidents' ) ) {
			$out .= '<a class="btn btn-outline-primary btn-sm" href="' . esc_url(
				Screen::url(
					array(
						Screen::ARG_NEW    => PostTypes::INCIDENT,
						Screen::ARG_PARENT => $post->ID,
					)
				)
			) . '">Abrir una incidencia</a>';
		}
		$out .= '</div>';
		if ( array() === $list ) {
			return $out . '<p class="text-secondary mb-0">Ninguna.</p></div></section>';
		}
		$out .= '<ul class="list-group list-group-flush">';
		foreach ( $list as $incident ) {
			$res  = (string) get_post_meta( $incident->ID, I::RESOLUTION, true );
			$out .= '<li class="list-group-item d-flex gap-2"><a href="' . esc_url( Screen::url( array( Screen::ARG_EDIT => $incident->ID ) ) ) . '">' . esc_html( get_the_date( 'd/m/Y', $incident ) ) . '</a><span class="text-truncate">' . esc_html( wp_trim_words( (string) get_post_meta( $incident->ID, I::REASON, true ), 14 ) ) . '</span><span class="ms-auto badge text-bg-light">' . esc_html( I::RESOLUTIONS[ $res ] ?? 'Pendiente' ) . '</span></li>';
		}
		return $out . '</ul></div></section>';
	}






	public static function save(): void {

		$type = isset( $_POST['fmc_type'] ) ? sanitize_key( wp_unslash( $_POST['fmc_type'] ) ) : '';
		$id   = isset( $_POST['fmc_id'] ) ? absint( $_POST['fmc_id'] ) : 0;

		if ( ! isset( PostTypes::definitions()[ $type ] ) ) {
			Screen::leave( Screen::url( array( Screen::ARG_NOTICE => 'denied' ) ) );
		}
		check_admin_referer( self::nonce_action( $type, $id ), '_fmc_nonce' );


		$raw    = isset( $_POST['f'] ) && is_array( $_POST['f'] ) ? wp_unslash( $_POST['f'] ) : array();
		$status = isset( $_POST['fmc_status'] ) ? sanitize_key( wp_unslash( $_POST['fmc_status'] ) ) : '';
		$parent = isset( $_POST['fmc_parent'] ) ? absint( $_POST['fmc_parent'] ) : 0;

		$files  = Documents::from_request( $_FILES );
		$remove = isset( $_POST['fmc_remove'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['fmc_remove'] ) ) : array();
		$result = self::apply( $type, $id, $raw, $status, $parent, $files, $remove );
		if ( 'denied' === $result['outcome'] ) {
			Screen::leave( Screen::url( array( Screen::ARG_NOTICE => 'denied' ) ) );
		}
		if ( 'invalid' === $result['outcome'] ) {
			if ( $files ) {
				$result['errors']['_files'] = 'Los documentos elegidos no se han subido: vuelva a elegirlos cuando corrija lo anterior.';
			}

			echo Screen::document( self::render( $type, $id ? get_post( $id ) : null, $result['values'], $result['errors'] ) );
			exit;
		}
		$id = $result['id'];
		if ( $result['problems'] ) {
			set_transient( 'fmc_problems_' . get_current_user_id(), $result['problems'], MINUTE_IN_SECONDS );
		}

		Screen::leave(
			Screen::url(
				array(
					Screen::ARG_EDIT   => $id,
					Screen::ARG_NOTICE => $result['outcome'],
				)
			)
		);
	}
















	public static function apply( string $type, int $id, array $raw, string $status, int $parent_id, array $files = array(), array $remove = array() ): array {
		$denied = array(
			'outcome'  => 'denied',
			'id'       => $id,
			'values'   => array(),
			'errors'   => array(),
			'problems' => array(),
		);
		if ( ! isset( PostTypes::definitions()[ $type ] ) ) {
			return $denied;
		}
		$post = $id ? get_post( $id ) : null;
		if ( $id && ( ! $post instanceof \WP_Post || $post->post_type !== $type ) ) {
			return $denied;
		}

		$fields   = Fields::all( $type );
		$values   = array();
		$errors   = array();
		$writable = array();
		foreach ( $fields as $key => $f ) {
			if ( 'docs' === $f['w'] || ! self::can_write( $type, $key, $id ) ) {
				continue;
			}
			$writable[ $key ] = true;
			$value            = $raw[ $key ] ?? ( in_array( $f['w'], array( 'checkbox' ), true ) ? '0' : '' );
			if ( is_array( $value ) ) {
				$value = array_values( array_filter( array_map( 'sanitize_text_field', $value ), 'strlen' ) );
			} else {
				$value = in_array( $f['w'], array( 'textarea', 'rich' ), true ) ? (string) $value : sanitize_text_field( (string) $value );
			}
			$values[ $key ] = $value;
		}



		if ( PostTypes::ACTION === $type && ! empty( $values[ A::DESIGN_ID ] ) ) {
			$design = (int) $values[ A::DESIGN_ID ];
			if ( isset( $values[ A::MODALITY ] ) && '' === $values[ A::MODALITY ] ) {
				$values[ A::MODALITY ] = (string) get_post_meta( $design, D::MODALITY, true );
			}
			if ( isset( $values[ A::HOURS_ONSITE ], $values[ A::HOURS_ONLINE ] ) && '' === $values[ A::HOURS_ONSITE ] . $values[ A::HOURS_ONLINE ] ) {
				$values[ A::HOURS_ONSITE ] = (string) get_post_meta( $design, D::HOURS_ONSITE, true );
				$values[ A::HOURS_ONLINE ] = (string) get_post_meta( $design, D::HOURS_ONLINE, true );
			}
		}


		$read = static function ( string $k ) use ( &$values, $post ) {
			return array_key_exists( $k, $values ) ? $values[ $k ] : self::value( $post, $k );
		};
		foreach ( $values as $key => $value ) {
			$f = $fields[ $key ];
			if ( ! self::shown( $f, $read ) ) {

				$values[ $key ] = is_array( $value ) ? array() : '';
				continue;
			}
			$empty = '' === $value || array() === $value;
			if ( ! empty( $f['req'] ) && $empty && ! ( 'post_title' === $key && PostTypes::ACTION === $type ) ) {
				$errors[ $key ] = 'Falta rellenar «' . $f['label'] . '».';
				continue;
			}
			if ( ! $empty && 'number' === $f['w'] && ( ( isset( $f['min'] ) && (float) $value < $f['min'] ) || ( isset( $f['max'] ) && (float) $value > $f['max'] ) ) ) {
				$errors[ $key ] = '«' . $f['label'] . '» tiene que estar entre ' . (int) ( $f['min'] ?? 0 ) . ' y ' . (int) $f['max'] . '.';
				continue;
			}
			if ( ! $empty && ! empty( $f['unique'] ) && self::taken( $type, $key, (string) $value, $id ) ) {
				$errors[ $key ] = 'Ya hay otro con ese valor en «' . $f['label'] . '».';
			}
		}

		if ( array() === $writable ) {
			return $denied;
		}
		if ( PostTypes::INCIDENT === $type && ! $id && ( PostTypes::ACTION !== get_post_type( $parent_id ) || ! current_user_can( 'edit_post', $parent_id ) ) ) {
			return $denied;
		}
		if ( $errors ) {
			$values['_parent'] = $parent_id;
			return array(
				'outcome'  => 'invalid',
				'id'       => $id,
				'values'   => $values,
				'errors'   => $errors,
				'problems' => array(),
			);
		}

		$postarr = array( 'post_type' => $type );
		if ( $post ) {
			$postarr['ID'] = $post->ID;
		}
		if ( isset( $writable['post_title'] ) ) {
			$postarr['post_title'] = (string) $values['post_title'];
		}


		if ( PostTypes::ACTION === $type && ! empty( $values[ A::DESIGN_ID ] ) && ( ! $post || '' === trim( $post->post_title ) || (int) get_post_meta( $post->ID, A::DESIGN_ID, true ) !== (int) $values[ A::DESIGN_ID ] ) ) {
			$postarr['post_title'] = get_the_title( (int) $values[ A::DESIGN_ID ] );
		}

		if ( PostTypes::DESIGN === $type && ( ! $post || '' === (string) get_post_meta( $post->ID, D::CODE, true ) ) ) {
			$values[ D::CODE ] = self::next_code();
		}
		if ( PostTypes::SPEAKER === $type && isset( $values['fmc_last_name'] ) ) {
			$postarr['post_title'] = trim( $values['fmc_last_name'] . ', ' . $values['fmc_first_name'], ', ' );
		}
		if ( isset( $writable['post_content'] ) ) {
			$postarr['post_content'] = wp_kses_post( (string) $values['post_content'] );
		}
		$postarr['post_status'] = self::next_status( $type, $post, $status );
		if ( ! $post && PostTypes::INCIDENT === $type ) {
			$postarr['post_parent'] = $parent_id;

			$postarr['post_title'] = get_the_title( $parent_id );
		}

		$can_edit_post = $post ? current_user_can( 'edit_post', $post->ID ) : true;
		if ( $can_edit_post ) {
			$result = $post ? wp_update_post( $postarr, true ) : wp_insert_post( $postarr, true );
			if ( is_wp_error( $result ) ) {
				return $denied;
			}
			$id = (int) $result;
		}

		foreach ( $values as $key => $value ) {
			if ( in_array( $key, array( 'post_title', 'post_content', '_parent' ), true ) ) {
				continue;
			}
			if ( 0 === strpos( $key, 'tax:' ) ) {
				if ( $can_edit_post ) {
					wp_set_object_terms( $id, array_map( 'intval', (array) $value ), substr( $key, 4 ) );
				}
				continue;
			}

			if ( '' === $value || array() === $value ) {
				delete_post_meta( $id, $key );
				continue;
			}
			update_post_meta( $id, $key, $value );
		}
		if ( ! $post && PostTypes::INCIDENT === $type && ! isset( $values[ I::RESOLUTION ] ) ) {
			update_post_meta( $id, I::RESOLUTION, 'pending' );
		}

		$problems = array();
		if ( PostTypes::DESIGN === $type && ( $files || $remove ) ) {
			$problems = Documents::apply( $id, $files, $remove );
		}

		return array(
			'outcome'  => $post ? 'saved' : 'created',
			'id'       => $id,
			'values'   => $values,
			'errors'   => array(),
			'problems' => $problems,
		);
	}










	public static function taken( string $type, string $key, string $value, int $id ): bool {
		$args = array(
			'post_type'      => $type,
			'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'post__not_in'   => array( $id ), 
		);
		if ( 'post_title' === $key ) {
			$args['title'] = $value;
		} else {
			$args['meta_query'] = array( 
				array(
					'key'   => $key,
					'value' => $value,
				),
			);
		}
		return array() !== get_posts( $args );
	}









	public static function next_status( string $type, ?\WP_Post $post, string $requested ): string {
		$current = $post ? $post->post_status : '';
		if ( PostTypes::DESIGN === $type ) {
			if ( 'publish' === $requested && ! current_user_can( 'publish_fmc_designs' ) ) {
				return '' !== $current ? $current : 'draft';
			}
			return in_array( $requested, array( 'draft', 'pending', 'publish' ), true ) ? $requested : ( '' !== $current ? $current : 'draft' );
		}
		if ( '' !== $current ) {
			return $current;
		}
		return current_user_can( get_post_type_object( $type )->cap->publish_posts ) ? 'publish' : 'draft';
	}
}








namespace Fmc\PublicFront;

use Fmc\Meta\SpeakerMetaKeys;
use Fmc\PostType\PostTypes;









final class Screen {

	public const ARG_TAB    = 'fmc_tab';
	public const ARG_EDIT   = 'fmc_edit';
	public const ARG_NEW    = 'fmc_new';
	public const ARG_PARENT = 'fmc_parent';
	public const ARG_NOTICE = 'fmc_notice';




	public const ARG_FRAME = 'fmc_marco';




	public const BOOTSTRAP = array(
		'css'     => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css',
		'css_sri' => 'sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB',
	);




	public const SWEETALERT = array(
		'js'     => 'https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.all.min.js',
		'js_sri' => 'sha384-nLoOnA/BDh8A/jxqtckg4DumuCGOBYUnNJLZdQz/zfYNp3wcjGSoWTAzgko06G/2',
	);






	public const TOM_SELECT = array(
		'js'      => 'https://cdn.jsdelivr.net/npm/tom-select@2.6.2/dist/js/tom-select.complete.min.js',
		'js_sri'  => 'sha384-1mYKSrq1Nu5YJmWrIU9cvwWQlUyyukJJM9XMkAxY03nb/T69CK+Sn7rjFxVU3SSM',
		'css'     => 'https://cdn.jsdelivr.net/npm/tom-select@2.6.2/dist/css/tom-select.bootstrap5.min.css',
		'css_sri' => 'sha384-qNqaCnsmyTrYVwmqv4/4PcwMK8ZFAQnYPpVjWor+6cX6rKsPhebzu8vO2J3s+VZg',
	);






	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'route' ), 1 );
		add_filter( 'login_redirect', array( self::class, 'login_redirect' ), 10, 2 );
		add_filter( 'show_admin_bar', array( self::class, 'show_admin_bar' ), 100 );
	}













	public static function show_admin_bar( $show = false ): bool {
		unset( $show );
		if ( self::framed() || ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$key = 'wpfront_ure_user_switching_stack_' . COOKIEHASH;
		if ( ! class_exists( '\\WPFront\\URE\\WPFront_User_Role_Editor_Utils' ) || ! isset( $_COOKIE[ $key ] ) || ! is_string( $_COOKIE[ $key ] ) ) {
			return false;
		}

		$stack = explode( '-', (string) \WPFront\URE\WPFront_User_Role_Editor_Utils::decrypt( sanitize_text_field( wp_unslash( $_COOKIE[ $key ] ) ) ), 4 );
		if ( count( $stack ) < 3 || COOKIEHASH !== $stack[0] || ! ctype_digit( $stack[1] ) ) {
			return false;
		}
		$age   = time() - (int) $stack[1];
		$users = explode( ',', $stack[2] );
		return $age >= 0 && $age <= 12 * HOUR_IN_SECONDS && ctype_digit( $users[0] ) && user_can( (int) $users[0], 'manage_options' );
	}








	public static function login_redirect( $redirect_to, $requested ) {
		if ( '' === (string) $requested || admin_url() === (string) $requested ) {
			return home_url( '/' );
		}
		return (string) $redirect_to;
	}






	public static function tabs(): array {
		$tabs    = array();
		$actions = current_user_can( 'edit_fmc_actions' ) || current_user_can( 'read_private_fmc_actions' );
		if ( $actions ) {
			$tabs['calendar'] = array(
				'label' => 'Calendario',
				'type'  => PostTypes::ACTION,
			);
			$tabs['actions']  = array(
				'label' => 'Acciones formativas',
				'type'  => PostTypes::ACTION,
			);
		}
		$tabs['designs'] = array(
			'label' => 'Diseños de curso',
			'type'  => PostTypes::DESIGN,
		);
		if ( current_user_can( 'edit_fmc_speakers' ) ) {
			$tabs['speakers'] = array(
				'label' => 'Ponentes',
				'type'  => PostTypes::SPEAKER,
			);
		}
		if ( current_user_can( 'edit_fmc_incidents' ) || current_user_can( 'read_private_fmc_incidents' ) ) {
			$tabs['incidents'] = array(
				'label' => 'Incidencias',
				'type'  => PostTypes::INCIDENT,
			);
		}
		return $tabs;
	}







	public static function tab_of( string $type ): string {
		$map = array(
			PostTypes::ACTION   => 'actions',
			PostTypes::DESIGN   => 'designs',
			PostTypes::SPEAKER  => 'speakers',
			PostTypes::INCIDENT => 'incidents',
		);
		return $map[ $type ] ?? 'designs';
	}







	public static function url( array $args = array() ): string {

		if ( self::framed() && ! array_key_exists( self::ARG_FRAME, $args ) ) {
			$args[ self::ARG_FRAME ] = '1';
		}
		return add_query_arg( array_filter( $args, static fn( $v ) => '' !== $v && null !== $v ), home_url( '/' ) );
	}






	public static function framed(): bool {

		return isset( $_REQUEST[ self::ARG_FRAME ] ) && '1' === $_REQUEST[ self::ARG_FRAME ];
	}






	public static function route(): void {
		if ( ! is_front_page() ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( '/' ) ) );
			exit;
		}
		nocache_headers();


		if ( isset( $_POST['fmc_delete'] ) ) {
			self::delete( absint( $_POST['fmc_delete'] ) ); 
			return;
		}

		if ( isset( $_POST['fmc_save'] ) ) {
			Editor::save();
			return;
		}


		$edit = isset( $_GET[ self::ARG_EDIT ] ) ? absint( $_GET[ self::ARG_EDIT ] ) : 0;
		$new  = isset( $_GET[ self::ARG_NEW ] ) ? sanitize_key( wp_unslash( $_GET[ self::ARG_NEW ] ) ) : '';
		$tab  = isset( $_GET[ self::ARG_TAB ] ) ? sanitize_key( wp_unslash( $_GET[ self::ARG_TAB ] ) ) : '';


		$tabs = self::tabs();


		if ( '' !== Lists::arg( 'fmc_csv' ) && isset( $tabs[ $tab ] ) && PostTypes::SPEAKER !== $tabs[ $tab ]['type'] ) {
			Lists::csv( $tabs[ $tab ]['type'] );
			exit;
		}
		if ( $edit || '' !== $new ) {
			$screen = Editor::screen( $edit, $new );
		} else {
			if ( ! isset( $tabs[ $tab ] ) ) {
				$tab = (string) array_key_first( $tabs );
			}
			$screen = 'calendar' === $tab ? Calendar::screen() : Lists::screen( $tab, $tabs[ $tab ]['type'] );
		}


		echo self::document( $screen );
		exit;
	}












	public static function deployment_chrome(): array {
		$chrome = (array) apply_filters(
			'fmc_chrome',
			array(
				'head'   => '',
				'brand'  => '',
				'footer' => '',
			)
		);
		return array(
			'head'   => (string) ( $chrome['head'] ?? '' ),
			'brand'  => (string) ( $chrome['brand'] ?? '' ),
			'footer' => (string) ( $chrome['footer'] ?? '' ),
		);
	}







	public static function document( array $screen ): string {
		if ( self::framed() ) {
			return self::fragment( $screen );
		}
		$chrome = self::deployment_chrome();
		$out    = '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
		$out   .= '<title>' . esc_html( $screen['title'] . ' · Formación' ) . '</title>';


		$out .= '<link rel="stylesheet" href="' . esc_url( self::BOOTSTRAP['css'] ) . '" integrity="' . esc_attr( self::BOOTSTRAP['css_sri'] ) . '" crossorigin="anonymous">';


		$out .= '<link rel="stylesheet" href="' . esc_url( self::TOM_SELECT['css'] ) . '" integrity="' . esc_attr( self::TOM_SELECT['css_sri'] ) . '" crossorigin="anonymous">';
		$bar  = self::admin_bar();
		$out .= '<style>' . Assets::css() . '</style>' . $bar['head'] . $chrome['head'] . '</head><body class="fmc' . ( '' !== $bar['body'] ? ' admin-bar' : '' ) . '">' . $bar['body'];
		$out .= self::chrome( $screen['tab'], $chrome['brand'] );

		$out .= '<div class="fmc-head"><div class="container d-flex flex-wrap align-items-start gap-3">';
		$out .= '<div class="me-auto">' . ( $screen['back'] ?? '' ) . '<h1 class="fmc-h1">' . esc_html( $screen['title'] ) . ( $screen['badges'] ?? '' ) . '</h1>';
		if ( ! empty( $screen['subtitle'] ) ) {
			$out .= '<p class="fmc-sub">' . esc_html( $screen['subtitle'] ) . '</p>';
		}
		$out .= '</div>' . ( $screen['actions'] ?? '' ) . '</div></div>';

		$out .= '<main class="container py-4">' . self::notice() . $screen['body'] . '</main>';
		$out .= '<footer class="fmc-foot"><div class="container">Aplicativo de formación' . $chrome['footer'] . '</div></footer>';

		$out .= '<script src="' . esc_url( self::TOM_SELECT['js'] ) . '" integrity="' . esc_attr( self::TOM_SELECT['js_sri'] ) . '" crossorigin="anonymous"></script>';

		$out .= '<script src="' . esc_url( self::SWEETALERT['js'] ) . '" integrity="' . esc_attr( self::SWEETALERT['js_sri'] ) . '" crossorigin="anonymous"></script>';


		$out .= '<script src="' . esc_url( includes_url( 'js/tinymce/tinymce.min.js' ) ) . '"></script>';
		$out .= '<script>window.fmcTinymceBase = ' . wp_json_encode( includes_url( 'js/tinymce' ) ) . ';</script>';
		$out .= '<script>' . Assets::contents( 'js/fmc-app.js' ) . '</script>';
		$out .= $bar['foot'] . '</body></html>';
		return $out;
	}












	private static function admin_bar(): array {
		global $wp_admin_bar;
		$none = array(
			'head' => '',
			'body' => '',
			'foot' => '',
		);
		if ( ! is_admin_bar_showing() || ! is_object( $wp_admin_bar ) ) {
			return $none;
		}
		ob_start();
		wp_print_styles( array( 'admin-bar' ) );
		$head = (string) ob_get_clean() . '<style>html{margin-top:32px!important}@media screen and (max-width:782px){html{margin-top:46px!important}}</style>';
		ob_start();
		wp_admin_bar_render();
		$body = (string) ob_get_clean();
		ob_start();
		wp_print_scripts( array( 'admin-bar' ) );
		return array(
			'head' => $head,
			'body' => $body,
			'foot' => (string) ob_get_clean(),
		);
	}










	public static function fragment( array $screen ): string {
		$out = '<div class="fmc-fragment" data-fmc-title="' . esc_attr( $screen['title'] ) . '">';
		if ( ! empty( $screen['badges'] ) || ! empty( $screen['actions'] ) ) {
			$out .= '<div class="d-flex flex-wrap align-items-center gap-2 mb-3">' . ( $screen['badges'] ?? '' ) . '<span class="ms-auto">' . ( $screen['actions'] ?? '' ) . '</span></div>';
		}
		return $out . self::notice() . $screen['body'] . '</div>';
	}












	private static function chrome( string $current, string $brand = '' ): string {
		$user = wp_get_current_user();
		$name = (string) $user->display_name;
		$out  = '<header class="fmc-top"><div class="container fmc-top-in">' . $brand;
		$out .= '<a class="fmc-brand" href="' . esc_url( home_url( '/' ) ) . '">Formación</a>';
		$out .= '<details class="fmc-yo"><summary><span class="fmc-avatar" aria-hidden="true">' . esc_html( strtoupper( mb_substr( $name, 0, 1 ) ) ) . '</span>';
		$out .= '<span class="fmc-yo-txt"><strong>' . esc_html( $name ) . '</strong><small>' . esc_html( self::role_label( $user ) ) . '</small></span></summary><div class="fmc-yo-menu">';
		if ( current_user_can( 'manage_options' ) ) {
			$out .= '<a href="' . esc_url( admin_url() ) . '">Escritorio</a>';
		}

		if ( current_user_can( 'switch_users' ) ) {
			$out .= '<a href="' . esc_url( admin_url( 'users.php' ) ) . '">Ver como otro usuario</a>';
		}
		$out .= '<a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">Salir</a></div></details>';
		$out .= '</div></header>';

		$out .= '<nav class="fmc-nav" aria-label="Secciones"><div class="container"><ul class="nav">';
		foreach ( self::tabs() as $key => $tab ) {
			$active = $key === $current;
			$out   .= '<li class="nav-item"><a class="nav-link' . ( $active ? ' active' : '' ) . '"' . ( $active ? ' aria-current="page"' : '' ) . ' href="' . esc_url( self::url( array( self::ARG_TAB => $key ) ) ) . '">' . esc_html( $tab['label'] ) . '</a></li>';
		}
		$out .= '</ul></div></nav>';
		return $out;
	}







	public static function role_label( \WP_User $user ): string {
		$names = wp_roles()->role_names;
		foreach ( array( 'administrator', 'fmc_curator', 'fmc_adviser', 'fmc_training_service' ) as $role ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				return 'administrator' === $role ? 'Administración' : translate_user_role( $names[ $role ] ?? $role );
			}
		}
		return '';
	}






	private static function notice(): string {
		$texts = array(
			'saved'   => array( 'success', 'Guardado.' ),
			'created' => array( 'success', 'Creado.' ),
			'denied'  => array( 'danger', 'No tiene permiso para hacer eso.' ),
			'trashed' => array( 'success', 'Enviado a la papelera. Se puede recuperar desde el escritorio.' ),
			'reason'  => array( 'warning', 'Para dar de baja a un ponente hay que escribir el motivo.' ),
		);

		$key = isset( $_GET[ self::ARG_NOTICE ] ) ? sanitize_key( wp_unslash( $_GET[ self::ARG_NOTICE ] ) ) : '';
		$out = isset( $texts[ $key ] ) ? '<div class="alert alert-' . esc_attr( $texts[ $key ][0] ) . '" role="status">' . esc_html( $texts[ $key ][1] ) . '</div>' : '';


		$problems = get_transient( 'fmc_problems_' . get_current_user_id() );
		if ( is_array( $problems ) && $problems ) {
			delete_transient( 'fmc_problems_' . get_current_user_id() );
			$out .= '<div class="alert alert-warning" role="alert"><strong>Algunos documentos no se han subido:</strong><ul class="mb-0">';
			foreach ( $problems as $problem ) {
				$out .= '<li>' . esc_html( (string) $problem ) . '</li>';
			}
			$out .= '</ul></div>';
		}
		return $out;
	}











	public static function can_delete( \WP_Post $post ): bool {
		if ( PostTypes::SPEAKER === $post->post_type && 'trash' !== $post->post_status ) {
			return current_user_can( 'delete_post', $post->ID );
		}
		return current_user_can( 'manage_options' ) && current_user_can( 'delete_post', $post->ID );
	}







	public static function trash_form( \WP_Post $post ): string {
		return '<form class="card fmc-section mt-3" method="post" action="' . esc_url( home_url( '/' ) ) . '" data-fmc-confirm="' . esc_attr( '¿Dar de baja a «' . get_the_title( $post ) . '»?' ) . '" data-fmc-confirm-text="Su ficha pasa a la papelera con el motivo y deja de verse en el aplicativo." data-fmc-confirm-button="Dar de baja"><div class="card-body">'
			. '<h2 class="h6">Dar de baja</h2><p class="text-secondary small">Por ejemplo, si la persona pide que se borren sus datos. La curaduría ve en la papelera quién la dio de baja, cuándo y por qué.</p>'
			. wp_nonce_field( 'fmc_delete_' . $post->ID, '_fmc_nonce', true, false )
			. '<input type="hidden" name="fmc_delete" value="' . esc_attr( (string) $post->ID ) . '">'
			. '<label class="form-label fw-semibold" for="fmc-reason">Motivo <span class="text-danger" aria-hidden="true">*</span></label><div class="d-flex gap-2"><input class="form-control" id="fmc-reason" name="fmc_reason" required maxlength="500">'
			. '<button class="btn btn-outline-danger text-nowrap" type="submit">Dar de baja</button></div></div></form>';
	}








	public static function delete_form( \WP_Post $post, string $size = '' ): string {
		$title = get_the_title( $post );

		return '<form class="d-inline" method="post" action="' . esc_url( home_url( '/' ) ) . '" data-fmc-confirm="' . esc_attr( '¿Enviar «' . $title . '» a la papelera?' ) . '" data-fmc-confirm-text="Dejará de verse en el aplicativo. Se puede recuperar desde el escritorio." data-fmc-confirm-button="Enviar a la papelera">'
			. wp_nonce_field( 'fmc_delete_' . $post->ID, '_fmc_nonce', true, false )
			. '<input type="hidden" name="fmc_delete" value="' . esc_attr( (string) $post->ID ) . '">'
			. '<button class="btn btn-outline-danger ' . esc_attr( $size ) . '" type="submit">Borrar</button></form>';
	}







	public static function delete( int $id ): void {
		check_admin_referer( 'fmc_delete_' . $id, '_fmc_nonce' );
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || ! isset( PostTypes::definitions()[ $post->post_type ] ) || ! self::can_delete( $post ) ) {
			self::leave( self::url( array( self::ARG_NOTICE => 'denied' ) ) );
		}
		if ( PostTypes::SPEAKER === $post->post_type ) {

			$reason = isset( $_POST['fmc_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fmc_reason'] ) ) : '';
			if ( '' === trim( $reason ) ) {
				self::leave(
					self::url(
						array(
							self::ARG_EDIT   => $id,
							self::ARG_NOTICE => 'reason',
						)
					)
				);
			}
			update_post_meta( $id, SpeakerMetaKeys::TRASH_REASON, $reason );
			update_post_meta( $id, SpeakerMetaKeys::TRASHED_BY, get_current_user_id() );
			update_post_meta( $id, SpeakerMetaKeys::TRASHED_AT, current_time( 'mysql' ) );
		}
		wp_trash_post( $id );
		self::leave(
			self::url(
				array(
					self::ARG_FRAME  => '',
					self::ARG_TAB    => self::tab_of( $post->post_type ),
					self::ARG_NOTICE => 'trashed',
				)
			)
		);
	}







	public static function leave( string $url ): void {
		wp_safe_redirect( $url );
		exit;
	}
}








namespace Fmc;

use Fmc\Meta\MetaRegistration;
use Fmc\PostType\PostTypes;
use Fmc\PublicFront\Lists;
use Fmc\PublicFront\Screen;
use Fmc\Taxonomy\Taxonomies;









final class App {






	public static function boot(): void {
		static $booted = false;
		if ( $booted ) {
			return;
		}
		$booted = true;

		add_action( 'init', array( Taxonomies::class, 'register' ), 9 );
		add_action( 'init', array( PostTypes::class, 'register' ), 10 );
		add_action( 'init', array( PostTypes::class, 'grant_caps_to_roles' ), 11 );

		MetaRegistration::register();


		Lists::register();
		Screen::register();
	}
}


\Fmc\PublicFront\Assets::set_inline( array (
  'css/fmc-app.css' => '/* Aplicativo de formación: la piel de los aplicativos hermanos (eventos y
   registro de visitas), con los mismos colores, cabecera, pestañas y pie. */

.fmc {
	--fmc-brand: #1b4f8a;
	--fmc-brand-cont: #e3ecf7;
	--fmc-line: #e4e7eb;
	--fmc-soft: #f7f8fa;
	--fmc-sup-2: #f4f6f9;
	--fmc-text: #1c2024;
	--fmc-text-2: #5a6672;
	--fmc-foot-bg: #05395c;
	--fmc-ok: #1f6b45;
	--fmc-ok-cont: #e4f0e8;
	--fmc-wait: #8a5a12;
	--fmc-wait-cont: #f9efe0;
	--fmc-bad: #a8342c;
	--fmc-bad-cont: #fae9e7;
	--fmc-e1: 0 1px 2px rgba(16, 24, 40, .05), 0 1px 3px rgba(16, 24, 40, .07);
	--fmc-e2: 0 2px 4px rgba(16, 24, 40, .05), 0 4px 12px rgba(16, 24, 40, .09);
	--fmc-r: 12px;
	--fmc-width: 1200px;
	--bs-primary: #1b4f8a;
	--bs-primary-rgb: 27, 79, 138;
	--bs-link-color: #1b4f8a;
	--bs-link-color-rgb: 27, 79, 138;
	--bs-link-hover-color: #123a68;
	background: var(--fmc-soft);
	color: var(--fmc-text);
	/* El pie, abajo del todo aunque haya poco contenido. */
	min-height: 100vh;
	display: flex;
	flex-direction: column;
}
.fmc main { flex: 1 0 auto; }
.fmc .container { max-width: var(--fmc-width); }

/* Cabecera: marca del despliegue (si la hay), nombre del aplicativo y la cuenta */
.fmc-top { background: #fff; border-bottom: 1px solid var(--fmc-line); }
.fmc-top-in { display: flex; align-items: center; gap: 14px; min-height: 72px; flex-wrap: wrap; padding-top: 10px; padding-bottom: 10px; }
.fmc-brand { color: var(--fmc-brand); font-weight: 700; font-size: 18px; letter-spacing: -.02em; text-decoration: none; }
.fmc-yo { margin-left: auto; position: relative; }
.fmc-yo > summary { list-style: none; display: flex; align-items: center; gap: 10px; cursor: pointer; border-radius: 12px; padding: 4px 6px; }
.fmc-yo > summary::-webkit-details-marker { display: none; }
.fmc-yo > summary:hover, .fmc-yo[open] > summary { background: var(--fmc-sup-2); }
.fmc-avatar { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 50%; background: var(--fmc-brand-cont); color: var(--fmc-brand); font-size: 13px; font-weight: 700; }
.fmc-yo-txt { display: flex; flex-direction: column; line-height: 1.25; }
.fmc-yo-txt strong { font-size: 14px; font-weight: 600; }
.fmc-yo-txt small { font-size: 12.5px; color: var(--fmc-text-2); }
.fmc-yo-menu { position: absolute; right: 0; top: calc(100% + 6px); min-width: 12rem; padding: 6px; background: #fff; border: 1px solid var(--fmc-line); border-radius: 12px; box-shadow: var(--fmc-e2); z-index: 1050; display: grid; }
.fmc-yo-menu a { padding: 10px 12px; border-radius: 8px; color: var(--fmc-text); text-decoration: none; font-size: 14px; }
.fmc-yo-menu a:hover { background: var(--fmc-sup-2); color: var(--fmc-brand); }

/* Pestañas */
.fmc-nav { background: #fff; border-bottom: 1px solid var(--fmc-line); }
.fmc-nav .nav { flex-wrap: nowrap; overflow-x: auto; gap: 4px; }
.fmc-nav .nav-link { display: flex; align-items: center; min-height: 48px; padding: 0 16px; font-size: 14.5px; color: var(--fmc-text-2); border-bottom: 3px solid transparent; white-space: nowrap; }
.fmc-nav .nav-link:hover { color: var(--fmc-brand); }
.fmc-nav .nav-link.active { color: var(--fmc-brand); font-weight: 700; border-bottom-color: var(--fmc-brand); }

/* Título de la pantalla */
.fmc-head { padding: 28px 0 4px; }
.fmc-h1 { font-size: 27px; font-weight: 700; letter-spacing: -.02em; margin: 0 0 4px; }
.fmc-h1 .badge { font-size: .75rem; vertical-align: middle; margin-left: .35rem; }
.fmc-sub { margin: 0; color: var(--fmc-text-2); font-size: 14.5px; }

/* Pie */
.fmc-foot { background: var(--fmc-foot-bg); color: #fff; font-size: 13px; padding: 18px 0; }
.fmc-foot a { color: #fff; }

/* Bootstrap con los colores del aplicativo: botones en píldora y tarjetas suaves */
.fmc .btn { border-radius: 999px; font-weight: 600; }
.fmc .btn-primary { --bs-btn-bg: var(--fmc-brand); --bs-btn-border-color: var(--fmc-brand); --bs-btn-hover-bg: #163f6f; --bs-btn-hover-border-color: #163f6f; --bs-btn-active-bg: #163f6f; --bs-btn-active-border-color: #163f6f; --bs-btn-disabled-bg: var(--fmc-brand); --bs-btn-disabled-border-color: var(--fmc-brand); }
.fmc .btn-outline-primary { --bs-btn-color: var(--fmc-brand); --bs-btn-border-color: var(--fmc-brand); --bs-btn-hover-bg: var(--fmc-brand); --bs-btn-hover-border-color: var(--fmc-brand); --bs-btn-active-bg: var(--fmc-brand); }
.fmc .card { border-color: var(--fmc-line); border-radius: var(--fmc-r); box-shadow: var(--fmc-e1); }
.fmc .form-control, .fmc .form-select { border-color: #d3d8de; }

@media (max-width: 640px) {
	.fmc-yo-txt { display: none; }
	.fmc-h1 { font-size: 23px; }
}

/* Contadores: cada uno filtra el listado */
.fmc-counters { display: grid; grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr)); gap: .75rem; }
.fmc-counter { display: block; background: #fff; border: 1px solid var(--fmc-line); border-radius: var(--fmc-r); box-shadow: var(--fmc-e1); padding: .85rem 1rem; color: inherit; text-decoration: none; }
.fmc-counter strong { display: block; font-size: 1.6rem; line-height: 1.1; }
.fmc-counter span { color: #6c757d; font-size: .875rem; }
a.fmc-counter:hover, .fmc-counter.is-active { border-color: var(--fmc-brand); box-shadow: inset 0 0 0 1px var(--fmc-brand); }

.fmc-filters { border-radius: var(--fmc-r); }
.fmc-table { border-radius: var(--fmc-r); overflow: hidden; }
.fmc-table th { font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; color: var(--fmc-text-2); background: var(--fmc-sup-2); white-space: nowrap; }
.fmc-table td { font-size: .9rem; }

/* Fichas de edición: una tarjeta por sección */
.fmc-section { border-radius: var(--fmc-r); padding-top: .25rem; }
.fmc-section-title { float: none; width: auto; margin: 0 0 -.75rem 1rem; padding: 0 .4rem; background: var(--fmc-soft); font-size: 1rem; font-weight: 700; position: relative; top: -.85rem; }
.fmc-checks.is-long { max-height: 14rem; overflow: auto; border: 1px solid var(--fmc-line); border-radius: .4rem; padding: .5rem .75rem; background: #fff; }
.fmc-savebar { position: sticky; bottom: 0; background: var(--fmc-soft); padding: .75rem 0; border-top: 1px solid var(--fmc-line); z-index: 2; }
.fmc-form :disabled { background-color: #f8f9fa; }

/* Leyenda y colores de la situación */
.fmc-legend { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; }
.fmc-chip { display: inline-flex; align-items: center; gap: .3rem; font-size: .78rem; padding: .15rem .55rem; border-radius: 1rem; background: #fff; border: 2px solid var(--fmc-line); }
.fmc-sit-managing { --sit: #adb5bd; }
.fmc-sit-running { --sit: #0d6efd; }
.fmc-sit-done { --sit: #198754; }
.fmc-sit-postponed { --sit: #e0a800; }
.fmc-sit-cancelled { --sit: #dc3545; }
.fmc-sit-none { --sit: #ced4da; }
.fmc-chip[class*="fmc-sit-"] { border-color: var(--sit); }

/* Calendario */
.fmc-cal { border-radius: var(--fmc-r); overflow: hidden; }
.fmc-cal-head, .fmc-cal-week { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)) repeat(2, minmax(0, .55fr)); }
.fmc-cal-head div { padding: .5rem; font-size: .78rem; font-weight: 600; text-transform: uppercase; color: #6c757d; border-bottom: 1px solid var(--fmc-line); background: #fff; }
.fmc-cal-day { min-height: 7rem; padding: .35rem; border-right: 1px solid var(--fmc-line); border-bottom: 1px solid var(--fmc-line); background: #fff; display: flex; flex-direction: column; gap: .3rem; min-width: 0; }
.fmc-cal-day.is-weekend { background: #fafbfc; }
.fmc-cal-day.is-out { background: var(--fmc-soft); }
.fmc-cal-day.is-today .fmc-cal-num { background: var(--fmc-brand); color: #fff; }
.fmc-cal-num { align-self: flex-end; font-size: .78rem; font-weight: 600; min-width: 1.5rem; text-align: center; border-radius: 1rem; }
.fmc-ev { position: relative; display: block; text-decoration: none; color: inherit; background: #fff; border: 1px solid var(--fmc-line); border-left: 4px solid var(--sit); border-radius: .35rem; padding: .25rem .4rem; font-size: .75rem; line-height: 1.25; }
.fmc-ev:hover { box-shadow: 0 1px 4px rgba(0, 0, 0, .15); }
.fmc-dot { display: inline-block; width: .55rem; height: .55rem; border-radius: 50%; margin-right: .3rem; }
.fmc-ev.fmc-sit-running { background: #eef5ff; }
.fmc-ev.fmc-sit-cancelled .fmc-ev-title { text-decoration: line-through; }
.fmc-ev-title { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-weight: 600; }
.fmc-ev-meta { display: block; color: #6c757d; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fmc-ribbon { float: right; margin-left: .2rem; font-size: .65rem; font-weight: 700; padding: 0 .3rem; border-radius: .3rem; background: #ffe69c; color: #664d03; }
.fmc-ribbon.is-warn { background: #f8d7da; color: #842029; }

@media (max-width: 767px) {
	.fmc-cal-head { display: none; }
	.fmc-cal-week { grid-template-columns: 1fr; }
	.fmc-cal-day.is-out, .fmc-cal-day:not(:has(.fmc-ev)) { display: none; }
	.fmc-cal-day { min-height: 0; }
	.fmc-cal-num { align-self: flex-start; }
}

/* Tom Select dentro de las fichas */
.fmc-form .ts-wrapper.multi .ts-control > .item { background: #e7f0fb; border: 1px solid #b6cff0; border-radius: .3rem; color: #1a4f8b; }
.fmc-form .ts-wrapper.disabled .ts-control { background-color: #f8f9fa; }

/* Panel lateral: el offcanvas de Bootstrap con la ficha dentro */
body.has-panel { overflow: hidden; }
.fmc-panel.offcanvas { --bs-offcanvas-width: min(880px, 100vw); background: var(--fmc-soft); }
.fmc-panel .offcanvas-header { background: #fff; }
.fmc-panel .offcanvas-body { padding: 1.25rem; }
.fmc-panel .offcanvas-body[aria-busy="true"] { opacity: .5; pointer-events: none; }
.fmc-panel .fmc-section { margin-bottom: 1rem !important; }
.fmc-panel .fmc-savebar { bottom: -1.25rem; margin: 0 -1.25rem -1.25rem; padding: .75rem 1.25rem; }
.fmc-panel .fmc-counters { grid-template-columns: repeat(auto-fit, minmax(7.5rem, 1fr)); }
.fmc-panel .fmc-counter strong { font-size: 1.25rem; }

/* Documentos del diseño */
.fmc-doc-kind + .fmc-doc-kind { border-top: 1px dashed var(--fmc-line); padding-top: .75rem; }
.fmc-docs .btn-check:checked + .btn-outline-danger { color: #fff; }
.fmc-docs .list-group-item:has(.btn-check:checked) a { text-decoration: line-through; opacity: .6; }

/* Calendario de lunes a viernes cuando el fin de semana está vacío */
.fmc-cal.no-weekend .fmc-cal-head, .fmc-cal.no-weekend .fmc-cal-week { grid-template-columns: repeat(5, minmax(0, 1fr)); }
.fmc-cal.no-weekend .is-weekend { display: none; }

/* Listados: miniatura del diseño y detalle bajo cada dato */
.fmc-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: .3rem; margin-right: .5rem; float: left; }
.fmc-col-title { min-width: 16rem; }
.fmc-table td .small { line-height: 1.25; }

/* Filtros: que quepan todos sin cortar el texto de los desplegables */
.fmc-filters .row > [class*="col-md"]:not(.col-md-2):not(.col-md-3) { flex: 1 0 9rem; max-width: 100%; }

/* Calendario por semana y por día: una columna por día, con lo que empieza */
.fmc-days { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); overflow: hidden; }
.fmc-days.is-one { grid-template-columns: 1fr; }
.fmc-days-col { padding: .5rem; border-right: 1px solid var(--fmc-line); min-height: 12rem; display: flex; flex-direction: column; gap: .4rem; min-width: 0; }
.fmc-days-col:last-child { border-right: 0; }
.fmc-days-col.is-today { background: var(--fmc-brand-cont); }
.fmc-days-h { font-size: .78rem; font-weight: 600; text-transform: uppercase; margin: 0 0 .25rem; }
.fmc-days-a { color: var(--fmc-text-2); text-decoration: none; }
.fmc-days-a:hover { color: var(--fmc-brand); }
.fmc-days.is-one .fmc-days-col { display: grid; grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr)); align-content: start; }
.fmc-days.is-one .fmc-days-h { grid-column: 1 / -1; }
.fmc-running { display: grid; grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr)); gap: .4rem; }
@media (max-width: 767px) {
	.fmc-days { grid-template-columns: 1fr; }
	.fmc-days-col { border-right: 0; border-bottom: 1px solid var(--fmc-line); min-height: 0; }
}

/* Incidencias de una acción: ámbar si alguna espera, verde si están resueltas */
.fmc-inc { font-weight: 600; }
.fmc-inc.is-pending { background: var(--fmc-wait-cont); color: var(--fmc-wait); }
.fmc-inc.is-done { background: var(--fmc-ok-cont); color: var(--fmc-ok); }
.fmc-ev .fmc-inc { display: inline-block; margin-top: .15rem; font-size: .65rem; }

/* Resumen del perfil de competencia digital */
.fmc-digcomp { background: var(--fmc-sup-2); box-shadow: none; }
.fmc-digcomp td { font-size: .85rem; }
',
  'js/fmc-app.js' => '/**
 * Formación: los dos comportamientos del lado del navegador.
 *
 * 1. Los `select[data-fmc-ts]` se convierten en Tom Select: buscador, y cada
 *    elegido como una etiqueta con su «×» cuando se pueden elegir varios.
 * 3. Los `a.fmc-abre-panel` abren su ficha en un panel lateral de Bootstrap
 *    (offcanvas): se pide la ficha sola (`fmc_marco=1`), se pinta dentro y se
 *    guarda desde ahí sin salir. Cerrarlo recarga la vista de debajo solo si
 *    se guardó algo. Sin guion, el enlace abre la ficha en su página.
 * 4. Los campos con `data-fmc-show` se ven solo si otro campo tiene uno de
 *    los valores de `data-fmc-show-values` (las horas según la modalidad, por
 *    ejemplo). El servidor aplica la misma regla al guardar.
 * 5. Al elegir un fichero, su nombre aparece al lado del botón.
 * 6. Los `textarea[data-fmc-rich]` llevan el TinyMCE que trae WordPress,
 *    con lo justo: negrita, cursiva y listas. Sin él, se quedan como cuadro
 *    de texto y el HTML se guarda igual.
 * 2. Los `form[data-fmc-confirm]` piden confirmación antes de enviarse, en
 *    tres escalones como en eventos: SweetAlert2 si ha cargado; si no, el
 *    `confirm()` del navegador; y sin JavaScript el formulario se envía tal
 *    cual, porque el servidor vuelve a comprobar el permiso y el nonce.
 */
( function () {
	\'use strict\';

	function initSelects( root ) {
		if ( ! window.TomSelect ) {
			return 0;
		}
		let n = 0;
		( root || document ).querySelectorAll( \'select[data-fmc-ts]\' ).forEach( function ( s ) {
			if ( s.tomselect ) {
				return;
			}
			// eslint-disable-next-line no-new
			new window.TomSelect( s, {
				maxOptions: null,
				allowEmptyOption: ! s.multiple,
				hidePlaceholder: true,
				placeholder: s.dataset.placeholder || \'\',
				plugins: s.multiple ? [ \'remove_button\' ] : [ \'dropdown_input\' ],
			} );
			n++;
		} );
		return n;
	}

	function valuesOf( form, name ) {
		const out = [];
		form.querySelectorAll( \'[name="\' + name + \'"], [name="\' + name + \'[]"]\' ).forEach( function ( input ) {
			if ( \'SELECT\' === input.tagName ) {
				Array.prototype.forEach.call( input.selectedOptions, function ( o ) {
					out.push( o.value );
				} );
			} else if ( \'checkbox\' === input.type || \'radio\' === input.type ) {
				if ( input.checked ) {
					out.push( input.value );
				}
			} else if ( \'hidden\' !== input.type ) {
				out.push( input.value );
			}
		} );
		return out;
	}

	function applyShow( root ) {
		( root || document ).querySelectorAll( \'[data-fmc-show]\' ).forEach( function ( el ) {
			const form = el.closest( \'form\' );
			if ( ! form ) {
				return;
			}
			const wanted = ( el.dataset.fmcShowValues || \'\' ).split( \',\' );
			const shown = valuesOf( form, el.dataset.fmcShow ).some( function ( v ) {
				return wanted.indexOf( v ) !== -1;
			} );
			el.classList.toggle( \'d-none\', ! shown );
		} );
	}

	function onChange( event ) {
		const target = event.target;
		if ( ! target || ! target.closest ) {
			return;
		}
		if ( target.classList && target.classList.contains( \'fmc-file\' ) ) {
			const label = document.querySelector( \'.fmc-file-name[data-for="\' + target.id + \'"]\' );
			if ( label ) {
				const names = Array.prototype.map.call( target.files || [], function ( f ) {
					return f.name;
				} );
				label.textContent = names.length ? \'Se subirá al guardar: \' + names.join( \', \' ) : \'\';
			}
		}
		const form = target.closest( \'form\' );
		if ( form ) {
			applyShow( form );
		}
	}

	function richInside( root ) {
		if ( ! window.tinymce || ! window.tinymce.editors ) {
			return [];
		}
		return Array.prototype.filter.call( window.tinymce.editors, function ( ed ) {
			return ed.targetElm && root.contains( ed.targetElm );
		} );
	}

	function removeRich( root ) {
		richInside( root || document ).forEach( function ( ed ) {
			ed.remove();
		} );
	}

	function saveRich() {
		if ( window.tinymce && window.tinymce.triggerSave ) {
			window.tinymce.triggerSave();
		}
	}

	function initRich( root ) {
		if ( ! window.tinymce || ! window.tinymce.init ) {
			return 0;
		}
		if ( window.fmcTinymceBase ) {
			window.tinymce.baseURL = window.fmcTinymceBase;
			window.tinymce.suffix = \'.min\';
		}
		let n = 0;
		( root || document ).querySelectorAll( \'textarea[data-fmc-rich]\' ).forEach( function ( area ) {
			if ( area.dataset.fmcRichOn ) {
				return;
			}
			area.dataset.fmcRichOn = \'1\';
			window.tinymce.init( {
				target: area,
				menubar: false,
				statusbar: false,
				branding: false,
				plugins: \'lists paste\',
				toolbar: \'bold italic | bullist numlist | undo redo\',
				height: 200,
				readonly: area.disabled ? 1 : 0,
				setup: function ( ed ) {
					ed.on( \'change\', function () {
						ed.save();
					} );
				},
			} );
			n++;
		} );
		return n;
	}

	function send( form ) {
		form.dataset.fmcConfirmed = \'1\';
		form.submit();
	}

	function onSubmit( event ) {
		const form = event.target;
		if ( ! form || ! form.matches || ! form.matches( \'form[data-fmc-confirm]\' ) || form.dataset.fmcConfirmed ) {
			return;
		}
		event.preventDefault();
		const question = form.dataset.fmcConfirm;
		if ( window.Swal && window.Swal.fire ) {
			return window.Swal.fire( {
				title: question,
				text: form.dataset.fmcConfirmText || \'\',
				icon: \'warning\',
				showCancelButton: true,
				focusCancel: true,
				confirmButtonText: form.dataset.fmcConfirmButton || \'Sí\',
				cancelButtonText: \'Cancelar\',
				confirmButtonColor: \'#dc3545\',
			} ).then( function ( result ) {
				if ( result && result.isConfirmed ) {
					send( form );
				}
			} );
		}
		if ( window.confirm( question ) ) {
			send( form );
		}
	}

	function reload() {
		window.location.reload();
	}

	function panelParts() {
		return {
			panel: document.querySelector( \'.fmc-panel\' ),
			backdrop: document.querySelector( \'.fmc-panel-backdrop\' ),
		};
	}

	function closePanel() {
		const p = panelParts();
		if ( ! p.panel ) {
			return false;
		}
		const saved = \'saved\' in p.panel.dataset;
		removeRich( p.panel );
		const reduced = window.matchMedia && window.matchMedia( \'(prefers-reduced-motion: reduce)\' ).matches;
		p.panel.classList.remove( \'show\' );
		if ( p.backdrop ) {
			p.backdrop.classList.remove( \'show\' );
		}
		window.setTimeout( function () {
			[ p.panel, p.backdrop ].forEach( function ( el ) {
				if ( el ) {
					el.remove();
				}
			} );
			document.body.classList.remove( \'has-panel\' );
			// Solo si se guardó algo: la vista de debajo tiene que enseñarlo.
			if ( saved ) {
				window.fmcApp.reload();
			}
		}, reduced ? 0 : 300 );
		return true;
	}

	function withFrame( url ) {
		const target = new URL( url, window.location.href );
		target.searchParams.set( \'fmc_marco\', \'1\' );
		return target;
	}

	// Pinta en el panel la ficha que devuelve el servidor, y la deja viva:
	// buscadores de Tom Select y enlaces que siguen dentro del panel.
	function fill( panel, html ) {
		const body = panel.querySelector( \'.offcanvas-body\' );
		removeRich( body );
		body.innerHTML = html;
		const fragment = body.querySelector( \'.fmc-fragment\' );
		if ( fragment && fragment.dataset.fmcTitle ) {
			panel.querySelector( \'.offcanvas-title\' ).textContent = fragment.dataset.fmcTitle;
		}
		if ( body.querySelector( \'.alert-success\' ) ) {
			panel.dataset.saved = \'1\';
		}
		initSelects( body );
		applyShow( body );
		initRich( body );
		const first = body.querySelector( \'.is-invalid, input:not([type=hidden]):not([disabled]), textarea:not([disabled]), select:not([disabled])\' );
		if ( first && first.focus ) {
			first.focus();
		}
	}

	function load( panel, request ) {
		panel.querySelector( \'.offcanvas-body\' ).setAttribute( \'aria-busy\', \'true\' );
		return window.fetch( request.url, { method: request.method || \'GET\', body: request.body, credentials: \'same-origin\' } )
			.then( function ( response ) {
				return response.text();
			} )
			.then( function ( html ) {
				panel.querySelector( \'.offcanvas-body\' ).removeAttribute( \'aria-busy\' );
				fill( panel, html );
			} )
			.catch( function () {
				// Sin respuesta, se abre la ficha en la página entera.
				window.location.assign( request.fallback );
			} );
	}

	function openPanel( url, title ) {
		const target = withFrame( url );
		if ( target.origin !== window.location.origin ) {
			return false;
		}
		let panel = panelParts().panel;
		if ( ! panel ) {
			const backdrop = document.createElement( \'div\' );
			backdrop.className = \'offcanvas-backdrop fade fmc-panel-backdrop\';
			backdrop.addEventListener( \'click\', closePanel );

			panel = document.createElement( \'div\' );
			panel.className = \'offcanvas offcanvas-end fmc-panel\';
			panel.setAttribute( \'role\', \'dialog\' );
			panel.setAttribute( \'aria-modal\', \'true\' );
			panel.setAttribute( \'aria-labelledby\', \'fmc-panel-title\' );
			panel.tabIndex = -1;
			panel.innerHTML = \'<div class="offcanvas-header border-bottom"><h2 class="offcanvas-title h5" id="fmc-panel-title"></h2><a class="small ms-auto me-3 fmc-panel-out"></a><button type="button" class="btn-close" aria-label="Cerrar"></button></div><div class="offcanvas-body"><div class="text-center text-secondary py-5"><div class="spinner-border" role="status"></div><div class="mt-2">Cargando…</div></div></div>\';
			panel.querySelector( \'.btn-close\' ).addEventListener( \'click\', closePanel );
			document.body.append( backdrop, panel );
			document.body.classList.add( \'has-panel\' );
			// Un fotograma para que Bootstrap anime la entrada.
			window.requestAnimationFrame( function () {
				backdrop.classList.add( \'show\' );
				panel.classList.add( \'showing\', \'show\' );
			} );
		}
		panel.querySelector( \'.offcanvas-title\' ).textContent = title;
		const out = panel.querySelector( \'.fmc-panel-out\' );
		out.href = url;
		out.textContent = \'Abrir en la página entera\';
		load( panel, { url: target.toString(), fallback: url } );
		return true;
	}

	function onClick( event ) {
		if ( event.metaKey || event.ctrlKey || event.shiftKey || event.button || ! event.target.closest ) {
			return;
		}
		const link = event.target.closest( \'a.fmc-abre-panel\' );
		if ( ! link ) {
			return;
		}
		const title = ( link.getAttribute( \'title\' ) || link.textContent || \'\' ).trim().replace( /^\\+\\s*/, \'\' );
		if ( openPanel( link.href, title ) ) {
			event.preventDefault();
		}
	}

	// Guardar dentro del panel: se envía sin salir de él y se pinta la respuesta.
	function onPanelSubmit( event ) {
		const form = event.target;
		const panel = form && form.closest ? form.closest( \'.fmc-panel\' ) : null;
		if ( ! panel || ! form.matches( \'form.fmc-form\' ) || event.defaultPrevented ) {
			return;
		}
		event.preventDefault();
		saveRich();
		const data = new window.FormData( form );
		if ( event.submitter && event.submitter.name ) {
			data.set( event.submitter.name, event.submitter.value );
		}
		load( panel, { url: form.action, method: \'POST\', body: data, fallback: window.location.href } );
	}

	function onKey( event ) {
		if ( \'Escape\' === event.key ) {
			closePanel();
		}
	}

	document.addEventListener( \'submit\', onSubmit );
	document.addEventListener( \'submit\', onPanelSubmit );
	document.addEventListener( \'click\', onClick );
	document.addEventListener( \'keydown\', onKey );
	document.addEventListener( \'change\', onChange );
	if ( \'loading\' === document.readyState ) {
		document.addEventListener( \'DOMContentLoaded\', function () {
			initSelects();
			applyShow();
			initRich();
		} );
	} else {
		initSelects();
		applyShow();
		initRich();
	}

	window.fmcApp = {
		initSelects: initSelects,
		onSubmit: onSubmit,
		onClick: onClick,
		onPanelSubmit: onPanelSubmit,
		onKey: onKey,
		onChange: onChange,
		applyShow: applyShow,
		initRich: initRich,
		removeRich: removeRich,
		openPanel: openPanel,
		closePanel: closePanel,
		reload: reload,
	};
} )();
',
) );

if ( \class_exists( \Fmc\App::class ) ) {
	\Fmc\App::boot();
}

// phpcs:enable

<?php
/**
 * Meta keys and closed lists of a training action.
 *
 * @package Fmc
 */

namespace Fmc\Meta;

/**
 * An action is one edition of a design: where, when, for how many, and how it went.
 *
 * La matrícula **no** vive aquí: la lleva el sistema de gestión del servicio
 * de formación. Esto es el registro de la acción (ADR-0005). Los totales
 * —horas, matriculados, asistentes, certificados— no se guardan: se suman.
 */
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
	public const VENUE_CODE       = 'fmc_venue_centre_code';
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

	/**
	 * The sessions, one by one: day, start and end (ADR-0027). «Días y horas»
	 * (`SCHEDULE`) queda para lo que llegó escrito a mano.
	 */
	public const SESSIONS         = 'fmc_sessions';
	public const CERTIFICATION    = 'fmc_certification';
	public const COURSE_SPACE_URL = 'fmc_course_space_url';
	public const NOTES            = 'fmc_notes';
	public const PROCESS          = 'fmc_process';
	public const SITUATION        = 'fmc_situation';
	public const ADVISER          = 'fmc_adviser';

	/**
	 * Name of the adviser when the action was saved, next to the account
	 * (ADR-0024): la cuenta puede borrarse y el histórico se queda.
	 */
	public const ADVISER_NAME    = 'fmc_adviser_name';
	public const SPEAKERS        = 'fmc_speakers';
	public const ENROLLED_MEN    = 'fmc_enrolled_men';
	public const ENROLLED_WOMEN  = 'fmc_enrolled_women';
	public const ATTENDED_MEN    = 'fmc_attended_men';
	public const ATTENDED_WOMEN  = 'fmc_attended_women';
	public const CERTIFIED_MEN   = 'fmc_certified_men';
	public const CERTIFIED_WOMEN = 'fmc_certified_women';
	public const DOCS_DELIVERED  = 'fmc_docs_delivered';
	public const COMPLETED       = 'fmc_completed';

	/**
	 * Where the action sits in the centres' own training plans. El itinerario
	 * formativo ya no existe: se quitó de la elección y lo guardado se queda.
	 */
	public const TRAINING_PLAN_KINDS = array(
		'plan'    => 'Incluida en su plan de formación',
		'seminar' => 'Incluida en un seminario o grupo de trabajo',
	);

	/**
	 * Who pays for the action.
	 */
	public const FUNDERS = array(
		'none'    => 'Sin coste',
		'centre'  => 'Centro de formación',
		'area'    => 'Área',
		'service' => 'Servicio de Perfeccionamiento',
	);

	/**
	 * The funder that charges the action to a programme: the active one that
	 * covers its design (ADR-0018).
	 */
	public const PROGRAMME_FUNDER = 'service';

	/**
	 * The funder that pays when the design is in no active programme.
	 */
	public const FALLBACK_FUNDER = 'centre';

	/**
	 * Who pays, with the names the deployment gives them.
	 *
	 * Las claves son del código; los nombres, de cada instalación, que los
	 * cambia con el filtro `fmc_funders` sin decir en el repositorio de quién es
	 * el despliegue (ADR-0002). Solo se cambia el nombre: una clave que no está
	 * en `FUNDERS` no entra.
	 *
	 * @return array<string, string>
	 */
	public static function funders(): array {
		$names = (array) apply_filters( 'fmc_funders', self::FUNDERS );
		$out   = array();
		foreach ( self::FUNDERS as $key => $label ) {
			$out[ $key ] = isset( $names[ $key ] ) && is_string( $names[ $key ] ) && '' !== trim( $names[ $key ] ) ? $names[ $key ] : $label;
		}
		return $out;
	}

	/**
	 * Administrative process, in order. Ya no se rellena: la acción se guarda
	 * en borrador o se entrega (ADR-0020). Queda para lo migrado.
	 */
	public const PROCESSES = array(
		'draft'     => 'En borrador',
		'paid'      => 'Pagada por el centro',
		'delivered' => 'Entregada al área',
		'closed'    => 'Cerrada y entregada al servicio',
	);

	/**
	 * What happened to the action. Each one is a colour in the calendar.
	 */
	public const SITUATIONS = array(
		'running'   => 'Impartiéndose',
		'done'      => 'Realizada',
		'postponed' => 'Aplazada',
		'cancelled' => 'Cancelada',
	);

	/**
	 * The states of an action's record: a draft, delivered, or completed.
	 *
	 * Borrador y entregada son el estado de la entrada (ADR-0020). Completada
	 * no se guarda: es la entregada a la que el servicio de perfeccionamiento
	 * ya le ha puesto su expediente ({@see delivery()}). Mientras no lo tiene,
	 * «Entregada» es lo que el servicio tiene pendiente.
	 */
	public const DELIVERY = array(
		'draft'     => 'Borrador',
		'publish'   => 'Entregada',
		'completed' => 'Completada',
	);

	/**
	 * Prefix of the internal file number, unless the deployment gives its own.
	 */
	public const INTERNAL_REF_PREFIX = 'AF';

	/**
	 * The state of an action's record: a key of `DELIVERY`.
	 *
	 * @param \WP_Post $action Action.
	 * @return string
	 */
	public static function delivery( \WP_Post $action ): string {
		if ( 'publish' !== $action->post_status ) {
			return $action->post_status;
		}
		return '' !== trim( (string) get_post_meta( $action->ID, self::FILE_NUMBER, true ) ) ? 'completed' : 'publish';
	}

	/**
	 * The file numbers of an action, one per line: the internal one and the
	 * training service's, whichever it has.
	 *
	 * @param int  $action Action ID.
	 * @param bool $short  Whether to name them briefly, as under a title.
	 * @return string Plain text; the lines are split by a line feed.
	 */
	public static function file_numbers( int $action, bool $short = false ): string {
		$lines = array();
		foreach ( array(
			self::INTERNAL_REF => $short ? self::internal_ref_label() : 'Expediente interno',
			self::FILE_NUMBER  => $short ? 'Perfeccionamiento' : 'Expediente de perfeccionamiento',
		) as $key => $label ) {
			$value = trim( (string) get_post_meta( $action, $key, true ) );
			if ( '' !== $value ) {
				$lines[] = $label . ': ' . $value;
			}
		}
		return implode( "\n", $lines );
	}

	/**
	 * The prefix of the internal file numbers: the deployment's, or the default.
	 *
	 * @return string Letters and digits only.
	 */
	public static function internal_ref_prefix(): string {
		return (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) apply_filters( 'fmc_internal_ref_prefix', self::INTERNAL_REF_PREFIX ) );
	}

	/**
	 * How the internal file number is named where there is little room.
	 *
	 * Con el prefijo de la casa —sus siglas—, si el despliegue lo ha puesto:
	 * es como la conoce quien lo lee, junto al del servicio de
	 * perfeccionamiento. Sin él, «Interno».
	 *
	 * @return string
	 */
	public static function internal_ref_label(): string {
		$prefix = self::internal_ref_prefix();
		return in_array( $prefix, array( '', self::INTERNAL_REF_PREFIX ), true ) ? 'Interno' : $prefix;
	}

	/**
	 * A new internal file number: who creates the action, and when.
	 *
	 * Es el que la asesoría da al servicio de perfeccionamiento para que
	 * localice la acción antes de que tenga su propio expediente. Lleva la
	 * forma que ya se conocía —prefijo, cuenta, fecha y hora—, y el prefijo lo
	 * pone cada despliegue con el filtro `fmc_internal_ref_prefix` (ADR-0002).
	 *
	 * @param int $user Who creates the action.
	 * @return string
	 */
	public static function internal_ref( int $user ): string {
		return self::internal_ref_prefix() . $user . '-' . current_time( 'd/m/Y' ) . '-' . current_time( 'H:i:s' );
	}

	/**
	 * The strategic lines an action may be put under, as the deployment names them.
	 *
	 * Son las de la norma de cada administración, y cambian con ella: las da
	 * el despliegue con el filtro `fmc_strategic_lines` (ADR-0002). Sin
	 * ninguna, el campo no sale. Lo guardado es el nombre, como estaba.
	 *
	 * @return array<string, string> name => name.
	 */
	public static function strategic_lines(): array {
		$out = array();
		foreach ( (array) apply_filters( 'fmc_strategic_lines', array() ) as $line ) {
			$line = is_string( $line ) ? sanitize_text_field( $line ) : '';
			if ( '' !== $line ) {
				$out[ $line ] = $line;
			}
		}
		return $out;
	}

	/**
	 * The situation of an action today, from its dates and its incidents.
	 *
	 * En la meta solo se guarda lo que deja una incidencia —aplazada o
	 * cancelada—; lo demás se calcula (`Domain\Situation`).
	 *
	 * @param int $action Action ID.
	 * @return string A key of `SITUATIONS`, or '' when it has not started.
	 */
	public static function situation( int $action ): string {
		return \Fmc\Domain\Situation::of(
			(string) get_post_meta( $action, self::START, true ),
			(string) get_post_meta( $action, self::END, true ),
			(string) get_post_meta( $action, self::SITUATION, true ),
			current_time( 'Y-m-d' )
		);
	}

	/**
	 * Every key with its value type.
	 *
	 * @return array<string, string>
	 */
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
			self::VENUE_CODE       => MetaTypes::STRING,
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
			self::SESSIONS         => MetaTypes::SESSIONS,
			self::CERTIFICATION    => MetaTypes::HTML,
			self::COURSE_SPACE_URL => MetaTypes::URL,
			self::NOTES            => MetaTypes::HTML,
			self::PROCESS          => MetaTypes::STRING,
			self::SITUATION        => MetaTypes::STRING,
			self::ADVISER          => MetaTypes::INT,
			self::ADVISER_NAME     => MetaTypes::STRING,
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

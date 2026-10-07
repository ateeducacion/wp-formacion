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

	/**
	 * Where the action sits in the centres' own training plans.
	 */
	public const TRAINING_PLAN_KINDS = array(
		'plan'      => 'Incluida en su plan de formación',
		'itinerary' => 'Incluida en un itinerario formativo',
		'seminar'   => 'Incluida en un seminario o grupo de trabajo',
	);

	/**
	 * Who pays for the action.
	 */
	public const FUNDERS = array(
		'none'    => 'Sin coste',
		'centre'  => 'Centro de formación',
		'area'    => 'Área',
		'service' => 'Servicio de formación',
	);

	/**
	 * Administrative process, in order.
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
		'managing'  => 'Gestionándose',
		'running'   => 'Impartiéndose',
		'done'      => 'Realizada',
		'postponed' => 'Aplazada',
		'cancelled' => 'Cancelada',
	);

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

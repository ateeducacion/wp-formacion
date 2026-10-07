<?php
/**
 * Meta keys and closed lists of a course design.
 *
 * @package Fmc
 */

namespace Fmc\Meta;

/**
 * A design is the reusable description of a training action (REQ-0001).
 *
 * La descripción va en `post_content`; el título, en `post_title`. El estado
 * del diseño **no es una meta**: es el `post_status` de WordPress —borrador,
 * pendiente de revisión, finalizado (`publish`)— (ADR-0004).
 */
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

	/**
	 * Training types. «Histórico» is not a type: it follows from the dates.
	 */
	public const TYPES = array(
		'course'       => 'Curso',
		'one_off'      => 'Acción puntual',
		'e_learning'   => 'Teleformación',
		'self_paced'   => 'Autodirigido',
		'in_classroom' => 'Intervención en aula',
	);

	/**
	 * Modalities.
	 */
	public const MODALITIES = array(
		'onsite'  => 'Presencial',
		'online'  => 'En línea',
		'blended' => 'Mixta',
	);

	/**
	 * Every key with its value type.
	 *
	 * @return array<string, string>
	 */
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
			// ponytail: los documentos son direcciones; subirlos desde la pantalla llega con su ADR.
			self::IMAGE         => MetaTypes::URL,
			self::DESIGN_FILE   => MetaTypes::URL,
			self::SESSIONS_FILE => MetaTypes::URL,
			self::SUPPORT_FILE  => MetaTypes::URL,
			self::IN_CATALOGUE  => MetaTypes::BOOL,
		);
	}
}

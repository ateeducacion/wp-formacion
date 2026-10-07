<?php
/**
 * Meta keys and closed lists of an incident.
 *
 * @package Fmc
 */

namespace Fmc\Meta;

/**
 * An incident is a change request on an action: it hangs from it (`post_parent`).
 *
 * Quien la abre no elige a qué acción: el padre lo fija el aplicativo y se
 * comprueba que quien la abre puede editar esa acción. Es justo el agujero del
 * sistema anterior, donde la acción llegaba por la URL (ADR-0007).
 */
final class IncidentMetaKeys {

	public const REASON           = 'fmc_reason';
	public const PROPOSAL         = 'fmc_proposal';
	public const HAS_COST         = 'fmc_has_cost';
	public const RESOLUTION       = 'fmc_resolution';
	public const RESOLUTION_NOTES = 'fmc_resolution_notes';

	/**
	 * Resolutions. Only the training service moves an incident out of `pending`.
	 */
	public const RESOLUTIONS = array(
		'pending'  => 'Pendiente',
		'approved' => 'Aprobada',
		'denied'   => 'Denegada',
	);

	/**
	 * Every key with its value type.
	 *
	 * @return array<string, string>
	 */
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

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
 * comprueba que quien la abre puede editar esa acción o es suya. Es justo el
 * agujero del sistema anterior, donde la acción llegaba por la URL (ADR-0007).
 * Una que pide aplazarla o cancelarla la deja así al enviarla (ADR-0020).
 */
final class IncidentMetaKeys {

	public const REASON           = 'fmc_reason';
	public const PROPOSAL         = 'fmc_proposal';
	public const HAS_COST         = 'fmc_has_cost';
	public const RESOLUTION       = 'fmc_resolution';
	public const RESOLUTION_NOTES = 'fmc_resolution_notes';
	public const REQUEST          = 'fmc_request';
	public const NEW_START        = 'fmc_new_start';
	public const NEW_END          = 'fmc_new_end';

	/**
	 * What an incident asks for. Aplazar o cancelar cambia la situación de la acción.
	 */
	public const REQUESTS = array(
		'change'   => 'Un cambio en la acción',
		'postpone' => 'Aplazarla',
		'cancel'   => 'Cancelarla',
	);

	/**
	 * The situation each request leaves the action in.
	 */
	public const SITUATION_OF = array(
		'postpone' => 'postponed',
		'cancel'   => 'cancelled',
	);

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
			self::REQUEST          => MetaTypes::STRING,
			self::NEW_START        => MetaTypes::DATE,
			self::NEW_END          => MetaTypes::DATE,
		);
	}
}

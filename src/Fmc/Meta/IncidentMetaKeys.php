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
 *
 * La incidencia deja constancia de lo que cambia y nadie la aprueba ni la
 * deniega (ADR-0029): la resolución, los cambios propuestos y el coste salieron
 * de la ficha. Sus claves siguen registradas para lo que ya estaba guardado.
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
	 * The dates the action had before an incident moved it: what it leaves on record.
	 */
	public const OLD_START = 'fmc_old_start';
	public const OLD_END   = 'fmc_old_end';

	/**
	 * What an incident is: the form, the lists and the mail name it the same.
	 *
	 * Un cambio de fechas mueve la acción, o la deja aplazada si aún no se
	 * saben; una cancelación la deja cancelada. Lo demás solo queda apuntado.
	 */
	public const KINDS = array(
		'postpone' => 'Cambio de fechas',
		'speaker'  => 'Cambio de ponente',
		'cancel'   => 'Cancelación',
		'change'   => 'Otro cambio',
	);

	/**
	 * Resolutions of the incidents that were resolved before ADR-0029. Ya no
	 * se resuelven: la lista queda para que lo guardado siga siendo válido.
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
			self::OLD_START        => MetaTypes::DATE,
			self::OLD_END          => MetaTypes::DATE,
		);
	}
}

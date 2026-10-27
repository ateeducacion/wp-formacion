<?php
/**
 * The situation of an action, from its dates and its incidents.
 *
 * @package Fmc
 */

namespace Fmc\Domain;

/**
 * La situación de una acción no se teclea: sale de sus fechas (ADR-0020).
 *
 * En blanco hasta que empieza; impartiéndose entre la fecha de inicio y la de
 * fin; realizada cuando pasa. Una incidencia que pide aplazarla o cancelarla
 * la deja aplazada o cancelada, y eso manda sobre las fechas. Sin fecha de
 * fin, la acción dura un día. Puro: la fecha de hoy entra como argumento.
 */
final class Situation {

	/**
	 * Situations an incident sets, which win over the dates.
	 */
	public const SET_BY_INCIDENT = array( 'postponed', 'cancelled' );

	/**
	 * The situation of an action.
	 *
	 * @param string $start  Y-m-d, or ''.
	 * @param string $end    Y-m-d, or ''.
	 * @param string $stored What an incident left: `postponed`, `cancelled` or anything else.
	 * @param string $today  Y-m-d.
	 * @return string `running`, `done`, `postponed`, `cancelled` or '' (not started, or no dates).
	 */
	public static function of( string $start, string $end, string $stored, string $today ): string {
		if ( in_array( $stored, self::SET_BY_INCIDENT, true ) ) {
			return $stored;
		}
		if ( ! self::is_date( $start ) || $today < $start ) {
			return '';
		}
		$last = self::is_date( $end ) && $end >= $start ? $end : $start;
		return $today <= $last ? 'running' : 'done';
	}

	/**
	 * Whether a value is a Y-m-d date.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	private static function is_date( string $value ): bool {
		return 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value );
	}
}

<?php
/**
 * The school year a date falls in.
 *
 * @package Fmc
 */

namespace Fmc\Domain;

/**
 * «2026-2027» for any date from 1 September 2026 to 31 August 2027.
 *
 * El curso escolar de una acción se deduce de su fecha de inicio; no se teclea
 * ni se guarda (ADR-0003). Pura: sin WordPress.
 */
final class SchoolYear {

	/**
	 * First month of the school year.
	 */
	public const FIRST_MONTH = 9;

	/**
	 * School year of a `Y-m-d` date, or an empty string if it is not one.
	 *
	 * @param string $date Date.
	 * @return string
	 */
	public static function of( string $date ): string {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-\d{2}$/', $date, $m ) ) {
			return '';
		}
		$start = (int) $m[2] >= self::FIRST_MONTH ? (int) $m[1] : (int) $m[1] - 1;
		return $start . '-' . ( $start + 1 );
	}
}

<?php
/**
 * The sessions of an action: day, start time and end time of each one.
 *
 * @package Fmc
 */

namespace Fmc\Domain;

/**
 * Las sesiones de una acción, en lugar de «Días y horas» escrito a mano
 * (issue #53, ADR-0027).
 *
 * Cada sesión es `array( 'day' => 'AAAA-MM-DD', 'from' => 'HH:MM', 'to' =>
 * 'HH:MM' )`, y la acción guarda la lista entera en una meta. Puro: no toca
 * WordPress, así que lo usan igual el guardado, el saneado de la meta, el
 * calendario y el listado.
 */
final class Sessions {

	/**
	 * More than this is not a training action: a pasted calendar gone wrong.
	 */
	public const MAX = 100;

	/**
	 * Read the submitted rows into a clean, sorted list.
	 *
	 * Una fila del todo vacía se ignora: es la que el formulario deja para
	 * añadir otra. Una a medias, o que acaba antes de empezar, es un error que
	 * se dice; no se adivina qué quería decir.
	 *
	 * @param mixed $raw Submitted rows, or a stored list.
	 * @return array{sessions: list<array{day:string, from:string, to:string}>, error:string}
	 */
	public static function read( $raw ): array {
		$out   = array();
		$error = '';
		foreach ( is_array( $raw ) ? $raw : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$day  = self::day( (string) ( $row['day'] ?? '' ) );
			$from = self::clock( (string) ( $row['from'] ?? '' ) );
			$to   = self::clock( (string) ( $row['to'] ?? '' ) );
			$any  = '' !== trim( (string) ( $row['day'] ?? '' ) . (string) ( $row['from'] ?? '' ) . (string) ( $row['to'] ?? '' ) );
			if ( ! $any ) {
				continue;
			}
			if ( '' === $day || '' === $from || '' === $to ) {
				$error = $error ? $error : 'Cada sesión necesita el día y las horas de inicio y de fin.';
				continue;
			}
			if ( $to <= $from ) {
				$error = $error ? $error : 'La sesión del ' . self::date_label( $day ) . ' acaba antes de empezar.';
				continue;
			}
			$out[ $day . ' ' . $from . ' ' . $to ] = array(
				'day'  => $day,
				'from' => $from,
				'to'   => $to,
			);
		}
		ksort( $out );
		if ( count( $out ) > self::MAX ) {
			$error = $error ? $error : 'Son más de ' . self::MAX . ' sesiones: revise la lista.';
		}
		return array(
			'sessions' => array_slice( array_values( $out ), 0, self::MAX ),
			'error'    => $error,
		);
	}

	/**
	 * A clean list, for the meta's sanitize callback: what is wrong is dropped.
	 *
	 * @param mixed $raw Rows.
	 * @return list<array{day:string, from:string, to:string}>
	 */
	public static function clean( $raw ): array {
		return self::read( $raw )['sessions'];
	}

	/**
	 * The first session that falls outside the action's dates, as an error.
	 *
	 * Sin fecha de fin, una sesión no puede ser anterior al inicio. Las fechas
	 * de la acción siguen mandando: de ellas salen el curso escolar y su sitio
	 * en el listado.
	 *
	 * @param list<array{day:string, from:string, to:string}> $sessions Sessions.
	 * @param string                                          $start    Y-m-d.
	 * @param string                                          $end      Y-m-d, or ''.
	 * @return string Error, or ''.
	 */
	public static function outside( array $sessions, string $start, string $end ): string {
		foreach ( $sessions as $s ) {
			if ( ( '' !== $start && $s['day'] < $start ) || ( '' !== $end && $s['day'] > $end ) ) {
				return 'La sesión del ' . self::date_label( $s['day'] ) . ' está fuera de las fechas de inicio y de fin de la acción.';
			}
		}
		return '';
	}

	/**
	 * Hours of all the sessions together.
	 *
	 * @param list<array{day:string, from:string, to:string}> $sessions Sessions.
	 * @return float
	 */
	public static function hours( array $sessions ): float {
		$minutes = 0;
		foreach ( $sessions as $s ) {
			$minutes += self::minutes( $s['to'] ) - self::minutes( $s['from'] );
		}
		return round( $minutes / 60, 2 );
	}

	/**
	 * «20/03/2027, de 16:00 a 20:00».
	 *
	 * @param array{day:string, from:string, to:string} $s Session.
	 * @return string
	 */
	public static function label( array $s ): string {
		return self::date_label( $s['day'] ) . ', de ' . $s['from'] . ' a ' . $s['to'];
	}

	/**
	 * «3 sesiones · 12 h», for a list cell.
	 *
	 * @param list<array{day:string, from:string, to:string}> $sessions Sessions.
	 * @return string
	 */
	public static function summary( array $sessions ): string {
		$n = count( $sessions );
		if ( 0 === $n ) {
			return '';
		}
		return $n . ( 1 === $n ? ' sesión' : ' sesiones' ) . ' · ' . ( self::hours( $sessions ) + 0 ) . ' h';
	}

	/**
	 * The sessions of each day.
	 *
	 * @param list<array{day:string, from:string, to:string}> $sessions Sessions.
	 * @return array<string, list<array{day:string, from:string, to:string}>> Y-m-d => sessions.
	 */
	public static function by_day( array $sessions ): array {
		$out = array();
		foreach ( $sessions as $s ) {
			$out[ $s['day'] ][] = $s;
		}
		return $out;
	}

	/**
	 * A valid `Y-m-d`, or ''.
	 *
	 * @param string $value Raw.
	 * @return string
	 */
	private static function day( string $value ): string {
		$value = trim( $value );
		return preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : '';
	}

	/**
	 * A valid `HH:MM`, or ''. Admite «9:30» y lo deja en «09:30».
	 *
	 * @param string $value Raw.
	 * @return string
	 */
	private static function clock( string $value ): string {
		if ( ! preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', trim( $value ), $m ) ) {
			return '';
		}
		return sprintf( '%02d:%s', (int) $m[1], $m[2] );
	}

	/**
	 * Minutes since midnight of a `HH:MM`.
	 *
	 * @param string $time HH:MM.
	 * @return int
	 */
	private static function minutes( string $time ): int {
		return (int) substr( $time, 0, 2 ) * 60 + (int) substr( $time, 3, 2 );
	}

	/**
	 * «20/03/2027».
	 *
	 * @param string $day Y-m-d.
	 * @return string
	 */
	private static function date_label( string $day ): string {
		return substr( $day, 8, 2 ) . '/' . substr( $day, 5, 2 ) . '/' . substr( $day, 0, 4 );
	}
}

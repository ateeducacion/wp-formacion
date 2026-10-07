<?php
/**
 * Value types of the application meta and how each one is sanitised.
 *
 * @package Fmc
 */

namespace Fmc\Meta;

/**
 * One closed list of value types, one sanitiser per type.
 *
 * Cada clase `*MetaKeys` declara su mapa `clave => tipo` y
 * {@see MetaRegistration} registra cada clave con el saneado de su tipo. Así el
 * saneado se escribe una vez y no uno por campo, que es como acaba el
 * formulario anterior: el mismo número tecleado de cinco formas distintas.
 */
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

	/**
	 * The `register_post_meta()` type of a value type.
	 *
	 * @param string $type One of the constants.
	 * @return string
	 */
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

	/**
	 * Sanitise a raw value for a type.
	 *
	 * Una fecha que no es `AAAA-MM-DD` real se queda vacía, no se «arregla»: una
	 * fecha inventada en un plazo de matrícula es peor que ninguna.
	 *
	 * @param string $type  One of the constants.
	 * @param mixed  $value Raw value.
	 * @return mixed
	 */
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

	/**
	 * A valid `Y-m-d` date, or an empty string.
	 *
	 * @param string $value Raw date.
	 * @return string
	 */
	private static function ymd( string $value ): string {
		$value = trim( $value );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return '';
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : '';
	}
}

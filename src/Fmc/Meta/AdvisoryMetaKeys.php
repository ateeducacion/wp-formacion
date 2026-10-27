<?php
/**
 * Meta keys and closed lists of an advisory.
 *
 * @package Fmc
 */

namespace Fmc\Meta;

/**
 * An advisory is an entry of the advisories directory, not a user (ADR-0019).
 *
 * La asesoría responsable de una acción se elige de este directorio: así se
 * filtra y se cuenta por asesoría aunque cambie quien la ocupa, y está también
 * la que no entra en el aplicativo. Si entra, su cuenta va en `USER`.
 */
final class AdvisoryMetaKeys {

	public const KIND = 'fmc_advisory_kind';
	public const USER = 'fmc_user';

	/**
	 * Kinds of advisory. Lista cerrada (ADR-0003): ampliarla es un cambio de código.
	 */
	public const KINDS = array(
		'ict'   => 'TIC',
		'steam' => 'STEAM',
		'other' => 'Otra',
	);

	/**
	 * Every key with its value type.
	 *
	 * @return array<string, string>
	 */
	public static function map(): array {
		return array(
			self::KIND => MetaTypes::STRING,
			self::USER => MetaTypes::INT,
		);
	}
}

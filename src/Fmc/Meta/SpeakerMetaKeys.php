<?php
/**
 * Meta keys of a speaker.
 *
 * @package Fmc
 */

namespace Fmc\Meta;

/**
 * A speaker is a person, so every key here is personal data.
 *
 * Por eso el tipo no es público ni sale en REST, y quién lo lee lo decide la
 * capacidad del tipo, no la pantalla (ADR-0006).
 */
final class SpeakerMetaKeys {

	public const FIRST_NAME  = 'fmc_first_name';
	public const LAST_NAME   = 'fmc_last_name';
	public const ID_NUMBER   = 'fmc_id_number';
	public const PHONE       = 'fmc_phone';
	public const PROFESSION  = 'fmc_profession';
	public const ZONE        = 'fmc_zone';
	public const EMAIL       = 'fmc_email';
	public const EMAIL_ALT   = 'fmc_email_alt';
	public const WEBSITE     = 'fmc_website';
	public const ADDRESS     = 'fmc_address';
	public const UNAVAILABLE = 'fmc_unavailable';
	public const NOTES       = 'fmc_notes';
	public const RECOMMENDED = 'fmc_recommended_designs';

	/**
	 * Every key with its value type.
	 *
	 * @return array<string, string>
	 */
	public static function map(): array {
		return array(
			self::FIRST_NAME  => MetaTypes::STRING,
			self::LAST_NAME   => MetaTypes::STRING,
			self::ID_NUMBER   => MetaTypes::STRING,
			self::PHONE       => MetaTypes::STRING,
			self::PROFESSION  => MetaTypes::STRING,
			self::ZONE        => MetaTypes::STRING,
			self::EMAIL       => MetaTypes::EMAIL,
			self::EMAIL_ALT   => MetaTypes::EMAIL,
			self::WEBSITE     => MetaTypes::URL,
			self::ADDRESS     => MetaTypes::TEXT,
			self::UNAVAILABLE => MetaTypes::BOOL,
			self::NOTES       => MetaTypes::TEXT,
			self::RECOMMENDED => MetaTypes::IDS,
		);
	}
}

<?php
/**
 * Register every meta key with its type, its sanitiser and who may write it.
 *
 * @package Fmc
 */

namespace Fmc\Meta;

use Fmc\PostType\PostTypes;

/**
 * One loop over the four key maps.
 *
 * Quién escribe cada clave lo decide el `auth_callback`, no la pantalla: el
 * sistema anterior escondía campos con CSS y cualquiera que mandase el campo a
 * mano lo guardaba. Aquí un campo que no te toca no se guarda aunque lo mandes
 * (ADR-0006).
 */
final class MetaRegistration {

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_meta' ), 12 );
	}

	/**
	 * Key maps by post type.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function maps(): array {
		return array(
			PostTypes::DESIGN   => DesignMetaKeys::map(),
			PostTypes::ACTION   => ActionMetaKeys::map(),
			PostTypes::SPEAKER  => SpeakerMetaKeys::map(),
			PostTypes::INCIDENT => IncidentMetaKeys::map(),
		);
	}

	/**
	 * Closed lists: a value outside its list is stored as empty.
	 *
	 * @return array<string, array<string, array<string, string>>>
	 */
	public static function enums(): array {
		return array(
			PostTypes::DESIGN   => array(
				DesignMetaKeys::TYPE     => DesignMetaKeys::TYPES,
				DesignMetaKeys::MODALITY => DesignMetaKeys::MODALITIES,
			),
			PostTypes::ACTION   => array(
				ActionMetaKeys::MODALITY       => DesignMetaKeys::MODALITIES,
				ActionMetaKeys::FUNDED_BY      => ActionMetaKeys::FUNDERS,
				ActionMetaKeys::PROCESS        => ActionMetaKeys::PROCESSES,
				ActionMetaKeys::SITUATION      => ActionMetaKeys::SITUATIONS,
				ActionMetaKeys::TRAINING_PLANS => ActionMetaKeys::TRAINING_PLAN_KINDS,
			),
			PostTypes::INCIDENT => array(
				IncidentMetaKeys::RESOLUTION => IncidentMetaKeys::RESOLUTIONS,
			),
		);
	}

	/**
	 * Keys that need a capability beyond editing the post.
	 *
	 * @return array<string, array<string, string>> type => key => capability.
	 */
	public static function guarded(): array {
		return array(
			PostTypes::ACTION   => array(
				ActionMetaKeys::FILE_NUMBER     => PostTypes::CAP_SERVICE_FIELDS,
				ActionMetaKeys::SITUATION       => PostTypes::CAP_SERVICE_FIELDS,
				ActionMetaKeys::SERVICE_OFFICER => PostTypes::CAP_SERVICE_FIELDS,
				ActionMetaKeys::SERVICE_MANAGER => PostTypes::CAP_SERVICE_FIELDS,
			),
			PostTypes::INCIDENT => array(
				IncidentMetaKeys::RESOLUTION       => PostTypes::CAP_RESOLVE,
				IncidentMetaKeys::RESOLUTION_NOTES => PostTypes::CAP_RESOLVE,
			),
		);
	}

	/**
	 * Register the meta.
	 *
	 * @return void
	 */
	public static function register_meta(): void {
		foreach ( self::maps() as $type => $map ) {
			foreach ( $map as $key => $value_type ) {
				register_post_meta(
					$type,
					$key,
					array(
						'type'              => MetaTypes::wp_type( $value_type ),
						'single'            => true,
						'show_in_rest'      => false,
						'sanitize_callback' => static function ( $value ) use ( $type, $key ) {
							return self::sanitize( $type, $key, $value );
						},
						'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) use ( $type ) {
							unset( $allowed );
							return self::can_write( $type, (string) $meta_key, (int) $post_id );
						},
					)
				);
			}
		}
	}

	/**
	 * Sanitise a value for a key, closed lists included.
	 *
	 * @param string $type  Post type.
	 * @param string $key   Meta key.
	 * @param mixed  $value Raw value.
	 * @return mixed
	 */
	public static function sanitize( string $type, string $key, $value ) {
		$value_type = self::maps()[ $type ][ $key ] ?? MetaTypes::STRING;
		$clean      = MetaTypes::sanitize( $value_type, $value );

		$list = self::enums()[ $type ][ $key ] ?? null;
		if ( null === $list ) {
			return $clean;
		}
		if ( is_array( $clean ) ) {
			return array_values( array_intersect( $clean, array_keys( $list ) ) );
		}
		return array_key_exists( (string) $clean, $list ) ? $clean : '';
	}

	/**
	 * Whether the current user may write a key of a post.
	 *
	 * @param string $type    Post type.
	 * @param string $key     Meta key.
	 * @param int    $post_id Post ID.
	 * @return bool
	 */
	public static function can_write( string $type, string $key, int $post_id ): bool {
		$cap = self::guarded()[ $type ][ $key ] ?? '';
		if ( '' !== $cap ) {
			return current_user_can( $cap );
		}
		return current_user_can( 'edit_post', $post_id );
	}
}

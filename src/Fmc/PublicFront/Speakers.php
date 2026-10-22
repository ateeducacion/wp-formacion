<?php
/**
 * What each speaker has given, and which designs a design replaces.
 *
 * @package Fmc
 */

namespace Fmc\PublicFront;

use Fmc\Meta\ActionMetaKeys as A;
use Fmc\Meta\DesignMetaKeys as D;
use Fmc\Meta\SpeakerMetaKeys as S;
use Fmc\PostType\PostTypes;

/**
 * Ponentes por lo que han impartido, no por categorías (ADR-0012).
 *
 * Las categorías se quedan viejas y repiten lo que ya dicen las acciones. Aquí
 * se cuenta, de cada ponente, en cuántas acciones ha estado y cuántas horas
 * suman, y se busca por palabras del título: quien busca «robótica» encuentra
 * a quien la ha dado, con sus horas, aunque nadie lo haya apuntado nunca.
 */
final class Speakers {

	/**
	 * Every published action with its speakers, read once per request.
	 *
	 * Una consulta para todas: son unos miles de filas, y hacerlo ponente a
	 * ponente serían cientos de consultas por pantalla. Se recuerda mientras
	 * no cambie ninguna entrada ni ninguna meta (`last_changed` de `posts`).
	 *
	 * @return list<array{text:string, file:string, start:string, hours:float, speakers:int[]}>
	 */
	private static function actions(): array {
		static $cache = array();
		$changed      = wp_cache_get_last_changed( 'posts' );
		if ( isset( $cache[ $changed ] ) ) {
			return $cache[ $changed ];
		}
		global $wpdb;
		$keys = array(
			'speakers'  => A::SPEAKERS,
			'subtitle'  => A::SUBTITLE,
			'file'      => A::FILE_NUMBER,
			'start'     => A::START,
			'onsite'    => A::HOURS_ONSITE,
			'online'    => A::HOURS_ONLINE,
			'replicas'  => A::REPLICAS,
			'situation' => A::SITUATION,
		);
		$join = '';
		foreach ( $keys as $alias => $key ) {
			$join .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} AS m_{$alias} ON ( m_{$alias}.post_id = p.ID AND m_{$alias}.meta_key = %s )", $key ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- alias fijos de esta lista.
		}
		$cols = implode( ', ', array_map( static fn( $alias ) => "m_{$alias}.meta_value AS {$alias}", array_keys( $keys ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- una lectura agregada sin API; las uniones van preparadas arriba.
		$found = $wpdb->get_results( $wpdb->prepare( "SELECT p.post_title AS title, {$cols} FROM {$wpdb->posts} p {$join} WHERE p.post_type = %s AND p.post_status = 'publish' AND m_speakers.meta_value IS NOT NULL", PostTypes::ACTION ), ARRAY_A );

		$rows = array();
		foreach ( (array) $found as $r ) {
			// Una acción cancelada no la impartió nadie.
			if ( 'cancelled' === $r['situation'] ) {
				continue;
			}
			$rows[] = array(
				'text'     => self::fold( $r['title'] . ' ' . $r['subtitle'] ),
				'file'     => (string) $r['file'],
				'start'    => (string) $r['start'],
				'hours'    => ( (float) $r['onsite'] + (float) $r['online'] ) * max( 1, (int) $r['replicas'] ),
				'speakers' => array_map( 'intval', (array) maybe_unserialize( $r['speakers'] ) ),
			);
		}
		$cache = array( $changed => $rows );
		return $rows;
	}

	/**
	 * Lower case and no accents, so «Robótica» finds «robotica».
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function fold( string $text ): string {
		return mb_strtolower( remove_accents( $text ) );
	}

	/**
	 * Actions and hours of each speaker, among the actions that match.
	 *
	 * @param string $given Words of the action title or subtitle; all must appear.
	 * @param string $file  Start of the file number.
	 * @param string $from  Y-m-d: actions starting that day or later.
	 * @return array<int, array{actions:int, hours:float}> speaker => totals.
	 */
	public static function stats( string $given = '', string $file = '', string $from = '' ): array {
		$words = array_filter( explode( ' ', self::fold( $given ) ), 'strlen' );
		$file  = self::fold( $file );
		$out   = array();
		foreach ( self::actions() as $row ) {
			if ( '' !== $file && 0 !== strpos( self::fold( $row['file'] ), $file ) ) {
				continue;
			}
			if ( '' !== $from && $row['start'] < $from ) {
				continue;
			}
			foreach ( $words as $word ) {
				if ( false === strpos( $row['text'], $word ) ) {
					continue 2;
				}
			}
			foreach ( array_unique( $row['speakers'] ) as $id ) {
				$out[ $id ]['actions'] = ( $out[ $id ]['actions'] ?? 0 ) + 1;
				$out[ $id ]['hours']   = ( $out[ $id ]['hours'] ?? 0.0 ) + $row['hours'];
			}
		}
		return $out;
	}

	/**
	 * A design and every design it replaces, down the chain.
	 *
	 * @param int $design Design ID.
	 * @return int[]
	 */
	public static function lineage( int $design ): array {
		$seen = array();
		$todo = array( $design );
		while ( $todo ) {
			$id = (int) array_shift( $todo );
			if ( $id <= 0 || isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$todo        = array_merge( $todo, array_map( 'intval', (array) get_post_meta( $id, D::REPLACES, true ) ) );
		}
		return array_keys( $seen );
	}

	/**
	 * Speakers recommended for a design or for one it replaces.
	 *
	 * @param int $design Design ID.
	 * @return int[]
	 */
	public static function recommended_for( int $design ): array {
		if ( $design <= 0 ) {
			return array();
		}
		return array_keys( array_filter( self::recommended_map(), static fn( $designs ) => in_array( $design, $designs, true ) ) );
	}

	/**
	 * Designs each speaker is recommended for, the designs that replace them included.
	 *
	 * Se lee en PHP y no con un `LIKE` sobre la lista serializada: en
	 * `a:2:{i:0;i:7;i:1;i:9;}` las posiciones (`i:0;`, `i:1;`) se escriben igual
	 * que los diseños, y un `LIKE 'i:1;'` encontraba el diseño 1 en cualquier
	 * ponente con dos recomendados. Son pocos ponentes con recomendados, y una
	 * lista corta de sustituciones.
	 *
	 * @return array<int, int[]> speaker => design IDs.
	 */
	public static function recommended_map(): array {
		// De cada diseño, los que lo sustituyen: la cadena de lineage(), al revés.
		$successors = array();
		foreach ( self::with_meta( PostTypes::DESIGN, D::REPLACES, 'any' ) as $id ) {
			foreach ( array_map( 'intval', (array) get_post_meta( $id, D::REPLACES, true ) ) as $old ) {
				$successors[ $old ][] = $id;
			}
		}
		$out = array();
		foreach ( self::with_meta( PostTypes::SPEAKER, S::RECOMMENDED, 'publish' ) as $speaker ) {
			$seen = array();
			$todo = array_map( 'intval', (array) get_post_meta( $speaker, S::RECOMMENDED, true ) );
			while ( $todo ) {
				$id = (int) array_shift( $todo );
				if ( $id <= 0 || isset( $seen[ $id ] ) ) {
					continue;
				}
				$seen[ $id ] = true;
				$todo        = array_merge( $todo, $successors[ $id ] ?? array() );
			}
			if ( $seen ) {
				$out[ $speaker ] = array_keys( $seen );
			}
		}
		return $out;
	}

	/**
	 * IDs of the posts of a type that have a meta key.
	 *
	 * @param string          $type   Post type.
	 * @param string          $key    Meta key.
	 * @param string|string[] $status Post status.
	 * @return int[]
	 */
	private static function with_meta( string $type, string $key, $status ): array {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => $type,
					'post_status'    => $status,
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- solo los que tienen el dato.
				)
			)
		);
	}
}

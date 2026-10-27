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
	 * @return list<array{id:int, title:string, subtitle:string, text:string, file:string, start:string, hours:float, speakers:int[]}>
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
			$join .= $wpdb->prepare( " LEFT JOIN $wpdb->postmeta AS m_$alias ON ( m_$alias.post_id = p.ID AND m_$alias.meta_key = %s )", $key ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- alias fijos de esta lista.
		}
		$cols = implode( ', ', array_map( static fn( $alias ) => "m_$alias.meta_value AS $alias", array_keys( $keys ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- una lectura agregada sin API; las uniones van preparadas arriba.
		$found = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID AS id, p.post_title AS title, $cols FROM $wpdb->posts p $join WHERE p.post_type = %s AND p.post_status = 'publish' AND m_speakers.meta_value IS NOT NULL", PostTypes::ACTION ), ARRAY_A );

		$rows = array();
		foreach ( (array) $found as $r ) {
			// Una acción cancelada no la impartió nadie.
			if ( 'cancelled' === $r['situation'] ) {
				continue;
			}
			$rows[] = array(
				'id'       => (int) $r['id'],
				'title'    => (string) $r['title'],
				'subtitle' => (string) $r['subtitle'],
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
		$out = array();
		foreach ( self::matching( $given, $file, $from ) as $row ) {
			foreach ( array_unique( $row['speakers'] ) as $id ) {
				$out[ $id ]['actions'] = ( $out[ $id ]['actions'] ?? 0 ) + 1;
				$out[ $id ]['hours']   = ( $out[ $id ]['hours'] ?? 0.0 ) + $row['hours'];
			}
		}
		return $out;
	}

	/**
	 * The actions a speaker has given among the ones that match, newest first.
	 *
	 * Son las que cuenta {@see stats()}, para enseñarlas una a una.
	 *
	 * @param int    $speaker Speaker ID.
	 * @param string $given   Words of the action title or subtitle; all must appear.
	 * @param string $file    Start of the file number.
	 * @param string $from    Y-m-d: actions starting that day or later.
	 * @return list<array{id:int, title:string, subtitle:string, start:string}>
	 */
	public static function given( int $speaker, string $given = '', string $file = '', string $from = '' ): array {
		$out = array();
		foreach ( self::matching( $given, $file, $from ) as $row ) {
			if ( in_array( $speaker, $row['speakers'], true ) ) {
				$out[] = array(
					'id'       => $row['id'],
					'title'    => $row['title'],
					'subtitle' => $row['subtitle'],
					'start'    => $row['start'],
				);
			}
		}
		usort(
			$out,
			static function ( $a, $b ) {
				$order = strcmp( $b['start'], $a['start'] );
				return 0 !== $order ? $order : $b['id'] <=> $a['id'];
			}
		);
		return $out;
	}

	/**
	 * The given actions that match the words, the file prefix and the date.
	 *
	 * @param string $given Words of the action title or subtitle; all must appear.
	 * @param string $file  Start of the file number.
	 * @param string $from  Y-m-d: actions starting that day or later.
	 * @return list<array{id:int, title:string, subtitle:string, text:string, file:string, start:string, hours:float, speakers:int[]}>
	 */
	private static function matching( string $given, string $file, string $from ): array {
		// Una vez por filtro y petición: el listado lo pide para cada fila.
		static $cache = array();
		$key          = wp_cache_get_last_changed( 'posts' ) . '|' . $given . '|' . $file . '|' . $from;
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}
		$words = self::words( $given );
		$file  = self::fold( $file );
		$out   = array();
		foreach ( self::actions() as $row ) {
			if ( '' !== $file && 0 !== strpos( self::fold( $row['file'] ), $file ) ) {
				continue;
			}
			if ( '' !== $from && $row['start'] < $from ) {
				continue;
			}
			if ( self::has_words( $row['text'], $words ) ) {
				$out[] = $row;
			}
		}
		$cache[ $key ] = $out;
		return $out;
	}

	/**
	 * Speakers recommended for a design whose title has every word.
	 *
	 * Cuenta la cadena de sustituciones, como {@see recommended_map()}: quien
	 * se recomendaba para un diseño ya sustituido lo es para el que lo sustituye.
	 * Solo los diseños que quien busca puede leer: si no, la búsqueda le diría
	 * qué palabras lleva el título de un borrador.
	 *
	 * @param string $given Words of the design title.
	 * @return int[]
	 */
	public static function recommended_matching( string $given ): array {
		$words = self::words( $given );
		if ( array() === $words ) {
			return array();
		}
		$map     = self::recommended_map();
		$designs = array_values( array_unique( array_merge( array(), ...array_values( $map ) ) ) );
		update_meta_cache( 'post', $designs );
		$titles = array();
		foreach ( $designs as $design ) {
			if ( current_user_can( 'read_post', $design ) ) {
				// El título tal cual, como el de las acciones: sin las comillas tipográficas de `get_the_title()`.
				$titles[ $design ] = self::fold( get_post_field( 'post_title', $design, 'raw' ) . ' ' . get_post_meta( $design, D::CODE, true ) );
			}
		}
		$out = array();
		foreach ( $map as $speaker => $chain ) {
			foreach ( array_intersect( $chain, array_keys( $titles ) ) as $design ) {
				if ( self::has_words( $titles[ $design ], $words ) ) {
					$out[] = (int) $speaker;
					break;
				}
			}
		}
		return $out;
	}

	/**
	 * The words of a search, folded.
	 *
	 * @param string $text Search.
	 * @return string[]
	 */
	private static function words( string $text ): array {
		return array_values( array_filter( explode( ' ', self::fold( $text ) ), 'strlen' ) );
	}

	/**
	 * Whether a folded text holds every word.
	 *
	 * @param string   $text  Folded text.
	 * @param string[] $words Folded words.
	 * @return bool
	 */
	private static function has_words( string $text, array $words ): bool {
		foreach ( $words as $word ) {
			if ( false === strpos( $text, $word ) ) {
				return false;
			}
		}
		return true;
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

<?php
/**
 * The list screens: counters, filters and a paged table, as in the events app.
 *
 * @package Fmc
 */

namespace Fmc\PublicFront;

use Fmc\Domain\SchoolYear;
use Fmc\Meta\ActionMetaKeys as A;
use Fmc\Meta\DesignMetaKeys as D;
use Fmc\Meta\IncidentMetaKeys as I;
use Fmc\Meta\SpeakerMetaKeys as S;
use Fmc\PostType\PostTypes;

/**
 * Listados de acciones, diseños, ponentes e incidencias.
 *
 * Qué filas ve cada cual lo decide la consulta, no la pantalla: quien no
 * puede editar lo ajeno solo ve lo publicado y lo suyo (`fmc_visible`).
 */
final class Lists {

	public const PER_PAGE = 50;

	/**
	 * What each list is for, under its title.
	 */
	public const SUBTITLES = array(
		PostTypes::ACTION   => 'Cada edición de un diseño: dónde, cuándo, para cuántos y cómo fue.',
		PostTypes::DESIGN   => 'El catálogo y la mesa de trabajo: los borradores se escriben aquí hasta que la validación los da por finalizados.',
		PostTypes::SPEAKER  => 'Datos personales: solo para quien gestiona acciones. Busque por lo que han impartido, con sus acciones y sus horas.',
		PostTypes::INCIDENT => 'Cambios pedidos sobre una acción. Se abren desde la propia acción.',
	);

	/**
	 * Bootstrap colour of each action situation.
	 */
	public const SITUATION_COLOURS = array(
		'managing'  => 'secondary',
		'running'   => 'primary',
		'done'      => 'success',
		'postponed' => 'warning',
		'cancelled' => 'danger',
	);

	/**
	 * Label and colour of each design status.
	 */
	public const DESIGN_STATUSES = array(
		'draft'   => array( 'Borrador', 'secondary' ),
		'pending' => array( 'En revisión', 'warning' ),
		'publish' => array( 'Finalizado', 'success' ),
	);

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'posts_where', array( self::class, 'visible_where' ), 10, 2 );
		add_filter( 'posts_where', array( self::class, 'text_where' ), 10, 2 );
		add_filter( 'posts_clauses', array( self::class, 'order_by_meta' ), 10, 2 );
	}

	/**
	 * `fmc_order_meta` query arg, `array( meta key, 'asc'|'desc' )`: by that
	 * meta, with the posts that lack it last either way.
	 *
	 * Ordenar con `meta_key` deja fuera lo que no tiene la meta, y con una
	 * cláusula «no existe» WordPress ordena por otra unión cualquiera. Una
	 * unión propia, de una fila por entrada, lo hace bien.
	 *
	 * @param array<string, string> $clauses SQL clauses.
	 * @param \WP_Query             $query   Query.
	 * @return array<string, string>
	 */
	public static function order_by_meta( $clauses, $query ) {
		$order = $query instanceof \WP_Query ? $query->get( 'fmc_order_meta' ) : null;
		if ( ! is_array( $order ) || 2 !== count( $order ) ) {
			return $clauses;
		}
		global $wpdb;
		$dir                = 'asc' === $order[1] ? 'ASC' : 'DESC';
		$clauses['join']   .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} AS fmc_sort ON ( fmc_sort.post_id = {$wpdb->posts}.ID AND fmc_sort.meta_key = %s )", (string) $order[0] );
		$clauses['orderby'] = "( fmc_sort.meta_value IS NULL OR fmc_sort.meta_value = '' ) ASC, fmc_sort.meta_value {$dir}, {$wpdb->posts}.ID {$dir}";
		return $clauses;
	}

	/**
	 * Columns that sort the list, per type: heading => sort key.
	 *
	 * Solo las que tienen un orden con sentido: el ámbito es una taxonomía y
	 * los ponentes de una acción son varios.
	 */
	public const SORTABLE = array(
		PostTypes::ACTION   => array(
			'Acción formativa' => 'title',
			'Fechas'           => 'start',
		),
		PostTypes::DESIGN   => array(
			'Diseño de curso' => 'title',
			'Código'          => 'code',
		),
		PostTypes::SPEAKER  => array(
			'Ponente'      => 'name',
			'Isla o zona'  => 'zone',
			'Ha impartido' => 'actions',
		),
		PostTypes::INCIDENT => array( 'Creada' => 'date' ),
	);

	/**
	 * Sort keys that start descending: the newest, or the most, first.
	 */
	private const DESCENDING = array( 'start', 'date', 'actions', 'hours' );

	/**
	 * The sort asked for (`fmc_sort`, `fmc_dir`), or the list's own.
	 *
	 * @param string $type Post type.
	 * @return array{0:string, 1:string} Sort key, and `asc` or `desc`.
	 */
	private static function sort_of( string $type ): array {
		$own  = array(
			PostTypes::ACTION   => 'start',
			PostTypes::DESIGN   => 'title',
			PostTypes::SPEAKER  => self::speaker_filtered() ? 'actions' : 'name',
			PostTypes::INCIDENT => 'date',
		);
		$keys = array_values( self::SORTABLE[ $type ] ?? array() );
		if ( PostTypes::SPEAKER === $type ) {
			// «Ordenar por» ofrece también las horas.
			$keys[] = 'hours';
		}
		$key = in_array( self::arg( 'fmc_sort' ), $keys, true ) ? self::arg( 'fmc_sort' ) : ( $own[ $type ] ?? 'title' );
		$dir = self::arg( 'fmc_dir' );
		if ( 'asc' !== $dir && 'desc' !== $dir ) {
			$dir = in_array( $key, self::DESCENDING, true ) ? 'desc' : 'asc';
		}
		return array( $key, $dir );
	}

	/**
	 * Query args with the sort of the list.
	 *
	 * @param string               $type Post type.
	 * @param array<string, mixed> $args Query args.
	 * @return array<string, mixed>
	 */
	private static function sorted( string $type, array $args ): array {
		list( $key, $dir ) = self::sort_of( $type );
		$metas             = array(
			'start' => A::START,
			'code'  => D::CODE,
		);
		if ( isset( $metas[ $key ] ) ) {
			$args['fmc_order_meta'] = array( $metas[ $key ], $dir );
		} elseif ( 'title' === $key || 'date' === $key ) {
			$args['orderby'] = $key;
			$args['order']   = strtoupper( $dir );
		}
		return $args;
	}

	/**
	 * `fmc_visible` query arg: published, or authored by the current user.
	 *
	 * @param string    $where SQL.
	 * @param \WP_Query $query Query.
	 * @return string
	 */
	public static function visible_where( $where, $query ) {
		if ( ! $query instanceof \WP_Query || ! $query->get( 'fmc_visible' ) ) {
			return $where;
		}
		global $wpdb;
		return $where . $wpdb->prepare( " AND ( {$wpdb->posts}.post_status = 'publish' OR {$wpdb->posts}.post_author = %d )", get_current_user_id() );
	}

	/**
	 * `fmc_text` query arg: the words in the title or in some meta keys.
	 *
	 * La búsqueda de WordPress solo mira el título y el texto; en las acciones
	 * hay que encontrar también por el expediente y por el subtítulo, que es lo
	 * que se teclea a diario (`fmc_text_keys`).
	 *
	 * @param string    $where SQL.
	 * @param \WP_Query $query Query.
	 * @return string
	 */
	public static function text_where( $where, $query ) {
		if ( ! $query instanceof \WP_Query || '' === (string) $query->get( 'fmc_text' ) ) {
			return $where;
		}
		global $wpdb;
		$like = '%' . $wpdb->esc_like( (string) $query->get( 'fmc_text' ) ) . '%';
		$keys = array_map( 'strval', (array) $query->get( 'fmc_text_keys' ) );
		$in   = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- los marcadores se cuentan arriba.
		return $where . $wpdb->prepare( " AND ( {$wpdb->posts}.post_title LIKE %s OR EXISTS ( SELECT 1 FROM {$wpdb->postmeta} fmc_t WHERE fmc_t.post_id = {$wpdb->posts}.ID AND fmc_t.meta_key IN ( {$in} ) AND fmc_t.meta_value LIKE %s ) )", array_merge( array( $like ), $keys, array( $like ) ) );
	}

	/**
	 * Incidents per action: how many and how many still pending.
	 *
	 * @return array<int, array{total:int, pending:int}> action => counts.
	 */
	private static function incidents(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- un recuento agrupado; no hay API.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT p.post_parent AS action, COUNT(*) AS total, SUM( COALESCE( r.meta_value, 'pending' ) = 'pending' ) AS pending FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} r ON ( r.post_id = p.ID AND r.meta_key = %s ) WHERE p.post_type = %s AND p.post_status = 'publish' AND p.post_parent > 0 GROUP BY p.post_parent", I::RESOLUTION, PostTypes::INCIDENT ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r['action'] ] = array(
				'total'   => (int) $r['total'],
				'pending' => (int) $r['pending'],
			);
		}
		return $out;
	}

	/**
	 * A filter value from the request.
	 *
	 * @param string $key Query var.
	 * @return string
	 */
	public static function arg( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtros de lectura.
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
	}

	/**
	 * Base query args for a type, with the filters of the request applied.
	 *
	 * @param string $type Post type.
	 * @param bool   $with_state Whether to apply the state filter (off for the counters).
	 * @return array<string, mixed>
	 */
	public static function query_args( string $type, bool $with_state = true ): array {
		$args = array(
			'post_type'      => $type,
			'post_status'    => array( 'publish', 'pending', 'draft' ),
			'posts_per_page' => self::PER_PAGE,
			'orderby'        => 'title',
			'order'          => 'ASC',
			's'              => self::arg( 'fmc_s' ),
			'meta_query'     => array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- filtros del listado.
			'tax_query'      => array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- filtros del listado.
		);
		if ( ! current_user_can( 'edit_others_' . $type . 's' ) ) {
			$args['fmc_visible'] = true;
		}

		$meta = static function ( string $key, string $value ) use ( &$args ): void {
			if ( '' !== $value ) {
				$args['meta_query'][] = array(
					'key'   => $key,
					'value' => $value,
				);
			}
		};
		$term = static function ( string $taxonomy, string $slug ) use ( &$args ): void {
			if ( '' !== $slug ) {
				$args['tax_query'][] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $slug,
				);
			}
		};

		switch ( $type ) {
			case PostTypes::ACTION:
				$args = self::action_search( $args );
				$term( 'fmc_scope', self::arg( 'fmc_scope' ) );
				$term( 'fmc_programme', self::arg( 'fmc_programme' ) );
				$meta( A::MODALITY, self::arg( 'fmc_modality' ) );
				$meta( A::PROCESS, self::arg( 'fmc_process' ) );
				// El tipo es del diseño: se filtra por los diseños de ese tipo.
				if ( isset( D::TYPES[ self::arg( 'fmc_type' ) ] ) ) {
					$designs              = get_posts(
						array(
							'post_type'      => PostTypes::DESIGN,
							'post_status'    => 'any',
							'posts_per_page' => -1,
							'fields'         => 'ids',
							'meta_key'       => D::TYPE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- filtro por tipo.
							'meta_value'     => self::arg( 'fmc_type' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- filtro por tipo.
						)
					);
					$args['meta_query'][] = array(
						'key'     => A::DESIGN_ID,
						'value'   => $designs ? $designs : array( 0 ),
						'compare' => 'IN',
					);
				}
				// Los ponentes se guardan como lista serializada de enteros: `i:<ID>;`.
				if ( absint( self::arg( 'fmc_speaker' ) ) ) {
					$args['meta_query'][] = array(
						'key'     => A::SPEAKERS,
						'value'   => 'i:' . absint( self::arg( 'fmc_speaker' ) ) . ';',
						'compare' => 'LIKE',
					);
				}
				foreach ( array(
					'fmc_from' => '>=',
					'fmc_to'   => '<=',
				) as $arg => $compare ) {
					if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', self::arg( $arg ) ) ) {
						$args['meta_query'][] = array(
							'key'     => A::START,
							'value'   => self::arg( $arg ),
							'compare' => $compare,
						);
					}
				}
				if ( $with_state ) {
					$meta( A::SITUATION, self::arg( 'fmc_situation' ) );
				}
				$year = self::arg( 'fmc_year' );
				if ( preg_match( '/^(\d{4})-\d{4}$/', $year, $m ) ) {
					$args['meta_query'][] = array(
						'key'     => A::START,
						'value'   => array( $m[1] . '-09-01', ( (int) $m[1] + 1 ) . '-08-31' ),
						'compare' => 'BETWEEN',
					);
				}
				break;
			case PostTypes::DESIGN:
				$term( 'fmc_programme', self::arg( 'fmc_programme' ) );
				$term( 'fmc_topic', self::arg( 'fmc_topic' ) );
				$meta( D::TYPE, self::arg( 'fmc_type' ) );
				$meta( D::MODALITY, self::arg( 'fmc_modality' ) );
				if ( '1' === self::arg( 'fmc_catalogue' ) ) {
					$meta( D::IN_CATALOGUE, '1' );
				}
				if ( $with_state && isset( self::DESIGN_STATUSES[ self::arg( 'fmc_status' ) ] ) ) {
					$args['post_status'] = self::arg( 'fmc_status' );
				}
				break;
			case PostTypes::SPEAKER:
				$args = self::speaker_args( $args, $with_state );
				break;
			case PostTypes::INCIDENT:
				if ( $with_state ) {
					$meta( I::RESOLUTION, self::arg( 'fmc_resolution' ) );
				}
				break;
		}
		return self::sorted( $type, $args );
	}

	/**
	 * Whether the speaker trash is asked for by someone who may see it.
	 *
	 * La papelera, con el motivo de cada baja, es de la validación: a quien no
	 * puede verla, el mismo enlace le enseña el listado de siempre.
	 *
	 * @return bool
	 */
	private static function speaker_trash(): bool {
		return 'trash' === self::arg( 'fmc_status' ) && current_user_can( 'edit_others_fmc_speakers' );
	}

	/**
	 * Speaker filters: zone, recommended, what they have given, availability and trash.
	 *
	 * @param array<string, mixed> $args       Query args.
	 * @param bool                 $with_state Whether to apply the state filters.
	 * @return array<string, mixed>
	 */
	private static function speaker_args( array $args, bool $with_state ): array {
		if ( '' !== self::arg( 'fmc_zone' ) ) {
			$args['meta_query'][] = array(
				'key'   => S::ZONE,
				'value' => self::arg( 'fmc_zone' ),
			);
		}
		// Recomendado para el diseño o para alguno de los que sustituye (ADR-0012).
		if ( absint( self::arg( 'fmc_recommended' ) ) ) {
			$args['meta_query'][] = Speakers::recommended_clause( absint( self::arg( 'fmc_recommended' ) ) );
		}
		$args = self::speaker_order( $args );
		if ( $with_state && self::speaker_trash() ) {
			$args['post_status'] = 'trash';
		}
		if ( $with_state && '' !== self::arg( 'fmc_available' ) ) {
			$args['meta_query'][] = '1' === self::arg( 'fmc_available' )
				? array(
					'key'     => S::UNAVAILABLE,
					'compare' => 'NOT EXISTS',
				)
				: array(
					'key'   => S::UNAVAILABLE,
					'value' => '1',
				);
		}
		return $args;
	}

	/**
	 * Action filters beyond the list ones: text, file number and incidents.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<string, mixed>
	 */
	private static function action_search( array $args ): array {
		// Se busca también por el expediente y el subtítulo, no solo por el título.
		$args['fmc_text']      = $args['s'];
		$args['fmc_text_keys'] = array( A::FILE_NUMBER, A::INTERNAL_REF, A::SUBTITLE );
		$args['s']             = '';
		// «Empieza por»: el prefijo del expediente dice con qué fondos se paga.
		if ( '' !== self::arg( 'fmc_file' ) ) {
			$args['meta_query'][] = array(
				'key'     => A::FILE_NUMBER,
				'value'   => '^' . preg_quote( self::arg( 'fmc_file' ), '' ),
				'compare' => 'REGEXP',
			);
		}
		if ( '' !== self::arg( 'fmc_incident' ) ) {
			$with             = array_filter( self::incidents(), static fn( $c ) => 'pending' !== self::arg( 'fmc_incident' ) || $c['pending'] > 0 );
			$args['post__in'] = $with ? array_keys( $with ) : array( 0 );
		}
		return $args;
	}

	/**
	 * Speakers by what they have given: filter and order by it.
	 *
	 * «Ha impartido», el expediente y la fecha dejan solo a quien ha estado en
	 * alguna de esas acciones; el orden puede ser por acciones, por horas o por
	 * isla. Sin nada de eso, por nombre, como siempre.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<string, mixed>
	 */
	private static function speaker_order( array $args ): array {
		list( $sort, $dir ) = self::sort_of( PostTypes::SPEAKER );
		$filtered           = self::speaker_filtered();
		if ( 'name' === $sort ) {
			$args['order'] = strtoupper( $dir );
			if ( ! $filtered ) {
				return $args;
			}
		}
		$stats = self::speaker_stats();
		$ids   = $filtered ? array_keys( $stats ) : get_posts(
			array(
				'post_type'      => PostTypes::SPEAKER,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$key   = static function ( int $id ) use ( $stats, $sort ) {
			if ( 'zone' === $sort ) {
				return (string) get_post_meta( $id, S::ZONE, true );
			}
			return - (float) ( $stats[ $id ][ 'hours' === $sort ? 'hours' : 'actions' ] ?? 0 );
		};
		// Con un filtro y sin orden elegido, primero quien más acciones ha dado.
		if ( 'name' !== $sort ) {
			usort(
				$ids,
				static function ( $a, $b ) use ( $key ) {
					$order = $key( (int) $a ) <=> $key( (int) $b );
					return 0 !== $order ? $order : $a <=> $b;
				}
			);
			// La clave ya ordena en el sentido de partida; el otro, al revés.
			if ( ( in_array( $sort, self::DESCENDING, true ) ? 'desc' : 'asc' ) !== $dir ) {
				$ids = array_reverse( $ids );
			}
			$args['orderby'] = 'post__in';
		}
		$args['post__in'] = $ids ? array_map( 'intval', $ids ) : array( 0 );
		return $args;
	}

	/**
	 * Whether the speakers are filtered by what they have given.
	 *
	 * @return bool
	 */
	private static function speaker_filtered(): bool {
		return '' !== self::arg( 'fmc_given' ) . self::arg( 'fmc_file' ) . self::arg( 'fmc_from' );
	}

	/**
	 * Stats of the speakers for the filters of the request.
	 *
	 * @return array<int, array{actions:int, hours:float}>
	 */
	private static function speaker_stats(): array {
		return Speakers::stats( self::arg( 'fmc_given' ), self::arg( 'fmc_file' ), self::arg( 'fmc_from' ) );
	}

	/**
	 * How many posts match a query.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int
	 */
	private static function total( array $args ): int {
		$args['posts_per_page'] = 1;
		$args['fields']         = 'ids';
		$q                      = new \WP_Query( $args );
		return (int) $q->found_posts;
	}

	/**
	 * Counters above the list: the total and one per state.
	 *
	 * @param string $type Post type.
	 * @return list<array{label:string, n:int, arg:string, value:string}>
	 */
	public static function counters( string $type ): array {
		$base = self::query_args( $type, false );
		$out  = array(
			array(
				'label' => 'En total',
				'n'     => self::total( $base ),
				'arg'   => '',
				'value' => '',
			),
		);
		$by   = array();
		switch ( $type ) {
			case PostTypes::ACTION:
				foreach ( A::SITUATIONS as $key => $label ) {
					$by[] = array(
						$label,
						'fmc_situation',
						$key,
						array(
							'meta_query' => array_merge( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- contadores del listado.
								$base['meta_query'],
								array(
									array(
										'key'   => A::SITUATION,
										'value' => $key,
									),
								)
							),
						),
					); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				}
				break;
			case PostTypes::DESIGN:
				foreach ( self::DESIGN_STATUSES as $key => $status ) {
					$by[] = array( $status[0], 'fmc_status', $key, array( 'post_status' => $key ) );
				}
				break;
			case PostTypes::SPEAKER:
				if ( current_user_can( 'edit_others_fmc_speakers' ) ) {
					$by[] = array( 'En la papelera', 'fmc_status', 'trash', array( 'post_status' => 'trash' ) );
				}
				break;
			case PostTypes::INCIDENT:
				foreach ( I::RESOLUTIONS as $key => $label ) {
					$by[] = array(
						$label,
						'fmc_resolution',
						$key,
						array(
							'meta_query' => array_merge( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- contadores del listado.
								$base['meta_query'],
								array(
									array(
										'key'   => I::RESOLUTION,
										'value' => $key,
									),
								)
							),
						),
					); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				}
				break;
		}
		foreach ( $by as list( $label, $arg, $value, $extra ) ) {
			$out[] = array(
				'label' => $label,
				'n'     => self::total( array_merge( $base, $extra ) ),
				'arg'   => $arg,
				'value' => $value,
			);
		}
		return $out;
	}

	/**
	 * The list screen of a tab.
	 *
	 * @param string $tab  Tab key.
	 * @param string $type Post type.
	 * @return array<string, string>
	 */
	public static function screen( string $tab, string $type ): array {
		$page = max( 1, (int) self::arg( 'fmc_page' ) );
		$args = self::query_args( $type ) + array( 'paged' => $page );
		$q    = new \WP_Query( $args );

		$object  = get_post_type_object( $type );
		$actions = current_user_can( $object->cap->create_posts ) && PostTypes::INCIDENT !== $type
			? '<a class="btn btn-primary fmc-abre-panel" href="' . esc_url( Screen::url( array( Screen::ARG_NEW => $type ) ) ) . '">+ Añadir ' . esc_html( mb_strtolower( $object->labels->singular_name ) ) . '</a>'
			: '';

		// Los ponentes no se exportan: un listado con correos y documentos de
		// identidad es demasiado fácil de sacar fuera (ADR-0011).
		if ( PostTypes::SPEAKER !== $type ) {
			$actions .= ' <a class="btn btn-outline-secondary" href="' . esc_url(
				Screen::url(
					array_merge(
						self::current_filters(),
						array(
							Screen::ARG_TAB => $tab,
							'fmc_csv'       => '1',
						)
					)
				)
			) . '">Exportar CSV</a>';
		}
		if ( PostTypes::DESIGN === $type ) {
			$actions .= ' <a class="btn btn-outline-secondary" href="' . esc_url( Catalogue::url() ) . '" target="_blank" rel="noopener">Ver el catálogo público</a>';
		}
		// La lista es una vista más de las acciones, junto al calendario.
		if ( PostTypes::ACTION === $type ) {
			$actions = Calendar::views_html( 'list' ) . ' ' . $actions;
		}

		// Los ponentes no tienen estados que contar: su total va en una línea.
		$body  = PostTypes::SPEAKER === $type ? '' : self::counters_html( $tab, self::counters( $type ) );
		$body .= self::filters_html( $tab, $type );
		$body .= PostTypes::SPEAKER === $type ? self::found_html( $tab, (int) $q->found_posts ) : '';
		$body .= self::table_html( $type, $q->posts );
		$body .= self::pager_html( $page, (int) $q->max_num_pages );

		return array(
			'tab'      => $tab,
			'title'    => $object->labels->name,
			'subtitle' => self::SUBTITLES[ $type ],
			'actions'  => $actions,
			'body'     => $body,
		);
	}

	/**
	 * How many speakers the filters find, and the way to the trash and back.
	 *
	 * @param string $tab   Tab.
	 * @param int    $found Speakers found.
	 * @return string
	 */
	private static function found_html( string $tab, int $found ): string {
		$trash = self::speaker_trash();
		$out   = '<p class="fmc-found">' . esc_html( sprintf( $trash ? '%s en la papelera' : '%s ponente(s)', number_format_i18n( $found ) ) );
		if ( current_user_can( 'edit_others_fmc_speakers' ) ) {
			$args = array_merge( self::current_filters(), array( Screen::ARG_TAB => $tab ) );
			// La papelera se abre con los filtros puestos, y se vuelve con ellos.
			$args['fmc_status'] = $trash ? '' : 'trash';
			$out               .= ' · <a href="' . esc_url( Screen::url( $args ) ) . '">' . ( $trash ? 'Volver a los ponentes' : 'Ver la papelera' ) . '</a>';
		}
		return $out . '</p>';
	}

	/**
	 * The counter cards; each one filters the list.
	 *
	 * @param string                          $tab      Tab.
	 * @param list<array<string, int|string>> $counters Counters.
	 * @return string
	 */
	private static function counters_html( string $tab, array $counters ): string {
		$out = '<div class="fmc-counters mb-3">';
		foreach ( $counters as $c ) {
			$args   = array_merge( self::current_filters(), array( Screen::ARG_TAB => $tab ) );
			$active = '' !== $c['arg'] ? self::arg( (string) $c['arg'] ) === $c['value'] : '' === self::arg( 'fmc_situation' ) . self::arg( 'fmc_status' ) . self::arg( 'fmc_resolution' );
			unset( $args['fmc_situation'], $args['fmc_status'], $args['fmc_resolution'], $args['fmc_page'] );
			if ( '' !== $c['arg'] ) {
				$args[ (string) $c['arg'] ] = $c['value'];
			}
			$out .= '<a class="fmc-counter' . ( $active ? ' is-active' : '' ) . '" href="' . esc_url( Screen::url( $args ) ) . '"><strong>' . esc_html( number_format_i18n( (int) $c['n'] ) ) . '</strong><span>' . esc_html( (string) $c['label'] ) . '</span></a>';
		}
		return $out . '</div>';
	}

	/**
	 * Filters currently applied, to keep them across links.
	 *
	 * @return array<string, string>
	 */
	private static function current_filters(): array {
		$out = array();
		foreach ( array( 'fmc_s', 'fmc_scope', 'fmc_programme', 'fmc_topic', 'fmc_modality', 'fmc_process', 'fmc_situation', 'fmc_year', 'fmc_type', 'fmc_status', 'fmc_zone', 'fmc_available', 'fmc_resolution', 'fmc_month', 'fmc_speaker', 'fmc_from', 'fmc_to', 'fmc_catalogue', 'fmc_recommended', 'fmc_file', 'fmc_incident', 'fmc_given', 'fmc_sort', 'fmc_dir', 'fmc_view' ) as $key ) {
			if ( '' !== self::arg( $key ) ) {
				$out[ $key ] = self::arg( $key );
			}
		}
		return $out;
	}

	/**
	 * Filter card of a type.
	 *
	 * @param string $tab  Tab.
	 * @param string $type Post type.
	 * @return string
	 */
	public static function filters_html( string $tab, string $type ): string {
		$placeholders = array(
			PostTypes::ACTION  => 'Título, subtítulo o expediente…',
			PostTypes::SPEAKER => 'Nombre…',
		);
		$fields       = array( self::search_field( $placeholders[ $type ] ?? 'Título…' ) );
		switch ( $type ) {
			case PostTypes::ACTION:
				$fields[] = self::text_field( 'fmc_file', 'Expediente empieza por', 'Prefijo' );
				$fields[] = self::select( 'fmc_scope', 'Ámbito', self::term_choices( 'fmc_scope' ), 'Todos' );
				$fields[] = self::select( 'fmc_year', 'Curso escolar', self::school_years(), 'Todos' );
				$fields[] = self::select( 'fmc_programme', 'Programa', self::term_choices( 'fmc_programme' ), 'Todos' );
				$fields[] = self::select( 'fmc_modality', 'Modalidad', D::MODALITIES, 'Todas' );
				$fields[] = self::select( 'fmc_type', 'Tipo', D::TYPES, 'Todos' );
				$fields[] = self::select( 'fmc_process', 'Estado', A::PROCESSES, 'Todos' );
				$fields[] = self::select( 'fmc_situation', 'Situación', A::SITUATIONS, 'Todas' );
				$fields[] = self::select( 'fmc_speaker', 'Ponente', self::post_choices( PostTypes::SPEAKER ), 'Todos', true );
				$fields[] = self::select(
					'fmc_incident',
					'Incidencias',
					array(
						'pending' => 'Con alguna pendiente',
						'any'     => 'Con alguna',
					),
					'Todas'
				);
				$fields[] = self::date_field( 'fmc_from', 'Empieza desde' );
				$fields[] = self::date_field( 'fmc_to', 'hasta' );
				break;
			case PostTypes::DESIGN:
				$fields[] = self::select( 'fmc_type', 'Tipo', D::TYPES, 'Todos' );
				$fields[] = self::select( 'fmc_modality', 'Modalidad', D::MODALITIES, 'Todas' );
				$fields[] = self::select( 'fmc_programme', 'Programa', self::term_choices( 'fmc_programme' ), 'Todos' );
				$fields[] = self::select( 'fmc_topic', 'Temática', self::term_choices( 'fmc_topic' ), 'Todas' );
				$fields[] = self::select( 'fmc_status', 'Estado', array_map( static fn( $s ) => $s[0], self::DESIGN_STATUSES ), 'Todos' );
				$fields[] = self::select( 'fmc_catalogue', 'Catálogo', array( '1' => 'En el catálogo público' ), 'Todos' );
				break;
			case PostTypes::SPEAKER:
				$fields[] = self::select( 'fmc_zone', 'Isla o zona', self::meta_choices( S::ZONE, PostTypes::SPEAKER ), 'Todas' );
				$fields[] = self::select(
					'fmc_available',
					'Disponibilidad',
					array(
						'1' => 'Disponibles',
						'0' => 'No disponibles',
					),
					'Todos'
				);
				$fields[] = self::select( 'fmc_recommended', 'Recomendado para', self::post_choices( PostTypes::DESIGN ), 'Cualquier diseño', true );
				// Lo que ha impartido cada cual, en vez de categorías de ponentes (ADR-0012).
				$fields[] = self::text_field( 'fmc_given', 'Ha impartido', 'Palabras del título' );
				$fields[] = self::text_field( 'fmc_file', 'Expediente empieza por', 'Prefijo' );
				$fields[] = self::date_field( 'fmc_from', 'Acciones desde' );
				$fields[] = self::select(
					'fmc_sort',
					'Ordenar por',
					array(
						'name'    => 'Nombre',
						'actions' => 'Acciones impartidas',
						'hours'   => 'Horas impartidas',
						'zone'    => 'Isla o zona',
					),
					'Lo filtrado'
				);
				break;
			case PostTypes::INCIDENT:
				$fields[] = self::select( 'fmc_resolution', 'Resolución', I::RESOLUTIONS, 'Todas' );
				break;
		}

		$out  = '<form class="card card-body mb-3 fmc-filters" method="get" action="' . esc_url( home_url( '/' ) ) . '">';
		$out .= '<input type="hidden" name="' . esc_attr( Screen::ARG_TAB ) . '" value="' . esc_attr( $tab ) . '">';
		// Filtrar no cambia de vista, ni de fecha, ni de orden; el de los
		// ponentes va en su propio «Ordenar por».
		foreach ( array_merge( array( 'fmc_view', 'fmc_month', 'fmc_day', 'fmc_dir' ), PostTypes::SPEAKER === $type ? array() : array( 'fmc_sort' ) ) as $key ) {
			if ( '' !== self::arg( $key ) ) {
				$out .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( self::arg( $key ) ) . '">';
			}
		}
		$out  .= '<div class="row g-2 align-items-end">' . implode( '', $fields );
		$reset = array(
			Screen::ARG_TAB => $tab,
			'fmc_view'      => self::arg( 'fmc_view' ),
		);
		$out  .= '<div class="col-auto"><button class="btn btn-outline-dark" type="submit">Filtrar</button> <a class="btn btn-link" href="' . esc_url( Screen::url( $reset ) ) . '">Quitar filtros</a></div>';
		return $out . '</div></form>';
	}

	/**
	 * The search box.
	 *
	 * @param string $placeholder What to type.
	 * @return string
	 */
	private static function search_field( string $placeholder ): string {
		return '<div class="col-12 col-md-3"><label class="form-label small fw-semibold" for="fmc_s">Buscar</label><input class="form-control" id="fmc_s" name="fmc_s" type="search" value="' . esc_attr( self::arg( 'fmc_s' ) ) . '" placeholder="' . esc_attr( $placeholder ) . '"></div>';
	}

	/**
	 * A free-text filter.
	 *
	 * @param string $name        Query var.
	 * @param string $label       Label.
	 * @param string $placeholder What to type.
	 * @return string
	 */
	private static function text_field( string $name, string $label, string $placeholder ): string {
		return '<div class="col-6 col-md-2"><label class="form-label small fw-semibold" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label><input class="form-control" type="text" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( self::arg( $name ) ) . '" placeholder="' . esc_attr( $placeholder ) . '"></div>';
	}

	/**
	 * A filter select.
	 *
	 * @param string                $name    Query var.
	 * @param string                $label   Label.
	 * @param array<string, string> $choices Value => label.
	 * @param string                $all     Label of the empty option.
	 * @param bool                  $search  Whether to add a search box (Tom Select).
	 * @return string
	 */
	public static function select( string $name, string $label, array $choices, string $all, bool $search = false ): string {
		// Las de buscador llevan títulos largos: media fila, para leerlos enteros.
		$wide = $search ? 'col-12 col-lg-6' : 'col-6 col-md';
		$out  = '<div class="' . $wide . '"><label class="form-label small fw-semibold" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label><select class="form-select" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"' . ( $search ? ' data-fmc-ts data-placeholder="' . esc_attr( $all ) . '"' : '' ) . '><option value="">' . esc_html( $all ) . '</option>';
		foreach ( $choices as $value => $text ) {
			$out .= '<option value="' . esc_attr( (string) $value ) . '"' . selected( self::arg( $name ), (string) $value, false ) . '>' . esc_html( $text ) . '</option>';
		}
		return $out . '</select></div>';
	}

	/**
	 * A date filter.
	 *
	 * @param string $name  Query var.
	 * @param string $label Label.
	 * @return string
	 */
	private static function date_field( string $name, string $label ): string {
		return '<div class="col-6 col-md-2"><label class="form-label small fw-semibold" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label><input class="form-control" type="date" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( self::arg( $name ) ) . '"></div>';
	}

	/**
	 * Posts of a type, ID => title, for a filter.
	 *
	 * @param string $type Post type.
	 * @return array<int, string>
	 */
	public static function post_choices( string $type ): array {
		$args = array(
			'post_type'      => $type,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);
		if ( PostTypes::DESIGN === $type ) {
			// Solo los del catálogo, que son los que se imparten (ADR-0012): los
			// históricos alargaban la lista hasta hacerla inmanejable.
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- solo los diseños del catálogo.
			$args['meta_query'] = array(
				array(
					'key'   => D::IN_CATALOGUE,
					'value' => '1',
				),
			);
		}
		$out = array();
		foreach ( get_posts( $args ) as $p ) {
			// El código delante: se busca por él y separa títulos parecidos.
			$code          = PostTypes::DESIGN === $type ? (string) get_post_meta( $p->ID, D::CODE, true ) : '';
			$out[ $p->ID ] = ( '' !== $code ? $code . ' · ' : '' ) . get_the_title( $p );
		}
		return $out;
	}

	/**
	 * Terms of a taxonomy, slug => name.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return array<string, string>
	 */
	public static function term_choices( string $taxonomy ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		$out   = array();
		foreach ( is_array( $terms ) ? $terms : array() as $t ) {
			$out[ $t->slug ] = $t->name;
		}
		return $out;
	}

	/**
	 * Distinct values of a meta key.
	 *
	 * @param string $key  Meta key.
	 * @param string $type Post type.
	 * @return array<string, string>
	 */
	private static function meta_choices( string $key, string $type ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- valores distintos para un desplegable; no hay API.
		$values = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = %s AND pm.meta_value <> '' ORDER BY pm.meta_value", $key, $type ) );
		return array_combine( $values, $values );
	}

	/**
	 * School years that have actions, newest first.
	 *
	 * @return array<string, string>
	 */
	public static function school_years(): array {
		$out  = array();
		$year = (int) gmdate( 'Y' ) + ( (int) gmdate( 'n' ) >= 9 ? 1 : 0 );
		for ( $y = $year; $y >= 2013; $y-- ) {
			$out[ ( $y - 1 ) . '-' . $y ] = ( $y - 1 ) . '-' . $y;
		}
		return $out;
	}

	/**
	 * The table.
	 *
	 * @param string     $type  Post type.
	 * @param \WP_Post[] $posts Posts.
	 * @return string
	 */
	public static function table_html( string $type, array $posts ): string {
		if ( array() === $posts ) {
			return '<div class="card card-body text-secondary">No hay nada que mostrar con estos filtros.</div>';
		}
		$columns = self::columns( $type );
		$out     = '<div class="card fmc-table"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr>';
		foreach ( array_merge( array( get_post_type_object( $type )->labels->singular_name ), array_keys( $columns ) ) as $h ) {
			$out .= self::th( $type, $h );
		}
		$out .= '<th scope="col" class="text-end"><span class="visually-hidden">Acciones</span></th></tr></thead><tbody>';
		foreach ( $posts as $post ) {
			$thumb = PostTypes::DESIGN === $type && has_post_thumbnail( $post ) ? get_the_post_thumbnail( $post, array( 48, 48 ), array( 'class' => 'fmc-thumb' ) ) : '';
			// En la papelera no hay ficha que abrir: se recupera desde el escritorio.
			$name = 'trash' === $post->post_status ? '<span class="fw-semibold">' . esc_html( self::title( $post ) ) . '</span>' : '<a class="fw-semibold fmc-abre-panel" href="' . esc_url( Screen::url( array( Screen::ARG_EDIT => $post->ID ) ) ) . '">' . esc_html( self::title( $post ) ) . '</a>';
			$file = PostTypes::ACTION === $type ? (string) get_post_meta( $post->ID, A::FILE_NUMBER, true ) : '';
			// El expediente va debajo del título: una columna menos, y el ámbito cabe.
			$link = $thumb . $name . self::badge( $post ) . self::subtitle( $post ) . ( '' !== $file ? '<div class="small text-secondary">' . esc_html( $file ) . '</div>' : '' );
			$out .= '<tr><td class="fmc-col-title">' . $link . '</td>';
			foreach ( $columns as $cell ) {
				$lines = explode( "\n", (string) $cell( $post ) );
				$out  .= '<td>' . esc_html( array_shift( $lines ) );
				foreach ( array_filter( $lines, 'strlen' ) as $line ) {
					$out .= '<div class="small text-secondary">' . esc_html( $line ) . '</div>';
				}
				$out .= '</td>';
			}
			$out .= '<td class="text-end text-nowrap">' . self::row_buttons( $post ) . '</td></tr>';
		}
		return $out . '</tbody></table></div></div>';
	}

	/**
	 * A column heading; the ones that sort are a link that flips the order.
	 *
	 * @param string $type    Post type.
	 * @param string $heading Heading.
	 * @return string
	 */
	private static function th( string $type, string $heading ): string {
		$key = self::SORTABLE[ $type ][ $heading ] ?? '';
		if ( '' === $key ) {
			return '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		list( $current, $dir ) = self::sort_of( $type );
		$active                = $key === $current;
		$start                 = in_array( $key, self::DESCENDING, true ) ? 'desc' : 'asc';
		$args                  = array(
			Screen::ARG_TAB => Screen::tab_of( $type ),
			'fmc_sort'      => $key,
			// Pulsar la columna por la que ya se ordena le da la vuelta.
			'fmc_dir'       => $active ? ( 'asc' === $dir ? 'desc' : 'asc' ) : $start,
		);
		$icon                  = $active ? ( 'asc' === $dir ? 'bi-sort-up' : 'bi-sort-down' ) : 'bi-arrow-down-up';
		return '<th scope="col"' . ( $active ? ' aria-sort="' . ( 'asc' === $dir ? 'ascending' : 'descending' ) . '"' : '' ) . '><a class="fmc-sort' . ( $active ? ' is-active' : '' ) . '" href="' . esc_url( Screen::url( array_merge( self::current_filters(), $args ) ) ) . '">' . esc_html( $heading ) . ' <i class="bi ' . $icon . '" aria-hidden="true"></i></a></th>';
	}

	/**
	 * Download the list as CSV, with the filters applied and every field.
	 *
	 * Es el «informe a la carta» del sistema anterior: se filtra en la
	 * pantalla y se descarga lo que se ve, con todas las columnas de la ficha.
	 * Separador `;` y BOM, que es lo que abre bien una hoja de cálculo en
	 * castellano.
	 *
	 * @param string $type Post type.
	 * @return void
	 */
	public static function csv( string $type ): void {
		// Los ponentes no se exportan (ADR-0011): ni por la ruta ni llamando aquí.
		if ( PostTypes::SPEAKER === $type ) {
			return;
		}
		$args                   = self::query_args( $type );
		$args['posts_per_page'] = 5000; // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- una exportación entera.
		$args['no_found_rows']  = true;
		$fields                 = array_filter( Fields::all( $type ), static fn( $f ) => 'docs' !== $f['w'] );

		// La acción no tiene el título en la ficha —es el del diseño—, pero la hoja sí lo lleva.
		$titled = ! isset( $fields['post_title'] );
		$head   = array_merge( array( 'ID', 'Estado de la ficha' ), $titled ? array( 'Título' ) : array() );
		foreach ( $fields as $f ) {
			$head[] = $f['label'];
		}
		if ( PostTypes::ACTION === $type ) {
			$head = array_merge( $head, array( 'Tipo de formación', 'Curso escolar', 'Horas totales' ) );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( 'formacion-' . Screen::tab_of( $type ) . '-' . gmdate( 'Y-m-d' ) . '.csv' ) . '"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- salida de la descarga.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- BOM para las hojas de cálculo.
		fputcsv( $out, $head, ';' );
		foreach ( get_posts( $args + array( 'suppress_filters' => false ) ) as $post ) {
			$row = array_merge( array( $post->ID, get_post_status_object( $post->post_status )->label ?? $post->post_status ), $titled ? array( get_the_title( $post ) ) : array() );
			foreach ( $fields as $key => $f ) {
				$row[] = self::plain( $post, $key, $f );
			}
			if ( PostTypes::ACTION === $type ) {
				$n     = static fn( string $k ): float => (float) get_post_meta( $post->ID, $k, true );
				$row[] = D::TYPES[ (string) get_post_meta( (int) get_post_meta( $post->ID, A::DESIGN_ID, true ), D::TYPE, true ) ] ?? '';
				$row[] = SchoolYear::of( (string) get_post_meta( $post->ID, A::START, true ) );
				$row[] = ( $n( A::HOURS_ONSITE ) + $n( A::HOURS_ONLINE ) ) * max( 1, (int) $n( A::REPLICAS ) );
			}
			fputcsv( $out, $row, ';' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- salida de la descarga.
	}

	/**
	 * A field of a post as plain text: labels instead of keys, names instead of IDs.
	 *
	 * @param \WP_Post             $post Post.
	 * @param string               $key  Field key.
	 * @param array<string, mixed> $f    Field definition.
	 * @return string
	 */
	public static function plain( \WP_Post $post, string $key, array $f ): string {
		$value = Editor::value( $post, $key );
		switch ( $f['w'] ) {
			case 'checkbox':
				return $value ? 'Sí' : 'No';
			case 'select':
			case 'radio':
				return (string) ( $f['choices'][ (string) $value ] ?? $value );
			case 'checks':
				return implode( ', ', array_map( static fn( $v ) => $f['choices'][ $v ] ?? $v, (array) $value ) );
			case 'term':
			case 'terms':
				return implode( ', ', array_map( static fn( $id ) => get_term( (int) $id )->name ?? '', (array) $value ) );
			case 'design':
			case 'designs':
			case 'speakers':
				return implode( ', ', array_map( 'get_the_title', array_filter( array_map( 'intval', (array) $value ) ) ) );
			case 'adviser':
				return $value ? (string) get_the_author_meta( 'display_name', (int) $value ) : '';
			case 'rich':
			case 'textarea':
				return trim( wp_strip_all_tags( (string) $value ) );
			default:
				return is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		}
	}

	/**
	 * The list columns of a type, after the title: heading => how to read it.
	 *
	 * Un salto de línea separa el dato de su detalle, que sale debajo, en
	 * pequeño. Son las columnas del sistema anterior que se usan a diario.
	 *
	 * @param string $type Post type.
	 * @return array<string, callable(\WP_Post): string>
	 */
	public static function columns( string $type ): array {
		$m = static fn( \WP_Post $p, string $key ): string => (string) get_post_meta( $p->ID, $key, true );
		switch ( $type ) {
			case PostTypes::ACTION:
				return array(
					'Ámbito y centro' => static fn( $p ) => self::terms( $p->ID, 'fmc_scope' ) . "\n" . $m( $p, A::VENUE_CENTRE ),
					'Ponentes'        => static fn( $p ) => implode( "\n", array_map( 'get_the_title', array_filter( array_map( 'intval', (array) get_post_meta( $p->ID, A::SPEAKERS, true ) ) ) ) ),
					'Tipo y horas'    => static fn( $p ) => ( D::TYPES[ (string) get_post_meta( (int) $m( $p, A::DESIGN_ID ), D::TYPE, true ) ] ?? '' ) . "\n" . trim( ( D::MODALITIES[ $m( $p, A::MODALITY ) ] ?? '' ) . ' · ' . self::hours( $m( $p, A::HOURS_ONSITE ), $m( $p, A::HOURS_ONLINE ) ), ' ·' ),
					'Fechas'          => static fn( $p ) => self::dates( $m( $p, A::START ), $m( $p, A::END ) ) . "\n" . wp_trim_words( $m( $p, A::SCHEDULE ), 12 ),
					'Estado'          => static fn( $p ) => ( A::PROCESSES[ $m( $p, A::PROCESS ) ] ?? '' ) . "\n" . SchoolYear::of( $m( $p, A::START ) ),
				);
			case PostTypes::DESIGN:
				return array(
					'Código'    => static fn( $p ) => $m( $p, D::CODE ) . ( $m( $p, D::IN_CATALOGUE ) ? "\nEn el catálogo" : '' ),
					'Tipo'      => static fn( $p ) => ( D::TYPES[ $m( $p, D::TYPE ) ] ?? '' ) . "\n" . ( D::MODALITIES[ $m( $p, D::MODALITY ) ] ?? '' ),
					'Horas'     => static fn( $p ) => self::hours( $m( $p, D::HOURS_ONSITE ), $m( $p, D::HOURS_ONLINE ) ),
					'Temática'  => static fn( $p ) => self::terms( $p->ID, 'fmc_topic' ),
					'Programas' => static fn( $p ) => self::terms( $p->ID, 'fmc_programme' ),
				);
			case PostTypes::SPEAKER:
				if ( self::speaker_trash() ) {
					return array(
						'Baja'   => static fn( $p ) => $m( $p, S::TRASHED_AT ) . "\n" . get_the_author_meta( 'display_name', (int) $m( $p, S::TRASHED_BY ) ),
						'Motivo' => static fn( $p ) => $m( $p, S::TRASH_REASON ),
					);
				}
				$stats = self::speaker_stats();
				return array(
					'Contacto'         => static fn( $p ) => $m( $p, S::EMAIL ) . "\n" . $m( $p, S::PHONE ),
					'Isla o zona'      => static fn( $p ) => $m( $p, S::ZONE ),
					'Ha impartido'     => static fn( $p ) => isset( $stats[ $p->ID ] ) ? sprintf( '%d acción(es)', $stats[ $p->ID ]['actions'] ) . "\n" . ( $stats[ $p->ID ]['hours'] + 0 ) . ' h' : '—',
					'Recomendado para' => static fn( $p ) => implode( "\n", array_map( 'get_the_title', array_slice( array_filter( array_map( 'intval', (array) get_post_meta( $p->ID, S::RECOMMENDED, true ) ) ), 0, 3 ) ) ),
				);
			default:
				return array(
					'Expediente' => static fn( $p ) => ( $p->post_parent ? (string) get_post_meta( $p->post_parent, A::FILE_NUMBER, true ) . "\n" . self::terms( $p->post_parent, 'fmc_scope' ) : '' ),
					'Creada'     => static fn( $p ) => get_the_date( 'd/m/Y', $p ) . "\n" . get_the_author_meta( 'display_name', (int) $p->post_author ),
					'Motivos'    => static fn( $p ) => wp_trim_words( $m( $p, I::REASON ), 18 ),
					'Coste'      => static fn( $p ) => $m( $p, I::HAS_COST ) ? 'Con coste' : 'Sin coste',
					'Resolución' => static fn( $p ) => ( I::RESOLUTIONS[ $m( $p, I::RESOLUTION ) ] ?? 'Pendiente' ) . "\n" . wp_trim_words( $m( $p, I::RESOLUTION_NOTES ), 10 ),
				);
		}
	}

	/**
	 * Edit and delete buttons of a row.
	 *
	 * Borrar es de administración y nada más, y es mandar a la papelera
	 * (ADR-0002). El botón es un formulario con su nonce: con JavaScript pide
	 * confirmación con SweetAlert2; sin él se envía y el servidor vuelve a
	 * comprobar el permiso.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public static function row_buttons( \WP_Post $post ): string {
		if ( 'trash' === $post->post_status ) {
			return '<a class="btn btn-sm btn-outline-secondary" href="' . esc_url( admin_url( 'edit.php?post_status=trash&post_type=' . $post->post_type ) ) . '">Recuperar en el escritorio</a>';
		}
		// Iconos, para que la fila no se coma la tabla; el nombre va en `title`
		// (al pasar el ratón) y en `aria-label` (lectores de pantalla).
		$edit  = current_user_can( 'edit_post', $post->ID );
		$label = ( $edit ? 'Editar' : 'Ver' ) . ' «' . self::title( $post ) . '»';
		$out   = '<a class="btn btn-sm btn-outline-primary fmc-icon fmc-abre-panel" title="' . esc_attr( $edit ? 'Editar' : 'Ver' ) . '" aria-label="' . esc_attr( $label ) . '" href="' . esc_url( Screen::url( array( Screen::ARG_EDIT => $post->ID ) ) ) . '"><i class="bi ' . ( $edit ? 'bi-pencil' : 'bi-eye' ) . '" aria-hidden="true"></i></a>';
		// Como en el sistema anterior, la incidencia se abre desde la fila de su acción.
		if ( PostTypes::ACTION === $post->post_type && $edit && current_user_can( 'edit_fmc_incidents' ) ) {
			$out .= ' <a class="btn btn-sm btn-outline-secondary fmc-icon fmc-abre-panel" title="Abrir una incidencia" aria-label="' . esc_attr( 'Abrir una incidencia sobre «' . self::title( $post ) . '»' ) . '" href="' . esc_url(
				Screen::url(
					array(
						Screen::ARG_NEW    => PostTypes::INCIDENT,
						Screen::ARG_PARENT => $post->ID,
					)
				)
			) . '"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></a>';
		}
		if ( PostTypes::SPEAKER !== $post->post_type && Screen::can_delete( $post ) ) {
			$out .= ' ' . Screen::delete_form( $post, 'btn-sm', true );
		}
		return $out;
	}

	/**
	 * Title of a post, with a fallback for incidents.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public static function title( \WP_Post $post ): string {
		$title = get_the_title( $post );
		return '' !== $title ? $title : '(sin título)';
	}

	/**
	 * State badge of a post.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public static function badge( \WP_Post $post ): string {
		if ( PostTypes::ACTION === $post->post_type ) {
			$s = (string) get_post_meta( $post->ID, A::SITUATION, true );
			return ( isset( A::SITUATIONS[ $s ] ) ? ' <span class="badge rounded-pill text-bg-' . esc_attr( self::SITUATION_COLOURS[ $s ] ) . '">' . esc_html( A::SITUATIONS[ $s ] ) . '</span>' : '' ) . self::incident_badge( $post->ID );
		}
		if ( PostTypes::DESIGN === $post->post_type && isset( self::DESIGN_STATUSES[ $post->post_status ] ) ) {
			list( $label, $colour ) = self::DESIGN_STATUSES[ $post->post_status ];
			return ' <span class="badge rounded-pill text-bg-' . esc_attr( $colour ) . '">' . esc_html( $label ) . '</span>';
		}
		if ( PostTypes::SPEAKER === $post->post_type && get_post_meta( $post->ID, S::UNAVAILABLE, true ) ) {
			return ' <span class="badge rounded-pill text-bg-secondary">No disponible</span>';
		}
		return '';
	}

	/**
	 * Whether an action has incidents, and whether any is still pending.
	 *
	 * De un vistazo, como se pidió: en ámbar las que esperan resolución, en
	 * verde cuando ya están todas resueltas.
	 *
	 * @param int $action Action ID.
	 * @return string
	 */
	public static function incident_badge( int $action ): string {
		static $cache = array();
		$changed      = wp_cache_get_last_changed( 'posts' );
		$cache        = isset( $cache[ $changed ] ) ? $cache : array( $changed => self::incidents() );
		$c            = $cache[ $changed ][ $action ] ?? null;
		if ( ! $c ) {
			return '';
		}
		return $c['pending'] > 0
			? ' <span class="badge rounded-pill fmc-inc is-pending">' . esc_html( sprintf( 'Incidencia pendiente (%d)', $c['pending'] ) ) . '</span>'
			: ' <span class="badge rounded-pill fmc-inc is-done">Incidencias resueltas</span>';
	}

	/**
	 * The subtitle of an action, under its title (ADR-0009).
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public static function subtitle( \WP_Post $post ): string {
		$sub = PostTypes::ACTION === $post->post_type ? (string) get_post_meta( $post->ID, A::SUBTITLE, true ) : '';
		return '' !== $sub ? '<div class="small text-secondary">' . esc_html( $sub ) . '</div>' : '';
	}

	/**
	 * Pagination.
	 *
	 * @param int $page  Current page.
	 * @param int $pages Pages.
	 * @return string
	 */
	private static function pager_html( int $page, int $pages ): string {
		if ( $pages < 2 ) {
			return '';
		}
		$link = static function ( int $p, string $text, bool $disabled = false ): string {
			$args = array_merge(
				self::current_filters(),
				array(
					Screen::ARG_TAB => self::arg( Screen::ARG_TAB ),
					'fmc_page'      => $p,
				)
			);
			return '<li class="page-item' . ( $disabled ? ' disabled' : '' ) . '"><a class="page-link" href="' . esc_url( Screen::url( $args ) ) . '">' . esc_html( $text ) . '</a></li>';
		};
		$out  = '<nav class="mt-3" aria-label="Páginas"><ul class="pagination justify-content-center">';
		$out .= $link( max( 1, $page - 1 ), '‹ Anterior', 1 === $page );
		$out .= '<li class="page-item disabled"><span class="page-link">' . esc_html( sprintf( 'Página %1$d de %2$d', $page, $pages ) ) . '</span></li>';
		$out .= $link( min( $pages, $page + 1 ), 'Siguiente ›', $page === $pages );
		return $out . '</ul></nav>';
	}

	/**
	 * «10/03/2026 – 24/03/2026».
	 *
	 * @param string $start Y-m-d.
	 * @param string $end   Y-m-d.
	 * @return string
	 */
	public static function dates( string $start, string $end ): string {
		$fmt = static function ( string $d ): string {
			return preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m ) ? $m[3] . '/' . $m[2] . '/' . $m[1] : '';
		};
		return trim( $fmt( $start ) . ( '' !== $end && $end !== $start ? ' – ' . $fmt( $end ) : '' ) );
	}

	/**
	 * «8 + 12 h» (on site + online), or the one there is.
	 *
	 * @param string $onsite On-site hours.
	 * @param string $online Online hours.
	 * @return string
	 */
	public static function hours( string $onsite, string $online ): string {
		$a = (float) $onsite;
		$b = (float) $online;
		if ( $a > 0 && $b > 0 ) {
			return ( $a + 0 ) . ' + ' . ( $b + 0 ) . ' h';
		}
		return ( $a + $b ) > 0 ? ( $a + $b + 0 ) . ' h' : '';
	}

	/**
	 * Comma-separated term names.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @return string
	 */
	public static function terms( int $post_id, string $taxonomy ): string {
		$names = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
		return is_array( $names ) ? implode( ', ', $names ) : '';
	}
}

<?php
/**
 * Month calendar of the training actions, with a colour code.
 *
 * @package Fmc
 */

namespace Fmc\PublicFront;

use Fmc\Meta\ActionMetaKeys as A;
use Fmc\PostType\PostTypes;

/**
 * El calendario que se usa a diario.
 *
 * Cada acción cae en su fecha de inicio. De un vistazo: el color es la
 * situación, el borde de puntos dice quién la paga y las cintas marcan el
 * desdoble y los datos incompletos. Menos texto que el calendario anterior,
 * que es lo que se pidió.
 */
final class Calendar {

	/**
	 * Colour of each funder, for the dotted border.
	 */
	public const FUNDER_COLOURS = array(
		'centre'  => '#d63384',
		'area'    => '#6f42c1',
		'service' => '#0d6efd',
		'none'    => '#adb5bd',
	);

	/**
	 * First day of the month being shown, Y-m-01.
	 *
	 * @return string
	 */
	public static function month(): string {
		$m = Lists::arg( 'fmc_month' );
		return preg_match( '/^(\d{4})-(0[1-9]|1[0-2])$/', $m ) ? $m . '-01' : gmdate( 'Y-m-01', (int) current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- día local.
	}

	/**
	 * Actions of the month, by start day.
	 *
	 * @param string $first Y-m-01.
	 * @return array<string, \WP_Post[]> Y-m-d => posts.
	 */
	public static function by_day( string $first ): array {
		$args                   = Lists::query_args( PostTypes::ACTION );
		$args['posts_per_page'] = 500; // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- un mes entero de acciones.
		$args['orderby']        = 'title';
		$args['order']          = 'ASC';
		unset( $args['fmc_order_start'] );
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- el mes del calendario.
		$args['meta_query']   = array_values(
			array_filter(
				$args['meta_query'],
				static fn( $c ) => ( $c['key'] ?? '' ) !== A::START
			)
		);
		$args['meta_query'][] = array(
			'key'     => A::START,
			'value'   => array( $first, gmdate( 'Y-m-t', (int) strtotime( $first ) ) ),
			'compare' => 'BETWEEN',
		);

		$out = array();
		// Sin `suppress_filters`, get_posts() se salta `fmc_visible` y la asesoría
		// vería en el calendario los borradores ajenos que el listado le esconde.
		foreach ( get_posts( $args + array( 'suppress_filters' => false ) ) as $post ) {
			$out[ (string) get_post_meta( $post->ID, A::START, true ) ][] = $post;
		}
		return $out;
	}

	/**
	 * Weeks of the month grid, Monday first; days outside the month are ''.
	 *
	 * @param string $first Y-m-01.
	 * @return list<list<string>>
	 */
	public static function weeks( string $first ): array {
		$ts    = (int) strtotime( $first );
		$days  = (int) gmdate( 't', $ts );
		$blank = (int) gmdate( 'N', $ts ) - 1;
		$cells = array_merge( array_fill( 0, $blank, '' ), array_map( static fn( $d ) => gmdate( 'Y-m-', $ts ) . sprintf( '%02d', $d ), range( 1, $days ) ) );
		$pad   = ( 7 - count( $cells ) % 7 ) % 7;
		$cells = array_merge( $cells, array_fill( 0, $pad, '' ) );
		return array_chunk( $cells, 7 );
	}

	/**
	 * The calendar screen.
	 *
	 * @return array<string, string>
	 */
	public static function screen(): array {
		$first  = self::month();
		$ts     = (int) strtotime( $first );
		$months = array( '', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre' );
		$title  = $months[ (int) gmdate( 'n', $ts ) ] . ' de ' . gmdate( 'Y', $ts );
		$nav    = static function ( string $month, string $text ): string {
			return '<a class="btn btn-outline-secondary" href="' . esc_url(
				Screen::url(
					array_merge(
						self::filters(),
						array(
							Screen::ARG_TAB => 'calendar',
							'fmc_month'     => $month,
						)
					)
				)
			) . '">' . esc_html( $text ) . '</a>';
		};

		$actions  = '<div class="btn-group">' . $nav( gmdate( 'Y-m', (int) strtotime( '-1 month', $ts ) ), '‹' ) . $nav( gmdate( 'Y-m' ), 'Hoy' ) . $nav( gmdate( 'Y-m', (int) strtotime( '+1 month', $ts ) ), '›' ) . '</div>';
		$actions .= current_user_can( 'edit_fmc_actions' ) ? ' <a class="btn btn-primary ms-2 fmc-abre-panel" href="' . esc_url( Screen::url( array( Screen::ARG_NEW => PostTypes::ACTION ) ) ) . '">+ Añadir acción</a>' : '';

		$by_day = self::by_day( $first );
		$body   = Lists::filters_html( 'calendar', PostTypes::ACTION ) . self::legend();
		// De lunes a viernes, como el calendario de siempre: el fin de semana solo
		// se enseña si ese mes hay alguna acción que caiga en él.
		$weekend = array() !== array_filter( array_keys( $by_day ), static fn( $d ) => (int) gmdate( 'N', (int) strtotime( $d ) ) > 5 );
		$body   .= '<div class="fmc-cal card' . ( $weekend ? '' : ' no-weekend' ) . '"><div class="fmc-cal-head">';
		foreach ( array( 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo' ) as $i => $d ) {
			$body .= '<div' . ( $i > 4 ? ' class="is-weekend"' : '' ) . '>' . esc_html( $d ) . '</div>';
		}
		$body .= '</div>';
		$today = gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- día local.
		foreach ( self::weeks( $first ) as $week ) {
			$body .= '<div class="fmc-cal-week">';
			foreach ( $week as $i => $day ) {
				$class = 'fmc-cal-day' . ( $i > 4 ? ' is-weekend' : '' ) . ( '' === $day ? ' is-out' : '' ) . ( $day === $today ? ' is-today' : '' );
				$body .= '<div class="' . esc_attr( $class ) . '">';
				if ( '' !== $day ) {
					$body .= '<div class="fmc-cal-num">' . esc_html( (string) (int) substr( $day, 8 ) ) . '</div>';
					foreach ( $by_day[ $day ] ?? array() as $post ) {
						$body .= self::card( $post );
					}
				}
				$body .= '</div>';
			}
			$body .= '</div>';
		}
		$body .= '</div>';

		return array(
			'tab'      => 'calendar',
			'title'    => 'Calendario de ' . $title,
			'subtitle' => sprintf( '%d acción(es) empiezan este mes.', array_sum( array_map( 'count', $by_day ) ) ),
			'actions'  => $actions,
			'body'     => $body,
		);
	}

	/**
	 * Filters applied, to keep them when moving between months.
	 *
	 * @return array<string, string>
	 */
	private static function filters(): array {
		$out = array();
		foreach ( array( 'fmc_s', 'fmc_scope', 'fmc_programme', 'fmc_modality', 'fmc_process', 'fmc_situation' ) as $key ) {
			if ( '' !== Lists::arg( $key ) ) {
				$out[ $key ] = Lists::arg( $key );
			}
		}
		return $out;
	}

	/**
	 * What the colours mean.
	 *
	 * @return string
	 */
	private static function legend(): string {
		$out = '<div class="fmc-legend mb-3"><strong class="small me-2">Leyenda:</strong>';
		foreach ( A::SITUATIONS as $key => $label ) {
			$out .= '<span class="fmc-chip fmc-sit-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</span>';
		}
		foreach ( A::FUNDERS as $key => $label ) {
			if ( 'none' === $key ) {
				continue;
			}
			$out .= '<span class="fmc-chip"><i class="fmc-dot" style="background:' . esc_attr( self::FUNDER_COLOURS[ $key ] ) . '"></i>Asume: ' . esc_html( mb_strtolower( $label ) ) . '</span>';
		}
		$out .= '<span class="fmc-chip"><span class="fmc-ribbon">×2</span> Desdoble</span><span class="fmc-chip"><span class="fmc-ribbon is-warn">!</span> Incompleta</span>';
		return $out . '</div>';
	}

	/**
	 * One action in its day.
	 *
	 * @param \WP_Post $post Action.
	 * @return string
	 */
	public static function card( \WP_Post $post ): string {
		$m          = static fn( string $key ): string => (string) get_post_meta( $post->ID, $key, true );
		$situation  = $m( A::SITUATION );
		$funded     = $m( A::FUNDED_BY );
		$replicas   = (int) $m( A::REPLICAS );
		$incomplete = ! $m( A::DESIGN_ID ) || ! $m( A::PLACES ) || ! $m( A::MODALITY );

		$dot = isset( self::FUNDER_COLOURS[ $funded ] ) && 'none' !== $funded ? '<i class="fmc-dot" style="background:' . esc_attr( self::FUNDER_COLOURS[ $funded ] ) . '" title="' . esc_attr( 'Asume: ' . A::FUNDERS[ $funded ] ) . '"></i>' : '';
		$out = '<a class="fmc-ev fmc-abre-panel fmc-sit-' . esc_attr( '' !== $situation ? $situation : 'none' ) . '" href="' . esc_url( Screen::url( array( Screen::ARG_EDIT => $post->ID ) ) ) . '" title="' . esc_attr( get_the_title( $post ) ) . '">';
		if ( $replicas > 1 ) {
			$out .= '<span class="fmc-ribbon">×' . esc_html( (string) $replicas ) . '</span>';
		}
		if ( $incomplete ) {
			$out .= '<span class="fmc-ribbon is-warn" title="Datos incompletos">!</span>';
		}
		$out  .= '<span class="fmc-ev-title">' . esc_html( get_the_title( $post ) ) . '</span>';
		$out  .= '<span class="fmc-ev-meta">' . esc_html( trim( $m( A::FILE_NUMBER ) . ' · ' . Lists::terms( $post->ID, 'fmc_scope' ), ' ·' ) ) . '</span>';
		$venue = $m( A::VENUE_CENTRE );
		if ( '' !== $venue && false === strpos( $venue, Lists::terms( $post->ID, 'fmc_scope' ) ) ) {
			$out .= '<span class="fmc-ev-meta">En: ' . esc_html( $venue ) . '</span>';
		}
		// Quién asume el coste y de qué programa: lo que el calendario anterior
		// escribía entero en cada acción, aquí en una línea.
		$programmes = Lists::terms( $post->ID, 'fmc_programme' );
		if ( '' !== $dot || '' !== $programmes ) {
			$out .= '<span class="fmc-ev-meta">' . $dot . esc_html( trim( ( A::FUNDERS[ $funded ] ?? '' ) . ( '' !== $programmes ? ' · ' . $programmes : '' ), ' ·' ) ) . '</span>';
		}
		return $out . '</a>';
	}
}

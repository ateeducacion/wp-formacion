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
 * Es lo primero que se ve de las acciones formativas: por mes, semana o día,
 * como el registro de visitas; su vista «Lista» es el listado de siempre
 * (`Lists`), con sus contadores y su paginación. Cada acción cae en su fecha
 * de inicio; en la semana y el día se ven aparte las que
 * empezaron antes y siguen en curso. De un vistazo: el color es la
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
	 * Month names, by number.
	 */
	public const MONTHS = array( '', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre' );

	/**
	 * Weekday names, Monday first.
	 */
	public const WEEKDAYS = array( 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo' );

	/**
	 * First day of the month being shown, Y-m-01.
	 *
	 * @return string
	 */
	public static function month(): string {
		$m = Lists::arg( 'fmc_month' );
		if ( preg_match( '/^(\d{4})-(0[1-9]|1[0-2])$/', $m ) ) {
			return $m . '-01';
		}
		// Desde la semana o el día, el mes de ese día.
		return substr( self::day(), 0, 8 ) . '01';
	}

	/**
	 * Today, Y-m-d, in the site's time zone.
	 *
	 * @return string
	 */
	public static function today(): string {
		return gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- día local.
	}

	/**
	 * Views of the calendar, as in the visits register: month, week, day, list.
	 */
	public const VIEWS = array(
		'month' => 'Mes',
		'week'  => 'Semana',
		'day'   => 'Día',
		'list'  => 'Lista',
	);

	/**
	 * The view asked for; the month when none.
	 *
	 * @return string
	 */
	public static function view(): string {
		$view = Lists::arg( 'fmc_view' );
		return isset( self::VIEWS[ $view ] ) ? $view : 'month';
	}

	/**
	 * The day the week and day views show, Y-m-d: `fmc_day`; from another
	 * month, its first day; else today.
	 *
	 * @return string
	 */
	public static function day(): string {
		$d = Lists::arg( 'fmc_day' );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) && strtotime( $d ) ) {
			return $d;
		}
		// Del mes a la semana: la de hoy si es este mes; si no, la primera del mes.
		$m = Lists::arg( 'fmc_month' );
		return preg_match( '/^(\d{4})-(0[1-9]|1[0-2])$/', $m ) && substr( self::today(), 0, 7 ) !== $m ? $m . '-01' : self::today();
	}

	/**
	 * Actions of the month, by start day.
	 *
	 * @param string $first Y-m-01.
	 * @return array<string, \WP_Post[]> Y-m-d => posts.
	 */
	public static function by_day( string $first ): array {
		return self::by_range( $first, gmdate( 'Y-m-t', (int) strtotime( $first ) ) );
	}

	/**
	 * Actions with the filters of the request, the dates replaced by a clause.
	 *
	 * @param array<string, mixed> $clause Meta clause on the dates.
	 * @return \WP_Post[]
	 */
	private static function actions( array $clause ): array {
		$args                   = Lists::query_args( PostTypes::ACTION );
		$args['posts_per_page'] = 500; // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- un mes entero de acciones.
		$args['orderby']        = 'title';
		$args['order']          = 'ASC';
		unset( $args['fmc_order_start'] );
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- las fechas del calendario.
		$args['meta_query']   = array_values(
			array_filter(
				$args['meta_query'],
				static fn( $c ) => ( $c['key'] ?? '' ) !== A::START
			)
		);
		$args['meta_query'][] = $clause;
		// Sin `suppress_filters`, get_posts() se salta `fmc_visible` y la asesoría
		// vería en el calendario los borradores ajenos que el listado le esconde.
		return get_posts( $args + array( 'suppress_filters' => false ) );
	}

	/**
	 * Actions starting between two days, by start day.
	 *
	 * @param string $from Y-m-d.
	 * @param string $to   Y-m-d.
	 * @return array<string, \WP_Post[]> Y-m-d => posts.
	 */
	public static function by_range( string $from, string $to ): array {
		$out = array();
		foreach ( self::actions(
			array(
				'key'     => A::START,
				'value'   => array( $from, $to ),
				'compare' => 'BETWEEN',
			)
		) as $post ) {
			$out[ (string) get_post_meta( $post->ID, A::START, true ) ][] = $post;
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Actions that started before a day and are still running on it.
	 *
	 * @param string $day Y-m-d.
	 * @return \WP_Post[]
	 */
	public static function ongoing( string $day ): array {
		return self::actions(
			array(
				'relation' => 'AND',
				array(
					'key'     => A::START,
					'value'   => $day,
					'compare' => '<',
				),
				array(
					'key'     => A::END,
					'value'   => $day,
					'compare' => '>=',
				),
			)
		);
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
	 * The calendar screen, in the view asked for.
	 *
	 * @return array<string, string>
	 */
	public static function screen(): array {
		$view   = self::view();
		$period = 'week' === $view || 'day' === $view ? self::days_screen( $view ) : self::month_screen();
		$object = get_post_type_object( PostTypes::ACTION );
		$add    = current_user_can( $object->cap->create_posts ) ? ' <a class="btn btn-primary fmc-abre-panel" href="' . esc_url( Screen::url( array( Screen::ARG_NEW => PostTypes::ACTION ) ) ) . '">+ Añadir acción formativa</a>' : '';
		// El CSV, de lo que empieza en el periodo que se ve, con los filtros.
		$csv = array(
			'fmc_csv'  => '1',
			'fmc_from' => $period['from'],
			'fmc_to'   => $period['to'],
		);
		// La cabecera es la de la lista, así que al cambiar de vista no se mueve
		// nada; el periodo, con sus flechas, va abajo, junto a lo que se ve.
		return array(
			'tab'      => 'actions',
			'title'    => $object->labels->name,
			'subtitle' => Lists::SUBTITLES[ PostTypes::ACTION ],
			'actions'  => self::views_html( $view ) . $add . ' ' . self::nav_link( $csv, 'Exportar CSV' ),
			'body'     => Lists::filters_html( 'actions', PostTypes::ACTION ) . self::period_html( $period ) . self::legend() . $period['body'],
		);
	}

	/**
	 * A link of the calendar that keeps the filters.
	 *
	 * @param array<string, string> $args Calendar args.
	 * @param string                $text Text.
	 * @param string                $css  Classes.
	 * @param array<string, string> $attr More attributes.
	 * @return string
	 */
	private static function nav_link( array $args, string $text, string $css = 'btn btn-outline-secondary', array $attr = array() ): string {
		$more = '';
		foreach ( $attr as $name => $value ) {
			$more .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}
		return '<a class="' . esc_attr( $css ) . '" href="' . esc_url( Screen::url( array_merge( self::filters(), array( Screen::ARG_TAB => 'actions' ), $args ) ) ) . '"' . $more . '>' . esc_html( $text ) . '</a>';
	}

	/**
	 * Buttons to switch view, on the same day or month; the list is `Lists`.
	 *
	 * @param string $current View shown.
	 * @return string
	 */
	public static function views_html( string $current ): string {
		$out = '<nav class="fmc-views" aria-label="Vista">';
		foreach ( self::VIEWS as $view => $label ) {
			$anchor = 'week' === $view || 'day' === $view ? array( 'fmc_day' => self::day() ) : array( 'fmc_month' => substr( self::month(), 0, 7 ) );
			$out   .= self::nav_link( array( 'fmc_view' => 'month' === $view ? '' : $view ) + $anchor, $label, 'fmc-view', $view === $current ? array( 'aria-current' => 'true' ) : array() );
		}
		return $out . '</nav>';
	}

	/**
	 * The period shown, its arrows and today; how many actions, on the right.
	 *
	 * Las flechas van delante del título para que no salten cuando el título
	 * cambia de largo.
	 *
	 * @param array<string, mixed> $period Period: title, count, and prev, next and today args.
	 * @return string
	 */
	private static function period_html( array $period ): string {
		return '<div class="fmc-period"><div class="fmc-period-nav" role="group" aria-label="Navegar por fechas">'
			. self::nav_link( $period['prev'], '‹', 'btn btn-light', array( 'title' => 'Periodo anterior' ) )
			. self::nav_link( $period['next'], '›', 'btn btn-light', array( 'title' => 'Periodo siguiente' ) )
			. self::nav_link( $period['today'], 'Hoy', 'fmc-period-hoy' )
			. '</div><h2 class="fmc-period-title">' . esc_html( $period['title'] ) . '</h2><span class="fmc-period-count">' . esc_html( $period['count'] ) . '</span></div>';
	}

	/**
	 * The month grid.
	 *
	 * @return array<string, mixed>
	 */
	private static function month_screen(): array {
		$first  = self::month();
		$ts     = (int) strtotime( $first );
		$by_day = self::by_day( $first );
		return array(
			'title' => ucfirst( self::MONTHS[ (int) gmdate( 'n', $ts ) ] ) . ' de ' . gmdate( 'Y', $ts ),
			'count' => sprintf( '%d acción(es) empiezan este mes.', array_sum( array_map( 'count', $by_day ) ) ),
			'prev'  => array( 'fmc_month' => gmdate( 'Y-m', (int) strtotime( '-1 month', $ts ) ) ),
			'next'  => array( 'fmc_month' => gmdate( 'Y-m', (int) strtotime( '+1 month', $ts ) ) ),
			'today' => array( 'fmc_month' => substr( self::today(), 0, 7 ) ),
			'from'  => $first,
			'to'    => gmdate( 'Y-m-t', $ts ),
			'body'  => self::grid( $first, $by_day ),
		);
	}

	/**
	 * The month grid, Monday first.
	 *
	 * @param string                    $first  Y-m-01.
	 * @param array<string, \WP_Post[]> $by_day Actions by start day.
	 * @return string
	 */
	private static function grid( string $first, array $by_day ): string {
		// De lunes a viernes, como el calendario de siempre: el fin de semana solo
		// se enseña si ese mes hay alguna acción que caiga en él.
		$weekend = array() !== array_filter( array_keys( $by_day ), static fn( $d ) => (int) gmdate( 'N', (int) strtotime( $d ) ) > 5 );
		$body    = '<div class="fmc-cal card' . ( $weekend ? '' : ' no-weekend' ) . '"><div class="fmc-cal-head">';
		foreach ( self::WEEKDAYS as $i => $d ) {
			$body .= '<div' . ( $i > 4 ? ' class="is-weekend"' : '' ) . '>' . esc_html( $d ) . '</div>';
		}
		$body .= '</div>';
		$today = self::today();
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
		return $body . '</div>';
	}

	/**
	 * One day of the week or day view, with the actions that start on it.
	 *
	 * @param string     $day     Y-m-d.
	 * @param \WP_Post[] $actions Actions starting that day.
	 * @param bool       $today   Whether it is today.
	 * @return string
	 */
	private static function day_html( string $day, array $actions, bool $today ): string {
		$weekday = self::WEEKDAYS[ (int) gmdate( 'N', (int) strtotime( $day ) ) - 1 ];
		$out     = '<section class="fmc-days-col' . ( $today ? ' is-today' : '' ) . '"><h2 class="fmc-days-h">' . self::nav_link(
			array(
				'fmc_view' => 'day',
				'fmc_day'  => $day,
			),
			$weekday . ' ' . (int) substr( $day, 8 ),
			'fmc-days-a'
		) . '</h2>';
		foreach ( $actions as $post ) {
			$out .= self::card( $post );
		}
		return $out . ( $actions ? '' : '<p class="small text-secondary mb-0">Ninguna empieza.</p>' ) . '</section>';
	}

	/**
	 * The actions that started before and are still running.
	 *
	 * @param \WP_Post[] $running Actions.
	 * @return string
	 */
	private static function running_html( array $running ): string {
		if ( ! $running ) {
			return '';
		}
		$out = '<h2 class="h6 mt-4">Empezaron antes y siguen en curso</h2><div class="fmc-running">';
		foreach ( $running as $post ) {
			$out .= self::card( $post );
		}
		return $out . '</div>';
	}

	/**
	 * The days a view shows: the day, or Monday to Sunday of its week.
	 *
	 * @param string $view `week` or `day`.
	 * @param string $day  Y-m-d.
	 * @return string[]
	 */
	public static function days_of( string $view, string $day ): array {
		if ( 'week' !== $view ) {
			return array( $day );
		}
		$monday = (int) strtotime( '-' . ( (int) gmdate( 'N', (int) strtotime( $day ) ) - 1 ) . ' days', (int) strtotime( $day ) );
		return array_map( static fn( $i ) => gmdate( 'Y-m-d', (int) strtotime( "+{$i} days", $monday ) ), range( 0, 6 ) );
	}

	/**
	 * A week or a day: what starts each day, and what is already running.
	 *
	 * @param string $view `week` or `day`.
	 * @return array<string, mixed>
	 */
	private static function days_screen( string $view ): array {
		$day   = self::day();
		$ts    = (int) strtotime( $day );
		$days  = self::days_of( $view, $day );
		$from  = $days[0];
		$to    = (string) end( $days );
		$step  = 'week' === $view ? '7 days' : '1 day';
		$by    = self::by_range( $from, $to );
		$today = self::today();

		$body = '<div class="fmc-days card' . ( 'day' === $view ? ' is-one' : '' ) . '">';
		foreach ( $days as $d ) {
			$body .= self::day_html( $d, $by[ $d ] ?? array(), $d === $today );
		}
		$body   .= '</div>';
		$running = self::ongoing( $from );
		$body   .= self::running_html( $running );

		$label = (int) substr( $from, 8 ) . ' de ' . self::MONTHS[ (int) gmdate( 'n', (int) strtotime( $from ) ) ] . ' de ' . gmdate( 'Y', (int) strtotime( $from ) );
		return array(
			'title' => 'week' === $view ? 'Semana del ' . $label : self::WEEKDAYS[ (int) gmdate( 'N', $ts ) - 1 ] . ' ' . $label,
			'count' => sprintf( '%1$d acción(es) empiezan; %2$d siguen en curso.', array_sum( array_map( 'count', $by ) ), count( $running ) ),
			'prev'  => array(
				'fmc_view' => $view,
				'fmc_day'  => gmdate( 'Y-m-d', (int) strtotime( '-' . $step, $ts ) ),
			),
			'next'  => array(
				'fmc_view' => $view,
				'fmc_day'  => gmdate( 'Y-m-d', (int) strtotime( '+' . $step, $ts ) ),
			),
			'today' => array(
				'fmc_view' => $view,
				'fmc_day'  => $today,
			),
			'from'  => $from,
			'to'    => $to,
			'body'  => $body,
		);
	}

	/**
	 * Filters applied, to keep them when moving between months.
	 *
	 * @return array<string, string>
	 */
	private static function filters(): array {
		$out = array();
		foreach ( array( 'fmc_s', 'fmc_scope', 'fmc_programme', 'fmc_modality', 'fmc_process', 'fmc_situation', 'fmc_file', 'fmc_incident', 'fmc_type', 'fmc_speaker' ) as $key ) {
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
		$out .= '<span class="fmc-ev-title">' . esc_html( get_the_title( $post ) ) . '</span>';
		$sub  = $m( A::SUBTITLE );
		if ( '' !== $sub ) {
			$out .= '<span class="fmc-ev-meta">' . esc_html( $sub ) . '</span>';
		}
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
		return $out . Lists::incident_badge( $post->ID ) . '</a>';
	}
}

<?php
/**
 * Tests for the month calendar: grid, weekend, colours and filters.
 *
 * @package Fmc
 */

use Fmc\Meta\ActionMetaKeys as A;
use Fmc\Meta\MetaRegistration;
use Fmc\PostType\PostTypes;
use Fmc\PublicFront\Calendar;
use Fmc\PublicFront\Lists;

/**
 * Cada acción cae en su día de inicio, con el color de su situación.
 */
class Test_Calendar extends WP_UnitTestCase {

	/**
	 * Roles, caps and meta in place.
	 */
	public function set_up() {
		parent::set_up();
		fmc_register_roles();
		PostTypes::grant_caps_to_roles();
		MetaRegistration::register_meta();
	}

	/**
	 * Leave the request clean for the next test.
	 */
	public function tear_down() {
		$_GET = array();
		parent::tear_down();
	}

	/**
	 * A logged-in user with a role.
	 *
	 * @param string $role Role.
	 * @return int
	 */
	private function as_role( string $role ): int {
		$id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $id );
		return $id;
	}

	/**
	 * An action that starts on a day.
	 *
	 * @param string               $title Title.
	 * @param string               $start Y-m-d.
	 * @param array<string, mixed> $meta  More meta.
	 * @param array<string, mixed> $args  Extra post args.
	 * @return int
	 */
	private function action( string $title, string $start, array $meta = array(), array $args = array() ): int {
		$id = self::factory()->post->create(
			$args + array(
				'post_type'   => PostTypes::ACTION,
				'post_title'  => $title,
				'post_author' => get_current_user_id(),
			)
		);
		foreach ( array( A::START => $start ) + $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		return $id;
	}

	/**
	 * The period bar of a calendar screen: arrows, today, title and count.
	 *
	 * @param array<string, string> $screen Calendar screen.
	 * @return string
	 */
	private function period( array $screen ): string {
		return (string) strstr( (string) strstr( $screen['body'], '<div class="fmc-period">' ), '<div class="fmc-legend', true );
	}

	/**
	 * The titles of the cards shown on a day of the calendar body.
	 *
	 * @param string $body Calendar body.
	 * @param int    $day  Day of the month.
	 * @return string[]
	 */
	private function cards_on( string $body, int $day ): array {
		// Las tarjetas no llevan `div`: el día acaba en el primer cierre.
		if ( ! preg_match( '/<div class="fmc-cal-num">' . $day . '<\/div>(.*?)<\/div>/s', $body, $m ) ) {
			return array();
		}
		preg_match_all( '/<span class="fmc-ev-title">(.*?)<\/span>/', $m[1], $titles );
		return $titles[1];
	}

	/**
	 * El mes se lee de `fmc_month`; si no vale, es el mes en curso.
	 */
	public function test_the_month_comes_from_the_request_or_is_the_current_one() {
		$current = gmdate( 'Y-m-01', (int) current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- igual que el calendario.

		$_GET['fmc_month'] = '2026-03';
		$this->assertSame( '2026-03-01', Calendar::month() );

		foreach ( array( '2026-13', '2026-3', 'marzo', '' ) as $bad ) {
			$_GET['fmc_month'] = $bad;
			$this->assertSame( $current, Calendar::month(), $bad );
		}
	}

	/**
	 * Cada acción cae en su día de inicio; las de otro mes no salen.
	 */
	public function test_actions_fall_on_their_start_day() {
		$this->as_role( 'fmc_curator' );
		$this->action( 'Robótica', '2026-03-10' );
		$this->action( 'Ajedrez', '2026-03-10' );
		$this->action( 'Teatro', '2026-03-31' );
		$this->action( 'De febrero', '2026-02-28' );
		$this->action( 'De abril', '2026-04-01' );
		$_GET['fmc_month'] = '2026-03';

		$screen = Calendar::screen();

		$this->assertSame( 'actions', $screen['tab'] );
		$this->assertMatchesRegularExpression( '/fmc_tab=actions[^"]*fmc_csv=1[^"]*fmc_from=2026-03-01[^"]*fmc_to=2026-03-31">Exportar CSV</', $screen['actions'], 'el CSV, de lo que empieza ese mes' );
		$this->assertSame( 'Planificación de acciones formativas', $screen['title'] );
		$this->assertStringContainsString( '<h2 class="fmc-period-title">Marzo de 2026</h2>', $screen['body'] );
		$this->assertStringContainsString( '3 acciones este mes.', $this->period( $screen ) );
		$this->assertSame( array( 'Ajedrez', 'Robótica' ), $this->cards_on( $screen['body'], 10 ) );
		$this->assertSame( array( 'Teatro' ), $this->cards_on( $screen['body'], 31 ) );
		$this->assertStringNotContainsString( 'De febrero', $screen['body'] );
		$this->assertStringNotContainsString( 'De abril', $screen['body'] );
		$this->assertSame( array( '2026-03-10', '2026-03-31' ), array_keys( Calendar::by_day( '2026-03-01' ) ) );
	}

	/**
	 * Una acción con sesiones sale el día de cada sesión, con su horario,
	 * aunque empezara el mes anterior; cuenta una vez (ADR-0027).
	 */
	public function test_an_action_with_sessions_shows_on_each_session_day() {
		$this->as_role( 'fmc_curator' );
		$this->action(
			'Con sesiones',
			'2026-02-24',
			array(
				A::END      => '2026-03-17',
				A::SESSIONS => array(
					array(
						'day'  => '2026-02-24',
						'from' => '16:00',
						'to'   => '20:00',
					),
					array(
						'day'  => '2026-03-03',
						'from' => '16:00',
						'to'   => '20:00',
					),
					array(
						'day'  => '2026-03-17',
						'from' => '09:30',
						'to'   => '13:30',
					),
				),
			)
		);
		$this->action( 'Sin sesiones', '2026-03-03' );
		$_GET['fmc_month'] = '2026-03';

		$screen = Calendar::screen();

		$this->assertSame( array( 'Con sesiones', 'Sin sesiones' ), $this->cards_on( $screen['body'], 3 ) );
		$this->assertSame( array( 'Con sesiones' ), $this->cards_on( $screen['body'], 17 ) );
		$this->assertSame( array(), $this->cards_on( $screen['body'], 24 ), 'el día de inicio no es una sesión de marzo' );
		$this->assertStringContainsString( '<span class="fmc-ev-meta fmc-ev-time">09:30–13:30</span>', $screen['body'] );
		$this->assertStringContainsString( '2 acciones este mes.', $this->period( $screen ), 'tres sesiones son una acción' );

		// En febrero, la sesión de su día de inicio.
		$this->assertSame( array( '2026-02-24' ), array_keys( Calendar::by_day( '2026-02-01' ) ) );
	}

	/**
	 * En la semana, una acción con sesión ese día sale en el día y no se
	 * repite entre las que siguen en curso.
	 */
	public function test_the_week_does_not_repeat_an_action_with_a_session_in_it() {
		$this->as_role( 'fmc_curator' );
		$this->action(
			'Con sesión el jueves',
			'2026-10-05',
			array(
				A::END      => '2026-11-30',
				A::SESSIONS => array(
					array(
						'day'  => '2026-10-22',
						'from' => '17:00',
						'to'   => '19:00',
					),
				),
			)
		);
		$this->action( 'Sigue en curso', '2026-10-05', array( A::END => '2026-11-30' ) );

		$_GET['fmc_view'] = 'week';
		$_GET['fmc_day']  = '2026-10-21';
		$screen           = Calendar::screen();

		$this->assertStringContainsString( '1 acción esta semana. Además, 1 sigue en curso.', $this->period( $screen ) );
		$this->assertSame( 1, substr_count( $screen['body'], 'Con sesión el jueves</span>' ) );
		$this->assertStringContainsString( '17:00–19:00', $screen['body'] );
	}

	/**
	 * El fin de semana solo se enseña si ese mes alguna acción cae en él.
	 */
	public function test_the_weekend_is_hidden_unless_an_action_falls_on_it() {
		$this->as_role( 'fmc_curator' );
		$this->action( 'Martes', '2026-03-10' );
		$_GET['fmc_month'] = '2026-03';

		$this->assertStringContainsString( '<div class="fmc-cal card no-weekend">', Calendar::screen()['body'] );

		// El 14 de marzo de 2026 es sábado.
		$this->action( 'Sábado', '2026-03-14' );
		$body = Calendar::screen()['body'];
		$this->assertStringContainsString( '<div class="fmc-cal card">', $body );
		$this->assertSame( array( 'Sábado' ), $this->cards_on( $body, 14 ) );
		$this->assertStringContainsString( '<div class="fmc-cal-day is-weekend"><div class="fmc-cal-num">14</div>', $body );
		// Marzo de 2026 empieza en domingo: seis días de relleno delante.
		$this->assertSame( 6, substr_count( strstr( $body, '<div class="fmc-cal-num">1</div>', true ), 'is-out' ) );
	}

	/**
	 * La flecha de cada lado lleva al mes vecino y conserva los filtros.
	 */
	public function test_month_navigation_keeps_the_filters() {
		$this->as_role( 'fmc_curator' );
		$_GET = array(
			'fmc_month'     => '2026-01',
			'fmc_situation' => 'done',
			'fmc_year'      => '2025-2026',
		);

		$screen = Calendar::screen();
		$nav    = $this->period( $screen );

		$this->assertMatchesRegularExpression( '/href="[^"]*fmc_situation=done[^"]*fmc_month=2025-12" title="Periodo anterior">‹<\/a>/', $nav );
		$this->assertMatchesRegularExpression( '/href="[^"]*fmc_month=2026-02" title="Periodo siguiente">›<\/a>/', $nav );
		$this->assertMatchesRegularExpression( '/href="[^"]*fmc_month=' . substr( Calendar::today(), 0, 7 ) . '">Hoy<\/a>/', $nav );
		// El curso escolar no viaja con las flechas: el mes ya dice cuándo.
		$this->assertStringNotContainsString( 'fmc_year', $nav );
		$this->assertStringContainsString( '+ Agendar acción formativa', $screen['actions'] );
	}

	/**
	 * Elegir un curso lleva el calendario a ese curso: si el mes que se está
	 * viendo no es de él, se va a septiembre, que es por donde empieza.
	 */
	public function test_choosing_a_school_year_moves_the_calendar_to_it() {
		$this->as_role( 'fmc_curator' );

		$_GET = array(
			'fmc_month' => '2026-11',
			'fmc_year'  => '2024-2025',
		);
		$this->assertSame( '2024-09-01', Calendar::month() );
		$this->assertSame( '2024-09-01', Calendar::day() );

		// Un mes que sí es de ese curso se respeta.
		$_GET['fmc_month'] = '2025-03';
		$this->assertSame( '2025-03-01', Calendar::month() );

		// Y un día elegido a mano, también.
		$_GET['fmc_day'] = '2025-03-10';
		$this->assertSame( '2025-03-10', Calendar::day() );

		// Sin curso elegido, manda el mes.
		unset( $_GET['fmc_year'] );
		$_GET['fmc_month'] = '2026-11';
		$this->assertSame( '2026-11-01', Calendar::month() );
	}

	/**
	 * Hoy lleva su marca cuando se ve el mes en curso.
	 */
	public function test_today_is_marked() {
		$this->as_role( 'fmc_curator' );
		$today = gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- igual que el calendario.

		$body = Calendar::screen()['body'];

		$this->assertSame( 1, substr_count( $body, 'is-today' ) );
		$this->assertMatchesRegularExpression( '/is-today"><div class="fmc-cal-num">' . (int) substr( $today, 8 ) . '<\/div>/', $body );
	}

	/**
	 * Los filtros de la pantalla estrechan el calendario, salvo las fechas:
	 * manda el mes que se está viendo.
	 */
	public function test_filters_narrow_the_calendar_but_the_month_rules_the_dates() {
		$this->as_role( 'fmc_curator' );
		$this->action( 'Realizada', '2026-03-10', array( A::SITUATION => 'done' ) );
		$this->action( 'Aplazada', '2026-03-11', array( A::SITUATION => 'postponed' ) );
		$_GET = array(
			'fmc_month'     => '2026-03',
			'fmc_situation' => 'done',
		);

		$body = Calendar::screen()['body'];
		$this->assertSame( array( 'Realizada' ), $this->cards_on( $body, 10 ) );
		$this->assertSame( array(), $this->cards_on( $body, 11 ) );
		$this->assertStringContainsString( '<option value="done" selected=\'selected\'>Realizada</option>', $body );

		$_GET = array(
			'fmc_month' => '2026-03',
			'fmc_from'  => '2027-01-01',
			'fmc_year'  => '2020-2021',
		);
		$this->assertCount( 2, Calendar::by_day( '2026-03-01' ) );
	}

	/**
	 * La asesoría no ve en el calendario los borradores de otras asesorías,
	 * igual que no los ve en el listado.
	 */
	public function test_an_adviser_does_not_see_the_drafts_of_others() {
		$other = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$this->action( 'Ajena publicada', '2026-03-10', array(), array( 'post_author' => $other ) );
		$this->action(
			'Ajena en borrador',
			'2026-03-10',
			array(),
			array(
				'post_author' => $other,
				'post_status' => 'draft',
			)
		);
		$this->as_role( 'fmc_adviser' );
		$this->action( 'Mía en borrador', '2026-03-10', array(), array( 'post_status' => 'draft' ) );
		$_GET['fmc_month'] = '2026-03';

		$this->assertSame( array( 'Ajena publicada', 'Mía en borrador' ), $this->cards_on( Calendar::screen()['body'], 10 ) );
	}

	/**
	 * Sin poder editar acciones, no hay botón de añadir.
	 */
	public function test_the_training_service_cannot_add_from_the_calendar() {
		$this->as_role( 'fmc_training_service' );

		$this->assertStringNotContainsString( 'Añadir', Calendar::screen()['actions'] );
	}

	/**
	 * La leyenda explica las situaciones y quién paga, sin «sin coste». Lo que
	 * filtra se puede pulsar —cada situación y lo que espera el expediente—, y
	 * pulsar lo que ya filtra lo quita; ni la vista ni el mes se pierden.
	 */
	public function test_the_legend_explains_the_colours_and_filters() {
		$this->as_role( 'fmc_curator' );
		$_GET = array(
			'fmc_view'    => 'week',
			'fmc_day'     => '2026-03-10',
			'fmc_adviser' => '7',
		);

		$body = Calendar::screen()['body'];

		foreach ( A::SITUATIONS as $key => $label ) {
			$this->assertMatchesRegularExpression( '#<a class="fmc-chip fmc-sit-' . $key . '" href="[^"]*fmc_adviser=7[^"]*fmc_view=week[^"]*fmc_day=2026-03-10[^"]*fmc_situation=' . $key . '" title="Ver solo estas">' . $label . '</a>#', $body );
		}
		$this->assertMatchesRegularExpression( '#<a class="fmc-chip " href="[^"]*fmc_status=publish" title="Ver solo estas"><span class="fmc-ribbon is-warn">!</span> Sin expediente</a>#', $body );
		$this->assertStringContainsString( 'style="background:#6f42c1"></i>Asume: área</span>', $body );
		$this->assertStringNotContainsString( 'Asume: sin coste', $body );
		$this->assertStringNotContainsString( 'Incompleta', $body );

		$_GET['fmc_situation'] = 'done';
		$body                  = Calendar::screen()['body'];
		$this->assertMatchesRegularExpression( '#<a class="fmc-chip fmc-sit-done is-active" href="([^"]*)" aria-current="true" title="Quitar este filtro">Realizada</a>#', $body );
		preg_match( '#<a class="fmc-chip fmc-sit-done is-active" href="([^"]*)"#', $body, $m );
		$this->assertStringNotContainsString( 'fmc_situation', $m[1] );
	}

	/**
	 * Cambiar de vista, de mes o exportar no pierde ningún filtro: ni la
	 * asesoría, ni el estado, ni las fechas. El curso viaja al cambiar de
	 * vista, y no con las flechas.
	 */
	public function test_no_filter_is_lost_when_moving_around() {
		$this->as_role( 'fmc_curator' );
		$_GET = array(
			'fmc_month'        => '2026-01',
			'fmc_year'         => '2025-2026',
			'fmc_adviser'      => '7',
			'fmc_adviser_kind' => 'ict',
			'fmc_status'       => 'completed',
			'fmc_from'         => '2026-01-01',
			'fmc_to'           => '2026-06-30',
			'fmc_scope'        => 'norte',
		);

		$screen = Calendar::screen();
		$kept   = array( 'fmc_adviser=7', 'fmc_adviser_kind=ict', 'fmc_status=completed', 'fmc_scope=norte' );
		preg_match_all( '#<a class="fmc-view" href="([^"]*)"#', $screen['actions'], $views );
		$this->assertCount( 4, $views[1] );
		foreach ( $views[1] as $url ) {
			foreach ( array_merge( $kept, array( 'fmc_from=2026-01-01', 'fmc_to=2026-06-30', 'fmc_year=2025-2026' ) ) as $filter ) {
				$this->assertStringContainsString( $filter, $url );
			}
		}
		$nav = $this->period( $screen );
		foreach ( $kept as $filter ) {
			$this->assertSame( 3, substr_count( $nav, $filter ), $filter . ' en las dos flechas y en «Hoy»' );
		}
		$this->assertStringNotContainsString( 'fmc_year', $nav );
		// Desde la lista, lo mismo: sus botones de vista son los del calendario.
		$_GET['fmc_view'] = 'list';
		preg_match_all( '#<a class="fmc-view" href="([^"]*)"#', Lists::screen( 'actions', PostTypes::ACTION )['actions'], $views );
		foreach ( $views[1] as $url ) {
			$this->assertStringContainsString( 'fmc_adviser=7', $url );
			$this->assertStringContainsString( 'fmc_status=completed', $url );
		}
	}

	/**
	 * Al servicio de perfeccionamiento las acciones se le abren en lista: las
	 * recorre una a una para ponerles el expediente. Los demás, en el mes.
	 */
	public function test_the_training_service_opens_the_actions_as_a_list() {
		$this->as_role( 'fmc_curator' );
		$this->assertSame( 'month', Calendar::view() );
		$this->assertMatchesRegularExpression( '#<a class="fmc-view" href="[^"]*fmc_view=list[^"]*">Lista</a>#', Calendar::views_html( 'month' ) );

		$this->as_role( 'fmc_training_service' );
		$this->assertSame( 'list', Calendar::view() );
		$views = Calendar::views_html( 'list' );
		// Su «Mes» lo dice en la dirección; su «Lista» es la de serie y no lleva nada.
		$this->assertMatchesRegularExpression( '#<a class="fmc-view" href="[^"]*fmc_view=month[^"]*">Mes</a>#', $views );
		$this->assertMatchesRegularExpression( '#<a class="fmc-view" href="(?:(?!fmc_view)[^"])*" aria-current="true">Lista</a>#', $views );
		$_GET['fmc_view'] = 'month';
		$this->assertSame( 'month', Calendar::view(), 'si pide el mes, el mes' );

		$this->as_role( 'administrator' );
		unset( $_GET['fmc_view'] );
		$this->assertSame( 'month', Calendar::view(), 'la administración gestiona las acciones: como la coordinación' );
	}

	/**
	 * Una acción completa: color de su situación, punto de quién la paga,
	 * desdoble, centro y programas; sin la cinta de incompleta.
	 */
	public function test_a_card_shows_situation_funder_replicas_and_programmes() {
		$this->as_role( 'fmc_curator' );
		$design = self::factory()->post->create( array( 'post_type' => PostTypes::DESIGN ) );
		$id     = $this->action(
			'Robótica',
			'2026-03-10',
			array(
				// Impartiéndose: empezó y aún no ha acabado (ADR-0020).
				A::END          => '2099-12-31',
				A::FUNDED_BY    => 'area',
				A::REPLICAS     => 2,
				A::DESIGN_ID    => $design,
				A::PLACES       => 25,
				A::MODALITY     => 'onsite',
				A::FILE_NUMBER  => 'EXP-1',
				A::VENUE_CENTRE => 'IES Las Palmeras',
			)
		);
		wp_set_object_terms( $id, 'Ámbito norte', 'fmc_scope' );
		wp_set_object_terms( $id, 'Programa uno', 'fmc_programme' );

		$card = Calendar::card( get_post( $id ) );

		$this->assertStringContainsString( 'class="fmc-ev fmc-abre-panel fmc-sit-running"', $card );
		$this->assertStringContainsString( 'fmc_edit=' . $id, $card );
		$this->assertStringContainsString( '<span class="fmc-ribbon">×2</span>', $card );
		$this->assertStringNotContainsString( 'is-warn', $card );
		$this->assertStringContainsString( '<span class="fmc-ev-meta">EXP-1</span><span class="fmc-ev-meta">Ámbito norte</span>', $card );
		// Los dos expedientes, y el subtítulo no (adenda de ADR-0009).
		update_post_meta( $id, A::INTERNAL_REF, 'AF9-10/03/2026-08:00:00' );
		update_post_meta( $id, A::SUBTITLE, 'Edición de primavera' );
		$both = Calendar::card( get_post( $id ) );
		$this->assertStringContainsString( '<span class="fmc-ev-meta">AF9-10/03/2026-08:00:00</span><span class="fmc-ev-meta">EXP-1</span>', $both );
		$this->assertStringNotContainsString( 'Edición de primavera', $both );
		$this->assertStringContainsString( '<span class="fmc-ev-meta">En: IES Las Palmeras</span>', $card );
		$this->assertStringContainsString( '<i class="fmc-dot" style="background:#6f42c1" title="Asume: Área"></i>Área · Programa uno</span>', $card );
	}

	/**
	 * Una acción entregada que espera el expediente del servicio de
	 * perfeccionamiento lleva su cinta; y sin situación —aún no ha empezado—
	 * ni quién paga, ni color ni punto. Si es un borrador, se dice, y no
	 * lleva la cinta: todavía no se ha entregado.
	 */
	public function test_a_card_waiting_for_its_file_number_is_flagged_and_has_no_colour() {
		$this->as_role( 'fmc_curator' );
		$id = $this->action(
			'A medias',
			'2099-03-10',
			array(
				A::FUNDED_BY    => 'none',
				A::REPLICAS     => 1,
				A::VENUE_CENTRE => 'Norte',
			)
		);
		wp_set_object_terms( $id, 'Norte', 'fmc_scope' );

		$card = Calendar::card( get_post( $id ) );

		$this->assertStringContainsString( 'fmc-sit-none', $card );
		$this->assertStringContainsString( '<span class="fmc-ribbon is-warn" title="Sin expediente de perfeccionamiento">!</span>', $card );
		update_post_meta( $id, A::FILE_NUMBER, 'EXP-3' );
		$this->assertStringNotContainsString( 'is-warn', Calendar::card( get_post( $id ) ), 'con él, está completada' );
		delete_post_meta( $id, A::FILE_NUMBER );
		$this->assertStringNotContainsString( '×', $card );
		$this->assertStringNotContainsString( 'fmc-dot', $card );
		// El centro ya va en el ámbito: no se repite.
		$this->assertStringNotContainsString( 'En: ', $card );
		// Sin coste y sin programa, no hay línea de financiación.
		$this->assertSame( 1, substr_count( $card, 'fmc-ev-meta' ) );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'draft',
			)
		);
		$draft = Calendar::card( get_post( $id ) );
		$this->assertStringContainsString( 'fmc-sit-none is-draft', $draft );
		$this->assertStringContainsString( '<span class="fmc-ev-meta">Borrador, sin entregar</span>', $draft );
	}

	/**
	 * Una acción con sesiones que empieza en la semana, pero sin sesión en
	 * ella, sigue en curso: no desaparece de la vista.
	 */
	public function test_an_action_without_a_session_in_the_week_is_still_running() {
		$this->as_role( 'fmc_curator' );
		$session = static fn( string $day ): array => array(
			'day'  => $day,
			'from' => '17:00',
			'to'   => '19:00',
		);
		$this->action(
			'Empieza sin sesión',
			'2026-03-02',
			array(
				A::END      => '2026-04-30',
				A::SESSIONS => array( $session( '2026-03-12' ), $session( '2026-03-19' ) ),
			)
		);
		// Sin fecha de fin y con su última sesión antes de la semana: acabó.
		$this->action( 'Acabó sin fecha de fin', '2026-02-02', array( A::SESSIONS => array( $session( '2026-02-10' ) ) ) );
		// Sin fecha de fin y con una sesión después: sigue.
		$this->action( 'Sigue sin fecha de fin', '2026-02-02', array( A::SESSIONS => array( $session( '2026-02-10' ), $session( '2026-03-26' ) ) ) );

		$_GET['fmc_view'] = 'week';
		$_GET['fmc_day']  = '2026-03-04';
		$screen           = Calendar::screen();

		$this->assertStringContainsString( '0 acciones esta semana. Además, 2 siguen en curso.', $this->period( $screen ) );
		$this->assertStringContainsString( 'Empieza sin sesión', $screen['body'] );
		$this->assertStringContainsString( 'Sigue sin fecha de fin', $screen['body'] );
		$this->assertStringNotContainsString( 'Acabó sin fecha de fin', $screen['body'] );
	}

	/**
	 * El CSV del calendario lleva lo que se ve en él: la acción que empezó
	 * antes y tiene sesión en el mes, sí; la que empieza en el mes y no tiene
	 * ninguna en él, no.
	 */
	public function test_the_calendar_csv_exports_what_the_calendar_shows() {
		$this->as_role( 'fmc_curator' );
		$session = static fn( string $day ): array => array(
			'day'  => $day,
			'from' => '17:00',
			'to'   => '19:00',
		);
		$before  = $this->action( 'Empezó en febrero', '2026-02-24', array( A::SESSIONS => array( $session( '2026-03-03' ) ) ) );
		$later   = $this->action( 'Sesiones en abril', '2026-03-30', array( A::SESSIONS => array( $session( '2026-04-07' ) ) ) );
		$plain   = $this->action( 'Sin sesiones', '2026-03-10' );

		$_GET   = array( 'fmc_month' => '2026-03' );
		$screen = Calendar::screen();
		preg_match( '/href="([^"]*fmc_csv=1[^"]*)">Exportar CSV</', $screen['actions'], $m );
		$this->assertNotEmpty( $m );
		wp_parse_str( (string) wp_parse_url( html_entity_decode( $m[1] ), PHP_URL_QUERY ), $query );
		$_GET = $query;

		$previous = set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- las cabeceras de la descarga, ya con salida.
			static function ( $errno, $errstr, ...$rest ) use ( &$previous ) {
				return false !== strpos( $errstr, 'Cannot modify header information' ) ? true : ( $previous ? $previous( $errno, $errstr, ...$rest ) : false );
			}
		);
		ob_start();
		try {
			Lists::csv( PostTypes::ACTION );
		} finally {
			$raw = (string) ob_get_clean();
			restore_error_handler();
		}
		preg_match_all( '/^(\d+);/m', $raw, $ids );
		$ids = array_map( 'intval', $ids[1] );
		sort( $ids );

		$this->assertSame( array( $before, $plain ), $ids );
		$this->assertNotContains( $later, $ids );
	}

	/**
	 * Un título hostil sale escapado en la tarjeta.
	 */
	public function test_a_hostile_title_is_escaped_on_the_card() {
		$this->as_role( 'administrator' );
		// La administración tiene `unfiltered_html`: el título se guarda tal cual.
		$id = $this->action( '<img src=x onerror=alert(1)>', '2026-03-10' );

		$card = Calendar::card( get_post( $id ) );

		$this->assertStringNotContainsString( '<img', $card );
		$this->assertStringContainsString( 'title="&lt;img src=x onerror=alert(1)&gt;"', $card );
		$this->assertStringContainsString( '<span class="fmc-ev-title">&lt;img src=x onerror=alert(1)&gt;</span>', $card );
	}


	/**
	 * La semana: de lunes a domingo, lo que empieza cada día y lo que sigue en curso.
	 */
	public function test_the_week_view_shows_its_days_and_what_is_running() {
		$this->as_role( 'fmc_curator' );
		$this->action( 'Empieza el martes', '2026-10-20' );
		$this->action( 'Empezó antes', '2026-10-05', array( A::END => '2026-11-30' ) );
		$this->action( 'Ya acabó', '2026-10-01', array( A::END => '2026-10-10' ) );
		$this->action( 'La semana siguiente', '2026-10-27' );

		$_GET['fmc_view'] = 'week';
		$_GET['fmc_day']  = '2026-10-21';
		$screen           = Calendar::screen();

		$this->assertStringContainsString( '>Semana del 19 de octubre de 2026</h2>', $screen['body'] );
		$this->assertStringContainsString( '1 acción esta semana. Además, 1 sigue en curso.', $this->period( $screen ) );
		$this->assertSame( 7, substr_count( $screen['body'], '<section class="fmc-days-col' ) );
		$this->assertStringContainsString( 'Empieza el martes', $screen['body'] );
		$this->assertStringContainsString( 'Empezó antes', $screen['body'] );
		$this->assertStringNotContainsString( 'Ya acabó', $screen['body'] );
		$this->assertStringNotContainsString( 'La semana siguiente', $screen['body'] );
		$this->assertMatchesRegularExpression( '/fmc_view=week[^"]*fmc_day=2026-10-14" title="Periodo anterior">‹</', $this->period( $screen ) );
		$this->assertMatchesRegularExpression( '/fmc_view=week[^"]*fmc_day=2026-10-28" title="Periodo siguiente">›</', $this->period( $screen ) );
		$this->assertMatchesRegularExpression( '/<a class="fmc-view" href="[^"]*fmc_month=2026-10[^"]*">Mes</', $screen['actions'], 'al mes de esa semana' );
	}

	/**
	 * El día, y la lista, que es el listado de acciones.
	 */
	public function test_the_day_and_list_views() {
		$this->as_role( 'fmc_curator' );
		$this->action( 'Hoy', '2026-10-21' );
		$this->action( 'Mañana', '2026-10-22' );

		$_GET['fmc_view'] = 'day';
		$_GET['fmc_day']  = '2026-10-21';
		$screen           = Calendar::screen();
		$this->assertStringContainsString( '>Miércoles 21 de octubre de 2026</h2>', $screen['body'] );
		$calendar = $screen;
		$this->assertStringContainsString( '>Hoy</span>', $screen['body'] );
		$this->assertStringNotContainsString( 'Mañana', $screen['body'] );

		$this->assertMatchesRegularExpression( '/fmc_csv=1[^"]*fmc_from=2026-10-21[^"]*fmc_to=2026-10-21">Exportar CSV</', $screen['actions'] );

		$_GET   = array(
			'fmc_view'  => 'list',
			'fmc_month' => '2026-10',
		);
		$screen = Lists::screen( 'actions', PostTypes::ACTION );
		$this->assertStringContainsString( '<table', $screen['body'] );
		$this->assertStringContainsString( '>Mañana</a>', $screen['body'] );
		$this->assertMatchesRegularExpression( '/<a class="fmc-view" href="[^"]*fmc_view=list[^"]*" aria-current="true">Lista</', $screen['actions'], 'la vista activa, marcada' );
		$this->assertMatchesRegularExpression( '/<a class="fmc-view" href="[^"]*fmc_month=2026-10[^"]*">Mes</', $screen['actions'], 'vuelve al mismo mes' );
		// La misma cabecera en el calendario y en la lista: al cambiar de vista no se mueve nada.
		$this->assertSame( $calendar['title'], $screen['title'] );
		$this->assertSame( $calendar['subtitle'], $screen['subtitle'] );
		$this->assertSame( preg_replace( '/<nav.*<\/nav>| href="[^"]*"/U', '', $calendar['actions'] ), preg_replace( '/<nav.*<\/nav>| href="[^"]*"/U', '', $screen['actions'] ) );
		$this->assertMatchesRegularExpression( '/fmc_view=list[^"]*fmc_csv=1">Exportar CSV</', $screen['actions'] );
		$this->assertStringContainsString( '<input type="hidden" name="fmc_view" value="list">', $screen['body'], 'filtrar no cambia de vista' );

		// Cambiar de vista no pierde la fecha: de otro mes, a su primera semana.
		$_GET = array( 'fmc_month' => '2025-03' );
		$this->assertMatchesRegularExpression( '/fmc_view=week[^"]*fmc_day=2025-03-01">Semana</', Calendar::views_html( 'month' ) );

		$_GET = array( 'fmc_view' => 'inventada' );
		$this->assertSame( 'month', Calendar::view() );
	}
}

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

		$this->assertSame( 'calendar', $screen['tab'] );
		$this->assertSame( 'Calendario de marzo de 2026', $screen['title'] );
		$this->assertSame( '3 acción(es) empiezan este mes.', $screen['subtitle'] );
		$this->assertSame( array( 'Ajedrez', 'Robótica' ), $this->cards_on( $screen['body'], 10 ) );
		$this->assertSame( array( 'Teatro' ), $this->cards_on( $screen['body'], 31 ) );
		$this->assertStringNotContainsString( 'De febrero', $screen['body'] );
		$this->assertStringNotContainsString( 'De abril', $screen['body'] );
		$this->assertSame( array( '2026-03-10', '2026-03-31' ), array_keys( Calendar::by_day( '2026-03-01' ) ) );
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

		$actions = Calendar::screen()['actions'];

		$this->assertMatchesRegularExpression( '/href="[^"]*fmc_situation=done[^"]*fmc_month=2025-12">‹<\/a>/', $actions );
		$this->assertMatchesRegularExpression( '/href="[^"]*fmc_month=2026-02">›<\/a>/', $actions );
		$this->assertMatchesRegularExpression( '/href="[^"]*fmc_month=' . gmdate( 'Y-m' ) . '">Hoy<\/a>/', $actions );
		// El curso escolar no viaja con las flechas: el mes ya dice cuándo.
		$this->assertStringNotContainsString( 'fmc_year', $actions );
		$this->assertStringContainsString( '+ Añadir acción', $actions );
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
	 * La leyenda explica las situaciones y quién paga, sin «sin coste».
	 */
	public function test_the_legend_explains_the_colours() {
		$this->as_role( 'fmc_curator' );

		$body = Calendar::screen()['body'];

		foreach ( A::SITUATIONS as $key => $label ) {
			$this->assertStringContainsString( '<span class="fmc-chip fmc-sit-' . $key . '">' . $label . '</span>', $body );
		}
		$this->assertStringContainsString( 'style="background:#6f42c1"></i>Asume: área</span>', $body );
		$this->assertStringNotContainsString( 'Asume: sin coste', $body );
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
				A::SITUATION    => 'running',
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
		$this->assertStringContainsString( '<span class="fmc-ev-meta">EXP-1 · Ámbito norte</span>', $card );
		$this->assertStringContainsString( '<span class="fmc-ev-meta">En: IES Las Palmeras</span>', $card );
		$this->assertStringContainsString( '<i class="fmc-dot" style="background:#6f42c1" title="Asume: Área"></i>Área · Programa uno</span>', $card );
	}

	/**
	 * A una acción a medias le falta diseño, plazas o modalidad: lleva la
	 * cinta de incompleta y, sin situación ni quién paga, ni color ni punto.
	 */
	public function test_an_incomplete_card_is_flagged_and_has_no_colour() {
		$this->as_role( 'fmc_curator' );
		$id = $this->action(
			'A medias',
			'2026-03-10',
			array(
				A::FUNDED_BY    => 'none',
				A::REPLICAS     => 1,
				A::VENUE_CENTRE => 'Norte',
			)
		);
		wp_set_object_terms( $id, 'Norte', 'fmc_scope' );

		$card = Calendar::card( get_post( $id ) );

		$this->assertStringContainsString( 'fmc-sit-none', $card );
		$this->assertStringContainsString( '<span class="fmc-ribbon is-warn" title="Datos incompletos">!</span>', $card );
		$this->assertStringNotContainsString( '×', $card );
		$this->assertStringNotContainsString( 'fmc-dot', $card );
		// El centro ya va en el ámbito: no se repite.
		$this->assertStringNotContainsString( 'En: ', $card );
		// Sin coste y sin programa, no hay línea de financiación.
		$this->assertSame( 1, substr_count( $card, 'fmc-ev-meta' ) );
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
}

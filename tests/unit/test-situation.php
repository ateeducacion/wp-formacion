<?php
/**
 * Tests for the situation of an action.
 *
 * @package Fmc
 */

use Fmc\Domain\Situation;

/**
 * La situación sale de las fechas, y lo que deja una incidencia manda (ADR-0020).
 */
class Test_Situation extends WP_UnitTestCase {

	/**
	 * Cada caso: inicio, fin, lo guardado y hoy.
	 *
	 * @return array<string, array{string, string, string, string, string}>
	 */
	public function cases(): array {
		return array(
			'sin fechas, en blanco'              => array( '', '', '', '2026-10-27', '' ),
			'aún no ha empezado'                 => array( '2026-11-02', '2026-11-05', '', '2026-10-27', '' ),
			'el primer día'                      => array( '2026-10-27', '2026-11-05', '', '2026-10-27', 'running' ),
			'entre medias'                       => array( '2026-10-20', '2026-11-05', '', '2026-10-27', 'running' ),
			'el último día'                      => array( '2026-10-20', '2026-10-27', '', '2026-10-27', 'running' ),
			'pasada'                             => array( '2026-10-20', '2026-10-26', '', '2026-10-27', 'done' ),
			'sin fin dura un día'                => array( '2026-10-27', '', '', '2026-10-27', 'running' ),
			'sin fin, al día siguiente'          => array( '2026-10-26', '', '', '2026-10-27', 'done' ),
			'un fin anterior al inicio'          => array( '2026-10-26', '2026-10-01', '', '2026-10-27', 'done' ),
			'aplazada manda sobre las fechas'    => array( '2026-10-20', '2026-10-26', 'postponed', '2026-10-27', 'postponed' ),
			'cancelada, aunque no haya empezado' => array( '2026-11-02', '', 'cancelled', '2026-10-27', 'cancelled' ),
			'lo guardado a mano antes no cuenta' => array( '2026-11-02', '', 'done', '2026-10-27', '' ),
			'una fecha que no es fecha'          => array( '27/10/2026', '', '', '2026-10-27', '' ),
		);
	}

	/**
	 * La situación de cada caso.
	 *
	 * @dataProvider cases
	 *
	 * @param string $start    Start.
	 * @param string $end      End.
	 * @param string $stored   Stored by an incident.
	 * @param string $today    Today.
	 * @param string $expected Situation.
	 */
	public function test_the_situation_follows_the_dates( string $start, string $end, string $stored, string $today, string $expected ) {
		$this->assertSame( $expected, Situation::of( $start, $end, $stored, $today ) );
	}
}

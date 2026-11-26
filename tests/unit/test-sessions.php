<?php
/**
 * Tests for the sessions of an action.
 *
 * @package Fmc
 */

use Fmc\Domain\Sessions;
use Fmc\Meta\ActionMetaKeys as A;
use Fmc\Meta\MetaRegistration;
use Fmc\PostType\PostTypes;

/**
 * Las sesiones de una acción: día y horas de cada una (ADR-0027, issue #53).
 */
class Test_Sessions extends WP_UnitTestCase {

	/**
	 * A session row.
	 *
	 * @param string $day  Day.
	 * @param string $from From.
	 * @param string $to   To.
	 * @return array<string, string>
	 */
	private function row( string $day, string $from, string $to ): array {
		return array(
			'day'  => $day,
			'from' => $from,
			'to'   => $to,
		);
	}

	/**
	 * Las filas se ordenan, se quitan las repetidas y las vacías, y la hora
	 * escrita «9:30» se guarda «09:30».
	 */
	public function test_rows_are_cleaned_and_sorted() {
		$read = Sessions::read(
			array(
				$this->row( '2027-04-03', '16:00', '20:00' ),
				$this->row( '', '', '' ),
				$this->row( '2027-03-20', '9:30', '13:30' ),
				$this->row( '2027-04-03', '16:00', '20:00' ),
				'no es una fila',
			)
		);

		$this->assertSame( '', $read['error'] );
		$this->assertSame(
			array(
				$this->row( '2027-03-20', '09:30', '13:30' ),
				$this->row( '2027-04-03', '16:00', '20:00' ),
			),
			$read['sessions']
		);
	}

	/**
	 * Una fila a medias o que acaba antes de empezar es un error que se dice, y no se guarda.
	 */
	public function test_wrong_rows_are_errors() {
		$half = Sessions::read( array( $this->row( '2027-03-20', '16:00', '' ) ) );
		$this->assertSame( 'Cada sesión necesita el día, la hora de inicio y la de fin.', $half['error'] );
		$this->assertSame( array(), $half['sessions'] );

		$back = Sessions::read( array( $this->row( '2027-03-20', '20:00', '16:00' ) ) );
		$this->assertSame( 'La sesión del 20/03/2027 acaba antes de empezar.', $back['error'] );

		$bad = Sessions::read( array( $this->row( '2027-02-30', '16:00', '20:00' ) ) );
		$this->assertNotSame( '', $bad['error'], 'el 30 de febrero no existe' );

		$this->assertSame( '', Sessions::read( 'texto' )['error'] );
		$this->assertSame( array(), Sessions::clean( null ) );
	}

	/**
	 * Cada sesión, dentro de las fechas de la acción; sin fin, desde el inicio.
	 */
	public function test_sessions_stay_within_the_action_dates() {
		$sessions = array( $this->row( '2027-03-20', '16:00', '20:00' ), $this->row( '2027-04-03', '16:00', '20:00' ) );

		$this->assertSame( '', Sessions::outside( $sessions, '2027-03-20', '2027-04-03' ) );
		$this->assertSame( '', Sessions::outside( $sessions, '2027-03-01', '' ) );
		$this->assertSame( 'La sesión del 03/04/2027 está fuera de las fechas de inicio y de fin de la acción.', Sessions::outside( $sessions, '2027-03-01', '2027-03-31' ) );
		$this->assertSame( 'La sesión del 20/03/2027 está fuera de las fechas de inicio y de fin de la acción.', Sessions::outside( $sessions, '2027-03-21', '' ) );
	}

	/**
	 * Las horas se suman, con sus minutos, y se escriben en una línea.
	 */
	public function test_hours_and_labels() {
		$sessions = array( $this->row( '2027-03-20', '16:00', '20:00' ), $this->row( '2027-03-27', '09:30', '13:00' ) );

		$this->assertSame( 7.5, Sessions::hours( $sessions ) );
		$this->assertSame( '2 sesiones · 7.5 h', Sessions::summary( $sessions ) );
		$this->assertSame( '1 sesión · 4 h', Sessions::summary( array( $sessions[0] ) ) );
		$this->assertSame( '', Sessions::summary( array() ) );
		$this->assertSame( '20/03/2027, de 16:00 a 20:00', Sessions::label( $sessions[0] ) );
		$this->assertSame( array( '2027-03-20', '2027-03-27' ), array_keys( Sessions::by_day( $sessions ) ) );
	}

	/**
	 * La meta se sanea igual que el formulario: lo que está mal no llega a guardarse.
	 */
	public function test_the_meta_is_sanitised_as_sessions() {
		$clean = MetaRegistration::sanitize(
			PostTypes::ACTION,
			A::SESSIONS,
			array( $this->row( '2027-03-20', '16:00', '20:00' ), $this->row( '2027-03-21', '20:00', '16:00' ) )
		);
		$this->assertSame( array( $this->row( '2027-03-20', '16:00', '20:00' ) ), $clean );
	}
}

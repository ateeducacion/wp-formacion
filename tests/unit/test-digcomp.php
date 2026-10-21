<?php
/**
 * Tests for the digital-competence profile summary.
 *
 * @package Fmc
 */

use Fmc\Domain\DigComp;

/**
 * El resumen sale de la dirección que comparte la herramienta, y nada más.
 */
class Test_DigComp extends WP_UnitTestCase {

	/**
	 * Competencias en el orden del marco, niveles ordenados y cuántos indicadores.
	 */
	public function test_the_summary_groups_indicators_by_competence_and_level() {
		$url = 'https://herramienta.example/?c=2.1.A2.2%7C1.1.A1.1%7C1.1.A1.2%7C1.1.B1.1|basura|6.4.A1.1';

		$this->assertSame(
			array(
				'1.1' => array(
					'A1' => 2,
					'B1' => 1,
				),
				'2.1' => array( 'A2' => 1 ),
				'6.4' => array( 'A1' => 1 ),
			),
			DigComp::summary( $url )
		);
		$this->assertSame( 'Comunicación organizativa', DigComp::COMPETENCES['1.1'] );
	}

	/**
	 * Sin `c`, o con algo que no son indicadores, no hay resumen.
	 */
	public function test_an_url_without_indicators_has_no_summary() {
		$this->assertSame( array(), DigComp::summary( 'https://herramienta.example/' ) );
		$this->assertSame( array(), DigComp::summary( 'https://herramienta.example/?c[]=1.1.A1.1' ) );
		$this->assertSame( array(), DigComp::summary( 'no es una dirección' ) );
	}
}

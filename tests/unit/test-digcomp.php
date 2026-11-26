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

	/**
	 * El perfil ya resumido, para quien lo pinta sin conocer el marco: las
	 * seis áreas con todas sus competencias y, de cada una, el nivel más alto.
	 */
	public function test_the_profile_gives_every_area_with_the_top_level_of_each_competence() {
		$profile = DigComp::profile( 'https://herramienta.example/?c=1.3.A1.1|1.3.A2.1|1.3.A2.2|2.1.B1.1|6.5.C2.3' );

		$this->assertCount( 6, $profile );
		$this->assertSame( array( 1, 2, 3, 4, 5, 6 ), array_column( $profile, 'area' ) );
		$this->assertSame( 'Compromiso profesional', $profile[0]['name'] );
		$this->assertSame( 23, array_sum( array_map( static fn( $a ) => count( $a['competences'] ), $profile ) ) );

		$first = array_column( $profile[0]['competences'], null, 'code' );
		$this->assertSame( 'Práctica reflexiva', $first['1.3']['name'] );
		$this->assertSame( 'A2', $first['1.3']['level'], 'el más alto de los marcados' );
		$this->assertSame(
			array(
				'A1' => 1,
				'A2' => 2,
			),
			$first['1.3']['levels']
		);
		$this->assertSame( '', $first['1.1']['level'], 'la que no se trabaja va vacía, pero va' );
		$this->assertSame( array(), $first['1.1']['levels'] );
		$this->assertSame( 'C2', array_column( $profile[5]['competences'], 'level', 'code' )['6.5'] );
	}

	/**
	 * El enlace al perfil lleva a la herramienta con la misma selección, la
	 * guardara el diseño con la dirección que la guardara: la de otra copia, o
	 * la de una página que ya no existe.
	 */
	public function test_the_link_goes_to_the_tool_with_the_same_selection() {
		$tool = 'https://herramienta.example/';

		$this->assertSame( 'https://herramienta.example/?c=1.1.A1.1|3.1.B1.2', DigComp::tool_link( 'https://sitio-anterior.example/formacion/elidd/?c=1.1.A1.1|3.1.B1.2', $tool ) );
		$this->assertSame( 'https://herramienta.example/?c=1.1.A1.1|3.1.B1.2', DigComp::tool_link( 'https://herramienta.example/?c=1.1.A1.1|3.1.B1.2', $tool ), 'la que ya apunta a la herramienta se queda igual' );
		$this->assertSame( 'https://copia.example/elidd/?c=2.2.A2.1', DigComp::tool_link( 'https://herramienta.example/?c=2.2.A2.1&otra=cosa', 'https://copia.example/elidd' ), 'con o sin barra final' );
		$this->assertSame( 'https://herramienta.example/?c=1.1.A1.1', DigComp::tool_link( 'https://x.example/?c=1.1.A1.1|<script>|9', $tool ), 'solo pasan indicadores' );
		// Sin selección no hay nada que llevar: se deja como está.
		$this->assertSame( 'https://x.example/perfil', DigComp::tool_link( 'https://x.example/perfil', $tool ) );
		$this->assertSame( '', DigComp::tool_link( '', $tool ) );
	}

	/**
	 * Sin indicadores no hay perfil que dar.
	 */
	public function test_an_url_without_indicators_has_no_profile() {
		$this->assertSame( array(), DigComp::profile( 'https://herramienta.example/' ) );
		$this->assertSame( array(), DigComp::profile( '' ) );
	}
}

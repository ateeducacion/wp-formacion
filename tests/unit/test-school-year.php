<?php
/**
 * Tests for the school year derivation.
 *
 * @package Fmc
 */

use Fmc\Domain\SchoolYear;

/**
 * El curso escolar empieza el 1 de septiembre.
 */
class Test_School_Year extends WP_UnitTestCase {

	/**
	 * Los dos bordes y una fecha que no lo es.
	 */
	public function test_the_school_year_turns_on_the_first_of_september() {
		$this->assertSame( '2025-2026', SchoolYear::of( '2026-08-31' ) );
		$this->assertSame( '2026-2027', SchoolYear::of( '2026-09-01' ) );
		$this->assertSame( '2026-2027', SchoolYear::of( '2027-01-15' ) );
		$this->assertSame( '', SchoolYear::of( '' ) );
		$this->assertSame( '', SchoolYear::of( '15/01/2027' ) );
	}
}

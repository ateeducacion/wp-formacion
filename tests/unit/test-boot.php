<?php
/**
 * Tests for App::boot(): what it hooks and that it only hooks it once.
 *
 * @package Fmc
 */

use Fmc\App;
use Fmc\Meta\MetaRegistration;
use Fmc\PostType\PostTypes;
use Fmc\Taxonomy\Taxonomies;

/**
 * El arranque del aplicativo.
 *
 * `App::boot()` se llama desde `src/Fmc/bootstrap.php` y desde el bundle: si no
 * fuese idempotente, tener los dos activos a la vez engancharía cada módulo dos
 * veces y cada `init` haría el trabajo por duplicado.
 */
class Test_Boot extends WP_UnitTestCase {

	/**
	 * How many callbacks hang off each hook the boot touches, across every priority.
	 *
	 * @return array<string, int>
	 */
	private function enganchadas(): array {
		global $wp_filter;

		$cuenta = array();
		foreach ( array( 'init' ) as $gancho ) {
			$total = 0;
			if ( isset( $wp_filter[ $gancho ] ) ) {
				foreach ( $wp_filter[ $gancho ]->callbacks as $prioridad ) {
					$total += count( $prioridad );
				}
			}
			$cuenta[ $gancho ] = $total;
		}
		return $cuenta;
	}

	/**
	 * Un segundo arranque no vuelve a enganchar nada.
	 */
	public function test_boot_is_idempotent() {
		$antes = $this->enganchadas();

		App::boot();
		App::boot();

		$this->assertSame( $antes, $this->enganchadas() );
	}

	/**
	 * Cada módulo cuelga de su gancho y en su prioridad: las taxonomías antes
	 * que los tipos, las capacidades después de los tipos, y las metas al final.
	 */
	public function test_boot_wired_every_module() {
		$this->assertSame( 9, has_action( 'init', array( Taxonomies::class, 'register' ) ) );
		$this->assertSame( 10, has_action( 'init', array( PostTypes::class, 'register' ) ) );
		$this->assertSame( 11, has_action( 'init', array( PostTypes::class, 'grant_caps_to_roles' ) ) );
		$this->assertSame( 12, has_action( 'init', array( MetaRegistration::class, 'register_meta' ) ) );
	}

	/**
	 * Y al terminar `init`, los cuatro tipos y las cuatro taxonomías están montados.
	 */
	public function test_boot_registered_the_post_types_and_the_taxonomies() {
		foreach ( array_keys( PostTypes::definitions() ) as $slug ) {
			$this->assertTrue( post_type_exists( $slug ), $slug );
		}
		foreach ( array_keys( Taxonomies::definitions() ) as $slug ) {
			$this->assertTrue( taxonomy_exists( $slug ), $slug );
		}
	}
}

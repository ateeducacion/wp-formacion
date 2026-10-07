<?php
/**
 * Tests for the application assets.
 *
 * @package Fmc
 */

use Fmc\PublicFront\Assets;

/**
 * En desarrollo se leen de `assets/`; en el bundle, de lo que el empaquetador inlinea.
 */
class Test_Assets extends WP_UnitTestCase {

	/**
	 * Forget anything inlined by a test.
	 */
	public function tear_down() {
		Assets::set_inline( array() );
		parent::tear_down();
	}

	/**
	 * Sin bundle, la hoja de estilos es el fichero de `assets/`, entero.
	 */
	public function test_the_stylesheet_is_the_file_in_assets() {
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- ficheros del repositorio.
		$file = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/fmc-app.css' );

		$this->assertNotSame( '', $file );
		$this->assertSame( $file, Assets::css() );
		$this->assertSame( (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/fmc-app.js' ), Assets::contents( 'js/fmc-app.js' ) );
		// phpcs:enable
	}

	/**
	 * Lo inlineado por el empaquetador manda sobre el disco.
	 */
	public function test_inlined_contents_win_over_the_disk() {
		Assets::set_inline( array( 'css/fmc-app.css' => '.fmc{color:red}' ) );

		$this->assertSame( '.fmc{color:red}', Assets::css() );
		// Lo que no se inlineó se sigue leyendo de `assets/`.
		$this->assertStringContainsString( 'fmcApp', Assets::contents( 'js/fmc-app.js' ) );
	}

	/**
	 * Un fichero que no existe es una cadena vacía, no un error.
	 */
	public function test_a_missing_asset_is_empty() {
		$this->assertSame( '', Assets::contents( 'css/no-existe.css' ) );
	}
}

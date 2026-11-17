<?php
/**
 * Tests for the help tab and its generated snippet.
 *
 * @package Fmc
 */

use Fmc\PublicFront\Help;

/**
 * La ayuda llega en su propio snippet, «FMC — Ayuda», generado desde
 * `assets/help/` con las capturas dentro (ADR-0026). El aplicativo solo pinta
 * la pestaña si ese snippet está desplegado.
 *
 * `tests/bootstrap.php` se salta los `*.bundle.php`, así que el snippet de la
 * ayuda no está cargado al empezar: el primer test lo comprueba sin él y los
 * demás lo cargan.
 */
class Test_Help extends WP_UnitTestCase {

	/**
	 * Path of the generated help snippet.
	 *
	 * @return string
	 */
	private function snippet_path(): string {
		$path = dirname( __DIR__, 2 ) . '/snippets/fmc-help.bundle.php';
		$this->assertFileIsReadable( $path, 'no está el snippet de la ayuda: ejecute `make bundle`' );
		return $path;
	}

	/**
	 * Load the help snippet, as Code Snippets would.
	 *
	 * @return void
	 */
	private function load_snippet(): void {
		require_once $this->snippet_path();
	}

	/**
	 * Sin el snippet de la ayuda no hay pestaña, y nada se rompe.
	 */
	public function test_without_the_help_snippet_there_is_no_tab() {
		if ( function_exists( 'fmc_help_html' ) ) {
			$this->markTestSkipped( 'Otro test ya cargó el snippet de la ayuda.' );
		}
		$this->assertFalse( Help::available() );
		$this->assertSame( '', Help::tab( false ) );
		$this->assertSame( '', Help::screen()['body'] );
	}

	/**
	 * La cabecera es la que lee Code Snippets, con la versión del CHANGELOG.
	 */
	public function test_the_snippet_header_has_name_and_version() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichero del repositorio.
		$code = (string) file_get_contents( $this->snippet_path() );
		$head = substr( $code, 0, 800 );
		$this->assertStringContainsString( 'Snippet Name: FMC — Ayuda', $head );
		$this->assertStringContainsString( 'Scope: global', $head );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichero del repositorio.
		preg_match( '/^## \[(\d+\.\d+\.\d+)\]/m', (string) file_get_contents( dirname( __DIR__, 2 ) . '/CHANGELOG.md' ), $version );
		$this->assertStringContainsString( '@version ' . $version[1], $head );
	}

	/**
	 * El snippet está al día con `assets/help/`: mismo texto, y cada captura dentro.
	 *
	 * Si alguien cambia la ayuda y no ejecuta `make bundle`, en producción
	 * seguiría la versión vieja sin que nadie lo notara.
	 */
	public function test_the_snippet_matches_its_sources() {
		$this->load_snippet();
		$html = fmc_help_html();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichero del repositorio.
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/help/ayuda.html' );
		preg_match_all( '/src="img\/([a-z0-9-]+)\.webp"/', $source, $images );
		$this->assertNotEmpty( $images[1], 'la ayuda no tiene capturas' );

		foreach ( $images[1] as $name ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- la captura del repositorio, como en su URI `data:`.
			$data = base64_encode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/help/img/' . $name . '.webp' ) );
			$this->assertStringContainsString( 'src="data:image/webp;base64,' . $data . '"', $html, 'la captura ' . $name . ' no está al día: ejecute `make bundle`' );
		}

		$plain = preg_replace( '/src="data:[^"]+"/', 'src=""', $html );
		$this->assertSame( trim( (string) preg_replace( '/src="img\/[^"]+"/', 'src=""', $source ) ), $plain, 'el texto de la ayuda no está al día: ejecute `make bundle`' );
	}

	/**
	 * Cada enlace del índice lleva a una sección que existe, y cada captura dice qué enseña.
	 */
	public function test_the_index_links_and_images_are_complete() {
		$this->load_snippet();
		$html = fmc_help_html();

		preg_match_all( '/href="#([a-z-]+)"/', $html, $links );
		$this->assertNotEmpty( $links[1] );
		foreach ( array_unique( $links[1] ) as $id ) {
			$this->assertStringContainsString( 'id="' . $id . '"', $html, 'el índice apunta a #' . $id . ', que no existe' );
		}

		preg_match_all( '/<img\b[^>]*>/', $html, $images );
		foreach ( $images[0] as $img ) {
			$this->assertMatchesRegularExpression( '/\salt="[^"]{10,}"/', $img, 'una captura sin texto alternativo' );
		}
	}

	/**
	 * Con el snippet, la pestaña sale al final y la pantalla pinta la ayuda.
	 */
	public function test_with_the_snippet_the_tab_and_screen_show_the_help() {
		$this->load_snippet();

		$this->assertTrue( Help::available() );
		$tab = Help::tab( false );
		$this->assertStringContainsString( 'fmc_ayuda=1', $tab );
		$this->assertStringContainsString( '>Ayuda</a>', $tab );
		$this->assertStringNotContainsString( 'aria-current', $tab );
		$this->assertStringContainsString( 'aria-current="page"', Help::tab( true ) );

		$screen = Help::screen();
		$this->assertSame( 'Ayuda', $screen['title'] );
		$this->assertStringStartsWith( '<div class="fmc-help">', $screen['body'] );
		$this->assertStringContainsString( 'id="ayuda-preguntas"', $screen['body'] );
	}

	/**
	 * La ayuda se pide con `?fmc_ayuda=1`.
	 */
	public function test_it_is_asked_with_its_own_argument() {
		$this->assertFalse( Help::asked() );
		$_GET[ Help::ARG ] = '1';
		$this->assertTrue( Help::asked() );
		unset( $_GET[ Help::ARG ] );
	}
}

<?php
/**
 * Tests for the generated Code Snippets bundle.
 *
 * @package Fmc
 */

/**
 * El bundle es un artefacto, y por eso mismo nadie lo mira.
 *
 * `tests/bootstrap.php` carga los módulos de `src/Fmc/` y **se salta** el
 * bundle, así que ni un solo test del resto de la suite lo toca. Lo único que
 * lo vigilaba era el `php -l` de `make bundle`, y eso solo dice que es PHP
 * válido: un empaquetador que se coma media línea por el medio produce PHP
 * perfectamente válido y un aplicativo roto.
 *
 * Estos tests son el contrato del artefacto: que la cabecera que lee Code
 * Snippets siga entera, que el CSS y el JavaScript inlineados lleguen **byte a
 * byte**, y que los comentarios del código se hayan quitado —que es para lo que
 * se quitan—.
 */
class Test_Bundle extends WP_UnitTestCase {

	/**
	 * El bundle generado.
	 *
	 * @return string
	 */
	private function bundle(): string {
		$ruta = dirname( __DIR__, 2 ) . '/snippets/fmc-formacion-app.bundle.php';
		$this->assertFileIsReadable( $ruta, 'no está el bundle: ejecute `make bundle`' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichero del repositorio.
		return (string) file_get_contents( $ruta );
	}

	/**
	 * La cabecera sobrevive al empaquetado, con las cuatro claves que se leen.
	 *
	 * No es decoración: `fmc_parse_snippet_header()` saca de ahí el nombre, el
	 * ámbito y la prioridad con los que el snippet se crea en Code Snippets, y
	 * `make release` busca el `@version` para negarse a publicar un bundle que
	 * no sea el de la versión del CHANGELOG.
	 */
	public function test_the_snippet_header_survives() {
		$bundle = $this->bundle();

		foreach ( array( 'Snippet Name:', 'Description:', 'Scope:', 'Priority:', '@version' ) as $clave ) {
			$this->assertStringContainsString( $clave, $bundle, 'falta ' . $clave . ' en la cabecera' );
		}

		// Y va en las primeras líneas, donde el analizador de cabeceras mira.
		$this->assertStringContainsString( 'Snippet Name:', substr( $bundle, 0, 600 ) );
	}

	/**
	 * El cuerpo va sin comentarios: es lo que hace el snippet manejable.
	 *
	 * Se cuenta sobre el código, no sobre el fichero entero: la cabecera es un
	 * docblock a propósito, y dentro del JavaScript inlineado hay comentarios
	 * que son **contenido de ese fichero** y tienen que seguir ahí.
	 */
	public function test_the_body_carries_no_php_comments() {
		$bundle = $this->bundle();

		$docblocks = preg_match_all( '#^\s*/\*\*#m', $bundle );
		$this->assertLessThanOrEqual(
			1,
			$docblocks,
			'el cuerpo del bundle trae docblocks: solo tenía que quedar el de la cabecera'
		);

		$this->assertStringNotContainsString(
			'@package Fmc',
			substr( $bundle, 600 ),
			'los docblocks de los ficheros de src/Fmc no se han quitado'
		);
	}

	/**
	 * El CSS y el JavaScript llegan **byte a byte**.
	 *
	 * Es la comprobación que importa de verdad, porque es la que `php -l` no
	 * puede hacer: el CSS y el JavaScript viajan **dentro de cadenas PHP**, así
	 * que cualquier cosa que el empaquetador les haga por el medio —recortar
	 * espacios, colapsar líneas, normalizar saltos— produce PHP perfectamente
	 * válido y un aplicativo que se ve mal. Hoy los assets no tienen rachas de
	 * líneas en blanco que se puedan colapsar; mañana sí, y entonces esto es lo
	 * único que lo dice.
	 */
	public function test_the_inlined_assets_are_byte_identical() {
		if ( ! is_dir( dirname( __DIR__, 2 ) . '/assets' ) ) {
			$this->markTestSkipped( 'todavía no hay assets/: el bundle no inlinea nada' );
		}
		$bundle = $this->bundle();

		$this->assertSame(
			1,
			preg_match( '/set_inline\( (array \(.*?\n\) )\);/s', $bundle, $coincidencia ),
			'no se encontró el mapa de assets inlineados'
		);

		// phpcs:ignore Squiz.PHP.Eval.Discouraged -- es el propio artefacto del repositorio, recién generado.
		$inlineados = eval( 'return ' . $coincidencia[1] . ';' );
		$this->assertIsArray( $inlineados );
		$this->assertNotEmpty( $inlineados, 'el bundle no lleva ningún asset dentro' );

		$raiz = dirname( __DIR__, 2 ) . '/assets/';
		foreach ( $inlineados as $rel => $contenido ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichero del repositorio.
			$real = (string) file_get_contents( $raiz . $rel );
			$this->assertSame( $real, $contenido, $rel . ' no llega igual al bundle' );
		}
	}

	/**
	 * Y la guarda del doble `eval()` sigue detrás del primer `namespace`.
	 */
	public function test_the_double_eval_guard_is_in_place() {
		$bundle = $this->bundle();

		$this->assertSame(
			1,
			preg_match( '/namespace [^;]+;(.*?)FMC_BUNDLE_LOADED/s', $bundle, $entre ),
			'la guarda FMC_BUNDLE_LOADED no está detrás del primer namespace'
		);
		// Entre el namespace y la guarda solo puede haber la comprobación de
		// acceso directo: cualquier otra cosa sería código que corre dos veces.
		$this->assertStringContainsString( "defined( 'ABSPATH' )", $entre[1] );
	}

	/**
	 * Ningún método se llama como una función nativa de PHP.
	 *
	 * El validador de Code Snippets busca `function nombre` sin mirar si está
	 * dentro de una clase: un método `count()` le parece una redeclaración de
	 * `count()` y desactiva el snippet al guardarlo.
	 */
	public function test_no_method_shares_a_name_with_a_php_function() {
		preg_match_all( '/\bfunction\s+(\w+)\s*\(/', $this->bundle(), $nombres );
		$nativas = array_flip( get_defined_functions()['internal'] );
		$choques = array_filter( $nombres[1], static fn( $n ) => isset( $nativas[ strtolower( $n ) ] ) );

		$this->assertSame( array(), array_values( $choques ) );
	}
}

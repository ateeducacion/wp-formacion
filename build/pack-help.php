<?php
/**
 * Pack assets/help into the «FMC — Ayuda» snippet, screenshots included.
 *
 * Usage (host):
 *   php build/pack-help.php
 *
 * Writes: snippets/fmc-help.bundle.php
 *
 * @package Fmc
 */

$root = dirname( __DIR__ );
$dir  = $root . '/assets/help';
$out  = $root . '/snippets/fmc-help.bundle.php';

// Misma versión que el aplicativo: las dos cosas se publican juntas.
$changelog = (string) file_get_contents( $root . '/CHANGELOG.md' );
if ( ! preg_match( '/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $fmc_version ) ) {
	fwrite( STDERR, "No version heading (## [X.Y.Z]) found in CHANGELOG.md\n" );
	exit( 1 );
}

$html = (string) file_get_contents( $dir . '/ayuda.html' );
if ( '' === trim( $html ) ) {
	fwrite( STDERR, "Empty or missing assets/help/ayuda.html\n" );
	exit( 1 );
}

// Code Snippets 4 cuenta llaves para saltarse los cuerpos al validar, también
// dentro de una cadena: una llave en el texto lo descuadraría (AGENTS.md).
if ( false !== strpbrk( $html, '{}' ) ) {
	fwrite( STDERR, "assets/help/ayuda.html contains a brace; Code Snippets would reject the snippet.\n" );
	exit( 1 );
}

// Las capturas van dentro, como `data:`: en producción no hay ficheros que
// servir (ADR-0001), y así la ayuda es un solo snippet (ADR-0026).
$types = array(
	'webp' => 'image/webp',
	'png'  => 'image/png',
	'jpg'  => 'image/jpeg',
);
$used  = array();
$html  = preg_replace_callback(
	'/\ssrc="img\/([a-z0-9-]+\.(webp|png|jpg))"/',
	static function ( array $m ) use ( $dir, $types, &$used ): string {
		$path = $dir . '/img/' . $m[1];
		if ( ! is_readable( $path ) ) {
			fwrite( STDERR, "Missing screenshot: assets/help/img/{$m[1]}\n" );
			exit( 1 );
		}
		$used[ $m[1] ] = true;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- la captura, como URI `data:`; no es código.
		return ' src="data:' . $types[ $m[2] ] . ';base64,' . base64_encode( (string) file_get_contents( $path ) ) . '"';
	},
	$html
);
if ( preg_match( '/\ssrc="(?!data:)/', (string) $html ) ) {
	fwrite( STDERR, "assets/help/ayuda.html has an image that is not under img/ (or not webp, png or jpg).\n" );
	exit( 1 );
}

// Una captura que nadie usa engorda el repositorio y no sale en ningún sitio.
foreach ( (array) glob( $dir . '/img/*' ) as $file ) {
	if ( ! isset( $used[ basename( (string) $file ) ] ) ) {
		fwrite( STDERR, 'Unused screenshot: assets/help/img/' . basename( (string) $file ) . "\n" );
		exit( 1 );
	}
}

$header = <<<'HDR'
<?php
/**
 * Snippet Name: FMC — Ayuda
 * Description: La pestaña «Ayuda» del aplicativo de formación: guías paso a paso con capturas. Código generado desde assets/help — no editar a mano; ejecutar make bundle.
 * Scope: global
 * Priority: 10
 *
 * @package Fmc
 * @version {{VERSION}}
 */

// phpcs:disable

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'fmc_help_html' ) ) {
	function fmc_help_html(): string {
		return {{HTML}};
	}
}

// phpcs:enable

HDR;

$code = str_replace(
	array( '{{VERSION}}', '{{HTML}}' ),
	array( $fmc_version[1], var_export( trim( (string) $html ), true ) ),
	$header
);

if ( false === file_put_contents( $out, $code ) ) {
	fwrite( STDERR, "Cannot write {$out}\n" );
	exit( 1 );
}

$lint   = array();
$status = 0;
// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Build script, host-only.
exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $out ) . ' 2>&1', $lint, $status );
if ( 0 !== $status ) {
	fwrite( STDERR, implode( "\n", $lint ) . "\nHelp snippet is not valid PHP.\n" );
	exit( 1 );
}

fwrite( STDOUT, "Wrote {$out} (" . filesize( $out ) . " bytes, syntax OK)\n" );

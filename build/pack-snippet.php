<?php
/**
 * Bundle src/Fmc into a single Code Snippets-compatible PHP file.
 *
 * Usage (host):
 *   php build/pack-snippet.php
 *
 * Writes: snippets/fmc-formacion-app.bundle.php
 *
 * @package Fmc
 */

$root    = dirname( __DIR__ );
$src_dir = $root . '/src/Fmc';
$out     = $root . '/snippets/fmc-formacion-app.bundle.php';

// The changelog's top version heading is the single source of truth.
$changelog = (string) file_get_contents( $root . '/CHANGELOG.md' );
if ( ! preg_match( '/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $fmc_version ) ) {
	fwrite( STDERR, "No version heading (## [X.Y.Z]) found in CHANGELOG.md\n" );
	exit( 1 );
}

$order = require $src_dir . '/load-order.php';

// Guard: every source file must be in the load order, or it silently would not ship.
$ignored = array( 'bootstrap.php', 'load-order.php' );
$files   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src_dir, FilesystemIterator::SKIP_DOTS ) );
foreach ( $files as $file ) {
	if ( 'php' !== $file->getExtension() ) {
		continue;
	}
	$rel = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $src_dir ) + 1 ) );
	if ( ! in_array( $rel, $ignored, true ) && ! in_array( $rel, $order, true ) ) {
		fwrite( STDERR, "Not in src/Fmc/load-order.php: {$rel}\n" );
		exit( 1 );
	}
}

$header = <<<'HDR'
<?php
/**
 * Snippet Name: FMC — Aplicativo de formación (CPT)
 * Description: Diseños de curso, acciones formativas, ponentes e incidencias, sus taxonomías y los permisos por campo. Código generado desde src/Fmc — no editar a mano; ejecutar php build/pack-snippet.php.
 * Scope: global
 * Priority: 15
 *
 * @package Fmc
 * @version {{VERSION}}
 */

// phpcs:disable

HDR;

$header = str_replace( '{{VERSION}}', $fmc_version[1], $header );

$body = '';
foreach ( $order as $rel ) {
	$path = $src_dir . '/' . $rel;
	if ( ! is_readable( $path ) ) {
		fwrite( STDERR, "Missing: {$path}\n" );
		exit( 1 );
	}
	$code = file_get_contents( $path );
	if ( false === $code ) {
		fwrite( STDERR, "Cannot read {$path}\n" );
		exit( 1 );
	}
	// Strip opening PHP tag from each unit.
	$code = preg_replace( '/^\s*<\?php\s*/', '', $code );
	// Code Snippets re-evaluates an active snippet when it is saved. PHP wants
	// `namespace` first, so the guard goes right after the first one. It checks
	// a constant, not class_exists( App ): parentless classes are early-bound
	// while the eval()'d code compiles, so App would already exist on the first
	// pass and the bundle would return before booting anything.
	if ( $rel === $order[0] ) {
		$code = preg_replace(
			'/^namespace [^;]+;/m',
			"$0\n\n// La guarda de acceso directo va AQUÍ y no en la cabecera: antes de un\n// `namespace` no puede haber ninguna sentencia, y el bundle empieza por el\n// suyo. Code Snippets evalúa este código, no lo incluye, así que no hay\n// acceso directo que valga; va igual por si el fichero acaba servido.\ndefined( 'ABSPATH' ) || exit;\n\n// Code Snippets vuelve a evaluar un snippet activo al guardarlo: si el\n// bundle ya se cargó hay que salir, o el segundo eval() muere redeclarando\n// clases. Una constante, porque class_exists() ya es cierto al compilar.\nif ( \\defined( 'FMC_BUNDLE_LOADED' ) ) {\n\treturn;\n}\n\\define( 'FMC_BUNDLE_LOADED', true );",
			(string) $code,
			1,
			$n
		);
		if ( 1 !== $n ) {
			fwrite( STDERR, "First unit must start with a namespace: {$rel}\n" );
			exit( 1 );
		}
	}
	$body .= "\n// ---- src/Fmc/{$rel} ----\n";
	$body .= trim( $code ) . "\n";
}

// Los assets viajan dentro del bundle: en producción no hay repositorio en
// disco ni URL de plugin, así que un `src` registrado daría 404 y el aplicativo
// se vería sin hoja de estilos. Véase src/Fmc/PublicFront/Assets.php.
// La lista sale del directorio y no de aquí: escrita a mano, un fichero nuevo se
// olvida, `Assets::contents()` devuelve cadena vacía y el guion sencillamente no
// sale en la página, sin error que lo delate. El orden de este mapa da igual: es
// una búsqueda por ruta, y quien decide en qué orden se cargan es
// `Assets::register_assets()`.
/**
 * Strip every comment from the bundled body, keeping its line structure.
 *
 * El bundle es un artefacto: se pega en Code Snippets, no se lee ni se edita
 * —AGENTS.md lo prohíbe— y `src/Fmc/` conserva los comentarios enteros, que es
 * donde se leen. Quitarlos aquí hace el snippet bastante más pequeño y más
 * manejable en el editor del plugin, que es lo que se pidió.
 *
 * Se hace con el analizador léxico de PHP y **no con expresiones regulares**:
 * un `//` dentro de una cadena o una URL no es un comentario, y un reemplazo a
 * ciegas se llevaría por delante medio fichero. `token_get_all()` sabe la
 * diferencia.
 *
 * Cada comentario se sustituye por sus propios saltos de línea, no por nada:
 * así el número de línea del bundle sigue significando algo cuando PHP informa
 * de un error, y la guarda del empaquetador —que el primer fichero abra con su
 * `namespace`— se sigue viendo donde estaba.
 *
 * **La cabecera no pasa por aquí.** Es un docblock y la leen dos cosas:
 * `fmc_parse_snippet_header()` al sincronizar —de ahí salen el nombre, el
 * ámbito y la prioridad del snippet— y `make release`, que busca su
 * `@version`. Sin ella el snippet no se puede publicar.
 *
 * @param string $code Concatenated body of the bundle.
 * @return string The same code without comments.
 */
function fmc_strip_comments( string $code ): string {
	// El `<?php` de entrada no es decorativo: sin él `token_get_all()` empieza
	// en modo HTML y devuelve el fichero entero como un solo T_INLINE_HTML…
	// hasta que tropieza con un `<?php` metido dentro de una cadena, y a
	// partir de ahí tokeniza desincronizado. Se le da la apertura y se le quita
	// después el token que corresponde.
	$tokens = token_get_all( '<?php ' . $code );
	$out    = '';
	$abrio  = false;

	foreach ( $tokens as $token ) {
		if ( ! $abrio && is_array( $token ) && T_OPEN_TAG === $token[0] ) {
			$abrio = true;
			continue;
		}
		if ( is_string( $token ) ) {
			$out .= $token;
			continue;
		}
		if ( T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
			// Los saltos que tuviera dentro se conservan; el texto no.
			$out .= str_repeat( "\n", substr_count( $token[1], "\n" ) );
			continue;
		}
		$out .= $token[1];
	}

	// Un comentario que ocupaba su línea deja la sangría suelta detrás. Se
	// recoge, pero **solo eso**: colapsar líneas en blanco tocaría también las
	// de dentro de las cadenas del CSS y el JavaScript inlineados, que son
	// contenido y no formato.
	$out = (string) preg_replace( '/^[ \t]+$/m', '', $out );

	return $out;
}

$assets = array();
foreach ( array( 'css', 'js' ) as $tipo ) {
	$encontrados = is_dir( $root . '/assets/' . $tipo ) ? glob( $root . '/assets/' . $tipo . '/*.' . $tipo ) : array();
	if ( false === $encontrados ) {
		fwrite( STDERR, "Cannot list assets/{$tipo}\n" );
		exit( 1 );
	}
	foreach ( $encontrados as $path ) {
		$assets[] = $tipo . '/' . basename( $path );
	}
}
sort( $assets );

$inlined = array();
foreach ( $assets as $rel ) {
	$path = $root . '/assets/' . $rel;
	$data = is_readable( $path ) ? file_get_contents( $path ) : false;
	if ( false === $data ) {
		fwrite( STDERR, "Cannot read asset: {$path}\n" );
		exit( 1 );
	}
	$inlined[ $rel ] = $data;
}

// Sin assets todavía no hay a quién dárselos: la clase que los sirve llega con
// la primera pantalla propia.
if ( array() !== $inlined ) {
	$body .= "\n// ---- assets/ (inlined) ----\n";
	$body .= '\Fmc\PublicFront\Assets::set_inline( ' . var_export( $inlined, true ) . " );\n";
}

$footer = <<<'FTR'

if ( \class_exists( \Fmc\App::class ) ) {
	\Fmc\App::boot();
}

// phpcs:enable

FTR;

$body = fmc_strip_comments( $body );

if ( false === file_put_contents( $out, $header . $body . $footer ) ) {
	fwrite( STDERR, "Cannot write {$out}\n" );
	exit( 1 );
}

// The test suite loads the modular sources, never the bundle: a per-file legal
// `declare(strict_types=1)` becomes a fatal once concatenated. Lint the result.
$lint   = array();
$status = 0;
// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Build script, host-only.
exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $out ) . ' 2>&1', $lint, $status );
if ( 0 !== $status ) {
	fwrite( STDERR, implode( "\n", $lint ) . "\nBundle is not valid PHP; fix the source and rebuild.\n" );
	exit( 1 );
}

fwrite( STDOUT, "Wrote {$out} (" . filesize( $out ) . " bytes, syntax OK)\n" );

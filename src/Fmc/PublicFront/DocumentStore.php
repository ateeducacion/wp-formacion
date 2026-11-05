<?php
/**
 * The closed store of the documents of the designs.
 *
 * @package Fmc
 */

namespace Fmc\PublicFront;

/**
 * Dónde viven los ficheros de los documentos y cómo se abren (ADR-0025).
 *
 * En `uploads/fmc-private/`, con nombre aleatorio y en modo `0200`: se
 * escriben, no se leen. Para leer uno se abre (`0400`), se lee entero y se
 * vuelve a cerrar. Quién puede pedirlo no se decide aquí, sino en
 * {@see Documents::verdict()}: esta clase solo guarda, abre y cierra.
 */
final class DocumentStore {

	/**
	 * Directory of the private store, under the uploads base.
	 */
	public const DIR = 'fmc-private';

	/**
	 * At rest: the owner may write, nobody may read.
	 */
	public const MODE_CLOSED = 0200;

	/**
	 * While the application reads it.
	 */
	private const MODE_OPEN = 0400;

	/**
	 * Absolute path of the private store of this site.
	 *
	 * @return string
	 */
	public static function root(): string {
		return wp_normalize_path( (string) wp_upload_dir( null, false )['basedir'] ) . '/' . self::DIR;
	}

	/**
	 * Whether a path is inside the private store.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	public static function is_private( string $path ): bool {
		$dir = realpath( dirname( $path ) );
		return false !== $dir && 0 === strpos( wp_normalize_path( $dir ) . '/', self::root() . '/' );
	}

	/**
	 * Create the private store, with its deny rules, the first time.
	 *
	 * El `.htaccess` es un cinturón y no el pantalón: nginx no lo lee. Lo que
	 * cierra el paso es el modo del fichero y que la descarga pase por el
	 * aplicativo; esto se pone porque en Apache es gratis (ADR-0025).
	 *
	 * @return bool
	 */
	public static function prepare(): bool {
		$root = self::root();
		if ( ! wp_mkdir_p( $root ) ) {
			return false;
		}
		$guards = array(
			'.htaccess' => "# Los documentos de los diseños los sirve el aplicativo (ADR-0025).\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n",
			'index.php' => "<?php\n// Silence is golden.\n",
		);
		foreach ( $guards as $name => $contents ) {
			if ( ! file_exists( $root . '/' . $name ) ) {
				file_put_contents( $root . '/' . $name, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- guarda del almacén privado.
			}
		}
		return true;
	}

	/**
	 * Close a stored file: writable, not readable.
	 *
	 * @param string $path Absolute path.
	 * @return bool Whether it is closed now.
	 */
	public static function close( string $path ): bool {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- el modo es la protección (ADR-0025).
		$done = '' !== $path && chmod( $path, self::MODE_CLOSED );
		clearstatcache( true, $path );
		return $done;
	}

	/**
	 * Move a document stored the old way into the private store.
	 *
	 * Para lo que ya estaba subido y para lo que traiga la migración por su
	 * cuenta. Idempotente: uno que ya está dentro solo se vuelve a cerrar. De una
	 * imagen se conserva el original y se borran sus miniaturas.
	 *
	 * @param int $attachment_id Attachment with a kind.
	 * @return string Empty when done, the problem otherwise.
	 */
	public static function protect( int $attachment_id ): string {
		$path = self::file_of( $attachment_id );
		if ( '' === $path ) {
			return 'El documento ' . $attachment_id . ' no tiene fichero.';
		}
		if ( self::is_private( $path ) ) {
			return self::closed( $attachment_id, $path );
		}
		$source = self::source_of( $attachment_id, $path );
		$target = self::move( $source );
		if ( '' === $target ) {
			return 'No se ha podido mover el documento ' . $attachment_id . '.';
		}
		// Único: si ya tiene nombre, se queda el que tenía.
		add_post_meta( $attachment_id, Documents::NAME_META, wp_basename( $path ), true );
		self::drop_copies( $attachment_id, $path, $source, $target );
		return self::closed( $attachment_id, $target );
	}

	/**
	 * The file to keep: of a big image, the original and not the reduced copy.
	 *
	 * @param int    $attachment_id Attachment.
	 * @param string $path          Attached file.
	 * @return string
	 */
	private static function source_of( int $attachment_id, string $path ): string {
		$original = (string) wp_get_original_image_path( $attachment_id );
		return is_file( $original ) ? $original : $path;
	}

	/**
	 * The file of a document, if it is one and its file is there.
	 *
	 * @param int $attachment_id Attachment.
	 * @return string Absolute path, or empty.
	 */
	private static function file_of( int $attachment_id ): string {
		$path = (string) get_attached_file( $attachment_id );
		$kind = (string) get_post_meta( $attachment_id, Documents::KIND_META, true );
		return '' !== $kind && is_file( $path ) ? $path : '';
	}

	/**
	 * Close a document's file and say so.
	 *
	 * @param int    $attachment_id Attachment.
	 * @param string $path          Its file.
	 * @return string Empty when closed, the problem otherwise.
	 */
	private static function closed( int $attachment_id, string $path ): string {
		return self::close( $path ) ? '' : 'No se ha podido cerrar el documento ' . $attachment_id . '.';
	}

	/**
	 * Move a file into the store, under a random name.
	 *
	 * @param string $source Absolute path.
	 * @return string Where it is now; empty when it could not be moved.
	 */
	private static function move( string $source ): string {
		$hex    = bin2hex( random_bytes( 16 ) );
		$target = self::root() . '/' . substr( $hex, 0, 2 ) . '/' . $hex . '.' . strtolower( pathinfo( $source, PATHINFO_EXTENSION ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- se mueve dentro del mismo directorio de subidas.
		$moved = self::prepare() && wp_mkdir_p( dirname( $target ) ) && rename( $source, $target );
		return $moved ? $target : '';
	}

	/**
	 * The bytes of a document, opening it only while it is read.
	 *
	 * Se abre (`0400`), se lee entero y se vuelve a cerrar (`0200`) en el
	 * `finally`, antes de mandar nada por la red. Las lecturas se ponen en fila
	 * con un cerrojo: si no, una que cierra le quitaría el fichero a otra que
	 * lo acaba de abrir. Lo que aún no se ha movido al almacén se lee sin más.
	 *
	 * @param int $attachment_id Attachment.
	 * @return string|null Null when it is not there or could not be read.
	 */
	public static function read( int $attachment_id ): ?string {
		$path = (string) get_attached_file( $attachment_id );
		if ( '' === $path || ! file_exists( $path ) ) {
			return null;
		}
		if ( ! self::is_private( $path ) ) {
			$bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- documento local, aún sin mover.
			return false === $bytes ? null : $bytes;
		}

		// ponytail: un cerrojo para todo el almacén; uno por fichero si alguna vez hay cola.
		$lock = fopen( self::root() . '/.lock', 'c' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- cerrojo de las lecturas.
		if ( false === $lock ) {
			return null;
		}
		$try = 0;
		while ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
			if ( ++$try > 50 ) {
				fclose( $lock ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- cerrojo de las lecturas.
				return null;
			}
			usleep( 100000 );
		}
		$bytes = false;
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- se abre solo mientras se lee (ADR-0025).
			chmod( $path, self::MODE_OPEN );
			clearstatcache( true, $path );
			// ponytail: el documento entero en memoria (hasta 50 MB); leer a trozos si el límite de memoria se queda corto.
			$bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- documento del almacén privado.
		} finally {
			if ( ! self::close( $path ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- aviso para administración, sin ruta ni contenido.
				error_log( 'fmc: no se ha podido volver a cerrar el documento ' . $attachment_id );
			}
			flock( $lock, LOCK_UN );
			fclose( $lock ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- cerrojo de las lecturas.
		}
		return false === $bytes ? null : $bytes;
	}

	/**
	 * Point the attachment at its new file, and delete the copies WordPress made next to the old one.
	 *
	 * Las miniaturas, y la versión reducida si el documento es el original de
	 * una imagen grande: copias legibles que no deben quedarse fuera.
	 *
	 * @param int    $attachment_id Attachment.
	 * @param string $path          Attached file, before moving.
	 * @param string $source        File that was moved.
	 * @param string $target        Where it is now.
	 * @return void
	 */
	private static function drop_copies( int $attachment_id, string $path, string $source, string $target ): void {
		update_attached_file( $attachment_id, $target );
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $meta ) ) {
			return;
		}
		foreach ( (array) ( $meta['sizes'] ?? array() ) as $size ) {
			wp_delete_file( dirname( $path ) . '/' . $size['file'] );
		}
		if ( $source !== $path ) {
			wp_delete_file( $path );
		}
		$meta['sizes'] = array();
		$meta['file']  = _wp_relative_upload_path( $target );
		unset( $meta['original_image'] );
		wp_update_attachment_metadata( $attachment_id, $meta );
	}
}

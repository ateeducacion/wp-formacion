<?php
/**
 * The documents of a design: upload, replace and remove, by kind.
 *
 * @package Fmc
 */

namespace Fmc\PublicFront;

use Fmc\PostType\PostTypes;

/**
 * Los documentos de un diseño son adjuntos de WordPress colgados de él, con el
 * fichero en un almacén privado (ADR-0025).
 *
 * Siguen siendo adjuntos para que la lista, el orden y la imagen destacada los
 * lleve WordPress; lo que cambia es dónde está el fichero y quién lo sirve. El
 * fichero va al almacén cerrado de {@see DocumentStore}, con nombre aleatorio,
 * y lo sirve el aplicativo en `?fmc_documento=ID`
 * tras preguntar si quien lo pide puede leer el diseño. Nunca el servidor web:
 * un borrador o un histórico solo los lee quien los escribe (ADR-0016), y eso
 * no vale nada si el fichero se descarga por su dirección física.
 *
 * La excepción es la ficha técnica —la clase «Diseño del curso»— de un diseño
 * que está en el catálogo: esa es pública, como lo es la ficha del catálogo.
 *
 * La clase de cada adjunto va en su meta `_fmc_kind`, y el nombre con el que
 * se subió, en `_fmc_name`. Cada clase admite sus formatos y ni uno más: se
 * comprueba el tipo real del fichero, no su extensión.
 */
final class Documents {

	/**
	 * Attachment meta holding the kind.
	 */
	public const KIND_META = '_fmc_kind';

	/**
	 * Attachment meta holding the name the file was uploaded with.
	 */
	public const NAME_META = '_fmc_name';

	/**
	 * `?fmc_documento=ID`: the download of one document.
	 */
	public const ARG = 'fmc_documento';

	/**
	 * Kinds that anyone may download when their design is in the catalogue.
	 */
	public const PUBLIC_KINDS = array( 'design' );

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wp_get_attachment_url', array( self::class, 'attachment_url' ), 10, 2 );
	}

	/**
	 * URL of a document: the application's, never the file's.
	 *
	 * @param int $attachment_id Attachment.
	 * @return string
	 */
	public static function url( int $attachment_id ): string {
		return add_query_arg( self::ARG, $attachment_id, home_url( '/' ) );
	}

	/**
	 * The URL WordPress gives for a document is the application's.
	 *
	 * Así ninguna pantalla —la mediateca, la REST, una plantilla— reparte la
	 * dirección física: toda descarga pasa por la comprobación.
	 *
	 * @param string|false $url           URL WordPress computed.
	 * @param int          $attachment_id Attachment.
	 * @return string|false
	 */
	public static function attachment_url( $url, $attachment_id ) {
		return '' === (string) get_post_meta( (int) $attachment_id, self::KIND_META, true ) ? $url : self::url( (int) $attachment_id );
	}

	/**
	 * Name to show for a document: the one it was uploaded with.
	 *
	 * @param int $attachment_id Attachment.
	 * @return string
	 */
	public static function name( int $attachment_id ): string {
		$name = (string) get_post_meta( $attachment_id, self::NAME_META, true );
		return '' !== $name ? $name : basename( (string) get_attached_file( $attachment_id ) );
	}

	/**
	 * The kinds of document, with what each one accepts.
	 *
	 * @return array<string, array{label:string, help:string, mimes:array<string, string>, multiple:bool, max_mb:int}>
	 */
	public static function kinds(): array {
		// Del diseño y de la escaleta se suben los editables; para conservar la
		// versión, los recursos en PDF (issue #11).
		$texts  = array(
			'doc'  => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'odt'  => 'application/vnd.oasis.opendocument.text',
		);
		$slides = array(
			'ppt'  => 'application/vnd.ms-powerpoint',
			'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'odp'  => 'application/vnd.oasis.opendocument.presentation',
		);
		$pdf    = array( 'pdf' => 'application/pdf' );
		return array(
			'image'    => array(
				'label'    => 'Imagen representativa',
				'help'     => 'JPG, PNG o GIF, de hasta 3 MB. Sale en el catálogo.',
				'mimes'    => array(
					'jpg|jpeg' => 'image/jpeg',
					'png'      => 'image/png',
					'gif'      => 'image/gif',
				),
				'multiple' => false,
				'max_mb'   => 3,
			),
			'design'   => array(
				'label'    => 'Diseño del curso',
				'help'     => 'El editable: DOC, DOCX u ODT.',
				'mimes'    => $texts,
				'multiple' => true,
				'max_mb'   => 20,
			),
			'sessions' => array(
				'label'    => 'Escaleta de las sesiones',
				'help'     => 'El editable: DOC, DOCX u ODT.',
				'mimes'    => $texts,
				'multiple' => true,
				'max_mb'   => 20,
			),
			'slides'   => array(
				'label'    => 'Presentaciones',
				'help'     => 'Las editables: PPT, PPTX u ODP.',
				'mimes'    => $slides,
				'multiple' => true,
				'max_mb'   => 50,
			),
			'support'  => array(
				'label'    => 'Recursos en PDF',
				'help'     => 'En PDF, para conservar la versión.',
				'mimes'    => $pdf,
				'multiple' => true,
				'max_mb'   => 50,
			),
			'extra'    => array(
				'label'    => 'Otros documentos',
				'help'     => 'Cualquier otro documento del diseño: PDF, DOC, DOCX, ODT o ZIP.',
				'mimes'    => array_merge( $pdf, $texts, array( 'zip' => 'application/zip' ) ),
				'multiple' => true,
				'max_mb'   => 50,
			),
		);
	}

	/**
	 * Attachments of a design, by kind.
	 *
	 * @param int $post_id Design.
	 * @return array<string, \WP_Post[]>
	 */
	public static function of( int $post_id ): array {
		$out = array_fill_keys( array_keys( self::kinds() ), array() );
		if ( ! $post_id ) {
			return $out;
		}
		foreach ( get_children(
			array(
				'post_parent' => $post_id,
				'post_type'   => 'attachment',
				'orderby'     => 'menu_order date',
				'order'       => 'ASC',
			)
		) as $attachment ) {
			$kind = (string) get_post_meta( $attachment->ID, self::KIND_META, true );
			if ( isset( $out[ $kind ] ) ) {
				$out[ $kind ][] = $attachment;
			}
		}
		return $out;
	}

	/**
	 * Whether the current user may change the documents of a design.
	 *
	 * @param int $post_id Design.
	 * @return bool
	 */
	public static function can_manage( int $post_id ): bool {
		return current_user_can( 'upload_files' ) && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Check one uploaded file against its kind.
	 *
	 * @param string               $kind Kind.
	 * @param array<string, mixed> $file One entry shaped like `$_FILES['x']`.
	 * @return string Empty when valid, the reason otherwise.
	 */
	public static function check( string $kind, array $file ): string {
		$def = self::kinds()[ $kind ] ?? null;
		if ( ! $def ) {
			return 'Clase de documento desconocida.';
		}
		if ( ! empty( $file['error'] ) ) {
			return 'No se pudo subir «' . $file['name'] . '».';
		}
		if ( (int) $file['size'] > $def['max_mb'] * MB_IN_BYTES ) {
			return '«' . $file['name'] . '» pasa de ' . $def['max_mb'] . ' MB.';
		}
		$type = wp_check_filetype_and_ext( (string) $file['tmp_name'], (string) $file['name'], $def['mimes'] );
		if ( empty( $type['type'] ) || ! in_array( $type['type'], $def['mimes'], true ) ) {
			return '«' . $file['name'] . '» no es de un formato admitido aquí: ' . $def['help'];
		}
		return '';
	}

	/**
	 * Apply uploads, replacements and removals to a design.
	 *
	 * @param int                  $post_id Design.
	 * @param array<string, mixed> $files   Normalised uploads: kind => list of file arrays, and `replace` => attachment ID => file array.
	 * @param int[]                $remove  Attachment IDs to remove.
	 * @return string[] Problems, one per file that did not make it.
	 */
	public static function apply( int $post_id, array $files, array $remove ): array {
		if ( PostTypes::DESIGN !== get_post_type( $post_id ) || ! self::can_manage( $post_id ) ) {
			return array( 'No tiene permiso para cambiar los documentos.' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$problems = array();
		$mine     = static function ( int $attachment_id ) use ( $post_id ): bool {
			return 'attachment' === get_post_type( $attachment_id ) && (int) get_post_field( 'post_parent', $attachment_id ) === $post_id;
		};

		foreach ( $remove as $attachment_id ) {
			if ( $mine( (int) $attachment_id ) ) {
				wp_delete_attachment( (int) $attachment_id, true );
			}
		}

		foreach ( (array) ( $files['replace'] ?? array() ) as $attachment_id => $file ) {
			$attachment_id = (int) $attachment_id;
			if ( ! $mine( $attachment_id ) ) {
				continue;
			}
			$kind  = (string) get_post_meta( $attachment_id, self::KIND_META, true );
			$error = self::check( $kind, $file );
			if ( '' !== $error ) {
				$problems[] = $error;
				continue;
			}
			$new = self::store( $post_id, $kind, $file );
			if ( is_string( $new ) ) {
				$problems[] = $new;
				continue;
			}
			// Se cambia en el mismo sitio de la lista y se borra el anterior.
			wp_update_post(
				array(
					'ID'         => $new,
					'menu_order' => (int) get_post_field( 'menu_order', $attachment_id ),
				)
			);
			wp_delete_attachment( $attachment_id, true );
		}

		foreach ( self::kinds() as $kind => $def ) {
			$list = array_values( (array) ( $files[ $kind ] ?? array() ) );
			if ( ! $def['multiple'] ) {
				$list = array_slice( $list, 0, 1 );
			}
			foreach ( $list as $file ) {
				$error = self::check( $kind, $file );
				if ( '' !== $error ) {
					$problems[] = $error;
					continue;
				}
				if ( ! $def['multiple'] ) {
					foreach ( self::of( $post_id )[ $kind ] as $old ) {
						wp_delete_attachment( $old->ID, true );
					}
				}
				$new = self::store( $post_id, $kind, $file );
				if ( is_string( $new ) ) {
					$problems[] = $new;
				}
			}
		}

		$image = self::of( $post_id )['image'];
		if ( $image ) {
			set_post_thumbnail( $post_id, $image[0]->ID );
		} else {
			delete_post_thumbnail( $post_id );
		}
		return $problems;
	}

	/**
	 * Store one checked file as an attachment of the design, in the private store.
	 *
	 * El nombre físico es aleatorio y no dice nada —ni el curso ni el fichero
	 * original, que se queda en `_fmc_name`—, y no se generan miniaturas ni
	 * vistas previas: serían copias legibles del documento al lado del cerrado.
	 *
	 * @param int                  $post_id Design.
	 * @param string               $kind    Kind.
	 * @param array<string, mixed> $file    File array.
	 * @return int|string Attachment ID, or the problem.
	 */
	private static function store( int $post_id, string $kind, array $file ) {
		$original = sanitize_text_field( wp_basename( (string) $file['name'] ) );
		$type     = wp_check_filetype_and_ext( (string) $file['tmp_name'], $original, self::kinds()[ $kind ]['mimes'] );
		$hex      = bin2hex( random_bytes( 16 ) );
		if ( ! DocumentStore::prepare() ) {
			return '«' . $original . '»: no se ha podido preparar el almacén de documentos.';
		}
		$file['name'] = $hex . '.' . ( $type['ext'] ? $type['ext'] : strtolower( pathinfo( $original, PATHINFO_EXTENSION ) ) );

		$dir     = static function ( array $dirs ) use ( $hex ): array {
			$dirs['subdir'] = '/' . DocumentStore::DIR . '/' . substr( $hex, 0, 2 );
			$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
			$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
			return $dirs;
		};
		$none    = static fn(): array => array();
		$filters = array(
			'upload_dir'                        => $dir,
			'intermediate_image_sizes_advanced' => $none,
			'fallback_intermediate_image_sizes' => $none,
			'big_image_size_threshold'          => '__return_false',
		);
		foreach ( $filters as $hook => $callback ) {
			add_filter( $hook, $callback );
		}
		try {
			// Sideload y no upload: `from_request()` ya descartó lo que no llegó por
			// una subida de verdad, y así el mismo camino sirve a los tests y a la
			// migración.
			$id = media_handle_sideload( $file, $post_id, null, array( 'post_title' => pathinfo( $original, PATHINFO_FILENAME ) ) );
		} finally {
			foreach ( $filters as $hook => $callback ) {
				remove_filter( $hook, $callback );
			}
		}
		if ( is_wp_error( $id ) ) {
			return '«' . $original . '»: ' . $id->get_error_message();
		}
		update_post_meta( (int) $id, self::KIND_META, $kind );
		update_post_meta( (int) $id, self::NAME_META, $original );
		if ( ! DocumentStore::close( (string) get_attached_file( (int) $id ) ) ) {
			// Un documento que no se puede cerrar no se da por guardado.
			wp_delete_attachment( (int) $id, true );
			return '«' . $original . '»: no se ha podido proteger el fichero.';
		}
		return (int) $id;
	}

	/**
	 * Who may have a document: everyone, someone with a session, or nobody.
	 *
	 * La regla es la del diseño, y no otra: quien puede leer el diseño puede
	 * leer sus documentos (`read_post`, con lo que añade ADR-0016). La ficha
	 * técnica de un diseño del catálogo es pública, como la ficha del catálogo.
	 *
	 * @param int $attachment_id Attachment.
	 * @return string `public`, `allowed`, `login` (ask for a session first) or `denied` (also when it does not exist).
	 */
	public static function verdict( int $attachment_id ): string {
		$kind   = (string) get_post_meta( $attachment_id, self::KIND_META, true );
		$design = get_post( (int) get_post_field( 'post_parent', $attachment_id ) );
		if ( 'attachment' !== get_post_type( $attachment_id ) || ! isset( self::kinds()[ $kind ] ) || ! $design instanceof \WP_Post || PostTypes::DESIGN !== $design->post_type ) {
			return 'denied';
		}
		if ( in_array( $kind, self::PUBLIC_KINDS, true ) && Catalogue::is_listed( $design ) ) {
			return 'public';
		}
		if ( ! is_user_logged_in() ) {
			return 'login';
		}
		return current_user_can( 'read_post', $design->ID ) ? 'allowed' : 'denied';
	}

	/**
	 * Response headers of a download, in order.
	 *
	 * Siempre como descarga: el navegador no interpreta nada de lo que se sirve
	 * desde aquí, `nosniff` le impide adivinar el tipo y `no-store` que se
	 * quede en una caché compartida. Aparte del manejador para poder probarlas.
	 *
	 * @param int $attachment_id Attachment.
	 * @param int $length        Length of the body.
	 * @return string[]
	 */
	public static function headers( int $attachment_id, int $length ): array {
		$name  = self::name( $attachment_id );
		$ascii = sanitize_file_name( remove_accents( $name ) );
		return array(
			'Content-Type: ' . sanitize_mime_type( (string) get_post_mime_type( $attachment_id ) ),
			'Content-Disposition: attachment; filename="' . ( '' !== $ascii ? $ascii : 'documento' ) . '"; filename*=UTF-8\'\'' . rawurlencode( $name ),
			'Content-Length: ' . $length,
			'X-Content-Type-Options: nosniff',
			'Cache-Control: private, no-store',
		);
	}

	/**
	 * What `?fmc_documento=ID` answers, without sending it.
	 *
	 * El identificador no autoriza nada: decide {@see self::verdict()}. Sin
	 * sesión, lo que no es público manda al acceso y vuelve aquí; con sesión y
	 * sin permiso, la respuesta es la misma que si no existiera. Aparte de
	 * {@see self::serve()}, que acaba en `exit`, para poder probarla.
	 *
	 * @param int $attachment_id Attachment.
	 * @return array{redirect?: string, status?: int, headers?: string[], body?: string}
	 */
	public static function answer( int $attachment_id ): array {
		$verdict = self::verdict( $attachment_id );
		if ( 'login' === $verdict ) {
			return array( 'redirect' => wp_login_url( self::url( $attachment_id ) ) );
		}
		$bytes = 'denied' === $verdict ? null : DocumentStore::read( $attachment_id );
		if ( null !== $bytes ) {
			return array(
				'status'  => 200,
				'headers' => self::headers( $attachment_id, strlen( $bytes ) ),
				'body'    => $bytes,
			);
		}
		$nocache = array();
		// Sin `Last-Modified`, que WordPress pone a `false` para que se quite.
		foreach ( array_filter( wp_get_nocache_headers() ) as $name => $value ) {
			$nocache[] = $name . ': ' . $value;
		}
		return array(
			'status'  => 'denied' === $verdict ? 404 : 503,
			'headers' => array_merge( $nocache, array( 'Content-Type: text/plain; charset=utf-8', 'X-Content-Type-Options: nosniff' ) ),
			'body'    => 'denied' === $verdict ? 'Este documento no existe, o no puede verlo.' : 'No se ha podido leer el documento. Pruebe otra vez dentro de un momento.',
		);
	}

	/**
	 * Serve `?fmc_documento=ID` and stop.
	 *
	 * @return void
	 */
	public static function serve(): void {
		$answer = self::answer( absint( Lists::arg( self::ARG ) ) );
		if ( isset( $answer['redirect'] ) ) {
			Screen::leave( $answer['redirect'] );
			return;
		}
		status_header( $answer['status'] );
		foreach ( $answer['headers'] as $line ) {
			header( $line );
		}
		echo $answer['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- el fichero, o un texto fijo: se sirve como descarga o como text/plain, siempre con nosniff.
		exit;
	}

	/**
	 * Regroup `$_FILES['fmc_doc']` and `$_FILES['fmc_replace']` by kind.
	 *
	 * PHP entrega los ficheros de un campo con corchetes «al revés»: una lista
	 * por atributo (name, tmp_name…). Aquí se vuelven una lista de ficheros.
	 *
	 * @param array<string, mixed> $raw `$_FILES`.
	 * @return array<string, mixed>
	 */
	public static function from_request( array $raw ): array {
		$out = array();
		foreach ( array( 'fmc_doc', 'fmc_replace' ) as $field ) {
			if ( empty( $raw[ $field ]['name'] ) || ! is_array( $raw[ $field ]['name'] ) ) {
				continue;
			}
			foreach ( $raw[ $field ]['name'] as $key => $names ) {
				foreach ( (array) $names as $i => $name ) {
					if ( '' === (string) $name ) {
						continue;
					}
					$file = array();
					foreach ( array( 'name', 'type', 'tmp_name', 'error', 'size' ) as $attr ) {
						$value         = $raw[ $field ][ $attr ][ $key ];
						$file[ $attr ] = is_array( $value ) ? $value[ $i ] : $value;
					}
					// Solo lo que PHP recibió como subida: nada de rutas mandadas a mano.
					if ( ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
						continue;
					}
					if ( 'fmc_doc' === $field ) {
						$out[ sanitize_key( (string) $key ) ][] = $file;
					} else {
						$out['replace'][ absint( $key ) ] = $file;
					}
				}
			}
		}
		return $out;
	}
}

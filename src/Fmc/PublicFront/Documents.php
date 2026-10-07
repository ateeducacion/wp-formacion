<?php
/**
 * The documents of a design: upload, replace and remove, by kind.
 *
 * @package Fmc
 */

namespace Fmc\PublicFront;

use Fmc\PostType\PostTypes;

/**
 * Los documentos de un diseño son adjuntos de WordPress colgados de él.
 *
 * Son material editorial —el diseño, el minutaje, el material de apoyo—, no
 * datos personales, así que van a la biblioteca de medios como cualquier otro
 * adjunto (a diferencia de los ficheros de una inscripción en eventos). La
 * clase de cada uno va en la meta `_fmc_kind` del adjunto; la imagen
 * representativa es la imagen destacada del diseño.
 *
 * Cada clase admite los formatos que admitía el formulario anterior, y ni uno
 * más: se comprueba el tipo real del fichero, no su extensión.
 */
final class Documents {

	/**
	 * Attachment meta holding the kind.
	 */
	public const KIND_META = '_fmc_kind';

	/**
	 * The kinds of document, with what each one accepts.
	 *
	 * @return array<string, array{label:string, help:string, mimes:array<string, string>, multiple:bool, max_mb:int}>
	 */
	public static function kinds(): array {
		$texts = array(
			'pdf'  => 'application/pdf',
			'doc'  => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'odt'  => 'application/vnd.oasis.opendocument.text',
		);
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
				'help'     => 'PDF, DOC, DOCX u ODT.',
				'mimes'    => $texts,
				'multiple' => true,
				'max_mb'   => 20,
			),
			'sessions' => array(
				'label'    => 'Minutaje detallado de las sesiones',
				'help'     => 'PDF, DOC, DOCX u ODT.',
				'mimes'    => $texts,
				'multiple' => true,
				'max_mb'   => 20,
			),
			'support'  => array(
				'label'    => 'Documentos de apoyo',
				'help'     => 'PDF, DOC, DOCX o ZIP.',
				'mimes'    => array_merge( array_slice( $texts, 0, 3, true ), array( 'zip' => 'application/zip' ) ),
				'multiple' => true,
				'max_mb'   => 50,
			),
			'extra'    => array(
				'label'    => 'Otros documentos',
				'help'     => 'Cualquier otro documento del diseño: PDF, DOC, DOCX, ODT o ZIP.',
				'mimes'    => array_merge( $texts, array( 'zip' => 'application/zip' ) ),
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
	 * Store one checked file as an attachment of the design.
	 *
	 * @param int                  $post_id Design.
	 * @param string               $kind    Kind.
	 * @param array<string, mixed> $file    File array.
	 * @return int|string Attachment ID, or the problem.
	 */
	private static function store( int $post_id, string $kind, array $file ) {
		$overrides = array(
			'test_form' => false,
			'mimes'     => self::kinds()[ $kind ]['mimes'],
		);
		// Sideload y no upload: `from_request()` ya descartó lo que no llegó por
		// una subida de verdad, y así el mismo camino sirve a los tests.
		$id = media_handle_sideload( $file, $post_id, null, $overrides );
		if ( is_wp_error( $id ) ) {
			return '«' . $file['name'] . '»: ' . $id->get_error_message();
		}
		update_post_meta( (int) $id, self::KIND_META, $kind );
		return (int) $id;
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

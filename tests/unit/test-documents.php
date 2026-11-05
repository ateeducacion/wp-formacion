<?php
/**
 * Tests for the documents of a design: kinds, checks, upload, replace, remove.
 *
 * @package Fmc
 */

use Fmc\PostType\PostTypes;
use Fmc\PublicFront\DocumentStore;
use Fmc\PublicFront\Documents;

/**
 * Los documentos son adjuntos del diseño; se comprueba el tipo real del
 * fichero, no su extensión, y la imagen es la imagen destacada.
 */
class Test_Documents extends WP_UnitTestCase {

	/**
	 * A minimal PDF that `finfo` recognises.
	 */
	private const PDF = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";

	/**
	 * A valid 1×1 PNG.
	 */
	private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

	/**
	 * Roles and caps in place.
	 */
	public function set_up() {
		parent::set_up();
		fmc_register_roles();
		PostTypes::grant_caps_to_roles();
	}

	/**
	 * No session left behind.
	 */
	public function tear_down() {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Permission bits of a file, as `0200`.
	 *
	 * @param string $path Path.
	 * @return int
	 */
	private function mode( string $path ): int {
		clearstatcache( true, $path );
		return fileperms( $path ) & 0777;
	}

	/**
	 * One document of a kind, stored in a design by a curator.
	 *
	 * @param int                  $id   Design.
	 * @param string               $kind Kind.
	 * @param array<string, mixed> $file Upload.
	 * @return int Attachment ID.
	 */
	private function stored( int $id, string $kind, array $file ): int {
		$this->assertSame( array(), Documents::apply( $id, array( $kind => array( $file ) ), array() ) );
		return Documents::of( $id )[ $kind ][0]->ID;
	}

	/**
	 * Validation, logged in, with a draft design of its own.
	 *
	 * @return int Design ID.
	 */
	private function curator_design(): int {
		$curator = self::factory()->user->create( array( 'role' => 'fmc_curator' ) );
		wp_set_current_user( $curator );
		return self::factory()->post->create(
			array(
				'post_type'   => PostTypes::DESIGN,
				'post_author' => $curator,
				'post_status' => 'draft',
			)
		);
	}

	/**
	 * A temporary file shaped like one entry of `$_FILES`.
	 *
	 * @param string $name  File name.
	 * @param string $bytes Contents.
	 * @return array<string, mixed>
	 */
	private function upload( string $name, string $bytes ): array {
		$tmp = wp_tempnam( $name );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fichero de prueba.
		file_put_contents( $tmp, $bytes );
		return array(
			'name'     => $name,
			'type'     => '',
			'tmp_name' => $tmp,
			'error'    => 0,
			'size'     => strlen( $bytes ),
		);
	}

	/**
	 * An ODT upload that `finfo` recognises: a ZIP whose first entry, stored, is its mime type.
	 *
	 * @param string $name File name.
	 * @return array<string, mixed>
	 */
	private function odt( string $name ): array {
		$tmp = wp_tempnam( $name );
		$zip = new ZipArchive();
		$zip->open( $tmp, ZipArchive::OVERWRITE );
		$zip->addFromString( 'mimetype', 'application/vnd.oasis.opendocument.text' );
		$zip->setCompressionName( 'mimetype', ZipArchive::CM_STORE );
		$zip->addFromString( 'content.xml', '<?xml version="1.0" encoding="UTF-8"?><office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"/>' );
		$zip->close();
		return array(
			'name'     => $name,
			'type'     => '',
			'tmp_name' => $tmp,
			'error'    => 0,
			'size'     => (int) filesize( $tmp ),
		);
	}

	/**
	 * A PNG upload.
	 *
	 * @param string $name File name.
	 * @return array<string, mixed>
	 */
	private function png( string $name ): array {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- imagen de prueba.
		return $this->upload( $name, base64_decode( self::PNG ) );
	}

	/**
	 * La imagen es una sola y pequeña; el resto, listas. Del diseño, la
	 * escaleta y las presentaciones, los editables; los recursos, en PDF (#11).
	 */
	public function test_each_kind_accepts_its_own_formats() {
		$kinds = Documents::kinds();

		$this->assertSame( array( 'image', 'design', 'sessions', 'slides', 'support', 'extra' ), array_keys( $kinds ) );
		$this->assertFalse( $kinds['image']['multiple'] );
		$this->assertSame( 3, $kinds['image']['max_mb'] );
		$this->assertTrue( $kinds['design']['multiple'] );
		foreach ( array( 'design', 'sessions' ) as $editable ) {
			$this->assertContains( 'application/vnd.oasis.opendocument.text', $kinds[ $editable ]['mimes'], $editable );
			$this->assertNotContains( 'application/pdf', $kinds[ $editable ]['mimes'], $editable );
		}
		$this->assertContains( 'application/vnd.openxmlformats-officedocument.presentationml.presentation', $kinds['slides']['mimes'] );
		$this->assertContains( 'application/vnd.oasis.opendocument.presentation', $kinds['slides']['mimes'] );
		$this->assertSame( array( 'pdf' => 'application/pdf' ), $kinds['support']['mimes'] );
		$this->assertContains( 'application/zip', $kinds['extra']['mimes'] );
	}

	/**
	 * Lo que no se puede guardar se dice con su motivo.
	 */
	public function test_check_rejects_with_a_reason() {
		$odt = $this->odt( 'diseno.odt' );
		$pdf = $this->upload( 'diseno.pdf', self::PDF );

		$this->assertSame( '', Documents::check( 'design', $odt ) );
		$this->assertSame( '', Documents::check( 'support', $pdf ) );
		$this->assertStringContainsString( '«diseno.pdf» no es de un formato admitido aquí: El editable', Documents::check( 'design', $pdf ), 'del diseño, el editable' );
		$this->assertStringContainsString( 'no es de un formato admitido aquí: En PDF', Documents::check( 'support', $odt ), 'los recursos, en PDF' );
		$this->assertSame( 'Clase de documento desconocida.', Documents::check( 'poster', $odt ) );
		$this->assertSame( 'No se pudo subir «diseno.odt».', Documents::check( 'design', array( 'error' => UPLOAD_ERR_PARTIAL ) + $odt ) );

		$big = $this->png( 'grande.png' );
		$this->assertSame( '', Documents::check( 'image', $big ) );
		$big['size'] = 3 * MB_IN_BYTES + 1;
		$this->assertSame( '«grande.png» pasa de 3 MB.', Documents::check( 'image', $big ) );
	}

	/**
	 * Cuenta el contenido, no la extensión: un texto con .pdf no es un PDF, y una imagen no es un diseño.
	 */
	public function test_check_looks_at_the_real_file_type() {
		$this->assertStringContainsString( 'no es de un formato admitido', Documents::check( 'support', $this->upload( 'falso.pdf', 'esto es texto' ) ) );
		$this->assertStringContainsString( 'no es de un formato admitido', Documents::check( 'design', $this->upload( 'falso.odt', 'esto es texto' ) ) );
		$this->assertStringContainsString( 'no es de un formato admitido', Documents::check( 'design', $this->png( 'foto.png' ) ) );
		$this->assertStringContainsString( 'no es de un formato admitido', Documents::check( 'image', $this->upload( 'diseno.png', self::PDF ) ) );
	}

	/**
	 * La imagen pasa a ser la destacada; una segunda sustituye a la primera, y quitarla la quita.
	 */
	public function test_the_image_is_single_and_becomes_the_thumbnail() {
		$id = $this->curator_design();

		$this->assertSame( array(), Documents::apply( $id, array( 'image' => array( $this->png( 'una.png' ) ) ), array() ) );
		$first = Documents::of( $id )['image'];
		$this->assertCount( 1, $first );
		$this->assertSame( $first[0]->ID, get_post_thumbnail_id( $id ) );
		$this->assertSame( 'image', get_post_meta( $first[0]->ID, Documents::KIND_META, true ) );

		// Dos de golpe: solo cuenta la primera, y la anterior desaparece.
		Documents::apply( $id, array( 'image' => array( $this->png( 'dos.png' ), $this->png( 'tres.png' ) ) ), array() );
		$second = Documents::of( $id )['image'];
		$this->assertCount( 1, $second );
		$this->assertNull( get_post( $first[0]->ID ) );
		$this->assertSame( 'dos.png', Documents::name( $second[0]->ID ) );
		$this->assertSame( $second[0]->ID, get_post_thumbnail_id( $id ) );

		Documents::apply( $id, array(), array( $second[0]->ID ) );
		$this->assertSame( array(), Documents::of( $id )['image'] );
		$this->assertSame( 0, get_post_thumbnail_id( $id ) );
	}

	/**
	 * Varias de una clase a la vez, cada una con su clase; lo que no vale se cuenta y no entra.
	 */
	public function test_many_documents_at_once_and_one_problem_per_bad_file() {
		$id = $this->curator_design();

		$problems = Documents::apply(
			$id,
			array(
				'design'   => array( $this->odt( 'a.odt' ), $this->odt( 'b.odt' ) ),
				'sessions' => array( $this->odt( 'escaleta.odt' ) ),
				'support'  => array( $this->upload( 'mal.pdf', 'texto' ), $this->png( 'foto.png' ) ),
			),
			array()
		);

		$this->assertCount( 2, $problems );
		$docs = Documents::of( $id );
		$this->assertCount( 2, $docs['design'] );
		$this->assertCount( 1, $docs['sessions'] );
		$this->assertCount( 0, $docs['support'] );
		$this->assertSame( 0, get_post_thumbnail_id( $id ), 'sin imagen no hay destacada' );
	}

	/**
	 * Cambiar un documento por otro que no vale deja el que había.
	 */
	public function test_a_bad_replacement_keeps_the_original() {
		$id = $this->curator_design();
		Documents::apply( $id, array( 'design' => array( $this->odt( 'bueno.odt' ) ) ), array() );
		$old = Documents::of( $id )['design'][0]->ID;

		$problems = Documents::apply( $id, array( 'replace' => array( $old => $this->upload( 'malo.pdf', 'texto' ) ) ), array() );

		$this->assertCount( 1, $problems );
		$this->assertNotNull( get_post( $old ) );
		$this->assertSame( array( $old ), wp_list_pluck( Documents::of( $id )['design'], 'ID' ) );
	}

	/**
	 * El cambiado ocupa el sitio del anterior en la lista.
	 */
	public function test_a_replacement_keeps_its_place_in_the_list() {
		$id = $this->curator_design();
		Documents::apply( $id, array( 'design' => array( $this->odt( 'uno.odt' ), $this->odt( 'dos.odt' ) ) ), array() );
		list( $first, $second ) = Documents::of( $id )['design'];
		wp_update_post(
			array(
				'ID'         => $first->ID,
				'menu_order' => 2,
			)
		);
		wp_update_post(
			array(
				'ID'         => $second->ID,
				'menu_order' => 1,
			)
		);
		$this->assertSame( array( $second->ID, $first->ID ), wp_list_pluck( Documents::of( $id )['design'], 'ID' ) );

		Documents::apply( $id, array( 'replace' => array( $first->ID => $this->odt( 'uno-v2.odt' ) ) ), array() );

		$docs = Documents::of( $id )['design'];
		$this->assertSame( $second->ID, $docs[0]->ID );
		$this->assertSame( 2, $docs[1]->menu_order );
		$this->assertSame( 'uno-v2.odt', Documents::name( $docs[1]->ID ) );
	}

	/**
	 * Solo cuentan los adjuntos con clase; los de otro diseño ni se cambian ni se ven.
	 */
	public function test_only_attachments_with_a_kind_are_documents() {
		$other = $this->curator_design();
		$id    = $this->curator_design();
		$loose = self::factory()->attachment->create( array( 'post_parent' => $id ) );
		$alien = self::factory()->attachment->create( array( 'post_parent' => $other ) );
		update_post_meta( $alien, Documents::KIND_META, 'design' );

		$this->assertSame( array(), array_merge( ...array_values( Documents::of( $id ) ) ) );
		$this->assertSame( array_fill_keys( array_keys( Documents::kinds() ), array() ), Documents::of( 0 ) );

		Documents::apply( $id, array( 'replace' => array( $alien => $this->upload( 'x.pdf', self::PDF ) ) ), array() );
		$this->assertNotNull( get_post( $alien ), 'no se cambia un adjunto que no es del diseño' );
		$this->assertNotNull( get_post( $loose ) );
	}

	/**
	 * Solo un diseño tiene documentos.
	 */
	public function test_only_designs_have_documents() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$action = self::factory()->post->create( array( 'post_type' => PostTypes::ACTION ) );

		$this->assertSame( array( 'No tiene permiso para cambiar los documentos.' ), Documents::apply( $action, array( 'design' => array( $this->upload( 'a.pdf', self::PDF ) ) ), array() ) );
		$this->assertSame( array(), get_children( array( 'post_parent' => $action ) ) );
	}

	/**
	 * Ni el servicio de perfeccionamiento ni la asesoría suben nada; la coordinación, en cualquier diseño.
	 */
	public function test_who_manages_documents() {
		$id = $this->curator_design();
		$this->assertTrue( Documents::can_manage( $id ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'fmc_training_service' ) ) );
		$this->assertFalse( Documents::can_manage( $id ) );

		// La asesoría consulta los documentos, pero no los cambia (ADR-0017).
		wp_publish_post( $id );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'fmc_adviser' ) ) );
		$this->assertFalse( Documents::can_manage( $id ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'fmc_curator' ) ) );
		$this->assertTrue( Documents::can_manage( $id ) );
	}

	/**
	 * Una ruta mandada a mano no es una subida: se descarta.
	 */
	public function test_from_request_ignores_paths_that_were_not_uploaded() {
		$fake = $this->upload( 'diseno.pdf', self::PDF );
		$raw  = array(
			'fmc_doc'     => array(
				'name'     => array( 'design' => array( 'diseno.pdf', '' ) ),
				'type'     => array( 'design' => array( 'application/pdf', '' ) ),
				'tmp_name' => array( 'design' => array( $fake['tmp_name'], '' ) ),
				'error'    => array( 'design' => array( 0, UPLOAD_ERR_NO_FILE ) ),
				'size'     => array( 'design' => array( $fake['size'], 0 ) ),
			),
			'fmc_replace' => array(
				'name'     => array( 12 => 'otro.pdf' ),
				'type'     => array( 12 => 'application/pdf' ),
				'tmp_name' => array( 12 => '/etc/passwd' ),
				'error'    => array( 12 => 0 ),
				'size'     => array( 12 => 10 ),
			),
		);

		$this->assertSame( array(), Documents::from_request( $raw ) );
		$this->assertSame( array(), Documents::from_request( array( 'fmc_doc' => array( 'name' => 'suelto.pdf' ) ) ) );
		$this->assertSame( array(), Documents::from_request( array() ) );
	}

	/**
	 * El fichero va al almacén privado, con un nombre que no dice nada, cerrado
	 * y sin copias; la dirección que reparte WordPress es la del aplicativo.
	 */
	public function test_a_document_is_stored_closed_under_a_random_name() {
		$id  = $this->curator_design();
		$doc = $this->stored( $id, 'design', $this->odt( 'Ficha técnica.odt' ) );
		$png = $this->stored( $id, 'image', $this->png( 'portada.png' ) );

		foreach ( array( $doc, $png ) as $attachment ) {
			$path = get_attached_file( $attachment );
			$this->assertTrue( DocumentStore::is_private( $path ), 'dentro del almacén privado' );
			$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}\.(odt|png)$/', basename( $path ) );
			$this->assertSame( DocumentStore::MODE_CLOSED, $this->mode( $path ) );
			$this->assertSame( Documents::url( $attachment ), wp_get_attachment_url( $attachment ) );
		}
		$this->assertSame( 'Ficha técnica.odt', Documents::name( $doc ) );
		$this->assertSame( 'Ficha técnica', get_the_title( $doc ) );
		$this->assertSame( array(), wp_get_attachment_metadata( $png )['sizes'], 'sin miniaturas legibles al lado' );
		$this->assertFileExists( DocumentStore::root() . '/.htaccess' );
		$this->assertFileExists( DocumentStore::root() . '/index.php' );
	}

	/**
	 * Leer lo abre solo mientras lee: los bytes salen enteros y el fichero
	 * vuelve a quedar cerrado.
	 */
	public function test_read_opens_the_file_only_while_reading() {
		$id   = $this->curator_design();
		$doc  = $this->stored( $id, 'support', $this->upload( 'recurso.pdf', self::PDF ) );
		$path = get_attached_file( $doc );

		$this->assertSame( self::PDF, DocumentStore::read( $doc ) );
		$this->assertSame( self::PDF, DocumentStore::read( $doc ), 'y otra vez' );
		$this->assertSame( DocumentStore::MODE_CLOSED, $this->mode( $path ) );
		$this->assertNull( DocumentStore::read( 0 ) );
	}

	/**
	 * Quién descarga qué: la ficha técnica de un curso del catálogo, cualquiera;
	 * el resto, quien puede leer el diseño, y sin sesión se pide primero.
	 */
	public function test_who_may_download_a_document() {
		$id       = $this->curator_design();
		$sheet    = $this->stored( $id, 'design', $this->odt( 'ficha.odt' ) );
		$sessions = $this->stored( $id, 'sessions', $this->odt( 'escaleta.odt' ) );
		$curator  = get_current_user_id();
		$adviser  = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$verdicts = function ( int $user ) use ( $sheet, $sessions ): array {
			wp_set_current_user( $user );
			return array( Documents::verdict( $sheet ), Documents::verdict( $sessions ) );
		};

		// Un borrador: solo quien lo escribe.
		$this->assertSame( array( 'allowed', 'allowed' ), $verdicts( $curator ) );
		$this->assertSame( array( 'denied', 'denied' ), $verdicts( $adviser ) );
		$this->assertSame( array( 'login', 'login' ), $verdicts( 0 ) );

		// Finalizado: la asesoría lo consulta, con sus documentos (ADR-0017).
		wp_publish_post( $id );
		$this->assertSame( array( 'allowed', 'allowed' ), $verdicts( $adviser ) );
		$this->assertSame( array( 'login', 'login' ), $verdicts( 0 ), 'finalizado, pero fuera del catálogo' );

		// En el catálogo, la ficha técnica es pública; la escaleta, no.
		update_post_meta( $id, \Fmc\Meta\DesignMetaKeys::IN_CATALOGUE, '1' );
		$this->assertSame( array( 'public', 'login' ), $verdicts( 0 ) );

		// Al histórico: deja de ser pública y la asesoría deja de verla (ADR-0016).
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => PostTypes::ARCHIVED,
			)
		);
		$this->assertSame( array( 'login', 'login' ), $verdicts( 0 ) );
		$this->assertSame( array( 'denied', 'denied' ), $verdicts( $adviser ) );
		$this->assertSame( array( 'allowed', 'allowed' ), $verdicts( $curator ) );

		// Lo que no es un documento de un diseño no se sirve, a nadie.
		$loose = self::factory()->attachment->create( array( 'post_parent' => $id ) );
		wp_set_current_user( $curator );
		$this->assertSame( 'denied', Documents::verdict( $loose ) );
		$this->assertSame( 'denied', Documents::verdict( 0 ) );
		$this->assertSame( 'denied', Documents::verdict( $id ), 'un diseño no es un documento' );
	}

	/**
	 * Siempre como descarga, con su tipo, su nombre y sin caché compartida.
	 */
	public function test_a_download_is_an_attachment_with_nosniff() {
		$id      = $this->curator_design();
		$doc     = $this->stored( $id, 'support', $this->upload( 'Guía rápida.pdf', self::PDF ) );
		$headers = Documents::headers( $doc, 42 );

		$this->assertContains( 'Content-Type: application/pdf', $headers );
		$this->assertContains( 'Content-Disposition: attachment; filename="Guia-rapida.pdf"; filename*=UTF-8\'\'Gu%C3%ADa%20r%C3%A1pida.pdf', $headers );
		$this->assertContains( 'Content-Length: 42', $headers );
		$this->assertContains( 'X-Content-Type-Options: nosniff', $headers );
		$this->assertContains( 'Cache-Control: private, no-store', $headers );
	}

	/**
	 * Lo subido antes se mueve al almacén —el original de una imagen, sin sus
	 * miniaturas—, y repetirlo no hace nada más.
	 */
	public function test_protect_moves_what_was_stored_the_old_way() {
		$id  = $this->curator_design();
		$tmp = get_temp_dir() . 'antigua.png';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- imagen de prueba.
		file_put_contents( $tmp, base64_decode( self::PNG ) );
		$old = self::factory()->attachment->create_upload_object( $tmp, $id );
		update_post_meta( $old, Documents::KIND_META, 'image' );
		$before = get_attached_file( $old );
		$this->assertFalse( DocumentStore::is_private( $before ) );

		$this->assertSame( '', DocumentStore::protect( $old ) );
		$path = get_attached_file( $old );
		$this->assertTrue( DocumentStore::is_private( $path ) );
		$this->assertFileDoesNotExist( $before );
		$this->assertSame( DocumentStore::MODE_CLOSED, $this->mode( $path ) );
		$this->assertStringStartsWith( 'antigua', Documents::name( $old ) );
		$this->assertSame( array(), wp_get_attachment_metadata( $old )['sizes'] );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- imagen de prueba.
		$this->assertSame( base64_decode( self::PNG ), DocumentStore::read( $old ) );

		$this->assertSame( '', DocumentStore::protect( $old ), 'idempotente' );
		$this->assertSame( $path, get_attached_file( $old ) );
		$this->assertNotSame( '', DocumentStore::protect( self::factory()->attachment->create( array( 'post_parent' => $id ) ) ), 'sin clase no es un documento' );
	}

	/**
	 * Quitar un documento se lleva su fichero cerrado.
	 */
	public function test_removing_a_document_removes_its_file() {
		$id   = $this->curator_design();
		$doc  = $this->stored( $id, 'support', $this->upload( 'fuera.pdf', self::PDF ) );
		$path = get_attached_file( $doc );

		Documents::apply( $id, array(), array( $doc ) );

		$this->assertNull( get_post( $doc ) );
		$this->assertFileDoesNotExist( $path );
	}
}

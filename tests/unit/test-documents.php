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
	 * A DOCX upload: a ZIP with the parts Word puts first.
	 *
	 * @param string $name File name.
	 * @return array<string, mixed>
	 */
	private function docx( string $name ): array {
		$tmp = wp_tempnam( $name );
		$zip = new ZipArchive();
		$zip->open( $tmp, ZipArchive::OVERWRITE );
		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>' );
		$zip->addFromString( 'word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>' );
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
	 * La imagen es una sola y pequeña; el resto, listas. De la escaleta y las
	 * presentaciones, los editables; los recursos, en PDF (#11). El diseño del
	 * curso, en editable y en PDF: uno por formato.
	 */
	public function test_each_kind_accepts_its_own_formats() {
		$kinds = Documents::kinds();

		$this->assertSame( array( 'image', 'design', 'sessions', 'slides', 'support', 'extra' ), array_keys( $kinds ) );
		$this->assertFalse( $kinds['image']['multiple'] );
		$this->assertSame( 3, $kinds['image']['max_mb'] );
		// El diseño del curso es el fichero maestro: el vigente, en cada formato (#26).
		$this->assertTrue( $kinds['design']['multiple'] );
		$this->assertTrue( $kinds['design']['per_format'] );
		$this->assertContains( 'application/pdf', $kinds['design']['mimes'] );
		foreach ( array( 'design', 'sessions' ) as $editable ) {
			$this->assertContains( 'application/vnd.oasis.opendocument.text', $kinds[ $editable ]['mimes'], $editable );
		}
		$this->assertNotContains( 'application/pdf', $kinds['sessions']['mimes'] );
		foreach ( array( 'image', 'sessions', 'slides', 'support', 'extra' ) as $other ) {
			$this->assertArrayNotHasKey( 'per_format', $kinds[ $other ], $other );
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
		$this->assertSame( '', Documents::check( 'design', $pdf ), 'el diseño, también en PDF' );
		$this->assertSame( '«diseno.pdf» no tiene un formato admitido. Se admite DOC, DOCX u ODT.', Documents::check( 'sessions', $pdf ), 'de la escaleta, el editable' );
		$this->assertSame( '«foto.png» no tiene un formato admitido. Se admite DOC, DOCX, ODT o PDF.', Documents::check( 'design', $this->png( 'foto.png' ) ) );
		$this->assertSame( '«diseno.odt» no tiene un formato admitido. Se admite PDF.', Documents::check( 'support', $odt ), 'los recursos, en PDF' );
		$this->assertSame( 'JPG, JPEG, PNG o GIF', Documents::formats( Documents::kinds()['image']['mimes'] ) );
		$this->assertSame( 'Tipo de documento desconocido.', Documents::check( 'poster', $odt ) );
		$this->assertSame( 'No se ha podido subir «diseno.odt».', Documents::check( 'design', array( 'error' => UPLOAD_ERR_PARTIAL ) + $odt ) );

		$big = $this->png( 'grande.png' );
		$this->assertSame( '', Documents::check( 'image', $big ) );
		$big['size'] = 3 * MB_IN_BYTES + 1;
		$this->assertSame( '«grande.png» supera el máximo de 3 MB.', Documents::check( 'image', $big ) );
	}

	/**
	 * Cuenta el contenido, no la extensión: un texto con .pdf no es un PDF, y una imagen no es un diseño.
	 */
	public function test_check_looks_at_the_real_file_type() {
		$this->assertStringContainsString( 'no tiene un formato admitido', Documents::check( 'support', $this->upload( 'falso.pdf', 'esto es texto' ) ) );
		$this->assertStringContainsString( 'no tiene un formato admitido', Documents::check( 'design', $this->upload( 'falso.odt', 'esto es texto' ) ) );
		$this->assertStringContainsString( 'no tiene un formato admitido', Documents::check( 'design', $this->png( 'foto.png' ) ) );
		$this->assertStringContainsString( 'no tiene un formato admitido', Documents::check( 'image', $this->upload( 'diseno.png', self::PDF ) ) );
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
	 * El diseño del curso es el fichero maestro, y puede estar en varios
	 * formatos: subir otro del mismo formato, también con «Añadir», deja solo el
	 * nuevo y el anterior deja de descargarse (#26); el de otro formato se queda.
	 */
	public function test_the_design_file_is_one_per_format() {
		$id    = $this->curator_design();
		$names = static fn(): array => array_map( array( Documents::class, 'name' ), wp_list_pluck( Documents::of( $id )['design'], 'ID' ) );
		Documents::apply( $id, array( 'design' => array( $this->odt( 'v1.odt' ) ) ), array() );
		$old   = Documents::of( $id )['design'][0]->ID;

		// El PDF de la misma ficha se suma: es otro formato.
		$this->assertSame( array(), Documents::apply( $id, array( 'design' => array( $this->upload( 'v1.pdf', self::PDF ) ) ), array() ) );
		$this->assertEqualsCanonicalizing( array( 'v1.odt', 'v1.pdf' ), $names() );

		// Otro ODT sustituye al ODT, y solo a él.
		$this->assertSame( array(), Documents::apply( $id, array( 'design' => array( $this->odt( 'v2.odt' ) ) ), array() ) );
		$this->assertEqualsCanonicalizing( array( 'v1.pdf', 'v2.odt' ), $names() );
		$this->assertNull( get_post( $old ) );

		// DOC y DOCX son el mismo formato: el de Word.
		$doc = self::factory()->attachment->create(
			array(
				'post_parent'    => $id,
				'post_mime_type' => 'application/msword',
			)
		);
		update_post_meta( $doc, Documents::KIND_META, 'design' );
		update_post_meta( $doc, Documents::NAME_META, 'v1.doc' );
		$this->assertSame( array(), Documents::apply( $id, array( 'design' => array( $this->docx( 'v2.docx' ) ) ), array() ) );
		$this->assertEqualsCanonicalizing( array( 'v1.pdf', 'v2.odt', 'v2.docx' ), $names() );
		$this->assertNull( get_post( $doc ) );
	}

	/**
	 * «Reemplazar» un documento del diseño por uno de otro formato que ya
	 * estaba deja uno solo de ese formato: el nuevo.
	 */
	public function test_replacing_a_design_file_keeps_one_per_format() {
		$id  = $this->curator_design();
		$odt = $this->stored( $id, 'design', $this->odt( 'ficha.odt' ) );
		Documents::apply( $id, array( 'design' => array( $this->upload( 'ficha.pdf', self::PDF ) ) ), array() );
		$pdf = (int) current( array_diff( wp_list_pluck( Documents::of( $id )['design'], 'ID' ), array( $odt ) ) );

		$this->assertSame( array(), Documents::apply( $id, array( 'replace' => array( $odt => $this->upload( 'nueva.pdf', self::PDF ) ) ), array() ) );

		$docs = Documents::of( $id )['design'];
		$this->assertCount( 1, $docs );
		$this->assertSame( 'nueva.pdf', Documents::name( $docs[0]->ID ) );
		$this->assertNull( get_post( $odt ) );
		$this->assertNull( get_post( $pdf ) );
	}

	/**
	 * Las demás clases con varios documentos no miran el formato: dos PDF de
	 * recursos son dos recursos.
	 */
	public function test_other_kinds_keep_several_of_the_same_format() {
		$id = $this->curator_design();
		Documents::apply( $id, array( 'support' => array( $this->upload( 'uno.pdf', self::PDF ), $this->upload( 'dos.pdf', self::PDF ) ) ), array() );

		$this->assertCount( 2, Documents::of( $id )['support'] );
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
		$this->assertCount( 1, $docs['design'], 'el diseño es uno por formato: de dos ODT queda el último' );
		$this->assertSame( 'b.odt', Documents::name( $docs['design'][0]->ID ) );
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
		Documents::apply( $id, array( 'sessions' => array( $this->odt( 'uno.odt' ), $this->odt( 'dos.odt' ) ) ), array() );
		list( $first, $second ) = Documents::of( $id )['sessions'];
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
		$this->assertSame( array( $second->ID, $first->ID ), wp_list_pluck( Documents::of( $id )['sessions'], 'ID' ) );

		Documents::apply( $id, array( 'replace' => array( $first->ID => $this->odt( 'uno-v2.odt' ) ) ), array() );

		$docs = Documents::of( $id )['sessions'];
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
		$id    = $this->curator_design();
		$tmp   = get_temp_dir() . 'antigua.png';
		$image = imagecreatetruecolor( 400, 300 );
		imagepng( $image, $tmp );
		// Una imagen «grande»: WordPress deja el original, una copia reducida y miniaturas.
		$small = static fn(): int => 320;
		add_filter( 'big_image_size_threshold', $small );
		$old   = self::factory()->attachment->create_upload_object( $tmp, $id );
		remove_filter( 'big_image_size_threshold', $small );
		update_post_meta( $old, Documents::KIND_META, 'image' );
		$before = get_attached_file( $old );
		$meta   = wp_get_attachment_metadata( $old );
		$copies = array_map( static fn( array $size ): string => dirname( $before ) . '/' . $size['file'], $meta['sizes'] );
		$this->assertNotEmpty( $copies, 'con miniaturas' );
		$this->assertStringContainsString( '-scaled', $before, 'y con copia reducida' );
		$this->assertFalse( DocumentStore::is_private( $before ) );
		$bytes = DocumentStore::read( $old );
		$this->assertNotNull( $bytes, 'lo aún no movido se lee sin más' );

		$this->assertSame( '', DocumentStore::protect( $old ) );
		$path = get_attached_file( $old );
		$this->assertTrue( DocumentStore::is_private( $path ) );
		$this->assertFileDoesNotExist( $before );
		$this->assertSame( DocumentStore::MODE_CLOSED, $this->mode( $path ) );
		$this->assertStringStartsWith( 'antigua', Documents::name( $old ) );
		$this->assertSame( array(), wp_get_attachment_metadata( $old )['sizes'] );
		$this->assertArrayNotHasKey( 'original_image', wp_get_attachment_metadata( $old ) );
		foreach ( $copies as $copy ) {
			$this->assertFileDoesNotExist( $copy, 'ninguna copia legible fuera' );
		}
		$this->assertSame( 'png', pathinfo( $path, PATHINFO_EXTENSION ) );
		$this->assertSame( array( 400, 300 ), array_slice( getimagesizefromstring( (string) DocumentStore::read( $old ) ), 0, 2 ), 'se guarda el original, no la copia' );

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

	/**
	 * Lo que contesta cada descarga: a entrar, el fichero, «no existe» o «inténtelo
	 * luego»; nunca se cachea un rechazo.
	 */
	public function test_the_answer_to_each_download() {
		$id      = $this->curator_design();
		$doc     = $this->stored( $id, 'support', $this->upload( 'recurso.pdf', self::PDF ) );
		$curator = get_current_user_id();

		$answer = Documents::answer( $doc );
		$this->assertSame( 200, $answer['status'] );
		$this->assertSame( self::PDF, $answer['body'] );
		$this->assertContains( 'Content-Length: ' . strlen( self::PDF ), $answer['headers'] );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'fmc_adviser' ) ) );
		$answer = Documents::answer( $doc );
		$this->assertSame( 404, $answer['status'], 'un borrador ajeno no existe' );
		$this->assertStringNotContainsString( 'recurso', $answer['body'] );
		$this->assertContains( 'X-Content-Type-Options: nosniff', $answer['headers'] );
		$this->assertNotEmpty( preg_grep( '/^Cache-Control: .*no-cache/', $answer['headers'] ) );
		$this->assertEmpty( preg_grep( '/^Last-Modified/', $answer['headers'] ) );

		wp_set_current_user( 0 );
		$this->assertSame( array( 'redirect' => wp_login_url( Documents::url( $doc ) ) ), Documents::answer( $doc ) );

		// El fichero no está: se dice que se pruebe luego, no que no existe.
		wp_set_current_user( $curator );
		$gone   = static fn(): string => '';
		add_filter( 'get_attached_file', $gone );
		$answer = Documents::answer( $doc );
		remove_filter( 'get_attached_file', $gone );
		$this->assertSame( 503, $answer['status'] );
	}

	/**
	 * La miniatura de la imagen se revalida con su ETag; el permiso se comprueba antes.
	 */
	public function test_the_image_is_revalidated_with_its_etag() {
		$id    = $this->curator_design();
		$image = $this->stored( $id, 'image', $this->png( 'portada.png' ) );

		$answer = Documents::answer( $image );
		$this->assertSame( 200, $answer['status'] );
		$this->assertContains( 'Cache-Control: private, no-cache', $answer['headers'] );
		$etags = preg_grep( '/^ETag: "[0-9a-f]{32}"$/', $answer['headers'] );
		$this->assertCount( 1, $etags );
		$etag = substr( (string) reset( $etags ), 6 );

		$again = Documents::answer( $image, 'W/"otro", ' . $etag );
		$this->assertSame( 304, $again['status'] );
		$this->assertSame( '', $again['body'] );
		$this->assertContains( 'ETag: ' . $etag, $again['headers'] );
		$this->assertSame( 200, Documents::answer( $image, '"otro"' )['status'] );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'fmc_adviser' ) ) );
		$this->assertSame( 404, Documents::answer( $image, $etag )['status'], 'sin permiso no hay 304' );

		// La imagen de un curso del catálogo sale en su tarjeta, para cualquiera:
		// es pública y el navegador la guarda un rato.
		wp_publish_post( $id );
		update_post_meta( $id, \Fmc\Meta\DesignMetaKeys::IN_CATALOGUE, '1' );
		wp_set_current_user( 0 );
		$public = Documents::answer( $image );
		$this->assertSame( 200, $public['status'] );
		$this->assertContains( 'Cache-Control: public, max-age=3600', $public['headers'] );
		$this->assertContains( 'ETag: ' . $etag, $public['headers'] );
		// Fuera del catálogo vuelve a ser de quien lee el diseño.
		delete_post_meta( $id, \Fmc\Meta\DesignMetaKeys::IN_CATALOGUE );
		$this->assertArrayHasKey( 'redirect', Documents::answer( $image ) );

		// El resto de documentos no se guarda en el navegador.
		wp_set_current_user( (int) get_post_field( 'post_author', $id ) );
		$doc = $this->stored( $id, 'support', $this->upload( 'recurso.pdf', self::PDF ) );
		$this->assertContains( 'Cache-Control: private, no-store', Documents::answer( $doc )['headers'] );
		$this->assertEmpty( preg_grep( '/^ETag/', Documents::answer( $doc )['headers'] ) );
	}

	/**
	 * `serve()` manda lo que dice `answer()`: a entrar, o con su código.
	 */
	public function test_serve_sends_the_answer() {
		$id   = $this->curator_design();
		$doc  = $this->stored( $id, 'support', $this->upload( 'recurso.pdf', self::PDF ) );
		$stop = static function ( $value ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- lo lee el test, no se pinta.
			throw new RuntimeException( (string) $value );
		};
		add_filter( 'wp_redirect', $stop );
		add_filter( 'status_header', $stop );
		$_GET = array( Documents::ARG => (string) $doc );
		try {
			wp_set_current_user( 0 );
			try {
				Documents::serve();
				$this->fail( 'sin sesión se manda a entrar' );
			} catch ( RuntimeException $e ) {
				$this->assertSame( wp_login_url( Documents::url( $doc ) ), $e->getMessage() );
			}
			wp_set_current_user( self::factory()->user->create( array( 'role' => 'fmc_adviser' ) ) );
			try {
				Documents::serve();
				$this->fail( 'sin permiso, no existe' );
			} catch ( RuntimeException $e ) {
				$this->assertStringContainsString( '404', $e->getMessage() );
			}
		} finally {
			$_GET = array();
			remove_filter( 'wp_redirect', $stop );
			remove_filter( 'status_header', $stop );
		}
	}

	/**
	 * La dirección que reparte WordPress de un documento es la del aplicativo;
	 * la de cualquier otro adjunto, la suya.
	 */
	public function test_only_documents_get_the_app_url() {
		Documents::register();
		$this->assertSame( 10, has_filter( 'wp_get_attachment_url', array( Documents::class, 'attachment_url' ) ) );
		$loose = self::factory()->attachment->create( array( 'file' => 'suelto.pdf' ) );
		$this->assertStringNotContainsString( Documents::ARG, (string) wp_get_attachment_url( $loose ) );
	}

	/**
	 * Si WordPress no puede guardar el fichero, se dice cuál, al subir y al
	 * cambiar; y el que había se queda.
	 */
	public function test_a_file_that_cannot_be_saved_is_reported() {
		$id       = $this->curator_design();
		$old      = $this->stored( $id, 'design', $this->odt( 'ficha.odt' ) );
		$err      = static fn( array $file ): array => array( 'error' => 'disco lleno' ) + $file;
		add_filter( 'wp_handle_sideload_prefilter', $err );
		$problems = Documents::apply(
			$id,
			array(
				'support' => array( $this->upload( 'nuevo.pdf', self::PDF ) ),
				'replace' => array( $old => $this->odt( 'otra.odt' ) ),
			),
			array()
		);
		remove_filter( 'wp_handle_sideload_prefilter', $err );

		$this->assertCount( 2, $problems );
		$this->assertStringContainsString( 'disco lleno', implode( ' ', $problems ) );
		$this->assertSame( array( $old ), wp_list_pluck( Documents::of( $id )['design'], 'ID' ) );
		$this->assertSame( array(), Documents::of( $id )['support'] );
	}

	/**
	 * Un documento que no se puede cerrar no se da por guardado.
	 */
	public function test_a_file_that_cannot_be_closed_is_not_kept() {
		$id       = $this->curator_design();
		$gone     = static fn(): string => '';
		add_filter( 'get_attached_file', $gone );
		$problems = Documents::apply( $id, array( 'support' => array( $this->upload( 'abierto.pdf', self::PDF ) ) ), array() );
		remove_filter( 'get_attached_file', $gone );

		$this->assertSame( array( '«abierto.pdf»: no se ha podido proteger el fichero.' ), $problems );
		$this->assertSame( array(), Documents::of( $id )['support'] );
	}

	/**
	 * Sin almacén donde guardar, no se guarda ni se mueve nada fuera de él.
	 */
	public function test_without_a_store_nothing_is_stored_or_moved() {
		$id     = $this->curator_design();
		$old    = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $id );
		$before = get_attached_file( $old );
		update_post_meta( $old, Documents::KIND_META, 'image' );
		$nowhere = static function ( array $dirs ): array {
			$dirs['basedir'] = '/dev/null/fmc';
			return $dirs;
		};
		$same    = static fn(): string => $before;
		add_filter( 'upload_dir', $nowhere );
		add_filter( 'get_attached_file', $same );
		try {
			$this->assertFalse( DocumentStore::prepare() );
			$problems = Documents::apply( $id, array( 'support' => array( $this->upload( 'nuevo.pdf', self::PDF ) ) ), array() );
			$moved    = DocumentStore::protect( $old );
		} finally {
			remove_filter( 'upload_dir', $nowhere );
			remove_filter( 'get_attached_file', $same );
		}

		$this->assertSame( array( '«nuevo.pdf»: no se ha podido preparar el almacén de documentos.' ), $problems );
		$this->assertSame( 'No se ha podido mover el documento ' . $old . '.', $moved );
		$this->assertFileExists( $before, 'el original sigue donde estaba' );
	}

	/**
	 * Las lecturas van de una en una: si otra no suelta el cerrojo, se rinde
	 * en vez de abrir el fichero a la vez.
	 */
	public function test_a_read_gives_up_when_another_holds_the_lock() {
		$id   = $this->curator_design();
		$doc  = $this->stored( $id, 'support', $this->upload( 'recurso.pdf', self::PDF ) );
		$lock = fopen( DocumentStore::root() . '/.lock', 'c' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- el cerrojo de otra lectura.
		flock( $lock, LOCK_EX );
		try {
			$this->assertNull( DocumentStore::read( $doc ) );
			$this->assertSame( DocumentStore::MODE_CLOSED, $this->mode( get_attached_file( $doc ) ), 'sin abrirlo' );
		} finally {
			flock( $lock, LOCK_UN );
			fclose( $lock ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- el cerrojo de otra lectura.
		}
		$this->assertSame( self::PDF, DocumentStore::read( $doc ), 'suelto el cerrojo, se lee' );
	}
}

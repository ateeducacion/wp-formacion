<?php
/**
 * Tests for the documents of a design: kinds, checks, upload, replace, remove.
 *
 * @package Fmc
 */

use Fmc\PostType\PostTypes;
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
	 * An adviser, logged in, with a draft design of her own.
	 *
	 * @return int Design ID.
	 */
	private function adviser_design(): int {
		$adviser = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		wp_set_current_user( $adviser );
		return self::factory()->post->create(
			array(
				'post_type'   => PostTypes::DESIGN,
				'post_author' => $adviser,
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
	 * La imagen es una sola y pequeña; el resto, listas, y el apoyo admite ZIP y no ODT.
	 */
	public function test_each_kind_accepts_its_own_formats() {
		$kinds = Documents::kinds();

		$this->assertSame( array( 'image', 'design', 'sessions', 'support', 'extra' ), array_keys( $kinds ) );
		$this->assertFalse( $kinds['image']['multiple'] );
		$this->assertSame( 3, $kinds['image']['max_mb'] );
		$this->assertTrue( $kinds['design']['multiple'] );
		$this->assertContains( 'application/zip', $kinds['support']['mimes'] );
		$this->assertNotContains( 'application/vnd.oasis.opendocument.text', $kinds['support']['mimes'] );
		$this->assertNotContains( 'application/zip', $kinds['design']['mimes'] );
	}

	/**
	 * Lo que no se puede guardar se dice con su motivo.
	 */
	public function test_check_rejects_with_a_reason() {
		$pdf = $this->upload( 'diseno.pdf', self::PDF );

		$this->assertSame( '', Documents::check( 'design', $pdf ) );
		$this->assertSame( 'Clase de documento desconocida.', Documents::check( 'poster', $pdf ) );
		$this->assertSame( 'No se pudo subir «diseno.pdf».', Documents::check( 'design', array( 'error' => UPLOAD_ERR_PARTIAL ) + $pdf ) );

		$big = $this->png( 'grande.png' );
		$this->assertSame( '', Documents::check( 'image', $big ) );
		$big['size'] = 3 * MB_IN_BYTES + 1;
		$this->assertSame( '«grande.png» pasa de 3 MB.', Documents::check( 'image', $big ) );
	}

	/**
	 * Cuenta el contenido, no la extensión: un texto con .pdf no es un PDF, y una imagen no es un diseño.
	 */
	public function test_check_looks_at_the_real_file_type() {
		$this->assertStringContainsString( 'no es de un formato admitido', Documents::check( 'design', $this->upload( 'falso.pdf', 'esto es texto' ) ) );
		$this->assertStringContainsString( 'no es de un formato admitido', Documents::check( 'design', $this->png( 'foto.png' ) ) );
		$this->assertStringContainsString( 'no es de un formato admitido', Documents::check( 'image', $this->upload( 'diseno.png', self::PDF ) ) );
	}

	/**
	 * La imagen pasa a ser la destacada; una segunda sustituye a la primera, y quitarla la quita.
	 */
	public function test_the_image_is_single_and_becomes_the_thumbnail() {
		$id = $this->adviser_design();

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
		$this->assertStringStartsWith( 'dos', basename( get_attached_file( $second[0]->ID ) ) );
		$this->assertSame( $second[0]->ID, get_post_thumbnail_id( $id ) );

		Documents::apply( $id, array(), array( $second[0]->ID ) );
		$this->assertSame( array(), Documents::of( $id )['image'] );
		$this->assertSame( 0, get_post_thumbnail_id( $id ) );
	}

	/**
	 * Varias de una clase a la vez, cada una con su clase; lo que no vale se cuenta y no entra.
	 */
	public function test_many_documents_at_once_and_one_problem_per_bad_file() {
		$id = $this->adviser_design();

		$problems = Documents::apply(
			$id,
			array(
				'design'   => array( $this->upload( 'a.pdf', self::PDF ), $this->upload( 'b.pdf', self::PDF ) ),
				'sessions' => array( $this->upload( 'minutaje.pdf', self::PDF ) ),
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
		$id = $this->adviser_design();
		Documents::apply( $id, array( 'design' => array( $this->upload( 'bueno.pdf', self::PDF ) ) ), array() );
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
		$id = $this->adviser_design();
		Documents::apply( $id, array( 'design' => array( $this->upload( 'uno.pdf', self::PDF ), $this->upload( 'dos.pdf', self::PDF ) ) ), array() );
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

		Documents::apply( $id, array( 'replace' => array( $first->ID => $this->upload( 'uno-v2.pdf', self::PDF ) ) ), array() );

		$docs = Documents::of( $id )['design'];
		$this->assertSame( $second->ID, $docs[0]->ID );
		$this->assertSame( 2, $docs[1]->menu_order );
		$this->assertStringStartsWith( 'uno-v2', basename( get_attached_file( $docs[1]->ID ) ) );
	}

	/**
	 * Solo cuentan los adjuntos con clase; los de otro diseño ni se cambian ni se ven.
	 */
	public function test_only_attachments_with_a_kind_are_documents() {
		$other = $this->adviser_design();
		$id    = $this->adviser_design();
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
	 * El servicio de formación no sube nada; la curaduría, en cualquier diseño.
	 */
	public function test_who_manages_documents() {
		$id = $this->adviser_design();
		$this->assertTrue( Documents::can_manage( $id ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'fmc_training_service' ) ) );
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
}

<?php
/**
 * Tests for the public catalogue.
 *
 * @package Fmc
 */

use Fmc\Meta\DesignMetaKeys as D;
use Fmc\PostType\PostTypes;
use Fmc\PublicFront\Catalogue;

/**
 * El catálogo público enseña solo lo que está en él, y de cada curso solo lo
 * que se publica.
 */
class Test_Catalogue extends WP_UnitTestCase {

	/**
	 * Clean request and user.
	 */
	public function tear_down() {
		$_GET = array();
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * A design with its meta.
	 *
	 * @param string               $title  Title.
	 * @param array<string, mixed> $meta   Meta.
	 * @param string               $status Status.
	 * @return int
	 */
	private function design( string $title, array $meta = array(), string $status = 'publish' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'    => PostTypes::DESIGN,
				'post_title'   => $title,
				'post_status'  => $status,
				'post_content' => 'De qué va ' . $title,
			)
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		return $id;
	}

	/**
	 * Titles of the courses the list shows.
	 *
	 * @return string[]
	 */
	private function titles(): array {
		preg_match_all( '/<h2 class="h6 mb-1"><a href="[^"]*">([^<]*)<\/a>/', Catalogue::screen()['body'], $m );
		return $m[1];
	}

	/**
	 * Solo los diseños finalizados que están en el catálogo, para cualquiera.
	 */
	public function test_the_list_shows_only_finished_designs_in_the_catalogue() {
		$this->design(
			'En el catálogo',
			array(
				D::IN_CATALOGUE => '1',
				D::CODE         => 'A7',
				D::TYPE         => 'course',
			)
		);
		$this->design( 'Finalizado fuera' );
		$this->design( 'Borrador marcado', array( D::IN_CATALOGUE => '1' ), 'draft' );
		$this->design(
			'Taller',
			array(
				D::IN_CATALOGUE => '1',
				D::TYPE         => 'one_off',
			)
		);
		$_GET[ Catalogue::ARG_LIST ] = '1';

		$this->assertSame( array( 'En el catálogo', 'Taller' ), $this->titles() );
		// Ni con sesión de administración se cuela un borrador.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( array( 'En el catálogo', 'Taller' ), $this->titles() );

		$_GET['fmc_type'] = 'one_off';
		$this->assertSame( array( 'Taller' ), $this->titles() );
		$_GET = array(
			Catalogue::ARG_LIST => '1',
			'fmc_s'             => 'a7',
		);
		$this->assertSame( array( 'En el catálogo' ), $this->titles(), 'se busca también por el código' );
		$screen = Catalogue::screen();
		$this->assertTrue( $screen['public'] );
		$this->assertStringContainsString( '1 curso(s)', $screen['body'] );
	}

	/**
	 * La ficha publica la descripción, los destinatarios, los objetivos, los
	 * contenidos y la competencia digital; lo de trabajo, no.
	 */
	public function test_a_sheet_publishes_only_the_public_fields() {
		$id                           = $this->design(
			'Robótica',
			array(
				D::IN_CATALOGUE => '1',
				D::CODE         => 'A9',
				D::OBJECTIVES   => 'Programar un robot',
				D::METHODOLOGY  => 'Por proyectos',
				D::NOTES        => 'Nota interna',
				D::AUTHORSHIP   => 'Persona autora',
				D::DIGCOMP_URL  => 'https://herramienta.example/?c=1.1.A1.1|3.1.B1.1',
			)
		);
		$_GET[ Catalogue::ARG_SHEET ] = (string) $id;

		$screen = Catalogue::screen();
		$this->assertSame( 'Robótica', $screen['title'] );
		$this->assertStringStartsWith( 'A9', $screen['subtitle'] );
		$this->assertStringContainsString( 'De qué va Robótica', $screen['body'] );
		$this->assertStringContainsString( 'Programar un robot', $screen['body'] );
		$this->assertStringContainsString( '>Competencia digital</h2>', $screen['body'] );
		foreach ( array( 'Por proyectos', 'Nota interna', 'Persona autora' ) as $internal ) {
			$this->assertStringNotContainsString( $internal, $screen['body'] );
		}
	}

	/**
	 * Lo que no está en el catálogo no tiene ficha: ni un finalizado fuera de
	 * él, ni un borrador, ni algo que no es un diseño.
	 */
	public function test_a_course_outside_the_catalogue_is_not_found() {
		$ids = array(
			$this->design( 'Fuera' ),
			$this->design( 'Borrador', array( D::IN_CATALOGUE => '1' ), 'draft' ),
			self::factory()->post->create( array( 'post_title' => 'Una entrada' ) ),
		);
		foreach ( $ids as $id ) {
			$_GET[ Catalogue::ARG_SHEET ] = (string) $id;
			$screen                       = Catalogue::screen();
			$this->assertSame( 'Este curso no está en el catálogo', $screen['title'] );
			$this->assertStringNotContainsString( 'De qué va', $screen['body'] );
		}
	}

	/**
	 * La página que el tema da a un diseño lleva a su ficha del catálogo.
	 */
	public function test_the_theme_page_of_a_design_goes_to_its_sheet() {
		$id = $this->design( 'Robótica', array( D::IN_CATALOGUE => '1' ) );
		$this->go_to( get_permalink( $id ) );
		$redirect = static function ( $location ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- lo lee el test, no se pinta.
			throw new RuntimeException( $location );
		};
		add_filter( 'wp_redirect', $redirect );
		try {
			Catalogue::redirect_single();
			$to = 'returned';
		} catch ( RuntimeException $e ) {
			$to = $e->getMessage();
		} finally {
			remove_filter( 'wp_redirect', $redirect );
		}
		$this->assertSame( Catalogue::url( $id ), $to );
	}
}

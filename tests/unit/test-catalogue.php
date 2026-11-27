<?php
/**
 * Tests for the public catalogue.
 *
 * @package Fmc
 */

use Fmc\Meta\DesignMetaKeys as D;
use Fmc\PostType\PostTypes;
use Fmc\PublicFront\Catalogue;
use Fmc\PublicFront\Documents;

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
	 * Todos los diseños finalizados, y solo ellos, para cualquiera (ADR-0032).
	 */
	public function test_the_list_shows_every_finished_design_and_nothing_else() {
		$this->design(
			'En el catálogo',
			array(
				D::CODE => 'A7',
				D::TYPE => 'course',
			)
		);
		$this->design( 'Borrador', array(), 'draft' );
		$this->design( 'Histórico', array(), PostTypes::ARCHIVED );
		$this->design(
			'Taller',
			array(
				D::TYPE => 'one_off',
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
		$this->assertStringContainsString( '1 curso', $screen['body'] );
	}

	/**
	 * La ficha publica la descripción, los destinatarios, los objetivos, los
	 * contenidos y la competencia digital; lo de trabajo, no.
	 */
	public function test_a_sheet_publishes_only_the_public_fields() {
		$id                           = $this->design(
			'Robótica',
			array(
				D::CODE        => 'A9',
				D::OBJECTIVES  => 'Programar un robot',
				D::METHODOLOGY => 'Por proyectos',
				D::NOTES       => 'Nota interna',
				D::AUTHORSHIP  => 'Persona autora',
				D::DIGCOMP_URL => 'https://herramienta.example/?c=1.1.A1.1|3.1.B1.1',
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
	 * La ficha técnica se descarga desde la ficha del curso, por el aplicativo;
	 * el resto de los documentos se queda dentro (ADR-0025).
	 */
	public function test_a_sheet_links_its_technical_sheet_and_nothing_else() {
		$id = $this->design( 'Impresión 3D', array() );
		foreach ( array(
			'design'   => 'Ficha técnica.odt',
			'sessions' => 'Escaleta.odt',
		) as $kind => $name ) {
			$doc = self::factory()->attachment->create( array( 'post_parent' => $id ) );
			update_post_meta( $doc, Documents::KIND_META, $kind );
			update_post_meta( $doc, Documents::NAME_META, $name );
			$docs[ $kind ] = $doc;
		}
		$_GET[ Catalogue::ARG_SHEET ] = (string) $id;

		$body = Catalogue::screen()['body'];
		$this->assertStringContainsString( '>Diseño del curso</h2>', $body );
		$this->assertStringContainsString( '<a href="' . esc_url( Documents::url( $docs['design'] ) ) . '">Ficha técnica.odt</a>', $body );
		$this->assertStringNotContainsString( 'Escaleta', $body );
		$this->assertStringNotContainsString( 'fmc_documento=' . $docs['sessions'], $body );
	}

	/**
	 * La tarjeta de un curso lleva su imagen representativa, que se sube para
	 * eso; sin imagen, la tarjeta sale igual.
	 */
	public function test_a_card_shows_the_image_of_the_course() {
		$with  = $this->design( 'Con imagen', array() );
		$image = self::factory()->attachment->create( array( 'post_parent' => $with ) );
		update_post_meta( $image, Documents::KIND_META, 'image' );
		$this->design( 'Sin imagen', array() );
		$_GET[ Catalogue::ARG_LIST ] = '1';

		$body = Catalogue::screen()['body'];
		$this->assertSame( Documents::url( $image ), Catalogue::image( $with ) );
		$this->assertSame( 1, substr_count( $body, '<img ' ) );
		$this->assertStringContainsString( '<img src="' . esc_url( Documents::url( $image ) ) . '" alt="" loading="lazy">', $body );
		$this->assertSame( array( 'Con imagen', 'Sin imagen' ), $this->titles() );
		// Sin sesión, la imagen de un curso del catálogo se ve.
		$this->assertSame( 'public', Documents::verdict( $image ) );
	}

	/**
	 * «Ver en el catálogo» lleva a donde el profesorado ve el catálogo, que
	 * puede ser otra web: lo dice el despliegue. Los enlaces de dentro del
	 * catálogo propio no cambian.
	 */
	public function test_the_deployment_says_where_the_catalogue_is_seen() {
		$id = $this->design( 'Robótica', array() );
		$this->assertSame( Catalogue::url(), Catalogue::public_url() );
		$this->assertSame( Catalogue::url( $id ), Catalogue::public_url( $id ) );

		$elsewhere = static fn( $url, $design ) => 'https://otra.example/catalogo/' . ( $design ? '?curso=' . $design : '' );
		add_filter( 'fmc_catalogue_url', $elsewhere, 10, 2 );
		$this->assertSame( 'https://otra.example/catalogo/', Catalogue::public_url() );
		$this->assertSame( 'https://otra.example/catalogo/?curso=' . $id, Catalogue::public_url( $id ) );
		$this->assertStringContainsString( 'fmc_curso=' . $id, Catalogue::url( $id ), 'el catálogo propio sigue en su sitio' );
		remove_filter( 'fmc_catalogue_url', $elsewhere, 10 );

		// Un filtro que no devuelve una dirección no deja el botón sin destino.
		add_filter( 'fmc_catalogue_url', '__return_empty_string' );
		$this->assertSame( Catalogue::url( $id ), Catalogue::public_url( $id ) );
		remove_filter( 'fmc_catalogue_url', '__return_empty_string' );
	}

	/**
	 * Lo que no está en el catálogo no tiene ficha: ni un borrador, ni un
	 * histórico, ni algo que no es un diseño.
	 */
	public function test_a_course_outside_the_catalogue_is_not_found() {
		$ids = array(
			$this->design( 'Borrador', array(), 'draft' ),
			$this->design( 'Histórico', array(), PostTypes::ARCHIVED ),
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
		$id = $this->design( 'Robótica', array() );
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

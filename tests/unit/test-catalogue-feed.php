<?php
/**
 * Tests for the catalogue as data.
 *
 * @package Fmc
 */

use Fmc\Meta\DesignMetaKeys as D;
use Fmc\PostType\PostTypes;
use Fmc\PublicFront\Catalogue;
use Fmc\PublicFront\CatalogueFeed;
use Fmc\PublicFront\Documents;

/**
 * Otras webs piden el catálogo para pintarlo en sus páginas: reciben lo mismo
 * que publica la pantalla del catálogo, y nada más (ADR-0028).
 */
class Test_Catalogue_Feed extends WP_UnitTestCase {

	/**
	 * A design with its meta.
	 *
	 * @param string               $title  Title.
	 * @param array<string, mixed> $meta   Meta.
	 * @param string               $status Status.
	 * @param string               $date   Date.
	 * @return int
	 */
	private function design( string $title, array $meta = array(), string $status = 'publish', string $date = '2026-01-01 10:00:00' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'    => PostTypes::DESIGN,
				'post_title'   => $title,
				'post_status'  => $status,
				'post_date'    => $date,
				'post_content' => '<p>De qué va <strong>' . $title . '</strong></p>',
			)
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		return $id;
	}

	/**
	 * Ask the feed.
	 *
	 * @param string               $route  Route, after the namespace.
	 * @param array<string, mixed> $params Query.
	 * @return \WP_REST_Response
	 */
	private function get( string $route, array $params = array() ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'GET', '/' . CatalogueFeed::REST_NAMESPACE . $route );
		$request->set_query_params( $params );
		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Titles of the courses of a response.
	 *
	 * @param \WP_REST_Response $response Response.
	 * @return string[]
	 */
	private function titles( \WP_REST_Response $response ): array {
		return wp_list_pluck( $response->get_data()['courses'], 'title' );
	}

	/**
	 * La lista, sin sesión: solo lo finalizado que está en el catálogo, con sus
	 * filtros, sus páginas y lo que hace falta para pintar una tarjeta.
	 */
	public function test_the_list_gives_the_courses_of_the_catalogue() {
		wp_set_current_user( 0 );
		$topic = self::factory()->term->create(
			array(
				'taxonomy' => 'fmc_topic',
				'name'     => 'Robótica',
				'slug'     => 'robotica',
			)
		);
		$robot = $this->design(
			'Robots en el aula',
			array(
				D::IN_CATALOGUE => '1',
				D::CODE         => 'A007',
				D::TYPE         => 'course',
				D::MODALITY     => 'blended',
				D::HOURS_ONSITE => '8',
				D::HOURS_ONLINE => '12',
				D::METHODOLOGY  => 'De trabajo',
			),
			'publish',
			'2026-03-01 10:00:00'
		);
		wp_set_object_terms( $robot, array( $topic ), 'fmc_topic' );
		$image = self::factory()->attachment->create( array( 'post_parent' => $robot ) );
		update_post_meta( $image, Documents::KIND_META, 'image' );
		$this->design(
			'Aulas virtuales',
			array(
				D::IN_CATALOGUE => '1',
				D::TYPE         => 'one_off',
			)
		);
		$this->design( 'Finalizado fuera' );
		$this->design( 'Borrador marcado', array( D::IN_CATALOGUE => '1' ), 'draft' );
		$this->design( 'Histórico marcado', array( D::IN_CATALOGUE => '1' ), PostTypes::ARCHIVED );

		$response = $this->get( '/catalogue' );
		$data     = $response->get_data();
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'public, max-age=300', $response->get_headers()['Cache-Control'] );
		$this->assertSame( array( 'Aulas virtuales', 'Robots en el aula' ), $this->titles( $response ) );
		$this->assertSame( 2, $data['total'] );
		$this->assertSame( 1, $data['pages'] );
		$this->assertSame( 'Robótica', $data['filters']['topics']['robotica'] );
		$this->assertSame( D::TYPES, $data['filters']['types'] );

		$card = $data['courses'][1];
		$this->assertSame(
			array(
				'id'             => $robot,
				'code'           => 'A007',
				'title'          => 'Robots en el aula',
				'url'            => Catalogue::url( $robot ),
				'image'          => Documents::url( $image ),
				'excerpt'        => 'De qué va Robots en el aula',
				'date'           => '2026-03-01',
				'type'           => 'course',
				'type_label'     => D::TYPES['course'],
				'modality'       => 'blended',
				'modality_label' => D::MODALITIES['blended'],
				'hours_onsite'   => 8.0,
				'hours_online'   => 12.0,
				'topics'         => array( 'Robótica' ),
				'programmes'     => array(),
			),
			$card,
			'la tarjeta no lleva la ficha, ni nada de trabajo'
		);

		// Los filtros, los mismos de la pantalla; la temática admite varias.
		$this->assertSame( array( 'Aulas virtuales' ), $this->titles( $this->get( '/catalogue', array( 'type' => 'one_off' ) ) ) );
		$this->assertSame( array( 'Robots en el aula' ), $this->titles( $this->get( '/catalogue', array( 'topic' => 'otra, robotica' ) ) ) );
		$this->assertSame( array( 'Robots en el aula' ), $this->titles( $this->get( '/catalogue', array( 's' => 'a007' ) ) ) );
		$this->assertSame( array( 'Robots en el aula', 'Aulas virtuales' ), $this->titles( $this->get( '/catalogue', array( 'order' => 'recent' ) ) ) );
		// Un valor que no es de la lista no filtra, ni rompe.
		$this->assertCount( 2, $this->titles( $this->get( '/catalogue', array( 'type' => 'inventado' ) ) ) );

		$paged = $this->get(
			'/catalogue',
			array(
				'per_page' => 1,
				'page'     => 2,
			)
		);
		$this->assertSame( array( 'Robots en el aula' ), $this->titles( $paged ) );
		$this->assertSame( 2, $paged->get_data()['pages'] );
		$this->assertSame( 2, $paged->get_data()['page'] );
	}

	/**
	 * El resumen de la tarjeta es texto: sin etiquetas, sin las entidades con
	 * que el editor guarda las tildes, en una línea y cortado con su elipsis.
	 */
	public function test_the_excerpt_is_plain_text() {
		$this->assertSame( 'Diseño de demostración. Con <dos> partes & media', CatalogueFeed::excerpt( "<p>Dise&ntilde;o de&nbsp;demostraci&oacute;n.</p>\n<ul><li>Con &lt;dos&gt; partes &amp; media</li></ul>" ) );
		$this->assertSame( '', CatalogueFeed::excerpt( ' <p>&nbsp;</p> ' ) );
		$long = CatalogueFeed::excerpt( str_repeat( 'año', 80 ) );
		$this->assertSame( CatalogueFeed::EXCERPT + 1, mb_strlen( $long ) );
		$this->assertStringEndsWith( '…', $long );
		// Si el corte cae en un espacio, la elipsis va pegada a la última palabra.
		$this->assertStringEndsWith( 'año…', CatalogueFeed::excerpt( str_repeat( 'año ', 80 ) ) );
	}

	/**
	 * Un curso entero: lo que publica su ficha, su ficha técnica y nada de trabajo.
	 */
	public function test_a_course_gives_what_its_sheet_publishes() {
		wp_set_current_user( 0 );
		$id = $this->design(
			'Impresión 3D',
			array(
				D::IN_CATALOGUE => '1',
				D::AUDIENCE     => 'Profesorado de secundaria',
				D::OBJECTIVES   => "Uno\n\nDos<script>alert(1)</script>",
				D::METHODOLOGY  => 'Solo de trabajo',
				D::NOTES        => 'Observación interna',
				D::AUTHORSHIP   => 'Quien lo escribió',
				D::DIGCOMP_URL  => 'https://ejemplo.example/perfil?c=1.3.A2.1|2.2.A1.3#x',
			)
		);
		foreach ( array(
			'design'   => 'Ficha técnica.odt',
			'sessions' => 'Escaleta.odt',
		) as $kind => $name ) {
			$doc = self::factory()->attachment->create( array( 'post_parent' => $id ) );
			update_post_meta( $doc, Documents::KIND_META, $kind );
			update_post_meta( $doc, Documents::NAME_META, $name );
			$docs[ $kind ] = $doc;
		}

		$response = $this->get( '/catalogue/' . $id );
		$data     = $response->get_data();
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Impresión 3D', $data['title'] );
		$this->assertStringContainsString( '<strong>Impresión 3D</strong>', $data['description'] );
		$this->assertSame( "<p>Profesorado de secundaria</p>\n", $data['audience'] );
		$this->assertStringContainsString( '<p>Uno</p>', $data['objectives'] );
		$this->assertStringNotContainsString( '<script', $data['objectives'] );
		$this->assertSame( '', $data['contents'], 'lo vacío, vacío' );
		// El enlace al perfil lleva a la herramienta, con la misma selección.
		$this->assertSame( 'https://elidd.ernesto.es/?c=1.3.A2.1|2.2.A1.3', $data['digcomp_url'] );
		// El perfil, ya resumido: quien lo pinta en otra web no necesita conocer el marco.
		$this->assertCount( 6, $data['digcomp'] );
		$this->assertSame( 'A2', array_column( $data['digcomp'][0]['competences'], 'level', 'code' )['1.3'] );
		$this->assertSame( 'A1', array_column( $data['digcomp'][1]['competences'], 'level', 'code' )['2.2'] );
		$this->assertSame( '', array_column( $data['digcomp'][2]['competences'], 'level', 'code' )['3.1'] );
		$this->assertSame(
			array(
				array(
					'kind' => 'Diseño del curso',
					'name' => 'Ficha técnica.odt',
					'url'  => Documents::url( $docs['design'] ),
				),
			),
			$data['documents']
		);
		$json = (string) wp_json_encode( $data );
		foreach ( array( 'Solo de trabajo', 'Observación interna', 'Quien lo escribió', 'Escaleta' ) as $private ) {
			$this->assertStringNotContainsString( $private, $json );
		}
	}

	/**
	 * Lo que no está en el catálogo no existe para quien lo pide: ni un
	 * finalizado fuera de él, ni un borrador, ni un histórico, ni otra cosa.
	 * Tampoco con sesión de administración.
	 */
	public function test_a_course_outside_the_catalogue_is_not_found() {
		$ids = array(
			$this->design( 'Fuera' ),
			$this->design( 'Borrador', array( D::IN_CATALOGUE => '1' ), 'draft' ),
			$this->design( 'Histórico', array( D::IN_CATALOGUE => '1' ), PostTypes::ARCHIVED ),
			self::factory()->post->create( array( 'post_title' => 'Una entrada' ) ),
			999999,
		);
		foreach ( array( 0, self::factory()->user->create( array( 'role' => 'administrator' ) ) ) as $user ) {
			wp_set_current_user( $user );
			foreach ( $ids as $id ) {
				$response = $this->get( '/catalogue/' . $id );
				$this->assertSame( 404, $response->get_status() );
				$this->assertStringNotContainsString( 'De qué va', (string) wp_json_encode( $response->get_data() ) );
			}
		}
	}

	/**
	 * Solo se lee: no hay ruta que escriba.
	 */
	public function test_the_feed_is_read_only() {
		$routes = rest_get_server()->get_routes( CatalogueFeed::REST_NAMESPACE );
		$this->assertArrayHasKey( '/' . CatalogueFeed::REST_NAMESPACE . '/catalogue', $routes );
		foreach ( $routes as $route => $handlers ) {
			foreach ( $handlers as $handler ) {
				$this->assertSame( array( 'GET' => true ), $handler['methods'], $route );
			}
		}
		$request = new \WP_REST_Request( 'POST', '/' . CatalogueFeed::REST_NAMESPACE . '/catalogue' );
		$this->assertSame( 404, rest_get_server()->dispatch( $request )->get_status() );
	}
}

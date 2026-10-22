<?php
/**
 * Tests for the list screens: rows, counters, filters, pages and the CSV.
 *
 * @package Fmc
 */

use Fmc\Meta\ActionMetaKeys as A;
use Fmc\Meta\DesignMetaKeys as D;
use Fmc\Meta\IncidentMetaKeys as I;
use Fmc\Meta\MetaRegistration;
use Fmc\Meta\SpeakerMetaKeys as S;
use Fmc\PostType\PostTypes;
use Fmc\PublicFront\Fields;
use Fmc\PublicFront\Lists;
use Fmc\PublicFront\Speakers;
use Fmc\Taxonomy\Taxonomies;

/**
 * Qué filas ve cada cual lo decide la consulta: estos tests leen lo que sale
 * en la pantalla y en el CSV, no cómo se monta la consulta.
 */
class Test_Lists extends WP_UnitTestCase {

	/**
	 * Roles, caps and meta in place.
	 */
	public function set_up() {
		parent::set_up();
		fmc_register_roles();
		PostTypes::grant_caps_to_roles();
		MetaRegistration::register_meta();
	}

	/**
	 * Leave the request clean for the next test.
	 */
	public function tear_down() {
		$_GET = array();
		parent::tear_down();
	}

	/**
	 * A logged-in user with a role.
	 *
	 * @param string $role Role.
	 * @return int
	 */
	private function as_role( string $role ): int {
		$id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $id );
		return $id;
	}

	/**
	 * A post of a type with its metas and terms.
	 *
	 * @param string               $type  Post type.
	 * @param string               $title Title.
	 * @param array<string, mixed> $meta  Meta key => value.
	 * @param array<string, mixed> $args  Extra post args.
	 * @return int
	 */
	private function post( string $type, string $title, array $meta = array(), array $args = array() ): int {
		$id = self::factory()->post->create(
			$args + array(
				'post_type'   => $type,
				'post_title'  => $title,
				'post_author' => get_current_user_id(),
			)
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		return $id;
	}

	/**
	 * A term of a taxonomy, attached to some posts.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $name     Name.
	 * @param int[]  $posts    Posts.
	 * @return WP_Term
	 */
	private function term( string $taxonomy, string $name, array $posts ): WP_Term {
		$term = get_term(
			self::factory()->term->create(
				array(
					'taxonomy' => $taxonomy,
					'name'     => $name,
				)
			)
		);
		foreach ( $posts as $post ) {
			wp_set_object_terms( $post, $term->term_id, $taxonomy, true );
		}
		return $term;
	}

	/**
	 * The titles of the rows of a list screen, in order.
	 *
	 * @param string $type Post type.
	 * @return string[]
	 */
	private function rows( string $type ): array {
		preg_match_all( '/<a class="fw-semibold fmc-abre-panel" href="[^"]*">(.*?)<\/a>/', Lists::screen( 'tab', $type )['body'], $m );
		return $m[1];
	}

	/**
	 * The counters of a list screen, label => number.
	 *
	 * @param string $body Screen body.
	 * @return array<string, int>
	 */
	private function counters( string $body ): array {
		preg_match_all( '/<strong>(\d+)<\/strong><span>([^<]+)<\/span>/', $body, $m );
		return array_map( 'intval', array_combine( $m[2], $m[1] ) );
	}

	/**
	 * Run the CSV export and read it back as rows.
	 *
	 * @param string $type Post type.
	 * @return array{0: string, 1: list<list<string>>}
	 */
	private function csv( string $type ): array {
		// PHPUnit ya ha escrito en la salida: las cabeceras de la descarga no
		// pueden mandarse y PHP avisa. Se calla ese aviso y solo ese.
		$previous = set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- ver arriba.
			static function ( $errno, $errstr, ...$rest ) use ( &$previous ) {
				if ( false !== strpos( $errstr, 'Cannot modify header information' ) ) {
					return true;
				}
				return $previous ? $previous( $errno, $errstr, ...$rest ) : false;
			}
		);
		ob_start();
		try {
			Lists::csv( $type );
		} finally {
			$raw = (string) ob_get_clean();
			restore_error_handler();
		}
		// fgetcsv y no explode(): un campo de texto puede llevar saltos de línea.
		$in = fopen( 'php://memory', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- lectura en memoria.
		fwrite( $in, substr( $raw, 3 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- lectura en memoria.
		rewind( $in );
		$rows = array();
		for ( $row = fgetcsv( $in, 0, ';' ); false !== $row; $row = fgetcsv( $in, 0, ';' ) ) {
			$rows[] = $row;
		}
		fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- lectura en memoria.
		return array( $raw, $rows );
	}

	/**
	 * La pantalla trae el título del tipo, sus filas y los contadores por situación.
	 */
	public function test_the_actions_screen_lists_rows_and_counts_them_by_situation() {
		$this->as_role( 'fmc_curator' );
		$this->post( PostTypes::ACTION, 'Robótica', array( A::SITUATION => 'done' ) );
		$this->post( PostTypes::ACTION, 'Ajedrez', array( A::SITUATION => 'done' ) );
		$this->post( PostTypes::ACTION, 'Teatro', array( A::SITUATION => 'running' ) );

		$screen   = Lists::screen( 'actions', PostTypes::ACTION );
		$counters = $this->counters( $screen['body'] );

		$this->assertSame( 'actions', $screen['tab'] );
		$this->assertSame( 'Acciones formativas', $screen['title'] );
		$this->assertSame( 3, $counters['En total'] );
		$this->assertSame( 2, $counters['Realizada'] );
		$this->assertSame( 1, $counters['Impartiéndose'] );
		$this->assertSame( 0, $counters['Cancelada'] );
		$this->assertStringContainsString( 'text-bg-success">Realizada</span>', $screen['body'] );
		$this->assertStringContainsString( '+ Añadir acción formativa', $screen['actions'] );
	}

	/**
	 * Filtrar por situación estrecha la tabla, pero los contadores siguen
	 * contando todas: son los que permiten cambiar de situación.
	 */
	public function test_the_situation_filter_narrows_the_rows_but_not_the_counters() {
		$this->as_role( 'fmc_curator' );
		$this->post( PostTypes::ACTION, 'Robótica', array( A::SITUATION => 'done' ) );
		$this->post( PostTypes::ACTION, 'Teatro', array( A::SITUATION => 'running' ) );
		$_GET['fmc_situation'] = 'done';
		$_GET['fmc_page']      = '1';
		$_GET['fmc_tab']       = 'actions';

		$screen = Lists::screen( 'actions', PostTypes::ACTION );

		$this->assertSame( array( 'Robótica' ), $this->rows( PostTypes::ACTION ) );
		$this->assertSame( 2, $this->counters( $screen['body'] )['En total'] );
		// El contador activo es el de la situación filtrada, y su enlace no
		// arrastra la página en la que se estaba.
		$this->assertMatchesRegularExpression( '/<a class="fmc-counter is-active" href="[^"]*fmc_situation=done[^"]*"><strong>1<\/strong><span>Realizada/', $screen['body'] );
		$this->assertMatchesRegularExpression( '/<a class="fmc-counter" href="[^"]*"><strong>2<\/strong><span>En total/', $screen['body'] );
		$this->assertStringNotContainsString( 'fmc_page', implode( '', $this->counter_links( $screen['body'] ) ) );
		// El CSV exporta lo filtrado.
		$this->assertMatchesRegularExpression( '/fmc_situation=done[^"]*fmc_csv=1|fmc_csv=1[^"]*fmc_situation=done/', $screen['actions'] );
	}

	/**
	 * The hrefs of the counter cards.
	 *
	 * @param string $body Screen body.
	 * @return string[]
	 */
	private function counter_links( string $body ): array {
		preg_match_all( '/<a class="fmc-counter[^"]*" href="([^"]*)"/', $body, $m );
		return $m[1];
	}

	/**
	 * Un título hostil sale escapado en la fila y en el botón.
	 */
	public function test_hostile_titles_are_escaped() {
		$this->as_role( 'administrator' );
		$this->post( PostTypes::SPEAKER, '<script>alert(1)</script>Ana', array( S::EMAIL => 'ana@example.org' ) );

		$body = Lists::screen( 'speakers', PostTypes::SPEAKER )['body'];

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $body );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;Ana', $body );
	}

	/**
	 * La asesoría ve lo publicado y lo suyo; la validación, todo.
	 */
	public function test_an_adviser_only_sees_published_designs_and_her_own() {
		$other = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$this->post( PostTypes::DESIGN, 'Ajeno publicado', array(), array( 'post_author' => $other ) );
		$this->post(
			PostTypes::DESIGN,
			'Ajeno en borrador',
			array(),
			array(
				'post_author' => $other,
				'post_status' => 'draft',
			)
		);
		$mine = $this->as_role( 'fmc_adviser' );
		$this->post(
			PostTypes::DESIGN,
			'Mío en revisión',
			array(),
			array(
				'post_author' => $mine,
				'post_status' => 'pending',
			)
		);

		$this->assertSame( array( 'Ajeno publicado', 'Mío en revisión' ), $this->rows( PostTypes::DESIGN ) );
		$counters = $this->counters( Lists::screen( 'designs', PostTypes::DESIGN )['body'] );
		$this->assertSame( 2, $counters['En total'] );
		$this->assertSame( 0, $counters['Borrador'] );

		$this->as_role( 'fmc_curator' );
		$this->assertSame( array( 'Ajeno en borrador', 'Ajeno publicado', 'Mío en revisión' ), $this->rows( PostTypes::DESIGN ) );
	}

	/**
	 * Cada estado del diseño lleva su chapa, y el filtro de estado la estrecha.
	 */
	public function test_designs_show_their_status_and_filter_by_it() {
		$this->as_role( 'fmc_curator' );
		$this->post( PostTypes::DESIGN, 'A', array(), array( 'post_status' => 'draft' ) );
		$this->post( PostTypes::DESIGN, 'B', array(), array( 'post_status' => 'pending' ) );
		$this->post( PostTypes::DESIGN, 'C' );

		$body = Lists::screen( 'designs', PostTypes::DESIGN )['body'];
		$this->assertStringContainsString( 'A</a> <span class="badge rounded-pill text-bg-secondary">Borrador</span>', $body );
		$this->assertStringContainsString( 'B</a> <span class="badge rounded-pill text-bg-warning">En revisión</span>', $body );
		$this->assertStringContainsString( 'C</a> <span class="badge rounded-pill text-bg-success">Finalizado</span>', $body );
		$this->assertSame(
			array(
				'En total'    => 3,
				'Borrador'    => 1,
				'En revisión' => 1,
				'Finalizado'  => 1,
			),
			$this->counters( $body )
		);

		$_GET['fmc_status'] = 'pending';
		$this->assertSame( array( 'B' ), $this->rows( PostTypes::DESIGN ) );
		// Un estado que no es de la lista no filtra.
		$_GET['fmc_status'] = 'trash';
		$this->assertSame( array( 'A', 'B', 'C' ), $this->rows( PostTypes::DESIGN ) );
	}

	/**
	 * Los filtros del diseño: tipo, modalidad, catálogo, programa y temática.
	 */
	public function test_design_filters_narrow_the_list() {
		$this->as_role( 'fmc_curator' );
		$a         = $this->post(
			PostTypes::DESIGN,
			'Curso presencial',
			array(
				D::TYPE         => 'course',
				D::MODALITY     => 'onsite',
				D::IN_CATALOGUE => true,
			)
		);
		$b         = $this->post(
			PostTypes::DESIGN,
			'Puntual en línea',
			array(
				D::TYPE     => 'one_off',
				D::MODALITY => 'online',
			)
		);
		$programme = $this->term( 'fmc_programme', 'Programa uno', array( $a ) );
		$topic     = $this->term( 'fmc_topic', 'Temática dos', array( $b ) );

		$cases = array(
			array( 'fmc_type', 'course', array( 'Curso presencial' ) ),
			array( 'fmc_modality', 'online', array( 'Puntual en línea' ) ),
			array( 'fmc_catalogue', '1', array( 'Curso presencial' ) ),
			array( 'fmc_programme', $programme->slug, array( 'Curso presencial' ) ),
			array( 'fmc_topic', $topic->slug, array( 'Puntual en línea' ) ),
			array( 'fmc_s', 'Puntual', array( 'Puntual en línea' ) ),
		);
		foreach ( $cases as list( $arg, $value, $expected ) ) {
			$_GET = array( $arg => $value );
			$this->assertSame( $expected, $this->rows( PostTypes::DESIGN ), $arg );
		}

		// Cualquier otro valor de «catálogo» no filtra.
		$_GET = array( 'fmc_catalogue' => '0' );
		$this->assertCount( 2, $this->rows( PostTypes::DESIGN ) );
	}

	/**
	 * Las columnas del diseño: código, catálogo, tipo, horas, competencias y programas.
	 */
	public function test_design_columns_read_the_design() {
		$this->as_role( 'fmc_curator' );
		$id = $this->post(
			PostTypes::DESIGN,
			'Curso',
			array(
				D::CODE         => 'CD-7',
				D::IN_CATALOGUE => true,
				D::TYPE         => 'course',
				D::MODALITY     => 'blended',
				D::HOURS_ONSITE => 8,
				D::HOURS_ONLINE => 12,
			)
		);
		$this->term( 'fmc_topic', 'Comunicación', array( $id ) );
		$this->term( 'fmc_programme', 'Programa uno', array( $id ) );

		$body = Lists::screen( 'designs', PostTypes::DESIGN )['body'];

		$this->assertStringContainsString( '<td>CD-7<div class="small text-secondary">En el catálogo</div></td>', $body );
		$this->assertStringContainsString( '<td>Curso<div class="small text-secondary">Mixta</div></td>', $body );
		$this->assertStringContainsString( '<td>8 + 12 h</td>', $body );
		$this->assertStringContainsString( '<td>Comunicación</td>', $body );
		$this->assertStringContainsString( '<td>Programa uno</td>', $body );
		$this->assertStringContainsString( '+ Añadir diseño de curso', Lists::screen( 'designs', PostTypes::DESIGN )['actions'] );
	}

	/**
	 * Las columnas de la acción: expediente, ámbito y centro, ponentes, tipo y horas, fechas, estado y curso.
	 */
	public function test_action_columns_read_the_action_and_its_design() {
		$this->as_role( 'fmc_curator' );
		$design  = $this->post( PostTypes::DESIGN, 'Diseño', array( D::TYPE => 'e_learning' ) );
		$speaker = $this->post( PostTypes::SPEAKER, 'Ponente Uno' );
		$id      = $this->post(
			PostTypes::ACTION,
			'Acción',
			array(
				A::FILE_NUMBER  => 'EXP-42',
				A::VENUE_CENTRE => 'Centro de formación Norte',
				A::SPEAKERS     => array( $speaker ),
				A::DESIGN_ID    => $design,
				A::MODALITY     => 'online',
				A::HOURS_ONLINE => 20,
				A::START        => '2026-03-10',
				A::END          => '2026-03-24',
				A::SCHEDULE     => 'Martes y jueves por la tarde',
				A::PROCESS      => 'paid',
			)
		);
		$this->term( 'fmc_scope', 'Ámbito norte', array( $id ) );

		$body = Lists::screen( 'actions', PostTypes::ACTION )['body'];

		// El expediente, debajo del título.
		$this->assertStringContainsString( '>Acción</a><div class="small text-secondary">EXP-42</div></td>', $body );
		$this->assertStringContainsString( '<td>Ámbito norte<div class="small text-secondary">Centro de formación Norte</div></td>', $body );
		$this->assertStringContainsString( '<td>Ponente Uno</td>', $body );
		$this->assertStringContainsString( '<td>Teleformación<div class="small text-secondary">En línea · 20 h</div></td>', $body );
		$this->assertStringContainsString( '<td>10/03/2026 – 24/03/2026<div class="small text-secondary">Martes y jueves por la tarde</div></td>', $body );
		$this->assertStringContainsString( '<td>Pagada por el centro<div class="small text-secondary">2025-2026</div></td>', $body );
	}

	/**
	 * Las acciones más recientes arriba y las que no tienen fecha, al final.
	 */
	public function test_actions_are_ordered_by_start_newest_first_and_undated_last() {
		$this->as_role( 'fmc_curator' );
		$this->post( PostTypes::ACTION, 'Sin fecha' );
		$this->post( PostTypes::ACTION, 'Antigua', array( A::START => '2024-01-10' ) );
		$this->post( PostTypes::ACTION, 'Reciente', array( A::START => '2026-05-10' ) );

		$this->assertSame( array( 'Reciente', 'Antigua', 'Sin fecha' ), $this->rows( PostTypes::ACTION ) );
	}

	/**
	 * Los filtros de la acción: ámbito, programa, modalidad, estado, fechas y curso escolar.
	 */
	public function test_action_filters_narrow_the_list() {
		$this->as_role( 'fmc_curator' );
		$march = $this->post(
			PostTypes::ACTION,
			'Marzo',
			array(
				A::START    => '2026-03-10',
				A::MODALITY => 'onsite',
				A::PROCESS  => 'paid',
			)
		);
		$june  = $this->post(
			PostTypes::ACTION,
			'Junio',
			array(
				A::START    => '2026-06-10',
				A::MODALITY => 'online',
			)
		);
		$this->post( PostTypes::ACTION, 'Octubre', array( A::START => '2026-10-10' ) );
		$scope     = $this->term( 'fmc_scope', 'Norte', array( $march ) );
		$programme = $this->term( 'fmc_programme', 'Programa', array( $june ) );

		$cases = array(
			array( array( 'fmc_scope' => $scope->slug ), array( 'Marzo' ) ),
			array( array( 'fmc_programme' => $programme->slug ), array( 'Junio' ) ),
			array( array( 'fmc_modality' => 'online' ), array( 'Junio' ) ),
			array( array( 'fmc_process' => 'paid' ), array( 'Marzo' ) ),
			array( array( 'fmc_from' => '2026-06-01' ), array( 'Octubre', 'Junio' ) ),
			array( array( 'fmc_to' => '2026-06-10' ), array( 'Junio', 'Marzo' ) ),
			array(
				array(
					'fmc_from' => '2026-04-01',
					'fmc_to'   => '2026-09-30',
				),
				array( 'Junio' ),
			),
			// El curso escolar va de septiembre a agosto.
			array( array( 'fmc_year' => '2026-2027' ), array( 'Octubre' ) ),
			array( array( 'fmc_year' => '2025-2026' ), array( 'Junio', 'Marzo' ) ),
			// Una fecha o un curso mal escritos no filtran.
			array( array( 'fmc_from' => '10/06/2026' ), array( 'Octubre', 'Junio', 'Marzo' ) ),
			array( array( 'fmc_year' => '2026' ), array( 'Octubre', 'Junio', 'Marzo' ) ),
		);
		foreach ( $cases as list( $get, $expected ) ) {
			$_GET = $get;
			$this->assertSame( $expected, $this->rows( PostTypes::ACTION ), wp_json_encode( $get ) );
		}
	}

	/**
	 * Sin diseños de ese tipo, el filtro por tipo no deja ninguna acción.
	 */
	public function test_a_design_type_without_designs_leaves_no_actions() {
		$this->as_role( 'fmc_curator' );
		$this->post( PostTypes::ACTION, 'Acción' );
		$_GET['fmc_type'] = 'self_paced';

		$this->assertSame( array(), $this->rows( PostTypes::ACTION ) );
		$this->assertStringContainsString( 'No hay nada que mostrar con estos filtros.', Lists::screen( 'actions', PostTypes::ACTION )['body'] );
	}

	/**
	 * Los filtros del ponente: zona, disponibilidad y diseño recomendado.
	 */
	public function test_speaker_filters_narrow_the_list() {
		$this->as_role( 'fmc_curator' );
		$design = $this->post( PostTypes::DESIGN, 'Diseño' );
		$ana    = $this->post( PostTypes::SPEAKER, 'Ana', array( S::RECOMMENDED => array( $design ) ) );
		$berta  = $this->post(
			PostTypes::SPEAKER,
			'Berta',
			array(
				S::UNAVAILABLE => true,
				S::NOTES       => 'De baja hasta junio',
			)
		);
		wp_set_object_terms( $ana, 'Norte', Taxonomies::ZONE );
		wp_set_object_terms( $berta, 'Sur', Taxonomies::ZONE );

		$cases = array(
			array( array( 'fmc_zone' => 'sur' ), array( 'Berta' ) ),
			array( array( 'fmc_available' => '1' ), array( 'Ana' ) ),
			array( array( 'fmc_available' => '0' ), array( 'Berta' ) ),
			array( array( 'fmc_recommended' => (string) $design ), array( 'Ana' ) ),
		);
		foreach ( $cases as list( $get, $expected ) ) {
			$_GET = $get;
			$this->assertSame( $expected, $this->rows( PostTypes::SPEAKER ), wp_json_encode( $get ) );
		}

		$_GET = array();
		$body = Lists::screen( 'speakers', PostTypes::SPEAKER )['body'];
		$this->assertStringContainsString( 'Berta</a> <span class="badge rounded-pill text-bg-secondary">No disponible</span> <button type="button" class="btn btn-link btn-sm p-0 fmc-note" data-bs-toggle="tooltip" title="De baja hasta junio" aria-label="Observaciones: De baja hasta junio" data-fmc-note="De baja hasta junio">', $body );
		$this->assertStringContainsString( '<td>Diseño</td>', $body );
		$this->assertStringContainsString( '<td>Sur</td>', $body );
		// Las islas del desplegable son los términos de la taxonomía (ADR-0015).
		$this->assertStringContainsString( '<option value="norte">Norte</option><option value="sur">Sur</option></select>', $body );
	}

	/**
	 * Las incidencias: las más nuevas arriba, con el expediente de su acción y su resolución.
	 */
	public function test_incidents_show_their_action_and_filter_by_resolution() {
		$this->as_role( 'fmc_curator' );
		$action = $this->post( PostTypes::ACTION, 'Acción', array( A::FILE_NUMBER => 'EXP-9' ) );
		$this->term( 'fmc_scope', 'Sur', array( $action ) );
		$this->post(
			PostTypes::INCIDENT,
			'',
			array(
				I::REASON   => 'Cambio de fechas',
				I::HAS_COST => true,
			),
			array(
				'post_parent' => $action,
				'post_date'   => '2026-01-01 10:00:00',
			)
		);
		$this->post(
			PostTypes::INCIDENT,
			'Nueva',
			array( I::RESOLUTION => 'approved' ),
			array( 'post_date' => '2026-02-01 10:00:00' )
		);

		$screen = Lists::screen( 'incidents', PostTypes::INCIDENT );

		$this->assertSame( array( 'Nueva', '(sin título)' ), $this->rows( PostTypes::INCIDENT ) );
		$this->assertStringContainsString( '<td>EXP-9<div class="small text-secondary">Sur</div></td>', $screen['body'] );
		$this->assertStringContainsString( '<td>Cambio de fechas</td><td>Con coste</td><td>Pendiente</td>', $screen['body'] );
		$this->assertStringContainsString( '<td>Sin coste</td><td>Aprobada</td>', $screen['body'] );
		$this->assertSame( 1, $this->counters( $screen['body'] )['Aprobada'] );
		// La incidencia se abre desde su acción: aquí no hay botón de añadir.
		$this->assertStringNotContainsString( 'Añadir', $screen['actions'] );

		$_GET['fmc_resolution'] = 'approved';
		$this->assertSame( array( 'Nueva' ), $this->rows( PostTypes::INCIDENT ) );
	}

	/**
	 * Cincuenta por página, con la página en la que se está y los filtros en los enlaces.
	 */
	public function test_long_lists_are_paged() {
		$this->as_role( 'fmc_curator' );
		self::factory()->post->create_many(
			Lists::PER_PAGE + 1,
			array(
				'post_type'  => PostTypes::SPEAKER,
				'post_title' => 'Ponente',
			)
		);
		$_GET = array(
			'fmc_tab'  => 'speakers',
			'fmc_zone' => '',
		);

		$body = Lists::screen( 'speakers', PostTypes::SPEAKER )['body'];
		$this->assertCount( Lists::PER_PAGE, $this->rows( PostTypes::SPEAKER ) );
		$this->assertStringContainsString( 'Página 1 de 2', $body );
		$this->assertMatchesRegularExpression( '/<li class="page-item disabled"><a class="page-link" href="[^"]*fmc_page=1[^"]*">‹ Anterior/', $body );
		$this->assertMatchesRegularExpression( '/<li class="page-item"><a class="page-link" href="[^"]*fmc_tab=speakers[^"]*fmc_page=2[^"]*">Siguiente ›/', $body );

		$_GET['fmc_page'] = '2';
		$body             = Lists::screen( 'speakers', PostTypes::SPEAKER )['body'];
		$this->assertCount( 1, $this->rows( PostTypes::SPEAKER ) );
		$this->assertStringContainsString( 'Página 2 de 2', $body );
		$this->assertMatchesRegularExpression( '/<li class="page-item disabled"><a class="page-link" href="[^"]*">Siguiente ›/', $body );
	}

	/**
	 * Una lista corta no lleva paginación.
	 */
	public function test_short_lists_have_no_pager() {
		$this->as_role( 'fmc_curator' );
		$this->post( PostTypes::SPEAKER, 'Ana' );

		$this->assertStringNotContainsString( 'pagination', Lists::screen( 'speakers', PostTypes::SPEAKER )['body'] );
	}

	/**
	 * El servicio de formación lee las acciones, pero no añade ni edita.
	 */
	public function test_the_training_service_reads_actions_without_editing_them() {
		$adviser = $this->as_role( 'fmc_adviser' );
		$this->post( PostTypes::ACTION, 'Acción', array(), array( 'post_author' => $adviser ) );
		$post = get_post( $this->post( PostTypes::ACTION, 'Otra', array(), array( 'post_author' => $adviser ) ) );

		// La asesoría edita la suya y abre una incidencia desde la fila.
		$buttons = Lists::row_buttons( $post );
		$this->assertMatchesRegularExpression( '/title="Editar" aria-label="Editar «Otra»" href="[^"]*"><i class="bi bi-pencil" aria-hidden="true"><\/i><\/a>/', $buttons );
		$this->assertStringContainsString( 'title="Abrir una incidencia" aria-label="Abrir una incidencia sobre «Otra»"', $buttons );
		$this->assertMatchesRegularExpression( '/fmc_new=fmc_incident&(amp;|#038;)?fmc_parent=' . $post->ID . '/', $buttons );

		$this->as_role( 'fmc_training_service' );
		$screen  = Lists::screen( 'actions', PostTypes::ACTION );
		$buttons = Lists::row_buttons( $post );
		$this->assertStringNotContainsString( 'Añadir', $screen['actions'] );
		$this->assertStringContainsString( 'Exportar CSV', $screen['actions'] );
		$this->assertMatchesRegularExpression( '/title="Ver" aria-label="Ver «Otra»" href="[^"]*"><i class="bi bi-eye" aria-hidden="true"><\/i><\/a>/', $buttons );
		$this->assertStringNotContainsString( 'fmc_incident', $buttons );
		$this->assertStringNotContainsString( 'fmc_delete', $buttons );

		$this->as_role( 'administrator' );
		$buttons = Lists::row_buttons( $post );
		$this->assertStringContainsString( 'name="fmc_delete" value="' . $post->ID . '"', $buttons );
		$this->assertStringContainsString( 'title="Borrar" aria-label="Borrar «Otra»"><i class="bi bi-trash3" aria-hidden="true"></i></button>', $buttons );
	}

	/**
	 * Las cabeceras ordenan en el servidor, en los dos sentidos, y lo que no
	 * tiene el dato va al final; filtrar o paginar no pierde el orden.
	 */
	public function test_headings_sort_the_whole_list_both_ways() {
		$this->as_role( 'fmc_curator' );
		$this->post( PostTypes::ACTION, 'B', array( A::START => '2026-03-01' ) );
		$this->post( PostTypes::ACTION, 'A', array( A::START => '2026-05-01' ) );
		$this->post( PostTypes::ACTION, 'C sin fecha' );

		$this->assertSame( array( 'A', 'B', 'C sin fecha' ), $this->rows( PostTypes::ACTION ), 'de serie, la más reciente arriba' );
		$_GET = array(
			'fmc_sort' => 'start',
			'fmc_dir'  => 'asc',
		);
		$this->assertSame( array( 'B', 'A', 'C sin fecha' ), $this->rows( PostTypes::ACTION ), 'sin fecha, al final también al revés' );
		$_GET = array(
			'fmc_sort' => 'title',
			'fmc_dir'  => 'desc',
		);
		$this->assertSame( array( 'C sin fecha', 'B', 'A' ), $this->rows( PostTypes::ACTION ) );

		$body = Lists::screen( 'actions', PostTypes::ACTION )['body'];
		$this->assertMatchesRegularExpression( '/<th scope="col" aria-sort="descending"><a class="fmc-sort is-active" href="[^"]*fmc_sort=title[^"]*fmc_dir=asc[^"]*">Acción formativa <i class="bi bi-sort-down"/', $body, 'pulsarla otra vez le da la vuelta' );
		$this->assertMatchesRegularExpression( '/<th scope="col"><a class="fmc-sort" href="[^"]*fmc_sort=start[^"]*fmc_dir=desc[^"]*">Fechas/', $body, 'otra columna empieza por lo más reciente' );
		$this->assertStringContainsString( '<th scope="col">Ponentes</th>', $body );
		$this->assertStringContainsString( '<input type="hidden" name="fmc_sort" value="title">', $body, 'filtrar no pierde el orden' );

		$this->post( PostTypes::DESIGN, 'Sin código' );
		$this->post( PostTypes::DESIGN, 'Segundo', array( D::CODE => 'A002' ) );
		$this->post( PostTypes::DESIGN, 'Primero', array( D::CODE => 'A001' ) );
		$_GET = array(
			'fmc_sort' => 'code',
			'fmc_dir'  => 'desc',
		);
		$this->assertSame( array( 'Segundo', 'Primero', 'Sin código' ), $this->rows( PostTypes::DESIGN ) );

		wp_set_object_terms( $this->post( PostTypes::SPEAKER, 'Ana' ), 'Norte', Taxonomies::ZONE );
		wp_set_object_terms( $this->post( PostTypes::SPEAKER, 'Bea' ), 'Sur', Taxonomies::ZONE );
		$_GET = array(
			'fmc_sort' => 'zone',
			'fmc_dir'  => 'desc',
		);
		$this->assertSame( array( 'Bea', 'Ana' ), $this->rows( PostTypes::SPEAKER ) );
		$_GET['fmc_dir'] = 'asc';
		$this->assertSame( array( 'Ana', 'Bea' ), $this->rows( PostTypes::SPEAKER ) );
	}

	/**
	 * La tarjeta de filtros conserva lo elegido y el mes del calendario.
	 */
	public function test_the_filter_card_keeps_the_current_choices() {
		$this->as_role( 'fmc_curator' );
		$speaker = $this->post( PostTypes::SPEAKER, 'Ana' );
		$this->post( PostTypes::SPEAKER, 'Borrador', array(), array( 'post_status' => 'draft' ) );
		$_GET = array(
			'fmc_situation' => 'done',
			'fmc_month'     => '2026-03',
			'fmc_from'      => '2026-01-01',
			'fmc_s'         => '"><b>x',
		);

		$html = Lists::filters_html( 'actions', PostTypes::ACTION );

		$this->assertStringContainsString( '<input type="hidden" name="fmc_tab" value="actions">', $html );
		$this->assertStringContainsString( '<input type="hidden" name="fmc_month" value="2026-03">', $html );
		$this->assertStringContainsString( '<option value="done" selected=\'selected\'>Realizada</option>', $html );
		$this->assertStringContainsString( 'id="fmc_from" name="fmc_from" value="2026-01-01"', $html );
		$this->assertStringContainsString( 'value="&quot;&gt;x"', $html );
		// Solo los ponentes publicados, con buscador.
		$this->assertStringContainsString( 'name="fmc_speaker" data-fmc-ts data-placeholder="Todos"><option value="">Todos</option><option value="' . $speaker . '">Ana</option></select>', $html );
	}

	/**
	 * En el filtro de diseños solo entran los del catálogo, con su código
	 * delante para buscar por él (ADR-0012).
	 */
	public function test_design_choices_are_the_catalogue_with_their_code() {
		$this->as_role( 'fmc_curator' );
		$this->post( PostTypes::DESIGN, 'C borrador', array( D::IN_CATALOGUE => '1' ), array( 'post_status' => 'draft' ) );
		$this->post( PostTypes::DESIGN, 'B histórico' );
		$listed = $this->post(
			PostTypes::DESIGN,
			'A en el catálogo',
			array(
				D::IN_CATALOGUE => '1',
				D::CODE         => 'A7',
			)
		);
		$plain  = $this->post( PostTypes::DESIGN, 'D sin código', array( D::IN_CATALOGUE => '1' ) );

		$this->assertSame(
			array(
				$listed => 'A7 · A en el catálogo',
				$plain  => 'D sin código',
			),
			Lists::post_choices( PostTypes::DESIGN )
		);
	}

	/**
	 * Los cursos escolares van del actual a 2012-2013, el más reciente primero.
	 */
	public function test_school_years_go_from_the_current_one_back_to_2012() {
		$years = Lists::school_years();
		$end   = (int) gmdate( 'Y' ) + ( (int) gmdate( 'n' ) >= 9 ? 1 : 0 );

		$this->assertSame( ( $end - 1 ) . '-' . $end, array_key_first( $years ) );
		$this->assertSame( '2012-2013', array_key_last( $years ) );
		$this->assertCount( $end - 2012, $years );
	}

	/**
	 * El CSV: BOM, punto y coma, todas las columnas de la ficha y las horas sumadas.
	 */
	public function test_the_csv_exports_the_filtered_actions_with_their_totals() {
		$this->as_role( 'fmc_curator' );
		$design = $this->post( PostTypes::DESIGN, 'Diseño', array( D::TYPE => 'course' ) );
		$done   = $this->post(
			PostTypes::ACTION,
			'Hecha',
			array(
				A::SITUATION    => 'done',
				A::DESIGN_ID    => $design,
				A::START        => '2025-10-01',
				A::HOURS_ONSITE => 4,
				A::HOURS_ONLINE => 6,
				A::REPLICAS     => 2,
				A::SUBTITLE     => 'Edición de otoño',
			)
		);
		$this->post( PostTypes::ACTION, 'Aplazada', array( A::SITUATION => 'postponed' ) );
		$_GET['fmc_situation'] = 'done';

		list( $raw, $rows ) = $this->csv( PostTypes::ACTION );

		$this->assertSame( "\xEF\xBB\xBF", substr( $raw, 0, 3 ) );
		$head = $rows[0];
		// El título no está en la ficha de la acción —es el del diseño—, pero la hoja lo lleva.
		$this->assertSame( array( 'ID', 'Estado de la ficha', 'Título', 'Diseño', 'Subtítulo' ), array_slice( $head, 0, 5 ) );
		$this->assertSame( array( 'Tipo de formación', 'Curso escolar', 'Horas totales' ), array_slice( $head, -3 ) );
		$this->assertNotContains( 'Matriculados', $head, 'las cifras de cierre ya no son de aquí (ADR-0010)' );
		$this->assertCount( 2, $rows, 'Solo la acción filtrada' );
		$row = array_combine( $head, $rows[1] );
		$this->assertSame( (string) $done, $row['ID'] );
		$this->assertSame( get_post_status_object( 'publish' )->label, $row['Estado de la ficha'] );
		$this->assertSame( 'Diseño', $row['Diseño'] );
		$this->assertSame( 'Realizada', $row['Situación'] );
		$this->assertSame( 'Curso', $row['Tipo de formación'] );
		$this->assertSame( '2025-2026', $row['Curso escolar'] );
		// (4 + 6) horas por dos ediciones.
		$this->assertSame( '20', $row['Horas totales'] );
		$this->assertSame( 'Hecha', $row['Título'] );
		$this->assertSame( 'Edición de otoño', $row['Subtítulo'] );
	}

	/**
	 * Los ponentes no se exportan: ni hay botón ni sale nada (ADR-0011).
	 */
	public function test_speakers_are_never_exported() {
		$this->as_role( 'fmc_curator' );
		$this->post( PostTypes::SPEAKER, 'Ana', array( S::EMAIL => 'ana@example.org' ) );

		$this->assertSame( array(), $this->csv( PostTypes::SPEAKER )[1] );
		$this->assertStringNotContainsString( 'fmc_csv', Lists::screen( 'speakers', PostTypes::SPEAKER )['actions'] );
		$this->assertStringContainsString( 'fmc_csv', Lists::screen( 'actions', PostTypes::ACTION )['actions'] );
	}

	/**
	 * Cada clase de campo se escribe como texto llano: etiquetas y nombres, no claves ni IDs.
	 */
	public function test_plain_turns_keys_and_ids_into_readable_text() {
		$this->as_role( 'fmc_curator' );
		$adviser = self::factory()->user->create( array( 'display_name' => 'Asesora Uno' ) );
		$speaker = $this->post( PostTypes::SPEAKER, 'Ponente Uno' );
		$action  = $this->post(
			PostTypes::ACTION,
			'Acción',
			array(
				A::TRAINING_PLANS => array( 'plan', 'seminar' ),
				A::ADVISER        => $adviser,
				A::SPEAKERS       => array( $speaker ),
				A::FUNDED_BY      => 'area',
			)
		);
		$this->term( 'fmc_programme', 'Programa uno', array( $action ) );
		$this->term( 'fmc_programme', 'Programa dos', array( $action ) );
		$post   = get_post( $action );
		$fields = Fields::all( PostTypes::ACTION );
		$plain  = static fn( string $key ): string => Lists::plain( $post, $key, $fields[ $key ] );

		$this->assertSame( 'Incluida en su plan de formación, Incluida en un seminario o grupo de trabajo', $plain( A::TRAINING_PLANS ) );
		$this->assertSame( 'Asesora Uno', $plain( A::ADVISER ) );
		$this->assertSame( 'Ponente Uno', $plain( A::SPEAKERS ) );
		$this->assertSame( 'Área', $plain( A::FUNDED_BY ) );
		$this->assertSame( 'Programa dos, Programa uno', $plain( 'tax:fmc_programme' ) );
		$this->assertSame( 'No', $plain( A::VIDEOCONFERENCE ) );
		$this->assertSame( '', $plain( A::SUBTITLE ) );

		delete_post_meta( $action, A::ADVISER );
		$this->assertSame( '', $plain( A::ADVISER ) );
	}


	/**
	 * «Expediente empieza por»: el prefijo, no cualquier trozo.
	 */
	public function test_actions_filter_by_the_start_of_the_file_number() {
		$this->as_role( 'fmc_curator' );
		$this->post( PostTypes::ACTION, 'Con prefijo', array( A::FILE_NUMBER => 'XX4.12/26' ) );
		$this->post( PostTypes::ACTION, 'Con otro', array( A::FILE_NUMBER => 'D.06-XX4.1' ) );

		$_GET['fmc_file'] = 'XX4.';
		$this->assertSame( array( 'Con prefijo' ), $this->rows( PostTypes::ACTION ) );
		$_GET['fmc_file'] = 'XX4.1x';
		$this->assertSame( array(), $this->rows( PostTypes::ACTION ), 'el punto es un punto, no «cualquier carácter»' );
	}

	/**
	 * Buscar encuentra por el título, el subtítulo y el expediente.
	 */
	public function test_the_search_also_looks_at_the_file_number_and_subtitle() {
		$this->as_role( 'fmc_curator' );
		$this->post( PostTypes::ACTION, 'Robótica', array( A::FILE_NUMBER => 'D.06-0042' ) );
		$this->post( PostTypes::ACTION, 'Ajedrez', array( A::SUBTITLE => 'Edición de verano' ) );

		$_GET['fmc_s'] = '0042';
		$this->assertSame( array( 'Robótica' ), $this->rows( PostTypes::ACTION ) );
		$_GET['fmc_s'] = 'verano';
		$this->assertSame( array( 'Ajedrez' ), $this->rows( PostTypes::ACTION ) );
		$_GET['fmc_s'] = 'ajedrez';
		$this->assertSame( array( 'Ajedrez' ), $this->rows( PostTypes::ACTION ) );
	}

	/**
	 * Las acciones con incidencias se marcan, y se filtran por si alguna espera.
	 */
	public function test_actions_with_incidents_are_marked_and_filtered() {
		$this->as_role( 'fmc_curator' );
		$waiting = $this->post( PostTypes::ACTION, 'Espera' );
		$solved  = $this->post( PostTypes::ACTION, 'Resuelta' );
		$this->post( PostTypes::ACTION, 'Sin nada' );
		$this->post( PostTypes::INCIDENT, 'i1', array( I::RESOLUTION => 'pending' ), array( 'post_parent' => $waiting ) );
		$this->post( PostTypes::INCIDENT, 'i2', array( I::RESOLUTION => 'approved' ), array( 'post_parent' => $solved ) );

		$this->assertStringContainsString( 'Incidencia pendiente (1)', Lists::badge( get_post( $waiting ) ) );
		$this->assertStringContainsString( 'Incidencias resueltas', Lists::badge( get_post( $solved ) ) );

		$_GET['fmc_incident'] = 'pending';
		$this->assertSame( array( 'Espera' ), $this->rows( PostTypes::ACTION ) );
		$_GET['fmc_incident'] = 'any';
		$this->assertEqualsCanonicalizing( array( 'Espera', 'Resuelta' ), $this->rows( PostTypes::ACTION ) );
	}

	/**
	 * Ponentes por lo que han impartido: acciones, horas, y en ese orden (ADR-0012).
	 */
	public function test_speakers_are_found_by_what_they_have_given() {
		$this->as_role( 'fmc_curator' );
		$ana  = $this->post( PostTypes::SPEAKER, 'Ana' );
		$luis = $this->post( PostTypes::SPEAKER, 'Luis' );
		wp_set_object_terms( $ana, 'Norte', Taxonomies::ZONE );
		wp_set_object_terms( $luis, 'Este', Taxonomies::ZONE );
		$this->post( PostTypes::SPEAKER, 'Nadie' );
		$this->post(
			PostTypes::ACTION,
			'Robótica educativa',
			array(
				A::SPEAKERS     => array( $ana, $luis ),
				A::HOURS_ONSITE => 10,
				A::FILE_NUMBER  => 'XX4.1',
				A::START        => '2026-10-01',
			)
		);
		$this->post(
			PostTypes::ACTION,
			'Robótica avanzada',
			array(
				A::SPEAKERS     => array( $luis ),
				A::HOURS_ONSITE => 20,
				A::START        => '2025-10-01',
			)
		);
		$this->post(
			PostTypes::ACTION,
			'Robótica cancelada',
			array(
				A::SPEAKERS  => array( $ana ),
				A::SITUATION => 'cancelled',
			)
		);

		$_GET['fmc_given'] = 'ROBOTICA';
		$this->assertSame( array( 'Luis', 'Ana' ), $this->rows( PostTypes::SPEAKER ), 'quien más ha dado, primero; sin acentos ni mayúsculas' );
		$body = Lists::screen( 'speakers', PostTypes::SPEAKER )['body'];
		$this->assertStringContainsString( '<td>2 acción(es)<div class="small text-secondary">30 h</div></td>', $body );

		$_GET['fmc_file'] = 'XX4';
		$this->assertSame( array( 'Ana', 'Luis' ), $this->rows( PostTypes::SPEAKER ) );
		$_GET['fmc_file'] = '';
		$_GET['fmc_from'] = '2026-01-01';
		$_GET['fmc_sort'] = 'zone';
		$this->assertSame( array( 'Luis', 'Ana' ), $this->rows( PostTypes::SPEAKER ), 'por isla: Este antes que Norte' );

		$_GET = array( 'fmc_sort' => 'hours' );
		$this->assertSame( array( 'Luis', 'Ana', 'Nadie' ), $this->rows( PostTypes::SPEAKER ), 'sin filtro, todos' );
	}

	/**
	 * Recomendado para un diseño cuenta también lo recomendado para el que sustituye.
	 */
	public function test_recommended_speakers_follow_the_replaced_designs() {
		$this->as_role( 'fmc_curator' );
		$old  = $this->post( PostTypes::DESIGN, 'Antiguo' );
		$mid  = $this->post( PostTypes::DESIGN, 'Intermedio', array( D::REPLACES => array( $old ) ) );
		$new  = $this->post( PostTypes::DESIGN, 'Nuevo', array( D::REPLACES => array( $mid ) ) );
		$ana  = $this->post( PostTypes::SPEAKER, 'Ana', array( S::RECOMMENDED => array( $old ) ) );
		$luis = $this->post( PostTypes::SPEAKER, 'Luis', array( S::RECOMMENDED => array( $new ) ) );
		$this->post( PostTypes::SPEAKER, 'Otra' );
		update_post_meta( $old, D::REPLACES, array( $new ) ); // Un ciclo no cuelga la cadena.

		$this->assertEqualsCanonicalizing( array( $new, $mid, $old ), Speakers::lineage( $new ) );
		$this->assertEqualsCanonicalizing( array( $ana, $luis ), Speakers::recommended_for( $new ) );
		$_GET['fmc_recommended'] = (string) $new;
		$this->assertEqualsCanonicalizing( array( 'Ana', 'Luis' ), $this->rows( PostTypes::SPEAKER ) );
		$_GET['fmc_given'] = 'nada que coincida';
		$this->assertSame( array(), $this->rows( PostTypes::SPEAKER ), 'con otro filtro, los dos a la vez' );
	}

	/**
	 * La posición en la lista guardada no cuenta como diseño recomendado.
	 *
	 * Serializada, `array( 901, 902 )` es `a:2:{i:0;i:901;i:1;i:902;}`: un
	 * `LIKE 'i:1;'` daba al ponente por recomendado para el diseño 1.
	 */
	public function test_a_list_position_is_not_a_recommended_design() {
		$this->as_role( 'fmc_curator' );
		$this->post( PostTypes::SPEAKER, 'Ana', array( S::RECOMMENDED => array( 901, 902 ) ) );
		$this->assertSame( array(), Speakers::recommended_for( 1 ) );
		$this->assertSame( array(), Speakers::recommended_for( 0 ) );
	}

	/**
	 * La papelera de ponentes, con el motivo, solo para la validación.
	 */
	public function test_the_speaker_trash_shows_who_and_why() {
		$curator = $this->as_role( 'fmc_curator' );
		$gone    = $this->post(
			PostTypes::SPEAKER,
			'Se fue',
			array(
				S::TRASH_REASON => 'Pidió que se borrasen sus datos',
				S::TRASHED_BY   => $curator,
			)
		);
		wp_trash_post( $gone );

		$this->post( PostTypes::SPEAKER, 'Sigue' );
		$body = Lists::screen( 'speakers', PostTypes::SPEAKER )['body'];
		// Sin tarjetas: el total, en una línea, con la puerta de la papelera.
		$this->assertStringNotContainsString( 'fmc-counter', $body );
		$this->assertMatchesRegularExpression( '/<p class="fmc-found">1 ponente\(s\) · <a href="[^"]*fmc_status=trash[^"]*">Ver la papelera<\/a><\/p>/', $body );
		$_GET['fmc_status'] = 'trash';
		$body               = Lists::screen( 'speakers', PostTypes::SPEAKER )['body'];
		$this->assertStringContainsString( '1 en la papelera · ', $body );
		$this->assertStringContainsString( '>Volver a los ponentes</a>', $body );
		$this->assertStringContainsString( 'Pidió que se borrasen sus datos', $body );
		$this->assertStringContainsString( 'Recuperar en el escritorio', $body );

		$this->as_role( 'fmc_adviser' );
		$this->assertStringNotContainsString( 'papelera', Lists::screen( 'speakers', PostTypes::SPEAKER )['body'] );
		$this->assertStringNotContainsString( 'Pidió que se borrasen', Lists::screen( 'speakers', PostTypes::SPEAKER )['body'] );
	}
}

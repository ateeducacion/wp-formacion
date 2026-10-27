<?php
/**
 * Tests for who pays an action and the programme it is charged to.
 *
 * @package Fmc
 */

use Fmc\Meta\ActionMetaKeys as A;
use Fmc\Meta\DesignMetaKeys as D;
use Fmc\Meta\MetaRegistration;
use Fmc\PostType\PostTypes;
use Fmc\PublicFront\Calendar;
use Fmc\PublicFront\Editor;
use Fmc\PublicFront\Fields;
use Fmc\Taxonomy\Taxonomies;

/**
 * Quién paga se elige; el programa, si paga quien cobra a programas, es el
 * activo que cubre el diseño (ADR-0018).
 */
class Test_Programmes extends WP_UnitTestCase {

	/**
	 * Roles, caps and meta in place.
	 */
	public function set_up() {
		parent::set_up();
		fmc_register_roles();
		PostTypes::grant_caps_to_roles();
		MetaRegistration::register_meta();
		Taxonomies::register();
	}

	/**
	 * Undo the faked request and the filters.
	 */
	public function tear_down() {
		$_POST = array();
		remove_all_filters( 'fmc_funders' );
		parent::tear_down();
	}

	/**
	 * A programme, active or not.
	 *
	 * @param string $name   Name.
	 * @param bool   $active Whether it is active.
	 * @return int
	 */
	private function programme( string $name, bool $active ): int {
		$id = self::factory()->term->create(
			array(
				'taxonomy' => Taxonomies::PROGRAMME,
				'name'     => $name,
			)
		);
		if ( $active ) {
			update_term_meta( $id, Taxonomies::ACTIVE, '1' );
		}
		return $id;
	}

	/**
	 * A finalised design in some programmes.
	 *
	 * @param int[] $programmes Programmes.
	 * @return int
	 */
	private function design( array $programmes ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::DESIGN,
				'post_status' => 'publish',
			)
		);
		update_post_meta( $id, D::MODALITY, 'onsite' );
		wp_set_object_terms( $id, $programmes, Taxonomies::PROGRAMME );
		return $id;
	}

	/**
	 * Valid action fields for a design and a funder.
	 *
	 * @param int    $design Design.
	 * @param string $funder Who pays.
	 * @param int[]  $chosen Programmes chosen by hand.
	 * @return array<string, mixed>
	 */
	private function fields( int $design, string $funder, array $chosen = array() ): array {
		return array(
			A::DESIGN_ID        => (string) $design,
			'tax:fmc_scope'     => array( (string) self::factory()->term->create( array( 'taxonomy' => 'fmc_scope' ) ) ),
			A::PLACES           => '20',
			A::START            => '2026-10-01',
			A::FUNDED_BY        => $funder,
			'tax:fmc_programme' => array_map( 'strval', $chosen ),
		);
	}

	/**
	 * Programmes of an action.
	 *
	 * @param int $action Action.
	 * @return int[]
	 */
	private function programmes_of( int $action ): array {
		return array_map( 'intval', wp_get_object_terms( $action, Taxonomies::PROGRAMME, array( 'fields' => 'ids' ) ) );
	}

	/**
	 * Cada despliegue pone sus nombres a quién paga, sin cambiar las claves ni añadir ninguna.
	 */
	public function test_the_deployment_names_who_pays() {
		$this->assertSame( A::FUNDERS, A::funders() );

		add_filter(
			'fmc_funders',
			static fn( array $names ): array => array_merge(
				$names,
				array(
					'service' => 'Servicio de nombre propio',
					'area'    => '  ',
					'nueva'   => 'No entra',
				)
			)
		);

		$names = A::funders();
		$this->assertSame( array_keys( A::FUNDERS ), array_keys( $names ) );
		$this->assertSame( 'Servicio de nombre propio', $names['service'] );
		$this->assertSame( A::FUNDERS['area'], $names['area'], 'vacío no vale' );
		$this->assertSame( 'Servicio de nombre propio', Fields::all( PostTypes::ACTION )[ A::FUNDED_BY ]['choices']['service'] );
		$this->assertStringContainsString( 'Asume: servicio de nombre propio', Calendar::screen()['body'] );
	}

	/**
	 * Con quien cobra a programas, el programa es el activo que cubre el
	 * diseño, aunque se haya elegido otro.
	 */
	public function test_the_service_charges_the_active_programme_of_the_design() {
		$active   = $this->programme( 'Programa vigente', true );
		$finished = $this->programme( 'Programa cerrado', false );
		$design   = $this->design( array( $active, $finished ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'fmc_adviser' ) ) );

		$result = Editor::apply( PostTypes::ACTION, 0, $this->fields( $design, A::PROGRAMME_FUNDER, array( $finished ) ), '', 0 );

		$this->assertSame( 'created', $result['outcome'] );
		$this->assertSame( array( $active ), $this->programmes_of( $result['id'] ) );
	}

	/**
	 * Si el diseño no está en ningún programa activo, no la puede asumir quien
	 * cobra a programas: lo dice, con los nombres del despliegue.
	 */
	public function test_without_an_active_programme_the_centre_pays() {
		$design = $this->design( array( $this->programme( 'Programa cerrado', false ) ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'fmc_adviser' ) ) );
		add_filter( 'fmc_funders', static fn( array $names ): array => array_merge( $names, array( 'centre' => 'El centro propio' ) ) );

		$result = Editor::apply( PostTypes::ACTION, 0, $this->fields( $design, A::PROGRAMME_FUNDER ), '', 0 );

		$this->assertSame( 'invalid', $result['outcome'] );
		$this->assertSame( 'El diseño no está en ningún programa activo, así que no la puede asumir «Servicio de formación»: la asume «El centro propio».', $result['errors'][ A::FUNDED_BY ] );
	}

	/**
	 * Con otro que paga, el programa es el que se elija; y una acción guardada
	 * que no cambia ni de quién paga ni de diseño se guarda aunque su programa
	 * se haya cerrado.
	 */
	public function test_other_funders_and_old_actions_keep_their_programme() {
		$programme = $this->programme( 'Programa', true );
		$other     = $this->programme( 'Otro', false );
		$design    = $this->design( array( $programme ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'fmc_adviser' ) ) );

		$centre = Editor::apply( PostTypes::ACTION, 0, $this->fields( $design, 'centre', array( $other ) ), '', 0 );
		$this->assertSame( array( $other ), $this->programmes_of( $centre['id'] ) );

		$service = Editor::apply( PostTypes::ACTION, 0, $this->fields( $design, A::PROGRAMME_FUNDER ), '', 0 );
		delete_term_meta( $programme, Taxonomies::ACTIVE );
		$this->assertSame( 'saved', Editor::apply( PostTypes::ACTION, $service['id'], $this->fields( $design, A::PROGRAMME_FUNDER, array( $programme ) ), '', 0 )['outcome'] );
		$this->assertSame( array( $programme ), $this->programmes_of( $service['id'] ) );
	}

	/**
	 * La casilla «Activo» del programa la guarda la administración, con su nonce.
	 */
	public function test_the_active_box_is_saved_by_administration_only() {
		$programme = $this->programme( 'Programa', false );
		$send      = function ( bool $checked ) use ( $programme ): void {
			$_POST = array( '_fmc_programme_nonce' => wp_create_nonce( 'fmc_programme_active' ) ) + ( $checked ? array( 'fmc_active' => '1' ) : array() );
			Taxonomies::save_active( $programme );
		};

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'fmc_curator' ) ) );
		$send( true );
		$this->assertSame( array(), Taxonomies::active_programmes(), 'la validación no administra vocabularios' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = array( 'fmc_active' => '1' );
		Taxonomies::save_active( $programme );
		$this->assertSame( array(), Taxonomies::active_programmes(), 'sin nonce, nada' );

		$send( true );
		$this->assertSame( array( $programme ), Taxonomies::active_programmes() );
		$this->assertSame( 'Sí', Taxonomies::active_cell( '', 'fmc_active', $programme ) );
		$this->assertSame( 'otra', Taxonomies::active_cell( 'otra', 'name', $programme ) );
		$this->assertArrayHasKey( 'fmc_active', Taxonomies::active_column( array() ) );

		ob_start();
		Taxonomies::edit_active_field( get_term( $programme ) );
		$this->assertStringContainsString( 'name="fmc_active" value="1" checked=\'checked\'', (string) ob_get_clean() );

		$send( false );
		$this->assertSame( array(), Taxonomies::active_programmes() );
		ob_start();
		Taxonomies::add_active_field();
		$this->assertStringContainsString( 'name="_fmc_programme_nonce"', (string) ob_get_clean() );
	}
}

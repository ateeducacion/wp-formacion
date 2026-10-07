<?php
/**
 * Tests for the post types and who can do what with them.
 *
 * @package Fmc
 */

use Fmc\PostType\PostTypes;

/**
 * El ciclo del diseño y el reparto de capacidades (ADR-0004, ADR-0006).
 */
class Test_Post_Types extends WP_UnitTestCase {

	/**
	 * Roles and caps in place, as a provisioned site has them.
	 */
	public function set_up() {
		parent::set_up();
		fmc_register_roles();
		PostTypes::grant_caps_to_roles();
	}

	/**
	 * A user with a role, logged in.
	 *
	 * @param string $role Role slug.
	 * @return int
	 */
	private function as_role( string $role ): int {
		$id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $id );
		return $id;
	}

	/**
	 * Los cuatro tipos existen; solo el diseño es público y ninguno sale en REST.
	 */
	public function test_the_four_types_exist() {
		foreach ( array_keys( PostTypes::definitions() ) as $type ) {
			$this->assertTrue( post_type_exists( $type ), $type );
			$this->assertFalse( get_post_type_object( $type )->show_in_rest, $type );
		}
		$this->assertTrue( get_post_type_object( PostTypes::DESIGN )->public );
		$this->assertFalse( get_post_type_object( PostTypes::SPEAKER )->public, 'un ponente son datos personales' );
	}

	/**
	 * La asesoría escribe su diseño y lo manda a revisión, pero no lo finaliza.
	 */
	public function test_an_adviser_drafts_but_cannot_finalise_a_design() {
		$adviser = $this->as_role( 'fmc_adviser' );
		$design  = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::DESIGN,
				'post_status' => 'pending',
				'post_author' => $adviser,
			)
		);

		$this->assertTrue( current_user_can( 'edit_post', $design ) );
		$this->assertFalse( current_user_can( 'publish_fmc_designs' ) );
	}

	/**
	 * Finalizado, la asesoría ya no lo toca; la curaduría sí.
	 */
	public function test_a_finalised_design_is_locked_for_its_adviser() {
		$adviser = $this->as_role( 'fmc_adviser' );
		$design  = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::DESIGN,
				'post_status' => 'publish',
				'post_author' => $adviser,
			)
		);

		$this->assertFalse( current_user_can( 'edit_post', $design ) );

		$this->as_role( 'fmc_curator' );
		$this->assertTrue( current_user_can( 'edit_post', $design ) );
	}

	/**
	 * Una asesoría no edita la acción de otra.
	 */
	public function test_an_adviser_cannot_edit_someone_elses_action() {
		$owner  = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$action = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::ACTION,
				'post_status' => 'publish',
				'post_author' => $owner,
			)
		);

		$this->as_role( 'fmc_adviser' );
		$this->assertFalse( current_user_can( 'edit_post', $action ) );

		wp_set_current_user( $owner );
		$this->assertTrue( current_user_can( 'edit_post', $action ) );
	}

	/**
	 * El servicio de formación no edita acciones por la vía general: tiene sus dos capacidades.
	 */
	public function test_the_training_service_only_holds_its_own_caps() {
		$this->as_role( 'fmc_training_service' );

		$this->assertFalse( current_user_can( 'edit_fmc_actions' ) );
		$this->assertTrue( current_user_can( PostTypes::CAP_SERVICE_FIELDS ) );
		$this->assertTrue( current_user_can( PostTypes::CAP_RESOLVE ) );
	}

	/**
	 * Cada tipo lleva sus propias capacidades, y las meta-capacidades se traducen a ellas.
	 */
	public function test_each_type_registers_its_own_capabilities() {
		PostTypes::register();

		foreach ( PostTypes::definitions() as $type => $def ) {
			$object = get_post_type_object( $type );
			$this->assertSame( 'edit_' . $type . 's', $object->cap->edit_posts, $type );
			$this->assertSame( 'publish_' . $type . 's', $object->cap->publish_posts, $type );
			$this->assertSame( 'edit_published_' . $type . 's', $object->cap->edit_published_posts, $type );
			$this->assertTrue( $object->map_meta_cap, $type );
			$this->assertSame( $def['public'], $object->public, $type );
			$this->assertFalse( $object->has_archive, $type );
			$this->assertSame( $def['plural'], $object->labels->name, $type );
			$this->assertSame( $def['singular'], $object->labels->singular_name, $type );
		}
		// Ni los ponentes ni las incidencias se consultan desde fuera.
		$this->assertFalse( get_post_type_object( PostTypes::SPEAKER )->publicly_queryable );
		$this->assertFalse( get_post_type_object( PostTypes::INCIDENT )->publicly_queryable );
		$this->assertArrayNotHasKey( PostTypes::SPEAKER, get_post_types( array( 'show_in_rest' => true ) ) );
	}

	/**
	 * La curaduría finaliza, edita y borra diseños ajenos.
	 */
	public function test_a_curator_finalises_someone_elses_design() {
		$adviser = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$design  = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::DESIGN,
				'post_status' => 'pending',
				'post_author' => $adviser,
			)
		);

		$this->as_role( 'fmc_curator' );
		$this->assertTrue( current_user_can( 'publish_post', $design ) );
		$this->assertTrue( current_user_can( 'edit_post', $design ) );
		$this->assertTrue( current_user_can( 'delete_post', $design ) );
	}

	/**
	 * La asesoría no publica su diseño ni lo borra una vez finalizado.
	 */
	public function test_an_adviser_cannot_publish_nor_delete_a_finalised_design() {
		$adviser = $this->as_role( 'fmc_adviser' );
		$draft   = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::DESIGN,
				'post_status' => 'draft',
				'post_author' => $adviser,
			)
		);
		$final   = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::DESIGN,
				'post_status' => 'publish',
				'post_author' => $adviser,
			)
		);

		$this->assertFalse( current_user_can( 'publish_post', $draft ) );
		$this->assertTrue( current_user_can( 'delete_post', $draft ) );
		$this->assertFalse( current_user_can( 'delete_post', $final ) );
		$this->assertFalse( current_user_can( 'edit_published_fmc_designs' ) );
	}

	/**
	 * Su acción la publica y la sigue editando, pero publicada ya no la borra.
	 */
	public function test_an_adviser_keeps_editing_but_cannot_delete_a_published_action() {
		$adviser = $this->as_role( 'fmc_adviser' );
		$action  = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::ACTION,
				'post_status' => 'publish',
				'post_author' => $adviser,
			)
		);

		$this->assertTrue( current_user_can( 'edit_post', $action ) );
		$this->assertTrue( current_user_can( 'publish_fmc_actions' ) );
		$this->assertFalse( current_user_can( 'delete_post', $action ) );
		$this->assertFalse( current_user_can( 'read_private_fmc_actions' ) );
	}

	/**
	 * El servicio lee las acciones e incidencias privadas, y nada más: ni diseños privados ni ponentes.
	 */
	public function test_the_training_service_reads_private_actions_and_incidents_only() {
		$owner    = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$private  = static fn( string $type ) => self::factory()->post->create(
			array(
				'post_type'   => $type,
				'post_status' => 'private',
				'post_author' => $owner,
			)
		);
		$action   = $private( PostTypes::ACTION );
		$incident = $private( PostTypes::INCIDENT );
		$design   = $private( PostTypes::DESIGN );
		$speaker  = $private( PostTypes::SPEAKER );

		$this->as_role( 'fmc_training_service' );
		$this->assertTrue( current_user_can( 'read_post', $action ) );
		$this->assertTrue( current_user_can( 'read_post', $incident ) );
		$this->assertFalse( current_user_can( 'edit_post', $action ) );
		$this->assertFalse( current_user_can( 'edit_post', $incident ) );
		$this->assertFalse( current_user_can( 'read_post', $design ) );
		$this->assertFalse( current_user_can( 'read_post', $speaker ) );
		$this->assertFalse( current_user_can( 'edit_fmc_speakers' ) );
	}

	/**
	 * La asesoría abre incidencias pero no las resuelve ni toca las ajenas.
	 */
	public function test_an_adviser_opens_incidents_but_does_not_resolve_them() {
		$other    = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$incident = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::INCIDENT,
				'post_status' => 'draft',
				'post_author' => $other,
			)
		);

		$this->as_role( 'fmc_adviser' );
		$this->assertTrue( current_user_can( 'publish_fmc_incidents' ) );
		$this->assertFalse( current_user_can( 'edit_post', $incident ) );
		$this->assertFalse( current_user_can( PostTypes::CAP_RESOLVE ) );
		$this->assertFalse( current_user_can( PostTypes::CAP_SERVICE_FIELDS ) );
	}

	/**
	 * Repartir dos veces deja lo mismo, y no quita lo que el rol ya tenía.
	 */
	public function test_granting_caps_is_idempotent_and_additive() {
		$role = get_role( 'fmc_adviser' );
		$role->add_cap( 'fmc_cap_added_by_hand' );
		$role->remove_cap( 'edit_fmc_actions' );

		PostTypes::grant_caps_to_roles();
		$once = get_role( 'fmc_adviser' )->capabilities;
		PostTypes::grant_caps_to_roles();

		$this->assertSame( $once, get_role( 'fmc_adviser' )->capabilities );
		$this->assertTrue( $once['fmc_cap_added_by_hand'], 'lo puesto a mano se queda' );
		$this->assertTrue( $once['edit_fmc_actions'], 'lo que faltaba vuelve' );

		// Exactamente lo del mapa, con el sufijo del tipo, y nada que no le toque.
		foreach ( PostTypes::role_caps()['fmc_adviser'] as $type => $primitives ) {
			foreach ( $primitives as $primitive ) {
				$this->assertTrue( $once[ $primitive . '_' . $type . 's' ] ?? false, $primitive . '_' . $type . 's' );
			}
		}
		$this->assertArrayNotHasKey( 'publish_fmc_designs', $once );
		$this->assertArrayNotHasKey( PostTypes::CAP_RESOLVE, $once );

		$role->remove_cap( 'fmc_cap_added_by_hand' );
	}

	/**
	 * Un rol que no existe se salta: el reparto no lo crea.
	 */
	public function test_granting_caps_skips_missing_roles() {
		remove_role( 'fmc_training_service' );

		PostTypes::grant_caps_to_roles();
		$this->assertNull( get_role( 'fmc_training_service' ) );
		$this->assertTrue( get_role( 'fmc_curator' )->has_cap( PostTypes::CAP_RESOLVE ) );

		fmc_register_roles();
		PostTypes::grant_caps_to_roles();
		$this->assertTrue( get_role( 'fmc_training_service' )->has_cap( 'read_private_fmc_actions' ) );
	}
}

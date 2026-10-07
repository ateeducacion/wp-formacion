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
}

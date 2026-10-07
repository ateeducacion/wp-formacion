<?php
/**
 * Tests for the product roles snippet.
 *
 * @package Fmc
 */

/**
 * El snippet suelto de roles.
 *
 * Es aditivo a propósito: crea lo que falta y no quita nada, para que lo que se
 * conceda a mano en el editor de roles siga ahí en la siguiente carga.
 */
class Test_Roles_And_Profiles extends WP_UnitTestCase {

	/**
	 * Los tres roles del contrato, en este orden.
	 */
	public function test_role_slugs_are_the_three_of_the_contract() {
		$this->assertSame( array( 'fmc_curator', 'fmc_adviser', 'fmc_training_service' ), fmc_role_slugs() );
	}

	/**
	 * El registro crea los roles, y administrar el aplicativo es solo de administración.
	 */
	public function test_register_roles_creates_the_roles() {
		fmc_register_roles();

		foreach ( fmc_role_slugs() as $slug ) {
			$role = get_role( $slug );
			$this->assertNotNull( $role, $slug );
			$this->assertTrue( $role->has_cap( 'read' ), $slug );
			$this->assertFalse( $role->has_cap( 'fmc_manage_app' ), $slug );
		}
		$this->assertTrue( get_role( 'administrator' )->has_cap( 'fmc_manage_app' ) );
		$this->assertSame( array(), fmc_roles_status() );
	}

	/**
	 * Es idempotente y no quita lo que se concedió a mano.
	 */
	public function test_register_roles_is_additive() {
		fmc_register_roles();
		get_role( 'fmc_adviser' )->add_cap( 'moderate_comments' );

		fmc_register_roles();

		$this->assertTrue( get_role( 'fmc_adviser' )->has_cap( 'moderate_comments' ) );
	}

	/**
	 * Una capacidad prohibida concedida a mano se avisa, no se quita.
	 */
	public function test_a_forbidden_cap_granted_by_hand_is_reported() {
		fmc_register_roles();
		get_role( 'fmc_curator' )->add_cap( 'unfiltered_html' );

		$this->assertSame( array( 'fmc_curator' => array( 'unfiltered_html' ) ), fmc_roles_status() );

		get_role( 'fmc_curator' )->remove_cap( 'unfiltered_html' );
	}
}

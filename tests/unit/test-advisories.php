<?php
/**
 * Tests for the advisories directory.
 *
 * @package Fmc
 */

use Fmc\Meta\ActionMetaKeys as A;
use Fmc\Meta\AdvisoryMetaKeys as K;
use Fmc\Meta\MetaRegistration;
use Fmc\PostType\PostTypes;
use Fmc\PublicFront\Editor;
use Fmc\PublicFront\Lists;
use Fmc\PublicFront\Screen;

/**
 * La asesoría responsable de una acción sale de un directorio, no de las
 * cuentas: se cuenta por asesoría aunque cambie quien la ocupa (ADR-0019).
 */
class Test_Advisories extends WP_UnitTestCase {

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
	 * Undo the faked request.
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
	 * An advisory of the directory.
	 *
	 * @param string $name Name.
	 * @param string $kind Kind.
	 * @param int    $user Account that holds it.
	 * @return int
	 */
	private function advisory( string $name, string $kind, int $user = 0 ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::ADVISORY,
				'post_status' => 'publish',
				'post_title'  => $name,
			)
		);
		update_post_meta( $id, K::KIND, $kind );
		if ( $user ) {
			update_post_meta( $id, K::USER, $user );
		}
		return $id;
	}

	/**
	 * Valid action fields with an advisory.
	 *
	 * @param string $advisory Advisory ID.
	 * @return array<string, mixed>
	 */
	private function action_fields( string $advisory ): array {
		$design = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::DESIGN,
				'post_status' => 'publish',
			)
		);
		return array(
			A::DESIGN_ID    => (string) $design,
			'tax:fmc_scope' => array( (string) self::factory()->term->create( array( 'taxonomy' => 'fmc_scope' ) ) ),
			A::PLACES       => '20',
			A::START        => '2026-10-01',
			A::ADVISORY     => $advisory,
		);
	}

	/**
	 * Es un tipo propio, sin página pública ni REST; lo lleva la validación.
	 */
	public function test_the_directory_is_a_private_type_kept_by_validation() {
		PostTypes::register();
		$object = get_post_type_object( PostTypes::ADVISORY );

		$this->assertFalse( $object->public );
		$this->assertFalse( $object->show_in_rest );
		$this->assertSame( 'Asesorías', $object->labels->name );

		$this->as_role( 'fmc_curator' );
		$this->assertTrue( current_user_can( 'edit_fmc_advisorys' ) );
		$this->assertTrue( current_user_can( 'publish_fmc_advisorys' ) );
		$this->as_role( 'fmc_adviser' );
		$this->assertFalse( current_user_can( 'edit_fmc_advisorys' ) );
		$this->as_role( 'fmc_training_service' );
		$this->assertFalse( current_user_can( 'edit_fmc_advisorys' ) );
	}

	/**
	 * La validación da de alta una asesoría: nombre y tipo obligatorios, el
	 * nombre no se repite y el tipo es de la lista; la asesoría no lo hace.
	 */
	public function test_validation_adds_an_advisory() {
		$adviser = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$this->as_role( 'fmc_curator' );

		$missing = Editor::apply( PostTypes::ADVISORY, 0, array( 'post_title' => '' ), '', 0 );
		$this->assertSame( array( 'post_title', K::KIND ), array_keys( $missing['errors'] ) );

		$fields  = array(
			'post_title' => 'Asesoría TIC de Norte',
			K::KIND      => 'ict',
			K::USER      => (string) $adviser,
		);
		$created = Editor::apply( PostTypes::ADVISORY, 0, $fields, '', 0 );
		$this->assertSame( 'created', $created['outcome'] );
		$this->assertSame( 'publish', get_post_status( $created['id'] ) );
		$this->assertSame( 'ict', get_post_meta( $created['id'], K::KIND, true ) );
		$this->assertSame( $adviser, (int) get_post_meta( $created['id'], K::USER, true ) );
		$this->assertSame( 'invalid', Editor::apply( PostTypes::ADVISORY, 0, $fields, '', 0 )['outcome'], 'el nombre no se repite' );
		$this->assertSame( '', MetaRegistration::sanitize( PostTypes::ADVISORY, K::KIND, 'Asesoría TIC' ), 'el tipo es de la lista' );

		wp_set_current_user( $adviser );
		$this->assertSame( 'denied', Editor::apply( PostTypes::ADVISORY, 0, array_merge( $fields, array( 'post_title' => 'Otra' ) ), '', 0 )['outcome'] );
	}

	/**
	 * La acción guarda una asesoría del directorio; otra cosa, no.
	 */
	public function test_an_action_takes_an_advisory_of_the_directory() {
		$advisory = $this->advisory( 'Asesoría STEAM', 'steam' );
		$this->as_role( 'fmc_adviser' );

		$saved = Editor::apply( PostTypes::ACTION, 0, $this->action_fields( (string) $advisory ), '', 0 );
		$this->assertSame( 'created', $saved['outcome'] );
		$this->assertSame( $advisory, (int) get_post_meta( $saved['id'], A::ADVISORY, true ) );

		$wrong = Editor::apply( PostTypes::ACTION, 0, $this->action_fields( (string) self::factory()->post->create() ), '', 0 );
		$this->assertSame( 'invalid', $wrong['outcome'] );
		$this->assertSame( 'La asesoría elegida no está en el directorio.', $wrong['errors'][ A::ADVISORY ] );
	}

	/**
	 * El directorio se lista con su tipo y su cuenta, y se filtra por tipo; las
	 * acciones, por asesoría.
	 */
	public function test_the_directory_and_the_actions_filter_by_advisory() {
		$curator = $this->as_role( 'fmc_curator' );
		$ict     = $this->advisory( 'Asesoría TIC', 'ict', $curator );
		$steam   = $this->advisory( 'Asesoría STEAM', 'steam' );
		$action  = static function ( string $title, int $advisory ): int {
			$id = self::factory()->post->create(
				array(
					'post_type'   => PostTypes::ACTION,
					'post_status' => 'publish',
					'post_title'  => $title,
				)
			);
			update_post_meta( $id, A::ADVISORY, $advisory );
			return $id;
		};
		$action( 'Con la TIC', $ict );
		$action( 'Con la STEAM', $steam );

		$screen = Lists::screen( 'advisories', PostTypes::ADVISORY );
		$this->assertSame( 'Asesorías', $screen['title'] );
		$this->assertStringContainsString( '+ Añadir asesoría', $screen['actions'] );
		$this->assertStringContainsString( '<p class="fmc-found">2 asesoría(s)</p>', $screen['body'] );
		$user = get_userdata( $curator );
		$this->assertStringContainsString( '<td>TIC</td><td>' . esc_html( $user->display_name ) . '</td>', $screen['body'] );

		$_GET['fmc_kind'] = 'steam';
		$this->assertSame( array( $steam ), wp_list_pluck( ( new WP_Query( Lists::query_args( PostTypes::ADVISORY ) ) )->posts, 'ID' ) );

		$_GET   = array( 'fmc_advisory' => (string) $ict );
		$titles = wp_list_pluck( ( new WP_Query( Lists::query_args( PostTypes::ACTION ) ) )->posts, 'post_title' );
		$this->assertSame( array( 'Con la TIC' ), $titles );
		$this->assertStringContainsString( 'name="fmc_advisory"', Lists::filters_html( 'actions', PostTypes::ACTION ) );
		$this->assertSame( 'advisories', Screen::tab_of( PostTypes::ADVISORY ) );
	}
}

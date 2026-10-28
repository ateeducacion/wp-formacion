<?php
/**
 * Tests for the adviser's centre of reference and what follows from it.
 *
 * @package Fmc
 */

use Fmc\Centre\CentreCatalogue;
use Fmc\Meta\ActionMetaKeys as A;
use Fmc\Meta\MetaRegistration;
use Fmc\PostType\PostTypes;
use Fmc\PublicFront\Editor;
use Fmc\PublicFront\Lists;
use Fmc\Taxonomy\Taxonomies;
use Fmc\User\Advisers;

/**
 * La asesoría pertenece a un centro, solo ve sus incidencias, estrena la
 * acción con su ámbito y su nombre puestos, y mantiene cualquier ponente
 * (ADR-0022).
 */
class Test_Adviser_Centre extends WP_UnitTestCase {

	/**
	 * A centre of the catalogue, and its scope term.
	 */
	public const CODE = '38000001';

	/**
	 * Its name, the same in the catalogue and in the scope term.
	 */
	public const NAME = 'Centro de formación Norte';

	/**
	 * Roles, caps, meta and a catalogue with one centre.
	 */
	public function set_up() {
		parent::set_up();
		fmc_register_roles();
		PostTypes::grant_caps_to_roles();
		MetaRegistration::register_meta();
		update_option(
			CentreCatalogue::OPTION_CATALOGUE,
			array(
				self::CODE => array(
					'code'         => self::CODE,
					'name'         => self::NAME,
					'island'       => 'Isla Norte',
					'municipality' => 'Villa Norte',
					'type'         => 'Centro de formación',
					'active'       => true,
				),
			)
		);
	}

	/**
	 * Undo the faked request.
	 */
	public function tear_down() {
		$_GET  = array();
		$_POST = array();
		delete_option( CentreCatalogue::OPTION_CATALOGUE );
		parent::tear_down();
	}

	/**
	 * An adviser of the centre, logged in.
	 *
	 * @param string $centre Centre code, '' for none.
	 * @return int
	 */
	private function adviser( string $centre = self::CODE ): int {
		$id = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		if ( '' !== $centre ) {
			update_user_option( $id, Advisers::CENTRE, $centre );
		}
		wp_set_current_user( $id );
		return $id;
	}

	/**
	 * An action with its adviser.
	 *
	 * @param int $adviser Adviser.
	 * @return int
	 */
	private function action( int $adviser ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::ACTION,
				'post_status' => 'publish',
				'post_author' => $adviser,
			)
		);
		update_post_meta( $id, A::ADVISER, $adviser );
		return $id;
	}

	/**
	 * An incident on an action.
	 *
	 * @param int $action Action.
	 * @param int $author Who opened it.
	 * @return int
	 */
	private function incident( int $action, int $author ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => PostTypes::INCIDENT,
				'post_status' => 'publish',
				'post_author' => $author,
				'post_parent' => $action,
			)
		);
	}

	/**
	 * Only a centre of the catalogue is kept.
	 */
	public function test_centre_comes_from_the_catalogue() {
		$id = $this->adviser();
		$this->assertSame( self::CODE, Advisers::centre( $id ) );

		update_user_option( $id, Advisers::CENTRE, '99999999' );
		$this->assertSame( '', Advisers::centre( $id ), 'Un código que no está en el catálogo no vale.' );
	}

	/**
	 * The scope is the term named like the centre; without it, none.
	 */
	public function test_scope_is_the_term_named_like_the_centre() {
		$id = $this->adviser();
		$this->assertSame( 0, Advisers::scope( $id ), 'Sin término, no hay ámbito que proponer.' );

		$term = self::factory()->term->create(
			array(
				'taxonomy' => Taxonomies::SCOPE,
				'name'     => self::NAME,
			)
		);
		$this->assertSame( (int) $term, Advisers::scope( $id ) );
	}

	/**
	 * A new action opens with the adviser and their scope already chosen.
	 */
	public function test_new_action_proposes_the_adviser_and_their_scope() {
		$id   = $this->adviser();
		$term = self::factory()->term->create(
			array(
				'taxonomy' => Taxonomies::SCOPE,
				'name'     => self::NAME,
			)
		);

		$screen = Editor::render( PostTypes::ACTION, null, array(), array() );

		$this->assertStringContainsString( 'value="' . $id . '" selected', $screen['body'], 'La asesoría que la crea sale elegida.' );
		$this->assertStringContainsString( 'value="' . $term . '" selected', $screen['body'], 'Y el ámbito de su centro.' );
	}

	/**
	 * What comes proposed is a proposal: the field is writable.
	 */
	public function test_the_proposal_can_be_changed() {
		$this->adviser();
		$screen = Editor::render( PostTypes::ACTION, null, array(), array() );
		$this->assertStringNotContainsString( 'name="fmc[' . A::ADVISER . ']" disabled', $screen['body'] );
	}

	/**
	 * Without a centre, nothing is proposed as scope.
	 */
	public function test_without_a_centre_there_is_no_scope() {
		$this->adviser( '' );
		self::factory()->term->create(
			array(
				'taxonomy' => Taxonomies::SCOPE,
				'name'     => self::NAME,
			)
		);
		$screen = Editor::render( PostTypes::ACTION, null, array(), array() );

		preg_match( '#<select[^>]*id="f-tax-fmc_scope".*?</select>#s', $screen['body'], $m );
		$this->assertNotEmpty( $m, 'La ficha trae su campo de ámbito.' );
		$this->assertStringNotContainsString( 'selected', $m[0], 'Sin centro, el ámbito se queda por elegir.' );
	}

	/**
	 * An adviser reads the incidents of their actions, and no others.
	 */
	public function test_an_adviser_only_reads_their_own_incidents() {
		$other  = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$theirs = $this->incident( $this->action( $other ), $other );

		$mine = $this->adviser();
		$own  = $this->incident( $this->action( $mine ), $mine );

		$this->assertTrue( current_user_can( 'read_post', $own ), 'La incidencia de su acción, sí.' );
		$this->assertFalse( current_user_can( 'read_post', $theirs ), 'La de otra asesoría, no.' );
		$this->assertFalse( Editor::can_view( get_post( $theirs ) ), 'Ni por el enlace directo.' );
	}

	/**
	 * The list shows the same ones the capability allows.
	 */
	public function test_the_list_only_brings_their_own_incidents() {
		$other  = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$theirs = $this->incident( $this->action( $other ), $other );

		$mine = $this->adviser();
		$own  = $this->incident( $this->action( $mine ), $mine );

		$query = new WP_Query( Lists::query_args( PostTypes::INCIDENT ) );
		$ids   = wp_list_pluck( $query->posts, 'ID' );

		$this->assertContains( $own, $ids );
		$this->assertNotContains( $theirs, $ids );
	}

	/**
	 * Who resolves them reads every incident.
	 */
	public function test_the_service_reads_every_incident() {
		$adviser = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$id      = $this->incident( $this->action( $adviser ), $adviser );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'fmc_training_service' ) ) );
		$this->assertTrue( current_user_can( 'read_post', $id ) );
		$this->assertTrue( Editor::can_view( get_post( $id ) ) );

		$query = new WP_Query( Lists::query_args( PostTypes::INCIDENT ) );
		$this->assertContains( $id, wp_list_pluck( $query->posts, 'ID' ) );
	}

	/**
	 * Speakers are a shared directory: any adviser keeps any file up to date.
	 */
	public function test_an_adviser_edits_any_speaker() {
		$other   = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$speaker = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::SPEAKER,
				'post_status' => 'publish',
				'post_author' => $other,
			)
		);

		$this->adviser();
		$this->assertTrue( current_user_can( 'edit_post', $speaker ) );
		$this->assertTrue( Editor::can_view( get_post( $speaker ) ) );
	}
}

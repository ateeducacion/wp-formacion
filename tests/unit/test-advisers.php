<?php
/**
 * Tests for the adviser accounts and their kind.
 *
 * @package Fmc
 */

use Fmc\Meta\ActionMetaKeys as A;
use Fmc\Meta\MetaRegistration;
use Fmc\PostType\PostTypes;
use Fmc\PublicFront\Editor;
use Fmc\PublicFront\Lists;
use Fmc\User\Advisers;

/**
 * La asesoría responsable es una cuenta del sitio, y su tipo va en su perfil,
 * por sitio (ADR-0019).
 */
class Test_Advisers extends WP_UnitTestCase {

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
		$_GET  = array();
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * An account with a role and, maybe, a kind.
	 *
	 * @param string $role Role.
	 * @param string $name Display name.
	 * @param string $kind Kind.
	 * @return int
	 */
	private function account( string $role, string $name, string $kind = '' ): int {
		$id = self::factory()->user->create(
			array(
				'role'         => $role,
				'display_name' => $name,
			)
		);
		if ( '' !== $kind ) {
			update_user_option( $id, Advisers::KIND, $kind );
		}
		return $id;
	}

	/**
	 * Send the profile form.
	 *
	 * @param int    $user User being edited.
	 * @param string $kind Kind chosen.
	 * @return void
	 */
	private function send( int $user, string $kind ): void {
		$_POST = array(
			'_fmc_adviser_nonce' => wp_create_nonce( 'fmc_adviser_kind' ),
			'fmc_adviser_kind'   => $kind,
		);
		Advisers::save_profile( $user );
	}

	/**
	 * El tipo lo pone la administración en el perfil, con su nonce, y solo de la lista.
	 */
	public function test_administration_sets_the_kind_in_the_profile() {
		$adviser = $this->account( 'fmc_adviser', 'Ana' );

		wp_set_current_user( $this->account( 'fmc_curator', 'Validación' ) );
		$this->send( $adviser, 'steam' );
		$this->assertSame( '', Advisers::kind( $adviser ), 'la validación no gestiona cuentas' );
		ob_start();
		Advisers::profile_field( get_userdata( $adviser ) );
		$this->assertSame( '', ob_get_clean() );

		wp_set_current_user( $this->account( 'administrator', 'Admin' ) );
		$_POST = array( 'fmc_adviser_kind' => 'steam' );
		Advisers::save_profile( $adviser );
		$this->assertSame( '', Advisers::kind( $adviser ), 'sin nonce, nada' );

		$this->send( $adviser, 'steam' );
		$this->assertSame( 'steam', Advisers::kind( $adviser ) );
		$this->assertSame( 'steam', get_user_option( Advisers::KIND, $adviser ), 'por sitio' );
		ob_start();
		Advisers::profile_field( get_userdata( $adviser ) );
		$this->assertStringContainsString( '<option value="steam" selected=\'selected\'>STEAM</option>', (string) ob_get_clean() );
		$this->assertSame( 'STEAM', Advisers::kind_cell( '', Advisers::KIND, $adviser ) );
		$this->assertSame( 'otra', Advisers::kind_cell( 'otra', 'email', $adviser ) );
		$this->assertArrayHasKey( Advisers::KIND, Advisers::kind_column( array() ) );

		$this->send( $adviser, 'inventado' );
		$this->assertSame( '', Advisers::kind( $adviser ), 'lo que no es de la lista se quita' );
		$this->assertSame( '—', Advisers::kind_cell( '', Advisers::KIND, $adviser ) );
	}

	/**
	 * Al elegir la asesoría responsable, cada cuenta lleva su tipo; el
	 * servicio de formación no es asesoría.
	 */
	public function test_the_choices_carry_the_kind() {
		$ana  = $this->account( 'fmc_adviser', 'Ana', 'ict' );
		$luis = $this->account( 'fmc_curator', 'Luis' );
		$this->account( 'fmc_training_service', 'Servicio' );

		$this->assertSame(
			array(
				$ana  => 'Ana · TIC',
				$luis => 'Luis',
			),
			Advisers::choices()
		);
		$this->assertSame( array( $ana ), Advisers::of_kind( 'ict' ) );
	}

	/**
	 * La acción guarda una cuenta de asesoría del sitio; otra, no.
	 */
	public function test_an_action_takes_an_adviser_account() {
		$ana     = $this->account( 'fmc_adviser', 'Ana', 'ict' );
		$service = $this->account( 'fmc_training_service', 'Servicio' );
		wp_set_current_user( $ana );
		$design = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::DESIGN,
				'post_status' => 'publish',
			)
		);
		$fields = static fn( int $adviser ): array => array(
			A::DESIGN_ID    => (string) $design,
			'tax:fmc_scope' => array( (string) self::factory()->term->create( array( 'taxonomy' => 'fmc_scope' ) ) ),
			A::PLACES       => '20',
			A::START        => '2026-10-01',
			A::ADVISER      => (string) $adviser,
		);

		$saved = Editor::apply( PostTypes::ACTION, 0, $fields( $ana ), '', 0 );
		$this->assertSame( 'created', $saved['outcome'] );
		$this->assertSame( $ana, (int) get_post_meta( $saved['id'], A::ADVISER, true ) );

		$wrong = Editor::apply( PostTypes::ACTION, 0, $fields( $service ), '', 0 );
		$this->assertSame( 'La asesoría elegida no es una cuenta de asesoría de este sitio.', $wrong['errors'][ A::ADVISER ] );
	}

	/**
	 * Las acciones se filtran por asesoría y por tipo de asesoría.
	 */
	public function test_actions_filter_by_adviser_and_kind() {
		wp_set_current_user( $this->account( 'fmc_curator', 'Validación' ) );
		$ana    = $this->account( 'fmc_adviser', 'Ana', 'ict' );
		$luis   = $this->account( 'fmc_adviser', 'Luis', 'steam' );
		$action = static function ( string $title, int $adviser ): void {
			$id = self::factory()->post->create(
				array(
					'post_type'   => PostTypes::ACTION,
					'post_status' => 'publish',
					'post_title'  => $title,
				)
			);
			update_post_meta( $id, A::ADVISER, $adviser );
		};
		$action( 'De Ana', $ana );
		$action( 'De Luis', $luis );
		$titles = static fn(): array => wp_list_pluck( ( new WP_Query( Lists::query_args( PostTypes::ACTION ) ) )->posts, 'post_title' );

		$_GET['fmc_adviser'] = (string) $luis;
		$this->assertSame( array( 'De Luis' ), $titles() );

		$_GET = array( 'fmc_adviser_kind' => 'ict' );
		$this->assertSame( array( 'De Ana' ), $titles() );

		$_GET = array( 'fmc_adviser_kind' => 'other' );
		$this->assertSame( array(), $titles(), 'un tipo sin nadie no enseña nada' );

		$html = Lists::filters_html( 'actions', PostTypes::ACTION );
		$this->assertStringContainsString( 'name="fmc_adviser"', $html );
		$this->assertStringContainsString( '>Ana · TIC</option>', $html );
	}
}

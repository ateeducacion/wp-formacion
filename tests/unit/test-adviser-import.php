<?php
/**
 * Tests for the bulk sign-up of adviser accounts.
 *
 * @package Fmc
 */

use Fmc\Centre\CentreCatalogue;
use Fmc\User\AdviserImport;
use Fmc\User\AdviserRoster;
use Fmc\User\Advisers;

/**
 * La lista del curso, pegada tal cual sale de la hoja de cálculo.
 */
class Test_Adviser_Import extends WP_UnitTestCase {

	/**
	 * A centre of the catalogue.
	 */
	public const CODE = '38000001';

	/**
	 * Roles, caps and a catalogue with one centre.
	 */
	public function set_up() {
		parent::set_up();
		fmc_register_roles();
		update_option(
			CentreCatalogue::OPTION_CATALOGUE,
			array(
				self::CODE => array(
					'code'   => self::CODE,
					'name'   => 'Centro de formación Norte',
					'island' => 'Isla Norte',
					'cep'    => self::CODE,
					'active' => true,
				),
			)
		);
	}

	/**
	 * Leave the catalogue as it was.
	 */
	public function tear_down() {
		$_POST = array();
		delete_option( CentreCatalogue::OPTION_CATALOGUE );
		parent::tear_down();
	}

	/**
	 * Cada columna se reconoce por lo que es, no por dónde está.
	 */
	public function test_a_line_is_read_by_what_each_column_is() {
		$this->assertSame(
			array(
				'email'   => 'ana.perez@ejemplo.org',
				'nif'     => '12345678Z',
				'centre'  => self::CODE,
				'name'    => 'Ana',
				'surname' => 'Pérez Gómez',
			),
			AdviserRoster::parse_line( 'Ana;Pérez Gómez;ana.perez@ejemplo.org;12345678Z;' . self::CODE )
		);

		// Tabuladores, comas y el orden cambiado valen igual.
		$this->assertSame( '12345678Z', AdviserRoster::parse_line( "12345678Z\tana.perez@ejemplo.org" )['nif'] );
		$this->assertSame( 'ana.perez@ejemplo.org', AdviserRoster::parse_line( 'ana.perez@ejemplo.org' )['email'] );
		$this->assertSame( '', AdviserRoster::parse_line( 'ana.perez@ejemplo.org' )['nif'] );

		// Ocho cifras son el código del centro aunque el catálogo no lo tenga:
		// el apellido no es sitio para un código suelto.
		$this->assertSame( '99999999', AdviserRoster::parse_line( 'ana.perez@ejemplo.org;99999999' )['centre'] );
		$this->assertSame( '', AdviserRoster::parse_line( 'ana.perez@ejemplo.org;99999999' )['surname'] );

		$this->assertNull( AdviserRoster::parse_line( '   ' ) );
		$this->assertNull( AdviserRoster::parse_line( 'Ana;Pérez Gómez' ), 'Sin correo ni DNI no hay a quién dar de alta.' );
	}

	/**
	 * Quien no tiene cuenta la estrena con el DNI de usuario y sus datos.
	 */
	public function test_an_account_is_created_with_the_dni_as_the_user_name() {
		$row = AdviserRoster::parse_line( 'Ana;Pérez Gómez;ana.perez@ejemplo.org;12345678Z;' . self::CODE );

		list( $id, $what ) = AdviserRoster::import_row( $row, 'ict' );
		$user              = get_user_by( 'id', $id );

		$this->assertStringContainsString( 'cuenta creada', $what );
		$this->assertSame( '12345678Z', $user->user_login );
		$this->assertSame( 'ana.perez@ejemplo.org', $user->user_email );
		$this->assertSame( 'Ana Pérez Gómez', $user->display_name );
		$this->assertContains( AdviserRoster::ROLE, (array) $user->roles );
		$this->assertSame( 'ict', Advisers::kind( $id ) );
		$this->assertSame( self::CODE, Advisers::centre( $id ) );

		// Volver a pegar la misma línea no duplica a nadie ni cambia nada.
		list( $again, $what ) = AdviserRoster::import_row( $row, 'ict' );
		$this->assertSame( $id, $again );
		$this->assertSame( 'ya tenía cuenta', $what );
	}

	/**
	 * Un código de centro que el catálogo no conoce se dice, no se guarda.
	 */
	public function test_a_centre_the_catalogue_does_not_know_is_reported() {
		list( $id, $what ) = AdviserRoster::import_row( AdviserRoster::parse_line( 'Ana;Pérez;ana.perez@ejemplo.org;12345678Z;99999999' ), 'ict' );

		$this->assertStringContainsString( 'no está en el catálogo', $what );
		$this->assertSame( '', Advisers::centre( $id ) );
		$this->assertSame( 'Pérez', get_user_by( 'id', $id )->last_name );
	}

	/**
	 * Sin DNI no se crea la cuenta: es el usuario con el que se entra.
	 */
	public function test_without_a_dni_no_account_is_created() {
		list( $id, $what ) = AdviserRoster::import_row( AdviserRoster::parse_line( 'Ana;Pérez;ana.perez@ejemplo.org' ), 'ict' );

		$this->assertSame( 0, $id );
		$this->assertStringContainsString( 'no se crea', $what );
		$this->assertFalse( get_user_by( 'email', 'ana.perez@ejemplo.org' ) );
	}

	/**
	 * A quien ya tiene cuenta se le pone el perfil, el tipo y su centro.
	 */
	public function test_an_existing_account_gets_the_role_the_kind_and_the_centre() {
		$id = self::factory()->user->create(
			array(
				'user_login' => '87654321X',
				'user_email' => 'luis@ejemplo.org',
				'role'       => 'subscriber',
			)
		);

		list( $same, $what ) = AdviserRoster::import_row( AdviserRoster::parse_line( 'luis@ejemplo.org;87654321X;' . self::CODE ), 'steam' );

		$this->assertSame( $id, $same );
		$this->assertStringContainsString( 'perfil de asesoría', $what );
		$this->assertContains( AdviserRoster::ROLE, (array) get_user_by( 'id', $id )->roles );
		$this->assertSame( 'steam', Advisers::kind( $id ) );
		$this->assertSame( self::CODE, Advisers::centre( $id ) );
	}

	/**
	 * A quien ya tiene otro papel en el aplicativo no se le toca.
	 */
	public function test_an_account_with_another_profile_is_left_alone() {
		$id = self::factory()->user->create(
			array(
				'user_email' => 'coordina@ejemplo.org',
				'role'       => 'fmc_curator',
			)
		);

		list( $same, $what ) = AdviserRoster::import_row( AdviserRoster::parse_line( 'coordina@ejemplo.org' ), 'ict' );

		$this->assertSame( $id, $same );
		$this->assertStringContainsString( 'no se toca', $what );
		$this->assertNotContains( AdviserRoster::ROLE, (array) get_user_by( 'id', $id )->roles );
	}

	/**
	 * «Solo ver qué haría» no escribe nada.
	 */
	public function test_the_dry_run_writes_nothing() {
		list( $id, $what ) = AdviserRoster::import_row( AdviserRoster::parse_line( 'Ana;Pérez;ana.perez@ejemplo.org;12345678Z' ), 'ict', true );

		$this->assertSame( 0, $id );
		$this->assertSame( 'se crearía la cuenta', $what );
		$this->assertFalse( get_user_by( 'login', '12345678Z' ) );
	}

	/**
	 * Whoever may run the screen.
	 *
	 * @return int
	 */
	private function admin(): int {
		$id   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$user = get_user_by( 'id', $id );
		$user->add_cap( AdviserImport::CAP );
		wp_set_current_user( $id );
		return $id;
	}

	/**
	 * La pantalla recorre lo pegado y cuenta qué ha pasado con cada línea.
	 */
	public function test_the_screen_runs_the_pasted_list() {
		$this->admin();
		$_POST = array(
			'_fmc_nonce' => wp_create_nonce( AdviserImport::ACTION ),
			'fmc_kind'   => 'ict',
			'fmc_list'   => "Ana;Pérez;ana.perez@ejemplo.org;12345678Z\n\ntexto suelto\n",
			'fmc_dry'    => '1',
		);

		ob_start();
		AdviserImport::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Lo que haría', $html );
		$this->assertStringContainsString( 'se crearía la cuenta', $html );
		$this->assertStringContainsString( 'ni correo ni DNI: se omite', $html );
		$this->assertStringContainsString( 'value="ict" selected=\'selected\'', $html );
		$this->assertFalse( get_user_by( 'login', '12345678Z' ), 'El simulacro no escribe.' );
	}

	/**
	 * Sin simulacro, da de alta y retira de verdad a quien no esté.
	 */
	public function test_the_screen_signs_up_and_retires() {
		$this->admin();
		$gone = self::factory()->user->create( array( 'role' => AdviserRoster::ROLE ) );
		update_user_option( $gone, Advisers::KIND, 'ict' );
		$_POST = array(
			'_fmc_nonce' => wp_create_nonce( AdviserImport::ACTION ),
			'fmc_kind'   => 'ict',
			'fmc_list'   => 'Ana;Pérez;ana.perez@ejemplo.org;12345678Z',
			'fmc_retire' => '1',
		);

		ob_start();
		AdviserImport::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Lo que ha hecho', $html );
		$this->assertStringContainsString( 'cuenta creada', $html );
		$this->assertStringContainsString( 'ya no es asesoría', $html );
		$this->assertInstanceOf( WP_User::class, get_user_by( 'login', '12345678Z' ) );
		$this->assertNotContains( AdviserRoster::ROLE, (array) get_user_by( 'id', $gone )->roles );
	}

	/**
	 * Sin formulario enviado, la pantalla es solo el formulario.
	 */
	public function test_without_a_submitted_form_there_is_no_report() {
		$this->admin();

		ob_start();
		AdviserImport::render();
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'Lo que ha hecho', $html );
		$this->assertStringNotContainsString( 'Lo que haría', $html );
		$this->assertStringContainsString( 'name="fmc_list"', $html );
		$this->assertStringContainsString( 'name="fmc_dry" value="1" checked', $html );
	}

	/**
	 * Quien no está en la lista deja de ser asesoría, pero conserva la cuenta.
	 */
	public function test_whoever_is_not_in_the_list_stops_being_an_adviser() {
		$keeps = self::factory()->user->create( array( 'role' => AdviserRoster::ROLE ) );
		$gone  = self::factory()->user->create( array( 'role' => AdviserRoster::ROLE ) );
		$steam = self::factory()->user->create( array( 'role' => AdviserRoster::ROLE ) );
		foreach ( array( $keeps, $gone ) as $id ) {
			update_user_option( $id, Advisers::KIND, 'ict' );
		}
		update_user_option( $steam, Advisers::KIND, 'steam' );

		$dry = AdviserRoster::retire_missing( array( $keeps ), 'ict', true );
		$this->assertCount( 1, $dry );
		$this->assertStringContainsString( 'se le quitaría', $dry[0][1] );
		$this->assertContains( AdviserRoster::ROLE, (array) get_user_by( 'id', $gone )->roles, 'En simulacro no se toca.' );

		$report = AdviserRoster::retire_missing( array( $keeps ), 'ict' );

		$this->assertCount( 1, $report );
		$this->assertNotContains( AdviserRoster::ROLE, (array) get_user_by( 'id', $gone )->roles );
		$this->assertSame( '', Advisers::kind( $gone ) );
		$this->assertInstanceOf( WP_User::class, get_user_by( 'id', $gone ), 'La cuenta no se borra.' );
		$this->assertContains( AdviserRoster::ROLE, (array) get_user_by( 'id', $keeps )->roles );
		$this->assertContains( AdviserRoster::ROLE, (array) get_user_by( 'id', $steam )->roles, 'La lista de un tipo no desmonta la de otro.' );
	}
}

<?php
/**
 * The admin screen that signs up the adviser roster of a school year.
 *
 * @package Fmc
 */

namespace Fmc\User;

/**
 * «Usuarios → Alta de asesorías»: la lista del curso, pegada de una hoja.
 *
 * Aquí solo está la pantalla: leer el formulario, recorrer las líneas y contar
 * qué ha pasado con cada una. Lo que hace cada línea es de [[AdviserRoster]].
 */
final class AdviserImport {

	/**
	 * Submenu slug.
	 */
	public const PAGE = 'fmc-alta-asesorias';

	/**
	 * Nonce action of the form.
	 */
	public const ACTION = 'fmc_import_advisers';

	/**
	 * Who may run it: the site administration.
	 */
	public const CAP = 'fmc_manage_app';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 11 );
	}

	/**
	 * Add the page under Users.
	 *
	 * @return void
	 */
	public static function menu(): void {
		add_submenu_page( 'users.php', 'Alta de asesorías', 'Alta de asesorías', self::CAP, self::PAGE, array( self::class, 'render' ) );
	}

	/**
	 * What the submitted form says, once checked.
	 *
	 * @return array{kind:string, dry:bool, retire:bool, list:string}|null Null when there is nothing to run.
	 */
	private static function request(): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- se comprueba aquí mismo.
		$nonce = isset( $_POST['_fmc_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_fmc_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::ACTION ) ) {
			return null;
		}
		$kind = isset( $_POST['fmc_kind'] ) ? sanitize_key( wp_unslash( (string) $_POST['fmc_kind'] ) ) : '';

		return array(
			'kind'   => isset( Advisers::KINDS[ $kind ] ) ? $kind : 'other',
			'dry'    => isset( $_POST['fmc_dry'] ),
			'retire' => isset( $_POST['fmc_retire'] ),
			'list'   => isset( $_POST['fmc_list'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['fmc_list'] ) ) : '',
		);
	}

	/**
	 * Run a submitted list, if there is one.
	 *
	 * @return array{report:array<int, array{0:string, 1:string}>, kind:string, dry:bool}
	 */
	private static function run(): array {
		$form = self::request();
		if ( null === $form ) {
			return array(
				'report' => array(),
				'kind'   => 'ict',
				'dry'    => true,
			);
		}

		$report = array();
		$keep   = array();
		foreach ( (array) preg_split( '/\r\n|\r|\n/', $form['list'] ) as $line ) {
			list( $id, $said ) = self::run_line( (string) $line, $form['kind'], $form['dry'] );
			$keep              = $id ? array_merge( $keep, array( $id ) ) : $keep;
			$report            = null === $said ? $report : array_merge( $report, array( $said ) );
		}
		if ( $form['retire'] ) {
			$report = array_merge( $report, AdviserRoster::retire_missing( $keep, $form['kind'], $form['dry'] ) );
		}

		return array(
			'report' => $report,
			'kind'   => $form['kind'],
			'dry'    => $form['dry'],
		);
	}

	/**
	 * Read and sign up one pasted line.
	 *
	 * @param string $line Raw line.
	 * @param string $kind Kind of advisory for the whole list.
	 * @param bool   $dry  Only say what it would do.
	 * @return array{0:int, 1:array{0:string, 1:string}|null} User ID and the report row, if any.
	 */
	private static function run_line( string $line, string $kind, bool $dry ): array {
		$row = AdviserRoster::parse_line( $line );
		if ( null === $row ) {
			return array( 0, '' === trim( $line ) ? null : array( $line, 'ni correo ni DNI: se omite' ) );
		}
		list( $id, $what ) = AdviserRoster::import_row( $row, $kind, $dry );

		return array( $id, array( '' !== $row['email'] ? $row['email'] : $row['nif'], $what ) );
	}

	/**
	 * Render the screen and process a submitted list.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'No tiene permiso.' );
		}
		$run = self::run();

		echo '<div class="wrap"><h1>Alta de asesorías</h1>';
		echo '<p>Pegue la lista del curso, una persona por línea: nombre, apellidos, correo, DNI y código de su centro de referencia. Valen el punto y coma, el tabulador y la coma, y el orden de las columnas da igual. Si la persona ya tiene cuenta se la añade a este sitio con el perfil de asesoría; si no la tiene, se le crea con el DNI de usuario y <strong>sin enviarle ningún correo</strong>. Volver a pegar la lista no duplica a nadie: completa lo que falte.</p>';
		echo '<p><code>Ana;Pérez Gómez;ana.perez@ejemplo.org;12345678Z;38001234</code></p>';
		self::report_html( $run['report'], $run['dry'] );
		self::form_html( $run['kind'] );
		echo '</div>';
	}

	/**
	 * What happened with each line.
	 *
	 * @param array<int, array{0:string, 1:string}> $report Report rows.
	 * @param bool                                  $dry    Whether nothing was written.
	 * @return void
	 */
	private static function report_html( array $report, bool $dry ): void {
		if ( ! $report ) {
			return;
		}
		echo '<h2>' . ( $dry ? 'Lo que haría' : 'Lo que ha hecho' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:52em;margin-bottom:1em;"><thead><tr><th>Persona</th><th>Resultado</th></tr></thead><tbody>';
		foreach ( $report as $row ) {
			printf( '<tr><td>%s</td><td>%s</td></tr>', esc_html( $row[0] ), esc_html( $row[1] ) );
		}
		echo '</tbody></table>';
	}

	/**
	 * The form.
	 *
	 * @param string $kind Kind chosen last time.
	 * @return void
	 */
	private static function form_html( string $kind ): void {
		echo '<form method="post">';
		wp_nonce_field( self::ACTION, '_fmc_nonce' );
		echo '<p><label>Tipo de asesoría para toda la lista <select name="fmc_kind">';
		foreach ( Advisers::KINDS as $slug => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $kind, $slug, false ), esc_html( $label ) );
		}
		echo '</select></label></p>';
		echo '<textarea name="fmc_list" rows="12" style="width:100%;max-width:52em;font-family:monospace;"></textarea>';
		echo '<p><label><input type="checkbox" name="fmc_retire" value="1"> Quitar la asesoría a quien no esté en la lista, del tipo elegido. No se borra ninguna cuenta: pierde el perfil y vuelve a tenerlo si se la pega otra vez.</label></p>';
		echo '<p><label><input type="checkbox" name="fmc_dry" value="1" checked> Solo ver qué haría, sin tocar nada.</label></p>';
		echo '<p><button type="submit" class="button button-primary">Dar de alta</button></p>';
		echo '</form>';
	}
}

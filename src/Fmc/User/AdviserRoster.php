<?php
/**
 * The adviser roster of a school year, as a pasted list.
 *
 * @package Fmc
 */

namespace Fmc\User;

use Fmc\Centre\CentreCatalogue;

/**
 * Lo que hace falta para llevar al día quién es asesoría y de qué tipo.
 *
 * Dar de alta a una persona a mano son varias pantallas del escritorio
 * —buscarla en la red, añadirla al sitio, ponerle el perfil, el tipo y su
 * centro de referencia— y a principio de curso llegan decenas de golpe. Aquí
 * está la regla; la pantalla que la usa es [[AdviserImport]].
 *
 * Quién es quién lo dice el DNI, que es el usuario con el que entra la gente;
 * el correo solo sirve para encontrar a quien ya tiene cuenta.
 */
final class AdviserRoster {

	/**
	 * The role an account of the list gets.
	 */
	public const ROLE = 'fmc_adviser';

	/**
	 * Roles that nunca se tocan: quien ya tiene otro papel en el aplicativo no
	 * es asesoría por descuido, y añadirle el perfil le cambiaría lo que ve sin
	 * que nadie lo haya pedido.
	 */
	public const KEEP = array( 'fmc_curator', 'fmc_training_service', 'administrator' );

	/**
	 * Read one pasted line.
	 *
	 * Lo que se pega sale de una hoja de cálculo, así que valen el punto y
	 * coma, el tabulador y la coma, y las columnas se reconocen por lo que son
	 * y no por el orden: el correo es el que parece un correo, el DNI el que
	 * parece un DNI y el centro el código de ocho cifras que está en el
	 * catálogo. Así vale tanto la lista entera como una columna de correos.
	 *
	 * @param string $line One raw line.
	 * @return array{email:string, nif:string, centre:string, name:string, surname:string}|null
	 */
	public static function parse_line( string $line ): ?array {
		$parts = array_filter( array_map( 'trim', explode( self::separator( $line ), trim( $line ) ) ) );
		$found = array(
			'email'  => '',
			'nif'    => '',
			'centre' => '',
		);
		$words = array();
		foreach ( $parts as $part ) {
			$field = self::field_of( (string) $part, $found );
			if ( '' === $field ) {
				$words[] = sanitize_text_field( (string) $part );
				continue;
			}
			$found[ $field ] = 'email' === $field ? sanitize_email( (string) $part ) : strtoupper( (string) $part );
		}

		return '' === $found['email'] . $found['nif'] ? null : $found + array(
			'name'    => (string) reset( $words ),
			'surname' => implode( ' ', array_slice( $words, 1 ) ),
		);
	}

	/**
	 * What separates the columns of a pasted line.
	 *
	 * @param string $line Raw line.
	 * @return string
	 */
	private static function separator( string $line ): string {
		foreach ( array( ';', "\t" ) as $sep ) {
			if ( false !== strpos( $line, $sep ) ) {
				return $sep;
			}
		}
		return ',';
	}

	/**
	 * Which field one pasted column is, or '' when it is part of the name.
	 *
	 * @param string                $part  Column.
	 * @param array<string, string> $found What the line already brought.
	 * @return string `email`, `nif`, `centre` or ''.
	 */
	private static function field_of( string $part, array $found ): string {
		if ( '' === $found['email'] && is_email( $part ) ) {
			return 'email';
		}
		if ( '' === $found['nif'] && preg_match( '/^[0-9XYZxyz][0-9]{7}[A-Za-z]$/', $part ) ) {
			return 'nif';
		}
		// Ocho cifras son el código del centro, esté o no en el catálogo: si no
		// está, se dice en el informe, que es mejor que colárselo al apellido.
		if ( '' === $found['centre'] && preg_match( '/^\d{8}$/', $part ) ) {
			return 'centre';
		}
		return '';
	}

	/**
	 * Whoever this row is already about.
	 *
	 * El DNI manda: en el sitio de destino las cuentas entran por el DNI, y la
	 * lista puede traer otro correo de la misma persona. Buscar solo por correo
	 * acabaría creando una segunda cuenta con el DNI ocupado.
	 *
	 * @param array{email:string, nif:string, centre:string, name:string, surname:string} $row Parsed row.
	 * @return \WP_User|null
	 */
	private static function find( array $row ): ?\WP_User {
		$user = '' !== $row['nif'] ? get_user_by( 'login', sanitize_user( $row['nif'], true ) ) : false;
		if ( ! $user && '' !== $row['email'] ) {
			$user = get_user_by( 'email', $row['email'] );
		}
		return $user instanceof \WP_User ? $user : null;
	}

	/**
	 * Sign up (or complete) one account from a parsed row.
	 *
	 * Es idempotente: volver a pasar la misma lista completa lo que falte en
	 * vez de duplicar a nadie, porque lo que llega es la lista corregida entera
	 * y no lo que ha cambiado.
	 *
	 * @param array{email:string, nif:string, centre:string, name:string, surname:string} $row  Parsed row.
	 * @param string                                                                      $kind Kind of advisory for the whole list.
	 * @param bool                                                                        $dry  Only say what it would do.
	 * @return array{0:int, 1:string} User ID (0 when none) and what happened.
	 */
	public static function import_row( array $row, string $kind, bool $dry = false ): array {
		$user   = self::find( $row );
		$others = $user ? array_intersect( (array) $user->roles, self::KEEP ) : array();
		if ( $others ) {
			return array( (int) $user->ID, 'ya tiene otro perfil (' . implode( ', ', $others ) . '): no se toca' );
		}

		$status = 'ya tenía cuenta';
		if ( ! $user ) {
			list( $id, $status ) = self::create_account( $row, $dry );
			if ( ! $id ) {
				return array( 0, $status );
			}
			$user = get_user_by( 'id', $id );
		}

		return array( (int) $user->ID, $status . self::apply_profile( $user, $kind, $row, $dry ) );
	}

	/**
	 * Create the account of whoever has none.
	 *
	 * No sale ningún correo de bienvenida: `wp_insert_user()` no avisa a nadie
	 * —quien lo hace es `wp_send_new_user_notifications()`—, que es lo que se
	 * quiere, porque estas cuentas entran por el acceso de la organización y
	 * nunca con una contraseña de WordPress.
	 *
	 * @param array{email:string, nif:string, centre:string, name:string, surname:string} $row Parsed row.
	 * @param bool                                                                        $dry Only say what it would do.
	 * @return array{0:int, 1:string} User ID (0 when it was not created) and what happened.
	 */
	private static function create_account( array $row, bool $dry ): array {
		if ( '' === $row['nif'] . $row['email'] || '' === $row['nif'] || '' === $row['email'] ) {
			return array( 0, 'no tiene cuenta y la lista no trae su ' . ( '' === $row['nif'] ? 'DNI' : 'correo' ) . ': no se crea' );
		}
		if ( $dry ) {
			return array( 0, 'se crearía la cuenta' );
		}

		$id = wp_insert_user(
			array(
				'user_login'   => sanitize_user( $row['nif'], true ),
				'user_email'   => $row['email'],
				'user_pass'    => wp_generate_password( 24 ),
				'first_name'   => $row['name'],
				'last_name'    => $row['surname'],
				'display_name' => trim( $row['name'] . ' ' . $row['surname'] ),
				'role'         => self::ROLE,
			)
		);

		return is_wp_error( $id ) ? array( 0, $id->get_error_message() ) : array( (int) $id, 'cuenta creada' );
	}

	/**
	 * Leave the account as the list says: site, role, kind and centre.
	 *
	 * @param \WP_User                                                                    $user Account.
	 * @param string                                                                      $kind Kind of advisory for the whole list.
	 * @param array{email:string, nif:string, centre:string, name:string, surname:string} $row  Parsed row.
	 * @param bool                                                                        $dry  Only say what it would do.
	 * @return string What changed, to add to the report.
	 */
	private static function apply_profile( \WP_User $user, string $kind, array $row, bool $dry ): string {
		$id   = (int) $user->ID;
		$said = self::join_site( $user, $dry );

		if ( Advisers::kind( $id ) !== $kind ) {
			$said .= ', tipo ' . ( Advisers::KINDS[ $kind ] ?? $kind );
			if ( ! $dry ) {
				update_user_option( $id, Advisers::KIND, $kind );
			}
		}

		// El centro solo se toca cuando la lista lo trae: la ficha puede
		// haberse corregido a mano después de mandarla.
		if ( '' !== $row['centre'] && null === CentreCatalogue::find( $row['centre'] ) ) {
			return $said . ', el centro ' . $row['centre'] . ' no está en el catálogo: no se pone';
		}
		if ( '' !== $row['centre'] && Advisers::centre( $id ) !== $row['centre'] ) {
			$said .= ', centro ' . CentreCatalogue::label( $row['centre'] );
			if ( ! $dry ) {
				update_user_option( $id, Advisers::CENTRE, $row['centre'] );
			}
		}

		return $said;
	}

	/**
	 * Make sure the account belongs to this site with the adviser role.
	 *
	 * En una red, tener cuenta no es estar en este sitio: sin añadirla aquí no
	 * tendría perfil ni vería el aplicativo.
	 *
	 * @param \WP_User $user Account.
	 * @param bool     $dry  Only say what it would do.
	 * @return string What changed, to add to the report.
	 */
	private static function join_site( \WP_User $user, bool $dry ): string {
		$id = (int) $user->ID;
		if ( is_multisite() && ! is_user_member_of_blog( $id ) ) {
			if ( ! $dry ) {
				add_user_to_blog( get_current_blog_id(), $id, self::ROLE );
			}
			return ', añadida al sitio';
		}
		if ( in_array( self::ROLE, (array) $user->roles, true ) ) {
			return '';
		}
		if ( ! $dry ) {
			$user->add_role( self::ROLE );
		}
		return ', con el perfil de asesoría';
	}

	/**
	 * Take the advisory away from the accounts of a kind the list leaves out.
	 *
	 * La cuenta no se borra nunca: se le quita el perfil de asesoría y el tipo,
	 * que es lo que deja de verse en el aplicativo, y vuelve pegándola otra vez
	 * en la lista. Solo se miran las del tipo que se está actualizando, para
	 * que la lista de un tipo no desmonte la de otro.
	 *
	 * @param array<int, int> $keep Account IDs the list brings.
	 * @param string          $kind Kind being updated.
	 * @param bool            $dry  Only say what it would do.
	 * @return array<int, array{0:string, 1:string}> Report rows.
	 */
	public static function retire_missing( array $keep, string $kind, bool $dry = false ): array {
		$report = array();
		foreach ( get_users( array( 'role' => self::ROLE ) ) as $user ) {
			$id = (int) $user->ID;
			if ( in_array( $id, $keep, true ) || Advisers::kind( $id ) !== $kind || array_intersect( (array) $user->roles, self::KEEP ) ) {
				continue;
			}
			$report[] = array( $user->display_name, $dry ? 'se le quitaría la asesoría: no está en la lista' : 'ya no es asesoría: no está en la lista' );
			if ( ! $dry ) {
				delete_user_option( $id, Advisers::KIND );
				$user->remove_role( self::ROLE );
			}
		}
		return $report;
	}
}

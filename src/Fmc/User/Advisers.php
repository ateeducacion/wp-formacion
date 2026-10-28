<?php
/**
 * The adviser accounts of the site and their kind.
 *
 * @package Fmc
 */

namespace Fmc\User;

use Fmc\Centre\CentreCatalogue;
use Fmc\Taxonomy\Taxonomies;

/**
 * La asesoría responsable de una acción es una cuenta del sitio (ADR-0019).
 *
 * La administración da de alta a la asesoría como usuaria del sitio, con su
 * rol; aquí solo se le añade de qué tipo es —TIC, STEAM u otra— y de qué centro
 * del profesorado es, en su perfil del escritorio. Se guarda por sitio
 * (`update_user_option()`): en un multisitio los datos de usuario son de toda
 * la red, y una persona puede ser asesoría de un tipo en un sitio y de otro, o
 * de ninguno, en otro.
 */
final class Advisers {

	/**
	 * User option, per site: the kind of advisory.
	 */
	public const KIND = 'fmc_adviser_kind';

	/**
	 * User option, per site: the code of the adviser's centre (ADR-0022).
	 */
	public const CENTRE = 'fmc_adviser_centre';

	/**
	 * Kinds of advisory. Lista cerrada (ADR-0003): ampliarla es un cambio de código.
	 */
	public const KINDS = array(
		'ict'   => 'TIC',
		'steam' => 'STEAM',
		'other' => 'Otra',
	);

	/**
	 * Roles whose accounts can be the adviser of an action.
	 */
	public const ROLES = array( 'fmc_adviser', 'fmc_curator' );

	/**
	 * Hook registration: the kind in the dashboard's user profile and list.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		add_action( 'show_user_profile', array( self::class, 'profile_field' ) );
		add_action( 'edit_user_profile', array( self::class, 'profile_field' ) );
		add_action( 'personal_options_update', array( self::class, 'save_profile' ) );
		add_action( 'edit_user_profile_update', array( self::class, 'save_profile' ) );
		add_filter( 'manage_users_columns', array( self::class, 'kind_column' ) );
		add_filter( 'manage_users_custom_column', array( self::class, 'kind_cell' ), 10, 3 );
	}

	/**
	 * The kind of advisory of an account in this site, or '' if none.
	 *
	 * @param int $user_id User.
	 * @return string
	 */
	public static function kind( int $user_id ): string {
		$kind = (string) get_user_option( self::KIND, $user_id );
		return isset( self::KINDS[ $kind ] ) ? $kind : '';
	}

	/**
	 * The code of the centre an account belongs to in this site, or ''.
	 *
	 * @param int $user_id User.
	 * @return string
	 */
	public static function centre( int $user_id ): string {
		$code = trim( (string) get_user_option( self::CENTRE, $user_id ) );
		return null !== CentreCatalogue::find( $code ) ? $code : '';
	}

	/**
	 * The scope term of an account's centre, or 0 when there is none.
	 *
	 * El ámbito que gestiona una acción es el centro del profesorado que la
	 * organiza, y los términos de `fmc_scope` se llaman igual que el centro en
	 * el catálogo. Por eso el ámbito sale del centro de referencia por su
	 * nombre y no hace falta configurar nada más (ADR-0022): si el término no
	 * existe o se renombra, no se rellena nada y se elige a mano.
	 *
	 * @param int $user_id User.
	 * @return int Term ID.
	 */
	public static function scope( int $user_id ): int {
		$centre = CentreCatalogue::find( self::centre( $user_id ) );
		$name   = trim( (string) ( $centre['name'] ?? '' ) );
		if ( '' === $name ) {
			return 0;
		}
		$term = get_term_by( 'name', $name, Taxonomies::SCOPE );
		return $term instanceof \WP_Term ? (int) $term->term_id : 0;
	}

	/**
	 * The accounts that can be the adviser of an action, with their kind.
	 *
	 * @return array<int, string> User ID => «Nombre · TIC».
	 */
	public static function choices(): array {
		$out = array();
		foreach ( get_users(
			array(
				'role__in' => self::ROLES,
				'orderby'  => 'display_name',
				'fields'   => array( 'ID', 'display_name' ),
			)
		) as $u ) {
			$kind                = self::kind( (int) $u->ID );
			$out[ (int) $u->ID ] = $u->display_name . ( '' !== $kind ? ' · ' . self::KINDS[ $kind ] : '' );
		}
		return $out;
	}

	/**
	 * The adviser accounts of a kind.
	 *
	 * @param string $kind Kind.
	 * @return int[]
	 */
	public static function of_kind( string $kind ): array {
		return array_values( array_filter( array_keys( self::choices() ), static fn( int $id ): bool => self::kind( $id ) === $kind ) );
	}

	/**
	 * The «Tipo de asesoría» field of the profile, for who manages accounts.
	 *
	 * @param \WP_User $user User being edited.
	 * @return void
	 */
	public static function profile_field( $user ): void {
		if ( ! current_user_can( 'promote_users' ) ) {
			return;
		}
		$kind = self::kind( (int) $user->ID );
		echo '<h2>Formación</h2><table class="form-table" role="presentation"><tr><th><label for="fmc-adviser-kind">Tipo de asesoría</label></th><td>';
		wp_nonce_field( 'fmc_adviser_kind', '_fmc_adviser_nonce' );
		echo '<select id="fmc-adviser-kind" name="fmc_adviser_kind"><option value="">—</option>';
		foreach ( self::KINDS as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $kind, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><p class="description">Si la cuenta es de una asesoría, de qué tipo es en este sitio. Sale junto a su nombre al elegir la asesoría responsable de una acción.</p></td></tr>';

		$code     = self::centre( (int) $user->ID );
		$centres  = CentreCatalogue::active_options();
		$centres += '' !== $code && ! isset( $centres[ $code ] ) ? array( $code => (string) ( CentreCatalogue::find( $code )['name'] ?? $code ) ) : array();
		echo '<tr><th><label for="fmc-adviser-centre">Centro de referencia</label></th><td>';
		if ( array() === $centres ) {
			echo '<p class="description">Todavía no hay catálogo de centros. Actualícelo en «Ajustes → Centros educativos».</p>';
		} else {
			echo '<select id="fmc-adviser-centre" name="fmc_adviser_centre"><option value="">—</option>';
			foreach ( $centres as $value => $label ) {
				echo '<option value="' . esc_attr( (string) $value ) . '"' . selected( $code, (string) $value, false ) . '>' . esc_html( CentreCatalogue::label( (string) $value, $label ) ) . '</option>';
			}
			echo '</select>';
		}
		echo '<p class="description">El centro del profesorado al que pertenece. De él sale el ámbito que se propone al crear una acción formativa, que se puede cambiar.</p></td></tr></table>';
	}

	/**
	 * Save the kind, if who sends it manages accounts.
	 *
	 * @param int $user_id User being edited.
	 * @return void
	 */
	public static function save_profile( $user_id ): void {
		if ( ! isset( $_POST['_fmc_adviser_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_fmc_adviser_nonce'] ) ), 'fmc_adviser_kind' ) || ! current_user_can( 'promote_users' ) || ! current_user_can( 'edit_user', (int) $user_id ) ) {
			return;
		}
		$kind = isset( $_POST['fmc_adviser_kind'] ) ? sanitize_key( wp_unslash( $_POST['fmc_adviser_kind'] ) ) : '';
		if ( isset( self::KINDS[ $kind ] ) ) {
			update_user_option( (int) $user_id, self::KIND, $kind );
		} else {
			delete_user_option( (int) $user_id, self::KIND );
		}

		// El centro, del catálogo y por su código (ADR-0021): uno que no esté en
		// él no se guarda.
		$centre = isset( $_POST['fmc_adviser_centre'] ) ? sanitize_text_field( wp_unslash( $_POST['fmc_adviser_centre'] ) ) : '';
		if ( null !== CentreCatalogue::find( $centre ) ) {
			update_user_option( (int) $user_id, self::CENTRE, $centre );
		} else {
			delete_user_option( (int) $user_id, self::CENTRE );
		}
	}

	/**
	 * The «Asesoría» column of the users list.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function kind_column( $columns ): array {
		$columns[ self::KIND ] = 'Asesoría';
		return (array) $columns;
	}

	/**
	 * A cell of the «Asesoría» column.
	 *
	 * @param string $output  Output so far.
	 * @param string $column  Column.
	 * @param int    $user_id User.
	 * @return string
	 */
	public static function kind_cell( $output, $column, $user_id ): string {
		if ( self::KIND !== $column ) {
			return (string) $output;
		}
		$kind = self::kind( (int) $user_id );
		return '' !== $kind ? esc_html( self::KINDS[ $kind ] ) : '—';
	}
}

<?php
/**
 * The edit screen of every type, built from Fields, and its save handler.
 *
 * @package Fmc
 */

namespace Fmc\PublicFront;

use Fmc\Meta\ActionMetaKeys as A;
use Fmc\Meta\DesignMetaKeys as D;
use Fmc\Domain\DigComp;
use Fmc\Meta\IncidentMetaKeys as I;
use Fmc\Meta\MetaRegistration;
use Fmc\Meta\SpeakerMetaKeys as S;
use Fmc\PostType\PostTypes;

/**
 * La ficha de edición, como el taller de eventos: tarjetas por sección, ayuda
 * bajo cada campo y un botón de guardar.
 *
 * Todo lo que no se puede escribir sale deshabilitado, y **además** el
 * guardado lo vuelve a comprobar campo a campo: deshabilitar en la pantalla no
 * protege nada (ADR-0006). Quien solo tiene un campo —el servicio de formación
 * con el expediente— ve la acción entera y puede guardar solo ese.
 */
final class Editor {

	/**
	 * Nonce action for a post (0 when new).
	 *
	 * @param string $type Post type.
	 * @param int    $id   Post ID.
	 * @return string
	 */
	public static function nonce_action( string $type, int $id ): string {
		return 'fmc_save_' . $type . '_' . $id;
	}

	/**
	 * Whether the current user may open a post.
	 *
	 * Los ponentes son datos personales: abrirlos pide poder gestionarlos, no
	 * basta con que estén publicados. Quien los gestiona abre cualquiera, para
	 * consultarlo, aunque solo edite los suyos (ADR-0011).
	 *
	 * @param \WP_Post $post Post.
	 * @return bool
	 */
	public static function can_view( \WP_Post $post ): bool {
		if ( current_user_can( 'edit_post', $post->ID ) ) {
			return true;
		}
		switch ( $post->post_type ) {
			case PostTypes::DESIGN:
				return current_user_can( 'read_post', $post->ID );
			case PostTypes::ACTION:
				// La que sale en el calendario se puede abrir para verla; se edita si es tuya.
				return 'publish' === $post->post_status && ( current_user_can( 'read_private_fmc_actions' ) || current_user_can( 'edit_fmc_actions' ) );
			case PostTypes::INCIDENT:
				return current_user_can( 'read_private_fmc_incidents' ) || current_user_can( PostTypes::CAP_RESOLVE );
			case PostTypes::SPEAKER:
				return 'publish' === $post->post_status && current_user_can( 'edit_fmc_speakers' );
		}
		return false;
	}

	/**
	 * Whether the current user may write a field.
	 *
	 * @param string $type Post type.
	 * @param string $key  Field key.
	 * @param int    $id   Post ID; 0 when new.
	 * @return bool
	 */
	public static function can_write( string $type, string $key, int $id ): bool {
		if ( 'docs' === $key ) {
			return $id ? Documents::can_manage( $id ) : current_user_can( 'upload_files' ) && current_user_can( get_post_type_object( $type )->cap->create_posts );
		}
		if ( ! empty( Fields::all( $type )[ $key ]['auto'] ) ) {
			return false;
		}
		$post_field = 'post_title' === $key || 'post_content' === $key || 0 === strpos( $key, 'tax:' );
		if ( 0 === $id ) {
			if ( ! current_user_can( get_post_type_object( $type )->cap->create_posts ) ) {
				return false;
			}
			$cap = MetaRegistration::guarded()[ $type ][ $key ] ?? '';
			return $post_field || '' === $cap || current_user_can( $cap );
		}
		if ( $post_field ) {
			return current_user_can( 'edit_post', $id );
		}
		return MetaRegistration::can_write( $type, $key, $id );
	}

	/**
	 * The screen for `?fmc_edit=ID` or `?fmc_new=type`.
	 *
	 * @param int    $id  Post ID.
	 * @param string $new_type Post type to create.
	 * @return array<string, string>
	 */
	public static function screen( int $id, string $new_type ): array {
		if ( $id ) {
			$post = get_post( $id );
			if ( ! $post instanceof \WP_Post || ! isset( PostTypes::definitions()[ $post->post_type ] ) || ! self::can_view( $post ) ) {
				return self::denied();
			}
			return self::render( $post->post_type, $post, array(), array() );
		}
		if ( ! isset( PostTypes::definitions()[ $new_type ] ) || ! current_user_can( get_post_type_object( $new_type )->cap->create_posts ) ) {
			return self::denied();
		}
		$parent = absint( Lists::arg( Screen::ARG_PARENT ) );
		if ( PostTypes::INCIDENT === $new_type && ( PostTypes::ACTION !== get_post_type( $parent ) || ! current_user_can( 'edit_post', $parent ) ) ) {
			return self::denied();
		}
		return self::render( $new_type, null, array(), array() );
	}

	/**
	 * The «not allowed» screen.
	 *
	 * @return array<string, string>
	 */
	private static function denied(): array {
		return array(
			'tab'   => '',
			'title' => 'No disponible',
			'body'  => '<div class="alert alert-warning">No existe o no tiene permiso para verlo.</div>',
		);
	}

	/**
	 * Stored value of a field.
	 *
	 * @param \WP_Post|null $post Post.
	 * @param string        $key  Field key.
	 * @return mixed
	 */
	public static function value( ?\WP_Post $post, string $key ) {
		if ( ! $post ) {
			return '';
		}
		if ( 'post_title' === $key || 'post_content' === $key ) {
			return $post->$key;
		}
		if ( 0 === strpos( $key, 'tax:' ) ) {
			$ids = wp_get_object_terms( $post->ID, substr( $key, 4 ), array( 'fields' => 'ids' ) );
			return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
		}
		return get_post_meta( $post->ID, $key, true );
	}

	/**
	 * Render the edit screen.
	 *
	 * @param string                $type   Post type.
	 * @param \WP_Post|null         $post   Post, or null when new.
	 * @param array<string, mixed>  $values Submitted values that override the stored ones.
	 * @param array<string, string> $errors Field key => what is wrong with it.
	 * @return array<string, string>
	 */
	public static function render( string $type, ?\WP_Post $post, array $values, array $errors ): array {
		$id       = $post ? $post->ID : 0;
		$object   = get_post_type_object( $type );
		$tab      = Screen::tab_of( $type );
		$writable = false;
		$parent   = $post ? (int) $post->post_parent : absint( $values['_parent'] ?? Lists::arg( Screen::ARG_PARENT ) );

		// Una acción nueva desde un diseño (`fmc_design`): trae su modalidad y sus horas.
		$from = absint( Lists::arg( 'fmc_design' ) );
		if ( ! $post && PostTypes::ACTION === $type && $from && PostTypes::DESIGN === get_post_type( $from ) ) {
			$values += array(
				A::DESIGN_ID    => $from,
				A::MODALITY     => get_post_meta( $from, D::MODALITY, true ),
				A::HOURS_ONSITE => get_post_meta( $from, D::HOURS_ONSITE, true ),
				A::HOURS_ONLINE => get_post_meta( $from, D::HOURS_ONLINE, true ),
			);
		}

		// El código del diseño nuevo: el que le tocará al guardar.
		if ( ! $post && PostTypes::DESIGN === $type ) {
			$values += array( D::CODE => self::next_code() );
		}

		$body = '';
		if ( PostTypes::SPEAKER === $type ) {
			$body .= self::privacy_notice();
		}
		if ( $errors ) {
			$body .= '<div class="alert alert-danger" role="alert"><strong>No se ha guardado.</strong><ul class="mb-0">';
			foreach ( $errors as $message ) {
				$body .= '<li>' . esc_html( $message ) . '</li>';
			}
			$body .= '</ul></div>';
		}
		if ( PostTypes::INCIDENT === $type && $parent ) {
			$body .= '<div class="alert alert-light border">Sobre la acción: <a href="' . esc_url( Screen::url( array( Screen::ARG_EDIT => $parent ) ) ) . '">' . esc_html( get_the_title( $parent ) ) . '</a></div>';
		}
		if ( PostTypes::ACTION === $type && $post ) {
			$body .= self::action_summary( $post );
		}

		$body .= '<form method="post" enctype="multipart/form-data" action="' . esc_url( home_url( '/' ) ) . '" class="fmc-form">';
		$body .= wp_nonce_field( self::nonce_action( $type, $id ), '_fmc_nonce', true, false );
		$body .= Screen::framed() ? '<input type="hidden" name="' . esc_attr( Screen::ARG_FRAME ) . '" value="1">' : '';
		$body .= '<input type="hidden" name="fmc_save" value="1"><input type="hidden" name="fmc_type" value="' . esc_attr( $type ) . '"><input type="hidden" name="fmc_id" value="' . esc_attr( (string) $id ) . '">';
		if ( ! $post && $parent ) {
			$body .= '<input type="hidden" name="fmc_parent" value="' . esc_attr( (string) $parent ) . '">';
		}

		foreach ( Fields::sections( $type ) as $section ) {
			$body .= '<fieldset class="card fmc-section mb-4"><legend class="fmc-section-title">' . esc_html( $section['title'] ) . '</legend><div class="card-body">';
			if ( '' !== $section['help'] ) {
				$body .= '<p class="text-secondary small">' . esc_html( $section['help'] ) . '</p>';
			}
			$body .= '<div class="row g-3">';
			foreach ( $section['fields'] as $key => $field ) {
				$can      = self::can_write( $type, $key, $id );
				$writable = $writable || $can;
				$value    = array_key_exists( $key, $values ) ? $values[ $key ] : self::value( $post, $key );
				$shown    = self::shown( $field, static fn( $k ) => array_key_exists( $k, $values ) ? $values[ $k ] : self::value( $post, $k ) );
				$field   += array( 'design' => (int) ( array_key_exists( A::DESIGN_ID, $values ) ? $values[ A::DESIGN_ID ] : self::value( $post, A::DESIGN_ID ) ) );
				$body    .= 'docs' === $field['w'] ? self::docs( $post, $can ) : self::field( $type, $key, $field, $value, $can, isset( $errors[ $key ] ), $shown );
			}
			$body .= '</div></div></fieldset>';
		}

		if ( $writable ) {
			$body .= '<div class="fmc-savebar">' . self::buttons( $type, $post ) . '</div>';
		} else {
			$body .= '<p class="text-secondary">Solo lectura: no tiene nada que cambiar aquí.</p>';
		}
		$body .= '</form>';

		if ( PostTypes::ACTION === $type && $post ) {
			$body .= self::incidents( $post );
		}
		if ( PostTypes::SPEAKER === $type && $post && Screen::can_delete( $post ) ) {
			$body .= Screen::trash_form( $post );
		}

		$badges = $post ? Lists::badge( $post ) : '';
		return array(
			'tab'      => $tab,
			'back'     => '<a class="small" href="' . esc_url( Screen::url( array( Screen::ARG_TAB => $tab ) ) ) . '">← ' . esc_html( $object->labels->name ) . '</a>',
			'title'    => $post ? Lists::title( $post ) : $object->labels->new_item,
			// El subtítulo de la acción se añade al título, debajo (ADR-0009).
			'subtitle' => $post && PostTypes::ACTION === $type ? (string) get_post_meta( $post->ID, A::SUBTITLE, true ) : '',
			'badges'   => $badges,
			'actions'  => self::header_actions( $type, $post ),
			'body'     => $body,
		);
	}

	/**
	 * The data-protection notice above a speaker record.
	 *
	 * El texto legal es de quien despliega (ADR-0002): llega por el filtro
	 * `fmc_privacy_notice`. El de serie dice lo que hay que saber sin nombrar a
	 * nadie.
	 *
	 * @return string
	 */
	public static function privacy_notice(): string {
		$text = (string) apply_filters(
			'fmc_privacy_notice',
			'<strong>Datos personales.</strong> Registre solo a quien imparte o va a impartir formación, y solo los datos que se piden aquí. Quien los registra se asegura de que la persona sabe que constan en este aplicativo para gestionar la formación. Si pide que se borren, dé de baja su ficha indicando el motivo.'
		);
		return '' !== $text ? '<div class="alert alert-info fmc-privacy" role="note">' . wp_kses_post( $text ) . '</div>' : '';
	}

	/**
	 * The next design code: the highest number in use plus one, same prefix.
	 *
	 * @return string
	 */
	public static function next_code(): string {
		// ponytail: dos altas en el mismo segundo podrían llevarse el mismo código;
		// si llega a pasar, un índice único en la meta o un contador en una opción.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- el mayor código en uso; no hay API para esto.
		$codes  = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", D::CODE ) );
		$prefix = D::CODE_PREFIX;
		$max    = 0;
		foreach ( $codes as $code ) {
			if ( preg_match( '/^([A-Za-z]*)(\d+)$/', trim( (string) $code ), $m ) && (int) $m[2] > $max ) {
				$max    = (int) $m[2];
				$prefix = $m[1];
			}
		}
		return $prefix . ( $max + 1 );
	}

	/**
	 * Buttons next to the title of a record.
	 *
	 * @param string        $type Post type.
	 * @param \WP_Post|null $post Post.
	 * @return string
	 */
	private static function header_actions( string $type, ?\WP_Post $post ): string {
		if ( ! $post ) {
			return '';
		}
		$out = '';
		if ( PostTypes::DESIGN === $type && 'publish' === $post->post_status ) {
			if ( current_user_can( 'edit_fmc_actions' ) ) {
				$out .= '<a class="btn btn-primary btn-sm fmc-abre-panel" title="Nueva acción formativa" href="' . esc_url(
					Screen::url(
						array(
							Screen::ARG_NEW => PostTypes::ACTION,
							'fmc_design'    => $post->ID,
						)
					)
				) . '">Crear una acción con este diseño</a> ';
			}
			// Solo lo que está en el catálogo tiene ficha pública.
			if ( Catalogue::is_listed( $post ) ) {
				$out .= '<a class="btn btn-outline-primary btn-sm" href="' . esc_url( Catalogue::url( $post->ID ) ) . '" target="_blank" rel="noopener">Ver en el catálogo</a> ';
			}
		}
		// El ponente se da de baja abajo, con su motivo.
		if ( PostTypes::SPEAKER !== $type && Screen::can_delete( $post ) ) {
			$out .= Screen::delete_form( $post, 'btn-sm' );
		}
		return $out;
	}

	/**
	 * Save buttons: the design ones move it through its cycle (ADR-0004).
	 *
	 * @param string        $type Post type.
	 * @param \WP_Post|null $post Post.
	 * @return string
	 */
	private static function buttons( string $type, ?\WP_Post $post ): string {
		$button = static fn( string $status, string $label, string $css ): string => '<button class="btn ' . $css . '" type="submit" name="fmc_status" value="' . esc_attr( $status ) . '">' . esc_html( $label ) . '</button> ';
		if ( PostTypes::INCIDENT === $type && ! $post ) {
			return $button( 'publish', 'Enviar la incidencia', 'btn-primary' );
		}
		if ( PostTypes::DESIGN !== $type ) {
			return $button( '', 'Guardar', 'btn-primary' );
		}
		$status = $post ? $post->post_status : 'draft';
		$out    = '';
		if ( 'publish' !== $status ) {
			$out .= $button( 'draft', 'Guardar borrador', 'btn-outline-primary' );
			$out .= $button( 'pending', 'Mandar a revisión', 'pending' === $status ? 'btn-outline-warning' : 'btn-warning' );
		}
		if ( current_user_can( 'publish_fmc_designs' ) ) {
			$out .= 'publish' === $status
				? $button( 'publish', 'Guardar', 'btn-primary' ) . $button( 'draft', 'Devolver a borrador', 'btn-outline-secondary' )
				: $button( 'publish', 'Dar por finalizado', 'btn-success' );
		}
		return $out;
	}

	/**
	 * One field.
	 *
	 * @param string               $type  Post type.
	 * @param string               $key   Field key.
	 * @param array<string, mixed> $f     Field definition.
	 * @param mixed                $value Value.
	 * @param bool                 $can   Whether it can be written.
	 * @param bool                 $error Whether it failed validation.
	 * @param bool                 $shown Whether its `show` condition holds now.
	 * @return string
	 */
	private static function field( string $type, string $key, array $f, $value, bool $can, bool $error, bool $shown = true ): string {
		$id       = 'f-' . sanitize_html_class( str_replace( ':', '-', $key ) );
		$name     = 'f[' . $key . ']';
		$disabled = $can ? '' : ' disabled';
		$invalid  = $error ? ' is-invalid' : '';
		$label    = '<label class="form-label fw-semibold" for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . ( ! empty( $f['req'] ) ? ' <span class="text-danger" aria-hidden="true">*</span>' : '' ) . '</label>';
		$help     = ! empty( $f['help'] ) ? '<div class="form-text">' . esc_html( $f['help'] ) . '</div>' : '';
		if ( ! $can && isset( MetaRegistration::guarded()[ $type ][ $key ] ) ) {
			$help .= '<div class="form-text fst-italic">Lo rellena el servicio de formación.</div>';
		}
		// «Añadir ponente nuevo no incluido en la lista», como en el formulario
		// anterior: se abre en otra pestaña y luego se elige aquí.
		if ( $can && ! empty( $f['add'] ) && current_user_can( get_post_type_object( $f['add'] )->cap->create_posts ) ) {
			$help .= '<div class="form-text"><a href="' . esc_url(
				Screen::url(
					array(
						Screen::ARG_NEW   => $f['add'],
						Screen::ARG_FRAME => '',
					)
				)
			) . '" target="_blank" rel="noopener">¿No está en la lista? Añadirlo en otra pestaña</a> y volver a abrir esta ficha.</div>';
		}

		switch ( $f['w'] ) {
			case 'textarea':
			case 'rich':
				$rich  = 'rich' === $f['w'] ? ' data-fmc-rich' : '';
				$input = '<textarea class="form-control' . $invalid . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="' . ( $rich ? 6 : 4 ) . '"' . $rich . $disabled . '>' . esc_textarea( (string) $value ) . '</textarea>';
				break;
			case 'checkbox':
				$input = '<div class="form-check mt-2"><input type="hidden" name="' . esc_attr( $name ) . '" value="0"' . $disabled . '><input class="form-check-input" type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( (bool) $value, true, false ) . $disabled . '><label class="form-check-label" for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . '</label></div>';
				$label = '';
				break;
			case 'select':
			case 'term':
			case 'design':
			case 'adviser':
				$choices = self::choices( $f, $key );
				$current = is_array( $value ) ? (string) ( $value[0] ?? '' ) : (string) $value;
				// Las listas largas llevan buscador (Tom Select); las cortas, el select de siempre.
				$search = count( $choices ) > 10 ? ' data-fmc-ts data-placeholder="Escriba para buscar…"' : '';
				$input  = '<select class="form-select' . $invalid . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $search . $disabled . '><option value="">—</option>';
				foreach ( $choices as $v => $text ) {
					$input .= '<option value="' . esc_attr( (string) $v ) . '"' . selected( $current, (string) $v, false ) . '>' . esc_html( $text ) . '</option>';
				}
				$input .= '</select>';
				break;
			case 'speakers':
			case 'designs':
			case 'terms':
				// Varios de una lista: Tom Select enseña cada elegido como una
				// etiqueta con su «×», y se añaden más escribiendo. El oculto vacío
				// hace que quitarlos todos también se guarde.
				$choices = self::choices( $f, $key );
				$current = array_map( 'strval', array_filter( (array) $value ) );
				// Lo ya elegido se conserva aunque la lista ya no lo ofrezca (un diseño histórico).
				foreach ( array_diff( $current, array_map( 'strval', array_keys( $choices ) ) ) as $kept ) {
					$choices[ $kept ] = get_the_title( (int) $kept ) . ' (fuera del catálogo)';
				}
				$input = '<input type="hidden" name="' . esc_attr( $name ) . '[]" value=""' . $disabled . '><select class="form-select" multiple data-fmc-ts data-placeholder="Escriba para añadir…" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '[]"' . $disabled . '>';
				foreach ( $choices as $v => $text ) {
					$input .= '<option value="' . esc_attr( (string) $v ) . '"' . ( in_array( (string) $v, $current, true ) ? ' selected' : '' ) . '>' . esc_html( $text ) . '</option>';
				}
				$input .= '</select>';
				break;
			case 'radio':
			case 'checks':
				$choices = self::choices( $f, $key );
				$multi   = 'radio' !== $f['w'];
				$current = array_map( 'strval', (array) $value );
				$input   = '<div class="fmc-checks' . ( count( $choices ) > 8 ? ' is-long' : '' ) . '">' . ( $multi ? '<input type="hidden" name="' . esc_attr( $name ) . '[]" value=""' . $disabled . '>' : '' );
				foreach ( $choices as $v => $text ) {
					$cid    = $id . '-' . sanitize_html_class( (string) $v );
					$input .= '<div class="form-check"><input class="form-check-input" type="' . ( $multi ? 'checkbox' : 'radio' ) . '" id="' . esc_attr( $cid ) . '" name="' . esc_attr( $name . ( $multi ? '[]' : '' ) ) . '" value="' . esc_attr( (string) $v ) . '"' . ( in_array( (string) $v, $current, true ) ? ' checked' : '' ) . $disabled . '><label class="form-check-label" for="' . esc_attr( $cid ) . '">' . esc_html( $text ) . '</label></div>';
				}
				$input .= '</div>';
				$label  = '<span class="form-label fw-semibold d-block">' . esc_html( $f['label'] ) . '</span>';
				break;
			default:
				$types = array(
					'number' => 'number',
					'date'   => 'date',
					'url'    => 'url',
					'email'  => 'email',
				);
				$range = 'number' === $f['w'] ? ' step="any" min="' . (int) ( $f['min'] ?? 0 ) . '"' . ( isset( $f['max'] ) ? ' max="' . (int) $f['max'] . '"' : '' ) : '';
				$input = '<input class="form-control' . $invalid . '" type="' . ( $types[ $f['w'] ] ?? 'text' ) . '"' . $range . ' id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_array( $value ) ? '' : (string) $value ) . '"' . $disabled . '>';
		}
		if ( D::DIGCOMP_URL === $key && is_string( $value ) && '' !== $value ) {
			$input .= self::digcomp( $value );
		}
		$show = '';
		foreach ( (array) ( $f['show'] ?? array() ) as $other => $wanted ) {
			$show = ' data-fmc-show="' . esc_attr( 'f[' . $other . ']' ) . '" data-fmc-show-values="' . esc_attr( implode( ',', $wanted ) ) . '"';
		}
		return '<div class="col-12 col-md-' . (int) ( $f['col'] ?? 12 ) . ( $shown ? '' : ' d-none' ) . '"' . $show . '>' . $label . $input . $help . '</div>';
	}

	/**
	 * Whether a field's `show` condition holds, given how to read other fields.
	 *
	 * @param array<string, mixed> $f    Field definition.
	 * @param callable             $read Reads a field value by key.
	 * @return bool
	 */
	public static function shown( array $f, callable $read ): bool {
		foreach ( (array) ( $f['show'] ?? array() ) as $other => $wanted ) {
			if ( ! array_intersect( array_map( 'strval', (array) $read( $other ) ), $wanted ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * The documents section of a design.
	 *
	 * Cada clase es una lista: cada documento se abre, se cambia por otro
	 * («Cambiar» elige el fichero nuevo) o se marca para quitar; y al final,
	 * «Añadir». Todo viaja con el formulario y se aplica al guardar. Los
	 * documentos que solo existían en el sistema anterior salen como enlaces.
	 *
	 * @param \WP_Post|null $post Design.
	 * @param bool          $can  Whether they can be changed.
	 * @return string
	 */
	private static function docs( ?\WP_Post $post, bool $can ): string {
		$current = Documents::of( $post ? $post->ID : 0 );
		$legacy  = array(
			'image'    => D::IMAGE,
			'design'   => D::DESIGN_FILE,
			'sessions' => D::SESSIONS_FILE,
			'support'  => D::SUPPORT_FILE,
		);
		$out     = '<div class="col-12 fmc-docs">';
		foreach ( Documents::kinds() as $kind => $def ) {
			$accept = implode( ',', array_map( static fn( $ext ) => '.' . str_replace( '|', ',.', $ext ), array_keys( $def['mimes'] ) ) );
			$out   .= '<div class="fmc-doc-kind"><div class="d-flex align-items-baseline gap-2 mb-1"><span class="fw-semibold">' . esc_html( $def['label'] ) . '</span><span class="form-text m-0">' . esc_html( $def['help'] ) . '</span></div><ul class="list-group mb-2">';
			foreach ( $current[ $kind ] as $doc ) {
				$file = get_attached_file( $doc->ID );
				$size = $file && file_exists( $file ) ? size_format( (int) filesize( $file ) ) : '';
				$out .= '<li class="list-group-item d-flex align-items-center gap-2 flex-wrap"><a class="me-auto text-break" href="' . esc_url( (string) wp_get_attachment_url( $doc->ID ) ) . '" target="_blank" rel="noopener">' . esc_html( basename( (string) $file ) ) . '</a><span class="text-secondary small">' . esc_html( $size ) . '</span>';
				if ( $can ) {
					$rid  = 'fmc-replace-' . $doc->ID;
					$out .= '<label class="btn btn-sm btn-outline-primary mb-0" for="' . esc_attr( $rid ) . '">Cambiar</label><input class="visually-hidden fmc-file" type="file" id="' . esc_attr( $rid ) . '" name="fmc_replace[' . esc_attr( (string) $doc->ID ) . ']" accept="' . esc_attr( $accept ) . '">';
					$out .= '<input class="btn-check" type="checkbox" id="fmc-remove-' . esc_attr( (string) $doc->ID ) . '" name="fmc_remove[]" value="' . esc_attr( (string) $doc->ID ) . '"><label class="btn btn-sm btn-outline-danger mb-0" for="fmc-remove-' . esc_attr( (string) $doc->ID ) . '">Quitar</label>';
					$out .= '<span class="fmc-file-name small text-primary w-100" data-for="' . esc_attr( $rid ) . '"></span>';
				}
				$out .= '</li>';
			}
			$old = isset( $legacy[ $kind ] ) && $post ? (string) get_post_meta( $post->ID, $legacy[ $kind ], true ) : '';
			if ( '' !== $old ) {
				$out .= '<li class="list-group-item small"><span class="badge text-bg-light me-1">Sistema anterior</span><a href="' . esc_url( $old ) . '" target="_blank" rel="noopener">' . esc_html( rawurldecode( basename( (string) wp_parse_url( $old, PHP_URL_PATH ) ) ) ) . '</a></li>';
			}
			if ( ! $current[ $kind ] && '' === $old ) {
				$out .= '<li class="list-group-item small text-secondary">Ninguno.</li>';
			}
			$out .= '</ul>';
			if ( $can ) {
				$aid  = 'fmc-add-' . $kind;
				$out .= '<div class="mb-3"><label class="btn btn-sm btn-outline-secondary mb-0" for="' . esc_attr( $aid ) . '">' . esc_html( $def['multiple'] ? '+ Añadir' : ( $current[ $kind ] ? 'Cambiar la imagen' : '+ Añadir' ) ) . '</label><input class="visually-hidden fmc-file" type="file" id="' . esc_attr( $aid ) . '" name="fmc_doc[' . esc_attr( $kind ) . '][]" accept="' . esc_attr( $accept ) . '"' . ( $def['multiple'] ? ' multiple' : '' ) . '> <span class="fmc-file-name small text-primary" data-for="' . esc_attr( $aid ) . '"></span></div>';
			}
			$out .= '</div>';
		}
		return $out . '</div>';
	}

	/**
	 * Choices of a field.
	 *
	 * @param array<string, mixed> $f   Field definition.
	 * @param string               $key Field key.
	 * @return array<string|int, string>
	 */
	private static function choices( array $f, string $key ): array {
		if ( isset( $f['choices'] ) ) {
			return $f['choices'];
		}
		if ( 0 === strpos( $key, 'tax:' ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => substr( $key, 4 ),
					'hide_empty' => false,
				)
			);
			$out   = array();
			foreach ( is_array( $terms ) ? $terms : array() as $t ) {
				$out[ $t->term_id ] = $t->name;
			}
			return $out;
		}
		if ( 'adviser' === $f['w'] ) {
			return self::advisers();
		}
		$type  = 'speakers' === $f['w'] ? PostTypes::SPEAKER : PostTypes::DESIGN;
		$query = self::posts_query( $type, ! empty( $f['catalogue'] ) );
		if ( PostTypes::SPEAKER === $type ) {
			return self::speaker_choices( get_posts( $query ), (int) ( $f['design'] ?? 0 ) );
		}
		$out = array();
		foreach ( get_posts( $query ) as $p ) {
			$code          = (string) get_post_meta( $p->ID, D::CODE, true );
			$kind          = D::TYPES[ (string) get_post_meta( $p->ID, D::TYPE, true ) ] ?? '';
			$notes         = array_filter( array( $kind, 'publish' !== $p->post_status ? Lists::DESIGN_STATUSES[ $p->post_status ][0] ?? '' : '' ) );
			$out[ $p->ID ] = ( '' !== $code ? $code . ' · ' : '' ) . get_the_title( $p ) . ( $notes ? ' (' . implode( ', ', $notes ) . ')' : '' );
		}
		return $out;
	}

	/**
	 * The people who can be the adviser of an action.
	 *
	 * @return array<int, string>
	 */
	private static function advisers(): array {
		$out = array();
		foreach ( get_users(
			array(
				'role__in' => array( 'fmc_adviser', 'fmc_curator' ),
				'orderby'  => 'display_name',
				'fields'   => array( 'ID', 'display_name' ),
			)
		) as $u ) {
			$out[ (int) $u->ID ] = $u->display_name;
		}
		return $out;
	}

	/**
	 * The query of the posts a field chooses from.
	 *
	 * @param string $type      Post type.
	 * @param bool   $catalogue Only designs in the public catalogue.
	 * @return array<string, mixed>
	 */
	private static function posts_query( string $type, bool $catalogue ): array {
		$query = array(
			'post_type'        => $type,
			'post_status'      => PostTypes::DESIGN === $type && ! $catalogue ? array( 'publish', 'pending', 'draft' ) : 'publish',
			'posts_per_page'   => -1,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'fmc_visible'      => ! current_user_can( 'edit_others_' . $type . 's' ),
			'suppress_filters' => false,
			'no_found_rows'    => true,
		);
		if ( $catalogue ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- solo los diseños del catálogo.
			$query['meta_query'] = array(
				array(
					'key'   => D::IN_CATALOGUE,
					'value' => '1',
				),
			);
		}
		return $query;
	}

	/**
	 * Speakers to choose from, with what helps to choose: island, actions
	 * given, and a star when recommended for the action's design.
	 *
	 * @param \WP_Post[] $speakers Speakers.
	 * @param int        $design   Design of the action, if any.
	 * @return array<int, string>
	 */
	private static function speaker_choices( array $speakers, int $design ): array {
		$stats       = Speakers::stats();
		$recommended = array_flip( Speakers::recommended_for( $design ) );
		$out         = array();
		foreach ( $speakers as $p ) {
			$given         = (int) ( $stats[ $p->ID ]['actions'] ?? 0 );
			$details       = array_filter( array( (string) get_post_meta( $p->ID, S::ZONE, true ), $given ? sprintf( '%d acción(es)', $given ) : '' ) );
			$out[ $p->ID ] = ( isset( $recommended[ $p->ID ] ) ? '★ ' : '' ) . get_the_title( $p ) . ( $details ? ' · ' . implode( ' · ', $details ) : '' );
		}
		// Los recomendados, primero.
		uksort(
			$out,
			static function ( $a, $b ) use ( $out, $recommended ) {
				$first = isset( $recommended[ $b ] ) <=> isset( $recommended[ $a ] );
				return 0 !== $first ? $first : strcmp( $out[ $a ], $out[ $b ] );
			}
		);
		return $out;
	}

	/**
	 * The summary of a digital-competence profile: competences and levels.
	 *
	 * @param string $url Profile URL.
	 * @return string
	 */
	public static function digcomp( string $url ): string {
		$summary = DigComp::summary( $url );
		if ( array() === $summary ) {
			return '<div class="form-text text-warning">La dirección no trae ninguna competencia que se pueda leer.</div>';
		}
		$out = '<div class="fmc-digcomp card card-body mt-2"><div class="d-flex align-items-center mb-2"><strong class="me-auto">Resumen del perfil</strong><a class="btn btn-sm btn-outline-primary" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">Abrir en la herramienta</a></div><table class="table table-sm mb-0"><thead><tr><th scope="col">Competencia</th><th scope="col">Niveles (indicadores)</th></tr></thead><tbody>';
		foreach ( $summary as $code => $levels ) {
			$cells = array();
			foreach ( $levels as $level => $n ) {
				$cells[] = '<span class="badge text-bg-light border">' . esc_html( $level ) . ' · ' . (int) $n . '</span>';
			}
			$out .= '<tr><td><strong>' . esc_html( $code ) . '</strong> ' . esc_html( DigComp::COMPETENCES[ $code ] ?? '' ) . '</td><td>' . implode( ' ', $cells ) . '</td></tr>';
		}
		return $out . '</tbody></table></div>';
	}

	/**
	 * Totals and school year of an action, computed.
	 *
	 * @param \WP_Post $post Action.
	 * @return string
	 */
	private static function action_summary( \WP_Post $post ): string {
		$m        = static fn( string $k ): float => (float) get_post_meta( $post->ID, $k, true );
		$hours    = $m( A::HOURS_ONSITE ) + $m( A::HOURS_ONLINE );
		$replicas = max( 1, (int) $m( A::REPLICAS ) );
		// Las cifras de matrícula y certificación las lleva el servicio de formación (ADR-0010).
		$items = array(
			'Curso escolar' => \Fmc\Domain\SchoolYear::of( (string) get_post_meta( $post->ID, A::START, true ) ),
			'Horas totales' => $hours ? ( $hours + 0 ) . ' h' . ( $replicas > 1 ? ' (' . ( $hours * $replicas + 0 ) . ' h con el desdoble)' : '' ) : '',
			'Ponentes'      => (string) count( array_filter( (array) get_post_meta( $post->ID, A::SPEAKERS, true ) ) ),
		);
		$out   = '<div class="fmc-counters mb-4">';
		foreach ( $items as $label => $value ) {
			$out .= '<div class="fmc-counter"><strong>' . esc_html( '' !== $value ? $value : '—' ) . '</strong><span>' . esc_html( $label ) . '</span></div>';
		}
		return $out . '</div>';
	}

	/**
	 * The incidents of an action, and the button to open one.
	 *
	 * @param \WP_Post $post Action.
	 * @return string
	 */
	private static function incidents( \WP_Post $post ): string {
		$list = get_posts(
			array(
				'post_type'      => PostTypes::INCIDENT,
				'post_parent'    => $post->ID,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$out  = '<section class="card fmc-section mt-2"><div class="card-body"><div class="d-flex align-items-center mb-2"><h2 class="h5 me-auto mb-0">Incidencias</h2>';
		if ( current_user_can( 'edit_post', $post->ID ) && current_user_can( 'edit_fmc_incidents' ) ) {
			$out .= '<a class="btn btn-outline-primary btn-sm" href="' . esc_url(
				Screen::url(
					array(
						Screen::ARG_NEW    => PostTypes::INCIDENT,
						Screen::ARG_PARENT => $post->ID,
					)
				)
			) . '">Abrir una incidencia</a>';
		}
		$out .= '</div>';
		if ( array() === $list ) {
			return $out . '<p class="text-secondary mb-0">Ninguna.</p></div></section>';
		}
		$out .= '<ul class="list-group list-group-flush">';
		foreach ( $list as $incident ) {
			$res  = (string) get_post_meta( $incident->ID, I::RESOLUTION, true );
			$out .= '<li class="list-group-item d-flex gap-2"><a href="' . esc_url( Screen::url( array( Screen::ARG_EDIT => $incident->ID ) ) ) . '">' . esc_html( get_the_date( 'd/m/Y', $incident ) ) . '</a><span class="text-truncate">' . esc_html( wp_trim_words( (string) get_post_meta( $incident->ID, I::REASON, true ), 14 ) ) . '</span><span class="ms-auto badge text-bg-light">' . esc_html( I::RESOLUTIONS[ $res ] ?? 'Pendiente' ) . '</span></li>';
		}
		return $out . '</ul></div></section>';
	}

	/**
	 * Handle the POST of the edit screen.
	 *
	 * @return void
	 */
	public static function save(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verificado justo debajo.
		$type = isset( $_POST['fmc_type'] ) ? sanitize_key( wp_unslash( $_POST['fmc_type'] ) ) : '';
		$id   = isset( $_POST['fmc_id'] ) ? absint( $_POST['fmc_id'] ) : 0;
		// phpcs:enable
		if ( ! isset( PostTypes::definitions()[ $type ] ) ) {
			Screen::leave( Screen::url( array( Screen::ARG_NOTICE => 'denied' ) ) );
		}
		check_admin_referer( self::nonce_action( $type, $id ), '_fmc_nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cada campo se sanea con el saneado de su tipo al guardarlo.
		$raw    = isset( $_POST['f'] ) && is_array( $_POST['f'] ) ? wp_unslash( $_POST['f'] ) : array();
		$status = isset( $_POST['fmc_status'] ) ? sanitize_key( wp_unslash( $_POST['fmc_status'] ) ) : '';
		$parent = isset( $_POST['fmc_parent'] ) ? absint( $_POST['fmc_parent'] ) : 0;

		$files  = Documents::from_request( $_FILES );
		$remove = isset( $_POST['fmc_remove'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['fmc_remove'] ) ) : array();
		$result = self::apply( $type, $id, $raw, $status, $parent, $files, $remove );
		if ( 'denied' === $result['outcome'] ) {
			Screen::leave( Screen::url( array( Screen::ARG_NOTICE => 'denied' ) ) );
		}
		if ( 'invalid' === $result['outcome'] ) {
			if ( $files ) {
				$result['errors']['_files'] = 'Los documentos elegidos no se han subido: vuelva a elegirlos cuando corrija lo anterior.';
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- document() escapa.
			echo Screen::document( self::render( $type, $id ? get_post( $id ) : null, $result['values'], $result['errors'] ) );
			exit;
		}
		$id = $result['id'];
		if ( $result['problems'] ) {
			set_transient( 'fmc_problems_' . get_current_user_id(), $result['problems'], MINUTE_IN_SECONDS );
		}

		Screen::leave(
			Screen::url(
				array(
					Screen::ARG_EDIT   => $id,
					Screen::ARG_NOTICE => $result['outcome'],
				)
			)
		);
	}

	/**
	 * Validate and store a submission, field by field, as the current user.
	 *
	 * Sin nonce ni redirecciones: eso es de {@see save()}. Aquí solo se decide
	 * qué se guarda, y es lo que prueban los tests.
	 *
	 * @param string               $type   Post type.
	 * @param int                  $id     Post ID; 0 when new.
	 * @param array<string, mixed> $raw    Submitted `f[...]` values.
	 * @param string               $status Status button pressed.
	 * @param int                  $parent_id Parent action of a new incident.
	 * @param array<string, mixed> $files  Uploads, as `Documents::from_request()` returns them.
	 * @param int[]                $remove Documents to remove.
	 * @return array{outcome:string, id:int, values:array<string, mixed>, errors:array<string, string>, problems:string[]}
	 */
	public static function apply( string $type, int $id, array $raw, string $status, int $parent_id, array $files = array(), array $remove = array() ): array {
		$denied = array(
			'outcome'  => 'denied',
			'id'       => $id,
			'values'   => array(),
			'errors'   => array(),
			'problems' => array(),
		);
		if ( ! isset( PostTypes::definitions()[ $type ] ) ) {
			return $denied;
		}
		$post = $id ? get_post( $id ) : null;
		if ( $id && ( ! $post instanceof \WP_Post || $post->post_type !== $type ) ) {
			return $denied;
		}

		$fields   = Fields::all( $type );
		$values   = array();
		$errors   = array();
		$writable = array();
		foreach ( $fields as $key => $f ) {
			if ( 'docs' === $f['w'] || ! self::can_write( $type, $key, $id ) ) {
				continue;
			}
			$writable[ $key ] = true;
			$value            = $raw[ $key ] ?? ( in_array( $f['w'], array( 'checkbox' ), true ) ? '0' : '' );
			if ( is_array( $value ) ) {
				$value = array_values( array_filter( array_map( 'sanitize_text_field', $value ), 'strlen' ) );
			} else {
				$value = in_array( $f['w'], array( 'textarea', 'rich' ), true ) ? (string) $value : sanitize_text_field( (string) $value );
			}
			$values[ $key ] = $value;
		}

		// Lo que se deja en blanco en la acción se toma del diseño, como hacía el
		// formulario anterior al elegir la acción formativa.
		if ( PostTypes::ACTION === $type && ! empty( $values[ A::DESIGN_ID ] ) ) {
			$design = (int) $values[ A::DESIGN_ID ];
			if ( isset( $values[ A::MODALITY ] ) && '' === $values[ A::MODALITY ] ) {
				$values[ A::MODALITY ] = (string) get_post_meta( $design, D::MODALITY, true );
			}
			if ( isset( $values[ A::HOURS_ONSITE ], $values[ A::HOURS_ONLINE ] ) && '' === $values[ A::HOURS_ONSITE ] . $values[ A::HOURS_ONLINE ] ) {
				$values[ A::HOURS_ONSITE ] = (string) get_post_meta( $design, D::HOURS_ONSITE, true );
				$values[ A::HOURS_ONLINE ] = (string) get_post_meta( $design, D::HOURS_ONLINE, true );
			}
		}

		// Las reglas se miran con los valores ya reunidos: `show` depende de otro campo.
		$read = static function ( string $k ) use ( &$values, $post ) {
			return array_key_exists( $k, $values ) ? $values[ $k ] : self::value( $post, $k );
		};
		foreach ( $values as $key => $value ) {
			$f = $fields[ $key ];
			if ( ! self::shown( $f, $read ) ) {
				// Oculto por la regla: no cuenta y se guarda vacío.
				$values[ $key ] = is_array( $value ) ? array() : '';
				continue;
			}
			$empty = '' === $value || array() === $value;
			if ( ! empty( $f['req'] ) && $empty && ! ( 'post_title' === $key && PostTypes::ACTION === $type ) ) {
				$errors[ $key ] = 'Falta rellenar «' . $f['label'] . '».';
				continue;
			}
			if ( ! $empty && 'number' === $f['w'] && ( ( isset( $f['min'] ) && (float) $value < $f['min'] ) || ( isset( $f['max'] ) && (float) $value > $f['max'] ) ) ) {
				$errors[ $key ] = '«' . $f['label'] . '» tiene que estar entre ' . (int) ( $f['min'] ?? 0 ) . ' y ' . (int) $f['max'] . '.';
				continue;
			}
			if ( ! $empty && ! empty( $f['unique'] ) && self::taken( $type, $key, (string) $value, $id ) ) {
				$errors[ $key ] = 'Ya hay otro con ese valor en «' . $f['label'] . '».';
			}
		}

		if ( array() === $writable ) {
			return $denied;
		}
		if ( PostTypes::INCIDENT === $type && ! $id && ( PostTypes::ACTION !== get_post_type( $parent_id ) || ! current_user_can( 'edit_post', $parent_id ) ) ) {
			return $denied;
		}
		if ( $errors ) {
			$values['_parent'] = $parent_id;
			return array(
				'outcome'  => 'invalid',
				'id'       => $id,
				'values'   => $values,
				'errors'   => $errors,
				'problems' => array(),
			);
		}

		$postarr = array( 'post_type' => $type );
		if ( $post ) {
			$postarr['ID'] = $post->ID;
		}
		if ( isset( $writable['post_title'] ) ) {
			$postarr['post_title'] = (string) $values['post_title'];
		}
		// La acción lleva el título de su diseño: al crearla y al cambiarle el
		// diseño. Las antiguas con un título propio lo conservan (ADR-0009).
		if ( PostTypes::ACTION === $type && ! empty( $values[ A::DESIGN_ID ] ) && ( ! $post || '' === trim( $post->post_title ) || (int) get_post_meta( $post->ID, A::DESIGN_ID, true ) !== (int) $values[ A::DESIGN_ID ] ) ) {
			$postarr['post_title'] = get_the_title( (int) $values[ A::DESIGN_ID ] );
		}
		// El código del diseño lo pone el aplicativo, la primera vez que se guarda.
		if ( PostTypes::DESIGN === $type && ( ! $post || '' === (string) get_post_meta( $post->ID, D::CODE, true ) ) ) {
			$values[ D::CODE ] = self::next_code();
		}
		if ( PostTypes::SPEAKER === $type && isset( $values['fmc_last_name'] ) ) {
			$postarr['post_title'] = trim( $values['fmc_last_name'] . ', ' . $values['fmc_first_name'], ', ' );
		}
		if ( isset( $writable['post_content'] ) ) {
			$postarr['post_content'] = wp_kses_post( (string) $values['post_content'] );
		}
		$postarr['post_status'] = self::next_status( $type, $post, $status );
		if ( ! $post && PostTypes::INCIDENT === $type ) {
			$postarr['post_parent'] = $parent_id;
			// La incidencia se llama como su acción: así se lee en cualquier lista.
			$postarr['post_title'] = get_the_title( $parent_id );
		}

		$can_edit_post = $post ? current_user_can( 'edit_post', $post->ID ) : true;
		if ( $can_edit_post ) {
			$result = $post ? wp_update_post( $postarr, true ) : wp_insert_post( $postarr, true );
			if ( is_wp_error( $result ) ) {
				return $denied;
			}
			$id = (int) $result;
		}

		foreach ( $values as $key => $value ) {
			if ( in_array( $key, array( 'post_title', 'post_content', '_parent' ), true ) ) {
				continue;
			}
			if ( 0 === strpos( $key, 'tax:' ) ) {
				if ( $can_edit_post ) {
					wp_set_object_terms( $id, array_map( 'intval', (array) $value ), substr( $key, 4 ) );
				}
				continue;
			}
			// Vacío es «sin dato», no un cero ni una cadena vacía guardada.
			if ( '' === $value || array() === $value ) {
				delete_post_meta( $id, $key );
				continue;
			}
			update_post_meta( $id, $key, $value );
		}
		if ( ! $post && PostTypes::INCIDENT === $type && ! isset( $values[ I::RESOLUTION ] ) ) {
			update_post_meta( $id, I::RESOLUTION, 'pending' );
		}

		$problems = array();
		if ( PostTypes::DESIGN === $type && ( $files || $remove ) ) {
			$problems = Documents::apply( $id, $files, $remove );
		}

		return array(
			'outcome'  => $post ? 'saved' : 'created',
			'id'       => $id,
			'values'   => $values,
			'errors'   => array(),
			'problems' => $problems,
		);
	}

	/**
	 * Whether another post of the type already holds a value.
	 *
	 * @param string $type  Post type.
	 * @param string $key   Field key: `post_title` or a meta key.
	 * @param string $value Value.
	 * @param int    $id    Post being saved, which does not count.
	 * @return bool
	 */
	public static function taken( string $type, string $key, string $value, int $id ): bool {
		$args = array(
			'post_type'      => $type,
			'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'post__not_in'   => array( $id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- una sola fila.
		);
		if ( 'post_title' === $key ) {
			$args['title'] = $value;
		} else {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- comprobar que el valor no se repite.
				array(
					'key'   => $key,
					'value' => $value,
				),
			);
		}
		return array() !== get_posts( $args );
	}

	/**
	 * The status a save leaves the post in.
	 *
	 * @param string        $type      Post type.
	 * @param \WP_Post|null $post      Post, or null when new.
	 * @param string        $requested Status the button asked for.
	 * @return string
	 */
	public static function next_status( string $type, ?\WP_Post $post, string $requested ): string {
		$current = $post ? $post->post_status : '';
		if ( PostTypes::DESIGN === $type ) {
			if ( 'publish' === $requested && ! current_user_can( 'publish_fmc_designs' ) ) {
				return '' !== $current ? $current : 'draft';
			}
			return in_array( $requested, array( 'draft', 'pending', 'publish' ), true ) ? $requested : ( '' !== $current ? $current : 'draft' );
		}
		if ( '' !== $current ) {
			return $current;
		}
		return current_user_can( get_post_type_object( $type )->cap->publish_posts ) ? 'publish' : 'draft';
	}
}

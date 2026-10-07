<?php
/**
 * Tests for meta sanitising and field-level write permissions.
 *
 * @package Fmc
 */

use Fmc\Meta\ActionMetaKeys;
use Fmc\Meta\DesignMetaKeys;
use Fmc\Meta\IncidentMetaKeys;
use Fmc\Meta\MetaRegistration;
use Fmc\Meta\MetaTypes;
use Fmc\PostType\PostTypes;

/**
 * Un campo que no te toca no se guarda aunque lo mandes (ADR-0006).
 */
class Test_Meta extends WP_UnitTestCase {

	/**
	 * Roles and caps in place.
	 */
	public function set_up() {
		parent::set_up();
		fmc_register_roles();
		PostTypes::grant_caps_to_roles();
		// La suite limpia las metas registradas entre test y test.
		MetaRegistration::register_meta();
	}

	/**
	 * Cada tipo sanea lo suyo, y una fecha imposible se queda vacía.
	 */
	public function test_each_type_sanitises_its_values() {
		$this->assertSame( '2026-02-28', MetaTypes::sanitize( MetaTypes::DATE, '2026-02-28' ) );
		$this->assertSame( '', MetaTypes::sanitize( MetaTypes::DATE, '2026-02-30' ) );
		$this->assertSame( '', MetaTypes::sanitize( MetaTypes::DATE, '30/09/2026' ) );
		$this->assertSame( 2.5, MetaTypes::sanitize( MetaTypes::NUMBER, '2,5' ) );
		$this->assertSame( 0, MetaTypes::sanitize( MetaTypes::INT, '-3' ) );
		$this->assertSame( array( 3, 7 ), MetaTypes::sanitize( MetaTypes::IDS, array( '3', 'x', 7, 3 ) ) );
		$this->assertSame( '', MetaTypes::sanitize( MetaTypes::URL, 'javascript:alert(1)' ) );
		$this->assertStringNotContainsString( '<script', MetaTypes::sanitize( MetaTypes::HTML, '<p>a</p><script>x</script>' ) );
	}

	/**
	 * Un valor fuera de su lista cerrada se guarda vacío.
	 */
	public function test_closed_lists_reject_unknown_values() {
		$this->assertSame( 'blended', MetaRegistration::sanitize( PostTypes::DESIGN, DesignMetaKeys::MODALITY, 'blended' ) );
		$this->assertSame( '', MetaRegistration::sanitize( PostTypes::DESIGN, DesignMetaKeys::MODALITY, 'mixta' ) );
		$this->assertSame( '', MetaRegistration::sanitize( PostTypes::ACTION, ActionMetaKeys::SITUATION, 'lo que sea' ) );
	}

	/**
	 * La dueña de la acción no escribe el número de expediente; el servicio sí.
	 */
	public function test_the_file_number_is_only_written_by_the_training_service() {
		$owner  = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$action = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::ACTION,
				'post_author' => $owner,
			)
		);

		wp_set_current_user( $owner );
		$this->assertTrue( MetaRegistration::can_write( PostTypes::ACTION, ActionMetaKeys::PLACES, $action ) );
		$this->assertFalse( MetaRegistration::can_write( PostTypes::ACTION, ActionMetaKeys::FILE_NUMBER, $action ) );
		$this->assertFalse( current_user_can( 'edit_post_meta', $action, ActionMetaKeys::FILE_NUMBER ) );

		// El servicio no edita la acción, así que el `edit_post_meta` del núcleo
		// —que exige primero `edit_post`— le dice que no: su pantalla pregunta a
		// `can_write()`, que es la regla del campo (ADR-0006).
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'fmc_training_service' ) ) );
		$this->assertTrue( MetaRegistration::can_write( PostTypes::ACTION, ActionMetaKeys::FILE_NUMBER, $action ) );
	}

	/**
	 * Quien abre una incidencia no se la aprueba, y una vez enviada ya no la edita.
	 */
	public function test_an_adviser_cannot_resolve_an_incident() {
		$adviser  = self::factory()->user->create( array( 'role' => 'fmc_adviser' ) );
		$incident = self::factory()->post->create(
			array(
				'post_type'   => PostTypes::INCIDENT,
				'post_status' => 'draft',
				'post_author' => $adviser,
			)
		);

		wp_set_current_user( $adviser );
		$this->assertTrue( MetaRegistration::can_write( PostTypes::INCIDENT, IncidentMetaKeys::REASON, $incident ) );
		$this->assertFalse( MetaRegistration::can_write( PostTypes::INCIDENT, IncidentMetaKeys::RESOLUTION, $incident ) );

		wp_publish_post( $incident );
		$this->assertFalse( MetaRegistration::can_write( PostTypes::INCIDENT, IncidentMetaKeys::REASON, $incident ) );
	}

	/**
	 * Todas las claves llevan el prefijo y están registradas.
	 */
	public function test_every_key_is_prefixed_and_registered() {
		foreach ( MetaRegistration::maps() as $type => $map ) {
			foreach ( array_keys( $map ) as $key ) {
				$this->assertStringStartsWith( 'fmc_', $key );
				$this->assertTrue( registered_meta_key_exists( 'post', $key, $type ), $type . ' ' . $key );
			}
		}
	}
}

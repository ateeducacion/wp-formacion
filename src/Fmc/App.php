<?php
/**
 * Application bootstrap for the training app.
 *
 * @package Fmc
 */

namespace Fmc;

use Fmc\Meta\MetaRegistration;
use Fmc\PostType\PostTypes;
use Fmc\PublicFront\Lists;
use Fmc\PublicFront\Screen;
use Fmc\Taxonomy\Taxonomies;

/**
 * Wires hooks for the modular training application.
 *
 * Se llama `App` y no `Plugin` a propósito: el validador de Code Snippets
 * compara los nombres declarados ignorando el namespace, así que un
 * `Fmc\Plugin` chocaría con el `Code_Snippets\Plugin` del propio plugin y el
 * snippet se rechazaría con «code did not pass validation».
 */
final class App {

	/**
	 * Boot the application (idempotent).
	 *
	 * @return void
	 */
	public static function boot(): void {
		static $booted = false;
		if ( $booted ) {
			return;
		}
		$booted = true;

		add_action( 'init', array( Taxonomies::class, 'register' ), 9 );
		add_action( 'init', array( PostTypes::class, 'register' ), 10 );
		add_action( 'init', array( PostTypes::class, 'grant_caps_to_roles' ), 11 );

		MetaRegistration::register();

		// La portada del sitio es el aplicativo: sin sesión, al acceso.
		Lists::register();
		Screen::register();
	}
}

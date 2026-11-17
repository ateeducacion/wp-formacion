<?php
/**
 * The help screen: how the application is used, step by step, with screenshots.
 *
 * @package Fmc
 */

namespace Fmc\PublicFront;

/**
 * La ayuda es una pestaña más del aplicativo, no un manual aparte (issue #75).
 *
 * El texto y las capturas no viajan en el bundle: los trae su propio snippet,
 * «FMC — Ayuda», que genera `make bundle` desde `assets/help/` con las
 * imágenes dentro, y que define `fmc_help_html()`. Si ese snippet no está
 * desplegado, la pestaña no sale y nada se rompe, como con cualquier snippet
 * suelto (ADR-0026).
 */
final class Help {

	/**
	 * `?fmc_ayuda=1`: the help screen.
	 */
	public const ARG = 'fmc_ayuda';

	/**
	 * Tab key of the help screen in the header.
	 */
	public const TAB = 'help';

	/**
	 * Name of the tab and title of the screen.
	 */
	public const TITLE = 'Ayuda';

	/**
	 * Whether the help snippet is deployed.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return function_exists( 'fmc_help_html' );
	}

	/**
	 * Whether this request asks for the help screen.
	 *
	 * @return bool
	 */
	public static function asked(): bool {
		return '' !== Lists::arg( self::ARG );
	}

	/**
	 * URL of the help screen.
	 *
	 * @return string
	 */
	public static function url(): string {
		return Screen::url( array( self::ARG => '1' ) );
	}

	/**
	 * The help screen.
	 *
	 * @return array{tab:string, title:string, subtitle:string, body:string}
	 */
	public static function screen(): array {
		return array(
			'tab'      => self::TAB,
			'title'    => self::TITLE,
			'subtitle' => 'Cómo se usa el planificador, paso a paso.',
			'body'     => self::available() ? '<div class="fmc-help">' . (string) \fmc_help_html() . '</div>' : '',
		);
	}

	/**
	 * The help tab, at the end of the header tabs. Nada si no hay ayuda desplegada.
	 *
	 * @param bool $active Whether the help screen is the current one.
	 * @return string
	 */
	public static function tab( bool $active ): string {
		if ( ! self::available() ) {
			return '';
		}
		return '<li class="nav-item ms-auto"><a class="nav-link' . ( $active ? ' active' : '' ) . '"' . ( $active ? ' aria-current="page"' : '' ) . ' href="' . esc_url( self::url() ) . '"><i class="bi bi-question-circle me-1" aria-hidden="true"></i>' . esc_html( self::TITLE ) . '</a></li>';
	}
}

<?php
/**
 * The digital-competence profile of a design, read from its tool URL.
 *
 * @package Fmc
 */

namespace Fmc\Domain;

/**
 * Lo que dice el perfil de competencia digital, sin salir del aplicativo.
 *
 * La herramienta elidd, del marco de referencia de la competencia digital
 * docente, comparte la selección como una dirección con `?c=` y los
 * indicadores separados por `|`: `1.1.A1.2` es la competencia 1.1, nivel A1,
 * indicador 2. De ahí sale el resumen que se pinta en la ficha del diseño
 * —qué competencias y en qué niveles—, la misma tabla que elidd enseña al
 * generar. Puro: no toca WordPress.
 */
final class DigComp {

	/**
	 * Where profiles are chosen: elidd. A deployment may point elsewhere,
	 * to its own copy, with the `fmc_digcomp_tool` filter.
	 */
	public const TOOL = 'https://elidd.ernesto.es/';

	/**
	 * Areas of the framework, by number.
	 */
	public const AREAS = array(
		1 => 'Compromiso profesional',
		2 => 'Contenidos digitales',
		3 => 'Enseñanza y aprendizaje',
		4 => 'Evaluación y retroalimentación',
		5 => 'Empoderamiento del alumnado',
		6 => 'Desarrollo de la competencia digital del alumnado',
	);

	/**
	 * Competences of the teachers' digital-competence reference framework.
	 */
	public const COMPETENCES = array(
		'1.1' => 'Comunicación organizativa',
		'1.2' => 'Participación, colaboración y coordinación profesional',
		'1.3' => 'Práctica reflexiva',
		'1.4' => 'Desarrollo profesional digital continuo',
		'1.5' => 'Protección de datos personales, privacidad, seguridad y bienestar digital',
		'2.1' => 'Búsqueda y selección de contenidos digitales',
		'2.2' => 'Creación y modificación de contenidos digitales',
		'2.3' => 'Protección, gestión y compartición de contenidos digitales',
		'3.1' => 'Enseñanza',
		'3.2' => 'Orientación y apoyo en el aprendizaje',
		'3.3' => 'Aprendizaje entre iguales',
		'3.4' => 'Aprendizaje autorregulado',
		'4.1' => 'Estrategias de evaluación',
		'4.2' => 'Analíticas y evidencias de aprendizaje',
		'4.3' => 'Retroalimentación, programación y toma de decisiones',
		'5.1' => 'Accesibilidad e inclusión',
		'5.2' => 'Atención a las diferencias personales en el aprendizaje',
		'5.3' => 'Compromiso activo del alumnado con su propio aprendizaje',
		'6.1' => 'Alfabetización mediática y en el tratamiento de la información y de los datos',
		'6.2' => 'Comunicación, colaboración y ciudadanía digital',
		'6.3' => 'Creación de contenidos digitales',
		'6.4' => 'Uso responsable y bienestar digital',
		'6.5' => 'Resolución de problemas',
	);

	/**
	 * Competences and levels of a profile URL, in framework order.
	 *
	 * @param string $url Profile URL.
	 * @return array<string, array<string, int>> competence => level => indicators.
	 */
	public static function summary( string $url ): array {
		$query = (string) parse_url( $url, PHP_URL_QUERY ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pura, sin WordPress.
		parse_str( $query, $args );
		$out = array();
		foreach ( explode( '|', is_string( $args['c'] ?? null ) ? $args['c'] : '' ) as $item ) {
			if ( preg_match( '/^(\d\.\d)\.([ABC][12])\.\d+$/', trim( $item ), $m ) ) {
				$out[ $m[1] ][ $m[2] ] = ( $out[ $m[1] ][ $m[2] ] ?? 0 ) + 1;
			}
		}
		ksort( $out );
		foreach ( $out as &$levels ) {
			ksort( $levels );
		}
		return $out;
	}
}

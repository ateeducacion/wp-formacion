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
		$out = array();
		foreach ( self::indicators( $url ) as $item ) {
			list( $area, $competence, $level ) = explode( '.', $item );
			$code                              = $area . '.' . $competence;
			$out[ $code ][ $level ]            = ( $out[ $code ][ $level ] ?? 0 ) + 1;
		}
		ksort( $out );
		foreach ( $out as &$levels ) {
			ksort( $levels );
		}
		return $out;
	}

	/**
	 * The profile of a URL by areas: every competence of the framework with
	 * the highest level marked, or '' when the profile leaves it out.
	 *
	 * Es el resumen ya hecho, para quien lo pinta sin conocer el marco: otra
	 * web, con los datos del catálogo (ADR-0028).
	 *
	 * @param string $url Profile URL.
	 * @return array<int, array{area:int, name:string, competences:array<int, array{code:string, name:string, level:string, levels:array<string, int>}>}> Empty when the URL brings no competence.
	 */
	public static function profile( string $url ): array {
		$summary = self::summary( $url );
		if ( array() === $summary ) {
			return array();
		}
		$out = array();
		foreach ( self::AREAS as $area => $name ) {
			$competences = array();
			foreach ( self::COMPETENCES as $code => $title ) {
				if ( (int) $code !== $area ) {
					continue;
				}
				$levels        = $summary[ $code ] ?? array();
				$competences[] = array(
					'code'   => (string) $code,
					'name'   => $title,
					'level'  => (string) array_key_last( $levels ),
					'levels' => $levels,
				);
			}
			$out[] = array(
				'area'        => $area,
				'name'        => $name,
				'competences' => $competences,
			);
		}
		return $out;
	}

	/**
	 * The profile of a URL as a link to the tool, with the same selection.
	 *
	 * Lo que cuenta de un perfil es su selección, no dónde se guardó: un
	 * diseño puede traer la dirección de otra copia de la herramienta, o la de
	 * una página que ya no existe. Sin selección, se deja como está.
	 *
	 * @param string $url  Profile URL, as stored.
	 * @param string $tool Where profiles are opened.
	 * @return string
	 */
	public static function tool_link( string $url, string $tool ): string {
		$selection = self::indicators( $url );
		return array() === $selection ? $url : rtrim( $tool, '/' ) . '/?c=' . implode( '|', $selection );
	}

	/**
	 * The indicators a profile URL selects, as it lists them.
	 *
	 * @param string $url Profile URL.
	 * @return string[]
	 */
	private static function indicators( string $url ): array {
		$query = (string) parse_url( $url, PHP_URL_QUERY ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pura, sin WordPress.
		parse_str( $query, $args );
		$items = array_map( 'trim', explode( '|', is_string( $args['c'] ?? null ) ? $args['c'] : '' ) );
		return array_values( array_filter( $items, static fn( $item ) => 1 === preg_match( '/^\d\.\d\.[ABC][12]\.\d+$/', $item ) ) );
	}
}

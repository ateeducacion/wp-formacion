<?php
/**
 * What the editor shows for each type: sections, fields, labels and help.
 *
 * @package Fmc
 */

namespace Fmc\PublicFront;

use Fmc\Meta\ActionMetaKeys as A;
use Fmc\Meta\DesignMetaKeys as D;
use Fmc\Meta\IncidentMetaKeys as I;
use Fmc\Meta\SpeakerMetaKeys as S;
use Fmc\PostType\PostTypes;

/**
 * La pantalla de edición de cada tipo, escrita como datos.
 *
 * Una sección es una tarjeta, como en el taller de eventos; un campo lleva su
 * etiqueta, su ayuda y su «widget». Quién puede escribir cada campo **no** se
 * decide aquí: lo dice `MetaRegistration::can_write()`, y el editor pinta de
 * solo lectura lo que no te toca (ADR-0006).
 *
 * Claves especiales: `post_title`, `post_content`, `tax:<taxonomía>`, `docs`.
 * Un campo con `auto` lo rellena el aplicativo: sale de solo lectura para todos.
 * Widgets: text, textarea, number, date, url, email, checkbox, select, radio,
 * checks (varios de una lista), terms, term, design, speakers, adviser, docs.
 *
 * Las reglas del formulario anterior van aparte, en {@see rules()}: `min` y
 * `max`, `unique` (no se repite en otro del mismo tipo) y `show` (el campo solo
 * cuenta si otro tiene uno de esos valores; si no, se oculta y se guarda vacío).
 * Un campo con `req` y `show` es obligatorio solo cuando se ve. `active`, en
 * una lista de diseños, ofrece solo los finalizados (ADR-0016). `closed`, en
 * una lista que da el despliegue, no admite un valor de fuera de ella, salvo el
 * que ya estaba guardado.
 */
final class Fields {

	/**
	 * Sections of a type.
	 *
	 * @param string $type Post type.
	 * @return list<array{title:string, help:string, fields:array<string, array<string, mixed>>}>
	 */
	public static function sections( string $type ): array {
		$builders = array(
			PostTypes::DESIGN   => 'design',
			PostTypes::ACTION   => 'action',
			PostTypes::SPEAKER  => 'speaker',
			PostTypes::INCIDENT => 'incident',
		);
		if ( ! isset( $builders[ $type ] ) ) {
			return array();
		}
		$sections = call_user_func( array( self::class, $builders[ $type ] ) );
		$rules    = self::rules()[ $type ] ?? array();
		foreach ( $sections as &$section ) {
			foreach ( $section['fields'] as $key => &$field ) {
				$field = array_merge( $field, $rules[ $key ] ?? array() );
			}
		}
		return $sections;
	}

	/**
	 * The rules of the previous forms, by type and field.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public static function rules(): array {
		$onsite = array( 'onsite', 'blended' );
		$online = array( 'online', 'blended' );
		// Los textos con formato del formulario anterior (listas, negritas):
		// editor enriquecido, no un cuadro de texto que enseña las etiquetas.
		$rich = array( 'w' => 'rich' );
		return array(
			PostTypes::DESIGN   => array(
				'post_title'    => array( 'unique' => true ),
				'post_content'  => $rich,
				D::AUDIENCE     => $rich,
				D::OBJECTIVES   => $rich,
				D::CONTENTS     => $rich,
				D::METHODOLOGY  => $rich,
				D::PRACTICE     => $rich,
				D::TIMING       => $rich,
				D::NOTES        => $rich,
				// Las horas de cada parte, obligatorias cuando la modalidad la
				// tiene: una mixta pide las dos.
				D::HOURS_ONSITE => array(
					'max'  => 200,
					'req'  => true,
					'show' => array( D::MODALITY => $onsite ),
				),
				D::HOURS_ONLINE => array(
					'max'  => 200,
					'req'  => true,
					'show' => array( D::MODALITY => $online ),
				),
			),
			PostTypes::ACTION   => array(
				A::FILE_NUMBER      => array( 'unique' => true ),
				A::TRAINING_FILE    => array( 'show' => array( A::TRAINING_PLANS => array( 'plan', 'seminar' ) ) ),
				// El programa solo cuenta si la asume quien puede cargarla a uno:
				// si la paga el centro de formación o no tiene coste, no lo hay.
				'tax:fmc_programme' => array( 'show' => array( A::FUNDED_BY => A::PROGRAMME_FUNDERS ) ),
				A::NOTES            => $rich,
				A::SPEAKERS         => array( 'add' => PostTypes::SPEAKER ),
			),
			PostTypes::SPEAKER  => array(
				S::ID_NUMBER   => array( 'unique' => true ),
				// Solo los finalizados: un borrador aún no se imparte y un
				// histórico ya no (ADR-0016).
				S::RECOMMENDED => array( 'active' => true ),
			),
			PostTypes::INCIDENT => array(
				// Las fechas nuevas, solo al cambiar las fechas.
				I::NEW_START => array( 'show' => array( I::REQUEST => array( 'postpone' ) ) ),
				I::NEW_END   => array( 'show' => array( I::REQUEST => array( 'postpone' ) ) ),
			),
		);
	}

	/**
	 * Every field of a type, flattened.
	 *
	 * @param string $type Post type.
	 * @return array<string, array<string, mixed>>
	 */
	public static function all( string $type ): array {
		$out = array();
		foreach ( self::sections( $type ) as $section ) {
			$out += $section['fields'];
		}
		return $out;
	}

	/**
	 * Course design.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function design(): array {
		return array(
			array(
				'title'  => 'Datos generales',
				'help'   => 'El código se asigna solo y no cambia. Para guardar un borrador basta con el título; el resto se pide al marcarlo como finalizado.',
				'fields' => array(
					'post_title'    => array(
						'label' => 'Título',
						'w'     => 'text',
						'req'   => true,
						'help'  => 'Tal como se verá en el catálogo. Empiece por «Curso:» o «APU:» si es lo habitual.',
					),
					D::CODE         => array(
						'label' => 'Código',
						'w'     => 'text',
						'auto'  => true,
						'col'   => 4,
						'help'  => 'Se asigna solo la primera vez que se guarda.',
					),
					D::TYPE         => array(
						'label'   => 'Tipo de formación',
						'w'       => 'select',
						'choices' => D::TYPES,
						'col'     => 4,
						'req'     => true,
					),
					D::MODALITY     => array(
						'label'   => 'Modalidad',
						'w'       => 'select',
						'choices' => D::MODALITIES,
						'col'     => 4,
						'req'     => true,
					),
					D::HOURS_ONSITE => array(
						'label' => 'Horas presenciales',
						'w'     => 'number',
						'col'   => 4,
					),
					D::HOURS_ONLINE => array(
						'label' => 'Horas en línea',
						'w'     => 'number',
						'col'   => 4,
					),
					D::IN_CATALOGUE => array(
						'label' => 'Visible en el catálogo público',
						'w'     => 'checkbox',
						'col'   => 4,
					),
				),
			),
			array(
				'title'  => 'Clasificación',
				'help'   => 'Sirve para buscar y agrupar los diseños.',
				'fields' => array(
					'tax:fmc_programme' => array(
						'label' => 'Programas',
						'w'     => 'terms',
						'help'  => sprintf( 'Programas o proyectos a los que pertenece este diseño; puede ser más de uno. Solo se pueden elegir los vigentes, pero un diseño conserva los que ya tenía. Mientras un programa esté activo, las acciones de este diseño que asuma «%s» se imputan a ese programa.', A::funders()[ A::PROGRAMME_FUNDER ] ),
					),
					'tax:fmc_topic'     => array(
						'label' => 'Temática',
						'w'     => 'term',
						'col'   => 6,
					),
					D::REPLACES         => array(
						'label' => 'Sustituye a',
						'w'     => 'designs',
						'col'   => 6,
						'help'  => 'Al marcar este diseño como finalizado, los que sustituye pasan a histórico y sus ponentes recomendados pasan a este.',
					),
					// Las áreas antiguas ya no se rellenan: manda el perfil del marco
					// de referencia, y de él sale el resumen (ver Editor).
					D::DIGCOMP_URL      => array(
						'label' => 'Perfil de competencia digital',
						'w'     => 'url',
						'help'  => 'Pulse «Elegir los indicadores en elidd», márquelos y pulse «Guardar». También puede pegar aquí la dirección que da elidd al compartir. La tabla se ve al guardar la ficha.',
					),
				),
			),
			array(
				'title'  => 'Contenido del diseño',
				'help'   => 'La descripción, los destinatarios, los objetivos y los contenidos se publican en el catálogo. El resto es de uso interno.',
				'fields' => array(
					'post_content' => array(
						'label' => 'Descripción',
						'w'     => 'textarea',
						'req'   => true,
					),
					D::AUDIENCE    => array(
						'label' => 'Destinatarios',
						'w'     => 'textarea',
					),
					D::OBJECTIVES  => array(
						'label' => 'Objetivos',
						'w'     => 'textarea',
					),
					D::CONTENTS    => array(
						'label' => 'Contenidos',
						'w'     => 'textarea',
					),
					D::METHODOLOGY => array(
						'label' => 'Metodología',
						'w'     => 'textarea',
					),
					D::PRACTICE    => array(
						'label' => 'Fase práctica',
						'w'     => 'textarea',
					),
					D::TIMING      => array(
						'label' => 'Temporalización',
						'w'     => 'textarea',
					),
					D::NOTES       => array(
						'label' => 'Observaciones',
						'w'     => 'textarea',
					),
					D::AUTHORSHIP  => array(
						'label' => 'Autoría del diseño',
						'w'     => 'text',
					),
				),
			),
			array(
				'title'  => 'Documentos',
				'help'   => 'El diseño, la escaleta y las presentaciones se suben en formato editable; los recursos, en PDF. Cada documento se puede reemplazar o quitar, y los cambios se aplican al guardar.',
				'fields' => array(
					'docs' => array(
						'label' => 'Documentos',
						'w'     => 'docs',
					),
				),
			),
		);
	}

	/**
	 * Training action.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function action(): array {
		$funders = A::funders();
		$lines   = A::strategic_lines();
		$paying  = implode( '» o «', array_intersect_key( $funders, array_flip( A::PROGRAMME_FUNDERS ) ) );
		$out     = array(
			array(
				'title'  => 'La acción',
				'help'   => 'El título, la modalidad y las horas son siempre los del diseño.',
				'fields' => array(
					// Los dos expedientes, primero y juntos: son lo que más se
					// busca. El interno lo pone el aplicativo y es el que la
					// asesoría da al servicio de perfeccionamiento; el otro lo
					// rellena el servicio cuando da de alta la acción en su sistema.
					A::INTERNAL_REF    => array(
						'label' => 'Expediente interno',
						'w'     => 'text',
						'auto'  => true,
						'col'   => 6,
						'help'  => 'Se genera solo la primera vez que se guarda la acción y no cambia. Es el que se da al servicio de perfeccionamiento para que la localice.',
					),
					A::FILE_NUMBER     => array(
						'label' => 'Expediente de perfeccionamiento',
						'w'     => 'text',
						'col'   => 6,
						'help'  => 'El número que le da el servicio de perfeccionamiento en su sistema de gestión. Al rellenarlo, la acción pasa a «Completada».',
					),
					// El subtítulo ya no se pide ni se enseña: servía para que una
					// acción pareciera otro curso. Lo guardado se conserva.
					A::DESIGN_ID       => array(
						'label' => 'Diseño',
						'w'     => 'design',
						'req'   => true,
						'help'  => 'Solo se pueden elegir diseños finalizados. La acción toma el título del diseño.',
					),
					// La asesoría va antes que el ámbito porque lo decide: al
					// elegirla se pone el ámbito de su centro de referencia
					// (ADR-0022), y del ámbito salen los centros que se ofrecen.
					// Las dos se pueden cambiar a mano después.
					A::ADVISER         => array(
						'label' => 'Asesoría responsable',
						'w'     => 'adviser',
						'col'   => 6,
						'sets'  => 'tax:fmc_scope',
					),
					'tax:fmc_scope'    => array(
						'label'     => 'Ámbito que la gestiona',
						'w'         => 'term',
						'col'       => 6,
						'req'       => true,
						'refreshes' => A::VENUE_CODE,
					),
					// El centro se elige del catálogo por su código; el nombre se guarda aparte, como estaba (ADR-0021).
					A::VENUE_CODE      => array(
						'label' => 'Centro donde se imparte',
						'w'     => 'centre',
						'col'   => 8,
					),
					A::VIDEOCONFERENCE => array(
						'label' => 'Por videoconferencia',
						'w'     => 'checkbox',
						'col'   => 4,
					),
					A::SPEAKERS        => array(
						'label' => 'Ponentes',
						'w'     => 'speakers',
						'help'  => 'Escriba para buscar y añadir; la «×» quita a cada uno. Junto al nombre se ven su isla y las acciones que ha impartido. La «★» indica que está recomendado para el diseño elegido; el gris, que no está disponible; el bocadillo, que tiene observaciones.',
					),
				),
			),
			array(
				'title'  => 'Financiación',
				// Quién paga se elige; el programa, si paga quien cobra a programas, sale del diseño (ADR-0018).
				'help'   => sprintf( 'Indique quién la paga. El programa solo se pide si la asume «%3$s». Con «%1$s» se asigna solo al guardar: el programa activo del diseño. Si el diseño no está en ningún programa activo, la asume «%2$s».', $funders[ A::PROGRAMME_FUNDER ], $funders[ A::FALLBACK_FUNDER ], $paying ),
				'fields' => array(
					A::FUNDED_BY        => array(
						'label'   => 'Asumida por',
						'w'       => 'select',
						'choices' => $funders,
						'col'     => 6,
					),
					'tax:fmc_programme' => array(
						'label' => 'Con cargo al programa',
						'w'     => 'terms',
						'col'   => 6,
						'help'  => sprintf( 'Solo se pueden elegir programas vigentes. Con «%s» se asigna solo.', $funders[ A::PROGRAMME_FUNDER ] ),
					),
					// De una lista, y no escrita a mano: cada cual la escribía a su
					// manera. La lista la da el despliegue; lo guardado que ya no
					// está en ella se conserva y se dice.
					A::STRATEGIC_LINE   => array(
						'label'   => 'Línea estratégica',
						'w'       => 'select',
						'choices' => $lines,
						'closed'  => true,
						'col'     => 6,
					),
					// Una acción está en un plan de formación, en un seminario o
					// en ninguno: no en dos a la vez. Y su expediente, el del
					// plan o del seminario, va justo debajo de la elección.
					A::TRAINING_PLANS   => array(
						'label'   => 'Planes de formación de los centros',
						'w'       => 'radio',
						'choices' => array( '' => 'No está incluida en ninguno' ) + A::TRAINING_PLAN_KINDS,
					),
					A::TRAINING_FILE    => array(
						'label' => 'Expediente del plan o del seminario',
						'w'     => 'text',
						'col'   => 6,
						'help'  => 'Número de expediente del plan de formación del centro o del seminario en el que se incluye esta acción. Déjelo en blanco si todavía no lo tiene.',
					),
				),
			),
			// Las plazas, la modalidad, las horas, el desdoble, las horas con
			// desplazamiento y la matrícula en línea no se piden aquí: las dos
			// primeras las lleva el sistema de gestión del servicio, y la
			// modalidad y las horas son del diseño, que las copia al guardar.
			array(
				'title'  => 'Fechas y horas',
				'help'   => 'El curso escolar se calcula a partir de la fecha de inicio. La matrícula (plazos, enlace y listas) se lleva en el sistema de gestión del servicio de perfeccionamiento.',
				// Los plazos de matrícula y las listas no se piden aquí: los lleva el sistema
				// del servicio. Sus claves siguen registradas para lo migrado (issue #35, adenda de la ADR-0005).
				'fields' => array(
					A::START    => array(
						'label' => 'Fecha de inicio',
						'w'     => 'date',
						'col'   => 6,
						'req'   => true,
					),
					A::END      => array(
						'label' => 'Fecha de fin',
						'w'     => 'date',
						'col'   => 6,
					),
					// Una sesión por fila, con su día y sus horas: el calendario las
					// pinta una a una (ADR-0027). Lo que llegó escrito a mano se
					// enseña debajo, de solo lectura, y no se convierte.
					A::SESSIONS => array(
						'label' => 'Sesiones',
						'w'     => 'sessions',
						'help'  => 'Día y horario de cada sesión, dentro de las fechas de inicio y de fin. Cada sesión aparece en su día en el calendario.',
					),
					A::SCHEDULE => array(
						'label' => 'Días y horas (texto anterior)',
						'w'     => 'legacy',
						'help'  => 'Lo que se escribió a mano cuando aún no había sesiones. Se conserva tal cual y no se puede cambiar.',
					),
				),
			),
			// Los criterios de certificación y el espacio del curso en la
			// plataforma están en la ficha técnica y en el sistema del
			// servicio; el técnico de la empresa y la petición de servicio solo
			// hacían falta cuando una empresa gestionaba la formación.
			array(
				'title'  => 'Observaciones',
				// La situación no se teclea, ni el proceso: la acción se guarda o se entrega (ADR-0020).
				'help'   => 'La situación se calcula sola: «Impartiéndose» durante sus fechas y «Realizada» cuando pasan. Una incidencia puede aplazarla o cancelarla.',
				'fields' => array(
					A::NOTES => array(
						'label' => 'Observaciones',
						'w'     => 'textarea',
					),
				),
			),
		);
		// Sin líneas que elegir, el campo no sale: no hay nada que poner en él.
		if ( array() === $lines ) {
			unset( $out[1]['fields'][ A::STRATEGIC_LINE ] );
		}
		return $out;
	}

	/**
	 * Speaker.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function speaker(): array {
		return array(
			array(
				'title'  => 'Datos personales',
				'help'   => 'Los ve quien gestiona acciones formativas. Cada asesoría puede editar las fichas que ha dado de alta.',
				'fields' => array(
					S::FIRST_NAME  => array(
						'label' => 'Nombre',
						'w'     => 'text',
						'col'   => 6,
						'req'   => true,
					),
					S::LAST_NAME   => array(
						'label' => 'Apellidos',
						'w'     => 'text',
						'col'   => 6,
						'req'   => true,
					),
					S::ID_NUMBER   => array(
						'label' => 'Documento de identidad',
						'w'     => 'text',
						'col'   => 4,
					),
					S::PHONE       => array(
						'label' => 'Teléfono',
						'w'     => 'text',
						'col'   => 4,
					),
					'tax:fmc_zone' => array(
						'label' => 'Isla o zona',
						'w'     => 'term',
						'col'   => 4,
					),
					S::EMAIL       => array(
						'label' => 'Correo electrónico',
						'w'     => 'email',
						'col'   => 6,
						'req'   => true,
					),
					S::EMAIL_ALT   => array(
						'label' => 'Correo alternativo',
						'w'     => 'email',
						'col'   => 6,
					),
					S::PROFESSION  => array(
						'label'   => 'Cargo',
						'w'       => 'select',
						'choices' => S::PROFESSIONS,
						'col'     => 6,
					),
					S::WEBSITE     => array(
						'label' => 'Sitio web',
						'w'     => 'url',
						'col'   => 6,
					),
					S::ADDRESS     => array(
						'label' => 'Dirección',
						'w'     => 'textarea',
					),
				),
			),
			array(
				'title'  => 'Disponibilidad',
				'help'   => '',
				'fields' => array(
					S::UNAVAILABLE => array(
						'label' => 'No disponible',
						'w'     => 'checkbox',
						'help'  => 'Se puede seguir eligiendo para una acción, pero aparece en gris y con un aviso. Explique el motivo en las observaciones.',
					),
					S::RECOMMENDED => array(
						'label' => 'Recomendado para los diseños',
						'w'     => 'designs',
						'help'  => 'Escriba para buscar y añadir; la «×» quita cada uno. Solo se pueden elegir diseños finalizados.',
					),
					S::NOTES       => array(
						'label' => 'Observaciones',
						'w'     => 'textarea',
						'help'  => 'Se ven en un bocadillo junto al nombre, en el listado y al elegir ponentes para una acción.',
					),
				),
			),
		);
	}

	/**
	 * Incident.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function incident(): array {
		return array(
			array(
				'title'  => 'La incidencia',
				// La incidencia deja constancia; nadie la aprueba (ADR-0029).
				'help'   => 'La incidencia deja constancia de un cambio en la acción y no necesita aprobación. Una vez enviada no se puede modificar; si hay que corregirla, abra otra. Al enviarla, una cancelación deja la acción cancelada y un cambio de fechas la pasa a las fechas nuevas.',
				'fields' => array(
					I::REQUEST   => array(
						'label'   => 'Tipo de incidencia',
						'w'       => 'radio',
						'choices' => I::KINDS,
						'req'     => true,
					),
					I::NEW_START => array(
						'label' => 'Nueva fecha de inicio',
						'w'     => 'date',
						'col'   => 6,
						'help'  => 'Si todavía no se sabe, déjela en blanco y la acción quedará aplazada. Si la indica, las sesiones se desplazan los mismos días; las que no quepan en las fechas nuevas se quitan.',
					),
					I::NEW_END   => array(
						'label' => 'Nueva fecha de fin',
						'w'     => 'date',
						'col'   => 6,
						'help'  => 'Si la deja en blanco, la acción conserva su duración.',
					),
					I::REASON    => array(
						'label' => 'Justificación',
						'w'     => 'textarea',
						'req'   => true,
					),
				),
			),
		);
	}
}

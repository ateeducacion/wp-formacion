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
 * una lista de diseños, ofrece solo los finalizados (ADR-0016).
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
				A::FILE_NUMBER   => array( 'unique' => true ),
				A::TRAINING_FILE => array( 'show' => array( A::TRAINING_PLANS => array( 'plan', 'seminar' ) ) ),
				A::NOTES         => $rich,
				A::SPEAKERS      => array( 'add' => PostTypes::SPEAKER ),
			),
			PostTypes::SPEAKER  => array(
				S::ID_NUMBER   => array( 'unique' => true ),
				// Solo los finalizados: un borrador aún no se imparte y un
				// histórico ya no (ADR-0016).
				S::RECOMMENDED => array( 'active' => true ),
			),
			PostTypes::INCIDENT => array(
				I::RESOLUTION_NOTES => array( 'show' => array( I::RESOLUTION => array( 'approved', 'denied' ) ) ),
				// Las fechas nuevas, solo al pedir aplazarla.
				I::NEW_START        => array( 'show' => array( I::REQUEST => array( 'postpone' ) ) ),
				I::NEW_END          => array( 'show' => array( I::REQUEST => array( 'postpone' ) ) ),
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
				'title'  => 'Identidad',
				'help'   => 'Cómo se llama el diseño y de qué tipo es. El código lo asigna el aplicativo y no cambia.',
				'fields' => array(
					'post_title'    => array(
						'label' => 'Título',
						'w'     => 'text',
						'req'   => true,
						'help'  => 'Tal y como sale en el catálogo. Empiece por «Curso:» o «APU:» si así se viene haciendo.',
					),
					D::CODE         => array(
						'label' => 'Código',
						'w'     => 'text',
						'auto'  => true,
						'col'   => 4,
						'help'  => 'El siguiente libre; se asigna al guardar por primera vez.',
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
						'label' => 'Sale en el catálogo público',
						'w'     => 'checkbox',
						'col'   => 4,
						'help'  => 'Solo sale si, además, está finalizado. Lo que ya no se imparte no se quita de aquí: se pasa a histórico.',
					),
				),
			),
			array(
				'title'  => 'Clasificación',
				'help'   => 'Con qué se busca y se agrupa el diseño.',
				'fields' => array(
					'tax:fmc_programme' => array(
						'label' => 'Programas',
						'w'     => 'terms',
						'help'  => sprintf( 'Los programas o proyectos que cubren este diseño. Puede ser más de uno. Mientras un programa esté activo, las acciones de este diseño que asuma «%s» se le imputan.', A::funders()[ A::PROGRAMME_FUNDER ] ),
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
						'help'  => 'Al dar este por finalizado, los que sustituye pasan a histórico y sus ponentes recomendados pasan a este diseño.',
					),
					// Las áreas antiguas ya no se rellenan: manda el perfil del marco
					// de referencia, y de él sale el resumen (ver Editor).
					D::DIGCOMP_URL      => array(
						'label' => 'Perfil de competencia digital',
						'w'     => 'url',
						'help'  => 'Pulse «Elegir los indicadores en elidd», márquelos y pulse «Guardar»; o pegue aquí la dirección que da elidd al compartir. Al guardar la ficha se ve la tabla.',
					),
				),
			),
			array(
				'title'  => 'Contenido del diseño',
				'help'   => 'Descripción, destinatarios, objetivos y contenidos se publican en la ficha del catálogo; lo demás es de trabajo.',
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
				'help'   => 'El diseño, la escaleta y las presentaciones, en editable; los recursos, en PDF, para conservar la versión. Cada documento se puede cambiar por otro o quitar; los cambios se guardan con el diseño.',
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
		return array(
			array(
				'title'  => 'La acción',
				'help'   => 'De qué diseño sale y quién la gestiona. El título, la modalidad y las horas son siempre los del diseño.',
				'fields' => array(
					// El expediente, primero: es el campo que más se teclea y el
					// que más se busca, y el único que rellena el servicio de
					// perfeccionamiento.
					A::FILE_NUMBER     => array(
						'label' => 'Expediente',
						'w'     => 'text',
						'col'   => 6,
						'help'  => 'El del sistema de gestión del servicio de perfeccionamiento, cuando lo haya creado allí. Es por el que busca el listado.',
					),
					A::DESIGN_ID       => array(
						'label' => 'Diseño',
						'w'     => 'design',
						'req'   => true,
						'help'  => 'Solo los diseños finalizados. La acción lleva el título del diseño, y no se cambia.',
					),
					A::SUBTITLE        => array(
						'label' => 'Subtítulo',
						'w'     => 'text',
						'help'  => 'Un añadido que sale debajo del título, por ejemplo la edición o el lugar. No sustituye al título.',
					),
					'tax:fmc_scope'    => array(
						'label' => 'Ámbito que la gestiona',
						'w'     => 'term',
						'col'   => 6,
						'req'   => true,
					),
					A::ADVISER         => array(
						'label' => 'Asesoría responsable',
						'w'     => 'adviser',
						'col'   => 6,
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
						'help'  => 'Escriba para buscar y añadir; la «×» de cada uno lo quita. Al lado de cada nombre, su isla y las acciones que ha impartido; «★» si se le recomienda para el diseño elegido, en gris si no está disponible, y un bocadillo si tiene observaciones.',
					),
				),
			),
			array(
				'title'  => 'Financiación',
				// Quién paga se elige; el programa, si paga quien cobra a programas, sale del diseño (ADR-0018).
				'help'   => sprintf( 'Quién la paga y con cargo a qué programa. Si la asume «%1$s», el programa se pone solo al guardar: el programa activo que cubre el diseño. Si el diseño no está en ninguno, la asume «%2$s».', $funders[ A::PROGRAMME_FUNDER ], $funders[ A::FALLBACK_FUNDER ] ),
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
						'help'  => sprintf( 'Con «%s», lo pone el aplicativo.', $funders[ A::PROGRAMME_FUNDER ] ),
					),
					A::STRATEGIC_LINE   => array(
						'label' => 'Línea estratégica',
						'w'     => 'text',
						'col'   => 6,
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
						'help'  => 'El número de expediente del plan de formación del centro o del seminario en el que se incluye esta acción, para poder asociarla. No es el expediente de la acción, que lo pone el servicio de perfeccionamiento.',
					),
				),
			),
			// Las plazas, la modalidad, las horas, el desdoble, las horas con
			// desplazamiento y la matrícula en línea no se piden aquí: las dos
			// primeras las lleva el sistema de gestión del servicio, y la
			// modalidad y las horas son del diseño, que las copia al guardar.
			array(
				'title'  => 'Fechas y horas',
				'help'   => 'De la fecha de inicio sale el curso escolar y el sitio en el calendario. Los plazos de matrícula, el enlace y las listas se llevan en el sistema de gestión del servicio de perfeccionamiento.',
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
					A::SCHEDULE => array(
						'label' => 'Días y horas',
						'w'     => 'textarea',
						'help'  => 'Si tiene más de una sesión, los días y el horario de cada una. Por ejemplo: 20 y 27 de marzo y 3 y 24 de abril, de 16:00 a 20:00.',
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
				'help'   => 'La situación la pone el aplicativo: impartiéndose en sus fechas y realizada cuando pasan; una incidencia la aplaza o la cancela.',
				'fields' => array(
					A::NOTES        => array(
						'label' => 'Observaciones',
						'w'     => 'textarea',
					),
					A::INTERNAL_REF => array(
						'label' => 'Referencia interna',
						'w'     => 'text',
						'col'   => 6,
						'help'  => 'La del sistema anterior; solo para buscar.',
					),
				),
			),
		);
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
				'help'   => 'Los ve quien gestiona acciones formativas; cada asesoría edita las fichas que ha dado de alta.',
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
						'help'  => 'Se puede seguir eligiendo para una acción, pero sale en gris y avisado. Diga el porqué en las observaciones.',
					),
					S::RECOMMENDED => array(
						'label' => 'Recomendado para los diseños',
						'w'     => 'designs',
						'help'  => 'Escriba para buscar y añadir; la «×» de cada uno lo quita. Solo se ofrecen los diseños finalizados: ni borradores ni históricos.',
					),
					S::NOTES       => array(
						'label' => 'Observaciones',
						'w'     => 'textarea',
						'help'  => 'Salen en un bocadillo junto al nombre, en el listado y al elegir ponentes para una acción.',
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
				'help'   => 'Una vez enviada ya no se puede cambiar: si hay que corregirla, se abre otra. Si pide aplazar o cancelar la acción, la acción queda aplazada o cancelada al enviarla.',
				'fields' => array(
					I::REQUEST   => array(
						'label'   => 'Qué se pide',
						'w'       => 'radio',
						'choices' => I::REQUESTS,
						'req'     => true,
					),
					I::NEW_START => array(
						'label' => 'Nueva fecha de inicio',
						'w'     => 'date',
						'col'   => 6,
						'help'  => 'Si ya se sabe.',
					),
					I::NEW_END   => array(
						'label' => 'Nueva fecha de fin',
						'w'     => 'date',
						'col'   => 6,
					),
					I::REASON    => array(
						'label' => 'Motivos',
						'w'     => 'textarea',
						'req'   => true,
					),
					I::PROPOSAL  => array(
						'label' => 'Cambios que se proponen',
						'w'     => 'textarea',
						'req'   => true,
					),
					I::HAS_COST  => array(
						'label' => 'El cambio conlleva coste',
						'w'     => 'checkbox',
						'help'  => 'Con coste es, por ejemplo, duplicar el curso; sin coste, cambiar ponente, fechas o número de plazas.',
					),
				),
			),
			array(
				'title'  => 'Resolución',
				'help'   => 'La resuelve el servicio de perfeccionamiento.',
				'fields' => array(
					I::RESOLUTION       => array(
						'label'   => 'Resolución',
						'w'       => 'radio',
						'choices' => I::RESOLUTIONS,
					),
					I::RESOLUTION_NOTES => array(
						'label' => 'Observaciones a la resolución',
						'w'     => 'textarea',
					),
				),
			),
		);
	}
}

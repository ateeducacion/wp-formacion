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
				D::HOURS_ONSITE => array(
					'max'  => 200,
					'show' => array( D::MODALITY => $onsite ),
				),
				D::HOURS_ONLINE => array(
					'max'  => 200,
					'show' => array( D::MODALITY => $online ),
				),
			),
			PostTypes::ACTION   => array(
				A::FILE_NUMBER   => array( 'unique' => true ),
				A::PLACES        => array(
					'min' => 1,
					'max' => 10000,
				),
				A::HOURS_ONSITE  => array(
					'max'  => 500,
					'show' => array( A::MODALITY => $onsite ),
				),
				A::HOURS_ONLINE  => array(
					'max'  => 500,
					'show' => array( A::MODALITY => $online ),
				),
				A::REPLICAS      => array(
					'min' => 1,
					'max' => 10,
				),
				A::TRAINING_FILE => array( 'show' => array( A::TRAINING_PLANS => array( 'plan', 'seminar' ) ) ),
				A::CERTIFICATION => array(
					'w'    => 'rich',
					'show' => array( A::MODALITY => $online ),
				),
				A::NOTES         => $rich,
				A::SPEAKERS      => array( 'add' => PostTypes::SPEAKER ),
			),
			PostTypes::SPEAKER  => array(
				S::ID_NUMBER   => array( 'unique' => true ),
				// Solo los diseños del catálogo: los históricos ya no se imparten.
				S::RECOMMENDED => array( 'catalogue' => true ),
			),
			PostTypes::INCIDENT => array(
				I::RESOLUTION_NOTES => array( 'show' => array( I::RESOLUTION => array( 'approved', 'denied' ) ) ),
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
						'label' => 'Título del curso',
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
						'help'  => 'Aparte de estar finalizado. Fuera del catálogo, es histórico: ya no se recomienda a ponentes.',
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
						'help'  => 'El programa o proyecto para el que se crea. Puede ser más de uno.',
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
						'help'  => 'Los diseños que este deja en histórico: sus ponentes recomendados pasan a serlo de este.',
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
				'help'   => 'El diseño, el minutaje y el material de apoyo. Cada documento se puede cambiar por otro o quitar; los cambios se guardan con el diseño.',
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
		return array(
			array(
				'title'  => 'La acción',
				'help'   => 'De qué diseño sale y quién la gestiona. El título es siempre el del diseño; la modalidad y las horas se toman de él si se dejan en blanco.',
				'fields' => array(
					A::DESIGN_ID       => array(
						'label' => 'Diseño',
						'w'     => 'design',
						'req'   => true,
						'help'  => 'La acción lleva el título del diseño, y no se cambia.',
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
					A::VENUE_CENTRE    => array(
						'label' => 'Centro donde se imparte',
						'w'     => 'text',
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
				'help'   => 'Quién la paga y con cargo a qué programa. Si la paga el servicio de formación con fondos de un programa, elija el servicio y el programa: es lo que le dice qué expediente ponerle.',
				'fields' => array(
					A::FUNDED_BY        => array(
						'label'   => 'Asumida por',
						'w'       => 'select',
						'choices' => A::FUNDERS,
						'col'     => 6,
					),
					'tax:fmc_programme' => array(
						'label' => 'Con cargo al programa',
						'w'     => 'terms',
						'col'   => 6,
					),
					A::STRATEGIC_LINE   => array(
						'label' => 'Línea estratégica',
						'w'     => 'text',
						'col'   => 6,
					),
					A::TRAINING_FILE    => array(
						'label' => 'Expediente de formación',
						'w'     => 'text',
						'col'   => 6,
						'help'  => 'Número del expediente en el que se incluye esta acción.',
					),
					A::TRAINING_PLANS   => array(
						'label'   => 'Planes de formación de los centros',
						'w'       => 'checks',
						'choices' => A::TRAINING_PLAN_KINDS,
					),
					A::ITINERARY        => array(
						'label' => 'Itinerario formativo',
						'w'     => 'text',
					),
				),
			),
			array(
				'title'  => 'Plazas, modalidad y horas',
				'help'   => 'Las horas totales y las del desdoble se calculan.',
				'fields' => array(
					A::PLACES       => array(
						'label' => 'Plazas',
						'w'     => 'number',
						'col'   => 3,
						'req'   => true,
					),
					A::MODALITY     => array(
						'label'   => 'Modalidad',
						'w'       => 'select',
						'choices' => D::MODALITIES,
						'col'     => 3,
					),
					A::HOURS_ONSITE => array(
						'label' => 'Horas presenciales',
						'w'     => 'number',
						'col'   => 3,
					),
					A::HOURS_ONLINE => array(
						'label' => 'Horas en línea',
						'w'     => 'number',
						'col'   => 3,
					),
					A::REPLICAS     => array(
						'label' => 'Desdoblar',
						'w'     => 'number',
						'col'   => 3,
						'help'  => 'Veces que se repite con el mismo expediente.',
					),
					A::TRAVEL_HOURS => array(
						'label' => 'Horas con desplazamiento',
						'w'     => 'checkbox',
						'col'   => 3,
					),
					A::ONLINE_ENROL => array(
						'label' => 'Matrícula en línea',
						'w'     => 'checkbox',
						'col'   => 3,
					),
				),
			),
			array(
				'title'  => 'Fechas y plazos',
				'help'   => 'De la fecha de inicio sale el curso escolar y el sitio en el calendario.',
				'fields' => array(
					A::START            => array(
						'label' => 'Fecha de inicio',
						'w'     => 'date',
						'col'   => 6,
						'req'   => true,
					),
					A::END              => array(
						'label' => 'Fecha de fin',
						'w'     => 'date',
						'col'   => 6,
					),
					A::SCHEDULE         => array(
						'label' => 'Días y horas',
						'w'     => 'textarea',
						'help'  => 'Por ejemplo: 20 y 27 de marzo y 3 y 24 de abril, de 16:00 a 20:00.',
					),
					A::ENROL_START      => array(
						'label' => 'Inicio de matrícula',
						'w'     => 'date',
						'col'   => 4,
					),
					A::ENROL_END        => array(
						'label' => 'Fin de matrícula',
						'w'     => 'date',
						'col'   => 4,
					),
					A::ENROL_URL        => array(
						'label' => 'Enlace de matrícula',
						'w'     => 'url',
						'col'   => 4,
					),
					A::PROVISIONAL_LIST => array(
						'label' => 'Lista provisional',
						'w'     => 'date',
						'col'   => 3,
					),
					A::CLAIMS_START     => array(
						'label' => 'Inicio de reclamaciones',
						'w'     => 'date',
						'col'   => 3,
					),
					A::CLAIMS_END       => array(
						'label' => 'Fin de reclamaciones',
						'w'     => 'date',
						'col'   => 3,
					),
					A::FINAL_LIST       => array(
						'label' => 'Lista definitiva',
						'w'     => 'date',
						'col'   => 3,
					),
				),
			),
			array(
				'title'  => 'Desarrollo',
				'help'   => '',
				'fields' => array(
					A::CERTIFICATION    => array(
						'label' => 'Criterios de certificación',
						'w'     => 'textarea',
					),
					A::COURSE_SPACE_URL => array(
						'label' => 'Espacio del curso en la plataforma',
						'w'     => 'url',
					),
					A::NOTES            => array(
						'label' => 'Observaciones',
						'w'     => 'textarea',
					),
				),
			),
			array(
				'title'  => 'Estado',
				'help'   => 'El proceso lo lleva quien gestiona la acción; la situación, el expediente y el personal del servicio, el servicio de formación.',
				'fields' => array(
					A::PROCESS         => array(
						'label'   => 'Proceso',
						'w'       => 'select',
						'choices' => A::PROCESSES,
						'col'     => 6,
					),
					A::SITUATION       => array(
						'label'   => 'Situación',
						'w'       => 'select',
						'choices' => A::SITUATIONS,
						'col'     => 6,
					),
					A::FILE_NUMBER     => array(
						'label' => 'Expediente',
						'w'     => 'text',
						'col'   => 4,
						'help'  => 'Solo cuando se haya creado en el sistema de gestión.',
					),
					A::SERVICE_OFFICER => array(
						'label' => 'Negociado del servicio',
						'w'     => 'text',
						'col'   => 4,
					),
					A::SERVICE_MANAGER => array(
						'label' => 'Responsable del servicio',
						'w'     => 'text',
						'col'   => 4,
					),
					A::COMPANY_TECH    => array(
						'label' => 'Técnico de la empresa',
						'w'     => 'text',
						'col'   => 6,
					),
					A::SERVICE_REQUEST => array(
						'label' => 'Petición de servicio',
						'w'     => 'checkbox',
						'col'   => 6,
					),
					A::INTERNAL_REF    => array(
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
						'help'  => 'Escriba para buscar y añadir; la «×» de cada uno lo quita. Solo se ofrecen los diseños del catálogo.',
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
				'help'   => 'Una vez enviada ya no se puede cambiar: si hay que corregirla, se abre otra.',
				'fields' => array(
					I::REASON   => array(
						'label' => 'Motivos',
						'w'     => 'textarea',
						'req'   => true,
					),
					I::PROPOSAL => array(
						'label' => 'Cambios que se proponen',
						'w'     => 'textarea',
						'req'   => true,
					),
					I::HAS_COST => array(
						'label' => 'El cambio conlleva coste',
						'w'     => 'checkbox',
						'help'  => 'Con coste es, por ejemplo, duplicar el curso; sin coste, cambiar ponente, fechas o número de plazas.',
					),
				),
			),
			array(
				'title'  => 'Resolución',
				'help'   => 'La resuelve el servicio de formación.',
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

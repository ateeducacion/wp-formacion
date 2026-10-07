---
id: REQ-0001
title: "Requisitos del aplicativo de formación"
status: Propuesta
date: 2026-09-30
related:
  issues: []
  prs: []
  adrs: [ADR-0002, ADR-0003, ADR-0004, ADR-0005, ADR-0006, ADR-0007, ADR-0008]
  sdds: []
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# REQ-0001 — Requisitos: aplicativo de formación

**Estado:** propuesta. Se escribe el 2026-09-30 a partir de una reunión de
revisión del sistema actual y de la foto de ese sistema tomada el mismo día. Se
valida con quien gestiona hoy la formación antes de pasar a
`Aceptada` (PLAN-0001, fase 1).
**Fuentes:**

- Una reunión de revisión con quien usa el sistema. El material está en `.local/`.
- Una exportación del sistema actual, tomada **solo con lecturas**. El análisis,
  con sus identificadores, está en `.local/`.
- El aplicativo de eventos, del que este hereda la plataforma (ADR-0002).

---

## 1. Propósito

Sustituir el sitio con el que el área gestiona hoy la formación TIC del
profesorado por un aplicativo **versionado, probado y seguro**, sin perder lo
que el sistema actual hace bien. Quien lo usa lo resume así: la aplicación
«como tal está perfecta», lo que se ha quedado viejo es cómo está hecha, y
**sobre todo la seguridad**.

## 2. El problema, dicho por quien lo tiene

- El sistema se levantó sobre el gestor de formularios hace años y
  se ha ido **parcheando**: hay campos repetidos, campos «obsoletos» que siguen
  en el formulario y una casilla que mezcla tres cosas distintas.
- **La seguridad se basaba en la ignorancia.** Los campos que no te tocan se
  esconden en la pantalla; la acción sobre la que abres una incidencia llega en
  la URL. Hoy cualquiera con un asistente de IA cambia un identificador o manda
  un campo oculto, y el sistema lo guarda.
- La plataforma no solo publica el catálogo: **se usa como mesa de trabajo**. Un
  diseño se escribe en borrador durante semanas antes de estar listo.

## 3. Actores

| Actor | Qué hace | Rol |
|---|---|---|
| Administración | Todo, y administrar el aplicativo | `administrator` |
| Curaduría | Revisa los diseños y los da por finalizados; gestiona todas las acciones | `fmc_curator` |
| Asesoría | Escribe diseños, registra y mantiene sus acciones, abre incidencias, da de alta ponentes | `fmc_adviser` |
| Servicio de formación | Pone el número de expediente de la acción y resuelve las incidencias; nada más | `fmc_training_service` |
| Visitante | Consulta el catálogo de diseños finalizados | — |

Hay un quinto perfil en el sistema actual —una empresa que rellena la
documentación y la situación de las acciones que imparte— cuyo uso hoy no está
confirmado. Queda como pregunta abierta (§8).

## 4. Requisitos funcionales

### 4.1 Diseños de curso

- **RF-1.** Un diseño tiene título, tipo de formación (curso, acción puntual,
  teleformación, autodirigido, intervención en aula), modalidad (presencial, en
  línea, mixta), horas presenciales y en línea, temática, programas, áreas de
  competencia digital, descripción, destinatarios, objetivos, contenidos,
  metodología, fase práctica, temporalización, observaciones, autoría y tres
  documentos (diseño, minutaje de las sesiones, material de apoyo).
- **RF-2.** Un diseño lleva un **código** único y estable que se asigna solo.
- **RF-3.** Ciclo: **borrador → pendiente de revisión → finalizado**. La
  asesoría escribe y manda a revisión; **solo la curaduría lo da por
  finalizado**; finalizado, la asesoría ya no lo edita (ADR-0004).
- **RF-4.** Estar en el catálogo público es una decisión aparte de estar
  finalizado, y pertenecer a un programa es otra (ADR-0003).
- **RF-5.** El diseño puede enlazar su perfil de competencia digital, generado
  con una herramienta externa, y mostrarlo integrado en su ficha.
- **RF-6.** Catálogo público de diseños finalizados, con búsqueda y filtros por
  modalidad y tipo.

### 4.2 Acciones formativas

- **RF-7.** Una acción es **una edición de un diseño**: se crea eligiendo el
  diseño, y hereda de él título, tipo, modalidad y horas, que puede ajustar.
- **RF-8.** Datos propios: subtítulo, ámbito que la gestiona, centro donde se
  imparte, si es por videoconferencia, plazas, réplicas, quién la paga y de qué
  programa, asesoría responsable, ponentes (varios), plazos —inicio y fin de
  matrícula, enlace de matrícula, lista provisional, reclamaciones, lista
  definitiva—, fechas de inicio y fin, calendario de sesiones en texto,
  criterios de certificación, espacio del curso en la plataforma de
  aprendizaje y observaciones.
- **RF-9.** Estado administrativo (borrador → pagada por el centro → entregada
  al área → cerrada y entregada al servicio) y situación (gestionándose,
  impartiéndose, realizada, aplazada, cancelada).
- **RF-10.** Cifras finales por sexo: matriculados, asistentes y certificados.
  **Los totales se calculan**, no se teclean.
- **RF-11.** El **curso escolar** se deduce de la fecha de inicio.
- **RF-12.** El **número de expediente** lo pone solo el servicio de formación.
- **RF-13.** **Calendario** de acciones con un **código de colores** que diga de
  un vistazo la situación y quién la asume, con los filtros del actual
  (ámbito, tipo, modalidad, estado, situación, ponente). Menos texto que hoy.
- **RF-14.** Listados de control por programa, histórico y exportación a CSV.

### 4.3 Ponentes

- **RF-15.** Ficha con nombre, apellidos, documento de identidad, teléfono,
  profesión, zona, correos, web, dirección, observaciones, «no disponible» y
  diseños para los que se le recomienda.
- **RF-16.** Son **datos personales**: no públicos, fuera de la API y visibles
  solo para quien gestiona acciones.
- **RF-17.** Buscador de ponentes por diseño recomendado.

### 4.4 Incidencias

- **RF-18.** Sobre una acción se abre una incidencia con motivos, cambios que se
  proponen y si conllevan coste.
- **RF-19.** La abre quien puede editar la acción; **la acción la fija el
  aplicativo**, no la URL (ADR-0007).
- **RF-20.** Solo el servicio de formación la aprueba o la deniega, con
  observaciones; quien la abrió recibe aviso.

### 4.5 Consentimientos y documentos

- **RF-21.** Los documentos que hoy se suben a mano en dos formatos cada vez
  que cambia un texto se **generan al vuelo** desde el propio texto. Propuesta:
  se decide con su ADR en la fase 4.

## 5. Requisitos no funcionales

- **RNF-1. Seguridad.** Todo permiso se comprueba en el servidor, por tipo y
  por campo; nada se protege escondiéndolo (ADR-0006).
- **RNF-2.** Misma plataforma que eventos: Code Snippets, sin ficheros en el
  servidor, CI con cobertura y comprobación de publicación (ADR-0001, ADR-0002).
- **RNF-3.** Accesibilidad WCAG 2.1 AA en las pantallas propias.
- **RNF-4.** Nada de la organización en el repositorio (ADR-0002).
- **RNF-5.** Los listados que hoy consumen otras webs del área siguen
  respondiendo en su dirección, o se avisa con fecha a quien los consume
  (PLAN-0001, fase 5).

## 6. Fuera de alcance

- **La matrícula** del profesorado: la lleva el sistema de gestión del servicio
  de formación (ADR-0005).
- Los procesos de acreditación, las rúbricas y el plan de un curso concreto que
  conviven hoy en el mismo sitio: son históricos y se quedan en el sitio
  archivado (ADR-0008).

## 7. Migración

El sitio nuevo se monta **limpio**; el actual pasa a una dirección de archivo y
se queda de solo lectura. Diseños, acciones, ponentes e incidencias se importan
desde la exportación, con su mapa de campos en `.local/` (ADR-0008).

## 8. Preguntas abiertas

1. ¿Sigue en uso el perfil de empresa? Si sí, ¿qué campos rellena?
2. ¿La curaduría es siempre el área, o también la asesoría puede curar lo suyo?
3. ¿Se unifican el catálogo TIC y los cursos recomendados de otros programas, o
   se mantienen como catálogos separados?
4. ¿Se sigue usando el sistema de incidencias? La exportación dice que sí.
5. ¿Qué webs consumen hoy los listados como API y quién las mantiene?

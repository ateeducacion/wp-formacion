---
id: ADR-0029
title: "La incidencia deja constancia de un cambio, y nadie la resuelve"
status: Propuesta
date: 2026-11-26
related:
  issues: [23, 61]
  prs: []
  sdds: []
  adrs: [ADR-0007, ADR-0020, ADR-0027]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-fable-5-2"
---

# ADR-0029: La incidencia deja constancia de un cambio, y nadie la resuelve

## Estado

Propuesta

## Contexto

La incidencia pedía qué se quería, los motivos, los cambios que se proponían y
si llevaban coste, y el servicio de perfeccionamiento la aprobaba o la denegaba
([ADR-0020](ADR-0020-la-situacion-se-calcula-y-la-accion-se-entrega.md)). Una
campana contaba en la cabecera las que esperaban resolución.

En la tercera revisión con la coordinación se vio que eso venía del sistema
anterior y que nadie lo usa así:

- El servicio **no aprueba nada** en el aplicativo: pone el expediente de la
  acción y nada más. El cambio se tramita por su propio cauce.
- «Motivos» y «cambios que se proponen» eran escribir dos veces lo mismo, y el
  coste no lo decide quien abre la incidencia.
- Con un cambio de fechas, **la acción seguía en su día viejo** en el
  calendario hasta que alguien con permiso la editaba.
- No se distinguía un cambio de ponente de cualquier otro cambio.

## Problema

¿Qué es una incidencia, si nadie la resuelve, y qué le hace a su acción?

## Factores de decisión

- Quitar lo que no se usa: cada campo de más es una duda al rellenarlo.
- Que el calendario diga la verdad en cuanto se sabe el cambio.
- No perder lo que ya está guardado.
- Que el cauce de fuera —el sistema del servicio, su parte de cancelación— no
  se olvide, sin nombrarlo en el repositorio
  ([ADR-0002](ADR-0002-se-hereda-la-plataforma-del-aplicativo-de-eventos.md)).

## Alternativas consideradas

### Opción 1: dejar la resolución, pero opcional

- A favor: no se quita nada.
- En contra: una incidencia «pendiente» para siempre es ruido, y la campana
  cuenta algo que nadie va a vaciar.

### Opción 2: la incidencia es un apunte, y hace lo que pide al enviarla

- A favor: un paso menos y un calendario al día. Es como se trabaja.
- En contra: nadie revisa una cancelación antes de que surta efecto.

## Decisión

**Opción 2.**

- **El tipo de incidencia** (`IncidentMetaKeys::KINDS`): cambio de fechas
  (`postpone`), cambio de ponente (`speaker`), cancelación (`cancel`) u otro
  cambio (`change`). Una sola lista: la ficha, los listados y el correo lo
  nombran igual, por lo que es y no por lo que «se pide», que ya nadie
  concede.
- **Un solo cuadro obligatorio, «Justificación»** (`fmc_reason`). Los cambios
  propuestos, el coste, la resolución y sus observaciones salen de la ficha. Sus
  claves siguen registradas: lo guardado se conserva y no se enseña.
- **Lo que hace al enviarla** (`Editor::incident_effect()`):
  - una cancelación deja la acción cancelada;
  - un cambio de fechas con la fecha nueva **pasa la acción a esas fechas**. Sin
    fecha de fin nueva, dura lo que duraba. Sus sesiones
    ([ADR-0027](ADR-0027-las-sesiones-de-la-accion-son-una-lista-en-una-meta.md))
    se mueven los mismos días; si no caben en las fechas nuevas, se quitan,
    porque una sesión no puede quedar fuera de las fechas de su acción. La
    incidencia guarda las fechas que tenía (`fmc_old_start`, `fmc_old_end`);
  - sin fecha nueva, la acción queda aplazada, como antes;
  - un cambio de ponente u otro solo queda apuntado.
  Una acción cancelada no vuelve a la vida por cambiarle las fechas.
- **No hay resolución, ni cola, ni campana.** La chapa de la acción dice qué
  fue su última incidencia, o cuántas tiene; el listado de incidencias se
  filtra y se cuenta por su tipo.
- **La cancelada sale tachada** en el calendario y en la lista, y **no va en el
  CSV** de acciones salvo que se filtre por «Cancelada»: es la hoja con la que
  se cuenta lo impartido.
- **El aviso al enviar.** Enviar la incidencia pide confirmar siempre: no se
  puede modificar después, y una cancelación o un cambio de fechas se aplican en
  el momento (`Editor::INCIDENT_WARNING`). El mismo aviso recuerda, en su
  párrafo, que el cambio hay que tramitarlo además en el sistema de gestión del
  servicio de perfeccionamiento (`Editor::INCIDENT_REMINDER`); el despliegue lo
  dice con sus palabras —el nombre de su plataforma, su impreso— con el filtro
  `fmc_incident_reminder`, o lo quita. Sin JavaScript, va escrito sobre la
  ficha.
- **El correo** dice en el asunto de qué tipo es, y lleva los dos expedientes, las
  fechas que tenía, las nuevas y la justificación.

Esto cambia dos consecuencias de
[ADR-0020](ADR-0020-la-situacion-se-calcula-y-la-accion-se-entrega.md), que
lleva su adenda: la resolución ya no existe, y la incidencia sí cambia las
fechas.

Lo prueban `tests/unit/test-incidents.php`, `test-lists.php`,
`test-editor-render.php` y `tests/js/fmc-app.test.js`.

## Consecuencias

### Positivas

- Una pantalla más corta, y el calendario al día sin que nadie edite la acción.
- La cola de incidencias se lee de un vistazo: cuántas cancelaciones, cuántos
  cambios de ponente.
- El recuento de lo impartido no hay que limpiarlo a mano.

### Negativas

- Una cancelación surte efecto al enviarla, sin que nadie la mire antes, y **no
  se deshace desde la pantalla**: la situación guardada solo la cambia quien
  administra.
- Las sesiones que no caben en las fechas nuevas se pierden, y hay que volver a
  ponerlas. La ficha lo dice al pedir la fecha.
- Las incidencias anteriores no dicen qué pedían: salen en el total, sin clase.
- Sin el filtro del aviso, nada recuerda el trámite de fuera.

### Neutras

- La capacidad de resolver (`fmc_resolve_incidents`) sigue repartida y sin uso.
  Quitarla es otro cambio.

---
id: ADR-0020
title: "La situación de la acción se calcula, y la acción se entrega"
status: Propuesta
date: 2026-10-27
related:
  issues: [23, 24]
  prs: []
  sdds: []
  adrs: [ADR-0006, ADR-0007, ADR-0010, ADR-0012]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0020: La situación de la acción se calcula, y la acción se entrega

## Estado

Propuesta

## Contexto

La sección «Estado» de la acción tenía:

- el **proceso** (en borrador, pagada, entregada, cerrada), que llevaba la asesoría;
- la **situación** (gestionándose, impartiéndose, realizada, aplazada, cancelada), el expediente, el negociado y el responsable del servicio, que llevaba el servicio de formación.

La asesoría podía editar su acción también una vez publicada. La issue #24
preguntaba si a la asesoría había que ocultarle esa sección o enseñársela de
solo lectura.

La validación contestó, en la #24 y en la #23 (protocolo de incidencias):

- **La situación es automática.** En blanco por defecto, «impartiéndose» en
  las fechas de la formación y «realizada» cuando pasan sin incidencia.
- **Aplazada o cancelada sale de una incidencia.** La abre la asesoría, aquí y
  en el sistema de gestión del servicio. La aplazada pide fechas nuevas de
  inicio y de fin.
- **Una cancelada no suma horas** en el recuento de lo impartido.
- **El proceso sobra.** La acción se guarda en borrador o se entrega.
- **El expediente lo pone solo el servicio de formación.**
- **El negociado y el responsable del servicio no hacen falta.**
- **La incidencia se lleva en la plataforma** y se avisa por correo a quien
  tiene que gestionarla.

## Problema

¿Cómo se obtiene la situación de una acción, qué supone entregarla, y qué
hace una incidencia sobre ella?

## Factores de decisión

- Que la situación no se pueda dejar sin poner al día: si se teclea, se
  queda vieja.
- Que lo entregado no cambie sin dejar rastro.
- Que lo proteja el servidor
  ([ADR-0006](ADR-0006-los-permisos-se-deciden-en-el-servidor-campo-a-campo.md)).
- Ningún correo de la organización en el repositorio.

## Alternativas consideradas

### Opción 1: guardar la situación y ponerla al día con una tarea programada

- A favor: se filtra y se cuenta con una consulta simple.
- En contra: depende de que la tarea corra. Entre dos pasadas, la situación
  miente, y una acción cuyas fechas se cambian queda mal hasta la siguiente.

### Opción 2: calcularla al leerla, y guardar solo lo que deja una incidencia

- A favor: siempre está al día. Solo se guarda lo que no sale de las fechas:
  aplazada o cancelada.
- En contra: contar y filtrar por situación pide una consulta de fechas, no
  una meta. Una acción migrada con una situación tecleada pierde ese dato,
  salvo si era aplazada o cancelada.

## Decisión

**Opción 2.**

- **La situación** se calcula en `Domain\Situation::of()`, que es pura:
  - en blanco si no ha empezado o no tiene fecha de inicio;
  - impartiéndose desde el inicio hasta el fin, ambos incluidos (sin fecha de
    fin, la acción dura un día);
  - realizada cuando pasa.

  Lo guardado en `fmc_situation` solo cuenta si es `postponed` o `cancelled`,
  y entonces manda sobre las fechas (`ActionMetaKeys::situation()`).
  «Gestionándose» desaparece. Los contadores, el filtro, la chapa, el calendario
  y el CSV la calculan; los contadores y el filtro lo hacen con la misma regla en
  una consulta de fechas (`Lists::situation_clause()`).
- **La incidencia** gana «Qué se pide»: un cambio, aplazarla o cancelarla
  (`IncidentMetaKeys::REQUEST`). Para aplazarla, pide la fecha nueva de inicio
  y de fin, si ya se saben. Al enviarla, la acción queda aplazada o cancelada.
  Una aplazada vuelve a ir por sus fechas cuando se le cambia la de inicio.
- **El aviso.** Al abrir una incidencia se manda un correo con lo que se pide y
  el enlace (`Editor::notify_incident()`). Los destinatarios los pone cada
  despliegue con el filtro `fmc_incident_recipients`, vacío por defecto: sin
  destinatarios, no se manda nada.
- **Una cancelada no cuenta horas.** Ni en lo impartido de los ponentes
  ([ADR-0012](ADR-0012-un-diseno-puede-sustituir-a-otro-y-los-ponentes-se-buscan-por-lo-impartido.md)),
  ni en las horas totales de la ficha y del CSV.
- **Borrador o entregada.** La acción se guarda en borrador (`draft`) o se
  entrega (`publish`).
  - El borrador lo ven quien lo escribe y quien gestiona todas las acciones, y
    el calendario lo marca como tal.
  - Entregada, la asesoría ya no la edita: pierde
    `edit_published_fmc_actions`, también en los sitios ya desplegados
    (`PostTypes::revoked_caps()`). Los cambios los pide con una incidencia,
    que puede abrir sobre la acción que entregó (`Editor::can_open_incident()`).
  - La validación sigue editándola y puede devolverla a borrador.
- **El proceso** (`fmc_process`) y **el negociado y el responsable del
  servicio** salen de la ficha. Siguen registrados para lo migrado.
- **El expediente** lo escriben el servicio de formación y la administración.
  La validación pierde `fmc_edit_service_fields`, también en los sitios ya
  desplegados.

Lo prueban `tests/unit/test-situation.php`, `test-incidents.php`,
`test-lists.php` y `test-calendar.php`.

## Consecuencias

### Positivas

- La situación está siempre al día y nadie tiene que acordarse de cambiarla.
- La sección «Estado» se queda en el expediente y poco más. La pregunta de la
  #24, ocultar o no, deja de tener objeto.
- Lo entregado solo cambia por incidencia, que queda registrada y avisa.

### Negativas

- La asesoría ya no corrige una errata en lo que ha entregado: lo pide con una
  incidencia, o se lo pide a la validación.
- Contar por situación es una consulta de fechas más pesada que leer una meta.
  No está medida con miles de acciones.
- Lo que se tecleó a mano en el sistema anterior como «realizada» o
  «impartiéndose» se descarta. Si las fechas están mal, la situación también.
- Si nadie pone los destinatarios del aviso, no se manda ningún correo, y no
  hay otra señal de que falta.

### Neutras

- La resolución de la incidencia (aprobada o denegada) la sigue poniendo el
  servicio, y no cambia la situación.
- Una incidencia no cambia las fechas de la acción: las fechas nuevas que trae
  las pone quien gestiona la acción.

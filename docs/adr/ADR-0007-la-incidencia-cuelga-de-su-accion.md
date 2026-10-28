---
id: ADR-0007
title: "La incidencia cuelga de su acción"
status: Aceptada
date: 2026-09-30
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0006]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0007: La incidencia cuelga de su acción

## Estado

Aceptada

## Contexto

Hoy la incidencia se abre desde un enlace que lleva el identificador de la
acción en la URL, y el formulario copia de ahí los datos de la acción. Cambiar
el número abre una incidencia sobre la acción de otro.

## Decisión

`fmc_incident` es un tipo propio cuyo **`post_parent` es la acción**. Lo fija
el aplicativo al crearla, después de comprobar que quien la abre puede editar
esa acción. Los datos de la acción no se copian: se leen del padre. La
resolución solo la escribe quien tiene `fmc_resolve_incidents` (ADR-0006).

## Consecuencias

### Positivas

- Sin datos duplicados que se desincronicen.

### Negativas

- `post_parent` entre tipos distintos no lo pinta el escritorio de WordPress:
  la pantalla de la acción tiene que listar sus incidencias.

## Adenda — 2026-10-28

**La cola de lo pendiente se ve sin buscarla.** Una incidencia se publica al
abrirse y nace pendiente, así que la lista de incidencias enseñaba lo resuelto
y lo pendiente mezclado, y quien las resuelve tenía que filtrar a mano cada
vez para saber si tenía trabajo. Dos cambios, los dos de pantalla:

- **La lista de incidencias se abre por las pendientes.** Si la URL no trae
  filtro de resolución, se aplica «Pendiente»; el desplegable en «Todas» y el
  contador del total lo quitan. Como `Screen::url()` no escribe parámetros
  vacíos, «sin filtrar» se dice con el valor `all`, que no es ninguna
  resolución y por eso no filtra.
- **Una campana en la cabecera con el número de pendientes**, que lleva a esa
  misma lista. La ve **solo quien las resuelve** (`fmc_resolve_incidents`): es
  su cola de trabajo y se vacía al resolverlas. La asesoría no la ve: sus
  incidencias pendientes no las puede resolver ella, así que el número no
  bajaría nunca, y avisarle de que ya le han contestado pide guardar por
  persona hasta dónde ha visto, que es otra decisión y no se toma aquí.

**Pendiente es también la incidencia sin la meta `fmc_resolution`.** El
aplicativo la escribe al abrirla, pero lo que entra por el importador no la
trae, y esa es justo la que hay que ver: la condición es
`IncidentMetaKeys::resolution_clause()`, con `NOT EXISTS`, y la usan el filtro,
los contadores y la campana, para que los tres cuenten lo mismo.


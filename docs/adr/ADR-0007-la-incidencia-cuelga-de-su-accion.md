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

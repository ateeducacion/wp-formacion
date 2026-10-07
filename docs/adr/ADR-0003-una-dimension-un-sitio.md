---
id: ADR-0003
title: "Una dimensión, un sitio"
status: Aceptada
date: 2026-09-30
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0004]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0003: Una dimensión, un sitio

## Estado

Aceptada

## Contexto

En el sistema actual, una misma casilla del diseño dice a la vez si está en el
catálogo, si el diseño está finalizado y si pertenece a un programa; la acción
tiene tres listas de «quién la asume» que repiten los mismos programas; el tipo
de formación tiene «Curso» e «Histórico-curso»; y el curso escolar se guarda
como texto calculado al grabar. Quien lo mantiene lo describe como «no
normalizado». El recuento está en `.local/`.

## Problema

¿Dónde va cada dato clasificatorio para que no vuelvan a mezclarse ejes?

## Factores de decisión

- Filtrar y contar por cada eje por separado.
- Que un dato derivable no se pueda contradecir.
- Que los vocabularios de la organización no entren en el código (ADR-0002).

## Alternativas consideradas

### Opción 1: todo en taxonomías

Abiertas: es justo lo que degeneró.

### Opción 2: todo en listas cerradas en código

Obliga a desplegar para añadir un programa nuevo.

### Opción 3: cada eje donde le toca

## Decisión

- **Estado del diseño** → `post_status` (ADR-0004).
- **Catálogo** → meta booleana `fmc_in_catalogue`.
- **Programa**, **temática**, **área de competencia digital**, **ámbito** →
  taxonomías (`fmc_programme`, `fmc_topic`, `fmc_competence`, `fmc_scope`):
  cambian con los años y los crea cada instalación.
- **Tipo de formación**, **modalidad**, **quién paga**, **proceso**,
  **situación** → listas cerradas en código: gobiernan comportamiento.
- **«Histórico»** no es un valor: se deduce de las fechas.
- **Curso escolar** y **totales** → se calculan, no se guardan.

## Consecuencias

### Positivas

- Un filtro por eje, sin listas de exclusión escritas a mano.

### Negativas

- La migración tiene que **partir** cada campo mezclado en sus ejes, y habrá
  valores que no casen; se revisan a mano (ADR-0008).

### Neutras

- Añadir un tipo de formación pide un cambio de código y una versión.

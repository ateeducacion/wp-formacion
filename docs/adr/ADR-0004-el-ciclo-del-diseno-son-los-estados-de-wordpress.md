---
id: ADR-0004
title: "El ciclo del diseño son los estados de WordPress"
status: Propuesta
date: 2026-09-30
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0003, ADR-0006]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0004: El ciclo del diseño son los estados de WordPress

## Estado

Propuesta

## Contexto

La plataforma se usa como mesa de trabajo: un diseño se escribe durante semanas
antes de estar listo. Hoy «diseño finalizado» es una casilla que marca quien la
vea, y nada impide seguir editando un diseño ya finalizado. En la revisión con quien
lo usa se pide que **finalizar lo haga quien cura** y que, finalizado, la
asesoría **ya no pueda editarlo**.

## Problema

¿Cómo se representa y se protege el ciclo borrador → revisión → finalizado?

## Factores de decisión

- Que la regla la haga cumplir el servidor, no la pantalla.
- Revisiones, papelera y bloqueo de edición sin escribirlos.

## Alternativas consideradas

### Opción 1: una meta de estado con su guardián

Hay que reescribir lo que WordPress ya trae.

### Opción 2: los estados nativos y capacidades por tipo

`draft` → `pending` → `publish`, con `capability_type` propio del tipo.

## Decisión

**Opción 2.** `publish` se rotula «Diseño finalizado». La asesoría tiene
`edit_fmc_designs` y `delete_fmc_designs`, pero **no** `publish_fmc_designs` ni
`edit_published_fmc_designs`: puede escribir y mandar a revisión, no finalizar
ni tocar lo finalizado. La curaduría tiene todas. Está probado en
`tests/unit/test-post-types.php`.

Queda en propuesta hasta confirmar con quien gestiona la formación si la
curaduría es siempre el área (REQ-0001 §8).

## Consecuencias

### Positivas

- Cero código de estado; la regla la aplica `map_meta_cap`.

### Negativas

- Para corregir una errata en un diseño finalizado, la asesoría tiene que
  pedírselo a la curaduría o que esta lo devuelva a borrador.

### Neutras

- Un diseño devuelto a borrador deja de verse en el catálogo mientras tanto.

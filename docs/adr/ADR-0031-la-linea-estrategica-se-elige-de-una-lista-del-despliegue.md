---
id: ADR-0031
title: "La línea estratégica se elige de una lista que da el despliegue"
status: Propuesta
date: 2026-11-26
related:
  issues: [54]
  prs: []
  sdds: []
  adrs: [ADR-0002, ADR-0003]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-fable-5-2"
---

# ADR-0031: La línea estratégica se elige de una lista que da el despliegue

## Estado

Propuesta

## Contexto

«Línea estratégica» era un texto libre en la acción, y cada cual escribía la
misma línea con sus palabras. Se pidió que saliera de una lista. Las líneas las
fija la norma de cada administración, son pocas y cambian con ella.

## Problema

¿Dónde vive la lista de líneas?

## Factores de decisión

- Una lista abierta acaba con el mismo término escrito de tres maneras
  ([ADR-0003](ADR-0003-una-dimension-un-sitio.md)).
- Los nombres son de una administración concreta: no pueden ir en el código
  ([ADR-0002](ADR-0002-se-hereda-la-plataforma-del-aplicativo-de-eventos.md)).
- Lo guardado no se puede perder.

## Alternativas consideradas

### Opción 1: una constante en el código

- A favor: nada que configurar.
- En contra: publica los nombres de una norma ajena, y cambiarla pide una
  versión nueva.

### Opción 2: una taxonomía

- A favor: se mantiene desde el escritorio.
- En contra: es la lista abierta que se quiere evitar.

### Opción 3: una copia de una fuente externa

- A favor: se pone al día sola.
- En contra: esa fuente no existe, y para dos o tres valores es demasiada
  maquinaria.

### Opción 4: una lista cerrada que da el despliegue

- A favor: cerrada como una constante, sin nombrar a nadie en el repositorio. Es
  lo que ya se hace con quién paga.
- En contra: cambiarla es tocar el snippet de despliegue.

## Decisión

**Opción 4.**

- `ActionMetaKeys::strategic_lines()` lee el filtro `fmc_strategic_lines`, que
  devuelve los nombres. De serie, ninguno.
- El campo es un desplegable (`closed` en `Fields`): no admite un valor de fuera
  de la lista. Se sigue guardando el nombre, como estaba.
- **Lo guardado que ya no está en la lista se conserva** y se ve marcado, «ya no
  se usa», hasta que quien edita elija otro.
- **Sin líneas, el campo no sale**: no hay nada que poner en él.

Lo prueba `tests/unit/test-editor-save.php`.

## Consecuencias

### Positivas

- Se acaba la misma línea escrita de varias formas.
- El repositorio no dice de qué norma son.

### Negativas

- Una instalación que no pone el filtro se queda sin el campo, y nada lo avisa.
- Renombrar una línea deja las acciones anteriores con el nombre viejo.

### Neutras

- Lo migrado no se convierte: se queda con lo que traía.

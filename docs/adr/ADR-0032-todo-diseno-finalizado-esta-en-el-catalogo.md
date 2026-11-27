---
id: ADR-0032
title: "Todo diseño finalizado está en el catálogo público"
status: Propuesta
date: 2026-11-27
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0003, ADR-0004, ADR-0013, ADR-0016, ADR-0017, ADR-0025, ADR-0028]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-fable-5-2"
---

# ADR-0032: Todo diseño finalizado está en el catálogo público

## Estado

Propuesta

## Contexto

La ADR-0003 separó tres cosas que el sistema anterior mezclaba en una casilla:
el estado del diseño, si sale en el catálogo público y a qué programa
pertenece. El catálogo quedó en una meta booleana, `fmc_in_catalogue`, que la
coordinación marcaba en la ficha («Visible en el catálogo público»), y un ojo
junto al título lo decía en el listado.

En la primera semana de uso, la coordinación no supo decir por qué un curso
sin imagen salía en el catálogo, pasó a borrador seis diseños que no estaban
en él creyendo que sí, y buscó en el listado un «botón» que era la casilla de
la ficha. En la revisión del 27 de noviembre de 2026 pidió quitarla: «si no la
quiero visible es porque está en borrador».

## Problema

¿Hace falta que un diseño pueda estar finalizado y, a la vez, fuera del
catálogo público?

## Factores de decisión

- Que quien mantiene el catálogo sepa, sin pensar, por qué un curso sale o no.
- Lo que ve la asesoría: solo los diseños finalizados, y solo con ellos puede
  agendar una acción (ADR-0016, ADR-0017).
- Lo que se publica de un diseño del catálogo: su ficha, su imagen y su ficha
  técnica, a cualquiera (ADR-0013, ADR-0025, ADR-0028).

## Alternativas consideradas

### Opción 1: dejar la casilla

Permite el diseño que la asesoría ya puede agendar pero no se anuncia al
profesorado. En los datos migrados los hay —está medido sobre la exportación—:
formaciones internas, cursos a los que aún les falta la imagen. En contra: son
dos interruptores para una pregunta, y quien los usa se ha equivocado con
ellos la primera semana.

### Opción 2: finalizado es estar en el catálogo

Un interruptor: el estado. Lo que no deba verse se deja en borrador o se pasa
al histórico. En contra, y no es poco: **desaparece el diseño agendable y no
anunciado**. Un diseño en borrador no lo ve la asesoría ni puede agendarlo;
para que lo agende hay que finalizarlo, y entonces sale en el catálogo, con su
ficha técnica descargable por cualquiera.

### Opción 3: un tercer estado, «finalizado, sin publicar»

Conserva el caso de la opción 1 con un solo control. Es volver a tener tres
estados del diseño el día después de haber quitado «En revisión» por sobrar
(adenda de ADR-0004).

## Decisión

Haremos la **opción 2**. `Catalogue::is_listed()` es «diseño finalizado», y de
ahí cuelgan la lista, la ficha, los datos para otras webs y la descarga
pública de la imagen y de la ficha técnica. Salen la casilla de la ficha, el
ojo del listado, el filtro «Catálogo» y el registro de la meta.

Esto corrige a la ADR-0003 en un punto: el catálogo deja de ser una dimensión
con su sitio propio y pasa a ser consecuencia del estado. El programa sigue
siendo un término.

## Consecuencias

### Positivas

- Una sola regla, que cabe en una frase de la ayuda.
- Menos código: una meta, un campo, un filtro y un icono menos.

### Negativas

- **Al desplegar, todo diseño finalizado que no tuviera la casilla marcada
  aparece en el catálogo**, con su ficha técnica pública. Antes de desplegar,
  la coordinación tiene que repasar esos diseños y dejar en borrador los que
  no deban verse.
- Un diseño que no deba anunciarse no se puede agendar: en borrador, la
  asesoría no lo ve. Si ese caso vuelve, es la opción 3, con ADR nueva.
- El valor de `fmc_in_catalogue` se queda en la base de datos sin que nadie lo
  lea. Volver atrás es volver a registrarla; los datos no se han tocado.

### Neutras

- Las webs que piden el catálogo (ADR-0028) no cambian: reciben más cursos.

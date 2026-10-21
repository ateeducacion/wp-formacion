---
id: ADR-0005
title: "La matrícula no es de este aplicativo"
status: Aceptada
date: 2026-09-30
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0003]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0005: La matrícula no es de este aplicativo

## Estado

Aceptada

## Contexto

El profesorado se matricula en el sistema de gestión del servicio de formación,
no en este sitio. Aquí se registra la acción —con su enlace de matrícula y sus
plazos— y, al final, sus cifras. En la reunión se dice expresamente: «aquí no
se suben los usuarios ni se matriculan».

## Problema

¿Recoge el aplicativo nuevo la matrícula, como hace el de eventos con su
inscripción?

## Decisión

**No.** Ni formulario de matrícula, ni listas de admitidos. La acción guarda el
enlace, las fechas del proceso y las cifras finales por sexo; los totales se
calculan.

## Consecuencias

### Positivas

- Mucho menos dato personal: solo el de los ponentes.

### Negativas

- Las cifras se teclean a mano desde el otro sistema, como hoy.

### Neutras

- Si algún día el otro sistema ofrece una API, las cifras se pueden leer de
  ahí con otra ADR.

## Adenda — 2026-10-21: las cifras finales también salen

En la revisión con la curaduría del 2026-10-21 se confirma que las cifras
finales y el cierre de la acción tampoco se llevan aquí: se gestionan en el
sistema de gestión del servicio de formación. La
[ADR-0010](ADR-0010-las-cifras-de-cierre-salen-de-la-ficha-de-la-accion.md)
(propuesta) las saca de la ficha, del resumen y del CSV, y deja sus claves
registradas para no perder lo migrado. Si se acepta, la frase «la acción
guarda […] las cifras finales por sexo; los totales se calculan» deja de
regir. La decisión de esta ADR —aquí no hay matrícula— no cambia.

Asistencia de IA: Claude Code (claude-opus-5-5).

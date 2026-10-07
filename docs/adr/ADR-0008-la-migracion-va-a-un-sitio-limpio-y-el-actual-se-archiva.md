---
id: ADR-0008
title: "La migración va a un sitio limpio y el actual se archiva"
status: Propuesta
date: 2026-09-30
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0002, ADR-0003]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0008: La migración va a un sitio limpio y el actual se archiva

## Estado

Propuesta

## Contexto

Se va a sustituir el sitio entero. En el actual conviven con la formación viva
procesos ya cerrados —acreditaciones, rúbricas, el plan de un curso concreto—,
decenas de vistas y páginas, y listados que otras webs consumen como API. La
foto del 2026-09-30 está en `.local/`.

## Problema

¿Se transforma el sitio actual en su sitio, o se monta uno nuevo y se importa?

## Alternativas consideradas

### Opción 1: transformar en su sitio

Arrastra todo lo que no se quiere, y cualquier fallo se ve en producción.

### Opción 2: sitio limpio + importación + archivo

## Decisión

**Opción 2**, la misma que se tomó en eventos:

1. El sitio actual pasa a una dirección de archivo y queda **de solo lectura**.
2. En la dirección de siempre se monta un sitio limpio con este aplicativo.
3. Un importador idempotente lee la exportación —**nunca el sitio vivo**— y crea
   diseños, acciones, ponentes e incidencias con su mapa de campos, que vive en
   `.local/` porque describe el sistema anterior por dentro.
4. Los procesos cerrados no se importan: se consultan en el archivo.
5. Los listados que consumen otras webs se mantienen en su dirección o se
   avisa con fecha (REQ-0001 RNF-5).

## Consecuencias

### Positivas

- Se puede ensayar la migración entera en local tantas veces como haga falta.

### Negativas

- Hay que conservar los identificadores o redirigir los enlaces antiguos que
  estén compartidos.
- Hasta el corte, lo que se escriba en el sitio actual hay que reimportarlo.

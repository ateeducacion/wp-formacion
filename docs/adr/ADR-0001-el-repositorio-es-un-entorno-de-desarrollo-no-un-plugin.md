---
id: ADR-0001
title: "El repositorio es un entorno de desarrollo, no un plugin"
status: Aceptada
date: 2026-09-30
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0002]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0001: El repositorio es un entorno de desarrollo, no un plugin

## Estado

Aceptada

## Contexto

El sitio de formación vive, como el de eventos, en un subsitio de un multisitio
que no es nuestro. No admite despliegue de ficheros: el único canal es pegar
código en Code Snippets desde el panel. Hoy todo lo que hace de aplicativo son
formularios, vistas y fragmentos de código del gestor de formularios, sin
versionar ni probar. El inventario está en `.local/`. Lo que se hereda de eventos está en la [ADR-0002](ADR-0002-se-hereda-la-plataforma-del-aplicativo-de-eventos.md).

## Problema

¿Cómo versionar, revisar y probar un aplicativo cuyo único canal de despliegue
es el panel de administración?

## Factores de decisión

- Producción no instala plugins propios, y en un multisitio eso es de la red.
- Trazabilidad: diffs, PR y CI.
- Un entorno local reproducible con un comando.

## Alternativas consideradas

### Opción 1: un plugin

Estructura estándar, pero **no se puede instalar** en el destino.

### Opción 2: seguir en el panel

Es lo que hay: ni versiones, ni tests, ni revisión.

### Opción 3: repositorio de desarrollo que genera snippets

El código vive en `src/Fmc/`, `make bundle` lo empaqueta en un snippet y
`make sync-snippets` / `npm run snippets` lo llevan al WordPress local o al de
destino.

## Decisión

Opción 3, igual que en eventos: **el repositorio es un entorno de desarrollo**.
No lleva cabecera de plugin, ni `readme.txt`, ni hooks de activación.

## Consecuencias

### Positivas

- Todo cambio pasa por Git, CI y revisión.
- El mismo código corre en wp-env, en Playground y en producción.

### Negativas

- El bundle es un fichero grande que no se edita a mano, y hay que acordarse de
  regenerarlo.

### Neutras

- `make check-plugin` pasa Plugin Check sobre un envoltorio desechable, porque
  el código sí es el que corre dentro de WordPress.

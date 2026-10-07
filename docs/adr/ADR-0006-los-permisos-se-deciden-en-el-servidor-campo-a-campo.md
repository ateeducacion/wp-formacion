---
id: ADR-0006
title: "Los permisos se deciden en el servidor, campo a campo"
status: Aceptada
date: 2026-09-30
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0004, ADR-0007]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0006: Los permisos se deciden en el servidor, campo a campo

## Estado

Aceptada

## Contexto

El sistema actual esconde a cada perfil los campos que no le tocan, pero el
servidor guarda lo que le llegue. Así, cualquiera que mande el campo a mano
—y hoy basta con pedírselo a un asistente de IA— escribe el número de
expediente, se aprueba su propia incidencia o edita la entrada de otro.

## Problema

¿Dónde se decide quién escribe cada cosa?

## Decisión

En el servidor, en dos mapas y en ningún otro sitio:

- **Por tipo:** `PostTypes::role_caps()` dice qué primitivas tiene cada rol en
  cada tipo; `map_meta_cap` resuelve el resto (propio, ajeno, publicado).
- **Por campo:** `MetaRegistration::guarded()` dice qué clave pide una
  capacidad propia —`fmc_edit_service_fields` para el expediente, la situación y el personal del servicio,
  `fmc_resolve_incidents` para la resolución— y el `auth_callback` de la meta
  la exige. Lo demás pide poder editar el post.
- **Valores:** cada clave se sanea por su tipo, y las de lista cerrada guardan
  vacío si el valor no está en la lista.

Las pantallas propias, cuando lleguen, **preguntan** a estos mapas; no deciden.

## Consecuencias

### Positivas

- Un campo que no te toca no se guarda aunque lo mandes. Probado en
  `tests/unit/test-meta.php`.

### Negativas

- `update_post_meta()` directo no pasa por el `auth_callback`: las pantallas
  propias tienen que escribir con `MetaRegistration::can_write()` delante, y un
  test por pantalla que lo compruebe. El `edit_post_meta` del núcleo no sirve
  para el servicio de formación: exige antes poder editar el post entero, que
  es justo lo que el servicio no tiene.
- Una incidencia enviada ya no la edita quien la abrió (no tiene
  `edit_published_fmc_incidents`): si hay que corregirla, se abre otra.

### Neutras

- El acotado por ámbito (`fmc_scope`) es un tercer mapa, y llega con el
  guardián de acceso en la fase 2.

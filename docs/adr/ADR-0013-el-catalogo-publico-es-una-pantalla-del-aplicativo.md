---
id: ADR-0013
title: "El catálogo público es una pantalla del aplicativo y publica solo parte del diseño"
status: Propuesta
date: 2026-10-21
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0001, ADR-0002, ADR-0004, ADR-0006]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0013: El catálogo público es una pantalla del aplicativo y publica solo parte del diseño

## Estado

Propuesta

## Contexto

REQ-0001 pide un catálogo público de diseños finalizados, con búsqueda y
filtros por modalidad y tipo (RF-6), y que estar en él sea una decisión aparte
de estar finalizado (RF-4, ADR-0004). Estaba previsto para la fase 3 del
PLAN-0001.

Hasta ahora, `fmc_design` está registrado como público
(`src/Fmc/PostType/PostTypes.php`) y sin archivo. El tema le da a cada diseño
finalizado una página en `/fmc_design/<nombre>/`, y la ficha del diseño la
enlazaba con «Ver la ficha pública». Esa página tiene dos problemas:

- Se ve para **cualquier** diseño finalizado, esté o no en el catálogo. Un
  diseño que la curaduría no ha puesto en el catálogo se lee igual si se tiene
  la dirección.
- Pinta lo que el tema sepa pintar, que es el título y la descripción. Ni los
  datos del curso ni el perfil de competencia digital.

Tampoco hay lista que reúna esas páginas. En la revisión del 2026-10-21 se
preguntó cómo ver el catálogo, y la respuesta era que no se podía.

## Problema

¿Dónde vive el catálogo público, qué enseña de cada diseño, y qué pasa con la
página que el tema ya da a cada uno?

## Factores de decisión

- Todo se despliega como Code Snippets (ADR-0001): no hay plantillas de tema
  que subir, y una página que haya que crear a mano en cada sitio se puede
  borrar por error.
- Lo que no está en el catálogo no se publica (RF-4).
- La ficha del diseño mezcla lo que se ofrece al profesorado con lo de trabajo
  de la curaduría y las asesorías.
- La piel tiene que ser la del aplicativo, que es la de los hermanos.

## Alternativas consideradas

### Opción 1: el archivo del tipo y las plantillas del tema

Activar `has_archive` y dejar que el tema pinte la lista y la ficha.

- A favor: es lo que WordPress hace solo.
- En contra: el tema no sabe filtrar por «está en el catálogo», ni por tipo o
  modalidad, ni qué campos se publican. Haría falta una plantilla, y aquí no
  se despliegan ficheros (ADR-0001).

### Opción 2: un shortcode en una página

`[fmc_catalogo]` en una página que cree quien despliega.

- A favor: el catálogo hereda la cabecera y el menú del sitio.
- En contra: una página más que crear y que mantener en cada sitio, y la piel
  la pone el tema, no el aplicativo.

### Opción 3: una pantalla del aplicativo, sin sesión

Como el resto del aplicativo, un parámetro de la portada: `?fmc_catalogo=1`
para la lista y `?fmc_curso=ID` para la ficha. Se ven sin entrar.

- A favor: nada que crear. La misma piel y el mismo pie (`fmc_chrome`), y una
  sola dirección para enlazar desde fuera.
- En contra: es la primera pantalla del aplicativo que se ve sin sesión, así que
  hay que vigilar qué deja salir. No hereda el menú del sitio: quien quiera
  integrarla en otra web la enlaza o la incrusta.

## Decisión

**Opción 3.** Lo implementa `src/Fmc/PublicFront/Catalogue.php`, y
`Screen::route()` lo atiende antes de pedir sesión.

- **Qué sale.** Solo los diseños **finalizados** y **en el catálogo**, para
  cualquiera, también con sesión: ni la administración ve borradores en el
  catálogo. Lo que no cumple las dos cosas responde «no encontrado» (404).
- **Qué se publica de cada uno.** Código, tipo, modalidad, horas, programas y
  temática, y estos campos: descripción, destinatarios, objetivos y
  contenidos. También el resumen del perfil de competencia digital, con su
  enlace a la herramienta (RF-5). La lista está en
  `Catalogue::PUBLIC_FIELDS`.
- **Qué no se publica.** Metodología, fase práctica, temporalización,
  observaciones, autoría y documentos. Son de trabajo. Si la curaduría quiere
  publicar alguno, se añade a la lista.
- **La página del tema** de un diseño redirige (301) a su ficha del catálogo,
  que es la que sabe qué se enseña. Así se cierra la puerta de los diseños
  finalizados que no están en el catálogo.
- **Los enlaces.** El listado de diseños lleva «Ver el catálogo público». La
  ficha del diseño lleva «Ver en el catálogo», y solo si está en él.
- **La búsqueda** mira el título y el código, con los filtros de tipo,
  modalidad y temática.

## Consecuencias

### Positivas

- El catálogo se ve, desde hoy, sin crear nada.
- Un diseño fuera del catálogo deja de verse por su dirección.
- Qué es público está escrito en un sitio y lo comprueba un test
  (`tests/unit/test-catalogue.php`).

### Negativas

- Los campos que se publican los ha propuesto el desarrollo, no la curaduría.
  Hay que confirmarlos en la siguiente sesión de la fase 1. Hasta entonces, la
  ayuda de la sección «Contenido del diseño» dice cuáles se publican.
- Una pantalla sin sesión es superficie nueva: cualquier cambio en
  `Catalogue.php` tiene que mirarse con eso en la cabeza.
- La lista no hereda el menú del sitio donde se despliega.

### Neutras

- Si alguna web consume hoy el catálogo como API (REQ-0001, §8, pregunta 5),
  esta pantalla no la sustituye. Habría que decidir esa API aparte.

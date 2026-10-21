---
id: ADR-0014
title: "El perfil de competencia digital se elige en elidd, incrustada en la ficha"
status: Propuesta
date: 2026-10-21
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0002, ADR-0013]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0014: El perfil de competencia digital se elige en elidd, incrustada en la ficha

## Estado

Propuesta

## Contexto

Un diseño puede enlazar su perfil de competencia digital (REQ-0001, RF-5). El
perfil se elige en [elidd](https://github.com/erseco/elidd), la herramienta
libre (CC BY 4.0) del marco de referencia de la competencia digital docente.
elidd comparte la selección como una dirección: `?c=1.1.A1.2|…`.

Hasta ahora, la asesoría tenía que hacer estos pasos:

1. Abrir elidd en otra pestaña.
2. Marcar los indicadores.
3. Pulsar «Compartir».
4. Pegar la dirección en la ficha.

El aplicativo leía esa dirección y pintaba un resumen propio
(`src/Fmc/Domain/DigComp.php`). En la revisión del 2026-10-21 se pidió poder
elegir el perfil sin salir de la ficha, y que la ficha enseñe la tabla que
elidd da al generar.

Los perfiles ya guardados apuntan a dos copias de elidd: la pública y una que
aloja la organización. Ninguna de las dos impide que se la incruste en otra
página: no envían `X-Frame-Options` ni `frame-ancestors`. Lo comprobó un
experimento el 2026-10-21.

## Problema

¿Cómo se elige el perfil dentro del aplicativo sin rehacer elidd en él?

## Factores de decisión

- elidd ya resuelve la elección: tabla del marco, búsqueda y filtro por
  niveles. Rehacerla aquí duplica unas 470 líneas de JavaScript y los datos del
  marco.
- El aplicativo no tiene paso de compilación y carga lo de terceros de un CDN
  (ADR-0002).
- Lo que vuelve de otra página es entrada no fiable.
- El valor guardado tiene que seguir siendo la dirección de elidd, para que
  «Abrir en elidd» y lo ya guardado sigan valiendo.

## Alternativas consideradas

### Opción 1: seguir pegando la dirección

- A favor: nada que hacer.
- En contra: es justo lo que se pidió cambiar.

### Opción 2: un editor propio con los datos de elidd

Cargar `mrcdd.json` de una versión etiquetada de elidd y pintar los 360
indicadores como casillas en la ficha.

- A favor: no depende de que elidd esté en línea al editar.
- En contra: repite la interfaz de elidd y la mantiene dos veces. La búsqueda,
  los filtros y las afirmaciones de desempeño se quedarían en elidd, o habría
  que rehacerlos.

### Opción 3: elidd incrustada en un diálogo

elidd gana un modo incrustado: `?embed=1&origin=…`. Ese modo añade un botón
«Guardar» que envía la selección con `postMessage`, y solo al origen que la
abrió. El aplicativo la abre en un `<dialog>` y escribe la selección en el
campo, como dirección.

- A favor: una sola interfaz, la de elidd, que mejora para todos. En el
  aplicativo son unas 80 líneas de guion y ningún dato del marco.
- En contra: depende de que elidd esté disponible y de que la copia que se use
  tenga el modo incrustado. Además, se ejecuta una página de otro origen dentro
  de la ficha.

## Decisión

**Opción 3.**

- **Lado de elidd.** El modo incrustado y el arreglo de la selección, que
  antes se perdía al filtrar, van en elidd.
- **Lado del aplicativo.** «Elegir los indicadores en elidd» abre la copia de
  elidd que diga el filtro `fmc_digcomp_tool`. Si no dice ninguna, abre la
  pública (`DigComp::TOOL`), con lo ya elegido. La copia de la organización
  sirve igual en cuanto tenga esta versión.
- **Qué se acepta.** El guion (`assets/js/fmc-app.js`) solo acepta un mensaje
  que cumpla las tres condiciones:
  - Viene del **origen** de esa copia.
  - Lo manda **ese marco** (`event.source`).
  - Su tipo es `elidd:seleccion`, con un `c` de la forma `1.1.A1.2|…`.

  Lo que no cumple las tres cosas se ignora. El valor no se pinta: se escribe
  en el campo. El servidor lo vuelve a sanear al guardar, como cualquier
  dirección (ADR-0006).
- **La ficha** pinta la tabla que da elidd al generar: por áreas, con sus
  colores, y el nivel más alto marcado en cada competencia. Al pasar el ratón
  se ven todos los niveles con su número de indicadores.
- **Sin JavaScript**, se sigue pegando la dirección a mano.

## Consecuencias

### Positivas

- El perfil se elige sin salir de la ficha, y la ficha enseña lo mismo que
  elidd.
- El aplicativo no carga ni mantiene los datos del marco.

### Negativas

- Si elidd no está en línea, el perfil no se puede elegir. Se puede pegar la
  dirección, y lo ya guardado se sigue viendo.
- La copia que use cada despliegue tiene que tener el modo incrustado.
- Si el sitio llega a tener una política de seguridad con `frame-src`, hay que
  añadir el origen de elidd.
- La tabla solo enseña el nivel más alto de cada competencia. El detalle queda
  en el título de cada celda, y entero en elidd.

### Neutras

- Si algún día hace falta editar sin elidd en línea, la opción 2 cabe
  después: elidd ya publica `mrcdd.json` y la CI comprueba que coincide con su
  YAML.

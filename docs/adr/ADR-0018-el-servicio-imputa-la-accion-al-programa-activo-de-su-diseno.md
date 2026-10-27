---
id: ADR-0018
title: "Lo que asume el servicio de formación se imputa al programa activo del diseño"
status: Propuesta
date: 2026-10-27
related:
  issues: [27]
  prs: []
  sdds: []
  adrs: [ADR-0002, ADR-0003, ADR-0016]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0018: Lo que asume el servicio de formación se imputa al programa activo del diseño

## Estado

Propuesta

## Contexto

La ficha de la acción tiene «Asumida por» (`fmc_funded_by`) y «Con cargo al
programa» (`fmc_programme`). «Asumida por» es una lista cerrada de cuatro
valores en el código (`ActionMetaKeys::FUNDERS`): sin coste, centro de
formación, área y servicio de formación. El programa se elegía a mano, y la
ayuda de la sección pedía elegir los dos cuando la pagaba el servicio.

En la issue #27, la validación propone otra cosa:

- «Asumida por» se queda en cuatro opciones.
- **El programa no se elige al agendar.** Mientras un programa está activo, se
  dice qué diseños del catálogo cubre. Si una acción de uno de esos diseños la
  asume el servicio de formación, se imputa a ese programa.
- **Si el diseño no está en el programa activo**, la acción no tiene que ver
  con el programa: la asume el centro de formación.
- Las cuatro opciones llevan los nombres de la organización. En el código no
  pueden ir ([ADR-0002](ADR-0002-se-hereda-la-plataforma-del-aplicativo-de-eventos.md)).

El diseño ya dice para qué programas se crea (`tax:fmc_programme` en su ficha).
Lo que falta es saber qué programa está activo hoy.

## Problema

¿Cómo sabe el aplicativo a qué programa imputar una acción que asume el
servicio de formación, sin que nadie lo elija a mano y sin escribir los
nombres de la organización en el código?

## Factores de decisión

- Que quien agenda no tenga que saber de qué programa sale el dinero.
- Que no se pueda imputar a un programa cerrado ni a uno que no cubre el
  diseño.
- Que las acciones ya registradas no dejen de guardarse cuando un programa se
  cierra.
- Ningún nombre de la organización en el repositorio.

## Alternativas consideradas

### Opción 1: el programa, a mano, como hasta ahora

- A favor: no hay código nuevo.
- En contra: es lo que la validación quiere quitar. Se puede elegir un
  programa que no cubre el diseño, o uno ya cerrado.

### Opción 2: el programa lleva fechas de vigencia

- A favor: se cierra solo.
- En contra: hay que mantener dos fechas por programa, y los plazos reales no
  siempre coinciden con ellas. Se prefirió la casilla.

### Opción 3: una casilla «Activo» en el programa

La marca a mano la administración, que es quien gestiona los vocabularios.

- A favor: es simple, y el que cierra un programa decide cuándo.
- En contra: si nadie desmarca un programa terminado, sigue recibiendo
  imputaciones.

## Decisión

**Opción 3.**

- **La casilla.** El programa (`fmc_programme`) lleva la meta de término
  `fmc_active` (`Taxonomies::ACTIVE`). Se marca en el escritorio, al crear o
  editar un programa, y el listado de programas la enseña en una columna. Solo
  la guarda quien tiene `fmc_manage_app`, con su nonce
  (`Taxonomies::save_active()`).
- **Qué diseños cubre un programa.** Los que lo tienen en su ficha
  (`tax:fmc_programme` del diseño), sin otra lista que mantener.
- **La imputación.** Al guardar una acción que asume el servicio de formación
  (`ActionMetaKeys::PROGRAMME_FUNDER`), su programa son los programas activos
  de su diseño, aunque se haya elegido otro (`Editor::impute_programme()`).
  Si el diseño no está en ninguno, no se guarda, y se dice que la asume el
  centro de formación (`ActionMetaKeys::FALLBACK_FUNDER`).
- **Las acciones ya guardadas.** Una acción a la que no se le cambia ni quién
  paga ni el diseño se guarda como está, aunque su programa ya no esté activo.
- **Con otro que paga**, el programa es el que se elija, como hasta ahora.
- **Los nombres.** Las claves de «Asumida por» siguen en el código. Los nombres
  los pone cada despliegue con el filtro `fmc_funders`
  (`ActionMetaKeys::funders()`), que solo cambia nombres de claves que ya
  existen. Los usan la ficha, la ayuda y la leyenda del calendario.

Lo prueba `tests/unit/test-programmes.php`.

## Consecuencias

### Positivas

- Quien agenda elige quién paga, y el programa sale solo.
- No se puede imputar una acción a un programa cerrado ni a uno que no cubre
  su diseño.
- Cada despliegue ve sus propios nombres sin que el repositorio los diga.

### Negativas

- Si nadie desmarca un programa terminado, sigue recibiendo imputaciones.
- Un diseño en dos programas activos se imputa a los dos. Si eso no vale,
  habrá que elegir uno, y eso necesita otra decisión.
- El error de «no está en ningún programa activo» obliga a cambiar a mano
  quién paga. No se cambia solo, para que nadie guarde sin darse cuenta.

### Neutras

- La migración no marca ningún programa como activo: lo hace la administración
  al arrancar el sitio.
- Las temáticas nuevas de la misma issue son datos, no código. Las crea la
  migración con su descripción, y la ficha la enseña al elegir.

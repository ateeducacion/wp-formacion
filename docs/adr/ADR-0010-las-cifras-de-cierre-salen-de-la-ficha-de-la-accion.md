---
id: ADR-0010
title: "Las cifras de cierre salen de la ficha de la acción"
status: Propuesta
date: 2026-10-21
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0005, ADR-0006, ADR-0008]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0010: Las cifras de cierre salen de la ficha de la acción

## Estado

Propuesta

## Contexto

La ADR-0005 sacó la matrícula del aplicativo, pero dejó dentro sus cifras
finales: la acción guardaba matriculados, asistentes y certificados por sexo,
y los totales se sumaban (REQ-0001, RF-10). En la ficha eso es la sección
«Cifras y cierre» de `src/Fmc/PublicFront/Fields.php`, que además lleva la
documentación del expediente, la fecha de entrega al servicio y la de proceso
completado; sus claves están en `src/Fmc/Meta/ActionMetaKeys.php`
(`fmc_enrolled_*`, `fmc_attended_*`, `fmc_certified_*`, `fmc_docs_file`,
`fmc_docs_delivered`, `fmc_completed`). Las mismas columnas salían en el resumen
de la acción y en su CSV.

En la revisión del 2026-10-21, la curaduría —quien tendría que rellenarlas—
dice que esa sección **no la usa**: esas cifras y ese cierre se gestionan en el
sistema de gestión del servicio de formación, que es donde se matricula y
donde se certifica. La ADR-0005 ya preveía la pega: «las cifras se teclean a
mano desde el otro sistema». En la práctica no se teclean. El material de la
reunión está en `.local/`.

## Problema

¿Se mantienen en la ficha de la acción unas cifras que nadie rellena y que ya
tienen dueño en otro sistema?

## Factores de decisión

- Un dato que se copia a mano de otro sistema acaba desincronizado, y uno que
  no se copia, vacío. En ambos casos el listado miente.
- La raya de la ADR-0005: lo que pasa en la matrícula y después es del
  sistema de gestión del servicio.
- Lo que ya hay en el sistema anterior no se pierde al migrar (ADR-0008).
- Una ficha más corta se rellena mejor.

## Alternativas consideradas

### Opción 1: dejarlas como están

- A favor: nada que cambiar; si algún día alguien las rellena, están.
- En contra: una sección entera que nadie usa, en la ficha, en el resumen y en
  el CSV. Invita a pensar que el dato está y es fiable.

### Opción 2: borrarlas del todo, también las claves

- A favor: el modelo de datos queda limpio.
- En contra: lo que el sistema anterior sí tenga guardado se perdería en la
  migración, o habría que importarlo a otro sitio. Y si las claves dejan de
  estar registradas, pierden su saneado y su permiso por campo (ADR-0006).

### Opción 3: fuera de la pantalla, dentro del modelo

La sección sale de la ficha, del resumen y del CSV; las claves se quedan
registradas.

- A favor: lo migrado se conserva y se puede consultar; el aplicativo deja de
  pedir un dato que no es suyo.
- En contra: quedan claves registradas que ninguna pantalla escribe. Quien lea
  `ActionMetaKeys.php` sin esta ADR puede pensar que se usan.

## Decisión

**Opción 3.**

- La sección «Cifras y cierre» sale de la ficha de la acción
  (`Fields.php`), y sus columnas, del resumen de la acción y de su CSV.
- Las claves **siguen registradas** en `ActionMetaKeys.php`, con su tipo y con
  el `auth_callback` de `src/Fmc/Meta/MetaRegistration.php`: el importador
  puede guardar lo que traiga el sistema anterior, y el aplicativo no las
  escribe porque la ficha ya no las manda.
- Las cifras de una acción se consultan en el sistema de gestión del servicio
  de formación.

En la práctica **deja sin efecto el RF-10** de REQ-0001 y la parte de la
ADR-0005 que decía que la acción guarda «las cifras finales por sexo; los
totales se calculan». El resto de la ADR-0005 —aquí no hay matrícula— no
cambia; por eso esta ADR no la sustituye, sino que la acota, y la ADR-0005
lleva una adenda que lo dice.

## Consecuencias

### Positivas

- Una sección menos en la ficha y unas columnas menos en el CSV.
- Ningún listado de este aplicativo presume de unas cifras que no tiene.

### Negativas

- Para saber cuánta gente se certificó en una acción hay que ir al otro
  sistema; un informe que cruce acciones y cifras ya no sale de aquí.
- Las claves registradas sin pantalla son deuda visible: si dentro de un año
  siguen sin usarse, se retiran con otra ADR y su paso de migración.

### Neutras

- La ADR-0005 ya dejaba la puerta abierta a leer las cifras de una API del
  otro sistema si algún día existe. Sigue abierta, y entonces volverían a
  verse sin teclearlas.

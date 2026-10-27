---
id: ADR-0015
title: "La isla del ponente es una taxonomía y el cargo, una lista cerrada"
status: Propuesta
date: 2026-10-22
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0002, ADR-0003, ADR-0011, ADR-0012]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0015: La isla del ponente es una taxonomía y el cargo, una lista cerrada

## Estado

Propuesta

## Contexto

La ficha del ponente tenía la isla o zona (`fmc_zone`) y la profesión
(`fmc_profession`) como texto libre, igual que en el sistema anterior. Al cargar
en local la exportación de ese sistema, que está en `.local/`, se ve el precio
de dejarlos libres:

- **La profesión** tiene decenas de grafías para unas pocas cosas: mayúsculas,
  minúsculas, género, etapa, especialidad, abreviaturas y, a veces, un lugar.
  Lo medido está en `.local/`. Lo que se necesita al elegir ponente es mucho
  menos: si está en activo, si está jubilado (se pidió poder decirlo
  expresamente) o si viene de fuera de la docencia.
- **La isla** tiene pocos valores, pero solo porque la importación los pasa a
  mayúscula inicial (`.local/migracion/preparar.py`). Tecleada a mano, se
  ensuciaría igual. Hoy el filtro del listado ofrece los valores distintos que
  ya existen (`Lists::meta_choices()`, que se elimina), así que una errata se
  convierte en una opción más del filtro.

En la revisión se pidió que las dos se elijan de una lista.

## Problema

¿Dónde viven las opciones de la isla y del cargo, teniendo en cuenta que el
repositorio es público y no puede decir de quién es el despliegue (ADR-0002)?

## Factores de decisión

- Una lista abierta acaba con «Curso» e «Histórico-curso» a la vez (ADR-0003).
- Los nombres de lugar de una instalación la delatan. `make check-public` los
  rechaza, y con razón.
- El cargo no depende de la instalación: activo, jubilado o externo valen en
  cualquier sitio.
- Los filtros y el orden del listado tienen que seguir funcionando
  (ADR-0012).

## Alternativas consideradas

### Opción 1: las dos, listas cerradas en código

- A favor: es la regla general de la casa para las listas (ADR-0003).
- En contra: las islas en el código publican dónde está el despliegue. Es
  justo lo que prohíbe ADR-0002.

### Opción 2: las dos, desplegable con los valores ya usados, más «otro»

- A favor: no hace falta un tipo de dato nuevo.
- En contra: no cierra nada. La primera errata entra por «otro» y se queda en
  el desplegable para siempre.

### Opción 3: la isla, taxonomía; el cargo, lista cerrada en código

- A favor: cada cosa va donde le toca. La isla es un dato de la instalación,
  como los programas o los ámbitos, y la crea quien despliega. El cargo es
  igual en todas partes, así que va en el código.
- En contra: una taxonomía más que dar de alta en cada instalación, y una
  migración que tiene que pasar el texto a término y la profesión libre a una
  de tres.

## Decisión

**Opción 3.**

- **Isla o zona**: la taxonomía `fmc_zone` (`Taxonomies::ZONE`), no
  jerárquica, solo del ponente, no pública y fuera de REST, como las demás.
  Los términos los gestiona la administración (`fmc_manage_app`). La ficha
  la elige de un desplegable (`'tax:fmc_zone'` en `Fields::speaker()`). El
  filtro, la columna y el orden del listado leen el término. La meta
  `fmc_zone` desaparece. `scripts/setup-vocabulary.php` crea dos zonas
  ficticias para el entorno local.
- **Cargo**: `fmc_profession` pasa a ser una lista cerrada,
  `SpeakerMetaKeys::PROFESSIONS`: `teacher` (Docente en activo),
  `retired_teacher` (Docente jubilado) y `external` (No docente o externo).
  Un valor fuera de la lista se guarda vacío (`MetaRegistration::enums()`).
  La etiqueta de la ficha pasa de «Profesión» a «Cargo».
- **La migración** traduce la profesión libre a una de las tres: «jubilado»
  manda, después cualquier forma de docente, y el resto es externo. La isla
  pasa a término. Las dos cosas se hacen en el importador de `.local/`, que no
  se versiona.

## Consecuencias

### Positivas

- El filtro de islas ya no puede tener una opción por cada errata.
- «Jubilado» es un valor que se puede filtrar, no una palabra dentro de un
  texto.
- No queda ningún nombre de lugar en el repositorio.

### Negativas

- Se pierde el detalle de la profesión libre: la etapa o la especialidad. Si
  hace falta, va en las observaciones; la migración no lo copia ahí.
- Cada instalación tiene que crear sus islas antes de que alguien dé de alta
  un ponente. Si no, el desplegable sale vacío.
- Tres cargos se pueden quedar cortos. Ampliar la lista es un cambio de
  código, a propósito.

### Neutras

- Los datos de una instalación ya en marcha necesitan una pasada que lleve
  `fmc_zone` de meta a término. Hoy no hay ninguna instalación en marcha: la
  migración va a un sitio limpio (ADR-0008).

## Adenda — 2026-10-27: el docente en activo, por etapa

En la segunda revisión con la validación, el 2026-10-27, se pidió distinguir
la etapa del docente en activo. La lista cerrada pasa a tener siete valores
(`SpeakerMetaKeys::PROFESSIONS`):

- `primary_teacher`: Docente de infantil y primaria.
- `secondary_teacher`: Docente de secundaria y bachillerato.
- `vocational_teacher`: Docente de formación profesional.
- `language_teacher`: Docente de escuela oficial de idiomas.
- `teacher`: Docente en activo, sin etapa indicada. Es el de antes, y se queda
  para quien no dijo la etapa, que en el sistema anterior es la mayoría (medido
  en `.local/`).
- `retired_teacher` y `external`, sin cambios.

La migración solo pone la etapa cuando el texto libre la dice. Si dice
«maestro», «primaria» o «infantil», es infantil y primaria. Si dice «EOI», es
escuela oficial de idiomas. Si dice «FP» o «profesor técnico», y no dice
secundaria, es formación profesional. Si dice «secundaria», «bachillerato» o
una materia de instituto, es secundaria y bachillerato. «Docente» o
«profesora» a secas se quedan en `teacher`: no se adivina. La validación
confirmó que las escuelas oficiales de idiomas y la formación profesional van
aparte. La decisión de esta ADR no cambia: sigue siendo una lista cerrada que
se amplía en el código.

Asistencia de IA: Claude Code (claude-opus-5-5).

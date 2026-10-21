---
id: ADR-0009
title: "La acción lleva el título de su diseño"
status: Propuesta
date: 2026-10-21
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0004, ADR-0005, ADR-0006]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0009: La acción lleva el título de su diseño

## Estado

Propuesta

## Contexto

Una acción formativa es una edición de un diseño del catálogo (REQ-0001,
RF-7). Hasta ahora el aplicativo copiaba ese planteamiento del sistema
anterior: el título de la acción se puede escribir, y si se deja en blanco se
toma el del diseño (el `post_title` de la ficha de la acción en
`src/Fmc/PublicFront/Fields.php`, con la ayuda «En blanco, el del diseño»; el
relleno, en `src/Fmc/PublicFront/Editor.php`). Al lado hay un «subtítulo»
(`fmc_subtitle`, en `src/Fmc/Meta/ActionMetaKeys.php`).

En la revisión del 2026-10-21 con la curaduría salió cómo se usa eso de
verdad, y lo contó alguien que lo ha hecho desde una asesoría:

- En el formulario anterior el «subtítulo» **sustituye** al título: la acción
  se muestra, y llega al sistema de gestión del servicio de formación, con el
  subtítulo como nombre.
- Era práctica común **retitular** una acción para que se pareciera a otro
  curso, y hay casos en que la curaduría tuvo que pedir que se deshiciera porque
  la acción había llegado al sistema de gestión con un título que no era el del
  catálogo.
- Por eso mismo, en los datos que se van a migrar **hay muchas acciones con un
  título distinto del de su diseño**: un añadido de lugar, de destinatarios o
  un nombre entero distinto.

El material de la reunión y el recuento de títulos cambiados están en
`.local/`.

## Problema

¿Quién decide el título de una acción, y qué papel tiene el subtítulo?

## Factores de decisión

- Lo que se imparte es un diseño catalogado: si el título se puede cambiar, el
  catálogo deja de decir qué se está impartiendo.
- El título de la acción es lo que viaja al sistema de gestión del servicio de
  formación (ADR-0005): un error ahí se arregla fuera de este aplicativo, y a
  mano.
- La acción a veces necesita decir algo propio —la sede, el grupo, el
  nivel— sin dejar de llamarse como su diseño.
- Lo histórico no se reescribe: lo que ya se registró con otro título fue así,
  y la migración no debe inventarse el pasado (ADR-0008).
- Menos código: lo que no se puede editar no hay que validarlo.

## Alternativas consideradas

### Opción 1: título editable, como hasta ahora

El título se escribe; en blanco, el del diseño.

- A favor: cero cambios, y cero sorpresas para quien venga del sistema
  anterior.
- En contra: es exactamente el agujero que se describe arriba. Nada impide que
  una acción salga con el nombre de otro curso, y la regla «se llama como su
  diseño» queda en manos de la buena voluntad de cada asesoría. La curaduría
  sigue teniendo que perseguir títulos a mano.

### Opción 2: solo lectura, con permiso de la curaduría para cambiarlo

El título es el del diseño, pero la curaduría lo puede sobrescribir en casos
excepcionales.

- A favor: cubre la excepción sin que nadie tenga que tocar la base de datos.
- En contra: una capacidad nueva, un campo que es de solo lectura para unos y
  editable para otros, y la lógica de cuándo una sobrescritura sobrevive a un
  cambio de diseño. Todo para una excepción que en la reunión **nadie pidió**:
  lo que se pidió es que no se pueda cambiar. Si aparece la necesidad, se añade
  entonces (YAGNI).

### Opción 3: título bloqueado y subtítulo como añadido

El título de la acción **es** el del diseño y no se edita. El subtítulo pasa a
ser un **añadido** que se pinta debajo del título, nunca en su lugar.

- A favor: la regla la aplica el servidor y no la pantalla (ADR-0006); el
  catálogo y lo impartido dicen lo mismo; lo que la acción tiene de propio
  sigue teniendo su sitio.
- En contra: quien quiera un nombre distinto tiene que crear un diseño
  distinto, y eso pasa por la curaduría (ADR-0004). Es el efecto buscado, pero
  es una fricción real para una asesoría que hoy lo resuelve en un segundo.

## Decisión

**Opción 3.**

- El aplicativo pone el título de la acción **al crearla** y **cada vez que
  cambia su diseño**, copiándolo del diseño. El campo no se edita en la ficha, y
  lo que llegue por el formulario se ignora: lo escribe el servidor
  (`Editor.php`), no la pantalla.
- **Lo histórico se respeta.** Una acción que ya tiene un título distinto del
  de su diseño lo conserva mientras no se le cambie el diseño. No hay
  reescritura masiva, ni al migrar ni después.
- El **subtítulo** (`fmc_subtitle`) es un añadido: se pinta debajo del título
  en el listado de acciones (`Lists.php`), en el calendario (`Calendar.php`) y
  en la cabecera de la ficha (`Screen.php`). Nunca lo sustituye.

Cambia REQ-0001, RF-7: la acción hereda el título de su diseño **y no lo
ajusta**; tipo, modalidad y horas siguen heredándose y ajustándose como
hasta ahora.

## Consecuencias

### Positivas

- Lo que llega al sistema de gestión del servicio se llama como el diseño del
  catálogo.
- Un campo menos que validar y un sitio menos por donde equivocarse.
- Lo propio de la acción —sede, grupo, nivel— se ve, y se ve como lo que es.

### Negativas

- Una asesoría que necesite otro nombre tiene que pedir otro diseño a la
  curaduría.
- Si la curaduría renombra un diseño, sus acciones ya registradas **no** se
  renombran: conservan el título con el que se registraron hasta que se les
  cambie el diseño. Es coherente con no reescribir lo histórico, pero puede
  sorprender.
- Durante un tiempo convivirán acciones antiguas con títulos «libres» y nuevas
  con el del diseño: un listado que mezcle las dos no es homogéneo.

### Neutras

- Si algún día hace falta la sobrescritura de la opción 2, cabe sin deshacer
  esta: es una capacidad más y una excepción en `Editor.php`.

---
id: ADR-0019
title: "La asesoría es una cuenta del sitio, con su tipo en el perfil"
status: Propuesta
date: 2026-10-27
related:
  issues: [25]
  prs: []
  sdds: []
  adrs: [ADR-0003, ADR-0006, ADR-0017]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0019: La asesoría es una cuenta del sitio, con su tipo en el perfil

## Estado

Propuesta

## Contexto

«Asesoría responsable» (`fmc_adviser`) se elige entre las cuentas del sitio con
el rol de asesoría o de validación. En la issue #25, la validación pide poder
decir de cada asesoría si es TIC, STEAM u otra: hay asesorías que no son TIC y
que gestionan acciones a petición de los centros de formación. En el sistema
anterior, la asesoría se daba de alta como usuaria del sitio y se le marcaba
una casilla.

El destino es un subsitio de un multisitio. La administración da de alta a
cada asesoría como usuaria del sitio, buscándola por su documento de
identidad, y es también quien la da de baja.

## Problema

¿Dónde se dice de qué tipo es una asesoría, sin llevar una segunda lista de
asesorías aparte de las cuentas?

## Factores de decisión

- No duplicar el alta y la baja de asesorías, que ya hace la administración
  con las cuentas.
- El tipo, en una lista cerrada
  ([ADR-0003](ADR-0003-una-dimension-un-sitio.md)).
- Que solo lo cambie quien gestiona las cuentas
  ([ADR-0006](ADR-0006-los-permisos-se-deciden-en-el-servidor-campo-a-campo.md)).
- En un multisitio, los datos de usuario son de toda la red.

## Alternativas consideradas

### Opción 1: un directorio de asesorías propio

Un tipo de contenido «Asesoría», con su pestaña en el aplicativo, su tipo y la
cuenta de quien la ocupa. Se llegó a programar en la misma rama.

- A favor: se cuenta por asesoría aunque cambie la persona, y se puede elegir
  una asesoría que no tiene cuenta.
- En contra: duplica la gestión de cuentas. Habría dos listas que dar de alta y
  de baja, y se descuadrarían. La administración lo descartó: el alta la hace
  ella, con la cuenta.

### Opción 2: marcarla en la lista de ponentes

- A favor: no añade ninguna lista.
- En contra: los ponentes son quienes imparten, con datos personales, baja
  motivada y recomendaciones
  ([ADR-0011](ADR-0011-los-ponentes-son-un-directorio-compartido-con-baja-motivada.md)).
  Mezclarlos con quien gestiona una acción confunde las dos cosas.

### Opción 3: la cuenta del sitio, con el tipo en su perfil

- A favor: no hay ninguna lista nueva. El tipo se pone donde ya se da de alta
  la cuenta.
- En contra: la acción sigue unida a la persona. Si cambia quien ocupa una
  asesoría, las acciones antiguas cuentan para la cuenta anterior.

## Decisión

**Opción 3.**

- **El tipo** es una opción de usuario, `fmc_adviser_kind`
  (`Advisers::KIND`, en `src/Fmc/User/Advisers.php`), de la lista cerrada
  `Advisers::KINDS`: TIC, STEAM y Otra.
- **Se guarda por sitio**, con `update_user_option()`, que le pone el prefijo
  del sitio. En un multisitio, la misma persona puede ser asesoría TIC en un
  sitio y no serlo en otro.
- **Se pone en el perfil del usuario, en el escritorio**, en una sección
  «Formación». Solo lo ve y lo guarda quien tiene `promote_users` —la
  administración del sitio—, con su nonce (`Advisers::save_profile()`). El
  listado de usuarios lo enseña en una columna, «Asesoría».
- **En la acción**, «Asesoría responsable» ofrece las cuentas con el rol de
  asesoría o de validación, con su tipo al lado: «Ana · TIC»
  (`Advisers::choices()`). El guardado comprueba que la cuenta elegida es una
  de ellas (`Editor::check_choices()`).
- **El listado y el calendario de acciones** se filtran por asesoría y por
  tipo de asesoría.

Lo prueba `tests/unit/test-advisers.php`.

## Consecuencias

### Positivas

- La administración da de alta y de baja las asesorías como hasta ahora: con
  la cuenta. El tipo es un campo más del perfil.
- Ninguna pantalla nueva en el aplicativo.
- El tipo es un valor que se filtra, no una casilla suelta.

### Negativas

- La acción va unida a la cuenta. Si cambia quien ocupa una asesoría, contar
  por asesoría a lo largo de los cursos cuenta personas.
- Para poder elegir una asesoría, tiene que tener cuenta en el sitio.
- La migración no sabe el tipo de cada asesoría: hay que ponerlo en cada
  perfil.

### Neutras

- La otra mitad de la issue #25, elegir el centro donde se imparte por su
  código, no se ha contestado y queda fuera.
- Si con el uso hace falta contar por puesto y no por persona, el directorio
  de la opción 1 es el camino, con su propia ADR.

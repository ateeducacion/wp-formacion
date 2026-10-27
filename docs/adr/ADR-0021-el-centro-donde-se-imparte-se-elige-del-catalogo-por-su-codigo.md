---
id: ADR-0021
title: "El centro donde se imparte se elige del catálogo de centros, por su código"
status: Propuesta
date: 2026-10-27
related:
  issues: [25]
  prs: []
  sdds: []
  adrs: [ADR-0002, ADR-0003, ADR-0006, ADR-0008, ADR-0019]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0021: El centro donde se imparte se elige del catálogo de centros, por su código

## Estado

Propuesta

## Contexto

«Centro donde se imparte» (`fmc_venue_centre`) era texto libre. Lo migrado
llega como nombre, y lo que se escribió a mano trae el mismo centro con
variantes: con y sin su código, abreviado o con otra grafía.

La issue #25 proponía elegirlo del catálogo de centros que se hereda del
aplicativo de eventos
([ADR-0002](ADR-0002-se-hereda-la-plataforma-del-aplicativo-de-eventos.md):
allí, «El catálogo de centros es externo y se cachea»). La validación contestó
en la misma issue: buscar por el código del centro en el directorio de centros
y conservar lo escrito a mano. Y más adelante, no admitir texto a mano, que
pide tener el catálogo al día.

El catálogo es un conjunto de datos abierto que publica dos ficheros:

- `manifest.json`, con la versión del esquema, el SHA-256 del catálogo y su
  número de registros;
- `centros.min.json`, con el código de ocho cifras, el nombre, la isla, el
  municipio, el tipo y si el centro está activo.

Eventos lo copia a una opción y lo pone al día con una tarea diaria.

## Problema

¿Cómo se elige el centro de una acción, de dónde sale la lista, y qué pasa con
lo que ya está escrito a mano?

## Factores de decisión

- Un centro, un valor, para poder contar y filtrar por él
  ([ADR-0003](ADR-0003-una-dimension-un-sitio.md)).
- Pintar y guardar la ficha sin pedir nada a otro servidor.
- La dirección de la fuente es del despliegue, no del repositorio (ADR-0002).
- No perder nada de lo migrado
  ([ADR-0008](ADR-0008-la-migracion-va-a-un-sitio-limpio-y-el-actual-se-archiva.md)).

## Alternativas consideradas

### Opción 1: texto libre, con el catálogo como sugerencia

- A favor: admite un lugar que no es un centro educativo.
- En contra: siguen las variantes del mismo centro, y no se cuenta por centro.

### Opción 2: una taxonomía de centros, cargada con el catálogo

- A favor: los filtros y los contadores de una taxonomía, sin más código.
- En contra: todos los centros como términos, que habría que crear, renombrar
  y retirar en cada sitio. El catálogo ya trae su copia, su validación y su
  tarea diaria en eventos, y aquí se duplicarían.

### Opción 3: el código, elegido de la copia local del catálogo, y el nombre al lado

- A favor: un centro, un código. La lista se pone al día sola. Lo escrito a
  mano se conserva.
- En contra: un lugar que no está en el catálogo no se puede elegir.

## Decisión

**Opción 3.**

- **El catálogo** son las clases de eventos, con el prefijo de aquí:
  `Centre\CentreCatalogue` y `Centre\CentreCatalogueSync`.
  - Se copia en la opción `fmc_centres_catalogue`, sin autoload.
  - Una tarea diaria (`fmc_centres_cron_sync`) lee el manifest y solo descarga
    el catálogo si su SHA-256 ha cambiado.
  - Lo valida entero antes de tocar nada: esquema, hash, número de registros,
    código de ocho cifras sin repetir, nombre y «activo» booleano. Luego lo
    sustituye de una vez.
  - Si algo falla, se queda la copia anterior, y el motivo, en el estado.
  - Solo por https, y una sincronización a la vez.
- **La fuente** no tiene dirección por defecto. La fijan la constante
  `FMC_CENTRES_MANIFEST_URL` o el filtro `fmc_centres_manifest_url`. Si no, la
  pone la administración en «Ajustes → Centros educativos»
  (`Centre\CentreSettings`, con `fmc_manage_app`), donde también ve el estado
  de la copia y la actualiza sin esperar a la tarea.
- **La acción** guarda el código, en `fmc_venue_centre_code`.
  - Se elige entre los centros activos, con buscador por nombre o por código:
    «nombre (código)».
  - El guardado comprueba que es un centro activo, salvo que sea el que la
    acción ya tenía: a una acción no se le quita su centro porque cierre
    ([ADR-0006](ADR-0006-los-permisos-se-deciden-en-el-servidor-campo-a-campo.md)).
- **El nombre se guarda al lado**, en `fmc_venue_centre`, al elegir el centro.
  Lo leen el calendario, el listado y el CSV, y se queda aunque el centro salga
  del catálogo. Quitar el centro quita su nombre.
- **Lo escrito a mano** se queda en `fmc_venue_centre` mientras no se elija un
  centro, y la ficha lo enseña debajo del campo. No se escribe texto a mano
  nuevo.
- **La migración** pone el código del centro de la lista anterior. Lo escrito a
  mano lo casa por el código que lleve en el texto, o por el nombre, si es el
  de un solo centro. Medido sobre la exportación de `.local/`, casa el 97 % de
  las acciones que tienen centro. El resto se queda como texto, y el informe
  de la migración lo enumera.

Lo prueba `tests/unit/test-centres.php`.

## Consecuencias

### Positivas

- Un centro, un código: se puede contar y filtrar por centro.
- La lista se pone al día sola, y una descarga mala no la estropea.
- La ficha no pide nada fuera del sitio.

### Negativas

- Un lugar que no es un centro del catálogo, como la sala de otra
  institución, no se puede elegir. Si hace falta, será un campo aparte, con su
  ADR.
- Sin la dirección de la fuente, la lista está vacía y la acción se guarda sin
  centro. La ficha lo avisa, pero hay que configurarlo en cada despliegue.
- La ficha de la acción lleva en su HTML todos los centros activos cada vez
  que se abre.
- Cada sitio guarda su propia copia del catálogo.

### Neutras

- Filtrar las acciones por centro, o por la isla del centro, no está todavía.
  El dato ya está para hacerlo.
- La issue #25 se cierra con esta ADR y con la
  [ADR-0019](ADR-0019-la-asesoria-es-una-cuenta-del-sitio-con-su-tipo.md), que
  resolvió su otra mitad.

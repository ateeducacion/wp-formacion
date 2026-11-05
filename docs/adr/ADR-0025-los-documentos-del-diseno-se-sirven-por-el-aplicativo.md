---
id: ADR-0025
title: "Los documentos del diseño se guardan cerrados y se sirven por el aplicativo; la ficha técnica del catálogo, a cualquiera"
status: Propuesta
date: 2026-11-05
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0006, ADR-0008, ADR-0013, ADR-0016, ADR-0017]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0025: Los documentos del diseño se guardan cerrados y se sirven por el aplicativo; la ficha técnica del catálogo, a cualquiera

## Estado

Propuesta

## Contexto

Los documentos de un diseño —el diseño del curso, la escaleta, las
presentaciones, los recursos, la imagen— son adjuntos de WordPress colgados de
él, con su clase en la meta `_fmc_kind`
([`PublicFront/Documents.php`](../../src/Fmc/PublicFront/Documents.php)). Se
guardaban con `media_handle_sideload()` en la carpeta de subidas de siempre,
`uploads/AAAA/MM/`, con el nombre original del fichero.

Quién lee un diseño ya está decidido, y en el servidor: un borrador, uno en
revisión o un histórico solo los lee quien puede editarlos
(`PostTypes::hide_unfinished()`, [ADR-0016](ADR-0016-lo-que-ya-no-se-imparte-es-un-estado-del-diseno.md));
la asesoría consulta los finalizados ([ADR-0017](ADR-0017-la-asesoria-consulta-los-disenos-finalizados-y-no-los-escribe.md)).
Pero esa regla **solo valía dentro del aplicativo**. Medido en el entorno local
el 05/11/2026, sin sesión:

| Petición | Respuesta |
|---|---|
| El fichero de la escaleta de un diseño en borrador, por su dirección en `uploads/` | **200**, el PDF entero |
| Su página de adjunto (`?attachment_id=`) | 404 |
| La REST de medios (`wp/v2/media/ID`) | 401: el diseño no está en REST |

El servidor web sirve los ficheros de `uploads/` sin preguntar a WordPress, y
el nombre se adivina: carpeta por año y mes y el nombre que trajo el fichero,
que suele ser el del curso. Es justo lo que la casa dice que no se hace:
esconder un enlace no es proteger lo que hay detrás (AGENTS.md, «Permisos»).

Al mismo tiempo, el catálogo del sistema anterior **publica la ficha técnica**
de cada curso: desde la ficha pública se descarga el diseño del curso en sus
formatos, sin sesión. Esa parte tiene que seguir siendo pública, y con una
dirección que se pueda enlazar.

## Problema

¿Dónde se guarda el fichero de un documento del diseño, quién lo sirve y con
qué regla, de modo que lo de trabajo no salga por su dirección y la ficha
técnica de un curso del catálogo se siga descargando sin sesión?

## Factores de decisión

- **La regla ya existe.** Quién lee un documento tiene que ser quien lee su
  diseño: no un segundo sistema de permisos.
- **Fallar en cerrado.** Si algo se olvida, el documento no tiene que quedar
  publicado.
- **No depende del servidor web.** Este WordPress solo admite pegar código en
  Code Snippets ([ADR-0001](ADR-0001-el-repositorio-es-un-entorno-de-desarrollo-no-un-plugin.md)):
  no se puede tocar la configuración de nginx ni sacar ficheros de la raíz web.
- **Lo que ya funciona sigue funcionando.** La lista por clase, el orden, el
  «Cambiar» que conserva el sitio, la imagen destacada y los tests de todo
  eso.
- **Tamaño del cambio.** Cabe en la clase que ya hay.

## Alternativas consideradas

### Opción 1: dejarlo como está

- **A favor:** nada que hacer.
- **En contra:** un borrador o un histórico se leen sin sesión por su
  dirección física. Descartada.

### Opción 2: nombre aleatorio y nada más

Guardar con un nombre imposible de adivinar y seguir sirviéndolo por su URL.

- **A favor:** un cambio de dos líneas.
- **En contra:** un nombre aleatorio no es una autorización. La dirección sale
  en la ficha, en el historial, en el `Referer` y en lo que se pegue, y desde
  ahí vale para siempre y para cualquiera, también cuando el diseño pasa al
  histórico. Descartada como única defensa; se usa igual, pero **encima** de
  lo demás.

### Opción 3: ficheros privados sin adjunto

Guardar el fichero fuera de la biblioteca de medios, sin entrada en
`wp_posts`, con un descriptor en una meta del diseño.

- **A favor:** sin adjunto no hay biblioteca, ni página de adjunto, ni REST de
  medios que vigilar.
- **En contra:** hay que rehacer lo que hoy hace WordPress: la lista, el orden,
  el reemplazo, la imagen destacada, el borrado con el diseño. Y aquí esas
  puertas **ya están cerradas por otro lado**: la REST de medios responde 401
  porque el diseño no está en REST; la página del adjunto, 404; y la
  mediateca solo la ve quien tiene `upload_files`, que es la coordinación, que
  ya lee todos los diseños (ADR-0017). Lo único abierto es el fichero.
  Descartada: mucho código para cerrar una puerta que no es la abierta.

### Opción 4: adjunto con el fichero en un almacén cerrado, servido por el aplicativo — **elegida**

El adjunto se queda; lo que cambia es dónde está el fichero, en qué modo y
quién lo sirve.

- **A favor:** cierra exactamente la puerta abierta y no toca lo demás. La
  regla es la del diseño (`read_post`), en un sitio. No depende del servidor
  web.
- **En contra:** hay una descarga propia que mantener, y el modo del fichero
  pide abrirlo y cerrarlo en cada lectura. Más abajo, con sus límites.

## Decisión

**Opción 4.** En [`PublicFront/Documents.php`](../../src/Fmc/PublicFront/Documents.php), que decide quién y sirve, y [`PublicFront/DocumentStore.php`](../../src/Fmc/PublicFront/DocumentStore.php), que guarda, abre y cierra:

1. **Dónde.** El fichero va a `uploads/fmc-private/ab/`, con un nombre de 32
   cifras hexadecimales aleatorias más la extensión del tipo ya comprobado.
   No lleva el nombre del curso ni el del fichero; ese se guarda en la meta
   `_fmc_name` y es el que se enseña y el que lleva la descarga. Con la carpeta
   se escriben un `.htaccess` que lo deniega todo y un `index.php` vacío.
2. **En qué modo.** El fichero queda en `0200`: se escribe, no se lee. Para
   leerlo, el aplicativo lo abre (`0400`), lo lee entero y lo vuelve a cerrar
   en el `finally`, **antes** de mandar nada por la red. Las lecturas se ponen
   en fila con un cerrojo (`flock` sobre `fmc-private/.lock`): sin él, una
   lectura que cierra le quitaría el fichero a otra que lo acaba de abrir. Si
   el cerrojo sigue ocupado a los cinco segundos, la respuesta es un 503 y no
   un fichero a medias. Un documento que no se puede cerrar al subirlo no se da
   por guardado: se borra y se dice.
3. **Sin copias al lado.** No se generan miniaturas, ni la versión reducida de
   una imagen grande, ni la vista previa de un PDF: serían copias legibles del
   documento junto al cerrado.
4. **Quién lo sirve.** `?fmc_documento=ID`, en la portada, como el resto de
   pantallas. El identificador no autoriza nada; decide `Documents::verdict()`:

   | Quién | Qué |
   |---|---|
   | Cualquiera, sin sesión | La ficha técnica —la clase «Diseño del curso»— de un diseño **finalizado y en el catálogo** (`Catalogue::is_listed()`) |
   | Sin sesión, cualquier otro documento | Al acceso, y de vuelta al documento |
   | Con sesión | Lo que pueda leer del diseño: `current_user_can( 'read_post', $diseño )`, con lo que añade ADR-0016 |
   | Con sesión y sin permiso, o lo que no es un documento de un diseño | 404, igual que si no existiera |

   La respuesta va siempre como descarga (`Content-Disposition: attachment`),
   con el tipo comprobado al subir, `X-Content-Type-Options: nosniff` y
   `Cache-Control: private, no-store`.
5. **La dirección física no se reparte.** `wp_get_attachment_url()` de un
   documento devuelve la del aplicativo, así que tampoco la dan la mediateca,
   la REST ni una plantilla. El filtro solo toca los adjuntos con clase.
6. **La ficha técnica, en el catálogo.** La ficha pública de un curso
   (`?fmc_curso=ID`) lista sus documentos de la clase «Diseño del curso», con
   su nombre y enlazados a `?fmc_documento=ID`. La lista de clases públicas es
   `Documents::PUBLIC_KINDS`; el resto se queda dentro, como decía
   [ADR-0013](ADR-0013-el-catalogo-publico-es-una-pantalla-del-aplicativo.md)
   (con su adenda). En cuanto el diseño sale del catálogo o pasa al histórico,
   el mismo enlace deja de ser público.
7. **Lo que ya estaba subido se mueve.** `DocumentStore::protect()` lleva un
   documento de `uploads/AAAA/MM/` al almacén —de una imagen, el original, y
   borra sus miniaturas— y `scripts/protect-documents.php` lo pasa por todos
   (`make protect-documents`). Es idempotente: lo que ya está dentro solo se
   vuelve a cerrar. La migración (fase 5 de PLAN-0001, [ADR-0008](ADR-0008-la-migracion-va-a-un-sitio-limpio-y-el-actual-se-archiva.md))
   trae los ficheros del sistema anterior por `Documents::apply()`, que ya los
   guarda cerrados, y pasa el guion al final por si algo entró por otro lado.

De paso se corrige que `store()` pasaba sus opciones de subida como cuarto
argumento de `media_handle_sideload()`, que es el de los datos del adjunto: las
ignoraba. La comprobación de formato la hace `check()` antes, así que no había
agujero, pero el título del adjunto salía del nombre físico; ahora sale del
original.

## Consecuencias

### Positivas

- Un borrador o un histórico dejan de leerse por la dirección de sus ficheros,
  y la regla que lo decide es la del diseño, sin un segundo sistema.
- La ficha técnica de un curso del catálogo se descarga sin sesión desde su
  ficha pública, como en el sistema anterior, con una dirección estable
  mientras no se cambie el fichero.
- Medido en local: el fichero físico responde 403; la ficha técnica de un curso
  del catálogo, 200 sin sesión; la escaleta del mismo curso, al acceso; diez
  descargas a la vez del mismo documento salen enteras y el fichero queda en
  `0200`. Lo sujetan `tests/unit/test-documents.php` y
  `tests/unit/test-catalogue.php`.

### Negativas

- **El modo `0200` no está comprobado en el entorno local**: el disco que Docker
  monta en macOS deja leer el fichero igual, así que lo que da allí el 403 es el
  `.htaccess` de Apache. Que `0200` impida al servidor web leer el fichero
  depende de con qué usuario corren el servidor web y PHP en el destino: se
  midió en la misma plataforma para otro aplicativo, y el material no se
  versiona. **Antes de dar esto por bueno en el destino**, pedir sin sesión la
  dirección física de un documento ya movido y comprobar que no responde 200.
- **El modo no protege si PHP corre como `root`**, ni sobre un sistema de
  ficheros que lo ignore. Es una capa, no un muro; la otra es que la dirección
  física no se reparte.
- **El cerrojo es uno para todo el almacén**, y es consultivo: pone en fila al
  código PHP, no al servidor web. En un sistema de ficheros en red, `flock`
  puede no coordinar entre nodos; no está medido.
- **La descarga lee el documento entero en memoria** (hasta 50 MB, el tope de
  las clases grandes) antes de mandarlo, para no dejarlo abierto durante una
  descarga lenta.
- **Hay una descarga propia que mantener**, con sus cabeceras.
- **Una herramienta que lea `uploads/` por su cuenta** —una copia de seguridad
  que solo copie lo legible, un regenerador de miniaturas— no lee estos
  ficheros. Una copia de seguridad del destino tiene que incluir
  `uploads/fmc-private/` sabiendo eso.
- **Las imágenes ya no tienen miniaturas.** Hoy ninguna pantalla las usa; si el
  catálogo llegara a enseñar la imagen, se serviría por el mismo camino.

### Neutras

- Las clases públicas son una lista en el código, `Documents::PUBLIC_KINDS`, y
  hoy es solo la ficha técnica. Hacer pública otra clase es cambiar esa lista,
  a propósito y con revisión.
- La URL de la ficha técnica es la del aplicativo con el identificador del
  adjunto. Al cambiar el fichero por otro, el identificador cambia: un enlace a
  la ficha técnica desde fuera conviene que apunte a la ficha del curso
  (`?fmc_curso=ID`), que no cambia.
- Las direcciones del catálogo anterior no se redirigen desde aquí: es otro
  sitio, y eso lo decide la fase 5.2 de PLAN-0001.
- No se decide cifrado en reposo. Si alguna vez hiciera falta, entra en
  `Documents::store()` y `DocumentStore::read()`, que son las dos funciones
  que tocan el contenido.

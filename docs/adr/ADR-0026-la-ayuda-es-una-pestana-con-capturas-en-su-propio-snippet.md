---
id: ADR-0026
title: "La ayuda es una pestaña con guías y capturas, en su propio snippet, y las capturas las saca un guion"
status: Propuesta
date: 2026-11-17
related:
  issues: [75]
  prs: []
  sdds: []
  adrs: [ADR-0001, ADR-0002, ADR-0005]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0026: La ayuda es una pestaña con guías y capturas, en su propio snippet, y las capturas las saca un guion

## Estado

Propuesta

## Contexto

La issue #75 dejaba
abierta la forma de la ayuda y proponía una pestaña **sin capturas**, porque
las capturas envejecen. Quien va a presentar el aplicativo a las asesorías ha
contestado: pestaña, sí, pero **con guías paso a paso y capturas** («cómo doy de
alta un diseño: entra aquí y pulsa esto»), y con preguntas frecuentes que se
irán llenando con las dudas que lleguen. Sin capturas no le sirve a quien llega
de nuevas.

Eso deja tres preguntas técnicas:

1. **Dónde viven las imágenes.** En producción no hay ficheros que servir:
   todo es código pegado en Code Snippets
   ([ADR-0001](ADR-0001-el-repositorio-es-un-entorno-de-desarrollo-no-un-plugin.md)).
2. **Cuánto pesan.** Once capturas de pantalla en WebP, a 1280 px de ancho y
   calidad 80, suman unos 470 KB; en base64, unos 630 KB. El bundle del
   aplicativo pesa hoy 311 KB, y el empaquetador quita los comentarios
   precisamente para que sea manejable en el editor de Code Snippets.
   Medido el 17/11/2026: `ls -la snippets/`.
3. **Cómo no envejecen.** Una captura hecha a mano se queda vieja en cuanto
   cambia la pantalla, y nadie la rehace.

## Decisión

**La ayuda es una pestaña del aplicativo, «Ayuda»**, al final de las demás,
que ve todo el que entra (`?fmc_ayuda=1`,
[`PublicFront/Help.php`](../../src/Fmc/PublicFront/Help.php)). Es una sola
página con un índice: qué es un diseño y qué es una acción, quién hace qué, una
guía por tarea con su captura, y preguntas frecuentes. Cada guía dice qué
perfil hace esa tarea.

**El texto y las capturas viajan en su propio snippet, «FMC — Ayuda».** La
fuente está en `assets/help/ayuda.html` y `assets/help/img/*.webp`;
`make bundle` ejecuta [`build/pack-help.php`](../../build/pack-help.php), que
mete cada captura como `data:image/webp;base64,…` y escribe
`snippets/fmc-help.bundle.php`, que define `fmc_help_html()`. El aplicativo
pinta la pestaña solo si esa función existe: si el snippet no está desplegado,
no hay pestaña y nada se rompe, igual que con cualquier snippet suelto
(AGENTS.md, «Añadir código al aplicativo»).

**Las capturas no se hacen a mano.** `make capturas` arranca Playground con los
datos de demostración, que son inventados
([`scripts/seed-demo.php`](../../scripts/seed-demo.php)), y
[`scripts/help-screenshots.mjs`](../../scripts/help-screenshots.mjs) las saca
todas con Playwright, entrando con cada cuenta de demostración. Un PR que
cambia una pantalla rehace sus capturas con una orden.

El empaquetador **falla** si una captura que cita la ayuda no existe, si hay
una captura que no cita nadie, o si el texto lleva una llave (el validador de
Code Snippets cuenta llaves). Un test comprueba que el snippet está al día con
`assets/help/`, que el índice no apunta a secciones que no existen y que cada
captura tiene texto alternativo.

## Alternativas consideradas

- **Las capturas dentro del bundle del aplicativo**, con el mecanismo que ya
  inlinea el CSS y el JavaScript
  ([`PublicFront/Assets.php`](../../src/Fmc/PublicFront/Assets.php)). Sin un
  snippet más que desplegar. Descartada: el bundle pasaría de 311 KB a unos
  940 KB, tres veces más, para algo que solo se abre en una pantalla, y
  cada cambio de una captura sería un cambio del aplicativo entero.
- **Las capturas desde un CDN**, sacadas del propio repositorio en jsDelivr. Es
  el camino de las librerías ([ADR-0002](ADR-0002-se-hereda-la-plataforma-del-aplicativo-de-eventos.md)),
  pero la dirección nombra la organización dueña del repositorio, que no se
  escribe en lo que se versiona, y solo existiría tras publicar el tag: habría
  que llevarla por configuración del despliegue y la ayuda saldría sin
  imágenes hasta configurarla.
- **Subirlas a la mediateca del sitio.** Exige un paso a mano en cada
  instalación y en cada cambio de captura; es justo lo que se quiere evitar.
- **Un manual en PDF.** Descartado en la issue #75 y en la reunión: se reenvía,
  se congela y a los dos meses circulan tres versiones.

## Consecuencias

### Positivas

- La ayuda está donde surge la duda, y siempre corresponde a la versión
  desplegada: se publica con el mismo `@version` que el aplicativo.
- Rehacer las capturas cuesta una orden, así que el argumento de que envejecen
  pierde fuerza.
- Los datos de demostración ganan ponentes, acciones en el mes y una
  incidencia: sirven también para enseñar el aplicativo en una sesión.
- El bundle del aplicativo no crece.

### Negativas

- **Un snippet más que desplegar**, y de unos 640 KB. Quien despliega tiene que
  acordarse de llevarlo cuando cambie; si no lo lleva, la pestaña simplemente
  no sale.
- Cada vez que se rehacen las capturas, el repositorio guarda otra copia de las
  imágenes, dos veces: los `.webp` y el snippet generado.
- El texto es HTML, no Markdown: quien lo edite tiene que respetar las
  etiquetas. Es poco texto y casi siempre lo editará un agente.
- `make capturas` añade Playwright como dependencia de desarrollo y necesita
  Chromium; no corre en la CI.
- La guía usa los nombres genéricos de los perfiles; una instalación que los
  cambie con `fmc_role_labels` verá otros en la cabecera. La propia ayuda lo
  advierte.

## Referencias

- Issue #75: la discusión y lo decidido.
- [`build/pack-help.php`](../../build/pack-help.php), [`tests/unit/test-help.php`](../../tests/unit/test-help.php).

## Adenda — 2026-11-17: el ámbito es `front-end`, y lo que pesa ahora

- **Lo que pesa.** Con las catorce capturas de la v0.1.0, el snippet ocupa
  unos 730 KB, no los 640 KB que se estimaron con once. Code Snippets lee de
  la base de datos el código de cada snippet activo de los ámbitos que tocan
  en la petición y lo evalúa entero
  (`Evaluate_Functions::evaluate_db_snippets()`, que pide
  `global`, `single-use` y `admin` o `front-end` según `is_admin()`).
- **Por eso el ámbito pasa de `global` a `front-end`.** La ayuda solo se pinta
  en la portada, que es el aplicativo; con `front-end`, el escritorio y
  admin-ajax no la cargan. Las peticiones de la portada, la REST y el cron sí
  la siguen cargando: para quitarla de ahí, el HTML tendría que vivir fuera
  del código del snippet, por ejemplo en una opción sin autoload que el
  snippet escribiera al cambiar de versión. No se hace hoy: es más mecanismo
  que mantener para un coste que no está medido como problema.
- En el sitio de destino, el snippet ya creado se cambia de ámbito al
  desplegar la versión siguiente.


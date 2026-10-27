---
id: ADR-0016
title: "Lo que ya no se imparte es un estado del diseño: «Histórico»"
status: Propuesta
date: 2026-10-27
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0003, ADR-0004, ADR-0006, ADR-0008, ADR-0012, ADR-0013, ADR-0017]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0016: Lo que ya no se imparte es un estado del diseño: «Histórico»

## Estado

Propuesta

## Contexto

El ciclo del diseño son los estados de WordPress: borrador, en revisión y
finalizado (`publish`) ([ADR-0004](ADR-0004-el-ciclo-del-diseno-son-los-estados-de-wordpress.md)).
Lo que ya no se imparte no tenía sitio propio. La
[ADR-0012](ADR-0012-un-diseno-puede-sustituir-a-otro-y-los-ponentes-se-buscan-por-lo-impartido.md)
lo trataba como «finalizado y fuera del catálogo público», y dejaba sin tocar
el diseño sustituido.

En la segunda revisión con la validación, el 2026-10-27, salieron tres cosas:

- **La migración pierde lo histórico.** En el sistema anterior, lo histórico
  se marcaba en el tipo de formación —«Histórico-curso», el ejemplo que ya
  cita el `AGENTS.md`—, aparte de la marca de finalizado. El importador
  (`.local/migracion/`, [ADR-0008](ADR-0008-la-migracion-va-a-un-sitio-limpio-y-el-actual-se-archiva.md))
  quitaba el prefijo para quedarse con el tipo. Así, lo histórico no
  finalizado llegaba como borrador y lo histórico finalizado, como un
  finalizado más. Se pidió expresamente un estado «Histórico». Las cifras
  están medidas en `.local/`.
- **«Fuera del catálogo» no es «histórico».** «Sale en el catálogo público» es
  una casilla aparte (REQ-0001, §9.3). Desmarcarla también sirve para no
  publicar un diseño mientras se prepara. Hay diseños finalizados que se
  imparten y no salen en el catálogo público. Con la regla de la ADR-0012,
  esos diseños no se podían elegir en «Recomendado para» de ningún ponente.
- **Lo que no se imparte no se elige.** Un borrador no se imparte hasta que
  está finalizado, y un histórico ya no. Ninguno de los dos debe ofrecerse
  para recomendar ponentes ni para agendar una acción. Y la asesoría no debe
  verlos ([ADR-0017](ADR-0017-la-asesoria-consulta-los-disenos-finalizados-y-no-los-escribe.md)).

## Problema

¿Cómo se marca un diseño que ya no se imparte, de modo que salga de todas las
listas de elección, del catálogo y de lo que ve la asesoría, sin borrarlo ni
reescribirlo?

## Factores de decisión

- Que lo haga cumplir el servidor, en las consultas y en las capacidades, y
  no la pantalla ([ADR-0006](ADR-0006-los-permisos-se-deciden-en-el-servidor-campo-a-campo.md)).
- No mezclarlo con «sale en el catálogo público», que es otra decisión.
- Que la migración pueda traerlo tal cual.
- Que quien escribe diseños lo siga consultando, con sus documentos.
- El menor código propio posible sobre estados (ADR-0004).

## Alternativas consideradas

### Opción 1: seguir como hasta ahora

Histórico es estar finalizado y fuera del catálogo público.

- A favor: no hay código nuevo.
- En contra: confunde dos decisiones. A un finalizado que no sale en el
  catálogo no se le pueden recomendar ponentes. Y el importador no tiene dónde
  poner lo histórico que nunca se finalizó.

### Opción 2: una meta booleana sobre un finalizado

`fmc_archived = 1` en un diseño `publish`.

- A favor: no toca los estados.
- En contra: para WordPress sigue siendo un publicado. `read_post` deja
  leerlo a cualquiera con `read`, y el catálogo, los selectores, los
  contadores, el CSV y la ficha tendrían que acordarse, uno por uno, de
  excluirlo. El primer sitio que se olvide lo enseña.

### Opción 3: un estado propio, `fmc_archived`

Un estado registrado con `register_post_status()`, con el rótulo «Histórico».

- A favor: todo lo que ya pide `publish` lo excluye sin tocarlo: el catálogo
  público, los selectores, lo que ve la asesoría y el filtro «Recomendado
  para». Los contadores y los filtros salen del mismo mapa de estados. El
  escritorio lo lista con su nombre.
- En contra: el editor del escritorio de WordPress no conoce los estados
  propios. Su desplegable de estado (`post_submit_meta_box()`, en
  `wp-admin/includes/meta-boxes.php`) y la edición rápida no lo ofrecen, y
  guardar un histórico desde ahí puede cambiarle el estado. WordPress solo
  protege `publish` y `future` frente a quien no tiene `edit_published_*`
  (`map_meta_cap()`, caso `edit_post`, en `wp-includes/capabilities.php`), y
  a su autor le deja leer lo que no es público (caso `read_post`): las dos
  cosas hay que cerrarlas aparte. Y las consultas `any` lo incluyen, porque no
  se excluye de la búsqueda. Es a propósito: la cadena de sustituciones tiene
  que seguir leyéndolo.

## Decisión

**Opción 3.**

- **El estado.** `fmc_archived`, «Histórico» (`PostTypes::ARCHIVED`,
  registrado en `PostTypes::register()`). No es público, está protegido, entra
  en las consultas `any` y sale en los listados del escritorio.
- **Cerrado como un finalizado.** Editar o borrar un histórico pide además
  `edit_published_fmc_designs` o `delete_published_fmc_designs`
  (`PostTypes::lock_archived()`, en el filtro `map_meta_cap`). Y leer un
  diseño que no está finalizado —histórico, borrador o en revisión— pide poder
  editarlo, también a su autor (`PostTypes::hide_unfinished()`, en el mismo
  filtro). Lo prueba `tests/unit/test-post-types.php`.
- **Quién lo mueve.** Quien finaliza diseños (`publish_fmc_designs`): «Pasar a
  histórico» en un finalizado y «Recuperar del histórico» en un histórico
  (`Editor::buttons()`, `Editor::next_status()`). Sin esa capacidad, pedirlo
  no cambia el estado, ni para entrar ni para salir.
- **Lo histórico no se completa.** Guardarlo como histórico o pasarlo a él no
  pide los campos obligatorios ni el título único (`Editor::apply()`): un
  diseño antiguo migrado sin horas se puede retirar sin inventárselas.
- **Sustitución.** Corrige la pieza 1 de la ADR-0012. Al dar por finalizado un
  diseño, los que declara sustituir pasan a histórico
  (`Editor::archive_replaced()`). Cada uno pasa una sola vez: queda apuntado
  en el diseño nuevo (`Editor::ARCHIVED_META`), y uno que se recupera a mano
  no vuelve al histórico al guardar el nuevo, ni al finalizarlo otra vez.
  Mientras el nuevo es un borrador, el viejo sigue en uso. Los recomendados
  siguen pasando al nuevo por la cadena de sustituciones, como hasta ahora.
- **Solo se elige lo finalizado.** Corrige la pieza 2 de la ADR-0012. El
  diseño de una acción, «Recomendado para» en la ficha del ponente y su filtro
  en el listado ofrecen los diseños finalizados (`publish`), estén o no en el
  catálogo público (`Editor::posts_query()`, `Lists::post_choices()`). Y lo
  comprueba también el guardado, porque el formulario puede llegar con
  cualquier número (`Editor::check_choices()`): una acción con un borrador no
  se guarda, y un recomendado que no es un diseño finalizado se descarta. Lo
  ya guardado se conserva y lleva su estado al lado, por ejemplo «(Curso,
  Histórico)». Una acción nueva desde un diseño (`?fmc_design=`) solo se
  precarga si está finalizado.
- **Catálogo público.** Sigue pidiendo finalizado y la casilla
  (`Catalogue::is_listed()`): un histórico no sale, aunque conserve la casilla
  marcada ([ADR-0013](ADR-0013-el-catalogo-publico-es-una-pantalla-del-aplicativo.md)).
- **Listado de diseños.** Lo histórico no se mezcla con lo vigente. Los
  contadores son «Vigentes», «Borrador», «En revisión», «Finalizado» e
  «Histórico». Lo histórico se ve pulsando su contador o con el filtro de
  estado (`Lists::query_args()`, `Lists::DESIGN_CURRENT`).
- **Título único.** Ni un histórico ni el diseño que se sustituye cuentan
  (`Editor::taken()`): la versión nueva de un curso puede llamarse como la
  vieja. Lo prueba `tests/unit/test-editor-save.php`.
- **Migración.** El importador de `.local/` lleva a «Histórico» lo que el
  sistema anterior marcaba como histórico, estuviera o no finalizado.

## Consecuencias

### Positivas

- El listado de diseños enseña lo que está en uso, y el histórico, en su
  contador.
- Las recomendaciones de ponentes y el diseño de una acción solo ofrecen lo
  que se imparte. Ni el catálogo ni lo que ve la asesoría necesitan código
  propio para excluir lo histórico.
- Pasar un curso a su versión nueva no pide tocar el viejo: basta con
  declarar la sustitución en el nuevo y finalizarlo.
- La migración trae el histórico como histórico.

### Negativas

- El escritorio de WordPress no sabe de este estado. Un histórico no debe
  tocarse desde el editor ni desde la edición rápida del escritorio: la
  pantalla de trabajo es el aplicativo.
- Una acción ya no se puede agendar sobre un borrador: hay que finalizar el
  diseño antes. Es lo que se pidió, pero quita la posibilidad de agendar
  mientras se termina de escribir.
- Lo que en el sistema anterior quedó en borrador sin marca de histórico sigue
  en borrador. Decidir qué es histórico y qué se borra es una tarea de datos
  de la migración, no de este código.

### Neutras

- «"Histórico" no es un tipo» (`AGENTS.md`) sigue siendo cierto: es un
  estado. Las acciones no tienen estado histórico: el suyo sale de las fechas.
- Las consultas `any` incluyen el histórico. Quien escriba una consulta nueva
  de diseños tiene que decir qué estados quiere, como ya hacen todas.

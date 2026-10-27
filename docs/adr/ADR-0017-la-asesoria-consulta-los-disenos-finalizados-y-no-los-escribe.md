---
id: ADR-0017
title: "La asesoría consulta los diseños finalizados y no los escribe"
status: Propuesta
date: 2026-10-27
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0004, ADR-0006, ADR-0016]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0017: La asesoría consulta los diseños finalizados y no los escribe

## Estado

Propuesta

## Contexto

La [ADR-0004](ADR-0004-el-ciclo-del-diseno-son-los-estados-de-wordpress.md)
dio a la asesoría `edit_fmc_designs` y `delete_fmc_designs`: escribía
borradores y los mandaba a revisión, sin finalizarlos. Quedó en propuesta
hasta confirmar quién valida los diseños (REQ-0001, §8, pregunta 2).

En la segunda revisión con la validación, el 2026-10-27, se aclaró:

- **Las asesorías no dan de alta diseños.** Los diseña y los finaliza el área,
  que en el aplicativo es la validación.
- **Lo que necesitan es consultarlos**: la ficha y los materiales, para saber
  de qué va un curso antes de agendarlo.
- **No deben ver los borradores ni el histórico**, y eso tiene que valer
  aunque tecleen la dirección de la ficha.
- **Una sola ficha.** Se pidió expresamente la misma vista, sin poder editar,
  y no una segunda pantalla de consulta que haya que mantener aparte.

Hoy la asesoría ve en el listado lo publicado y lo suyo (`fmc_visible`), y en
la ficha nueva tiene «Guardar borrador» y «Mandar a revisión».

## Problema

¿Qué puede hacer la asesoría con un diseño, y cómo se garantiza en el
servidor y no solo en la pantalla?

## Factores de decisión

- Que lo haga cumplir el servidor
  ([ADR-0006](ADR-0006-los-permisos-se-deciden-en-el-servidor-campo-a-campo.md)).
  Esconder en la pantalla no protege (`AGENTS.md`, «Permisos»).
- Una sola ficha que mantener.
- Los sitios ya desplegados: el reparto de capacidades era aditivo y no quitaba
  nada (`PostTypes::grant_caps_to_roles()`).
- La asesoría sigue agendando acciones a partir de un diseño.

## Alternativas consideradas

### Opción 1: esconderlo en la pantalla

Quitar el botón de alta y filtrar el listado.

- A favor: es poco código.
- En contra: no protege nada. La dirección directa de la ficha y las acciones
  del escritorio siguen abiertas: es el agujero del sistema anterior. Y la
  REST, el día que el tipo se abra a ella.

### Opción 2: una vista de consulta aparte para la asesoría

- A favor: se puede recortar a medida.
- En contra: son dos pantallas que mantener y que divergen. Se pidió
  expresamente que no.

### Opción 3: quitarle las capacidades de escritura y dejar la misma ficha en solo lectura

- A favor: la ficha ya pinta deshabilitado lo que no se puede escribir
  (ADR-0006), con sus documentos como enlaces. Sin `edit_fmc_designs`,
  WordPress no le deja leer un borrador ni un histórico ajeno (`read_post`).
- En contra: en un sitio ya desplegado, el rol conservaría
  `edit_fmc_designs`, porque el reparto no quita. Hay que decir qué se quita.
  Y WordPress sí deja a su autor leer lo suyo no publicado: `read_post` le
  pide solo `read` (`map_meta_cap()`, en `wp-includes/capabilities.php`). Hay
  que cerrarlo aparte. Los borradores que escribió una asesoría dejan de estar
  a su alcance.

## Decisión

**Opción 3.**

- **Capacidades.** `PostTypes::role_caps()` deja a la asesoría sin
  capacidades sobre los diseños. Los finalizados los lee con `read`, como
  cualquier publicado.
- **Lo que se quita.** `PostTypes::revoked_caps()` lista lo que el mapa dio y
  ya no da. `grant_caps_to_roles()` lo quita cada vez que se carga una
  página: a la asesoría, `edit_fmc_designs` y `delete_fmc_designs`. Es lo
  único que el reparto quita. Lo demás que se ponga a mano en el editor de
  roles se queda.
- **Sin subir ficheros.** La asesoría pierde también `upload_files`, que tenía
  para los documentos del diseño. Con él veía en la mediateca del escritorio
  los documentos de todos los diseños, borradores e histórico incluidos. Lo
  quita el snippet de roles, que es quien lo daba
  (`fmc_revoked_role_caps()`, en `snippets/roles-and-profiles.php`).
- **Leer.** Un diseño que no está finalizado solo lo lee quien puede
  editarlo, también si es su autor (`PostTypes::hide_unfinished()`, en el
  filtro `map_meta_cap`, que cambia `read_post` por `edit_post`). Así vale en
  cualquier sitio que pregunte por `read_post`, no solo en el aplicativo.
- **Abrir la ficha.** Quien no edita un diseño solo abre los finalizados
  (`Editor::can_view()`): ni sus borradores de antes, ni un histórico, ni
  tecleando la dirección.
- **El listado.** Solo finalizados (`Lists::query_args()`), sin contadores ni
  filtro de estado. El recuento va en una línea, con un subtítulo para quien
  consulta, y sin «Añadir diseño».
- **La ficha.** Es la misma, de solo lectura, con los documentos para abrir
  y con «Agendar una acción con este diseño». Lo que una ficha guarda y ella
  no puede leer —un recomendado que pasó a histórico, el diseño que otro
  sustituye— no le enseña el título: sale como «Un diseño que ya no se
  ofrece» (`Editor::kept_label()`), o no sale en las columnas y en el CSV
  (`Lists::design_titles()`). La búsqueda por lo recomendado y el filtro
  «Recomendado para» tampoco miran lo que no puede leer.
- **El servicio de formación** tampoco escribe diseños, y ve lo mismo.

Lo prueban `tests/unit/test-post-types.php`, `test-editor-render.php`,
`test-editor-save.php`, `test-lists.php` y `test-documents.php`.

## Consecuencias

### Positivas

- Una sola ficha, y la regla está en la capacidad, no en la vista.
- La asesoría encuentra lo que puede impartir, con sus materiales, sin
  borradores ni históricos por medio.
- Los sitios desplegados se corrigen solos en la siguiente carga.

### Negativas

- Una asesoría que quiera proponer un diseño lo hace fuera del aplicativo,
  con el documento, y lo da de alta la validación. Para corregir una errata,
  se lo pide a la validación.
- Los borradores que una asesoría escribió los sigue viendo la validación,
  pero ya no su autora.
- `revoked_caps()` gana poder: lo que se ponga ahí se quita aunque alguien lo
  conceda a mano. Solo debe llevar lo que el propio código concedió antes.

### Neutras

- «En revisión» queda para el trabajo entre quienes escriben diseños.
- La ADR-0004 sigue en propuesta hasta que se acepte en revisión. Esta ADR
  fija su parte de la asesoría, y la
  [ADR-0016](ADR-0016-lo-que-ya-no-se-imparte-es-un-estado-del-diseno.md)
  añade el estado «Histórico» a su ciclo.

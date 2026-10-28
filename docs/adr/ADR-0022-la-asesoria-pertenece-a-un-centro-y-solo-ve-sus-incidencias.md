---
id: ADR-0022
title: "La asesoría pertenece a un centro, y solo ve sus incidencias"
status: Propuesta
date: 2026-10-28
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0007, ADR-0011, ADR-0019, ADR-0021]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5"
---

# ADR-0022: La asesoría pertenece a un centro, y solo ve sus incidencias

## Estado

Propuesta

## Contexto

La asesoría responsable de una acción es una cuenta del sitio, y su perfil del
escritorio ya guarda de qué tipo es —TIC, STEAM u otra—
([ADR-0019](ADR-0019-la-asesoria-es-una-cuenta-del-sitio-con-su-tipo.md),
`src/Fmc/User/Advisers.php`). No guarda, en cambio, **de dónde es**, y de ahí
salen tres asperezas de uso diarias:

1. **El ámbito y la asesoría se teclean en cada acción nueva.** La ficha pide
   «Ámbito que la gestiona» —obligatorio— y «Asesoría responsable»
   (`src/Fmc/PublicFront/Fields.php`), y quien crea la acción es, casi siempre,
   la propia asesoría de ese ámbito. En la exportación del sistema anterior
   (`.local/migracion/datos.json`) el ámbito de las 3983 acciones es siempre un
   centro del profesorado, escrito «NOMBRE DEL CENTRO - 38000001»: el nombre
   del centro en el catálogo y su código.
2. **El listado de incidencias enseña las de todo el mundo.** Una incidencia se
   abre sobre una acción y cuelga de ella
   ([ADR-0007](ADR-0007-la-incidencia-cuelga-de-su-accion.md)). Como el rol
   `fmc_adviser` no tiene `edit_others_fmc_incidents`, el listado aplica
   `fmc_visible` —publicadas o propias—, y una incidencia se publica al
   abrirla: el resultado es que **toda** asesoría ve **todas** las incidencias
   publicadas (`src/Fmc/PublicFront/Lists.php`, `visible_where()`). Lo que
   cuenta una incidencia —qué se cambia de una acción, por qué, con qué coste—
   no es asunto de las demás.
3. **Una ficha de ponente solo la edita quien la dio de alta.** El directorio
   de ponentes es compartido y cualquier asesoría consulta cualquier ficha
   ([ADR-0011](ADR-0011-los-ponentes-son-un-directorio-compartido-con-baja-motivada.md)),
   pero corregir un teléfono mal escrito en la ficha de otro no se puede: hay
   que pedirlo. En un directorio de 777 ponentes compartido por todas las
   asesorías, eso significa datos de contacto desactualizados.

Los términos de `fmc_scope` los creó el importador con el nombre del centro,
sin su código (`.local/migracion/importador.php`), así que **el término del
ámbito se llama exactamente como el centro en el catálogo**
([ADR-0021](ADR-0021-el-centro-donde-se-imparte-se-elige-del-catalogo-por-su-codigo.md)).

## Problema

¿Dónde se guarda el centro de una asesoría, qué se rellena solo con él, y qué
ve y qué edita cada asesoría de lo que no es suyo?

## Factores de decisión

- Teclear menos no puede costar exactitud: lo que se propone se tiene que poder
  cambiar antes de guardar.
- Lo que acota de verdad es la capacidad, no la pantalla: esconder una fila del
  listado deja abierto el enlace directo, que es el agujero del sistema
  anterior.
- Ninguna lista de centros del despliegue se escribe en el repositorio
  (ADR-0002): el catálogo ya es una copia local de una fuente externa.
- Configurar dos veces lo mismo se acaba desincronizando: cuantos menos sitios
  haya que rellenar a mano, mejor.
- Los datos personales del ponente los trata quien gestiona formación, y la
  baja deja rastro de quién y por qué (ADR-0011).

## Alternativas consideradas

### Opción 1: Un ámbito de referencia en el perfil, elegido a mano

Un segundo desplegable en el perfil con los términos de `fmc_scope`.

- A favor: no deduce nada, siempre acierta.
- En contra: son dos campos que dicen lo mismo —el centro y su ámbito— en 120
  perfiles, y se desincronizan en cuanto uno se cambia y el otro no. Además no
  da el código del centro, que es lo que pide el dominio.

### Opción 2: El código del centro en el término del ámbito

Una meta de término con el código, puesta desde el escritorio, y el perfil con
solo el centro.

- A favor: el enlace es explícito y no depende del nombre.
- En contra: hay que construir la pantalla de la meta de término y rellenarla
  para los 15 ámbitos antes de que nada funcione; y sigue siendo configuración
  a mano que se puede olvidar.

### Opción 3: El centro en el perfil, y el ámbito por su nombre

El perfil guarda el **código** del centro, del catálogo; el ámbito que se
propone es el término de `fmc_scope` que se llama igual que ese centro.

- A favor: un solo dato que rellenar, y el enlace ya existe en los datos
  migrados sin tocar nada.
- En contra: si alguien renombra el término, deja de encontrarse. Es un fallo
  benigno —no se propone ámbito y se elige a mano—, pero es silencioso.

## Decisión

Haremos la **opción 3**, y con ella:

- El perfil del escritorio gana **«Centro de referencia»**, un desplegable del
  catálogo de centros que guarda el código, por sitio
  (`update_user_option()`), junto al tipo de asesoría. Lo rellena quien
  gestiona cuentas, como el tipo.
- Al crear una acción formativa se proponen ya puestos **la asesoría** —quien
  la está creando, si su cuenta puede serlo— y **el ámbito** —el término que se
  llama como su centro de referencia—. Los dos son campos normales de la ficha:
  se cambian antes de guardar. Si no hay centro, o no hay término con ese
  nombre, no se propone ámbito y se elige a mano.
- Una incidencia la ve quien la abrió, quien es la asesoría responsable de su
  acción y quien las resuelve. Se decide en `map_meta_cap`
  (`PostTypes::own_incidents()`), de modo que cierra igual el listado, la ficha
  y el enlace directo; el listado solo añade la misma condición en SQL para no
  paginar lo que después no se puede abrir.
- Cualquier asesoría **edita** cualquier ficha de ponente
  (`edit_others_fmc_speakers`), que es lo que ya se esperaba de un directorio
  compartido. La papelera de ponentes, que dice quién dio de baja a quién y por
  qué, sigue siendo de la validación: pasa a pedir
  `read_private_fmc_speakers`, que es lo que la distingue de verdad.

## Consecuencias

### Positivas

- Una acción nueva se abre con los dos campos de gestión puestos, y el ámbito
  deja de ser el error de tecleo más probable de la ficha.
- El listado de incidencias pasa a ser el de cada asesoría; el servicio de
  formación y la validación los siguen viendo todos.
- Un teléfono o un correo equivocado de un ponente lo arregla quien lo detecta.
- El código del centro queda disponible en el perfil para lo que venga
  —informes por centro, el guardián de ámbito de la fase 2— sin volver a pedir
  el dato.

### Negativas

- El ámbito propuesto depende de que el término se siga llamando como el
  centro. Si se renombra, se deja de proponer sin avisar.
- Dar de alta a una asesoría pasa a tener un paso más que hoy nadie hace: si
  nadie rellena el centro, no se gana nada.
- Que cualquier asesoría edite cualquier ficha de ponente amplía quién puede
  tocar datos personales; lo que lo contiene es que ya los podían leer todos y
  que la baja sigue dejando rastro (ADR-0011).

### Neutras

- No hace falta migrar nada: el centro de referencia nace vacío y lo que
  depende de él degrada a lo de hoy.
- La propuesta es solo al crear; una acción ya guardada no se toca.

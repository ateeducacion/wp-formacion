# AGENTS.md — Formación: instrucciones para agentes

Este es el **fichero de instrucciones canónico** para todos los agentes de
código (GitHub Copilot, Claude Code, Gemini Code Assist, Codex, Aider y otros)
que trabajen en este repositorio. Los demás ficheros de agentes (`CLAUDE.md`)
apuntan aquí.

---

## Qué es este proyecto (y qué NO es)

**Formación** es el repositorio de trabajo de un aplicativo que gestiona la
formación del profesorado de un área: los **diseños de curso** (el catálogo), las
**acciones formativas** que se imparten a partir de ellos, sus **ponentes** y
las **incidencias** que se abren sobre una acción. Es hermano del aplicativo de
eventos (`wp-eventos`) y **hereda entera su plataforma**: el mismo entorno, el
mismo empaquetado en Code Snippets, la misma CI y las mismas reglas
([ADR-0002](docs/adr/ADR-0002-se-hereda-la-plataforma-del-aplicativo-de-eventos.md)).
En producción se activa con **Code Snippets** y **WPFront User Role Editor**
—este, solo para suplantar a alguien y probar el aplicativo con su perfil; los
roles y sus capacidades salen del código, no de su editor—; dónde, lo dice el
`.env` y **no el repositorio**.

El dominio son cuatro CPT —`fmc_design`, `fmc_action`, `fmc_speaker`,
`fmc_incident`— y cuatro taxonomías —`fmc_programme`, `fmc_topic`,
`fmc_competence`, `fmc_scope`—. Qué pide cada uno está en
[REQ-0001](docs/requisitos/REQ-0001-aplicativo-de-formacion.md) y cómo se llega
hasta el sitio nuevo, en
[PLAN-0001](docs/plan/PLAN-0001-implantacion-y-migracion.md).

- **NO es un plugin de WordPress** instalable en producción. No crees fichero
  principal de plugin, ni `readme.txt`, ni `register_activation_hook()`, ni
  uses `plugin_dir_path()` / `plugin_dir_url()` / `plugins_url()`. Ese
  WordPress no admite despliegue de ficheros: solo pegar código en Code
  Snippets ([ADR-0001](docs/adr/ADR-0001-el-repositorio-es-un-entorno-de-desarrollo-no-un-plugin.md)).
- Fuente de verdad:
  - `src/Fmc/` → código del aplicativo (**editar aquí**);
  - `make bundle` → genera `snippets/fmc-formacion-app.bundle.php`;
  - `snippets/*.php` → `make sync-snippets` los lleva al entorno local;
    `npm run snippets` (el cliente `@erseco/code-snippets-client`) los lleva al
    sitio de destino, con las credenciales del `.env`.
- El repo se monta en el contenedor en `wp-content/fmc-dev`.
- `.local/` es material descargado de producción con datos personales —la
  exportación del sitio, la del gestor de formularios y el análisis del sistema
  actual—: **no se versiona y no se cita**.

### Lo que sustituye, en una línea cada cosa

Hace falta saberlo para no reinventar los errores de la casa. El detalle —con
sus números de formulario, de campo y de vista— está en `.local/`.

| Hoy en producción | Aquí |
|---|---|
| Un formulario de diseño de curso cuyas entradas son el catálogo | CPT `fmc_design`, público; la descripción en `post_content` |
| Una casilla «Opciones» que mezcla *está en el catálogo*, *diseño finalizado* y *pertenece a tal programa* | El estado es el `post_status` (ADR-0004); el catálogo, una meta; el programa, un término de `fmc_programme` (ADR-0003) |
| «Diseño finalizado» lo marca quien sea que vea la casilla | Solo la curaduría publica: la asesoría no tiene `publish_fmc_designs` ni `edit_published_fmc_designs` |
| Un formulario de acción formativa enorme, con campos «obsoletos» dentro | CPT `fmc_action` que apunta a su diseño (`fmc_design_id`) y guarda solo lo suyo; los totales se suman, no se teclean |
| Tres listas de «asumida por» que repiten los mismos programas | Quién paga es `fmc_funded_by` (lista cerrada); de qué programa, un término |
| Campos que solo rellena el servicio de formación, escondidos a los demás por la vista | Permiso por campo en el servidor: `auth_callback` de la meta (ADR-0006) |
| Una incidencia que recibe el identificador de la acción por la URL | `fmc_incident` cuelga de su acción por `post_parent`, y lo fija el aplicativo (ADR-0007) |
| Los ponentes, con NIF y teléfono, en un formulario que alimenta listados | CPT `fmc_speaker`, no público y fuera de REST |
| El curso escolar, un shortcode que se evalúa al guardar | Se deduce de la fecha de inicio: `Domain/SchoolYear` |

**La matrícula no es de este aplicativo** (ADR-0005): la lleva el sistema de
gestión del servicio de formación. Aquí se registra la acción, sus plazos, su
enlace de matrícula y sus cifras finales.

---

## Mapa del repositorio

| Ruta | Contenido |
|------|-----------|
| `src/Fmc/` | Aplicativo (fuente; `make bundle`) |
| `src/Fmc/load-order.php` | Orden de carga: lista única, la usan bootstrap y el bundler |
| `src/Fmc/bootstrap.php` | Define `FMC_SRC_DIR`, recorre la lista y llama `App::boot()` |
| `build/pack-snippet.php` | Empaquetador `src/Fmc/` → bundle |
| `snippets/` | Snippets sueltos + bundle generado; sync a Code Snippets |
| `scripts/` | Aprovisionamiento (`wp eval-file` / Playground) |
| `scripts/lib/snippet-sync.php` | Librería de sincronización con Code Snippets |
| `scripts/mu-plugins/` | mu-plugin solo de desarrollo |
| `tests/` | PHPUnit sobre un WordPress vivo; `tests/js/`, Vitest para los guiones de `assets/js` cuando los haya |
| `docs/requisitos/`, `docs/adr/`, `docs/sdd/`, `docs/plan/` | Requisitos, decisiones, diseño y planes |
| `.agents/skills/` | Skills de agentes (`.claude/skills/` lleva una copia) |
| `CHANGELOG.md` | Versión y cambios; `make bundle` lee de ahí el `@version` |
| `.env.dist` | Plantilla de configuración de despliegue (copiar a `.env`); la lee `npm run snippets` |
| `blueprint.json` / `blueprint-local.json` | Playground remoto / local |
| `.wp-env.json` / `Makefile` | Docker / comandos |
| `.local/` | Descargas de producción. **Ignorado. No lo abras ni lo cites en un commit** |

### Anatomía de `src/Fmc/`

Todo son `final class` con métodos `static`. Sin contenedor de inyección, sin
interfaces, sin factorías, sin capa de servicios: es lo que permite
concatenarlo en un fichero.

```
src/Fmc/
├── load-order.php                Lista única y ordenada
├── bootstrap.php                 FMC_SRC_DIR + require de la lista + App::boot()
├── App.php                       Idempotente. Registra tipos, taxonomías y metas
├── Meta/MetaTypes.php            Tipos de valor y su saneado, uno por tipo
├── Meta/DesignMetaKeys.php       Claves del diseño y sus listas cerradas (tipos, modalidades)
├── Meta/ActionMetaKeys.php       Claves de la acción y sus listas (financiación, proceso, situación)
├── Meta/SpeakerMetaKeys.php      Claves del ponente (datos personales)
├── Meta/IncidentMetaKeys.php     Claves de la incidencia y sus resoluciones
├── Meta/MetaRegistration.php     register_post_meta con tipo, lista cerrada y permiso por campo
├── Domain/SchoolYear.php         El curso escolar de una fecha. Puro
├── PostType/PostTypes.php        Los cuatro CPT y qué capacidades tiene cada rol en cada uno
├── Taxonomy/Taxonomies.php       Programa, temática, competencia digital y ámbito
└── PublicFront/                  El aplicativo, en la portada del sitio
    ├── Screen.php                Enrutado (?fmc_tab, ?fmc_edit, ?fmc_new), cabecera y pestañas
    ├── Lists.php                 Contadores, filtros y tabla paginada de cada tipo
    ├── Calendar.php              Calendario mensual con colores por situación y financiación
    ├── Fields.php                Las fichas de edición escritas como datos: secciones y campos
    ├── Editor.php                Ficha de edición y guardado campo a campo (`apply()`), con las reglas de Fields::rules()
    ├── Documents.php             Documentos del diseño: adjuntos colgados de él, por clase y formato
    └── Assets.php                El CSS de assets/, inlineado en el bundle
```

**La portada del sitio es el aplicativo**: sin sesión manda al acceso; con
sesión pinta su propio documento (Bootstrap 5, Tom Select y SweetAlert2 desde
jsDelivr con SRI), no el del tema. Las fichas se abren en un **panel lateral**
(offcanvas): `assets/js/fmc-app.js` pide la pantalla con `fmc_marco=1`, que
`Screen::document()` devuelve como fragmento sin cabecera, y la pinta y la
guarda dentro del panel. Sin JavaScript, los mismos enlaces abren la ficha en
su página. Los guiones de `assets/js/` se prueban con Vitest (`make test-js`). Una ficha nueva o un campo nuevo se añaden en `Fields.php` y en su
`*MetaKeys.php`; `tests/unit/test-editor.php` comprueba que cada campo de la
ficha es una meta registrada.

Ni una capa más. Si crees que hace falta otra, escribe la ADR primero.

---

## Qué comando ejecutar

| Situación | Comando |
|-----------|---------|
| Primera vez | `make install && make up` |
| Cambio en `src/Fmc/` | `make bundle && make sync-snippets` (+ `make lint` / `make test`) |
| Cambio en un snippet suelto | `make sync-snippets` |
| Tras `make bundle`, con wp-env arrancado | `make snippet-check` (los snippets sobreviven al guardado de Code Snippets) |
| Un test concreto | `make test FILE=tests/unit/test-post-types.php` o `make test FILTER=nombre_del_metodo` |
| Cambio en un guion de `assets/js/` | `make test-js` (Vitest y jsdom; la cobertura queda en `artifacts/coverage-js/`) |
| Ver la cobertura | `make coverage` (reinicia wp-env con Xdebug) |
| Entorno raro | `make clean` |
| Empezar de cero | `make destroy && make up` |
| Probar sin Docker | `make playground` |
| Antes de commit/PR | `make check` |
| Comprobar el código como lo revisaría WordPress.org | `make check-plugin` (errores **y avisos**) |
| Publicar una versión | `make release` (tras cerrar el bloque del CHANGELOG) |
| Llevar un snippet al sitio de destino | `npm run snippets -- push <id> --file snippets/… --dry-run` (sin `--yes` no escribe) |

Sitio local: <http://localhost:8858> (`admin` / `password`). Tests en el 8859.
Los puertos son otros que los de eventos para poder tener los dos arrancados.

---

## Cuando quien te dirige no viene de desarrollo

Parte del equipo trabaja en este repositorio **a través de un agente**, sin
experiencia previa en desarrollo. Para esa persona está
[`docs/desarrollo-para-empezar.md`](docs/desarrollo-para-empezar.md): instalación
en macOS y en Windows, `git pull` antes de empezar, `make install` / `make up`,
el ciclo rama → `make check` → `gh pr create` → **revisión de otra persona**, y
qué mirar cuando algo falla.

Si es tu caso, dos consecuencias para ti:

- **Explica en castellano llano qué has tocado y por qué**, y qué tiene que ver
  en pantalla para comprobarlo. Un resumen que solo entiende quien ya sabe no
  sirve de nada.
- **No propongas ni ejecutes nada que se salte la revisión**: ni `push` a
  `main`, ni fusionar el PR, ni desactivar una comprobación para que `make
  check` pase. Si `make check` está en rojo, se arregla; no se rodea.

---

## Añadir código al aplicativo

**¿Clase o función suelta?** Dominio de formación → `src/Fmc/` (acaba inlineado
en el bundle). Helpers que otros snippets puedan usar por su cuenta → un
`snippets/*.php` propio con su cabecero `Snippet Name: FMC — …`. El bundle
llama a los sueltos con `function_exists()`: si el snippet no está desplegado,
degrada en silencio, no peta. Los roles son el caso canónico:
`snippets/roles-and-profiles.php` **no entra en el bundle**.

**Fichero nuevo en `src/Fmc/`** → añádelo a `src/Fmc/load-order.php`, después
de todo lo que extienda o implemente. Es la única lista; `make bundle` falla si
un fichero de `src/Fmc/` no está en ella. Sin esa guarda, el fichero
simplemente no llegaría a producción, en silencio.

**El primer fichero del load-order** tiene que abrir con `namespace`: ahí es
donde el bundler inyecta la guarda `FMC_BUNDLE_LOADED`, que evita que el
segundo `eval()` de Code Snippets muera con «Cannot redeclare class». Es una
constante y no un `class_exists()` a propósito: PHP resuelve pronto las clases
sin padre y `App` ya existiría en la primera pasada.

**El bundle sale sin comentarios**: el empaquetador se los quita con el
analizador léxico de PHP —no con expresiones regulares— para que el snippet sea
más pequeño y manejable en el editor de Code Snippets. Los comentarios se leen
en `src/Fmc/`, que es donde están enteros. La **cabecera no se toca**: de ella
salen el nombre, el ámbito y la prioridad del snippet, y el `@version` que mira
`make release`.

**Al bundle solo lo miran sus propios tests** (`tests/unit/test-bundle.php`):
`tests/bootstrap.php` carga los módulos de `src/Fmc/` y se salta `*.bundle.php`. Por eso `make bundle` hace `php -l` del
resultado — un `declare(strict_types=1)` es legal por fichero y **fatal** al
concatenar. Por eso también está prohibido.

**Permisos**: quién hace qué con cada tipo está en un único mapa,
`PostTypes::role_caps()`, y quién escribe cada campo, en
`MetaRegistration::guarded()` (ADR-0006). Esconder algo en el listado con
`pre_get_posts` o en la pantalla con CSS **no es protegerlo** —es exactamente el
agujero del sistema anterior—: quedan abiertos el enlace directo a
`post.php?post=N`, la edición rápida, la REST API y las acciones en bloque. La
capa que protege es la capacidad (`map_meta_cap`, `auth_callback`). El acotado
por ámbito (`fmc_scope`) llega con el guardián `Access/`, en la fase 2 del plan.

**Verlo funcionando:** `make bundle && make sync-snippets` y recarga
<http://localhost:8858/wp-admin/edit.php?post_type=fmc_design>.

---

## Convenciones

- **Idiomas:** ver [más abajo](#idiomas); en corto, identificadores en
  **inglés** y todo lo que lee una persona en **castellano**.
- **Prefijo:** `fmc_` / `FMC_`; namespace `Fmc`; snippets `FMC — …`.
- **Estilo:** WPCS (`wp-coding-standards/wpcs`, `<rule ref="WordPress">`),
  tabuladores, Yoda conditions, escape de salida. `make lint` / `make fix`.
  Cubre `src/`, `snippets/`, `scripts/`, `tests/` **y `build/`**; en `build/` se
  apagan solo las reglas que exigen `WP_Filesystem`, porque esos guiones corren
  desde la línea de órdenes y ahí no hay WordPress.
- **`declare(strict_types=1)` está prohibido** (ver arriba).
- **Aplicativo:** editar solo `src/Fmc/`; no editar a mano
  `snippets/*.bundle.php` (regenerar con `make bundle`).
- **Librerías de terceros:** en producción desde **jsDelivr con SRI**
  (`cdn.jsdelivr.net/npm/<paquete>@<versión>/…`, con `integrity` y
  `crossorigin`); en desarrollo y en los tests desde **`node_modules`**, que el
  mu-plugin reescribe. Van en `package.json` con la versión **exacta**, y esa
  versión es la misma en los tres sitios: `package.json`, la URL y el `$ver` del
  encolado. **Nunca se inlinean en el bundle.** El CDN **no** es un problema en
  este proyecto: es el camino
  (ADR-0002).
- **Workflows:** `make check` compila el JavaScript de `github-script`, que va
  dentro de una cadena YAML y que si no nadie mira hasta que el job corre. **No**
  valida las expresiones `${{ … }}`: un `if` que lea un contexto inexistente
  —`secrets`, por ejemplo— es YAML válido, pasa aquí, y GitHub rechaza el fichero
  entero al recibirlo: rojo a los cero segundos y sin un job que abrir. Eso solo
  lo dice GitHub.
- **`main` está protegida** por una regla de rama de GitHub
  (ADR-0002):
  solo se mezcla por PR con una aprobación y con los checks `lint` y `test` de
  `ci.yml` en verde. Si esos jobs cambian de nombre, hay que cambiar también la
  regla, o ningún PR se podrá mezclar. Por eso mismo `ci.yml` corre en todos
  los PR, también en los de solo documentación.
- **Scripts de `scripts/`:** idempotentes, **sin `WP_CLI`** (corren también
  bajo Playground), raíz con `dirname( __DIR__ )`, y lanzan `RuntimeException`
  cuando algo falla, para que `make provision` se caiga en vez de seguir a
  medias.
- **Listas cerradas en código, no en taxonomía:** los tipos de formación
  (`course`, `one_off`, `e_learning`, `self_paced`, `in_classroom`) y las
  modalidades viven en `Meta/DesignMetaKeys.php`; quién paga, el proceso y la
  situación de la acción, en `Meta/ActionMetaKeys.php`. «Histórico» **no** es un
  tipo: se deduce de las fechas. La lección del sistema anterior es que una
  lista abierta acaba teniendo «Curso» e «Histórico-curso» a la vez (ADR-0003).

### Idiomas

Qué va en cada idioma. Si algo no está en esta tabla, va en castellano: es lo
que lee una persona.

| Qué | Idioma |
|--|--|
| Identificadores que se llaman: clases, métodos, funciones, nombres de fichero | **inglés** |
| Variables y parámetros locales | **inglés** |
| Slugs, claves de meta y sus valores, roles, opciones y hooks | **inglés** |
| Nombres de test | **inglés** (son funciones) |
| Docblocks (`/** … */`) | **inglés** |
| Comentarios sueltos (`// …`) | **castellano** si explican una regla del dominio o una decisión; inglés si son puramente técnicos |
| Cadenas de la interfaz, etiquetas, mensajes de error y avisos | **castellano** |
| ADR, SDD, requisitos, planes, `CHANGELOG`, `README`, este fichero | **castellano** |
| Mensajes de commit, títulos y descripciones de PR | **castellano** |
| `Makefile`: `help` y comentarios | **castellano** |
| URL de las páginas | **castellano** |

**La raya está entre lo que lee una máquina y lo que lee una persona**: en
inglés los slugs de los tipos de contenido y de las taxonomías, las claves de
meta y sus valores (`fmc_modality`, `blended`), los roles
(`fmc_adviser`), las opciones, los hooks y las clases que los reflejan. En
castellano las cadenas, las etiquetas, los rótulos de rol y las URL de las
páginas, que se comparten y se teclean.

Los términos de `fmc_programme`, `fmc_topic`, `fmc_competence` y `fmc_scope` son **datos**, no
identificadores: sus nombres van en castellano y sus slugs, como los escriba
quien los cree.

**Por qué los comentarios del dominio van en castellano:** quien los lee
—persona o agente— tiene que enlazarlos con el requisito y con la cadena que
sale en pantalla, y ambos están en castellano. Los docblocks no: describen la
API, sus etiquetas ya son inglesas y las revisa WPCS.

---

## Política ADR/SDD

Toda decisión no trivial de IA se registra en `docs/adr/` o `docs/sdd/` con
`ai_assistance` (tool, model). Plantillas e índices en esos directorios; el ID
es `max(existentes) + 1`, con ceros a la izquierda, y nunca se reutiliza.

Una ADR aceptada **no se reescribe**: se le añade una `## Adenda — AAAA-MM-DD`,
o se crea una ADR nueva con `supersedes` / `superseded_by` y se actualiza
`registro.md`.

La raya está en **publicado**. Mientras una ADR no haya salido del repositorio
—sin commit, sin enlace compartido— no es todavía la dirección de nadie y se
corrige en su propio texto; ahí es donde se consolida y se renumera. En cuanto
hay commit, el identificador y el texto se congelan y toda corrección es adenda
o ADR nueva.

Evidencia antes que preferencia: cada afirmación técnica lleva su fuente
verificable (ruta del repo con línea, documentación oficial, experimento
reproducible, PR o ADR previa). Los contras se escriben con la misma dureza
que los pros.

---

## Este repositorio se publica en abierto

Va a ser **software libre**, así que **nada de lo que se versiona puede decir
de quién es el despliegue, dónde está, qué infraestructura usa ni cómo era por
dentro el sistema que se sustituye**
(ADR-0002, que hereda la regla de eventos).

Lo que **no** se escribe en `src/`, `docs/`, `scripts/`, `tests/`, `README.md`
ni en este fichero:

| Qué | Dónde va |
|---|---|
| URL de analítica, de avisos de cookies o de servicios de una organización, y sus identificadores | Configuración: el filtro `fmc_chrome`, vacío por defecto |
| El nombre, el escudo, el rótulo o los enlaces legales de una organización | Lo mismo |
| El nombre de la red, del sitio o del subsitio de destino | El `.env`, que no se sube. `.env.dist` los trae **vacíos** |
| Cómo era la instalación anterior por dentro: sus formularios, vistas, campos, fragmentos de código y sus números | `.local/`, que está en el `.gitignore` |
| Cuánta gente hay, en qué áreas y con qué rol | `.local/`, o se escribe la decisión sin la cifra |
| El nombre de otro repositorio del equipo, sus rutas, sus opciones o sus prefijos | `.local/`. **Son privados**: citarlos publica código de otro |
| Rutas absolutas de un portátil | En ningún sitio |

**La razón de una decisión se queda; el dato que la sostenía puede irse.** Se
puede escribir por qué se eligió algo sin publicar el inventario de una
instalación ajena. Vale igual para el argumento de autoridad: «se hace así en
tal repositorio» no es una razón, es una cita —**y además privada**—; lo que
convence es el motivo, escrito entero aquí. Cuando una ADR se apoye en una medición, se dice que está
medida y se deja el material en `.local/`.

**Se comprueba**, no se confía: `make check-public` recorre lo que git
versionaría y falla con el motivo escrito. Las reglas genéricas están en
`scripts/check-public.mjs`; las que nombran a una organización —su marca, sus
sitios, sus programas, sus repositorios— van en `.local/check-public.rules.json`
(o en la variable `FMC_PUBLIC_RULES`), porque versionarlas publicaría justo lo
que buscan. Sin ese fichero solo se aplican las genéricas, y el script lo avisa.

---

## Reglas duras

- **No convertir el repo en plugin de producción.** Ni cabecera, ni
  `readme.txt`, ni hooks de activación, ni rutas de plugin.
- **Código del aplicativo en `src/Fmc/`** → bundle → Code Snippets. No dejar
  cambios solo en el admin de Snippets.
- **Nada de `declare(strict_types=1)`.**
- **No tocar nada del WordPress de producción.** Este repositorio es de solo
  escritura hacia dentro: lo que se escribe, se escribe aquí.
- **No versionar ni citar `.local/`**: son datos personales reales y el mapa
  del sistema anterior.
- **Nada de ninguna organización concreta en lo que se versiona** (ADR-0002):
  ni marca, ni infraestructura, ni destino de despliegue. `make check-public`.
- **Aquí no hay matrícula** (ADR-0005): ni formulario de inscripción, ni
  listas de admitidos. Si una petición empuja hacia ahí, se para y se pregunta.
- **Nada de programas, partidas, servicios o herramientas de la organización
  en el código**: son términos que crea cada instalación. `make check-public`
  conoce los nombres de la de hoy.
- **La migración no lee el gestor de formularios en vivo**: trabaja sobre la
  exportación de `.local/` y escribe en un sitio limpio (ADR-0008).
- El mu-plugin de `scripts/mu-plugins/` es solo desarrollo.
- Diffs pequeños y enfocados. No inventar ficheros que nadie ha pedido.

---

## Definición de hecho

1. `make lint` sin errores.
2. `make test` sin fallos, y `make check-plugin` sin errores de Plugin Check.
3. `make bundle` genera un bundle que pasa `php -l`, y `make snippet-check`
   pasa con el entorno arrancado.
4. `make up` / provisión OK (<http://localhost:8858>, `admin` / `password`).
5. Docs actualizadas si aplica; ADR/SDD con `ai_assistance` si hubo decisión.

---

## Referencia de herramientas

- `make help` y el `Makefile` son la referencia de targets.
- wp-env: **Code Snippets + WPFront User Role Editor + SQL Buddy**
  (ningún gestor de formularios). Puertos `8858` / `8859`.
- El destino en producción **puede ser un subsitio de un multisitio**, a
  diferencia del entorno local, que es un sitio único. Cualquier cosa que
  construya el nombre de una tabla o invoque `wp eval-file` sin `--url=` merece
  una mirada antes de darla por buena.

---

## Skills (`.agents/skills/`)

Viven en:

- `.agents/skills/` — GitHub Copilot, Codex, Cursor y el resto de agentes que
  comparten esa ruta
- `.claude/skills/` — Claude Code

Las copias canónicas viven en `.agents/skills/`, y `.claude/skills/` lleva una
**copia** de cada una. Copia y no enlace porque **en Windows los enlaces
simbólicos no funcionan** sin habilitarlos a mano, y `gh skill` tampoco enlaza.
Las dos carpetas se igualan con `make skills-sync`, y `make check` falla si
difieren.

**Las que hay hoy** —todas de terceros y verbatim, con su índice y su origen en
[`.agents/skills/README.md`](.agents/skills/README.md)—:

| Para | Skills |
|---|---|
| Seguridad | `security-audit`, `wp-plugin-security`, `github-actions-hardening` |
| WordPress | `wp-plugin-development`, `wp-performance`, `wp-wpcli-and-ops`, `wp-project-triage`, `wp-playground`, `blueprint` |
| Pruebas | `playwright-cli` |
| Propia | `changelog` — el bloque de versión y `make release` |

Skills propias: créalas en `.agents/skills/<nombre>/` y ejecuta
`make skills-sync`. Las de terceros, instálalas solo para Copilot y sincroniza
igual:

```bash
gh skill add WordPress/agent-skills wp-performance --agent github-copilot
gh skill update --all
make skills-sync
```

`gh skill` mete la procedencia en el frontmatter del `SKILL.md`. No instales
`--agent claude-code` ni `--agent grok` en este repo: escribirían en
`.claude/skills/` por su cuenta y la canónica dejaría de ser la de `.agents/`. Las de terceros van
**verbatim**: no las reformatees, divergir de upstream complica
`gh skill update`.

### Compatibilidad de skills

Las skills genéricas de WordPress orientan la implementación, pero este
repositorio **no es un plugin distribuible**. La arquitectura del repositorio
siempre prevalece sobre las recomendaciones de una skill:

- No crear un fichero bootstrap de plugin ni cabeceras de plugin ni
  `readme.txt`.
- El código del aplicativo pertenece a `src/Fmc/`.
- Los artefactos de producción son Code Snippets generados con `make bundle`.
- No introducir hooks de activación/desactivación de plugin.
- No asumir rutas o URL relativas a un plugin (`plugin_dir_path()`,
  `plugin_dir_url()`, `plugins_url()`, `register_activation_hook()`).

`wp-plugin-development` se usa para **patrones de desarrollo WordPress**
(hooks, CPT, admin, shortcodes, capabilities, enqueue), no para packaging ni
estructura de plugin.

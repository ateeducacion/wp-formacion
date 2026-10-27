---
id: REQ-0001
title: "Requisitos del aplicativo de formación"
status: Propuesta
date: 2026-09-30
related:
  issues: []
  prs: []
  adrs: [ADR-0002, ADR-0003, ADR-0004, ADR-0005, ADR-0006, ADR-0007, ADR-0008, ADR-0009, ADR-0010, ADR-0011, ADR-0012, ADR-0013, ADR-0014, ADR-0015, ADR-0016, ADR-0017]
  sdds: []
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# REQ-0001 — Requisitos: aplicativo de formación

**Estado:** propuesta. Se escribe el 2026-09-30 a partir de una reunión de
revisión del sistema actual y de la foto de ese sistema tomada el mismo día. Se
valida con quien gestiona hoy la formación antes de pasar a
`Aceptada` (PLAN-0001, fase 1). La primera revisión con la validación fue el
2026-10-21: lo que cambió está en el §9. La segunda, el 2026-10-27: está en el
§10.
**Fuentes:**

- Una reunión de revisión con quien usa el sistema, el 2026-09-30, y las dos
  revisiones del aplicativo con la validación, el 2026-10-21 y el 2026-10-27.
  El material de todas está en `.local/`.
- Una exportación del sistema actual, tomada **solo con lecturas**. El análisis,
  con sus identificadores, está en `.local/`.
- El aplicativo de eventos, del que este hereda la plataforma (ADR-0002).

---

## 1. Propósito

Sustituir el sitio con el que el área gestiona hoy la formación TIC del
profesorado por un aplicativo **versionado, probado y seguro**, sin perder lo
que el sistema actual hace bien. Quien lo usa lo resume así: la aplicación
«como tal está perfecta», lo que se ha quedado viejo es cómo está hecha, y
**sobre todo la seguridad**.

## 2. El problema, dicho por quien lo tiene

- El sistema se levantó sobre el gestor de formularios hace años y
  se ha ido **parcheando**: hay campos repetidos, campos «obsoletos» que siguen
  en el formulario y una casilla que mezcla tres cosas distintas.
- **La seguridad se basaba en la ignorancia.** Los campos que no te tocan se
  esconden en la pantalla; la acción sobre la que abres una incidencia llega en
  la URL. Hoy cualquiera con un asistente de IA cambia un identificador o manda
  un campo oculto, y el sistema lo guarda.
- La plataforma no solo publica el catálogo: **se usa como mesa de trabajo**. Un
  diseño se escribe en borrador durante semanas antes de estar listo.

## 3. Actores

| Actor | Qué hace | Rol |
|---|---|---|
| Administración | Todo, y administrar el aplicativo | `administrator` |
| Validación | Escribe los diseños, los da por finalizados y los pasa a histórico; gestiona todas las acciones | `fmc_curator` |
| Asesoría | Consulta los diseños finalizados; agenda y mantiene sus acciones, abre incidencias, da de alta ponentes (ver §10) | `fmc_adviser` |
| Servicio de formación | Pone el número de expediente de la acción y resuelve las incidencias; nada más | `fmc_training_service` |
| Visitante | Consulta el catálogo de diseños finalizados | — |

Hay un quinto perfil en el sistema actual —una empresa que rellena la
documentación y la situación de las acciones que imparte— cuyo uso hoy no está
confirmado. Queda como pregunta abierta (§8).

## 4. Requisitos funcionales

### 4.1 Diseños de acciones formativas

- **RF-1.** Un diseño tiene título, tipo de formación (curso, acción puntual,
  teleformación, autodirigido, intervención en aula), modalidad (presencial, en
  línea, mixta), horas presenciales y en línea, temática, programas, áreas de
  competencia digital, descripción, destinatarios, objetivos, contenidos,
  metodología, fase práctica, temporalización, observaciones, autoría y tres
  documentos (diseño, minutaje de las sesiones, material de apoyo). Las áreas
  de competencia digital salen del formulario (ver §9).
- **RF-2.** Un diseño lleva un **código** único y estable que se asigna solo
  (ver §9).
- **RF-3.** Ciclo: **borrador → pendiente de revisión → finalizado →
  histórico**. **Solo la validación** escribe diseños, los da por finalizados
  y los pasa a histórico; la asesoría los consulta finalizados, de solo
  lectura (ADR-0004, ADR-0016, ADR-0017; ver §10).
- **RF-4.** Estar en el catálogo público es una decisión aparte de estar
  finalizado, y pertenecer a un programa es otra (ADR-0003).
- **RF-5.** El diseño puede enlazar su perfil de competencia digital, generado
  con una herramienta externa, y mostrarlo integrado en su ficha (ver §9).
- **RF-6.** Catálogo público de diseños finalizados, con búsqueda y filtros por
  modalidad y tipo. Adelantado de la fase 3 (ADR-0013, ver §9).

### 4.2 Acciones formativas

- **RF-7.** Una acción es **una edición de un diseño**: se crea eligiendo el
  diseño, y hereda de él título, tipo, modalidad y horas, que puede ajustar.
  El título ya no se ajusta (ADR-0009, ver §9). El diseño ha de estar
  finalizado (ADR-0016, ver §10).
- **RF-8.** Datos propios: subtítulo, ámbito que la gestiona, centro donde se
  imparte, si es por videoconferencia, plazas, réplicas, quién la paga y de qué
  programa, asesoría responsable, ponentes (varios), plazos —inicio y fin de
  matrícula, enlace de matrícula, lista provisional, reclamaciones, lista
  definitiva—, fechas de inicio y fin, calendario de sesiones en texto,
  criterios de certificación, espacio del curso en la plataforma de
  aprendizaje y observaciones.
- **RF-9.** Estado administrativo (borrador → pagada por el centro → entregada
  al área → cerrada y entregada al servicio) y situación (gestionándose,
  impartiéndose, realizada, aplazada, cancelada).
- **RF-10.** Cifras finales por sexo: matriculados, asistentes y certificados.
  **Los totales se calculan**, no se teclean. Queda sin efecto: las cifras
  se llevan en el sistema de gestión del servicio (ADR-0010, ver §9).
- **RF-11.** El **curso escolar** se deduce de la fecha de inicio.
- **RF-12.** El **número de expediente** lo pone solo el servicio de formación.
- **RF-13.** **Calendario** de acciones con un **código de colores** que diga de
  un vistazo la situación y quién la asume, con los filtros del actual
  (ámbito, tipo, modalidad, estado, situación, ponente). Menos texto que hoy.
  Vistas de mes, semana, día y lista (ver §9).
- **RF-14.** Listados de control por programa, histórico y exportación a CSV;
  los ponentes no se exportan (ADR-0011, ver §9).

### 4.3 Ponentes

- **RF-15.** Ficha con nombre, apellidos, documento de identidad, teléfono,
  cargo, zona, correos, web, dirección, observaciones, «no disponible» y
  diseños para los que se le recomienda.
- **RF-16.** Son **datos personales**: no públicos, fuera de la API y visibles
  solo para quien gestiona acciones. Todas las asesorías los consultan; la baja
  pide motivo (ADR-0011, ver §9).
- **RF-17.** Buscador de ponentes por diseño recomendado y por lo que han
  impartido (ADR-0012, ver §9).

### 4.4 Incidencias

- **RF-18.** Sobre una acción se abre una incidencia con motivos, cambios que se
  proponen y si conllevan coste.
- **RF-19.** La abre quien puede editar la acción; **la acción la fija el
  aplicativo**, no la URL (ADR-0007).
- **RF-20.** Solo el servicio de formación la aprueba o la deniega, con
  observaciones; quien la abrió recibe aviso. El protocolo con el servicio está
  por cerrar (§8, pregunta 6).

### 4.5 Consentimientos y documentos

- **RF-21.** Los documentos que hoy se suben a mano en dos formatos cada vez
  que cambia un texto se **generan al vuelo** desde el propio texto. Propuesta:
  se decide con su ADR en la fase 4.

## 5. Requisitos no funcionales

- **RNF-1. Seguridad.** Todo permiso se comprueba en el servidor, por tipo y
  por campo; nada se protege escondiéndolo (ADR-0006).
- **RNF-2.** Misma plataforma que eventos: Code Snippets, sin ficheros en el
  servidor, CI con cobertura y comprobación de publicación (ADR-0001, ADR-0002).
- **RNF-3.** Accesibilidad WCAG 2.1 AA en las pantallas propias.
- **RNF-4.** Nada de la organización en el repositorio (ADR-0002).
- **RNF-5.** Los listados que hoy consumen otras webs del área siguen
  respondiendo en su dirección, o se avisa con fecha a quien los consume
  (PLAN-0001, fase 5).

## 6. Fuera de alcance

- **La matrícula** del profesorado: la lleva el sistema de gestión del servicio
  de formación (ADR-0005).
- Los procesos de acreditación, las rúbricas y el plan de un curso concreto que
  conviven hoy en el mismo sitio: son históricos y se quedan en el sitio
  archivado (ADR-0008).

## 7. Migración

El sitio nuevo se monta **limpio**; el actual pasa a una dirección de archivo y
se queda de solo lectura. Diseños, acciones, ponentes e incidencias se importan
desde la exportación, con su mapa de campos en `.local/` (ADR-0008).

## 8. Preguntas abiertas

Las que las revisiones del 2026-10-21 y del 2026-10-27 contestaron, del todo o
en parte, lo dicen.

1. ¿Sigue en uso el perfil de empresa? Si sí, ¿qué campos rellena? *No se
   trató.*
2. ¿Valida siempre el área, o también la asesoría puede validar lo suyo?
   *Contestada el 2026-10-27:* los diseños los escribe y los finaliza la
   validación, que es el área; la asesoría los consulta (ADR-0017, §10).
3. ¿Se unifican el catálogo TIC y los cursos recomendados de otros programas, o
   se mantienen como catálogos separados? *En parte:* el catálogo es público y
   estar en él es una casilla aparte (RF-4); que un diseño sea de un programa
   lo dice su término de programa (ADR-0003). Falta decidir si hay un catálogo
   o varios.
4. ¿Se sigue usando el sistema de incidencias? *Sí.* Pero no hay protocolo con
   el servicio de formación: pasa a la pregunta 6.
5. ¿Qué webs consumen hoy los listados como API y quién las mantiene? *En
   parte:* al menos la web del área muestra el catálogo. Falta el inventario.
6. ¿Cuál es el protocolo de incidencias y cancelaciones entre asesoría,
   validación y servicio de formación? Quién abre, quién cierra, y cómo una
   cancelación tramitada en el sistema de gestión del servicio llega también
   aquí.
7. La sección «Estado» de la acción —expediente, situación y campos del
   servicio—, ¿se le oculta a la asesoría o se le enseña de solo lectura?
8. ¿Las asesorías pasan a ser una lista cerrada, y el centro donde se imparte
   se rellena por su código?
9. ¿Se genera el documento del diseño desde su ficha? ¿Y se abre un
   formulario público para que las empresas proveedoras rellenen el diseño?
10. ¿Los ficheros maestros del diseño se guardan en el aplicativo, en vez de en
    un almacenamiento en la nube externo?
11. ¿Cuál es la lista nueva de temáticas, y qué términos de programa repetidos
    se funden en uno?

## 9. Revisión con la validación (2026-10-21)

Primera sesión de la fase 1 del PLAN-0001: la validación recorrió el aplicativo
sobre el entorno local. El material de la reunión está en `.local/`. Este
apartado dice qué cambió y por qué; las secciones anteriores solo llevan una
referencia aquí.

### 9.1 Decisiones con ADR

| Qué cambia | Por qué | Afecta a | ADR |
|---|---|---|---|
| El título de la acción es el de su diseño y no se edita; el subtítulo se añade debajo, nunca en su lugar. Las acciones históricas conservan su título | En el sistema anterior el «subtítulo» sustituía al título, y había acciones que llegaban al sistema de gestión del servicio con un título que no era el del catálogo | RF-7, RF-8 | [ADR-0009](../adr/ADR-0009-la-accion-lleva-el-titulo-de-su-diseno.md) |
| Las cifras y el cierre —matriculados, asistentes y certificados por sexo, documentación del expediente, entrega al servicio, proceso completado— salen de la ficha, del resumen y del CSV de la acción | Se gestionan en el sistema de gestión del servicio, y aquí nadie las rellena | RF-10 | [ADR-0010](../adr/ADR-0010-las-cifras-de-cierre-salen-de-la-ficha-de-la-accion.md) |
| Los ponentes son un directorio que toda asesoría consulta, documento de identidad incluido; cada una edita lo suyo; la baja va a la papelera con motivo obligatorio; sin exportación a CSV; aviso de protección de datos en la ficha | Las asesorías necesitan el documento de identidad para registrar al ponente en los planes de formación de los centros; una baja tiene que poder explicarse; un listado entero con correos y documentos no debe salir de un clic | RF-14, RF-15, RF-16 | [ADR-0011](../adr/ADR-0011-los-ponentes-son-un-directorio-compartido-con-baja-motivada.md) |
| Un diseño declara a cuáles sustituye y hereda sus ponentes recomendados; «Recomendado para» solo ofrece diseños del catálogo; los ponentes se buscan por lo que han impartido, con acciones y horas | Mantener categorías de ponentes se queda viejo; lo impartido ya está en los datos; hoy esas horas se cuentan a mano en una hoja de cálculo | RF-15, RF-17 | [ADR-0012](../adr/ADR-0012-un-diseno-puede-sustituir-a-otro-y-los-ponentes-se-buscan-por-lo-impartido.md) |

### 9.2 Cambios sin ADR propia

- **Código del diseño (RF-2).** Lo asigna el aplicativo: el siguiente al más
  alto que ya exista, con el mismo prefijo. Es de solo lectura para todos. Si
  cada cual escribe el suyo, los códigos se solapan.
- **Calendario (RF-13).** Calendario y listado de acciones son una sola
  pestaña, «Acciones formativas»: se abre en el mes, y se ve también por
  semana, por día o como lista, que es el listado de siempre, con sus
  contadores (`fmc_view`, `fmc_day`). Cada vista exporta a CSV: el calendario,
  lo que empieza en el periodo que enseña; la lista, lo filtrado.
- **Listado de acciones (RF-14).** Filtro «Expediente empieza por», y la
  búsqueda de texto mira también el expediente. El prefijo del expediente dice
  con qué partida se paga una acción; el programa no, porque un diseño creado
  para un programa no tiene por qué impartirse ni pagarse con él. Con los
  filtros de fecha de inicio y el CSV, sustituye al filtrado que hoy se hace a
  mano en una hoja de cálculo.
- **Incidencias (RF-18).** Las acciones con una incidencia pendiente se marcan
  en el listado y en el calendario, y hay un filtro para verlas.
- **Competencia digital (RF-1, RF-5).** El perfil se elige en elidd sin salir
  de la ficha: se abre incrustada en un diálogo y su «Guardar» lo devuelve
  ([ADR-0014](../adr/ADR-0014-el-perfil-de-competencia-digital-se-elige-en-elidd-incrustada.md)).
  La ficha pinta la tabla que da elidd al generar —por áreas, el nivel más
  alto de cada competencia—, con un enlace para abrirlo en elidd
  (`src/Fmc/Domain/DigComp.php`). El campo «Áreas de
  competencia digital» sale del formulario: ahora se trabaja con el marco de
  referencia nuevo. Sus datos (`fmc_competence`) se conservan.
- **Barra de administración.** Sale en el aplicativo para la administración, y
  para quien ha cambiado a otro usuario con WPFront User Role Editor, para que
  pueda volver: así se prueba el aplicativo con el perfil de otro. Nunca dentro
  del panel lateral.
- **Aspecto.** Cabecera, pestañas, menú de usuario, pie y paleta se igualan a
  los de los aplicativos hermanos.
- **Catálogo público (RF-4, RF-6).** Se preguntó cómo verlo y no había forma.
  Es una pantalla del aplicativo que se ve sin sesión, con los diseños
  finalizados que están en el catálogo, y de cada uno solo una parte: la
  descripción, los destinatarios, los objetivos, los contenidos y el perfil de
  competencia digital ([ADR-0013](../adr/ADR-0013-el-catalogo-publico-es-una-pantalla-del-aplicativo.md)).
  Qué campos son públicos hay que confirmarlo con la validación.

### 9.3 Lo que se confirma

- El sitio actual se archiva y se conserva un tiempo, por si hay que recuperar
  algo (ADR-0008).
- Estar en el catálogo es una casilla aparte (RF-4). La marca de «diseño
  finalizado» del sistema anterior se ponía también para poder ver un diseño y
  crearle acciones; aquí eso ya lo da el estado (ADR-0004).
- Las incidencias se siguen usando (§8, pregunta 4).
- Los ponentes no son todos docentes: también los hay externos. No hace falta
  un consentimiento firmado, pero sí informar del tratamiento allí donde se
  introducen datos personales (ADR-0011).

### 9.4 Pendiente

- **Protocolo de incidencias y cancelaciones** entre asesorías, validación y
  servicio de formación: quién abre, quién cierra. Una cancelación tramitada
  en el sistema de gestión del servicio tiene que constar también aquí; si no,
  una acción cancelada se cuenta como impartida (§8, pregunta 6).
- **La sección «Estado» de la acción** —expediente, situación y campos del
  servicio—: oculta o de solo lectura para la asesoría. Hoy el expediente, la
  situación y el personal del servicio le salen deshabilitados y no se guardan
  aunque los mande (ADR-0006); el proceso sí lo lleva ella (§8, pregunta 7).
- **Temáticas y programas.** Hace falta una lista nueva de temáticas, y los
  términos de programa repetidos —y los valores de «quién la asume» que los
  mezclan— se limpian como datos en la migración, no en el código (ADR-0003,
  ADR-0008) (§8, pregunta 11).
- **Asesorías como lista**, y el centro donde se imparte rellenado por su
  código, sobre el catálogo de centros externo que se hereda de eventos
  (ADR-0002) (§8, pregunta 8).
- **Documento del diseño generado** desde su ficha, en vez de un documento
  subido que se desincroniza; y un **formulario público** para que las
  empresas proveedoras rellenen el diseño. Lo segundo no tiene acuerdo: hay
  quien prefiere seguir recibiendo un borrador y mejorarlo. Va con el RF-21 y la
  fase 4 del plan (§8, pregunta 9).
- **Ficheros maestros en el aplicativo**, no en un almacenamiento en la nube
  externo cuyos enlaces han cambiado varias veces. Pide dimensionar el espacio
  de subida del sitio (§8, pregunta 10).
- **Siguiente sesión**, con el servicio de formación y una o dos asesorías.

## 10. Segunda revisión con la validación (2026-10-27)

Segunda sesión de la fase 1 del PLAN-0001, sobre el sitio de pruebas con los
datos migrados. El material de la reunión está en `.local/`.

### 10.1 Decisiones con ADR

| Qué cambia | Por qué | Afecta a | ADR |
|---|---|---|---|
| Lo que ya no se imparte pasa a un estado propio, «Histórico». Sale del listado de vigentes, del catálogo público y de toda elección. Al finalizar un diseño, los que sustituye pasan a histórico. «Recomendado para» y el diseño de una acción ofrecen solo los finalizados | La migración traía lo histórico como borrador o como finalizado. «Fuera del catálogo» no es lo mismo que «histórico». Un borrador no se imparte | RF-3, RF-4, RF-6, RF-7, RF-15, RF-17 | [ADR-0016](../adr/ADR-0016-lo-que-ya-no-se-imparte-es-un-estado-del-diseno.md) |
| La asesoría no escribe diseños ni sube ficheros. Los consulta, solo los finalizados, en la misma ficha de solo lectura y con sus documentos. Ni borradores ni histórico, tampoco por la dirección | Los diseños los hace el área. La asesoría los necesita para saber de qué va un curso antes de agendarlo. Se pidió una sola ficha y no dos | RF-3 | [ADR-0017](../adr/ADR-0017-la-asesoria-consulta-los-disenos-finalizados-y-no-los-escribe.md) |

Y tres adendas: la ADR-0004 recoge las dos decisiones; la ADR-0012, la
búsqueda por lo recomendado y el menú de lo impartido; la ADR-0015, el cargo
por etapa.

### 10.2 Cambios sin ADR propia

- **Nombres.** El aplicativo se llama «Planificador de formación». Las
  pestañas pasan a ser «Planificación de acciones formativas», que es el
  calendario, y «Diseño de acciones formativas», porque no todo diseño es un
  curso. El botón de alta de una acción dice «Agendar acción formativa», y en
  la ficha de un diseño, «Agendar una acción con este diseño». El logotipo de
  quien despliega va por el filtro `fmc_chrome` (ADR-0002), no en el código.
- **Horas del diseño (RF-1).** Son obligatorias según la modalidad: en un
  diseño presencial, las horas presenciales; en uno en línea, las horas en
  línea; en uno mixto, las dos. La descripción sigue siendo obligatoria también para un borrador:
  se valoró y se prefirió así.
- **Código del diseño (RF-2).** Tres cifras, y tras el `A999` viene el `B001`.
  El número de un diseño borrado no se reutiliza, aunque se borre
  definitivamente: el aplicativo recuerda el último que dio. Un código ya ha
  podido salir en un documento y tiene que seguir diciendo de qué diseño se
  trata.
- **Ponentes (RF-15, RF-17).** El filtro «Impartido o recomendado» encuentra
  también a quien se recomienda para un diseño con esas palabras. En el
  listado, «N acción(es)» abre un menú con las acciones que ha impartido, cada
  una a su ficha (adenda de la ADR-0012).
- **Cargo del ponente (RF-15).** El docente en activo se distingue por etapa:
  infantil y primaria, secundaria y bachillerato, formación profesional, o
  escuela oficial de idiomas. Se queda un valor para quien no dijo la etapa
  (adenda de la ADR-0015).
- **Asesoría responsable.** Si todavía no hay ninguna cuenta de asesoría, la
  ficha de la acción dice por qué la lista sale vacía.

### 10.3 Lo que se confirma

- La papelera del escritorio de WordPress basta para recuperar lo borrado: no
  hace falta una pantalla propia en el aplicativo.
- El título y el icono de cada fila abren la misma ficha: quien puede editar,
  la edita; quien no, la ve de solo lectura.

### 10.4 Pendiente

- **Ver el aplicativo con el perfil de otro** desde el propio aplicativo, en
  producción (issue #8): antes hay que comprobar cómo convive el cambio de
  usuario con el inicio de sesión único del sitio de destino, y escribir su
  ADR.
- **Limpieza de los datos migrados**: los borradores que el sistema anterior
  dejó sin marca de histórico, los ponentes que ya no deben estar, y las
  acciones sin diseño. Es una tarea de la migración (ADR-0008), no del código.
- **Temáticas** (§8, pregunta 11; issue #27) y **documento del diseño
  generado** desde su ficha (§8, pregunta 9; issues #10, #11 y #12): siguen
  abiertas.
- **Correo del ponente.** Se habló de que la ficha avise cuando el correo del
  ponente no es de la organización, pero hay ponentes externos que no lo
  tienen. No se decidió.
- **Siguiente sesión**, con el servicio de formación y dos asesorías.

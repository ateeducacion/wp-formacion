# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
y versionado [SemVer](https://semver.org/lang/es/).

La versión de este fichero es la **única** fuente de verdad: `make bundle` la
lee de la cabecera superior y la escribe en el `@version` del bundle.
`make release` crea el tag y la release en GitHub; el despliegue de los snippets
en el sitio de destino se hace después.

## [0.0.7] — 2026-10-28

### Cambiado

- **Los perfiles se llaman como los llama quien los usa**: el rol que da los diseños por finalizados pasa de «Validación de formación» a «Coordinación de formación», y «Servicio de formación», a «Servicio de Perfeccionamiento». Los sitios ya desplegados cogen el rótulo nuevo en la siguiente carga; los permisos y los identificadores de los roles no cambian
- El rótulo de cada rol se puede poner desde el despliegue con el filtro `fmc_role_labels`, porque cómo se llama un perfil en un organigrama no se versiona (adenda de la [ADR-0002](docs/adr/ADR-0002-se-hereda-la-plataforma-del-aplicativo-de-eventos.md)). El filtro cambia el rótulo y nada más: las capacidades siguen saliendo del código
- Los documentos, las ayudas de las fichas y los datos de ejemplo dicen «coordinación» y «servicio de perfeccionamiento». Las ADR ya publicadas no se reescriben: la traducción de nombres está en el [registro de ADR](docs/adr/registro.md)

## [0.0.6] — 2026-10-28

### Añadido

- **Centro de referencia de la asesoría** ([ADR-0022](docs/adr/ADR-0022-la-asesoria-pertenece-a-un-centro-y-solo-ve-sus-incidencias.md)): en el perfil del escritorio, elegido del catálogo de centros y guardado por su código
- Una acción formativa nueva se abre con la asesoría responsable y el ámbito ya propuestos —quien la crea y el ámbito de su centro—, y los dos se pueden cambiar antes de guardar

### Cambiado

- Cada asesoría ve solo las incidencias de sus acciones y las que ha abierto; el servicio de formación y la validación las siguen viendo todas
- Cualquier asesoría edita la ficha de cualquier ponente, y no solo la que dio de alta ella (adenda de la [ADR-0011](docs/adr/ADR-0011-los-ponentes-son-un-directorio-compartido-con-baja-motivada.md))
- La papelera de ponentes, con quién dio de baja a quién y por qué, sigue siendo solo de la validación

### Pendiente antes de desplegar

- Rellenar el centro de referencia en el perfil de cada asesoría: sin él no se propone ámbito y todo queda como hoy

## [0.0.5] — 2026-10-27

Lo que sale de la segunda revisión del aplicativo, el 2026-10-27: el porqué está en el §10 de [REQ-0001](docs/requisitos/REQ-0001-aplicativo-de-formacion.md), en la [ADR-0016](docs/adr/ADR-0016-lo-que-ya-no-se-imparte-es-un-estado-del-diseno.md) y la [ADR-0017](docs/adr/ADR-0017-la-asesoria-consulta-los-disenos-finalizados-y-no-los-escribe.md), y en las adendas de la 0004, la 0012 y la 0015.

### Añadido

- **Estado «Histórico» para los diseños** ([ADR-0016](docs/adr/ADR-0016-lo-que-ya-no-se-imparte-es-un-estado-del-diseno.md)): lo que ya no se imparte se pasa a histórico desde la ficha, y se recupera de él
- Un diseño histórico no sale en el catálogo público ni se puede elegir, y en el listado de diseños tiene su propio contador, aparte de los vigentes
- Solo la validación pasa un diseño a histórico o lo recupera
- **Lo que ha impartido cada ponente, acción por acción**: en el listado, «N acción(es)» abre un menú con las más recientes, cada una a su ficha, y el enlace al listado de acciones de ese ponente
- **Buscar ponentes por lo que se les recomienda**: el filtro «Impartido o recomendado» encuentra también a quien se recomienda para un diseño con esas palabras en el título o en el código, aunque todavía no lo haya impartido
- **Si no hay ninguna cuenta de asesoría**, la ficha de la acción dice por qué «Asesoría responsable» sale vacía
- **Tipo de asesoría** ([ADR-0019](docs/adr/ADR-0019-la-asesoria-es-una-cuenta-del-sitio-con-su-tipo.md)): TIC, STEAM u otra, en el perfil del usuario del escritorio, que pone la administración; se guarda por sitio
- Al elegir la asesoría responsable de una acción, cada cuenta lleva su tipo al lado, y el guardado comprueba que es una cuenta de asesoría del sitio
- El listado y el calendario de acciones se filtran por asesoría y por tipo de asesoría
- **El programa se imputa solo** ([ADR-0018](docs/adr/ADR-0018-el-servicio-imputa-la-accion-al-programa-activo-de-su-diseno.md)): si la acción la asume el servicio de formación, su programa es el programa activo que cubre el diseño
- Si el diseño no está en ningún programa activo, la acción no se guarda con el servicio de formación y se avisa de que la asume el centro
- Los programas llevan una casilla «Activo» en el escritorio de WordPress, que marca la administración
- Cada despliegue pone sus nombres a las opciones de «Asumida por» con el filtro `fmc_funders`
- **La situación de la acción se calcula** ([ADR-0020](docs/adr/ADR-0020-la-situacion-se-calcula-y-la-accion-se-entrega.md)): en blanco hasta que empieza, «Impartiéndose» en sus fechas y «Realizada» cuando pasan
- **La incidencia dice qué pide**: un cambio, aplazar la acción, con sus fechas nuevas, o cancelarla; al enviarla, la acción queda aplazada o cancelada, y una aplazada vuelve a ir por sus fechas cuando se le ponen las nuevas
- Al abrir una incidencia se avisa por correo a quien dice el despliegue con el filtro `fmc_incident_recipients`
- Una acción cancelada no cuenta horas, ni en lo impartido de los ponentes ni en la ficha ni en el CSV
- **La acción se guarda en borrador o se entrega**: el borrador sale marcado en el calendario y solo lo ven quien lo escribe y quien gestiona; entregada, la asesoría ya no la cambia y pide los cambios con una incidencia
- El listado de acciones se filtra por borrador o entregada, y el CSV lleva la situación
- **Cada temática dice lo que engloba**: al pasar por ella en el selector y debajo de la elegida, con la descripción del término
- **El centro donde se imparte se elige del catálogo de centros** ([ADR-0021](docs/adr/ADR-0021-el-centro-donde-se-imparte-se-elige-del-catalogo-por-su-codigo.md)), buscando por su nombre o su código; la acción guarda el código, y el nombre al lado para el calendario, el listado y el CSV
- El catálogo es una copia local que se pone al día sola cada día; si una descarga llega mal, se sigue con la copia anterior
- **«Ajustes → Centros educativos»** en el escritorio: la administración pone la dirección del listado de centros, ve el estado de la copia y la actualiza sin esperar

### Cambiado

- **El aplicativo se llama «Planificador de formación»**, y sus pestañas, «Planificación de acciones formativas» (el calendario) y «Diseño de acciones formativas»
- En el escritorio de WordPress, el menú de los diseños pasa a llamarse «Diseños de acciones formativas»
- El botón de alta de una acción dice «Agendar acción formativa», y en la ficha del diseño, «Agendar una acción con este diseño»
- **La asesoría consulta los diseños y no los escribe** ([ADR-0017](docs/adr/ADR-0017-la-asesoria-consulta-los-disenos-finalizados-y-no-los-escribe.md)): ve solo los finalizados, en la misma ficha de solo lectura y con sus documentos
- La asesoría no abre borradores ni históricos, tampoco tecleando la dirección
- La asesoría deja de poder subir ficheros: ya no sube documentos de diseño, y con ese permiso veía en la mediateca los de todos los diseños
- En un sitio ya desplegado, las capacidades de escribir diseños se le quitan a la asesoría en cuanto se despliega esta versión, sin hacer nada más
- **Solo se elige lo finalizado**: el diseño de una acción y «Recomendado para» ofrecen los diseños finalizados, estén o no en el catálogo público
- Lo que ya estaba elegido se conserva, con su estado al lado
- El guardado también lo comprueba: una acción con un diseño sin finalizar no se guarda, aunque el formulario llegue con él
- Pasar un diseño a histórico no pide completar los campos obligatorios
- **Al finalizar un diseño, los que sustituye pasan a histórico**, en vez de quedarse como estaban; «Sustituye a» lo explica en la ficha
- Cada diseño sustituido pasa a histórico una sola vez: si se recupera a mano, no vuelve a él
- El diseño nuevo puede llevar el mismo título que el que sustituye
- **El expediente lo pone solo el servicio de formación**: la validación deja de poder escribirlo, también en los sitios ya desplegados
- La ficha de la acción ya no tiene proceso, situación tecleada, negociado ni responsable del servicio
- **Las horas del diseño son obligatorias según la modalidad**: presencial, las presenciales; en línea, las en línea; mixta, las dos
- **El código del diseño lleva tres cifras**, y tras el `A999` viene el `B001`
- El número de un diseño borrado no vuelve a darse, aunque se borre definitivamente
- **Documentos del diseño**: el diseño y la escaleta de las sesiones se suben en editable (DOC, DOCX u ODT), las presentaciones en PPT, PPTX u ODP, y los recursos en PDF, para conservar la versión
- El centro escrito a mano antes se conserva, y la ficha lo enseña hasta que se elige uno de la lista; ya no se escribe a mano
- **La acción ya no pide los plazos de matrícula**: el inicio y el fin de la matrícula, su enlace, las listas provisional y definitiva y las reclamaciones se rellenan en el sistema de gestión del servicio; quedan la fecha de inicio, la de fin y los días y horas de las sesiones
- **El cargo del ponente distingue la etapa**: infantil y primaria, secundaria y bachillerato, formación profesional, escuela oficial de idiomas, o docente en activo sin etapa indicada

### Pendiente antes de desplegar

- **Volver a importar** con el importador de `.local/`, que ahora trae lo histórico del sistema anterior como «Histórico» y el cargo por etapa
- **El logotipo** de quien despliega va en `brand` del filtro `fmc_chrome`, en el snippet de despliegue
- **Desplegar también el snippet de roles**, que es el que le quita a la asesoría el permiso de subir ficheros
- **Marcar los programas activos** en el escritorio de WordPress: la migración no marca ninguno
- **Revisar el mapa de temáticas** viejas a nuevas, que es provisional, antes de importar
- **Poner el tipo de asesoría** en el perfil de cada cuenta de asesoría
- **Poner los destinatarios del aviso de incidencias** con el filtro `fmc_incident_recipients` en el snippet de despliegue

## [0.0.4] — 2026-10-22

Lo que sale de la primera revisión del aplicativo, el 2026-10-21: el porqué está en el §9 de [REQ-0001](docs/requisitos/REQ-0001-aplicativo-de-formacion.md) y en las ADR de la 0009 a la 0012.

### Añadido

- **Ponentes por lo que han impartido** ([ADR-0012](docs/adr/ADR-0012-un-diseno-puede-sustituir-a-otro-y-los-ponentes-se-buscan-por-lo-impartido.md)): filtro «Ha impartido» por palabra clave sobre los títulos y subtítulos de sus acciones, combinable con el prefijo del expediente y las fechas de inicio. Cada ponente dice cuántas acciones y cuántas horas ha impartido, y el listado se ordena por acciones, horas o zona.
- **Un diseño puede sustituir a otros** ([ADR-0012](docs/adr/ADR-0012-un-diseno-puede-sustituir-a-otro-y-los-ponentes-se-buscan-por-lo-impartido.md)): los ponentes recomendados para el diseño sustituido cuentan como recomendados para el nuevo, y el viejo sigue consultable como histórico.
- **Baja motivada de ponentes** ([ADR-0011](docs/adr/ADR-0011-los-ponentes-son-un-directorio-compartido-con-baja-motivada.md)): asesoría y validación mandan a la papelera cualquier ponente diciendo por qué, y queda quién, cuándo y el motivo. Se hace con el botón rojo «Dar de baja» de la barra de guardar, que pide el motivo en un aviso. La validación y la administración ven la papelera, con el motivo, desde el listado.
- **Aviso de protección de datos** en la ficha del ponente. El texto legal lo pone quien despliega con el filtro `fmc_privacy_notice`.
- **Calendario por semana y por día**, además de por mes. Calendario y listado de acciones pasan a ser una sola pestaña, «Acciones formativas», que se abre en el calendario; el listado de siempre es su vista «Lista». La cabecera es la misma en todas las vistas, para que nada se mueva al cambiar, y el periodo —flechas, «Hoy», título y recuento— va debajo de los filtros. Cambiar de vista conserva la fecha. Todas las vistas exportan a CSV; desde el calendario, lo que empieza en el periodo que se ve. Los enlaces viejos a la pestaña del calendario siguen llevando a él.
- **Listado de acciones: «Expediente empieza por»**, y la búsqueda de texto también encuentra por número de expediente. Con las fechas de inicio y el CSV, da lo que hoy se filtra a mano en una hoja de cálculo.
- **Incidencias pendientes a la vista**: la acción que tiene una se marca en el listado y en el calendario, y hay un filtro para verlas.
- **Catálogo público** ([ADR-0013](docs/adr/ADR-0013-el-catalogo-publico-es-una-pantalla-del-aplicativo.md)): `?fmc_catalogo=1` lista, sin sesión, los diseños finalizados que están en el catálogo, con búsqueda por título o código y filtros de tipo, modalidad y temática; `?fmc_curso=ID` es la ficha de un curso, con su descripción, destinatarios, objetivos, contenidos y perfil de competencia digital. La página que el tema daba a cada diseño lleva ahora a esa ficha, y un diseño fuera del catálogo ya no se ve por su dirección. Enlaces: «Ver el catálogo público» en el listado de diseños y «Ver en el catálogo» en la ficha.
- **Las tablas se ordenan por sus cabeceras**, en el servidor y en los dos sentidos: acciones por título o fechas, diseños por título o código, ponentes por nombre, isla o lo impartido, e incidencias por fecha. Lo que no tiene el dato va al final; filtrar, paginar, los contadores y el CSV respetan el orden.
- **El perfil de competencia digital se elige en elidd sin salir de la ficha** ([ADR-0014](docs/adr/ADR-0014-el-perfil-de-competencia-digital-se-elige-en-elidd-incrustada.md)): «Elegir los indicadores en elidd» la abre incrustada en un diálogo, con lo ya elegido, y su «Guardar» escribe la selección en el campo. La ficha y el catálogo pintan la tabla que da elidd al generar: por áreas y con sus colores, el nivel más alto de cada competencia, y todos al pasar el ratón. El filtro `fmc_digcomp_tool` apunta a otra copia de elidd; sin él, la pública. Necesita la versión de elidd con el modo incrustado.
- **La barra de administración de WordPress** sale en el aplicativo para la administración, y para quien ha cambiado a otro usuario con WPFront User Role Editor, para que pueda volver. Nunca dentro del panel lateral.

### Cambiado

- **La isla y el cargo del ponente se eligen de una lista** ([ADR-0015](docs/adr/ADR-0015-la-isla-del-ponente-es-una-taxonomia-y-el-cargo-una-lista-cerrada.md)). La isla o zona es una taxonomía nueva, `fmc_zone`, cuyas islas crea cada instalación. El cargo tiene tres opciones: docente en activo, docente jubilado y no docente o externo.
- **Al elegir los ponentes de una acción**, cada uno ocupa dos líneas: el nombre con «★ Recomendado» o «No disponible», y debajo su isla, su cargo y lo que ha impartido. Los no disponibles se pueden seguir añadiendo, pero salen en gris y al final de la lista.
- **Observaciones del ponente en un bocadillo**, junto al nombre, con un tooltip de Bootstrap: en el listado se leen al pasar el ratón y enteras al pulsarlo, y también al elegir ponentes para una acción. Para el tooltip se carga el JavaScript de Bootstrap 5.3.8, desde jsDelivr con SRI.
- **Los ponentes recomendados siguen al diseño**: al elegir o cambiar el diseño de la acción, su estrella se pone al día y suben los primeros de la lista, sin guardar ni recargar.
- **El título de la acción es el de su diseño** ([ADR-0009](docs/adr/ADR-0009-la-accion-lleva-el-titulo-de-su-diseno.md)): se pone al crearla y al cambiarle el diseño, y no se edita. Las acciones que ya tienen otro título lo conservan. El subtítulo se ve debajo del título en el listado, en el calendario y en la cabecera de la ficha, nunca en su lugar.
- **El código del diseño lo pone el aplicativo**: el siguiente al más alto, con el mismo prefijo. Nadie lo edita.
- **Toda asesoría consulta cualquier ponente** ([ADR-0011](docs/adr/ADR-0011-los-ponentes-son-un-directorio-compartido-con-baja-motivada.md)), documento de identidad incluido, de solo lectura. Cada una sigue editando solo los que dio de alta.
- **«Recomendado para» solo ofrece diseños del catálogo** ([ADR-0012](docs/adr/ADR-0012-un-diseno-puede-sustituir-a-otro-y-los-ponentes-se-buscan-por-lo-impartido.md)), en la ficha del ponente y en el filtro del listado, con el código delante del título para buscar por él. Las recomendaciones ya guardadas se conservan. Los filtros con buscador ocupan media fila y el elegido se ve en una línea.
- **El panel lateral se abre por encima de la barra de WordPress**, y los avisos, por encima del panel.
- **Los enlaces del aplicativo dentro del panel se quedan en el panel** («Abrir una incidencia», «Sobre la acción», cada incidencia de la ficha). Antes, los que no llevaban la clase del panel llevaban a la ficha suelta, sin estilos; y si esa dirección se abre como página —otra pestaña, un marcador—, ahora se pinta entera.
- **El título de una ficha nueva concuerda**: «Nueva incidencia», «Nueva acción formativa», «Nuevo diseño de curso», «Nuevo ponente».
- **Los botones de cada fila son iconos** —lápiz para editar, ojo para ver, aviso para abrir una incidencia y papelera para borrar—, con su nombre al pasar el ratón y para los lectores de pantalla. Los iconos son Bootstrap Icons 1.11.3, desde jsDelivr con SRI, como el resto de librerías.
- **En la lista de acciones, el expediente va debajo del título**: una columna menos, y «Ámbito y centro» tiene más sitio.
- **Ponentes sin tarjetas de recuento**: no tienen estados que contar. Encima de la tabla sale cuántos encuentran los filtros y, para la validación, el enlace a la papelera y de vuelta.
- **La asesoría abre cualquier acción publicada** desde el calendario o el listado, de solo lectura; sigue editando solo las suyas.
- El aspecto del aplicativo —cabecera, pestañas, menú de usuario, pie y paleta— se iguala al de los aplicativos hermanos. `fmc_chrome` admite una pieza más, `brand`, para el logotipo y el nombre de la organización delante del del aplicativo.

### Corregido

- **El snippet del aplicativo no se podía guardar con Code Snippets 4**, que dice «Cannot redeclare class MetaTypes»: su validador lee el código sin ejecutarlo y no ve la guarda contra la doble carga. Ahora cada clase va en su `class_exists()`, y las interpolaciones `{$…}`, que descuadraban al validador, se escriben sin llaves. `make snippet-check` vuelve a pasar.
- **«Recomendado para» confundía la posición en la lista con un diseño**: un ponente con dos recomendados contaba como recomendado también para el diseño número 1, porque la búsqueda miraba con `LIKE` dentro de la lista guardada. Ahora la lista se lee en PHP.

### Eliminado

- **«Cifras y cierre» de la acción** ([ADR-0010](docs/adr/ADR-0010-las-cifras-de-cierre-salen-de-la-ficha-de-la-accion.md)): matriculados, asistentes y certificados, documentación del expediente, entrega al servicio y proceso completado salen de la ficha, del resumen y del CSV. Se llevan en el sistema de gestión del servicio de formación; lo que ya esté guardado se conserva.
- **Exportar ponentes a CSV** ([ADR-0011](docs/adr/ADR-0011-los-ponentes-son-un-directorio-compartido-con-baja-motivada.md)): el botón desaparece y el servidor rechaza la exportación aunque se pida a mano.
- **«Áreas de competencia digital»** sale del formulario del diseño: se trabaja con el perfil de competencia. Los datos se conservan.

### Pendiente antes de desplegar

- Un snippet del despliegue que conteste al filtro `fmc_privacy_notice` con el texto legal del tratamiento de datos. Sin él, la ficha del ponente enseña un texto genérico.
- **Las islas o zonas** se crean en el destino (`fmc_zone`), y los ponentes que ya estén allí pasan su isla del campo de texto antiguo al término, y su profesión libre a uno de los tres cargos. Hasta entonces salen sin isla ni cargo, y guardar una ficha deja el cargo vacío.

## [0.0.3] — 2026-10-07

### Corregido

- **El calendario enseñaba a una asesoría los borradores de acciones ajenas**, con su título y su enlace, que el listado sí le escondía: la consulta del calendario se saltaba el filtro de visibilidad.

### Añadido

- **El filtro `fmc_chrome`**, vacío por defecto: lo que quien despliega añade a la cabecera (analítica, aviso de cookies) y al pie (enlaces legales) del aplicativo, sin versionarlo.
- Tests de las pantallas del aplicativo —listados, calendario, ficha de edición, documentos, enrutado y permisos por perfil—: la cobertura pasa del 56 % al 97 %, y Codecov exige el 90 % al proyecto y a cada PR.

### Cambiado

- Bootstrap entra en `package.json` con la misma versión que se carga de jsDelivr, como Tom Select y SweetAlert2; un test comprueba que cada librería del CDN coincide.
- Members deja de formar parte del entorno: los roles y sus capacidades salen del código. WPFront User Role Editor se queda, para suplantar a alguien y probar el aplicativo con su perfil.

## [0.0.2] — 2026-10-07

### Corregido

- El snippet del aplicativo se desactivaba al guardarlo en Code Snippets: su validador tomaba el método `count()` de los listados por una redeclaración de la función de PHP. Se renombra, y un test impide que un método vuelva a llamarse como una función nativa.

## [0.0.1] — 2026-10-07

Primera versión publicada: la que se lleva al sitio de destino.

### Añadido

- **Esqueleto del aplicativo de formación**, sobre la misma plataforma que el aplicativo de eventos ([ADR-0002](docs/adr/ADR-0002-se-hereda-la-plataforma-del-aplicativo-de-eventos.md)): entorno wp-env y Playground, empaquetado en un snippet, sincronización con Code Snippets, CI, comprobación de publicación y skills de agentes.
- **Cuatro tipos de contenido**: diseño de curso, acción formativa, ponente e incidencia, cada uno con sus capacidades.
- **Cuatro taxonomías**, una por dimensión: programa, temática, área de competencia digital y ámbito ([ADR-0003](docs/adr/ADR-0003-una-dimension-un-sitio.md)).
- **El ciclo del diseño con los estados de WordPress**: la asesoría escribe y manda a revisión; solo la validación lo da por finalizado, y finalizado ya no lo edita la asesoría ([ADR-0004](docs/adr/ADR-0004-el-ciclo-del-diseno-son-los-estados-de-wordpress.md)).
- **Permisos por campo en el servidor**: el número de expediente y la resolución de una incidencia solo los guarda el servicio de formación, aunque otro los mande ([ADR-0006](docs/adr/ADR-0006-los-permisos-se-deciden-en-el-servidor-campo-a-campo.md)).
- **Tres roles**: validación, asesoría y servicio de formación.
- **Requisitos, decisiones y plan de implantación y migración** en `docs/`.
- **El aplicativo en la portada del sitio**: tras iniciar sesión se abre directamente, con la cabecera y las pestañas del aplicativo de eventos.
- **Calendario mensual** de acciones: el color es la situación, el punto dice quién la paga, y las cintas marcan el desdoble y los datos incompletos. Con los filtros de ámbito, curso escolar, programa, modalidad, proceso y situación.
- **Listados** de acciones, diseños, ponentes e incidencias, con contadores que filtran, filtros y paginación.
- **Fichas de edición** por secciones para los cuatro tipos. Lo que no le toca a cada perfil sale deshabilitado y, además, no se guarda aunque se mande.
- **El ciclo del diseño en la ficha**: la asesoría guarda el borrador o lo manda a revisión; solo la validación lo da por finalizado o lo devuelve a borrador.
- **Incidencias desde su acción**: nacen colgadas de ella y pendientes.
- **Panel lateral para editar y añadir**, como en eventos: «Editar», el título de cada fila, «+ Añadir» y las acciones del calendario abren la ficha en un panel de Bootstrap que se desliza desde la derecha. Se guarda sin salir de él; al cerrarlo, la vista de debajo se recarga si se guardó algo.
- **Listas de varios con buscador** (Tom Select): ponentes, programas, áreas de competencia y diseños recomendados se ven como etiquetas con su «×» y se añaden escribiendo. Las listas largas de una sola opción —diseño, asesoría, ámbito— también llevan buscador.
- **Borrar, solo administración**, con confirmación de SweetAlert2: manda a la papelera.
- **Ajustes a partir del uso del sistema anterior** (comparando pantalla a pantalla):
  - Calendario de lunes a viernes (el fin de semana solo si ese mes hay algo), con el centro donde se imparte y quién asume el coste y de qué programa en cada acción; filtros por tipo y por ponente.
  - Listado de acciones con expediente, ámbito y centro, ponentes, tipo y horas, fechas con el texto de días y horas, y estado; «Incidencia» en cada fila; filtro por rango de fechas de inicio.
  - **Exportar CSV** en todos los listados, con los filtros aplicados y todas las columnas de la ficha: el «informe a la carta» de antes.
  - Catálogo con miniatura, competencias y marca de «en el catálogo»; filtro por catálogo.
  - Ponentes con contacto y diseños recomendados; filtro «recomendado para».
  - Incidencias con expediente, ámbito, quién la creó, motivos y resolución; la incidencia se llama como su acción.
  - El selector de diseño dice el tipo; lo que la acción deja en blanco (modalidad, horas) se toma del diseño; «Crear una acción con este diseño» desde un diseño finalizado; enlace para añadir un ponente que no está en la lista.
  - **Editor enriquecido** (el TinyMCE de WordPress) en los textos con formato: descripción, objetivos, contenidos, metodología… y en las observaciones y criterios de la acción.
- **Documentos del diseño**: imagen representativa, diseño, minutaje, material de apoyo y otros documentos. Cada uno se sube, se cambia por otro o se quita desde la ficha, y se comprueba su formato real (un texto con extensión .pdf no entra). Los del sistema anterior salen como enlaces.
- **Las reglas de los formularios anteriores**: las horas presenciales y en línea según la modalidad, el expediente de formación solo si la acción va en un plan o seminario, los criterios de certificación solo en línea o mixta, las observaciones de una incidencia solo si está resuelta; mínimos y máximos (horas, plazas, desdoble, cifras); y sin repetir el título de un diseño, el expediente de una acción ni el documento de identidad de un ponente. Lo que se oculta se guarda vacío.
- **Todos los campos del sistema anterior** que siguen en uso: planes e itinerario de formación, expediente de formación, línea estratégica, horas con desplazamiento, matrícula en línea, petición de servicio, personal del servicio y de la empresa, y documentos.
- El correo electrónico del ponente es obligatorio.

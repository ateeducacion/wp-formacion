# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
y versionado [SemVer](https://semver.org/lang/es/).

La versión de este fichero es la **única** fuente de verdad: `make bundle` la
lee de la cabecera superior y la escribe en el `@version` del bundle.
`make release` crea el tag y la release en GitHub; el despliegue de los snippets
en el sitio de destino se hace después.

## [Sin publicar]

Lo que sale de la primera revisión del aplicativo con la curaduría, el 2026-10-21: el porqué está en el §9 de [REQ-0001](docs/requisitos/REQ-0001-aplicativo-de-formacion.md) y en las ADR de la 0009 a la 0012.

### Añadido

- **Ponentes por lo que han impartido** ([ADR-0012](docs/adr/ADR-0012-un-diseno-puede-sustituir-a-otro-y-los-ponentes-se-buscan-por-lo-impartido.md)): filtro «Ha impartido» por palabra clave sobre los títulos y subtítulos de sus acciones, combinable con el prefijo del expediente y las fechas de inicio. Cada ponente dice cuántas acciones y cuántas horas ha impartido, y el listado se ordena por acciones, horas o zona.
- **Un diseño puede sustituir a otros** ([ADR-0012](docs/adr/ADR-0012-un-diseno-puede-sustituir-a-otro-y-los-ponentes-se-buscan-por-lo-impartido.md)): los ponentes recomendados para el diseño sustituido cuentan como recomendados para el nuevo, y el viejo sigue consultable como histórico.
- **Baja motivada de ponentes** ([ADR-0011](docs/adr/ADR-0011-los-ponentes-son-un-directorio-compartido-con-baja-motivada.md)): asesoría y curaduría mandan a la papelera cualquier ponente diciendo por qué, y queda quién, cuándo y el motivo. La curaduría y la administración ven la papelera, con el motivo, desde el listado.
- **Aviso de protección de datos** en la ficha del ponente. El texto legal lo pone quien despliega con el filtro `fmc_privacy_notice`.
- **Calendario por semana y por día**, además de por mes. Calendario y listado de acciones pasan a ser una sola pestaña, «Acciones formativas», que se abre en el calendario; el listado de siempre es su vista «Lista». La cabecera es la misma en todas las vistas, para que nada se mueva al cambiar, y el periodo —flechas, «Hoy», título y recuento— va debajo de los filtros. Cambiar de vista conserva la fecha. Todas las vistas exportan a CSV; desde el calendario, lo que empieza en el periodo que se ve. Los enlaces viejos a la pestaña del calendario siguen llevando a él.
- **Listado de acciones: «Expediente empieza por»**, y la búsqueda de texto también encuentra por número de expediente. Con las fechas de inicio y el CSV, da lo que hoy se filtra a mano en una hoja de cálculo.
- **Incidencias pendientes a la vista**: la acción que tiene una se marca en el listado y en el calendario, y hay un filtro para verlas.
- **Cuadro de competencia digital** en la ficha del diseño: las competencias y los niveles del perfil enlazado, con un enlace para abrirlo en la herramienta.
- **La barra de administración de WordPress** sale en el aplicativo para la administración, y para quien ha cambiado a otro usuario con WPFront User Role Editor, para que pueda volver. Nunca dentro del panel lateral.

### Cambiado

- **El título de la acción es el de su diseño** ([ADR-0009](docs/adr/ADR-0009-la-accion-lleva-el-titulo-de-su-diseno.md)): se pone al crearla y al cambiarle el diseño, y no se edita. Las acciones que ya tienen otro título lo conservan. El subtítulo se ve debajo del título en el listado, en el calendario y en la cabecera de la ficha, nunca en su lugar.
- **El código del diseño lo pone el aplicativo**: el siguiente al más alto, con el mismo prefijo. Nadie lo edita.
- **Toda asesoría consulta cualquier ponente** ([ADR-0011](docs/adr/ADR-0011-los-ponentes-son-un-directorio-compartido-con-baja-motivada.md)), documento de identidad incluido, de solo lectura. Cada una sigue editando solo los que dio de alta.
- **«Recomendado para» solo ofrece diseños del catálogo** ([ADR-0012](docs/adr/ADR-0012-un-diseno-puede-sustituir-a-otro-y-los-ponentes-se-buscan-por-lo-impartido.md)), en la ficha del ponente y en el filtro del listado, con el código delante del título para buscar por él. Las recomendaciones ya guardadas se conservan. Los filtros con buscador ocupan media fila y el elegido se ve en una línea.
- **El panel lateral se abre por encima de la barra de WordPress**, y los avisos, por encima del panel.
- **Los botones de cada fila son iconos** —lápiz para editar, ojo para ver, aviso para abrir una incidencia y papelera para borrar—, con su nombre al pasar el ratón y para los lectores de pantalla. Los iconos son Bootstrap Icons 1.11.3, desde jsDelivr con SRI, como el resto de librerías.
- **En la lista de acciones, el expediente va debajo del título**: una columna menos, y «Ámbito y centro» tiene más sitio.
- **Ponentes sin tarjetas de recuento**: no tienen estados que contar. Encima de la tabla sale cuántos encuentran los filtros y, para la curaduría, el enlace a la papelera y de vuelta.
- **La asesoría abre cualquier acción publicada** desde el calendario o el listado, de solo lectura; sigue editando solo las suyas.
- **Al elegir ponentes en una acción**, cada nombre lleva su isla y cuántas acciones ha impartido, y una «★» y el primer puesto si se le recomienda para el diseño de la acción.
- El aspecto del aplicativo —cabecera, pestañas, menú de usuario, pie y paleta— se iguala al de los aplicativos hermanos. `fmc_chrome` admite una pieza más, `brand`, para el logotipo y el nombre de la organización delante del del aplicativo.

### Eliminado

- **«Cifras y cierre» de la acción** ([ADR-0010](docs/adr/ADR-0010-las-cifras-de-cierre-salen-de-la-ficha-de-la-accion.md)): matriculados, asistentes y certificados, documentación del expediente, entrega al servicio y proceso completado salen de la ficha, del resumen y del CSV. Se llevan en el sistema de gestión del servicio de formación; lo que ya esté guardado se conserva.
- **Exportar ponentes a CSV** ([ADR-0011](docs/adr/ADR-0011-los-ponentes-son-un-directorio-compartido-con-baja-motivada.md)): el botón desaparece y el servidor rechaza la exportación aunque se pida a mano.
- **«Áreas de competencia digital»** sale del formulario del diseño: se trabaja con el perfil de competencia. Los datos se conservan.

### Pendiente antes de desplegar

- Un snippet del despliegue que conteste al filtro `fmc_privacy_notice` con el texto legal del tratamiento de datos. Sin él, la ficha del ponente enseña un texto genérico.

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
- **El ciclo del diseño con los estados de WordPress**: la asesoría escribe y manda a revisión; solo la curaduría lo da por finalizado, y finalizado ya no lo edita la asesoría ([ADR-0004](docs/adr/ADR-0004-el-ciclo-del-diseno-son-los-estados-de-wordpress.md)).
- **Permisos por campo en el servidor**: el número de expediente y la resolución de una incidencia solo los guarda el servicio de formación, aunque otro los mande ([ADR-0006](docs/adr/ADR-0006-los-permisos-se-deciden-en-el-servidor-campo-a-campo.md)).
- **Tres roles**: curaduría, asesoría y servicio de formación.
- **Requisitos, decisiones y plan de implantación y migración** en `docs/`.
- **El aplicativo en la portada del sitio**: tras iniciar sesión se abre directamente, con la cabecera y las pestañas del aplicativo de eventos.
- **Calendario mensual** de acciones: el color es la situación, el punto dice quién la paga, y las cintas marcan el desdoble y los datos incompletos. Con los filtros de ámbito, curso escolar, programa, modalidad, proceso y situación.
- **Listados** de acciones, diseños, ponentes e incidencias, con contadores que filtran, filtros y paginación.
- **Fichas de edición** por secciones para los cuatro tipos. Lo que no le toca a cada perfil sale deshabilitado y, además, no se guarda aunque se mande.
- **El ciclo del diseño en la ficha**: la asesoría guarda el borrador o lo manda a revisión; solo la curaduría lo da por finalizado o lo devuelve a borrador.
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

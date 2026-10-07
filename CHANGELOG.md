# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
y versionado [SemVer](https://semver.org/lang/es/).

La versión de este fichero es la **única** fuente de verdad: `make bundle` la
lee de la cabecera superior y la escribe en el `@version` del bundle.
`make release` crea el tag y la release en GitHub; el despliegue de los snippets
en el sitio de destino se hace después.

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

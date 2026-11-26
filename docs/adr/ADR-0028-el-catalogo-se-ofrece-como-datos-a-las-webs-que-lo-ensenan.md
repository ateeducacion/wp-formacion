---
id: ADR-0028
title: "El catálogo se ofrece como datos a las webs que lo enseñan en sus páginas"
status: Propuesta
date: 2026-11-26
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0002, ADR-0013, ADR-0025]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-fable-5-2"
---

# ADR-0028: El catálogo se ofrece como datos a las webs que lo enseñan en sus páginas

## Estado

Propuesta

## Contexto

El catálogo público es una pantalla del aplicativo
([ADR-0013](ADR-0013-el-catalogo-publico-es-una-pantalla-del-aplicativo.md)).
Pero el profesorado no lo ve ahí: lo ve dentro de otras webs, cada una con su
cabecera, su menú y su aspecto, que hasta ahora pedían el listado al sistema
anterior por una dirección suya y lo pintaban en su página. Al sustituir ese
sistema, esas páginas se quedan sin nada que pedir.

En la tercera revisión con la coordinación se pidió que el catálogo **siga
viéndose en esas webs, como se ve**, con la imagen de cada curso, y que «Ver en
el catálogo» lleve allí.

## Problema

¿Cómo recibe otra web el catálogo, para pintarlo con su aspecto, sin copiarlo y
sin que el aplicativo deje de decidir qué es público?

## Factores de decisión

- Una sola regla de qué sale en el catálogo: la de la pantalla
  (`Catalogue::is_listed()`, [ADR-0013](ADR-0013-el-catalogo-publico-es-una-pantalla-del-aplicativo.md)).
- Que el aspecto de cada web sea cosa suya: el aplicativo no conoce su tema.
- Nada de ninguna organización en el repositorio
  ([ADR-0002](ADR-0002-se-hereda-la-plataforma-del-aplicativo-de-eventos.md)).
- Que se pueda probar aquí, sin la otra web delante.

## Alternativas consideradas

### Opción 1: que la otra web lea la base de datos de este sitio

En un multisitio, la otra web puede cambiar de sitio y consultar los diseños.

- A favor: sin peticiones entre sitios.
- En contra: repite fuera la regla de qué es público, y la ata a cómo se guarda
  aquí cada dato. Un cambio en una meta rompe una web que nadie mira. Y no vale
  para una web que no esté en la misma red.

### Opción 2: servir el catálogo como un trozo de página ya montado

- A favor: la otra web solo lo inserta.
- En contra: el marcado lo decide el tema de cada web. Para que se viera «como
  se ve», el aplicativo tendría que escribir las clases de un tema ajeno, que es
  justo lo que no puede ir en el repositorio.

### Opción 3: servir los datos, y que cada web los pinte

Una ruta pública y de solo lectura que devuelve la lista —con sus filtros y
sus páginas— y cada curso.

- A favor: el aplicativo decide qué se publica; la web, cómo se ve. Se prueba
  aquí entera.
- En contra: una superficie nueva, abierta a cualquiera, que hay que mantener
  estable porque otros dependen de ella.

### Opción 4: enlazar al catálogo del aplicativo y nada más

- A favor: no hay nada que construir.
- En contra: no es lo que se pidió. El catálogo dejaría de estar dentro de las
  webs por las que el profesorado llega.

## Decisión

**Opción 3.**

- `PublicFront\CatalogueFeed` registra dos rutas de lectura, sin sesión:
  `GET fmc/v1/catalogue` (la lista: `s`, `type`, `modality`, `topic` y
  `programme` —una o varias, por comas—, `order`, `page` y `per_page`, con un
  tope de 100) y `GET fmc/v1/catalogue/<id>` (un curso entero).
- **La lista sale de `Catalogue::query()`**, que es también la consulta de la
  pantalla: finalizado y puesto en el catálogo. Un curso de fuera de él contesta
  404, también a la administración.
- **El curso entero lleva lo que publica su ficha**
  (`Catalogue::PUBLIC_FIELDS`) y su ficha técnica (`Documents::PUBLIC_KINDS`).
  Lo de trabajo —metodología, observaciones, autoría, los demás documentos— no
  sale ([ADR-0025](ADR-0025-los-documentos-del-diseno-se-sirven-por-el-aplicativo.md)).
- El resumen de la tarjeta es texto llano: quien lo recibe lo escapa a su manera.
- La respuesta dice que se puede guardar cinco minutos. El catálogo cambia poco.
- **«Ver en el catálogo» y «Ver el catálogo público» llevan a donde diga el
  despliegue**: `Catalogue::public_url()` pasa la dirección propia por el filtro
  `fmc_catalogue_url`, que recibe el diseño. Sin filtro, es la propia. Los
  enlaces de dentro del catálogo del aplicativo no cambian.
- **La imagen representativa de un curso del catálogo es pública**, como su
  ficha técnica: es para lo que se sube. Se sirve por el aplicativo, que
  comprueba en cada petición que el curso sigue en el catálogo, y el navegador
  puede guardarla una hora.

Cómo pinta cada web esos datos —su marcado, sus estilos— no es de este
repositorio: va en un snippet de cada despliegue.

Lo prueban `tests/unit/test-catalogue-feed.php`, `test-catalogue.php` y
`test-documents.php`.

## Consecuencias

### Positivas

- El catálogo se ve donde llega el profesorado, con los datos de siempre al día.
- Qué es público se decide en un sitio.
- La web que lo pinta puede cambiar de aspecto sin tocar el aplicativo.

### Negativas

- Los nombres de los campos de la respuesta son un contrato: cambiarlos rompe a
  quien los lee.
- Cada imagen del catálogo es una petición que pasa por WordPress y lee el
  fichero entero: no hay miniaturas. Con doce o veinticuatro tarjetas por página
  es asumible; no está medido con tráfico real.
- Un curso que sale del catálogo puede seguir viéndose hasta una hora —su
  imagen— o cinco minutos —sus datos— en quien ya lo había pedido.

### Neutras

- No hay ruta para las acciones: el aplicativo no publica qué se imparte ni
  cuándo ([ADR-0005](ADR-0005-la-matricula-no-es-de-este-aplicativo.md)).

## Adenda — 2026-11-26: el curso entero lleva el perfil de competencia, ya resumido

La ficha del catálogo del aplicativo enseña el cuadro de la competencia digital
docente que trabaja el curso. A las otras webs solo les llegaba la dirección
del perfil (`digcomp_url`): para pintar el mismo cuadro tenían que saber leer
esa dirección y llevar dentro el marco —sus áreas, sus competencias y cómo se
llaman—, que es justo lo que esta decisión quería que no hiciera falta.

- `GET fmc/v1/catalogue/<id>` añade **`digcomp`**: las seis áreas, cada una con
  todas sus competencias (`code`, `name`), el nivel más alto que marca el perfil
  (`level`, vacío si no la trabaja) y cuántos indicadores hay en cada nivel
  (`levels`). Sin perfil, o con una dirección que no trae competencias, es una
  lista vacía. `digcomp_url` sigue saliendo: es el enlace al perfil entero, con
  sus indicadores.
- Lo calcula `Domain\DigComp::profile()`, y **el cuadro del aplicativo sale de
  ahí también** (`Editor::digcomp()`): lo que ve quien entra y lo que reciben las
  otras webs no pueden decir cosas distintas.
- Es un campo más, no un cambio de los que había: quien ya leía la respuesta
  sigue leyéndola igual.

No van los textos de los indicadores: el aplicativo no los lleva, y están en la
herramienta a la que apunta `digcomp_url`.

Lo prueban `tests/unit/test-digcomp.php` y `test-catalogue-feed.php`.

Asistencia de IA: Claude Code (claude-fable-5-2).

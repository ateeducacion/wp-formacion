---
id: ADR-0012
title: "Un diseño puede sustituir a otro, y los ponentes se buscan por lo que han impartido"
status: Propuesta
date: 2026-10-21
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0003, ADR-0004, ADR-0011]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0012: Un diseño puede sustituir a otro, y los ponentes se buscan por lo que han impartido

## Estado

Propuesta

## Contexto

Un ponente lleva una lista de diseños para los que se le recomienda
(`fmc_recommended_designs`, en `src/Fmc/Meta/SpeakerMetaKeys.php`), y el
listado de ponentes se filtra por ahí (REQ-0001, RF-17). Hasta ahora el
selector ofrecía **todos** los diseños, también los que ya no están en el
catálogo.

En la revisión del 2026-10-21 la curaduría planteó dos necesidades que al
principio parecían una sola y que hubo que separar:

1. **Un diseño que sustituye a otro.** Un diseño pasa a histórico y otro nuevo
   ocupa su sitio. Los ponentes recomendados para el viejo deberían aparecer
   como recomendados para el nuevo, y el viejo tiene que seguir consultable,
   con sus materiales, como histórico. Es el mismo patrón que el catálogo de
   centros educativos usa cuando un centro pasa a ser otro: el nuevo guarda el
   código del anterior.
2. **Diseños de la misma familia.** Un nivel avanzado de algo que ya tiene
   nivel inicial no sustituye a nada, pero quien lo organiza quiere ponentes
   que hayan dado el inicial. Se habló de **categorías de ponentes** y de un
   botón para «traerse los ponentes de otro diseño». La relación entre dos
   diseños de la misma familia la conoce la curaduría, pero el aplicativo no
   puede deducirla.

Y una tercera, ya resuelta en lo demás: en «Recomendado para» no deberían
salir los diseños que ya no están en el catálogo, porque no se pueden impartir.

Hoy la curaduría lleva a mano, en una hoja de cálculo sacada del CSV de
acciones, cuántas horas ha impartido cada ponente de un programa, para que las
asesorías lo consulten. El material de la reunión está en `.local/`.

## Problema

¿Cómo se encuentra al ponente adecuado para un diseño, sin mantener a mano una
clasificación de ponentes que se queda vieja?

## Factores de decisión

- Lo que un ponente ha impartido ya está en los datos: son las acciones donde
  figura (`fmc_speakers`, en `src/Fmc/Meta/ActionMetaKeys.php`).
- Toda lista que se mantiene a mano se desfasa; la lección del sistema
  anterior es que una lista abierta acaba duplicada (ADR-0003).
- La sustitución de un diseño por otro es un hecho que se declara una vez y no
  cambia.
- La familia de un diseño es una opinión, y cambia con quien pregunta.
- Lo histórico no se borra ni se reescribe.

## Alternativas consideradas

### Opción 1: categorías de ponentes

Una taxonomía —o una lista— de temas, y cada ponente en las suyas.

- A favor: una búsqueda directa por tema, y fácil de explicar.
- En contra: hay que mantenerla, ponente a ponente, para siempre. Duplica lo
  que ya dicen las acciones impartidas, se queda desfasada con el primer tema
  nuevo y, como cualquier lista abierta, acaba con términos repetidos. Fue la
  objeción que se hizo en la propia reunión, y la curaduría la aceptó.

### Opción 2: un botón «heredar los ponentes de otro diseño»

En el diseño nuevo, elegir otro y copiar sus recomendados.

- A favor: hace exactamente lo que se pidió al principio.
- En contra: copia una foto que no se actualiza —un ponente recomendado
  después en el otro diseño no llega— y mezcla en un solo gesto dos cosas
  distintas: sustituir y parecerse. Además no ayuda a encontrar a quien no está
  recomendado en ninguna parte pero ya ha dado algo parecido.

### Opción 3: sustitución declarada y búsqueda por lo impartido

Lo que es un hecho se guarda como dato; lo que es una opinión se resuelve con
una búsqueda.

- A favor: no hay nada que mantener aparte de lo que ya se registra; la
  búsqueda mejora sola con cada acción; los números —acciones y horas— salen de
  los datos y sustituyen a la hoja de cálculo.
- En contra: la búsqueda es por palabras, y depende de cómo se titularon las
  acciones. Un ponente que nunca ha impartido nada no aparece por tema: hay que
  buscarlo por su nombre. Y si nadie declara que un diseño sustituye a otro, los
  recomendados del viejo no pasan al nuevo.

## Decisión

**Opción 3**, en tres piezas:

1. **Sustitución.** Un diseño declara qué diseños anteriores sustituye
   (`fmc_replaces`, una lista de identificadores, en
   `src/Fmc/Meta/DesignMetaKeys.php`). Un ponente recomendado para un diseño
   sustituido **cuenta como recomendado** para el que lo sustituye, también
   si la sustitución se encadena (`Speakers::lineage()`, en
   `src/Fmc/PublicFront/Speakers.php`). El diseño viejo no se toca: sigue
   legible como histórico.
2. **Solo el catálogo.** «Recomendado para» solo ofrece diseños que están en
   el catálogo público (`fmc_in_catalogue`). Los que no están —los
   históricos— no se ofrecen, pero **los valores ya elegidos se conservan**:
   una recomendación antigua no se borra porque el diseño haya salido del
   catálogo.
3. **«Ha impartido».** El listado de ponentes (`src/Fmc/PublicFront/Lists.php`)
   gana un filtro por palabra clave sobre **los títulos y subtítulos de las
   acciones** publicadas en las que figura cada ponente, combinable con el
   **prefijo del expediente** y la **fecha de inicio** de esas acciones. Cada
   ponente muestra **cuántas acciones** y **cuántas horas** ha impartido
   —la cuenta la hace `Speakers::stats()`—, y el listado se ordena por
   acciones, por horas o por zona.

La relación «quiero ponentes de ese otro diseño de la misma familia» la cubre
la búsqueda por palabra clave, **no** un vínculo entre diseños: no se guarda.

## Consecuencias

### Positivas

- Nada nuevo que mantener a mano: ni categorías ni copias de recomendados.
- La hoja de cálculo de horas por ponente sobra: el filtro por prefijo de
  expediente y fechas, con las horas al lado, da lo mismo y está al día.
- Un ponente nuevo aparece en la búsqueda en cuanto imparte su primera acción.
- El selector de «Recomendado para» es más corto y solo ofrece lo que se puede
  impartir.

### Negativas

- La búsqueda es tan buena como los títulos. Un tema que no está en el título
  ni en el subtítulo de ninguna acción no se encuentra.
- Contar acciones y horas por ponente no es un campo guardado: es una
  consulta sobre todas las acciones publicadas, que se lee una vez por
  petición y está pensada para unos miles. No está medida: si las acciones
  crecen mucho, habrá que vigilar lo que tarda el listado.
- La sustitución hay que declararla. Si la curaduría no lo hace al pasar un
  diseño a histórico, sus recomendados se quedan atrás.

### Neutras

- Si con el uso se ve que la familia de diseños sí necesita guardarse, cabe
  como otra relación junto a `fmc_replaces`, con su propia ADR.
- La zona por la que se ordena es la del ponente (`fmc_zone`), no la de las
  acciones que ha impartido.

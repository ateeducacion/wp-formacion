---
id: ADR-0019
title: "Las asesorías son un directorio propio, no cuentas de usuario"
status: Propuesta
date: 2026-10-27
related:
  issues: [25]
  prs: []
  sdds: []
  adrs: [ADR-0003, ADR-0006, ADR-0011, ADR-0017]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0019: Las asesorías son un directorio propio, no cuentas de usuario

## Estado

Propuesta

## Contexto

«Asesoría responsable» (`fmc_adviser`) se elegía entre las cuentas con el rol
de asesoría o de validación (`Editor::advisers()`). En la issue #25, la
validación responde que quiere una lista de asesorías como la de ponentes, en
la que se diga de cada una si es TIC, STEAM u otra. Hoy hay personas asesoras
que no son TIC y que gestionan acciones a petición de los centros de formación.
En el sistema anterior, para que una asesoría saliera había que darla de alta
como usuaria del sitio y marcarle una casilla.

Atar la asesoría a una cuenta tiene dos problemas:

- **Cambia quien la ocupa.** La acción queda unida a la persona, y contar por
  asesoría a lo largo de los cursos cuenta personas.
- **No toda asesoría entra en el aplicativo.** Para elegirla había que crearle
  una cuenta.

## Problema

¿Qué es la asesoría responsable de una acción, y dónde se dice de qué tipo es?

## Factores de decisión

- Contar y filtrar por asesoría aunque cambie quien la ocupa.
- Que una asesoría sin cuenta se pueda elegir.
- El tipo, en una lista cerrada
  ([ADR-0003](ADR-0003-una-dimension-un-sitio.md)).
- Que lo proteja la capacidad del tipo, no la pantalla
  ([ADR-0006](ADR-0006-los-permisos-se-deciden-en-el-servidor-campo-a-campo.md)).

## Alternativas consideradas

### Opción 1: cuentas de usuario con un tipo

- A favor: es poco código.
- En contra: cada asesoría necesita una cuenta, y el histórico va unido a la
  persona.

### Opción 2: una taxonomía de asesorías

- A favor: es ligera.
- En contra: un término no tiene ficha, y no dice quién ocupa la asesoría ni
  qué cuenta usa.

### Opción 3: un tipo de contenido propio, «Asesoría»

Un directorio, como los ponentes: cada asesoría tiene nombre, tipo y, si entra
en el aplicativo, la cuenta de quien la ocupa.

- A favor: la acción apunta a la asesoría, no a la persona. Tiene su ficha y
  su capacidad propia.
- En contra: es un tipo más que mantener. Y las acciones migradas, que
  apuntaban a una cuenta, hay que llevarlas a la asesoría correspondiente.

## Decisión

**Opción 3.**

- **El tipo.** `fmc_advisory` (`PostTypes::ADVISORY`), «Asesoría». No es
  público ni sale en REST. Lo escriben la validación y la administración. La
  asesoría y el servicio de formación no tienen capacidades sobre él
  (`PostTypes::role_caps()`). Su pestaña, «Asesorías», es la última del
  aplicativo.
- **La ficha** (`Fields::advisory()`): nombre (obligatorio y sin repetir),
  tipo (obligatorio) y cuenta en el aplicativo (opcional).
- **El tipo de asesoría** es una lista cerrada, `AdvisoryMetaKeys::KINDS`: TIC,
  STEAM y Otra. Ampliarla es un cambio de código.
- **La acción** guarda la asesoría en `fmc_advisory` (`ActionMetaKeys::ADVISORY`).
  Se elige del directorio, con su tipo al lado, y el guardado comprueba que es
  una asesoría del directorio (`Editor::check_choices()`). El listado y el
  calendario de acciones se filtran por asesoría.
- **`fmc_adviser`**, la cuenta, sale de la ficha de la acción. Sigue
  registrada para lo migrado.
- **La migración** crea una asesoría por cada nombre de asesoría del sistema
  anterior, ligada a su cuenta si la hay, y apunta a ella cada acción. El tipo
  se queda sin poner: hay que darlo en cada ficha.

## Consecuencias

### Positivas

- Se cuenta y se filtra por asesoría aunque cambie quien la ocupa.
- Una asesoría que no entra en el aplicativo se puede elegir.
- El tipo es un valor que se filtra, no una casilla en una cuenta.

### Negativas

- Hay que mantener el directorio: dar de alta la asesoría nueva y cambiar la
  cuenta cuando cambia la persona.
- Lo migrado llega sin tipo, y hay que ponerlo a mano.
- Quién edita una acción sigue dependiendo de su autor, no de la asesoría
  elegida. Si eso tiene que cambiar, se decide aparte.

### Neutras

- La otra mitad de la issue #25, elegir el centro donde se imparte por su
  código, no se ha contestado y queda fuera.

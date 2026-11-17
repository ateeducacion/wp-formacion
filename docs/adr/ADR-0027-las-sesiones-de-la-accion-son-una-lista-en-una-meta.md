---
id: ADR-0027
title: "Las sesiones de una acción son una lista de día y horas en una meta, y el calendario pinta cada una"
status: Propuesta
date: 2026-11-17
related:
  issues: [53]
  prs: []
  sdds: []
  adrs: [ADR-0008, ADR-0020]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0027: Las sesiones de una acción son una lista de día y horas en una meta, y el calendario pinta cada una

## Estado

Propuesta

## Contexto

«Días y horas» (`fmc_schedule`) era un cuadro de texto libre: *«20 y 27 de
marzo y 3 y 24 de abril, de 16:00 a 20:00»*. Cada persona lo escribía a su
manera, y el calendario solo podía poner la acción en su fecha de inicio
([`PublicFront/Calendar.php`](../../src/Fmc/PublicFront/Calendar.php),
`by_range()`), no cada sesión en su día.

En la issue #53 se pidió elegir el día y las horas en vez de escribirlos, y se
aceptó la propuesta: una lista de sesiones en las acciones nuevas, con lo ya
migrado como está, de solo lectura, porque un texto libre no se puede convertir
solo.

La issue dejaba abiertas dos formas de guardarla:

1. **Una meta con la lista**: `array` de `{día, desde, hasta}` en la acción.
2. **Una entrada por sesión**, colgada de la acción.

## Decisión

**Una meta, `fmc_sessions`, con la lista de sesiones de la acción.** Cada
sesión es `array( 'day' => 'AAAA-MM-DD', 'from' => 'HH:MM', 'to' => 'HH:MM' )`,
ordenadas y sin repetir. Un tipo de valor propio, `MetaTypes::SESSIONS`, las
sanea con la misma función que lee el formulario
([`Domain/Sessions.php`](../../src/Fmc/Domain/Sessions.php)): lo que no es una
sesión válida no llega a guardarse, entre por donde entre.

**En la ficha**, una fila por sesión (día, desde, hasta), «+ Añadir sesión» y
el total de horas. Al guardar:

- una fila a medias o que acaba antes de empezar no deja guardar, y vuelve a la
  ficha tal cual se escribió;
- cada sesión tiene que caer entre la fecha de inicio y la de fin de la acción.
  Las fechas siguen mandando: de la de inicio sale el curso escolar y el orden
  del listado, como hasta ahora.

**Lo escrito a mano antes se conserva**: `fmc_schedule` sigue registrada, se
enseña debajo de las sesiones solo si tiene algo, y no se puede cambiar. El
guardado no la toca.

**El calendario pinta cada sesión en su día**, con su horario, aunque la acción
empezara en un mes anterior. Una acción sin sesiones sigue saliendo el día que
empieza. Los contadores cuentan acciones, no sesiones, y en la semana una
acción que tiene sesión no se repite entre las que «siguen en curso».

## Alternativas consideradas

- **Una entrada por sesión, colgada de la acción.** Permitiría consultar las
  sesiones de un mes con una consulta directa y darles campos propios (ponente
  de esa sesión, aula). Descartada por ahora: es un tipo de contenido más, con
  sus permisos, su papelera y su migración, para un dato que hoy solo se pinta.
  El calendario ya pide las acciones de un periodo y recorre sus sesiones en
  PHP; con los volúmenes de un área es suficiente.
- **Convertir el texto migrado a sesiones con expresiones regulares.**
  Descartada: el texto mezcla formatos («martes y jueves», «del 3 al 24»,
  «por la tarde») y un error silencioso pondría sesiones en días que no son.
  Se queda como está y se ve.
- **Sacar las fechas de inicio y fin de las sesiones.** Más cómodo, pero cambia
  de dónde sale el curso escolar y rompe lo migrado, que no tiene sesiones.
  Puede revisarse cuando casi todas las acciones vivas las tengan.

## Consecuencias

### Positivas

- El horario se escribe igual en todas las acciones, y el calendario enseña
  cuándo hay clase de verdad.
- El total de horas de las sesiones se ve al escribirlas.
- Nada de lo migrado se pierde ni se reinterpreta.

### Negativas

- El calendario busca las acciones que **se solapan** con el periodo (empiezan
  antes de que acabe y terminan después de que empiece, o tienen sesiones) y no
  solo las que empiezan en él: la consulta trae más filas, que se filtran en
  PHP. Medido solo en local; si el sitio crece mucho, es el sitio donde mirar.
- No se puede filtrar el listado por día de sesión: la lista está en una meta
  serializada.
- Las horas de las sesiones no se comparan con las del diseño: una acción puede
  tener sesiones que sumen otra cosa. Se deja como información y no como regla,
  porque hay desdobles y horas en línea que no son sesiones.

## Referencias

- Issue #53: la petición, la propuesta y su aceptación.
- [`tests/unit/test-sessions.php`](../../tests/unit/test-sessions.php),
  `test-calendar.php`, `test-editor-save.php`, `test-editor-render.php`.

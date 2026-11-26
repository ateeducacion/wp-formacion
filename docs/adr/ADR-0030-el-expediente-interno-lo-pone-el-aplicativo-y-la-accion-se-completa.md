---
id: ADR-0030
title: "El expediente interno lo pone el aplicativo, y la acción queda completada con el del servicio"
status: Propuesta
date: 2026-11-26
related:
  issues: [24, 63]
  prs: []
  sdds: []
  adrs: [ADR-0006, ADR-0020]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-fable-5-2"
---

# ADR-0030: El expediente interno lo pone el aplicativo, y la acción queda completada con el del servicio

## Estado

Propuesta

## Contexto

Una acción tiene dos números. El del servicio de perfeccionamiento, que lo pone
él cuando la da de alta en su sistema
([ADR-0020](ADR-0020-la-situacion-se-calcula-y-la-accion-se-entrega.md)); y uno
interno, anterior, que es el que la asesoría le da al servicio para que sepa de
qué acción le habla. El sistema anterior generaba el interno solo. Aquí se
había quedado como «Referencia interna», un texto libre «solo para buscar», y
las acciones nuevas no lo tenían.

En la tercera revisión se vio lo que faltaba:

- Sin expediente interno, la asesoría no tiene qué darle al servicio.
- «Expediente», a secas, no dice de quién.
- Una acción entregada se quedaba en «Entregada» para siempre, también cuando
  el servicio ya le había puesto su expediente. Se preguntaba qué faltaba.
- El servicio trabaja con listas, y lo primero que busca son esos dos números y
  cuáles le quedan por poner.
- La cinta «incompleta» del calendario se encendía por no tener plazas, que ya
  no se piden.

## Problema

¿De dónde sale el expediente interno, y cómo se sabe que una acción ya no
espera nada del servicio?

## Factores de decisión

- Que no se pueda teclear mal ni olvidar.
- Que nada se guarde si se puede calcular: lo guardado se queda viejo
  ([ADR-0020](ADR-0020-la-situacion-se-calcula-y-la-accion-se-entrega.md)).
- Que el servicio no note el cambio de sistema.
- El nombre de una organización no va en el código
  ([ADR-0002](ADR-0002-se-hereda-la-plataforma-del-aplicativo-de-eventos.md)).

## Alternativas consideradas

### Opción 1: un número corto, el de la entrada

- A favor: breve y único por construcción.
- En contra: cambia la forma que el servicio ya conoce, y las acciones migradas
  traen la otra.

### Opción 2: la misma forma que tenía, con el prefijo del despliegue

Prefijo, cuenta de quien la crea, fecha y hora.

- A favor: nuevas y migradas se parecen; nadie tiene que aprender nada.
- En contra: es largo, y dos altas de la misma cuenta en el mismo segundo
  coincidirían. No pasa por la pantalla.

### Opción 3: guardar «completada» como un estado más

- A favor: se filtra leyendo un dato.
- En contra: hay que acordarse de ponerlo, que es lo que ya fallaba.

## Decisión

**Opción 2 para el número; el estado, calculado.**

- **El expediente interno** (`fmc_internal_ref`) lo pone el aplicativo al crear
  la acción (`ActionMetaKeys::internal_ref()`), y a la que llegó sin él, la
  primera vez que se guarda. Es de solo lectura para todos. El prefijo es `AF`,
  o el que dé el despliegue con el filtro `fmc_internal_ref_prefix`. Las
  migradas conservan el suyo.
- **Los dos van juntos y arriba**: «Expediente interno» y «Expediente de
  perfeccionamiento». Salen en la cabecera de la ficha, bajo el título en la
  lista, en la tarjeta del calendario y en el CSV, y se busca por los dos. Cada
  uno en su línea: el interno es largo y, seguidos, no caben. Bajo el título,
  donde hay poco sitio, se nombran en corto: «Perfeccionamiento» y, el interno,
  con el prefijo del despliegue —las siglas de la casa, que es como lo conoce
  quien lo lee— o «Interno» si no lo ha puesto
  (`ActionMetaKeys::internal_ref_label()`).
- **Entregada o completada** (`ActionMetaKeys::delivery()`): la acción
  entregada está completada cuando tiene el expediente del servicio. No se
  guarda. El filtro «Estado» ofrece las tres, con una subconsulta
  (`Lists::delivery_sql()`).
- **Lo que espera el servicio se ve**: quien escribe el expediente tiene el
  contador «Sin expediente»; la cinta **!** del calendario marca la entregada
  que lo espera —ya no la que no tiene plazas—, y su entrada en la leyenda
  filtra.
- **Al servicio la planificación se le abre en lista**
  (`Calendar::default_view()`), con los dos expedientes en las primeras
  columnas. A los demás, en el mes.

Lo prueban `tests/unit/test-editor-save.php`, `test-lists.php` y
`test-calendar.php`.

## Consecuencias

### Positivas

- La asesoría tiene el número en cuanto guarda, y el servicio ve de un vistazo
  qué le falta.
- «Completada» no se puede quedar sin poner.

### Negativas

- El expediente interno es largo para una tarjeta del calendario.
- Las acciones antiguas que nunca tuvieron expediente del servicio salen como
  «Entregada» y con la cinta, aunque nadie vaya a ponérselo ya.
- Distinguir al servicio por lo que no puede hacer —no edita acciones ajenas—
  es indirecto: si un día se le da ese permiso, deja de abrírsele la lista.

### Neutras

- El expediente del servicio lo sigue escribiendo solo él
  ([ADR-0006](ADR-0006-los-permisos-se-deciden-en-el-servidor-campo-a-campo.md)).

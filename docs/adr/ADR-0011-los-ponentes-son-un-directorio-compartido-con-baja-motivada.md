---
id: ADR-0011
title: "Los ponentes son un directorio compartido con baja motivada"
status: Propuesta
date: 2026-10-21
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0002, ADR-0006, ADR-0012]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0011: Los ponentes son un directorio compartido con baja motivada

## Estado

Propuesta

## Contexto

`fmc_speaker` guarda datos personales: nombre, documento de identidad,
teléfono, correos y dirección (`src/Fmc/Meta/SpeakerMetaKeys.php`). No es
público y está fuera de REST (REQ-0001, RF-16). Hasta ahora la asesoría tenía
sobre los ponentes lo mismo que sobre sus acciones: crear, editar y borrar
**lo suyo** (`PostTypes::role_caps()`), y el listado de ponentes se exportaba a
CSV como todos los demás.

En la revisión del 2026-10-21 con la curaduría, con alguien de protección de
datos presente, salió esto:

- Las asesorías **necesitan consultar el documento de identidad** de un
  ponente que no han dado de alta ellas: lo piden los planes de formación de
  los centros, en otra plataforma, para registrar a un ponente.
- También se reconoce un uso que no es el previsto —buscar el teléfono de
  alguien para otra cosa—, que es habitual y que hay que tener en cuenta.
- No todos los ponentes son docentes: los hay externos.
- No hace falta un consentimiento firmado, porque el tratamiento es parte de
  la función de quien lo hace y los datos no se publican; **sí** hay que
  informar del tratamiento allí donde se introducen datos personales.
- Si un ponente pide que se le borre o que no se le vuelva a llamar, la
  asesoría que lo atiende tiene que poder darle de baja, pero la curaduría
  quiere **ver quién lo hizo, cuándo y por qué**.
- Exportar a CSV un listado entero con correos y documentos de identidad es
  «ponerlo demasiado fácil». Copiar a mano se puede igual, pero que cueste.

El material de la reunión está en `.local/`.

## Problema

¿Quién ve, quién edita y quién da de baja a un ponente, y cómo se deja rastro
de una baja?

## Factores de decisión

- Las asesorías necesitan consultar la ficha completa de cualquier ponente
  para su trabajo diario.
- Editar lo de otro es otra cosa: el dato lo mantiene quien lo dio de alta.
- Una baja la puede pedir el propio ponente a cualquier asesoría.
- Borrar es mandar a la papelera, y de la papelera se vuelve (decisión
  heredada de eventos, ADR-0002).
- El rastro de una baja tiene que estar en el servidor, no en la memoria de
  nadie.
- Lo que sale del aplicativo de un clic sale del todo.
- El texto legal del tratamiento es de quien despliega, no del repositorio
  (ADR-0002).

## Alternativas consideradas

### Opción 1: cada asesoría ve solo sus ponentes

- A favor: el mínimo acceso posible.
- En contra: rompe el caso de uso principal —registrar en un plan de centro a
  un ponente que dio de alta otra asesoría— y empuja a duplicar fichas, que es
  peor para los datos personales que compartir una.

### Opción 2: directorio compartido, y cualquiera borra sin más

- A favor: lo más sencillo de construir.
- En contra: una baja sin motivo ni autor no se puede explicar si alguien
  pregunta, y una baja por error no se detecta.

### Opción 3: directorio compartido, edición de lo propio y baja motivada

Todas las asesorías **leen** cualquier ficha; cada una **edita** las que creó;
asesoría y curaduría **dan de baja** cualquier ficha, con motivo obligatorio y
rastro.

- A favor: cubre el uso real; la baja la puede hacer quien atiende al ponente;
  queda constancia.
- En contra: más gente ve más datos personales que en la opción 1. Es el
  precio del caso de uso, y se acota con el aviso y sin exportación masiva. Y
  una asesoría puede dar de baja la ficha que mantiene otra: el rastro y la
  papelera lo hacen reversible, no imposible.

## Decisión

**Opción 3.** Extiende la ADR-0006; no la sustituye.

- **Lectura.** Toda asesoría abre la ficha de cualquier ponente **de solo
  lectura**, documento de identidad incluido.
- **Edición.** Sin cambios: cada asesoría edita los ponentes que creó; la
  curaduría y la administración, todos (`PostTypes::role_caps()`).
- **Baja.** Asesoría y curaduría pueden mandar **cualquier** ponente a la
  papelera, y el servidor exige un **motivo**: sin motivo no hay baja. Se
  guardan en metas quién (`fmc_trashed_by`), cuándo (`fmc_trashed_at`) y por qué
  (`fmc_trash_reason`), declaradas en `src/Fmc/Meta/SpeakerMetaKeys.php`; la
  baja la hace la ruta de borrado de `src/Fmc/PublicFront/Screen.php`.
- **Papelera.** La curaduría y la administración tienen en el listado de
  ponentes un filtro «Papelera» que enseña cada baja con su motivo
  (`src/Fmc/PublicFront/Lists.php`, solo para quien puede editar ponentes
  ajenos). Restaurar se hace desde el escritorio de WordPress, que ya lo trae.
- **Sin CSV de ponentes.** El botón desaparece del listado y la ruta de
  exportación lo rechaza en el servidor, aunque se pida a mano
  (`Screen::route()`).
- **Aviso de protección de datos** en la ficha del ponente. El texto legal lo
  pone quien despliega con el filtro `fmc_privacy_notice`
  (`src/Fmc/PublicFront/Editor.php`); el repositorio trae un texto genérico por
  defecto, como hace `fmc_chrome` con la cabecera y el pie (ADR-0002).

## Consecuencias

### Positivas

- Una asesoría encuentra el dato que necesita sin pedírselo a otra ni duplicar
  la ficha.
- Toda baja tiene autor, fecha y motivo, y se puede deshacer.
- Sacar el directorio entero ya no es un clic.
- Quien introduce datos personales ve, en el mismo formulario, para qué se
  tratan.

### Negativas

- Más personas leen más datos personales que antes. El aviso y la ausencia de
  exportación lo acotan; no lo impiden.
- Una asesoría puede dar de baja la ficha que mantiene otra. Se ve y se
  restaura, pero pasa.
- Restaurar exige entrar en el escritorio de WordPress: es una pantalla más
  que conocer, solo para curaduría y administración.
- Sin un snippet de despliegue que conteste a `fmc_privacy_notice`, el aviso
  dice un texto genérico que no es el texto legal de nadie.

### Neutras

- Copiar a mano desde la pantalla sigue siendo posible. Esto no es un control
  de exfiltración, es quitar la vía fácil.
- El acotado por ámbito de la fase 2 (`fmc_scope`) no se aplica a los
  ponentes: el directorio es de todas las asesorías a propósito.

---
id: REGISTRO-ADR
title: "Índice de estados de las ADR"
status: Aceptada
date: 2026-09-30
related:
  issues: []
  prs: []
  adrs: [ADR-0001, ADR-0002, ADR-0003, ADR-0004, ADR-0005, ADR-0006, ADR-0007, ADR-0008, ADR-0009, ADR-0010, ADR-0011, ADR-0012, ADR-0013, ADR-0014]
  sdds: []
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# Índice de ADR

La tabla es el registro único de estados; no se duplican listas por estado.
Ver la [guía](README.md) y la [plantilla](plantilla.md).

Las ocho primeras se redactaron el 2026-09-30, sobre la reunión de revisión y
la foto del sistema actual tomadas ese día, que son material de investigación y
viven en `.local/`. Las decisiones transversales que se heredan del aplicativo
de eventos no se copian aquí: las enumera la [ADR-0002](ADR-0002-se-hereda-la-plataforma-del-aplicativo-de-eventos.md).

De la ADR-0009 a la ADR-0014 salen de la primera revisión del aplicativo con
la validación, el 2026-10-21; su material también está en `.local/`.

El perfil que estas ADR llaman «curaduría» se llama hoy **validación**: da los
diseños por finalizados (rol `fmc_curator`, «Validación de formación»). Las ADR
con commit no se reescriben por un cambio de nombre; esta nota lo traduce.

| ID | Título | Estado |
|----|--------|--------|
| [ADR-0001](ADR-0001-el-repositorio-es-un-entorno-de-desarrollo-no-un-plugin.md) | El repositorio es un entorno de desarrollo, no un plugin | Aceptada |
| [ADR-0002](ADR-0002-se-hereda-la-plataforma-del-aplicativo-de-eventos.md) | Se hereda la plataforma del aplicativo de eventos | Aceptada |
| [ADR-0003](ADR-0003-una-dimension-un-sitio.md) | Una dimensión, un sitio | Aceptada |
| [ADR-0004](ADR-0004-el-ciclo-del-diseno-son-los-estados-de-wordpress.md) | El ciclo del diseño son los estados de WordPress | Propuesta |
| [ADR-0005](ADR-0005-la-matricula-no-es-de-este-aplicativo.md) | La matrícula no es de este aplicativo | Aceptada |
| [ADR-0006](ADR-0006-los-permisos-se-deciden-en-el-servidor-campo-a-campo.md) | Los permisos se deciden en el servidor, campo a campo | Aceptada |
| [ADR-0007](ADR-0007-la-incidencia-cuelga-de-su-accion.md) | La incidencia cuelga de su acción | Aceptada |
| [ADR-0008](ADR-0008-la-migracion-va-a-un-sitio-limpio-y-el-actual-se-archiva.md) | La migración va a un sitio limpio y el actual se archiva | Propuesta |
| [ADR-0009](ADR-0009-la-accion-lleva-el-titulo-de-su-diseno.md) | La acción lleva el título de su diseño | Propuesta |
| [ADR-0010](ADR-0010-las-cifras-de-cierre-salen-de-la-ficha-de-la-accion.md) | Las cifras de cierre salen de la ficha de la acción | Propuesta |
| [ADR-0011](ADR-0011-los-ponentes-son-un-directorio-compartido-con-baja-motivada.md) | Los ponentes son un directorio compartido con baja motivada | Propuesta |
| [ADR-0012](ADR-0012-un-diseno-puede-sustituir-a-otro-y-los-ponentes-se-buscan-por-lo-impartido.md) | Un diseño puede sustituir a otro, y los ponentes se buscan por lo que han impartido | Propuesta |
| [ADR-0013](ADR-0013-el-catalogo-publico-es-una-pantalla-del-aplicativo.md) | El catálogo público es una pantalla del aplicativo y publica solo parte del diseño | Propuesta |
| [ADR-0014](ADR-0014-el-perfil-de-competencia-digital-se-elige-en-elidd-incrustada.md) | El perfil de competencia digital se elige en elidd, incrustada en la ficha | Propuesta |
| [ADR-0015](ADR-0015-la-isla-del-ponente-es-una-taxonomia-y-el-cargo-una-lista-cerrada.md) | La isla del ponente es una taxonomía y el cargo, una lista cerrada | Propuesta |

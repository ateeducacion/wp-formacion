---
id: PLAN-0001
title: "Implantación por fases y migración del sitio de formación"
status: Propuesta
date: 2026-09-30
related:
  issues: []
  prs: []
  adrs: [ADR-0001, ADR-0002, ADR-0003, ADR-0004, ADR-0005, ADR-0006, ADR-0007, ADR-0008]
  sdds: []
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# PLAN-0001 — Implantación por fases y migración

**Estado:** propuesta. La **fase 0 está hecha**. Las demás son el orden
propuesto; a 2026-09-30 no se ha tocado nada en el sitio de destino.
**Basado en:** [REQ-0001](../requisitos/REQ-0001-aplicativo-de-formacion.md),
las ADR del [registro](../adr/registro.md) y el análisis del sistema actual, que
está en `.local/` porque describe por dentro una instalación que no es nuestra.

Cada fase termina en uno o varios PR con `make check` en verde, y se puede
desplegar sin la siguiente.

---

## Fase 0 — Esqueleto (hecha)

- Plataforma heredada de eventos (ADR-0002): wp-env en `8858`/`8859`,
  Playground, empaquetado, sincronización, CI, `check-public`, skills.
- Cuatro CPT, cuatro taxonomías, metas con tipo, lista cerrada y permiso por
  campo; tres roles; datos de demostración.
- Requisitos, ocho ADR y este plan.
- Material de análisis en `.local/`: exportación del sitio, de los formularios
  y sus vistas, y el análisis con el mapa de campos.

## Fase 1 — Validar el modelo con quien lo usa

Sin código. Una sesión con quien gestiona la formación, sobre
el entorno local con los datos de demostración:

- recorrer REQ-0001 y cerrar sus preguntas abiertas (§8);
- confirmar el ciclo del diseño y quién cura (ADR-0004 → `Aceptada`);
- confirmar qué campos del formulario actual sobran y cuáles faltan;
- decidir el perfil de empresa.

**Sale:** REQ-0001 en `Aceptada`, adendas donde toque.

## Fase 2 — Guardián de acceso y ámbitos

- `Access/` con `map_meta_cap`: la acción se acota por `fmc_scope`, como el
  área en eventos; la asesoría ve y edita lo de su ámbito.
- El ámbito en el perfil del usuario, reservado a administración.
- Listado del escritorio acotado, columnas y filtros.
- La incidencia se crea desde su acción, con el padre fijado por el
  aplicativo (ADR-0007).

**Sale:** con solo el escritorio de WordPress ya se puede trabajar, y es
seguro.

## Fase 3 — Pantallas propias (en marcha)

Hecho en la 0.2.0: la portada es el aplicativo, con calendario, listados y
fichas de edición de los cuatro tipos. Falta lo que sigue en la lista.

Mismo armazón que eventos (Bootstrap 5, SweetAlert2, con sus snippets y sus
pruebas en navegador, que vuelven aquí):

- ficha del diseño con su flujo de revisión y la integración del perfil de
  competencia digital;
- alta de acción a partir de un diseño (hereda y ajusta);
- **calendario con código de colores** por situación y financiación, y con los
  filtros actuales;
- vista mínima del servicio de formación: la acción y **solo** su expediente,
  y la bandeja de incidencias;
- catálogo público con búsqueda y filtros;
- listados de control y exportación a CSV;
- `make capturas` y su workflow, que se copian de eventos con la primera
  pantalla.

## Fase 4 — Documentos generados

- Consentimientos y documentos que hoy se suben a mano en dos formatos: se
  generan al vuelo desde su texto. ADR propia antes de empezar.

## Fase 5 — Migración y corte (ADR-0008)

1. **Importador** idempotente en `scripts/`, que lee la exportación de
   `.local/` —nunca el sitio vivo— y aplica el mapa de campos. Se ensaya en
   local hasta que el informe salga limpio: recuentos por tipo, valores que no
   casan con las listas cerradas, diseños sin acción y acciones sin diseño.
2. **Listados consumidos por otras webs:** inventariar quién los llama y
   decidir, uno a uno, si se sirven igual desde el sitio nuevo o se avisa con
   fecha.
3. **Congelación:** el sitio actual pasa a solo lectura; última exportación.
4. **Corte:** el actual se mueve a su dirección de archivo; en la de siempre se
   monta el sitio limpio con Code Snippets y WPFront, los snippets de
   este repositorio y la importación final.
5. **Comprobación:** recuentos contra la exportación, enlaces antiguos más
   usados y una semana de convivencia con el archivo accesible.

## Riesgos

| Riesgo | Mitigación |
|---|---|
| Campos mezclados que no se pueden partir solos | El importador los lista y se deciden a mano en la fase 5.1 |
| Webs externas que dejan de recibir datos | Inventario y aviso antes del corte (5.2) |
| Datos personales de ponentes en tránsito | La exportación vive en `.local/`, nunca en el repositorio ni en un PR |
| Divergencia con la plataforma de eventos | Un arreglo en el andamiaje se lleva a los dos repositorios en el mismo día (ADR-0002) |

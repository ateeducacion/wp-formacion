---
id: PLAN-0001
title: "Implantación por fases y migración del sitio de formación"
status: Propuesta
date: 2026-09-30
related:
  issues: []
  prs: []
  adrs: [ADR-0001, ADR-0002, ADR-0003, ADR-0004, ADR-0005, ADR-0006, ADR-0007, ADR-0008, ADR-0009, ADR-0010, ADR-0011, ADR-0012]
  sdds: []
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# PLAN-0001 — Implantación por fases y migración

**Estado:** propuesta. La **fase 0 está hecha** y la **fase 1, en marcha**:
la primera sesión con la curaduría fue el 2026-10-21. Las demás son el orden
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

## Fase 1 — Validar el modelo con quien lo usa (en marcha)

Sin código. Una sesión con quien gestiona la formación, sobre
el entorno local con los datos de demostración:

- recorrer REQ-0001 y cerrar sus preguntas abiertas (§8);
- confirmar el ciclo del diseño y quién cura (ADR-0004 → `Aceptada`);
- confirmar qué campos del formulario actual sobran y cuáles faltan;
- decidir el perfil de empresa.

**Sale:** REQ-0001 en `Aceptada`, adendas donde toque.

**Hecho, 2026-10-21:** primera sesión, con la curaduría. La reunión se cortó
antes de recorrerlo todo. Lo que cambió está en el
[§9 de REQ-0001](../requisitos/REQ-0001-aplicativo-de-formacion.md), y las
decisiones, en cuatro ADR propuestas:
[ADR-0009](../adr/ADR-0009-la-accion-lleva-el-titulo-de-su-diseno.md),
[ADR-0010](../adr/ADR-0010-las-cifras-de-cierre-salen-de-la-ficha-de-la-accion.md),
[ADR-0011](../adr/ADR-0011-los-ponentes-son-un-directorio-compartido-con-baja-motivada.md)
y [ADR-0012](../adr/ADR-0012-un-diseno-puede-sustituir-a-otro-y-los-ponentes-se-buscan-por-lo-impartido.md).
Las incidencias se siguen usando. Quién cura y el perfil de empresa siguen
sin cerrar.

**Siguiente:** una sesión con el servicio de formación y una o dos asesorías
de las que más tiempo llevan, para cerrar:

- el protocolo de incidencias y cancelaciones —quién abre, quién cierra, y
  cómo una cancelación tramitada en el sistema de gestión del servicio consta
  también aquí—;
- si la sección «Estado» de la acción se le oculta a la asesoría o se le
  enseña de solo lectura;
- lo que quedó sin recorrer en la primera: el resto de la ficha del diseño y
  el catálogo público.

## Fase 2 — Guardián de acceso y ámbitos

- `Access/` con `map_meta_cap`: la acción se acota por `fmc_scope`, como el
  área en eventos; la asesoría ve y edita lo de su ámbito.
- El ámbito en el perfil del usuario, reservado a administración.
- Listado del escritorio acotado, columnas y filtros.
- La incidencia se crea desde su acción, con el padre fijado por el
  aplicativo (ADR-0007).
- La sección «Estado» de la acción, oculta o de solo lectura para la asesoría,
  según salga de la fase 1.
- Los ponentes **no** se acotan por ámbito: el directorio es de todas las
  asesorías (ADR-0011).

**Sale:** con solo el escritorio de WordPress ya se puede trabajar, y es
seguro.

## Fase 3 — Pantallas propias (en marcha)

Hecho en la 0.2.0: la portada es el aplicativo, con calendario, listados y
fichas de edición de los cuatro tipos. Falta lo que sigue en la lista.

Hecho tras la primera sesión de la fase 1 (sin publicar todavía):

- el título de la acción es el de su diseño (ADR-0009) y las cifras de cierre
  salen de la ficha (ADR-0010);
- ponentes como directorio compartido, con baja motivada, sin CSV y con el
  aviso de protección de datos (ADR-0011);
- sustitución de diseños, «Recomendado para» solo con el catálogo y búsqueda
  «Ha impartido» con acciones y horas (ADR-0012);
- el código del diseño lo pone el aplicativo; calendario por mes, semana, día
  y lista; filtro por prefijo de expediente; marca y filtro de incidencia
  pendiente; cuadro de competencia digital en el diseño; barra de
  administración para volver de una suplantación; aspecto igualado al de los
  aplicativos hermanos.

Mismo armazón que eventos (Bootstrap 5, SweetAlert2, con sus snippets y sus
pruebas en navegador, que vuelven aquí):

- ficha del diseño con su flujo de revisión y la integración del perfil de
  competencia digital;
- alta de acción a partir de un diseño (hereda y ajusta);
- **calendario con código de colores** por situación y financiación, y con los
  filtros actuales;
- vista mínima del servicio de formación: la acción y **solo** su expediente,
  y la bandeja de incidencias, según el protocolo que salga de la fase 1;
- asesorías como lista, y el centro donde se imparte rellenado por su código,
  sobre el catálogo de centros que se hereda de eventos (ADR-0002);
- catálogo público con búsqueda y filtros;
- listados de control y exportación a CSV;
- `make capturas` y su workflow, que se copian de eventos con la primera
  pantalla.

## Fase 4 — Documentos generados

- Consentimientos y documentos que hoy se suben a mano en dos formatos: se
  generan al vuelo desde su texto. ADR propia antes de empezar.
- El documento del diseño, generado desde su ficha en vez de subido aparte.
- Por decidir: un formulario público para que las empresas proveedoras
  rellenen el diseño. En la primera sesión no hubo acuerdo.
- Los ficheros maestros del diseño, guardados en el aplicativo y no en un
  almacenamiento en la nube externo; con su cálculo del espacio de subida que
  necesita el sitio.

## Fase 5 — Migración y corte (ADR-0008)

1. **Importador** idempotente en `scripts/`, que lee la exportación de
   `.local/` —nunca el sitio vivo— y aplica el mapa de campos. Se ensaya en
   local hasta que el informe salga limpio: recuentos por tipo, valores que no
   casan con las listas cerradas, diseños sin acción y acciones sin diseño.
   Los títulos de las acciones se importan tal cual, aunque no sean el de su
   diseño (ADR-0009); las cifras de cierre van a sus claves, que se conservan
   (ADR-0010). Antes, la curaduría da la lista nueva de temáticas y decide qué
   términos de programa repetidos —y qué valores de «quién la asume»— se funden
   (ADR-0003); y, si quiere, qué diseños históricos sustituye cada uno
   (ADR-0012).
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

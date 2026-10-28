---
id: ADR-0002
title: "Se hereda la plataforma del aplicativo de eventos"
status: Aceptada
date: 2026-09-30
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0001]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5-5"
---

# ADR-0002: Se hereda la plataforma del aplicativo de eventos

## Estado

Aceptada

## Contexto

El aplicativo de eventos (`wp-eventos`, público) ya sustituyó un sistema
hermano de este, hecho con la misma herramienta, y dejó resueltas las piezas que no son del dominio: entorno,
empaquetado, sincronización, CI, publicación y política de documentación. Quien
encarga este trabajo pide expresamente «el mismo sistema»: se instala como
snippet, está todo en Git y está preparado para cambiarse con ayuda de agentes.

## Problema

¿Se reescriben esas piezas, se comparten como dependencia o se copian?

## Factores de decisión

- Están probadas en producción.
- Los dos aplicativos se despliegan igual, en sitios distintos.
- Una dependencia compartida obliga a publicar versiones de algo que hoy no es
  una librería.

## Alternativas consideradas

### Opción 1: reescribir

Repetir decisiones ya tomadas, y probablemente sus errores.

### Opción 2: librería común

Correcto a largo plazo, prematuro con dos consumidores y ningún tercero a la
vista.

### Opción 3: copiar y adaptar

Copiar el andamiaje con el prefijo `fmc`, sin el dominio de eventos.

## Decisión

**Opción 3.** Se copia el andamiaje de eventos y se **heredan sus decisiones
transversales**, que aquí rigen igual sin reescribirlas. Son, con su número en
aquel repositorio:

| En eventos | Qué decide |
|---|---|
| ADR-0002, ADR-0035 | Los snippets se sincronizan por `wp eval-file` y se reconocen por su contenido |
| ADR-0009 | Los identificadores internos van en inglés; lo que lee una persona, en castellano |
| ADR-0011 | CI y política de pruebas, con suelo de cobertura |
| ADR-0015 | Las librerías de terceros, desde jsDelivr con SRI; en desarrollo, desde `node_modules` |
| ADR-0016 | Borrar es mandar a la papelera |
| ADR-0023 | El bloqueo de edición es el nativo de WordPress |
| ADR-0030 | **El repositorio se publica sin nada de nadie**: ni marca, ni infraestructura, ni el interior del sistema que se sustituye |
| ADR-0034 | `main` solo se mezcla por PR, con revisión y con CI en verde |
| ADR-0037 | El catálogo de centros es externo y se cachea |
| ADR-0038 | Los ámbitos organizativos son jerárquicos y acotan permisos |

Dos cosas cambian respecto a eventos: los puertos del entorno (`8858` /
`8859`, para tener los dos arrancados) y la lista de `make check-public`, que
añade los programas, servicios y herramientas de la organización que usa
formación.

## Consecuencias

### Positivas

- El repositorio nace con CI, cobertura, comprobación de publicación y skills.
- Quien conoce eventos conoce este.

### Negativas

- **Dos copias que pueden divergir.** Un arreglo en el empaquetador o en la
  sincronización hay que llevarlo a los dos a mano. Si aparece un tercer
  aplicativo, toca extraer la librería.

### Neutras

- Las pantallas, los guiones de navegador y las capturas de eventos no se
  copian: llegan con la primera pantalla propia.

## Adenda — 2026-10-28

**El rótulo de un rol también es configuración.** Los tres roles del aplicativo
se llamaban por su función tal como la nombra una organización concreta. Cómo
se llama un perfil en el organigrama de quien despliega es exactamente lo que
esta ADR saca del repositorio —igual que la marca, el pie o los nombres de
quien financia—, y además cambia sin que cambie el aplicativo: una
reorganización renombra el servicio y el código no tiene nada que ver con eso.

Así que `fmc_role_definitions()` (en `snippets/roles-and-profiles.php`) trae
rótulos genéricos —«Coordinación de formación», «Asesoría de formación»,
«Servicio de Perfeccionamiento»— y los pasa por el filtro **`fmc_role_labels`**,
un mapa `slug => rótulo` vacío por defecto, que el despliegue rellena fuera del
repositorio. El filtro cambia solo el rótulo: las capacidades se reparten en el
código (ADR-0006) y no se tocan desde fuera, y un rótulo en blanco deja el del
código. `fmc_register_roles()` ya escribía el nombre del rol cuando difería, así
que un sitio desplegado lo coge en la siguiente carga.

Los slugs no cambian: `fmc_curator`, `fmc_adviser` y `fmc_training_service`
siguen siendo los mismos, porque son identificadores y están escritos en las
capacidades y en los perfiles ya creados.

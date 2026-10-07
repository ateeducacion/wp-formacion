# Formación

[![CI](https://github.com/ateeducacion/wp-formacion/actions/workflows/ci.yml/badge.svg)](https://github.com/ateeducacion/wp-formacion/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/ateeducacion/wp-formacion/graph/badge.svg)](https://codecov.io/gh/ateeducacion/wp-formacion)
[![Probar en WordPress Playground](https://img.shields.io/badge/Probar%20en%20WordPress%20Playground-3858E9?logo=wordpress&logoColor=white)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/ateeducacion/wp-formacion/main/blueprint.json)

Un aplicativo para WordPress que gestiona la **formación del profesorado**: el
catálogo de **diseños de curso**, las **acciones formativas** que se imparten a
partir de ellos, sus **ponentes** y las **incidencias** que se abren sobre una
acción. La asesoría diseña y registra; la curaduría da los diseños por
finalizados; el servicio de formación pone el expediente y resuelve las
incidencias. La matrícula no: esa la lleva el sistema de gestión del servicio.

No es un plugin: se instala pegando un único **Code Snippet**, generado desde
`src/Fmc/`. Es hermano de [wp-eventos](https://github.com/ateeducacion/wp-eventos)
y comparte su plataforma.

> **Estado:** esqueleto (fase 0 del
> [plan](docs/plan/PLAN-0001-implantacion-y-migracion.md)). Hay modelo de
> datos, roles y permisos, con sus tests; las pantallas propias llegan en la
> fase 3.

## Pruébalo sin instalar nada

**[Abrir en WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/ateeducacion/wp-formacion/main/blueprint.json)**:
monta el sitio con los plugins que hacen falta, carga el aplicativo, crea las
cuentas de prueba y un par de diseños y una acción de demostración.

### Usuarios de prueba

La contraseña es `password` en todos. Los crea `scripts/seed-demo.php`.

| Usuario | Rol | Qué puede hacer |
|---------|-----|-----------------|
| `admin` | administrator | Todo |
| `curaduria` | `fmc_curator` | Finalizar diseños y gestionar todas las acciones |
| `asesoria`, `asesoria2` | `fmc_adviser` | Escribir diseños y mandarlos a revisión; sus acciones; abrir incidencias |
| `servicio` | `fmc_training_service` | Poner el expediente de una acción y resolver incidencias |

## Requisitos

- **Docker** (wp-env)
- **Node.js 22** (ver `.nvmrc`)
- **PHP 8.1+** y **Composer**, para el lint y los tests en tu equipo

## En tu equipo

```bash
git clone https://github.com/ateeducacion/wp-formacion.git
cd wp-formacion
make install
make up
```

Sitio en <http://localhost:8858> (`admin` / `password`); tests en el 8859.
Tras cambiar algo en `src/Fmc/`: `make bundle && make sync-snippets`. Antes de
proponer un cambio: `make check`.

> **¿No vienes de desarrollo?** Empieza por
> [Empezar a trabajar en este proyecto](docs/desarrollo-para-empezar.md).

## Más documentación

- [Requisitos](docs/requisitos/REQ-0001-aplicativo-de-formacion.md) y
  [plan de implantación y migración](docs/plan/PLAN-0001-implantacion-y-migracion.md).
- [Decisiones (ADR)](docs/adr/registro.md).
- [AGENTS.md](AGENTS.md): convenciones del repositorio, para personas y agentes.
- [CHANGELOG](CHANGELOG.md).

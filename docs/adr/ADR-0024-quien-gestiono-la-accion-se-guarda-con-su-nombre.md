---
id: ADR-0024
title: "Quién gestionó la acción se guarda con su nombre, y solo la asesoría la gestiona"
status: Propuesta
date: 2026-11-02
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0019, ADR-0021, ADR-0022, ADR-0023]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5"
---

# ADR-0024: Quién gestionó la acción se guarda con su nombre, y solo la asesoría la gestiona

## Estado

Propuesta

## Contexto

La asesoría responsable de una acción es una cuenta del sitio
([ADR-0019](ADR-0019-la-asesoria-es-una-cuenta-del-sitio-con-su-tipo.md)), y se
elige de entre las cuentas con perfil de asesoría o de coordinación. De ahí
salen dos problemas que se ven en los datos reales.

**1. La lista ofrece a quien ya no gestiona acciones.** Quien coordina no
imparte ni gestiona formación: lo hace mientras es asesoría, y deja de hacerlo
al pasar a coordinación. En el listado salían las cuentas de coordinación sin
nada que las distinguiera, como si fueran una opción más.

**2. El histórico depende de que la cuenta exista.** El sistema anterior
guardaba la asesoría **como texto**, no como cuenta: en la exportación hay
**120 nombres distintos** repartidos en 3.764 de las 3.983 acciones, y de esos
nombres **la mitad no tiene cuenta localizable** —gente que pasó por un centro
del profesorado hace diez cursos—. La migración resolvió por nombre las que
coincidían y dejó el resto en una meta propia, `_fmc_legacy_adviser`, que no
mira nadie. Y mirando hacia delante: la pantalla de alta puede retirar el
perfil a quien ya no está (ADR-0023), y una cuenta que se borra de la red se
lleva por delante el único sitio donde constaba quién gestionó la acción.

El acotado por centro de referencia (ADR-0022) y la lista del curso (ADR-0023)
agravan lo segundo: cuanto mejor se lleva el reparto de ahora, más gente sale
del reparto de antes.

## Decisión

**Elegir, solo se elige una asesoría.** `Advisers::ROLES` se queda en
`fmc_adviser`. Quien coordina o lleva el servicio de perfeccionamiento no sale
en la lista, ni en la ficha ni en el filtro del listado.

**Quien ya no es asesoría se queda en lo que gestionó.** La acción que lleva a
alguien que dejó de serlo —cambió de destino, pasó a coordinación, salió del
reparto— lo sigue enseñando, en cursiva y con el aviso «— ya no es asesoría»,
y guardar la ficha no lo borra. Es la misma regla del programa que dejó de
estar vigente (ADR-0018) y del centro que se desactivó (ADR-0021).

**El nombre se guarda junto a la cuenta.** Al guardar una acción, su asesoría
deja también su nombre en `fmc_adviser_name`, como el centro deja el suyo al
lado del código (ADR-0021). Borrar la cuenta deja de borrar la historia.

**El nombre del sistema anterior se enseña.** Mientras una acción migrada no
tenga cuenta, la ficha dice debajo del campo «Asesoría en el sistema anterior:
…», con lo que guardó `_fmc_legacy_adviser`. Es lo mismo que ya se hace con el
centro escrito a mano.

**No hay un listado propio de asesorías.** Se consideró un tipo de contenido
para las personas asesoras, enlazado con su cuenta. Se descarta: sería un
tercer sitio donde mantener personas —ya están los ponentes y las cuentas—, con
sus duplicados y su pantalla, y la asesoría necesita entrar al aplicativo, o
sea que necesita cuenta igual. El nombre guardado da lo que hace falta del
histórico sin nada de eso.

## Consecuencias

**A favor**

- La lista de asesorías dice lo que dice: quién puede gestionar una acción hoy.
- El histórico deja de depender de una cuenta. Miles de acciones migradas pasan
  a decir quién las llevó, con un dato que ya estaba guardado y que no miraba
  nadie.
- Quitar el perfil a quien ya no está (ADR-0023) deja de tener consecuencias
  sobre lo que ya ocurrió.
- Se puede borrar de la red a quien se va sin perder el histórico, que es justo
  lo que hay que poder hacer con los datos de quien deja la organización.

**En contra**

- El nombre guardado es una copia: si alguien corrige su nombre en el perfil,
  lo guardado en acciones antiguas se queda como estaba. Es lo que se quiere
  —dice cómo se llamaba cuando aquello pasó—, pero conviene saberlo.
- Una acción gestionada de verdad por coordinación ya no se puede registrar
  como tal. No aparece ninguna en la exportación: las que llevan a alguien que
  hoy coordina son de cuando esa persona era asesoría.
- Hay dos sitios de donde sale el nombre, el del sistema anterior y el guardado
  al elegir la cuenta. Se leen en ese orden y ninguno se escribe sobre el otro.

## Adenda al reparto de perfiles

[ADR-0019](ADR-0019-la-asesoria-es-una-cuenta-del-sitio-con-su-tipo.md) decía
que la asesoría de una acción es una cuenta con perfil de asesoría **o de
coordinación**. Esta decisión estrecha eso a la asesoría, por lo dicho arriba.

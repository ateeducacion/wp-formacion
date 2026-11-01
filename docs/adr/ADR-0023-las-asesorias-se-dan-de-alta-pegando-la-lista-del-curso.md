---
id: ADR-0023
title: "Las asesorías se dan de alta pegando la lista del curso"
status: Propuesta
date: 2026-11-01
related:
  issues: []
  prs: []
  sdds: []
  adrs: [ADR-0019, ADR-0021, ADR-0022]
supersedes: []
superseded_by: []
ai_assistance:
  tool: "Claude Code"
  model: "claude-opus-5"
---

# ADR-0023: Las asesorías se dan de alta pegando la lista del curso

## Estado

Propuesta

## Contexto

Quién es asesoría, de qué tipo y de qué centro de referencia son datos del
perfil de cada cuenta ([ADR-0019](ADR-0019-la-asesoria-es-una-cuenta-del-sitio-con-su-tipo.md),
[ADR-0022](ADR-0022-la-asesoria-pertenece-a-un-centro-y-solo-ve-sus-incidencias.md)),
y de ellos dependen cosas que se notan a diario: qué incidencias ve cada cual,
qué centros ofrece la ficha de la acción y qué ámbito se propone al crearla.

Ponerlos a mano, cuenta por cuenta, son varias pantallas del escritorio por
persona —buscarla, añadirla al sitio, darle el perfil, el tipo y el centro—, y
el reparto **cambia entero a principio de curso**. Quien lo administra recibe la
lista en una hoja de cálculo. Sin una forma de volcarla, pasa dos cosas que ya
se han visto: cuentas que se quedan sin centro de referencia —y entonces el
acotado por ámbito no hace nada para esa persona— y gente que dejó de ser
asesoría y conserva el perfil.

El aplicativo hermano de registro de visitas resolvió lo mismo con una pantalla
que lee la lista pegada. Aquí hace falta además el tipo de asesoría, el centro
de referencia y una forma de **quitar** el perfil a quien ya no está.

## Decisión

Una pantalla de administración, «Usuarios → Alta de asesorías», que lee la
lista pegada y deja el reparto como dice la lista.

1. **Las columnas se reconocen por lo que son, no por el orden.** El correo es
   lo que parece un correo; el DNI, lo que parece un DNI; el centro, un código
   de ocho cifras; lo demás, el nombre. Valen el punto y coma, el tabulador y la
   coma. Así sirve igual la hoja entera que una columna de correos, y nadie
   tiene que normalizar lo que le mandan.
2. **El DNI identifica a la persona**, y es el usuario de la cuenta. El correo
   solo sirve para encontrar a quien ya la tiene: la lista puede traer otro
   correo de la misma persona, y buscar solo por correo acabaría creando una
   segunda cuenta con el DNI ocupado.
3. **A quien no tiene cuenta se le crea**, con el DNI de usuario, sus datos y su
   correo, y **sin enviarle ningún correo**: estas cuentas entran por el acceso
   de la organización y nunca con una contraseña de WordPress.
4. **El tipo es el de toda la lista.** Cada tipo manda la suya, y es lo que
   decide el acotado del listado por tipo de asesoría.
5. **Quitar el perfil es opcional y solo del tipo que se actualiza**, para que
   la lista de un tipo no desmonte la de otro. La cuenta **no se borra nunca**:
   pierde el perfil de asesoría y el tipo, y los recupera pegándola otra vez.
6. **De serie no escribe nada.** La casilla «Solo ver qué haría» viene marcada:
   una operación en bloque que toca decenas de cuentas se mira antes de hacerse.
7. **Quien ya tiene otro papel en el aplicativo no se toca** —coordinación,
   servicio de perfeccionamiento, administración—: añadirle el perfil de
   asesoría le cambiaría lo que ve sin que nadie lo haya pedido.

La regla vive en `src/Fmc/User/AdviserRoster.php` y la pantalla en
`src/Fmc/User/AdviserImport.php`: lo que hace cada línea se prueba sin pasar por
el formulario.

## Consecuencias

**A favor**

- El reparto de principio de curso es pegar una lista y mirar el informe.
- El centro de referencia, que es lo que estaba quedándose vacío, se rellena en
  el mismo gesto que da de alta a la persona.
- Es idempotente: volver a pegar la lista corregida completa lo que falte y no
  duplica a nadie, que es lo que de verdad ocurre —llega la lista entera otra
  vez, no lo que ha cambiado—.

**En contra**

- Es una operación en bloque, y una lista mal pegada toca muchas cuentas de una
  vez. Lo compensan el simulacro de serie y que nada de lo que hace borra datos:
  el peor caso es quitar un perfil que se devuelve pegando la lista buena.
- El DNI como identificador deja fuera a quien no lo traiga en la lista: esa
  línea se informa y no se crea la cuenta. Es preferible a crear una cuenta a la
  que luego no se puede entrar.
- Reconocer las columnas por su forma falla con un dato que parezca otro. El
  único caso real son las ocho cifras: un código de centro que el catálogo no
  conoce se dice en el informe en vez de guardarse.

## Alternativas consideradas

- **Seguir a mano.** Es lo que hay, y es lo que deja cuentas sin centro.
- **Importar un CSV.** Un fichero más que preparar, y lo que llega es una
  selección pegada de una hoja.
- **Sincronizar con el directorio de la organización.** Es la respuesta de
  verdad, pero depende de un servicio externo y de credenciales que este
  repositorio no tiene; esta pantalla no estorba a esa otra y se quita el día
  que exista.

#!/usr/bin/env node
/**
 * Capturas de la ayuda (assets/help/img/), sacadas del sitio de demostración.
 *
 * Se rehacen todas con `make capturas`, que arranca Playground con los datos
 * de demostración —inventados— y corre este guion. Así una pantalla que cambia
 * se vuelve a fotografiar en el mismo PR, y la ayuda no envejece (issue #75).
 *
 * Nunca contra un sitio con datos reales: lo que sale aquí se versiona en un
 * repositorio público (ADR-0002).
 *
 * Uso: node scripts/help-screenshots.mjs [URL]   (por defecto, http://127.0.0.1:9410)
 */

import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const base = ( process.argv[ 2 ] || process.env.FMC_CAPTURAS_URL || 'http://127.0.0.1:9410' ).replace( /\/$/, '' );
const out = join( dirname( fileURLToPath( import.meta.url ) ), '..', 'assets', 'help', 'img' );
const viewport = { width: 1280, height: 800 };

// Cada captura: con qué cuenta de demostración, qué pantalla, qué se abre y qué se recorta.
const shots = [
	{ name: 'cabecera', user: 'coordinacion', path: '/', clip: { x: 0, y: 0, width: 1280, height: 330 } },
	{ name: 'calendario', user: 'coordinacion', path: '/' },
	{ name: 'disenos', user: 'coordinacion', path: '/?fmc_tab=designs', clip: { x: 0, y: 0, width: 1280, height: 580 } },
	{ name: 'diseno-nuevo', user: 'coordinacion', path: '/?fmc_tab=designs', open: 'a.fmc-abre-panel' },
	{ name: 'disenos-asesoria', user: 'asesoria', path: '/?fmc_tab=designs', clip: { x: 0, y: 0, width: 1280, height: 520 } },
	{ name: 'diseno-ficha', user: 'asesoria', path: '/?fmc_tab=designs', open: 'tbody a.fmc-abre-panel' },
	{ name: 'accion-nueva', user: 'asesoria', path: '/', open: 'a.fmc-abre-panel' },
	{ name: 'acciones-lista', user: 'asesoria', path: '/?fmc_view=list', clip: { x: 0, y: 0, width: 1280, height: 700 } },
	{ name: 'incidencia-nueva', user: 'asesoria', path: '/?fmc_view=list', open: 'a[href*="fmc_new=fmc_incident"]' },
	{ name: 'ponentes', user: 'asesoria', path: '/?fmc_tab=speakers', clip: { x: 0, y: 0, width: 1280, height: 560 } },
	{ name: 'incidencias-servicio', user: 'servicio', path: '/?fmc_tab=incidents', clip: { x: 0, y: 0, width: 1280, height: 560 } },
	{ name: 'incidencia-resolver', user: 'servicio', path: '/?fmc_tab=incidents', open: 'tbody a.fmc-abre-panel' },
	{ name: 'catalogo', user: '', path: '/?fmc_catalogo=1', clip: { x: 0, y: 0, width: 1280, height: 640 } },
];

async function login( browser, user ) {
	const context = await browser.newContext( { viewport, locale: 'es-ES', reducedMotion: 'reduce' } );
	const page = await context.newPage();
	// Playground entra solo como administración la primera vez; después de eso
	// se puede salir y entrar con la cuenta de demostración que toque.
	await page.goto( base + '/wp-login.php?loggedout=true' );
	await context.clearCookies( { name: /^wordpress_(logged_in_)?[0-9a-f]+$/ } );
	if ( user ) {
		await page.goto( base + '/wp-login.php?loggedout=true' );
		await page.fill( '#user_login', user );
		await page.fill( '#user_pass', 'password' );
		await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );
	}
	await page.close();
	return context;
}

const browser = await chromium.launch();
mkdirSync( out, { recursive: true } );
const contexts = {};
try {
	for ( const shot of shots ) {
		contexts[ shot.user ] ??= await login( browser, shot.user );
		const page = await contexts[ shot.user ].newPage();
		await page.goto( base + shot.path );
		await page.waitForLoadState( 'networkidle' );
		if ( shot.open ) {
			// El clic por guion, como el de una persona: el panel lo abre fmc-app.js.
			await page.evaluate( ( selector ) => document.querySelector( selector ).click(), shot.open );
			await page.waitForSelector( '.fmc-panel .fmc-fragment', { timeout: 30000 } );
			await page.waitForTimeout( 600 );
			// El foco del primer campo no pinta nada que explicar.
			await page.evaluate( () => document.activeElement && document.activeElement.blur() );
		}
		const file = join( out, shot.name + '.webp' );
		await page.screenshot( { path: file, type: 'webp', quality: 80, clip: shot.clip } );
		console.log( 'Captura ' + shot.name );
		await page.close();
	}
} finally {
	await browser.close();
}

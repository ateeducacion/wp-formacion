import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

async function load() {
	vi.resetModules();
	await import( '../../assets/js/fmc-app.js' );
}

// Cada carga del guion engancha sus oyentes al documento: se quitan al acabar.
function detach() {
	if ( window.fmcApp ) {
		document.removeEventListener( 'submit', window.fmcApp.onSubmit );
		document.removeEventListener( 'click', window.fmcApp.onClick );
		document.removeEventListener( 'keydown', window.fmcApp.onKey );
		document.removeEventListener( 'change', window.fmcApp.onChange );
	}
}

function form() {
	document.body.innerHTML = '<form data-fmc-confirm="¿Enviar a la papelera?"><button>Borrar</button></form>';
	const f = document.querySelector( 'form' );
	f.submit = vi.fn();
	return f;
}

function submit( f ) {
	const event = new Event( 'submit', { bubbles: true, cancelable: true } );
	f.dispatchEvent( event );
	return event;
}

describe( 'confirmación antes de enviar', () => {
	beforeEach( async () => {
		delete window.Swal;
		await load();
	} );
	afterEach( () => {
		detach();
		vi.restoreAllMocks();
	} );

	it( 'sin SweetAlert, un «no» del confirm() no envía', () => {
		const f = form();
		vi.spyOn( window, 'confirm' ).mockReturnValue( false );
		expect( submit( f ).defaultPrevented ).toBe( true );
		expect( f.submit ).not.toHaveBeenCalled();
	} );

	it( 'sin SweetAlert, un «sí» envía', () => {
		const f = form();
		vi.spyOn( window, 'confirm' ).mockReturnValue( true );
		submit( f );
		expect( f.submit ).toHaveBeenCalledOnce();
	} );

	it( 'con SweetAlert, solo envía si se confirma', async () => {
		const f = form();
		window.Swal = { fire: vi.fn().mockResolvedValue( { isConfirmed: false } ) };
		submit( f );
		await Promise.resolve();
		expect( f.submit ).not.toHaveBeenCalled();

		window.Swal.fire.mockResolvedValue( { isConfirmed: true } );
		submit( f );
		await new Promise( ( r ) => setTimeout( r ) );
		expect( f.submit ).toHaveBeenCalledOnce();
	} );

	it( 'un formulario sin data-fmc-confirm no se toca', () => {
		document.body.innerHTML = '<form><button>Guardar</button></form>';
		expect( submit( document.querySelector( 'form' ) ).defaultPrevented ).toBe( false );
	} );
} );

describe( 'Tom Select', () => {
	it( 'convierte los select marcados, una sola vez', async () => {
		const TomSelect = vi.fn( function ( s ) {
			s.tomselect = this;
		} );
		window.TomSelect = TomSelect;
		document.body.innerHTML = '<select data-fmc-ts multiple></select><select data-fmc-ts></select><select></select>';
		await load();
		expect( TomSelect ).toHaveBeenCalledTimes( 2 );
		expect( TomSelect.mock.calls[ 0 ][ 1 ].plugins ).toEqual( [ 'remove_button' ] );
		expect( window.fmcApp.initSelects() ).toBe( 0 );
		delete window.TomSelect;
		detach();
	} );

	it( 'sin Tom Select, los select se quedan como están', async () => {
		delete window.TomSelect;
		await load();
		expect( window.fmcApp.initSelects() ).toBe( 0 );
		detach();
	} );
} );

describe( 'el panel lateral', () => {
	beforeEach( async () => {
		vi.useFakeTimers();
		document.body.className = '';
		document.body.innerHTML = '<a class="fmc-abre-panel" href="/?fmc_edit=7">Editar</a><a href="/?fmc_tab=designs">Otra</a>';
		window.requestAnimationFrame = ( fn ) => fn();
		window.fetch = vi.fn().mockResolvedValue( {
			text: () => Promise.resolve( '<div class="fmc-fragment" data-fmc-title="Curso A"><form class="fmc-form" method="post" action="/"><input name="f[x]" value="1"><button name="fmc_status" value="publish">Guardar</button></form></div>' ),
		} );
		await load();
		window.fmcApp.reload = vi.fn();
	} );
	afterEach( () => {
		detach();
		document.removeEventListener( 'submit', window.fmcApp.onPanelSubmit );
		vi.useRealTimers();
	} );

	async function settle() {
		await vi.runAllTimersAsync();
	}

	it( 'pide la ficha sola y la pinta dentro del panel', async () => {
		const event = new MouseEvent( 'click', { bubbles: true, cancelable: true } );
		document.querySelector( '.fmc-abre-panel' ).dispatchEvent( event );
		expect( event.defaultPrevented ).toBe( true );
		const url = new URL( window.fetch.mock.calls[ 0 ][ 0 ] );
		expect( url.searchParams.get( 'fmc_marco' ) ).toBe( '1' );
		expect( url.searchParams.get( 'fmc_edit' ) ).toBe( '7' );
		await settle();
		const panel = document.querySelector( '.fmc-panel' );
		expect( panel.classList.contains( 'offcanvas' ) ).toBe( true );
		expect( panel.querySelector( 'form.fmc-form' ) ).not.toBeNull();
		expect( panel.querySelector( '.offcanvas-title' ).textContent ).toBe( 'Curso A' );
		expect( document.querySelector( 'iframe' ) ).toBeNull();
	} );

	it( 'guardar se envía desde el panel, con el botón pulsado', async () => {
		window.fmcApp.openPanel( '/?fmc_edit=7', 'Editar' );
		await settle();
		window.fetch.mockResolvedValue( { text: () => Promise.resolve( '<div class="fmc-fragment" data-fmc-title="Curso A"><div class="alert alert-success">Guardado.</div></div>' ) } );
		const form = document.querySelector( '.fmc-panel form' );
		const event = new Event( 'submit', { bubbles: true, cancelable: true } );
		event.submitter = form.querySelector( 'button' );
		form.dispatchEvent( event );
		expect( event.defaultPrevented ).toBe( true );
		const [ , options ] = window.fetch.mock.calls[ 1 ];
		expect( options.method ).toBe( 'POST' );
		expect( options.body.get( 'fmc_status' ) ).toBe( 'publish' );
		await settle();
		expect( document.querySelector( '.fmc-panel .alert-success' ) ).not.toBeNull();
	} );

	it( 'cerrar solo recarga si se guardó algo', async () => {
		window.fmcApp.openPanel( '/?fmc_edit=7', 'Editar' );
		await settle();
		document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Escape' } ) );
		await settle();
		expect( document.querySelector( '.fmc-panel' ) ).toBeNull();
		expect( window.fmcApp.reload ).not.toHaveBeenCalled();

		window.fetch.mockResolvedValue( { text: () => Promise.resolve( '<div class="alert alert-success">Guardado.</div>' ) } );
		window.fmcApp.openPanel( '/?fmc_edit=7', 'Editar' );
		await settle();
		window.fmcApp.closePanel();
		await settle();
		expect( window.fmcApp.reload ).toHaveBeenCalledOnce();
	} );

	it( 'un enlace normal, o con Ctrl, no abre panel', () => {
		document.querySelectorAll( 'a' )[ 1 ].dispatchEvent( new MouseEvent( 'click', { bubbles: true, cancelable: true } ) );
		document.querySelector( '.fmc-abre-panel' ).dispatchEvent( new MouseEvent( 'click', { bubbles: true, cancelable: true, ctrlKey: true } ) );
		expect( document.querySelector( '.fmc-panel' ) ).toBeNull();
	} );

	it( 'no abre fuera del propio sitio', () => {
		expect( window.fmcApp.openPanel( 'https://example.org/', 'Fuera' ) ).toBe( false );
	} );
} );

describe( 'campos que dependen de otro', () => {
	beforeEach( async () => {
		document.body.innerHTML = '<form><select name="f[fmc_modality]"><option value="">—</option><option value="onsite">Presencial</option><option value="online">En línea</option><option value="blended">Mixta</option></select>'
			+ '<div id="on" data-fmc-show="f[fmc_modality]" data-fmc-show-values="onsite,blended"></div>'
			+ '<div id="off" data-fmc-show="f[fmc_modality]" data-fmc-show-values="online,blended"></div>'
			+ '<input type="checkbox" name="f[fmc_training_plans][]" value="plan"><div id="plan" data-fmc-show="f[fmc_training_plans]" data-fmc-show-values="plan,seminar"></div>'
			+ '<input class="fmc-file" type="file" id="up"><span class="fmc-file-name" data-for="up"></span></form>';
		await load();
	} );
	afterEach( () => detach() );

	function change( el ) {
		el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	it( 'las horas siguen a la modalidad', () => {
		const select = document.querySelector( 'select' );
		expect( document.getElementById( 'on' ).classList.contains( 'd-none' ) ).toBe( true );
		select.value = 'onsite';
		change( select );
		expect( document.getElementById( 'on' ).classList.contains( 'd-none' ) ).toBe( false );
		expect( document.getElementById( 'off' ).classList.contains( 'd-none' ) ).toBe( true );
		select.value = 'blended';
		change( select );
		expect( document.getElementById( 'off' ).classList.contains( 'd-none' ) ).toBe( false );
	} );

	it( 'una casilla de una lista también cuenta', () => {
		const box = document.querySelector( 'input[type=checkbox]' );
		expect( document.getElementById( 'plan' ).classList.contains( 'd-none' ) ).toBe( true );
		box.checked = true;
		change( box );
		expect( document.getElementById( 'plan' ).classList.contains( 'd-none' ) ).toBe( false );
	} );

	it( 'el fichero elegido se nombra', () => {
		const input = document.getElementById( 'up' );
		Object.defineProperty( input, 'files', { value: [ { name: 'diseño.pdf' } ] } );
		change( input );
		expect( document.querySelector( '.fmc-file-name' ).textContent ).toBe( 'Se subirá al guardar: diseño.pdf' );
	} );
} );

describe( 'el editor enriquecido', () => {
	afterEach( () => {
		delete window.tinymce;
		detach();
	} );

	it( 'se pone en los textos con formato, una vez, y de solo lectura si el campo lo es', async () => {
		const editors = [];
		window.tinymce = {
			editors,
			init: vi.fn( ( o ) => editors.push( { targetElm: o.target, remove: vi.fn() } ) ),
			triggerSave: vi.fn(),
		};
		document.body.innerHTML = '<textarea data-fmc-rich></textarea><textarea data-fmc-rich disabled></textarea><textarea></textarea>';
		await load();
		expect( window.tinymce.init ).toHaveBeenCalledTimes( 2 );
		expect( window.tinymce.init.mock.calls[ 1 ][ 0 ].readonly ).toBe( 1 );
		expect( window.fmcApp.initRich() ).toBe( 0 );

		window.fmcApp.removeRich( document.body );
		editors.forEach( ( ed ) => expect( ed.remove ).toHaveBeenCalledOnce() );
	} );

	it( 'sin TinyMCE, el cuadro de texto se queda como está', async () => {
		document.body.innerHTML = '<textarea data-fmc-rich></textarea>';
		await load();
		expect( window.fmcApp.initRich() ).toBe( 0 );
	} );
} );

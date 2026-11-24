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
		document.removeEventListener( 'click', window.fmcApp.onToolClick );
		document.removeEventListener( 'click', window.fmcApp.onNoteClick );
		document.removeEventListener( 'keydown', window.fmcApp.onKey );
		document.removeEventListener( 'click', window.fmcApp.onSessionClick );
		document.removeEventListener( 'input', window.fmcApp.onSessionInput );
		document.removeEventListener( 'change', window.fmcApp.onChange );
		document.removeEventListener( 'click', window.fmcApp.onDocClick );
		[ 'dragenter', 'dragover', 'dragleave', 'drop' ].forEach( ( type ) => document.removeEventListener( type, window.fmcApp.onDrag ) );
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

describe( 'dar de baja a un ponente', () => {
	beforeEach( async () => {
		delete window.Swal;
		await load();
		document.body.innerHTML = '<form id="fmc-baja-9" data-fmc-reason data-fmc-confirm="¿Dar de baja a «Ana»?"><input type="hidden" name="fmc_reason" value=""></form>';
	} );
	afterEach( () => {
		detach();
		vi.restoreAllMocks();
		delete window.Swal;
	} );

	function leave() {
		const f = document.querySelector( 'form' );
		f.submit = vi.fn();
		return f;
	}

	it( 'pide el motivo en el SweetAlert y lo envía con el formulario', async () => {
		const f = leave();
		window.Swal = { fire: vi.fn().mockResolvedValue( { isConfirmed: true, value: '  Pidió que se borrasen sus datos ' } ) };
		submit( f );
		await new Promise( ( r ) => setTimeout( r ) );
		const options = window.Swal.fire.mock.calls[ 0 ][ 0 ];
		expect( options.input ).toBe( 'textarea' );
		expect( options.inputValidator( '   ' ) ).toBeTruthy();
		expect( options.inputValidator( 'Motivo' ) ).toBeNull();
		expect( f.elements.fmc_reason.value ).toBe( 'Pidió que se borrasen sus datos' );
		expect( f.submit ).toHaveBeenCalledOnce();
	} );

	it( 'si se cancela, no se envía', async () => {
		const f = leave();
		window.Swal = { fire: vi.fn().mockResolvedValue( { isConfirmed: false } ) };
		submit( f );
		await new Promise( ( r ) => setTimeout( r ) );
		expect( f.submit ).not.toHaveBeenCalled();
	} );

	it( 'sin SweetAlert, lo pide con prompt(), y sin motivo no envía', () => {
		const f = leave();
		vi.spyOn( window, 'prompt' ).mockReturnValue( '   ' );
		submit( f );
		expect( f.submit ).not.toHaveBeenCalled();
		window.prompt.mockReturnValue( 'Ya no colabora' );
		submit( f );
		expect( f.elements.fmc_reason.value ).toBe( 'Ya no colabora' );
		expect( f.submit ).toHaveBeenCalledOnce();
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

	it( 'las opciones con significado lo enseñan al pasar, y debajo el del elegido', async () => {
		const TomSelect = vi.fn( function ( s ) {
			s.tomselect = this;
		} );
		window.TomSelect = TomSelect;
		document.body.innerHTML = '<form><select id="f-t" data-fmc-ts data-fmc-hints><option value="">—</option><option value="1" data-fmc-hint="Robots y <3D>">Robótica</option><option value="2">Sin descripción</option></select><div class="fmc-hint" data-fmc-hint-for="f-t"></div></form>';
		await load();
		const render = TomSelect.mock.calls[ 0 ][ 1 ].render.option;
		const escape = ( s ) => s.replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
		const options = document.querySelectorAll( 'option' );
		expect( render( { text: 'Robótica', $option: options[ 1 ] }, escape ) ).toBe( '<div title="Robots y &lt;3D&gt;">Robótica</div>' );
		expect( render( { text: 'Sin descripción', $option: options[ 2 ] }, escape ) ).toBe( '<div>Sin descripción</div>' );

		const s = document.querySelector( 'select' );
		s.value = '1';
		s.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		expect( document.querySelector( '.fmc-hint' ).textContent ).toBe( 'Robots y <3D>' );
		s.value = '2';
		s.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		expect( document.querySelector( '.fmc-hint' ).textContent ).toBe( '' );
		delete window.TomSelect;
		detach();
	} );

	it( 'el diseño elegido dice su tipo, modalidad y horas y enlaza a su ficha en otra pestaña', async () => {
		document.body.innerHTML = '<form><select id="f-d" data-fmc-hints><option value="">—</option><option value="7" data-fmc-hint="Curso · Mixta · 20 h (8 presenciales + 12 en línea)" data-fmc-hint-url="https://ejemplo.test/?fmc_edit=7">A001 · IA</option><option value="8" data-fmc-hint="Acción puntual">A002 · Robótica</option></select><div class="fmc-hint" data-fmc-hint-for="f-d"></div></form>';
		await load();
		const s = document.querySelector( 'select' );
		const box = document.querySelector( '.fmc-hint' );

		s.value = '7';
		s.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		expect( box.textContent ).toBe( 'Curso · Mixta · 20 h (8 presenciales + 12 en línea) Ver la ficha del diseño' );
		const link = box.querySelector( 'a' );
		expect( link.getAttribute( 'href' ) ).toBe( 'https://ejemplo.test/?fmc_edit=7' );
		expect( link.target ).toBe( '_blank' );
		expect( link.rel ).toBe( 'noopener' );

		s.value = '8';
		s.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		expect( box.textContent ).toBe( 'Acción puntual' );
		expect( box.querySelector( 'a' ) ).toBeNull();

		s.value = '';
		s.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		expect( box.textContent ).toBe( '' );
		detach();
	} );

	it( 'sin Tom Select, los select se quedan como están', async () => {
		delete window.TomSelect;
		await load();
		expect( window.fmcApp.initSelects() ).toBe( 0 );
		detach();
	} );
} );

describe( 'las sesiones de una acción', () => {
	const row = ( i, day, from, to ) => '<tr data-fmc-session><td><input type="date" name="f[s][' + i + '][day]" value="' + day + '"></td><td><input type="time" name="f[s][' + i + '][from]" value="' + from + '"></td><td><input type="time" name="f[s][' + i + '][to]" value="' + to + '"></td><td><button type="button" data-fmc-session-remove>×</button></td></tr>';

	it( 'añade filas con un índice nuevo, las quita y suma las horas', async () => {
		document.body.innerHTML = '<form><div data-fmc-sessions data-fmc-next="2"><table><tbody>' + row( 0, '2027-03-20', '16:00', '20:00' ) + row( 1, '', '', '' ) + '</tbody></table><template data-fmc-session-template>' + row( '__i__', '', '', '' ) + '</template><button type="button" data-fmc-session-add>+</button> <strong data-fmc-sessions-total>4 h</strong></div></form>';
		await load();
		const box = document.querySelector( '[data-fmc-sessions]' );
		const total = () => box.querySelector( '[data-fmc-sessions-total]' ).textContent;

		box.querySelector( '[data-fmc-session-add]' ).click();
		const rows = box.querySelectorAll( 'tbody tr' );
		expect( rows ).toHaveLength( 3 );
		expect( rows[ 2 ].querySelector( 'input' ).name ).toBe( 'f[s][2][day]' );
		expect( box.dataset.fmcNext ).toBe( '3' );

		rows[ 2 ].querySelector( 'input[name$="[from]"]' ).value = '09:30';
		rows[ 2 ].querySelector( 'input[name$="[to]"]' ).value = '13:00';
		rows[ 2 ].querySelector( 'input[name$="[to]"]' ).dispatchEvent( new Event( 'input', { bubbles: true } ) );
		expect( total() ).toBe( '7.5 h' );

		rows[ 0 ].querySelector( '[data-fmc-session-remove]' ).click();
		expect( box.querySelectorAll( 'tbody tr' ) ).toHaveLength( 2 );
		expect( total() ).toBe( '3.5 h' );

		// Una que acaba antes de empezar no resta.
		rows[ 2 ].querySelector( 'input[name$="[to]"]' ).value = '08:00';
		rows[ 2 ].querySelector( 'input[name$="[to]"]' ).dispatchEvent( new Event( 'input', { bubbles: true } ) );
		expect( total() ).toBe( '0 h' );
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

	it( 'los enlaces del aplicativo dentro del panel siguen en él; los de fuera, no', async () => {
		window.fetch.mockResolvedValue( {
			text: () => Promise.resolve( '<div class="fmc-fragment" data-fmc-title="Acción A"><a id="dentro" href="/?fmc_new=fmc_incident&amp;fmc_parent=7&amp;fmc_marco=1">Abrir una incidencia</a><a id="fuera" href="/wp-content/uploads/a.pdf">Documento</a></div>' ),
		} );
		window.fmcApp.openPanel( '/?fmc_edit=7', 'Acción A' );
		await settle();
		const inside = new MouseEvent( 'click', { bubbles: true, cancelable: true } );
		document.getElementById( 'dentro' ).dispatchEvent( inside );
		expect( inside.defaultPrevented ).toBe( true );
		const url = new URL( window.fetch.mock.calls[ 1 ][ 0 ] );
		expect( url.searchParams.get( 'fmc_new' ) ).toBe( 'fmc_incident' );
		expect( url.searchParams.get( 'fmc_marco' ) ).toBe( '1' );
		await settle();
		const outside = new MouseEvent( 'click', { bubbles: true, cancelable: true } );
		document.querySelector( '.fmc-panel' ).append( Object.assign( document.createElement( 'a' ), { id: 'pdf', href: '/wp-content/uploads/a.pdf' } ) );
		document.getElementById( 'pdf' ).dispatchEvent( outside );
		expect( outside.defaultPrevented ).toBe( false );
	} );

	it( 'no abre fuera del propio sitio', () => {
		expect( window.fmcApp.openPanel( 'https://example.org/', 'Fuera' ) ).toBe( false );
	} );
} );

describe( 'el perfil en elidd', () => {
	beforeEach( async () => {
		document.body.innerHTML = '<input id="perfil" value="https://otra.example/elidd/?c=1.1.A1.1"><button type="button" data-fmc-tool="perfil" data-fmc-tool-url="https://elidd.example/">Elegir</button>';
		await load();
	} );
	afterEach( detach );

	function message( dialog, origin, data ) {
		window.dispatchEvent( new MessageEvent( 'message', { origin, data, source: dialog.querySelector( 'iframe' ).contentWindow } ) );
	}

	it( 'abre elidd incrustada, con lo ya elegido, y escribe lo que devuelve su «Guardar»', () => {
		const dialog = window.fmcApp.openTool( document.querySelector( 'button' ) );
		const src = new URL( dialog.querySelector( 'iframe' ).src );
		expect( src.origin + src.pathname ).toBe( 'https://elidd.example/' );
		expect( src.searchParams.get( 'embed' ) ).toBe( '1' );
		expect( src.searchParams.get( 'origin' ) ).toBe( window.location.origin );
		expect( src.searchParams.get( 'c' ) ).toBe( '1.1.A1.1' );

		message( dialog, 'https://elidd.example', { type: 'elidd:seleccion', c: '1.1.A1.1|3.1.B1.2' } );
		expect( document.getElementById( 'perfil' ).value ).toBe( 'https://elidd.example/?c=1.1.A1.1|3.1.B1.2' );
		expect( document.querySelector( '.fmc-tool-note' ).textContent ).toContain( '2 indicadores' );
		expect( document.querySelector( 'dialog' ) ).toBeNull();
	} );

	it( 'no hace caso a otro origen ni a algo que no es una selección', () => {
		const dialog = window.fmcApp.openTool( document.querySelector( 'button' ) );
		message( dialog, 'https://intruso.example', { type: 'elidd:seleccion', c: '6.5.A1.1' } );
		message( dialog, 'https://elidd.example', { type: 'elidd:seleccion', c: 'javascript:alert(1)' } );
		message( dialog, 'https://elidd.example', { type: 'otra', c: '6.5.A1.1' } );
		expect( document.getElementById( 'perfil' ).value ).toBe( 'https://otra.example/elidd/?c=1.1.A1.1' );
		expect( document.querySelector( 'dialog' ) ).not.toBeNull();
		dialog.querySelector( '.btn-close' ).click();
		expect( document.querySelector( 'dialog' ) ).toBeNull();
	} );
} );

describe( 'campos que dependen de otro', () => {
	beforeEach( async () => {
		document.body.innerHTML = '<form><select name="f[fmc_modality]"><option value="">—</option><option value="onsite">Presencial</option><option value="online">En línea</option><option value="blended">Mixta</option></select>'
			+ '<div id="on" data-fmc-show="f[fmc_modality]" data-fmc-show-values="onsite,blended"></div>'
			+ '<div id="off" data-fmc-show="f[fmc_modality]" data-fmc-show-values="online,blended"></div>'
			+ '<input type="radio" name="f[fmc_training_plans]" value="" checked><input type="radio" name="f[fmc_training_plans]" value="plan"><div id="plan" data-fmc-show="f[fmc_training_plans]" data-fmc-show-values="plan,seminar"></div></form>';
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

	it( 'una opción de una lista también cuenta, y «ninguno» no', () => {
		const [ none, plan ] = document.querySelectorAll( 'input[type=radio]' );
		expect( document.getElementById( 'plan' ).classList.contains( 'd-none' ) ).toBe( true );
		plan.checked = true;
		change( plan );
		expect( document.getElementById( 'plan' ).classList.contains( 'd-none' ) ).toBe( false );
		none.checked = true;
		plan.checked = false;
		change( none );
		expect( document.getElementById( 'plan' ).classList.contains( 'd-none' ) ).toBe( true );
	} );
} );

describe( 'los documentos del diseño', () => {
	// Como los pinta Editor::docs(): un documento guardado y la zona para añadir.
	beforeEach( async () => {
		document.body.innerHTML = '<form><div class="fmc-docs"><p data-fmc-doc-status aria-live="polite"></p>'
			+ '<ul class="fmc-doc-list"><li class="fmc-doc">'
			+ '<input class="fmc-doc-rm" type="checkbox" id="fmc-remove-7" name="fmc_remove[]" value="7" aria-label="Quitar: escaleta.docx" data-fmc-doc-name="escaleta.docx">'
			+ '<div class="fmc-doc-card"><div class="fmc-doc-main"><input class="fmc-file" type="file" id="fmc-replace-7" name="fmc_replace[7]"><label for="fmc-replace-7">Reemplazar</label>'
			+ '<p class="fmc-doc-pending" data-fmc-picked="fmc-replace-7" hidden><span>Se reemplazará al guardar por: <strong data-fmc-picked-name></strong></span> <button type="button" data-fmc-file-clear="fmc-replace-7">Cancelar</button></p></div>'
			+ '<label class="fmc-doc-x" for="fmc-remove-7">Quitar</label></div>'
			+ '<div class="fmc-doc-gone">Se quitará al guardar <label class="fmc-doc-undo" for="fmc-remove-7">Deshacer</label></div></li></ul>'
			+ '<label class="fmc-drop" for="fmc-add-slides"><input class="fmc-file fmc-drop-input" type="file" id="fmc-add-slides" name="fmc_doc[slides][]" multiple></label>'
			+ '<ul class="fmc-drop-list" data-fmc-picked="fmc-add-slides"></ul></div></form>';
		await load();
	} );
	afterEach( () => {
		delete window.DataTransfer;
		detach();
	} );

	function change( el ) {
		el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	// jsdom no deja escribir `files`: se hace a mano, y vaciar el valor lo vacía.
	function pick( input, files ) {
		let list = files;
		Object.defineProperty( input, 'files', { configurable: true, get: () => list, set: ( v ) => {
			list = v;
		} } );
		Object.defineProperty( input, 'value', { configurable: true, get: () => ( list.length ? list[ 0 ].name : '' ), set: ( v ) => {
			if ( '' === v ) {
				list = [];
			}
		} } );
		change( input );
	}

	const status = () => document.querySelector( '[data-fmc-doc-status]' ).textContent;

	it( 'el aspa marca la casilla de quitar, sin borrar nada, y «Deshacer» la desmarca', () => {
		const box = document.getElementById( 'fmc-remove-7' );
		document.querySelector( '.fmc-doc-x' ).click();
		expect( box.checked ).toBe( true );
		expect( new FormData( document.querySelector( 'form' ) ).getAll( 'fmc_remove[]' ) ).toEqual( [ '7' ] );
		expect( document.activeElement ).toBe( box );
		expect( status() ).toBe( 'Se quitará al guardar: escaleta.docx' );

		document.querySelector( '.fmc-doc-undo' ).click();
		expect( box.checked ).toBe( false );
		expect( new FormData( document.querySelector( 'form' ) ).getAll( 'fmc_remove[]' ) ).toEqual( [] );
		expect( document.activeElement ).toBe( box );
		expect( status() ).toBe( 'Se mantiene: escaleta.docx' );
	} );

	it( 'el reemplazo elegido se anuncia y se puede cancelar', () => {
		const input = document.getElementById( 'fmc-replace-7' );
		const note = document.querySelector( '[data-fmc-picked="fmc-replace-7"]' );
		pick( input, [ { name: 'escaleta-v2.docx', size: 2048 } ] );
		expect( note.hidden ).toBe( false );
		expect( note.textContent ).toContain( 'Se reemplazará al guardar por: escaleta-v2.docx' );

		note.querySelector( 'button' ).click();
		expect( input.value ).toBe( '' );
		expect( note.hidden ).toBe( true );
		expect( document.activeElement ).toBe( input );
	} );

	it( 'los ficheros que se añaden salen como fichas pendientes', () => {
		const input = document.getElementById( 'fmc-add-slides' );
		pick( input, [ { name: 'sesion1.pptx', size: 3 * 1048576 }, { name: 'guion.odp', size: 500 } ] );
		const cards = document.querySelectorAll( '.fmc-drop-list .fmc-doc' );
		expect( cards ).toHaveLength( 2 );
		expect( cards[ 0 ].textContent ).toContain( 'PPTX' );
		expect( cards[ 0 ].textContent ).toContain( 'sesion1.pptx' );
		expect( cards[ 0 ].textContent ).toContain( '3,0 MB' );
		expect( cards[ 0 ].textContent ).toContain( 'Se subirá al guardar' );
		expect( cards[ 1 ].querySelector( '.fmc-doc-type' ).dataset.family ).toBe( 'slides' );
		expect( cards[ 1 ].querySelector( 'button' ).getAttribute( 'aria-label' ) ).toBe( 'No subir: guion.odp' );
		expect( status() ).toBe( 'Se subirán al guardar 2 ficheros' );
	} );

	it( 'un fichero pendiente se quita de la subida y el foco va al que queda', () => {
		window.DataTransfer = class {
			constructor() {
				this.files = [];
				this.items = { add: ( f ) => this.files.push( f ) };
			}
		};
		const input = document.getElementById( 'fmc-add-slides' );
		pick( input, [ { name: 'a.pptx', size: 1 }, { name: 'b.pptx', size: 1 } ] );
		document.querySelector( '.fmc-drop-list button' ).click();
		expect( Array.from( input.files, ( f ) => f.name ) ).toEqual( [ 'b.pptx' ] );
		const left = document.querySelectorAll( '.fmc-drop-list button' );
		expect( left ).toHaveLength( 1 );
		expect( document.activeElement ).toBe( left[ 0 ] );

		left[ 0 ].click();
		expect( input.files ).toHaveLength( 0 );
		expect( document.querySelectorAll( '.fmc-drop-list li' ) ).toHaveLength( 0 );
		expect( document.activeElement ).toBe( input );
	} );

	it( 'sin DataTransfer, quitar uno vacía la elección', () => {
		const input = document.getElementById( 'fmc-add-slides' );
		pick( input, [ { name: 'a.pptx', size: 1 }, { name: 'b.pptx', size: 1 } ] );
		document.querySelector( '.fmc-drop-list button' ).click();
		expect( input.files ).toHaveLength( 0 );
		expect( document.querySelectorAll( '.fmc-drop-list li' ) ).toHaveLength( 0 );
	} );

	it( 'arrastrar encima resalta la zona, y soltar o salir la apaga', () => {
		const input = document.getElementById( 'fmc-add-slides' );
		const zone = document.querySelector( '.fmc-drop' );
		input.dispatchEvent( new Event( 'dragenter', { bubbles: true } ) );
		expect( zone.classList.contains( 'is-over' ) ).toBe( true );
		input.dispatchEvent( new Event( 'dragleave', { bubbles: true } ) );
		expect( zone.classList.contains( 'is-over' ) ).toBe( false );
		input.dispatchEvent( new Event( 'dragover', { bubbles: true } ) );
		input.dispatchEvent( new Event( 'drop', { bubbles: true } ) );
		expect( zone.classList.contains( 'is-over' ) ).toBe( false );
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

describe( 'los ponentes de la acción', () => {
	beforeEach( async () => {
		delete window.Swal;
		delete window.TomSelect;
		document.body.innerHTML = '<form><select name="f[fmc_design_id]"><option value="">—</option><option value="7">Uno</option><option value="9">Otro</option></select>'
			+ '<select multiple name="f[fmc_speakers][]" data-fmc-speakers="f[fmc_design_id]">'
			+ '<option value="1" data-fmc-for="7,8">★ Zoe</option><option value="2">Ana</option>'
			+ '<option value="3" data-fmc-off="1" data-fmc-note="De baja &lt;hasta&gt; junio">Bea · No disponible</option></select></form>'
			+ '<button type="button" data-fmc-note="Solo por la tarde">nota</button>';
		await load();
	} );
	afterEach( () => {
		detach();
		delete window.Swal;
	} );

	function texts() {
		return Array.prototype.map.call( document.querySelectorAll( '[data-fmc-speakers] option' ), ( o ) => o.textContent );
	}

	it( 'la estrella sigue al diseño elegido', () => {
		const design = document.querySelector( 'select[name="f[fmc_design_id]"]' );
		design.value = '9';
		design.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		expect( texts() ).toEqual( [ 'Ana', 'Zoe', 'Bea · No disponible' ] );
		design.value = '7';
		design.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		expect( texts() ).toEqual( [ '★ Zoe', 'Ana', 'Bea · No disponible' ] );
	} );

	it( 'al elegir el diseño, sus recomendados suben arriba; los demás, por nombre y los no disponibles al final', () => {
		const list = document.querySelector( '[data-fmc-speakers]' );
		list.insertAdjacentHTML( 'beforeend', '<option value="4" data-fmc-for="9">Luis</option>' );
		const design = document.querySelector( 'select[name="f[fmc_design_id]"]' );
		design.value = '9';
		design.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		expect( texts() ).toEqual( [ '★ Luis', 'Ana', 'Zoe', 'Bea · No disponible' ] );
		design.value = '';
		design.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		expect( texts() ).toEqual( [ 'Ana', 'Luis', 'Zoe', 'Bea · No disponible' ] );
	} );

	it( 'con Tom Select, también cambia su copia de la opción', () => {
		const ts = { updateOption: vi.fn(), options: { 1: { $order: 0 } }, clearCache: vi.fn() };
		document.querySelector( '[data-fmc-speakers]' ).tomselect = ts;
		const design = document.querySelector( 'select[name="f[fmc_design_id]"]' );
		design.value = '9';
		design.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		expect( ts.updateOption ).toHaveBeenCalledWith( '1', expect.objectContaining( { value: '1', text: 'Zoe' } ) );
		expect( ts.clearCache ).toHaveBeenCalled();
	} );

	it( 'cada ponente, en dos líneas: nombre con etiquetas y, debajo, isla, cargo y lo impartido', () => {
		const escape = ( t ) => String( t ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
		const [ zoe, ana, bea ] = document.querySelectorAll( '[data-fmc-speakers] option' );
		Object.assign( zoe.dataset, { fmcName: 'Zoe', fmcZone: 'Norte', fmcRole: 'Docente jubilado', fmcGiven: '3 acción(es)' } );

		const star = window.fmcApp.speakerHtml( { text: '★ Zoe · Norte', $option: zoe }, escape );
		expect( star ).toContain( 'bi-star-fill' );
		expect( star ).toContain( '<strong>Zoe</strong> <span class="badge fmc-rec">Recomendado</span>' );
		expect( star ).toContain( '<div class="small text-secondary">Norte · Docente jubilado · 3 acción(es)</div>' );

		expect( window.fmcApp.speakerHtml( { text: 'Ana', $option: ana }, escape ) ).toBe( '<div class="fmc-person"><div><strong>Ana</strong></div></div>' );

		const off = window.fmcApp.speakerHtml( { text: 'Bea · No disponible', $option: bea }, escape );
		expect( off ).toContain( '<div class="fmc-person fmc-off">' );
		expect( off ).toContain( '<span class="badge text-bg-secondary">No disponible</span>' );
		expect( off ).toContain( 'data-bs-toggle="tooltip" title="De baja &lt;hasta&gt; junio"' );

		expect( window.fmcApp.speakerItem( { text: '★ Zoe · Norte', $option: zoe }, escape ) ).toMatch( /^<div><i class="bi bi-star-fill fmc-star"[^>]*><\/i> Zoe<\/div>$/ );
	} );

	it( 'Tom Select pinta así solo el selector de ponentes', async () => {
		const seen = [];
		window.TomSelect = vi.fn( function ( s, settings ) {
			s.tomselect = this;
			seen.push( settings );
		} );
		document.querySelectorAll( 'select' ).forEach( ( s ) => s.setAttribute( 'data-fmc-ts', '' ) );
		window.fmcApp.initSelects();
		expect( seen[ 0 ].render ).toBeUndefined();
		expect( seen[ 1 ].render.option ).toBe( window.fmcApp.speakerHtml );
		expect( seen[ 1 ].render.item ).toBe( window.fmcApp.speakerItem );
		expect( seen[ 1 ].onDropdownClose ).toBe( window.fmcApp.hideTips );
	} );

	it( 'un tooltip de Bootstrap, una vez y delegado, para lo que se pinte después', () => {
		delete document.body.dataset.fmcTips;
		const Tooltip = vi.fn();
		window.bootstrap = { Tooltip: Tooltip };
		expect( window.fmcApp.initTips() ).toBe( true );
		expect( window.fmcApp.initTips() ).toBe( false );
		expect( Tooltip ).toHaveBeenCalledOnce();
		expect( Tooltip ).toHaveBeenCalledWith( document.body, expect.objectContaining( { selector: '[data-bs-toggle="tooltip"]' } ) );
		delete window.bootstrap;
		delete document.body.dataset.fmcTips;
	} );

	it( 'al cerrar el desplegable no queda ningún tooltip abierto', () => {
		document.body.insertAdjacentHTML( 'beforeend', '<div class="tooltip">x</div>' );
		window.fmcApp.hideTips();
		expect( document.querySelector( '.tooltip' ) ).toBeNull();
	} );

	it( 'el bocadillo se abre entero al pulsarlo', () => {
		window.Swal = { fire: vi.fn() };
		document.querySelector( 'button[data-fmc-note]' ).click();
		expect( window.Swal.fire ).toHaveBeenCalledWith( expect.objectContaining( { title: 'Observaciones', text: 'Solo por la tarde' } ) );
	} );
} );

describe( 'lo que ya no se elige se lee en cursiva', () => {
	beforeEach( async () => {
		document.body.innerHTML = '';
		await load();
	} );
	afterEach( () => {
		detach();
	} );

	it( 'la opción marcada sale en cursiva y las demás, normales', () => {
		const option = ( dataset ) => ( { text: 'Quien se fue — ya no es asesoría', $option: { dataset } } );
		const escape = ( t ) => t;

		expect( window.fmcApp.goneHtml( option( { fmcGone: '1' } ), escape ) ).toBe( '<div class="fmc-gone">Quien se fue — ya no es asesoría</div>' );
		expect( window.fmcApp.goneHtml( option( {} ), escape ) ).toBe( '<div>Quien se fue — ya no es asesoría</div>' );
		expect( window.fmcApp.goneHtml( { text: 'Sin opción' }, escape ) ).toBe( '<div>Sin opción</div>' );
	} );
} );

describe( 'la asesoría pone el ámbito y el ámbito trae sus centros', () => {
	const ficha = `<form class="fmc-form" method="post" action="/">
		<input name="fmc_id" value="0"><input name="fmc_type" value="fmc_action">
		<div class="col-12 col-md-6"><select name="f[fmc_adviser]" data-fmc-sets="f[tax:fmc_scope]">
			<option value="">—</option>
			<option value="7" data-fmc-value="12">Ana · TIC</option>
		</select></div>
		<div class="col-12 col-md-6"><select name="f[tax:fmc_scope]" data-fmc-refreshes="f[fmc_venue_code]">
			<option value="">—</option><option value="12">Centro de formación Norte</option>
		</select></div>
		<div class="col-12 col-md-8"><select name="f[fmc_venue_code]"><option value="">—</option><option value="38000001">CEIP Lejano (38000001)</option></select></div>
	</form>`;

	beforeEach( async () => {
		document.body.innerHTML = ficha;
		window.fetch = vi.fn().mockResolvedValue( {
			text: () => Promise.resolve( '<div class="col-12 col-md-8"><select name="f[fmc_venue_code]"><option value="">—</option><option value="38000002">CEIP del ámbito (38000002)</option></select><div class="form-text">Solo los centros de Centro de formación Norte</div></div>' ),
		} );
		await load();
	} );
	afterEach( () => {
		detach();
		vi.restoreAllMocks();
	} );

	it( 'elegir la asesoría pone su ámbito y pide los centros de ese ámbito', async () => {
		const adviser = document.querySelector( '[name="f[fmc_adviser]"]' );
		adviser.value = '7';
		adviser.dispatchEvent( new Event( 'change', { bubbles: true } ) );

		expect( document.querySelector( '[name="f[tax:fmc_scope]"]' ).value ).toBe( '12' );
		const url = new URL( window.fetch.mock.calls[ 0 ][ 0 ] );
		expect( url.searchParams.get( 'fmc_ambito' ) ).toBe( '12' );
		expect( url.searchParams.get( 'fmc_marco' ) ).toBe( '1' );
		expect( url.searchParams.get( 'fmc_new' ) ).toBe( 'fmc_action' );

		await vi.waitFor( () => {
			expect( document.querySelector( '[name="f[fmc_venue_code]"] option[value="38000002"]' ) ).not.toBeNull();
		} );
		expect( document.querySelector( '[name="f[fmc_venue_code]"] option[value="38000001"]' ) ).toBeNull();
		expect( document.body.textContent ).toContain( 'Solo los centros de Centro de formación Norte' );
	} );

	it( 'lo ya elegido se queda si el ámbito nuevo lo ofrece', async () => {
		window.fetch.mockResolvedValue( {
			text: () => Promise.resolve( '<div class="col-12 col-md-8"><select name="f[fmc_venue_code]"><option value="">—</option><option value="38000001">CEIP Lejano (38000001)</option><option value="38000002">CEIP del ámbito (38000002)</option></select></div>' ),
		} );
		const centre = document.querySelector( '[name="f[fmc_venue_code]"]' );
		centre.value = '38000001';
		const scope = document.querySelector( '[name="f[tax:fmc_scope]"]' );
		scope.value = '12';
		scope.dispatchEvent( new Event( 'change', { bubbles: true } ) );

		await vi.waitFor( () => {
			expect( document.querySelector( '[name="f[fmc_venue_code]"]' ).value ).toBe( '38000001' );
		} );
	} );

	it( 'si la respuesta no trae el campo, se deja el que hay', async () => {
		window.fetch.mockResolvedValue( { text: () => Promise.resolve( '<p>Otra cosa</p>' ) } );
		const scope = document.querySelector( '[name="f[tax:fmc_scope]"]' );
		const antes = document.querySelector( '[name="f[fmc_venue_code]"]' );
		scope.value = '12';
		scope.dispatchEvent( new Event( 'change', { bubbles: true } ) );

		await vi.waitFor( () => {
			expect( antes.closest( '[class*="col-"]' ).hasAttribute( 'aria-busy' ) ).toBe( false );
		} );
		expect( document.querySelector( '[name="f[fmc_venue_code]"]' ) ).toBe( antes );
	} );

	it( 'sin respuesta del servidor, la ficha se queda como estaba', async () => {
		window.fetch.mockRejectedValue( new Error( 'sin red' ) );
		const scope = document.querySelector( '[name="f[tax:fmc_scope]"]' );
		const antes = document.querySelector( '[name="f[fmc_venue_code]"]' );
		scope.value = '12';
		scope.dispatchEvent( new Event( 'change', { bubbles: true } ) );

		await vi.waitFor( () => {
			expect( antes.closest( '[class*="col-"]' ).hasAttribute( 'aria-busy' ) ).toBe( false );
		} );
		expect( document.querySelector( '[name="f[fmc_venue_code]"]' ) ).toBe( antes );
	} );

	it( 'una asesoría sin ámbito no cambia el que haya puesto', () => {
		const scope = document.querySelector( '[name="f[tax:fmc_scope]"]' );
		scope.value = '12';
		const adviser = document.querySelector( '[name="f[fmc_adviser]"]' );
		adviser.value = '';
		adviser.dispatchEvent( new Event( 'change', { bubbles: true } ) );

		expect( scope.value ).toBe( '12' );
	} );

	it( 'un campo que no está no se pide al servidor', () => {
		document.querySelector( '[name="f[fmc_venue_code]"]' ).closest( '[class*="col-"]' ).remove();
		const scope = document.querySelector( '[name="f[tax:fmc_scope]"]' );
		scope.value = '12';
		scope.dispatchEvent( new Event( 'change', { bubbles: true } ) );

		expect( window.fetch ).not.toHaveBeenCalled();
	} );

	it( 'con buscador, el ámbito se pone por él y el campo se rehace', async () => {
		const scope = document.querySelector( '[name="f[tax:fmc_scope]"]' );
		const centre = document.querySelector( '[name="f[fmc_venue_code]"]' );
		const setValue = vi.fn();
		const destroy = vi.fn();
		scope.tomselect = { setValue };
		centre.tomselect = { destroy };

		const adviser = document.querySelector( '[name="f[fmc_adviser]"]' );
		adviser.value = '7';
		adviser.dispatchEvent( new Event( 'change', { bubbles: true } ) );

		expect( setValue ).toHaveBeenCalledWith( '12', true );
		await vi.waitFor( () => {
			expect( destroy ).toHaveBeenCalled();
		} );
	} );

	it( 'una ficha ya guardada se pide por su identificador', () => {
		document.querySelector( '[name="fmc_id"]' ).value = '42';
		const scope = document.querySelector( '[name="f[tax:fmc_scope]"]' );
		scope.value = '12';
		scope.dispatchEvent( new Event( 'change', { bubbles: true } ) );

		const url = new URL( window.fetch.mock.calls[ 0 ][ 0 ] );
		expect( url.searchParams.get( 'fmc_edit' ) ).toBe( '42' );
		expect( url.searchParams.get( 'fmc_new' ) ).toBeNull();
	} );
} );

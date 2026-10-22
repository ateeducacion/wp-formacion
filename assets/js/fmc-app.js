/**
 * Formación: los dos comportamientos del lado del navegador.
 *
 * 1. Los `select[data-fmc-ts]` se convierten en Tom Select: buscador, y cada
 *    elegido como una etiqueta con su «×» cuando se pueden elegir varios.
 * 3. Los `a.fmc-abre-panel` abren su ficha en un panel lateral de Bootstrap
 *    (offcanvas): se pide la ficha sola (`fmc_marco=1`), se pinta dentro y se
 *    guarda desde ahí sin salir. Cerrarlo recarga la vista de debajo solo si
 *    se guardó algo. Sin guion, el enlace abre la ficha en su página.
 * 4. Los campos con `data-fmc-show` se ven solo si otro campo tiene uno de
 *    los valores de `data-fmc-show-values` (las horas según la modalidad, por
 *    ejemplo). El servidor aplica la misma regla al guardar.
 * 5. Al elegir un fichero, su nombre aparece al lado del botón.
 * 6. Los `textarea[data-fmc-rich]` llevan el TinyMCE que trae WordPress,
 *    con lo justo: negrita, cursiva y listas. Sin él, se quedan como cuadro
 *    de texto y el HTML se guarda igual.
 * 7. Los `button[data-fmc-tool]` abren elidd incrustada en un diálogo: su
 *    «Guardar» devuelve los indicadores elegidos, que se escriben en el campo
 *    del perfil como su dirección. Sin guion, la dirección se pega a mano.
 * 2. Los `form[data-fmc-confirm]` piden confirmación antes de enviarse, en
 *    tres escalones como en eventos: SweetAlert2 si ha cargado; si no, el
 *    `confirm()` del navegador; y sin JavaScript el formulario se envía tal
 *    cual, porque el servidor vuelve a comprobar el permiso y el nonce.
 */
( function () {
	'use strict';

	function initSelects( root ) {
		if ( ! window.TomSelect ) {
			return 0;
		}
		let n = 0;
		( root || document ).querySelectorAll( 'select[data-fmc-ts]' ).forEach( function ( s ) {
			if ( s.tomselect ) {
				return;
			}
			const settings = {
				maxOptions: null,
				allowEmptyOption: ! s.multiple,
				hidePlaceholder: true,
				placeholder: s.dataset.placeholder || '',
				plugins: s.multiple ? [ 'remove_button' ] : [ 'dropdown_input' ],
			};
			if ( s.dataset.fmcSpeakers ) {
				settings.render = { option: speakerHtml, item: speakerItem };
				// Las opciones se repintan: un tooltip abierto se quedaría huérfano.
				settings.onDropdownClose = hideTips;
			}
			// eslint-disable-next-line no-new
			new window.TomSelect( s, settings );
			n++;
		} );
		return n;
	}

	// Un ponente en la lista, en dos líneas: el nombre con sus etiquetas
	// (recomendado, no disponible, el bocadillo de las observaciones) y,
	// debajo, isla, cargo y lo que ha impartido. Recomendado es lo que dice el
	// texto de la opción, que markRecommended() pone al día con el diseño.
	function speakerParts( data, escape ) {
		const set = ( data.$option && data.$option.dataset ) || {};
		const recommended = /^★ /.test( data.text );
		return {
			set: set,
			name: escape( set.fmcName || data.text.replace( /^★ /, '' ) ),
			star: recommended ? '<i class="bi bi-star-fill fmc-star" title="Recomendado para este diseño" aria-label="Recomendado"></i> ' : '',
			tags: ( recommended ? ' <span class="badge fmc-rec">Recomendado</span>' : '' ) + ( set.fmcOff ? ' <span class="badge text-bg-secondary">No disponible</span>' : '' ),
			note: set.fmcNote ? ' <i class="bi bi-chat-left-text fmc-note-icon" data-bs-toggle="tooltip" title="' + escape( set.fmcNote ) + '" aria-label="' + escape( 'Observaciones: ' + set.fmcNote ) + '"></i>' : '',
			facts: [ set.fmcZone, set.fmcRole, set.fmcGiven ].filter( Boolean ).map( escape ).join( ' · ' ),
		};
	}

	function speakerHtml( data, escape ) {
		const p = speakerParts( data, escape );
		return '<div class="fmc-person' + ( p.set.fmcOff ? ' fmc-off' : '' ) + '"><div>' + p.star + '<strong>' + p.name + '</strong>' + p.tags + p.note + '</div>'
			+ ( p.facts ? '<div class="small text-secondary">' + p.facts + '</div>' : '' ) + '</div>';
	}

	// El elegido, en la caja: solo la estrella, el nombre y el bocadillo.
	function speakerItem( data, escape ) {
		const p = speakerParts( data, escape );
		return '<div' + ( p.set.fmcOff ? ' class="fmc-off"' : '' ) + '>' + p.star + p.name + p.note + '</div>';
	}

	// La estrella del recomendado sigue al diseño elegido, sin recargar, y los
	// recomendados suben arriba del todo; después, por nombre, y los no
	// disponibles al final, como los ordena el servidor.
	function markRecommended( form ) {
		form.querySelectorAll( 'select[data-fmc-speakers]' ).forEach( function ( s ) {
			const design = valuesOf( form, s.dataset.fmcSpeakers )[ 0 ] || '';
			const options = Array.prototype.slice.call( s.options );
			options.forEach( function ( o ) {
				const wanted = ( o.dataset.fmcFor || '' ).split( ',' );
				o.textContent = ( design && wanted.indexOf( design ) !== -1 ? '★ ' : '' ) + o.textContent.replace( /^★ /, '' );
			} );
			const starred = function ( o ) {
				return /^★ /.test( o.textContent ) ? 1 : 0;
			};
			const off = function ( o ) {
				return o.dataset.fmcOff ? 1 : 0;
			};
			options.sort( function ( a, b ) {
				return ( starred( b ) - starred( a ) ) || ( off( a ) - off( b ) ) || a.textContent.localeCompare( b.textContent, 'es' );
			} );
			options.forEach( function ( o, i ) {
				s.appendChild( o );
				if ( s.tomselect ) {
					s.tomselect.updateOption( o.value, { value: o.value, text: o.textContent, $option: o } );
					if ( s.tomselect.options[ o.value ] ) {
						s.tomselect.options[ o.value ].$order = i;
					}
				}
			} );
			if ( s.tomselect && s.tomselect.clearCache ) {
				s.tomselect.clearCache();
			}
		} );
	}

	// Un solo tooltip de Bootstrap, delegado: vale para lo que se pinte después,
	// como las opciones de Tom Select o la ficha del panel lateral.
	function initTips() {
		if ( ! window.bootstrap || ! window.bootstrap.Tooltip || document.body.dataset.fmcTips ) {
			return false;
		}
		document.body.dataset.fmcTips = '1';
		// eslint-disable-next-line no-new
		new window.bootstrap.Tooltip( document.body, { selector: '[data-bs-toggle="tooltip"]', container: 'body' } );
		return true;
	}

	function hideTips() {
		document.querySelectorAll( '.tooltip' ).forEach( function ( tip ) {
			tip.remove();
		} );
	}

	function onNoteClick( event ) {
		const button = event.target.closest ? event.target.closest( 'button[data-fmc-note]' ) : null;
		if ( ! button ) {
			return;
		}
		event.preventDefault();
		if ( window.Swal ) {
			window.Swal.fire( { title: 'Observaciones', text: button.dataset.fmcNote, confirmButtonText: 'Cerrar' } );
		}
	}

	function valuesOf( form, name ) {
		const out = [];
		form.querySelectorAll( '[name="' + name + '"], [name="' + name + '[]"]' ).forEach( function ( input ) {
			if ( 'SELECT' === input.tagName ) {
				Array.prototype.forEach.call( input.selectedOptions, function ( o ) {
					out.push( o.value );
				} );
			} else if ( 'checkbox' === input.type || 'radio' === input.type ) {
				if ( input.checked ) {
					out.push( input.value );
				}
			} else if ( 'hidden' !== input.type ) {
				out.push( input.value );
			}
		} );
		return out;
	}

	function applyShow( root ) {
		( root || document ).querySelectorAll( '[data-fmc-show]' ).forEach( function ( el ) {
			const form = el.closest( 'form' );
			if ( ! form ) {
				return;
			}
			const wanted = ( el.dataset.fmcShowValues || '' ).split( ',' );
			const shown = valuesOf( form, el.dataset.fmcShow ).some( function ( v ) {
				return wanted.indexOf( v ) !== -1;
			} );
			el.classList.toggle( 'd-none', ! shown );
		} );
	}

	function onChange( event ) {
		const target = event.target;
		if ( ! target || ! target.closest ) {
			return;
		}
		if ( target.classList && target.classList.contains( 'fmc-file' ) ) {
			const label = document.querySelector( '.fmc-file-name[data-for="' + target.id + '"]' );
			if ( label ) {
				const names = Array.prototype.map.call( target.files || [], function ( f ) {
					return f.name;
				} );
				label.textContent = names.length ? 'Se subirá al guardar: ' + names.join( ', ' ) : '';
			}
		}
		const form = target.closest( 'form' );
		if ( form ) {
			applyShow( form );
			if ( target.name && form.querySelector( 'select[data-fmc-speakers="' + target.name + '"]' ) ) {
				markRecommended( form );
			}
		}
	}

	function richInside( root ) {
		if ( ! window.tinymce || ! window.tinymce.editors ) {
			return [];
		}
		return Array.prototype.filter.call( window.tinymce.editors, function ( ed ) {
			return ed.targetElm && root.contains( ed.targetElm );
		} );
	}

	function removeRich( root ) {
		richInside( root || document ).forEach( function ( ed ) {
			ed.remove();
		} );
	}

	function saveRich() {
		if ( window.tinymce && window.tinymce.triggerSave ) {
			window.tinymce.triggerSave();
		}
	}

	function initRich( root ) {
		if ( ! window.tinymce || ! window.tinymce.init ) {
			return 0;
		}
		if ( window.fmcTinymceBase ) {
			window.tinymce.baseURL = window.fmcTinymceBase;
			window.tinymce.suffix = '.min';
		}
		let n = 0;
		( root || document ).querySelectorAll( 'textarea[data-fmc-rich]' ).forEach( function ( area ) {
			if ( area.dataset.fmcRichOn ) {
				return;
			}
			area.dataset.fmcRichOn = '1';
			window.tinymce.init( {
				target: area,
				menubar: false,
				statusbar: false,
				branding: false,
				plugins: 'lists paste',
				toolbar: 'bold italic | bullist numlist | undo redo',
				height: 200,
				readonly: area.disabled ? 1 : 0,
				setup: function ( ed ) {
					ed.on( 'change', function () {
						ed.save();
					} );
				},
			} );
			n++;
		} );
		return n;
	}

	function send( form ) {
		form.dataset.fmcConfirmed = '1';
		form.submit();
	}

	// Dar de baja a un ponente: el motivo se pide en el mismo aviso, y sin
	// motivo no se envía (el servidor tampoco lo admite, ADR-0011).
	function askReason( form, question ) {
		const field = form.querySelector( 'input[name="fmc_reason"]' );
		if ( window.Swal && window.Swal.fire ) {
			return window.Swal.fire( {
				title: question,
				text: form.dataset.fmcConfirmText || '',
				icon: 'warning',
				input: 'textarea',
				inputLabel: 'Motivo',
				inputPlaceholder: 'Por ejemplo: ha pedido que se borren sus datos.',
				inputAttributes: { maxlength: '500', 'aria-label': 'Motivo de la baja' },
				inputValidator: function ( value ) {
					return value && value.trim() ? null : 'Diga el motivo: sin motivo no hay baja.';
				},
				showCancelButton: true,
				confirmButtonText: form.dataset.fmcConfirmButton || 'Dar de baja',
				cancelButtonText: 'Cancelar',
				confirmButtonColor: '#dc3545',
			} ).then( function ( result ) {
				if ( result && result.isConfirmed ) {
					field.value = String( result.value ).trim();
					send( form );
				}
			} );
		}
		const reason = window.prompt( question + ' Motivo:' );
		if ( reason && reason.trim() ) {
			field.value = reason.trim();
			send( form );
		}
	}

	function onSubmit( event ) {
		const form = event.target;
		if ( ! form || ! form.matches || ! form.matches( 'form[data-fmc-confirm]' ) || form.dataset.fmcConfirmed ) {
			return;
		}
		event.preventDefault();
		const question = form.dataset.fmcConfirm;
		if ( 'fmcReason' in form.dataset ) {
			return askReason( form, question );
		}
		if ( window.Swal && window.Swal.fire ) {
			return window.Swal.fire( {
				title: question,
				text: form.dataset.fmcConfirmText || '',
				icon: 'warning',
				showCancelButton: true,
				focusCancel: true,
				confirmButtonText: form.dataset.fmcConfirmButton || 'Sí',
				cancelButtonText: 'Cancelar',
				confirmButtonColor: '#dc3545',
			} ).then( function ( result ) {
				if ( result && result.isConfirmed ) {
					send( form );
				}
			} );
		}
		if ( window.confirm( question ) ) {
			send( form );
		}
	}

	function reload() {
		window.location.reload();
	}

	function panelParts() {
		return {
			panel: document.querySelector( '.fmc-panel' ),
			backdrop: document.querySelector( '.fmc-panel-backdrop' ),
		};
	}

	function closePanel() {
		const p = panelParts();
		if ( ! p.panel ) {
			return false;
		}
		const saved = 'saved' in p.panel.dataset;
		removeRich( p.panel );
		const reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		p.panel.classList.remove( 'show' );
		if ( p.backdrop ) {
			p.backdrop.classList.remove( 'show' );
		}
		window.setTimeout( function () {
			[ p.panel, p.backdrop ].forEach( function ( el ) {
				if ( el ) {
					el.remove();
				}
			} );
			document.body.classList.remove( 'has-panel' );
			// Solo si se guardó algo: la vista de debajo tiene que enseñarlo.
			if ( saved ) {
				window.fmcApp.reload();
			}
		}, reduced ? 0 : 300 );
		return true;
	}

	function withFrame( url ) {
		const target = new URL( url, window.location.href );
		target.searchParams.set( 'fmc_marco', '1' );
		return target;
	}

	// Pinta en el panel la ficha que devuelve el servidor, y la deja viva:
	// buscadores de Tom Select y enlaces que siguen dentro del panel.
	function fill( panel, html ) {
		const body = panel.querySelector( '.offcanvas-body' );
		removeRich( body );
		body.innerHTML = html;
		const fragment = body.querySelector( '.fmc-fragment' );
		if ( fragment && fragment.dataset.fmcTitle ) {
			panel.querySelector( '.offcanvas-title' ).textContent = fragment.dataset.fmcTitle;
		}
		if ( body.querySelector( '.alert-success' ) ) {
			panel.dataset.saved = '1';
		}
		initSelects( body );
		applyShow( body );
		initRich( body );
		const first = body.querySelector( '.is-invalid, input:not([type=hidden]):not([disabled]), textarea:not([disabled]), select:not([disabled])' );
		if ( first && first.focus ) {
			first.focus();
		}
	}

	function load( panel, request ) {
		panel.querySelector( '.offcanvas-body' ).setAttribute( 'aria-busy', 'true' );
		return window.fetch( request.url, { method: request.method || 'GET', body: request.body, credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.text();
			} )
			.then( function ( html ) {
				panel.querySelector( '.offcanvas-body' ).removeAttribute( 'aria-busy' );
				fill( panel, html );
			} )
			.catch( function () {
				// Sin respuesta, se abre la ficha en la página entera.
				window.location.assign( request.fallback );
			} );
	}

	function openPanel( url, title ) {
		const target = withFrame( url );
		if ( target.origin !== window.location.origin ) {
			return false;
		}
		let panel = panelParts().panel;
		if ( ! panel ) {
			const backdrop = document.createElement( 'div' );
			backdrop.className = 'offcanvas-backdrop fade fmc-panel-backdrop';
			backdrop.addEventListener( 'click', closePanel );

			panel = document.createElement( 'div' );
			panel.className = 'offcanvas offcanvas-end fmc-panel';
			panel.setAttribute( 'role', 'dialog' );
			panel.setAttribute( 'aria-modal', 'true' );
			panel.setAttribute( 'aria-labelledby', 'fmc-panel-title' );
			panel.tabIndex = -1;
			panel.innerHTML = '<div class="offcanvas-header border-bottom"><h2 class="offcanvas-title h5" id="fmc-panel-title"></h2><a class="small ms-auto me-3 fmc-panel-out"></a><button type="button" class="btn-close" aria-label="Cerrar"></button></div><div class="offcanvas-body"><div class="text-center text-secondary py-5"><div class="spinner-border" role="status"></div><div class="mt-2">Cargando…</div></div></div>';
			panel.querySelector( '.btn-close' ).addEventListener( 'click', closePanel );
			document.body.append( backdrop, panel );
			document.body.classList.add( 'has-panel' );
			// Un fotograma para que Bootstrap anime la entrada.
			window.requestAnimationFrame( function () {
				backdrop.classList.add( 'show' );
				panel.classList.add( 'showing', 'show' );
			} );
		}
		panel.querySelector( '.offcanvas-title' ).textContent = title;
		const out = panel.querySelector( '.fmc-panel-out' );
		out.href = url;
		out.textContent = 'Abrir en la página entera';
		load( panel, { url: target.toString(), fallback: url } );
		return true;
	}

	function onClick( event ) {
		if ( event.metaKey || event.ctrlKey || event.shiftKey || event.button || ! event.target.closest ) {
			return;
		}
		// Dentro del panel, los enlaces del aplicativo llevan `fmc_marco=1`
		// (`Screen::url()`): siguen en el panel aunque no lleven la clase, porque
		// si el navegador los siguiera pintaría el fragmento suelto, sin estilos.
		const link = event.target.closest( 'a.fmc-abre-panel, .fmc-panel a[href*="fmc_marco=1"]' );
		if ( ! link ) {
			return;
		}
		const title = ( link.getAttribute( 'title' ) || link.textContent || '' ).trim().replace( /^\+\s*/, '' );
		if ( openPanel( link.href, title ) ) {
			event.preventDefault();
		}
	}

	// Guardar dentro del panel: se envía sin salir de él y se pinta la respuesta.
	function onPanelSubmit( event ) {
		const form = event.target;
		const panel = form && form.closest ? form.closest( '.fmc-panel' ) : null;
		if ( ! panel || ! form.matches( 'form.fmc-form' ) || event.defaultPrevented ) {
			return;
		}
		event.preventDefault();
		saveRich();
		const data = new window.FormData( form );
		if ( event.submitter && event.submitter.name ) {
			data.set( event.submitter.name, event.submitter.value );
		}
		load( panel, { url: form.action, method: 'POST', body: data, fallback: window.location.href } );
	}

	// Una selección de elidd: indicadores `1.1.A1.2` separados por `|`, o nada.
	const SELECTION = /^(\d\.\d\.[ABC][12]\.\d+(\|\d\.\d\.[ABC][12]\.\d+)*)?$/;

	function openTool( button ) {
		const field = document.getElementById( button.dataset.fmcTool );
		let tool;
		try {
			tool = new URL( button.dataset.fmcToolUrl );
		} catch ( error ) {
			return null;
		}
		if ( ! field || ! /^https?:$/.test( tool.protocol ) ) {
			return null;
		}
		const base = tool.origin + tool.pathname;
		const src = new URL( base );
		src.searchParams.set( 'embed', '1' );
		src.searchParams.set( 'origin', window.location.origin );
		// Lo ya elegido viene en la dirección del campo, sea de esta copia o de otra.
		let current = '';
		try {
			current = field.value ? new URL( field.value ).searchParams.get( 'c' ) || '' : '';
		} catch ( error ) {
			current = '';
		}
		if ( current ) {
			src.searchParams.set( 'c', current );
		}

		const dialog = document.createElement( 'dialog' );
		dialog.className = 'fmc-tool';
		dialog.setAttribute( 'aria-label', 'Perfil de competencia digital' );
		dialog.innerHTML = '<div class="fmc-tool-head"><strong>Perfil de competencia digital</strong><span class="small text-secondary">Marque los indicadores y pulse «Guardar».</span><button type="button" class="btn-close ms-auto" aria-label="Cerrar"></button></div><iframe title="elidd"></iframe>';
		const frame = dialog.querySelector( 'iframe' );
		frame.src = src.toString();

		// Sin `<dialog>` nativo (navegadores viejos), se cierra a mano.
		function close() {
			if ( dialog.close ) {
				dialog.close();
			} else {
				dialog.removeAttribute( 'open' );
				dialog.dispatchEvent( new Event( 'close' ) );
			}
		}

		// Solo se acepta lo que manda elidd desde este marco, y con su formato.
		function onMessage( event ) {
			const data = event.data || {};
			if ( event.origin !== tool.origin || event.source !== frame.contentWindow || 'elidd:seleccion' !== data.type || 'string' !== typeof data.c || ! SELECTION.test( data.c ) ) {
				return;
			}
			field.value = data.c ? base + '?c=' + data.c : '';
			field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			const note = field.parentNode.querySelector( '.fmc-tool-note' ) || document.createElement( 'div' );
			note.className = 'form-text fmc-tool-note';
			note.textContent = data.c ? 'Elegidos ' + data.c.split( '|' ).length + ' indicadores. Guarde la ficha para ver la tabla.' : 'Sin indicadores. Guarde la ficha para quitar el perfil.';
			field.after( note );
			close();
		}
		window.addEventListener( 'message', onMessage );
		dialog.addEventListener( 'close', function () {
			window.removeEventListener( 'message', onMessage );
			dialog.remove();
		} );
		dialog.querySelector( '.btn-close' ).addEventListener( 'click', close );
		document.body.append( dialog );
		if ( dialog.showModal ) {
			dialog.showModal();
		} else {
			dialog.setAttribute( 'open', '' );
		}
		return dialog;
	}

	function onToolClick( event ) {
		const button = event.target.closest ? event.target.closest( 'button[data-fmc-tool]' ) : null;
		if ( button && openTool( button ) ) {
			event.preventDefault();
		}
	}

	function onKey( event ) {
		if ( 'Escape' === event.key ) {
			closePanel();
		}
	}

	document.addEventListener( 'submit', onSubmit );
	document.addEventListener( 'submit', onPanelSubmit );
	document.addEventListener( 'click', onClick );
	document.addEventListener( 'click', onToolClick );
	document.addEventListener( 'click', onNoteClick );
	document.addEventListener( 'keydown', onKey );
	document.addEventListener( 'change', onChange );
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initSelects();
			applyShow();
			initRich();
			initTips();
		} );
	} else {
		initSelects();
		applyShow();
		initRich();
		initTips();
	}

	window.fmcApp = {
		initSelects: initSelects,
		onSubmit: onSubmit,
		onClick: onClick,
		onPanelSubmit: onPanelSubmit,
		onKey: onKey,
		onChange: onChange,
		applyShow: applyShow,
		initRich: initRich,
		removeRich: removeRich,
		openPanel: openPanel,
		openTool: openTool,
		onToolClick: onToolClick,
		onNoteClick: onNoteClick,
		speakerHtml: speakerHtml,
		speakerItem: speakerItem,
		initTips: initTips,
		hideTips: hideTips,
		closePanel: closePanel,
		reload: reload,
	};
} )();

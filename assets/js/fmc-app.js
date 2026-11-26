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
 * 5. Los documentos del diseño: lo elegido para subir o para reemplazar se
 *    enseña antes de guardar, cada fichero nuevo con su aspa para no subirlo;
 *    la zona para añadir se resalta al arrastrar encima. Quitar y «Deshacer»
 *    no necesitan guion: son etiquetas de la casilla `fmc_remove[]`.
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
			if ( s.hasAttribute( 'data-fmc-hints' ) ) {
				settings.render = { option: hintHtml };
			}
			if ( ! settings.render && s.querySelector( 'option[data-fmc-gone]' ) ) {
				settings.render = { option: goneHtml, item: goneHtml };
			}
			// eslint-disable-next-line no-new
			new window.TomSelect( s, settings );
			n++;
		} );
		return n;
	}

	// Lo que sigue guardado pero ya no se elige —una asesoría que dejó de
	// serlo— se lee en cursiva: está en la lista porque la ficha es antigua.
	function goneHtml( data, escape ) {
		const set = ( data.$option && data.$option.dataset ) || {};
		return '<div' + ( set.fmcGone ? ' class="fmc-gone"' : '' ) + '>' + escape( data.text ) + '</div>';
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

	// Un término con lo que significa: el nombre, y al pasar por él, su
	// descripción (`data-fmc-hint`), como un bocadillo del navegador.
	function hintHtml( data, escape ) {
		const hint = ( data.$option && data.$option.dataset && data.$option.dataset.fmcHint ) || '';
		return '<div' + ( hint ? ' title="' + escape( hint ) + '"' : '' ) + '>' + escape( data.text ) + '</div>';
	}

	// Debajo del elegido, lo que significa; cambia al elegir otro.
	function showHint( select ) {
		const box = select.id ? document.querySelector( '.fmc-hint[data-fmc-hint-for="' + select.id + '"]' ) : null;
		if ( ! box ) {
			return;
		}
		const option = select.options[ select.selectedIndex ];
		const hint = ( option && option.dataset.fmcHint ) || '';
		box.textContent = hint;
		// El diseño de una acción enlaza además a su ficha, en otra pestaña para
		// no perder la que se está rellenando (#57). Igual que Editor::hint_html().
		const url = ( option && option.dataset.fmcHintUrl ) || '';
		if ( hint && url ) {
			const link = document.createElement( 'a' );
			link.href = url;
			link.target = '_blank';
			link.rel = 'noopener';
			link.textContent = 'Ver la ficha del diseño';
			box.append( ' ', link );
		}
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

	// La asesoría elegida pone el ámbito de su centro de referencia: lo trae
	// la propia opción. Después se puede cambiar a mano, que es lo que pasa
	// cuando la acción la organiza otro ámbito.
	function setFromOption( select ) {
		const form = select.form;
		const target = form && form.querySelector( '[name="' + select.dataset.fmcSets + '"]' );
		const option = select.options[ select.selectedIndex ];
		const value = option && option.dataset ? option.dataset.fmcValue : '';
		if ( ! target || ! value || target.value === value ) {
			return false;
		}
		if ( target.tomselect ) {
			target.tomselect.setValue( value, true );
		}
		target.value = value;
		target.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		return true;
	}

	// Qué centros se ofrecen lo decide el ámbito, y lo decide el servidor: al
	// cambiarlo se vuelve a pedir la ficha y se cambia solo ese campo, con su
	// línea de ayuda. Sin guion, el ámbito guardado es el que acota la lista,
	// como siempre.
	function refreshField( select ) {
		const form = select.form;
		const name = select.dataset.fmcRefreshes;
		const target = form && form.querySelector( '[name="' + name + '"]' );
		const column = target && target.closest( '[class*="col-"]' );
		if ( ! column ) {
			return null;
		}
		const url = withFrame( form.getAttribute( 'action' ) || window.location.href );
		const id = ( form.querySelector( '[name="fmc_id"]' ) || {} ).value;
		if ( parseInt( id, 10 ) > 0 ) {
			url.searchParams.set( 'fmc_edit', id );
		} else {
			url.searchParams.set( 'fmc_new', ( form.querySelector( '[name="fmc_type"]' ) || {} ).value || '' );
		}
		url.searchParams.set( 'fmc_ambito', select.value || '0' );
		const keep = target.value;
		column.setAttribute( 'aria-busy', 'true' );
		return window.fetch( url, { credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.text();
			} )
			.then( function ( html ) {
				const fresh = new window.DOMParser().parseFromString( html, 'text/html' ).querySelector( '[name="' + name + '"]' );
				const fresher = fresh && fresh.closest( '[class*="col-"]' );
				if ( ! fresher ) {
					column.removeAttribute( 'aria-busy' );
					return;
				}
				// Lo que ya estaba elegido se queda si el ámbito nuevo lo ofrece.
				if ( keep && fresh.querySelector( 'option[value="' + keep + '"]' ) ) {
					fresh.value = keep;
				}
				if ( target.tomselect ) {
					target.tomselect.destroy();
				}
				column.replaceWith( fresher );
				initSelects( fresher );
			} )
			.catch( function () {
				column.removeAttribute( 'aria-busy' );
			} );
	}

	// Documentos: la familia de un fichero por su extensión, como la pinta el
	// servidor en la ficha de cada documento guardado.
	const DOC_FAMILIES = {
		pdf: 'pdf',
		doc: 'text',
		docx: 'text',
		odt: 'text',
		rtf: 'text',
		txt: 'text',
		ppt: 'slides',
		pptx: 'slides',
		odp: 'slides',
		jpg: 'image',
		jpeg: 'image',
		png: 'image',
		gif: 'image',
		webp: 'image',
	};

	const ICON_X = '<svg class="fmc-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>';

	// Lo ya elegido en cada zona que admite varios: una elección nueva se suma.
	const pickedBefore = new WeakMap();

	function extensionOf( name ) {
		const match = /\.([^.]+)$/.exec( name || '' );
		return match ? match[ 1 ].toLowerCase() : '';
	}

	function docBadge( name ) {
		const ext = extensionOf( name );
		const badge = document.createElement( 'span' );
		badge.className = 'fmc-doc-type';
		badge.dataset.family = DOC_FAMILIES[ ext ] || 'other';
		badge.setAttribute( 'aria-hidden', 'true' );
		badge.textContent = ext ? ext.slice( 0, 4 ).toUpperCase() : 'FICH';
		return badge;
	}

	// Same units and rounding as WordPress's size_format(): binary units, no decimals.
	function humanSize( bytes ) {
		if ( 'number' !== typeof bytes || bytes < 0 ) {
			return '';
		}
		const units = [ [ 'TB', 1099511627776 ], [ 'GB', 1073741824 ], [ 'MB', 1048576 ], [ 'KB', 1024 ] ];
		for ( let i = 0; i < units.length; i++ ) {
			if ( bytes >= units[ i ][ 1 ] ) {
				return Math.round( bytes / units[ i ][ 1 ] ) + ' ' + units[ i ][ 0 ];
			}
		}
		return bytes + ' B';
	}

	// Lo mismo que comprueba el servidor al guardar: el formato y el tamaño.
	function fileProblem( input, file ) {
		const exts = ( input.dataset.fmcExts || '' ).split( ',' ).filter( Boolean );
		const ext = extensionOf( file.name );
		if ( exts.length && -1 === exts.indexOf( ext ) ) {
			return ext ? 'No se admite el formato ' + ext.toUpperCase() : 'No se admite un fichero sin extensión';
		}
		const max = parseInt( input.dataset.fmcMax, 10 );
		if ( max && file.size > max ) {
			return 'Pasa de ' + humanSize( max );
		}
		return '';
	}

	function fileKey( file ) {
		return file.name + '|' + file.size + '|' + file.lastModified;
	}

	// Rewrites the files of an input; false where the browser does not allow it.
	function setFiles( input, files ) {
		try {
			const transfer = new window.DataTransfer();
			files.forEach( function ( file ) {
				transfer.items.add( file );
			} );
			input.files = transfer.files;
			return true;
		} catch ( error ) {
			return false;
		}
	}

	function canSetFiles() {
		try {
			return !! new window.DataTransfer();
		} catch ( error ) {
			return false;
		}
	}

	// Lo que se dice a un lector de pantalla cuando cambia algo de la lista.
	function docStatus( el, text ) {
		const docs = el.closest( '.fmc-docs' );
		const region = docs && docs.querySelector( '[data-fmc-doc-status]' );
		if ( region ) {
			region.textContent = text;
		}
	}

	// Una elección nueva en una zona que admite varios se suma a la anterior,
	// sin repetir ficheros. Los cambios que hace el propio guion (quitar uno,
	// cancelar) llevan `fmcQuiet` y no se suman.
	function mergePicked( input ) {
		if ( ! input.multiple ) {
			return;
		}
		const quiet = 'fmcQuiet' in input.dataset;
		delete input.dataset.fmcQuiet;
		const before = pickedBefore.get( input ) || [];
		const now = Array.prototype.slice.call( input.files || [] );
		if ( ! quiet && before.length ) {
			const all = before.slice();
			const keys = all.map( fileKey );
			now.forEach( function ( file ) {
				if ( -1 === keys.indexOf( fileKey( file ) ) ) {
					all.push( file );
					keys.push( fileKey( file ) );
				}
			} );
			if ( all.length !== now.length ) {
				setFiles( input, all );
			}
		}
		pickedBefore.set( input, Array.prototype.slice.call( input.files || [] ) );
	}

	function pendingCard( input, file, index ) {
		const problem = fileProblem( input, file );
		const item = document.createElement( 'li' );
		item.className = 'fmc-doc is-new' + ( problem ? ' is-bad' : '' );
		const card = document.createElement( 'div' );
		card.className = 'fmc-doc-card';
		const main = document.createElement( 'div' );
		main.className = 'fmc-doc-main';
		const name = document.createElement( 'span' );
		name.className = 'fmc-doc-name';
		name.title = file.name;
		name.textContent = file.name;
		main.append( name );
		const size = humanSize( file.size );
		if ( size ) {
			const meta = document.createElement( 'span' );
			meta.className = 'fmc-doc-meta';
			meta.textContent = size;
			main.append( meta );
		}
		const note = document.createElement( 'span' );
		note.className = 'fmc-doc-new';
		note.textContent = problem ? problem + ': no se subirá. Quítelo de la lista.' : 'Se subirá al guardar';
		main.append( note );
		const drop = document.createElement( 'button' );
		drop.type = 'button';
		drop.className = 'fmc-doc-x';
		drop.title = 'No subir';
		drop.dataset.fmcUnpick = input.id;
		drop.dataset.fmcIndex = String( index );
		drop.setAttribute( 'aria-label', 'No subir: ' + file.name );
		drop.innerHTML = ICON_X;
		card.append( docBadge( file.name ), main, drop );
		item.append( card );
		return item;
	}

	// Lo elegido en un campo de fichero se enseña antes de guardar: en un
	// documento guardado, «Se reemplazará al guardar por…»; en la zona para
	// añadir, una ficha por fichero, «Se subirá al guardar», cada una con su
	// aspa para no subirlo. Lo que el servidor no va a admitir se dice ya.
	function showPicked( input ) {
		const box = document.querySelector( '[data-fmc-picked="' + input.id + '"]' );
		if ( ! box ) {
			return;
		}
		const files = Array.prototype.slice.call( input.files || [] );
		if ( 'UL' !== box.tagName ) {
			const problem = files.length ? fileProblem( input, files[ 0 ] ) : '';
			const name = box.querySelector( '[data-fmc-picked-name]' );
			if ( name ) {
				name.textContent = files.length ? files[ 0 ].name : '';
			}
			let why = box.querySelector( '[data-fmc-picked-problem]' );
			if ( ! why ) {
				why = document.createElement( 'span' );
				why.className = 'fmc-doc-problem';
				why.dataset.fmcPickedProblem = '';
				box.insertBefore( why, box.querySelector( 'button' ) );
			}
			why.textContent = problem ? ' ' + problem + ': no se puede guardar así.' : '';
			box.classList.toggle( 'is-bad', !! problem );
			box.hidden = ! files.length || input.disabled;
			docStatus( input, files.length ? 'Se reemplazará al guardar por: ' + files[ 0 ].name + ( problem ? '. ' + problem : '' ) : '' );
			return;
		}
		box.textContent = '';
		files.forEach( function ( file, index ) {
			box.append( pendingCard( input, file, index ) );
		} );
		if ( input.multiple && files.length && ! canSetFiles() ) {
			const note = document.createElement( 'li' );
			note.className = 'fmc-doc-replaces';
			note.textContent = 'Si vuelve a elegir, la nueva elección sustituye a esta.';
			box.append( note );
		}
		const fine = files.filter( function ( file ) {
			return ! fileProblem( input, file );
		} );
		let text = '';
		if ( 1 === fine.length ) {
			text = 'Se subirá al guardar: ' + fine[ 0 ].name;
		} else if ( fine.length ) {
			text = 'Se subirán al guardar ' + fine.length + ' ficheros';
		}
		if ( fine.length < files.length ) {
			text += ( text ? '. ' : '' ) + ( files.length - fine.length ) + ' no se pueden subir';
		}
		docStatus( input, text );
	}

	// Lo que impide guardar los documentos: un fichero que el servidor no
	// admite, o que entre todos pasen de lo que PHP acepta en un envío, que
	// entonces tira el formulario entero sin decir nada.
	function docsProblem( form ) {
		const docs = form && form.querySelector ? form.querySelector( '.fmc-docs' ) : null;
		if ( ! docs ) {
			return '';
		}
		let total = 0;
		const bad = [];
		form.querySelectorAll( 'input.fmc-file' ).forEach( function ( input ) {
			if ( input.disabled ) {
				return;
			}
			Array.prototype.forEach.call( input.files || [], function ( file ) {
				total += file.size || 0;
				if ( fileProblem( input, file ) ) {
					bad.push( '«' + file.name + '»' );
				}
			} );
		} );
		if ( bad.length ) {
			return 'No se puede guardar: ' + bad.join( ', ' ) + ( 1 === bad.length ? ' no se puede subir. Quítelo' : ' no se pueden subir. Quítelos' ) + ' de la lista o elija otro.';
		}
		const max = parseInt( docs.dataset.fmcPostMax, 10 );
		if ( max && total > max ) {
			return 'No se puede guardar: los ficheros elegidos suman ' + humanSize( total ) + ' y el máximo por guardado es ' + humanSize( max ) + '. Quite alguno y súbalo después.';
		}
		return '';
	}

	function showDocsProblem( form, focus ) {
		const text = docsProblem( form );
		const alert = form && form.querySelector ? form.querySelector( '[data-fmc-doc-alert]' ) : null;
		if ( alert ) {
			alert.textContent = text;
			alert.hidden = ! text;
			if ( text && focus ) {
				alert.focus();
			}
		}
		return text;
	}

	// Antes que cualquier otro envío (va en captura): con un problema en los
	// documentos no sale nada, ni a la página ni al panel.
	function onDocSubmit( event ) {
		if ( showDocsProblem( event.target, true ) ) {
			event.preventDefault();
			event.stopImmediatePropagation();
		}
	}

	// Quitar un fichero de los elegidos: se rehace la lista sin él; donde el
	// navegador no deja, se vacía la elección entera.
	function unpick( input, index ) {
		const keep = Array.prototype.slice.call( input.files || [] ).filter( function ( file, i ) {
			return i !== index;
		} );
		input.dataset.fmcQuiet = '';
		if ( ! keep.length || ! setFiles( input, keep ) ) {
			input.value = '';
		}
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	function onDocClick( event ) {
		if ( ! event.target.closest ) {
			return;
		}
		const clear = event.target.closest( 'button[data-fmc-file-clear]' );
		const drop = event.target.closest( 'button[data-fmc-unpick]' );
		if ( ! clear && ! drop ) {
			return;
		}
		const input = document.getElementById( clear ? clear.dataset.fmcFileClear : drop.dataset.fmcUnpick );
		if ( ! input ) {
			return;
		}
		event.preventDefault();
		if ( clear ) {
			const box = input.closest( '.fmc-doc' ) ? input.closest( '.fmc-doc' ).querySelector( '.fmc-doc-rm' ) : null;
			input.dataset.fmcQuiet = '';
			input.value = '';
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			docStatus( input, 'Se mantiene: ' + ( box ? box.dataset.fmcDocName : '' ) );
			input.focus();
			return;
		}
		const index = parseInt( drop.dataset.fmcIndex, 10 ) || 0;
		const file = ( input.files || [] )[ index ];
		unpick( input, index );
		if ( file ) {
			docStatus( input, 'No se subirá: ' + file.name );
		}
		// El foco, a la ficha que queda en su sitio, o a la zona si no queda ninguna.
		const left = document.querySelectorAll( 'button[data-fmc-unpick="' + input.id + '"]' );
		const next = left[ Math.min( index, left.length - 1 ) ];
		( next || input ).focus();
	}

	// Marcar o desmarcar un documento para quitar: lo enseña el CSS; aquí se
	// dice en voz alta, el foco se queda en la casilla —su anillo se pinta en
	// «Deshacer» o en el aspa, la que se vea— y el reemplazo que hubiera
	// elegido se apaga, para que no se suba un documento que se quita.
	function onDocRemove( box ) {
		const name = box.dataset.fmcDocName || '';
		const item = box.closest( '.fmc-doc' );
		const replace = item ? item.querySelector( 'input.fmc-file' ) : null;
		if ( replace ) {
			replace.disabled = box.checked;
			const note = document.querySelector( '[data-fmc-picked="' + replace.id + '"]' );
			if ( note ) {
				note.hidden = box.checked || ! ( replace.files && replace.files.length );
			}
		}
		docStatus( box, box.checked ? 'Se quitará al guardar: ' + name : 'Se mantiene: ' + name );
		showDocsProblem( box.form, false );
		box.focus();
	}

	function carriesFiles( event ) {
		const types = event.dataTransfer ? event.dataTransfer.types : null;
		return !! types && -1 !== Array.prototype.indexOf.call( types, 'Files' );
	}

	// Arrastrar ficheros: la zona para añadir se resalta y el propio campo los
	// recibe; sobre la ficha de un documento guardado, el fichero lo
	// reemplaza; en cualquier otro sitio de la ficha no se deja soltar, porque
	// el navegador abriría el fichero y se perdería lo que no se ha guardado.
	function onDrag( event ) {
		if ( ! carriesFiles( event ) || ! document.querySelector( '.fmc-docs' ) ) {
			return;
		}
		const target = event.target && event.target.closest ? event.target : document.documentElement;
		const over = 'dragenter' === event.type || 'dragover' === event.type;
		const zone = target.closest( '.fmc-drop' );
		if ( zone ) {
			zone.classList.toggle( 'is-over', over );
			return;
		}
		const item = target.closest( '.fmc-doc' );
		const replace = item ? item.querySelector( 'input.fmc-file:not([disabled])' ) : null;
		if ( 'dragleave' !== event.type ) {
			event.preventDefault();
		}
		if ( ! replace ) {
			event.dataTransfer.dropEffect = 'none';
			return;
		}
		item.classList.toggle( 'is-over', over );
		event.dataTransfer.dropEffect = 'copy';
		const file = 'drop' === event.type && event.dataTransfer.files ? event.dataTransfer.files[ 0 ] : null;
		if ( file && setFiles( replace, [ file ] ) ) {
			replace.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
	}

	// Los filtros de un listado se aplican al cambiarlos, sin botón: un
	// desplegable o una fecha, en el momento. Lo que se escribe espera a Intro
	// o a la lupa: al salir de la casilla para elegir otro filtro se recargaría
	// la página a medio camino, y ese otro filtro ya lo envía todo.
	function filterForm( field ) {
		return field && field.form && field.form.matches && field.form.matches( 'form.fmc-filters' ) ? field.form : null;
	}

	// Una fecha a medio teclear ya es una fecha —el año «0002»—: se espera a
	// que el año tenga sus cuatro cifras.
	function filterReady( field ) {
		if ( 'date' === field.type ) {
			return '' === field.value || Number( field.value.slice( 0, 4 ) ) > 999;
		}
		return 'SELECT' === field.tagName || 'checkbox' === field.type;
	}

	function applyFilters( form ) {
		if ( form.dataset.fmcSent ) {
			return;
		}
		if ( form.requestSubmit ) {
			form.requestSubmit();
		} else {
			form.submit();
		}
	}

	// El primer envío deja la tarjeta «cargando»; otro igual mientras tanto
	// —Intro después de elegir— no hace falta, y se para.
	function onFilterSubmit( event ) {
		const form = event.target;
		if ( ! form || ! form.matches || ! form.matches( 'form.fmc-filters' ) ) {
			return;
		}
		if ( form.dataset.fmcSent ) {
			event.preventDefault();
			return;
		}
		form.dataset.fmcSent = '1';
		form.setAttribute( 'aria-busy', 'true' );
	}

	// El aspa de una caja de búsqueda la vacía sin pasar por Intro.
	function onSearch( event ) {
		const form = filterForm( event.target );
		if ( form && '' === event.target.value ) {
			applyFilters( form );
		}
	}

	// Al volver atrás, el navegador devuelve la página como se dejó: enviada.
	function onPageShow() {
		document.querySelectorAll( 'form.fmc-filters' ).forEach( function ( form ) {
			delete form.dataset.fmcSent;
			form.removeAttribute( 'aria-busy' );
		} );
	}

	function onChange( event ) {
		const target = event.target;
		if ( ! target || ! target.closest ) {
			return;
		}
		const filters = filterForm( target );
		if ( filters ) {
			if ( filterReady( target ) ) {
				applyFilters( filters );
			}
			return;
		}
		if ( target.classList && target.classList.contains( 'fmc-file' ) ) {
			mergePicked( target );
			showPicked( target );
			showDocsProblem( target.form, false );
		}
		if ( target.classList && target.classList.contains( 'fmc-doc-rm' ) ) {
			onDocRemove( target );
		}
		if ( target.hasAttribute && target.hasAttribute( 'data-fmc-hints' ) ) {
			showHint( target );
		}
		if ( target.dataset && target.dataset.fmcSets ) {
			setFromOption( target );
		}
		if ( target.dataset && target.dataset.fmcRefreshes ) {
			refreshField( target );
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

	// Una ficha (`form.fmc-form`) se vuelve a enviar como lo haría su botón:
	// `submit()` se saltaría el guardado dentro del panel y no mandaría el
	// botón pulsado. Lo demás, como siempre.
	function send( form, submitter ) {
		form.dataset.fmcConfirmed = '1';
		if ( form.matches( 'form.fmc-form' ) && form.requestSubmit ) {
			form.requestSubmit( submitter && submitter.form === form ? submitter : undefined );
			delete form.dataset.fmcConfirmed;
			return;
		}
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
					return value && value.trim() ? null : 'Indique el motivo de la baja.';
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
		// Que el aviso no deje pasar a los demás oyentes: el del panel
		// guardaría la ficha antes de que nadie conteste.
		event.stopImmediatePropagation();
		const question = form.dataset.fmcConfirm;
		const submitter = event.submitter;
		if ( 'fmcReason' in form.dataset ) {
			return askReason( form, question );
		}
		// Con su propio icono —un recordatorio, una advertencia— no es un
		// borrado: sin rojo, y con el foco en seguir.
		const icon = form.dataset.fmcConfirmIcon;
		const calm = !! icon;
		if ( window.Swal && window.Swal.fire ) {
			return window.Swal.fire( {
				title: question,
				text: form.dataset.fmcConfirmText || '',
				icon: icon || 'warning',
				showCancelButton: true,
				focusCancel: ! calm,
				confirmButtonText: form.dataset.fmcConfirmButton || 'Sí',
				cancelButtonText: 'Cancelar',
				confirmButtonColor: calm ? '#0d6efd' : '#dc3545',
			} ).then( function ( result ) {
				if ( result && result.isConfirmed ) {
					send( form, submitter );
				}
			} );
		}
		const text = form.dataset.fmcConfirmText ? '\n\n' + form.dataset.fmcConfirmText : '';
		if ( window.confirm( question + ( calm ? text : '' ) ) ) {
			send( form, submitter );
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
		out.textContent = 'Abrir en página completa';
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

	// Las sesiones de una acción (ADR-0027): «+ Añadir sesión» saca una fila
	// de la plantilla con un índice nuevo, «×» quita la suya, y el total de
	// horas se pone al día con cada cambio. El servidor pinta lo mismo.
	function sessionHours( box ) {
		let minutes = 0;
		box.querySelectorAll( 'tbody tr[data-fmc-session]' ).forEach( function ( row ) {
			const from = row.querySelector( 'input[name$="[from]"]' );
			const to = row.querySelector( 'input[name$="[to]"]' );
			const a = /^(\d{1,2}):(\d{2})$/.exec( from ? from.value : '' );
			const b = /^(\d{1,2}):(\d{2})$/.exec( to ? to.value : '' );
			if ( a && b ) {
				const span = ( +b[ 1 ] * 60 + +b[ 2 ] ) - ( +a[ 1 ] * 60 + +a[ 2 ] );
				minutes += span > 0 ? span : 0;
			}
		} );
		return Math.round( minutes / 60 * 100 ) / 100;
	}

	function updateSessions( box ) {
		const total = box.querySelector( '[data-fmc-sessions-total]' );
		if ( total ) {
			total.textContent = sessionHours( box ) + ' h';
		}
	}

	function onSessionClick( event ) {
		if ( ! event.target.closest ) {
			return;
		}
		const add = event.target.closest( 'button[data-fmc-session-add]' );
		const remove = event.target.closest( 'button[data-fmc-session-remove]' );
		const box = ( add || remove ) ? ( add || remove ).closest( '[data-fmc-sessions]' ) : null;
		if ( ! box ) {
			return;
		}
		event.preventDefault();
		if ( add ) {
			const template = box.querySelector( 'template[data-fmc-session-template]' );
			const next = parseInt( box.dataset.fmcNext || '0', 10 ) || 0;
			box.dataset.fmcNext = String( next + 1 );
			const holder = document.createElement( 'tbody' );
			holder.innerHTML = template.innerHTML.replace( /__i__/g, String( next ) );
			const row = holder.firstElementChild;
			box.querySelector( 'tbody' ).append( row );
			const first = row.querySelector( 'input' );
			if ( first && first.focus ) {
				first.focus();
			}
		} else {
			remove.closest( 'tr' ).remove();
		}
		updateSessions( box );
	}

	function onSessionInput( event ) {
		const box = event.target.closest ? event.target.closest( '[data-fmc-sessions]' ) : null;
		if ( box ) {
			updateSessions( box );
		}
	}

	// Un guiño escondido: tres clics seguidos en el círculo de la inicial, en
	// el perfil, y llueven hamburguesas. No toca nada: es un adorno por encima
	// de la página, que no se puede pulsar y se quita solo; quien ha pedido
	// menos movimiento no lo ve.
	function party() {
		if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
			return;
		}
		if ( document.querySelector( '.fmc-party' ) ) {
			return;
		}
		const box = document.createElement( 'div' );
		box.className = 'fmc-party';
		box.setAttribute( 'aria-hidden', 'true' );
		for ( let i = 0; i < 36; i++ ) {
			const bit = document.createElement( 'span' );
			bit.textContent = '🍔';
			bit.style.left = ( Math.random() * 100 ) + '%';
			bit.style.fontSize = ( 1.4 + Math.random() * 1.6 ) + 'rem';
			bit.style.animationDuration = ( 2.5 + Math.random() * 2.5 ) + 's';
			bit.style.animationDelay = ( Math.random() * 1.5 ) + 's';
			box.appendChild( bit );
		}
		document.body.appendChild( box );
		window.setTimeout( function () {
			box.remove();
		}, 8000 );
	}

	function onPartyClick( event ) {
		if ( 3 === event.detail && event.target.closest && event.target.closest( '.fmc-yo .fmc-avatar' ) ) {
			party();
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
	document.addEventListener( 'submit', onFilterSubmit );
	document.addEventListener( 'search', onSearch );
	document.addEventListener( 'click', onPartyClick );
	window.addEventListener( 'pageshow', onPageShow );
	document.addEventListener( 'click', onSessionClick );
	document.addEventListener( 'input', onSessionInput );
	document.addEventListener( 'click', onDocClick );
	document.addEventListener( 'submit', onDocSubmit, true );
	[ 'dragenter', 'dragover', 'dragleave', 'drop' ].forEach( function ( type ) {
		document.addEventListener( type, onDrag );
	} );
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
		onFilterSubmit: onFilterSubmit,
		onSearch: onSearch,
		onPartyClick: onPartyClick,
		setFromOption: setFromOption,
		refreshField: refreshField,
		applyShow: applyShow,
		initRich: initRich,
		removeRich: removeRich,
		openPanel: openPanel,
		openTool: openTool,
		onToolClick: onToolClick,
		onNoteClick: onNoteClick,
		speakerHtml: speakerHtml,
		goneHtml: goneHtml,
		hintHtml: hintHtml,
		speakerItem: speakerItem,
		initTips: initTips,
		hideTips: hideTips,
		closePanel: closePanel,
		reload: reload,
		onSessionClick: onSessionClick,
		onSessionInput: onSessionInput,
		sessionHours: sessionHours,
		onDocClick: onDocClick,
		onDocSubmit: onDocSubmit,
		humanSize: humanSize,
		onDrag: onDrag,
		showPicked: showPicked,
	};
} )();

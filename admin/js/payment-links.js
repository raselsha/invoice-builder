/**
 * Payment Links screen: mode switching, currency symbols, amount options,
 * the end-date calendar, copy buttons, QR codes and delete confirmation.
 * Localized data: window.WIM_LINKS { symbols, today, months, days, i18n }.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.WIM_LINKS || { symbols: {}, i18n: {} };
	var i18n = cfg.i18n || {};

	/* ---------------- Editor ---------------- */

	var editor = document.querySelector( '[data-wim-pl-editor]' );
	if ( editor ) {
		var syncMode = function () {
			var checked = editor.querySelector( '[data-wim-mode]:checked' );
			var mode = checked ? checked.value : 'fixed';
			editor.querySelectorAll( '[data-wim-show]' ).forEach( function ( el ) {
				el.hidden = el.getAttribute( 'data-wim-show' ) !== mode;
			} );
		};
		editor.querySelectorAll( '[data-wim-mode]' ).forEach( function ( input ) {
			input.addEventListener( 'change', syncMode );
		} );
		syncMode();

		var currency = editor.querySelector( '[data-wim-currency] select' );
		var symbol = function () {
			return currency ? ( cfg.symbols[ currency.value ] || currency.value ) : '';
		};

		// Amount options ("chips").
		var chipsRoot = editor.querySelector( '[data-wim-chips]' );
		var chipsValue = chipsRoot.querySelector( '[data-wim-chips-value]' );
		var chipsList = chipsRoot.querySelector( '[data-wim-chips-list]' );
		var chipsInput = chipsRoot.querySelector( '[data-wim-chips-input]' );

		var values = function () {
			return chipsValue.value.split( ',' ).map( function ( v ) { return v.trim(); } ).filter( Boolean );
		};
		var renderChips = function () {
			chipsList.innerHTML = '';
			values().forEach( function ( v ) {
				var chip = document.createElement( 'span' );
				chip.className = 'wim-pl-chip';
				chip.textContent = symbol() + Number( v ).toLocaleString( 'en-US', { maximumFractionDigits: 2 } );
				var x = document.createElement( 'button' );
				x.type = 'button';
				x.setAttribute( 'aria-label', ( i18n.remove || 'Remove' ) + ' ' + chip.textContent );
				x.innerHTML = '&times;';
				x.addEventListener( 'click', function () {
					chipsValue.value = values().filter( function ( o ) { return o !== v; } ).join( ',' );
					renderChips();
				} );
				chip.appendChild( x );
				chipsList.appendChild( chip );
			} );
		};
		var addChip = function () {
			var raw = chipsInput.value.replace( /[,\s]/g, '' );
			if ( ! /^\d+(\.\d{1,2})?$/.test( raw ) || parseFloat( raw ) <= 0 ) {
				chipsInput.focus();
				return;
			}
			var clean = String( parseFloat( raw ) );
			var list = values().filter( function ( v ) { return parseFloat( v ) !== parseFloat( clean ); } );
			list.push( clean );
			list.sort( function ( a, b ) { return parseFloat( a ) - parseFloat( b ); } );
			chipsValue.value = list.join( ',' );
			chipsInput.value = '';
			renderChips();
			chipsInput.focus();
		};
		chipsRoot.querySelector( '[data-wim-chips-add]' ).addEventListener( 'click', addChip );
		chipsInput.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' === e.key || ',' === e.key ) {
				e.preventDefault();
				addChip();
			}
		} );
		renderChips();

		// The custom select fires change through jQuery, so listen with jQuery.
		$( currency ).on( 'change', function () {
			editor.querySelectorAll( '[data-wim-symbol]' ).forEach( function ( el ) {
				el.textContent = symbol();
			} );
			renderChips();
		} );

		// Enter in a text field shouldn't submit the whole form by accident.
		editor.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' === e.key && 'INPUT' === e.target.tagName && 'text' === e.target.type && ! e.target.hasAttribute( 'data-wim-chips-input' ) ) {
				e.preventDefault();
			}
		} );
	}

	/* ---------------- End-date calendar ---------------- */

	function pad( n ) { return ( n < 10 ? '0' : '' ) + n; }
	function ymd( d ) { return d.getFullYear() + '-' + pad( d.getMonth() + 1 ) + '-' + pad( d.getDate() ); }
	function parseYmd( s ) {
		var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec( s || '' );
		return m ? new Date( +m[ 1 ], +m[ 2 ] - 1, +m[ 3 ] ) : null;
	}

	document.querySelectorAll( '[data-wim-date]' ).forEach( function ( root ) {
		var input = root.querySelector( '[data-wim-date-value]' );
		var trigger = root.querySelector( '.wim-pl-date__trigger' );
		var label = root.querySelector( '[data-wim-date-label]' );
		var clear = root.querySelector( '[data-wim-date-clear]' );
		var panel = root.querySelector( '[data-wim-cal]' );
		var today = parseYmd( cfg.today ) || new Date();
		var view = parseYmd( input.value ) || today;
		view = new Date( view.getFullYear(), view.getMonth(), 1 );

		// Fixed positioning so a scrolling ancestor never clips the panel;
		// flips above the trigger when there's no room below.
		function place() {
			var r = trigger.getBoundingClientRect();
			panel.style.position = 'fixed';
			panel.style.left = Math.max( 8, Math.min( r.left, window.innerWidth - panel.offsetWidth - 8 ) ) + 'px';
			var top = r.bottom + 6;
			if ( window.innerHeight - r.bottom < panel.offsetHeight + 12 && r.top > panel.offsetHeight + 12 ) {
				top = r.top - panel.offsetHeight - 6;
			}
			panel.style.top = top + 'px';
		}

		function render() {
			var first = new Date( view.getFullYear(), view.getMonth(), 1 );
			var days = new Date( view.getFullYear(), view.getMonth() + 1, 0 ).getDate();
			var html = '<div class="wim-pl-cal__head"><button type="button" class="wim-pl-cal__nav" data-nav="-1" aria-label="Previous month">&lsaquo;</button>' +
				'<strong>' + ( cfg.months ? cfg.months[ view.getMonth() ] : view.getMonth() + 1 ) + ' ' + view.getFullYear() + '</strong>' +
				'<button type="button" class="wim-pl-cal__nav" data-nav="1" aria-label="Next month">&rsaquo;</button></div><div class="wim-pl-cal__grid">';
			( cfg.days || [ 'S', 'M', 'T', 'W', 'T', 'F', 'S' ] ).forEach( function ( d ) {
				html += '<span class="wim-pl-cal__wd">' + d + '</span>';
			} );
			for ( var b = 0; b < first.getDay(); b++ ) {
				html += '<span></span>';
			}
			for ( var d = 1; d <= days; d++ ) {
				var date = new Date( view.getFullYear(), view.getMonth(), d );
				var key = ymd( date );
				var cls = 'wim-pl-cal__day' + ( key === input.value ? ' is-selected' : '' ) + ( key === ymd( today ) ? ' is-today' : '' );
				html += '<button type="button" class="' + cls + '" data-day="' + key + '"' + ( date < today ? ' disabled' : '' ) + '>' + d + '</button>';
			}
			panel.innerHTML = html + '</div>';
		}

		function close() {
			panel.hidden = true;
			trigger.setAttribute( 'aria-expanded', 'false' );
		}

		function set( value ) {
			input.value = value;
			var d = parseYmd( value );
			label.textContent = d ? d.toLocaleDateString( undefined, { year: 'numeric', month: 'long', day: 'numeric' } ) : ( i18n.noDate || '' );
			clear.hidden = ! d;
		}

		trigger.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			if ( ! panel.hidden ) {
				close();
				return;
			}
			render();
			panel.hidden = false;
			place();
			trigger.setAttribute( 'aria-expanded', 'true' );
		} );

		panel.addEventListener( 'click', function ( e ) {
			// Re-rendering replaces the clicked node; stop the document's
			// "outside click" handler from treating that as a click outside.
			e.stopPropagation();
			var nav = e.target.closest( '[data-nav]' );
			var day = e.target.closest( '[data-day]' );
			if ( nav ) {
				view = new Date( view.getFullYear(), view.getMonth() + parseInt( nav.getAttribute( 'data-nav' ), 10 ), 1 );
				render();
				place();
			} else if ( day && ! day.disabled ) {
				set( day.getAttribute( 'data-day' ) );
				close();
				trigger.focus();
			}
		} );

		clear.addEventListener( 'click', function () { set( '' ); } );
		document.addEventListener( 'click', function ( e ) {
			if ( ! panel.hidden && ! root.contains( e.target ) ) {
				close();
			}
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && ! panel.hidden ) {
				close();
				trigger.focus();
			}
		} );
		window.addEventListener( 'scroll', close, true );
		window.addEventListener( 'resize', close );
	} );

	/* ---------------- Copy / confirm ---------------- */

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-wim-copy]' );
		if ( btn ) {
			var text = btn.getAttribute( 'data-wim-copy' );
			var done = function () {
				var label = btn.querySelector( 'span:not(.dashicons)' );
				btn.classList.add( 'is-copied' );
				if ( label ) {
					var old = label.textContent;
					label.textContent = i18n.copied || 'Copied!';
					window.setTimeout( function () { label.textContent = old; }, 1600 );
				}
				window.setTimeout( function () { btn.classList.remove( 'is-copied' ); }, 1600 );
			};
			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( text ).then( done );
			} else {
				var ta = document.createElement( 'textarea' );
				ta.value = text;
				document.body.appendChild( ta );
				ta.select();
				document.execCommand( 'copy' );
				ta.remove();
				done();
			}
		}

		var confirmLink = e.target.closest( '[data-wim-confirm]' );
		if ( confirmLink && ! window.confirm( i18n['delete'] ) ) {
			e.preventDefault();
		}
	} );

	/* ---------------- QR codes ---------------- */

	function drawQr( container, url ) {
		if ( typeof window.qrcode !== 'function' ) {
			return;
		}
		var qr = window.qrcode( 0, 'M' );
		qr.addData( url );
		qr.make();
		container.innerHTML = qr.createSvgTag( { cellSize: 6, margin: 4, scalable: true } );
		container._qr = qr;
	}

	function downloadQr( container, name ) {
		var qr = container._qr;
		if ( ! qr ) {
			return;
		}
		var count = qr.getModuleCount();
		var scale = 12;
		var margin = 4;
		var size = ( count + margin * 2 ) * scale;
		var canvas = document.createElement( 'canvas' );
		canvas.width = canvas.height = size;
		var ctx = canvas.getContext( '2d' );
		ctx.fillStyle = '#ffffff';
		ctx.fillRect( 0, 0, size, size );
		ctx.fillStyle = '#0f172a';
		for ( var r = 0; r < count; r++ ) {
			for ( var c = 0; c < count; c++ ) {
				if ( qr.isDark( r, c ) ) {
					ctx.fillRect( ( c + margin ) * scale, ( r + margin ) * scale, scale, scale );
				}
			}
		}
		var a = document.createElement( 'a' );
		a.href = canvas.toDataURL( 'image/png' );
		a.download = 'payment-link-' + ( name || 'qr' ) + '.png';
		document.body.appendChild( a );
		a.click();
		a.remove();
	}

	document.querySelectorAll( '[data-wim-qr]' ).forEach( function ( box ) {
		drawQr( box, box.getAttribute( 'data-wim-qr' ) );
		var btn = box.parentNode.querySelector( '[data-wim-qr-download]' );
		if ( btn ) {
			btn.addEventListener( 'click', function () {
				downloadQr( box, box.getAttribute( 'data-wim-qr-name' ) );
			} );
		}
	} );

	var dialog = document.querySelector( '[data-wim-qr-dialog]' );
	if ( dialog ) {
		var target = dialog.querySelector( '[data-wim-qr-target]' );
		var urlEl = dialog.querySelector( '[data-wim-qr-url]' );
		var currentName = '';
		document.addEventListener( 'click', function ( e ) {
			var open = e.target.closest( '[data-wim-qr-open]' );
			if ( open ) {
				currentName = open.getAttribute( 'data-wim-qr-name' );
				drawQr( target, open.getAttribute( 'data-wim-qr-open' ) );
				urlEl.textContent = open.getAttribute( 'data-wim-qr-open' );
				if ( dialog.showModal ) {
					dialog.showModal();
				}
			}
		} );
		dialog.querySelector( '[data-wim-dialog-close]' ).addEventListener( 'click', function () { dialog.close(); } );
		dialog.querySelector( '[data-wim-qr-download]' ).addEventListener( 'click', function () { downloadQr( target, currentName ); } );
		dialog.addEventListener( 'click', function ( e ) {
			if ( e.target === dialog ) {
				dialog.close();
			}
		} );
	}
} )( jQuery );

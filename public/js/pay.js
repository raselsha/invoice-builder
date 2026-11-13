/**
 * Online-payment page (invoice + payment link): show the "Other amount" box when picked, keep the Pay
 * button's amount in sync, and stop double submits.
 */
( function () {
	'use strict';

	var form = document.querySelector( '[data-wim-pay-form]' );
	if ( ! form ) {
		return;
	}

	var symbol = form.getAttribute( 'data-symbol' ) || '';
	var zero = '1' === form.getAttribute( 'data-zero' );
	var button = form.querySelector( '[data-wim-pay]' );
	var label = form.querySelector( '[data-wim-pay-label]' );
	var customWrap = form.querySelector( '[data-wim-custom-wrap]' );
	var customInput = form.querySelector( '[data-wim-custom]' );
	var payWord = button.getAttribute( 'data-label-pay' );

	function format( value ) {
		var n = parseFloat( String( value ).replace( /[,\s]/g, '' ) );
		if ( ! isFinite( n ) || n <= 0 ) {
			return '';
		}
		var whole = zero || Math.abs( n - Math.round( n ) ) < 0.001;
		return symbol + n.toLocaleString( 'en-US', { minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: 2 } );
	}

	function currentAmount() {
		var picked = form.querySelector( 'input[name="wim_choice"]:checked' );
		if ( picked && picked.hasAttribute( 'data-amount' ) ) {
			return format( picked.getAttribute( 'data-amount' ) );
		}
		if ( customInput && ( ( picked && picked.hasAttribute( 'data-custom' ) ) || customInput.hasAttribute( 'data-open' ) ) ) {
			return format( customInput.value );
		}
		return button.getAttribute( 'data-start' ) || '';
	}

	function sync() {
		var picked = form.querySelector( 'input[name="wim_choice"]:checked' );
		if ( customWrap ) {
			var showCustom = !! ( picked && picked.hasAttribute( 'data-custom' ) );
			customWrap.hidden = ! showCustom;
			customInput.required = showCustom;
		}
		var amount = currentAmount();
		label.textContent = amount ? payWord + ' ' + amount : payWord;
	}

	form.addEventListener( 'change', function ( e ) {
		sync();
		if ( e.target.hasAttribute && e.target.hasAttribute( 'data-custom' ) && customInput ) {
			customInput.focus();
		}
	} );
	if ( customInput ) {
		customInput.addEventListener( 'input', sync );
	}

	form.addEventListener( 'submit', function () {
		button.disabled = true;
		label.textContent = button.getAttribute( 'data-label-wait' );
	} );

	// Coming back with the browser's Back button can restore a disabled button.
	window.addEventListener( 'pageshow', function () {
		button.disabled = false;
		sync();
	} );

	sync();
} )();

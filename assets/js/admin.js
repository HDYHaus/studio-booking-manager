( function () {
	'use strict';

	function updateBookingAccessOptions() {
		var person = document.getElementById( 'sbm-booking-person' );
		var location = document.getElementById( 'sbm-booking-location' );
		var access = document.getElementById( 'sbm-booking-access' );

		if ( ! person || ! location || ! access ) {
			return;
		}

		var personId = person.value;
		var locationId = location.value;

		Array.prototype.forEach.call( access.options, function ( option ) {
			if ( '' === option.value ) {
				option.hidden = false;
				option.disabled = false;
				return;
			}

			var optionPersonId = option.getAttribute( 'data-person-id' ) || '';
			var optionLocationId = option.getAttribute( 'data-location-id' ) || '';
			var matches = '' !== personId && '' !== locationId && optionPersonId === personId && optionLocationId === locationId;

			option.hidden = ! matches;
			option.disabled = ! matches;
		} );

		if ( access.selectedOptions.length > 0 && access.selectedOptions[0].disabled ) {
			access.value = '';
		}
	}

	function renderQrCodes() {
		if ( 'undefined' === typeof jQuery || 'function' !== typeof jQuery.fn.qrcode ) {
			return;
		}

		Array.prototype.forEach.call( document.querySelectorAll( '[data-sbm-qr-value]' ), function ( element ) {
			if ( element.getAttribute( 'data-sbm-qr-rendered' ) ) {
				return;
			}

			element.setAttribute( 'data-sbm-qr-rendered', '1' );
			jQuery( element ).empty().qrcode( {
				text: element.getAttribute( 'data-sbm-qr-value' ) || '',
				width: 320,
				height: 320
			} );
		} );
	}

	function qrCanvas() {
		return document.querySelector( '.sbm-qr-code canvas' );
	}

	function downloadQr( event ) {
		var canvas = qrCanvas();

		if ( ! canvas ) {
			event.preventDefault();
			return;
		}

		event.currentTarget.href = canvas.toDataURL( 'image/png' );
		event.currentTarget.download = event.currentTarget.getAttribute( 'data-filename' ) || 'qr-code.png';
	}

	function printQr( event ) {
		if ( event ) {
			event.preventDefault();
		}

		if ( ! qrCanvas() ) {
			renderQrCodes();
		}

		window.print();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var person = document.getElementById( 'sbm-booking-person' );
		var location = document.getElementById( 'sbm-booking-location' );

		updateBookingAccessOptions();

		if ( person ) {
			person.addEventListener( 'change', updateBookingAccessOptions );
		}

		if ( location ) {
			location.addEventListener( 'change', updateBookingAccessOptions );
		}

		renderQrCodes();

		Array.prototype.forEach.call( document.querySelectorAll( '.sbm-qr-download' ), function ( link ) {
			link.addEventListener( 'click', downloadQr );
		} );

		Array.prototype.forEach.call( document.querySelectorAll( '.sbm-qr-print' ), function ( button ) {
			button.addEventListener( 'click', printQr );
		} );

		if ( document.querySelector( '[data-sbm-auto-print="1"]' ) ) {
			window.setTimeout( printQr, 400 );
		}
	} );
}() );

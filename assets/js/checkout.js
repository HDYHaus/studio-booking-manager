( function () {
	'use strict';

	if ( ! document.body.classList.contains( 'sbm-simplify-booking-checkout' ) ) {
		return;
	}

	var defaults = window.sbmCheckoutFields || {};
	var values = {
		'billing-address_1': defaults.address1 || 'Studio booking',
		'billing-city': defaults.city || 'Not required',
		'billing-postcode': defaults.postcode || '00000',
		'billing-country': defaults.country || '',
		'billing-state': defaults.state || '',
	};

	function setNativeValue( element, value ) {
		if ( ! element || ! value || element.value === value ) {
			return;
		}

		var prototype = Object.getPrototypeOf( element );
		var descriptor = Object.getOwnPropertyDescriptor( prototype, 'value' );

		if ( descriptor && descriptor.set ) {
			descriptor.set.call( element, value );
		} else {
			element.value = value;
		}

		element.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		element.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	function fillHiddenAddressFields() {
		Object.keys( values ).forEach( function ( id ) {
			setNativeValue( document.getElementById( id ), values[ id ] );
		} );
	}

	fillHiddenAddressFields();
	document.addEventListener( 'DOMContentLoaded', fillHiddenAddressFields );
	window.addEventListener( 'load', fillHiddenAddressFields );

	if ( 'MutationObserver' in window ) {
		new MutationObserver( fillHiddenAddressFields ).observe( document.body, {
			childList: true,
			subtree: true,
		} );
	}
}() );

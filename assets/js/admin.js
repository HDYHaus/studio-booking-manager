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
	} );
}() );

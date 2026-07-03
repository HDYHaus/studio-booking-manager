<?php
/**
 * Booking access rule.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Access\Rules;

use StudioBookingManager\Access\Validators\AccessValidationResult;

defined( 'ABSPATH' ) || exit;

/**
 * Validates booking requirements.
 */
final class BookingRule {
	/**
	 * Validate the rule.
	 *
	 * @param object                 $access Access row.
	 * @param array<string,mixed>    $context Validation context.
	 * @param AccessValidationResult $result Validation result.
	 */
	public function validate( object $access, array $context, AccessValidationResult $result ): void {
		/**
		 * Filters whether an access record requires a booking for check-in.
		 *
		 * @param bool                $requires_booking Whether a booking is required.
		 * @param object              $access           Access row.
		 * @param array<string,mixed> $context          Validation context.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `sbm_` is the documented public API prefix for Studio Booking Manager.
		$requires_booking = (bool) apply_filters( 'sbm_access_requires_booking', false, $access, $context );

		if ( ! $requires_booking ) {
			return;
		}

		$booking_id = isset( $context['booking_id'] ) ? absint( $context['booking_id'] ) : 0;

		if ( $booking_id <= 0 ) {
			$result->add_error( 'booking_required', __( 'This access record requires a booking before check-in.', 'studio-booking-manager' ) );
		}
	}
}

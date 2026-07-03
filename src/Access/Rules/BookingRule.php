<?php
/**
 * Booking access rule.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Access\Rules;

use StudioBookingManager\Access\Validators\AccessValidationResult;
use StudioBookingManager\Bookings\BookingRepository;

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
		$requires_booking = $this->access_requires_booking( $access );
		$requires_booking = (bool) apply_filters( 'sbm_access_requires_booking', $requires_booking, $access, $context );

		$booking_id = isset( $context['booking_id'] ) ? absint( $context['booking_id'] ) : 0;

		if ( $booking_id <= 0 ) {
			if ( $requires_booking ) {
				$result->add_error( 'booking_required', __( 'This access record requires a booking before check-in.', 'studio-booking-manager' ) );
			}

			return;
		}

		$booking = ( new BookingRepository() )->find( $booking_id );

		if ( null === $booking || ! in_array( (string) $booking->status, array( 'pending', 'confirmed' ), true ) ) {
			$result->add_error( 'booking_invalid', __( 'The selected booking is not available for check-in.', 'studio-booking-manager' ) );
			return;
		}

		if ( (int) $booking->person_id !== (int) $access->person_id || (int) $booking->location_id !== (int) $access->location_id ) {
			$result->add_error( 'booking_mismatch', __( 'The selected booking does not match this access record.', 'studio-booking-manager' ) );
			return;
		}

		if ( isset( $booking->access_id ) && null !== $booking->access_id && (int) $booking->access_id > 0 && (int) $booking->access_id !== (int) $access->id ) {
			$result->add_error( 'booking_mismatch', __( 'The selected booking does not match this access record.', 'studio-booking-manager' ) );
		}
	}

	/**
	 * Determine whether the access metadata requires a booking.
	 *
	 * @param object $access Access row.
	 * @return bool
	 */
	private function access_requires_booking( object $access ): bool {
		if ( empty( $access->metadata ) ) {
			return false;
		}

		$metadata = json_decode( (string) $access->metadata, true );

		if ( ! is_array( $metadata ) ) {
			return false;
		}

		return ! empty( $metadata['booking_required'] );
	}
}

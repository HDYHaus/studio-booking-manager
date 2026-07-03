<?php
/**
 * Access validator.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Access\Validators;

use StudioBookingManager\Access\Rules\BookingRule;
use StudioBookingManager\Access\Rules\CreditRule;
use StudioBookingManager\Access\Rules\ExpiryRule;
use StudioBookingManager\Access\Rules\GuestRule;
use StudioBookingManager\Access\Rules\WeeklyLimitRule;

defined( 'ABSPATH' ) || exit;

/**
 * Validates whether an access record can be used.
 */
final class AccessValidator {
	/**
	 * Validate a check-in attempt.
	 *
	 * @param object|null          $access Access row.
	 * @param array<string,mixed>  $context Validation context.
	 * @return AccessValidationResult
	 */
	public function validate_check_in( ?object $access, array $context ): AccessValidationResult {
		$result = new AccessValidationResult();

		if ( null === $access ) {
			return $result->add_error( 'access_not_found', __( 'The selected access record could not be found.', 'studio-booking-manager' ) );
		}

		$person_id   = isset( $context['person_id'] ) ? absint( $context['person_id'] ) : 0;
		$location_id = isset( $context['location_id'] ) ? absint( $context['location_id'] ) : 0;

		if ( $person_id <= 0 || $location_id <= 0 ) {
			$result->add_error( 'missing_context', __( 'A person and location are required for check-in.', 'studio-booking-manager' ) );
		}

		if ( 'active' !== (string) $access->status ) {
			$result->add_error( 'inactive_access', __( 'The selected access record is not active.', 'studio-booking-manager' ) );
		}

		if ( $person_id > 0 && (int) $access->person_id !== $person_id ) {
			$result->add_error( 'person_mismatch', __( 'The selected access record does not belong to this person.', 'studio-booking-manager' ) );
		}

		if ( $location_id > 0 && (int) $access->location_id !== $location_id ) {
			$result->add_error( 'location_mismatch', __( 'The selected access record is not valid for this location.', 'studio-booking-manager' ) );
		}

		foreach ( $this->rules() as $rule ) {
			$rule->validate( $access, $context, $result );
		}

		/**
		 * Filters the result of a Studio Booking Manager access validation attempt.
		 *
		 * @param AccessValidationResult $result  Validation result.
		 * @param object                 $access  Access row.
		 * @param array<string,mixed>    $context Validation context.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `sbm_` is the documented public API prefix for Studio Booking Manager.
		$filtered = apply_filters( 'sbm_access_validation_result', $result, $access, $context );

		return $filtered instanceof AccessValidationResult ? $filtered : $result;
	}

	/**
	 * Get validation rules.
	 *
	 * @return array<int, object>
	 */
	private function rules(): array {
		return array(
			new ExpiryRule(),
			new CreditRule(),
			new GuestRule(),
			new WeeklyLimitRule(),
			new BookingRule(),
		);
	}
}

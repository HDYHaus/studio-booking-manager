<?php
/**
 * Guest access rule.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Access\Rules;

use StudioBookingManager\Access\Validators\AccessValidationResult;

defined( 'ABSPATH' ) || exit;

/**
 * Validates guest limits.
 */
final class GuestRule {
	/**
	 * Validate the rule.
	 *
	 * @param object                 $access Access row.
	 * @param array<string,mixed>    $context Validation context.
	 * @param AccessValidationResult $result Validation result.
	 */
	public function validate( object $access, array $context, AccessValidationResult $result ): void {
		$guest_count = isset( $context['guest_count'] ) ? absint( $context['guest_count'] ) : 0;
		$guest_limit = isset( $access->guest_limit ) ? absint( $access->guest_limit ) : 0;

		if ( $guest_count > $guest_limit ) {
			$result->add_error(
				'guest_limit_exceeded',
				sprintf(
					/* translators: 1: selected guest count, 2: allowed guest count. */
					__( 'This access record allows %2$d guests, but %1$d were selected.', 'studio-booking-manager' ),
					$guest_count,
					$guest_limit
				)
			);
		}
	}
}

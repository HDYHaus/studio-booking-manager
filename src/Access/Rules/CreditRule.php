<?php
/**
 * Credit access rule.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Access\Rules;

use StudioBookingManager\Access\Validators\AccessValidationResult;

defined( 'ABSPATH' ) || exit;

/**
 * Validates remaining finite credits.
 */
final class CreditRule {
	/**
	 * Validate the rule.
	 *
	 * @param object                 $access Access row.
	 * @param array<string,mixed>    $context Validation context.
	 * @param AccessValidationResult $result Validation result.
	 */
	public function validate( object $access, array $context, AccessValidationResult $result ): void {
		if ( 'membership' === (string) $access->access_type || null === $access->remaining_credits ) {
			return;
		}

		if ( absint( $access->remaining_credits ) <= 0 ) {
			$result->add_error( 'no_remaining_credits', __( 'This access record has no remaining credits.', 'studio-booking-manager' ) );
		}
	}
}

<?php
/**
 * Expiry access rule.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Access\Rules;

use StudioBookingManager\Access\Validators\AccessValidationResult;

defined( 'ABSPATH' ) || exit;

/**
 * Validates access start and expiry windows.
 */
final class ExpiryRule {
	/**
	 * Validate the rule.
	 *
	 * @param object                 $access Access row.
	 * @param array<string,mixed>    $context Validation context.
	 * @param AccessValidationResult $result Validation result.
	 */
	public function validate( object $access, array $context, AccessValidationResult $result ): void {
		$now = current_time( 'timestamp' );

		if ( ! empty( $access->starts_at ) && strtotime( (string) $access->starts_at ) > $now ) {
			$result->add_error( 'access_not_started', __( 'This access record is not active yet.', 'studio-booking-manager' ) );
		}

		if ( ! empty( $access->expires_at ) && strtotime( (string) $access->expires_at ) < $now ) {
			$result->add_error( 'access_expired', __( 'This access record has expired.', 'studio-booking-manager' ) );
		}
	}
}

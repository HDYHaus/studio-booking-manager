<?php
/**
 * Weekly limit access rule.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Access\Rules;

use StudioBookingManager\Access\Validators\AccessValidationResult;
use StudioBookingManager\Visits\VisitRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Validates weekly visit limits.
 */
final class WeeklyLimitRule {
	/**
	 * Visit repository.
	 *
	 * @var VisitRepository
	 */
	private VisitRepository $visits;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->visits = new VisitRepository();
	}

	/**
	 * Validate the rule.
	 *
	 * @param object                 $access Access row.
	 * @param array<string,mixed>    $context Validation context.
	 * @param AccessValidationResult $result Validation result.
	 */
	public function validate( object $access, array $context, AccessValidationResult $result ): void {
		$weekly_limit = isset( $access->weekly_limit ) ? absint( $access->weekly_limit ) : 0;

		if ( $weekly_limit <= 0 ) {
			return;
		}

		$week_start = wp_date( 'Y-m-d 00:00:00', strtotime( 'monday this week', current_time( 'timestamp' ) ) );
		$used       = $this->visits->count_completed_for_access_since( (int) $access->id, $week_start );

		if ( $used >= $weekly_limit ) {
			$result->add_error(
				'weekly_limit_reached',
				sprintf(
					/* translators: %d: weekly visit limit. */
					__( 'This access record has reached its weekly limit of %d visits.', 'studio-booking-manager' ),
					$weekly_limit
				)
			);
		}
	}
}

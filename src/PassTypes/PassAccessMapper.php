<?php
/**
 * Maps pass types to access records.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\PassTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Builds issued access snapshots from pass type templates.
 */
final class PassAccessMapper {
	/**
	 * Map a pass type into access data.
	 *
	 * @param PassType             $pass Pass type.
	 * @param int                  $person_id Person ID.
	 * @param int                  $location_id Location ID.
	 * @param array<string,mixed>  $overrides Additional access fields.
	 * @return array<string,mixed>
	 */
	public function map( PassType $pass, int $person_id, int $location_id, array $overrides = array() ): array {
		$access_type = $this->map_access_type( $pass->behaviour );
		$now         = current_time( 'mysql' );

		$data = array(
			'person_id'         => $person_id,
			'location_id'       => $location_id,
			'access_type'       => $access_type,
			'status'            => 'active',
			'guest_limit'       => $pass->guest_allowance,
			'total_credits'     => null,
			'remaining_credits' => null,
			'weekly_limit'      => $pass->maximum_visits_per_week,
			'starts_at'         => $now,
			'expires_at'        => $this->calculate_expiry( $pass->valid_for ),
			'metadata'          => wp_json_encode(
				array(
					'pass_type_id'     => $pass->id,
					'booking_required' => (bool) $pass->booking_required,
					'booking_duration_minutes' => $pass->booking_duration_minutes,
				)
			),
		);

		if ( 'one_time' === $pass->behaviour ) {
			$data['total_credits']     = 1;
			$data['remaining_credits'] = 1;
			$data['weekly_limit']      = null;
		}

		if ( 'multiple_visits' === $pass->behaviour ) {
			$data['total_credits']     = $pass->number_of_visits;
			$data['remaining_credits'] = $pass->number_of_visits;
		}

		return array_merge( $data, $overrides );
	}

	/**
	 * Map pass behaviour to access type.
	 *
	 * @param string $behaviour Pass behaviour.
	 * @return string
	 */
	private function map_access_type( string $behaviour ): string {
		switch ( $behaviour ) {
			case 'membership':
				return 'membership';
			case 'multiple_visits':
				return 'visit_pass';
			case 'one_time':
			default:
				return 'single_visit';
		}
	}

	/**
	 * Calculate pass expiry timestamp.
	 *
	 * @param int|null $valid_for Days valid.
	 * @return string|null
	 */
	private function calculate_expiry( ?int $valid_for ): ?string {
		if ( null === $valid_for || $valid_for <= 0 ) {
			return null;
		}

		$timestamp = strtotime( '+' . absint( $valid_for ) . ' days', current_time( 'timestamp' ) );

		if ( false === $timestamp ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}

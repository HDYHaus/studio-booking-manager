<?php
/**
 * Visit service.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Visits;

use StudioBookingManager\Access\AccessService;
use StudioBookingManager\Locations\LocationService;
use StudioBookingManager\People\PersonService;

defined( 'ABSPATH' ) || exit;

/**
 * Business logic for visit records.
 */
final class VisitService {
	/**
	 * Repository.
	 *
	 * @var VisitRepository
	 */
	private VisitRepository $repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new VisitRepository();
	}

	/**
	 * Get all visits.
	 *
	 * @return array<int, object>
	 */
	public function all(): array {
		return $this->repository->all();
	}

	/**
	 * Find a visit.
	 *
	 * @param int $id Visit ID.
	 * @return object|null
	 */
	public function find( int $id ): ?object {
		return $this->repository->find( $id );
	}

	/**
	 * Save a visit.
	 *
	 * @param array<string, mixed> $data Visit data.
	 * @return int
	 */
	public function save( array $data ): int {
		if ( ! $this->is_valid_payload( $data ) ) {
			return 0;
		}

		$id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;

		if ( $id > 0 ) {
			$updated = $this->repository->update( $id, $data );
			return $updated ? $id : 0;
		}

		$saved_id = $this->repository->create( $data );

		if ( $saved_id > 0 ) {
			$visit = $this->find( $saved_id );
			if ( null !== $visit ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `sbm_` is the documented public API prefix for Studio Booking Manager.
				do_action( 'sbm_visit_created', $visit );
			}
		}

		return $saved_id;
	}

	/**
	 * Archive a visit.
	 *
	 * @param int $id Visit ID.
	 * @return bool
	 */
	public function archive( int $id ): bool {
		return $this->repository->archive( $id );
	}


	/**
	 * Get currently checked-in visits.
	 *
	 * @return array<int, object>
	 */
	public function current(): array {
		return $this->repository->current();
	}

	/**
	 * Check a person in.
	 *
	 * @param array<string, mixed> $data Check-in data.
	 * @return int
	 */
	public function check_in( array $data ): int {
		$person_id   = isset( $data['person_id'] ) ? absint( $data['person_id'] ) : 0;
		$location_id = isset( $data['location_id'] ) ? absint( $data['location_id'] ) : 0;
		$access_id   = isset( $data['access_id'] ) ? absint( $data['access_id'] ) : 0;
		$guest_count = isset( $data['guest_count'] ) ? absint( $data['guest_count'] ) : 0;

		if ( $person_id <= 0 || $location_id <= 0 || $access_id <= 0 ) {
			return 0;
		}

		$current = $this->repository->find_current_for_person( $person_id, $location_id );

		if ( null !== $current ) {
			return (int) $current->id;
		}

		$access_service = new AccessService();
		$access         = $this->get_valid_access_for_check_in( $access_service, $access_id, $person_id, $location_id, $guest_count );

		if ( null === $access ) {
			return 0;
		}

		$data['status']         = 'checked_in';
		$data['checked_in_at']  = current_time( 'mysql' );
		$data['checked_in_by']  = get_current_user_id();
		$data['scheduled_start'] = isset( $data['scheduled_start'] ) ? $data['scheduled_start'] : current_time( 'mysql' );
		$data['visit_date']     = current_time( 'Y-m-d' );

		$visit_id = $this->save( $data );

		if ( $visit_id > 0 ) {
			$visit = $this->find( $visit_id );
			if ( null !== $visit ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `sbm_` is the documented public API prefix for Studio Booking Manager.
				do_action( 'sbm_visit_checked_in', $visit );
			}

			$this->consume_access_credit( $access_service, $access );
		}

		return $visit_id;
	}

	/**
	 * Check a visit out.
	 *
	 * @param int $visit_id Visit ID.
	 * @return bool
	 */
	public function check_out( int $visit_id ): bool {
		$visit = $this->find( $visit_id );

		if ( null === $visit || 'checked_in' !== (string) $visit->status ) {
			return false;
		}

		$checked_out = $this->repository->check_out( $visit_id, get_current_user_id() );

		if ( $checked_out ) {
			$updated_visit = $this->find( $visit_id );
			if ( null !== $updated_visit ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `sbm_` is the documented public API prefix for Studio Booking Manager.
				do_action( 'sbm_visit_checked_out', $updated_visit );
			}
		}

		return $checked_out;
	}


	/**
	 * Get visit statuses.
	 *
	 * @return array<string,string>
	 */
	public function statuses(): array {
		return array(
			'expected'    => __( 'Expected', 'studio-booking-manager' ),
			'checked_in'  => __( 'Checked In', 'studio-booking-manager' ),
			'checked_out' => __( 'Checked Out', 'studio-booking-manager' ),
			'cancelled'   => __( 'Cancelled', 'studio-booking-manager' ),
			'no_show'     => __( 'No Show', 'studio-booking-manager' ),
		);
	}

	/**
	 * Get check-in methods.
	 *
	 * @return array<string,string>
	 */
	public function checkin_methods(): array {
		$methods = array(
			'reception'       => __( 'Reception', 'studio-booking-manager' ),
			'qr_code'         => __( 'QR Code', 'studio-booking-manager' ),
			'manual_override' => __( 'Manual Override', 'studio-booking-manager' ),
			'api'             => __( 'API', 'studio-booking-manager' ),
			'import'          => __( 'Import', 'studio-booking-manager' ),
		);

		/**
		 * Filters available check-in methods.
		 *
		 * @param array<string,string> $methods Check-in methods.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `sbm_` is the documented public API prefix for Studio Booking Manager.
		return (array) apply_filters( 'sbm_check_in_methods', $methods );
	}

	/**
	 * Validate payload references.
	 *
	 * @param array<string, mixed> $data Visit data.
	 * @return bool
	 */
	private function is_valid_payload( array $data ): bool {
		$person_id   = isset( $data['person_id'] ) ? absint( $data['person_id'] ) : 0;
		$location_id = isset( $data['location_id'] ) ? absint( $data['location_id'] ) : 0;
		$access_id   = isset( $data['access_id'] ) ? absint( $data['access_id'] ) : 0;

		if ( $person_id <= 0 || $location_id <= 0 || $access_id <= 0 ) {
			return false;
		}

		$person   = ( new PersonService() )->find( $person_id );
		$location = ( new LocationService() )->find( $location_id );
		$access   = ( new AccessService() )->find( $access_id );

		if ( null === $person || null === $location || null === $access ) {
			return false;
		}

		return 'active' === (string) $access->status
			&& (int) $access->person_id === $person_id
			&& (int) $access->location_id === $location_id;
	}

	/**
	 * Get an access record when it is valid for check-in.
	 *
	 * @param AccessService $access_service Access service.
	 * @param int           $access_id Access ID.
	 * @param int           $person_id Person ID.
	 * @param int           $location_id Location ID.
	 * @param int           $guest_count Guest count.
	 * @return object|null
	 */
	private function get_valid_access_for_check_in( AccessService $access_service, int $access_id, int $person_id, int $location_id, int $guest_count ): ?object {
		$access = $access_service->find( $access_id );

		if ( null === $access || 'active' !== (string) $access->status ) {
			return null;
		}

		if ( (int) $access->person_id !== $person_id || (int) $access->location_id !== $location_id ) {
			return null;
		}

		$now = current_time( 'mysql' );

		if ( ! empty( $access->starts_at ) && $now < (string) $access->starts_at ) {
			return null;
		}

		if ( ! empty( $access->expires_at ) && $now > (string) $access->expires_at ) {
			return null;
		}

		if ( $guest_count > absint( $access->guest_limit ) ) {
			return null;
		}

		if ( 'membership' !== (string) $access->access_type && null !== $access->remaining_credits && absint( $access->remaining_credits ) <= 0 ) {
			return null;
		}

		if ( null !== $access->weekly_limit && absint( $access->weekly_limit ) > 0 ) {
			$week_start = wp_date( 'Y-m-d 00:00:00', strtotime( 'monday this week', current_time( 'timestamp' ) ) );

			if ( $this->repository->count_completed_for_access_since( $access_id, $week_start ) >= absint( $access->weekly_limit ) ) {
				return null;
			}
		}

		return $access;
	}

	/**
	 * Consume one credit after a successful check-in.
	 *
	 * @param AccessService $access_service Access service.
	 * @param object        $access Access row.
	 */
	private function consume_access_credit( AccessService $access_service, object $access ): void {
		if ( 'membership' === (string) $access->access_type || null === $access->remaining_credits ) {
			return;
		}

		$access_service->update_remaining_credits( (int) $access->id, absint( $access->remaining_credits ) - 1 );
	}
}

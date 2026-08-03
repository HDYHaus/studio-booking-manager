<?php
/**
 * Booking service.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Bookings;

use StudioBookingManager\Access\AccessService;
use StudioBookingManager\Locations\LocationService;
use StudioBookingManager\People\PersonService;

defined( 'ABSPATH' ) || exit;

/**
 * Business logic for booking records.
 */
final class BookingService {
	/**
	 * Repository.
	 *
	 * @var BookingRepository
	 */
	private BookingRepository $repository;

	/**
	 * Last save error.
	 *
	 * @var string
	 */
	private string $last_error = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new BookingRepository();
	}

	/**
	 * Get bookings.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @return array<int, object>
	 */
	public function all( array $filters = array() ): array {
		return $this->repository->all( $filters );
	}

	/**
	 * Find a booking.
	 *
	 * @param int $id Booking ID.
	 * @return object|null
	 */
	public function find( int $id ): ?object {
		return $this->repository->find( $id );
	}

	/**
	 * Get upcoming bookings for a person.
	 *
	 * @param int $person_id Person ID.
	 * @return array<int, object>
	 */
	public function upcoming_for_person( int $person_id ): array {
		return $this->repository->upcoming_for_person( $person_id );
	}

	/**
	 * Save a booking.
	 *
	 * @param array<string,mixed> $data Booking data.
	 * @return int
	 */
	public function save( array $data ): int {
		$this->last_error = '';
		$id               = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$skip_conflict    = ! empty( $data['skip_conflict_check'] );
		$data             = $this->normalize_payload( $data );

		if ( ! $this->is_valid_payload( $data ) ) {
			return 0;
		}

		if ( ! $skip_conflict && $this->repository->has_conflict( (int) $data['location_id'], (string) $data['starts_at'], (string) $data['ends_at'], $id ) ) {
			$this->last_error = 'conflict';
			return 0;
		}

		if ( $id > 0 ) {
			$updated = $this->repository->update( $id, $data );
			if ( ! $updated ) {
				return 0;
			}

			$this->fire_booking_action( 'sbm_booking_updated', $id );

			return $id;
		}

		$booking_id = $this->repository->create( $data );

		if ( $booking_id > 0 ) {
			$this->fire_booking_action( 'sbm_booking_created', $booking_id );
		} else {
			$this->last_error = '' !== $this->repository->last_error() ? 'database: ' . $this->repository->last_error() : 'database';
		}

		return $booking_id;
	}

	/**
	 * Archive a booking.
	 *
	 * @param int $id Booking ID.
	 * @return bool
	 */
	public function archive( int $id ): bool {
		$archived = $this->repository->archive( $id );

		if ( $archived ) {
			$this->fire_booking_action( 'sbm_booking_archived', $id );
		}

		return $archived;
	}

	/**
	 * Mark a booking as completed.
	 *
	 * @param int $id Booking ID.
	 * @return bool
	 */
	public function complete( int $id ): bool {
		$completed = $this->repository->update_status( $id, 'completed' );

		if ( $completed ) {
			$this->fire_booking_action( 'sbm_booking_completed', $id );
		}

		return $completed;
	}

	/**
	 * Get the last save error code.
	 *
	 * @return string
	 */
	public function last_error(): string {
		return $this->last_error;
	}

	/**
	 * Booking statuses.
	 *
	 * @return array<string,string>
	 */
	public function statuses(): array {
		return array(
			'pending'   => __( 'Pending', 'studio-booking-manager' ),
			'confirmed' => __( 'Confirmed', 'studio-booking-manager' ),
			'cancelled' => __( 'Cancelled', 'studio-booking-manager' ),
			'completed' => __( 'Completed', 'studio-booking-manager' ),
			'no_show'   => __( 'No Show', 'studio-booking-manager' ),
		);
	}

	/**
	 * Normalize booking payload.
	 *
	 * @param array<string,mixed> $data Raw data.
	 * @return array<string,mixed>
	 */
	private function normalize_payload( array $data ): array {
		$data['starts_at'] = isset( $data['starts_at'] ) ? $this->normalize_datetime( (string) $data['starts_at'] ) : '';
		$data['ends_at']   = isset( $data['ends_at'] ) ? $this->normalize_datetime( (string) $data['ends_at'] ) : '';

		return $data;
	}

	/**
	 * Validate booking payload.
	 *
	 * @param array<string,mixed> $data Booking data.
	 * @return bool
	 */
	private function is_valid_payload( array $data ): bool {
		$person_id   = isset( $data['person_id'] ) ? absint( $data['person_id'] ) : 0;
		$location_id = isset( $data['location_id'] ) ? absint( $data['location_id'] ) : 0;
		$access_id   = isset( $data['access_id'] ) ? absint( $data['access_id'] ) : 0;
		$starts_at   = isset( $data['starts_at'] ) ? (string) $data['starts_at'] : '';
		$ends_at     = isset( $data['ends_at'] ) ? (string) $data['ends_at'] : '';

		if ( $person_id <= 0 || $location_id <= 0 || '' === $starts_at || '' === $ends_at ) {
			$this->last_error = 'missing';
			return false;
		}

		if ( strtotime( $starts_at ) >= strtotime( $ends_at ) ) {
			$this->last_error = 'time';
			return false;
		}

		if ( null === ( new PersonService() )->find( $person_id ) || null === ( new LocationService() )->find( $location_id ) ) {
			$this->last_error = 'missing';
			return false;
		}

		if ( $access_id > 0 ) {
			$access = ( new AccessService() )->find( $access_id );
			if ( null === $access || (int) $access->person_id !== $person_id || (int) $access->location_id !== $location_id ) {
				$this->last_error = 'access';
				return false;
			}
		}

		return true;
	}

	/**
	 * Normalize a datetime value.
	 *
	 * @param string $value Raw datetime value.
	 * @return string
	 */
	private function normalize_datetime( string $value ): string {
		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value );

		return false === $timestamp ? '' : gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Fire a booking lifecycle action.
	 *
	 * @param string $action Action name.
	 * @param int    $booking_id Booking ID.
	 */
	private function fire_booking_action( string $action, int $booking_id ): void {
		$booking = $this->find( $booking_id );

		if ( null === $booking ) {
			return;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Booking lifecycle actions are internal plugin-owned `sbm_` action names chosen by callers in this service.
		do_action( $action, $booking );
	}
}

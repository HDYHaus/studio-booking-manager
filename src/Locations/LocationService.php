<?php
/**
 * Location service.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Locations;

defined( 'ABSPATH' ) || exit;

/**
 * Business logic for locations.
 */
final class LocationService {
	/**
	 * Repository.
	 *
	 * @var LocationRepository
	 */
	private LocationRepository $repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new LocationRepository();
	}

	/**
	 * Get all locations.
	 *
	 * @return array<int, object>
	 */
	public function all(): array {
		return $this->repository->all();
	}

	/**
	 * Find a location.
	 *
	 * @param int $id Location ID.
	 * @return object|null
	 */
	public function find( int $id ): ?object {
		return $this->repository->find( $id );
	}

	/**
	 * Save a location.
	 *
	 * @param array<string, mixed> $data Location data.
	 * @return int
	 */
	public function save( array $data ): int {
		$id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;

		if ( $id > 0 ) {
			$updated = $this->repository->update( $id, $data );
			return $updated ? $id : 0;
		}

		return $this->repository->create( $data );
	}

	/**
	 * Archive a location.
	 *
	 * @param int $id Location ID.
	 * @return bool
	 */
	public function archive( int $id ): bool {
		return $this->repository->archive( $id );
	}

	/**
	 * Set default location.
	 *
	 * @param int $id Location ID.
	 * @return bool
	 */
	public function set_default( int $id ): bool {
		return $this->repository->set_default( $id );
	}
}

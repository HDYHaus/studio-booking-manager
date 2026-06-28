<?php
/**
 * Pass type service.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\PassTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Business logic for pass types.
 */
final class PassTypeService {
	/**
	 * Repository.
	 *
	 * @var PassTypeRepository
	 */
	private PassTypeRepository $repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new PassTypeRepository();
	}

	/**
	 * Get all pass types.
	 *
	 * @return array<int, PassType>
	 */
	public function all(): array {
		return $this->repository->all();
	}

	/**
	 * Find a pass type.
	 *
	 * @param int $id Pass type ID.
	 * @return PassType|null
	 */
	public function find( int $id ): ?PassType {
		return $this->repository->find( $id );
	}

	/**
	 * Save a pass type.
	 *
	 * @param array<string,mixed> $data Pass type data.
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
	 * Archive a pass type.
	 *
	 * @param int $id Pass type ID.
	 * @return bool
	 */
	public function archive( int $id ): bool {
		return $this->repository->archive( $id );
	}

	/**
	 * Available behaviours.
	 *
	 * @return array<string,string>
	 */
	public function behaviours(): array {
		return array(
			'one_time'       => __( 'One-time', 'studio-booking-manager' ),
			'multiple_visits' => __( 'Multiple Visits', 'studio-booking-manager' ),
			'membership'     => __( 'Membership', 'studio-booking-manager' ),
		);
	}

	/**
	 * Available statuses.
	 *
	 * @return array<string,string>
	 */
	public function statuses(): array {
		return array(
			'active'   => __( 'Active', 'studio-booking-manager' ),
			'inactive' => __( 'Inactive', 'studio-booking-manager' ),
			'archived' => __( 'Archived', 'studio-booking-manager' ),
		);
	}
}

<?php
/**
 * Access service.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Access;

defined( 'ABSPATH' ) || exit;

/**
 * Business logic for access records.
 */
final class AccessService {
	/**
	 * Repository.
	 *
	 * @var AccessRepository
	 */
	private AccessRepository $repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new AccessRepository();
	}

	/**
	 * Get all access records.
	 *
	 * @return array<int, object>
	 */
	public function all(): array {
		return $this->repository->all();
	}

	/**
	 * Find an access record.
	 *
	 * @param int $id Access ID.
	 * @return object|null
	 */
	public function find( int $id ): ?object {
		return $this->repository->find( $id );
	}

	/**
	 * Find an existing active access record with matching issue criteria.
	 *
	 * @param int $person_id Person ID.
	 * @param int $location_id Location ID.
	 * @param string $access_type Access type.
	 * @param int|null $total_credits Total credits.
	 * @param int|null $weekly_limit Weekly limit.
	 * @param int $guest_limit Guest limit.
	 * @param string|null $expires_at Expiry timestamp.
	 * @return object|null
	 */
	public function find_duplicate_active( int $person_id, int $location_id, string $access_type, ?int $total_credits, ?int $weekly_limit, int $guest_limit, ?string $expires_at ): ?object {
		return $this->repository->find_duplicate_active( $person_id, $location_id, $access_type, $total_credits, $weekly_limit, $guest_limit, $expires_at );
	}

	/**
	 * Save an access record.
	 *
	 * @param array<string, mixed> $data Access data.
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
	 * Archive an access record.
	 *
	 * @param int $id Access ID.
	 * @return bool
	 */
	public function archive( int $id ): bool {
		return $this->repository->archive( $id );
	}

	/**
	 * Get available access types.
	 *
	 * @return array<string,string>
	 */
	public function access_types(): array {
		return array(
			'single_visit' => __( 'Single Visit', 'studio-booking-manager' ),
			'visit_pass'   => __( 'Visit Pass', 'studio-booking-manager' ),
			'membership'   => __( 'Membership', 'studio-booking-manager' ),
		);
	}

	/**
	 * Get available statuses.
	 *
	 * @return array<string,string>
	 */
	public function statuses(): array {
		return array(
			'active'    => __( 'Active', 'studio-booking-manager' ),
			'pending'   => __( 'Pending', 'studio-booking-manager' ),
			'expired'   => __( 'Expired', 'studio-booking-manager' ),
			'suspended' => __( 'Suspended', 'studio-booking-manager' ),
		);
	}
}

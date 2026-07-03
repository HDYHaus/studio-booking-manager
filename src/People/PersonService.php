<?php
/**
 * Person service.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\People;

defined( 'ABSPATH' ) || exit;

/**
 * Business logic for people.
 */
final class PersonService {
	/**
	 * Repository.
	 *
	 * @var PersonRepository
	 */
	private PersonRepository $repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new PersonRepository();
	}

	/**
	 * Get all people.
	 *
	 * @param string $search Optional search term.
	 * @return array<int, object>
	 */
	public function all( string $search = '' ): array {
		return $this->repository->all( $search );
	}

	/**
	 * Find a person.
	 *
	 * @param int $id Person ID.
	 * @return object|null
	 */
	public function find( int $id ): ?object {
		return $this->repository->find( $id );
	}

	/**
	 * Find a person by email.
	 *
	 * @param string $email Email address.
	 * @return object|null
	 */
	public function find_by_email( string $email ): ?object {
		return $this->repository->find_by_email( $email );
	}

	/**
	 * Find a person by QR token.
	 *
	 * @param string $token QR identity token.
	 * @return object|null
	 */
	public function find_by_qr_token( string $token ): ?object {
		return $this->repository->find_by_qr_token( $token );
	}

	/**
	 * Save a person.
	 *
	 * @param array<string, mixed> $data Person data.
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
	 * Archive a person.
	 *
	 * @param int $id Person ID.
	 * @return bool
	 */
	public function archive( int $id ): bool {
		return $this->repository->archive( $id );
	}

	/**
	 * Regenerate a person's QR token.
	 *
	 * @param int $id Person ID.
	 * @return string
	 */
	public function regenerate_qr_token( int $id ): string {
		return $this->repository->regenerate_qr_token( $id );
	}
}

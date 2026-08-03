<?php
/**
 * RSVP service.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\RSVP;

defined( 'ABSPATH' ) || exit;

/**
 * Business logic for public post RSVPs.
 */
final class RsvpService {
	/**
	 * Repository.
	 *
	 * @var RsvpRepository
	 */
	private RsvpRepository $repository;

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
		$this->repository = new RsvpRepository();
	}

	/**
	 * Save an RSVP.
	 *
	 * @param array<string,mixed> $data RSVP data.
	 * @return int
	 */
	public function save( array $data ): int {
		$this->last_error = '';
		$data             = $this->normalize_payload( $data );

		if ( ! $this->is_valid_payload( $data ) ) {
			return 0;
		}

		$id = $this->repository->save( $data );

		if ( $id <= 0 ) {
			$this->last_error = '' !== $this->repository->last_error() ? 'database: ' . $this->repository->last_error() : 'database';
		}

		return $id;
	}

	/**
	 * Get RSVPs for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, object>
	 */
	public function for_post( int $post_id ): array {
		return $this->repository->for_post( $post_id );
	}

	/**
	 * Get grouped RSVP totals by post.
	 *
	 * @return array<int, object>
	 */
	public function summaries(): array {
		return $this->repository->summaries();
	}

	/**
	 * Get totals for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{rsvp_count:int,guest_count:int,headcount:int}
	 */
	public function totals_for_post( int $post_id ): array {
		return $this->repository->totals_for_post( $post_id );
	}

	/**
	 * Get the last save error code.
	 */
	public function last_error(): string {
		return $this->last_error;
	}

	/**
	 * Normalize RSVP payload.
	 *
	 * @param array<string,mixed> $data Raw data.
	 * @return array<string,mixed>
	 */
	private function normalize_payload( array $data ): array {
		$bringing_guest = ! empty( $data['bringing_guest'] );
		$guest_count    = isset( $data['guest_count'] ) ? absint( $data['guest_count'] ) : 0;

		if ( $bringing_guest && $guest_count <= 0 ) {
			$guest_count = 1;
		}

		if ( ! $bringing_guest ) {
			$guest_count          = 0;
			$data['guest_names'] = '';
		}

		$data['guest_count'] = min( 20, $guest_count );
		$data['status']      = 'attending';

		return $data;
	}

	/**
	 * Validate RSVP payload.
	 *
	 * @param array<string,mixed> $data RSVP data.
	 */
	private function is_valid_payload( array $data ): bool {
		$post_id = isset( $data['post_id'] ) ? absint( $data['post_id'] ) : 0;
		$name    = isset( $data['attendee_name'] ) ? trim( (string) $data['attendee_name'] ) : '';
		$email   = isset( $data['attendee_email'] ) ? sanitize_email( (string) $data['attendee_email'] ) : '';

		if ( $post_id <= 0 || null === get_post( $post_id ) ) {
			$this->last_error = 'event';
			return false;
		}

		if ( '' === $name ) {
			$this->last_error = 'name';
			return false;
		}

		if ( '' === $email || ! is_email( $email ) ) {
			$this->last_error = 'email';
			return false;
		}

		return true;
	}
}

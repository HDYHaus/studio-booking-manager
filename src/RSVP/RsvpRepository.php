<?php
/**
 * RSVP repository.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\RSVP;

use StudioBookingManager\Database\Tables;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Handles persistence for public post RSVPs.
 */
final class RsvpRepository {
	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * RSVPs table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Last database error.
	 *
	 * @var string
	 */
	private string $last_error = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb  = $wpdb;
		$this->table = Tables::get( 'rsvps' );
	}

	/**
	 * Save an RSVP, updating an existing email for the same post.
	 *
	 * @param array<string,mixed> $data RSVP data.
	 * @return array{id:int,operation:string}
	 */
	public function save( array $data ): array {
		$this->last_error = '';
		$data             = $this->prepare_for_storage( $data );

		if ( (int) $data['post_id'] <= 0 || '' === $data['attendee_name'] || '' === $data['attendee_email'] ) {
			return array(
				'id'        => 0,
				'operation' => '',
			);
		}

		$existing = $this->find_by_post_email( (int) $data['post_id'], (string) $data['attendee_email'] );
		$now      = current_time( 'mysql' );

		if ( $existing instanceof \stdClass ) {
			$data['updated_at'] = $now;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom operational table update.
			$updated = $this->wpdb->update( $this->table, $data, array( 'id' => (int) $existing->id ), $this->formats( $data ), array( '%d' ) );

			if ( false === $updated ) {
				$this->last_error = (string) $this->wpdb->last_error;
				return array(
					'id'        => 0,
					'operation' => '',
				);
			}

			return array(
				'id'        => (int) $existing->id,
				'operation' => 'updated',
			);
		}

		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom operational table insert.
		$inserted = $this->wpdb->insert( $this->table, $data, $this->formats( $data ) );

		if ( false === $inserted ) {
			$this->last_error = (string) $this->wpdb->last_error;
			return array(
				'id'        => 0,
				'operation' => '',
			);
		}

		return array(
			'id'        => (int) $this->wpdb->insert_id,
			'operation' => 'created',
		);
	}

	/**
	 * Find an RSVP by ID.
	 *
	 * @param int $id RSVP ID.
	 * @return object|null
	 */
	public function find( int $id ): ?object {
		if ( $id <= 0 ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from the trusted Tables registry.
		$query = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE id = %d LIMIT 1", $id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom operational table query with prepared values.
		$record = $this->wpdb->get_row( $query );

		return $record instanceof \stdClass ? $record : null;
	}

	/**
	 * Find an RSVP by event post and attendee email.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $email Attendee email.
	 * @return object|null
	 */
	public function find_by_post_email( int $post_id, string $email ): ?object {
		$email = sanitize_email( $email );

		if ( $post_id <= 0 || '' === $email ) {
			return null;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from the trusted Tables registry.
		$query = $this->wpdb->prepare(
			"SELECT * FROM `{$this->table}` WHERE post_id = %d AND attendee_email = %s LIMIT 1",
			$post_id,
			$email
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom operational table query with prepared values.
		$record = $this->wpdb->get_row( $query );

		return $record instanceof \stdClass ? $record : null;
	}

	/**
	 * Get RSVP records for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, object>
	 */
	public function for_post( int $post_id ): array {
		if ( $post_id <= 0 ) {
			return array();
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from the trusted Tables registry.
		$query = $this->wpdb->prepare(
			"SELECT * FROM `{$this->table}` WHERE post_id = %d AND status <> %s ORDER BY updated_at DESC, id DESC",
			$post_id,
			'archived'
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom operational table query with prepared values.
		$records = $this->wpdb->get_results( $query );

		return is_array( $records ) ? $records : array();
	}

	/**
	 * Get grouped RSVP totals by post.
	 *
	 * @return array<int, object>
	 */
	public function summaries(): array {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from the trusted Tables registry.
		$query = $this->wpdb->prepare(
			"SELECT post_id, COUNT(id) AS rsvp_count, COALESCE(SUM(guest_count), 0) AS guest_count, COUNT(id) + COALESCE(SUM(guest_count), 0) AS headcount, MAX(updated_at) AS last_updated
			FROM `{$this->table}`
			WHERE status = %s
			GROUP BY post_id
			ORDER BY last_updated DESC, post_id DESC",
			'attending'
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom operational table aggregate query.
		$records = $this->wpdb->get_results( $query );

		return is_array( $records ) ? $records : array();
	}

	/**
	 * Get totals for a single post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{rsvp_count:int,guest_count:int,headcount:int}
	 */
	public function totals_for_post( int $post_id ): array {
		if ( $post_id <= 0 ) {
			return array(
				'rsvp_count'  => 0,
				'guest_count' => 0,
				'headcount'   => 0,
			);
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from the trusted Tables registry.
		$query = $this->wpdb->prepare(
			"SELECT COUNT(id) AS rsvp_count, COALESCE(SUM(guest_count), 0) AS guest_count
			FROM `{$this->table}`
			WHERE post_id = %d AND status = %s",
			$post_id,
			'attending'
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom operational table aggregate query.
		$row = $this->wpdb->get_row( $query );

		$rsvp_count  = $row instanceof \stdClass ? absint( $row->rsvp_count ) : 0;
		$guest_count = $row instanceof \stdClass ? absint( $row->guest_count ) : 0;

		return array(
			'rsvp_count'  => $rsvp_count,
			'guest_count' => $guest_count,
			'headcount'   => $rsvp_count + $guest_count,
		);
	}

	/**
	 * Get the last database error.
	 */
	public function last_error(): string {
		return $this->last_error;
	}

	/**
	 * Prepare data for storage.
	 *
	 * @param array<string,mixed> $data Raw data.
	 * @return array<string,mixed>
	 */
	private function prepare_for_storage( array $data ): array {
		$status = isset( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : 'attending';
		if ( ! in_array( $status, array( 'attending', 'waitlisted', 'cancelled', 'maybe', 'archived' ), true ) ) {
			$status = 'attending';
		}

		return array(
			'post_id'          => isset( $data['post_id'] ) ? absint( $data['post_id'] ) : 0,
			'attendee_name'    => isset( $data['attendee_name'] ) ? sanitize_text_field( (string) $data['attendee_name'] ) : '',
			'attendee_email'   => isset( $data['attendee_email'] ) ? sanitize_email( (string) $data['attendee_email'] ) : '',
			'guest_count'      => isset( $data['guest_count'] ) ? min( 6, absint( $data['guest_count'] ) ) : 0,
			'guest_names'      => isset( $data['guest_names'] ) ? sanitize_textarea_field( (string) $data['guest_names'] ) : '',
			'status'           => $status,
			'notes'            => isset( $data['notes'] ) ? sanitize_textarea_field( (string) $data['notes'] ) : '',
			'ip_hash'          => isset( $data['ip_hash'] ) ? sanitize_text_field( (string) $data['ip_hash'] ) : '',
			'user_agent_hash'  => isset( $data['user_agent_hash'] ) ? sanitize_text_field( (string) $data['user_agent_hash'] ) : '',
		);
	}

	/**
	 * Build formats for a row.
	 *
	 * @param array<string,mixed> $data Data.
	 * @return array<int,string>
	 */
	private function formats( array $data ): array {
		$formats = array();

		foreach ( array_keys( $data ) as $key ) {
			$formats[] = in_array( $key, array( 'post_id', 'guest_count' ), true ) ? '%d' : '%s';
		}

		return $formats;
	}
}

<?php
/**
 * Person repository.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\People;

use StudioBookingManager\Database\Tables;

use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Handles persistence for people.
 */
final class PersonRepository {
	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb  = $wpdb;
		$this->table = Tables::get( 'people' );
	}

	/**
	 * Get all non-archived people.
	 *
	 * @param string $search Optional search term.
	 * @return array<int, object>
	 */
	public function all( string $search = '' ): array {
		if ( '' !== $search ) {
			$like = '%' . $this->wpdb->esc_like( $search ) . '%';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from the trusted Tables registry; search values are prepared.
			$query = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE status <> 'archived' AND (display_name LIKE %s OR email LIKE %s OR phone LIKE %s) ORDER BY display_name ASC, id DESC", $like, $like, $like );
		} else {
			$query = "SELECT * FROM `{$this->table}` WHERE status <> 'archived' ORDER BY display_name ASC, id DESC";
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom operational table query using a trusted table name from the Tables registry; user input is prepared when present.
		$people = $this->wpdb->get_results( $query );

		return is_array( $people ) ? $people : array();
	}

	/**
	 * Find a person by ID.
	 *
	 * @param int $id Person ID.
	 * @return object|null
	 */
	public function find( int $id ): ?object {
		if ( $id <= 0 ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from the trusted Tables registry; the ID is prepared.
		$query = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE id = %d LIMIT 1", $id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom operational table query using a trusted table name from the Tables registry; the ID is prepared above.
		$person = $this->wpdb->get_row( $query );

		return $person instanceof \stdClass ? $person : null;
	}

	/**
	 * Find a person by email.
	 *
	 * @param string $email Email address.
	 * @return object|null
	 */
	public function find_by_email( string $email ): ?object {
		$email = sanitize_email( $email );

		if ( '' === $email ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from the trusted Tables registry; the email is prepared.
		$query = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE email = %s LIMIT 1", $email );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom operational table query using a trusted table name from the Tables registry; the email is prepared above.
		$person = $this->wpdb->get_row( $query );

		return $person instanceof \stdClass ? $person : null;
	}


	/**
	 * Find a person by QR token.
	 *
	 * @param string $token QR identity token.
	 * @return object|null
	 */
	public function find_by_qr_token( string $token ): ?object {
		$token = sanitize_text_field( $token );

		if ( '' === $token ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from the trusted Tables registry; the token is prepared.
		$query = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE qr_token = %s AND status <> %s LIMIT 1", $token, 'archived' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom operational table query using a trusted table name from the Tables registry; values are prepared above.
		$person = $this->wpdb->get_row( $query );

		return $person instanceof \stdClass ? $person : null;
	}

	/**
	 * Create a person.
	 *
	 * @param array<string, mixed> $data Person data.
	 * @return int
	 */
	public function create( array $data ): int {
		$now  = current_time( 'mysql' );
		$data = $this->prepare_for_storage( $data );

		$data['qr_token']   = $this->generate_qr_token();
		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom operational table insert.
		$inserted = $this->wpdb->insert(
			$this->table,
			$data,
			$this->formats( $data )
		);

		if ( false === $inserted ) {
			return 0;
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update a person.
	 *
	 * @param int                  $id   Person ID.
	 * @param array<string, mixed> $data Person data.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		if ( $id <= 0 ) {
			return false;
		}

		$data               = $this->prepare_for_storage( $data );
		$data['updated_at'] = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom operational table update.
		$updated = $this->wpdb->update(
			$this->table,
			$data,
			array( 'id' => $id ),
			$this->formats( $data ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Archive a person.
	 *
	 * @param int $id Person ID.
	 * @return bool
	 */
	public function archive( int $id ): bool {
		if ( $id <= 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom operational table update.
		$updated = $this->wpdb->update(
			$this->table,
			array(
				'status'     => 'archived',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Regenerate a person's QR token.
	 *
	 * @param int $id Person ID.
	 * @return string
	 */
	public function regenerate_qr_token( int $id ): string {
		if ( $id <= 0 ) {
			return '';
		}

		$token = $this->generate_qr_token();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom operational table update.
		$updated = $this->wpdb->update(
			$this->table,
			array(
				'qr_token'   => $token,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false === $updated ? '' : $token;
	}

	/**
	 * Prepare data for storage.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	private function prepare_for_storage( array $data ): array {
		$first_name   = isset( $data['first_name'] ) ? sanitize_text_field( (string) $data['first_name'] ) : '';
		$last_name    = isset( $data['last_name'] ) ? sanitize_text_field( (string) $data['last_name'] ) : '';
		$display_name = isset( $data['display_name'] ) ? sanitize_text_field( (string) $data['display_name'] ) : '';

		if ( '' === $display_name ) {
			$display_name = trim( $first_name . ' ' . $last_name );
		}

		$email = isset( $data['email'] ) ? sanitize_email( (string) $data['email'] ) : '';
		$phone = isset( $data['phone'] ) ? sanitize_text_field( (string) $data['phone'] ) : '';
		$status = isset( $data['status'] ) && 'inactive' === $data['status'] ? 'inactive' : 'active';

		return array(
			'wp_user_id'   => isset( $data['wp_user_id'] ) ? absint( $data['wp_user_id'] ) : null,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => '' !== $display_name ? $display_name : __( 'Unnamed Person', 'studio-booking-manager' ),
			'email'        => $email,
			'phone'        => $phone,
			'status'       => $status,
			'notes'        => isset( $data['notes'] ) ? sanitize_textarea_field( (string) $data['notes'] ) : '',
		);
	}

	/**
	 * Generate a unique QR token.
	 *
	 * @return string
	 */
	private function generate_qr_token(): string {
		return wp_hash( wp_generate_uuid4() . '|' . microtime( true ) );
	}

	/**
	 * Build formats for a row.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return array<int, string>
	 */
	private function formats( array $data ): array {
		$formats = array();

		foreach ( array_keys( $data ) as $key ) {
			$formats[] = 'wp_user_id' === $key ? '%d' : '%s';
		}

		return $formats;
	}
}

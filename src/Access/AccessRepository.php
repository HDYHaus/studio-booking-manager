<?php
/**
 * Access repository.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Access;

use StudioBookingManager\Database\Tables;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Handles persistence for access records.
 */
final class AccessRepository {
	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Access table name.
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
		$this->table = Tables::get( 'access' );
	}

	/**
	 * Get all non-archived access records.
	 *
	 * @return array<int, object>
	 */
	public function all(): array {
		$people_table    = Tables::get( 'people' );
		$locations_table = Tables::get( 'locations' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from the trusted Tables registry.
		$query = $this->wpdb->prepare(
			"SELECT access.*, people.display_name AS person_name, locations.name AS location_name
			FROM `{$this->table}` access
			LEFT JOIN `{$people_table}` people ON people.id = access.person_id
			LEFT JOIN `{$locations_table}` locations ON locations.id = access.location_id
			WHERE access.status <> %s
			ORDER BY access.created_at DESC, access.id DESC",
			'archived'
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom operational table query with trusted table names and prepared status value.
		$records = $this->wpdb->get_results( $query );

		return is_array( $records ) ? $records : array();
	}

	/**
	 * Find an access record by ID.
	 *
	 * @param int $id Access ID.
	 * @return object|null
	 */
	public function find( int $id ): ?object {
		if ( $id <= 0 ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from the trusted Tables registry; the ID is prepared.
		$query = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE id = %d LIMIT 1", $id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom operational table query using a trusted table name from the Tables registry; the ID is prepared above.
		$record = $this->wpdb->get_row( $query );

		return $record instanceof \stdClass ? $record : null;
	}

	/**
	 * Create an access record.
	 *
	 * @param array<string, mixed> $data Access data.
	 * @return int
	 */
	public function create( array $data ): int {
		$now  = current_time( 'mysql' );
		$data = $this->prepare_for_storage( $data );

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
	 * Update an access record.
	 *
	 * @param int                  $id   Access ID.
	 * @param array<string, mixed> $data Access data.
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
	 * Archive an access record.
	 *
	 * @param int $id Access ID.
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
	 * Prepare data for storage.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	private function prepare_for_storage( array $data ): array {
		$access_type = isset( $data['access_type'] ) ? sanitize_key( (string) $data['access_type'] ) : 'single_visit';
		$allowed     = array( 'single_visit', 'visit_pass', 'membership' );

		if ( ! in_array( $access_type, $allowed, true ) ) {
			$access_type = 'single_visit';
		}

		$status          = isset( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : 'active';
		$allowed_status  = array( 'active', 'pending', 'expired', 'suspended', 'archived' );
		$status          = in_array( $status, $allowed_status, true ) ? $status : 'active';
		$total_credits   = isset( $data['total_credits'] ) && '' !== (string) $data['total_credits'] ? absint( $data['total_credits'] ) : null;
		$remaining       = isset( $data['remaining_credits'] ) && '' !== (string) $data['remaining_credits'] ? absint( $data['remaining_credits'] ) : $total_credits;
		$weekly_limit    = isset( $data['weekly_limit'] ) && '' !== (string) $data['weekly_limit'] ? absint( $data['weekly_limit'] ) : null;
		$guest_limit     = isset( $data['guest_limit'] ) ? absint( $data['guest_limit'] ) : 0;
		$starts_at       = isset( $data['starts_at'] ) ? $this->normalize_datetime( (string) $data['starts_at'] ) : null;
		$expires_at      = isset( $data['expires_at'] ) ? $this->normalize_datetime( (string) $data['expires_at'] ) : null;
		$metadata        = isset( $data['metadata'] ) ? sanitize_textarea_field( (string) $data['metadata'] ) : '';

		if ( 'membership' === $access_type ) {
			$total_credits = null;
			$remaining     = null;
			$weekly_limit  = null;
		}

		if ( 'single_visit' === $access_type ) {
			$total_credits = 1;
			$remaining     = isset( $data['remaining_credits'] ) && '' !== (string) $data['remaining_credits'] ? absint( $data['remaining_credits'] ) : 1;
			$weekly_limit  = null;
		}

		return array(
			'person_id'         => isset( $data['person_id'] ) ? absint( $data['person_id'] ) : 0,
			'location_id'       => isset( $data['location_id'] ) ? absint( $data['location_id'] ) : 0,
			'wp_user_id'        => isset( $data['wp_user_id'] ) && '' !== (string) $data['wp_user_id'] ? absint( $data['wp_user_id'] ) : null,
			'order_id'          => isset( $data['order_id'] ) && '' !== (string) $data['order_id'] ? absint( $data['order_id'] ) : null,
			'product_id'        => isset( $data['product_id'] ) && '' !== (string) $data['product_id'] ? absint( $data['product_id'] ) : null,
			'variation_id'      => isset( $data['variation_id'] ) && '' !== (string) $data['variation_id'] ? absint( $data['variation_id'] ) : null,
			'access_type'       => $access_type,
			'status'            => $status,
			'total_credits'     => $total_credits,
			'remaining_credits' => $remaining,
			'weekly_limit'      => $weekly_limit,
			'guest_limit'       => $guest_limit,
			'starts_at'         => $starts_at,
			'expires_at'        => $expires_at,
			'metadata'          => $metadata,
		);
	}

	/**
	 * Normalize a datetime value for storage.
	 *
	 * @param string $value Raw datetime value.
	 * @return string|null
	 */
	private function normalize_datetime( string $value ): ?string {
		$value = trim( $value );

		if ( '' === $value ) {
			return null;
		}

		$timestamp = strtotime( $value );

		if ( false === $timestamp ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Build formats for a row.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return array<int, string>
	 */
	private function formats( array $data ): array {
		$formats = array();
		$ints    = array( 'person_id', 'location_id', 'wp_user_id', 'order_id', 'product_id', 'variation_id', 'total_credits', 'remaining_credits', 'weekly_limit', 'guest_limit' );

		foreach ( array_keys( $data ) as $key ) {
			$formats[] = in_array( $key, $ints, true ) ? '%d' : '%s';
		}

		return $formats;
	}
}

<?php
/**
 * Location repository.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Locations;

use StudioBookingManager\Database\Tables;

use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Handles persistence for locations.
 */
final class LocationRepository {
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
		$this->table = Tables::get( 'locations' );
	}

	/**
	 * Get all non-archived locations.
	 *
	 * @return array<int, object>
	 */
	public function all(): array {
		$query = "SELECT * FROM `{$this->table}` WHERE status <> 'archived' ORDER BY is_default DESC, name ASC";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom operational table query using a trusted table name from the Tables registry and no user input.
		$locations = $this->wpdb->get_results( $query );

		return is_array( $locations ) ? $locations : array();
	}

	/**
	 * Find a location by ID.
	 *
	 * @param int $id Location ID.
	 * @return object|null
	 */
	public function find( int $id ): ?object {
		if ( $id <= 0 ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from the trusted Tables registry; the ID is prepared.
		$query = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE id = %d LIMIT 1", $id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom operational table query using a trusted table name from the Tables registry; the ID is prepared above.
		$location = $this->wpdb->get_row( $query );

		return $location instanceof \stdClass ? $location : null;
	}

	/**
	 * Create a location.
	 *
	 * @param array<string, mixed> $data Location data.
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
	 * Update a location.
	 *
	 * @param int                  $id   Location ID.
	 * @param array<string, mixed> $data Location data.
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
	 * Archive a location.
	 *
	 * @param int $id Location ID.
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
				'is_default' => 0,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Set default location.
	 *
	 * @param int $id Location ID.
	 * @return bool
	 */
	public function set_default( int $id ): bool {
		if ( $id <= 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom operational table update.
		$this->wpdb->update(
			$this->table,
			array( 'is_default' => 0 ),
			array( 'is_default' => 1 ),
			array( '%d' ),
			array( '%d' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom operational table update.
		$updated = $this->wpdb->update(
			$this->table,
			array(
				'is_default' => 1,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
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
		$name = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
		$slug = isset( $data['slug'] ) ? sanitize_title( (string) $data['slug'] ) : sanitize_title( $name );

		return array(
			'name'          => $name,
			'slug'          => '' !== $slug ? $slug : sanitize_title( $name ),
			'address'       => isset( $data['address'] ) ? sanitize_textarea_field( (string) $data['address'] ) : '',
			'timezone'      => isset( $data['timezone'] ) ? sanitize_text_field( (string) $data['timezone'] ) : wp_timezone_string(),
			'capacity'      => isset( $data['capacity'] ) ? absint( $data['capacity'] ) : 0,
			'opening_hours' => isset( $data['opening_hours'] ) ? sanitize_textarea_field( (string) $data['opening_hours'] ) : '',
			'calendar_id'   => isset( $data['calendar_id'] ) ? sanitize_text_field( (string) $data['calendar_id'] ) : '',
			'status'        => isset( $data['status'] ) && 'inactive' === $data['status'] ? 'inactive' : 'active',
			'is_default'    => isset( $data['is_default'] ) ? absint( $data['is_default'] ) : 0,
		);
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
			$formats[] = in_array( $key, array( 'capacity', 'is_default' ), true ) ? '%d' : '%s';
		}

		return $formats;
	}
}

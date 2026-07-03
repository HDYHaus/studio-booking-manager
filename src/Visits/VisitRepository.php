<?php
/**
 * Visit repository.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Visits;

use StudioBookingManager\Database\Tables;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Handles persistence for visit records.
 */
final class VisitRepository {
	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Visits table name.
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
		$this->table = Tables::get( 'visits' );
	}

	/**
	 * Get all visit records.
	 *
	 * @return array<int, object>
	 */
	public function all(): array {
		$people_table    = Tables::get( 'people' );
		$locations_table = Tables::get( 'locations' );
		$access_table    = Tables::get( 'access' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from the trusted Tables registry.
		$query = $this->wpdb->prepare(
			"SELECT visits.*, people.display_name AS person_name, locations.name AS location_name, access.access_type AS access_type
			FROM `{$this->table}` visits
			LEFT JOIN `{$people_table}` people ON people.id = visits.person_id
			LEFT JOIN `{$locations_table}` locations ON locations.id = visits.location_id
			LEFT JOIN `{$access_table}` access ON access.id = visits.access_id
			WHERE visits.status <> %s
			ORDER BY visits.created_at DESC, visits.id DESC",
			'archived'
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom operational table query with trusted table names and prepared status value.
		$records = $this->wpdb->get_results( $query );

		return is_array( $records ) ? $records : array();
	}

	/**
	 * Find a visit by ID.
	 *
	 * @param int $id Visit ID.
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
	 * Create a visit record.
	 *
	 * @param array<string, mixed> $data Visit data.
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
	 * Update a visit record.
	 *
	 * @param int                  $id   Visit ID.
	 * @param array<string, mixed> $data Visit data.
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
	 * Archive a visit record.
	 *
	 * @param int $id Visit ID.
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
	 * Get visits that are currently checked in.
	 *
	 * @return array<int, object>
	 */
	public function current(): array {
		$people_table    = Tables::get( 'people' );
		$locations_table = Tables::get( 'locations' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from the trusted Tables registry.
		$query = $this->wpdb->prepare(
			"SELECT visits.*, people.display_name AS person_name, locations.name AS location_name
			FROM `{$this->table}` visits
			LEFT JOIN `{$people_table}` people ON people.id = visits.person_id
			LEFT JOIN `{$locations_table}` locations ON locations.id = visits.location_id
			WHERE visits.status = %s
			ORDER BY visits.checked_in_at ASC, visits.id ASC",
			'checked_in'
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom operational table query with trusted table names and prepared status value.
		$records = $this->wpdb->get_results( $query );

		return is_array( $records ) ? $records : array();
	}

	/**
	 * Find an active checked-in visit for a person and location.
	 *
	 * @param int $person_id Person ID.
	 * @param int $location_id Location ID.
	 * @return object|null
	 */
	public function find_current_for_person( int $person_id, int $location_id ): ?object {
		if ( $person_id <= 0 || $location_id <= 0 ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from the trusted Tables registry; values are prepared.
		$query = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE person_id = %d AND location_id = %d AND status = %s LIMIT 1", $person_id, $location_id, 'checked_in' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom operational table query using trusted table name and prepared values.
		$record = $this->wpdb->get_row( $query );

		return $record instanceof \stdClass ? $record : null;
	}

	/**
	 * Mark a visit as checked out.
	 *
	 * @param int $id Visit ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function check_out( int $id, int $user_id ): bool {
		if ( $id <= 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom operational table update.
		$updated = $this->wpdb->update(
			$this->table,
			array(
				'status'         => 'checked_out',
				'checked_out_at' => current_time( 'mysql' ),
				'checked_out_by' => $user_id,
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Count checked-in and checked-out visits for an access record since a timestamp.
	 *
	 * @param int    $access_id Access ID.
	 * @param string $since Date/time lower bound.
	 * @return int
	 */
	public function count_completed_for_access_since( int $access_id, string $since ): int {
		if ( $access_id <= 0 || '' === trim( $since ) ) {
			return 0;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from the trusted Tables registry; values are prepared.
		$query = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM `{$this->table}` WHERE access_id = %d AND status IN (%s, %s) AND checked_in_at >= %s",
			$access_id,
			'checked_in',
			'checked_out',
			$since
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom operational table query for access validation.
		return (int) $this->wpdb->get_var( $query );
	}


	/**
	 * Prepare data for storage.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	private function prepare_for_storage( array $data ): array {
		return array(
			'person_id'       => isset( $data['person_id'] ) ? absint( $data['person_id'] ) : 0,
			'location_id'     => isset( $data['location_id'] ) ? absint( $data['location_id'] ) : 0,
			'access_id'       => isset( $data['access_id'] ) ? absint( $data['access_id'] ) : 0,
			'booking_id'      => isset( $data['booking_id'] ) ? absint( $data['booking_id'] ) : 0,
			'status'          => isset( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : 'expected',
			'visit_date'      => isset( $data['visit_date'] ) ? sanitize_text_field( (string) $data['visit_date'] ) : null,
			'scheduled_start' => isset( $data['scheduled_start'] ) ? sanitize_text_field( (string) $data['scheduled_start'] ) : null,
			'scheduled_end'   => isset( $data['scheduled_end'] ) ? sanitize_text_field( (string) $data['scheduled_end'] ) : null,
			'checked_in_at'   => isset( $data['checked_in_at'] ) ? sanitize_text_field( (string) $data['checked_in_at'] ) : null,
			'checked_out_at'  => isset( $data['checked_out_at'] ) ? sanitize_text_field( (string) $data['checked_out_at'] ) : null,
			'guest_count'     => isset( $data['guest_count'] ) ? absint( $data['guest_count'] ) : 0,
			'guest_names'     => isset( $data['guest_names'] ) ? sanitize_textarea_field( (string) $data['guest_names'] ) : '',
			'checkin_method'  => isset( $data['checkin_method'] ) ? sanitize_key( (string) $data['checkin_method'] ) : '',
			'checked_in_by'   => isset( $data['checked_in_by'] ) ? absint( $data['checked_in_by'] ) : 0,
			'checked_out_by'  => isset( $data['checked_out_by'] ) ? absint( $data['checked_out_by'] ) : 0,
			'notes'           => isset( $data['notes'] ) ? sanitize_textarea_field( (string) $data['notes'] ) : '',
		);
	}

	/**
	 * Get DB formats for a data array.
	 *
	 * @param array<string, mixed> $data Data being stored.
	 * @return array<int, string>
	 */
	private function formats( array $data ): array {
		$formats = array();

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, array( 'person_id', 'location_id', 'access_id', 'booking_id', 'guest_count', 'checked_in_by', 'checked_out_by' ), true ) ) {
				$formats[] = '%d';
				continue;
			}

			$formats[] = '%s';
		}

		return $formats;
	}
}

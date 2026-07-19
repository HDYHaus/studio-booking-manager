<?php
/**
 * Booking repository.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Bookings;

use StudioBookingManager\Database\Tables;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Handles persistence for booking records.
 */
final class BookingRepository {
	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Bookings table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Current or legacy primary key column.
	 *
	 * @var string
	 */
	private string $id_column = 'id';

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
		$this->table = Tables::get( 'bookings' );
		$this->id_column = $this->detect_id_column();
	}

	/**
	 * Get non-archived bookings.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @return array<int, object>
	 */
	public function all( array $filters = array() ): array {
		$people_table    = Tables::get( 'people' );
		$locations_table = Tables::get( 'locations' );
		$access_table    = Tables::get( 'access' );

		$where = array( 'bookings.status <> %s' );
		$args  = array( 'archived' );

		$status = isset( $filters['status'] ) ? sanitize_key( (string) $filters['status'] ) : '';
		if ( '' !== $status ) {
			$where[] = 'bookings.status = %s';
			$args[]  = $status;
		}

		$visibility = isset( $filters['visibility'] ) ? sanitize_key( (string) $filters['visibility'] ) : '';
		if ( in_array( $visibility, array( 'internal', 'public', 'private', 'blocked' ), true ) ) {
			$where[] = 'bookings.visibility = %s';
			$args[]  = $visibility;
		}

		$person_id = isset( $filters['person_id'] ) ? absint( $filters['person_id'] ) : 0;
		if ( $person_id > 0 ) {
			$where[] = 'bookings.person_id = %d';
			$args[]  = $person_id;
		}

		$location_id = isset( $filters['location_id'] ) ? absint( $filters['location_id'] ) : 0;
		if ( $location_id > 0 ) {
			$where[] = 'bookings.location_id = %d';
			$args[]  = $location_id;
		}

		$date = isset( $filters['date'] ) ? sanitize_text_field( (string) $filters['date'] ) : '';
		if ( '' !== $date ) {
			$where[] = 'DATE(bookings.starts_at) = %s';
			$args[]  = $date;
		}

		$where_sql = implode( ' AND ', $where );
		$id_column = $this->id_column;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table names, column names, and WHERE fragments are internal trusted values with prepared placeholder args.
		$query = $this->wpdb->prepare(
			"SELECT bookings.*, bookings.`{$id_column}` AS id, people.display_name AS person_name, locations.name AS location_name, access.access_type AS access_type
			FROM `{$this->table}` bookings
			LEFT JOIN `{$people_table}` people ON people.id = bookings.person_id
			LEFT JOIN `{$locations_table}` locations ON locations.id = bookings.location_id
			LEFT JOIN `{$access_table}` access ON access.id = bookings.access_id
			WHERE {$where_sql}
			ORDER BY bookings.starts_at ASC, bookings.`{$id_column}` ASC",
			$args
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom operational table query with trusted table names and prepared values.
		$records = $this->wpdb->get_results( $query );

		return is_array( $records ) ? $records : array();
	}

	/**
	 * Find a booking.
	 *
	 * @param int $id Booking ID.
	 * @return object|null
	 */
	public function find( int $id ): ?object {
		if ( $id <= 0 ) {
			return null;
		}

		$id_column = $this->id_column;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and column names come from trusted registry/detection; ID is prepared.
		$query = $this->wpdb->prepare( "SELECT *, `{$id_column}` AS id FROM `{$this->table}` WHERE `{$id_column}` = %d LIMIT 1", $id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom operational table query using trusted table name and prepared ID.
		$record = $this->wpdb->get_row( $query );

		return $record instanceof \stdClass ? $record : null;
	}

	/**
	 * Find upcoming bookings for a person.
	 *
	 * @param int $person_id Person ID.
	 * @return array<int, object>
	 */
	public function upcoming_for_person( int $person_id ): array {
		if ( $person_id <= 0 ) {
			return array();
		}

		$locations_table = Tables::get( 'locations' );
		$id_column       = $this->id_column;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and column names come from trusted Tables registry/detection; values are prepared.
		$query = $this->wpdb->prepare(
			"SELECT bookings.*, bookings.`{$id_column}` AS id, locations.name AS location_name, locations.timezone AS location_timezone
			FROM `{$this->table}` bookings
			LEFT JOIN `{$locations_table}` locations ON locations.id = bookings.location_id
			WHERE bookings.person_id = %d
			AND bookings.status IN ( %s, %s )
			AND bookings.ends_at >= %s
			ORDER BY bookings.starts_at ASC, bookings.`{$id_column}` ASC",
			$person_id,
			'pending',
			'confirmed',
			current_time( 'mysql' )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom operational table query using trusted table name and prepared values.
		$records = $this->wpdb->get_results( $query );

		return is_array( $records ) ? $records : array();
	}

	/**
	 * Find upcoming booking blocks safe for member-facing schedule views.
	 *
	 * @param int $days Number of days ahead.
	 * @param int $location_id Optional location ID.
	 * @param int $limit Maximum rows.
	 * @return array<int, object>
	 */
	public function member_schedule( int $days = 14, int $location_id = 0, int $limit = 30 ): array {
		$locations_table = Tables::get( 'locations' );
		$days            = max( 1, min( 120, $days ) );
		$limit           = max( 1, min( 100, $limit ) );
		$now             = current_time( 'mysql' );
		$end             = gmdate( 'Y-m-d H:i:s', strtotime( '+' . $days . ' days', strtotime( $now ) ) );

		$where = array(
			'bookings.status IN ( %s, %s )',
			'bookings.ends_at >= %s',
			'bookings.starts_at <= %s',
		);
		$args  = array( 'pending', 'confirmed', $now, $end );

		if ( $location_id > 0 ) {
			$where[] = 'bookings.location_id = %d';
			$args[]  = $location_id;
		}

		$where_sql = implode( ' AND ', $where );
		$args[]    = $limit;
		$id_column = $this->id_column;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names, column names, and WHERE fragments are internal trusted values.
		$query = $this->wpdb->prepare(
			"SELECT bookings.`{$id_column}` AS id, bookings.location_id, bookings.status, bookings.visibility, bookings.public_title, bookings.starts_at, bookings.ends_at, locations.name AS location_name, locations.timezone AS location_timezone
			FROM `{$this->table}` bookings
			LEFT JOIN `{$locations_table}` locations ON locations.id = bookings.location_id
			WHERE {$where_sql}
			ORDER BY bookings.starts_at ASC, bookings.`{$id_column}` ASC
			LIMIT %d",
			$args
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom operational table query with trusted table names and prepared values.
		$records = $this->wpdb->get_results( $query );

		return is_array( $records ) ? $records : array();
	}

	/**
	 * Find public-safe schedule context for a selected date.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @param int    $location_id Optional location ID.
	 * @return array<int, object>
	 */
	public function public_schedule_for_date( string $date, int $location_id = 0 ): array {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return array();
		}

		$where = array(
			'bookings.status IN ( %s, %s )',
			'bookings.visibility IN ( %s, %s, %s )',
			'DATE(bookings.starts_at) = %s',
		);
		$args  = array( 'pending', 'confirmed', 'public', 'private', 'blocked', $date );

		if ( $location_id > 0 ) {
			$where[] = 'bookings.location_id = %d';
			$args[]  = $location_id;
		}

		$where_sql = implode( ' AND ', $where );
		$id_column = $this->id_column;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table name, column name, and WHERE fragments are internal trusted values with prepared args.
		$query = $this->wpdb->prepare(
			"SELECT bookings.`{$id_column}` AS id, bookings.location_id, bookings.visibility, bookings.public_title, bookings.starts_at, bookings.ends_at
			FROM `{$this->table}` bookings
			WHERE {$where_sql}
			ORDER BY bookings.starts_at ASC, bookings.`{$id_column}` ASC",
			$args
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Public schedule query uses prepared values.
		$records = $this->wpdb->get_results( $query );

		return is_array( $records ) ? $records : array();
	}

	/**
	 * Create a booking.
	 *
	 * @param array<string,mixed> $data Booking data.
	 * @return int
	 */
	public function create( array $data ): int {
		$this->last_error = '';
		$now  = current_time( 'mysql' );
		$data = $this->prepare_for_storage( $data );

		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom operational table insert.
		$inserted = $this->wpdb->insert( $this->table, $data, $this->formats( $data ) );

		if ( false === $inserted ) {
			$this->last_error = (string) $this->wpdb->last_error;
			return 0;
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Get the last database error.
	 */
	public function last_error(): string {
		return $this->last_error;
	}

	/**
	 * Update a booking.
	 *
	 * @param int                  $id Booking ID.
	 * @param array<string,mixed>  $data Booking data.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		if ( $id <= 0 ) {
			return false;
		}

		$existing = $this->find( $id );
		if ( $existing instanceof \stdClass ) {
			foreach ( array( 'calendar_event_id', 'calendar_sync_status', 'calendar_sync_error', 'calendar_synced_at' ) as $key ) {
				if ( ! array_key_exists( $key, $data ) && isset( $existing->{$key} ) ) {
					$data[ $key ] = $existing->{$key};
				}
			}
		}

		$data               = $this->prepare_for_storage( $data );
		$data['updated_at'] = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom operational table update.
		$updated = $this->wpdb->update( $this->table, $data, array( $this->id_column => $id ), $this->formats( $data ), array( '%d' ) );

		return false !== $updated;
	}

	/**
	 * Archive a booking.
	 *
	 * @param int $id Booking ID.
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
			array( $this->id_column => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Update booking status.
	 *
	 * @param int    $id Booking ID.
	 * @param string $status Status.
	 * @return bool
	 */
	public function update_status( int $id, string $status ): bool {
		if ( $id <= 0 ) {
			return false;
		}

		$status = sanitize_key( $status );
		if ( ! in_array( $status, array( 'pending', 'confirmed', 'cancelled', 'completed', 'no_show', 'archived' ), true ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom operational table update.
		$updated = $this->wpdb->update(
			$this->table,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( $this->id_column => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Update calendar sync fields for a booking.
	 *
	 * @param int    $id Booking ID.
	 * @param string $status Sync status.
	 * @param string $error Sync error message.
	 * @param string $event_id Calendar event ID.
	 * @return bool
	 */
	public function update_calendar_sync( int $id, string $status, string $error = '', string $event_id = '' ): bool {
		if ( $id <= 0 ) {
			return false;
		}

		$status = sanitize_key( $status );
		if ( ! in_array( $status, array( 'not_synced', 'disabled', 'synced', 'failed', 'deleted' ), true ) ) {
			$status = 'not_synced';
		}

		$data = array(
			'calendar_sync_status' => $status,
			'calendar_sync_error'  => sanitize_textarea_field( $error ),
			'calendar_synced_at'   => in_array( $status, array( 'synced', 'deleted' ), true ) ? current_time( 'mysql' ) : null,
			'updated_at'           => current_time( 'mysql' ),
		);

		$formats = array( '%s', '%s', '%s', '%s' );

		if ( 'deleted' === $status ) {
			$data['calendar_event_id'] = '';
			$formats[]                 = '%s';
		} elseif ( '' !== $event_id ) {
			$data['calendar_event_id'] = sanitize_text_field( $event_id );
			$formats[]                 = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom operational table update.
		$updated = $this->wpdb->update( $this->table, $data, array( $this->id_column => $id ), $formats, array( '%d' ) );

		return false !== $updated;
	}

	/**
	 * Determine whether a location has an overlapping booking.
	 *
	 * @param int    $location_id Location ID.
	 * @param string $starts_at Start datetime.
	 * @param string $ends_at End datetime.
	 * @param int    $exclude_id Booking ID to exclude.
	 * @return bool
	 */
	public function has_conflict( int $location_id, string $starts_at, string $ends_at, int $exclude_id = 0 ): bool {
		if ( $location_id <= 0 || '' === $starts_at || '' === $ends_at ) {
			return false;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from trusted Tables registry; values are prepared.
		$query = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM `{$this->table}`
			WHERE location_id = %d
				AND `{$this->id_column}` <> %d
				AND status IN ( %s, %s )
				AND starts_at < %s
				AND ends_at > %s",
			$location_id,
			$exclude_id,
			'pending',
			'confirmed',
			$ends_at,
			$starts_at
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom operational table conflict check using prepared values.
		return (int) $this->wpdb->get_var( $query ) > 0;
	}

	/**
	 * Prepare data for storage.
	 *
	 * @param array<string,mixed> $data Raw data.
	 * @return array<string,mixed>
	 */
	private function prepare_for_storage( array $data ): array {
		$status = isset( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : 'pending';
		if ( ! in_array( $status, array( 'pending', 'confirmed', 'cancelled', 'completed', 'no_show', 'archived' ), true ) ) {
			$status = 'pending';
		}

		return array(
			'person_id'       => isset( $data['person_id'] ) ? absint( $data['person_id'] ) : 0,
			'location_id'     => isset( $data['location_id'] ) ? absint( $data['location_id'] ) : 0,
			'access_id'       => isset( $data['access_id'] ) && '' !== (string) $data['access_id'] ? absint( $data['access_id'] ) : null,
			'status'          => $status,
			'visibility'      => $this->prepare_visibility( $data ),
			'public_title'    => isset( $data['public_title'] ) ? sanitize_text_field( (string) $data['public_title'] ) : '',
			'starts_at'       => isset( $data['starts_at'] ) ? sanitize_text_field( (string) $data['starts_at'] ) : '',
			'ends_at'         => isset( $data['ends_at'] ) ? sanitize_text_field( (string) $data['ends_at'] ) : '',
			'guest_count'     => isset( $data['guest_count'] ) ? absint( $data['guest_count'] ) : 0,
			'guest_names'     => isset( $data['guest_names'] ) ? sanitize_textarea_field( (string) $data['guest_names'] ) : '',
			'calendar_event_id' => isset( $data['calendar_event_id'] ) ? sanitize_text_field( (string) $data['calendar_event_id'] ) : '',
			'calendar_sync_status' => isset( $data['calendar_sync_status'] ) ? sanitize_key( (string) $data['calendar_sync_status'] ) : 'not_synced',
			'calendar_sync_error' => isset( $data['calendar_sync_error'] ) ? sanitize_textarea_field( (string) $data['calendar_sync_error'] ) : '',
			'calendar_synced_at' => isset( $data['calendar_synced_at'] ) ? sanitize_text_field( (string) $data['calendar_synced_at'] ) : null,
			'notes'           => isset( $data['notes'] ) ? sanitize_textarea_field( (string) $data['notes'] ) : '',
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
			$formats[] = in_array( $key, array( 'person_id', 'location_id', 'access_id', 'guest_count' ), true ) ? '%d' : '%s';
		}

		return $formats;
	}

	/**
	 * Prepare public visibility for storage.
	 *
	 * @param array<string,mixed> $data Raw data.
	 */
	private function prepare_visibility( array $data ): string {
		$visibility = isset( $data['visibility'] ) ? sanitize_key( (string) $data['visibility'] ) : 'internal';

		return in_array( $visibility, array( 'public', 'private', 'blocked', 'internal' ), true ) ? $visibility : 'internal';
	}

	/**
	 * Detect current or legacy booking primary key column.
	 */
	private function detect_id_column(): string {
		if ( '' === $this->table ) {
			return 'id';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema compatibility detection for known plugin table.
		$has_id = $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $this->table, 'id' ) );

		if ( null !== $has_id && false !== $has_id ) {
			return 'id';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema compatibility detection for known plugin table.
		$has_legacy_id = $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $this->table, 'booking_id' ) );

		return null !== $has_legacy_id && false !== $has_legacy_id ? 'booking_id' : 'id';
	}
}

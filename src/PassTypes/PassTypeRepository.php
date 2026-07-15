<?php
/**
 * Pass type repository.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\PassTypes;

use StudioBookingManager\Database\Tables;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Handles persistence for pass types.
 */
final class PassTypeRepository {
	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Pass types table name.
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
		$this->table = Tables::get( 'pass_types' );
	}

	/**
	 * Get all pass types.
	 *
	 * @return array<int, PassType>
	 */
	public function all(): array {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is trusted via Tables::get().
		$query = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE status <> %s ORDER BY created_at DESC, id DESC", 'archived' );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom operational table query with trusted table name and prepared status value.
		$rows = $this->wpdb->get_results( $query );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( static function( $row ): PassType {
			return new PassType( $row );
		}, $rows );
	}

	/**
	 * Find a pass type.
	 *
	 * @param int $id Pass type ID.
	 * @return PassType|null
	 */
	public function find( int $id ): ?PassType {
		if ( $id <= 0 ) {
			return null;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is trusted via Tables::get().
		$query = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE id = %d LIMIT 1", $id );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom operational table query with trusted table name and prepared ID value.
		$row = $this->wpdb->get_row( $query );

		return $row instanceof \stdClass ? new PassType( $row ) : null;
	}

	/**
	 * Create a pass type.
	 *
	 * @param array<string,mixed> $data Pass type data.
	 * @return int
	 */
	public function create( array $data ): int {
		$now = current_time( 'mysql' );
		$data = $this->prepare_for_storage( $data );

		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
		$inserted = $this->wpdb->insert( $this->table, $data, $this->formats( $data ) );

		if ( false === $inserted ) {
			return 0;
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update a pass type.
	 *
	 * @param int $id Pass type ID.
	 * @param array<string,mixed> $data Pass type data.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		if ( $id <= 0 ) {
			return false;
		}

		$data = $this->prepare_for_storage( $data );
		$data['updated_at'] = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table update.
		$updated = $this->wpdb->update( $this->table, $data, array( 'id' => $id ), $this->formats( $data ), array( '%d' ) );

		return false !== $updated;
	}

	/**
	 * Archive a pass type.
	 *
	 * @param int $id Pass type ID.
	 * @return bool
	 */
	public function archive( int $id ): bool {
		if ( $id <= 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table update.
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
	 * Prepare pass type data for storage.
	 *
	 * @param array<string,mixed> $data Raw data.
	 * @return array<string,mixed>
	 */
	private function prepare_for_storage( array $data ): array {
		$name = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';

		$behaviour = isset( $data['behaviour'] ) ? sanitize_key( (string) $data['behaviour'] ) : 'one_time';
		$allowed   = array( 'one_time', 'multiple_visits', 'membership' );
		if ( ! in_array( $behaviour, $allowed, true ) ) {
			$behaviour = 'one_time';
		}

		$number_of_visits = isset( $data['number_of_visits'] ) && '' !== (string) $data['number_of_visits'] ? absint( $data['number_of_visits'] ) : null;
		$maximum_visits_per_week = isset( $data['maximum_visits_per_week'] ) && '' !== (string) $data['maximum_visits_per_week'] ? absint( $data['maximum_visits_per_week'] ) : null;
		$guest_allowance = isset( $data['guest_allowance'] ) ? absint( $data['guest_allowance'] ) : 0;
		$booking_required = ! empty( $data['booking_required'] ) ? 1 : 0;
		$booking_duration_minutes = isset( $data['booking_duration_minutes'] ) && '' !== (string) $data['booking_duration_minutes'] ? absint( $data['booking_duration_minutes'] ) : null;
		$valid_for = isset( $data['valid_for'] ) && '' !== (string) $data['valid_for'] ? absint( $data['valid_for'] ) : null;
		$status = isset( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : 'active';
		$allowed_status = array( 'active', 'inactive', 'archived' );
		if ( ! in_array( $status, $allowed_status, true ) ) {
			$status = 'active';
		}

		if ( 'one_time' === $behaviour ) {
			$number_of_visits        = 1;
			$maximum_visits_per_week = null;
		}

		if ( 'membership' === $behaviour ) {
			$number_of_visits = null;
		}

		return array(
			'name' => '' !== $name ? $name : __( 'Untitled Pass', 'studio-booking-manager' ),
			'description' => isset( $data['description'] ) ? sanitize_textarea_field( (string) $data['description'] ) : '',
			'behaviour' => $behaviour,
			'number_of_visits' => $number_of_visits,
			'maximum_visits_per_week' => $maximum_visits_per_week,
			'guest_allowance' => $guest_allowance,
			'booking_required' => $booking_required,
			'booking_duration_minutes' => $booking_duration_minutes,
			'valid_for' => $valid_for,
			'status' => $status,
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
		$ints = array(
			'number_of_visits',
			'maximum_visits_per_week',
			'guest_allowance',
			'booking_required',
			'booking_duration_minutes',
			'valid_for',
		);

		foreach ( array_keys( $data ) as $key ) {
			$formats[] = in_array( $key, $ints, true ) ? '%d' : '%s';
		}

		return $formats;
	}
}

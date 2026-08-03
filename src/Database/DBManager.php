<?php
/**
 * Database manager.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates database installation and upgrades.
 */
final class DBManager {
	/**
	 * Maybe run database upgrades.
	 */
	public function maybe_upgrade(): void {
		$installed = get_option( 'sbm_db_version' );

		if ( SBM_DB_VERSION !== $installed || $this->requires_install() ) {
			( new Installer() )->install();
		}
	}

	/**
	 * Determine whether the plugin database requires installation.
	 *
	 * This covers cases where a new table was added on a patch release
	 * without changing the database version constant.
	 *
	 * @return bool
	 */
	private function requires_install(): bool {
		global $wpdb;

		$required = array(
			Tables::get( 'pass_types' ),
			Tables::get( 'bookings' ),
			Tables::get( 'notifications' ),
			Tables::get( 'rsvps' ),
		);

		foreach ( $required as $table ) {
			if ( '' === $table ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema upgrade check against known plugin tables.
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

			if ( false === $exists || null === $exists ) {
				return true;
			}
		}

		return $this->missing_required_columns();
	}

	/**
	 * Determine whether existing tables are missing columns added by later releases.
	 */
	private function missing_required_columns(): bool {
		$required_columns = array(
			Tables::get( 'bookings' ) => array(
				'person_id',
				'location_id',
				'access_id',
				'status',
				'visibility',
				'public_title',
				'starts_at',
				'ends_at',
				'guest_count',
				'guest_names',
				'calendar_event_id',
				'calendar_sync_status',
				'calendar_sync_error',
				'calendar_synced_at',
				'notes',
				'created_at',
				'updated_at',
			),
			Tables::get( 'visits' ) => array(
				'id',
				'person_id',
				'location_id',
				'access_id',
				'booking_id',
				'status',
				'visit_date',
				'scheduled_start',
				'scheduled_end',
				'checked_in_at',
				'checked_out_at',
				'guest_count',
				'guest_names',
				'checkin_method',
				'checked_in_by',
				'checked_out_by',
				'notes',
				'created_at',
				'updated_at',
			),
			Tables::get( 'access' ) => array(
				'id',
				'person_id',
				'location_id',
				'wp_user_id',
				'order_id',
				'product_id',
				'variation_id',
				'access_type',
				'status',
				'total_credits',
				'remaining_credits',
				'weekly_limit',
				'guest_limit',
				'starts_at',
				'expires_at',
				'metadata',
				'created_at',
				'updated_at',
			),
			Tables::get( 'rsvps' ) => array(
				'id',
				'post_id',
				'attendee_name',
				'attendee_email',
				'guest_count',
				'guest_names',
				'status',
				'notes',
				'ip_hash',
				'user_agent_hash',
				'created_at',
				'updated_at',
			),
		);

		foreach ( $required_columns as $table => $columns ) {
			if ( '' === $table || $this->table_missing_columns( $table, $columns ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether a table is missing any expected columns.
	 *
	 * @param string        $table   Table name.
	 * @param array<int,string> $columns Required columns.
	 */
	private function table_missing_columns( string $table, array $columns ): bool {
		global $wpdb;

		foreach ( $columns as $column ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema upgrade check against known plugin tables and columns.
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table, $column ) );

			if ( false === $exists || null === $exists ) {
				return true;
			}
		}

		return false;
	}
}

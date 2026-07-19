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
				'visibility',
				'public_title',
				'calendar_event_id',
				'calendar_sync_status',
				'calendar_sync_error',
				'calendar_synced_at',
			),
			Tables::get( 'visits' ) => array(
				'booking_id',
				'scheduled_start',
				'scheduled_end',
			),
			Tables::get( 'access' ) => array(
				'variation_id',
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

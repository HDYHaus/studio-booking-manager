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
		);

		foreach ( $required as $table ) {
			if ( '' === $table ) {
				continue;
			}

			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

			if ( false === $exists || null === $exists ) {
				return true;
			}
		}

		return false;
	}
}

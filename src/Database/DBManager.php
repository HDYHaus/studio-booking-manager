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

		if ( SBM_DB_VERSION !== $installed ) {
			( new Installer() )->install();
		}
	}
}

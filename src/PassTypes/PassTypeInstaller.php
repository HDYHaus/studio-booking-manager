<?php
/**
 * Pass type installer.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\PassTypes;

use StudioBookingManager\Database\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * Installs pass type database structures.
 */
final class PassTypeInstaller {
	/**
	 * Install pass type tables.
	 */
	public function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE " . Tables::get( 'pass_types' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			description longtext NULL,
			behaviour varchar(30) NOT NULL DEFAULT 'one_time',
			number_of_visits int(11) unsigned NULL,
			maximum_visits_per_week int(11) unsigned NULL,
			guest_allowance int(11) unsigned NOT NULL DEFAULT 0,
			booking_required tinyint(1) NOT NULL DEFAULT 0,
			booking_duration_minutes int(11) unsigned NULL,
			valid_for int(11) unsigned NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY status (status)
		) $charset_collate;";

		dbDelta( $sql );
	}
}

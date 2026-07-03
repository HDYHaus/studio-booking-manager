<?php
/**
 * Database installer.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Database;

use StudioBookingManager\PassTypes\PassTypeInstaller;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and updates database tables.
 */
final class Installer {
	/**
	 * Run installer.
	 */
	public function install(): void {
		$this->create_tables();
		( new PassTypeInstaller() )->install();
		$this->create_default_options();
		$this->maybe_create_default_location();
		update_option( 'sbm_db_version', SBM_DB_VERSION );
	}

	/**
	 * Create custom tables.
	 */
	private function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = array();

		$sql[] = "CREATE TABLE " . Tables::get( 'people' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wp_user_id bigint(20) unsigned NULL,
			first_name varchar(100) NOT NULL DEFAULT '',
			last_name varchar(100) NOT NULL DEFAULT '',
			display_name varchar(190) NOT NULL DEFAULT '',
			email varchar(190) NULL,
			phone varchar(50) NULL,
			qr_token varchar(64) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			notes longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY qr_token (qr_token),
			KEY wp_user_id (wp_user_id),
			KEY email (email),
			KEY status (status)
		) $charset_collate;";

		$sql[] = "CREATE TABLE " . Tables::get( 'locations' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			slug varchar(190) NOT NULL,
			address text NULL,
			timezone varchar(100) NOT NULL DEFAULT '',
			capacity int(11) unsigned NOT NULL DEFAULT 0,
			opening_hours longtext NULL,
			calendar_id varchar(190) NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			is_default tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status),
			KEY is_default (is_default)
		) $charset_collate;";

		$sql[] = "CREATE TABLE " . Tables::get( 'access' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			person_id bigint(20) unsigned NOT NULL,
			location_id bigint(20) unsigned NOT NULL,
			wp_user_id bigint(20) unsigned NULL,
			order_id bigint(20) unsigned NULL,
			product_id bigint(20) unsigned NULL,
			variation_id bigint(20) unsigned NULL,
			access_type varchar(30) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			total_credits int(11) unsigned NULL,
			remaining_credits int(11) unsigned NULL,
			weekly_limit int(11) unsigned NULL,
			guest_limit int(11) unsigned NOT NULL DEFAULT 0,
			starts_at datetime NULL,
			expires_at datetime NULL,
			metadata longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY person_id (person_id),
			KEY location_id (location_id),
			KEY wp_user_id (wp_user_id),
			KEY order_id (order_id),
			KEY access_type (access_type),
			KEY status (status),
			KEY expires_at (expires_at)
		) $charset_collate;";

		$sql[] = "CREATE TABLE " . Tables::get( 'visits' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			person_id bigint(20) unsigned NOT NULL,
			location_id bigint(20) unsigned NOT NULL,
			access_id bigint(20) unsigned NULL,
			booking_id bigint(20) unsigned NULL,
			status varchar(20) NOT NULL DEFAULT 'expected',
			visit_date date NULL,
			scheduled_start datetime NULL,
			scheduled_end datetime NULL,
			checked_in_at datetime NULL,
			checked_out_at datetime NULL,
			guest_count int(11) unsigned NOT NULL DEFAULT 0,
			guest_names longtext NULL,
			checkin_method varchar(30) NULL,
			checked_in_by bigint(20) unsigned NULL,
			checked_out_by bigint(20) unsigned NULL,
			notes longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY person_id (person_id),
			KEY location_id (location_id),
			KEY access_id (access_id),
			KEY visit_date (visit_date),
			KEY scheduled_start (scheduled_start),
			KEY status (status),
			KEY checked_in_at (checked_in_at),
			KEY checked_out_at (checked_out_at)
		) $charset_collate;";

		$sql[] = "CREATE TABLE " . Tables::get( 'bookings' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			person_id bigint(20) unsigned NOT NULL,
			location_id bigint(20) unsigned NOT NULL,
			access_id bigint(20) unsigned NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			starts_at datetime NOT NULL,
			ends_at datetime NOT NULL,
			guest_count int(11) unsigned NOT NULL DEFAULT 0,
			guest_names longtext NULL,
			calendar_event_id varchar(190) NULL,
			notes longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY person_id (person_id),
			KEY location_id (location_id),
			KEY access_id (access_id),
			KEY status (status),
			KEY starts_at (starts_at),
			KEY ends_at (ends_at),
			KEY calendar_event_id (calendar_event_id)
		) $charset_collate;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Create default options.
	 */
	private function create_default_options(): void {
		add_option( 'sbm_settings', array() );
		add_option( 'sbm_db_version', SBM_DB_VERSION );
	}

	/**
	 * Create default location when none exists.
	 */
	private function maybe_create_default_location(): void {
		global $wpdb;

		$table = Tables::get( 'locations' );

		if ( '' === $table ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Installer needs to check whether the table is empty during activation.
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );

		if ( $count > 0 ) {
			return;
		}

		$now      = current_time( 'mysql' );
		$timezone = wp_timezone_string();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Installer creates the initial default location during activation.
		$wpdb->insert(
			$table,
			array(
				'name'       => get_bloginfo( 'name' ),
				'slug'       => 'default',
				'timezone'   => $timezone,
				'capacity'   => 0,
				'status'     => 'active',
				'is_default' => 1,
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
		);
	}
}

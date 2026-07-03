<?php
/**
 * Database table helper.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Provides table names.
 */
final class Tables {
	/**
	 * Allowed table suffixes.
	 *
	 * @var array<string, bool>
	 */
	private const ALLOWED = array(
		'people'    => true,
		'locations' => true,
		'access'    => true,
		'bookings'  => true,
		'visits'    => true,
		'pass_types' => true,
		'notifications' => true,
	);

	/**
	 * Get a table name with WordPress prefix.
	 *
	 * @param string $name Unprefixed table suffix.
	 * @return string
	 */
	public static function get( string $name ): string {
		global $wpdb;

		$name = sanitize_key( $name );

		if ( ! isset( self::ALLOWED[ $name ] ) ) {
			return '';
		}

		return $wpdb->prefix . 'sbm_' . $name;
	}
	/**
	 * Get all table names.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		$tables = array();

		foreach ( array_keys( self::ALLOWED ) as $name ) {
			$tables[] = self::get( $name );
		}

		return $tables;
	}

}

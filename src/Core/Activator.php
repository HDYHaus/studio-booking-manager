<?php
/**
 * Plugin activation.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Core;

use StudioBookingManager\Database\Installer;
use StudioBookingManager\Roles\RoleInstaller;

defined( 'ABSPATH' ) || exit;

/**
 * Handles activation.
 */
final class Activator {
	/**
	 * Activate the plugin.
	 */
	public static function activate(): void {
		( new Installer() )->install();
		( new RoleInstaller() )->install();
		flush_rewrite_rules();
	}
}

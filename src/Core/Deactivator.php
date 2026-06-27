<?php
/**
 * Plugin deactivation.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Handles deactivation.
 */
final class Deactivator {
	/**
	 * Deactivate the plugin.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}

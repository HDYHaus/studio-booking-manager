<?php
/**
 * Plugin Name:       Studio Booking Manager
 * Plugin URI:        https://hdyhaus.com/wp-plugins/studio-booking-manager
 * Description:       Open source booking and operations management for studios, coworking spaces, creative hubs, libraries, and shared spaces.
 * Version:           0.13.16
 * Requires at least: 6.8
 * Requires PHP:      8.1
 * Author:            HDY Haus
 * Author URI:        https://hdyhaus.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       studio-booking-manager
 * Domain Path:       /languages
 *
 * @package StudioBookingManager
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'SBM_VERSION' ) ) {
	define( 'SBM_VERSION', '0.13.16' );
}

if ( ! defined( 'SBM_DB_VERSION' ) ) {
	define( 'SBM_DB_VERSION', '0.13.16' );
}

if ( ! defined( 'SBM_PLUGIN_FILE' ) ) {
	define( 'SBM_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'SBM_PLUGIN_DIR' ) ) {
	define( 'SBM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'SBM_PLUGIN_URL' ) ) {
	define( 'SBM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'SBM_PLUGIN_BASENAME' ) ) {
	define( 'SBM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

require_once SBM_PLUGIN_DIR . 'src/Core/Autoloader.php';
StudioBookingManager\Core\Autoloader::register();

register_activation_hook( __FILE__, array( 'StudioBookingManager\\Core\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'StudioBookingManager\\Core\\Deactivator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		StudioBookingManager\Core\Plugin::instance()->boot();
	}
);

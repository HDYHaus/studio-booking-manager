<?php
/**
 * Main plugin bootstrap.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Core;

use StudioBookingManager\Access\AccessAdmin;
use StudioBookingManager\Commerce\WooCommerce\OrderListener;
use StudioBookingManager\Admin\AdminAssets;
use StudioBookingManager\Admin\AdminMenu;
use StudioBookingManager\Database\DBManager;
use StudioBookingManager\Locations\LocationAdmin;
use StudioBookingManager\Operations\OperationsAdmin;
use StudioBookingManager\PassTypes\PassTypeAdmin;
use StudioBookingManager\People\PersonAdmin;
use StudioBookingManager\QR\QRAdmin;
use StudioBookingManager\Roles\RoleInstaller;
use StudioBookingManager\Settings\SettingsPage;
use StudioBookingManager\Visits\VisitAdmin;
use StudioBookingManager\WooCommerce\ProductPanel;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 */
final class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Get instance.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boot plugin services.
	 */
	public function boot(): void {
		( new DBManager() )->maybe_upgrade();
		( new RoleInstaller() )->install();
		( new AdminAssets() )->register();
		( new LocationAdmin() )->register();
		( new PersonAdmin() )->register();
		( new AccessAdmin() )->register();
		( new VisitAdmin() )->register();
		( new PassTypeAdmin() )->register();
		( new OperationsAdmin() )->register();
		( new QRAdmin() )->register();
		( new AdminMenu() )->register();
		( new SettingsPage() )->register();

		if ( class_exists( 'WooCommerce' ) ) {
			( new ProductPanel() )->register();
			( new OrderListener() )->register();
		}
	}
}

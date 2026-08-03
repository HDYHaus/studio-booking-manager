<?php
/**
 * Main plugin bootstrap.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Core;

use StudioBookingManager\Access\AccessAdmin;
use StudioBookingManager\Bookings\BookingAdmin;
use StudioBookingManager\Commerce\WooCommerce\BookingDateField;
use StudioBookingManager\Calendar\GoogleCalendarSync;
use StudioBookingManager\Commerce\WooCommerce\CheckoutFields;
use StudioBookingManager\Commerce\WooCommerce\LoopAddToCart;
use StudioBookingManager\Commerce\WooCommerce\OrderListener;
use StudioBookingManager\Admin\AdminAssets;
use StudioBookingManager\Admin\AdminMenu;
use StudioBookingManager\Database\DBManager;
use StudioBookingManager\Frontend\MemberAccount;
use StudioBookingManager\Frontend\MemberScheduleShortcode;
use StudioBookingManager\ImportExport\ImportExportAdmin;
use StudioBookingManager\Integrations\GravityFormsIntegration;
use StudioBookingManager\Integrations\IntegrationAdmin;
use StudioBookingManager\Locations\LocationAdmin;
use StudioBookingManager\Notifications\NotificationAdmin;
use StudioBookingManager\Notifications\NotificationService;
use StudioBookingManager\Operations\OperationsAdmin;
use StudioBookingManager\PassTypes\PassTypeAdmin;
use StudioBookingManager\People\PersonAdmin;
use StudioBookingManager\QR\QRAdmin;
use StudioBookingManager\Reports\ReportsAdmin;
use StudioBookingManager\Roles\RoleInstaller;
use StudioBookingManager\RSVP\RsvpAdmin;
use StudioBookingManager\RSVP\RsvpShortcode;
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
		( new BookingAdmin() )->register();
		( new GoogleCalendarSync() )->register();
		( new NotificationService() )->register();
		( new VisitAdmin() )->register();
		( new PassTypeAdmin() )->register();
		( new OperationsAdmin() )->register();
		( new QRAdmin() )->register();
		( new ReportsAdmin() )->register();
		( new RsvpAdmin() )->register();
		( new ImportExportAdmin() )->register();
		( new IntegrationAdmin() )->register();
		( new GravityFormsIntegration() )->register();
		( new AdminMenu() )->register();
		( new SettingsPage() )->register();
		( new MemberAccount() )->register();
		( new NotificationAdmin() )->register();
		( new MemberScheduleShortcode() )->register();
		( new RsvpShortcode() )->register();

		if ( class_exists( 'WooCommerce' ) ) {
			( new BookingDateField() )->register();
			( new CheckoutFields() )->register();
			( new LoopAddToCart() )->register();
			( new ProductPanel() )->register();
			( new OrderListener() )->register();
		}
	}
}

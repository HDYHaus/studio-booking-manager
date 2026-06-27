<?php
/**
 * Admin menu.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Admin;

use StudioBookingManager\Access\AccessAdmin;
use StudioBookingManager\Commerce\WooCommerce\CommerceAdmin;
use StudioBookingManager\Locations\LocationAdmin;
use StudioBookingManager\Operations\OperationsAdmin;
use StudioBookingManager\QR\QRAdmin;
use StudioBookingManager\UI\Stat;
use StudioBookingManager\People\PersonAdmin;
use StudioBookingManager\Visits\VisitAdmin;
use StudioBookingManager\Admin\PageHeader;
use StudioBookingManager\Settings\SettingsPage;
use StudioBookingManager\System\SystemHealth;

defined( 'ABSPATH' ) || exit;

/**
 * Registers admin screens.
 */
final class AdminMenu {
	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_menu(): void {
		$dashboard_capability = 'sbm_view_dashboard';

		add_menu_page(
			__( 'Studio Booking Manager', 'studio-booking-manager' ),
			__( 'Studio Booking', 'studio-booking-manager' ),
			$dashboard_capability,
			'sbm-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-building',
			56
		);

		add_submenu_page(
			'sbm-dashboard',
			__( 'Operations', 'studio-booking-manager' ),
			__( 'Operations', 'studio-booking-manager' ),
			'sbm_check_in',
			'sbm-operations',
			array( new OperationsAdmin(), 'render' )
		);

		add_submenu_page(
			'sbm-dashboard',
			__( 'QR Check-in', 'studio-booking-manager' ),
			__( 'QR Check-in', 'studio-booking-manager' ),
			'sbm_check_in',
			'sbm-qr-checkin',
			array( new QRAdmin(), 'render' )
		);

		add_submenu_page(
			'sbm-dashboard',
			__( 'People', 'studio-booking-manager' ),
			__( 'People', 'studio-booking-manager' ),
			'sbm_manage_people',
			'sbm-people',
			array( new PersonAdmin(), 'render' )
		);

		add_submenu_page(
			'sbm-dashboard',
			__( 'Access', 'studio-booking-manager' ),
			__( 'Access', 'studio-booking-manager' ),
			'sbm_manage_access',
			'sbm-access',
			array( new AccessAdmin(), 'render' )
		);

		add_submenu_page(
			'sbm-dashboard',
			__( 'Visits', 'studio-booking-manager' ),
			__( 'Visits', 'studio-booking-manager' ),
			'sbm_manage_visits',
			'sbm-visits',
			array( new VisitAdmin(), 'render' )
		);

		add_submenu_page(
			'sbm-dashboard',
			__( 'Locations', 'studio-booking-manager' ),
			__( 'Locations', 'studio-booking-manager' ),
			'sbm_manage_locations',
			'sbm-locations',
			array( new LocationAdmin(), 'render' )
		);


		add_submenu_page(
			'sbm-dashboard',
			__( 'Commerce', 'studio-booking-manager' ),
			__( 'Commerce', 'studio-booking-manager' ),
			'sbm_manage_settings',
			'sbm-commerce',
			array( new CommerceAdmin(), 'render' )
		);

		add_submenu_page(
			'sbm-dashboard',
			__( 'Reports', 'studio-booking-manager' ),
			__( 'Reports', 'studio-booking-manager' ),
			'sbm_view_reports',
			'sbm-reports',
			array( $this, 'render_placeholder' )
		);

		add_submenu_page(
			'sbm-dashboard',
			__( 'System Health', 'studio-booking-manager' ),
			__( 'System Health', 'studio-booking-manager' ),
			'sbm_manage_settings',
			'sbm-system-health',
			array( new SystemHealth(), 'render' )
		);

		add_submenu_page(
			'sbm-dashboard',
			__( 'Settings', 'studio-booking-manager' ),
			__( 'Settings', 'studio-booking-manager' ),
			'sbm_manage_settings',
			'sbm-settings',
			array( new SettingsPage(), 'render' )
		);
	}

	/**
	 * Render dashboard.
	 */
	public function render_dashboard(): void {
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( __( 'Studio Booking Manager', 'studio-booking-manager' ) ); ?>
			<p><?php echo esc_html__( 'Open source operations for shared spaces.', 'studio-booking-manager' ); ?></p>
			<div class="sbm-grid">
				<?php echo wp_kses_post( Stat::render( __( 'People', 'studio-booking-manager' ), '0' ) ); ?>
				<?php echo wp_kses_post( Stat::render( __( 'Locations', 'studio-booking-manager' ), '0' ) ); ?>
				<?php echo wp_kses_post( Stat::render( __( 'Active Access', 'studio-booking-manager' ), '0' ) ); ?>
				<?php echo wp_kses_post( Stat::render( __( 'Visits Today', 'studio-booking-manager' ), '0' ) ); ?>
			</div>
			<div class="sbm-card">
				<h2><?php echo esc_html__( 'People and Locations', 'studio-booking-manager' ); ?></h2>
				<p><?php echo esc_html__( 'Create people profiles and manage the physical spaces where access, visits, and operations happen.', 'studio-booking-manager' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render placeholder screens.
	 */
	public function render_placeholder(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$title = str_replace( 'sbm-', '', $page );
		$title = ucwords( str_replace( '-', ' ', $title ) );
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( $title ); ?>
			<p><?php echo esc_html__( 'This module is part of the Studio Booking Manager roadmap.', 'studio-booking-manager' ); ?></p>
		</div>
		<?php
	}
}

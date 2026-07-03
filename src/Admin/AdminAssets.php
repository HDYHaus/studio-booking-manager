<?php
/**
 * Shared admin assets.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers Studio Booking Manager admin assets.
 */
final class AdminAssets {
	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue assets for Studio Booking Manager screens only.
	 *
	 * @param string $hook_suffix Current admin hook suffix.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, 'sbm-' ) && false === strpos( $hook_suffix, 'studio-booking' ) ) {
			return;
		}

		wp_enqueue_style(
			'studio-booking-manager-admin',
			SBM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			SBM_VERSION
		);

		wp_enqueue_script(
			'studio-booking-manager-admin',
			SBM_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			SBM_VERSION,
			true
		);
	}
}

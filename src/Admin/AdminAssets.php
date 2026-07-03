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

		$dependencies = array( 'jquery' );
		if ( $this->register_qr_script() ) {
			$dependencies[] = 'wc-qrcode';
		}

		wp_enqueue_script(
			'studio-booking-manager-admin',
			SBM_PLUGIN_URL . 'assets/js/admin.js',
			$dependencies,
			SBM_VERSION,
			true
		);
	}

	/**
	 * Register the bundled WooCommerce QR library when available.
	 */
	private function register_qr_script(): bool {
		if ( wp_script_is( 'wc-qrcode', 'registered' ) ) {
			return true;
		}

		$path = WP_PLUGIN_DIR . '/woocommerce/assets/js/jquery-qrcode/jquery.qrcode.min.js';
		if ( ! is_readable( $path ) ) {
			return false;
		}

		wp_register_script(
			'wc-qrcode',
			plugins_url( 'woocommerce/assets/js/jquery-qrcode/jquery.qrcode.min.js' ),
			array( 'jquery' ),
			SBM_VERSION,
			true
		);

		return true;
	}
}

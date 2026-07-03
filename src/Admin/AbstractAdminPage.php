<?php
/**
 * Shared admin page foundation.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for Studio Booking Manager admin pages.
 */
abstract class AbstractAdminPage {
	/**
	 * Capability required for page actions.
	 *
	 * @var string
	 */
	protected string $capability = 'sbm_view_dashboard';

	/**
	 * Verify a protected admin request.
	 *
	 * @param string $nonce_action Nonce action.
	 * @param string $permission_message Permission failure message.
	 */
	protected function verify_admin_request( string $nonce_action, string $permission_message ): void {
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html( $permission_message ) );
		}

		check_admin_referer( $nonce_action );
	}


	/**
	 * Verify a protected admin request with an explicit capability.
	 *
	 * @param string $capability Capability required.
	 * @param string $nonce_action Nonce action.
	 * @param string $permission_message Permission failure message.
	 */
	protected function verify_capability_request( string $capability, string $nonce_action, string $permission_message ): void {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html( $permission_message ) );
		}

		check_admin_referer( $nonce_action );
	}

	/**
	 * Render a standard admin page notice from the message query parameter.
	 *
	 * @param array<string,string> $messages Message map.
	 */
	protected function render_query_notice( array $messages ): void {
		$message = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' === $message || ! isset( $messages[ $message ] ) ) {
			return;
		}

		$type = ( 'error' === $message || str_ends_with( $message, '_error' ) ) ? 'error' : 'success';
		AdminNotices::render( $type, $messages[ $message ] );
	}

	/**
	 * Build an admin URL for a Studio Booking Manager page.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return string
	 */
	protected function admin_url( array $args ): string {
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}
}

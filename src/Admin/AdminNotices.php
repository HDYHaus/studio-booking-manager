<?php
/**
 * Shared admin notices.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders standard WordPress admin notices.
 */
final class AdminNotices {
	/**
	 * Render a notice.
	 *
	 * @param string $type Notice type.
	 * @param string $message Notice message.
	 */
	public static function render( string $type, string $message ): void {
		$allowed = array( 'success', 'error', 'warning', 'info' );
		$type    = in_array( $type, $allowed, true ) ? $type : 'info';

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}
}

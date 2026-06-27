<?php
/**
 * Plugin logger.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Logging;

defined( 'ABSPATH' ) || exit;

/**
 * Writes plugin logs when debugging is enabled.
 */
final class Logger {
	/**
	 * Log a message.
	 *
	 * @param string $message Log message.
	 * @param string $level   Log level.
	 */
	public static function log( string $message, string $level = 'info' ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$log_dir    = trailingslashit( $upload_dir['basedir'] ) . 'studio-booking-manager/logs';

		if ( ! wp_mkdir_p( $log_dir ) ) {
			return;
		}

		$file  = trailingslashit( $log_dir ) . 'studio-booking-manager.log';
		$entry = sprintf( "[%s] %s: %s\n", gmdate( 'Y-m-d H:i:s' ), strtoupper( $level ), $message );

		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( $wp_filesystem ) {
			$existing = $wp_filesystem->exists( $file ) ? $wp_filesystem->get_contents( $file ) : '';
			$wp_filesystem->put_contents( $file, $existing . $entry, FS_CHMOD_FILE );
		}
	}
}

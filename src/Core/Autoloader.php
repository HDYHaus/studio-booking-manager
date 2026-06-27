<?php
/**
 * Simple PSR-4 style autoloader.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Autoloads plugin classes.
 */
final class Autoloader {
	/**
	 * Register the autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Load a class file.
	 *
	 * @param string $class Class name.
	 */
	public static function autoload( string $class ): void {
		$prefix = 'StudioBookingManager\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
		$file     = SBM_PLUGIN_DIR . 'src/' . $relative . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}

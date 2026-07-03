<?php
/**
 * Test bootstrap for isolated unit tests.
 *
 * @package StudioBookingManager
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Return an untranslated string in isolated tests.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Minimal sanitize_key replacement for isolated tests.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Minimal absint replacement for isolated tests.
	 *
	 * @param mixed $maybeint Value.
	 * @return int
	 */
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	/**
	 * Fixed test clock.
	 *
	 * @param string $type Time type.
	 * @return int|string
	 */
	function current_time( $type ) {
		$timestamp = strtotime( '2026-07-03 12:00:00 UTC' );

		return 'timestamp' === $type ? $timestamp : gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'StudioBookingManager\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = dirname( __DIR__ ) . '/src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

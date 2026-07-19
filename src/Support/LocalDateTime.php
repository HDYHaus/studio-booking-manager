<?php
/**
 * Local datetime helpers.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Handles datetimes stored as local wall time.
 */
final class LocalDateTime {
	/**
	 * Convert a stored local datetime to a Unix timestamp in the given timezone.
	 */
	public static function timestamp( string $value, \DateTimeZone $timezone ): int {
		$value = trim( $value );

		foreach ( array( 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d' ) as $format ) {
			$datetime = \DateTimeImmutable::createFromFormat( '!' . $format, $value, $timezone );

			if ( $datetime instanceof \DateTimeImmutable ) {
				return $datetime->getTimestamp();
			}
		}

		$timestamp = strtotime( $value );

		return false === $timestamp ? time() : $timestamp;
	}

	/**
	 * Format a stored local datetime in the given timezone.
	 */
	public static function format( string $format, string $value, \DateTimeZone $timezone ): string {
		return wp_date( $format, self::timestamp( $value, $timezone ), $timezone );
	}
}

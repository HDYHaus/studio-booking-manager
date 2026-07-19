<?php
/**
 * Local datetime tests.
 *
 * @package StudioBookingManager
 */

declare(strict_types=1);

namespace StudioBookingManager\Tests\Unit\Support;

use DateTimeImmutable;
use DateTimeZone;
use StudioBookingManager\Support\LocalDateTime;

require_once dirname( __DIR__, 3 ) . '/src/Support/LocalDateTime.php';

/**
 * Tests local datetime handling.
 */
final class LocalDateTimeTest extends TestCase {
	/**
	 * Stored booking datetimes are local wall time for the location.
	 */
	public function test_stored_datetime_keeps_location_wall_time(): void {
		$timezone  = new DateTimeZone( 'Europe/Tirane' );
		$timestamp = LocalDateTime::timestamp( '2026-08-07 09:30:00', $timezone );
		$display   = ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone )->format( 'Y-m-d H:i:s' );

		$this->assert_same( '2026-08-07 09:30:00', $display );
	}
}

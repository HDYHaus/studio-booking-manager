<?php
/**
 * Gravity Forms integration tests.
 *
 * @package StudioBookingManager
 */

declare(strict_types=1);

namespace StudioBookingManager\Tests\Unit\Integrations;

use ReflectionClass;
use StudioBookingManager\Integrations\GravityFormsIntegration;

/**
 * Tests Gravity Forms integration helpers.
 */
final class GravityFormsIntegrationTest extends TestCase {
	/**
	 * Date-only bookings use duration for the end time.
	 */
	public function test_date_only_booking_uses_duration_for_end_time(): void {
		$integration = new GravityFormsIntegration();
		$reflection  = new ReflectionClass( $integration );
		$method      = $reflection->getMethod( 'booking_end' );
		$method->setAccessible( true );

		$end = $method->invoke(
			$integration,
			array(
				'3' => '2026-09-08',
				'5' => '3',
			),
			array(
				'booking_date'   => '3',
				'end_time'       => '',
				'duration_hours' => '5',
			),
			'2026-09-08 09:00:00',
			60
		);

		$this->assert_same( '2026-09-08 12:00:00', $end );
	}

	/**
	 * Duration choice labels are parsed as hour counts.
	 */
	public function test_duration_hour_labels_are_parsed(): void {
		$integration = new GravityFormsIntegration();
		$reflection  = new ReflectionClass( $integration );
		$method      = $reflection->getMethod( 'booking_end' );
		$method->setAccessible( true );

		$end = $method->invoke(
			$integration,
			array(
				'3' => '08/08/2026',
				'5' => '2 Hours',
			),
			array(
				'booking_date'   => '3',
				'end_time'       => '',
				'duration_hours' => '5',
			),
			'2026-08-08 09:30:00',
			60
		);

		$this->assert_same( '2026-08-08 11:30:00', $end );
	}
}

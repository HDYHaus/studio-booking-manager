<?php
/**
 * Support test helpers.
 *
 * @package StudioBookingManager
 */

declare(strict_types=1);

namespace StudioBookingManager\Tests\Unit\Support;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use RuntimeException;

if ( class_exists( PhpUnitTestCase::class ) ) {
	abstract class TestCase extends PhpUnitTestCase {
		/**
		 * Assert two values are identical.
		 *
		 * @param mixed  $expected Expected value.
		 * @param mixed  $actual Actual value.
		 * @param string $message Failure message.
		 */
		protected function assert_same( $expected, $actual, string $message = 'Expected values to match.' ): void {
			$this->assertSame( $expected, $actual, $message );
		}
	}

	return;
}

abstract class TestCase {
	/**
	 * Assert two values are identical.
	 *
	 * @param mixed  $expected Expected value.
	 * @param mixed  $actual Actual value.
	 * @param string $message Failure message.
	 */
	protected function assert_same( $expected, $actual, string $message = 'Expected values to match.' ): void {
		if ( $expected !== $actual ) {
			throw new RuntimeException( $message . ' Expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . '.' );
		}
	}
}

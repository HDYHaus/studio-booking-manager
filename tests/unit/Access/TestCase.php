<?php
/**
 * Minimal test assertions.
 *
 * @package StudioBookingManager
 */

declare(strict_types=1);

namespace StudioBookingManager\Tests\Unit\Access;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use RuntimeException;

/**
 * Base test case for dependency-free unit tests.
 */
if ( class_exists( PhpUnitTestCase::class ) ) {
	abstract class TestCase extends PhpUnitTestCase {
		/**
		 * Assert a condition is true.
		 *
		 * @param bool   $condition Condition.
		 * @param string $message Failure message.
		 */
		protected function assert_true( bool $condition, string $message = 'Expected condition to be true.' ): void {
			$this->assertTrue( $condition, $message );
		}

		/**
		 * Assert a condition is false.
		 *
		 * @param bool   $condition Condition.
		 * @param string $message Failure message.
		 */
		protected function assert_false( bool $condition, string $message = 'Expected condition to be false.' ): void {
			$this->assertFalse( $condition, $message );
		}

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
	 * Assert a condition is true.
	 *
	 * @param bool   $condition Condition.
	 * @param string $message Failure message.
	 */
	protected function assert_true( bool $condition, string $message = 'Expected condition to be true.' ): void {
		if ( ! $condition ) {
			throw new RuntimeException( $message );
		}
	}

	/**
	 * Assert a condition is false.
	 *
	 * @param bool   $condition Condition.
	 * @param string $message Failure message.
	 */
	protected function assert_false( bool $condition, string $message = 'Expected condition to be false.' ): void {
		if ( $condition ) {
			throw new RuntimeException( $message );
		}
	}

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

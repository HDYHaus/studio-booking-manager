<?php
/**
 * Integration test helpers.
 *
 * @package StudioBookingManager
 */

declare(strict_types=1);

namespace StudioBookingManager\Tests\Unit\Integrations;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use RuntimeException;

/**
 * Base test case for integration unit tests.
 */
if ( class_exists( PhpUnitTestCase::class ) ) {
	abstract class TestCase extends PhpUnitTestCase {
		/**
		 * Reset isolated WordPress test state.
		 */
		protected function set_up_test_state(): void {
			$GLOBALS['sbm_test_options'] = array();
		}

		/**
		 * Reset state before each PHPUnit test.
		 */
		protected function setUp(): void {
			parent::setUp();
			$this->set_up_test_state();
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
	 * Reset isolated WordPress test state.
	 */
	protected function set_up_test_state(): void {
		$GLOBALS['sbm_test_options'] = array();
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

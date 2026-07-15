<?php
/**
 * Commerce test helpers.
 *
 * @package StudioBookingManager
 */

declare(strict_types=1);

namespace StudioBookingManager\Tests\Unit\Commerce;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use RuntimeException;

/**
 * Base test case for commerce unit tests.
 */
if ( class_exists( PhpUnitTestCase::class ) ) {
	abstract class TestCase extends PhpUnitTestCase {
		/**
		 * Reset isolated WordPress/WooCommerce test state.
		 */
		protected function set_up_test_state(): void {
			$this->reset_state();
		}

		/**
		 * Reset state before each PHPUnit test.
		 */
		protected function setUp(): void {
			parent::setUp();
			$this->reset_state();
		}

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

		/**
		 * Reset globals.
		 */
		private function reset_state(): void {
			$GLOBALS['sbm_test_options']           = array();
			$GLOBALS['sbm_test_post_meta']         = array();
			$GLOBALS['sbm_test_wc_notices']        = array();
			$GLOBALS['sbm_test_enqueued_styles']   = array();
			$GLOBALS['sbm_test_enqueued_scripts']  = array();
			$GLOBALS['sbm_test_localized_scripts'] = array();
			$GLOBALS['sbm_test_is_checkout']       = false;
			$GLOBALS['sbm_test_wc']                = new \SBM_Test_WC();
			$GLOBALS['wpdb']                       = new \wpdb();
			$_POST                                 = array();
		}
	}

	return;
}

abstract class TestCase {
	/**
	 * Reset isolated WordPress/WooCommerce test state.
	 */
	protected function set_up_test_state(): void {
		$GLOBALS['sbm_test_options']           = array();
		$GLOBALS['sbm_test_post_meta']         = array();
		$GLOBALS['sbm_test_wc_notices']        = array();
		$GLOBALS['sbm_test_enqueued_styles']   = array();
		$GLOBALS['sbm_test_enqueued_scripts']  = array();
		$GLOBALS['sbm_test_localized_scripts'] = array();
		$GLOBALS['sbm_test_is_checkout']       = false;
		$GLOBALS['sbm_test_wc']                = new \SBM_Test_WC();
		$GLOBALS['wpdb']                       = new \wpdb();
		$_POST                                 = array();
	}

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

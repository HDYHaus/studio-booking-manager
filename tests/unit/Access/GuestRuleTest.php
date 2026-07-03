<?php
/**
 * Tests for the guest access rule.
 *
 * @package StudioBookingManager
 */

declare(strict_types=1);

namespace StudioBookingManager\Tests\Unit\Access;

use StudioBookingManager\Access\Rules\GuestRule;
use StudioBookingManager\Access\Validators\AccessValidationResult;

/**
 * @covers \StudioBookingManager\Access\Rules\GuestRule
 */
final class GuestRuleTest extends TestCase {
	/**
	 * Access fails when selected guests exceed the record limit.
	 */
	public function test_guest_count_above_limit_fails(): void {
		$result = new AccessValidationResult();
		$access = (object) array(
			'guest_limit' => 1,
		);

		( new GuestRule() )->validate( $access, array( 'guest_count' => 2 ), $result );

		$this->assert_same( 'guest_limit_exceeded', $result->first_error_code() );
	}

	/**
	 * Access passes when selected guests are within the record limit.
	 */
	public function test_guest_count_within_limit_passes(): void {
		$result = new AccessValidationResult();
		$access = (object) array(
			'guest_limit' => 2,
		);

		( new GuestRule() )->validate( $access, array( 'guest_count' => 2 ), $result );

		$this->assert_true( $result->is_valid() );
	}
}

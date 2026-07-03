<?php
/**
 * Tests for the credit access rule.
 *
 * @package StudioBookingManager
 */

declare(strict_types=1);

namespace StudioBookingManager\Tests\Unit\Access;

use StudioBookingManager\Access\Rules\CreditRule;
use StudioBookingManager\Access\Validators\AccessValidationResult;

/**
 * @covers \StudioBookingManager\Access\Rules\CreditRule
 */
final class CreditRuleTest extends TestCase {
	/**
	 * Visit passes with zero credits are invalid.
	 */
	public function test_visit_pass_with_zero_credits_fails(): void {
		$result = new AccessValidationResult();
		$access = (object) array(
			'access_type'       => 'visit_pass',
			'remaining_credits' => 0,
		);

		( new CreditRule() )->validate( $access, array(), $result );

		$this->assert_same( 'no_remaining_credits', $result->first_error_code() );
	}

	/**
	 * Membership access does not require finite credits.
	 */
	public function test_membership_without_credits_passes(): void {
		$result = new AccessValidationResult();
		$access = (object) array(
			'access_type'       => 'membership',
			'remaining_credits' => null,
		);

		( new CreditRule() )->validate( $access, array(), $result );

		$this->assert_true( $result->is_valid() );
	}
}

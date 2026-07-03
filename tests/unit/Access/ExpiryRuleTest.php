<?php
/**
 * Tests for the expiry access rule.
 *
 * @package StudioBookingManager
 */

declare(strict_types=1);

namespace StudioBookingManager\Tests\Unit\Access;

use StudioBookingManager\Access\Rules\ExpiryRule;
use StudioBookingManager\Access\Validators\AccessValidationResult;

/**
 * @covers \StudioBookingManager\Access\Rules\ExpiryRule
 */
final class ExpiryRuleTest extends TestCase {
	/**
	 * Future start dates are not active yet.
	 */
	public function test_future_start_date_fails(): void {
		$result = new AccessValidationResult();
		$access = (object) array(
			'starts_at'  => '2026-07-04 12:00:00',
			'expires_at' => '',
		);

		( new ExpiryRule() )->validate( $access, array(), $result );

		$this->assert_same( 'access_not_started', $result->first_error_code() );
	}

	/**
	 * Past expiry dates are expired.
	 */
	public function test_past_expiry_date_fails(): void {
		$result = new AccessValidationResult();
		$access = (object) array(
			'starts_at'  => '',
			'expires_at' => '2026-07-02 12:00:00',
		);

		( new ExpiryRule() )->validate( $access, array(), $result );

		$this->assert_same( 'access_expired', $result->first_error_code() );
	}

	/**
	 * Current access windows are valid.
	 */
	public function test_current_access_window_passes(): void {
		$result = new AccessValidationResult();
		$access = (object) array(
			'starts_at'  => '2026-07-02 12:00:00',
			'expires_at' => '2026-07-04 12:00:00',
		);

		( new ExpiryRule() )->validate( $access, array(), $result );

		$this->assert_true( $result->is_valid() );
	}
}

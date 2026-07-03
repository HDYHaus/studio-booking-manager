<?php
/**
 * Tests for access validation results.
 *
 * @package StudioBookingManager
 */

declare(strict_types=1);

namespace StudioBookingManager\Tests\Unit\Access;

use StudioBookingManager\Access\Validators\AccessValidationResult;

/**
 * @covers \StudioBookingManager\Access\Validators\AccessValidationResult
 */
final class AccessValidationResultTest extends TestCase {
	/**
	 * Validation results start valid.
	 */
	public function test_result_starts_valid(): void {
		$result = new AccessValidationResult();

		$this->assert_true( $result->is_valid() );
		$this->assert_same( '', $result->first_error_code() );
		$this->assert_same( '', $result->first_error_message() );
	}

	/**
	 * First error helpers expose the first stored issue.
	 */
	public function test_first_error_helpers_return_first_error(): void {
		$result = new AccessValidationResult();
		$result->add_error( 'No Remaining Credits!', 'No credits left.' );
		$result->add_error( 'expired', 'Expired.' );

		$this->assert_false( $result->is_valid() );
		$this->assert_same( 'noremainingcredits', $result->first_error_code() );
		$this->assert_same( 'No credits left.', $result->first_error_message() );
	}
}

<?php
/**
 * Access validation result.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Access\Validators;

defined( 'ABSPATH' ) || exit;

/**
 * Value object for access validation outcomes.
 */
final class AccessValidationResult {
	/**
	 * Validation errors.
	 *
	 * @var array<string,string>
	 */
	private array $errors = array();

	/**
	 * Add an error.
	 *
	 * @param string $code Error code.
	 * @param string $message Error message.
	 * @return self
	 */
	public function add_error( string $code, string $message ): self {
		$this->errors[ sanitize_key( $code ) ] = $message;

		return $this;
	}

	/**
	 * Whether validation passed.
	 *
	 * @return bool
	 */
	public function is_valid(): bool {
		return empty( $this->errors );
	}

	/**
	 * Get all errors.
	 *
	 * @return array<string,string>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Get the first error code.
	 *
	 * @return string
	 */
	public function first_error_code(): string {
		$codes = array_keys( $this->errors );

		return isset( $codes[0] ) ? (string) $codes[0] : '';
	}

	/**
	 * Get the first error message.
	 *
	 * @return string
	 */
	public function first_error_message(): string {
		$messages = array_values( $this->errors );

		return isset( $messages[0] ) ? (string) $messages[0] : '';
	}
}

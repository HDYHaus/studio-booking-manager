<?php
/**
 * Visit pass access type.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Access\Types;

defined( 'ABSPATH' ) || exit;

/**
 * Represents a credit-based visit pass access model.
 */
final class VisitPass {
	/**
	 * Get access type key.
	 */
	public function key(): string {
		return 'visit_pass';
	}
}

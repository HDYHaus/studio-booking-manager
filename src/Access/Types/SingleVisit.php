<?php
/**
 * Single visit access type.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Access\Types;

defined( 'ABSPATH' ) || exit;

/**
 * Represents a single-visit access model.
 */
final class SingleVisit {
	/**
	 * Get access type key.
	 */
	public function key(): string {
		return 'single_visit';
	}
}

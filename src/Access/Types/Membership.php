<?php
/**
 * Membership access type.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Access\Types;

defined( 'ABSPATH' ) || exit;

/**
 * Represents an ongoing membership access model.
 */
final class Membership {
	/**
	 * Get access type key.
	 */
	public function key(): string {
		return 'membership';
	}
}

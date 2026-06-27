<?php
/**
 * Badge UI component.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Renders status badges.
 */
final class Badge {
	/**
	 * Render a status badge.
	 *
	 * @param string $label Badge label.
	 * @param string $type Badge type.
	 * @return string
	 */
	public static function render( string $label, string $type = 'neutral' ): string {
		return sprintf(
			'<span class="sbm-badge sbm-badge-%1$s">%2$s</span>',
			esc_attr( sanitize_html_class( $type ) ),
			esc_html( $label )
		);
	}
}

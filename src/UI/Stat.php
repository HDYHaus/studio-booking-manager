<?php
/**
 * Stat UI component.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Renders dashboard statistics.
 */
final class Stat {
	/**
	 * Render a stat card.
	 *
	 * @param string $label Stat label.
	 * @param string $value Stat value.
	 * @return string
	 */
	public static function render( string $label, string $value ): string {
		return sprintf(
			'<div class="sbm-stat"><span class="sbm-stat-value">%1$s</span><span class="sbm-stat-label">%2$s</span></div>',
			esc_html( $value ),
			esc_html( $label )
		);
	}
}

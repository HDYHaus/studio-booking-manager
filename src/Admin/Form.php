<?php
/**
 * Shared form helpers.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Small helpers for consistent admin form controls.
 */
final class Form {
	/**
	 * Render a text input row.
	 *
	 * @param string $id Field ID.
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @param string $value Field value.
	 * @param bool   $required Whether field is required.
	 */
	public static function text_row( string $id, string $name, string $label, string $value = '', bool $required = false ): void {
		printf(
			'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input name="%3$s" id="%1$s" type="text" class="regular-text" value="%4$s" %5$s></td></tr>',
			esc_attr( $id ),
			esc_html( $label ),
			esc_attr( $name ),
			esc_attr( $value ),
			$required ? 'required' : ''
		);
	}
}

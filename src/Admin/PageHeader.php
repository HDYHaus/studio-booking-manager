<?php
/**
 * Shared admin page header.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders standard admin page headers.
 */
final class PageHeader {
	/**
	 * Render a page header.
	 *
	 * @param string      $title Page title.
	 * @param string|null $action_url Optional action URL.
	 * @param string|null $action_label Optional action label.
	 */
	public static function render( string $title, ?string $action_url = null, ?string $action_label = null ): void {
		if ( null !== $action_url && null !== $action_label ) {
			printf(
				'<h1 class="wp-heading-inline">%1$s</h1> <a href="%2$s" class="page-title-action">%3$s</a><hr class="wp-header-end">',
				esc_html( $title ),
				esc_url( $action_url ),
				esc_html( $action_label )
			);
			return;
		}

		printf( '<h1>%s</h1>', esc_html( $title ) );
	}
}

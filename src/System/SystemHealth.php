<?php
/**
 * System health screen.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\System;

use StudioBookingManager\Admin\PageHeader;
use StudioBookingManager\Database\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * Renders system health information.
 */
final class SystemHealth {
	/**
	 * Render system health page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'sbm_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to view system health.', 'studio-booking-manager' ) );
		}

		global $wpdb;

		$tables = Tables::all();
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( __( 'System Health', 'studio-booking-manager' ) ); ?>
			<div class="sbm-card sbm-card-wide">
				<h2><?php echo esc_html__( 'Environment', 'studio-booking-manager' ); ?></h2>
				<table class="widefat striped">
					<tbody>
						<tr><th><?php echo esc_html__( 'Plugin Version', 'studio-booking-manager' ); ?></th><td><?php echo esc_html( SBM_VERSION ); ?></td></tr>
						<tr><th><?php echo esc_html__( 'Database Version', 'studio-booking-manager' ); ?></th><td><?php echo esc_html( get_option( 'sbm_db_version', 'not installed' ) ); ?></td></tr>
						<tr><th><?php echo esc_html__( 'WordPress Version', 'studio-booking-manager' ); ?></th><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
						<tr><th><?php echo esc_html__( 'PHP Version', 'studio-booking-manager' ); ?></th><td><?php echo esc_html( PHP_VERSION ); ?></td></tr>
					</tbody>
				</table>
			</div>
			<div class="sbm-card sbm-card-wide">
				<h2><?php echo esc_html__( 'Database Tables', 'studio-booking-manager' ); ?></h2>
				<table class="widefat striped">
					<thead><tr><th><?php echo esc_html__( 'Table', 'studio-booking-manager' ); ?></th><th><?php echo esc_html__( 'Status', 'studio-booking-manager' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( $tables as $table ) : ?>
							<?php
							$table_name = preg_replace( '/[^A-Za-z0-9_]/', '', $table );
							$exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostics screen intentionally checks table existence.
							?>
							<tr>
								<td><?php echo esc_html( $table_name ); ?></td>
								<td><?php echo $exists ? esc_html__( 'Available', 'studio-booking-manager' ) : esc_html__( 'Missing', 'studio-booking-manager' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}

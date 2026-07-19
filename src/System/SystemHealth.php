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
			<div class="sbm-card sbm-card-wide">
				<h2><?php echo esc_html__( 'Booking Diagnostics', 'studio-booking-manager' ); ?></h2>
				<?php $this->render_booking_diagnostics(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render raw booking table diagnostics for production troubleshooting.
	 */
	private function render_booking_diagnostics(): void {
		global $wpdb;

		$table = Tables::get( 'bookings' );

		if ( '' === $table ) {
			echo '<p>' . esc_html__( 'Bookings table name could not be resolved.', 'studio-booking-manager' ) . '</p>';
			return;
		}

		$id_column = $this->booking_id_column( $table );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Diagnostic reads against known plugin table.
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
		$count_error = $wpdb->last_error;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT `{$id_column}` AS id, person_id, location_id, status, visibility, starts_at, ends_at, guest_count FROM %i ORDER BY `{$id_column}` DESC LIMIT 5", $table ) );
		$rows_error = $wpdb->last_error;
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		?>
		<table class="widefat striped">
			<tbody>
				<tr><th><?php echo esc_html__( 'Bookings table', 'studio-booking-manager' ); ?></th><td><?php echo esc_html( $table ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Raw booking count', 'studio-booking-manager' ); ?></th><td><?php echo esc_html( null === $count ? 'unknown' : (string) absint( $count ) ); ?></td></tr>
				<?php if ( '' !== $count_error ) : ?>
					<tr><th><?php echo esc_html__( 'Count error', 'studio-booking-manager' ); ?></th><td><?php echo esc_html( $count_error ); ?></td></tr>
				<?php endif; ?>
				<?php if ( '' !== $rows_error ) : ?>
					<tr><th><?php echo esc_html__( 'Row query error', 'studio-booking-manager' ); ?></th><td><?php echo esc_html( $rows_error ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php if ( is_array( $rows ) && ! empty( $rows ) ) : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'ID', 'studio-booking-manager' ); ?></th>
						<th><?php echo esc_html__( 'Person', 'studio-booking-manager' ); ?></th>
						<th><?php echo esc_html__( 'Location', 'studio-booking-manager' ); ?></th>
						<th><?php echo esc_html__( 'Status', 'studio-booking-manager' ); ?></th>
						<th><?php echo esc_html__( 'Visibility', 'studio-booking-manager' ); ?></th>
						<th><?php echo esc_html__( 'Starts', 'studio-booking-manager' ); ?></th>
						<th><?php echo esc_html__( 'Ends', 'studio-booking-manager' ); ?></th>
						<th><?php echo esc_html__( 'Guests', 'studio-booking-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( (string) absint( $row->id ) ); ?></td>
							<td><?php echo esc_html( (string) absint( $row->person_id ) ); ?></td>
							<td><?php echo esc_html( (string) absint( $row->location_id ) ); ?></td>
							<td><?php echo esc_html( (string) $row->status ); ?></td>
							<td><?php echo esc_html( (string) $row->visibility ); ?></td>
							<td><?php echo esc_html( (string) $row->starts_at ); ?></td>
							<td><?php echo esc_html( (string) $row->ends_at ); ?></td>
							<td><?php echo esc_html( (string) absint( $row->guest_count ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Detect current or legacy booking ID column.
	 */
	private function booking_id_column( string $table ): string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema diagnostics against known plugin table.
		$has_id = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table, 'id' ) );

		return null !== $has_id && false !== $has_id ? 'id' : 'booking_id';
	}
}

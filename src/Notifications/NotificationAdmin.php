<?php
/**
 * Notification admin screen.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Notifications;

use StudioBookingManager\Admin\PageHeader;
use StudioBookingManager\UI\Badge;

defined( 'ABSPATH' ) || exit;

/**
 * Renders notification activity.
 */
final class NotificationAdmin {
	/**
	 * Register hooks.
	 */
	public function register(): void {}

	/**
	 * Render screen.
	 */
	public function render(): void {
		if ( ! current_user_can( 'sbm_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to view notification activity.', 'studio-booking-manager' ) );
		}

		$records = ( new NotificationRepository() )->recent();
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( __( 'Notifications', 'studio-booking-manager' ) ); ?>
			<div class="sbm-card sbm-card-wide">
				<table class="widefat striped sbm-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'When', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Type', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Recipient', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Subject', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Error', 'studio-booking-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $records ) ) : ?>
							<tr><td colspan="6"><?php echo esc_html__( 'No notification activity yet.', 'studio-booking-manager' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $records as $record ) : ?>
							<tr>
								<td><?php echo esc_html( (string) $record->created_at ); ?></td>
								<td><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $record->notification_type ) ) ); ?></td>
								<td><?php echo esc_html( $this->recipient_label( $record ) ); ?></td>
								<td><?php echo esc_html( (string) $record->subject ); ?></td>
								<td><?php echo wp_kses_post( Badge::render( ucfirst( (string) $record->status ), (string) $record->status ) ); ?></td>
								<td><?php echo esc_html( (string) $record->error_message ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Recipient label.
	 *
	 * @param object $record Notification row.
	 */
	private function recipient_label( object $record ): string {
		$name  = isset( $record->recipient_name ) ? (string) $record->recipient_name : '';
		$email = isset( $record->recipient_email ) ? (string) $record->recipient_email : '';

		return '' !== $name ? $name . ' <' . $email . '>' : $email;
	}
}

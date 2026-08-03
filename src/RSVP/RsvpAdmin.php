<?php
/**
 * RSVP admin screens.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\RSVP;

use StudioBookingManager\Admin\AbstractAdminPage;
use StudioBookingManager\Admin\PageHeader;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and handles RSVP admin screens.
 */
final class RsvpAdmin extends AbstractAdminPage {
	/**
	 * Capability required to manage RSVPs.
	 *
	 * @var string
	 */
	protected string $capability = 'sbm_manage_bookings';

	/**
	 * RSVP service.
	 *
	 * @var RsvpService
	 */
	private RsvpService $service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service = new RsvpService();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_post_sbm_export_rsvps', array( $this, 'handle_export' ) );
	}

	/**
	 * Handle RSVP CSV export.
	 */
	public function handle_export(): void {
		$this->verify_admin_request( 'sbm_export_rsvps', __( 'You do not have permission to export RSVPs.', 'studio-booking-manager' ) );

		$post_id = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rows    = $this->service->for_post( $post_id );
		$title   = $post_id > 0 ? get_the_title( $post_id ) : __( 'RSVPs', 'studio-booking-manager' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=sbm-rsvps-' . $post_id . '.csv' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV export streams directly to the HTTP response.
		$handle = fopen( 'php://output', 'w' );

		if ( false === $handle ) {
			exit;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CSV export streams directly to the HTTP response.
		fputcsv( $handle, array( 'Event', 'Name', 'Email', 'Guests', 'Guest Names', 'Notes', 'Status', 'Created At', 'Updated At' ) );

		foreach ( $rows as $row ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CSV export streams directly to the HTTP response.
			fputcsv(
				$handle,
				array(
					wp_strip_all_tags( $title ),
					(string) $row->attendee_name,
					(string) $row->attendee_email,
					(string) absint( $row->guest_count ),
					(string) $row->guest_names,
					(string) $row->notes,
					$this->status_label( (string) $row->status ),
					(string) $row->created_at,
					(string) $row->updated_at,
				)
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CSV export streams directly to the HTTP response.
		fclose( $handle );
		exit;
	}

	/**
	 * Render screen.
	 */
	public function render(): void {
		$post_id = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $post_id > 0 ) {
			$this->render_post_rsvps( $post_id );
			return;
		}

		$this->render_summary();
	}

	/**
	 * Render RSVP event summary.
	 */
	private function render_summary(): void {
		$summaries = $this->service->summaries();
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( __( 'RSVPs', 'studio-booking-manager' ) ); ?>
			<div class="sbm-card sbm-card-wide">
				<table class="widefat striped sbm-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Event Post', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'RSVPs', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Guests', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Total Headcount', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Last Updated', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Actions', 'studio-booking-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $summaries ) ) : ?>
							<tr><td colspan="6"><?php echo esc_html__( 'No RSVPs found yet. Add [sbm_rsvp] to a public post to start collecting responses.', 'studio-booking-manager' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $summaries as $summary ) : ?>
							<?php $post_id = absint( $summary->post_id ); ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $this->post_title( $post_id ) ); ?></strong>
									<br><a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html__( 'View share page', 'studio-booking-manager' ); ?></a>
								</td>
								<td><?php echo esc_html( (string) absint( $summary->rsvp_count ) ); ?></td>
								<td><?php echo esc_html( (string) absint( $summary->guest_count ) ); ?></td>
								<td><?php echo esc_html( (string) absint( $summary->headcount ) ); ?></td>
								<td><?php echo esc_html( $this->format_datetime( (string) $summary->last_updated ) ); ?></td>
								<td>
									<a class="button button-small" href="<?php echo esc_url( $this->post_rsvps_url( $post_id ) ); ?>"><?php echo esc_html__( 'View RSVPs', 'studio-booking-manager' ); ?></a>
									<a class="button button-small" href="<?php echo esc_url( $this->export_url( $post_id ) ); ?>"><?php echo esc_html__( 'Export CSV', 'studio-booking-manager' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render RSVPs for one post.
	 *
	 * @param int $post_id Post ID.
	 */
	private function render_post_rsvps( int $post_id ): void {
		$rows   = $this->service->for_post( $post_id );
		$totals = $this->service->totals_for_post( $post_id );
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( sprintf( /* translators: %s: post title. */ __( 'RSVPs: %s', 'studio-booking-manager' ), $this->post_title( $post_id ) ) ); ?>
			<p>
				<a class="button" href="<?php echo esc_url( $this->admin_url( array( 'page' => 'sbm-rsvps' ) ) ); ?>"><?php echo esc_html__( 'All RSVP Events', 'studio-booking-manager' ); ?></a>
				<a class="button" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html__( 'View Share Page', 'studio-booking-manager' ); ?></a>
				<a class="button" href="<?php echo esc_url( $this->export_url( $post_id ) ); ?>"><?php echo esc_html__( 'Export CSV', 'studio-booking-manager' ); ?></a>
			</p>
			<div class="sbm-grid">
				<?php $this->render_stat( __( 'RSVPs', 'studio-booking-manager' ), (string) $totals['rsvp_count'] ); ?>
				<?php $this->render_stat( __( 'Guests', 'studio-booking-manager' ), (string) $totals['guest_count'] ); ?>
				<?php $this->render_stat( __( 'Total Headcount', 'studio-booking-manager' ), (string) $totals['headcount'] ); ?>
			</div>
			<div class="sbm-card sbm-card-wide">
				<table class="widefat striped sbm-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Name', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Email', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Guests', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Guest Names', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Notes', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Updated', 'studio-booking-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $rows ) ) : ?>
							<tr><td colspan="6"><?php echo esc_html__( 'No RSVPs found for this post.', 'studio-booking-manager' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><strong><?php echo esc_html( (string) $row->attendee_name ); ?></strong></td>
								<td><a href="mailto:<?php echo esc_attr( (string) $row->attendee_email ); ?>"><?php echo esc_html( (string) $row->attendee_email ); ?></a></td>
								<td><?php echo esc_html( (string) absint( $row->guest_count ) ); ?></td>
								<td><?php echo esc_html( (string) $row->guest_names ); ?></td>
								<td><?php echo esc_html( (string) $row->notes ); ?></td>
								<td><?php echo esc_html( $this->format_datetime( (string) $row->updated_at ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render stat card.
	 *
	 * @param string $label Stat label.
	 * @param string $value Stat value.
	 */
	private function render_stat( string $label, string $value ): void {
		?>
		<div class="sbm-card">
			<h2><?php echo esc_html( $label ); ?></h2>
			<p class="sbm-stat-number"><?php echo esc_html( $value ); ?></p>
		</div>
		<?php
	}

	/**
	 * Build post RSVPs URL.
	 *
	 * @param int $post_id Post ID.
	 */
	private function post_rsvps_url( int $post_id ): string {
		return $this->admin_url(
			array(
				'page'    => 'sbm-rsvps',
				'post_id' => $post_id,
			)
		);
	}

	/**
	 * Build export URL.
	 *
	 * @param int $post_id Post ID.
	 */
	private function export_url( int $post_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'sbm_export_rsvps',
					'post_id' => $post_id,
				),
				admin_url( 'admin-post.php' )
			),
			'sbm_export_rsvps'
		);
	}

	/**
	 * Get a readable post title.
	 *
	 * @param int $post_id Post ID.
	 */
	private function post_title( int $post_id ): string {
		$title = get_the_title( $post_id );

		return '' !== $title ? $title : sprintf(
			/* translators: %d: post ID. */
			__( 'Post #%d', 'studio-booking-manager' ),
			$post_id
		);
	}

	/**
	 * Format a datetime value.
	 *
	 * @param string $value Datetime.
	 */
	private function format_datetime( string $value ): string {
		$timestamp = strtotime( $value );

		if ( false === $timestamp ) {
			return '';
		}

		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/**
	 * Status label.
	 *
	 * @param string $status Status key.
	 */
	private function status_label( string $status ): string {
		$labels = array(
			'attending'  => __( 'Attending', 'studio-booking-manager' ),
			'waitlisted' => __( 'Waitlisted', 'studio-booking-manager' ),
			'cancelled'  => __( 'Cancelled', 'studio-booking-manager' ),
			'maybe'      => __( 'Maybe', 'studio-booking-manager' ),
			'archived'   => __( 'Archived', 'studio-booking-manager' ),
		);

		return $labels[ $status ] ?? $status;
	}
}

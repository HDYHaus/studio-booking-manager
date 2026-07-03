<?php
/**
 * Member schedule shortcode.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Frontend;

use StudioBookingManager\Bookings\BookingRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Renders privacy-safe booking blocks for logged-in members.
 */
final class MemberScheduleShortcode {
	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_shortcode( 'sbm_member_schedule', array( $this, 'render' ) );
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style(
			'studio-booking-manager-frontend',
			SBM_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			SBM_VERSION
		);
	}

	/**
	 * Render shortcode output.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'days'          => 14,
				'limit'         => 30,
				'location_id'   => 0,
				'require_login' => 'yes',
			),
			$atts,
			'sbm_member_schedule'
		);

		if ( $this->requires_login( (string) $atts['require_login'] ) && ! is_user_logged_in() ) {
			return $this->login_message();
		}

		$records = ( new BookingRepository() )->member_schedule(
			absint( $atts['days'] ),
			absint( $atts['location_id'] ),
			absint( $atts['limit'] )
		);

		ob_start();
		?>
		<div class="sbm-member-schedule">
			<?php if ( empty( $records ) ) : ?>
				<p class="sbm-member-schedule-empty"><?php echo esc_html__( 'No upcoming studio bookings are currently scheduled.', 'studio-booking-manager' ); ?></p>
			<?php else : ?>
				<ul class="sbm-member-schedule-list">
					<?php foreach ( $records as $record ) : ?>
						<li class="sbm-member-schedule-item">
							<div class="sbm-member-schedule-date"><?php echo esc_html( $this->date_label( $record ) ); ?></div>
							<div class="sbm-member-schedule-main">
								<strong><?php echo esc_html( $this->time_range_label( $record ) ); ?></strong>
								<span><?php echo esc_html( $this->location_label( $record ) ); ?></span>
							</div>
							<span class="sbm-member-schedule-status"><?php echo esc_html( $this->status_label( (string) $record->status ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Determine whether login is required.
	 *
	 * @param string $value Attribute value.
	 */
	private function requires_login( string $value ): bool {
		return ! in_array( strtolower( $value ), array( 'no', 'false', '0' ), true );
	}

	/**
	 * Render login message.
	 */
	private function login_message(): string {
		$url = wp_login_url( get_permalink() ?: home_url( '/' ) );

		return sprintf(
			'<p class="sbm-member-schedule-login">%1$s <a href="%2$s">%3$s</a></p>',
			esc_html__( 'Sign in to view the studio schedule.', 'studio-booking-manager' ),
			esc_url( $url ),
			esc_html__( 'Sign in', 'studio-booking-manager' )
		);
	}

	/**
	 * Date label.
	 *
	 * @param object $record Booking record.
	 */
	private function date_label( object $record ): string {
		return wp_date( get_option( 'date_format' ), $this->timestamp( (string) $record->starts_at ), $this->timezone( $record ) );
	}

	/**
	 * Time range label.
	 *
	 * @param object $record Booking record.
	 */
	private function time_range_label( object $record ): string {
		$timezone = $this->timezone( $record );
		$format   = get_option( 'time_format' );

		return sprintf(
			/* translators: 1: start time, 2: end time. */
			__( '%1$s to %2$s', 'studio-booking-manager' ),
			wp_date( $format, $this->timestamp( (string) $record->starts_at ), $timezone ),
			wp_date( $format, $this->timestamp( (string) $record->ends_at ), $timezone )
		);
	}

	/**
	 * Location label.
	 *
	 * @param object $record Booking record.
	 */
	private function location_label( object $record ): string {
		return ! empty( $record->location_name ) ? (string) $record->location_name : __( 'Studio', 'studio-booking-manager' );
	}

	/**
	 * Status label.
	 *
	 * @param string $status Booking status.
	 */
	private function status_label( string $status ): string {
		return 'pending' === $status ? __( 'Pending hold', 'studio-booking-manager' ) : __( 'Booked', 'studio-booking-manager' );
	}

	/**
	 * Timestamp.
	 *
	 * @param string $value Stored datetime.
	 */
	private function timestamp( string $value ): int {
		$timestamp = strtotime( $value );

		return false === $timestamp ? time() : $timestamp;
	}

	/**
	 * Timezone.
	 *
	 * @param object $record Booking record.
	 */
	private function timezone( object $record ): \DateTimeZone {
		$timezone = ! empty( $record->location_timezone ) ? (string) $record->location_timezone : wp_timezone_string();

		try {
			return new \DateTimeZone( $timezone );
		} catch ( \Exception $exception ) {
			return wp_timezone();
		}
	}
}

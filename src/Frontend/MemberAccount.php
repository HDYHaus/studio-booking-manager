<?php
/**
 * Member account self-service views.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Frontend;

use StudioBookingManager\Access\AccessService;
use StudioBookingManager\Bookings\BookingRepository;
use StudioBookingManager\People\PersonService;

defined( 'ABSPATH' ) || exit;

/**
 * Renders customer-facing studio account information.
 */
final class MemberAccount {
	private const ENDPOINT = 'studio-booking';

	/**
	 * People service.
	 *
	 * @var PersonService
	 */
	private PersonService $people;

	/**
	 * Access service.
	 *
	 * @var AccessService
	 */
	private AccessService $access;

	/**
	 * Booking repository.
	 *
	 * @var BookingRepository
	 */
	private BookingRepository $bookings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->people   = new PersonService();
		$this->access   = new AccessService();
		$this->bookings = new BookingRepository();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_endpoint' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_shortcode( 'sbm_member_account', array( $this, 'render_shortcode' ) );

		if ( class_exists( 'WooCommerce' ) ) {
			add_filter( 'woocommerce_account_menu_items', array( $this, 'add_account_menu_item' ) );
			add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render_account_endpoint' ) );
		}
	}

	/**
	 * Register WooCommerce account endpoint.
	 */
	public function register_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );

		if ( '1' !== get_option( 'sbm_member_account_endpoint_flushed' ) ) {
			flush_rewrite_rules( false );
			update_option( 'sbm_member_account_endpoint_flushed', '1' );
		}
	}

	/**
	 * Enqueue frontend styles.
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
	 * Add Studio Booking to WooCommerce My Account navigation.
	 *
	 * @param array<string,string> $items Account menu items.
	 * @return array<string,string>
	 */
	public function add_account_menu_item( array $items ): array {
		$label = __( 'Studio Booking', 'studio-booking-manager' );

		if ( isset( $items['customer-logout'] ) ) {
			$logout = $items['customer-logout'];
			unset( $items['customer-logout'] );
			$items[ self::ENDPOINT ]  = $label;
			$items['customer-logout'] = $logout;

			return $items;
		}

		$items[ self::ENDPOINT ] = $label;

		return $items;
	}

	/**
	 * Render WooCommerce account endpoint.
	 */
	public function render_account_endpoint(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is escaped as it is rendered.
		echo $this->render();
	}

	/**
	 * Render shortcode.
	 *
	 * @return string
	 */
	public function render_shortcode( $atts = array() ): string {
		return $this->render();
	}

	/**
	 * Render member account content.
	 *
	 * @return string
	 */
	private function render(): string {
		if ( ! is_user_logged_in() ) {
			return $this->login_message();
		}

		$person = $this->people->find_by_wp_user_id( get_current_user_id() );

		if ( ! ( $person instanceof \stdClass ) ) {
			return $this->not_linked_message();
		}

		$access_records = $this->access->for_person( (int) $person->id );
		$bookings       = $this->bookings->upcoming_for_person( (int) $person->id );

		ob_start();
		?>
		<div class="sbm-member-account">
			<section class="sbm-member-panel">
				<div>
					<h2><?php echo esc_html__( 'Studio account', 'studio-booking-manager' ); ?></h2>
					<p><?php echo esc_html( (string) $person->display_name ); ?></p>
				</div>
				<div class="sbm-member-identity">
					<span><?php echo esc_html__( 'QR identity', 'studio-booking-manager' ); ?></span>
					<code><?php echo esc_html( (string) $person->qr_token ); ?></code>
				</div>
			</section>

			<section class="sbm-member-panel">
				<h3><?php echo esc_html__( 'My passes and access', 'studio-booking-manager' ); ?></h3>
				<?php $this->render_access_list( $access_records ); ?>
			</section>

			<section class="sbm-member-panel">
				<h3><?php echo esc_html__( 'Upcoming bookings', 'studio-booking-manager' ); ?></h3>
				<?php $this->render_booking_list( $bookings ); ?>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render access list.
	 *
	 * @param array<int, object> $records Access records.
	 */
	private function render_access_list( array $records ): void {
		if ( empty( $records ) ) {
			?>
			<p class="sbm-member-empty"><?php echo esc_html__( 'No active studio access is linked to your account yet.', 'studio-booking-manager' ); ?></p>
			<?php
			return;
		}
		?>
		<ul class="sbm-member-list">
			<?php foreach ( $records as $record ) : ?>
				<li class="sbm-member-list-item">
					<div>
						<strong><?php echo esc_html( $this->access_type_label( (string) $record->access_type ) ); ?></strong>
						<span><?php echo esc_html( $this->location_label( $record ) ); ?></span>
					</div>
					<div class="sbm-member-meta">
						<span><?php echo esc_html( $this->credit_label( $record ) ); ?></span>
						<span><?php echo esc_html( $this->expiry_label( $record ) ); ?></span>
					</div>
					<span class="sbm-member-status sbm-member-status-<?php echo esc_attr( sanitize_html_class( (string) $record->status ) ); ?>"><?php echo esc_html( $this->status_label( (string) $record->status ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Render booking list.
	 *
	 * @param array<int, object> $records Booking records.
	 */
	private function render_booking_list( array $records ): void {
		if ( empty( $records ) ) {
			?>
			<p class="sbm-member-empty"><?php echo esc_html__( 'You do not have upcoming bookings.', 'studio-booking-manager' ); ?></p>
			<?php
			return;
		}
		?>
		<ul class="sbm-member-list">
			<?php foreach ( $records as $record ) : ?>
				<li class="sbm-member-list-item">
					<div>
						<strong><?php echo esc_html( $this->date_label( $record ) ); ?></strong>
						<span><?php echo esc_html( $this->time_range_label( $record ) ); ?></span>
					</div>
					<div class="sbm-member-meta">
						<span><?php echo esc_html( $this->location_label( $record ) ); ?></span>
						<span><?php echo esc_html( sprintf( /* translators: %d: guest count. */ _n( '%d guest', '%d guests', absint( $record->guest_count ), 'studio-booking-manager' ), absint( $record->guest_count ) ) ); ?></span>
					</div>
					<span class="sbm-member-status sbm-member-status-<?php echo esc_attr( sanitize_html_class( (string) $record->status ) ); ?>"><?php echo esc_html( $this->booking_status_label( (string) $record->status ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Render login message.
	 */
	private function login_message(): string {
		$url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url( get_permalink() ?: home_url( '/' ) );

		return sprintf(
			'<p class="sbm-member-login">%1$s <a href="%2$s">%3$s</a></p>',
			esc_html__( 'Sign in to view your studio passes and bookings.', 'studio-booking-manager' ),
			esc_url( $url ),
			esc_html__( 'Sign in', 'studio-booking-manager' )
		);
	}

	/**
	 * Render not-linked message.
	 */
	private function not_linked_message(): string {
		return sprintf(
			'<div class="sbm-member-empty"><p>%1$s</p><p>%2$s</p></div>',
			esc_html__( 'Your WordPress account is not linked to a studio member profile yet.', 'studio-booking-manager' ),
			esc_html__( 'Ask the studio team to link your customer account to your person record, or purchase a pass while signed in.', 'studio-booking-manager' )
		);
	}

	/**
	 * Access type label.
	 *
	 * @param string $type Access type.
	 */
	private function access_type_label( string $type ): string {
		$labels = $this->access->access_types();

		return $labels[ $type ] ?? ucwords( str_replace( '_', ' ', $type ) );
	}

	/**
	 * Location label.
	 *
	 * @param object $record Record with location_name.
	 */
	private function location_label( object $record ): string {
		return ! empty( $record->location_name ) ? (string) $record->location_name : __( 'Studio', 'studio-booking-manager' );
	}

	/**
	 * Credit label.
	 *
	 * @param object $record Access record.
	 */
	private function credit_label( object $record ): string {
		if ( 'membership' === (string) $record->access_type || null === $record->remaining_credits ) {
			return __( 'Unlimited visits', 'studio-booking-manager' );
		}

		return sprintf(
			/* translators: 1: remaining credits, 2: total credits. */
			__( '%1$d of %2$d visits left', 'studio-booking-manager' ),
			absint( $record->remaining_credits ),
			absint( $record->total_credits )
		);
	}

	/**
	 * Expiry label.
	 *
	 * @param object $record Access record.
	 */
	private function expiry_label( object $record ): string {
		if ( empty( $record->expires_at ) ) {
			return __( 'No expiry date', 'studio-booking-manager' );
		}

		return sprintf(
			/* translators: %s: expiry date. */
			__( 'Expires %s', 'studio-booking-manager' ),
			wp_date( get_option( 'date_format' ), $this->timestamp( (string) $record->expires_at ) )
		);
	}

	/**
	 * Status label.
	 *
	 * @param string $status Access status.
	 */
	private function status_label( string $status ): string {
		$labels = $this->access->statuses();

		return $labels[ $status ] ?? ucfirst( $status );
	}

	/**
	 * Booking status label.
	 *
	 * @param string $status Booking status.
	 */
	private function booking_status_label( string $status ): string {
		return 'pending' === $status ? __( 'Pending', 'studio-booking-manager' ) : __( 'Confirmed', 'studio-booking-manager' );
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
	 * @param object $record Record with optional location timezone.
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

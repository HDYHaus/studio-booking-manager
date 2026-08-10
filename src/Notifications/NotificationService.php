<?php
/**
 * Notification service.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Notifications;

use StudioBookingManager\Locations\LocationService;
use StudioBookingManager\People\PersonService;
use StudioBookingManager\Support\LocalDateTime;

defined( 'ABSPATH' ) || exit;

/**
 * Sends and logs notifications.
 */
final class NotificationService {
	/**
	 * Repository.
	 *
	 * @var NotificationRepository
	 */
	private NotificationRepository $repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new NotificationRepository();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'sbm_booking_created', array( $this, 'booking_created' ) );
		add_action( 'sbm_booking_updated', array( $this, 'booking_updated' ) );
		add_action( 'sbm_booking_archived', array( $this, 'booking_cancelled' ) );
		add_action( 'sbm_calendar_sync_failed', array( $this, 'calendar_failed' ), 10, 2 );
		add_action( 'sbm_pass_issued', array( $this, 'pass_issued' ), 10, 4 );
		add_action( 'sbm_rsvp_saved', array( $this, 'rsvp_saved' ), 10, 3 );
	}

	/**
	 * Booking created.
	 *
	 * @param object $booking Booking row.
	 */
	public function booking_created( object $booking ): void {
		$this->send_person_booking_notification( 'booking_confirmation', $booking );
	}

	/**
	 * Booking updated.
	 *
	 * @param object $booking Booking row.
	 */
	public function booking_updated( object $booking ): void {
		$this->send_person_booking_notification( 'booking_update', $booking );
	}

	/**
	 * Booking cancelled.
	 *
	 * @param object $booking Booking row.
	 */
	public function booking_cancelled( object $booking ): void {
		$this->send_person_booking_notification( 'booking_cancellation', $booking );
	}

	/**
	 * Calendar sync failure.
	 *
	 * @param object $booking Booking row.
	 * @param string $error Error message.
	 */
	public function calendar_failed( object $booking, string $error ): void {
		$email = $this->staff_email();
		if ( '' === $email || ! $this->is_enabled( 'calendar_sync_failed' ) ) {
			return;
		}

		$context = $this->booking_context( $booking ) + array( 'error' => $error );

		$this->send(
			'calendar_sync_failed',
			$email,
			get_bloginfo( 'name' ),
			$this->render_template( $this->subject_template( 'calendar_sync_failed' ), $context ),
			$this->render_template( $this->message_template( 'calendar_sync_failed' ), $context ),
			array( 'booking_id' => (int) $booking->id )
		);
	}

	/**
	 * Pass issued.
	 *
	 * @param int    $access_id Access ID.
	 * @param object $pass Pass type.
	 * @param object $person Person.
	 * @param object $location Location.
	 */
	public function pass_issued( int $access_id, object $pass, object $person, object $location ): void {
		if ( empty( $person->email ) || ! $this->is_enabled( 'pass_issued' ) ) {
			return;
		}

		$context = array(
			'access_id'     => (string) $access_id,
			'person_name'   => (string) $person->display_name,
			'person_email'  => (string) $person->email,
			'pass_name'     => (string) $pass->name,
			'location_name' => (string) $location->name,
			'business_name' => $this->business_name(),
		);

		$this->send(
			'pass_issued',
			(string) $person->email,
			(string) $person->display_name,
			$this->render_template( $this->subject_template( 'pass_issued' ), $context ),
			$this->render_template( $this->message_template( 'pass_issued' ), $context ),
			array( 'access_id' => $access_id )
		);
	}

	/**
	 * RSVP saved.
	 *
	 * @param object $rsvp RSVP row.
	 * @param string $operation Save operation.
	 * @param array<string,mixed> $extra Extra RSVP context.
	 */
	public function rsvp_saved( object $rsvp, string $operation, array $extra = array() ): void {
		if ( empty( $rsvp->attendee_email ) || ! $this->is_enabled( 'rsvp_confirmation' ) ) {
			return;
		}

		$context = $this->rsvp_context( $rsvp, $operation, $extra );

		$this->send(
			'rsvp_confirmation',
			(string) $rsvp->attendee_email,
			(string) $rsvp->attendee_name,
			$this->render_template( $this->subject_template( 'rsvp_confirmation' ), $context ),
			$this->render_template( $this->message_template( 'rsvp_confirmation' ), $context ),
			array(
				'rsvp_id'      => (int) $rsvp->id,
				'post_id'      => (int) $rsvp->post_id,
				'operation'    => $operation,
				'calendar_url' => $context['calendar_url'],
			)
		);
	}

	/**
	 * Send booking notification to the person.
	 *
	 * @param string $type Notification type.
	 * @param object $booking Booking row.
	 */
	private function send_person_booking_notification( string $type, object $booking ): void {
		if ( ! $this->is_enabled( $type ) ) {
			return;
		}

		$person = ( new PersonService() )->find( (int) $booking->person_id );
		if ( ! $person instanceof \stdClass || empty( $person->email ) ) {
			$this->log_skipped( $type, $booking, __( 'Person has no email address.', 'studio-booking-manager' ) );
			return;
		}

		$context = $this->booking_context( $booking, $person );

		$this->send(
			$type,
			(string) $person->email,
			(string) $person->display_name,
			$this->render_template( $this->subject_template( $type ), $context ),
			$this->render_template( $this->message_template( $type ), $context ),
			array( 'booking_id' => (int) $booking->id )
		);
	}

	/**
	 * Send and log.
	 *
	 * @param string              $type Type.
	 * @param string              $email Recipient email.
	 * @param string              $name Recipient name.
	 * @param string              $subject Subject.
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 */
	private function send( string $type, string $email, string $name, string $subject, string $message, array $context = array() ): void {
		$email = sanitize_email( $email );

		if ( '' === $email ) {
			return;
		}

		$headers = $this->mail_headers();
		$context = $context + $this->sender_context();
		$sent    = wp_mail( $email, $subject, $message, $headers );

		$this->repository->create(
			array(
				'notification_type' => $type,
				'recipient_email'   => $email,
				'recipient_name'    => $name,
				'subject'           => $subject,
				'message'           => $message,
				'status'            => $sent ? 'sent' : 'failed',
				'error_message'     => $sent ? '' : __( 'wp_mail returned false.', 'studio-booking-manager' ),
				'context'           => $context,
				'sent_at'           => $sent ? current_time( 'mysql' ) : null,
			)
		);
	}

	/**
	 * Log skipped notification.
	 *
	 * @param string $type Type.
	 * @param object $booking Booking row.
	 * @param string $reason Reason.
	 */
	private function log_skipped( string $type, object $booking, string $reason ): void {
		$this->repository->create(
			array(
				'notification_type' => $type,
				'status'            => 'skipped',
				'error_message'     => $reason,
				'context'           => array( 'booking_id' => (int) $booking->id ),
			)
		);
	}

	/**
	 * Booking context.
	 *
	 * @param object      $booking Booking row.
	 * @param object|null $person Person row.
	 * @return array<string,string>
	 */
	private function booking_context( object $booking, ?object $person = null ): array {
		if ( null === $person ) {
			$person = ( new PersonService() )->find( (int) $booking->person_id );
		}

		$location = ( new LocationService() )->find( (int) $booking->location_id );
		$timezone = wp_timezone();

		if ( $location instanceof \stdClass && ! empty( $location->timezone ) ) {
			try {
				$timezone = new \DateTimeZone( (string) $location->timezone );
			} catch ( \Exception $exception ) {
				$timezone = wp_timezone();
			}
		}

		return array(
			'booking_id'    => (string) absint( $booking->id ),
			'person_name'   => $person instanceof \stdClass ? (string) $person->display_name : '',
			'person_email'  => $person instanceof \stdClass ? (string) $person->email : '',
			'location_name' => $location instanceof \stdClass ? (string) $location->name : '',
			'starts_at'     => LocalDateTime::format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $booking->starts_at, $timezone ),
			'ends_at'       => LocalDateTime::format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $booking->ends_at, $timezone ),
			'status'        => (string) $booking->status,
			'business_name' => $this->business_name(),
		);
	}

	/**
	 * RSVP context.
	 *
	 * @param object $rsvp RSVP row.
	 * @param string $operation Save operation.
	 * @param array<string,mixed> $extra Extra RSVP context.
	 * @return array<string,string>
	 */
	private function rsvp_context( object $rsvp, string $operation, array $extra = array() ): array {
		$post_id      = isset( $rsvp->post_id ) ? absint( $rsvp->post_id ) : 0;
		$calendar_url = isset( $extra['calendar_url'] ) ? esc_url_raw( (string) $extra['calendar_url'] ) : '';

		return array(
			'rsvp_id'       => isset( $rsvp->id ) ? (string) absint( $rsvp->id ) : '',
			'person_name'   => isset( $rsvp->attendee_name ) ? (string) $rsvp->attendee_name : '',
			'person_email'  => isset( $rsvp->attendee_email ) ? (string) $rsvp->attendee_email : '',
			'event_title'   => $post_id > 0 ? get_the_title( $post_id ) : '',
			'event_url'     => $post_id > 0 ? get_permalink( $post_id ) : '',
			'guest_count'   => isset( $rsvp->guest_count ) ? (string) absint( $rsvp->guest_count ) : '0',
			'status'        => 'updated' === $operation ? __( 'updated', 'studio-booking-manager' ) : __( 'confirmed', 'studio-booking-manager' ),
			'calendar_url'  => $calendar_url,
			'calendar_line' => '' !== $calendar_url ? sprintf( __( "Add to Google Calendar: %s\n", 'studio-booking-manager' ), $calendar_url ) : '',
			'business_name' => $this->business_name(),
		);
	}

	/**
	 * Render template.
	 *
	 * @param string              $template Template.
	 * @param array<string,mixed> $context Context.
	 */
	private function render_template( string $template, array $context ): string {
		foreach ( $context as $key => $value ) {
			$template = str_replace( '{' . $key . '}', (string) $value, $template );
		}

		return $template;
	}

	/**
	 * Is notification enabled.
	 *
	 * @param string $type Type.
	 */
	private function is_enabled( string $type ): bool {
		$settings = $this->settings();

		if ( ! array_key_exists( $type . '_enabled', $settings ) && 'rsvp_confirmation' === $type ) {
			return true;
		}

		return ! empty( $settings[ $type . '_enabled' ] );
	}

	/**
	 * Subject template.
	 *
	 * @param string $type Type.
	 */
	private function subject_template( string $type ): string {
		$settings = $this->settings();

		return isset( $settings[ $type . '_subject' ] ) && '' !== (string) $settings[ $type . '_subject' ]
			? (string) $settings[ $type . '_subject' ]
			: $this->default_subjects()[ $type ];
	}

	/**
	 * Message template.
	 *
	 * @param string $type Type.
	 */
	private function message_template( string $type ): string {
		$settings = $this->settings();

		return isset( $settings[ $type . '_message' ] ) && '' !== (string) $settings[ $type . '_message' ]
			? (string) $settings[ $type . '_message' ]
			: $this->default_messages()[ $type ];
	}

	/**
	 * Staff email.
	 */
	private function staff_email(): string {
		$settings = $this->settings();

		return isset( $settings['notification_staff_email'] ) && '' !== (string) $settings['notification_staff_email']
			? sanitize_email( (string) $settings['notification_staff_email'] )
			: sanitize_email( get_option( 'admin_email' ) );
	}

	/**
	 * Build Studio Booking Manager notification email headers.
	 *
	 * @return array<int,string>
	 */
	private function mail_headers(): array {
		$headers    = array();
		$from_email = $this->notification_from_email();

		if ( '' !== $from_email ) {
			$headers[] = sprintf( 'From: %1$s <%2$s>', $this->notification_from_name(), $from_email );
		}

		$reply_to_email = $this->notification_reply_to_email();
		if ( '' !== $reply_to_email ) {
			$headers[] = sprintf( 'Reply-To: %s', $reply_to_email );
		}

		return $headers;
	}

	/**
	 * Sender context for notification logs.
	 *
	 * @return array<string,string>
	 */
	private function sender_context(): array {
		return array(
			'sender_from_name'      => $this->notification_from_name(),
			'sender_from_email'     => $this->notification_from_email(),
			'sender_reply_to_email' => $this->notification_reply_to_email(),
		);
	}

	/**
	 * Notification from name.
	 */
	private function notification_from_name(): string {
		$settings = $this->settings();
		$name     = isset( $settings['notification_from_name'] ) ? sanitize_text_field( (string) $settings['notification_from_name'] ) : '';

		return '' !== $name ? $name : $this->business_name();
	}

	/**
	 * Notification from email.
	 */
	private function notification_from_email(): string {
		$settings = $this->settings();
		$email    = isset( $settings['notification_from_email'] ) ? sanitize_email( (string) $settings['notification_from_email'] ) : '';

		return '' !== $email ? $email : sanitize_email( get_option( 'admin_email' ) );
	}

	/**
	 * Notification reply-to email.
	 */
	private function notification_reply_to_email(): string {
		$settings = $this->settings();

		return isset( $settings['notification_reply_to_email'] ) ? sanitize_email( (string) $settings['notification_reply_to_email'] ) : '';
	}

	/**
	 * Business name.
	 */
	private function business_name(): string {
		$settings = $this->settings();

		return isset( $settings['business_name'] ) && '' !== (string) $settings['business_name'] ? (string) $settings['business_name'] : get_bloginfo( 'name' );
	}

	/**
	 * Settings.
	 *
	 * @return array<string,mixed>
	 */
	private function settings(): array {
		$settings = get_option( 'sbm_settings', array() );

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Default subjects.
	 *
	 * @return array<string,string>
	 */
	private function default_subjects(): array {
		return array(
			'booking_confirmation' => __( 'Your booking at {business_name}', 'studio-booking-manager' ),
			'booking_update'       => __( 'Your booking was updated', 'studio-booking-manager' ),
			'booking_cancellation' => __( 'Your booking was cancelled', 'studio-booking-manager' ),
			'calendar_sync_failed' => __( 'Calendar sync failed for booking #{booking_id}', 'studio-booking-manager' ),
			'pass_issued'          => __( 'Your {pass_name} is ready', 'studio-booking-manager' ),
			'rsvp_confirmation'    => __( 'Your RSVP for {event_title}', 'studio-booking-manager' ),
		);
	}

	/**
	 * Default messages.
	 *
	 * @return array<string,string>
	 */
	private function default_messages(): array {
		return array(
			'booking_confirmation' => __( "Hi {person_name},\n\nYour booking at {location_name} is confirmed for {starts_at} to {ends_at}.\n\nThank you,\n{business_name}", 'studio-booking-manager' ),
			'booking_update'       => __( "Hi {person_name},\n\nYour booking at {location_name} was updated. Current time: {starts_at} to {ends_at}.\n\nThank you,\n{business_name}", 'studio-booking-manager' ),
			'booking_cancellation' => __( "Hi {person_name},\n\nYour booking at {location_name} for {starts_at} has been cancelled.\n\nThank you,\n{business_name}", 'studio-booking-manager' ),
			'calendar_sync_failed' => __( "Calendar sync failed for booking #{booking_id} at {location_name}.\n\nError: {error}", 'studio-booking-manager' ),
			'pass_issued'          => __( "Hi {person_name},\n\nYour {pass_name} for {location_name} is ready.\n\nThank you,\n{business_name}", 'studio-booking-manager' ),
			'rsvp_confirmation'    => __( "Hi {person_name},\n\nYour RSVP for {event_title} is {status}.\n\nGuests: {guest_count}\nEvent page: {event_url}\n{calendar_line}\nThank you,\n{business_name}", 'studio-booking-manager' ),
		);
	}
}

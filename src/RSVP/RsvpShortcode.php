<?php
/**
 * Public RSVP shortcode.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\RSVP;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and handles public post RSVP forms.
 */
final class RsvpShortcode {
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_shortcode( 'sbm_rsvp', array( $this, 'render' ) );
		add_action( 'admin_post_sbm_submit_rsvp', array( $this, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_sbm_submit_rsvp', array( $this, 'handle_submit' ) );
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
				'post_id'          => 0,
				'starts_at'        => '',
				'ends_at'          => '',
				'location'         => '',
				'calendar_title'   => '',
				'calendar_details' => '',
			),
			$atts,
			'sbm_rsvp'
		);

		$post_id = absint( $atts['post_id'] );
		if ( $post_id <= 0 ) {
			$post_id = get_the_ID();
		}

		if ( $post_id <= 0 || null === get_post( $post_id ) ) {
			return '';
		}

		$totals  = $this->service->totals_for_post( $post_id );
		$calendar = $this->calendar_data(
			$post_id,
			array(
				'starts_at'        => $atts['starts_at'],
				'ends_at'          => $atts['ends_at'],
				'location'         => $atts['location'],
				'calendar_title'   => $atts['calendar_title'],
				'calendar_details' => $atts['calendar_details'],
			)
		);
		$message = isset( $_GET['sbm_rsvp_message'] ) ? sanitize_key( wp_unslash( $_GET['sbm_rsvp_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token   = isset( $_GET['sbm_rsvp_token'] ) ? sanitize_key( wp_unslash( $_GET['sbm_rsvp_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$feedback = $this->feedback( $token );
		$is_success = in_array( $message, array( 'confirmed', 'updated' ), true );

		ob_start();
		?>
		<div class="sbm-rsvp" id="sbm-rsvp-<?php echo esc_attr( (string) $post_id ); ?>">
			<?php $this->render_message( $message, $feedback, $totals ); ?>
			<div class="sbm-rsvp-summary">
				<strong><?php echo esc_html__( 'RSVPs', 'studio-booking-manager' ); ?></strong>
				<span>
					<?php
					printf(
						/* translators: 1: attendee count, 2: total headcount. */
						esc_html__( '%1$d attending, %2$d total with guests', 'studio-booking-manager' ),
						absint( $totals['rsvp_count'] ),
						absint( $totals['headcount'] )
					);
					?>
				</span>
			</div>
			<?php if ( $is_success ) : ?>
				<p class="sbm-rsvp-follow-up"><?php echo esc_html__( 'A confirmation email is on its way. If you need to make a change, submit the form again with the same email address.', 'studio-booking-manager' ); ?></p>
			<?php else : ?>
			<form class="sbm-rsvp-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'sbm_submit_rsvp_' . $post_id ); ?>
				<input type="hidden" name="action" value="sbm_submit_rsvp">
				<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $post_id ); ?>">
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
				<input type="hidden" name="calendar_starts_at" value="<?php echo esc_attr( $calendar['starts_at'] ); ?>">
				<input type="hidden" name="calendar_ends_at" value="<?php echo esc_attr( $calendar['ends_at'] ); ?>">
				<input type="hidden" name="calendar_location" value="<?php echo esc_attr( $calendar['location'] ); ?>">
				<input type="hidden" name="calendar_title" value="<?php echo esc_attr( $calendar['title'] ); ?>">
				<input type="hidden" name="calendar_details" value="<?php echo esc_url( $calendar['details'] ); ?>">
				<p>
					<label for="sbm-rsvp-name-<?php echo esc_attr( (string) $post_id ); ?>"><?php echo esc_html__( 'Your name', 'studio-booking-manager' ); ?> <span class="sbm-rsvp-required"><?php echo esc_html__( 'Required', 'studio-booking-manager' ); ?></span></label>
					<input id="sbm-rsvp-name-<?php echo esc_attr( (string) $post_id ); ?>" name="attendee_name" type="text" autocomplete="name" required>
				</p>
				<p>
					<label for="sbm-rsvp-email-<?php echo esc_attr( (string) $post_id ); ?>"><?php echo esc_html__( 'Email', 'studio-booking-manager' ); ?> <span class="sbm-rsvp-required"><?php echo esc_html__( 'Required', 'studio-booking-manager' ); ?></span></label>
					<input id="sbm-rsvp-email-<?php echo esc_attr( (string) $post_id ); ?>" name="attendee_email" type="email" autocomplete="email" required>
					<span class="sbm-rsvp-help"><?php echo esc_html__( 'We will send your RSVP confirmation and any event updates here.', 'studio-booking-manager' ); ?></span>
				</p>
				<p>
					<label for="sbm-rsvp-guest-count-<?php echo esc_attr( (string) $post_id ); ?>"><?php echo esc_html__( 'Guest count', 'studio-booking-manager' ); ?> <span class="sbm-rsvp-optional"><?php echo esc_html__( 'Optional', 'studio-booking-manager' ); ?></span></label>
					<input id="sbm-rsvp-guest-count-<?php echo esc_attr( (string) $post_id ); ?>" name="guest_count" type="number" min="0" max="6" value="0">
					<span class="sbm-rsvp-help"><?php echo esc_html__( 'Use 0 if you are coming alone. Maximum 6 guests. For larger groups, please contact the event host.', 'studio-booking-manager' ); ?></span>
				</p>
				<p>
					<label for="sbm-rsvp-guest-names-<?php echo esc_attr( (string) $post_id ); ?>"><?php echo esc_html__( 'Guest names', 'studio-booking-manager' ); ?> <span class="sbm-rsvp-optional"><?php echo esc_html__( 'Optional', 'studio-booking-manager' ); ?></span></label>
					<textarea id="sbm-rsvp-guest-names-<?php echo esc_attr( (string) $post_id ); ?>" name="guest_names" rows="3"></textarea>
				</p>
				<p>
					<label for="sbm-rsvp-notes-<?php echo esc_attr( (string) $post_id ); ?>"><?php echo esc_html__( 'Anything we should know?', 'studio-booking-manager' ); ?> <span class="sbm-rsvp-optional"><?php echo esc_html__( 'Optional', 'studio-booking-manager' ); ?></span></label>
					<textarea id="sbm-rsvp-notes-<?php echo esc_attr( (string) $post_id ); ?>" name="notes" rows="3"></textarea>
				</p>
				<p><button class="sbm-rsvp-submit" type="submit"><?php echo esc_html__( 'RSVP to Attend', 'studio-booking-manager' ); ?></button></p>
			</form>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Handle RSVP submission.
	 */
	public function handle_submit(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is checked after post ID is read; values are sanitized by the service/repository.
		$post_id     = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( $post_id <= 0 ) {
			$this->redirect( $redirect_to, 'error' );
		}

		check_admin_referer( 'sbm_submit_rsvp_' . $post_id );

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified above; values are sanitized by the service/repository.
		$data = array(
			'post_id'         => $post_id,
			'attendee_name'   => isset( $_POST['attendee_name'] ) ? wp_unslash( $_POST['attendee_name'] ) : '',
			'attendee_email'  => isset( $_POST['attendee_email'] ) ? wp_unslash( $_POST['attendee_email'] ) : '',
			'guest_count'     => isset( $_POST['guest_count'] ) ? wp_unslash( $_POST['guest_count'] ) : 0,
			'guest_names'     => isset( $_POST['guest_names'] ) ? wp_unslash( $_POST['guest_names'] ) : '',
			'notes'           => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
			'ip_hash'         => $this->request_hash( 'REMOTE_ADDR' ),
			'user_agent_hash' => $this->request_hash( 'HTTP_USER_AGENT' ),
		);
		$calendar = $this->calendar_data(
			$post_id,
			array(
				'starts_at'        => isset( $_POST['calendar_starts_at'] ) ? wp_unslash( $_POST['calendar_starts_at'] ) : '',
				'ends_at'          => isset( $_POST['calendar_ends_at'] ) ? wp_unslash( $_POST['calendar_ends_at'] ) : '',
				'location'         => isset( $_POST['calendar_location'] ) ? wp_unslash( $_POST['calendar_location'] ) : '',
				'calendar_title'   => isset( $_POST['calendar_title'] ) ? wp_unslash( $_POST['calendar_title'] ) : '',
				'calendar_details' => isset( $_POST['calendar_details'] ) ? wp_unslash( $_POST['calendar_details'] ) : '',
			)
		);
		$data['calendar_url'] = $calendar['url'];
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$id        = $this->service->save( $data );
		$operation = $this->service->last_operation();
		$message   = $id > 0 && 'updated' === $operation ? 'updated' : ( $id > 0 ? 'confirmed' : $this->message_for_error( $this->service->last_error() ) );
		$token     = '';

		if ( $id > 0 ) {
			$token = $this->store_feedback(
				array(
					'attendee_name' => isset( $data['attendee_name'] ) ? sanitize_text_field( (string) $data['attendee_name'] ) : '',
					'guest_count'   => isset( $data['guest_count'] ) ? min( 6, absint( $data['guest_count'] ) ) : 0,
					'calendar_url'  => $calendar['url'],
				)
			);
		}

		$this->redirect( $redirect_to, $message, $post_id, $token );
	}

	/**
	 * Render a submission message.
	 *
	 * @param string              $message Message key.
	 * @param array<string,mixed> $feedback Feedback data.
	 * @param array<string,int>   $totals RSVP totals.
	 */
	private function render_message( string $message, array $feedback = array(), array $totals = array() ): void {
		if ( '' === $message ) {
			return;
		}

		$messages = array(
			'confirmed' => __( 'Your RSVP is confirmed.', 'studio-booking-manager' ),
			'updated'   => __( 'Your RSVP has been updated.', 'studio-booking-manager' ),
			'email'     => __( 'Please enter a valid email address.', 'studio-booking-manager' ),
			'name'      => __( 'Please enter your name.', 'studio-booking-manager' ),
			'event'     => __( 'This RSVP event could not be found.', 'studio-booking-manager' ),
			'error'     => __( 'Your RSVP could not be saved. Please try again.', 'studio-booking-manager' ),
		);

		if ( ! isset( $messages[ $message ] ) ) {
			return;
		}

		$is_success = in_array( $message, array( 'confirmed', 'updated' ), true );
		$class      = $is_success ? 'sbm-rsvp-message-success' : 'sbm-rsvp-message-error';

		printf(
			'<p class="sbm-rsvp-message %1$s">%2$s</p>',
			esc_attr( $class ),
			esc_html( $messages[ $message ] )
		);

		if ( ! $is_success ) {
			return;
		}

		$name        = isset( $feedback['attendee_name'] ) ? sanitize_text_field( (string) $feedback['attendee_name'] ) : '';
		$guest_count = isset( $feedback['guest_count'] ) ? absint( $feedback['guest_count'] ) : 0;
		$calendar_url = isset( $feedback['calendar_url'] ) ? esc_url_raw( (string) $feedback['calendar_url'] ) : '';
		$headcount   = isset( $totals['headcount'] ) ? absint( $totals['headcount'] ) : 0;

		echo '<div class="sbm-rsvp-confirmation-details">';
		if ( '' !== $name ) {
			printf(
				'<p>%1$s <strong>%2$s</strong></p>',
				esc_html__( 'Name:', 'studio-booking-manager' ),
				esc_html( $name )
			);
		}
		printf(
			'<p>%1$s <strong>%2$d</strong></p>',
			esc_html__( 'Guests:', 'studio-booking-manager' ),
			$guest_count
		);
		printf(
			'<p>%1$s <strong>%2$d</strong></p>',
			esc_html__( 'Current total headcount:', 'studio-booking-manager' ),
			$headcount
		);
		if ( '' !== $calendar_url ) {
			printf(
				'<p><a class="sbm-rsvp-calendar-link" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></p>',
				esc_url( $calendar_url ),
				esc_html__( 'Add to Google Calendar', 'studio-booking-manager' )
			);
		}
		echo '</div>';
	}

	/**
	 * Build calendar metadata from shortcode attributes or submitted hidden fields.
	 *
	 * @param int                 $post_id Event post ID.
	 * @param array<string,mixed> $data Calendar data.
	 * @return array<string,string>
	 */
	private function calendar_data( int $post_id, array $data ): array {
		$starts_at = isset( $data['starts_at'] ) ? sanitize_text_field( (string) $data['starts_at'] ) : '';
		$ends_at   = isset( $data['ends_at'] ) ? sanitize_text_field( (string) $data['ends_at'] ) : '';
		$location  = isset( $data['location'] ) ? sanitize_text_field( (string) $data['location'] ) : '';
		$title     = isset( $data['calendar_title'] ) ? sanitize_text_field( (string) $data['calendar_title'] ) : '';
		$details   = isset( $data['calendar_details'] ) ? esc_url_raw( (string) $data['calendar_details'] ) : '';

		if ( '' === $title ) {
			$title = get_the_title( $post_id );
		}

		if ( '' === $details ) {
			$details = get_permalink( $post_id );
		}

		return array(
			'starts_at' => $starts_at,
			'ends_at'   => $ends_at,
			'location'  => $location,
			'title'     => $title,
			'details'   => $details,
			'url'       => $this->google_calendar_url( $title, $starts_at, $ends_at, $location, $details ),
		);
	}

	/**
	 * Build a Google Calendar template URL.
	 */
	private function google_calendar_url( string $title, string $starts_at, string $ends_at = '', string $location = '', string $details = '' ): string {
		$start = $this->calendar_datetime( $starts_at );
		if ( null === $start ) {
			return '';
		}

		$end = $this->calendar_datetime( $ends_at );
		if ( null === $end || $end <= $start ) {
			$end = $start->modify( '+2 hours' );
		}

		$utc   = new \DateTimeZone( 'UTC' );
		$dates = $start->setTimezone( $utc )->format( 'Ymd\THis\Z' ) . '/' . $end->setTimezone( $utc )->format( 'Ymd\THis\Z' );

		return add_query_arg(
			array(
				'action'   => 'TEMPLATE',
				'text'     => $title,
				'dates'    => $dates,
				'details'  => $details,
				'location' => $location,
			),
			'https://calendar.google.com/calendar/render'
		);
	}

	/**
	 * Parse a calendar datetime in the WordPress site timezone.
	 */
	private function calendar_datetime( string $value ): ?\DateTimeImmutable {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}

		try {
			return new \DateTimeImmutable( $value, wp_timezone() );
		} catch ( \Exception $exception ) {
			$timestamp = strtotime( $value );
			if ( false === $timestamp ) {
				return null;
			}

			return ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( wp_timezone() );
		}
	}

	/**
	 * Redirect back to the event page.
	 *
	 * @param string $redirect_to Requested redirect URL.
	 * @param string $message Message key.
	 * @param int    $post_id Post ID.
	 * @param string $token Feedback token.
	 */
	private function redirect( string $redirect_to, string $message, int $post_id = 0, string $token = '' ): void {
		if ( '' === $redirect_to && $post_id > 0 ) {
			$redirect_to = get_permalink( $post_id );
		}

		if ( '' === $redirect_to ) {
			$redirect_to = home_url( '/' );
		}

		$args = array(
			'sbm_rsvp_message' => $message,
		);

		if ( '' !== $token ) {
			$args['sbm_rsvp_token'] = $token;
		}

		wp_safe_redirect( add_query_arg( $args, $redirect_to ) . '#sbm-rsvp-' . absint( $post_id ) );
		exit;
	}

	/**
	 * Store short-lived success feedback without exposing attendee data in the URL.
	 *
	 * @param array<string,mixed> $feedback Feedback data.
	 */
	private function store_feedback( array $feedback ): string {
		$token = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : md5( (string) wp_rand() . microtime() );
		$token = sanitize_key( $token );

		set_transient( 'sbm_rsvp_feedback_' . $token, $feedback, 10 * MINUTE_IN_SECONDS );

		return $token;
	}

	/**
	 * Read short-lived success feedback.
	 *
	 * @param string $token Feedback token.
	 * @return array<string,mixed>
	 */
	private function feedback( string $token ): array {
		if ( '' === $token ) {
			return array();
		}

		$feedback = get_transient( 'sbm_rsvp_feedback_' . $token );

		return is_array( $feedback ) ? $feedback : array();
	}

	/**
	 * Map a save error to a public message key.
	 *
	 * @param string $error Error key.
	 */
	private function message_for_error( string $error ): string {
		return in_array( $error, array( 'email', 'name', 'event' ), true ) ? $error : 'error';
	}

	/**
	 * Hash request metadata without storing raw visitor data.
	 *
	 * @param string $key Server key.
	 */
	private function request_hash( string $key ): string {
		$value = isset( $_SERVER[ $key ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Request metadata is sanitized before hashing.

		return '' === $value ? '' : hash( 'sha256', $value . wp_salt( 'nonce' ) );
	}
}

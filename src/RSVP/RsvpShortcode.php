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
				'post_id' => 0,
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
		$message = isset( $_GET['sbm_rsvp_message'] ) ? sanitize_key( wp_unslash( $_GET['sbm_rsvp_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		ob_start();
		?>
		<div class="sbm-rsvp" id="sbm-rsvp-<?php echo esc_attr( (string) $post_id ); ?>">
			<?php $this->render_message( $message ); ?>
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
			<form class="sbm-rsvp-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'sbm_submit_rsvp_' . $post_id ); ?>
				<input type="hidden" name="action" value="sbm_submit_rsvp">
				<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $post_id ); ?>">
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
				<p>
					<label for="sbm-rsvp-name-<?php echo esc_attr( (string) $post_id ); ?>"><?php echo esc_html__( 'Your name', 'studio-booking-manager' ); ?></label>
					<input id="sbm-rsvp-name-<?php echo esc_attr( (string) $post_id ); ?>" name="attendee_name" type="text" autocomplete="name" required>
				</p>
				<p>
					<label for="sbm-rsvp-email-<?php echo esc_attr( (string) $post_id ); ?>"><?php echo esc_html__( 'Email', 'studio-booking-manager' ); ?></label>
					<input id="sbm-rsvp-email-<?php echo esc_attr( (string) $post_id ); ?>" name="attendee_email" type="email" autocomplete="email" required>
				</p>
				<fieldset>
					<legend><?php echo esc_html__( 'Are you bringing a guest?', 'studio-booking-manager' ); ?></legend>
					<label><input type="radio" name="bringing_guest" value="0" checked> <?php echo esc_html__( 'No', 'studio-booking-manager' ); ?></label>
					<label><input type="radio" name="bringing_guest" value="1"> <?php echo esc_html__( 'Yes', 'studio-booking-manager' ); ?></label>
				</fieldset>
				<p>
					<label for="sbm-rsvp-guest-count-<?php echo esc_attr( (string) $post_id ); ?>"><?php echo esc_html__( 'Guest count', 'studio-booking-manager' ); ?></label>
					<input id="sbm-rsvp-guest-count-<?php echo esc_attr( (string) $post_id ); ?>" name="guest_count" type="number" min="0" max="20" value="0">
				</p>
				<p>
					<label for="sbm-rsvp-guest-names-<?php echo esc_attr( (string) $post_id ); ?>"><?php echo esc_html__( 'Guest name or notes', 'studio-booking-manager' ); ?></label>
					<textarea id="sbm-rsvp-guest-names-<?php echo esc_attr( (string) $post_id ); ?>" name="guest_names" rows="3"></textarea>
				</p>
				<p>
					<label for="sbm-rsvp-notes-<?php echo esc_attr( (string) $post_id ); ?>"><?php echo esc_html__( 'Anything we should know?', 'studio-booking-manager' ); ?></label>
					<textarea id="sbm-rsvp-notes-<?php echo esc_attr( (string) $post_id ); ?>" name="notes" rows="3"></textarea>
				</p>
				<p><button class="sbm-rsvp-submit" type="submit"><?php echo esc_html__( 'RSVP to Attend', 'studio-booking-manager' ); ?></button></p>
			</form>
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
			'bringing_guest'  => isset( $_POST['bringing_guest'] ) && '1' === (string) wp_unslash( $_POST['bringing_guest'] ),
			'guest_count'     => isset( $_POST['guest_count'] ) ? wp_unslash( $_POST['guest_count'] ) : 0,
			'guest_names'     => isset( $_POST['guest_names'] ) ? wp_unslash( $_POST['guest_names'] ) : '',
			'notes'           => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
			'ip_hash'         => $this->request_hash( 'REMOTE_ADDR' ),
			'user_agent_hash' => $this->request_hash( 'HTTP_USER_AGENT' ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$id      = $this->service->save( $data );
		$message = $id > 0 ? 'confirmed' : $this->message_for_error( $this->service->last_error() );

		$this->redirect( $redirect_to, $message, $post_id );
	}

	/**
	 * Render a submission message.
	 *
	 * @param string $message Message key.
	 */
	private function render_message( string $message ): void {
		if ( '' === $message ) {
			return;
		}

		$messages = array(
			'confirmed' => __( 'Thank you. Your RSVP has been saved.', 'studio-booking-manager' ),
			'email'     => __( 'Please enter a valid email address.', 'studio-booking-manager' ),
			'name'      => __( 'Please enter your name.', 'studio-booking-manager' ),
			'event'     => __( 'This RSVP event could not be found.', 'studio-booking-manager' ),
			'error'     => __( 'Your RSVP could not be saved. Please try again.', 'studio-booking-manager' ),
		);

		if ( ! isset( $messages[ $message ] ) ) {
			return;
		}

		$class = 'confirmed' === $message ? 'sbm-rsvp-message-success' : 'sbm-rsvp-message-error';

		printf(
			'<p class="sbm-rsvp-message %1$s">%2$s</p>',
			esc_attr( $class ),
			esc_html( $messages[ $message ] )
		);
	}

	/**
	 * Redirect back to the event page.
	 *
	 * @param string $redirect_to Requested redirect URL.
	 * @param string $message Message key.
	 * @param int    $post_id Post ID.
	 */
	private function redirect( string $redirect_to, string $message, int $post_id = 0 ): void {
		if ( '' === $redirect_to && $post_id > 0 ) {
			$redirect_to = get_permalink( $post_id );
		}

		if ( '' === $redirect_to ) {
			$redirect_to = home_url( '/' );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'sbm_rsvp_message' => $message,
				),
				$redirect_to
			) . '#sbm-rsvp-' . absint( $post_id )
		);
		exit;
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

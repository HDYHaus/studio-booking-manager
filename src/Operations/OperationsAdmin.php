<?php
/**
 * Operations admin screens.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Operations;

use StudioBookingManager\Access\AccessService;
use StudioBookingManager\Access\Validators\AccessValidationResult;
use StudioBookingManager\Access\Validators\AccessValidator;
use StudioBookingManager\Admin\AbstractAdminPage;
use StudioBookingManager\Admin\PageHeader;
use StudioBookingManager\Bookings\BookingService;
use StudioBookingManager\Locations\LocationService;
use StudioBookingManager\People\PersonService;
use StudioBookingManager\UI\Badge;
use StudioBookingManager\Visits\VisitService;

\defined( 'ABSPATH' ) || exit;

/**
 * Daily operations screen for check-in and check-out.
 */
final class OperationsAdmin extends AbstractAdminPage {
	/**
	 * Capability required to use operations.
	 *
	 * @var string
	 */
	protected string $capability = 'sbm_check_in';

	/**
	 * Visit service.
	 *
	 * @var VisitService
	 */
	private VisitService $visits;

	/**
	 * Person service.
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
	 * Location service.
	 *
	 * @var LocationService
	 */
	private LocationService $locations;

	/**
	 * Booking service.
	 *
	 * @var BookingService
	 */
	private BookingService $bookings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->visits    = new VisitService();
		$this->people    = new PersonService();
		$this->access    = new AccessService();
		$this->locations = new LocationService();
		$this->bookings  = new BookingService();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_post_sbm_check_in', array( $this, 'handle_check_in' ) );
		add_action( 'admin_post_sbm_check_out', array( $this, 'handle_check_out' ) );
	}

	/**
	 * Render operations screen.
	 */
	public function render(): void {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'check_in'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( __( 'Operations', 'studio-booking-manager' ) ); ?>
			<?php $this->render_notice(); ?>
			<h2 class="nav-tab-wrapper">
				<a class="nav-tab <?php echo 'check_in' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $this->tab_url( 'check_in' ) ); ?>"><?php echo esc_html__( 'Check In', 'studio-booking-manager' ); ?></a>
				<a class="nav-tab <?php echo 'current' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $this->tab_url( 'current' ) ); ?>"><?php echo esc_html__( 'Current Visits', 'studio-booking-manager' ); ?></a>
			</h2>
			<?php
			if ( 'current' === $tab ) {
				$this->render_current_visits();
			} else {
				$this->render_check_in();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Handle check-in request.
	 */
	public function handle_check_in(): void {
		$this->verify_admin_request( 'sbm_check_in', __( 'You do not have permission to check visitors in.', 'studio-booking-manager' ) );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce and capability are verified above.
		$person_id   = isset( $_POST['person_id'] ) ? absint( wp_unslash( $_POST['person_id'] ) ) : 0;
		$access_id   = isset( $_POST['access_id'] ) ? absint( wp_unslash( $_POST['access_id'] ) ) : 0;
		$location_id = isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0;
		$booking_id  = isset( $_POST['booking_id'] ) ? absint( wp_unslash( $_POST['booking_id'] ) ) : 0;
		$guest_count = isset( $_POST['guest_count'] ) ? absint( wp_unslash( $_POST['guest_count'] ) ) : 0;
		$guest_names    = isset( $_POST['guest_names'] ) ? sanitize_textarea_field( wp_unslash( $_POST['guest_names'] ) ) : '';
		$checkin_method = isset( $_POST['checkin_method'] ) ? sanitize_key( wp_unslash( $_POST['checkin_method'] ) ) : 'reception';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$visit_id = $this->visits->check_in(
			array(
				'person_id'      => $person_id,
				'access_id'      => $access_id,
				'booking_id'     => $booking_id,
				'location_id'    => $location_id,
				'guest_count'    => $guest_count,
				'guest_names'    => $guest_names,
				'checkin_method' => $checkin_method,
			)
		);

		$validation = $this->visits->last_check_in_result();

		if ( $visit_id > 0 ) {
			$this->complete_booking_for_check_in( $booking_id, $person_id, $location_id );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'sbm-operations',
					'tab'     => $visit_id > 0 ? 'current' : 'check_in',
					'message' => $visit_id > 0 ? 'checked_in' : 'checkin_error',
					'reason'  => $validation instanceof AccessValidationResult ? $validation->first_error_code() : '',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle check-out request.
	 */
	public function handle_check_out(): void {
		$this->verify_capability_request( 'sbm_check_out', 'sbm_check_out', __( 'You do not have permission to check visitors out.', 'studio-booking-manager' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce and capability are verified above.
		$visit_id = isset( $_POST['visit_id'] ) ? absint( wp_unslash( $_POST['visit_id'] ) ) : 0;
		$checked_out = $this->visits->check_out( $visit_id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'sbm-operations',
					'tab'     => 'current',
					'message' => $checked_out ? 'checked_out' : 'checkout_error',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render check-in workflow.
	 */
	private function render_check_in(): void {
		$search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$people    = '' !== $search ? $this->people->all( $search ) : array();
		$locations = $this->locations->all();
		?>
		<div class="sbm-card sbm-card-wide">
			<h2><?php echo esc_html__( 'Find a person', 'studio-booking-manager' ); ?></h2>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="sbm-operations">
				<input type="hidden" name="tab" value="check_in">
				<p>
					<label class="screen-reader-text" for="sbm-operations-search"><?php echo esc_html__( 'Search people', 'studio-booking-manager' ); ?></label>
					<input class="regular-text" type="search" id="sbm-operations-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search by name, email, or phone', 'studio-booking-manager' ); ?>">
					<?php submit_button( __( 'Search', 'studio-booking-manager' ), 'secondary', '', false ); ?>
				</p>
			</form>
		</div>
		<?php if ( '' === $search ) : ?>
			<p><?php echo esc_html__( 'Search for a person to begin check-in.', 'studio-booking-manager' ); ?></p>
			<?php return; ?>
		<?php endif; ?>
		<?php if ( empty( $people ) ) : ?>
			<div class="sbm-card"><p><?php echo esc_html__( 'No matching people found.', 'studio-booking-manager' ); ?></p></div>
			<?php return; ?>
		<?php endif; ?>
		<div class="sbm-grid">
			<?php foreach ( $people as $person ) : ?>
				<?php $this->render_person_check_in_card( $person, $locations ); ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render a person card for check-in.
	 *
	 * @param object             $person Person row.
	 * @param array<int, object> $locations Locations.
	 */
	private function render_person_check_in_card( object $person, array $locations ): void {
		$access_records = $this->active_access_for_person( (int) $person->id );
		$bookings       = $this->bookings->upcoming_for_person( (int) $person->id );
		?>
		<div class="sbm-card">
			<h2><?php echo esc_html( (string) $person->display_name ); ?></h2>
			<p><?php echo esc_html( (string) $person->email ); ?></p>
			<?php if ( empty( $access_records ) ) : ?>
				<p><?php echo esc_html__( 'No active access available.', 'studio-booking-manager' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'sbm_check_in' ); ?>
					<input type="hidden" name="action" value="sbm_check_in">
					<input type="hidden" name="person_id" value="<?php echo esc_attr( (string) absint( $person->id ) ); ?>">
					<?php if ( ! empty( $bookings ) ) : ?>
						<p>
							<label for="sbm-booking-<?php echo esc_attr( (string) absint( $person->id ) ); ?>"><?php echo esc_html__( 'Booking', 'studio-booking-manager' ); ?></label><br>
							<select id="sbm-booking-<?php echo esc_attr( (string) absint( $person->id ) ); ?>" name="booking_id">
								<option value=""><?php echo esc_html__( 'No booking selected', 'studio-booking-manager' ); ?></option>
								<?php foreach ( $bookings as $booking ) : ?>
									<option value="<?php echo esc_attr( (string) absint( $booking->id ) ); ?>"><?php echo esc_html( $this->booking_label( $booking ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
					<?php endif; ?>
					<p>
						<label for="sbm-access-<?php echo esc_attr( (string) absint( $person->id ) ); ?>"><?php echo esc_html__( 'Access', 'studio-booking-manager' ); ?></label><br>
						<select id="sbm-access-<?php echo esc_attr( (string) absint( $person->id ) ); ?>" name="access_id" required>
							<?php foreach ( $access_records as $access ) : ?>
								<option value="<?php echo esc_attr( (string) absint( $access->id ) ); ?>"><?php echo esc_html( $this->access_label( $access ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label for="sbm-location-<?php echo esc_attr( (string) absint( $person->id ) ); ?>"><?php echo esc_html__( 'Location', 'studio-booking-manager' ); ?></label><br>
						<select id="sbm-location-<?php echo esc_attr( (string) absint( $person->id ) ); ?>" name="location_id" required>
							<?php foreach ( $locations as $location ) : ?>
								<option value="<?php echo esc_attr( (string) absint( $location->id ) ); ?>"><?php echo esc_html( (string) $location->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label for="sbm-guests-<?php echo esc_attr( (string) absint( $person->id ) ); ?>"><?php echo esc_html__( 'Guests', 'studio-booking-manager' ); ?></label><br>
						<input class="small-text" type="number" min="0" id="sbm-guests-<?php echo esc_attr( (string) absint( $person->id ) ); ?>" name="guest_count" value="0">
					</p>
					<p>
						<label for="sbm-guest-names-<?php echo esc_attr( (string) absint( $person->id ) ); ?>"><?php echo esc_html__( 'Guest names', 'studio-booking-manager' ); ?></label><br>
						<textarea class="large-text" rows="2" id="sbm-guest-names-<?php echo esc_attr( (string) absint( $person->id ) ); ?>" name="guest_names"></textarea>
					</p>
					<?php submit_button( __( 'Check In', 'studio-booking-manager' ), 'primary', '', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render current visits.
	 */
	private function render_current_visits(): void {
		$records = $this->visits->current();
		?>
		<div class="sbm-card sbm-card-wide">
			<h2><?php echo esc_html__( 'Current Visits', 'studio-booking-manager' ); ?></h2>
			<p><?php echo esc_html( sprintf( /* translators: %d: current occupancy count. */ __( 'Current occupancy: %d', 'studio-booking-manager' ), count( $records ) ) ); ?></p>
			<table class="widefat striped sbm-table">
				<thead><tr><th><?php echo esc_html__( 'Person', 'studio-booking-manager' ); ?></th><th><?php echo esc_html__( 'Location', 'studio-booking-manager' ); ?></th><th><?php echo esc_html__( 'Checked In', 'studio-booking-manager' ); ?></th><th><?php echo esc_html__( 'Guests', 'studio-booking-manager' ); ?></th><th><?php echo esc_html__( 'Actions', 'studio-booking-manager' ); ?></th></tr></thead>
				<tbody>
					<?php if ( empty( $records ) ) : ?>
						<tr><td colspan="5"><?php echo esc_html__( 'No one is currently checked in.', 'studio-booking-manager' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $records as $record ) : ?>
						<tr>
							<td><strong><?php echo esc_html( (string) $record->person_name ); ?></strong></td>
							<td><?php echo esc_html( (string) $record->location_name ); ?></td>
							<td><?php echo esc_html( (string) $record->checked_in_at ); ?></td>
							<td><?php echo esc_html( (string) absint( $record->guest_count ) ); ?></td>
							<td><?php $this->render_check_out_form( (int) $record->id ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render checkout form.
	 *
	 * @param int $visit_id Visit ID.
	 */
	private function render_check_out_form( int $visit_id ): void {
		?>
		<form class="sbm-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'sbm_check_out' ); ?>
			<input type="hidden" name="action" value="sbm_check_out">
			<input type="hidden" name="visit_id" value="<?php echo esc_attr( (string) absint( $visit_id ) ); ?>">
			<button type="submit" class="button button-small"><?php echo esc_html__( 'Check Out', 'studio-booking-manager' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Get active access for a person.
	 *
	 * @param int $person_id Person ID.
	 * @return array<int, object>
	 */
	private function active_access_for_person( int $person_id ): array {
		$records = array();

		foreach ( $this->access->all() as $access ) {
			$validation = ( new AccessValidator() )->validate_check_in(
				$access,
				array(
					'person_id'   => $person_id,
					'location_id' => (int) $access->location_id,
					'guest_count' => 0,
				)
			);

			if ( $validation->is_valid() ) {
				$records[] = $access;
			}
		}

		return $records;
	}

	/**
	 * Access label.
	 *
	 * @param object $access Access row.
	 * @return string
	 */
	private function access_label( object $access ): string {
		$type = str_replace( '_', ' ', (string) $access->access_type );
		return ucwords( $type ) . ' #' . (string) absint( $access->id );
	}

	/**
	 * Booking label.
	 *
	 * @param object $booking Booking row.
	 * @return string
	 */
	private function booking_label( object $booking ): string {
		return sprintf(
			/* translators: 1: booking ID, 2: start date/time, 3: end date/time. */
			__( '#%1$d %2$s to %3$s', 'studio-booking-manager' ),
			absint( $booking->id ),
			(string) $booking->starts_at,
			(string) $booking->ends_at
		);
	}

	/**
	 * Render notices.
	 */
	private function render_notice(): void {
		$messages = array(
			'checked_in'     => __( 'Person checked in successfully.', 'studio-booking-manager' ),
			'checked_out'    => __( 'Visit checked out successfully.', 'studio-booking-manager' ),
			'checkin_error'  => $this->check_in_error_message(),
			'checkout_error' => __( 'Check-out could not be completed.', 'studio-booking-manager' ),
		);

		$this->render_query_notice( $messages );
	}

	/**
	 * Get the current check-in error message.
	 *
	 * @return string
	 */
	private function check_in_error_message(): string {
		$reason = isset( $_GET['reason'] ) ? sanitize_key( wp_unslash( $_GET['reason'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$messages = array(
			'access_not_found'      => __( 'The selected access record could not be found.', 'studio-booking-manager' ),
			'missing_context'       => __( 'A person and location are required for check-in.', 'studio-booking-manager' ),
			'inactive_access'       => __( 'The selected access record is not active.', 'studio-booking-manager' ),
			'person_mismatch'       => __( 'The selected access record does not belong to this person.', 'studio-booking-manager' ),
			'location_mismatch'     => __( 'The selected access record is not valid for this location.', 'studio-booking-manager' ),
			'access_not_started'    => __( 'This access record is not active yet.', 'studio-booking-manager' ),
			'access_expired'        => __( 'This access record has expired.', 'studio-booking-manager' ),
			'no_remaining_credits'  => __( 'This access record has no remaining credits.', 'studio-booking-manager' ),
			'guest_limit_exceeded'  => __( 'The selected guest count exceeds this access record\'s guest limit.', 'studio-booking-manager' ),
			'weekly_limit_reached'  => __( 'This access record has reached its weekly visit limit.', 'studio-booking-manager' ),
			'booking_required'      => __( 'This access record requires a booking before check-in.', 'studio-booking-manager' ),
			'booking_invalid'       => __( 'The selected booking is not available for check-in.', 'studio-booking-manager' ),
			'booking_mismatch'      => __( 'The selected booking does not match this access record.', 'studio-booking-manager' ),
		);

		return $messages[ $reason ] ?? __( 'Check-in could not be completed.', 'studio-booking-manager' );
	}

	/**
	 * Build tab URL.
	 *
	 * @param string $tab Tab key.
	 * @return string
	 */
	private function tab_url( string $tab ): string {
		return add_query_arg(
			array(
				'page' => 'sbm-operations',
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Complete a selected booking when it belongs to the check-in context.
	 *
	 * @param int $booking_id Booking ID.
	 * @param int $person_id Person ID.
	 * @param int $location_id Location ID.
	 */
	private function complete_booking_for_check_in( int $booking_id, int $person_id, int $location_id ): void {
		if ( $booking_id <= 0 ) {
			return;
		}

		$booking = $this->bookings->find( $booking_id );

		if ( null === $booking || (int) $booking->person_id !== $person_id || (int) $booking->location_id !== $location_id ) {
			return;
		}

		if ( ! in_array( (string) $booking->status, array( 'pending', 'confirmed' ), true ) ) {
			return;
		}

		$this->bookings->complete( $booking_id );
	}
}

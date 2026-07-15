<?php
/**
 * Booking admin screens.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Bookings;

use StudioBookingManager\Access\AccessService;
use StudioBookingManager\Admin\AbstractAdminPage;
use StudioBookingManager\Admin\PageHeader;
use StudioBookingManager\Locations\LocationService;
use StudioBookingManager\People\PersonService;
use StudioBookingManager\UI\Badge;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and handles booking admin screens.
 */
final class BookingAdmin extends AbstractAdminPage {
	/**
	 * Capability required to manage bookings.
	 *
	 * @var string
	 */
	protected string $capability = 'sbm_manage_bookings';

	/**
	 * Booking service.
	 *
	 * @var BookingService
	 */
	private BookingService $service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service = new BookingService();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_post_sbm_save_booking', array( $this, 'handle_save' ) );
		add_action( 'admin_post_sbm_archive_booking', array( $this, 'handle_archive' ) );
		add_action( 'admin_post_sbm_retry_booking_calendar_sync', array( $this, 'handle_retry_calendar_sync' ) );
	}

	/**
	 * Render screen.
	 */
	public function render(): void {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id     = isset( $_GET['booking_id'] ) ? absint( wp_unslash( $_GET['booking_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'new' === $action || 'edit' === $action ) {
			$this->render_form( $id );
			return;
		}

		$this->render_list();
	}

	/**
	 * Handle save request.
	 */
	public function handle_save(): void {
		$this->verify_admin_request( 'sbm_save_booking', __( 'You do not have permission to manage bookings.', 'studio-booking-manager' ) );

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce and capability are verified above; values are sanitized in BookingService/BookingRepository.
		$data = array(
			'id'          => isset( $_POST['booking_id'] ) ? absint( wp_unslash( $_POST['booking_id'] ) ) : 0,
			'person_id'   => isset( $_POST['person_id'] ) ? absint( wp_unslash( $_POST['person_id'] ) ) : 0,
			'location_id' => isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0,
			'access_id'   => isset( $_POST['access_id'] ) ? wp_unslash( $_POST['access_id'] ) : '',
			'status'      => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'pending',
			'visibility'  => isset( $_POST['visibility'] ) ? sanitize_key( wp_unslash( $_POST['visibility'] ) ) : 'internal',
			'public_title' => isset( $_POST['public_title'] ) ? sanitize_text_field( wp_unslash( $_POST['public_title'] ) ) : '',
			'starts_at'   => isset( $_POST['starts_at'] ) ? wp_unslash( $_POST['starts_at'] ) : '',
			'ends_at'     => isset( $_POST['ends_at'] ) ? wp_unslash( $_POST['ends_at'] ) : '',
			'guest_count' => isset( $_POST['guest_count'] ) ? absint( wp_unslash( $_POST['guest_count'] ) ) : 0,
			'guest_names' => isset( $_POST['guest_names'] ) ? wp_unslash( $_POST['guest_names'] ) : '',
			'notes'       => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$saved_id = $this->service->save( $data );
		$message  = $saved_id > 0 ? 'saved' : $this->save_error_message_key();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'sbm-bookings',
					'message' => $message,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle archive request.
	 */
	public function handle_archive(): void {
		$this->verify_admin_request( 'sbm_archive_booking', __( 'You do not have permission to manage bookings.', 'studio-booking-manager' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce and capability are verified above.
		$id = isset( $_POST['booking_id'] ) ? absint( wp_unslash( $_POST['booking_id'] ) ) : 0;
		$this->service->archive( $id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'sbm-bookings',
					'message' => 'archived',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle calendar sync retry request.
	 */
	public function handle_retry_calendar_sync(): void {
		$this->verify_admin_request( 'sbm_retry_booking_calendar_sync', __( 'You do not have permission to manage bookings.', 'studio-booking-manager' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce and capability are verified above.
		$id      = isset( $_POST['booking_id'] ) ? absint( wp_unslash( $_POST['booking_id'] ) ) : 0;
		$booking = $this->service->find( $id );

		if ( $booking instanceof \stdClass ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `sbm_` is the documented public API prefix for Studio Booking Manager.
			do_action( 'sbm_booking_calendar_sync_requested', $booking );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'sbm-bookings',
					'message' => 'calendar_retry',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render booking list.
	 */
	private function render_list(): void {
		$filters   = $this->current_filters();
		$records   = $this->service->all( $filters );
		$locations = ( new LocationService() )->all();
		$statuses  = $this->service->statuses();
		$visibility_options = $this->visibility_options();
		$new_url   = add_query_arg(
			array(
				'page'   => 'sbm-bookings',
				'action' => 'new',
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( __( 'Bookings', 'studio-booking-manager' ), $new_url, __( 'Add New', 'studio-booking-manager' ) ); ?>
			<?php $this->render_notice(); ?>
			<div class="sbm-card sbm-card-wide">
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<input type="hidden" name="page" value="sbm-bookings">
					<label for="sbm-booking-filter-date"><?php echo esc_html__( 'Date', 'studio-booking-manager' ); ?></label>
					<input type="date" id="sbm-booking-filter-date" name="date" value="<?php echo esc_attr( (string) ( $filters['date'] ?? '' ) ); ?>">
					<label for="sbm-booking-filter-location"><?php echo esc_html__( 'Location', 'studio-booking-manager' ); ?></label>
					<select id="sbm-booking-filter-location" name="location_id">
						<option value=""><?php echo esc_html__( 'All locations', 'studio-booking-manager' ); ?></option>
						<?php foreach ( $locations as $location ) : ?>
							<option value="<?php echo esc_attr( (string) absint( $location->id ) ); ?>" <?php selected( (int) ( $filters['location_id'] ?? 0 ), (int) $location->id ); ?>><?php echo esc_html( (string) $location->name ); ?></option>
						<?php endforeach; ?>
					</select>
					<label for="sbm-booking-filter-status"><?php echo esc_html__( 'Status', 'studio-booking-manager' ); ?></label>
					<select id="sbm-booking-filter-status" name="status">
						<option value=""><?php echo esc_html__( 'All statuses', 'studio-booking-manager' ); ?></option>
						<?php foreach ( $statuses as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( (string) ( $filters['status'] ?? '' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<label for="sbm-booking-filter-visibility"><?php echo esc_html__( 'Visibility', 'studio-booking-manager' ); ?></label>
					<select id="sbm-booking-filter-visibility" name="visibility">
						<option value=""><?php echo esc_html__( 'All visibility', 'studio-booking-manager' ); ?></option>
						<?php foreach ( $visibility_options as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( (string) ( $filters['visibility'] ?? '' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( __( 'Filter', 'studio-booking-manager' ), 'secondary', '', false ); ?>
				</form>
			</div>
			<div class="sbm-card sbm-card-wide">
				<table class="widefat striped sbm-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Person', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Location', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'When', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Access', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Calendar', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Guests', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Visibility', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Customer Impact', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Actions', 'studio-booking-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $records ) ) : ?>
							<tr><td colspan="10"><?php echo esc_html__( 'No bookings found.', 'studio-booking-manager' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $records as $record ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $record->person_name ? (string) $record->person_name : __( 'Unknown person', 'studio-booking-manager' ) ); ?></strong></td>
								<td><?php echo esc_html( $record->location_name ? (string) $record->location_name : __( 'Unknown location', 'studio-booking-manager' ) ); ?></td>
								<td><?php echo esc_html( $this->date_range_label( $record ) ); ?></td>
								<td><?php echo esc_html( $record->access_type ? (string) $record->access_type : __( 'None', 'studio-booking-manager' ) ); ?></td>
								<td><?php $this->render_calendar_status( $record ); ?></td>
								<td><?php echo esc_html( (string) absint( $record->guest_count ) ); ?></td>
								<td><?php echo wp_kses_post( Badge::render( $this->visibility_label( (string) ( $record->visibility ?? 'internal' ) ), (string) ( $record->visibility ?? 'internal' ) ) ); ?></td>
								<td><?php $this->render_customer_impact( $record ); ?></td>
								<td><?php echo wp_kses_post( Badge::render( $this->status_label( (string) $record->status ), (string) $record->status ) ); ?></td>
								<td><?php $this->render_row_actions( $record ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render row actions.
	 *
	 * @param object $record Booking row.
	 */
	private function render_row_actions( object $record ): void {
		?>
		<a class="button button-small" href="<?php echo esc_url( $this->edit_url( (int) $record->id ) ); ?>"><?php echo esc_html__( 'Edit', 'studio-booking-manager' ); ?></a>
		<?php if ( isset( $record->calendar_sync_status ) && 'failed' === (string) $record->calendar_sync_status ) : ?>
			<form class="sbm-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'sbm_retry_booking_calendar_sync' ); ?>
				<input type="hidden" name="action" value="sbm_retry_booking_calendar_sync">
				<input type="hidden" name="booking_id" value="<?php echo esc_attr( (string) absint( $record->id ) ); ?>">
				<button type="submit" class="button button-small"><?php echo esc_html__( 'Retry Calendar', 'studio-booking-manager' ); ?></button>
			</form>
		<?php endif; ?>
		<form class="sbm-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'sbm_archive_booking' ); ?>
			<input type="hidden" name="action" value="sbm_archive_booking">
			<input type="hidden" name="booking_id" value="<?php echo esc_attr( (string) absint( $record->id ) ); ?>">
			<button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Archive this booking?', 'studio-booking-manager' ) ); ?>');"><?php echo esc_html__( 'Archive', 'studio-booking-manager' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Render form.
	 *
	 * @param int $id Booking ID.
	 */
	private function render_form( int $id = 0 ): void {
		$record    = $id > 0 ? $this->service->find( $id ) : null;
		$is_edit   = $record instanceof \stdClass;
		$id        = $is_edit ? $id : 0;
		$people    = ( new PersonService() )->all();
		$locations = ( new LocationService() )->all();
		$access    = ( new AccessService() )->all();
		$statuses  = $this->service->statuses();
		$visibility_options = $this->visibility_options();
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( $is_edit ? __( 'Edit Booking', 'studio-booking-manager' ) : __( 'Add Booking', 'studio-booking-manager' ) ); ?>
			<?php $this->render_notice(); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sbm-form-card">
				<?php wp_nonce_field( 'sbm_save_booking' ); ?>
				<input type="hidden" name="action" value="sbm_save_booking">
				<input type="hidden" name="booking_id" value="<?php echo esc_attr( (string) $id ); ?>">
				<table class="form-table" role="presentation">
					<tbody>
						<tr><th scope="row"><label for="sbm-booking-person"><?php echo esc_html__( 'Person', 'studio-booking-manager' ); ?></label></th><td><?php $this->render_people_select( $people, $is_edit ? (int) $record->person_id : 0 ); ?></td></tr>
						<tr><th scope="row"><label for="sbm-booking-location"><?php echo esc_html__( 'Location', 'studio-booking-manager' ); ?></label></th><td><?php $this->render_location_select( $locations, $is_edit ? (int) $record->location_id : 0 ); ?></td></tr>
						<tr>
							<th scope="row"><label for="sbm-booking-access"><?php echo esc_html__( 'Access', 'studio-booking-manager' ); ?></label></th>
							<td>
								<?php $this->render_access_select( $access, $is_edit ? (int) $record->access_id : 0 ); ?>
								<p class="description"><?php echo esc_html__( 'Access options are filtered to the selected person and location.', 'studio-booking-manager' ); ?></p>
							</td>
						</tr>
						<tr><th scope="row"><label for="sbm-booking-status"><?php echo esc_html__( 'Status', 'studio-booking-manager' ); ?></label></th><td><?php $this->render_status_select( $statuses, $is_edit ? (string) $record->status : 'pending' ); ?></td></tr>
						<tr>
							<th scope="row"><label for="sbm-booking-visibility"><?php echo esc_html__( 'Public schedule visibility', 'studio-booking-manager' ); ?></label></th>
							<td>
								<?php $this->render_select( 'visibility', 'sbm-booking-visibility', $visibility_options, $is_edit ? (string) ( $record->visibility ?? 'internal' ) : 'internal' ); ?>
								<div class="sbm-availability-impact">
									<p><?php echo esc_html__( 'Public appears by title. Private blocks purchases as a private booking. Studio unavailable blocks purchases as unavailable. Internal is hidden from customers.', 'studio-booking-manager' ); ?></p>
								</div>
							</td>
						</tr>
						<tr><th scope="row"><label for="sbm-booking-public-title"><?php echo esc_html__( 'Public title', 'studio-booking-manager' ); ?></label></th><td><input class="regular-text" type="text" id="sbm-booking-public-title" name="public_title" value="<?php echo esc_attr( $is_edit ? (string) ( $record->public_title ?? '' ) : '' ); ?>"><p class="description"><?php echo esc_html__( 'Shown only when visibility is Public, for example "Book Club". Leave private details in internal notes.', 'studio-booking-manager' ); ?></p></td></tr>
						<tr><th scope="row"><label for="sbm-booking-starts"><?php echo esc_html__( 'Starts At', 'studio-booking-manager' ); ?></label></th><td><input class="regular-text" type="datetime-local" id="sbm-booking-starts" name="starts_at" value="<?php echo esc_attr( $this->datetime_value( $is_edit ? (string) $record->starts_at : '' ) ); ?>" required></td></tr>
						<tr><th scope="row"><label for="sbm-booking-ends"><?php echo esc_html__( 'Ends At', 'studio-booking-manager' ); ?></label></th><td><input class="regular-text" type="datetime-local" id="sbm-booking-ends" name="ends_at" value="<?php echo esc_attr( $this->datetime_value( $is_edit ? (string) $record->ends_at : '' ) ); ?>" required></td></tr>
						<tr><th scope="row"><label for="sbm-booking-guests"><?php echo esc_html__( 'Guest Count', 'studio-booking-manager' ); ?></label></th><td><input class="small-text" type="number" min="0" id="sbm-booking-guests" name="guest_count" value="<?php echo esc_attr( $is_edit ? (string) absint( $record->guest_count ) : '0' ); ?>"></td></tr>
						<tr><th scope="row"><label for="sbm-booking-guest-names"><?php echo esc_html__( 'Guest Names', 'studio-booking-manager' ); ?></label></th><td><textarea class="large-text" rows="3" id="sbm-booking-guest-names" name="guest_names"><?php echo esc_textarea( $is_edit ? (string) $record->guest_names : '' ); ?></textarea></td></tr>
						<tr><th scope="row"><label for="sbm-booking-notes"><?php echo esc_html__( 'Notes', 'studio-booking-manager' ); ?></label></th><td><textarea class="large-text" rows="5" id="sbm-booking-notes" name="notes"><?php echo esc_textarea( $is_edit ? (string) $record->notes : '' ); ?></textarea></td></tr>
					</tbody>
				</table>
				<?php submit_button( $is_edit ? __( 'Update Booking', 'studio-booking-manager' ) : __( 'Create Booking', 'studio-booking-manager' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Current filters.
	 *
	 * @return array<string,mixed>
	 */
	private function current_filters(): array {
		return array(
			'date'        => isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'location_id' => isset( $_GET['location_id'] ) ? absint( wp_unslash( $_GET['location_id'] ) ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'status'      => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'visibility'  => isset( $_GET['visibility'] ) ? sanitize_key( wp_unslash( $_GET['visibility'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
	}

	/**
	 * Get save error message key.
	 *
	 * @return string
	 */
	private function save_error_message_key(): string {
		$error = $this->service->last_error();

		return '' === $error ? 'error' : 'error_' . $error;
	}

	/**
	 * Render notices.
	 */
	private function render_notice(): void {
		$this->render_query_notice(
			array(
				'saved'          => __( 'Booking saved successfully.', 'studio-booking-manager' ),
				'archived'       => __( 'Booking archived successfully.', 'studio-booking-manager' ),
				'calendar_retry' => __( 'Calendar sync retry requested.', 'studio-booking-manager' ),
				'error'          => __( 'Booking could not be saved.', 'studio-booking-manager' ),
				'error_missing'  => __( 'Booking requires a person, location, start, and end time.', 'studio-booking-manager' ),
				'error_time'     => __( 'Booking end time must be after the start time.', 'studio-booking-manager' ),
				'error_access'   => __( 'Selected access does not belong to this person and location.', 'studio-booking-manager' ),
				'error_conflict' => __( 'This location already has a booking during that time.', 'studio-booking-manager' ),
			)
		);
	}

	/**
	 * Render calendar sync status.
	 *
	 * @param object $record Booking row.
	 */
	private function render_calendar_status( object $record ): void {
		$status = isset( $record->calendar_sync_status ) ? (string) $record->calendar_sync_status : 'not_synced';
		$labels = array(
			'not_synced' => __( 'Not synced', 'studio-booking-manager' ),
			'disabled'   => __( 'Disabled', 'studio-booking-manager' ),
			'synced'     => __( 'Synced', 'studio-booking-manager' ),
			'failed'     => __( 'Failed', 'studio-booking-manager' ),
			'deleted'    => __( 'Deleted', 'studio-booking-manager' ),
		);

		echo wp_kses_post( Badge::render( $labels[ $status ] ?? ucfirst( $status ), $status ) );

		if ( 'failed' === $status && ! empty( $record->calendar_sync_error ) ) {
			echo '<p class="description">' . esc_html( (string) $record->calendar_sync_error ) . '</p>';
		}
	}

	/**
	 * Render customer-facing availability impact.
	 *
	 * @param object $record Booking row.
	 */
	private function render_customer_impact( object $record ): void {
		$visibility = isset( $record->visibility ) ? (string) $record->visibility : 'internal';
		$impact     = $this->customer_impact( $visibility );

		echo wp_kses_post( Badge::render( $impact['label'], $impact['type'] ) );
		echo '<p class="description sbm-customer-impact-description">' . esc_html( $impact['description'] ) . '</p>';
	}

	/**
	 * Render people select.
	 *
	 * @param array<int,object> $people People.
	 * @param int               $selected Selected ID.
	 */
	private function render_people_select( array $people, int $selected ): void {
		?>
		<select id="sbm-booking-person" name="person_id" required>
			<option value=""><?php echo esc_html__( 'Select person', 'studio-booking-manager' ); ?></option>
			<?php foreach ( $people as $person ) : ?>
				<option value="<?php echo esc_attr( (string) absint( $person->id ) ); ?>" <?php selected( $selected, (int) $person->id ); ?>><?php echo esc_html( (string) $person->display_name ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render location select.
	 *
	 * @param array<int,object> $locations Locations.
	 * @param int               $selected Selected ID.
	 */
	private function render_location_select( array $locations, int $selected ): void {
		?>
		<select id="sbm-booking-location" name="location_id" required>
			<option value=""><?php echo esc_html__( 'Select location', 'studio-booking-manager' ); ?></option>
			<?php foreach ( $locations as $location ) : ?>
				<option value="<?php echo esc_attr( (string) absint( $location->id ) ); ?>" <?php selected( $selected, (int) $location->id ); ?>><?php echo esc_html( (string) $location->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render access select.
	 *
	 * @param array<int,object> $records Access records.
	 * @param int               $selected Selected ID.
	 */
	private function render_access_select( array $records, int $selected ): void {
		?>
		<select id="sbm-booking-access" name="access_id">
			<option value=""><?php echo esc_html__( 'No access selected', 'studio-booking-manager' ); ?></option>
			<?php foreach ( $records as $record ) : ?>
				<option
					value="<?php echo esc_attr( (string) absint( $record->id ) ); ?>"
					data-person-id="<?php echo esc_attr( (string) absint( $record->person_id ) ); ?>"
					data-location-id="<?php echo esc_attr( (string) absint( $record->location_id ) ); ?>"
					<?php selected( $selected, (int) $record->id ); ?>
				>
					<?php echo esc_html( $this->access_label( $record ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Access select label.
	 *
	 * @param object $record Access row.
	 * @return string
	 */
	private function access_label( object $record ): string {
		$person   = isset( $record->person_name ) && '' !== (string) $record->person_name ? (string) $record->person_name : __( 'Unknown person', 'studio-booking-manager' );
		$location = isset( $record->location_name ) && '' !== (string) $record->location_name ? (string) $record->location_name : __( 'Unknown location', 'studio-booking-manager' );
		$type     = ucwords( str_replace( '_', ' ', (string) $record->access_type ) );

		return sprintf(
			/* translators: 1: access ID, 2: access type, 3: person name, 4: location name. */
			__( '#%1$d %2$s - %3$s at %4$s', 'studio-booking-manager' ),
			absint( $record->id ),
			$type,
			$person,
			$location
		);
	}

	/**
	 * Render status select.
	 *
	 * @param array<string,string> $statuses Statuses.
	 * @param string               $selected Selected status.
	 */
	private function render_status_select( array $statuses, string $selected ): void {
		?>
		<select id="sbm-booking-status" name="status" required>
			<?php foreach ( $statuses as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render a generic select.
	 *
	 * @param string               $name Selected name.
	 * @param string               $id Selected ID.
	 * @param array<string,string> $options Options.
	 * @param string               $selected Selected value.
	 */
	private function render_select( string $name, string $id, array $options, string $selected ): void {
		?>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
			<?php foreach ( $options as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Date range label.
	 *
	 * @param object $record Booking row.
	 * @return string
	 */
	private function date_range_label( object $record ): string {
		return trim( (string) $record->starts_at . ' - ' . (string) $record->ends_at );
	}

	/**
	 * Datetime-local input value.
	 *
	 * @param string $value Stored value.
	 * @return string
	 */
	private function datetime_value( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value );

		return false === $timestamp ? '' : gmdate( 'Y-m-d\TH:i', $timestamp );
	}

	/**
	 * Status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	private function status_label( string $status ): string {
		$statuses = $this->service->statuses();

		return $statuses[ $status ] ?? ucfirst( $status );
	}

	/**
	 * Public visibility options.
	 *
	 * @return array<string,string>
	 */
	private function visibility_options(): array {
		return array(
			'internal' => __( 'Internal only', 'studio-booking-manager' ),
			'public'   => __( 'Public event', 'studio-booking-manager' ),
			'private'  => __( 'Private booking', 'studio-booking-manager' ),
			'blocked'  => __( 'Studio unavailable', 'studio-booking-manager' ),
		);
	}

	/**
	 * Public visibility label.
	 *
	 * @param string $visibility Visibility key.
	 */
	private function visibility_label( string $visibility ): string {
		$options = $this->visibility_options();

		return $options[ $visibility ] ?? ucfirst( $visibility );
	}

	/**
	 * Customer-facing impact for a visibility mode.
	 *
	 * @param string $visibility Visibility key.
	 * @return array{label:string,type:string,description:string}
	 */
	private function customer_impact( string $visibility ): array {
		if ( 'public' === $visibility ) {
			return array(
				'label'       => __( 'Visible', 'studio-booking-manager' ),
				'type'        => 'public',
				'description' => __( 'Customers see the public title; day-pass purchasing remains available unless capacity is full.', 'studio-booking-manager' ),
			);
		}

		if ( 'private' === $visibility ) {
			return array(
				'label'       => __( 'Private block', 'studio-booking-manager' ),
				'type'        => 'private',
				'description' => __( 'Customers see a private booking label and cannot choose this date for day passes.', 'studio-booking-manager' ),
			);
		}

		if ( 'blocked' === $visibility ) {
			return array(
				'label'       => __( 'Unavailable', 'studio-booking-manager' ),
				'type'        => 'blocked',
				'description' => __( 'Customers see the date as unavailable and cannot choose it for day passes.', 'studio-booking-manager' ),
			);
		}

		return array(
			'label'       => __( 'Hidden', 'studio-booking-manager' ),
			'type'        => 'internal',
			'description' => __( 'Customers do not see this booking; it still participates in internal conflict checks.', 'studio-booking-manager' ),
		);
	}

	/**
	 * Build edit URL.
	 *
	 * @param int $id Booking ID.
	 * @return string
	 */
	private function edit_url( int $id ): string {
		return add_query_arg(
			array(
				'page'       => 'sbm-bookings',
				'action'     => 'edit',
				'booking_id' => $id,
			),
			admin_url( 'admin.php' )
		);
	}
}

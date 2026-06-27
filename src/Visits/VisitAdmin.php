<?php
/**
 * Visit admin screens.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Visits;

use StudioBookingManager\Access\AccessService;
use StudioBookingManager\Admin\AbstractAdminPage;
use StudioBookingManager\Admin\PageHeader;
use StudioBookingManager\Locations\LocationService;
use StudioBookingManager\People\PersonService;
use StudioBookingManager\UI\Badge;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and handles visit admin screens.
 */
final class VisitAdmin extends AbstractAdminPage {
	/**
	 * Capability required to manage visits.
	 *
	 * @var string
	 */
	protected string $capability = 'sbm_manage_visits';

	/**
	 * Visit service.
	 *
	 * @var VisitService
	 */
	private VisitService $service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service = new VisitService();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_post_sbm_save_visit', array( $this, 'handle_save' ) );
		add_action( 'admin_post_sbm_archive_visit', array( $this, 'handle_archive' ) );
	}

	/**
	 * Render screen.
	 */
	public function render(): void {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id     = isset( $_GET['visit_id'] ) ? absint( wp_unslash( $_GET['visit_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

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
		$this->verify_admin_request( 'sbm_save_visit', __( 'You do not have permission to manage visits.', 'studio-booking-manager' ) );

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce and capability are verified above; sanitization is centralised in VisitRepository::prepare_for_storage().
		$data = array(
			'id'              => isset( $_POST['visit_id'] ) ? absint( wp_unslash( $_POST['visit_id'] ) ) : 0,
			'person_id'       => isset( $_POST['person_id'] ) ? absint( wp_unslash( $_POST['person_id'] ) ) : 0,
			'access_id'       => isset( $_POST['access_id'] ) ? absint( wp_unslash( $_POST['access_id'] ) ) : 0,
			'location_id'     => isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0,
			'status'          => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'expected',
			'scheduled_start' => isset( $_POST['scheduled_start'] ) ? wp_unslash( $_POST['scheduled_start'] ) : '',
			'scheduled_end'   => isset( $_POST['scheduled_end'] ) ? wp_unslash( $_POST['scheduled_end'] ) : '',
			'guest_count'     => isset( $_POST['guest_count'] ) ? absint( wp_unslash( $_POST['guest_count'] ) ) : 0,
			'guest_names'     => isset( $_POST['guest_names'] ) ? wp_unslash( $_POST['guest_names'] ) : '',
			'checkin_method'  => isset( $_POST['checkin_method'] ) ? sanitize_key( wp_unslash( $_POST['checkin_method'] ) ) : 'reception',
			'notes'           => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$saved_id = $this->service->save( $data );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'sbm-visits',
					'message' => $saved_id > 0 ? 'saved' : 'error',
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
		$this->verify_admin_request( 'sbm_archive_visit', __( 'You do not have permission to manage visits.', 'studio-booking-manager' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce and capability are verified above.
		$id = isset( $_POST['visit_id'] ) ? absint( wp_unslash( $_POST['visit_id'] ) ) : 0;
		$this->service->archive( $id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'sbm-visits',
					'message' => 'archived',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render visit list.
	 */
	private function render_list(): void {
		$records = $this->service->all();
		$new_url = add_query_arg(
			array(
				'page'   => 'sbm-visits',
				'action' => 'new',
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( __( 'Visits', 'studio-booking-manager' ), $new_url, __( 'Add New', 'studio-booking-manager' ) ); ?>
			<?php $this->render_notice(); ?>
			<div class="sbm-card sbm-card-wide">
				<table class="widefat striped sbm-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Person', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Location', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Access', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Scheduled', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Checked In', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Checked Out', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Guests', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Actions', 'studio-booking-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $records ) ) : ?>
							<tr>
								<td colspan="9"><?php echo esc_html__( 'No visits found.', 'studio-booking-manager' ); ?></td>
							</tr>
						<?php endif; ?>
						<?php foreach ( $records as $record ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $record->person_name ? (string) $record->person_name : __( 'Unknown person', 'studio-booking-manager' ) ); ?></strong></td>
								<td><?php echo esc_html( $record->location_name ? (string) $record->location_name : __( 'Unknown location', 'studio-booking-manager' ) ); ?></td>
								<td><?php echo esc_html( $record->access_type ? (string) $record->access_type : __( 'Unknown access', 'studio-booking-manager' ) ); ?></td>
								<td><?php echo wp_kses_post( Badge::render( $this->status_label( (string) $record->status ), (string) $record->status ) ); ?></td>
								<td><?php echo esc_html( $this->date_range_label( $record ) ); ?></td>
								<td><?php echo esc_html( $record->checked_in_at ? (string) $record->checked_in_at : '—' ); ?></td>
								<td><?php echo esc_html( $record->checked_out_at ? (string) $record->checked_out_at : '—' ); ?></td>
								<td><?php echo esc_html( (string) absint( $record->guest_count ) ); ?></td>
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
	 * @param object $record Visit row.
	 */
	private function render_row_actions( object $record ): void {
		?>
		<a class="button button-small" href="<?php echo esc_url( $this->edit_url( (int) $record->id ) ); ?>"><?php echo esc_html__( 'Edit', 'studio-booking-manager' ); ?></a>
		<form class="sbm-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'sbm_archive_visit' ); ?>
			<input type="hidden" name="action" value="sbm_archive_visit">
			<input type="hidden" name="visit_id" value="<?php echo esc_attr( (string) absint( $record->id ) ); ?>">
			<button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Archive this visit?', 'studio-booking-manager' ) ); ?>');"><?php echo esc_html__( 'Archive', 'studio-booking-manager' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Render current query notice.
	 */
	private function render_notice(): void {
		$this->render_query_notice(
			array(
				'saved'    => __( 'Visit saved successfully.', 'studio-booking-manager' ),
				'archived' => __( 'Visit archived successfully.', 'studio-booking-manager' ),
				'error'    => __( 'Visit could not be saved.', 'studio-booking-manager' ),
			)
		);
	}

	/**
	 * Render form.
	 *
	 * @param int $id Visit ID.
	 */
	private function render_form( int $id = 0 ): void {
		$record    = $id > 0 ? $this->service->find( $id ) : null;
		$is_edit   = $record instanceof \stdClass;
		$people    = ( new PersonService() )->all();
		$locations = ( new LocationService() )->all();
		$access    = ( new AccessService() )->all();
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( $is_edit ? __( 'Edit Visit', 'studio-booking-manager' ) : __( 'Add Visit', 'studio-booking-manager' ) ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sbm-form-card">
				<?php wp_nonce_field( 'sbm_save_visit' ); ?>
				<input type="hidden" name="action" value="sbm_save_visit">
				<input type="hidden" name="visit_id" value="<?php echo esc_attr( (string) $id ); ?>">
				<table class="form-table" role="presentation">
					<tbody>
						<tr><th scope="row"><label for="sbm-visit-location"><?php echo esc_html__( 'Location', 'studio-booking-manager' ); ?></label></th><td><?php $this->render_location_select( $locations, $is_edit ? (int) $record->location_id : 0 ); ?></td></tr>
						<tr><th scope="row"><label for="sbm-visit-person"><?php echo esc_html__( 'Person', 'studio-booking-manager' ); ?></label></th><td><?php $this->render_people_select( $people, $is_edit ? (int) $record->person_id : 0 ); ?></td></tr>
						<tr><th scope="row"><label for="sbm-visit-access"><?php echo esc_html__( 'Access', 'studio-booking-manager' ); ?></label></th><td><?php $this->render_access_select( $access, $is_edit ? (int) $record->access_id : 0 ); ?></td></tr>
						<tr><th scope="row"><label for="sbm-visit-status"><?php echo esc_html__( 'Status', 'studio-booking-manager' ); ?></label></th><td><?php $this->render_status_select( $is_edit ? (string) $record->status : 'expected' ); ?></td></tr>
						<tr><th scope="row"><label for="sbm-visit-start"><?php echo esc_html__( 'Scheduled Start', 'studio-booking-manager' ); ?></label></th><td><input class="regular-text" type="datetime-local" id="sbm-visit-start" name="scheduled_start" value="<?php echo esc_attr( $this->datetime_value( $is_edit ? (string) $record->scheduled_start : '' ) ); ?>"></td></tr>
						<tr><th scope="row"><label for="sbm-visit-end"><?php echo esc_html__( 'Scheduled End', 'studio-booking-manager' ); ?></label></th><td><input class="regular-text" type="datetime-local" id="sbm-visit-end" name="scheduled_end" value="<?php echo esc_attr( $this->datetime_value( $is_edit ? (string) $record->scheduled_end : '' ) ); ?>"></td></tr>
						<tr><th scope="row"><label for="sbm-visit-guests"><?php echo esc_html__( 'Guest Count', 'studio-booking-manager' ); ?></label></th><td><input class="small-text" type="number" min="0" id="sbm-visit-guests" name="guest_count" value="<?php echo esc_attr( $is_edit ? (string) absint( $record->guest_count ) : '0' ); ?>"></td></tr>
						<tr><th scope="row"><label for="sbm-visit-guest-names"><?php echo esc_html__( 'Guest Names', 'studio-booking-manager' ); ?></label></th><td><textarea class="large-text" rows="3" id="sbm-visit-guest-names" name="guest_names"><?php echo esc_textarea( $is_edit ? (string) $record->guest_names : '' ); ?></textarea></td></tr>
						<tr><th scope="row"><label for="sbm-visit-method"><?php echo esc_html__( 'Check-in Method', 'studio-booking-manager' ); ?></label></th><td><?php $this->render_method_select( $is_edit ? (string) $record->checkin_method : 'reception' ); ?></td></tr>
						<tr><th scope="row"><label for="sbm-visit-notes"><?php echo esc_html__( 'Notes', 'studio-booking-manager' ); ?></label></th><td><textarea class="large-text" rows="5" id="sbm-visit-notes" name="notes"><?php echo esc_textarea( $is_edit ? (string) $record->notes : '' ); ?></textarea></td></tr>
					</tbody>
				</table>
				<?php submit_button( $is_edit ? __( 'Update Visit', 'studio-booking-manager' ) : __( 'Create Visit', 'studio-booking-manager' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render people select.
	 *
	 * @param array<int, object> $people People.
	 * @param int                $selected Selected ID.
	 */
	private function render_people_select( array $people, int $selected ): void {
		?>
		<select id="sbm-visit-person" name="person_id" required>
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
	 * @param array<int, object> $locations Locations.
	 * @param int                $selected Selected ID.
	 */
	private function render_location_select( array $locations, int $selected ): void {
		?>
		<select id="sbm-visit-location" name="location_id" required>
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
	 * @param array<int, object> $access Access records.
	 * @param int                $selected Selected ID.
	 */
	private function render_access_select( array $access, int $selected ): void {
		?>
		<select id="sbm-visit-access" name="access_id" required>
			<option value=""><?php echo esc_html__( 'Select access', 'studio-booking-manager' ); ?></option>
			<?php foreach ( $access as $item ) : ?>
				<option value="<?php echo esc_attr( (string) absint( $item->id ) ); ?>" <?php selected( $selected, (int) $item->id ); ?>><?php echo esc_html( sprintf( '%s — %s', (string) $item->person_name, (string) $item->access_type ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render status select.
	 *
	 * @param string $selected Selected status.
	 */
	private function render_status_select( string $selected ): void {
		?>
		<select id="sbm-visit-status" name="status">
			<?php foreach ( $this->service->statuses() as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render method select.
	 *
	 * @param string $selected Selected method.
	 */
	private function render_method_select( string $selected ): void {
		?>
		<select id="sbm-visit-method" name="checkin_method">
			<?php foreach ( $this->service->checkin_methods() as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Get edit URL.
	 *
	 * @param int $id Visit ID.
	 * @return string
	 */
	private function edit_url( int $id ): string {
		return add_query_arg(
			array(
				'page'     => 'sbm-visits',
				'action'   => 'edit',
				'visit_id' => $id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Render status label.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private function status_label( string $status ): string {
		$statuses = $this->service->statuses();
		return $statuses[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) );
	}

	/**
	 * Render date range label.
	 *
	 * @param object $record Visit record.
	 * @return string
	 */
	private function date_range_label( object $record ): string {
		$start = $record->scheduled_start ? (string) $record->scheduled_start : '';
		$end   = $record->scheduled_end ? (string) $record->scheduled_end : '';

		if ( '' === $start && '' === $end ) {
			return '—';
		}

		return trim( $start . ' — ' . $end, ' —' );
	}

	/**
	 * Convert mysql datetime to datetime-local value.
	 *
	 * @param string $value Date value.
	 * @return string
	 */
	private function datetime_value( string $value ): string {
		if ( '' === $value || '0000-00-00 00:00:00' === $value ) {
			return '';
		}

		return str_replace( ' ', 'T', substr( $value, 0, 16 ) );
	}
}

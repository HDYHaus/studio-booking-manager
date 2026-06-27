<?php
/**
 * Access admin screens.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Access;

use StudioBookingManager\Admin\AbstractAdminPage;
use StudioBookingManager\Admin\PageHeader;
use StudioBookingManager\Locations\LocationService;
use StudioBookingManager\People\PersonService;
use StudioBookingManager\UI\Badge;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and handles access admin screens.
 */
final class AccessAdmin extends AbstractAdminPage {
	/**
	 * Capability required to manage access.
	 *
	 * @var string
	 */
	protected string $capability = 'sbm_manage_access';

	/**
	 * Access service.
	 *
	 * @var AccessService
	 */
	private AccessService $service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service = new AccessService();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_post_sbm_save_access', array( $this, 'handle_save' ) );
		add_action( 'admin_post_sbm_archive_access', array( $this, 'handle_archive' ) );
	}

	/**
	 * Render screen.
	 */
	public function render(): void {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id     = isset( $_GET['access_id'] ) ? absint( wp_unslash( $_GET['access_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

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
		$this->verify_admin_request( 'sbm_save_access', __( 'You do not have permission to manage access records.', 'studio-booking-manager' ) );

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce and capability are verified above; sanitization is centralised in AccessRepository::prepare_for_storage().
		$data = array(
			'id'                => isset( $_POST['access_id'] ) ? absint( wp_unslash( $_POST['access_id'] ) ) : 0,
			'person_id'         => isset( $_POST['person_id'] ) ? absint( wp_unslash( $_POST['person_id'] ) ) : 0,
			'location_id'       => isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0,
			'access_type'       => isset( $_POST['access_type'] ) ? sanitize_key( wp_unslash( $_POST['access_type'] ) ) : 'single_visit',
			'status'            => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'active',
			'total_credits'     => isset( $_POST['total_credits'] ) ? wp_unslash( $_POST['total_credits'] ) : '',
			'remaining_credits' => isset( $_POST['remaining_credits'] ) ? wp_unslash( $_POST['remaining_credits'] ) : '',
			'weekly_limit'      => isset( $_POST['weekly_limit'] ) ? wp_unslash( $_POST['weekly_limit'] ) : '',
			'guest_limit'       => isset( $_POST['guest_limit'] ) ? absint( wp_unslash( $_POST['guest_limit'] ) ) : 0,
			'starts_at'         => isset( $_POST['starts_at'] ) ? wp_unslash( $_POST['starts_at'] ) : '',
			'expires_at'        => isset( $_POST['expires_at'] ) ? wp_unslash( $_POST['expires_at'] ) : '',
			'metadata'          => isset( $_POST['metadata'] ) ? wp_unslash( $_POST['metadata'] ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$saved_id = $this->service->save( $data );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'sbm-access',
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
		$this->verify_admin_request( 'sbm_archive_access', __( 'You do not have permission to manage access records.', 'studio-booking-manager' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce and capability are verified above.
		$id = isset( $_POST['access_id'] ) ? absint( wp_unslash( $_POST['access_id'] ) ) : 0;
		$this->service->archive( $id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'sbm-access',
					'message' => 'archived',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render access list.
	 */
	private function render_list(): void {
		$records = $this->service->all();
		$new_url = add_query_arg(
			array(
				'page'   => 'sbm-access',
				'action' => 'new',
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( __( 'Access', 'studio-booking-manager' ), $new_url, __( 'Add New', 'studio-booking-manager' ) ); ?>
			<?php $this->render_notice(); ?>
			<div class="sbm-card sbm-card-wide">
				<table class="widefat striped sbm-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Person', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Type', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Location', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Credits', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Guest Limit', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Actions', 'studio-booking-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $records ) ) : ?>
							<tr>
								<td colspan="7"><?php echo esc_html__( 'No access records found.', 'studio-booking-manager' ); ?></td>
							</tr>
						<?php endif; ?>
						<?php foreach ( $records as $record ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $record->person_name ? (string) $record->person_name : __( 'Unknown person', 'studio-booking-manager' ) ); ?></strong>
									<div class="row-actions"><span class="edit"><a href="<?php echo esc_url( $this->edit_url( (int) $record->id ) ); ?>"><?php echo esc_html__( 'Edit', 'studio-booking-manager' ); ?></a></span></div>
								</td>
								<td><?php echo esc_html( $this->type_label( (string) $record->access_type ) ); ?></td>
								<td><?php echo esc_html( $record->location_name ? (string) $record->location_name : __( 'Unknown location', 'studio-booking-manager' ) ); ?></td>
								<td><?php echo esc_html( $this->credits_label( $record ) ); ?></td>
								<td><?php echo esc_html( (string) absint( $record->guest_limit ) ); ?></td>
								<td><?php echo wp_kses_post( Badge::render( ucfirst( (string) $record->status ), (string) $record->status ) ); ?></td>
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
	 * @param object $record Access row.
	 */
	private function render_row_actions( object $record ): void {
		?>
		<a class="button button-small" href="<?php echo esc_url( $this->edit_url( (int) $record->id ) ); ?>"><?php echo esc_html__( 'Edit', 'studio-booking-manager' ); ?></a>
		<form class="sbm-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'sbm_archive_access' ); ?>
			<input type="hidden" name="action" value="sbm_archive_access">
			<input type="hidden" name="access_id" value="<?php echo esc_attr( (string) absint( $record->id ) ); ?>">
			<button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Archive this access record?', 'studio-booking-manager' ) ); ?>');"><?php echo esc_html__( 'Archive', 'studio-booking-manager' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Render form.
	 *
	 * @param int $id Access ID.
	 */
	private function render_form( int $id = 0 ): void {
		$record       = $id > 0 ? $this->service->find( $id ) : null;
		$is_edit      = $record instanceof \stdClass;
		$people       = ( new PersonService() )->all();
		$locations    = ( new LocationService() )->all();
		$access_types = $this->service->access_types();
		$statuses     = $this->service->statuses();
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( $is_edit ? __( 'Edit Access', 'studio-booking-manager' ) : __( 'Add Access', 'studio-booking-manager' ) ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sbm-form-card">
				<?php wp_nonce_field( 'sbm_save_access' ); ?>
				<input type="hidden" name="action" value="sbm_save_access">
				<input type="hidden" name="access_id" value="<?php echo esc_attr( (string) $id ); ?>">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="sbm-access-person"><?php echo esc_html__( 'Person', 'studio-booking-manager' ); ?></label></th>
							<td><?php $this->render_people_select( $people, $is_edit ? (int) $record->person_id : 0 ); ?></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-access-location"><?php echo esc_html__( 'Location', 'studio-booking-manager' ); ?></label></th>
							<td><?php $this->render_location_select( $locations, $is_edit ? (int) $record->location_id : 0 ); ?></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-access-type"><?php echo esc_html__( 'Access Type', 'studio-booking-manager' ); ?></label></th>
							<td><?php $this->render_select( 'access_type', 'sbm-access-type', $access_types, $is_edit ? (string) $record->access_type : 'single_visit' ); ?><p class="description"><?php echo esc_html__( 'Single Visit, Visit Pass, or Membership.', 'studio-booking-manager' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-access-status"><?php echo esc_html__( 'Status', 'studio-booking-manager' ); ?></label></th>
							<td><?php $this->render_select( 'status', 'sbm-access-status', $statuses, $is_edit ? (string) $record->status : 'active' ); ?></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-access-total-credits"><?php echo esc_html__( 'Total Credits', 'studio-booking-manager' ); ?></label></th>
							<td><input name="total_credits" id="sbm-access-total-credits" type="number" min="0" class="small-text" value="<?php echo esc_attr( $is_edit && null !== $record->total_credits ? (string) absint( $record->total_credits ) : '' ); ?>"><p class="description"><?php echo esc_html__( 'For Visit Pass access, enter the total number of visits included.', 'studio-booking-manager' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-access-remaining-credits"><?php echo esc_html__( 'Remaining Credits', 'studio-booking-manager' ); ?></label></th>
							<td><input name="remaining_credits" id="sbm-access-remaining-credits" type="number" min="0" class="small-text" value="<?php echo esc_attr( $is_edit && null !== $record->remaining_credits ? (string) absint( $record->remaining_credits ) : '' ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-access-weekly-limit"><?php echo esc_html__( 'Weekly Limit', 'studio-booking-manager' ); ?></label></th>
							<td><input name="weekly_limit" id="sbm-access-weekly-limit" type="number" min="0" class="small-text" value="<?php echo esc_attr( $is_edit && null !== $record->weekly_limit ? (string) absint( $record->weekly_limit ) : '' ); ?>"><p class="description"><?php echo esc_html__( 'For example, 3 visits per week for a Flex Pass.', 'studio-booking-manager' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-access-guest-limit"><?php echo esc_html__( 'Guest Limit', 'studio-booking-manager' ); ?></label></th>
							<td><input name="guest_limit" id="sbm-access-guest-limit" type="number" min="0" class="small-text" value="<?php echo esc_attr( $is_edit ? (string) absint( $record->guest_limit ) : '0' ); ?>"><p class="description"><?php echo esc_html__( 'Number of guests allowed per visit or check-in.', 'studio-booking-manager' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-access-starts-at"><?php echo esc_html__( 'Starts At', 'studio-booking-manager' ); ?></label></th>
							<td><input name="starts_at" id="sbm-access-starts-at" type="datetime-local" value="<?php echo esc_attr( $this->datetime_local_value( $is_edit ? (string) $record->starts_at : '' ) ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-access-expires-at"><?php echo esc_html__( 'Expires At', 'studio-booking-manager' ); ?></label></th>
							<td><input name="expires_at" id="sbm-access-expires-at" type="datetime-local" value="<?php echo esc_attr( $this->datetime_local_value( $is_edit ? (string) $record->expires_at : '' ) ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-access-metadata"><?php echo esc_html__( 'Internal Notes', 'studio-booking-manager' ); ?></label></th>
							<td><textarea name="metadata" id="sbm-access-metadata" class="large-text" rows="4"><?php echo esc_textarea( $is_edit ? (string) $record->metadata : '' ); ?></textarea></td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( __( 'Save Access', 'studio-booking-manager' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render people select.
	 *
	 * @param array<int,object> $people People.
	 * @param int               $selected Selected person ID.
	 */
	private function render_people_select( array $people, int $selected ): void {
		?>
		<select name="person_id" id="sbm-access-person" required>
			<option value=""><?php echo esc_html__( 'Select a person', 'studio-booking-manager' ); ?></option>
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
	 * @param int               $selected Selected location ID.
	 */
	private function render_location_select( array $locations, int $selected ): void {
		?>
		<select name="location_id" id="sbm-access-location" required>
			<option value=""><?php echo esc_html__( 'Select a location', 'studio-booking-manager' ); ?></option>
			<?php foreach ( $locations as $location ) : ?>
				<option value="<?php echo esc_attr( (string) absint( $location->id ) ); ?>" <?php selected( $selected, (int) $location->id ); ?>><?php echo esc_html( (string) $location->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render a select field.
	 *
	 * @param string               $name Selected field name.
	 * @param string               $id Field ID.
	 * @param array<string,string> $options Options.
	 * @param string               $selected Selected value.
	 */
	private function render_select( string $name, string $id, array $options, string $selected ): void {
		?>
		<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>">
			<?php foreach ( $options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Build edit URL.
	 *
	 * @param int $id Access ID.
	 * @return string
	 */
	private function edit_url( int $id ): string {
		return add_query_arg(
			array(
				'page'      => 'sbm-access',
				'action'    => 'edit',
				'access_id' => $id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Render current query notice.
	 */
	private function render_notice(): void {
		$this->render_query_notice(
			array(
				'saved'    => __( 'Access saved successfully.', 'studio-booking-manager' ),
				'archived' => __( 'Access archived successfully.', 'studio-booking-manager' ),
				'error'    => __( 'Access could not be saved.', 'studio-booking-manager' ),
			)
		);
	}

	/**
	 * Build a human-readable access type label.
	 *
	 * @param string $type Access type.
	 * @return string
	 */
	private function type_label( string $type ): string {
		$types = $this->service->access_types();
		return $types[ $type ] ?? $type;
	}

	/**
	 * Build credits label.
	 *
	 * @param object $record Access record.
	 * @return string
	 */
	private function credits_label( object $record ): string {
		if ( 'membership' === (string) $record->access_type ) {
			return __( 'Unlimited', 'studio-booking-manager' );
		}

		$total     = null === $record->total_credits ? 0 : absint( $record->total_credits );
		$remaining = null === $record->remaining_credits ? 0 : absint( $record->remaining_credits );

		return sprintf(
			/* translators: 1: remaining credits, 2: total credits. */
			__( '%1$d / %2$d', 'studio-booking-manager' ),
			$remaining,
			$total
		);
	}

	/**
	 * Convert storage datetime to datetime-local value.
	 *
	 * @param string $value Stored value.
	 * @return string
	 */
	private function datetime_local_value( string $value ): string {
		if ( '' === $value || '0000-00-00 00:00:00' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value );

		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d\TH:i', $timestamp );
	}
}

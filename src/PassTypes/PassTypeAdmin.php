<?php
/**
 * Pass type admin screens.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\PassTypes;

use StudioBookingManager\Admin\AbstractAdminPage;
use StudioBookingManager\Admin\PageHeader;
use StudioBookingManager\Admin\AdminNotices;
use StudioBookingManager\IssuePass\IssuePassService;
use StudioBookingManager\People\PersonService;
use StudioBookingManager\Locations\LocationService;
use StudioBookingManager\UI\Badge;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and handles pass admin screens.
 */
final class PassTypeAdmin extends AbstractAdminPage {
	/**
	 * Capability required to manage passes.
	 *
	 * @var string
	 */
	protected string $capability = 'sbm_manage_passes';

	/**
	 * Pass service.
	 *
	 * @var PassTypeService
	 */
	private PassTypeService $service;

	/**
	 * Issue pass service.
	 *
	 * @var IssuePassService
	 */
	private IssuePassService $issue_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service = new PassTypeService();
		$this->issue_service = new IssuePassService();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_post_sbm_save_pass', array( $this, 'handle_save' ) );
		add_action( 'admin_post_sbm_archive_pass', array( $this, 'handle_archive' ) );
		add_action( 'admin_post_sbm_issue_pass', array( $this, 'handle_issue' ) );
	}

	/**
	 * Render screen.
	 */
	public function render(): void {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id = isset( $_GET['pass_id'] ) ? absint( wp_unslash( $_GET['pass_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'new' === $action || 'edit' === $action ) {
			$this->render_form( $id );
			return;
		}

		if ( 'issue' === $action && $id > 0 ) {
			$this->render_issue_form( $id );
			return;
		}

		$this->render_list();
	}

	/**
	 * Handle save request.
	 */
	public function handle_save(): void {
		$this->verify_admin_request( 'sbm_save_pass', __( 'You do not have permission to manage passes.', 'studio-booking-manager' ) );

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce and capability verified above; sanitization is done in repository.
		$data = array(
			'id' => isset( $_POST['pass_id'] ) ? absint( wp_unslash( $_POST['pass_id'] ) ) : 0,
			'name' => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'behaviour' => isset( $_POST['behaviour'] ) ? sanitize_key( wp_unslash( $_POST['behaviour'] ) ) : 'one_time',
			'number_of_visits' => isset( $_POST['number_of_visits'] ) ? wp_unslash( $_POST['number_of_visits'] ) : '',
			'maximum_visits_per_week' => isset( $_POST['maximum_visits_per_week'] ) ? wp_unslash( $_POST['maximum_visits_per_week'] ) : '',
			'guest_allowance' => isset( $_POST['guest_allowance'] ) ? absint( wp_unslash( $_POST['guest_allowance'] ) ) : 0,
			'booking_required' => isset( $_POST['booking_required'] ) ? 1 : 0,
			'booking_duration_minutes' => isset( $_POST['booking_duration_minutes'] ) ? wp_unslash( $_POST['booking_duration_minutes'] ) : '',
			'valid_for' => isset( $_POST['valid_for'] ) ? wp_unslash( $_POST['valid_for'] ) : '',
			'status' => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'active',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$saved_id = $this->service->save( $data );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'sbm-passes',
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
		$this->verify_admin_request( 'sbm_archive_pass', __( 'You do not have permission to manage passes.', 'studio-booking-manager' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce and capability verified above.
		$id = isset( $_POST['pass_id'] ) ? absint( wp_unslash( $_POST['pass_id'] ) ) : 0;
		$this->service->archive( $id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'sbm-passes',
					'message' => 'archived',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle issue pass request.
	 */
	public function handle_issue(): void {
		$this->verify_admin_request( 'sbm_issue_pass', __( 'You do not have permission to issue passes.', 'studio-booking-manager' ) );

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce and capability verified above.
		$pass_id = isset( $_POST['pass_id'] ) ? absint( wp_unslash( $_POST['pass_id'] ) ) : 0;
		$person_id = isset( $_POST['person_id'] ) ? absint( wp_unslash( $_POST['person_id'] ) ) : 0;
		$location_id = isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$result = $this->issue_service->issue( $pass_id, $person_id, $location_id );
		$message = 'issued';

		if ( ! $result['success'] ) {
			$message = 'issue_error';
		} elseif ( ! empty( $result['warning'] ) ) {
			$message = 'issued_duplicate';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'sbm-passes',
					'message' => $message,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render pass list.
	 */
	private function render_list(): void {
		$records = $this->service->all();
		$new_url = add_query_arg(
			array(
				'page' => 'sbm-passes',
				'action' => 'new',
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( __( 'Passes', 'studio-booking-manager' ), $new_url, __( 'Add New', 'studio-booking-manager' ) ); ?>
			<?php $this->render_notice(); ?>
			<div class="sbm-card sbm-card-wide">
				<table class="widefat striped sbm-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Pass', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Included', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Limits', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Validity', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Requirements', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Actions', 'studio-booking-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $records ) ) : ?>
							<tr><td colspan="7"><?php echo esc_html__( 'No passes yet. Add one for single visits, visit packs, memberships, or booking-required access.', 'studio-booking-manager' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $records as $record ) : ?>
							<tr>
								<td>
									<span class="sbm-pass-marker sbm-pass-marker-<?php echo esc_attr( $this->behaviour_class( $record->behaviour ) ); ?>" aria-hidden="true"></span>
									<strong><?php echo esc_html( $record->name ); ?></strong>
									<div class="description"><?php echo esc_html( $this->behaviour_label( $record->behaviour ) ); ?></div>
									<?php if ( '' !== $record->description ) : ?>
										<div class="row-actions"><?php echo esc_html( wp_trim_words( $record->description, 14 ) ); ?></div>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $this->included_label( $record ) ); ?></td>
								<td><?php echo esc_html( $this->limits_label( $record ) ); ?></td>
								<td><?php echo esc_html( $this->validity_label( $record ) ); ?></td>
								<td><?php echo wp_kses_post( $this->requirements_badges( $record ) ); ?></td>
								<td><?php echo wp_kses_post( Badge::render( $this->status_label( $record->status ), $record->status ) ); ?></td>
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
	 * @param PassType $record Pass type row.
	 */
	private function render_row_actions( PassType $record ): void {
		?>
		<a class="button button-small" href="<?php echo esc_url( $this->edit_url( (int) $record->id ) ); ?>"><?php echo esc_html__( 'Edit', 'studio-booking-manager' ); ?></a>
		<form class="sbm-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'sbm_archive_pass' ); ?>
			<input type="hidden" name="action" value="sbm_archive_pass">
			<input type="hidden" name="pass_id" value="<?php echo esc_attr( (string) absint( $record->id ) ); ?>">
			<button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Archive this pass?', 'studio-booking-manager' ) ); ?>');"><?php echo esc_html__( 'Archive', 'studio-booking-manager' ); ?></button>
		</form>
		<form class="sbm-inline-form" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="sbm-passes">
			<input type="hidden" name="action" value="issue">
			<input type="hidden" name="pass_id" value="<?php echo esc_attr( (string) absint( $record->id ) ); ?>">
			<button type="submit" class="button button-small"><?php echo esc_html__( 'Issue Pass', 'studio-booking-manager' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Render issue pass form.
	 */
	private function render_issue_form( int $id ): void {
		$pass = $this->service->find( $id );

		if ( null === $pass ) {
			AdminNotices::render( 'error', __( 'Pass not found.', 'studio-booking-manager' ) );
			$this->render_list();
			return;
		}

		$people = ( new PersonService() )->all();
		$locations = ( new LocationService() )->all();
		$back_url = add_query_arg(
			array(
				'page' => 'sbm-passes',
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( __( 'Issue Pass', 'studio-booking-manager' ), $back_url, __( 'Back to pass list', 'studio-booking-manager' ) ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sbm-form-card">
				<?php wp_nonce_field( 'sbm_issue_pass' ); ?>
				<input type="hidden" name="action" value="sbm_issue_pass">
				<input type="hidden" name="pass_id" value="<?php echo esc_attr( (string) $id ); ?>">
				<div class="sbm-card sbm-card-wide">
					<h2><?php echo esc_html( $pass->name ); ?></h2>
					<p><?php echo esc_html( $pass->description ); ?></p>
					<div class="sbm-pass-summary-grid">
						<div><strong><?php echo esc_html__( 'Included', 'studio-booking-manager' ); ?></strong><br><?php echo esc_html( $this->included_label( $pass ) ); ?></div>
						<div><strong><?php echo esc_html__( 'Limits', 'studio-booking-manager' ); ?></strong><br><?php echo esc_html( $this->limits_label( $pass ) ); ?></div>
						<div><strong><?php echo esc_html__( 'Validity', 'studio-booking-manager' ); ?></strong><br><?php echo esc_html( $this->validity_label( $pass ) ); ?></div>
					</div>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Person', 'studio-booking-manager' ); ?></th>
								<td>
									<select name="person_id" id="sbm-issue-pass-person" required>
										<option value=""><?php echo esc_html__( 'Select a person', 'studio-booking-manager' ); ?></option>
										<?php foreach ( $people as $person ) : ?>
											<option value="<?php echo esc_attr( (string) absint( $person->id ) ); ?>"><?php echo esc_html( $person->display_name ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Location', 'studio-booking-manager' ); ?></th>
								<td>
									<select name="location_id" id="sbm-issue-pass-location" required>
										<option value=""><?php echo esc_html__( 'Select a location', 'studio-booking-manager' ); ?></option>
										<?php foreach ( $locations as $location ) : ?>
											<option value="<?php echo esc_attr( (string) absint( $location->id ) ); ?>"><?php echo esc_html( $location->name ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						</tbody>
					</table>
					<?php submit_button( __( 'Issue Pass', 'studio-booking-manager' ) ); ?>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render form.
	 *
	 * @param int $id Pass type ID.
	 */
	private function render_form( int $id = 0 ): void {
		$record = $id > 0 ? $this->service->find( $id ) : null;
		$is_edit = $record instanceof PassType;
		$behaviours = $this->service->behaviours();
		$statuses = $this->service->statuses();
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( $is_edit ? __( 'Edit Pass', 'studio-booking-manager' ) : __( 'Add Pass', 'studio-booking-manager' ) ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sbm-form-card">
				<?php wp_nonce_field( 'sbm_save_pass' ); ?>
				<input type="hidden" name="action" value="sbm_save_pass">
				<input type="hidden" name="pass_id" value="<?php echo esc_attr( (string) $id ); ?>">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="sbm-pass-name"><?php echo esc_html__( 'Name', 'studio-booking-manager' ); ?></label></th>
							<td><input name="name" id="sbm-pass-name" type="text" class="regular-text" required value="<?php echo esc_attr( $is_edit ? $record->name : '' ); ?>"><p class="description"><?php echo esc_html__( 'Use the customer-facing pass name staff will recognize, such as Day Pass, 10 Visit Pack, or Monthly Membership.', 'studio-booking-manager' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-pass-description"><?php echo esc_html__( 'Description', 'studio-booking-manager' ); ?></label></th>
							<td><textarea name="description" id="sbm-pass-description" class="large-text" rows="5"><?php echo esc_textarea( $is_edit ? $record->description : '' ); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-pass-behaviour"><?php echo esc_html__( 'Pass type', 'studio-booking-manager' ); ?></label></th>
							<td><?php $this->render_select( 'behaviour', 'sbm-pass-behaviour', $behaviours, $is_edit ? $record->behaviour : 'one_time' ); ?><p class="description"><?php echo esc_html__( 'Single visit creates one visit credit. Visit pack creates a finite balance. Membership creates ongoing access until expiry.', 'studio-booking-manager' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-pass-number-of-visits"><?php echo esc_html__( 'Visits included', 'studio-booking-manager' ); ?></label></th>
							<td><input name="number_of_visits" id="sbm-pass-number-of-visits" type="number" min="0" class="small-text" value="<?php echo esc_attr( $is_edit && null !== $record->number_of_visits ? (string) absint( $record->number_of_visits ) : '' ); ?>"><p class="description"><?php echo esc_html__( 'Used for visit packs. Single visit passes are saved as 1 visit; memberships are unlimited.', 'studio-booking-manager' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-pass-maximum-visits-per-week"><?php echo esc_html__( 'Weekly visit limit', 'studio-booking-manager' ); ?></label></th>
							<td><input name="maximum_visits_per_week" id="sbm-pass-maximum-visits-per-week" type="number" min="0" class="small-text" value="<?php echo esc_attr( $is_edit && null !== $record->maximum_visits_per_week ? (string) absint( $record->maximum_visits_per_week ) : '' ); ?>"><p class="description"><?php echo esc_html__( 'Optional cap for visit packs or memberships, for example 3 visits per week.', 'studio-booking-manager' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-pass-guest-allowance"><?php echo esc_html__( 'Guests allowed per visit', 'studio-booking-manager' ); ?></label></th>
							<td><input name="guest_allowance" id="sbm-pass-guest-allowance" type="number" min="0" class="small-text" value="<?php echo esc_attr( $is_edit ? (string) absint( $record->guest_allowance ) : '0' ); ?>"><p class="description"><?php echo esc_html__( 'Use 0 when guests are not included.', 'studio-booking-manager' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-pass-booking-required"><?php echo esc_html__( 'Booking requirement', 'studio-booking-manager' ); ?></label></th>
							<td><label><input name="booking_required" id="sbm-pass-booking-required" type="checkbox" value="1" <?php checked( $is_edit ? $record->booking_required : false ); ?>> <?php echo esc_html__( 'Require a booking before check-in', 'studio-booking-manager' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-pass-booking-duration-minutes"><?php echo esc_html__( 'Default booking duration', 'studio-booking-manager' ); ?></label></th>
							<td><input name="booking_duration_minutes" id="sbm-pass-booking-duration-minutes" type="number" min="0" step="15" class="small-text" value="<?php echo esc_attr( $is_edit && null !== $record->booking_duration_minutes ? (string) absint( $record->booking_duration_minutes ) : '' ); ?>"> <?php echo esc_html__( 'minutes', 'studio-booking-manager' ); ?><p class="description"><?php echo esc_html__( 'Used as the default length for booking-required passes, such as a 30-minute or 60-minute community experience. WooCommerce products can override this.', 'studio-booking-manager' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-pass-valid-for"><?php echo esc_html__( 'Expires after', 'studio-booking-manager' ); ?></label></th>
							<td><input name="valid_for" id="sbm-pass-valid-for" type="number" min="0" class="small-text" value="<?php echo esc_attr( $is_edit && null !== $record->valid_for ? (string) absint( $record->valid_for ) : '' ); ?>"> <?php echo esc_html__( 'days', 'studio-booking-manager' ); ?><p class="description"><?php echo esc_html__( 'Leave empty for no automatic expiry.', 'studio-booking-manager' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-pass-status"><?php echo esc_html__( 'Status', 'studio-booking-manager' ); ?></label></th>
							<td><?php $this->render_select( 'status', 'sbm-pass-status', $statuses, $is_edit ? $record->status : 'active' ); ?></td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( __( 'Save Pass', 'studio-booking-manager' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render select field.
	 *
	 * @param string $name Field name.
	 * @param string $id Field ID.
	 * @param array<string,string> $options Options.
	 * @param string $selected Selected value.
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
	 * Render status notice.
	 */
	private function render_notice(): void {
		$this->render_query_notice(
			array(
				'saved' => __( 'Pass saved successfully.', 'studio-booking-manager' ),
				'archived' => __( 'Pass archived successfully.', 'studio-booking-manager' ),
				'issued' => __( 'Pass issued successfully.', 'studio-booking-manager' ),
				'issued_duplicate' => __( 'Pass issued successfully. A similar active access record already exists for this person.', 'studio-booking-manager' ),
				'issue_error' => __( 'Pass could not be issued.', 'studio-booking-manager' ),
				'error' => __( 'Pass could not be saved.', 'studio-booking-manager' ),
			)
		);
	}

	/**
	 * Get the edit URL for a pass.
	 *
	 * @param int $id Pass ID.
	 * @return string
	 */
	private function edit_url( int $id ): string {
		return add_query_arg(
			array(
				'page' => 'sbm-passes',
				'action' => 'edit',
				'pass_id' => $id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Convert behaviour to label.
	 *
	 * @param string $behaviour Behaviour.
	 * @return string
	 */
	private function behaviour_label( string $behaviour ): string {
		$behaviours = $this->service->behaviours();
		return $behaviours[ $behaviour ] ?? $behaviour;
	}

	/**
	 * Included access label.
	 *
	 * @param PassType $record Pass.
	 */
	private function included_label( PassType $record ): string {
		if ( 'one_time' === $record->behaviour ) {
			return __( '1 visit credit', 'studio-booking-manager' );
		}

		if ( 'membership' === $record->behaviour ) {
			return __( 'Unlimited visits', 'studio-booking-manager' );
		}

		if ( null !== $record->number_of_visits && $record->number_of_visits > 0 ) {
			return sprintf(
				/* translators: %d: visit count. */
				__( '%d visit credits', 'studio-booking-manager' ),
				absint( $record->number_of_visits )
			);
		}

		return __( 'Visit credits not set', 'studio-booking-manager' );
	}

	/**
	 * Limits label.
	 *
	 * @param PassType $record Pass.
	 */
	private function limits_label( PassType $record ): string {
		$parts = array();

		if ( null !== $record->maximum_visits_per_week && $record->maximum_visits_per_week > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: weekly visit limit. */
				__( '%d/week', 'studio-booking-manager' ),
				absint( $record->maximum_visits_per_week )
			);
		}

		$parts[] = $record->guest_allowance > 0
			? sprintf(
				/* translators: %d: guest allowance. */
				_n( '%d guest', '%d guests', absint( $record->guest_allowance ), 'studio-booking-manager' ),
				absint( $record->guest_allowance )
			)
			: __( 'No guests', 'studio-booking-manager' );

		return implode( ' · ', $parts );
	}

	/**
	 * Validity label.
	 *
	 * @param PassType $record Pass.
	 */
	private function validity_label( PassType $record ): string {
		return null !== $record->valid_for && $record->valid_for > 0
			? sprintf(
				/* translators: %d: number of days. */
				__( '%d days from issue', 'studio-booking-manager' ),
				absint( $record->valid_for )
			)
			: __( 'No automatic expiry', 'studio-booking-manager' );
	}

	/**
	 * Requirement badges.
	 *
	 * @param PassType $record Pass.
	 */
	private function requirements_badges( PassType $record ): string {
		$duration = null !== $record->booking_duration_minutes && $record->booking_duration_minutes > 0
			? sprintf(
				/* translators: %d: default booking duration in minutes. */
				__( ' · %d min', 'studio-booking-manager' ),
				absint( $record->booking_duration_minutes )
			)
			: '';

		if ( $record->booking_required ) {
			return Badge::render( __( 'Booking required', 'studio-booking-manager' ) . $duration, 'warning' );
		}

		return Badge::render( __( 'Walk-in allowed', 'studio-booking-manager' ) . $duration, 'success' );
	}

	/**
	 * Status label.
	 *
	 * @param string $status Status.
	 */
	private function status_label( string $status ): string {
		$statuses = $this->service->statuses();

		return $statuses[ $status ] ?? ucfirst( $status );
	}

	/**
	 * Behavior class.
	 *
	 * @param string $behaviour Behaviour.
	 */
	private function behaviour_class( string $behaviour ): string {
		return sanitize_html_class( str_replace( '_', '-', $behaviour ) );
	}
}

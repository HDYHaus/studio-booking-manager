<?php
/**
 * Location admin screens.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Locations;

use StudioBookingManager\Admin\AbstractAdminPage;
use StudioBookingManager\Admin\PageHeader;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and handles location admin screens.
 */
final class LocationAdmin extends AbstractAdminPage {
	/**
	 * Capability required to manage locations.
	 *
	 * @var string
	 */
	protected string $capability = 'sbm_manage_locations';

	/**
	 * Location service.
	 *
	 * @var LocationService
	 */
	private LocationService $service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service = new LocationService();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_post_sbm_save_location', array( $this, 'handle_save' ) );
		add_action( 'admin_post_sbm_archive_location', array( $this, 'handle_archive' ) );
		add_action( 'admin_post_sbm_set_default_location', array( $this, 'handle_set_default' ) );
	}

	/**
	 * Render screen.
	 */
	public function render(): void {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id     = isset( $_GET['location_id'] ) ? absint( wp_unslash( $_GET['location_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

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
		$this->verify_manage_request( 'sbm_save_location' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce and capability are verified above; sanitization is centralised in LocationRepository::prepare_for_storage().
		$data = array(
			'id'            => isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0,
			'name'          => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
			'slug'          => isset( $_POST['slug'] ) ? wp_unslash( $_POST['slug'] ) : '',
			'address'       => isset( $_POST['address'] ) ? wp_unslash( $_POST['address'] ) : '',
			'timezone'      => isset( $_POST['timezone'] ) ? wp_unslash( $_POST['timezone'] ) : '',
			'capacity'      => isset( $_POST['capacity'] ) ? absint( wp_unslash( $_POST['capacity'] ) ) : 0,
			'opening_hours' => isset( $_POST['opening_hours'] ) ? wp_unslash( $_POST['opening_hours'] ) : '',
			'calendar_id'   => isset( $_POST['calendar_id'] ) ? wp_unslash( $_POST['calendar_id'] ) : '',
			'status'        => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'active',
			'is_default'    => isset( $_POST['is_default'] ) ? 1 : 0,
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$saved_id = $this->service->save( $data );

		if ( $saved_id > 0 && 1 === $data['is_default'] ) {
			$this->service->set_default( $saved_id );
		}

		$redirect = add_query_arg(
			array(
				'page'    => 'sbm-locations',
				'message' => $saved_id > 0 ? 'saved' : 'error',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle archive request.
	 */
	public function handle_archive(): void {
		$this->verify_manage_request( 'sbm_archive_location' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce and capability are verified above.
		$id = isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0;
		$this->service->archive( $id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'sbm-locations',
					'message' => 'archived',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle set default request.
	 */
	public function handle_set_default(): void {
		$this->verify_manage_request( 'sbm_set_default_location' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce and capability are verified above.
		$id = isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0;
		$this->service->set_default( $id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'sbm-locations',
					'message' => 'default',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render location list.
	 */
	private function render_list(): void {
		$locations = $this->service->all();
		$new_url   = add_query_arg(
			array(
				'page'   => 'sbm-locations',
				'action' => 'new',
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( __( 'Locations', 'studio-booking-manager' ), $new_url, __( 'Add New', 'studio-booking-manager' ) ); ?>
			<?php $this->render_notice(); ?>
			<div class="sbm-card sbm-card-wide">
				<table class="widefat striped sbm-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Name', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Capacity', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Timezone', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Default', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Actions', 'studio-booking-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $locations ) ) : ?>
							<tr>
								<td colspan="6"><?php echo esc_html__( 'No locations found.', 'studio-booking-manager' ); ?></td>
							</tr>
						<?php endif; ?>
						<?php foreach ( $locations as $location ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $location->name ); ?></strong>
									<div class="row-actions">
										<span class="edit"><a href="<?php echo esc_url( $this->edit_url( (int) $location->id ) ); ?>"><?php echo esc_html__( 'Edit', 'studio-booking-manager' ); ?></a></span>
									</div>
								</td>
								<td><?php echo esc_html( (string) absint( $location->capacity ) ); ?></td>
								<td><?php echo esc_html( $location->timezone ); ?></td>
								<td><?php echo esc_html( ucfirst( (string) $location->status ) ); ?></td>
								<td><?php echo 1 === (int) $location->is_default ? esc_html__( 'Yes', 'studio-booking-manager' ) : esc_html__( 'No', 'studio-booking-manager' ); ?></td>
								<td><?php $this->render_row_actions( $location ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render row action buttons.
	 *
	 * @param object $location Location row.
	 */
	private function render_row_actions( object $location ): void {
		?>
		<a class="button button-small" href="<?php echo esc_url( $this->edit_url( (int) $location->id ) ); ?>"><?php echo esc_html__( 'Edit', 'studio-booking-manager' ); ?></a>
		<?php if ( 1 !== (int) $location->is_default ) : ?>
			<form class="sbm-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'sbm_set_default_location' ); ?>
				<input type="hidden" name="action" value="sbm_set_default_location">
				<input type="hidden" name="location_id" value="<?php echo esc_attr( (string) absint( $location->id ) ); ?>">
				<button type="submit" class="button button-small"><?php echo esc_html__( 'Make Default', 'studio-booking-manager' ); ?></button>
			</form>
		<?php endif; ?>
		<form class="sbm-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'sbm_archive_location' ); ?>
			<input type="hidden" name="action" value="sbm_archive_location">
			<input type="hidden" name="location_id" value="<?php echo esc_attr( (string) absint( $location->id ) ); ?>">
			<button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Archive this location?', 'studio-booking-manager' ) ); ?>');"><?php echo esc_html__( 'Archive', 'studio-booking-manager' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Render form.
	 *
	 * @param int $id Location ID.
	 */
	private function render_form( int $id = 0 ): void {
		$location = $id > 0 ? $this->service->find( $id ) : null;
		$is_edit  = $location instanceof \stdClass;
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( $is_edit ? __( 'Edit Location', 'studio-booking-manager' ) : __( 'Add Location', 'studio-booking-manager' ) ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sbm-form-card">
				<?php wp_nonce_field( 'sbm_save_location' ); ?>
				<input type="hidden" name="action" value="sbm_save_location">
				<input type="hidden" name="location_id" value="<?php echo esc_attr( (string) $id ); ?>">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="sbm-location-name"><?php echo esc_html__( 'Name', 'studio-booking-manager' ); ?></label></th>
							<td><input name="name" id="sbm-location-name" type="text" class="regular-text" required value="<?php echo esc_attr( $is_edit ? (string) $location->name : '' ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-location-slug"><?php echo esc_html__( 'Slug', 'studio-booking-manager' ); ?></label></th>
							<td><input name="slug" id="sbm-location-slug" type="text" class="regular-text" value="<?php echo esc_attr( $is_edit ? (string) $location->slug : '' ); ?>"><p class="description"><?php echo esc_html__( 'Used internally for integrations and URLs.', 'studio-booking-manager' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-location-address"><?php echo esc_html__( 'Address', 'studio-booking-manager' ); ?></label></th>
							<td><textarea name="address" id="sbm-location-address" class="large-text" rows="3"><?php echo esc_textarea( $is_edit ? (string) $location->address : '' ); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-location-timezone"><?php echo esc_html__( 'Timezone', 'studio-booking-manager' ); ?></label></th>
							<td><input name="timezone" id="sbm-location-timezone" type="text" class="regular-text" value="<?php echo esc_attr( $is_edit ? (string) $location->timezone : wp_timezone_string() ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-location-capacity"><?php echo esc_html__( 'Capacity', 'studio-booking-manager' ); ?></label></th>
							<td><input name="capacity" id="sbm-location-capacity" type="number" min="0" class="small-text" value="<?php echo esc_attr( $is_edit ? (string) absint( $location->capacity ) : '0' ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-location-opening-hours"><?php echo esc_html__( 'Opening Hours', 'studio-booking-manager' ); ?></label></th>
							<td><textarea name="opening_hours" id="sbm-location-opening-hours" class="large-text code" rows="6" placeholder="Monday: 09:00-19:00&#10;Tuesday: 09:00-19:00"><?php echo esc_textarea( $is_edit ? (string) $location->opening_hours : '' ); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-location-calendar-id"><?php echo esc_html__( 'Google Calendar ID', 'studio-booking-manager' ); ?></label></th>
							<td><input name="calendar_id" id="sbm-location-calendar-id" type="text" class="regular-text" value="<?php echo esc_attr( $is_edit ? (string) $location->calendar_id : '' ); ?>"><p class="description"><?php echo esc_html__( 'Placeholder until the Google Calendar integration is connected.', 'studio-booking-manager' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-location-status"><?php echo esc_html__( 'Status', 'studio-booking-manager' ); ?></label></th>
							<td>
								<select name="status" id="sbm-location-status">
									<option value="active" <?php selected( $is_edit ? (string) $location->status : 'active', 'active' ); ?>><?php echo esc_html__( 'Active', 'studio-booking-manager' ); ?></option>
									<option value="inactive" <?php selected( $is_edit ? (string) $location->status : 'active', 'inactive' ); ?>><?php echo esc_html__( 'Inactive', 'studio-booking-manager' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Default Location', 'studio-booking-manager' ); ?></th>
							<td><label><input type="checkbox" name="is_default" value="1" <?php checked( $is_edit ? (int) $location->is_default : 0, 1 ); ?>> <?php echo esc_html__( 'Use as the default location.', 'studio-booking-manager' ); ?></label></td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( $is_edit ? __( 'Update Location', 'studio-booking-manager' ) : __( 'Create Location', 'studio-booking-manager' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render notice from query args.
	 */
	private function render_notice(): void {
		$this->render_query_notice(
			array(
				'saved'    => __( 'Location saved.', 'studio-booking-manager' ),
				'archived' => __( 'Location archived.', 'studio-booking-manager' ),
				'default'  => __( 'Default location updated.', 'studio-booking-manager' ),
				'error'    => __( 'Location could not be saved.', 'studio-booking-manager' ),
			)
		);
	}

	/**
	 * Create edit URL.
	 *
	 * @param int $id Location ID.
	 * @return string
	 */
	private function edit_url( int $id ): string {
		return add_query_arg(
			array(
				'page'        => 'sbm-locations',
				'action'      => 'edit',
				'location_id' => $id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Verify admin-post request.
	 *
	 * @param string $action Nonce action.
	 */
	private function verify_manage_request( string $action ): void {
		$this->verify_admin_request( $action, __( 'You do not have permission to manage locations.', 'studio-booking-manager' ) );
	}
}


<?php
/**
 * Person admin screens.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\People;

use StudioBookingManager\Admin\AbstractAdminPage;
use StudioBookingManager\Admin\PageHeader;
use StudioBookingManager\QR\QRService;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and handles person admin screens.
 */
final class PersonAdmin extends AbstractAdminPage {
	/**
	 * Capability required to manage people.
	 *
	 * @var string
	 */
	protected string $capability = 'sbm_manage_people';

	/**
	 * Person service.
	 *
	 * @var PersonService
	 */
	private PersonService $service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service = new PersonService();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_post_sbm_save_person', array( $this, 'handle_save' ) );
		add_action( 'admin_post_sbm_archive_person', array( $this, 'handle_archive' ) );
	}

	/**
	 * Render screen.
	 */
	public function render(): void {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id     = isset( $_GET['person_id'] ) ? absint( wp_unslash( $_GET['person_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

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
		$this->verify_manage_request( 'sbm_save_person' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce and capability are verified above; sanitization is centralised in PersonRepository::prepare_for_storage().
		$data = array(
			'id'           => isset( $_POST['person_id'] ) ? absint( wp_unslash( $_POST['person_id'] ) ) : 0,
			'wp_user_id'   => isset( $_POST['wp_user_id'] ) ? absint( wp_unslash( $_POST['wp_user_id'] ) ) : 0,
			'first_name'   => isset( $_POST['first_name'] ) ? wp_unslash( $_POST['first_name'] ) : '',
			'last_name'    => isset( $_POST['last_name'] ) ? wp_unslash( $_POST['last_name'] ) : '',
			'display_name' => isset( $_POST['display_name'] ) ? wp_unslash( $_POST['display_name'] ) : '',
			'email'        => isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '',
			'phone'        => isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '',
			'status'       => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'active',
			'notes'        => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( $data['wp_user_id'] > 0 ) {
			$linked = $this->service->find_by_wp_user_id( (int) $data['wp_user_id'] );

			if ( $linked instanceof \stdClass && (int) $linked->id !== (int) $data['id'] ) {
				wp_safe_redirect(
					add_query_arg(
						array(
							'page'    => 'sbm-people',
							'message' => 'linked_user_exists',
						),
						admin_url( 'admin.php' )
					)
				);
				exit;
			}
		}

		$saved_id = $this->service->save( $data );

		$redirect = add_query_arg(
			array(
				'page'    => 'sbm-people',
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
		$this->verify_manage_request( 'sbm_archive_person' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce and capability are verified above.
		$id = isset( $_POST['person_id'] ) ? absint( wp_unslash( $_POST['person_id'] ) ) : 0;
		$this->service->archive( $id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'sbm-people',
					'message' => 'archived',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render person list.
	 */
	private function render_list(): void {
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$people = $this->service->all( $search );
		$new_url = add_query_arg(
			array(
				'page'   => 'sbm-people',
				'action' => 'new',
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( __( 'People', 'studio-booking-manager' ), $new_url, __( 'Add New', 'studio-booking-manager' ) ); ?>
			<?php $this->render_notice(); ?>
			<div class="sbm-card sbm-card-wide">
				<form method="get" class="sbm-search-form">
					<input type="hidden" name="page" value="sbm-people">
					<p class="search-box">
						<label class="screen-reader-text" for="sbm-person-search-input"><?php echo esc_html__( 'Search people', 'studio-booking-manager' ); ?></label>
						<input type="search" id="sbm-person-search-input" name="s" value="<?php echo esc_attr( $search ); ?>">
						<?php submit_button( __( 'Search People', 'studio-booking-manager' ), '', '', false ); ?>
					</p>
				</form>
				<table class="widefat striped sbm-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Name', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Email', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Phone', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Account', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'QR Identity', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'studio-booking-manager' ); ?></th>
							<th><?php echo esc_html__( 'Actions', 'studio-booking-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $people ) ) : ?>
							<tr>
								<td colspan="7"><?php echo esc_html__( 'No people found.', 'studio-booking-manager' ); ?></td>
							</tr>
						<?php endif; ?>
						<?php foreach ( $people as $person ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $person->display_name ); ?></strong>
									<div class="row-actions">
										<span class="edit"><a href="<?php echo esc_url( $this->edit_url( (int) $person->id ) ); ?>"><?php echo esc_html__( 'Edit', 'studio-booking-manager' ); ?></a></span> | <span class="qr"><a href="<?php echo esc_url( $this->qr_view_url( (int) $person->id ) ); ?>"><?php echo esc_html__( 'QR Code', 'studio-booking-manager' ); ?></a></span> | <span class="checkin"><a href="<?php echo esc_url( $this->qr_checkin_url( $person ) ); ?>"><?php echo esc_html__( 'QR Check-in', 'studio-booking-manager' ); ?></a></span>
									</div>
								</td>
								<td><?php echo esc_html( (string) $person->email ); ?></td>
								<td><?php echo esc_html( (string) $person->phone ); ?></td>
								<td><?php echo esc_html( $this->account_label( $person ) ); ?></td>
								<td><code><?php echo esc_html( substr( (string) $person->qr_token, 0, 12 ) ); ?>...</code></td>
								<td><?php echo esc_html( ucfirst( (string) $person->status ) ); ?></td>
								<td><?php $this->render_row_actions( $person ); ?></td>
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
	 * @param object $person Person row.
	 */
	private function render_row_actions( object $person ): void {
		?>
		<a class="button button-small" href="<?php echo esc_url( $this->edit_url( (int) $person->id ) ); ?>"><?php echo esc_html__( 'Edit', 'studio-booking-manager' ); ?></a>
		<a class="button button-small" href="<?php echo esc_url( $this->qr_view_url( (int) $person->id ) ); ?>"><?php echo esc_html__( 'QR Code', 'studio-booking-manager' ); ?></a>
		<a class="button button-small" href="<?php echo esc_url( $this->qr_checkin_url( $person ) ); ?>"><?php echo esc_html__( 'QR Check-in', 'studio-booking-manager' ); ?></a>
		<form class="sbm-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'sbm_archive_person' ); ?>
			<input type="hidden" name="action" value="sbm_archive_person">
			<input type="hidden" name="person_id" value="<?php echo esc_attr( (string) absint( $person->id ) ); ?>">
			<button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Archive this person?', 'studio-booking-manager' ) ); ?>');"><?php echo esc_html__( 'Archive', 'studio-booking-manager' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Render form.
	 *
	 * @param int $id Person ID.
	 */
	private function render_form( int $id = 0 ): void {
		$person  = $id > 0 ? $this->service->find( $id ) : null;
		$is_edit = $person instanceof \stdClass;
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( $is_edit ? __( 'Edit Person', 'studio-booking-manager' ) : __( 'Add Person', 'studio-booking-manager' ) ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sbm-form-card">
				<?php wp_nonce_field( 'sbm_save_person' ); ?>
				<input type="hidden" name="action" value="sbm_save_person">
				<input type="hidden" name="person_id" value="<?php echo esc_attr( (string) $id ); ?>">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="sbm-person-first-name"><?php echo esc_html__( 'First Name', 'studio-booking-manager' ); ?></label></th>
							<td><input name="first_name" id="sbm-person-first-name" type="text" class="regular-text" value="<?php echo esc_attr( $is_edit ? (string) $person->first_name : '' ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-person-last-name"><?php echo esc_html__( 'Last Name', 'studio-booking-manager' ); ?></label></th>
							<td><input name="last_name" id="sbm-person-last-name" type="text" class="regular-text" value="<?php echo esc_attr( $is_edit ? (string) $person->last_name : '' ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-person-display-name"><?php echo esc_html__( 'Display Name', 'studio-booking-manager' ); ?></label></th>
							<td><input name="display_name" id="sbm-person-display-name" type="text" class="regular-text" required value="<?php echo esc_attr( $is_edit ? (string) $person->display_name : '' ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-person-email"><?php echo esc_html__( 'Email', 'studio-booking-manager' ); ?></label></th>
							<td><input name="email" id="sbm-person-email" type="email" class="regular-text" value="<?php echo esc_attr( $is_edit ? (string) $person->email : '' ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-person-phone"><?php echo esc_html__( 'Phone', 'studio-booking-manager' ); ?></label></th>
							<td><input name="phone" id="sbm-person-phone" type="text" class="regular-text" value="<?php echo esc_attr( $is_edit ? (string) $person->phone : '' ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-person-wp-user-id"><?php echo esc_html__( 'WordPress account', 'studio-booking-manager' ); ?></label></th>
							<td>
								<?php $this->render_user_select( $is_edit ? absint( $person->wp_user_id ) : 0 ); ?>
								<p class="description"><?php echo esc_html__( 'Link this person to a customer account so they can view their own passes, bookings, and QR identity in My Account. Choose No linked account to unlink them.', 'studio-booking-manager' ); ?></p>
							</td>
						</tr>
						<?php if ( $is_edit ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html__( 'QR Identity', 'studio-booking-manager' ); ?></th>
								<td>
									<code><?php echo esc_html( (string) $person->qr_token ); ?></code>
									<p class="description"><?php echo esc_html__( 'Regenerate this token to revoke existing QR codes for this person.', 'studio-booking-manager' ); ?></p>
									<p>
										<a class="button" href="<?php echo esc_url( $this->qr_view_url( (int) $person->id ) ); ?>"><?php echo esc_html__( 'View QR Code', 'studio-booking-manager' ); ?></a>
									</p>
								</td>
							</tr>
						<?php endif; ?>
						<tr>
							<th scope="row"><label for="sbm-person-status"><?php echo esc_html__( 'Status', 'studio-booking-manager' ); ?></label></th>
							<td>
								<select name="status" id="sbm-person-status">
									<option value="active" <?php selected( $is_edit ? (string) $person->status : 'active', 'active' ); ?>><?php echo esc_html__( 'Active', 'studio-booking-manager' ); ?></option>
									<option value="inactive" <?php selected( $is_edit ? (string) $person->status : 'active', 'inactive' ); ?>><?php echo esc_html__( 'Inactive', 'studio-booking-manager' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="sbm-person-notes"><?php echo esc_html__( 'Notes', 'studio-booking-manager' ); ?></label></th>
							<td><textarea name="notes" id="sbm-person-notes" class="large-text" rows="5"><?php echo esc_textarea( $is_edit ? (string) $person->notes : '' ); ?></textarea></td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( $is_edit ? __( 'Update Person', 'studio-booking-manager' ) : __( 'Create Person', 'studio-booking-manager' ) ); ?>
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
				'saved'    => __( 'Person saved.', 'studio-booking-manager' ),
				'archived' => __( 'Person archived.', 'studio-booking-manager' ),
				'linked_user_exists' => __( 'That WordPress account is already linked to another person.', 'studio-booking-manager' ),
				'error'    => __( 'Person could not be saved.', 'studio-booking-manager' ),
			)
		);
	}

	/**
	 * Render WordPress user selector.
	 *
	 * @param int $selected Selected user ID.
	 */
	private function render_user_select( int $selected ): void {
		$users = get_users(
			array(
				'fields'  => array( 'ID', 'display_name', 'user_email' ),
				'number'  => 200,
				'orderby' => 'display_name',
				'order'   => 'ASC',
			)
		);

		if ( $selected > 0 ) {
			$selected_user = get_userdata( $selected );
			$has_selected  = false;

			foreach ( $users as $user ) {
				if ( (int) $user->ID === $selected ) {
					$has_selected = true;
					break;
				}
			}

			if ( $selected_user instanceof \WP_User && ! $has_selected ) {
				$users[] = $selected_user;
			}
		}
		?>
		<select name="wp_user_id" id="sbm-person-wp-user-id">
			<option value="0"><?php echo esc_html__( 'No linked account', 'studio-booking-manager' ); ?></option>
			<?php foreach ( $users as $user ) : ?>
				<option value="<?php echo esc_attr( (string) absint( $user->ID ) ); ?>" <?php selected( $selected, (int) $user->ID ); ?>><?php echo esc_html( $this->user_option_label( $user ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Build an account label for the people list.
	 *
	 * @param object $person Person row.
	 */
	private function account_label( object $person ): string {
		$user_id = absint( $person->wp_user_id );

		if ( $user_id <= 0 ) {
			return __( 'Not linked', 'studio-booking-manager' );
		}

		$user = get_userdata( $user_id );

		return $user instanceof \WP_User ? $this->user_option_label( $user ) : __( 'Linked account missing', 'studio-booking-manager' );
	}

	/**
	 * Build a user option label.
	 *
	 * @param object $user WordPress user-like object.
	 */
	private function user_option_label( object $user ): string {
		$name  = '' !== (string) $user->display_name ? (string) $user->display_name : __( 'Unnamed user', 'studio-booking-manager' );
		$email = isset( $user->user_email ) ? (string) $user->user_email : '';

		if ( '' === $email ) {
			return $name;
		}

		return sprintf(
			/* translators: 1: user display name, 2: user email. */
			__( '%1$s (%2$s)', 'studio-booking-manager' ),
			$name,
			$email
		);
	}

	/**
	 * Create edit URL.
	 *
	 * @param int $id Person ID.
	 * @return string
	 */
	private function edit_url( int $id ): string {
		return add_query_arg(
			array(
				'page'      => 'sbm-people',
				'action'    => 'edit',
				'person_id' => $id,
			),
			admin_url( 'admin.php' )
		);
	}


	/**
	 * Create QR check-in URL for a person.
	 *
	 * @param object $person Person row.
	 * @return string
	 */
	private function qr_checkin_url( object $person ): string {
		return ( new QRService() )->person_checkin_url( $person );
	}

	/**
	 * Create QR view URL.
	 *
	 * @param int $id Person ID.
	 */
	private function qr_view_url( int $id ): string {
		return add_query_arg(
			array(
				'page'      => 'sbm-qr-checkin',
				'action'    => 'person',
				'person_id' => $id,
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
		$this->verify_admin_request( $action, __( 'You do not have permission to manage people.', 'studio-booking-manager' ) );
	}
}

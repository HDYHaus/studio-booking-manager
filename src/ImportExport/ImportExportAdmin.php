<?php
/**
 * Import and export admin tools.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\ImportExport;

use StudioBookingManager\Access\AccessService;
use StudioBookingManager\Admin\AbstractAdminPage;
use StudioBookingManager\Admin\PageHeader;
use StudioBookingManager\Bookings\BookingRepository;
use StudioBookingManager\Locations\LocationService;
use StudioBookingManager\PassTypes\PassTypeService;
use StudioBookingManager\People\PersonService;
use StudioBookingManager\Visits\VisitRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Provides CSV import/export tools for staff.
 */
final class ImportExportAdmin extends AbstractAdminPage {
	/**
	 * Capability required to use import/export tools.
	 *
	 * @var string
	 */
	protected string $capability = 'sbm_manage_settings';

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_post_sbm_export_dataset', array( $this, 'handle_export' ) );
		add_action( 'admin_post_sbm_import_dataset', array( $this, 'handle_import' ) );
	}

	/**
	 * Render screen.
	 */
	public function render(): void {
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have permission to use import and export tools.', 'studio-booking-manager' ) );
		}

		$result = $this->get_import_result();
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( __( 'Import / Export', 'studio-booking-manager' ) ); ?>
			<?php $this->render_result( $result ); ?>

			<div class="sbm-report-panels">
				<section class="sbm-card">
					<h2><?php echo esc_html__( 'Export CSV', 'studio-booking-manager' ); ?></h2>
					<p><?php echo esc_html__( 'Download current Studio Booking data as CSV files for backups, audits, or spreadsheet work.', 'studio-booking-manager' ); ?></p>
					<div class="sbm-import-export-actions">
						<?php foreach ( $this->export_datasets() as $dataset => $label ) : ?>
							<?php $this->render_export_button( $dataset, $label ); ?>
						<?php endforeach; ?>
					</div>
				</section>

				<section class="sbm-card">
					<h2><?php echo esc_html__( 'Import CSV', 'studio-booking-manager' ); ?></h2>
					<p><?php echo esc_html__( 'Import people or access records from CSV. Use Dry run first to validate rows without changing data.', 'studio-booking-manager' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
						<?php wp_nonce_field( 'sbm_import_dataset' ); ?>
						<input type="hidden" name="action" value="sbm_import_dataset">
						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row"><label for="sbm-import-dataset"><?php echo esc_html__( 'Dataset', 'studio-booking-manager' ); ?></label></th>
									<td>
										<select name="dataset" id="sbm-import-dataset">
											<option value="people"><?php echo esc_html__( 'People', 'studio-booking-manager' ); ?></option>
											<option value="access"><?php echo esc_html__( 'Access', 'studio-booking-manager' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="sbm-import-file"><?php echo esc_html__( 'CSV file', 'studio-booking-manager' ); ?></label></th>
									<td><input type="file" name="import_file" id="sbm-import-file" accept=".csv,text/csv" required></td>
								</tr>
								<tr>
									<th scope="row"><?php echo esc_html__( 'Mode', 'studio-booking-manager' ); ?></th>
									<td>
										<label><input type="radio" name="mode" value="dry_run" checked> <?php echo esc_html__( 'Dry run validation', 'studio-booking-manager' ); ?></label><br>
										<label><input type="radio" name="mode" value="import"> <?php echo esc_html__( 'Import valid rows', 'studio-booking-manager' ); ?></label>
									</td>
								</tr>
							</tbody>
						</table>
						<?php submit_button( __( 'Run Import', 'studio-booking-manager' ) ); ?>
					</form>
				</section>
			</div>

			<div class="sbm-card sbm-card-wide">
				<h2><?php echo esc_html__( 'Expected CSV columns', 'studio-booking-manager' ); ?></h2>
				<div class="sbm-import-help-grid">
					<div>
						<h3><?php echo esc_html__( 'People', 'studio-booking-manager' ); ?></h3>
						<p><code>id, wp_user_id, first_name, last_name, display_name, email, phone, status, notes</code></p>
						<p><?php echo esc_html__( 'A row updates by id when provided, otherwise by matching email. New people need a display name, first/last name, or email.', 'studio-booking-manager' ); ?></p>
					</div>
					<div>
						<h3><?php echo esc_html__( 'Access', 'studio-booking-manager' ); ?></h3>
						<p><code>id, person_id, person_email, location_id, location_slug, access_type, status, total_credits, remaining_credits, weekly_limit, guest_limit, starts_at, expires_at, metadata</code></p>
						<p><?php echo esc_html__( 'Access rows require a person and location, using either IDs or the email/slug lookup columns.', 'studio-booking-manager' ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle CSV export.
	 */
	public function handle_export(): void {
		$this->verify_admin_request( 'sbm_export_dataset', __( 'You do not have permission to export Studio Booking data.', 'studio-booking-manager' ) );

		$dataset = isset( $_GET['dataset'] ) ? sanitize_key( wp_unslash( $_GET['dataset'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified above.
		$export  = $this->get_export_data( $dataset );

		if ( empty( $export['headers'] ) ) {
			wp_die( esc_html__( 'Unknown export dataset.', 'studio-booking-manager' ) );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( 'studio-booking-' . $dataset . '-' . current_time( 'Y-m-d' ) . '.csv' ) . '"' );

		$output = fopen( 'php://output', 'w' );

		if ( false === $output ) {
			exit;
		}

		fputcsv( $output, $export['headers'] );

		foreach ( $export['rows'] as $row ) {
			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Handle CSV import.
	 */
	public function handle_import(): void {
		$this->verify_admin_request( 'sbm_import_dataset', __( 'You do not have permission to import Studio Booking data.', 'studio-booking-manager' ) );

		$dataset = isset( $_POST['dataset'] ) ? sanitize_key( wp_unslash( $_POST['dataset'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$mode    = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'dry_run'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$dry_run = 'import' !== $mode;

		$result = array(
			'dataset'  => $dataset,
			'dry_run'  => $dry_run,
			'total'    => 0,
			'valid'    => 0,
			'imported' => 0,
			'errors'   => array(),
		);

		$rows = $this->read_uploaded_csv();

		if ( is_wp_error( $rows ) ) {
			$result['errors'][] = array(
				'row'     => 0,
				'message' => $rows->get_error_message(),
			);
			$this->redirect_with_result( $result );
		}

		if ( ! in_array( $dataset, array( 'people', 'access' ), true ) ) {
			$result['errors'][] = array(
				'row'     => 0,
				'message' => __( 'Choose People or Access for import.', 'studio-booking-manager' ),
			);
			$this->redirect_with_result( $result );
		}

		foreach ( $rows as $index => $row ) {
			$row_number      = $index + 2;
			$result['total'] = (int) $result['total'] + 1;
			$validation      = 'people' === $dataset ? $this->validate_people_row( $row ) : $this->validate_access_row( $row );

			if ( ! empty( $validation['errors'] ) ) {
				foreach ( $validation['errors'] as $error ) {
					$result['errors'][] = array(
						'row'     => $row_number,
						'message' => $error,
					);
				}
				continue;
			}

			$result['valid'] = (int) $result['valid'] + 1;

			if ( ! $dry_run ) {
				$id = 'people' === $dataset ? $this->import_people_row( $validation['data'] ) : $this->import_access_row( $validation['data'] );

				if ( $id > 0 ) {
					$result['imported'] = (int) $result['imported'] + 1;
				} else {
					$result['errors'][] = array(
						'row'     => $row_number,
						'message' => __( 'The row passed validation but could not be saved.', 'studio-booking-manager' ),
					);
				}
			}
		}

		$this->redirect_with_result( $result );
	}

	/**
	 * Export dataset labels.
	 *
	 * @return array<string,string>
	 */
	private function export_datasets(): array {
		return array(
			'people'   => __( 'People', 'studio-booking-manager' ),
			'passes'   => __( 'Passes', 'studio-booking-manager' ),
			'access'   => __( 'Access', 'studio-booking-manager' ),
			'visits'   => __( 'Visits', 'studio-booking-manager' ),
			'bookings' => __( 'Bookings', 'studio-booking-manager' ),
		);
	}

	/**
	 * Render export button.
	 *
	 * @param string $dataset Dataset key.
	 * @param string $label Dataset label.
	 */
	private function render_export_button( string $dataset, string $label ): void {
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'sbm_export_dataset',
					'dataset' => $dataset,
				),
				admin_url( 'admin-post.php' )
			),
			'sbm_export_dataset'
		);
		?>
		<a class="button" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( sprintf( /* translators: %s: dataset name. */ __( 'Export %s', 'studio-booking-manager' ), $label ) ); ?></a>
		<?php
	}

	/**
	 * Get export data.
	 *
	 * @param string $dataset Dataset key.
	 * @return array{headers:array<int,string>,rows:array<int,array<int,string>>}
	 */
	private function get_export_data( string $dataset ): array {
		switch ( $dataset ) {
			case 'people':
				return $this->people_export();
			case 'passes':
				return $this->passes_export();
			case 'access':
				return $this->access_export();
			case 'visits':
				return $this->visits_export();
			case 'bookings':
				return $this->bookings_export();
		}

		return array(
			'headers' => array(),
			'rows'    => array(),
		);
	}

	/**
	 * People export data.
	 *
	 * @return array{headers:array<int,string>,rows:array<int,array<int,string>>}
	 */
	private function people_export(): array {
		$headers = array( 'id', 'wp_user_id', 'first_name', 'last_name', 'display_name', 'email', 'phone', 'status', 'notes', 'qr_token', 'created_at', 'updated_at' );
		$rows    = array();

		foreach ( ( new PersonService() )->all() as $person ) {
			$rows[] = $this->object_row( $person, $headers );
		}

		return compact( 'headers', 'rows' );
	}

	/**
	 * Passes export data.
	 *
	 * @return array{headers:array<int,string>,rows:array<int,array<int,string>>}
	 */
	private function passes_export(): array {
		$headers = array( 'id', 'name', 'description', 'behaviour', 'number_of_visits', 'maximum_visits_per_week', 'guest_allowance', 'booking_required', 'valid_for', 'status', 'created_at', 'updated_at' );
		$rows    = array();

		foreach ( ( new PassTypeService() )->all() as $pass ) {
			$rows[] = $this->object_row( $pass, $headers );
		}

		return compact( 'headers', 'rows' );
	}

	/**
	 * Access export data.
	 *
	 * @return array{headers:array<int,string>,rows:array<int,array<int,string>>}
	 */
	private function access_export(): array {
		$headers = array( 'id', 'person_id', 'person_name', 'location_id', 'location_name', 'wp_user_id', 'access_type', 'status', 'total_credits', 'remaining_credits', 'weekly_limit', 'guest_limit', 'starts_at', 'expires_at', 'metadata', 'created_at', 'updated_at' );
		$rows    = array();

		foreach ( ( new AccessService() )->all() as $access ) {
			$rows[] = $this->object_row( $access, $headers );
		}

		return compact( 'headers', 'rows' );
	}

	/**
	 * Visits export data.
	 *
	 * @return array{headers:array<int,string>,rows:array<int,array<int,string>>}
	 */
	private function visits_export(): array {
		$headers = array( 'id', 'person_id', 'person_name', 'location_id', 'location_name', 'access_id', 'access_type', 'booking_id', 'status', 'visit_date', 'scheduled_start', 'scheduled_end', 'checked_in_at', 'checked_out_at', 'guest_count', 'guest_names', 'checkin_method', 'notes', 'created_at', 'updated_at' );
		$rows    = array();

		foreach ( ( new VisitRepository() )->all() as $visit ) {
			$rows[] = $this->object_row( $visit, $headers );
		}

		return compact( 'headers', 'rows' );
	}

	/**
	 * Bookings export data.
	 *
	 * @return array{headers:array<int,string>,rows:array<int,array<int,string>>}
	 */
	private function bookings_export(): array {
		$headers = array( 'id', 'person_id', 'person_name', 'location_id', 'location_name', 'access_id', 'access_type', 'status', 'starts_at', 'ends_at', 'guest_count', 'guest_names', 'calendar_sync_status', 'calendar_sync_error', 'notes', 'created_at', 'updated_at' );
		$rows    = array();

		foreach ( ( new BookingRepository() )->all() as $booking ) {
			$rows[] = $this->object_row( $booking, $headers );
		}

		return compact( 'headers', 'rows' );
	}

	/**
	 * Build CSV row from object keys.
	 *
	 * @param object              $object Source object.
	 * @param array<int,string>   $keys Keys.
	 * @return array<int,string>
	 */
	private function object_row( object $object, array $keys ): array {
		$row = array();

		foreach ( $keys as $key ) {
			$row[] = isset( $object->{$key} ) ? (string) $object->{$key} : '';
		}

		return $row;
	}

	/**
	 * Read uploaded CSV into associative rows.
	 *
	 * @return array<int,array<string,string>>|\WP_Error
	 */
	private function read_uploaded_csv() {
		if ( empty( $_FILES['import_file'] ) || ! is_array( $_FILES['import_file'] ) ) {
			return new \WP_Error( 'missing_file', __( 'Upload a CSV file.', 'studio-booking-manager' ) );
		}

		$file = $_FILES['import_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Upload metadata validated below.

		if ( ! empty( $file['error'] ) ) {
			return new \WP_Error( 'upload_error', __( 'The CSV file could not be uploaded.', 'studio-booking-manager' ) );
		}

		$tmp_name = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';

		if ( '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			return new \WP_Error( 'invalid_file', __( 'The uploaded file was not valid.', 'studio-booking-manager' ) );
		}

		$handle = fopen( $tmp_name, 'r' );

		if ( false === $handle ) {
			return new \WP_Error( 'read_error', __( 'The CSV file could not be read.', 'studio-booking-manager' ) );
		}

		$header = fgetcsv( $handle );

		if ( ! is_array( $header ) || empty( $header ) ) {
			fclose( $handle );
			return new \WP_Error( 'empty_file', __( 'The CSV file must include a header row.', 'studio-booking-manager' ) );
		}

		$header = array_map( array( $this, 'normalize_header' ), $header );
		$rows   = array();

		while ( false !== ( $values = fgetcsv( $handle ) ) ) {
			if ( ! is_array( $values ) || $this->is_empty_csv_row( $values ) ) {
				continue;
			}

			$row = array();

			foreach ( $header as $index => $key ) {
				if ( '' === $key ) {
					continue;
				}

				$row[ $key ] = isset( $values[ $index ] ) ? trim( (string) $values[ $index ] ) : '';
			}

			$rows[] = $row;
		}

		fclose( $handle );

		return $rows;
	}

	/**
	 * Normalize a CSV header.
	 *
	 * @param string $value Header value.
	 */
	private function normalize_header( string $value ): string {
		return sanitize_key( strtolower( trim( $value ) ) );
	}

	/**
	 * Determine whether a CSV row is empty.
	 *
	 * @param array<int,mixed> $row Row values.
	 */
	private function is_empty_csv_row( array $row ): bool {
		foreach ( $row as $value ) {
			if ( '' !== trim( (string) $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate a people import row.
	 *
	 * @param array<string,string> $row Row.
	 * @return array{data:array<string,mixed>,errors:array<int,string>}
	 */
	private function validate_people_row( array $row ): array {
		$email        = isset( $row['email'] ) ? sanitize_email( $row['email'] ) : '';
		$first_name   = isset( $row['first_name'] ) ? sanitize_text_field( $row['first_name'] ) : '';
		$last_name    = isset( $row['last_name'] ) ? sanitize_text_field( $row['last_name'] ) : '';
		$display_name = isset( $row['display_name'] ) ? sanitize_text_field( $row['display_name'] ) : '';
		$status       = isset( $row['status'] ) && 'inactive' === sanitize_key( $row['status'] ) ? 'inactive' : 'active';
		$errors       = array();

		if ( '' === $display_name && '' === trim( $first_name . ' ' . $last_name ) && '' === $email ) {
			$errors[] = __( 'People rows need display_name, first/last name, or email.', 'studio-booking-manager' );
		}

		if ( isset( $row['email'] ) && '' !== $row['email'] && '' === $email ) {
			$errors[] = __( 'Email is not valid.', 'studio-booking-manager' );
		}

		$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;

		if ( $id > 0 && ! ( ( new PersonService() )->find( $id ) instanceof \stdClass ) ) {
			$errors[] = __( 'No person exists with the provided id.', 'studio-booking-manager' );
		}

		$wp_user_id = isset( $row['wp_user_id'] ) ? absint( $row['wp_user_id'] ) : 0;

		if ( $wp_user_id > 0 && ! get_userdata( $wp_user_id ) instanceof \WP_User ) {
			$errors[] = __( 'No WordPress user exists with the provided wp_user_id.', 'studio-booking-manager' );
		}

		$data = array(
			'id'           => $id,
			'wp_user_id'   => $wp_user_id,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => $display_name,
			'email'        => $email,
			'phone'        => isset( $row['phone'] ) ? sanitize_text_field( $row['phone'] ) : '',
			'status'       => $status,
			'notes'        => isset( $row['notes'] ) ? sanitize_textarea_field( $row['notes'] ) : '',
		);

		return array(
			'data'   => $data,
			'errors' => $errors,
		);
	}

	/**
	 * Validate an access import row.
	 *
	 * @param array<string,string> $row Row.
	 * @return array{data:array<string,mixed>,errors:array<int,string>}
	 */
	private function validate_access_row( array $row ): array {
		$errors      = array();
		$person_id   = $this->resolve_person_id( $row );
		$location_id = $this->resolve_location_id( $row );
		$access_type = isset( $row['access_type'] ) ? sanitize_key( $row['access_type'] ) : 'single_visit';
		$status      = isset( $row['status'] ) ? sanitize_key( $row['status'] ) : 'active';

		if ( $person_id <= 0 ) {
			$errors[] = __( 'A valid person_id or person_email is required.', 'studio-booking-manager' );
		}

		if ( $location_id <= 0 ) {
			$errors[] = __( 'A valid location_id or location_slug is required.', 'studio-booking-manager' );
		}

		if ( ! in_array( $access_type, array( 'single_visit', 'visit_pass', 'membership' ), true ) ) {
			$errors[] = __( 'access_type must be single_visit, visit_pass, or membership.', 'studio-booking-manager' );
		}

		if ( ! in_array( $status, array( 'active', 'pending', 'expired', 'suspended', 'archived' ), true ) ) {
			$errors[] = __( 'status must be active, pending, expired, suspended, or archived.', 'studio-booking-manager' );
		}

		$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;

		if ( $id > 0 && ! ( ( new AccessService() )->find( $id ) instanceof \stdClass ) ) {
			$errors[] = __( 'No access record exists with the provided id.', 'studio-booking-manager' );
		}

		foreach ( array( 'total_credits', 'remaining_credits', 'weekly_limit', 'guest_limit' ) as $numeric_key ) {
			if ( isset( $row[ $numeric_key ] ) && '' !== $row[ $numeric_key ] && ! ctype_digit( $row[ $numeric_key ] ) ) {
				$errors[] = sprintf(
					/* translators: %s: column name. */
					__( '%s must be a whole number.', 'studio-booking-manager' ),
					$numeric_key
				);
			}
		}

		foreach ( array( 'starts_at', 'expires_at' ) as $date_key ) {
			if ( isset( $row[ $date_key ] ) && '' !== $row[ $date_key ] && false === strtotime( $row[ $date_key ] ) ) {
				$errors[] = sprintf(
					/* translators: %s: column name. */
					__( '%s must be a valid date or datetime.', 'studio-booking-manager' ),
					$date_key
				);
			}
		}

		$data = array(
			'id'                => $id,
			'person_id'         => $person_id,
			'location_id'       => $location_id,
			'wp_user_id'        => isset( $row['wp_user_id'] ) ? absint( $row['wp_user_id'] ) : 0,
			'order_id'          => isset( $row['order_id'] ) ? absint( $row['order_id'] ) : 0,
			'product_id'        => isset( $row['product_id'] ) ? absint( $row['product_id'] ) : 0,
			'variation_id'      => isset( $row['variation_id'] ) ? absint( $row['variation_id'] ) : 0,
			'access_type'       => $access_type,
			'status'            => $status,
			'total_credits'     => $row['total_credits'] ?? '',
			'remaining_credits' => $row['remaining_credits'] ?? '',
			'weekly_limit'      => $row['weekly_limit'] ?? '',
			'guest_limit'       => isset( $row['guest_limit'] ) ? absint( $row['guest_limit'] ) : 0,
			'starts_at'         => $row['starts_at'] ?? '',
			'expires_at'        => $row['expires_at'] ?? '',
			'metadata'          => isset( $row['metadata'] ) ? sanitize_textarea_field( $row['metadata'] ) : '',
		);

		return array(
			'data'   => $data,
			'errors' => $errors,
		);
	}

	/**
	 * Resolve person ID from import row.
	 *
	 * @param array<string,string> $row Row.
	 */
	private function resolve_person_id( array $row ): int {
		$people = new PersonService();
		$id     = isset( $row['person_id'] ) ? absint( $row['person_id'] ) : 0;

		if ( $id > 0 && $people->find( $id ) instanceof \stdClass ) {
			return $id;
		}

		$email = isset( $row['person_email'] ) ? sanitize_email( $row['person_email'] ) : '';

		if ( '' === $email ) {
			return 0;
		}

		$person = $people->find_by_email( $email );

		return $person instanceof \stdClass ? (int) $person->id : 0;
	}

	/**
	 * Resolve location ID from import row.
	 *
	 * @param array<string,string> $row Row.
	 */
	private function resolve_location_id( array $row ): int {
		$locations = ( new LocationService() )->all();
		$id        = isset( $row['location_id'] ) ? absint( $row['location_id'] ) : 0;

		foreach ( $locations as $location ) {
			if ( $id > 0 && (int) $location->id === $id ) {
				return $id;
			}
		}

		$slug = isset( $row['location_slug'] ) ? sanitize_title( $row['location_slug'] ) : '';

		if ( '' === $slug ) {
			return 0;
		}

		foreach ( $locations as $location ) {
			if ( (string) $location->slug === $slug ) {
				return (int) $location->id;
			}
		}

		return 0;
	}

	/**
	 * Import a people row.
	 *
	 * @param array<string,mixed> $data Data.
	 */
	private function import_people_row( array $data ): int {
		$people = new PersonService();

		if ( empty( $data['id'] ) && ! empty( $data['email'] ) ) {
			$existing = $people->find_by_email( (string) $data['email'] );

			if ( $existing instanceof \stdClass ) {
				$data['id'] = (int) $existing->id;
			}
		}

		return $people->save( $data );
	}

	/**
	 * Import an access row.
	 *
	 * @param array<string,mixed> $data Data.
	 */
	private function import_access_row( array $data ): int {
		return ( new AccessService() )->save( $data );
	}

	/**
	 * Redirect with stored import result.
	 *
	 * @param array<string,mixed> $result Result data.
	 */
	private function redirect_with_result( array $result ): void {
		$key = 'sbm_import_' . get_current_user_id() . '_' . wp_generate_password( 8, false );

		set_transient( $key, $result, 10 * MINUTE_IN_SECONDS );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'sbm-import-export',
					'import_id' => rawurlencode( $key ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Get import result from transient.
	 *
	 * @return array<string,mixed>|null
	 */
	private function get_import_result(): ?array {
		$key = isset( $_GET['import_id'] ) ? sanitize_key( wp_unslash( $_GET['import_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' === $key ) {
			return null;
		}

		$result = get_transient( $key );
		delete_transient( $key );

		return is_array( $result ) ? $result : null;
	}

	/**
	 * Render import result.
	 *
	 * @param array<string,mixed>|null $result Result.
	 */
	private function render_result( ?array $result ): void {
		if ( null === $result ) {
			return;
		}

		$errors = isset( $result['errors'] ) && is_array( $result['errors'] ) ? $result['errors'] : array();
		$type   = empty( $errors ) ? 'notice-success' : 'notice-warning';
		?>
		<div class="notice <?php echo esc_attr( $type ); ?>">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: total rows, 2: valid rows, 3: imported rows. */
						__( 'Import checked %1$d rows, found %2$d valid rows, and imported %3$d rows.', 'studio-booking-manager' ),
						absint( $result['total'] ?? 0 ),
						absint( $result['valid'] ?? 0 ),
						absint( $result['imported'] ?? 0 )
					)
				);
				?>
			</p>
		</div>
		<?php if ( ! empty( $errors ) ) : ?>
			<div class="sbm-card sbm-card-wide">
				<h2><?php echo esc_html__( 'Import row issues', 'studio-booking-manager' ); ?></h2>
				<table class="widefat striped sbm-table">
					<thead><tr><th><?php echo esc_html__( 'Row', 'studio-booking-manager' ); ?></th><th><?php echo esc_html__( 'Issue', 'studio-booking-manager' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( $errors as $error ) : ?>
							<tr>
								<td><?php echo esc_html( (string) absint( $error['row'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( (string) ( $error['message'] ?? '' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
		<?php
	}
}

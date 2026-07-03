<?php
/**
 * QR check-in admin screen.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\QR;

use StudioBookingManager\Access\AccessService;
use StudioBookingManager\Admin\AbstractAdminPage;
use StudioBookingManager\Admin\PageHeader;
use StudioBookingManager\Locations\LocationService;
use StudioBookingManager\People\PersonService;

\defined( 'ABSPATH' ) || exit;

/**
 * Provides QR identity lookup for operations.
 */
final class QRAdmin extends AbstractAdminPage {
	/**
	 * Capability required to use QR check-in.
	 *
	 * @var string
	 */
	protected string $capability = 'sbm_check_in';

	/**
	 * People service.
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
	 * QR service.
	 *
	 * @var QRService
	 */
	private QRService $qr;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->people    = new PersonService();
		$this->access    = new AccessService();
		$this->locations = new LocationService();
		$this->qr        = new QRService();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_post_sbm_regenerate_person_qr', array( $this, 'handle_regenerate_person_qr' ) );
		add_action( 'admin_post_sbm_download_person_qr', array( $this, 'handle_download_person_qr' ) );
	}

	/**
	 * Render QR check-in screen.
	 */
	public function render(): void {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'person' === $action ) {
			$this->render_person_qr();
			return;
		}

		$resolved = $this->get_token_from_request();
		$token    = $resolved['token'];
		$error    = $resolved['error'];
		$person   = '' !== $token ? $this->people->find_by_qr_token( $token ) : null;
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( __( 'QR Check-in', 'studio-booking-manager' ) ); ?>
			<?php
			$this->render_query_notice(
				array(
					'not_found'      => __( 'No person matched that QR identity.', 'studio-booking-manager' ),
					'regenerated'    => __( 'QR token regenerated. Existing QR codes for that person are now revoked.', 'studio-booking-manager' ),
					'invalid_qr'     => __( 'That QR code is not valid for this site.', 'studio-booking-manager' ),
					'expired_qr'     => __( 'That QR code has expired. Regenerate or download a fresh QR code.', 'studio-booking-manager' ),
				)
			);
			?>
			<div class="sbm-card sbm-card-wide">
				<h2><?php echo esc_html__( 'Scan or paste QR identity', 'studio-booking-manager' ); ?></h2>
				<p><?php echo esc_html__( 'Use a QR scanner, barcode scanner, or paste a person QR identity token to start check-in.', 'studio-booking-manager' ); ?></p>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<input type="hidden" name="page" value="sbm-qr-checkin">
					<p>
						<label class="screen-reader-text" for="sbm-qr-token"><?php echo esc_html__( 'QR identity token', 'studio-booking-manager' ); ?></label>
						<input class="regular-text" type="text" id="sbm-qr-token" name="token" value="<?php echo esc_attr( $token ); ?>" autocomplete="off" autofocus>
						<?php submit_button( __( 'Find Person', 'studio-booking-manager' ), 'secondary', '', false ); ?>
					</p>
				</form>
			</div>
			<?php if ( '' !== $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $this->error_message( $error ) ); ?></p></div>
			<?php endif; ?>
			<?php if ( '' !== $token && null === $person ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html__( 'No person matched that QR identity.', 'studio-booking-manager' ); ?></p></div>
			<?php endif; ?>
			<?php if ( null !== $person ) : ?>
				<?php $this->render_check_in_card( $person ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get sanitized QR token from the current request.
	 *
	 * @return array{token:string,error:string}
	 */
	private function get_token_from_request(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only QR lookup request.
		if ( isset( $_GET['qr'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only QR lookup request.
			return $this->qr->resolve( (string) wp_unslash( $_GET['qr'] ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only manual lookup request.
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		return array(
			'token' => $token,
			'error' => '',
		);
	}

	/**
	 * Handle QR token regeneration.
	 */
	public function handle_regenerate_person_qr(): void {
		$this->verify_capability_request( 'sbm_manage_people', 'sbm_regenerate_person_qr', __( 'You do not have permission to regenerate QR codes.', 'studio-booking-manager' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce and capability are verified above.
		$id = isset( $_POST['person_id'] ) ? absint( wp_unslash( $_POST['person_id'] ) ) : 0;
		$this->people->regenerate_qr_token( $id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'sbm-qr-checkin',
					'action'    => 'person',
					'person_id' => $id,
					'message'   => 'regenerated',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle QR SVG download.
	 */
	public function handle_download_person_qr(): void {
		$id = isset( $_GET['person_id'] ) ? absint( wp_unslash( $_GET['person_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! current_user_can( 'sbm_manage_people' ) ) {
			wp_die( esc_html__( 'You do not have permission to download QR codes.', 'studio-booking-manager' ) );
		}

		check_admin_referer( 'sbm_download_person_qr_' . $id );

		$person = $this->people->find( $id );
		if ( ! $person instanceof \stdClass ) {
			wp_die( esc_html__( 'Person not found.', 'studio-booking-manager' ) );
		}

		$filename = sanitize_title( (string) $person->display_name ) . '-qr.svg';
		header( 'Content-Type: image/svg+xml; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo $this->qr->svg( $this->qr->person_checkin_url( $person ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG is generated by QRService.
		exit;
	}

	/**
	 * Render one person's QR code.
	 */
	private function render_person_qr(): void {
		$id     = isset( $_GET['person_id'] ) ? absint( wp_unslash( $_GET['person_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$person = $this->people->find( $id );

		if ( ! $person instanceof \stdClass ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'sbm-qr-checkin',
						'message' => 'not_found',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$url = $this->qr->person_checkin_url( $person );
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( __( 'Person QR Code', 'studio-booking-manager' ) ); ?>
			<?php
			$this->render_query_notice(
				array(
					'regenerated' => __( 'QR token regenerated. Existing QR codes for that person are now revoked.', 'studio-booking-manager' ),
				)
			);
			?>
			<div class="sbm-card sbm-qr-output">
				<div class="sbm-qr-code"><?php echo $this->qr->svg( $url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG is generated by QRService. ?></div>
				<h2><?php echo esc_html( $this->qr->person_label( $person ) ); ?></h2>
				<p><code><?php echo esc_html( substr( (string) $person->qr_token, 0, 12 ) ); ?>...</code></p>
				<p class="description">
					<?php
					$lifetime = $this->qr->lifetime_days();
					echo esc_html( $lifetime > 0 ? sprintf( /* translators: %d: days. */ __( 'QR links expire after %d days. Regenerating the token revokes older QR codes immediately.', 'studio-booking-manager' ), $lifetime ) : __( 'QR links do not expire. Regenerating the token revokes older QR codes immediately.', 'studio-booking-manager' ) );
					?>
				</p>
				<p class="sbm-qr-actions">
					<a class="button button-primary" href="<?php echo esc_url( $this->qr_checkin_url( $person ) ); ?>"><?php echo esc_html__( 'Open Check-in', 'studio-booking-manager' ); ?></a>
					<a class="button" href="<?php echo esc_url( $this->download_url( (int) $person->id ) ); ?>"><?php echo esc_html__( 'Download SVG', 'studio-booking-manager' ); ?></a>
					<button type="button" class="button" onclick="window.print();"><?php echo esc_html__( 'Print', 'studio-booking-manager' ); ?></button>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'sbm_regenerate_person_qr' ); ?>
					<input type="hidden" name="action" value="sbm_regenerate_person_qr">
					<input type="hidden" name="person_id" value="<?php echo esc_attr( (string) absint( $person->id ) ); ?>">
					<button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Regenerate this QR token? Existing QR codes for this person will stop working.', 'studio-booking-manager' ) ); ?>');"><?php echo esc_html__( 'Regenerate QR Token', 'studio-booking-manager' ); ?></button>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a check-in card for the located person.
	 *
	 * @param object $person Person row.
	 */
	private function render_check_in_card( object $person ): void {
		$access_records = $this->active_access_for_person( (int) $person->id );
		$locations      = $this->locations->all();
		?>
		<div class="sbm-card sbm-card-wide">
			<h2><?php echo esc_html( (string) $person->display_name ); ?></h2>
			<p><?php echo esc_html( (string) $person->email ); ?></p>
			<p><strong><?php echo esc_html__( 'QR Identity:', 'studio-booking-manager' ); ?></strong> <code><?php echo esc_html( (string) $person->qr_token ); ?></code></p>
			<?php if ( empty( $access_records ) ) : ?>
				<p><?php echo esc_html__( 'No active access available for this person.', 'studio-booking-manager' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'sbm_check_in' ); ?>
					<input type="hidden" name="action" value="sbm_check_in">
					<input type="hidden" name="person_id" value="<?php echo esc_attr( (string) absint( $person->id ) ); ?>">
					<p>
						<label for="sbm-qr-access"><?php echo esc_html__( 'Access', 'studio-booking-manager' ); ?></label><br>
						<select id="sbm-qr-access" name="access_id" required>
							<?php foreach ( $access_records as $access ) : ?>
								<option value="<?php echo esc_attr( (string) absint( $access->id ) ); ?>"><?php echo esc_html( $this->access_label( $access ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label for="sbm-qr-location"><?php echo esc_html__( 'Location', 'studio-booking-manager' ); ?></label><br>
						<select id="sbm-qr-location" name="location_id" required>
							<?php foreach ( $locations as $location ) : ?>
								<option value="<?php echo esc_attr( (string) absint( $location->id ) ); ?>"><?php echo esc_html( (string) $location->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label for="sbm-qr-guests"><?php echo esc_html__( 'Guests', 'studio-booking-manager' ); ?></label><br>
						<input class="small-text" type="number" min="0" id="sbm-qr-guests" name="guest_count" value="0">
					</p>
					<p>
						<label for="sbm-qr-guest-names"><?php echo esc_html__( 'Guest names', 'studio-booking-manager' ); ?></label><br>
						<textarea class="large-text" rows="2" id="sbm-qr-guest-names" name="guest_names"></textarea>
					</p>
					<input type="hidden" name="checkin_method" value="qr_code">
					<?php submit_button( __( 'Check In', 'studio-booking-manager' ), 'primary', '', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Create signed QR check-in URL.
	 *
	 * @param object $person Person row.
	 */
	private function qr_checkin_url( object $person ): string {
		return $this->qr->person_checkin_url( $person );
	}

	/**
	 * Download URL.
	 *
	 * @param int $id Person ID.
	 */
	private function download_url( int $id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'    => 'sbm_download_person_qr',
					'person_id' => $id,
				),
				admin_url( 'admin-post.php' )
			),
			'sbm_download_person_qr_' . $id
		);
	}

	/**
	 * Error message.
	 *
	 * @param string $error Error key.
	 */
	private function error_message( string $error ): string {
		$messages = array(
			'invalid_qr' => __( 'That QR code is not valid for this site.', 'studio-booking-manager' ),
			'expired_qr' => __( 'That QR code has expired. Regenerate or download a fresh QR code.', 'studio-booking-manager' ),
		);

		return $messages[ $error ] ?? __( 'QR code could not be read.', 'studio-booking-manager' );
	}

	/**
	 * Get active access records for a person.
	 *
	 * @param int $person_id Person ID.
	 * @return array<int, object>
	 */
	private function active_access_for_person( int $person_id ): array {
		$records = array();

		foreach ( $this->access->all() as $access ) {
			if ( (int) $access->person_id === $person_id && 'active' === (string) $access->status ) {
				$records[] = $access;
			}
		}

		return $records;
	}

	/**
	 * Build an access label.
	 *
	 * @param object $access Access row.
	 * @return string
	 */
	private function access_label( object $access ): string {
		$type = str_replace( '_', ' ', (string) $access->access_type );
		return ucwords( $type ) . ' #' . (string) absint( $access->id );
	}
}

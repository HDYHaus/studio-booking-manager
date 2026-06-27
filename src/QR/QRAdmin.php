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
	 * Constructor.
	 */
	public function __construct() {
		$this->people    = new PersonService();
		$this->access    = new AccessService();
		$this->locations = new LocationService();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {}

	/**
	 * Render QR check-in screen.
	 */
	public function render(): void {
		$token  = $this->get_token_from_request();
		$person = '' !== $token ? $this->people->find_by_qr_token( $token ) : null;
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( __( 'QR Check-in', 'studio-booking-manager' ) ); ?>
			<?php $this->render_query_notice( array( 'not_found' => __( 'No person matched that QR identity.', 'studio-booking-manager' ) ) ); ?>
			<div class="sbm-card sbm-card-wide">
				<h2><?php echo esc_html__( 'Scan or paste QR identity', 'studio-booking-manager' ); ?></h2>
				<p><?php echo esc_html__( 'Use a QR scanner, barcode scanner, or paste the person\'s QR identity token to start check-in.', 'studio-booking-manager' ); ?></p>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<input type="hidden" name="page" value="sbm-qr-checkin">
					<p>
						<label class="screen-reader-text" for="sbm-qr-token"><?php echo esc_html__( 'QR identity token', 'studio-booking-manager' ); ?></label>
						<input class="regular-text" type="text" id="sbm-qr-token" name="token" value="<?php echo esc_attr( $token ); ?>" autocomplete="off" autofocus>
						<?php submit_button( __( 'Find Person', 'studio-booking-manager' ), 'secondary', '', false ); ?>
					</p>
				</form>
			</div>
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
	 * @return string
	 */
	private function get_token_from_request(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only lookup request.
		return isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
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

<?php
/**
 * Settings page.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders settings.
 */
final class SettingsPage {
	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . SBM_PLUGIN_BASENAME, array( $this, 'add_plugin_action_links' ) );
		add_filter( 'option_page_capability_sbm_settings_group', array( $this, 'settings_capability' ) );
	}


	/**
	 * Get settings capability.
	 *
	 * @return string
	 */
	public function settings_capability(): string {
		return 'sbm_manage_settings';
	}

	/**
	 * Register settings.
	 */
	public function register_settings(): void {
		register_setting(
			'sbm_settings_group',
			'sbm_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Add plugin row action links.
	 *
	 * @param array<string, string> $links Existing plugin action links.
	 * @return array<string, string>
	 */
	public function add_plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'admin.php?page=sbm-settings' ) ),
			esc_html__( 'Settings', 'studio-booking-manager' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'sbm-' ) && false === strpos( $hook, 'studio-booking' ) ) {
			return;
		}

		wp_enqueue_style(
			'sbm-admin',
			SBM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			SBM_VERSION
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array<mixed> $settings Raw settings.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( array $settings ): array {
		$clean = array();

		if ( isset( $settings['business_name'] ) ) {
			$clean['business_name'] = sanitize_text_field( $settings['business_name'] );
		}

		if ( isset( $settings['default_timezone'] ) ) {
			$clean['default_timezone'] = sanitize_text_field( $settings['default_timezone'] );
		}

		$clean['google_calendar_enabled'] = ! empty( $settings['google_calendar_enabled'] ) ? 1 : 0;

		if ( isset( $settings['google_calendar_id'] ) ) {
			$clean['google_calendar_id'] = sanitize_text_field( $settings['google_calendar_id'] );
		}

		if ( isset( $settings['google_calendar_service_account_json'] ) && '' !== trim( (string) $settings['google_calendar_service_account_json'] ) ) {
			$clean['google_calendar_service_account_json'] = $this->sanitize_service_account_json( (string) $settings['google_calendar_service_account_json'] );
		} else {
			$current = get_option( 'sbm_settings', array() );
			if ( is_array( $current ) && isset( $current['google_calendar_service_account_json'] ) ) {
				$clean['google_calendar_service_account_json'] = (string) $current['google_calendar_service_account_json'];
			}
		}

		return $clean;
	}

	/**
	 * Sanitize service account JSON without corrupting escaped private key newlines.
	 *
	 * @param string $json Raw JSON.
	 * @return string
	 */
	private function sanitize_service_account_json( string $json ): string {
		$json = trim( $json );
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			return $json;
		}

		$allowed = array(
			'type',
			'project_id',
			'private_key_id',
			'private_key',
			'client_email',
			'client_id',
			'auth_uri',
			'token_uri',
			'auth_provider_x509_cert_url',
			'client_x509_cert_url',
			'universe_domain',
		);
		$clean   = array();

		foreach ( $allowed as $key ) {
			if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ) {
				$clean[ $key ] = (string) $data[ $key ];
			}
		}

		$encoded = wp_json_encode( $clean );

		return is_string( $encoded ) ? $encoded : $json;
	}

	/**
	 * Render settings page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'sbm_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Studio Booking Manager settings.', 'studio-booking-manager' ) );
		}

		$options = get_option( 'sbm_settings', array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$business_name   = isset( $options['business_name'] ) ? (string) $options['business_name'] : get_bloginfo( 'name' );
		$default_timezone = isset( $options['default_timezone'] ) ? (string) $options['default_timezone'] : wp_timezone_string();
		$calendar_enabled = ! empty( $options['google_calendar_enabled'] );
		$calendar_id      = isset( $options['google_calendar_id'] ) ? (string) $options['google_calendar_id'] : '';
		$has_credentials  = ! empty( $options['google_calendar_service_account_json'] );
		?>
		<div class="wrap sbm-admin-page">
			<h1><?php echo esc_html__( 'Studio Booking Manager Settings', 'studio-booking-manager' ); ?></h1>

			<nav class="nav-tab-wrapper sbm-settings-tabs" aria-label="<?php echo esc_attr__( 'Settings sections', 'studio-booking-manager' ); ?>">
				<a class="nav-tab nav-tab-active" href="#general"><?php echo esc_html__( 'General', 'studio-booking-manager' ); ?></a>
				<a class="nav-tab" href="#google-calendar"><?php echo esc_html__( 'Google Calendar', 'studio-booking-manager' ); ?></a>
				<a class="nav-tab" href="#qr-codes"><?php echo esc_html__( 'QR Codes', 'studio-booking-manager' ); ?></a>
				<a class="nav-tab" href="#notifications"><?php echo esc_html__( 'Notifications', 'studio-booking-manager' ); ?></a>
				<a class="nav-tab" href="#advanced"><?php echo esc_html__( 'Advanced', 'studio-booking-manager' ); ?></a>
			</nav>

			<form method="post" action="options.php" class="sbm-settings-form">
				<?php settings_fields( 'sbm_settings_group' ); ?>

				<section id="general" class="sbm-card">
					<h2><?php echo esc_html__( 'General', 'studio-booking-manager' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="sbm-business-name"><?php echo esc_html__( 'Business name', 'studio-booking-manager' ); ?></label>
							</th>
							<td>
								<input id="sbm-business-name" type="text" class="regular-text" name="sbm_settings[business_name]" value="<?php echo esc_attr( $business_name ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="sbm-default-timezone"><?php echo esc_html__( 'Default timezone', 'studio-booking-manager' ); ?></label>
							</th>
							<td>
								<input id="sbm-default-timezone" type="text" class="regular-text" name="sbm_settings[default_timezone]" value="<?php echo esc_attr( $default_timezone ); ?>" />
								<p class="description"><?php echo esc_html__( 'Used as the fallback timezone for new locations.', 'studio-booking-manager' ); ?></p>
							</td>
						</tr>
					</table>
				</section>

				<section id="google-calendar" class="sbm-card">
					<h2><?php echo esc_html__( 'Google Calendar', 'studio-booking-manager' ); ?></h2>
					<div class="sbm-settings-help">
						<p><?php echo esc_html__( 'Use a Google Cloud service account for server-to-server calendar sync. You will paste the downloaded JSON key here, then share the target Google Calendar with the service account email inside that JSON file.', 'studio-booking-manager' ); ?></p>
						<ol>
							<li>
								<?php
								printf(
									/* translators: 1: opening link tag, 2: closing link tag. */
									esc_html__( 'Create or choose a Google Cloud project, then enable the Google Calendar API from the %1$sGoogle Cloud console%2$s.', 'studio-booking-manager' ),
									'<a href="' . esc_url( 'https://console.cloud.google.com/apis/library/calendar-json.googleapis.com' ) . '" target="_blank" rel="noopener noreferrer">',
									'</a>'
								);
								?>
							</li>
							<li>
								<?php
								printf(
									/* translators: 1: opening link tag, 2: closing link tag. */
									esc_html__( 'Create a service account key, choose JSON as the key type, and paste the downloaded file contents into Service account JSON. See Google\'s %1$sservice account key guide%2$s.', 'studio-booking-manager' ),
									'<a href="' . esc_url( 'https://cloud.google.com/iam/docs/keys-create-delete#creating' ) . '" target="_blank" rel="noopener noreferrer">',
									'</a>'
								);
								?>
							</li>
							<li>
								<?php
								printf(
									/* translators: 1: opening link tag, 2: closing link tag. */
									esc_html__( 'Open the target calendar settings, copy its Calendar ID, and share that exact calendar with the service account client_email. Google explains calendar sharing in %1$sthis help article%2$s.', 'studio-booking-manager' ),
									'<a href="' . esc_url( 'https://support.google.com/calendar/answer/37082' ) . '" target="_blank" rel="noopener noreferrer">',
									'</a>'
								);
								?>
							</li>
						</ol>
					</div>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php echo esc_html__( 'Enable sync', 'studio-booking-manager' ); ?></th>
							<td>
								<label for="sbm-google-calendar-enabled">
									<input id="sbm-google-calendar-enabled" type="checkbox" name="sbm_settings[google_calendar_enabled]" value="1" <?php checked( $calendar_enabled ); ?> />
									<?php echo esc_html__( 'Sync bookings to Google Calendar', 'studio-booking-manager' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="sbm-google-calendar-id"><?php echo esc_html__( 'Calendar ID', 'studio-booking-manager' ); ?></label>
							</th>
							<td>
								<input id="sbm-google-calendar-id" type="text" class="regular-text" name="sbm_settings[google_calendar_id]" value="<?php echo esc_attr( $calendar_id ); ?>" />
								<p class="description"><?php echo esc_html__( 'In Google Calendar: Settings and sharing > Integrate calendar > Calendar ID. Use the full calendar ID, not the calendar name or the word primary.', 'studio-booking-manager' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="sbm-google-calendar-service-account"><?php echo esc_html__( 'Service account JSON', 'studio-booking-manager' ); ?></label>
							</th>
							<td>
								<textarea id="sbm-google-calendar-service-account" class="large-text code" rows="8" name="sbm_settings[google_calendar_service_account_json]" placeholder="<?php echo esc_attr( $has_credentials ? __( 'Service account JSON is already saved. Leave blank to keep it.', 'studio-booking-manager' ) : '' ); ?>"></textarea>
								<p class="description"><?php echo esc_html__( 'Paste the entire downloaded JSON key file, including client_email and private_key. Do not paste only the private key.', 'studio-booking-manager' ); ?></p>
								<p class="description"><?php echo esc_html__( 'If signing fails, download a fresh JSON key and paste the untouched file contents. The private_key value should include a BEGIN PRIVATE KEY block.', 'studio-booking-manager' ); ?></p>
								<?php if ( $has_credentials ) : ?>
									<p class="description"><?php echo esc_html__( 'Credentials are saved and hidden.', 'studio-booking-manager' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
				</section>

				<section id="qr-codes" class="sbm-card">
					<h2><?php echo esc_html__( 'QR Codes', 'studio-booking-manager' ); ?></h2>
					<p><?php echo esc_html__( 'QR identity settings will be added when the People and Visits modules are active.', 'studio-booking-manager' ); ?></p>
				</section>

				<section id="notifications" class="sbm-card">
					<h2><?php echo esc_html__( 'Notifications', 'studio-booking-manager' ); ?></h2>
					<p><?php echo esc_html__( 'Notification templates and reminders will be added in a future release.', 'studio-booking-manager' ); ?></p>
				</section>

				<section id="advanced" class="sbm-card">
					<h2><?php echo esc_html__( 'Advanced', 'studio-booking-manager' ); ?></h2>
					<p><?php echo esc_html__( 'Logging, imports, exports, and developer tools will live here.', 'studio-booking-manager' ); ?></p>
				</section>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

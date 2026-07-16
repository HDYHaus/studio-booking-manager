<?php
/**
 * Integrations admin screen.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Integrations;

use StudioBookingManager\Admin\PageHeader;
use StudioBookingManager\UI\Badge;

defined( 'ABSPATH' ) || exit;

/**
 * Renders integration provider settings.
 */
final class IntegrationAdmin {
	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'option_page_capability_sbm_integrations_group', array( $this, 'settings_capability' ) );
	}

	/**
	 * Settings capability.
	 */
	public function settings_capability(): string {
		return 'sbm_manage_settings';
	}

	/**
	 * Register integration settings.
	 */
	public function register_settings(): void {
		register_setting(
			'sbm_integrations_group',
			IntegrationSettings::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( new IntegrationSettings(), 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Render screen.
	 */
	public function render(): void {
		if ( ! current_user_can( 'sbm_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage integrations.', 'studio-booking-manager' ) );
		}

		$registry   = new IntegrationRegistry();
		$settings   = new IntegrationSettings();
		$providers  = $registry->form_providers();
		$actions    = $registry->actions();
		$duplicates = $registry->duplicate_strategies();
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( __( 'Integrations', 'studio-booking-manager' ) ); ?>
			<div class="sbm-card sbm-card-wide">
				<h2><?php echo esc_html__( 'Form Integrations', 'studio-booking-manager' ); ?></h2>
				<p><?php echo esc_html__( 'Connect form plugins to Studio Booking Manager workflows. The integration foundation stores provider settings now; provider-specific field mapping and submission handling will be added starting with Gravity Forms.', 'studio-booking-manager' ); ?></p>

				<form method="post" action="options.php">
					<?php settings_fields( 'sbm_integrations_group' ); ?>
					<table class="widefat striped sbm-table">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Provider', 'studio-booking-manager' ); ?></th>
								<th><?php echo esc_html__( 'Status', 'studio-booking-manager' ); ?></th>
								<th><?php echo esc_html__( 'Enabled', 'studio-booking-manager' ); ?></th>
								<th><?php echo esc_html__( 'Default action', 'studio-booking-manager' ); ?></th>
								<th><?php echo esc_html__( 'Duplicates', 'studio-booking-manager' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $providers as $slug => $provider ) : ?>
								<?php $config = $settings->provider( $slug ); ?>
								<tr>
									<td>
										<strong><?php echo esc_html( (string) $provider['name'] ); ?></strong>
										<p class="description"><?php echo esc_html( (string) $provider['description'] ); ?></p>
										<p class="description"><?php echo esc_html( (string) $provider['phase'] ); ?></p>
									</td>
									<td>
										<?php
										echo wp_kses_post(
											Badge::render(
												! empty( $provider['detected'] ) ? __( 'Detected', 'studio-booking-manager' ) : __( 'Not active', 'studio-booking-manager' ),
												! empty( $provider['detected'] ) ? 'active' : 'neutral'
											)
										);
										?>
									</td>
									<td>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( IntegrationSettings::OPTION_NAME ); ?>[providers][<?php echo esc_attr( $slug ); ?>][enabled]" value="1" <?php checked( ! empty( $config['enabled'] ) ); ?> />
											<?php echo esc_html__( 'Enable', 'studio-booking-manager' ); ?>
										</label>
									</td>
									<td>
										<select name="<?php echo esc_attr( IntegrationSettings::OPTION_NAME ); ?>[providers][<?php echo esc_attr( $slug ); ?>][action]">
											<?php foreach ( $actions as $action => $label ) : ?>
												<option value="<?php echo esc_attr( $action ); ?>" <?php selected( (string) $config['action'], $action ); ?>><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td>
										<select name="<?php echo esc_attr( IntegrationSettings::OPTION_NAME ); ?>[providers][<?php echo esc_attr( $slug ); ?>][duplicate_strategy]">
											<?php foreach ( $duplicates as $strategy => $label ) : ?>
												<option value="<?php echo esc_attr( $strategy ); ?>" <?php selected( (string) $config['duplicate_strategy'], $strategy ); ?>><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php submit_button( __( 'Save Integration Settings', 'studio-booking-manager' ) ); ?>
				</form>
			</div>

			<div class="sbm-card sbm-card-wide">
				<h2><?php echo esc_html__( 'Next Build Step', 'studio-booking-manager' ); ?></h2>
				<p><?php echo esc_html__( 'Gravity Forms will be the first active provider. It will add form selection, field mapping, and submission handling for people and pending bookings.', 'studio-booking-manager' ); ?></p>
			</div>
		</div>
		<?php
	}
}

<?php
/**
 * Integrations admin screen.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Integrations;

use StudioBookingManager\Admin\PageHeader;
use StudioBookingManager\Locations\LocationService;
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
								<?php if ( 'gravity_forms' === $slug ) : ?>
									<tr>
										<td colspan="5">
											<?php $this->render_gravity_forms_settings( $config ); ?>
										</td>
									</tr>
								<?php endif; ?>
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

	/**
	 * Render Gravity Forms mapping settings.
	 *
	 * @param array<string,mixed> $config Provider config.
	 */
	private function render_gravity_forms_settings( array $config ): void {
		$option       = IntegrationSettings::OPTION_NAME . '[providers][gravity_forms]';
		$form_id      = isset( $config['form_id'] ) ? absint( $config['form_id'] ) : 0;
		$field_map    = isset( $config['fields'] ) && is_array( $config['fields'] ) ? $config['fields'] : array();
		$field_map    = wp_parse_args( $field_map, ( new IntegrationSettings() )->gravity_forms_field_defaults() );
		$form_options = $this->gravity_forms_options();
		$field_options = $this->gravity_forms_field_options( $form_id );
		$locations    = ( new LocationService() )->all();
		?>
		<div class="sbm-inline-settings">
			<h3><?php echo esc_html__( 'Gravity Forms Mapping', 'studio-booking-manager' ); ?></h3>
			<p class="description"><?php echo esc_html__( 'Choose the Gravity Form and map its fields to Studio Booking records. Booking creation needs a booking date, start time, and either a mapped location ID or a default location.', 'studio-booking-manager' ); ?></p>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="sbm-gravity-forms-form-id"><?php echo esc_html__( 'Form', 'studio-booking-manager' ); ?></label></th>
						<td>
							<select id="sbm-gravity-forms-form-id" name="<?php echo esc_attr( $option ); ?>[form_id]">
								<option value="0"><?php echo esc_html__( 'Select a form', 'studio-booking-manager' ); ?></option>
								<?php foreach ( $form_options as $id => $label ) : ?>
									<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( $form_id, (int) $id ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sbm-gravity-forms-location"><?php echo esc_html__( 'Default location', 'studio-booking-manager' ); ?></label></th>
						<td>
							<select id="sbm-gravity-forms-location" name="<?php echo esc_attr( $option ); ?>[default_location_id]">
								<option value="0"><?php echo esc_html__( 'Use mapped location field', 'studio-booking-manager' ); ?></option>
								<?php foreach ( $locations as $location ) : ?>
									<option value="<?php echo esc_attr( (string) $location->id ); ?>" <?php selected( isset( $config['default_location_id'] ) ? absint( $config['default_location_id'] ) : 0, (int) $location->id ); ?>><?php echo esc_html( (string) $location->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sbm-gravity-forms-start-time"><?php echo esc_html__( 'Default start time', 'studio-booking-manager' ); ?></label></th>
						<td>
							<input id="sbm-gravity-forms-start-time" name="<?php echo esc_attr( $option ); ?>[default_start_time]" type="time" value="<?php echo esc_attr( isset( $config['default_start_time'] ) ? (string) $config['default_start_time'] : '09:00' ); ?>">
							<span class="description"><?php echo esc_html__( 'Used when no start time field is mapped.', 'studio-booking-manager' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sbm-gravity-forms-duration"><?php echo esc_html__( 'Default duration', 'studio-booking-manager' ); ?></label></th>
						<td>
							<input id="sbm-gravity-forms-duration" name="<?php echo esc_attr( $option ); ?>[default_duration_minutes]" type="number" min="15" max="1440" step="15" value="<?php echo esc_attr( (string) ( isset( $config['default_duration_minutes'] ) ? absint( $config['default_duration_minutes'] ) : 60 ) ); ?>">
							<span class="description"><?php echo esc_html__( 'Minutes, used when no end time field is mapped.', 'studio-booking-manager' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sbm-gravity-forms-visibility"><?php echo esc_html__( 'Booking visibility', 'studio-booking-manager' ); ?></label></th>
						<td>
							<select id="sbm-gravity-forms-visibility" name="<?php echo esc_attr( $option ); ?>[booking_visibility]">
								<?php foreach ( $this->booking_visibility_options() as $visibility => $label ) : ?>
									<option value="<?php echo esc_attr( $visibility ); ?>" <?php selected( isset( $config['booking_visibility'] ) ? (string) $config['booking_visibility'] : 'internal', $visibility ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</tbody>
			</table>

			<h4><?php echo esc_html__( 'Field Mapping', 'studio-booking-manager' ); ?></h4>
			<?php if ( $form_id > 0 && empty( $field_options ) ) : ?>
				<p class="description"><?php echo esc_html__( 'No mappable fields were found for the selected form.', 'studio-booking-manager' ); ?></p>
			<?php elseif ( $form_id <= 0 ) : ?>
				<p class="description"><?php echo esc_html__( 'Select and save a Gravity Form first, then return here to map its fields.', 'studio-booking-manager' ); ?></p>
			<?php endif; ?>

			<table class="widefat striped">
				<tbody>
					<?php foreach ( $this->gravity_forms_mapping_labels() as $field => $label ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td>
								<select name="<?php echo esc_attr( $option ); ?>[fields][<?php echo esc_attr( $field ); ?>]">
									<option value=""><?php echo esc_html__( 'Not mapped', 'studio-booking-manager' ); ?></option>
									<?php foreach ( $field_options as $id => $field_label ) : ?>
										<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( (string) $field_map[ $field ], (string) $id ); ?>><?php echo esc_html( $field_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Gravity Forms form options.
	 *
	 * @return array<int,string>
	 */
	private function gravity_forms_options(): array {
		if ( ! class_exists( 'GFAPI' ) || ! method_exists( 'GFAPI', 'get_forms' ) ) {
			return array();
		}

		$forms = \GFAPI::get_forms();

		if ( ! is_array( $forms ) ) {
			return array();
		}

		$options = array();

		foreach ( $forms as $form ) {
			if ( ! is_array( $form ) || empty( $form['id'] ) ) {
				continue;
			}

			$options[ absint( $form['id'] ) ] = isset( $form['title'] ) ? (string) $form['title'] : sprintf(
				/* translators: %d: form ID. */
				__( 'Form #%d', 'studio-booking-manager' ),
				absint( $form['id'] )
			);
		}

		return $options;
	}

	/**
	 * Gravity Forms field options for a form.
	 *
	 * @param int $form_id Form ID.
	 * @return array<string,string>
	 */
	private function gravity_forms_field_options( int $form_id ): array {
		if ( $form_id <= 0 || ! class_exists( 'GFAPI' ) || ! method_exists( 'GFAPI', 'get_form' ) ) {
			return array();
		}

		$form = \GFAPI::get_form( $form_id );

		if ( ! is_array( $form ) || empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return array();
		}

		$options = array();

		foreach ( $form['fields'] as $field ) {
			if ( ! is_object( $field ) || ! isset( $field->id ) ) {
				continue;
			}

			$label = isset( $field->label ) && '' !== (string) $field->label ? (string) $field->label : sprintf(
				/* translators: %s: field ID. */
				__( 'Field %s', 'studio-booking-manager' ),
				(string) $field->id
			);

			$options[ (string) $field->id ] = $label;

			if ( isset( $field->inputs ) && is_array( $field->inputs ) ) {
				foreach ( $field->inputs as $input ) {
					if ( empty( $input['id'] ) ) {
						continue;
					}

					$input_label = isset( $input['label'] ) && '' !== (string) $input['label'] ? (string) $input['label'] : (string) $input['id'];
					$options[ (string) $input['id'] ] = $label . ' - ' . $input_label;
				}
			}
		}

		return $options;
	}

	/**
	 * Mapping field labels.
	 *
	 * @return array<string,string>
	 */
	private function gravity_forms_mapping_labels(): array {
		return array(
			'first_name'   => __( 'First name', 'studio-booking-manager' ),
			'last_name'    => __( 'Last name', 'studio-booking-manager' ),
			'display_name' => __( 'Display name', 'studio-booking-manager' ),
			'email'        => __( 'Email', 'studio-booking-manager' ),
			'phone'        => __( 'Phone', 'studio-booking-manager' ),
			'booking_date' => __( 'Booking date', 'studio-booking-manager' ),
			'start_time'   => __( 'Start time', 'studio-booking-manager' ),
			'end_time'     => __( 'End time', 'studio-booking-manager' ),
			'location_id'  => __( 'Location ID', 'studio-booking-manager' ),
			'guest_count'  => __( 'Guest count', 'studio-booking-manager' ),
			'guest_names'  => __( 'Guest names', 'studio-booking-manager' ),
			'notes'        => __( 'Notes', 'studio-booking-manager' ),
		);
	}

	/**
	 * Booking visibility options.
	 *
	 * @return array<string,string>
	 */
	private function booking_visibility_options(): array {
		return array(
			'internal' => __( 'Internal', 'studio-booking-manager' ),
			'private'  => __( 'Private on member calendar', 'studio-booking-manager' ),
			'public'   => __( 'Public on member calendar', 'studio-booking-manager' ),
			'blocked'  => __( 'Blocked time', 'studio-booking-manager' ),
		);
	}
}

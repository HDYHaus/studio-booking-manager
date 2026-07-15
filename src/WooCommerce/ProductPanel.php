<?php
/**
 * WooCommerce product panel.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\WooCommerce;

use StudioBookingManager\Locations\LocationService;
use StudioBookingManager\PassTypes\PassTypeService;

defined( 'ABSPATH' ) || exit;

/**
 * Registers product settings panel.
 */
final class ProductPanel {
	/**
	 * Cached pass type options.
	 *
	 * @var array<int,string>|null
	 */
	private ?array $pass_type_options = null;

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_panel' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'render_variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );
	}

	/**
	 * Add product data tab.
	 *
	 * @param array<string, mixed> $tabs Existing tabs.
	 * @return array<string, mixed>
	 */
	public function add_tab( array $tabs ): array {
		$tabs['studio_booking_manager'] = array(
			'label'    => __( 'Studio Booking', 'studio-booking-manager' ),
			'target'   => 'studio_booking_manager_product_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 80,
		);

		return $tabs;
	}

	/**
	 * Render panel.
	 */
	public function render_panel(): void {
		global $post;

		$product_id = $post instanceof \WP_Post ? (int) $post->ID : 0;
		?>
		<div id="studio_booking_manager_product_data" class="panel woocommerce_options_panel hidden">
			<?php wp_nonce_field( 'sbm_save_product_panel', 'sbm_product_panel_nonce' ); ?>
			<div class="options_group">
				<?php $this->render_product_fields( $product_id ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render variation fields.
	 *
	 * @param int                  $loop           Variation loop index.
	 * @param array<string,mixed>  $variation_data Variation data.
	 * @param \WP_Post             $variation      Variation post.
	 */
	public function render_variation_fields( int $loop, array $variation_data, \WP_Post $variation ): void {
		$variation_id = (int) $variation->ID;
		?>
		<div class="form-row form-row-full">
			<h4><?php echo esc_html__( 'Studio Booking Manager', 'studio-booking-manager' ); ?></h4>
		</div>
		<?php
		woocommerce_wp_checkbox(
			array(
				'id'            => "_sbm_enabled_{$loop}",
				'name'          => "_sbm_enabled[{$loop}]",
				'label'         => __( 'Enable Studio Booking', 'studio-booking-manager' ),
				'value'         => 'yes' === get_post_meta( $variation_id, '_sbm_enabled', true ) ? 'yes' : 'no',
				'wrapper_class' => 'form-row form-row-full',
			)
		);
		woocommerce_wp_select(
			array(
				'id'            => "_sbm_pass_type_id_{$loop}",
				'name'          => "_sbm_pass_type_id[{$loop}]",
				'label'         => __( 'Pass', 'studio-booking-manager' ),
				'value'         => get_post_meta( $variation_id, '_sbm_pass_type_id', true ),
				'options'       => $this->pass_type_options(),
				'wrapper_class' => 'form-row form-row-full',
			)
		);
		woocommerce_wp_select(
			array(
				'id'            => "_sbm_access_type_{$loop}",
				'name'          => "_sbm_access_type[{$loop}]",
				'label'         => __( 'Access type', 'studio-booking-manager' ),
				'value'         => get_post_meta( $variation_id, '_sbm_access_type', true ),
				'options'       => $this->access_type_options(),
				'wrapper_class' => 'form-row form-row-first',
			)
		);
		woocommerce_wp_select(
			array(
				'id'            => "_sbm_location_id_{$loop}",
				'name'          => "_sbm_location_id[{$loop}]",
				'label'         => __( 'Location', 'studio-booking-manager' ),
				'value'         => get_post_meta( $variation_id, '_sbm_location_id', true ),
				'options'       => $this->location_options(),
				'wrapper_class' => 'form-row form-row-last',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'            => "_sbm_total_credits_{$loop}",
				'name'          => "_sbm_total_credits[{$loop}]",
				'label'         => __( 'Credits', 'studio-booking-manager' ),
				'value'         => get_post_meta( $variation_id, '_sbm_total_credits', true ),
				'type'          => 'number',
				'wrapper_class' => 'form-row form-row-first',
				'custom_attributes' => array( 'min' => '0' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'            => "_sbm_weekly_limit_{$loop}",
				'name'          => "_sbm_weekly_limit[{$loop}]",
				'label'         => __( 'Weekly limit', 'studio-booking-manager' ),
				'value'         => get_post_meta( $variation_id, '_sbm_weekly_limit', true ),
				'type'          => 'number',
				'wrapper_class' => 'form-row form-row-last',
				'custom_attributes' => array( 'min' => '0' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'            => "_sbm_guest_limit_{$loop}",
				'name'          => "_sbm_guest_limit[{$loop}]",
				'label'         => __( 'Guest limit', 'studio-booking-manager' ),
				'value'         => get_post_meta( $variation_id, '_sbm_guest_limit', true ),
				'type'          => 'number',
				'wrapper_class' => 'form-row form-row-first',
				'custom_attributes' => array( 'min' => '0' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'            => "_sbm_validity_days_{$loop}",
				'name'          => "_sbm_validity_days[{$loop}]",
				'label'         => __( 'Validity days', 'studio-booking-manager' ),
				'value'         => get_post_meta( $variation_id, '_sbm_validity_days', true ),
				'type'          => 'number',
				'wrapper_class' => 'form-row form-row-last',
				'custom_attributes' => array( 'min' => '0' ),
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'            => "_sbm_requires_booking_date_{$loop}",
				'name'          => "_sbm_requires_booking_date[{$loop}]",
				'label'         => __( 'Require booking date', 'studio-booking-manager' ),
				'description'   => __( 'Ask customers to choose their visit date before adding this variation to the cart.', 'studio-booking-manager' ),
				'value'         => 'yes' === get_post_meta( $variation_id, '_sbm_requires_booking_date', true ) ? 'yes' : 'no',
				'wrapper_class' => 'form-row form-row-full',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'            => "_sbm_booking_start_time_{$loop}",
				'name'          => "_sbm_booking_start_time[{$loop}]",
				'label'         => __( 'Booking start time', 'studio-booking-manager' ),
				'value'         => get_post_meta( $variation_id, '_sbm_booking_start_time', true ),
				'type'          => 'time',
				'wrapper_class' => 'form-row form-row-first',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'            => "_sbm_booking_end_time_{$loop}",
				'name'          => "_sbm_booking_end_time[{$loop}]",
				'label'         => __( 'Booking end time', 'studio-booking-manager' ),
				'value'         => get_post_meta( $variation_id, '_sbm_booking_end_time', true ),
				'type'          => 'time',
				'wrapper_class' => 'form-row form-row-last',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => "_sbm_booking_duration_minutes_{$loop}",
				'name'              => "_sbm_booking_duration_minutes[{$loop}]",
				'label'             => __( 'Booking duration minutes', 'studio-booking-manager' ),
				'value'             => get_post_meta( $variation_id, '_sbm_booking_duration_minutes', true ),
				'type'              => 'number',
				'wrapper_class'     => 'form-row form-row-full',
				'custom_attributes' => array(
					'min'  => '15',
					'step' => '15',
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => "_sbm_booking_daily_capacity_{$loop}",
				'name'              => "_sbm_booking_daily_capacity[{$loop}]",
				'label'             => __( 'Daily booking capacity', 'studio-booking-manager' ),
				'value'             => get_post_meta( $variation_id, '_sbm_booking_daily_capacity', true ),
				'type'              => 'number',
				'wrapper_class'     => 'form-row form-row-full',
				'custom_attributes' => array( 'min' => '0' ),
			)
		);
	}

	/**
	 * Render reusable product fields.
	 *
	 * @param int $product_id Product ID.
	 */
	private function render_product_fields( int $product_id ): void {
		$enabled = get_post_meta( $product_id, '_sbm_enabled', true );

		woocommerce_wp_checkbox(
			array(
				'id'          => '_sbm_enabled',
				'label'       => __( 'Enable Studio Booking', 'studio-booking-manager' ),
				'description' => __( 'Create Studio Booking Manager access when this product is paid for.', 'studio-booking-manager' ),
				'value'       => 'yes' === $enabled ? 'yes' : 'no',
			)
		);
		woocommerce_wp_select(
			array(
				'id'          => '_sbm_pass_type_id',
				'label'       => __( 'Pass', 'studio-booking-manager' ),
				'description' => __( 'Select a Pass to issue, or use the legacy access fields below.', 'studio-booking-manager' ),
				'options'     => $this->pass_type_options(),
			)
		);
		woocommerce_wp_select(
			array(
				'id'          => '_sbm_access_type',
				'label'       => __( 'Access type', 'studio-booking-manager' ),
				'options'     => $this->access_type_options(),
			)
		);
		woocommerce_wp_select(
			array(
				'id'          => '_sbm_location_id',
				'label'       => __( 'Location', 'studio-booking-manager' ),
				'options'     => $this->location_options(),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => '_sbm_total_credits',
				'label'             => __( 'Credits included', 'studio-booking-manager' ),
				'description'       => __( 'Use 1 for single visits, 12 for a monthly flex pass, or leave empty for memberships.', 'studio-booking-manager' ),
				'type'              => 'number',
				'custom_attributes' => array( 'min' => '0' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => '_sbm_weekly_limit',
				'label'             => __( 'Weekly visit limit', 'studio-booking-manager' ),
				'type'              => 'number',
				'custom_attributes' => array( 'min' => '0' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => '_sbm_guest_limit',
				'label'             => __( 'Guest limit', 'studio-booking-manager' ),
				'type'              => 'number',
				'custom_attributes' => array( 'min' => '0' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => '_sbm_validity_days',
				'label'             => __( 'Validity days', 'studio-booking-manager' ),
				'description'       => __( 'Leave empty for no automatic expiry.', 'studio-booking-manager' ),
				'type'              => 'number',
				'custom_attributes' => array( 'min' => '0' ),
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => '_sbm_requires_booking_date',
				'label'       => __( 'Require booking date', 'studio-booking-manager' ),
				'description' => __( 'Ask customers to choose their visit date before adding this product to the cart. Use for dated products such as a Day Pass.', 'studio-booking-manager' ),
				'value'       => 'yes' === get_post_meta( $product_id, '_sbm_requires_booking_date', true ) ? 'yes' : 'no',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_sbm_booking_start_time',
				'label'       => __( 'Booking start time', 'studio-booking-manager' ),
				'description' => __( 'Default start time for bookings created from customer-selected dates.', 'studio-booking-manager' ),
				'type'        => 'time',
				'value'       => get_post_meta( $product_id, '_sbm_booking_start_time', true ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_sbm_booking_end_time',
				'label'       => __( 'Booking end time', 'studio-booking-manager' ),
				'description' => __( 'Default end time for bookings created from customer-selected dates, such as closing time for a day pass.', 'studio-booking-manager' ),
				'type'        => 'time',
				'value'       => get_post_meta( $product_id, '_sbm_booking_end_time', true ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => '_sbm_booking_duration_minutes',
				'label'             => __( 'Booking duration minutes', 'studio-booking-manager' ),
				'description'       => __( 'Fallback used when no valid booking end time is set. Leave empty to use 480 minutes.', 'studio-booking-manager' ),
				'type'              => 'number',
				'value'             => get_post_meta( $product_id, '_sbm_booking_duration_minutes', true ),
				'custom_attributes' => array(
					'min'  => '15',
					'step' => '15',
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => '_sbm_booking_daily_capacity',
				'label'             => __( 'Daily booking capacity', 'studio-booking-manager' ),
				'description'       => __( 'Maximum quantity that can be booked for the same visit date. Leave empty or 0 for unlimited.', 'studio-booking-manager' ),
				'type'              => 'number',
				'value'             => get_post_meta( $product_id, '_sbm_booking_daily_capacity', true ),
				'custom_attributes' => array( 'min' => '0' ),
			)
		);
	}

	/**
	 * Save panel.
	 *
	 * @param int $post_id Product ID.
	 */
	public function save_panel( int $post_id ): void {
		$nonce = isset( $_POST['sbm_product_panel_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['sbm_product_panel_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'sbm_save_product_panel' ) ) {
			return;
		}

		$this->save_meta_values( $post_id, $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values are sanitized in save_meta_values().
	}

	/**
	 * Save variation fields.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $loop         Loop index.
	 */
	public function save_variation_fields( int $variation_id, int $loop ): void {
		$raw = array();
		$keys = array( '_sbm_enabled', '_sbm_pass_type_id', '_sbm_access_type', '_sbm_location_id', '_sbm_total_credits', '_sbm_weekly_limit', '_sbm_guest_limit', '_sbm_validity_days', '_sbm_requires_booking_date', '_sbm_booking_start_time', '_sbm_booking_end_time', '_sbm_booking_duration_minutes', '_sbm_booking_daily_capacity' );

		foreach ( $keys as $key ) {
			if ( isset( $_POST[ $key ][ $loop ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies variation save requests.
				$raw[ $key ] = wp_unslash( $_POST[ $key ][ $loop ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WooCommerce verifies variation save requests; sanitized in save_meta_values().
			}
		}

		$this->save_meta_values( $variation_id, $raw );
	}

	/**
	 * Save sanitized meta values.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $raw     Raw input.
	 */
	private function save_meta_values( int $post_id, array $raw ): void {
		$enabled                  = isset( $raw['_sbm_enabled'] ) ? 'yes' : 'no';
		$pass_type_id             = isset( $raw['_sbm_pass_type_id'] ) ? absint( $raw['_sbm_pass_type_id'] ) : 0;
		$access_type              = isset( $raw['_sbm_access_type'] ) ? sanitize_key( (string) $raw['_sbm_access_type'] ) : '';
		$location_id              = isset( $raw['_sbm_location_id'] ) ? absint( $raw['_sbm_location_id'] ) : 0;
		$total_credits            = isset( $raw['_sbm_total_credits'] ) ? absint( $raw['_sbm_total_credits'] ) : 0;
		$weekly_limit             = isset( $raw['_sbm_weekly_limit'] ) ? absint( $raw['_sbm_weekly_limit'] ) : 0;
		$guest_limit              = isset( $raw['_sbm_guest_limit'] ) ? absint( $raw['_sbm_guest_limit'] ) : 0;
		$validity_days            = isset( $raw['_sbm_validity_days'] ) ? absint( $raw['_sbm_validity_days'] ) : 0;
		$requires_booking_date    = isset( $raw['_sbm_requires_booking_date'] ) ? 'yes' : 'no';
		$booking_start_time       = isset( $raw['_sbm_booking_start_time'] ) ? sanitize_text_field( (string) $raw['_sbm_booking_start_time'] ) : '';
		$booking_end_time         = isset( $raw['_sbm_booking_end_time'] ) ? sanitize_text_field( (string) $raw['_sbm_booking_end_time'] ) : '';
		$booking_duration_minutes = isset( $raw['_sbm_booking_duration_minutes'] ) ? absint( $raw['_sbm_booking_duration_minutes'] ) : 0;
		$booking_daily_capacity   = isset( $raw['_sbm_booking_daily_capacity'] ) ? absint( $raw['_sbm_booking_daily_capacity'] ) : 0;

		if ( ! isset( $this->access_type_options()[ $access_type ] ) ) {
			$access_type = '';
		}

		if ( ! preg_match( '/^\d{2}:\d{2}$/', $booking_start_time ) ) {
			$booking_start_time = '';
		}

		if ( ! preg_match( '/^\d{2}:\d{2}$/', $booking_end_time ) ) {
			$booking_end_time = '';
		}

		if ( ! isset( $this->pass_type_options()[ $pass_type_id ] ) ) {
			$pass_type_id = 0;
		}

		update_post_meta( $post_id, '_sbm_enabled', $enabled );
		update_post_meta( $post_id, '_sbm_pass_type_id', $pass_type_id );
		update_post_meta( $post_id, '_sbm_access_type', $access_type );
		update_post_meta( $post_id, '_sbm_location_id', $location_id );
		update_post_meta( $post_id, '_sbm_total_credits', $total_credits );
		update_post_meta( $post_id, '_sbm_weekly_limit', $weekly_limit );
		update_post_meta( $post_id, '_sbm_guest_limit', $guest_limit );
		update_post_meta( $post_id, '_sbm_validity_days', $validity_days );
		update_post_meta( $post_id, '_sbm_requires_booking_date', $requires_booking_date );
		update_post_meta( $post_id, '_sbm_booking_start_time', $booking_start_time );
		update_post_meta( $post_id, '_sbm_booking_end_time', $booking_end_time );
		update_post_meta( $post_id, '_sbm_booking_duration_minutes', $booking_duration_minutes );
		update_post_meta( $post_id, '_sbm_booking_daily_capacity', $booking_daily_capacity );
	}

	/**
	 * Access type options.
	 *
	 * @return array<string,string>
	 */
	private function access_type_options(): array {
		return array(
			''             => __( 'Do not create access', 'studio-booking-manager' ),
			'single_visit' => __( 'Single Visit', 'studio-booking-manager' ),
			'visit_pass'   => __( 'Visit Pass', 'studio-booking-manager' ),
			'membership'   => __( 'Membership', 'studio-booking-manager' ),
		);
	}

	/**
	 * Location options.
	 *
	 * @return array<int|string,string>
	 */
	private function location_options(): array {
		$options = array( 0 => __( 'Default location', 'studio-booking-manager' ) );
		foreach ( ( new LocationService() )->all() as $location ) {
			$options[ (int) $location->id ] = (string) $location->name;
		}

		return $options;
	}

	/**
	 * Pass type options.
	 *
	 * @return array<int,string>
	 */
	private function pass_type_options(): array {
		if ( null !== $this->pass_type_options ) {
			return $this->pass_type_options;
		}

		$options = array(
			0 => __( 'Use legacy access settings', 'studio-booking-manager' ),
		);

		$service  = new PassTypeService();
		$statuses = $service->statuses();

		foreach ( $service->all() as $pass ) {
			$label = $pass->name;

			if ( 'active' !== $pass->status ) {
				$label = sprintf(
					/* translators: 1: pass name, 2: pass status. */
					__( '%1$s (%2$s)', 'studio-booking-manager' ),
					$pass->name,
					$statuses[ $pass->status ] ?? $pass->status
				);
			}

			$options[ (int) $pass->id ] = $label;
		}

		$this->pass_type_options = $options;

		return $this->pass_type_options;
	}
}

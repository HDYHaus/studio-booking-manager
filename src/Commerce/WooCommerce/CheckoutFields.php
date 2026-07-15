<?php
/**
 * WooCommerce checkout field adjustments.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Commerce\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Simplifies checkout fields for Studio Booking-only carts.
 */
final class CheckoutFields {
	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_checkout_fields', array( $this, 'maybe_simplify_checkout_fields' ) );
		add_filter( 'woocommerce_default_address_fields', array( $this, 'maybe_simplify_default_address_fields' ) );
		add_filter( 'woocommerce_get_country_locale', array( $this, 'maybe_simplify_country_locale' ) );
		add_filter( 'body_class', array( $this, 'add_checkout_body_class' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_styles' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'fill_classic_checkout_order_address' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'fill_block_checkout_order_address' ), 10, 2 );
		add_filter( 'woocommerce_order_get_formatted_billing_address', array( $this, 'format_booking_order_billing_address' ), 10, 3 );
	}

	/**
	 * Remove address fields for carts that only contain Studio Booking products.
	 *
	 * @param array<string,array<string,array<string,mixed>>> $fields Checkout fields.
	 * @return array<string,array<string,array<string,mixed>>>
	 */
	public function maybe_simplify_checkout_fields( array $fields ): array {
		if ( ! $this->is_enabled() || ! $this->cart_is_booking_only() ) {
			return $fields;
		}

		$billing_keep = array(
			'billing_first_name',
			'billing_last_name',
			'billing_email',
			'billing_phone',
		);

		if ( isset( $fields['billing'] ) && is_array( $fields['billing'] ) ) {
			foreach ( array_keys( $fields['billing'] ) as $key ) {
				if ( ! in_array( $key, $billing_keep, true ) ) {
					unset( $fields['billing'][ $key ] );
				}
			}

			if ( isset( $fields['billing']['billing_phone'] ) ) {
				$fields['billing']['billing_phone']['required'] = false;
			}
		}

		$fields['shipping'] = array();

		return $fields;
	}

	/**
	 * Mark address fields as optional and hidden for block checkout address forms.
	 *
	 * @param array<string,array<string,mixed>> $fields Default address fields.
	 * @return array<string,array<string,mixed>>
	 */
	public function maybe_simplify_default_address_fields( array $fields ): array {
		if ( ! $this->should_simplify_checkout() ) {
			return $fields;
		}

		foreach ( array( 'address_1', 'address_2', 'city', 'state', 'postcode' ) as $key ) {
			if ( isset( $fields[ $key ] ) && is_array( $fields[ $key ] ) ) {
				$fields[ $key ]['required'] = false;
				$fields[ $key ]['hidden']   = true;
			}
		}

		return $fields;
	}

	/**
	 * Mark localized address fields as optional and hidden for block checkout.
	 *
	 * @param array<string,array<string,array<string,mixed>>> $locale Country locale settings.
	 * @return array<string,array<string,array<string,mixed>>>
	 */
	public function maybe_simplify_country_locale( array $locale ): array {
		if ( ! $this->should_simplify_checkout() ) {
			return $locale;
		}

		foreach ( $locale as $country => $fields ) {
			foreach ( array( 'address_1', 'address_2', 'city', 'state', 'postcode' ) as $key ) {
				if ( isset( $fields[ $key ] ) && is_array( $fields[ $key ] ) ) {
					$locale[ $country ][ $key ]['required'] = false;
					$locale[ $country ][ $key ]['hidden']   = true;
				}
			}
		}

		return $locale;
	}

	/**
	 * Add a body class when the checkout can be simplified.
	 *
	 * @param array<int,string> $classes Body classes.
	 * @return array<int,string>
	 */
	public function add_checkout_body_class( array $classes ): array {
		if ( $this->is_checkout_screen() && $this->should_simplify_checkout() ) {
			$classes[] = 'sbm-simplify-booking-checkout';
		}

		return $classes;
	}

	/**
	 * Enqueue frontend styles on simplified checkout screens.
	 */
	public function enqueue_checkout_styles(): void {
		if ( ! $this->is_checkout_screen() || ! $this->is_enabled() ) {
			return;
		}

		wp_enqueue_style(
			'studio-booking-manager-frontend',
			SBM_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			SBM_VERSION
		);

		if ( $this->should_simplify_checkout() ) {
			$base = wc_get_base_location();

			wp_enqueue_script(
				'studio-booking-manager-checkout',
				SBM_PLUGIN_URL . 'assets/js/checkout.js',
				array(),
				SBM_VERSION,
				true
			);

			wp_localize_script(
				'studio-booking-manager-checkout',
				'sbmCheckoutFields',
				array(
					'address1' => __( 'Studio booking', 'studio-booking-manager' ),
					'city'     => __( 'Not required', 'studio-booking-manager' ),
					'postcode' => '00000',
					'country'  => isset( $base['country'] ) ? (string) $base['country'] : '',
					'state'    => isset( $base['state'] ) ? (string) $base['state'] : '',
				)
			);
		}
	}

	/**
	 * Keep block checkout orders valid when physical address fields are omitted.
	 *
	 * @param \WC_Order        $order Order object.
	 * @param \WP_REST_Request $request Store API request.
	 */
	public function fill_block_checkout_order_address( \WC_Order $order, \WP_REST_Request $request ): void {
		if ( ! $this->should_simplify_checkout() ) {
			return;
		}

		$this->fill_missing_billing_address( $order );
	}

	/**
	 * Keep classic checkout orders valid when physical address fields are omitted.
	 *
	 * @param \WC_Order           $order Order object.
	 * @param array<string,mixed> $data Posted checkout data.
	 */
	public function fill_classic_checkout_order_address( \WC_Order $order, array $data ): void {
		if ( ! $this->should_simplify_checkout() ) {
			return;
		}

		$this->fill_missing_billing_address( $order );
	}

	/**
	 * Hide placeholder physical address values on customer-facing order output.
	 *
	 * @param string              $address Formatted billing address.
	 * @param array<string,mixed> $raw_address Raw billing address pieces.
	 * @param \WC_Order           $order Order object.
	 */
	public function format_booking_order_billing_address( string $address, array $raw_address, \WC_Order $order ): string {
		if ( ! $this->is_enabled() || ! $this->order_is_booking_only( $order ) ) {
			return $address;
		}

		if (
			'Studio booking' !== $order->get_billing_address_1()
			|| 'Not required' !== $order->get_billing_city()
			|| '00000' !== $order->get_billing_postcode()
		) {
			return $address;
		}

		$lines = array_filter( array( $order->get_formatted_billing_full_name() ) );

		return implode( '<br/>', array_map( 'esc_html', $lines ) );
	}

	/**
	 * Determine whether simplified checkout is enabled.
	 */
	private function is_enabled(): bool {
		$settings = get_option( 'sbm_settings', array() );

		return is_array( $settings ) && ! empty( $settings['woocommerce_simplify_booking_checkout'] );
	}

	/**
	 * Determine whether the current request should use simplified checkout.
	 */
	private function should_simplify_checkout(): bool {
		return $this->is_enabled() && $this->cart_is_booking_only();
	}

	/**
	 * Determine whether the current frontend page is checkout.
	 */
	private function is_checkout_screen(): bool {
		return function_exists( 'is_checkout' ) && is_checkout();
	}

	/**
	 * Fill only the physical address pieces Woo may expect when the block omits them.
	 *
	 * @param \WC_Order $order Order object.
	 */
	private function fill_missing_billing_address( \WC_Order $order ): void {
		$base = wc_get_base_location();

		if ( '' === $order->get_billing_country() && ! empty( $base['country'] ) ) {
			$order->set_billing_country( (string) $base['country'] );
		}

		if ( '' === $order->get_billing_state() && ! empty( $base['state'] ) ) {
			$order->set_billing_state( (string) $base['state'] );
		}

		if ( '' === $order->get_billing_address_1() ) {
			$order->set_billing_address_1( __( 'Studio booking', 'studio-booking-manager' ) );
		}

		if ( '' === $order->get_billing_city() ) {
			$order->set_billing_city( __( 'Not required', 'studio-booking-manager' ) );
		}

		if ( '' === $order->get_billing_postcode() ) {
			$order->set_billing_postcode( '00000' );
		}
	}

	/**
	 * Determine whether every cart item is a Studio Booking access product.
	 */
	private function cart_is_booking_only(): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		$cart = WC()->cart->get_cart();

		if ( empty( $cart ) ) {
			return false;
		}

		foreach ( $cart as $cart_item ) {
			$product_id   = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
			$variation_id = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;

			if ( ! $this->has_access_config( $this->get_config_id( $product_id, $variation_id ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Determine whether every order line item is a Studio Booking access product.
	 *
	 * @param \WC_Order $order Order object.
	 */
	private function order_is_booking_only( \WC_Order $order ): bool {
		$items = $order->get_items();

		if ( empty( $items ) ) {
			return false;
		}

		foreach ( $items as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				return false;
			}

			if ( ! $this->has_access_config( $this->get_config_id( $item->get_product_id(), $item->get_variation_id() ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Determine whether a product or variation creates Studio Booking access.
	 *
	 * @param int $config_id Product or variation config ID.
	 */
	private function has_access_config( int $config_id ): bool {
		if ( $config_id <= 0 || 'yes' !== get_post_meta( $config_id, '_sbm_enabled', true ) ) {
			return false;
		}

		$pass_type_id = absint( get_post_meta( $config_id, '_sbm_pass_type_id', true ) );
		$type         = sanitize_key( (string) get_post_meta( $config_id, '_sbm_access_type', true ) );

		return $pass_type_id > 0 || in_array( $type, array( 'single_visit', 'visit_pass', 'membership' ), true );
	}

	/**
	 * Get the product or variation ID that stores Studio Booking config.
	 *
	 * @param int $product_id Product ID.
	 * @param int $variation_id Variation ID.
	 */
	private function get_config_id( int $product_id, int $variation_id ): int {
		return $variation_id > 0 && 'yes' === get_post_meta( $variation_id, '_sbm_enabled', true ) ? $variation_id : $product_id;
	}
}

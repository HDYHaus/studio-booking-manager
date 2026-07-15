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
	 * Determine whether simplified checkout is enabled.
	 */
	private function is_enabled(): bool {
		$settings = get_option( 'sbm_settings', array() );

		return is_array( $settings ) && ! empty( $settings['woocommerce_simplify_booking_checkout'] );
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
			$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

			if ( $product instanceof \WC_Product && $product->needs_shipping() ) {
				return false;
			}

			$product_id   = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
			$variation_id = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;

			if ( ! $this->has_access_config( $this->get_config_id( $product_id, $variation_id ) ) ) {
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

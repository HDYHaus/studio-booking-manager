<?php
/**
 * WooCommerce shop loop add-to-cart behavior.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Commerce\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Sends booking-date products to the product page from shop grids.
 */
final class LoopAddToCart {
	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'add_to_cart_text' ), 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_url', array( $this, 'add_to_cart_url' ), 10, 2 );
		add_filter( 'woocommerce_loop_add_to_cart_args', array( $this, 'add_to_cart_args' ), 10, 2 );
		add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'add_to_cart_link' ), 20, 3 );
	}

	/**
	 * Change shop-loop button text for date-required booking products.
	 *
	 * @param string      $text Product button text.
	 * @param \WC_Product $product Product.
	 */
	public function add_to_cart_text( string $text, \WC_Product $product ): string {
		return $this->requires_booking_date( $product ) ? __( 'Choose date', 'studio-booking-manager' ) : $text;
	}

	/**
	 * Send date-required booking products to the product page.
	 *
	 * @param string      $url Product add-to-cart URL.
	 * @param \WC_Product $product Product.
	 */
	public function add_to_cart_url( string $url, \WC_Product $product ): string {
		return $this->requires_booking_date( $product ) ? $product->get_permalink() : $url;
	}

	/**
	 * Remove AJAX add-to-cart behavior for date-required booking products.
	 *
	 * @param array<string,mixed> $args Button args.
	 * @param \WC_Product         $product Product.
	 * @return array<string,mixed>
	 */
	public function add_to_cart_args( array $args, \WC_Product $product ): array {
		if ( ! $this->requires_booking_date( $product ) ) {
			return $args;
		}

		$args['class'] = 'button sbm-choose-date-button';

		return $args;
	}

	/**
	 * Replace the final shop-loop button for date-required booking products.
	 *
	 * @param string              $html Product button HTML.
	 * @param \WC_Product         $product Product.
	 * @param array<string,mixed> $args Button args.
	 */
	public function add_to_cart_link( string $html, \WC_Product $product, array $args ): string {
		if ( ! $this->requires_booking_date( $product ) ) {
			return $html;
		}

		return sprintf(
			'<a href="%1$s" class="%2$s" aria-label="%3$s" rel="nofollow">%4$s</a>',
			esc_url( $product->get_permalink() ),
			esc_attr( 'button sbm-choose-date-button' ),
			esc_attr(
				sprintf(
					/* translators: %s: product name. */
					__( 'Choose a visit date for %s', 'studio-booking-manager' ),
					$product->get_name()
				)
			),
			esc_html__( 'Choose date', 'studio-booking-manager' )
		);
	}

	/**
	 * Determine whether a product needs a selected visit date before purchase.
	 *
	 * @param \WC_Product $product Product.
	 */
	private function requires_booking_date( \WC_Product $product ): bool {
		if ( $product instanceof \WC_Product_Variable ) {
			foreach ( $product->get_children() as $variation_id ) {
				if ( $this->requires_booking_date_for_config_id( absint( $variation_id ) ) ) {
					return true;
				}
			}
		}

		return $this->requires_booking_date_for_config_id( $product->get_id() );
	}

	/**
	 * Determine whether a product or variation config requires a booking date.
	 *
	 * @param int $config_id Product or variation ID.
	 */
	private function requires_booking_date_for_config_id( int $config_id ): bool {
		return $config_id > 0
			&& 'yes' === get_post_meta( $config_id, '_sbm_enabled', true )
			&& 'yes' === get_post_meta( $config_id, '_sbm_requires_booking_date', true );
	}
}

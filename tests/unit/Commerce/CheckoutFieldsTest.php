<?php
/**
 * Tests for WooCommerce checkout field simplification.
 *
 * @package StudioBookingManager
 */

declare(strict_types=1);

namespace StudioBookingManager\Tests\Unit\Commerce;

use StudioBookingManager\Commerce\WooCommerce\CheckoutFields;

/**
 * @covers \StudioBookingManager\Commerce\WooCommerce\CheckoutFields
 */
final class CheckoutFieldsTest extends TestCase {
	/**
	 * Booking-only carts keep contact fields and remove physical address fields.
	 */
	public function test_booking_only_cart_removes_classic_address_fields(): void {
		$this->set_up_test_state();
		$this->enable_simplified_checkout();
		$this->configure_booking_product( 42 );
		$this->set_cart(
			array(
				array(
					'product_id'   => 42,
					'variation_id' => 0,
				),
			)
		);

		$fields = ( new CheckoutFields() )->maybe_simplify_checkout_fields(
			array(
				'billing'  => array(
					'billing_first_name' => array( 'required' => true ),
					'billing_last_name'  => array( 'required' => true ),
					'billing_email'      => array( 'required' => true ),
					'billing_phone'      => array( 'required' => true ),
					'billing_address_1'  => array( 'required' => true ),
					'billing_city'       => array( 'required' => true ),
					'billing_postcode'   => array( 'required' => true ),
				),
				'shipping' => array(
					'shipping_address_1' => array( 'required' => true ),
				),
			)
		);

		$this->assert_true( isset( $fields['billing']['billing_first_name'] ) );
		$this->assert_true( isset( $fields['billing']['billing_email'] ) );
		$this->assert_false( isset( $fields['billing']['billing_address_1'] ) );
		$this->assert_false( isset( $fields['billing']['billing_city'] ) );
		$this->assert_same( false, $fields['billing']['billing_phone']['required'] );
		$this->assert_same( array(), $fields['shipping'] );
	}

	/**
	 * Mixed carts must keep normal checkout address fields.
	 */
	public function test_mixed_cart_keeps_classic_address_fields(): void {
		$this->set_up_test_state();
		$this->enable_simplified_checkout();
		$this->configure_booking_product( 42 );
		$this->set_cart(
			array(
				array(
					'product_id'   => 42,
					'variation_id' => 0,
				),
				array(
					'product_id'   => 99,
					'variation_id' => 0,
				),
			)
		);

		$fields = ( new CheckoutFields() )->maybe_simplify_checkout_fields(
			array(
				'billing'  => array(
					'billing_first_name' => array( 'required' => true ),
					'billing_address_1'  => array( 'required' => true ),
				),
				'shipping' => array(
					'shipping_address_1' => array( 'required' => true ),
				),
			)
		);

		$this->assert_true( isset( $fields['billing']['billing_address_1'] ) );
		$this->assert_true( isset( $fields['shipping']['shipping_address_1'] ) );
	}

	/**
	 * Block checkout address fields are hidden and made optional for booking-only carts.
	 */
	public function test_booking_only_cart_hides_block_address_fields(): void {
		$this->set_up_test_state();
		$this->enable_simplified_checkout();
		$this->configure_booking_product( 42 );
		$this->set_cart(
			array(
				array(
					'product_id'   => 42,
					'variation_id' => 0,
				),
			)
		);

		$fields = ( new CheckoutFields() )->maybe_simplify_default_address_fields(
			array(
				'address_1' => array( 'required' => true ),
				'city'      => array( 'required' => true ),
				'postcode'  => array( 'required' => true ),
				'country'   => array( 'required' => true ),
			)
		);

		$this->assert_same( false, $fields['address_1']['required'] );
		$this->assert_same( true, $fields['address_1']['hidden'] );
		$this->assert_same( false, $fields['city']['required'] );
		$this->assert_same( true, $fields['postcode']['hidden'] );
		$this->assert_same( true, $fields['country']['required'], 'Country is kept for Woo payment/location logic.' );
	}

	/**
	 * Checkout block script enqueues with fallback address defaults.
	 */
	public function test_checkout_assets_enqueue_for_booking_only_checkout(): void {
		$this->set_up_test_state();
		$this->enable_simplified_checkout();
		$this->configure_booking_product( 42 );
		$this->set_cart(
			array(
				array(
					'product_id'   => 42,
					'variation_id' => 0,
				),
			)
		);
		$GLOBALS['sbm_test_is_checkout'] = true;

		( new CheckoutFields() )->enqueue_checkout_styles();

		$this->assert_true( in_array( 'studio-booking-manager-frontend', $GLOBALS['sbm_test_enqueued_styles'], true ) );
		$this->assert_true( in_array( 'studio-booking-manager-checkout', $GLOBALS['sbm_test_enqueued_scripts'], true ) );
		$this->assert_same( 'Studio booking', $GLOBALS['sbm_test_localized_scripts']['studio-booking-manager-checkout']['sbmCheckoutFields']['address1'] );
	}

	/**
	 * Placeholder billing address values are hidden for booking-only order output.
	 */
	public function test_placeholder_billing_address_is_hidden_for_booking_orders(): void {
		$this->set_up_test_state();
		$this->enable_simplified_checkout();
		$this->configure_booking_product( 42 );

		$order = new \WC_Order( array( new \WC_Order_Item_Product( 42 ) ) );
		$order->set_billing_first_name( 'Test' );
		$order->set_billing_last_name( 'Customer' );
		$order->set_billing_address_1( 'Studio booking' );
		$order->set_billing_city( 'Not required' );
		$order->set_billing_postcode( '00000' );

		$formatted = ( new CheckoutFields() )->format_booking_order_billing_address( 'Test Customer<br/>Studio booking<br/>Not required<br/>00000', array(), $order );

		$this->assert_same( 'Test Customer', $formatted );
	}

	/**
	 * Enable the checkout simplification setting.
	 */
	private function enable_simplified_checkout(): void {
		$GLOBALS['sbm_test_options']['sbm_settings'] = array(
			'woocommerce_simplify_booking_checkout' => 1,
		);
	}

	/**
	 * Configure a product as a Studio Booking product.
	 *
	 * @param int $product_id Product ID.
	 */
	private function configure_booking_product( int $product_id ): void {
		$GLOBALS['sbm_test_post_meta'][ $product_id ] = array(
			'_sbm_enabled'      => 'yes',
			'_sbm_pass_type_id' => '7',
			'_sbm_access_type'  => 'single_visit',
		);
	}

	/**
	 * Set test cart items.
	 *
	 * @param array<int, array<string, mixed>> $items Cart items.
	 */
	private function set_cart( array $items ): void {
		\WC()->cart = new \SBM_Test_Cart( $items );
	}
}

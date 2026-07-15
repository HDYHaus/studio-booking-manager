<?php
/**
 * Tests for WooCommerce shop loop add-to-cart behavior.
 *
 * @package StudioBookingManager
 */

declare(strict_types=1);

namespace StudioBookingManager\Tests\Unit\Commerce;

use StudioBookingManager\Commerce\WooCommerce\LoopAddToCart;

/**
 * @covers \StudioBookingManager\Commerce\WooCommerce\LoopAddToCart
 */
final class LoopAddToCartTest extends TestCase {
	/**
	 * Booking-date products link to the product page with choose-date text.
	 */
	public function test_booking_date_product_uses_product_page_link(): void {
		$this->set_up_test_state();
		$this->configure_booking_date_product( 42 );

		$product = new \WC_Product( 42, 'Day pass', 'https://example.test/day-pass' );
		$loop    = new LoopAddToCart();

		$this->assert_same( 'Choose date', $loop->add_to_cart_text( 'Add to basket', $product ) );
		$this->assert_same( 'https://example.test/day-pass', $loop->add_to_cart_url( '?add-to-cart=42', $product ) );

		$args = $loop->add_to_cart_args( array( 'class' => 'button ajax_add_to_cart' ), $product );
		$this->assert_same( 'button sbm-choose-date-button', $args['class'] );

		$html = $loop->add_to_cart_link( '<a class="button ajax_add_to_cart">Add to basket</a>', $product, $args );
		$this->assert_true( false !== strpos( $html, 'href="https://example.test/day-pass"' ) );
		$this->assert_true( false !== strpos( $html, 'class="button sbm-choose-date-button"' ) );
		$this->assert_true( false === strpos( $html, 'ajax_add_to_cart' ) );
	}

	/**
	 * Regular products keep default shop-loop behavior.
	 */
	public function test_regular_product_keeps_existing_loop_link(): void {
		$this->set_up_test_state();

		$product = new \WC_Product( 99, 'Hoodie', 'https://example.test/hoodie' );
		$loop    = new LoopAddToCart();
		$html    = '<a class="button ajax_add_to_cart">Add to basket</a>';

		$this->assert_same( 'Add to basket', $loop->add_to_cart_text( 'Add to basket', $product ) );
		$this->assert_same( '?add-to-cart=99', $loop->add_to_cart_url( '?add-to-cart=99', $product ) );
		$this->assert_same( array( 'class' => 'button ajax_add_to_cart' ), $loop->add_to_cart_args( array( 'class' => 'button ajax_add_to_cart' ), $product ) );
		$this->assert_same( $html, $loop->add_to_cart_link( $html, $product, array() ) );
	}

	/**
	 * Variable products use choose-date behavior when any variation requires a date.
	 */
	public function test_variable_product_uses_choose_date_when_child_requires_date(): void {
		$this->set_up_test_state();
		$this->configure_booking_date_product( 123 );

		$product = new \WC_Product_Variable( 42, array( 123 ) );
		$loop    = new LoopAddToCart();

		$this->assert_same( 'Choose date', $loop->add_to_cart_text( 'Select options', $product ) );
	}

	/**
	 * Configure a product as requiring a booking date.
	 *
	 * @param int $product_id Product ID.
	 */
	private function configure_booking_date_product( int $product_id ): void {
		$GLOBALS['sbm_test_post_meta'][ $product_id ] = array(
			'_sbm_enabled'               => 'yes',
			'_sbm_requires_booking_date' => 'yes',
		);
	}
}

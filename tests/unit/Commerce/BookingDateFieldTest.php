<?php
/**
 * Tests for WooCommerce booking date field validation.
 *
 * @package StudioBookingManager
 */

declare(strict_types=1);

namespace StudioBookingManager\Tests\Unit\Commerce;

use StudioBookingManager\Commerce\WooCommerce\BookingDateField;

/**
 * @covers \StudioBookingManager\Commerce\WooCommerce\BookingDateField
 */
final class BookingDateFieldTest extends TestCase {
	/**
	 * Date-required products cannot be added without a visit date.
	 */
	public function test_date_required_product_requires_visit_date(): void {
		$this->set_up_test_state();
		$this->configure_date_required_product( 42 );

		$passed = ( new BookingDateField() )->validate_add_to_cart( true, 42, 1, 0 );

		$this->assert_false( $passed );
		$this->assert_same( 'error', $GLOBALS['sbm_test_wc_notices'][0]['type'] );
		$this->assert_same( 'Open this product and choose a visit date before adding it to your cart.', $GLOBALS['sbm_test_wc_notices'][0]['message'] );
	}

	/**
	 * Selected dates are stored on the cart item for date-required products.
	 */
	public function test_selected_date_is_stored_on_cart_item(): void {
		$this->set_up_test_state();
		$this->configure_date_required_product( 42 );
		$_POST['sbm_booking_date'] = '2026-07-18';

		$data = ( new BookingDateField() )->add_cart_item_data( array(), 42, 0 );

		$this->assert_same( '2026-07-18', $data['sbm_booking_date'] );
		$this->assert_true( isset( $data['sbm_booking_date_key'] ) );
	}

	/**
	 * Date display appears in cart and checkout item data.
	 */
	public function test_selected_date_displays_in_cart_item_data(): void {
		$this->set_up_test_state();
		$GLOBALS['sbm_test_options']['date_format'] = 'j F Y';

		$data = ( new BookingDateField() )->display_cart_item_data(
			array(),
			array( 'sbm_booking_date' => '2026-07-18' )
		);

		$this->assert_same( 'Visit date', $data[0]['key'] );
		$this->assert_same( '18 July 2026', $data[0]['value'] );
	}

	/**
	 * Daily capacity blocks add-to-cart when existing bookings plus request exceed capacity.
	 */
	public function test_daily_capacity_blocks_add_to_cart(): void {
		$this->set_up_test_state();
		$this->configure_date_required_product( 42 );
		$GLOBALS['sbm_test_post_meta'][42]['_sbm_booking_daily_capacity'] = '2';
		$GLOBALS['sbm_test_options']['date_format'] = 'j F Y';
		$GLOBALS['wpdb']->var = 1;
		$_POST['sbm_booking_date'] = '2026-07-18';

		$passed = ( new BookingDateField() )->validate_add_to_cart( true, 42, 2, 0 );

		$this->assert_false( $passed );
		$this->assert_same( 'The selected visit date 18 July 2026 has reached the daily capacity of 2.', $GLOBALS['sbm_test_wc_notices'][0]['message'] );
	}

	/**
	 * Private schedule blocks date-required products without exposing private details.
	 */
	public function test_private_schedule_blocks_add_to_cart(): void {
		$this->set_up_test_state();
		$this->configure_date_required_product( 42 );
		$GLOBALS['wpdb']->results = array(
			(object) array(
				'id'           => 7,
				'visibility'   => 'private',
				'public_title' => '',
				'starts_at'    => '2026-07-18 09:00:00',
				'ends_at'      => '2026-07-18 10:00:00',
			),
		);
		$_POST['sbm_booking_date'] = '2026-07-18';

		$passed = ( new BookingDateField() )->validate_add_to_cart( true, 42, 1, 0 );

		$this->assert_false( $passed );
		$this->assert_same( 'The selected visit date is unavailable for day passes.', $GLOBALS['sbm_test_wc_notices'][0]['message'] );
	}

	/**
	 * Configure a product as requiring a booking date.
	 *
	 * @param int $product_id Product ID.
	 */
	private function configure_date_required_product( int $product_id ): void {
		$GLOBALS['sbm_test_post_meta'][ $product_id ] = array(
			'_sbm_enabled'                => 'yes',
			'_sbm_requires_booking_date'  => 'yes',
			'_sbm_booking_daily_capacity' => '0',
			'_sbm_location_id'            => '0',
		);
	}
}

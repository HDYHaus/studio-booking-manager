<?php
/**
 * Tests for WooCommerce product panel metadata handling.
 *
 * @package StudioBookingManager
 */

declare(strict_types=1);

namespace StudioBookingManager\Tests\Unit\Commerce;

use StudioBookingManager\WooCommerce\ProductPanel;

/**
 * @covers \StudioBookingManager\WooCommerce\ProductPanel
 */
final class ProductPanelTest extends TestCase {
	/**
	 * Parent scalar Studio Booking fields must not leak into variation saves.
	 */
	public function test_parent_scalar_fields_do_not_enable_variations(): void {
		$this->set_up_test_state();
		$_POST['_sbm_enabled']                = 'yes';
		$_POST['_sbm_requires_booking_date']  = 'yes';
		$_POST['_sbm_booking_daily_capacity'] = '4';

		( new ProductPanel() )->save_variation_fields( 84, 0 );

		$this->assert_same( 'no', $GLOBALS['sbm_test_post_meta'][84]['_sbm_enabled'] );
		$this->assert_same( 'no', $GLOBALS['sbm_test_post_meta'][84]['_sbm_requires_booking_date'] );
		$this->assert_same( 0, $GLOBALS['sbm_test_post_meta'][84]['_sbm_booking_daily_capacity'] );
	}

	/**
	 * Variation fields save from names that cannot collide with parent product fields.
	 */
	public function test_variation_fields_save_from_variation_names(): void {
		$this->set_up_test_state();
		$_POST['_sbm_variation_enabled']                = array( 0 => 'yes' );
		$_POST['_sbm_variation_requires_booking_date']  = array( 0 => 'yes' );
		$_POST['_sbm_variation_booking_daily_capacity'] = array( 0 => '4' );
		$_POST['_sbm_variation_booking_duration_minutes'] = array( 0 => '30' );

		( new ProductPanel() )->save_variation_fields( 84, 0 );

		$this->assert_same( 'yes', $GLOBALS['sbm_test_post_meta'][84]['_sbm_enabled'] );
		$this->assert_same( 'yes', $GLOBALS['sbm_test_post_meta'][84]['_sbm_requires_booking_date'] );
		$this->assert_same( 4, $GLOBALS['sbm_test_post_meta'][84]['_sbm_booking_daily_capacity'] );
		$this->assert_same( 30, $GLOBALS['sbm_test_post_meta'][84]['_sbm_booking_duration_minutes'] );
	}

	/**
	 * Parent saves ignore array values produced by variation field groups.
	 */
	public function test_parent_save_ignores_variation_arrays(): void {
		$this->set_up_test_state();
		$_POST['sbm_product_panel_nonce'] = 'valid';
		$_POST['_sbm_enabled']            = array( 0 => 'yes' );
		$_POST['_sbm_booking_daily_capacity'] = array( 0 => '4' );
		$_POST['_sbm_variation_enabled']  = array( 0 => 'yes' );
		$_POST['_sbm_variation_booking_daily_capacity'] = array( 0 => '4' );

		( new ProductPanel() )->save_panel( 42 );

		$this->assert_same( 'no', $GLOBALS['sbm_test_post_meta'][42]['_sbm_enabled'] );
		$this->assert_same( 0, $GLOBALS['sbm_test_post_meta'][42]['_sbm_booking_daily_capacity'] );
	}
}

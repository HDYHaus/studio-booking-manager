<?php
/**
 * WooCommerce order listener.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Commerce\WooCommerce;

use StudioBookingManager\Access\AccessService;
use StudioBookingManager\Locations\LocationService;
use StudioBookingManager\PassTypes\PassAccessMapper;
use StudioBookingManager\PassTypes\PassType;
use StudioBookingManager\PassTypes\PassTypeService;
use StudioBookingManager\People\PersonService;

defined( 'ABSPATH' ) || exit;

/**
 * Creates access records from paid WooCommerce orders.
 */
final class OrderListener {
	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_order_status_processing', array( $this, 'maybe_create_access_from_order' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_create_access_from_order' ) );
		add_filter( 'woocommerce_cod_process_payment_order_status', array( $this, 'set_offline_payment_order_status' ) );
		add_filter( 'woocommerce_bacs_process_payment_order_status', array( $this, 'set_offline_payment_order_status' ) );
	}

	/**
	 * Set offline payment orders to pending until payment is confirmed.
	 *
	 * @param string $status Default WooCommerce order status.
	 * @return string
	 */
	public function set_offline_payment_order_status( string $status ): string {
		return 'pending';
	}

	/**
	 * Create access records from paid orders.
	 *
	 * @param int $order_id Order ID.
	 */
	public function maybe_create_access_from_order( int $order_id ): void {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$person_id = $this->get_or_create_person( $order );

		if ( $person_id <= 0 ) {
			return;
		}

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$existing_access_id = absint( wc_get_order_item_meta( $item_id, '_sbm_access_id', true ) );
			if ( $existing_access_id > 0 ) {
				continue;
			}

			$access_data = $this->get_item_access_data( $item, $person_id, $order );

			if ( empty( $access_data ) ) {
				continue;
			}

			$access_id = ( new AccessService() )->save( $access_data );

			if ( $access_id > 0 ) {
				wc_update_order_item_meta( $item_id, '_sbm_access_id', $access_id );
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `sbm_` is the documented public API prefix for Studio Booking Manager.
				do_action( 'sbm_access_created_from_order', $access_id, $order );
			}
		}
	}

	/**
	 * Find or create a person for an order.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return int
	 */
	private function get_or_create_person( \WC_Order $order ): int {
		$email = sanitize_email( $order->get_billing_email() );

		if ( '' === $email ) {
			return 0;
		}

		$people = new PersonService();
		$person = $people->find_by_email( $email );

		if ( $person instanceof \stdClass ) {
			return (int) $person->id;
		}

		$first_name   = sanitize_text_field( $order->get_billing_first_name() );
		$last_name    = sanitize_text_field( $order->get_billing_last_name() );
		$display_name = trim( $first_name . ' ' . $last_name );

		return $people->save(
			array(
				'wp_user_id'   => absint( $order->get_customer_id() ),
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => '' !== $display_name ? $display_name : $email,
				'email'        => $email,
				'phone'        => sanitize_text_field( $order->get_billing_phone() ),
				'status'       => 'active',
			)
		);
	}

	/**
	 * Get access data for an order item.
	 *
	 * @param \WC_Order_Item_Product $item Order item.
	 * @param int                    $person_id Person ID.
	 * @param \WC_Order              $order WooCommerce order.
	 * @return array<string,mixed>
	 */
	private function get_item_access_data( \WC_Order_Item_Product $item, int $person_id, \WC_Order $order ): array {
		$config_id = $this->get_item_config_id( $item );

		if ( $config_id <= 0 || 'yes' !== get_post_meta( $config_id, '_sbm_enabled', true ) ) {
			return array();
		}

		$location_id  = $this->get_configured_location_id( $config_id );
		$pass_type_id = absint( get_post_meta( $config_id, '_sbm_pass_type_id', true ) );

		if ( $pass_type_id > 0 ) {
			return $this->get_pass_access_data( $pass_type_id, $person_id, $location_id, $order, $item );
		}

		return $this->get_legacy_access_data( $config_id, $person_id, $location_id, $order, $item );
	}

	/**
	 * Get the product or variation ID that stores Studio Booking config.
	 *
	 * @param \WC_Order_Item_Product $item Order item.
	 * @return int
	 */
	private function get_item_config_id( \WC_Order_Item_Product $item ): int {
		$variation_id = absint( $item->get_variation_id() );
		$product_id   = absint( $item->get_product_id() );

		return $variation_id > 0 && 'yes' === get_post_meta( $variation_id, '_sbm_enabled', true ) ? $variation_id : $product_id;
	}

	/**
	 * Get access data from a selected Pass.
	 *
	 * @param int                    $pass_type_id Pass type ID.
	 * @param int                    $person_id Person ID.
	 * @param int                    $location_id Location ID.
	 * @param \WC_Order              $order WooCommerce order.
	 * @param \WC_Order_Item_Product $item Order item.
	 * @return array<string,mixed>
	 */
	private function get_pass_access_data( int $pass_type_id, int $person_id, int $location_id, \WC_Order $order, \WC_Order_Item_Product $item ): array {
		$pass = ( new PassTypeService() )->find( $pass_type_id );

		if ( ! $pass instanceof PassType || 'active' !== $pass->status ) {
			return array();
		}

		$metadata = sprintf(
			/* translators: 1: WooCommerce order ID, 2: pass name. */
			__( 'Created from WooCommerce order #%1$d using pass "%2$s".', 'studio-booking-manager' ),
			$order->get_id(),
			$pass->name
		);

		return ( new PassAccessMapper() )->map(
			$pass,
			$person_id,
			$location_id,
			$this->get_order_context_data( $order, $item, $metadata )
		);
	}

	/**
	 * Get access data from legacy product metadata.
	 *
	 * @param int                    $config_id Product or variation ID.
	 * @param int                    $person_id Person ID.
	 * @param int                    $location_id Location ID.
	 * @param \WC_Order              $order WooCommerce order.
	 * @param \WC_Order_Item_Product $item Order item.
	 * @return array<string,mixed>
	 */
	private function get_legacy_access_data( int $config_id, int $person_id, int $location_id, \WC_Order $order, \WC_Order_Item_Product $item ): array {
		$type = sanitize_key( (string) get_post_meta( $config_id, '_sbm_access_type', true ) );

		if ( ! in_array( $type, array( 'single_visit', 'visit_pass', 'membership' ), true ) ) {
			return array();
		}

		$total_credits = absint( get_post_meta( $config_id, '_sbm_total_credits', true ) );
		if ( 'single_visit' === $type && $total_credits <= 0 ) {
			$total_credits = 1;
		}

		$metadata = sprintf(
			/* translators: %d: WooCommerce order ID. */
			__( 'Created from WooCommerce order #%d.', 'studio-booking-manager' ),
			$order->get_id()
		);

		return array_merge(
			array(
				'person_id'         => $person_id,
				'location_id'       => $location_id,
				'access_type'       => $type,
				'status'            => 'active',
				'total_credits'     => $total_credits,
				'remaining_credits' => $total_credits,
				'weekly_limit'      => absint( get_post_meta( $config_id, '_sbm_weekly_limit', true ) ),
				'guest_limit'       => absint( get_post_meta( $config_id, '_sbm_guest_limit', true ) ),
				'starts_at'         => current_time( 'mysql' ),
				'expires_at'        => $this->calculate_expiry( absint( get_post_meta( $config_id, '_sbm_validity_days', true ) ) ),
			),
			$this->get_order_context_data( $order, $item, $metadata )
		);
	}

	/**
	 * Get configured or default location ID.
	 *
	 * @param int $config_id Product or variation ID.
	 * @return int
	 */
	private function get_configured_location_id( int $config_id ): int {
		$location_id = absint( get_post_meta( $config_id, '_sbm_location_id', true ) );

		if ( $location_id > 0 ) {
			return $location_id;
		}

		return $this->get_default_location_id();
	}

	/**
	 * Get common order context access data.
	 *
	 * @param \WC_Order              $order WooCommerce order.
	 * @param \WC_Order_Item_Product $item Order item.
	 * @param string                 $metadata Access metadata.
	 * @return array<string,mixed>
	 */
	private function get_order_context_data( \WC_Order $order, \WC_Order_Item_Product $item, string $metadata ): array {
		return array(
			'wp_user_id'   => absint( $order->get_customer_id() ),
			'order_id'     => $order->get_id(),
			'product_id'   => $item->get_product_id(),
			'variation_id' => $item->get_variation_id(),
			'metadata'     => $metadata,
		);
	}

	/**
	 * Get default location ID.
	 *
	 * @return int
	 */
	private function get_default_location_id(): int {
		foreach ( ( new LocationService() )->all() as $location ) {
			if ( ! empty( $location->is_default ) ) {
				return (int) $location->id;
			}
		}

		$locations = ( new LocationService() )->all();
		$first     = reset( $locations );

		return $first instanceof \stdClass ? (int) $first->id : 0;
	}

	/**
	 * Calculate expiry date.
	 *
	 * @param int $validity_days Number of days.
	 * @return string|null
	 */
	private function calculate_expiry( int $validity_days ): ?string {
		if ( $validity_days <= 0 ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', strtotime( '+' . $validity_days . ' days' ) );
	}
}

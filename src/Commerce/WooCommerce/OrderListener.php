<?php
/**
 * WooCommerce order listener.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Commerce\WooCommerce;

use StudioBookingManager\Access\AccessService;
use StudioBookingManager\Bookings\BookingService;
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
		add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_create_access_from_order' ) );
		add_filter( 'woocommerce_cod_process_payment_order_status', array( $this, 'set_offline_payment_order_status' ), 10, 2 );
		add_filter( 'woocommerce_bacs_process_payment_order_status', array( $this, 'set_offline_payment_order_status' ), 10, 2 );
	}

	/**
	 * Set offline payment orders to pending until payment is confirmed.
	 *
	 * @param string    $status Default WooCommerce order status.
	 * @param \WC_Order $order WooCommerce order.
	 * @return string
	 */
	public function set_offline_payment_order_status( string $status, $order = null ): string {
		return $order instanceof \WC_Order && $this->order_has_access_items( $order ) ? 'pending' : $status;
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

		if ( ! $this->order_has_access_items( $order ) ) {
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

			$access_data = $this->get_item_access_data( $item, $person_id, $order );

			if ( empty( $access_data ) ) {
				continue;
			}

			$access_ids = $this->get_order_item_access_ids( (int) $item_id );
			$quantity   = max( 1, absint( $item->get_quantity() ) );

			for ( $i = count( $access_ids ); $i < $quantity; ++$i ) {
				$access_id = ( new AccessService() )->save( $access_data );

				if ( $access_id > 0 ) {
					$access_ids[] = $access_id;
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `sbm_` is the documented public API prefix for Studio Booking Manager.
					do_action( 'sbm_access_created_from_order', $access_id, $order );
				}
			}

			$this->update_order_item_access_ids( (int) $item_id, $access_ids );
			$this->maybe_create_bookings_for_item( (int) $item_id, $item, $order, $person_id, $access_ids, $access_data );
		}
	}

	/**
	 * Determine whether an order contains Studio Booking access items.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return bool
	 */
	private function order_has_access_items( \WC_Order $order ): bool {
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof \WC_Order_Item_Product && $this->get_item_has_access_config( $item ) ) {
				return true;
			}
		}

		return false;
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
			$customer_id = absint( $order->get_customer_id() );
			$linked      = $customer_id > 0 ? $people->find_by_wp_user_id( $customer_id ) : null;

			if ( $customer_id > 0 && absint( $person->wp_user_id ) <= 0 && ! ( $linked instanceof \stdClass ) ) {
				$people->save(
					array(
						'id'           => absint( $person->id ),
						'wp_user_id'   => $customer_id,
						'first_name'   => (string) $person->first_name,
						'last_name'    => (string) $person->last_name,
						'display_name' => (string) $person->display_name,
						'email'        => (string) $person->email,
						'phone'        => (string) $person->phone,
						'status'       => (string) $person->status,
						'notes'        => (string) $person->notes,
					)
				);
			}

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
	 * Determine whether an order item has Studio Booking access configuration.
	 *
	 * @param \WC_Order_Item_Product $item Order item.
	 * @return bool
	 */
	private function get_item_has_access_config( \WC_Order_Item_Product $item ): bool {
		$config_id = $this->get_item_config_id( $item );

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
	 * Get access IDs previously created for an order item.
	 *
	 * @param int $item_id Order item ID.
	 * @return int[]
	 */
	private function get_order_item_access_ids( int $item_id ): array {
		$ids = wc_get_order_item_meta( $item_id, '_sbm_access_ids', true );

		if ( ! is_array( $ids ) ) {
			$ids = array();
		}

		$legacy_id = absint( wc_get_order_item_meta( $item_id, '_sbm_access_id', true ) );

		if ( $legacy_id > 0 ) {
			$ids[] = $legacy_id;
		}

		$ids = array_map( 'absint', $ids );
		$ids = array_filter( $ids );

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Store access IDs created for an order item.
	 *
	 * @param int   $item_id Order item ID.
	 * @param int[] $access_ids Access IDs.
	 */
	private function update_order_item_access_ids( int $item_id, array $access_ids ): void {
		$access_ids = array_values( array_unique( array_filter( array_map( 'absint', $access_ids ) ) ) );

		if ( empty( $access_ids ) ) {
			return;
		}

		wc_update_order_item_meta( $item_id, '_sbm_access_ids', $access_ids );
		wc_update_order_item_meta( $item_id, '_sbm_access_id', $access_ids[0] );
	}

	/**
	 * Create linked bookings for order items that collected a visit date.
	 *
	 * @param int                    $item_id Order item ID.
	 * @param \WC_Order_Item_Product $item Order item.
	 * @param \WC_Order              $order WooCommerce order.
	 * @param int                    $person_id Person ID.
	 * @param int[]                  $access_ids Access IDs.
	 * @param array<string,mixed>    $access_data Access data.
	 */
	private function maybe_create_bookings_for_item( int $item_id, \WC_Order_Item_Product $item, \WC_Order $order, int $person_id, array $access_ids, array $access_data ): void {
		$date = $this->get_item_booking_date( $item );

		if ( '' === $date || empty( $access_ids ) ) {
			return;
		}

		$config_id   = $this->get_item_config_id( $item );
		$location_id = isset( $access_data['location_id'] ) ? absint( $access_data['location_id'] ) : 0;

		if ( $config_id <= 0 || $location_id <= 0 || 'yes' !== get_post_meta( $config_id, '_sbm_requires_booking_date', true ) ) {
			return;
		}

		$quantity    = max( 1, absint( $item->get_quantity() ) );
		$booking_ids = $this->get_order_item_booking_ids( $item_id );
		$window      = $this->get_item_booking_window( $config_id, $date );

		for ( $i = count( $booking_ids ); $i < $quantity && isset( $access_ids[ $i ] ); ++$i ) {
			$booking_id = ( new BookingService() )->save(
				array(
					'person_id'            => $person_id,
					'location_id'          => $location_id,
					'access_id'            => absint( $access_ids[ $i ] ),
					'status'               => 'confirmed',
					'starts_at'            => $window['starts_at'],
					'ends_at'              => $window['ends_at'],
					'guest_count'          => 0,
					'guest_names'          => '',
					'notes'                => sprintf(
						/* translators: %d: WooCommerce order ID. */
						__( 'Created from WooCommerce order #%d for the selected visit date.', 'studio-booking-manager' ),
						$order->get_id()
					),
					'skip_conflict_check' => true,
				)
			);

			if ( $booking_id > 0 ) {
				$booking_ids[] = $booking_id;
			}
		}

		$this->update_order_item_booking_ids( $item_id, $booking_ids );
	}

	/**
	 * Get booking IDs previously created for an order item.
	 *
	 * @param int $item_id Order item ID.
	 * @return int[]
	 */
	private function get_order_item_booking_ids( int $item_id ): array {
		$ids = wc_get_order_item_meta( $item_id, '_sbm_booking_ids', true );

		if ( ! is_array( $ids ) ) {
			$ids = array();
		}

		$legacy_id = absint( wc_get_order_item_meta( $item_id, '_sbm_booking_id', true ) );

		if ( $legacy_id > 0 ) {
			$ids[] = $legacy_id;
		}

		$ids = array_map( 'absint', $ids );
		$ids = array_filter( $ids );

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Store booking IDs created for an order item.
	 *
	 * @param int   $item_id Order item ID.
	 * @param int[] $booking_ids Booking IDs.
	 */
	private function update_order_item_booking_ids( int $item_id, array $booking_ids ): void {
		$booking_ids = array_values( array_unique( array_filter( array_map( 'absint', $booking_ids ) ) ) );

		if ( empty( $booking_ids ) ) {
			return;
		}

		wc_update_order_item_meta( $item_id, '_sbm_booking_ids', $booking_ids );
		wc_update_order_item_meta( $item_id, '_sbm_booking_id', $booking_ids[0] );
	}

	/**
	 * Get selected booking date from an order item.
	 *
	 * @param \WC_Order_Item_Product $item Order item.
	 */
	private function get_item_booking_date( \WC_Order_Item_Product $item ): string {
		$date = sanitize_text_field( (string) $item->get_meta( '_sbm_booking_date', true ) );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return '';
		}

		return $date;
	}

	/**
	 * Build booking start and end datetimes for a selected visit date.
	 *
	 * @param int    $config_id Product or variation ID.
	 * @param string $date Visit date.
	 * @return array{starts_at:string,ends_at:string}
	 */
	private function get_item_booking_window( int $config_id, string $date ): array {
		$start_time = sanitize_text_field( (string) get_post_meta( $config_id, '_sbm_booking_start_time', true ) );
		$end_time   = sanitize_text_field( (string) get_post_meta( $config_id, '_sbm_booking_end_time', true ) );

		if ( ! preg_match( '/^\d{2}:\d{2}$/', $start_time ) ) {
			$start_time = '09:00';
		}

		$starts   = $date . ' ' . $start_time . ':00';
		$start_ts = strtotime( $starts );
		$ends     = '';

		if ( preg_match( '/^\d{2}:\d{2}$/', $end_time ) ) {
			$end_candidate    = $date . ' ' . $end_time . ':00';
			$end_candidate_ts = strtotime( $end_candidate );

			if ( false !== $start_ts && false !== $end_candidate_ts && $end_candidate_ts > $start_ts ) {
				$ends = $end_candidate;
			}
		}

		if ( '' === $ends ) {
			$duration = $this->get_configured_booking_duration_minutes( $config_id );
			$end_ts   = false === $start_ts ? false : strtotime( '+' . $duration . ' minutes', $start_ts );
			$ends     = false === $end_ts ? $starts : gmdate( 'Y-m-d H:i:s', $end_ts );
		}

		return array(
			'starts_at' => $starts,
			'ends_at'   => $ends,
		);
	}

	/**
	 * Get booking duration from product override, selected Pass, or default.
	 *
	 * @param int $config_id Product or variation ID.
	 */
	private function get_configured_booking_duration_minutes( int $config_id ): int {
		$product_duration = absint( get_post_meta( $config_id, '_sbm_booking_duration_minutes', true ) );

		if ( $product_duration > 0 ) {
			return max( 15, $product_duration );
		}

		$pass_type_id = absint( get_post_meta( $config_id, '_sbm_pass_type_id', true ) );
		$pass         = $pass_type_id > 0 ? ( new PassTypeService() )->find( $pass_type_id ) : null;

		if ( $pass instanceof PassType && null !== $pass->booking_duration_minutes && $pass->booking_duration_minutes > 0 ) {
			return max( 15, absint( $pass->booking_duration_minutes ) );
		}

		return 480;
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

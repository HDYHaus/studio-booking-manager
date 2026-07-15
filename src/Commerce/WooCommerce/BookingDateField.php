<?php
/**
 * WooCommerce booking date selection.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Commerce\WooCommerce;

use StudioBookingManager\Bookings\BookingRepository;
use StudioBookingManager\Database\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * Adds customer-selected visit dates to eligible WooCommerce products.
 */
final class BookingDateField {
	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_field' ) );
		add_action( 'wp_ajax_sbm_booking_date_context', array( $this, 'ajax_booking_date_context' ) );
		add_action( 'wp_ajax_nopriv_sbm_booking_date_context', array( $this, 'ajax_booking_date_context' ) );
		add_filter( 'woocommerce_available_variation', array( $this, 'add_variation_data' ), 10, 3 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 4 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'validate_cart_capacity' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_data' ), 10, 4 );
	}

	/**
	 * Enqueue storefront styles on WooCommerce product pages.
	 */
	public function enqueue_styles(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		wp_enqueue_style(
			'studio-booking-manager-frontend',
			SBM_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			SBM_VERSION
		);
	}

	/**
	 * Render visit date field on eligible product pages.
	 */
	public function render_field(): void {
		global $product;

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$is_required = $this->requires_booking_date_for_product( $product );
		$is_variable = $product instanceof \WC_Product_Variable;
		$config_id   = $product->get_id();

		if ( ! $is_required && ! $is_variable ) {
			return;
		}

		?>
		<div class="sbm-booking-date-field"<?php if ( ! $is_required ) : ?> style="display:none;"<?php endif; ?>>
			<label for="sbm-booking-date"><?php echo esc_html__( 'Visit date', 'studio-booking-manager' ); ?></label>
			<input
				type="date"
				id="sbm-booking-date"
				name="sbm_booking_date"
				min="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"
				<?php if ( $is_required ) : ?>
					required
				<?php endif; ?>
			>
			<div
				class="sbm-booking-date-context"
				data-endpoint="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'sbm_booking_date_context' ) ); ?>"
				data-config-id="<?php echo esc_attr( (string) absint( $config_id ) ); ?>"
				data-location-id="<?php echo esc_attr( (string) $this->get_configured_location_id( $config_id ) ); ?>"
				hidden
			></div>
		</div>
		<script>
			(function($) {
				var field = $('.sbm-booking-date-field');
				var input = $('#sbm-booking-date');
				var context = $('.sbm-booking-date-context');
				var request = null;

				function escapeHtml(value) {
					return String(value).replace(/[&<>"']/g, function(char) {
						return {
							'&': '&amp;',
							'<': '&lt;',
							'>': '&gt;',
							'"': '&quot;',
							"'": '&#039;'
						}[char];
					});
				}

				function renderContext(items, isBlocked) {
					var html = '<strong><?php echo esc_js( __( 'What\'s happening on this day', 'studio-booking-manager' ) ); ?></strong>';

					if (! items.length) {
						html += '<p><?php echo esc_js( __( 'Open studio day.', 'studio-booking-manager' ) ); ?></p>';
					} else {
						html += '<ul>';
						items.forEach(function(item) {
							html += '<li class="sbm-booking-context-' + escapeHtml(item.visibility) + '"><span>' + escapeHtml(item.time) + '</span> ' + escapeHtml(item.label) + '</li>';
						});
						html += '</ul>';
					}

					if (isBlocked) {
						html += '<p class="sbm-booking-context-warning"><?php echo esc_js( __( 'This date is not available for this day pass.', 'studio-booking-manager' ) ); ?></p>';
					}

					context.html(html).prop('hidden', false);
				}

				function loadContext() {
					var date = input.val();
					var required = input.prop('required');

					if (! required || ! date) {
						context.empty().prop('hidden', true);
						return;
					}

					if (request && request.abort) {
						request.abort();
					}

					context.html('<p><?php echo esc_js( __( 'Checking the studio schedule...', 'studio-booking-manager' ) ); ?></p>').prop('hidden', false);

					request = $.post(context.data('endpoint'), {
						action: 'sbm_booking_date_context',
						nonce: context.data('nonce'),
						date: date,
						location_id: context.data('location-id') || 0
					}).done(function(response) {
						if (! response || ! response.success) {
							context.empty().prop('hidden', true);
							return;
						}

						renderContext(response.data.items || [], !! response.data.blocked);
					}).fail(function() {
						context.empty().prop('hidden', true);
					});
				}

				input.on('change', loadContext);

				$('form.variations_form').on('show_variation', function(event, variation) {
					var required = variation && variation.sbm_requires_booking_date;
					field.toggle(!! required);
					input.prop('required', !! required);
					context.data('location-id', variation && variation.sbm_location_id ? variation.sbm_location_id : 0);
					if (! required) {
						input.val('');
						context.empty().prop('hidden', true);
					} else {
						loadContext();
					}
				}).on('hide_variation', function() {
					field.hide();
					input.prop('required', false).val('');
					context.empty().prop('hidden', true);
				});
			})(jQuery);
		</script>
		<?php
	}

	/**
	 * Add Studio Booking data to variation JSON.
	 *
	 * @param array<string,mixed> $data Variation data.
	 * @param \WC_Product         $product Parent product.
	 * @param \WC_Product         $variation Variation product.
	 * @return array<string,mixed>
	 */
	public function add_variation_data( array $data, \WC_Product $product, \WC_Product $variation ): array {
		$config_id                         = $this->get_config_id( $product->get_id(), $variation->get_id() );
		$data['sbm_requires_booking_date'] = $this->requires_booking_date_for_config_id( $config_id );
		$data['sbm_location_id']           = $this->get_configured_location_id( $config_id );

		return $data;
	}

	/**
	 * Return public-safe schedule context for the selected visit date.
	 */
	public function ajax_booking_date_context(): void {
		check_ajax_referer( 'sbm_booking_date_context', 'nonce' );

		$date        = isset( $_POST['date'] ) ? $this->sanitize_booking_date( sanitize_text_field( wp_unslash( $_POST['date'] ) ) ) : '';
		$location_id = isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0;

		if ( '' === $date ) {
			wp_send_json_error( array( 'message' => __( 'Invalid date.', 'studio-booking-manager' ) ) );
		}

		$items   = array();
		$blocked = false;

		foreach ( ( new BookingRepository() )->public_schedule_for_date( $date, $location_id ) as $booking ) {
			$visibility = isset( $booking->visibility ) ? sanitize_key( (string) $booking->visibility ) : 'private';
			$label      = $this->public_booking_label( $booking );

			if ( 'blocked' === $visibility ) {
				$blocked = true;
			}

			$items[] = array(
				'visibility' => $visibility,
				'label'      => $label,
				'time'       => $this->format_time_range( (string) $booking->starts_at, (string) $booking->ends_at ),
			);
		}

		wp_send_json_success(
			array(
				'blocked' => $blocked,
				'items'   => $items,
			)
		);
	}

	/**
	 * Validate cart submissions.
	 *
	 * @param bool $passed Validation result.
	 * @param int  $product_id Product ID.
	 * @param int  $quantity Quantity.
	 * @param int  $variation_id Variation ID.
	 * @return bool
	 */
	public function validate_add_to_cart( bool $passed, int $product_id, int $quantity = 1, int $variation_id = 0 ): bool {
		$config_id = $this->get_config_id( $product_id, $variation_id );

		if ( ! $this->requires_booking_date_for_config_id( $config_id ) ) {
			return $passed;
		}

		$date = $this->posted_booking_date();

		if ( '' === $date ) {
			wc_add_notice( __( 'Choose a visit date before adding this item to your cart.', 'studio-booking-manager' ), 'error' );
			return false;
		}

		if ( $this->date_has_unavailable_booking( $date, $this->get_configured_location_id( $config_id ) ) ) {
			wc_add_notice( __( 'The selected visit date is unavailable for day passes.', 'studio-booking-manager' ), 'error' );
			return false;
		}

		if ( ! $this->has_capacity_for_request( $config_id, $product_id, $variation_id, $date, max( 1, absint( $quantity ) ) ) ) {
			wc_add_notice( $this->capacity_notice( $config_id, $date ), 'error' );
			return false;
		}

		return $passed;
	}

	/**
	 * Store selected date on cart item.
	 *
	 * @param array<string,mixed> $cart_item_data Cart item data.
	 * @param int                 $product_id Product ID.
	 * @param int                 $variation_id Variation ID.
	 * @return array<string,mixed>
	 */
	public function add_cart_item_data( array $cart_item_data, int $product_id, int $variation_id ): array {
		$config_id = $this->get_config_id( $product_id, $variation_id );
		$date      = $this->posted_booking_date();

		if ( '' !== $date && $this->requires_booking_date_for_config_id( $config_id ) ) {
			$cart_item_data['sbm_booking_date'] = $date;
			$cart_item_data['sbm_booking_date_key'] = md5( $date . '|' . $config_id . '|' . wp_rand() );
		}

		return $cart_item_data;
	}

	/**
	 * Display selected date in cart and checkout.
	 *
	 * @param array<int,array<string,string>> $item_data Existing display data.
	 * @param array<string,mixed>             $cart_item Cart item.
	 * @return array<int,array<string,string>>
	 */
	public function display_cart_item_data( array $item_data, array $cart_item ): array {
		if ( empty( $cart_item['sbm_booking_date'] ) ) {
			return $item_data;
		}

		$item_data[] = array(
			'key'   => __( 'Visit date', 'studio-booking-manager' ),
			'value' => $this->format_date( (string) $cart_item['sbm_booking_date'] ),
		);

		return $item_data;
	}

	/**
	 * Validate dated booking capacity for existing cart contents.
	 */
	public function validate_cart_capacity(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$requested = array();

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['sbm_booking_date'] ) ) {
				continue;
			}

			$product_id   = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
			$variation_id = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;
			$config_id    = $this->get_config_id( $product_id, $variation_id );
			$date         = $this->sanitize_booking_date( (string) $cart_item['sbm_booking_date'] );

			if ( $config_id <= 0 || '' === $date || ! $this->requires_booking_date_for_config_id( $config_id ) ) {
				continue;
			}

			if ( $this->date_has_unavailable_booking( $date, $this->get_configured_location_id( $config_id ) ) ) {
				wc_add_notice( __( 'A selected visit date is no longer available for day passes.', 'studio-booking-manager' ), 'error' );
				continue;
			}

			$key = $config_id . '|' . $date;
			if ( ! isset( $requested[ $key ] ) ) {
				$requested[ $key ] = array(
					'config_id'    => $config_id,
					'product_id'   => $product_id,
					'variation_id' => $variation_id,
					'date'         => $date,
					'quantity'     => 0,
				);
			}

			$requested[ $key ]['quantity'] += isset( $cart_item['quantity'] ) ? max( 1, absint( $cart_item['quantity'] ) ) : 1;
		}

		foreach ( $requested as $request ) {
			if ( ! $this->has_capacity_for_request( (int) $request['config_id'], (int) $request['product_id'], (int) $request['variation_id'], (string) $request['date'], (int) $request['quantity'], false ) ) {
				wc_add_notice( $this->capacity_notice( (int) $request['config_id'], (string) $request['date'] ), 'error' );
			}
		}
	}

	/**
	 * Persist selected date to the order line item.
	 *
	 * @param \WC_Order_Item_Product $item Order item.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array<string,mixed>    $values Cart values.
	 * @param \WC_Order              $order Order.
	 */
	public function add_order_item_data( \WC_Order_Item_Product $item, string $cart_item_key, array $values, \WC_Order $order ): void {
		unset( $cart_item_key, $order );

		if ( empty( $values['sbm_booking_date'] ) ) {
			return;
		}

		$date = $this->sanitize_booking_date( (string) $values['sbm_booking_date'] );

		if ( '' === $date ) {
			return;
		}

		$item->add_meta_data( '_sbm_booking_date', $date, true );
		$item->add_meta_data( __( 'Visit date', 'studio-booking-manager' ), $this->format_date( $date ), true );
	}

	/**
	 * Determine whether a product requires a booking date.
	 *
	 * @param \WC_Product $product Product.
	 */
	private function requires_booking_date_for_product( \WC_Product $product ): bool {
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

	/**
	 * Determine whether requested quantity fits the product's daily capacity.
	 *
	 * @param int    $config_id            Product or variation config ID.
	 * @param int    $product_id           Product ID.
	 * @param int    $variation_id         Variation ID.
	 * @param string $date                 Visit date.
	 * @param int    $quantity             Requested quantity.
	 * @param bool   $include_current_cart Whether to add matching existing cart quantity.
	 */
	private function has_capacity_for_request( int $config_id, int $product_id, int $variation_id, string $date, int $quantity, bool $include_current_cart = true ): bool {
		$capacity = $this->get_daily_capacity( $config_id );

		if ( $capacity <= 0 ) {
			return true;
		}

		$requested = max( 1, $quantity );

		if ( $include_current_cart ) {
			$requested += $this->count_matching_cart_quantity( $config_id, $date );
		}

		return ( $this->count_existing_bookings( $config_id, $product_id, $variation_id, $date ) + $requested ) <= $capacity;
	}

	/**
	 * Get product-level daily booking capacity.
	 *
	 * @param int $config_id Product or variation config ID.
	 */
	private function get_daily_capacity( int $config_id ): int {
		return absint( get_post_meta( $config_id, '_sbm_booking_daily_capacity', true ) );
	}

	/**
	 * Determine whether a date is blocked from day-pass purchases.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @param int    $location_id Optional location ID.
	 */
	private function date_has_unavailable_booking( string $date, int $location_id = 0 ): bool {
		foreach ( ( new BookingRepository() )->public_schedule_for_date( $date, $location_id ) as $booking ) {
			if ( isset( $booking->visibility ) && 'blocked' === (string) $booking->visibility ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Count matching cart quantity already selected for the same dated config.
	 *
	 * @param int    $config_id Product or variation config ID.
	 * @param string $date      Visit date.
	 */
	private function count_matching_cart_quantity( int $config_id, string $date ): int {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0;
		}

		$total = 0;

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['sbm_booking_date'] ) || $date !== (string) $cart_item['sbm_booking_date'] ) {
				continue;
			}

			$cart_product_id   = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
			$cart_variation_id = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;

			if ( $config_id !== $this->get_config_id( $cart_product_id, $cart_variation_id ) ) {
				continue;
			}

			$total += isset( $cart_item['quantity'] ) ? max( 1, absint( $cart_item['quantity'] ) ) : 1;
		}

		return $total;
	}

	/**
	 * Count existing active bookings for the selected product config and date.
	 *
	 * @param int    $config_id    Product or variation config ID.
	 * @param int    $product_id   Product ID.
	 * @param int    $variation_id Variation ID.
	 * @param string $date         Visit date.
	 */
	private function count_existing_bookings( int $config_id, int $product_id, int $variation_id, string $date ): int {
		global $wpdb;

		$bookings_table = Tables::get( 'bookings' );
		$access_table   = Tables::get( 'access' );

		if ( '' === $bookings_table || '' === $access_table ) {
			return 0;
		}

		$match_variation = $variation_id > 0 && $config_id === $variation_id;

		if ( $match_variation ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from the trusted Tables registry; values are prepared.
			$query = $wpdb->prepare(
				"SELECT COUNT(bookings.id)
				FROM `{$bookings_table}` bookings
				INNER JOIN `{$access_table}` access ON access.id = bookings.access_id
				WHERE bookings.status IN ( %s, %s )
					AND DATE(bookings.starts_at) = %s
					AND access.variation_id = %d",
				'pending',
				'confirmed',
				$date,
				$config_id
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from the trusted Tables registry; values are prepared.
			$query = $wpdb->prepare(
				"SELECT COUNT(bookings.id)
				FROM `{$bookings_table}` bookings
				INNER JOIN `{$access_table}` access ON access.id = bookings.access_id
				WHERE bookings.status IN ( %s, %s )
					AND DATE(bookings.starts_at) = %s
					AND access.product_id = %d",
				'pending',
				'confirmed',
				$date,
				$product_id > 0 ? $product_id : $config_id
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom operational table capacity check using prepared values.
		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Build daily capacity notice.
	 *
	 * @param int    $config_id Product or variation config ID.
	 * @param string $date      Visit date.
	 */
	private function capacity_notice( int $config_id, string $date ): string {
		$capacity = $this->get_daily_capacity( $config_id );

		return sprintf(
			/* translators: 1: formatted date, 2: daily product capacity. */
			__( 'The selected visit date %1$s has reached the daily capacity of %2$d.', 'studio-booking-manager' ),
			$this->format_date( $date ),
			$capacity
		);
	}

	/**
	 * Get the config ID for a cart request.
	 *
	 * @param int $product_id Product ID.
	 * @param int $variation_id Variation ID.
	 */
	private function get_config_id( int $product_id, int $variation_id ): int {
		return $variation_id > 0 && 'yes' === get_post_meta( $variation_id, '_sbm_enabled', true ) ? $variation_id : $product_id;
	}

	/**
	 * Get configured product location, or 0 for all locations.
	 *
	 * @param int $config_id Product or variation config ID.
	 */
	private function get_configured_location_id( int $config_id ): int {
		return absint( get_post_meta( $config_id, '_sbm_location_id', true ) );
	}

	/**
	 * Get sanitized posted booking date.
	 */
	private function posted_booking_date(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce validates the add-to-cart request.
		$date = isset( $_POST['sbm_booking_date'] ) ? sanitize_text_field( wp_unslash( $_POST['sbm_booking_date'] ) ) : '';

		return $this->sanitize_booking_date( $date );
	}

	/**
	 * Sanitize and validate date.
	 *
	 * @param string $date Raw date.
	 */
	private function sanitize_booking_date( string $date ): string {
		$date = trim( $date );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return '';
		}

		$timestamp = strtotime( $date . ' 00:00:00' );

		if ( false === $timestamp || $date < current_time( 'Y-m-d' ) ) {
			return '';
		}

		return $date;
	}

	/**
	 * Format a date for display.
	 *
	 * @param string $date Date.
	 */
	private function format_date( string $date ): string {
		$timestamp = strtotime( $date . ' 00:00:00' );

		if ( false === $timestamp ) {
			return $date;
		}

		return date_i18n( get_option( 'date_format' ), $timestamp );
	}

	/**
	 * Format a public-safe booking label.
	 *
	 * @param object $booking Booking row.
	 */
	private function public_booking_label( object $booking ): string {
		$visibility = isset( $booking->visibility ) ? sanitize_key( (string) $booking->visibility ) : 'private';

		if ( 'public' === $visibility ) {
			$title = isset( $booking->public_title ) ? trim( (string) $booking->public_title ) : '';

			return '' !== $title ? $title : __( 'Studio activity', 'studio-booking-manager' );
		}

		if ( 'blocked' === $visibility ) {
			return __( 'Studio unavailable', 'studio-booking-manager' );
		}

		return __( 'Private booking', 'studio-booking-manager' );
	}

	/**
	 * Format a public time range.
	 *
	 * @param string $starts_at Start datetime.
	 * @param string $ends_at End datetime.
	 */
	private function format_time_range( string $starts_at, string $ends_at ): string {
		$start_ts = strtotime( $starts_at );
		$end_ts   = strtotime( $ends_at );

		if ( false === $start_ts || false === $end_ts ) {
			return '';
		}

		return date_i18n( get_option( 'time_format' ), $start_ts ) . ' - ' . date_i18n( get_option( 'time_format' ), $end_ts );
	}
}

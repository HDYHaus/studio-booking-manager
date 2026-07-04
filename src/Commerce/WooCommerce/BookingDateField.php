<?php
/**
 * WooCommerce booking date selection.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Commerce\WooCommerce;

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
		add_filter( 'woocommerce_available_variation', array( $this, 'add_variation_data' ), 10, 3 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 4 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
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
		</div>
		<?php if ( $is_variable ) : ?>
			<script>
				(function($) {
					var field = $('.sbm-booking-date-field');
					var input = $('#sbm-booking-date');
					$('form.variations_form').on('show_variation', function(event, variation) {
						var required = variation && variation.sbm_requires_booking_date;
						field.toggle(!! required);
						input.prop('required', !! required);
						if (! required) {
							input.val('');
						}
					}).on('hide_variation', function() {
						field.hide();
						input.prop('required', false).val('');
					});
				})(jQuery);
			</script>
		<?php endif; ?>
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

		return $data;
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
		unset( $quantity );

		$config_id = $this->get_config_id( $product_id, $variation_id );

		if ( ! $this->requires_booking_date_for_config_id( $config_id ) ) {
			return $passed;
		}

		$date = $this->posted_booking_date();

		if ( '' === $date ) {
			wc_add_notice( __( 'Choose a visit date before adding this item to your cart.', 'studio-booking-manager' ), 'error' );
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
	 * Get the config ID for a cart request.
	 *
	 * @param int $product_id Product ID.
	 * @param int $variation_id Variation ID.
	 */
	private function get_config_id( int $product_id, int $variation_id ): int {
		return $variation_id > 0 && 'yes' === get_post_meta( $variation_id, '_sbm_enabled', true ) ? $variation_id : $product_id;
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
}

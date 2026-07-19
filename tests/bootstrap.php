<?php
/**
 * Test bootstrap for isolated unit tests.
 *
 * @package StudioBookingManager
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'SBM_PLUGIN_URL' ) ) {
	define( 'SBM_PLUGIN_URL', 'https://example.test/wp-content/plugins/studio-booking-manager/' );
}

if ( ! defined( 'SBM_VERSION' ) ) {
	define( 'SBM_VERSION', 'test' );
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Return an untranslated string in isolated tests.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Minimal sanitize_key replacement for isolated tests.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * Minimal wp_parse_args replacement for isolated tests.
	 *
	 * @param mixed $args     Provided arguments.
	 * @param mixed $defaults Default arguments.
	 * @return array<string, mixed>
	 */
	function wp_parse_args( $args, $defaults = array() ) {
		$parsed_args = is_array( $args ) ? $args : array();
		$defaults    = is_array( $defaults ) ? $defaults : array();

		return array_merge( $defaults, $parsed_args );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Minimal absint replacement for isolated tests.
	 *
	 * @param mixed $maybeint Value.
	 * @return int
	 */
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	/**
	 * Fixed test clock.
	 *
	 * @param string $type Time type.
	 * @return int|string
	 */
	function current_time( $type ) {
		$timestamp = strtotime( '2026-07-03 12:00:00 UTC' );

		return 'timestamp' === $type ? $timestamp : gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Minimal get_option replacement for isolated tests.
	 *
	 * @param string $option  Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function get_option( $option, $default = false ) {
		return $GLOBALS['sbm_test_options'][ $option ] ?? $default;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * Minimal get_post_meta replacement for isolated tests.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Whether to return a single value.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key, $single = false ) {
		unset( $single );

		return $GLOBALS['sbm_test_post_meta'][ (int) $post_id ][ (string) $key ] ?? '';
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	/**
	 * Minimal update_post_meta replacement for isolated tests.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Meta value.
	 * @return bool
	 */
	function update_post_meta( $post_id, $key, $value ) {
		$GLOBALS['sbm_test_post_meta'][ (int) $post_id ][ (string) $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * Minimal wp_verify_nonce replacement for isolated tests.
	 *
	 * @param string $nonce  Nonce value.
	 * @param string $action Nonce action.
	 * @return bool
	 */
	function wp_verify_nonce( $nonce, $action ) {
		return 'valid' === $nonce && 'sbm_save_product_panel' === $action;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Minimal sanitize_text_field replacement for isolated tests.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Minimal wp_unslash replacement for isolated tests.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'wp_rand' ) ) {
	/**
	 * Deterministic wp_rand replacement for isolated tests.
	 *
	 * @return int
	 */
	function wp_rand() {
		return 1234;
	}
}

if ( ! function_exists( 'date_i18n' ) ) {
	/**
	 * Minimal date_i18n replacement for isolated tests.
	 *
	 * @param string $format    Date format.
	 * @param int    $timestamp Timestamp.
	 * @return string
	 */
	function date_i18n( $format, $timestamp ) {
		return gmdate( $format, (int) $timestamp );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Minimal esc_html replacement for isolated tests.
	 *
	 * @param mixed $text Text.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Minimal esc_attr replacement for isolated tests.
	 *
	 * @param mixed $text Text.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Minimal esc_url replacement for isolated tests.
	 *
	 * @param mixed $url URL.
	 * @return string
	 */
	function esc_url( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Minimal esc_html__ replacement for isolated tests.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html__( $text ) {
		return esc_html( $text );
	}
}

if ( ! function_exists( 'wc_add_notice' ) ) {
	/**
	 * Capture WooCommerce notices in isolated tests.
	 *
	 * @param string $message Notice message.
	 * @param string $type    Notice type.
	 */
	function wc_add_notice( $message, $type = 'success' ) {
		$GLOBALS['sbm_test_wc_notices'][] = array(
			'message' => (string) $message,
			'type'    => (string) $type,
		);
	}
}

if ( ! function_exists( 'wc_get_base_location' ) ) {
	/**
	 * Minimal WooCommerce base location.
	 *
	 * @return array{country:string,state:string}
	 */
	function wc_get_base_location() {
		return $GLOBALS['sbm_test_wc_base_location'] ?? array(
			'country' => 'AL',
			'state'   => 'DR',
		);
	}
}

if ( ! function_exists( 'is_checkout' ) ) {
	/**
	 * Minimal checkout conditional.
	 *
	 * @return bool
	 */
	function is_checkout() {
		return ! empty( $GLOBALS['sbm_test_is_checkout'] );
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	/**
	 * Capture enqueued styles.
	 *
	 * @param string $handle Style handle.
	 */
	function wp_enqueue_style( $handle ) {
		$GLOBALS['sbm_test_enqueued_styles'][] = (string) $handle;
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	/**
	 * Capture enqueued scripts.
	 *
	 * @param string $handle Script handle.
	 */
	function wp_enqueue_script( $handle ) {
		$GLOBALS['sbm_test_enqueued_scripts'][] = (string) $handle;
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	/**
	 * Capture localized script data.
	 *
	 * @param string               $handle Script handle.
	 * @param string               $object Object name.
	 * @param array<string, mixed> $data   Data.
	 */
	function wp_localize_script( $handle, $object, $data ) {
		$GLOBALS['sbm_test_localized_scripts'][ (string) $handle ][ (string) $object ] = $data;
	}
}

if ( ! class_exists( 'wpdb' ) ) {
	/**
	 * Minimal wpdb replacement for isolated tests.
	 */
	class wpdb {
		/**
		 * Table prefix.
		 *
		 * @var string
		 */
		public string $prefix = 'wp_';

		/**
		 * Results returned by get_results().
		 *
		 * @var array<int, object>
		 */
		public array $results = array();

		/**
		 * Value returned by get_var().
		 *
		 * @var mixed
		 */
		public $var = 0;

		/**
		 * Store query and args for inspection.
		 *
		 * @param string $query Query.
		 * @param mixed  ...$args Query args.
		 * @return string
		 */
		public function prepare( $query, ...$args ) {
			$GLOBALS['sbm_test_prepared_queries'][] = array( $query, $args );

			return (string) $query;
		}

		/**
		 * Return configured results.
		 *
		 * @return array<int, object>
		 */
		public function get_results() {
			return $this->results;
		}

		/**
		 * Return configured scalar.
		 *
		 * @return mixed
		 */
		public function get_var() {
			return $this->var;
		}
	}
}

if ( ! class_exists( 'WC_Product' ) ) {
	/**
	 * Minimal WooCommerce product replacement.
	 */
	class WC_Product {
		/**
		 * Product ID.
		 *
		 * @var int
		 */
		private int $id;

		/**
		 * Product name.
		 *
		 * @var string
		 */
		private string $name;

		/**
		 * Product permalink.
		 *
		 * @var string
		 */
		private string $permalink;

		/**
		 * Constructor.
		 *
		 * @param int    $id Product ID.
		 * @param string $name Product name.
		 * @param string $permalink Product permalink.
		 */
		public function __construct( int $id = 0, string $name = 'Test product', string $permalink = 'https://example.test/product' ) {
			$this->id        = $id;
			$this->name      = $name;
			$this->permalink = $permalink;
		}

		/**
		 * Get product ID.
		 */
		public function get_id(): int {
			return $this->id;
		}

		/**
		 * Get product permalink.
		 */
		public function get_permalink(): string {
			return $this->permalink;
		}

		/**
		 * Get product name.
		 */
		public function get_name(): string {
			return $this->name;
		}
	}
}

if ( ! class_exists( 'WC_Product_Variable' ) ) {
	/**
	 * Minimal WooCommerce variable product replacement.
	 */
	class WC_Product_Variable extends WC_Product {
		/**
		 * Child variation IDs.
		 *
		 * @var array<int, int>
		 */
		private array $children;

		/**
		 * Constructor.
		 *
		 * @param int             $id Product ID.
		 * @param array<int, int> $children Child IDs.
		 */
		public function __construct( int $id = 0, array $children = array() ) {
			parent::__construct( $id, 'Variable product', 'https://example.test/variable' );
			$this->children = $children;
		}

		/**
		 * Get child IDs.
		 *
		 * @return array<int, int>
		 */
		public function get_children(): array {
			return $this->children;
		}
	}
}

if ( ! class_exists( 'WC_Order_Item_Product' ) ) {
	/**
	 * Minimal WooCommerce order item replacement.
	 */
	class WC_Order_Item_Product {
		/**
		 * Product ID.
		 *
		 * @var int
		 */
		private int $product_id;

		/**
		 * Variation ID.
		 *
		 * @var int
		 */
		private int $variation_id;

		/**
		 * Meta data.
		 *
		 * @var array<string, mixed>
		 */
		public array $meta = array();

		/**
		 * Constructor.
		 *
		 * @param int $product_id Product ID.
		 * @param int $variation_id Variation ID.
		 */
		public function __construct( int $product_id = 0, int $variation_id = 0 ) {
			$this->product_id   = $product_id;
			$this->variation_id = $variation_id;
		}

		/**
		 * Get product ID.
		 */
		public function get_product_id(): int {
			return $this->product_id;
		}

		/**
		 * Get variation ID.
		 */
		public function get_variation_id(): int {
			return $this->variation_id;
		}

		/**
		 * Add order item meta.
		 *
		 * @param string $key Meta key.
		 * @param mixed  $value Meta value.
		 */
		public function add_meta_data( string $key, $value ): void {
			$this->meta[ $key ] = $value;
		}
	}
}

if ( ! class_exists( 'WC_Order' ) ) {
	/**
	 * Minimal WooCommerce order replacement.
	 */
	class WC_Order {
		/**
		 * Billing data.
		 *
		 * @var array<string, string>
		 */
		private array $billing = array();

		/**
		 * Items.
		 *
		 * @var array<int, WC_Order_Item_Product>
		 */
		private array $items = array();

		/**
		 * Constructor.
		 *
		 * @param array<int, WC_Order_Item_Product> $items Items.
		 */
		public function __construct( array $items = array() ) {
			$this->items = $items;
		}

		public function get_items(): array {
			return $this->items;
		}

		public function get_formatted_billing_full_name(): string {
			return trim( ( $this->billing['first_name'] ?? '' ) . ' ' . ( $this->billing['last_name'] ?? '' ) );
		}

		public function get_billing_address_1(): string {
			return $this->billing['address_1'] ?? '';
		}

		public function set_billing_address_1( string $value ): void {
			$this->billing['address_1'] = $value;
		}

		public function get_billing_city(): string {
			return $this->billing['city'] ?? '';
		}

		public function set_billing_city( string $value ): void {
			$this->billing['city'] = $value;
		}

		public function get_billing_postcode(): string {
			return $this->billing['postcode'] ?? '';
		}

		public function set_billing_postcode( string $value ): void {
			$this->billing['postcode'] = $value;
		}

		public function get_billing_country(): string {
			return $this->billing['country'] ?? '';
		}

		public function set_billing_country( string $value ): void {
			$this->billing['country'] = $value;
		}

		public function get_billing_state(): string {
			return $this->billing['state'] ?? '';
		}

		public function set_billing_state( string $value ): void {
			$this->billing['state'] = $value;
		}

		public function get_billing_phone(): string {
			return $this->billing['phone'] ?? '';
		}

		public function set_billing_first_name( string $value ): void {
			$this->billing['first_name'] = $value;
		}

		public function set_billing_last_name( string $value ): void {
			$this->billing['last_name'] = $value;
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * Minimal REST request replacement.
	 */
	class WP_REST_Request {}
}

if ( ! class_exists( 'SBM_Test_Cart' ) ) {
	/**
	 * Minimal cart replacement.
	 */
	class SBM_Test_Cart {
		/**
		 * Cart items.
		 *
		 * @var array<int, array<string, mixed>>
		 */
		private array $items;

		/**
		 * Constructor.
		 *
		 * @param array<int, array<string, mixed>> $items Cart items.
		 */
		public function __construct( array $items = array() ) {
			$this->items = $items;
		}

		/**
		 * Get cart items.
		 *
		 * @return array<int, array<string, mixed>>
		 */
		public function get_cart(): array {
			return $this->items;
		}
	}
}

if ( ! class_exists( 'SBM_Test_WC' ) ) {
	/**
	 * Minimal WC container.
	 */
	class SBM_Test_WC {
		/**
		 * Cart.
		 *
		 * @var SBM_Test_Cart|null
		 */
		public ?SBM_Test_Cart $cart = null;
	}
}

if ( ! function_exists( 'WC' ) ) {
	/**
	 * Return the test WooCommerce container.
	 *
	 * @return SBM_Test_WC
	 */
	function WC() {
		if ( ! isset( $GLOBALS['sbm_test_wc'] ) ) {
			$GLOBALS['sbm_test_wc'] = new SBM_Test_WC();
		}

		return $GLOBALS['sbm_test_wc'];
	}
}

$GLOBALS['wpdb'] = $GLOBALS['wpdb'] ?? new wpdb();

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'StudioBookingManager\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = dirname( __DIR__ ) . '/src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

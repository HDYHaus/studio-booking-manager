<?php
/**
 * Admin menu.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Admin;

use StudioBookingManager\Access\AccessAdmin;
use StudioBookingManager\Bookings\BookingAdmin;
use StudioBookingManager\Commerce\WooCommerce\CommerceAdmin;
use StudioBookingManager\Database\Tables;
use StudioBookingManager\Locations\LocationAdmin;
use StudioBookingManager\Operations\OperationsAdmin;
use StudioBookingManager\PassTypes\PassTypeAdmin;
use StudioBookingManager\QR\QRAdmin;
use StudioBookingManager\People\PersonAdmin;
use StudioBookingManager\Reports\ReportsAdmin;
use StudioBookingManager\Visits\VisitAdmin;
use StudioBookingManager\Admin\PageHeader;
use StudioBookingManager\Settings\SettingsPage;
use StudioBookingManager\System\SystemHealth;

defined( 'ABSPATH' ) || exit;

/**
 * Registers admin screens.
 */
final class AdminMenu {
	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_menu(): void {
		$dashboard_capability = 'sbm_view_dashboard';

		add_menu_page(
			__( 'Studio Booking Manager', 'studio-booking-manager' ),
			__( 'Studio Booking', 'studio-booking-manager' ),
			$dashboard_capability,
			'sbm-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-building',
			56
		);

		add_submenu_page(
			'sbm-dashboard',
			__( 'Operations', 'studio-booking-manager' ),
			__( 'Operations', 'studio-booking-manager' ),
			'sbm_check_in',
			'sbm-operations',
			array( new OperationsAdmin(), 'render' )
		);

		add_submenu_page(
			'sbm-dashboard',
			__( 'QR Check-in', 'studio-booking-manager' ),
			__( 'QR Check-in', 'studio-booking-manager' ),
			'sbm_check_in',
			'sbm-qr-checkin',
			array( new QRAdmin(), 'render' )
		);

		add_submenu_page(
			'sbm-dashboard',
			__( 'People', 'studio-booking-manager' ),
			__( 'People', 'studio-booking-manager' ),
			'sbm_manage_people',
			'sbm-people',
			array( new PersonAdmin(), 'render' )
		);

		add_submenu_page(
			'sbm-dashboard',
			__( 'Access', 'studio-booking-manager' ),
			__( 'Access', 'studio-booking-manager' ),
			'sbm_manage_access',
			'sbm-access',
			array( new AccessAdmin(), 'render' )
		);

		add_submenu_page(
			'sbm-dashboard',
			__( 'Bookings', 'studio-booking-manager' ),
			__( 'Bookings', 'studio-booking-manager' ),
			'sbm_manage_bookings',
			'sbm-bookings',
			array( new BookingAdmin(), 'render' )
		);

		add_submenu_page(
			'sbm-dashboard',
			__( 'Passes', 'studio-booking-manager' ),
			__( 'Passes', 'studio-booking-manager' ),
			'sbm_manage_passes',
			'sbm-passes',
			array( new PassTypeAdmin(), 'render' )
		);

		add_submenu_page(
			'sbm-dashboard',
			__( 'Visits', 'studio-booking-manager' ),
			__( 'Visits', 'studio-booking-manager' ),
			'sbm_manage_visits',
			'sbm-visits',
			array( new VisitAdmin(), 'render' )
		);

		add_submenu_page(
			'sbm-dashboard',
			__( 'Locations', 'studio-booking-manager' ),
			__( 'Locations', 'studio-booking-manager' ),
			'sbm_manage_locations',
			'sbm-locations',
			array( new LocationAdmin(), 'render' )
		);


		add_submenu_page(
			'sbm-dashboard',
			__( 'Commerce', 'studio-booking-manager' ),
			__( 'Commerce', 'studio-booking-manager' ),
			'sbm_manage_settings',
			'sbm-commerce',
			array( new CommerceAdmin(), 'render' )
		);

		add_submenu_page(
			'sbm-dashboard',
			__( 'Reports', 'studio-booking-manager' ),
			__( 'Reports', 'studio-booking-manager' ),
			'sbm_view_reports',
			'sbm-reports',
			array( new ReportsAdmin(), 'render' )
		);

		add_submenu_page(
			'sbm-dashboard',
			__( 'System Health', 'studio-booking-manager' ),
			__( 'System Health', 'studio-booking-manager' ),
			'sbm_manage_settings',
			'sbm-system-health',
			array( new SystemHealth(), 'render' )
		);

		add_submenu_page(
			'sbm-dashboard',
			__( 'Settings', 'studio-booking-manager' ),
			__( 'Settings', 'studio-booking-manager' ),
			'sbm_manage_settings',
			'sbm-settings',
			array( new SettingsPage(), 'render' )
		);
	}

	/**
	 * Render dashboard.
	 */
	public function render_dashboard(): void {
		$stats          = $this->get_dashboard_stats();
		$pending_orders = $this->get_pending_orders();
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( __( 'Studio Booking Manager', 'studio-booking-manager' ) ); ?>
			<p><?php echo esc_html__( 'Open source operations for shared spaces.', 'studio-booking-manager' ); ?></p>
			<div class="sbm-grid">
				<?php $this->render_dashboard_stat( __( 'People Stats', 'studio-booking-manager' ), (string) $stats['people_total'], sprintf( /* translators: %d: active people count. */ __( '%d active', 'studio-booking-manager' ), $stats['people_active'] ) ); ?>
				<?php $this->render_dashboard_stat( __( 'Active Access', 'studio-booking-manager' ), (string) $stats['active_access'], __( 'Available now', 'studio-booking-manager' ) ); ?>
				<?php $this->render_dashboard_stat( __( 'Visits Today', 'studio-booking-manager' ), (string) $stats['visits_today'], current_time( get_option( 'date_format' ) ) ); ?>
				<?php $this->render_dashboard_stat( __( 'Pending Orders', 'studio-booking-manager' ), (string) $pending_orders['count'], __( 'Awaiting payment', 'studio-booking-manager' ), $pending_orders['url'] ); ?>
			</div>
			<div class="sbm-card sbm-card-wide">
				<h2><?php echo esc_html__( 'Pending Orders', 'studio-booking-manager' ); ?></h2>
				<?php if ( empty( $pending_orders['orders'] ) ) : ?>
					<p><?php echo esc_html__( 'No pending WooCommerce orders found.', 'studio-booking-manager' ); ?></p>
				<?php else : ?>
					<table class="widefat striped sbm-table">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Order', 'studio-booking-manager' ); ?></th>
								<th><?php echo esc_html__( 'Customer', 'studio-booking-manager' ); ?></th>
								<th><?php echo esc_html__( 'Payment', 'studio-booking-manager' ); ?></th>
								<th><?php echo esc_html__( 'Total', 'studio-booking-manager' ); ?></th>
								<th><?php echo esc_html__( 'Actions', 'studio-booking-manager' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $pending_orders['orders'] as $order ) : ?>
								<tr>
									<td>
										<a href="<?php echo esc_url( $order['url'] ); ?>">
											<?php echo esc_html( $order['number'] ); ?>
										</a>
									</td>
									<td><?php echo esc_html( $order['customer'] ); ?></td>
									<td><?php echo esc_html( $order['payment'] ); ?></td>
									<td><?php echo wp_kses_post( $order['total'] ); ?></td>
									<td><a class="button button-small" href="<?php echo esc_url( $order['url'] ); ?>"><?php echo esc_html__( 'View Order', 'studio-booking-manager' ); ?></a></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p><a class="button" href="<?php echo esc_url( $pending_orders['url'] ); ?>"><?php echo esc_html__( 'View All Pending Orders', 'studio-booking-manager' ); ?></a></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get dashboard counts.
	 *
	 * @return array<string,int>
	 */
	private function get_dashboard_stats(): array {
		$now = current_time( 'mysql' );

		return array(
			'people_total'  => $this->count_rows( 'people', 'status <> %s', array( 'archived' ) ),
			'people_active' => $this->count_rows( 'people', 'status = %s', array( 'active' ) ),
			'active_access' => $this->count_rows(
				'access',
				'status = %s AND (starts_at IS NULL OR starts_at <= %s) AND (expires_at IS NULL OR expires_at >= %s)',
				array( 'active', $now, $now )
			),
			'visits_today'  => $this->count_rows(
				'visits',
				'status <> %s AND (visit_date = %s OR DATE(checked_in_at) = %s)',
				array( 'archived', current_time( 'Y-m-d' ), current_time( 'Y-m-d' ) )
			),
		);
	}

	/**
	 * Count rows in a trusted Studio Booking Manager table.
	 *
	 * @param string               $table_key Table key.
	 * @param string               $where_sql Prepared WHERE SQL fragment.
	 * @param array<int, mixed>    $args Prepared arguments.
	 * @return int
	 */
	private function count_rows( string $table_key, string $where_sql, array $args ): int {
		global $wpdb;

		$table = Tables::get( $table_key );

		if ( '' === $table ) {
			return 0;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and WHERE fragments are internal trusted values.
		$query = $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}", $args );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Dashboard count query for custom operational tables using prepared values.
		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Get pending WooCommerce orders for the dashboard.
	 *
	 * @return array{count:int, url:string, orders:array<int,array<string,string>>}
	 */
	private function get_pending_orders(): array {
		$orders_url = admin_url( 'admin.php?page=wc-orders&status=wc-pending' );

		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array(
				'count'  => 0,
				'url'    => '',
				'orders' => array(),
			);
		}

		$count = function_exists( 'wc_orders_count' ) ? (int) wc_orders_count( 'pending' ) : 0;
		$orders = wc_get_orders(
			array(
				'limit'   => 5,
				'orderby' => 'date',
				'order'   => 'DESC',
				'status'  => 'pending',
				'return'  => 'objects',
			)
		);

		if ( 0 === $count && is_array( $orders ) ) {
			$count = count( $orders );
		}

		$rows = array();

		if ( is_array( $orders ) ) {
			foreach ( $orders as $order ) {
				if ( ! $order instanceof \WC_Order ) {
					continue;
				}

				$customer = $order->get_formatted_billing_full_name();

				$rows[] = array(
					'number'   => sprintf(
						/* translators: %s: WooCommerce order number. */
						__( '#%s', 'studio-booking-manager' ),
						$order->get_order_number()
					),
					'customer' => '' !== $customer ? $customer : __( 'Guest', 'studio-booking-manager' ),
					'payment'  => $order->get_payment_method_title(),
					'total'    => $order->get_formatted_order_total(),
					'url'      => $order->get_edit_order_url(),
				);
			}
		}

		return array(
			'count'  => $count,
			'url'    => $orders_url,
			'orders' => $rows,
		);
	}

	/**
	 * Render a dashboard stat card.
	 *
	 * @param string $label Stat label.
	 * @param string $value Stat value.
	 * @param string $description Supporting text.
	 * @param string $url Optional destination URL.
	 */
	private function render_dashboard_stat( string $label, string $value, string $description = '', string $url = '' ): void {
		?>
		<div class="sbm-stat">
			<span class="sbm-stat-value"><?php echo esc_html( $value ); ?></span>
			<span class="sbm-stat-label"><?php echo esc_html( $label ); ?></span>
			<?php if ( '' !== $description ) : ?>
				<span class="sbm-stat-description"><?php echo esc_html( $description ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $url ) : ?>
				<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html__( 'View in WooCommerce', 'studio-booking-manager' ); ?></a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render placeholder screens.
	 */
	public function render_placeholder(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$title = str_replace( 'sbm-', '', $page );
		$title = ucwords( str_replace( '-', ' ', $title ) );
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( $title ); ?>
			<p><?php echo esc_html__( 'This module is part of the Studio Booking Manager roadmap.', 'studio-booking-manager' ); ?></p>
		</div>
		<?php
	}
}

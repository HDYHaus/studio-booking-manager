<?php
/**
 * Reports admin screen.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Reports;

use StudioBookingManager\Admin\AbstractAdminPage;
use StudioBookingManager\Admin\PageHeader;
use StudioBookingManager\Database\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only operational reports.
 */
final class ReportsAdmin extends AbstractAdminPage {
	/**
	 * Capability required to view reports.
	 *
	 * @var string
	 */
	protected string $capability = 'sbm_view_reports';

	/**
	 * Render reports screen.
	 */
	public function render(): void {
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have permission to view reports.', 'studio-booking-manager' ) );
		}

		$range       = $this->get_date_range();
		$summary     = $this->get_summary( $range['start_mysql'], $range['end_mysql'] );
		$locations   = $this->get_visits_by_location( $range['start_mysql'], $range['end_mysql'] );
		$access      = $this->get_access_by_type();
		$recent      = $this->get_recent_visits( $range['start_mysql'], $range['end_mysql'] );
		$woocommerce = $this->get_woocommerce_summary( $range['start_timestamp'], $range['end_timestamp'] );
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( __( 'Reports', 'studio-booking-manager' ) ); ?>

			<form class="sbm-report-filters" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="sbm-reports">
				<label for="sbm-report-start"><?php echo esc_html__( 'Start date', 'studio-booking-manager' ); ?></label>
				<input type="date" id="sbm-report-start" name="start" value="<?php echo esc_attr( $range['start'] ); ?>">
				<label for="sbm-report-end"><?php echo esc_html__( 'End date', 'studio-booking-manager' ); ?></label>
				<input type="date" id="sbm-report-end" name="end" value="<?php echo esc_attr( $range['end'] ); ?>">
				<?php submit_button( __( 'Apply', 'studio-booking-manager' ), 'secondary', '', false ); ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sbm-reports' ) ); ?>"><?php echo esc_html__( 'Reset', 'studio-booking-manager' ); ?></a>
			</form>

			<div class="sbm-grid sbm-report-stats">
				<?php $this->render_stat( __( 'Visits', 'studio-booking-manager' ), (string) $summary['visits'], __( 'Checked in during range', 'studio-booking-manager' ) ); ?>
				<?php $this->render_stat( __( 'Guests', 'studio-booking-manager' ), (string) $summary['guests'], __( 'Additional guests recorded', 'studio-booking-manager' ) ); ?>
				<?php $this->render_stat( __( 'Current Visits', 'studio-booking-manager' ), (string) $summary['current_visits'], __( 'Checked in now', 'studio-booking-manager' ) ); ?>
				<?php $this->render_stat( __( 'Active Access', 'studio-booking-manager' ), (string) $summary['active_access'], __( 'Usable access records', 'studio-booking-manager' ) ); ?>
				<?php $this->render_stat( __( 'People', 'studio-booking-manager' ), (string) $summary['people'], __( 'Non-archived people', 'studio-booking-manager' ) ); ?>
				<?php $this->render_stat( __( 'Revenue', 'studio-booking-manager' ), $woocommerce['revenue'], $woocommerce['description'] ); ?>
			</div>

			<div class="sbm-report-panels">
				<div class="sbm-card">
					<h2><?php echo esc_html__( 'Visits by Location', 'studio-booking-manager' ); ?></h2>
					<?php $this->render_table( $locations, array( __( 'Location', 'studio-booking-manager' ), __( 'Visits', 'studio-booking-manager' ), __( 'Guests', 'studio-booking-manager' ) ) ); ?>
				</div>

				<div class="sbm-card">
					<h2><?php echo esc_html__( 'Access by Type', 'studio-booking-manager' ); ?></h2>
					<?php $this->render_table( $access, array( __( 'Type', 'studio-booking-manager' ), __( 'Active', 'studio-booking-manager' ), __( 'Expiring Soon', 'studio-booking-manager' ) ) ); ?>
				</div>
			</div>

			<div class="sbm-card sbm-card-wide">
				<h2><?php echo esc_html__( 'Recent Visits', 'studio-booking-manager' ); ?></h2>
				<?php if ( empty( $recent ) ) : ?>
					<p><?php echo esc_html__( 'No visits found for this date range.', 'studio-booking-manager' ); ?></p>
				<?php else : ?>
					<table class="widefat striped sbm-table">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Person', 'studio-booking-manager' ); ?></th>
								<th><?php echo esc_html__( 'Location', 'studio-booking-manager' ); ?></th>
								<th><?php echo esc_html__( 'Checked In', 'studio-booking-manager' ); ?></th>
								<th><?php echo esc_html__( 'Checked Out', 'studio-booking-manager' ); ?></th>
								<th><?php echo esc_html__( 'Guests', 'studio-booking-manager' ); ?></th>
								<th><?php echo esc_html__( 'Status', 'studio-booking-manager' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $recent as $visit ) : ?>
								<tr>
									<td><?php echo esc_html( (string) $visit->person_name ); ?></td>
									<td><?php echo esc_html( (string) $visit->location_name ); ?></td>
									<td><?php echo esc_html( $this->format_datetime( $visit->checked_in_at ) ); ?></td>
									<td><?php echo esc_html( $this->format_datetime( $visit->checked_out_at ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( (int) $visit->guest_count ) ); ?></td>
									<td><?php echo esc_html( $this->format_label( (string) $visit->status ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get sanitized date range.
	 *
	 * @return array{start:string,end:string,start_mysql:string,end_mysql:string,start_timestamp:int,end_timestamp:int}
	 */
	private function get_date_range(): array {
		$timezone      = wp_timezone();
		$default_start = new \DateTimeImmutable( '-29 days', $timezone );
		$default_end   = new \DateTimeImmutable( 'now', $timezone );

		$start = isset( $_GET['start'] ) ? sanitize_text_field( wp_unslash( $_GET['start'] ) ) : $default_start->format( 'Y-m-d' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$end   = isset( $_GET['end'] ) ? sanitize_text_field( wp_unslash( $_GET['end'] ) ) : $default_end->format( 'Y-m-d' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) ) {
			$start = $default_start->format( 'Y-m-d' );
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) ) {
			$end = $default_end->format( 'Y-m-d' );
		}

		$start_date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $start, $timezone );
		$end_date   = \DateTimeImmutable::createFromFormat( '!Y-m-d', $end, $timezone );

		if ( false === $start_date ) {
			$start_date = $default_start->setTime( 0, 0, 0 );
		}

		if ( false === $end_date ) {
			$end_date = $default_end->setTime( 0, 0, 0 );
		}

		if ( $start_date > $end_date ) {
			$start_date = $end_date;
		}

		$start_date = $start_date->setTime( 0, 0, 0 );
		$end_date   = $end_date->setTime( 23, 59, 59 );

		return array(
			'start'           => $start_date->format( 'Y-m-d' ),
			'end'             => $end_date->format( 'Y-m-d' ),
			'start_mysql'     => $start_date->format( 'Y-m-d H:i:s' ),
			'end_mysql'       => $end_date->format( 'Y-m-d H:i:s' ),
			'start_timestamp' => $start_date->getTimestamp(),
			'end_timestamp'   => $end_date->getTimestamp(),
		);
	}

	/**
	 * Get top-level report summary.
	 *
	 * @param string $start Start datetime.
	 * @param string $end End datetime.
	 * @return array<string,int>
	 */
	private function get_summary( string $start, string $end ): array {
		$now = current_time( 'mysql' );

		return array(
			'visits'         => $this->count_rows( 'visits', 'status <> %s AND checked_in_at BETWEEN %s AND %s', array( 'archived', $start, $end ) ),
			'guests'         => $this->sum_column( 'visits', 'guest_count', 'status <> %s AND checked_in_at BETWEEN %s AND %s', array( 'archived', $start, $end ) ),
			'current_visits' => $this->count_rows( 'visits', 'status = %s', array( 'checked_in' ) ),
			'active_access'  => $this->count_rows(
				'access',
				'status = %s AND (starts_at IS NULL OR starts_at <= %s) AND (expires_at IS NULL OR expires_at >= %s)',
				array( 'active', $now, $now )
			),
			'people'         => $this->count_rows( 'people', 'status <> %s', array( 'archived' ) ),
		);
	}

	/**
	 * Get visits grouped by location.
	 *
	 * @param string $start Start datetime.
	 * @param string $end End datetime.
	 * @return array<int,array<int,string>>
	 */
	private function get_visits_by_location( string $start, string $end ): array {
		global $wpdb;

		$visits_table    = Tables::get( 'visits' );
		$locations_table = Tables::get( 'locations' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from the trusted Tables registry.
		$query = $wpdb->prepare(
			"SELECT COALESCE(locations.name, %s) AS label, COUNT(visits.id) AS visits, COALESCE(SUM(visits.guest_count), 0) AS guests
			FROM `{$visits_table}` visits
			LEFT JOIN `{$locations_table}` locations ON locations.id = visits.location_id
			WHERE visits.status <> %s AND visits.checked_in_at BETWEEN %s AND %s
			GROUP BY visits.location_id, locations.name
			ORDER BY visits DESC, label ASC
			LIMIT 10",
			__( 'Unknown location', 'studio-booking-manager' ),
			'archived',
			$start,
			$end
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Read-only report query against custom tables.
		$rows = $wpdb->get_results( $query );

		return $this->normalize_report_rows( is_array( $rows ) ? $rows : array(), array( 'label', 'visits', 'guests' ) );
	}

	/**
	 * Get active access grouped by type.
	 *
	 * @return array<int,array<int,string>>
	 */
	private function get_access_by_type(): array {
		global $wpdb;

		$table = Tables::get( 'access' );
		$now   = current_time( 'mysql' );
		$soon  = wp_date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + ( 30 * DAY_IN_SECONDS ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from the trusted Tables registry.
		$query = $wpdb->prepare(
			"SELECT access_type AS label,
				COUNT(id) AS active,
				SUM(CASE WHEN expires_at IS NOT NULL AND expires_at BETWEEN %s AND %s THEN 1 ELSE 0 END) AS expiring
			FROM `{$table}`
			WHERE status = %s AND (starts_at IS NULL OR starts_at <= %s) AND (expires_at IS NULL OR expires_at >= %s)
			GROUP BY access_type
			ORDER BY active DESC, label ASC",
			$now,
			$soon,
			'active',
			$now,
			$now
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Read-only report query against custom tables.
		$rows = $wpdb->get_results( $query );
		$rows = is_array( $rows ) ? $rows : array();

		foreach ( $rows as $row ) {
			$row->label = $this->format_label( (string) $row->label );
		}

		return $this->normalize_report_rows( $rows, array( 'label', 'active', 'expiring' ) );
	}

	/**
	 * Get recent visits in range.
	 *
	 * @param string $start Start datetime.
	 * @param string $end End datetime.
	 * @return array<int,object>
	 */
	private function get_recent_visits( string $start, string $end ): array {
		global $wpdb;

		$visits_table    = Tables::get( 'visits' );
		$people_table    = Tables::get( 'people' );
		$locations_table = Tables::get( 'locations' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from the trusted Tables registry.
		$query = $wpdb->prepare(
			"SELECT visits.*, COALESCE(people.display_name, %s) AS person_name, COALESCE(locations.name, %s) AS location_name
			FROM `{$visits_table}` visits
			LEFT JOIN `{$people_table}` people ON people.id = visits.person_id
			LEFT JOIN `{$locations_table}` locations ON locations.id = visits.location_id
			WHERE visits.status <> %s AND visits.checked_in_at BETWEEN %s AND %s
			ORDER BY visits.checked_in_at DESC, visits.id DESC
			LIMIT 15",
			__( 'Unknown person', 'studio-booking-manager' ),
			__( 'Unknown location', 'studio-booking-manager' ),
			'archived',
			$start,
			$end
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Read-only report query against custom tables.
		$rows = $wpdb->get_results( $query );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get WooCommerce revenue summary.
	 *
	 * @param int $start Start timestamp.
	 * @param int $end End timestamp.
	 * @return array{revenue:string,description:string}
	 */
	private function get_woocommerce_summary( int $start, int $end ): array {
		if ( ! function_exists( 'wc_get_orders' ) || ! function_exists( 'wc_price' ) ) {
			return array(
				'revenue'     => __( 'N/A', 'studio-booking-manager' ),
				'description' => __( 'WooCommerce unavailable', 'studio-booking-manager' ),
			);
		}

		$orders = wc_get_orders(
			array(
				'limit'        => 100,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'status'       => array( 'completed', 'processing' ),
				'date_created' => $start . '...' . $end,
				'return'       => 'objects',
			)
		);

		$total = 0.0;
		$count = 0;

		if ( is_array( $orders ) ) {
			foreach ( $orders as $order ) {
				if ( ! $order instanceof \WC_Order ) {
					continue;
				}

				$total += (float) $order->get_total();
				++$count;
			}
		}

		return array(
			'revenue'     => wp_strip_all_tags( wc_price( $total ) ),
			'description' => sprintf(
				/* translators: %d: WooCommerce order count. */
				_n( '%d paid order', '%d paid orders', $count, 'studio-booking-manager' ),
				$count
			),
		);
	}

	/**
	 * Count rows in a trusted Studio Booking Manager table.
	 *
	 * @param string           $table_key Table key.
	 * @param string           $where_sql Prepared WHERE fragment.
	 * @param array<int,mixed> $args Prepared arguments.
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Read-only report query against custom tables.
		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Sum a numeric column in a trusted Studio Booking Manager table.
	 *
	 * @param string           $table_key Table key.
	 * @param string           $column Column name.
	 * @param string           $where_sql Prepared WHERE fragment.
	 * @param array<int,mixed> $args Prepared arguments.
	 * @return int
	 */
	private function sum_column( string $table_key, string $column, string $where_sql, array $args ): int {
		global $wpdb;

		$table = Tables::get( $table_key );

		if ( '' === $table || ! preg_match( '/^[a-z_]+$/', $column ) ) {
			return 0;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table, column, and WHERE fragments are internal trusted values.
		$query = $wpdb->prepare( "SELECT COALESCE(SUM(`{$column}`), 0) FROM `{$table}` WHERE {$where_sql}", $args );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Read-only report query against custom tables.
		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Render a stat card.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $description Description.
	 */
	private function render_stat( string $label, string $value, string $description ): void {
		?>
		<div class="sbm-stat">
			<span class="sbm-stat-value"><?php echo esc_html( $value ); ?></span>
			<span class="sbm-stat-label"><?php echo esc_html( $label ); ?></span>
			<span class="sbm-stat-description"><?php echo esc_html( $description ); ?></span>
		</div>
		<?php
	}

	/**
	 * Render a small report table.
	 *
	 * @param array<int,array<int,string>> $rows Rows.
	 * @param string[]                     $headers Headers.
	 */
	private function render_table( array $rows, array $headers ): void {
		if ( empty( $rows ) ) {
			?>
			<p><?php echo esc_html__( 'No data found for this report.', 'studio-booking-manager' ); ?></p>
			<?php
			return;
		}
		?>
		<table class="widefat striped sbm-table">
			<thead>
				<tr>
					<?php foreach ( $headers as $header ) : ?>
						<th><?php echo esc_html( $header ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<?php foreach ( $row as $cell ) : ?>
							<td><?php echo esc_html( $cell ); ?></td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Normalize database objects into display rows.
	 *
	 * @param mixed[]  $rows Rows.
	 * @param string[] $keys Object keys.
	 * @return array<int,array<int,string>>
	 */
	private function normalize_report_rows( array $rows, array $keys ): array {
		$normalized = array();

		foreach ( $rows as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}

			$cells = array();

			foreach ( $keys as $key ) {
				$value   = property_exists( $row, $key ) ? $row->{$key} : '';
				$cells[] = is_numeric( $value ) ? number_format_i18n( (float) $value ) : (string) $value;
			}

			$normalized[] = $cells;
		}

		return $normalized;
	}

	/**
	 * Format a stored key for display.
	 *
	 * @param string $value Stored value.
	 * @return string
	 */
	private function format_label( string $value ): string {
		return ucwords( str_replace( '_', ' ', $value ) );
	}

	/**
	 * Format a datetime string.
	 *
	 * @param mixed $value Datetime value.
	 * @return string
	 */
	private function format_datetime( $value ): string {
		if ( empty( $value ) ) {
			return '-';
		}

		$timestamp = strtotime( (string) $value );

		if ( false === $timestamp ) {
			return (string) $value;
		}

		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}
}

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
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_post_sbm_export_report', array( $this, 'handle_export' ) );
	}

	/**
	 * Handle CSV export requests.
	 */
	public function handle_export(): void {
		$this->verify_admin_request( 'sbm_export_report', __( 'You do not have permission to export reports.', 'studio-booking-manager' ) );

		$range  = $this->get_date_range();
		$report = isset( $_GET['report'] ) ? sanitize_key( wp_unslash( $_GET['report'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified above.
		$data   = $this->get_export_data( $report, $range );

		if ( empty( $data['headers'] ) ) {
			wp_die( esc_html__( 'Unknown report export.', 'studio-booking-manager' ) );
		}

		$filename = sprintf( 'studio-booking-%s-%s-to-%s.csv', $report, $range['start'], $range['end'] );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV export streams directly to the HTTP response.
		$output = fopen( 'php://output', 'w' );

		if ( false === $output ) {
			exit;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CSV export streams directly to the HTTP response.
		fputcsv( $output, $data['headers'] );

		foreach ( $data['rows'] as $row ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CSV export streams directly to the HTTP response.
			fputcsv( $output, $row );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CSV export streams directly to the HTTP response.
		fclose( $output );
		exit;
	}

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
		$attendance  = $this->get_attendance_trend( $range['start'], $range['end'] );
		$bookings    = $this->get_bookings_by_status( $range['start_mysql'], $range['end_mysql'] );
		$usage       = $this->get_access_usage( $range['start_mysql'], $range['end_mysql'] );
		$recent      = $this->get_recent_visits( $range['start_mysql'], $range['end_mysql'] );
		$woocommerce = $this->get_woocommerce_summary( $range['start_timestamp'], $range['end_timestamp'] );
		$revenue     = $this->get_revenue_trend( $range['start_timestamp'], $range['end_timestamp'] );
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
				<span class="sbm-report-presets">
					<?php foreach ( $this->date_presets() as $preset => $label ) : ?>
						<a class="button button-small" href="<?php echo esc_url( $this->report_url_for_preset( $preset ) ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</span>
			</form>

			<div class="sbm-report-actions">
				<?php $this->render_export_link( 'visits', __( 'Export Visits', 'studio-booking-manager' ), $range ); ?>
				<?php $this->render_export_link( 'bookings', __( 'Export Bookings', 'studio-booking-manager' ), $range ); ?>
				<?php $this->render_export_link( 'access', __( 'Export Access', 'studio-booking-manager' ), $range ); ?>
				<?php $this->render_export_link( 'locations', __( 'Export Locations', 'studio-booking-manager' ), $range ); ?>
			</div>

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
					<h2><?php echo esc_html__( 'Attendance Trend', 'studio-booking-manager' ); ?></h2>
					<?php $this->render_bar_chart( $attendance, __( 'No attendance data found for this date range.', 'studio-booking-manager' ) ); ?>
				</div>

				<div class="sbm-card">
					<h2><?php echo esc_html__( 'Revenue Trend', 'studio-booking-manager' ); ?></h2>
					<?php $this->render_bar_chart( $revenue, __( 'No paid WooCommerce revenue found for this date range.', 'studio-booking-manager' ) ); ?>
				</div>

				<div class="sbm-card">
					<h2><?php echo esc_html__( 'Visits by Location', 'studio-booking-manager' ); ?></h2>
					<?php $this->render_bar_chart( $this->rows_to_chart_points( $locations, 1 ), __( 'No location trend data found for this date range.', 'studio-booking-manager' ) ); ?>
					<?php $this->render_table( $locations, array( __( 'Location', 'studio-booking-manager' ), __( 'Visits', 'studio-booking-manager' ), __( 'Guests', 'studio-booking-manager' ) ) ); ?>
				</div>

				<div class="sbm-card">
					<h2><?php echo esc_html__( 'Access by Type', 'studio-booking-manager' ); ?></h2>
					<?php $this->render_bar_chart( $this->rows_to_chart_points( $access, 1 ), __( 'No active access data found.', 'studio-booking-manager' ) ); ?>
					<?php $this->render_table( $access, array( __( 'Type', 'studio-booking-manager' ), __( 'Active', 'studio-booking-manager' ), __( 'Expiring Soon', 'studio-booking-manager' ) ) ); ?>
				</div>

				<div class="sbm-card">
					<h2><?php echo esc_html__( 'Bookings by Status', 'studio-booking-manager' ); ?></h2>
					<?php $this->render_table( $bookings, array( __( 'Status', 'studio-booking-manager' ), __( 'Bookings', 'studio-booking-manager' ), __( 'Guests', 'studio-booking-manager' ) ) ); ?>
				</div>

				<div class="sbm-card">
					<h2><?php echo esc_html__( 'Access Usage', 'studio-booking-manager' ); ?></h2>
					<?php $this->render_table( $usage, array( __( 'Access Type', 'studio-booking-manager' ), __( 'Visits', 'studio-booking-manager' ), __( 'Guests', 'studio-booking-manager' ) ) ); ?>
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
		$preset        = isset( $_GET['preset'] ) ? sanitize_key( wp_unslash( $_GET['preset'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' !== $preset ) {
			$preset_range = $this->date_range_for_preset( $preset, $timezone );

			if ( ! empty( $preset_range ) ) {
				$default_start = $preset_range['start'];
				$default_end   = $preset_range['end'];
			}
		}

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
	 * Available date presets.
	 *
	 * @return array<string,string>
	 */
	private function date_presets(): array {
		return array(
			'today'      => __( 'Today', 'studio-booking-manager' ),
			'7_days'     => __( '7 days', 'studio-booking-manager' ),
			'30_days'    => __( '30 days', 'studio-booking-manager' ),
			'month'      => __( 'This month', 'studio-booking-manager' ),
			'last_month' => __( 'Last month', 'studio-booking-manager' ),
		);
	}

	/**
	 * Get preset date range.
	 *
	 * @param string        $preset Preset key.
	 * @param \DateTimeZone $timezone Site timezone.
	 * @return array{start:\DateTimeImmutable,end:\DateTimeImmutable}|array{}
	 */
	private function date_range_for_preset( string $preset, \DateTimeZone $timezone ): array {
		$today = new \DateTimeImmutable( 'today', $timezone );

		switch ( $preset ) {
			case 'today':
				return array(
					'start' => $today,
					'end'   => $today,
				);
			case '7_days':
				return array(
					'start' => $today->modify( '-6 days' ),
					'end'   => $today,
				);
			case '30_days':
				return array(
					'start' => $today->modify( '-29 days' ),
					'end'   => $today,
				);
			case 'month':
				return array(
					'start' => $today->modify( 'first day of this month' ),
					'end'   => $today,
				);
			case 'last_month':
				$start = $today->modify( 'first day of last month' );
				return array(
					'start' => $start,
					'end'   => $start->modify( 'last day of this month' ),
				);
		}

		return array();
	}

	/**
	 * Build report URL for a preset.
	 *
	 * @param string $preset Preset key.
	 */
	private function report_url_for_preset( string $preset ): string {
		return add_query_arg(
			array(
				'page'   => 'sbm-reports',
				'preset' => $preset,
			),
			admin_url( 'admin.php' )
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only report query against custom tables.
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only report query against custom tables.
		$rows = $wpdb->get_results( $query );
		$rows = is_array( $rows ) ? $rows : array();

		foreach ( $rows as $row ) {
			$row->label = $this->format_label( (string) $row->label );
		}

		return $this->normalize_report_rows( $rows, array( 'label', 'active', 'expiring' ) );
	}

	/**
	 * Get attendance trend by day.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array<int,array{label:string,value:float}>
	 */
	private function get_attendance_trend( string $start_date, string $end_date ): array {
		global $wpdb;

		$table = Tables::get( 'visits' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from the trusted Tables registry.
		$query = $wpdb->prepare(
			"SELECT DATE(checked_in_at) AS report_date, COUNT(id) AS total
			FROM `{$table}`
			WHERE status <> %s AND checked_in_at BETWEEN %s AND %s
			GROUP BY DATE(checked_in_at)
			ORDER BY report_date ASC",
			'archived',
			$start_date . ' 00:00:00',
			$end_date . ' 23:59:59'
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only report query against custom tables.
		$rows = $wpdb->get_results( $query );
		$map  = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$map[ (string) $row->report_date ] = (float) $row->total;
		}

		return $this->fill_daily_chart_points( $start_date, $end_date, $map );
	}

	/**
	 * Get booking breakdown by status.
	 *
	 * @param string $start Start datetime.
	 * @param string $end End datetime.
	 * @return array<int,array<int,string>>
	 */
	private function get_bookings_by_status( string $start, string $end ): array {
		global $wpdb;

		$table = Tables::get( 'bookings' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from the trusted Tables registry.
		$query = $wpdb->prepare(
			"SELECT status AS label, COUNT(id) AS bookings, COALESCE(SUM(guest_count), 0) AS guests
			FROM `{$table}`
			WHERE status <> %s AND starts_at BETWEEN %s AND %s
			GROUP BY status
			ORDER BY bookings DESC, label ASC",
			'archived',
			$start,
			$end
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only report query against custom tables.
		$rows = $wpdb->get_results( $query );
		$rows = is_array( $rows ) ? $rows : array();

		foreach ( $rows as $row ) {
			$row->label = $this->format_label( (string) $row->label );
		}

		return $this->normalize_report_rows( $rows, array( 'label', 'bookings', 'guests' ) );
	}

	/**
	 * Get visits grouped by access type.
	 *
	 * @param string $start Start datetime.
	 * @param string $end End datetime.
	 * @return array<int,array<int,string>>
	 */
	private function get_access_usage( string $start, string $end ): array {
		global $wpdb;

		$visits_table = Tables::get( 'visits' );
		$access_table = Tables::get( 'access' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from the trusted Tables registry.
		$query = $wpdb->prepare(
			"SELECT COALESCE(access.access_type, %s) AS label, COUNT(visits.id) AS visits, COALESCE(SUM(visits.guest_count), 0) AS guests
			FROM `{$visits_table}` visits
			LEFT JOIN `{$access_table}` access ON access.id = visits.access_id
			WHERE visits.status <> %s AND visits.checked_in_at BETWEEN %s AND %s
			GROUP BY access.access_type
			ORDER BY visits DESC, label ASC",
			__( 'No access record', 'studio-booking-manager' ),
			'archived',
			$start,
			$end
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only report query against custom tables.
		$rows = $wpdb->get_results( $query );
		$rows = is_array( $rows ) ? $rows : array();

		foreach ( $rows as $row ) {
			$row->label = $this->format_label( (string) $row->label );
		}

		return $this->normalize_report_rows( $rows, array( 'label', 'visits', 'guests' ) );
	}

	/**
	 * Get revenue trend by day.
	 *
	 * @param int $start Start timestamp.
	 * @param int $end End timestamp.
	 * @return array<int,array{label:string,value:float}>
	 */
	private function get_revenue_trend( int $start, int $end ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$map   = array();
		$page  = 1;
		$limit = 100;

		do {
			$orders = wc_get_orders(
				array(
					'limit'        => $limit,
					'paged'        => $page,
					'orderby'      => 'date',
					'order'        => 'ASC',
					'status'       => array( 'completed', 'processing' ),
					'date_created' => $start . '...' . $end,
					'return'       => 'objects',
				)
			);

			if ( ! is_array( $orders ) || empty( $orders ) ) {
				break;
			}

			foreach ( $orders as $order ) {
				if ( ! $order instanceof \WC_Order || null === $order->get_date_created() ) {
					continue;
				}

				$date         = $order->get_date_created()->date_i18n( 'Y-m-d' );
				$map[ $date ] = ( $map[ $date ] ?? 0 ) + (float) $order->get_total();
			}

			++$page;
		} while ( count( $orders ) === $limit );

		return $this->fill_daily_chart_points( wp_date( 'Y-m-d', $start ), wp_date( 'Y-m-d', $end ), $map );
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only report query against custom tables.
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

		$total = 0.0;
		$count = 0;
		$page  = 1;
		$limit = 100;

		do {
			$orders = wc_get_orders(
				array(
					'limit'        => $limit,
					'paged'        => $page,
					'orderby'      => 'date',
					'order'        => 'DESC',
					'status'       => array( 'completed', 'processing' ),
					'date_created' => $start . '...' . $end,
					'return'       => 'objects',
				)
			);

			if ( ! is_array( $orders ) || empty( $orders ) ) {
				break;
			}

			foreach ( $orders as $order ) {
				if ( ! $order instanceof \WC_Order ) {
					continue;
				}

				$total += (float) $order->get_total();
				++$count;
			}

			++$page;
		} while ( count( $orders ) === $limit );

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
	 * Get export data for a report.
	 *
	 * @param string               $report Report key.
	 * @param array<string,mixed>  $range Date range.
	 * @return array{headers:array<int,string>,rows:array<int,array<int,string>>}
	 */
	private function get_export_data( string $report, array $range ): array {
		switch ( $report ) {
			case 'visits':
				return array(
					'headers' => array( 'Person', 'Location', 'Checked In', 'Checked Out', 'Guests', 'Status' ),
					'rows'    => $this->visits_export_rows( (string) $range['start_mysql'], (string) $range['end_mysql'] ),
				);
			case 'bookings':
				return array(
					'headers' => array( 'Person', 'Location', 'Starts At', 'Ends At', 'Guests', 'Status' ),
					'rows'    => $this->bookings_export_rows( (string) $range['start_mysql'], (string) $range['end_mysql'] ),
				);
			case 'access':
				return array(
					'headers' => array( 'Person', 'Location', 'Access Type', 'Status', 'Remaining Credits', 'Expires At' ),
					'rows'    => $this->access_export_rows(),
				);
			case 'locations':
				return array(
					'headers' => array( 'Location', 'Visits', 'Guests' ),
					'rows'    => $this->get_visits_by_location( (string) $range['start_mysql'], (string) $range['end_mysql'] ),
				);
		}

		return array(
			'headers' => array(),
			'rows'    => array(),
		);
	}

	/**
	 * Get visit export rows.
	 *
	 * @param string $start Start datetime.
	 * @param string $end End datetime.
	 * @return array<int,array<int,string>>
	 */
	private function visits_export_rows( string $start, string $end ): array {
		$rows = array();

		foreach ( $this->get_recent_visits_for_export( $start, $end ) as $visit ) {
			$rows[] = array(
				(string) $visit->person_name,
				(string) $visit->location_name,
				$this->format_datetime( $visit->checked_in_at ),
				$this->format_datetime( $visit->checked_out_at ),
				(string) absint( $visit->guest_count ),
				$this->format_label( (string) $visit->status ),
			);
		}

		return $rows;
	}

	/**
	 * Get booking export rows.
	 *
	 * @param string $start Start datetime.
	 * @param string $end End datetime.
	 * @return array<int,array<int,string>>
	 */
	private function bookings_export_rows( string $start, string $end ): array {
		global $wpdb;

		$bookings_table  = Tables::get( 'bookings' );
		$people_table    = Tables::get( 'people' );
		$locations_table = Tables::get( 'locations' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from the trusted Tables registry.
		$query = $wpdb->prepare(
			"SELECT bookings.*, COALESCE(people.display_name, %s) AS person_name, COALESCE(locations.name, %s) AS location_name
			FROM `{$bookings_table}` bookings
			LEFT JOIN `{$people_table}` people ON people.id = bookings.person_id
			LEFT JOIN `{$locations_table}` locations ON locations.id = bookings.location_id
			WHERE bookings.status <> %s AND bookings.starts_at BETWEEN %s AND %s
			ORDER BY bookings.starts_at ASC, bookings.id ASC
			LIMIT 2000",
			__( 'Unknown person', 'studio-booking-manager' ),
			__( 'Unknown location', 'studio-booking-manager' ),
			'archived',
			$start,
			$end
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only export query against custom tables.
		$records = $wpdb->get_results( $query );
		$rows    = array();

		foreach ( is_array( $records ) ? $records : array() as $record ) {
			$rows[] = array(
				(string) $record->person_name,
				(string) $record->location_name,
				$this->format_datetime( $record->starts_at ),
				$this->format_datetime( $record->ends_at ),
				(string) absint( $record->guest_count ),
				$this->format_label( (string) $record->status ),
			);
		}

		return $rows;
	}

	/**
	 * Get access export rows.
	 *
	 * @return array<int,array<int,string>>
	 */
	private function access_export_rows(): array {
		global $wpdb;

		$access_table    = Tables::get( 'access' );
		$people_table    = Tables::get( 'people' );
		$locations_table = Tables::get( 'locations' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from the trusted Tables registry.
		$query = $wpdb->prepare(
			"SELECT access.*, COALESCE(people.display_name, %s) AS person_name, COALESCE(locations.name, %s) AS location_name
			FROM `{$access_table}` access
			LEFT JOIN `{$people_table}` people ON people.id = access.person_id
			LEFT JOIN `{$locations_table}` locations ON locations.id = access.location_id
			WHERE access.status <> %s
			ORDER BY access.created_at DESC, access.id DESC
			LIMIT 2000",
			__( 'Unknown person', 'studio-booking-manager' ),
			__( 'Unknown location', 'studio-booking-manager' ),
			'archived'
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only export query against custom tables.
		$records = $wpdb->get_results( $query );
		$rows    = array();

		foreach ( is_array( $records ) ? $records : array() as $record ) {
			$rows[] = array(
				(string) $record->person_name,
				(string) $record->location_name,
				$this->format_label( (string) $record->access_type ),
				$this->format_label( (string) $record->status ),
				null === $record->remaining_credits ? __( 'Unlimited', 'studio-booking-manager' ) : (string) absint( $record->remaining_credits ),
				$this->format_datetime( $record->expires_at ),
			);
		}

		return $rows;
	}

	/**
	 * Get visits for export.
	 *
	 * @param string $start Start datetime.
	 * @param string $end End datetime.
	 * @return array<int,object>
	 */
	private function get_recent_visits_for_export( string $start, string $end ): array {
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
			LIMIT 2000",
			__( 'Unknown person', 'studio-booking-manager' ),
			__( 'Unknown location', 'studio-booking-manager' ),
			'archived',
			$start,
			$end
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only export query against custom tables.
		$rows = $wpdb->get_results( $query );

		return is_array( $rows ) ? $rows : array();
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

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table name and WHERE fragments are internal trusted values with prepared placeholder args.
		$query = $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}", $args );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only report query against custom tables.
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

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table, column, and WHERE fragments are internal trusted values with prepared placeholder args.
		$query = $wpdb->prepare( "SELECT COALESCE(SUM(`{$column}`), 0) FROM `{$table}` WHERE {$where_sql}", $args );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only report query against custom tables.
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
	 * Render an export link.
	 *
	 * @param string              $report Report key.
	 * @param string              $label Link label.
	 * @param array<string,mixed> $range Date range.
	 */
	private function render_export_link( string $report, string $label, array $range ): void {
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'sbm_export_report',
					'report' => $report,
					'start'  => $range['start'],
					'end'    => $range['end'],
				),
				admin_url( 'admin-post.php' )
			),
			'sbm_export_report'
		);
		?>
		<a class="button" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php
	}

	/**
	 * Render a compact bar chart.
	 *
	 * @param array<int,array{label:string,value:float}> $points Chart points.
	 * @param string                                     $empty Empty message.
	 */
	private function render_bar_chart( array $points, string $empty ): void {
		$points = array_values(
			array_filter(
				$points,
				static function( array $point ): bool {
					return $point['value'] > 0;
				}
			)
		);

		if ( empty( $points ) ) {
			?>
			<p><?php echo esc_html( $empty ); ?></p>
			<?php
			return;
		}

		$max = max( array_map( static fn( array $point ): float => $point['value'], $points ) );
		?>
		<div class="sbm-report-chart" role="list">
			<?php foreach ( $points as $point ) : ?>
				<?php $width = $max > 0 ? max( 4, ( $point['value'] / $max ) * 100 ) : 0; ?>
				<div class="sbm-report-chart-row" role="listitem">
					<span class="sbm-report-chart-label"><?php echo esc_html( $point['label'] ); ?></span>
					<span class="sbm-report-chart-track"><span class="sbm-report-chart-bar" style="width: <?php echo esc_attr( number_format( $width, 2, '.', '' ) ); ?>%;"></span></span>
					<span class="sbm-report-chart-value"><?php echo esc_html( number_format_i18n( $point['value'], $point['value'] === floor( $point['value'] ) ? 0 : 2 ) ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Convert display rows into chart points.
	 *
	 * @param array<int,array<int,string>> $rows Rows.
	 * @param int                          $value_index Value cell index.
	 * @return array<int,array{label:string,value:float}>
	 */
	private function rows_to_chart_points( array $rows, int $value_index ): array {
		$points = array();

		foreach ( $rows as $row ) {
			$value = isset( $row[ $value_index ] ) ? (float) str_replace( ',', '', (string) $row[ $value_index ] ) : 0;

			$points[] = array(
				'label' => isset( $row[0] ) ? (string) $row[0] : '',
				'value' => $value,
			);
		}

		return $points;
	}

	/**
	 * Fill daily chart points for a range.
	 *
	 * @param string              $start_date Start date.
	 * @param string              $end_date End date.
	 * @param array<string,float> $map Values by Y-m-d.
	 * @return array<int,array{label:string,value:float}>
	 */
	private function fill_daily_chart_points( string $start_date, string $end_date, array $map ): array {
		$timezone = wp_timezone();
		$start    = \DateTimeImmutable::createFromFormat( '!Y-m-d', $start_date, $timezone );
		$end      = \DateTimeImmutable::createFromFormat( '!Y-m-d', $end_date, $timezone );
		$points   = array();

		if ( false === $start || false === $end ) {
			return $points;
		}

		$days = (int) $start->diff( $end )->format( '%a' );

		for ( $i = 0; $i <= $days; ++$i ) {
			$date     = $start->modify( '+' . $i . ' days' );
			$key      = $date->format( 'Y-m-d' );
			$points[] = array(
				'label' => $date->format( 'M j' ),
				'value' => isset( $map[ $key ] ) ? (float) $map[ $key ] : 0.0,
			);
		}

		return $points;
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

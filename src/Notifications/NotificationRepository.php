<?php
/**
 * Notification repository.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Notifications;

use StudioBookingManager\Database\Tables;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Handles notification log persistence.
 */
final class NotificationRepository {
	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb  = $wpdb;
		$this->table = Tables::get( 'notifications' );
	}

	/**
	 * Get recent notification logs.
	 *
	 * @param int $limit Result limit.
	 * @return array<int, object>
	 */
	public function recent( int $limit = 50 ): array {
		$limit = max( 1, min( 200, $limit ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from trusted Tables registry; limit is prepared.
		$query = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` ORDER BY created_at DESC, id DESC LIMIT %d", $limit );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom operational table query using a trusted table name and prepared limit.
		$records = $this->wpdb->get_results( $query );

		return is_array( $records ) ? $records : array();
	}

	/**
	 * Create a log entry.
	 *
	 * @param array<string,mixed> $data Log data.
	 * @return int
	 */
	public function create( array $data ): int {
		$data = array(
			'notification_type' => isset( $data['notification_type'] ) ? sanitize_key( (string) $data['notification_type'] ) : '',
			'recipient_email'   => isset( $data['recipient_email'] ) ? sanitize_email( (string) $data['recipient_email'] ) : '',
			'recipient_name'    => isset( $data['recipient_name'] ) ? sanitize_text_field( (string) $data['recipient_name'] ) : '',
			'subject'           => isset( $data['subject'] ) ? sanitize_text_field( (string) $data['subject'] ) : '',
			'message'           => isset( $data['message'] ) ? sanitize_textarea_field( (string) $data['message'] ) : '',
			'status'            => isset( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : 'pending',
			'error_message'     => isset( $data['error_message'] ) ? sanitize_textarea_field( (string) $data['error_message'] ) : '',
			'context'           => isset( $data['context'] ) ? wp_json_encode( $data['context'] ) : '',
			'sent_at'           => isset( $data['sent_at'] ) ? sanitize_text_field( (string) $data['sent_at'] ) : null,
			'created_at'        => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom operational table insert.
		$inserted = $this->wpdb->insert(
			$this->table,
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return false === $inserted ? 0 : (int) $this->wpdb->insert_id;
	}
}

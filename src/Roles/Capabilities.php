<?php
/**
 * Studio Booking Manager capabilities.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Capability registry.
 */
final class Capabilities {
	public const VIEW_DASHBOARD   = 'sbm_view_dashboard';
	public const MANAGE_PEOPLE    = 'sbm_manage_people';
	public const MANAGE_ACCESS    = 'sbm_manage_access';
	public const MANAGE_VISITS    = 'sbm_manage_visits';
	public const MANAGE_LOCATIONS = 'sbm_manage_locations';
	public const CHECK_IN         = 'sbm_check_in';
	public const CHECK_OUT        = 'sbm_check_out';
	public const VIEW_REPORTS     = 'sbm_view_reports';
	public const MANAGE_SETTINGS  = 'sbm_manage_settings';

	/**
	 * Get all capabilities.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array(
			self::VIEW_DASHBOARD,
			self::MANAGE_PEOPLE,
			self::MANAGE_ACCESS,
			self::MANAGE_VISITS,
			self::MANAGE_LOCATIONS,
			self::CHECK_IN,
			self::CHECK_OUT,
			self::VIEW_REPORTS,
			self::MANAGE_SETTINGS,
		);
	}

	/**
	 * Get receptionist capabilities.
	 *
	 * @return string[]
	 */
	public static function receptionist(): array {
		return array(
			self::VIEW_DASHBOARD,
			self::CHECK_IN,
			self::CHECK_OUT,
			self::MANAGE_VISITS,
		);
	}

	/**
	 * Get studio manager capabilities.
	 *
	 * @return string[]
	 */
	public static function studio_manager(): array {
		return array(
			self::VIEW_DASHBOARD,
			self::MANAGE_PEOPLE,
			self::MANAGE_ACCESS,
			self::MANAGE_VISITS,
			self::MANAGE_LOCATIONS,
			self::CHECK_IN,
			self::CHECK_OUT,
			self::VIEW_REPORTS,
		);
	}
}

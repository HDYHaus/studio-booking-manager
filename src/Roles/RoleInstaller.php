<?php
/**
 * Role and capability installer.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Installs Studio Booking Manager roles and capabilities.
 */
final class RoleInstaller {
	/**
	 * Install or update roles and capabilities.
	 */
	public function install(): void {
		$this->grant_to_administrators();
		$this->create_or_update_role(
			'sbm_receptionist',
			__( 'Studio Receptionist', 'studio-booking-manager' ),
			Capabilities::receptionist()
		);
		$this->create_or_update_role(
			'sbm_studio_manager',
			__( 'Studio Manager', 'studio-booking-manager' ),
			Capabilities::studio_manager()
		);
	}

	/**
	 * Grant all SBM capabilities to administrators.
	 */
	private function grant_to_administrators(): void {
		$role = get_role( 'administrator' );

		if ( ! $role ) {
			return;
		}

		foreach ( Capabilities::all() as $capability ) {
			$role->add_cap( $capability );
		}
	}

	/**
	 * Create or update a role.
	 *
	 * @param string   $role_key     Role key.
	 * @param string   $display_name Display name.
	 * @param string[] $capabilities Capabilities.
	 */
	private function create_or_update_role( string $role_key, string $display_name, array $capabilities ): void {
		$role = get_role( $role_key );

		if ( ! $role ) {
			add_role( $role_key, $display_name, array( 'read' => true ) );
			$role = get_role( $role_key );
		}

		if ( ! $role ) {
			return;
		}

		$role->add_cap( 'read' );

		foreach ( $capabilities as $capability ) {
			$role->add_cap( $capability );
		}
	}
}

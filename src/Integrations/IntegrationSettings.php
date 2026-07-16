<?php
/**
 * Integration settings storage.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and sanitizes integration settings.
 */
final class IntegrationSettings {
	public const OPTION_NAME = 'sbm_integrations';

	/**
	 * Get saved settings.
	 *
	 * @return array<string,mixed>
	 */
	public function all(): array {
		$settings = get_option( self::OPTION_NAME, array() );

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Get provider settings.
	 *
	 * @param string $provider Provider slug.
	 * @return array<string,mixed>
	 */
	public function provider( string $provider ): array {
		$settings  = $this->all();
		$providers = isset( $settings['providers'] ) && is_array( $settings['providers'] ) ? $settings['providers'] : array();
		$config    = isset( $providers[ $provider ] ) && is_array( $providers[ $provider ] ) ? $providers[ $provider ] : array();

		return wp_parse_args(
			$config,
			array(
				'enabled'            => 0,
				'action'             => 'create_pending_booking',
				'duplicate_strategy' => 'update_existing',
			)
		);
	}

	/**
	 * Determine whether a provider is enabled.
	 *
	 * @param string $provider Provider slug.
	 */
	public function is_enabled( string $provider ): bool {
		$config = $this->provider( $provider );

		return ! empty( $config['enabled'] );
	}

	/**
	 * Sanitize settings.
	 *
	 * @param mixed $settings Raw settings.
	 * @return array<string,mixed>
	 */
	public function sanitize( $settings ): array {
		$settings = is_array( $settings ) ? $settings : array();

		$registry             = new IntegrationRegistry();
		$providers            = $registry->form_providers();
		$allowed_actions      = array_keys( $registry->actions() );
		$allowed_duplicates   = array_keys( $registry->duplicate_strategies() );
		$submitted_providers  = isset( $settings['providers'] ) && is_array( $settings['providers'] ) ? $settings['providers'] : array();
		$clean                = array( 'providers' => array() );

		foreach ( $providers as $slug => $provider ) {
			$raw       = isset( $submitted_providers[ $slug ] ) && is_array( $submitted_providers[ $slug ] ) ? $submitted_providers[ $slug ] : array();
			$action    = isset( $raw['action'] ) ? sanitize_key( (string) $raw['action'] ) : 'create_pending_booking';
			$duplicate = isset( $raw['duplicate_strategy'] ) ? sanitize_key( (string) $raw['duplicate_strategy'] ) : 'update_existing';

			$clean['providers'][ $slug ] = array(
				'enabled'            => ! empty( $raw['enabled'] ) ? 1 : 0,
				'action'             => in_array( $action, $allowed_actions, true ) ? $action : 'create_pending_booking',
				'duplicate_strategy' => in_array( $duplicate, $allowed_duplicates, true ) ? $duplicate : 'update_existing',
			);
		}

		return $clean;
	}
}

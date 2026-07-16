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

		$config = wp_parse_args(
			$config,
			array(
				'enabled'            => 0,
				'action'             => 'create_pending_booking',
				'duplicate_strategy' => 'update_existing',
			)
		);

		if ( 'gravity_forms' === $provider ) {
			$config = wp_parse_args(
				$config,
				array(
					'form_id'                  => 0,
					'default_location_id'      => 0,
					'default_start_time'       => '09:00',
					'default_duration_minutes' => 60,
					'booking_visibility'       => 'internal',
					'fields'                   => array(),
				)
			);

			$config['fields'] = wp_parse_args( is_array( $config['fields'] ) ? $config['fields'] : array(), $this->gravity_forms_field_defaults() );
		}

		return $config;
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

			if ( 'gravity_forms' === $slug ) {
				$clean['providers'][ $slug ] = array_merge(
					$clean['providers'][ $slug ],
					$this->sanitize_gravity_forms_settings( $raw )
				);
			}
		}

		return $clean;
	}

	/**
	 * Gravity Forms field mapping defaults.
	 *
	 * @return array<string,string>
	 */
	public function gravity_forms_field_defaults(): array {
		return array(
			'first_name'   => '',
			'last_name'    => '',
			'display_name' => '',
			'email'        => '',
			'phone'        => '',
			'booking_date' => '',
			'start_time'   => '',
			'end_time'     => '',
			'location_id'  => '',
			'guest_count'  => '',
			'guest_names'  => '',
			'notes'        => '',
		);
	}

	/**
	 * Sanitize Gravity Forms provider settings.
	 *
	 * @param array<string,mixed> $raw Raw provider config.
	 * @return array<string,mixed>
	 */
	private function sanitize_gravity_forms_settings( array $raw ): array {
		$visibility = isset( $raw['booking_visibility'] ) ? sanitize_key( (string) $raw['booking_visibility'] ) : 'internal';

		$clean = array(
			'form_id'                  => isset( $raw['form_id'] ) ? absint( $raw['form_id'] ) : 0,
			'default_location_id'      => isset( $raw['default_location_id'] ) ? absint( $raw['default_location_id'] ) : 0,
			'default_start_time'       => $this->sanitize_time( isset( $raw['default_start_time'] ) ? (string) $raw['default_start_time'] : '09:00' ),
			'default_duration_minutes' => isset( $raw['default_duration_minutes'] ) ? max( 15, min( 1440, absint( $raw['default_duration_minutes'] ) ) ) : 60,
			'booking_visibility'       => in_array( $visibility, array( 'internal', 'private', 'public', 'blocked' ), true ) ? $visibility : 'internal',
			'fields'                   => array(),
		);

		$fields = isset( $raw['fields'] ) && is_array( $raw['fields'] ) ? $raw['fields'] : array();

		foreach ( $this->gravity_forms_field_defaults() as $field => $default ) {
			$value                       = isset( $fields[ $field ] ) ? sanitize_text_field( (string) $fields[ $field ] ) : $default;
			$clean['fields'][ $field ] = $value;
		}

		return $clean;
	}

	/**
	 * Sanitize a time value.
	 */
	private function sanitize_time( string $time ): string {
		$time = trim( $time );

		if ( preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $time ) ) {
			return $time;
		}

		$timestamp = strtotime( $time );

		return false === $timestamp ? '09:00' : gmdate( 'H:i', $timestamp );
	}
}

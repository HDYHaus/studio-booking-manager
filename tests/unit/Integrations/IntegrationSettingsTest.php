<?php
/**
 * Integration settings tests.
 *
 * @package StudioBookingManager
 */

declare(strict_types=1);

namespace StudioBookingManager\Tests\Unit\Integrations;

use StudioBookingManager\Integrations\IntegrationSettings;

/**
 * Tests integration settings storage.
 */
final class IntegrationSettingsTest extends TestCase {
	/**
	 * Sanitize provider settings.
	 */
	public function test_sanitize_keeps_allowed_provider_settings(): void {
		$settings = new IntegrationSettings();

		$clean = $settings->sanitize(
			array(
				'providers' => array(
					'gravity_forms'  => array(
						'enabled'            => '1',
						'action'             => 'create_person',
						'duplicate_strategy' => 'skip_existing',
					),
					'contact_form_7' => array(
						'action'             => '<bad>',
						'duplicate_strategy' => 'unknown',
					),
					'unknown'        => array(
						'enabled' => '1',
					),
				),
			)
		);

		$this->assert_same( 1, $clean['providers']['gravity_forms']['enabled'] );
		$this->assert_same( 'create_person', $clean['providers']['gravity_forms']['action'] );
		$this->assert_same( 'skip_existing', $clean['providers']['gravity_forms']['duplicate_strategy'] );
		$this->assert_same( 0, $clean['providers']['contact_form_7']['enabled'] );
		$this->assert_same( 'create_pending_booking', $clean['providers']['contact_form_7']['action'] );
		$this->assert_same( 'update_existing', $clean['providers']['contact_form_7']['duplicate_strategy'] );
		$this->assert_same( false, isset( $clean['providers']['unknown'] ) );
	}

	/**
	 * Sanitizer tolerates invalid input.
	 */
	public function test_sanitize_tolerates_invalid_input(): void {
		$settings = new IntegrationSettings();

		$clean = $settings->sanitize( 'not-an-array' );

		$this->assert_same( 0, $clean['providers']['gravity_forms']['enabled'] );
		$this->assert_same( 'create_pending_booking', $clean['providers']['gravity_forms']['action'] );
		$this->assert_same( 'update_existing', $clean['providers']['gravity_forms']['duplicate_strategy'] );
	}

	/**
	 * Provider reads saved settings over defaults.
	 */
	public function test_provider_reads_saved_settings(): void {
		$this->set_up_test_state();

		$GLOBALS['sbm_test_options'][ IntegrationSettings::OPTION_NAME ] = array(
			'providers' => array(
				'gravity_forms' => array(
					'enabled'            => 1,
					'action'             => 'create_person',
					'duplicate_strategy' => 'skip_existing',
				),
			),
		);

		$config = ( new IntegrationSettings() )->provider( 'gravity_forms' );

		$this->assert_same( 1, $config['enabled'] );
		$this->assert_same( 'create_person', $config['action'] );
		$this->assert_same( 'skip_existing', $config['duplicate_strategy'] );
	}
}

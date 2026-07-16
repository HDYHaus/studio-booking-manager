<?php
/**
 * Integration provider registry.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Lists supported third-party integration providers.
 */
final class IntegrationRegistry {
	/**
	 * Get available form providers.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function form_providers(): array {
		return array(
			'gravity_forms'  => array(
				'name'        => __( 'Gravity Forms', 'studio-booking-manager' ),
				'description' => __( 'Create people and pending bookings from Gravity Forms submissions.', 'studio-booking-manager' ),
				'detected'    => class_exists( 'GFForms' ),
				'phase'       => __( 'Next provider to build', 'studio-booking-manager' ),
			),
			'contact_form_7' => array(
				'name'        => __( 'Contact Form 7', 'studio-booking-manager' ),
				'description' => __( 'Create people and pending bookings from Contact Form 7 submissions.', 'studio-booking-manager' ),
				'detected'    => defined( 'WPCF7_VERSION' ) || class_exists( 'WPCF7' ),
				'phase'       => __( 'Planned', 'studio-booking-manager' ),
			),
			'wpforms'        => array(
				'name'        => __( 'WPForms', 'studio-booking-manager' ),
				'description' => __( 'Create people and pending bookings from WPForms submissions.', 'studio-booking-manager' ),
				'detected'    => function_exists( 'wpforms' ),
				'phase'       => __( 'Planned', 'studio-booking-manager' ),
			),
			'fluent_forms'   => array(
				'name'        => __( 'Fluent Forms', 'studio-booking-manager' ),
				'description' => __( 'Create people and pending bookings from Fluent Forms submissions.', 'studio-booking-manager' ),
				'detected'    => defined( 'FLUENTFORM_VERSION' ) || function_exists( 'wpFluent' ),
				'phase'       => __( 'Planned', 'studio-booking-manager' ),
			),
			'ninja_forms'    => array(
				'name'        => __( 'Ninja Forms', 'studio-booking-manager' ),
				'description' => __( 'Create people and pending bookings from Ninja Forms submissions.', 'studio-booking-manager' ),
				'detected'    => function_exists( 'Ninja_Forms' ),
				'phase'       => __( 'Planned', 'studio-booking-manager' ),
			),
		);
	}

	/**
	 * Get allowed submission actions.
	 *
	 * @return array<string,string>
	 */
	public function actions(): array {
		return array(
			'create_person'          => __( 'Create or update person only', 'studio-booking-manager' ),
			'create_pending_booking' => __( 'Create or update person and pending booking', 'studio-booking-manager' ),
		);
	}

	/**
	 * Get duplicate handling options.
	 *
	 * @return array<string,string>
	 */
	public function duplicate_strategies(): array {
		return array(
			'update_existing' => __( 'Update existing person by email', 'studio-booking-manager' ),
			'skip_existing'   => __( 'Skip if person already exists', 'studio-booking-manager' ),
		);
	}
}

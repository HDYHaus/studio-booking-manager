<?php
/**
 * Gravity Forms integration.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Integrations;

use StudioBookingManager\Bookings\BookingService;
use StudioBookingManager\Logging\Logger;
use StudioBookingManager\People\PersonService;

defined( 'ABSPATH' ) || exit;

/**
 * Creates Studio Booking records from Gravity Forms submissions.
 */
final class GravityFormsIntegration {
	private const PROVIDER = 'gravity_forms';

	/**
	 * Register hooks.
	 */
	public function register(): void {
		if ( ! $this->is_available() || ! ( new IntegrationSettings() )->is_enabled( self::PROVIDER ) ) {
			return;
		}

		add_action( 'gform_after_submission', array( $this, 'handle_submission' ), 10, 2 );
	}

	/**
	 * Determine whether Gravity Forms is active.
	 */
	public function is_available(): bool {
		return class_exists( 'GFForms' ) || class_exists( 'GFAPI' );
	}

	/**
	 * Handle a Gravity Forms submission.
	 *
	 * @param array<string,mixed> $entry Entry data.
	 * @param array<string,mixed> $form Form data.
	 */
	public function handle_submission( array $entry, array $form ): int {
		$config  = ( new IntegrationSettings() )->provider( self::PROVIDER );
		$form_id = isset( $form['id'] ) ? absint( $form['id'] ) : 0;

		if ( empty( $config['enabled'] ) || (int) $config['form_id'] <= 0 || $form_id !== (int) $config['form_id'] ) {
			return 0;
		}

		$person_id = $this->upsert_person( $entry, $form, $config );

		if ( $person_id <= 0 || 'create_pending_booking' !== (string) $config['action'] ) {
			$this->add_entry_note( $entry, __( 'Studio Booking Manager processed this entry but could not create or update a person.', 'studio-booking-manager' ), 'error' );
			return 0;
		}

		return $this->create_pending_booking( $person_id, $entry, $form, $config );
	}

	/**
	 * Reprocess an existing Gravity Forms entry.
	 *
	 * @param int $entry_id Entry ID.
	 */
	public function reprocess_entry( int $entry_id ): int {
		if ( $entry_id <= 0 || ! class_exists( 'GFAPI' ) || ! method_exists( 'GFAPI', 'get_entry' ) || ! method_exists( 'GFAPI', 'get_form' ) ) {
			return 0;
		}

		$entry = \GFAPI::get_entry( $entry_id );

		if ( ! is_array( $entry ) || empty( $entry['form_id'] ) ) {
			return 0;
		}

		$form = \GFAPI::get_form( absint( $entry['form_id'] ) );

		if ( ! is_array( $form ) ) {
			return 0;
		}

		return $this->handle_submission( $entry, $form );
	}

	/**
	 * Create or update a person from mapped fields.
	 *
	 * @param array<string,mixed> $entry Entry data.
	 * @param array<string,mixed> $form Form data.
	 * @param array<string,mixed> $config Provider config.
	 */
	private function upsert_person( array $entry, array $form, array $config ): int {
		$fields = $this->fields( $config );
		$email  = sanitize_email( $this->entry_value( $entry, (string) $fields['email'] ) );
		$notes  = $this->source_note( $entry, $form );
		$mapped_notes = $this->entry_value( $entry, (string) $fields['notes'] );
		$custom_notes = $this->custom_field_notes( $entry, $config );

		if ( '' !== $mapped_notes ) {
			$notes .= "\n\n" . $mapped_notes;
		}

		if ( '' !== $custom_notes ) {
			$notes .= "\n\n" . $custom_notes;
		}

		$data = array(
			'first_name'   => $this->entry_value( $entry, (string) $fields['first_name'] ),
			'last_name'    => $this->entry_value( $entry, (string) $fields['last_name'] ),
			'display_name' => $this->entry_value( $entry, (string) $fields['display_name'] ),
			'email'        => $email,
			'phone'        => $this->entry_value( $entry, (string) $fields['phone'] ),
			'status'       => 'active',
			'notes'        => $notes,
		);

		$service = new PersonService();
		$person  = '' !== $email ? $service->find_by_email( $email ) : null;

		if ( null !== $person ) {
			if ( 'update_existing' === (string) $config['duplicate_strategy'] ) {
				$data['id'] = (int) $person->id;
				return $service->save( $data );
			}

			return (int) $person->id;
		}

		return $service->save( $data );
	}

	/**
	 * Create a pending booking from mapped fields.
	 *
	 * @param int                 $person_id Person ID.
	 * @param array<string,mixed> $entry Entry data.
	 * @param array<string,mixed> $form Form data.
	 * @param array<string,mixed> $config Provider config.
	 */
	private function create_pending_booking( int $person_id, array $entry, array $form, array $config ): int {
		$fields      = $this->fields( $config );
		$location_id = absint( $this->entry_value( $entry, (string) $fields['location_id'] ) );

		if ( $location_id <= 0 ) {
			$location_id = isset( $config['default_location_id'] ) ? absint( $config['default_location_id'] ) : 0;
		}

		$starts_at = $this->booking_start( $entry, $fields, isset( $config['default_start_time'] ) ? (string) $config['default_start_time'] : '09:00' );
		$ends_at   = $this->booking_end( $entry, $fields, $starts_at, isset( $config['default_duration_minutes'] ) ? absint( $config['default_duration_minutes'] ) : 60 );

		if ( $location_id <= 0 || '' === $starts_at || '' === $ends_at ) {
			$message = __( 'Studio Booking Manager created or updated the person, but the entry did not contain enough booking data for a pending booking.', 'studio-booking-manager' );
			Logger::log( $message, 'warning' );
			$this->add_entry_note( $entry, $message, 'error' );
			return 0;
		}

		$booking = array(
			'person_id'            => $person_id,
			'location_id'          => $location_id,
			'status'               => 'pending',
			'visibility'           => isset( $config['booking_visibility'] ) ? sanitize_key( (string) $config['booking_visibility'] ) : 'internal',
			'starts_at'            => $starts_at,
			'ends_at'              => $ends_at,
			'guest_count'          => absint( $this->entry_value( $entry, (string) $fields['guest_count'] ) ),
			'guest_names'          => $this->entry_value( $entry, (string) $fields['guest_names'] ),
			'calendar_sync_status' => 'not_synced',
			'notes'                => $this->booking_notes( $entry, $form, $config ),
		);

		$booking_service = new BookingService();
		$booking_id      = $booking_service->save( $booking );

		if ( $booking_id <= 0 ) {
			$message = sprintf(
				/* translators: %s: booking error code. */
				__( 'Studio Booking Manager could not create a pending booking. Error: %s', 'studio-booking-manager' ),
				$booking_service->last_error()
			);
			Logger::log( $message, 'warning' );
			$this->add_entry_note( $entry, $message, 'error' );
		} else {
			$this->add_entry_note(
				$entry,
				sprintf(
					/* translators: %d: booking ID. */
					__( 'Studio Booking Manager created pending booking #%d from this entry.', 'studio-booking-manager' ),
					$booking_id
				),
				'success'
			);
		}

		return $booking_id;
	}

	/**
	 * Get mapped fields from config.
	 *
	 * @param array<string,mixed> $config Provider config.
	 * @return array<string,string>
	 */
	private function fields( array $config ): array {
		$settings = new IntegrationSettings();
		$fields   = isset( $config['fields'] ) && is_array( $config['fields'] ) ? $config['fields'] : array();

		return wp_parse_args( $fields, $settings->gravity_forms_field_defaults() );
	}

	/**
	 * Build a booking start datetime.
	 *
	 * @param array<string,mixed> $entry Entry data.
	 * @param array<string,string> $fields Field mapping.
	 */
	private function booking_start( array $entry, array $fields, string $default_start_time ): string {
		$date = $this->entry_value( $entry, $fields['booking_date'] );
		$time = $this->entry_value( $entry, $fields['start_time'] );

		if ( '' === $time ) {
			$time = $default_start_time;
		}

		return $this->normalize_datetime( trim( $date . ' ' . $time ) );
	}

	/**
	 * Build a booking end datetime.
	 *
	 * @param array<string,mixed>  $entry Entry data.
	 * @param array<string,string> $fields Field mapping.
	 * @param string               $starts_at Start datetime.
	 * @param int                  $default_duration_minutes Default duration.
	 */
	private function booking_end( array $entry, array $fields, string $starts_at, int $default_duration_minutes ): string {
		$date = $this->entry_value( $entry, $fields['booking_date'] );
		$time = $this->entry_value( $entry, $fields['end_time'] );

		if ( '' !== $time ) {
			return $this->normalize_datetime( trim( $date . ' ' . $time ) );
		}

		if ( '' === $starts_at ) {
			return '';
		}

		$duration_minutes = $this->duration_minutes( $entry, $fields, $default_duration_minutes );

		return gmdate( 'Y-m-d H:i:s', strtotime( '+' . $duration_minutes . ' minutes', strtotime( $starts_at ) ) );
	}

	/**
	 * Determine booking duration.
	 *
	 * @param array<string,mixed>  $entry Entry data.
	 * @param array<string,string> $fields Field mapping.
	 * @param int                  $default_duration_minutes Default duration.
	 */
	private function duration_minutes( array $entry, array $fields, int $default_duration_minutes ): int {
		$hours = $this->entry_value( $entry, $fields['duration_hours'] );

		if ( '' !== $hours ) {
			$normalized_hours = $this->numeric_value( $hours );

			if ( null !== $normalized_hours ) {
				return max( 15, min( 1440, (int) round( $normalized_hours * 60 ) ) );
			}
		}

		return max( 15, min( 1440, $default_duration_minutes ) );
	}

	/**
	 * Extract a numeric value from form labels such as "2 Hours".
	 */
	private function numeric_value( string $value ): ?float {
		$value = trim( $value );

		if ( '' === $value ) {
			return null;
		}

		if ( is_numeric( $value ) ) {
			return (float) $value;
		}

		if ( preg_match( '/\d+(?:\.\d+)?/', $value, $matches ) ) {
			return (float) $matches[0];
		}

		return null;
	}

	/**
	 * Normalize datetime to storage format.
	 */
	private function normalize_datetime( string $value ): string {
		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value );

		return false === $timestamp ? '' : gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Read a mapped value from a Gravity Forms entry.
	 *
	 * @param array<string,mixed> $entry Entry data.
	 * @param string              $field_id Field/input ID.
	 */
	private function entry_value( array $entry, string $field_id ): string {
		$field_id = trim( $field_id );

		if ( '' === $field_id ) {
			return '';
		}

		if ( array_key_exists( $field_id, $entry ) ) {
			return sanitize_text_field( (string) $entry[ $field_id ] );
		}

		$normalized = (string) (float) $field_id;

		return array_key_exists( $normalized, $entry ) ? sanitize_text_field( (string) $entry[ $normalized ] ) : '';
	}

	/**
	 * Build booking notes with source, custom fields, and mapped notes.
	 *
	 * @param array<string,mixed> $entry Entry data.
	 * @param array<string,mixed> $form Form data.
	 * @param array<string,mixed> $config Provider config.
	 */
	private function booking_notes( array $entry, array $form, array $config ): string {
		$fields = $this->fields( $config );
		$notes  = $this->source_note( $entry, $form );
		$custom = $this->custom_field_notes( $entry, $config );
		$mapped = $this->entry_value( $entry, (string) $fields['notes'] );

		if ( '' !== $custom ) {
			$notes .= "\n\n" . $custom;
		}

		if ( '' !== $mapped ) {
			$notes .= "\n\n" . $mapped;
		}

		return $notes;
	}

	/**
	 * Build notes from configured custom fields.
	 *
	 * @param array<string,mixed> $entry Entry data.
	 * @param array<string,mixed> $config Provider config.
	 */
	private function custom_field_notes( array $entry, array $config ): string {
		$custom_fields = isset( $config['custom_fields'] ) && is_array( $config['custom_fields'] ) ? $config['custom_fields'] : array();
		$lines         = array();

		foreach ( $custom_fields as $custom_field ) {
			if ( ! is_array( $custom_field ) ) {
				continue;
			}

			$label = isset( $custom_field['label'] ) ? sanitize_text_field( (string) $custom_field['label'] ) : '';
			$field = isset( $custom_field['field'] ) ? (string) $custom_field['field'] : '';
			$value = $this->entry_value( $entry, $field );

			if ( '' === $label || '' === $value ) {
				continue;
			}

			$lines[] = $label . ': ' . $value;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Build an audit note for created records.
	 *
	 * @param array<string,mixed> $entry Entry data.
	 * @param array<string,mixed> $form Form data.
	 */
	private function source_note( array $entry, array $form ): string {
		$form_title = isset( $form['title'] ) ? sanitize_text_field( (string) $form['title'] ) : __( 'Gravity Forms', 'studio-booking-manager' );
		$entry_id   = isset( $entry['id'] ) ? absint( $entry['id'] ) : 0;

		return sprintf(
			/* translators: 1: form title, 2: entry ID. */
			__( 'Created from Gravity Forms form "%1$s", entry #%2$d.', 'studio-booking-manager' ),
			$form_title,
			$entry_id
		);
	}

	/**
	 * Add a processing note to the Gravity Forms entry when supported.
	 *
	 * @param array<string,mixed> $entry Entry data.
	 * @param string              $message Note message.
	 * @param string              $type Note type.
	 */
	private function add_entry_note( array $entry, string $message, string $type = 'success' ): void {
		if ( empty( $entry['id'] ) || ! class_exists( 'GFAPI' ) || ! method_exists( 'GFAPI', 'add_note' ) ) {
			return;
		}

		\GFAPI::add_note( absint( $entry['id'] ), 0, 'Studio Booking Manager', $message, $type );
	}
}

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
	public function handle_submission( array $entry, array $form ): void {
		$config  = ( new IntegrationSettings() )->provider( self::PROVIDER );
		$form_id = isset( $form['id'] ) ? absint( $form['id'] ) : 0;

		if ( empty( $config['enabled'] ) || (int) $config['form_id'] <= 0 || $form_id !== (int) $config['form_id'] ) {
			return;
		}

		$person_id = $this->upsert_person( $entry, $form, $config );

		if ( $person_id <= 0 || 'create_pending_booking' !== (string) $config['action'] ) {
			return;
		}

		$this->create_pending_booking( $person_id, $entry, $form, $config );
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
			Logger::log( 'Gravity Forms submission created a person but did not contain enough booking data for a pending booking.', 'warning' );
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
			Logger::log( 'Gravity Forms submission could not create a pending booking. Error: ' . $booking_service->last_error(), 'warning' );
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
		$end  = $this->normalize_datetime( trim( $date . ' ' . $time ) );

		if ( '' !== $end || '' === $starts_at ) {
			return $end;
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

		if ( '' !== $hours && is_numeric( $hours ) ) {
			return max( 15, min( 1440, (int) round( (float) $hours * 60 ) ) );
		}

		return max( 15, min( 1440, $default_duration_minutes ) );
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
}

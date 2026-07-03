<?php
/**
 * Google Calendar booking sync.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Calendar;

use StudioBookingManager\Bookings\BookingRepository;
use StudioBookingManager\Locations\LocationService;
use StudioBookingManager\People\PersonService;

defined( 'ABSPATH' ) || exit;

/**
 * Syncs booking lifecycle events to Google Calendar.
 */
final class GoogleCalendarSync {
	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'sbm_booking_created', array( $this, 'sync_booking' ) );
		add_action( 'sbm_booking_updated', array( $this, 'sync_booking' ) );
		add_action( 'sbm_booking_archived', array( $this, 'delete_booking_event' ) );
		add_action( 'sbm_booking_calendar_sync_requested', array( $this, 'sync_booking' ) );
	}

	/**
	 * Sync a booking.
	 *
	 * @param object $booking Booking row.
	 */
	public function sync_booking( object $booking ): void {
		$repository = new BookingRepository();
		$client     = new GoogleCalendarClient();

		if ( ! $client->is_configured() ) {
			$repository->update_calendar_sync( (int) $booking->id, 'disabled', __( 'Google Calendar sync is not configured.', 'studio-booking-manager' ) );
			return;
		}

		if ( in_array( (string) $booking->status, array( 'cancelled', 'archived', 'no_show' ), true ) ) {
			$this->delete_booking_event( $booking );
			return;
		}

		$event = $this->event_payload( $booking );

		if ( ! empty( $booking->calendar_event_id ) ) {
			$updated = $client->update_event( (string) $booking->calendar_event_id, $event );
			if ( $updated ) {
				$repository->update_calendar_sync( (int) $booking->id, 'synced', '', (string) $booking->calendar_event_id );
				return;
			}

			$repository->update_calendar_sync( (int) $booking->id, 'failed', $client->last_error() );
			return;
		}

		$event_id = $client->create_event( $event );
		if ( '' !== $event_id ) {
			$repository->update_calendar_sync( (int) $booking->id, 'synced', '', $event_id );
			return;
		}

		$repository->update_calendar_sync( (int) $booking->id, 'failed', $client->last_error() );
	}

	/**
	 * Delete a booking event.
	 *
	 * @param object $booking Booking row.
	 */
	public function delete_booking_event( object $booking ): void {
		$repository = new BookingRepository();

		if ( empty( $booking->calendar_event_id ) ) {
			$repository->update_calendar_sync( (int) $booking->id, 'deleted' );
			return;
		}

		$client = new GoogleCalendarClient();

		if ( ! $client->is_configured() ) {
			$repository->update_calendar_sync( (int) $booking->id, 'disabled', __( 'Google Calendar sync is not configured.', 'studio-booking-manager' ) );
			return;
		}

		if ( $client->delete_event( (string) $booking->calendar_event_id ) ) {
			$repository->update_calendar_sync( (int) $booking->id, 'deleted' );
			return;
		}

		$repository->update_calendar_sync( (int) $booking->id, 'failed', $client->last_error() );
	}

	/**
	 * Build event payload.
	 *
	 * @param object $booking Booking row.
	 * @return array<string,mixed>
	 */
	private function event_payload( object $booking ): array {
		$person   = ( new PersonService() )->find( (int) $booking->person_id );
		$location = ( new LocationService() )->find( (int) $booking->location_id );
		$timezone = $location instanceof \stdClass && ! empty( $location->timezone ) ? (string) $location->timezone : wp_timezone_string();
		$person_name = $person instanceof \stdClass && ! empty( $person->display_name ) ? (string) $person->display_name : __( 'Unknown person', 'studio-booking-manager' );
		$location_name = $location instanceof \stdClass && ! empty( $location->name ) ? (string) $location->name : __( 'Unknown location', 'studio-booking-manager' );

		return array(
			'summary'     => sprintf(
				/* translators: 1: person name, 2: location name. */
				__( 'Booking: %1$s at %2$s', 'studio-booking-manager' ),
				$person_name,
				$location_name
			),
			'location'    => $location_name,
			'description' => $this->description( $booking ),
			'start'       => array(
				'dateTime' => $this->calendar_datetime( (string) $booking->starts_at ),
				'timeZone' => $timezone,
			),
			'end'         => array(
				'dateTime' => $this->calendar_datetime( (string) $booking->ends_at ),
				'timeZone' => $timezone,
			),
		);
	}

	/**
	 * Build event description.
	 *
	 * @param object $booking Booking row.
	 */
	private function description( object $booking ): string {
		$lines = array(
			sprintf(
				/* translators: %d: booking ID. */
				__( 'Studio Booking Manager booking #%d', 'studio-booking-manager' ),
				absint( $booking->id )
			),
		);

		if ( ! empty( $booking->guest_count ) ) {
			$lines[] = sprintf(
				/* translators: %d: guest count. */
				__( 'Guests: %d', 'studio-booking-manager' ),
				absint( $booking->guest_count )
			);
		}

		if ( ! empty( $booking->guest_names ) ) {
			$lines[] = __( 'Guest names:', 'studio-booking-manager' );
			$lines[] = (string) $booking->guest_names;
		}

		if ( ! empty( $booking->notes ) ) {
			$lines[] = __( 'Notes:', 'studio-booking-manager' );
			$lines[] = (string) $booking->notes;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Format a datetime for Google Calendar.
	 *
	 * @param string $value Stored datetime.
	 */
	private function calendar_datetime( string $value ): string {
		$timestamp = strtotime( $value );

		return false === $timestamp ? current_time( 'Y-m-d\TH:i:s' ) : gmdate( 'Y-m-d\TH:i:s', $timestamp );
	}
}

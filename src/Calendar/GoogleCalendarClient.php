<?php
/**
 * Google Calendar HTTP client.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Calendar;

defined( 'ABSPATH' ) || exit;

/**
 * Talks to Google Calendar using a service account.
 */
final class GoogleCalendarClient {
	/**
	 * Settings.
	 *
	 * @var array<string,mixed>
	 */
	private array $settings;

	/**
	 * Last error.
	 *
	 * @var string
	 */
	private string $last_error = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$settings = get_option( 'sbm_settings', array() );

		$this->settings = is_array( $settings ) ? $settings : array();
	}

	/**
	 * Determine whether sync is configured.
	 */
	public function is_configured(): bool {
		return ! empty( $this->settings['google_calendar_enabled'] )
			&& ! empty( $this->settings['google_calendar_id'] )
			&& ! empty( $this->settings['google_calendar_service_account_json'] );
	}

	/**
	 * Get the last error message.
	 */
	public function last_error(): string {
		return $this->last_error;
	}

	/**
	 * Create a calendar event.
	 *
	 * @param array<string,mixed> $event Event payload.
	 * @return string
	 */
	public function create_event( array $event ): string {
		$response = $this->request( 'POST', $this->events_url(), $event );

		return isset( $response['id'] ) ? sanitize_text_field( (string) $response['id'] ) : '';
	}

	/**
	 * Update a calendar event.
	 *
	 * @param string              $event_id Event ID.
	 * @param array<string,mixed> $event Event payload.
	 * @return bool
	 */
	public function update_event( string $event_id, array $event ): bool {
		$response = $this->request( 'PATCH', $this->event_url( $event_id ), $event );

		return ! empty( $response['id'] );
	}

	/**
	 * Delete a calendar event.
	 *
	 * @param string $event_id Event ID.
	 * @return bool
	 */
	public function delete_event( string $event_id ): bool {
		$this->request( 'DELETE', $this->event_url( $event_id ) );

		return '' === $this->last_error;
	}

	/**
	 * Make an authenticated request.
	 *
	 * @param string              $method HTTP method.
	 * @param string              $url URL.
	 * @param array<string,mixed> $body Request body.
	 * @return array<string,mixed>
	 */
	private function request( string $method, string $url, array $body = array() ): array {
		$this->last_error = '';
		$token            = $this->access_token();

		if ( '' === $token ) {
			return array();
		}

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 15,
		);

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			return array();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			return is_array( $decoded ) ? $decoded : array();
		}

		$this->last_error = $this->response_error( $response );

		return array();
	}

	/**
	 * Get an access token.
	 */
	private function access_token(): string {
		$cache_key = 'sbm_google_calendar_access_token';
		$cached    = get_transient( $cache_key );

		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$credentials = $this->credentials();
		if ( empty( $credentials['client_email'] ) || empty( $credentials['private_key'] ) ) {
			$this->last_error = __( 'Google Calendar service account credentials are incomplete.', 'studio-booking-manager' );
			return '';
		}

		$now = time();
		$jwt = $this->jwt(
			array(
				'iss'   => $credentials['client_email'],
				'scope' => 'https://www.googleapis.com/auth/calendar',
				'aud'   => 'https://oauth2.googleapis.com/token',
				'iat'   => $now,
				'exp'   => $now + 3600,
			),
			(string) $credentials['private_key']
		);

		if ( '' === $jwt ) {
			return '';
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 15,
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			return '';
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) || empty( $decoded['access_token'] ) ) {
			$this->last_error = $this->response_error( $response );
			return '';
		}

		$token      = sanitize_text_field( (string) $decoded['access_token'] );
		$expires_in = isset( $decoded['expires_in'] ) ? max( 60, absint( $decoded['expires_in'] ) - 60 ) : 3000;
		set_transient( $cache_key, $token, $expires_in );

		return $token;
	}

	/**
	 * Decode service account credentials.
	 *
	 * @return array<string,mixed>
	 */
	private function credentials(): array {
		$json = isset( $this->settings['google_calendar_service_account_json'] ) ? (string) $this->settings['google_calendar_service_account_json'] : '';
		$data = json_decode( $json, true );

		if ( '' !== trim( $json ) && ! is_array( $data ) ) {
			$this->last_error = __( 'Google Calendar service account JSON could not be decoded. Paste the full JSON key file contents.', 'studio-booking-manager' );
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Build a signed JWT.
	 *
	 * @param array<string,mixed> $claims JWT claims.
	 * @param string              $private_key Private key.
	 */
	private function jwt( array $claims, string $private_key ): string {
		if ( ! function_exists( 'openssl_sign' ) || ! function_exists( 'openssl_pkey_get_private' ) ) {
			$this->last_error = __( 'Google Calendar sync requires the PHP OpenSSL extension.', 'studio-booking-manager' );
			return '';
		}

		$header = array(
			'alg' => 'RS256',
			'typ' => 'JWT',
		);

		$segments = array(
			$this->base64url( (string) wp_json_encode( $header ) ),
			$this->base64url( (string) wp_json_encode( $claims ) ),
		);

		$private_key = $this->normalize_private_key( $private_key );
		$key         = openssl_pkey_get_private( $private_key );

		if ( false === $key ) {
			$this->last_error = __( 'Google Calendar private key could not be read. Paste the full service account JSON key file, including the BEGIN PRIVATE KEY block.', 'studio-booking-manager' );
			return '';
		}

		$signature = '';
		$signed    = openssl_sign( implode( '.', $segments ), $signature, $key, 'sha256WithRSAEncryption' );

		if ( ! $signed ) {
			$this->last_error = $this->openssl_error();
			return '';
		}

		$segments[] = $this->base64url( $signature );

		return implode( '.', $segments );
	}

	/**
	 * Normalize a private key from JSON storage.
	 *
	 * @param string $private_key Private key value.
	 */
	private function normalize_private_key( string $private_key ): string {
		$private_key = trim( $private_key );
		$private_key = str_replace( array( "\r\n", "\r", '\\n' ), "\n", $private_key );

		return $private_key . "\n";
	}

	/**
	 * Get a safe OpenSSL error message.
	 */
	private function openssl_error(): string {
		$error = function_exists( 'openssl_error_string' ) ? openssl_error_string() : false;

		if ( is_string( $error ) && '' !== $error ) {
			return sprintf(
				/* translators: %s: OpenSSL error. */
				__( 'Could not sign Google Calendar authentication request: %s', 'studio-booking-manager' ),
				sanitize_text_field( $error )
			);
		}

		return __( 'Could not sign Google Calendar authentication request.', 'studio-booking-manager' );
	}

	/**
	 * Base64 URL encode.
	 */
	private function base64url( string $value ): string {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	/**
	 * Calendar events collection URL.
	 */
	private function events_url(): string {
		return 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode( (string) $this->settings['google_calendar_id'] ) . '/events';
	}

	/**
	 * Calendar event URL.
	 *
	 * @param string $event_id Event ID.
	 */
	private function event_url( string $event_id ): string {
		return $this->events_url() . '/' . rawurlencode( $event_id );
	}

	/**
	 * Build a readable response error.
	 *
	 * @param array<string,mixed>|\WP_Error $response Response.
	 */
	private function response_error( $response ): string {
		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( is_array( $decoded ) && isset( $decoded['error']['message'] ) ) {
			return sanitize_text_field( (string) $decoded['error']['message'] );
		}

		return sprintf(
			/* translators: %d: HTTP response code. */
			__( 'Google Calendar returned HTTP %d.', 'studio-booking-manager' ),
			(int) wp_remote_retrieve_response_code( $response )
		);
	}
}

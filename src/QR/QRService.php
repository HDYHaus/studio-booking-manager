<?php
/**
 * QR helpers.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\QR;

defined( 'ABSPATH' ) || exit;

/**
 * Builds signed QR URLs and output.
 */
final class QRService {
	/**
	 * Build a signed QR check-in URL for a person.
	 *
	 * @param object $person Person row.
	 */
	public function person_checkin_url( object $person ): string {
		$token = isset( $person->qr_token ) ? (string) $person->qr_token : '';
		$exp   = $this->expiry_timestamp();
		$value = $token . '.' . $exp;
		$sig   = $this->signature( $value );

		return add_query_arg(
			array(
				'page' => 'sbm-qr-checkin',
				'qr'   => rawurlencode( $value . '.' . $sig ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Resolve a signed QR value.
	 *
	 * @param string $qr QR value.
	 * @return array{token:string,error:string}
	 */
	public function resolve( string $qr ): array {
		$qr    = sanitize_text_field( rawurldecode( $qr ) );
		$parts = explode( '.', $qr );

		if ( 3 !== count( $parts ) ) {
			return array(
				'token' => '',
				'error' => 'invalid_qr',
			);
		}

		$token = sanitize_text_field( $parts[0] );
		$exp   = absint( $parts[1] );
		$sig   = sanitize_text_field( $parts[2] );
		$value = $token . '.' . $exp;

		if ( '' === $token || '' === $sig || ! hash_equals( $this->signature( $value ), $sig ) ) {
			return array(
				'token' => '',
				'error' => 'invalid_qr',
			);
		}

		if ( $exp > 0 && time() > $exp ) {
			return array(
				'token' => '',
				'error' => 'expired_qr',
			);
		}

		return array(
			'token' => $token,
			'error' => '',
		);
	}

	/**
	 * Build a person label for QR output.
	 *
	 * @param object $person Person row.
	 */
	public function person_label( object $person ): string {
		$settings = $this->settings();
		$parts    = array();

		if ( ! isset( $settings['qr_show_person_name'] ) || ! empty( $settings['qr_show_person_name'] ) ) {
			$parts[] = (string) $person->display_name;
		}

		if ( ! empty( $settings['qr_show_person_email'] ) && ! empty( $person->email ) ) {
			$parts[] = (string) $person->email;
		}

		return implode( ' - ', array_filter( $parts ) );
	}

	/**
	 * Token lifetime days.
	 */
	public function lifetime_days(): int {
		$settings = $this->settings();

		return isset( $settings['qr_token_lifetime_days'] ) ? max( 0, min( 3650, absint( $settings['qr_token_lifetime_days'] ) ) ) : 365;
	}

	/**
	 * Expiry timestamp.
	 */
	private function expiry_timestamp(): int {
		$days = $this->lifetime_days();

		return $days > 0 ? time() + ( DAY_IN_SECONDS * $days ) : 0;
	}

	/**
	 * Signature.
	 *
	 * @param string $value Value.
	 */
	private function signature( string $value ): string {
		return substr( hash_hmac( 'sha256', $value, wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * Settings.
	 *
	 * @return array<string,mixed>
	 */
	private function settings(): array {
		$settings = get_option( 'sbm_settings', array() );

		return is_array( $settings ) ? $settings : array();
	}
}

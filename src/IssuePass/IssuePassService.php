<?php
/**
 * Issue pass service.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\IssuePass;

use StudioBookingManager\Access\AccessService;
use StudioBookingManager\Locations\LocationService;
use StudioBookingManager\PassTypes\PassAccessMapper;
use StudioBookingManager\PassTypes\PassTypeService;
use StudioBookingManager\People\PersonService;

defined( 'ABSPATH' ) || exit;

/**
 * Business logic for issuing passes.
 */
final class IssuePassService {
	/**
	 * Pass type service.
	 *
	 * @var PassTypeService
	 */
	private PassTypeService $pass_types;

	/**
	 * Person service.
	 *
	 * @var PersonService
	 */
	private PersonService $people;

	/**
	 * Location service.
	 *
	 * @var LocationService
	 */
	private LocationService $locations;

	/**
	 * Access service.
	 *
	 * @var AccessService
	 */
	private AccessService $access;

	/**
	 * Pass access mapper.
	 *
	 * @var PassAccessMapper
	 */
	private PassAccessMapper $mapper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->pass_types = new PassTypeService();
		$this->people     = new PersonService();
		$this->locations  = new LocationService();
		$this->access     = new AccessService();
		$this->mapper     = new PassAccessMapper();
	}

	/**
	 * Issue a pass for a person and location.
	 *
	 * @param int $pass_id Pass type ID.
	 * @param int $person_id Person ID.
	 * @param int $location_id Location ID.
	 * @return array<string,mixed>
	 */
	public function issue( int $pass_id, int $person_id, int $location_id ): array {
		$pass = $this->pass_types->find( $pass_id );

		if ( null === $pass || 'active' !== $pass->status ) {
			return array(
				'success' => false,
				'message' => __( 'This pass is not available for issue.', 'studio-booking-manager' ),
			);
		}

		$person = $this->people->find( $person_id );
		if ( null === $person ) {
			return array(
				'success' => false,
				'message' => __( 'Selected person not found.', 'studio-booking-manager' ),
			);
		}

		$location = $this->locations->find( $location_id );
		if ( null === $location ) {
			return array(
				'success' => false,
				'message' => __( 'Selected location not found.', 'studio-booking-manager' ),
			);
		}

		$access_data = $this->mapper->map( $pass, $person_id, $location_id );
		$duplicate = $this->access->find_duplicate_active(
			$person_id,
			$location_id,
			$access_data['access_type'],
			$access_data['total_credits'] ?? null,
			$access_data['weekly_limit'] ?? null,
			$access_data['guest_limit'],
			$access_data['expires_at'] ?? null
		);

		$access_id = $this->access->save( $access_data );

		if ( $access_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Unable to create the access record.', 'studio-booking-manager' ),
			);
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `sbm_` is the documented public API prefix for Studio Booking Manager.
		do_action( 'sbm_pass_issued', $access_id, $pass, $person, $location );

		return array(
			'success' => true,
			'access_id' => $access_id,
			'warning' => null !== $duplicate ? 'duplicate' : '',
		);
	}
}

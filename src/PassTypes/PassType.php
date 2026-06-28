<?php
/**
 * Pass type model.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\PassTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Represents a pass type template.
 */
final class PassType {
	/**
	 * The pass type ID.
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * The pass name.
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * The pass description.
	 *
	 * @var string
	 */
	public string $description;

	/**
	 * The pass behaviour.
	 *
	 * @var string
	 */
	public string $behaviour;

	/**
	 * Number of visits.
	 *
	 * @var int|null
	 */
	public ?int $number_of_visits;

	/**
	 * Maximum visits per week.
	 *
	 * @var int|null
	 */
	public ?int $maximum_visits_per_week;

	/**
	 * Guest allowance.
	 *
	 * @var int
	 */
	public int $guest_allowance;

	/**
	 * Whether booking is required.
	 *
	 * @var bool
	 */
	public bool $booking_required;

	/**
	 * Valid for (days).
	 *
	 * @var int|null
	 */
	public ?int $valid_for;

	/**
	 * Status.
	 *
	 * @var string
	 */
	public string $status;

	/**
	 * Created at.
	 *
	 * @var string
	 */
	public string $created_at;

	/**
	 * Updated at.
	 *
	 * @var string
	 */
	public string $updated_at;

	/**
	 * Constructor.
	 *
	 * @param object $data Raw row data.
	 */
	public function __construct( object $data ) {
		$this->id = isset( $data->id ) ? absint( $data->id ) : 0;
		$this->name = isset( $data->name ) ? (string) $data->name : '';
		$this->description = isset( $data->description ) ? (string) $data->description : '';
		$this->behaviour = isset( $data->behaviour ) ? (string) $data->behaviour : '';
		$this->number_of_visits = isset( $data->number_of_visits ) ? absint( $data->number_of_visits ) : null;
		$this->maximum_visits_per_week = isset( $data->maximum_visits_per_week ) ? absint( $data->maximum_visits_per_week ) : null;
		$this->guest_allowance = isset( $data->guest_allowance ) ? absint( $data->guest_allowance ) : 0;
		$this->booking_required = ! empty( $data->booking_required );
		$this->valid_for = isset( $data->valid_for ) ? absint( $data->valid_for ) : null;
		$this->status = isset( $data->status ) ? (string) $data->status : 'active';
		$this->created_at = isset( $data->created_at ) ? (string) $data->created_at : '';
		$this->updated_at = isset( $data->updated_at ) ? (string) $data->updated_at : '';
	}
}

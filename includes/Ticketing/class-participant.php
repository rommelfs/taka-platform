<?php
/**
 * Native ticketing participant value object placeholder.
 *
 * Intended future fields: id, order_id, event_id, first_name, last_name,
 * email, dojo, style, association, rank, country, dietary_notes, allergies,
 * notes, checkin_status, created_at and updated_at.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Ticketing_Participant {
	private $data;

	public function __construct( $data = array() ) {
		$this->data = is_array( $data ) ? $data : array();
	}

	public function get( $field, $default = null ) {
		return array_key_exists( $field, $this->data ) ? $this->data[ $field ] : $default;
	}

	public function to_array() {
		return $this->data;
	}
}

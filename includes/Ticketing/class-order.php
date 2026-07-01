<?php
/**
 * Native ticketing order value object placeholder.
 *
 * Intended future fields: id, order_number, event_id, ticket_type_id,
 * buyer_data, participant_data, total_amount, currency, payment_method,
 * payment_status, order_status, created_at and updated_at.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Ticketing_Order {
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

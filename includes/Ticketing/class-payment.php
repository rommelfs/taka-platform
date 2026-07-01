<?php
/**
 * Native ticketing payment value object placeholder.
 *
 * Intended future fields: id, order_id, provider, amount, currency, status,
 * transaction_id, provider_payload, created_at and updated_at.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Ticketing_Payment {
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

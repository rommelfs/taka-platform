<?php
/**
 * Pay-at-the-door payment provider for native TAKA Ticketing.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Ticketing_Pay_At_Door_Provider implements TAKA_Ticketing_Payment_Provider_Interface {
	public function get_id() {
		return 'pay_at_door';
	}

	public function get_label() {
		return __( 'Pay at the Door', 'taka-platform' );
	}

	public function is_enabled() {
		return true;
	}

	public function get_public_instructions( $order ) {
		$order_data = is_object( $order ) && method_exists( $order, 'to_array' ) ? $order->to_array() : (array) $order;
		$event_id = absint( $order_data['event_id'] ?? 0 );
		$instructions = $event_id ? (string) get_post_meta( $event_id, TAKA_Ticketing_Module::PAY_AT_DOOR_INSTRUCTIONS_META, true ) : '';

		return array(
			'instructions' => sanitize_textarea_field( $instructions ),
			'message'      => __( 'Please pay your admission at the registration desk before entering the seminar. Payment is required before participation.', 'taka-platform' ),
		);
	}

	public function create_payment( $order ) {
		return array(
			'provider'   => $this->get_id(),
			'status'     => 'pending',
			'created_at' => current_time( 'mysql' ),
		);
	}

	public function handle_return( $request ) {
		return null;
	}

	public function handle_webhook( $request ) {
		return null;
	}

	public function mark_paid( $order, $transaction_id ) {
		return array(
			'order'          => $order,
			'transaction_id' => sanitize_text_field( $transaction_id ),
			'payment_status' => 'paid',
		);
	}

	public function refund( $order ) {
		return new WP_Error( 'taka_ticketing_refund_not_supported', __( 'Pay-at-the-door refunds are not implemented yet.', 'taka-platform' ) );
	}

	public function get_admin_fields() {
		return array(
			'enabled'           => array( 'type' => 'checkbox', 'label' => __( 'Enable Pay at the Door', 'taka-platform' ) ),
			'instructions_text' => array( 'type' => 'textarea', 'label' => __( 'Additional payment instructions', 'taka-platform' ) ),
		);
	}
}

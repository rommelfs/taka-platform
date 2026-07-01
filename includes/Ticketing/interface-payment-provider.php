<?php
/**
 * Native TAKA Ticketing payment provider contract.
 *
 * Checkout providers implement this contract so bank transfer, pay at the door
 * and future API providers share one payment surface.
 */

defined( 'ABSPATH' ) || exit;

interface TAKA_Ticketing_Payment_Provider_Interface {
	public function get_id();
	public function get_label();
	public function is_enabled();
	public function get_public_instructions( $order );
	public function create_payment( $order );
	public function handle_return( $request );
	public function handle_webhook( $request );
	public function mark_paid( $order, $transaction_id );
	public function refund( $order );
	public function get_admin_fields();
}

<?php
/**
 * Repository contracts reserved for later native ticketing phases.
 */

defined( 'ABSPATH' ) || exit;

interface TAKA_Ticketing_Order_Repository_Interface {
	public function find_by_id( $order_id );
	public function find_by_event( $event_id, $args = array() );
	public function save( TAKA_Ticketing_Order $order );
}

interface TAKA_Ticketing_Participant_Repository_Interface {
	public function find_by_order( $order_id );
	public function find_by_event( $event_id, $args = array() );
	public function save( TAKA_Ticketing_Participant $participant );
}

interface TAKA_Ticketing_Payment_Repository_Interface {
	public function find_by_order( $order_id );
	public function save( TAKA_Ticketing_Payment $payment );
}

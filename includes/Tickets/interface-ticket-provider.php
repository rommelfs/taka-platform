<?php
/**
 * Ticket provider contract for TAKA Platform.
 */

defined( 'ABSPATH' ) || exit;

interface TAKA_Platform_Ticket_Provider_Interface {
	public function key();
	public function widget_url( $event );
	public function direct_url( $event );
}

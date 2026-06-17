<?php
/**
 * Pretix ticket provider.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Pretix_Provider implements TAKA_Platform_Ticket_Provider_Interface {
	public function key() { return 'pretix'; }
	public function widget_url( $event ) {
		$provider = strtolower( (string) ( $event['ticket_provider'] ?? '' ) );
		$url      = (string) ( $event['ticket_shop_url'] ?? '' );
		if ( 'pretix' === $provider && '' !== $url ) { return $url; }
		if ( ! empty( $event['pretix']['enabled'] ) && ! empty( $event['pretix']['event'] ) ) { return (string) $event['pretix']['event']; }
		if ( ! empty( $event['pretix_url'] ) ) { return (string) $event['pretix_url']; }
		return '';
	}
	public function direct_url( $event ) { return $this->widget_url( $event ); }
}

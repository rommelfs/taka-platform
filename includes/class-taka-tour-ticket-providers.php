<?php
/**
 * Ticket-provider abstraction for tour events.
 */

defined( 'ABSPATH' ) || exit;

class Taka_Tour_Ticket_Providers {
	/** Get a renderable Pretix widget URL for an event, or empty string. */
	public static function pretix_widget_url( $event ) {
		$provider = strtolower( (string) ( $event['ticket_provider'] ?? '' ) );
		$url      = (string) ( $event['ticket_shop_url'] ?? '' );

		if ( 'pretix' === $provider && '' !== $url ) {
			return $url;
		}

		if ( ! empty( $event['pretix']['enabled'] ) && ! empty( $event['pretix']['event'] ) ) {
			return (string) $event['pretix']['event'];
		}

		if ( ! empty( $event['pretix_url'] ) ) {
			return (string) $event['pretix_url'];
		}

		return '';
	}

	/** Whether an event has any external ticket URL. */
	public static function direct_ticket_url( $event ) {
		return self::pretix_widget_url( $event ) ?: (string) ( $event['ticket_shop_url'] ?? '' );
	}
}

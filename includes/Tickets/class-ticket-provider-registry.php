<?php
/**
 * Ticket-provider registry for TAKA Platform.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Ticket_Provider_Registry {
	private static $providers = null;

	public static function providers() {
		if ( null === self::$providers ) {
			$pretix = new TAKA_Platform_Pretix_Provider();
			self::$providers = array( $pretix->key() => $pretix );
		}
		return self::$providers;
	}

	public static function provider_for_event( $event ) {
		$provider_key = strtolower( (string) ( $event['ticket_provider'] ?? '' ) );
		$providers    = self::providers();
		$provider     = $providers[ $provider_key ] ?? null;
		return apply_filters( 'taka_platform_ticket_provider', $provider, $event );
	}

	public static function pretix_widget_url( $event ) {
		$provider = self::provider_for_event( $event );
		if ( $provider instanceof TAKA_Platform_Ticket_Provider_Interface ) {
			return $provider->widget_url( $event );
		}
		return ! empty( $event['pretix_url'] ) ? (string) $event['pretix_url'] : '';
	}

	public static function direct_ticket_url( $event ) {
		$provider = self::provider_for_event( $event );
		if ( $provider instanceof TAKA_Platform_Ticket_Provider_Interface ) {
			return $provider->direct_url( $event );
		}
		return (string) ( $event['ticket_shop_url'] ?? '' );
	}
}

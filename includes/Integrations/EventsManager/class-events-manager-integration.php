<?php
/**
 * Events Manager export integration.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Events_Manager_Integration {
	const REST_NAMESPACE = 'taka-platform/v1';
	const REST_ROUTE = '/events';
	const EXPORT_ACTION = 'taka_platform_events_manager_export';

	/** Register hooks. */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( __CLASS__, 'handle_export' ) );
	}

	/** Available export providers. */
	public static function providers() {
		$providers = array(
			new TAKA_Platform_ICS_Provider(),
			new TAKA_Platform_CSV_Provider(),
			new TAKA_Platform_JSON_Provider(),
			new TAKA_Platform_Events_Manager_CSV_Provider(),
		);
		$indexed = array();
		foreach ( $providers as $provider ) {
			$indexed[ $provider->key() ] = $provider;
		}
		return apply_filters( 'taka_platform_event_export_providers', $indexed );
	}

	/** Register the public normalized event feed. */
	public static function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods' => 'GET',
				'callback' => array( __CLASS__, 'rest_events' ),
				'permission_callback' => '__return_true',
				'args' => array(
					'lang' => array(
						'description' => 'Language code for resolved display text.',
						'type' => 'string',
						'required' => false,
					),
				),
			)
		);
	}

	/** REST callback for normalized events. */
	public static function rest_events( $request ) {
		$lang = $request instanceof WP_REST_Request ? sanitize_key( (string) $request->get_param( 'lang' ) ) : '';
		return rest_ensure_response(
			array(
				'platform_version' => TAKA_PLATFORM_VERSION,
				'source' => 'taka_platform',
				'events' => self::normalized_events( $lang ),
			)
		);
	}

	/** Admin export download handler. */
	public static function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::EXPORT_ACTION, '_wpnonce' );
		$format = sanitize_key( wp_unslash( $_GET['format'] ?? 'json' ) );
		$lang = sanitize_key( wp_unslash( $_GET['lang'] ?? '' ) );
		$providers = self::providers();
		$provider = $providers[ $format ] ?? $providers['json'];
		$body = $provider->export( self::normalized_events( $lang ) );
		$filename = 'taka-platform-events-' . gmdate( 'Y-m-d' ) . '.' . $provider->file_extension();
		nocache_headers();
		header( 'Content-Type: ' . $provider->content_type() );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/** Build one admin download URL. */
	public static function export_url( $format, $lang = '' ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::EXPORT_ACTION,
					'format' => sanitize_key( $format ),
					'lang' => sanitize_key( $lang ),
				),
				admin_url( 'admin-post.php' )
			),
			self::EXPORT_ACTION
		);
	}

	/** Normalized event data for exports and integrations. */
	public static function normalized_events( $lang = '' ) {
		$lang = self::sanitize_language( $lang );
		$events = TAKA_Platform_Data::events_for_language( $lang );
		$out = array();
		foreach ( $events as $event ) {
			$range = self::event_range( $event );
			$venue = self::venue_payload( $event['venue_full'] ?? ( $event['venue_data'] ?? array() ) );
			$relationships = self::organizer_relationships_payload( $event['organizer_relationships'] ?? array() );
			$external_id = (string) ( $event['config_id'] ?? '' );
			if ( '' === $external_id ) {
				$external_id = (string) ( $event['slug'] ?? ( $event['id'] ?? '' ) );
			}
			$out[] = array(
				'taka_event_id' => (string) ( $event['id'] ?? '' ),
				'external_id' => $external_id,
				'title' => (string) ( $event['title'] ?? '' ),
				'subtitle' => (string) ( $event['subtitle'] ?? '' ),
				'description' => (string) ( $event['description'] ?? '' ),
				'source_language' => TAKA_Platform_Data::object_source_language( $event ),
				'translations' => is_array( $event['text_translations'] ?? null ) ? $event['text_translations'] : array(),
				'start_date' => $range['start_date'],
				'start_time' => $range['start_time'],
				'timezone' => TAKA_Platform_Event_Time::timezone( $event ),
				'end_date' => $range['end_date'],
				'end_time' => $range['end_time'],
				'program_items' => array_values( (array) ( $event['program_items'] ?? array() ) ),
				'venue' => $venue,
				'venue_name' => $venue['name'] ?? '',
				'venue_city' => $venue['city'] ?? '',
				'venue_country' => $venue['country'] ?? '',
				'organizer_relationships' => $relationships,
				'organizers' => implode( ', ', array_filter( wp_list_pluck( $relationships, 'name' ) ) ),
				'ticket_mode' => (string) ( $event['ticket_mode'] ?? '' ),
				'ticket_provider' => (string) ( $event['ticket_provider'] ?? '' ),
				'ticket_url' => (string) ( $event['ticket_shop_url'] ?? '' ),
				'ticket_door_price' => (string) ( $event['ticket_door_price'] ?? '' ),
				'ticket_door_note' => (string) ( $event['ticket_door_note'] ?? '' ),
				'event_url' => self::event_url( $event ),
				'image_url' => (string) ( $event['image'] ?? ( $event['image_url'] ?? '' ) ),
				'events_manager_mapping' => self::events_manager_mapping( $event, $range, $venue, $relationships ),
			);
		}
		return $out;
	}

	private static function sanitize_language( $lang ) {
		$lang = sanitize_key( (string) $lang );
		if ( '' !== $lang && in_array( $lang, TAKA_Platform_I18n::instance()->get_all_languages(), true ) ) {
			return $lang;
		}
		return taka_tour_current_language();
	}

	private static function event_range( $event ) {
		$items = array_values( (array) ( $event['program_items'] ?? array() ) );
		$first = $items[0] ?? array();
		$last = ! empty( $items ) ? $items[ count( $items ) - 1 ] : array();
		return array(
			'start_date' => (string) ( $first['date'] ?? ( $event['date_start'] ?? '' ) ),
			'start_time' => (string) ( $first['time_start'] ?? ( $event['time_start'] ?? '' ) ),
			'end_date' => (string) ( $last['date'] ?? ( $event['date_end'] ?? ( $event['date_start'] ?? '' ) ) ),
			'end_time' => (string) ( $last['time_end'] ?? ( $event['time_end'] ?? '' ) ),
		);
	}

	private static function venue_payload( $venue ) {
		$venue = is_array( $venue ) ? $venue : array();
		$address = is_array( $venue['address'] ?? null ) ? $venue['address'] : array();
		return array(
			'id' => (string) ( $venue['id'] ?? '' ),
			'name' => (string) ( $venue['name'] ?? '' ),
			'address' => trim( implode( ', ', array_filter( array( $address['street'] ?? '', $address['postal_code'] ?? '', $address['city'] ?? '', $address['country'] ?? '' ) ) ) ),
			'street' => (string) ( $address['street'] ?? '' ),
			'postal_code' => (string) ( $address['postal_code'] ?? '' ),
			'city' => (string) ( $address['city'] ?? '' ),
			'country' => (string) ( $address['country'] ?? '' ),
			'country_code' => (string) ( $address['country_code'] ?? '' ),
			'website' => (string) ( $venue['website'] ?? '' ),
		);
	}

	private static function organizer_relationships_payload( $relationships ) {
		$out = array();
		foreach ( (array) $relationships as $relationship ) {
			$organizer = is_array( $relationship['organizer'] ?? null ) ? $relationship['organizer'] : array();
			$out[] = array(
				'organizer_id' => (string) ( $relationship['organizer_id'] ?? ( $organizer['id'] ?? '' ) ),
				'relationship_type' => (string) ( $relationship['relationship_type'] ?? '' ),
				'label' => (string) ( $relationship['label'] ?? '' ),
				'name' => (string) ( $organizer['name'] ?? '' ),
				'website' => (string) ( $organizer['website'] ?? '' ),
				'emails' => array_values( (array) ( $organizer['emails'] ?? array() ) ),
			);
		}
		return $out;
	}

	private static function events_manager_mapping( $event, $range, $venue, $relationships ) {
		return array(
			'event_name' => (string) ( $event['title'] ?? '' ),
			'post_title' => (string) ( $event['title'] ?? '' ),
			'post_content' => (string) ( $event['description'] ?? '' ),
			'event_start_date' => $range['start_date'],
			'event_start_time' => $range['start_time'],
			'event_end_date' => $range['end_date'],
			'event_end_time' => $range['end_time'],
			'location_name' => $venue['name'] ?? '',
			'taka_ticket_mode' => (string) ( $event['ticket_mode'] ?? '' ),
			'taka_ticket_url' => (string) ( $event['ticket_shop_url'] ?? '' ),
			'taka_ticket_door_price' => (string) ( $event['ticket_door_price'] ?? '' ),
			'taka_ticket_door_note' => (string) ( $event['ticket_door_note'] ?? '' ),
			'taka_organizers' => implode( ', ', array_filter( wp_list_pluck( $relationships, 'name' ) ) ),
			'_taka_platform_event_id' => (string) ( $event['id'] ?? '' ),
		);
	}

	private static function event_url( $event ) {
		$id = absint( $event['id'] ?? 0 );
		if ( $id > 0 && function_exists( 'get_permalink' ) ) {
			$url = get_permalink( $id );
			if ( is_string( $url ) ) { return $url; }
		}
		return (string) ( $event['ticket_shop_url'] ?? '' );
	}
}

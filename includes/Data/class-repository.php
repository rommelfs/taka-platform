<?php
/**
 * WordPress-first data model for reusable international event tours.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Data {
	const EVENT_POST_TYPE = TAKA_PLATFORM_CPT_EVENT;
	const ORGANIZER_POST_TYPE = TAKA_PLATFORM_CPT_ORGANIZER;
	const VENUE_POST_TYPE = TAKA_PLATFORM_CPT_VENUE;
	const MEDIA_OPTION = 'taka_tour_media_settings';
	const HERO_OPTION = 'taka_platform_hero_settings';
	const SECTIONS_OPTION = 'taka_platform_content_sections';

	/** Load seed/fallback tour configuration. */
	public static function load_config() {
		static $config = null;
		if ( null === $config ) {
			$path = TAKA_TOUR_PLUGIN_DIR . 'config/tour-events.php';
			$config = file_exists( $path ) ? require $path : array();
		}
		return is_array( $config ) ? $config : array();
	}

	/** Check whether WordPress post APIs are available. */
	private static function can_use_wp_posts() {
		return function_exists( 'get_posts' ) && function_exists( 'get_post_meta' );
	}

	/** Count WordPress events regardless of status. */
	public static function count_wp_events() {
		return self::count_posts( self::EVENT_POST_TYPE, 'any' );
	}

	/** Count config events. */
	public static function count_config_events() {
		$config = self::load_config();
		return count( $config['events'] ?? array() );
	}

	/** Current live data source label. */
	public static function get_active_data_source() {
		return self::is_using_wp_events() ? 'wordpress' : 'config';
	}

	/** Whether frontend data is currently sourced from WordPress events. */
	public static function is_using_wp_events() {
		return self::count_wp_events() > 0;
	}

	/** Count posts safely. */
	private static function count_posts( $post_type, $status = 'publish' ) {
		if ( ! self::can_use_wp_posts() ) { return 0; }
		$posts = get_posts( array( 'post_type' => $post_type, 'post_status' => $status, 'posts_per_page' => -1, 'fields' => 'ids' ) );
		return is_array( $posts ) ? count( $posts ) : 0;
	}

	/** Get all organizers, WordPress first with config fallback. */
	public static function get_organizers() {
		$wp = self::load_organizers_from_wp();
		if ( ! empty( $wp ) ) { return $wp; }
		$config = self::load_config();
		return self::normalize_config_organizers( $config['organizers'] ?? array() );
	}

	/** Get one organizer by internal ID/config ID. */
	public static function get_organizer( $id ) {
		$organizers = self::get_organizers();
		return $organizers[ (string) $id ] ?? null;
	}

	/** Get all venues, WordPress first with config fallback. */
	public static function get_venues() {
		$wp = self::load_venues_from_wp();
		if ( ! empty( $wp ) ) { return $wp; }
		$config = self::load_config();
		return self::normalize_config_venues( $config['venues'] ?? array() );
	}

	/** Get one venue by internal ID/config ID. */
	public static function get_venue( $id ) {
		$venues = self::get_venues();
		return $venues[ (string) $id ] ?? null;
	}

	/** Get events, WordPress first if any WP event exists, otherwise config fallback. */
	public static function get_events() {
		if ( self::is_using_wp_events() ) { return self::load_events_from_wp(); }
		$config = self::load_config();
		return self::normalize_config_events( $config['events'] ?? array() );
	}

	/** Get one event by ID or slug. */
	public static function get_event( $id ) {
		foreach ( self::get_events() as $event ) {
			if ( (string) $id === (string) ( $event['id'] ?? '' ) || (string) $id === (string) ( $event['slug'] ?? '' ) ) { return $event; }
		}
		return null;
	}

	/** Get public, sorted events. */
	public static function get_public_events() {
		$events = array_values( array_filter( self::get_events(), static function ( $event ) { return 'draft' !== ( $event['status'] ?? '' ); } ) );
		usort( $events, array( __CLASS__, 'compare_events' ) );
		return apply_filters( 'taka_platform_events', $events );
	}

	/** Sort callback. */
	private static function compare_events( $a, $b ) {
		foreach ( array( 'sort_order' => 'int', 'date_start' => 'string', 'time_start' => 'string', 'title' => 'string' ) as $key => $type ) {
			$left = $a[ $key ] ?? ( 'int' === $type ? 0 : '' );
			$right = $b[ $key ] ?? ( 'int' === $type ? 0 : '' );
			$compare = 'int' === $type ? ( (int) $left <=> (int) $right ) : strcmp( (string) $left, (string) $right );
			if ( 0 !== $compare ) { return $compare; }
		}
		return 0;
	}

	/** Get public events by organizer. */
	public static function get_events_by_organizer( $organizer_id ) {
		return array_values( array_filter( self::get_public_events(), static function ( $event ) use ( $organizer_id ) { return (string) $organizer_id === (string) ( $event['organizer'] ?? '' ); } ) );
	}

	/** Get public events by venue. */
	public static function get_events_by_venue( $venue_id ) {
		return array_values( array_filter( self::get_public_events(), static function ( $event ) use ( $venue_id ) { return (string) $venue_id === (string) ( $event['venue'] ?? '' ) || in_array( (string) $venue_id, $event['venues'] ?? array(), true ); } ) );
	}

	/** Backward-compatible seminar accessor. */
	public static function seminars() { return self::get_public_events(); }

	/** Load organizers from WordPress. */
	private static function load_organizers_from_wp() {
		if ( ! self::can_use_wp_posts() ) { return array(); }
		$posts = get_posts( array( 'post_type' => self::ORGANIZER_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$items = array();
		foreach ( $posts as $post ) {
			$id = (string) $post->ID;
			$config_id = (string) get_post_meta( $post->ID, '_taka_config_id', true );
			$logo_id = absint( get_post_meta( $post->ID, '_taka_logo_id', true ) );
			$item = array(
				'id' => $id,
				'config_id' => $config_id,
				'name' => get_the_title( $post ),
				'legal_name' => (string) get_post_meta( $post->ID, '_taka_legal_name', true ),
				'website' => (string) get_post_meta( $post->ID, '_taka_website', true ),
				'logo_id' => $logo_id,
				'logo_url' => self::resolve_attachment_url( $logo_id, 'large', (string) get_post_meta( $post->ID, '_taka_logo_url', true ) ),
				'logo' => self::resolve_attachment_url( $logo_id, 'large', (string) get_post_meta( $post->ID, '_taka_logo_url', true ) ),
				'emails' => self::lines_to_array( get_post_meta( $post->ID, '_taka_emails', true ) ),
				'contact_persons' => self::lines_to_array( get_post_meta( $post->ID, '_taka_contact_persons', true ) ),
				'social_links' => array( 'instagram' => (string) get_post_meta( $post->ID, '_taka_instagram', true ), 'facebook' => (string) get_post_meta( $post->ID, '_taka_facebook', true ), 'youtube' => (string) get_post_meta( $post->ID, '_taka_youtube', true ) ),
				'social' => array( 'instagram' => (string) get_post_meta( $post->ID, '_taka_instagram', true ), 'facebook' => (string) get_post_meta( $post->ID, '_taka_facebook', true ), 'youtube' => (string) get_post_meta( $post->ID, '_taka_youtube', true ) ),
				'description' => $post->post_content,
				'active' => '' === (string) get_post_meta( $post->ID, '_taka_active', true ) || '1' === (string) get_post_meta( $post->ID, '_taka_active', true ),
			);
			$items[ $id ] = $item;
			if ( '' !== $config_id ) { $items[ $config_id ] = $item; }
		}
		return $items;
	}

	/** Load venues from WordPress. */
	private static function load_venues_from_wp() {
		if ( ! self::can_use_wp_posts() ) { return array(); }
		$posts = get_posts( array( 'post_type' => self::VENUE_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$items = array();
		foreach ( $posts as $post ) {
			$id = (string) $post->ID;
			$config_id = (string) get_post_meta( $post->ID, '_taka_config_id', true );
			$image_id = absint( get_post_meta( $post->ID, '_taka_image_id', true ) );
			$item = array(
				'id' => $id,
				'config_id' => $config_id,
				'name' => get_the_title( $post ),
				'address' => array( 'street' => (string) get_post_meta( $post->ID, '_taka_street', true ), 'postal_code' => (string) get_post_meta( $post->ID, '_taka_postal_code', true ), 'city' => (string) get_post_meta( $post->ID, '_taka_city', true ), 'country' => (string) get_post_meta( $post->ID, '_taka_country', true ), 'country_code' => (string) get_post_meta( $post->ID, '_taka_country_code', true ) ),
				'timezone' => (string) get_post_meta( $post->ID, '_taka_timezone', true ),
				'website' => (string) get_post_meta( $post->ID, '_taka_website', true ),
				'parking' => (string) get_post_meta( $post->ID, '_taka_parking', true ),
				'accessibility' => (string) get_post_meta( $post->ID, '_taka_accessibility', true ),
				'notes' => (string) get_post_meta( $post->ID, '_taka_notes', true ),
				'geo' => array( 'lat' => self::nullable_meta( $post->ID, 'lat' ), 'lng' => self::nullable_meta( $post->ID, 'lng' ) ),
				'image_id' => $image_id,
				'image_url' => self::resolve_attachment_url( $image_id, 'large', (string) get_post_meta( $post->ID, '_taka_image_url', true ) ),
				'image' => self::resolve_attachment_url( $image_id, 'large', (string) get_post_meta( $post->ID, '_taka_image_url', true ) ),
				'parking_image_id' => absint( get_post_meta( $post->ID, '_taka_parking_image_id', true ) ),
				'parking_image_url' => self::resolve_attachment_url( absint( get_post_meta( $post->ID, '_taka_parking_image_id', true ) ), 'large', (string) get_post_meta( $post->ID, '_taka_parking_image_url', true ) ),
				'gallery_image_ids' => self::csv_to_ints( get_post_meta( $post->ID, '_taka_gallery_image_ids', true ) ),
			);
			$items[ $id ] = $item;
			if ( '' !== $config_id ) { $items[ $config_id ] = $item; }
		}
		return $items;
	}

	/** Load published events from WordPress in config-compatible format. */
	private static function load_events_from_wp() {
		if ( ! self::can_use_wp_posts() ) { return array(); }
		$posts = get_posts( array( 'post_type' => self::EVENT_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$events = array();
		foreach ( $posts as $post ) {
			$image_id = absint( get_post_meta( $post->ID, '_taka_image_id', true ) );
			$venue_id = (string) absint( get_post_meta( $post->ID, '_taka_venue_id', true ) );
			$additional_venues = array_map( 'strval', self::csv_to_ints( get_post_meta( $post->ID, '_taka_venue_ids', true ) ) );
			$venues = array_values( array_unique( array_filter( array_merge( array( $venue_id ), $additional_venues ) ) ) );
			$events[] = array(
				'id' => (string) $post->ID,
				'config_id' => (string) get_post_meta( $post->ID, '_taka_config_id', true ),
				'slug' => $post->post_name,
				'title' => get_the_title( $post ),
				'subtitle' => (string) get_post_meta( $post->ID, '_taka_subtitle', true ),
				'description' => (string) get_post_meta( $post->ID, '_taka_short_description', true ) ?: $post->post_content,
				'long_description' => (string) get_post_meta( $post->ID, '_taka_long_description', true ),
				'ticket_card_text' => (string) get_post_meta( $post->ID, '_taka_ticket_card_text', true ),
				'country' => (string) get_post_meta( $post->ID, '_taka_country', true ),
				'country_code' => (string) get_post_meta( $post->ID, '_taka_country_code', true ),
				'flag' => (string) get_post_meta( $post->ID, '_taka_flag', true ),
				'city' => (string) get_post_meta( $post->ID, '_taka_city', true ),
				'date_start' => (string) get_post_meta( $post->ID, '_taka_date_start', true ),
				'date_end' => (string) get_post_meta( $post->ID, '_taka_date_end', true ),
				'time_start' => (string) get_post_meta( $post->ID, '_taka_time_start', true ),
				'time_end' => (string) get_post_meta( $post->ID, '_taka_time_end', true ),
				'doors_open' => (string) get_post_meta( $post->ID, '_taka_doors_open', true ),
				'timezone' => (string) get_post_meta( $post->ID, '_taka_timezone', true ),
				'organizer' => (string) absint( get_post_meta( $post->ID, '_taka_organizer_id', true ) ),
				'venue' => $venue_id,
				'venues' => $venues,
				'format' => (string) get_post_meta( $post->ID, '_taka_format', true ),
				'audience' => (string) get_post_meta( $post->ID, '_taka_audience', true ),
				'level' => (string) get_post_meta( $post->ID, '_taka_level', true ),
				'status' => 'confirmed',
				'ticket_status' => (string) get_post_meta( $post->ID, '_taka_ticket_status', true ),
				'ticket_provider' => strtolower( (string) get_post_meta( $post->ID, '_taka_ticket_provider', true ) ),
				'ticket_shop_url' => (string) get_post_meta( $post->ID, '_taka_ticket_shop_url', true ),
				'image_id' => $image_id,
				'image_url' => (string) get_post_meta( $post->ID, '_taka_image_url', true ),
				'image' => self::resolve_attachment_url( $image_id, 'large', (string) get_post_meta( $post->ID, '_taka_image_url', true ) ),
				'group_image_id' => absint( get_post_meta( $post->ID, '_taka_group_image_id', true ) ),
				'group_image_url' => self::resolve_attachment_url( absint( get_post_meta( $post->ID, '_taka_group_image_id', true ) ), 'large', (string) get_post_meta( $post->ID, '_taka_group_image_url', true ) ),
				'gallery_image_ids' => self::csv_to_ints( get_post_meta( $post->ID, '_taka_gallery_image_ids', true ) ),
				'gallery_urls' => self::attachment_urls( self::csv_to_ints( get_post_meta( $post->ID, '_taka_gallery_image_ids', true ) ) ),
				'gallery' => self::attachment_urls( self::csv_to_ints( get_post_meta( $post->ID, '_taka_gallery_image_ids', true ) ) ),
				'photo_credit' => (string) get_post_meta( $post->ID, '_taka_photo_credit', true ),
				'languages' => self::csv_to_strings( get_post_meta( $post->ID, '_taka_languages', true ) ),
				'notes' => (string) get_post_meta( $post->ID, '_taka_notes', true ),
				'accessibility' => (string) get_post_meta( $post->ID, '_taka_accessibility', true ),
				'parking' => (string) get_post_meta( $post->ID, '_taka_parking', true ),
				'sort_order' => (int) get_post_meta( $post->ID, '_taka_sort_order', true ),
			);
		}
		return $events;
	}

	/** Normalize config organizers. */
	private static function normalize_config_organizers( $organizers ) {
		$items = array();
		foreach ( $organizers as $id => $item ) { $item['id'] = (string) $id; $item['config_id'] = (string) $id; $item['logo_url'] = $item['logo'] ?? ''; $item['logo_id'] = 0; $item['social_links'] = $item['social'] ?? array(); $item['description'] = $item['description'] ?? ''; $item['active'] = $item['active'] ?? true; $items[ (string) $id ] = $item; }
		return $items;
	}

	/** Normalize config venues. */
	private static function normalize_config_venues( $venues ) {
		$items = array();
		foreach ( $venues as $id => $item ) { $item['id'] = (string) $id; $item['config_id'] = (string) $id; $item['image_id'] = $item['image_id'] ?? 0; $item['image_url'] = $item['image_url'] ?? ( $item['image'] ?? '' ); $item['parking_image_id'] = $item['parking_image_id'] ?? 0; $item['parking_image_url'] = $item['parking_image_url'] ?? ''; $item['gallery_image_ids'] = $item['gallery_image_ids'] ?? array(); $items[ (string) $id ] = $item; }
		return $items;
	}

	/** Normalize config events. */
	private static function normalize_config_events( $events ) {
		return array_map( static function ( $event ) { $event['long_description'] = $event['long_description'] ?? ''; $event['ticket_card_text'] = $event['ticket_card_text'] ?? ''; $event['accessibility'] = $event['accessibility'] ?? ''; $event['image_id'] = $event['image_id'] ?? 0; $event['image_url'] = $event['image_url'] ?? ( $event['image'] ?? '' ); $event['group_image_id'] = $event['group_image_id'] ?? 0; $event['group_image_url'] = $event['group_image_url'] ?? ( $event['group_image'] ?? '' ); $event['gallery_image_ids'] = $event['gallery_image_ids'] ?? array(); $event['gallery_urls'] = $event['gallery'] ?? array(); return $event; }, $events );
	}

	/** Global media labels. */
	public static function global_media_fields() {
		return array( 'hero_image' => 'Hero image', 'taka_portrait' => 'Taka portrait', 'community_group' => 'Community image', 'kobudo' => 'Kobudo image', 'softblock' => 'Softblock image', 'together_practice' => 'Together practice image', 'kids_group' => 'Kids seminar image', 'kleiner_wald_logo' => 'Kleiner Wald logo', 'sponsor_logo' => 'Sponsor logo' );
	}

	/** Get global media settings option. */
	public static function get_global_media_settings() {
		$settings = function_exists( 'get_option' ) ? get_option( self::MEDIA_OPTION, array() ) : array();
		return is_array( $settings ) ? $settings : array();
	}

	/** Get plugin-managed image URLs with attachment-ID override. */
	public static function images() {
		$fallbacks = array( 'hero_image' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-hero.jpg', 'group_image' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-group.jpg', 'portrait_image' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-portrait.jpg', 'group_large' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/Foto-04.10.23-20-02-21-scaled-1.jpg', 'kids_group' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/Kids-Seminar-Trier.jpeg', 'taka_portrait' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/Taka-Tour-2023-Berlin-Foto-30.09.23-17-00-52-1-scaled-1-e1781613695325.jpg', 'kobudo' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/Kobudo-Seminar-Trier-e1781607374996.jpeg', 'community_group' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-gruppe-trier-2025.jpg', 'together_practice' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-gemeinsam-2025.jpg', 'softblock' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-softblock-e1781607328699.jpeg', 'kleiner_wald_logo' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/Logo-Kleiner-Wald.svg', 'sponsor_logo' => '' );
		$settings = self::get_global_media_settings();
		foreach ( $fallbacks as $key => $url ) { $fallbacks[ $key ] = self::resolve_attachment_url( absint( $settings[ $key . '_id' ] ?? 0 ), 'large', (string) ( $settings[ $key . '_url' ] ?? $url ) ?: $url ); }
		return $fallbacks;
	}

	public static function get_media() { return self::images(); }

	/** Default editable hero settings with translation/config fallbacks. */
	public static function default_hero_settings() {
		$images        = self::images();
		$ticket_target = '#seminar-konz';
		foreach ( self::get_public_events() as $event ) {
			if ( '' !== self::pretix_event_url( $event ) ) {
				$ticket_target = '#seminar-' . ( $event['slug'] ?? '' );
				if ( 'konz' === ( $event['slug'] ?? '' ) ) {
					break;
				}
			}
		}

		return array(
			'kicker'                 => taka_tour_translate( 'hero.kicker', 'TAKA European Tour 2026' ),
			'title'                  => taka_tour_translate( 'hero.headline', 'Harmony in Motion' ),
			'description'            => taka_tour_translate( 'hero.intro', 'Eine europäische Seminarreise mit Takafumi Nakayama Sensei – von Helsinki über Berlin, die Niederlande, Belgien und Luxemburg bis in die Region Trier/Konz.' ),
			'primary_button_label'   => taka_tour_translate( 'hero.primary_button', 'Seminare ansehen' ),
			'primary_button_target'  => '#tour',
			'secondary_button_label' => taka_tour_translate( 'hero.secondary_button', 'Tickets' ),
			'secondary_button_target'=> $ticket_target,
			'image_id'               => 0,
			'image_url'              => $images['hero_image'] ?? '',
			'overlay_strength'       => '0.78',
			'text_box_enabled'       => '1',
			'text_box_opacity'       => '0.72',
			'text_box_max_width'     => '620px',
			'text_position'          => 'left',
			'vertical_alignment'     => 'center',
		);
	}

	/** Get editable hero settings. */
	public static function get_hero_settings() {
		$settings = function_exists( 'get_option' ) ? get_option( self::HERO_OPTION, array() ) : array();
		$settings = is_array( $settings ) ? $settings : array();
		$merged   = array_merge( self::default_hero_settings(), $settings );
		$merged['image'] = self::resolve_attachment_url( absint( $merged['image_id'] ?? 0 ), 'large', (string) ( $merged['image_url'] ?? '' ) );
		return $merged;
	}

	/** Save-clean defaults for editable content sections. */
	public static function default_content_sections() {
		$images         = self::images();
		$host_organizer = self::get_organizer( 'kleiner-wald' );
		$sponsor_venue  = self::get_venue( 'kanso-konz' );
		$host_logo      = $host_organizer['logo'] ?? ( $images['kleiner_wald_logo'] ?? '' );

		return array(
			'sensei'   => array( 'key' => 'sensei', 'visible' => '1', 'kicker' => taka_tour_translate( 'sections.sensei.kicker', 'Sensei' ), 'title' => taka_tour_translate( 'sections.sensei.headline', 'Takafumi Nakayama' ), 'text' => taka_tour_translate( 'sections.sensei.text', 'Präzision, Ruhe und Bewegungsqualität aus der okinawanischen Tradition.' ), 'image_id' => 0, 'image_url' => $images['taka_portrait'] ?? '', 'layout' => 'image_right', 'sort_order' => 10, 'link_url' => '', 'link_label' => '' ),
			'training' => array( 'key' => 'training', 'visible' => '1', 'kicker' => taka_tour_translate( 'sections.training.kicker', 'Training' ), 'title' => taka_tour_translate( 'sections.training.headline', 'Karate-Do, Kobujutsu und Soft Blocking' ), 'text' => taka_tour_translate( 'sections.training.text', 'Die Seminare verbinden Grundlagen, Partnerarbeit, Timing, Distanz und Körperstruktur.' ), 'image_id' => 0, 'image_url' => '', 'layout' => 'text_only', 'sort_order' => 20, 'link_url' => '', 'link_label' => '' ),
			'community'=> array( 'key' => 'community', 'visible' => '1', 'kicker' => taka_tour_translate( 'sections.community.kicker', 'Community' ), 'title' => taka_tour_translate( 'sections.community.headline', 'Gemeinsam trainieren' ), 'text' => taka_tour_translate( 'sections.community.text', 'Ein europäisches Treffen für ernsthaftes Training und respektvollen Austausch.' ), 'image_id' => 0, 'image_url' => '', 'layout' => 'text_only', 'sort_order' => 30, 'link_url' => '', 'link_label' => '' ),
			'host'     => array( 'key' => 'host', 'visible' => '1', 'kicker' => taka_tour_translate( 'sections.host.kicker', 'Gastgeber' ), 'title' => taka_tour_translate( 'sections.host.headline', '5 Jahre Kleiner Wald Dojo' ), 'text' => '', 'image_id' => 0, 'image_url' => $host_logo, 'layout' => 'image_right', 'sort_order' => 40, 'link_url' => $host_organizer['website'] ?? '', 'link_label' => $host_organizer['name'] ?? '' ),
			'sponsor'  => array( 'key' => 'sponsor', 'visible' => '1', 'kicker' => taka_tour_translate( 'sections.sponsor.kicker', 'Sponsor' ), 'title' => taka_tour_translate( 'sections.sponsor.headline', 'kanso' ), 'text' => taka_tour_translate( 'sections.sponsor.text', 'Zentrum für Körper, Geist und Seele in Konz.' ), 'image_id' => 0, 'image_url' => $images['sponsor_logo'] ?? '', 'layout' => 'text_only', 'sort_order' => 50, 'link_url' => $sponsor_venue['website'] ?? 'https://kan.so', 'link_label' => taka_tour_translate( 'sections.sponsor.link_text', 'kan.so' ) ),
		);
	}

	/** Get editable frontend content sections. */
	public static function get_content_sections() {
		$stored   = function_exists( 'get_option' ) ? get_option( self::SECTIONS_OPTION, array() ) : array();
		$stored   = is_array( $stored ) ? $stored : array();
		$sections = array();
		foreach ( self::default_content_sections() as $key => $default ) {
			$item           = array_merge( $default, is_array( $stored[ $key ] ?? null ) ? $stored[ $key ] : array() );
			$item['key']    = $key;
			$item['image']  = self::resolve_attachment_url( absint( $item['image_id'] ?? 0 ), 'large', (string) ( $item['image_url'] ?? '' ) );
			$sections[$key] = $item;
		}
		uasort( $sections, static function ( $a, $b ) { return (int) ( $a['sort_order'] ?? 0 ) <=> (int) ( $b['sort_order'] ?? 0 ); } );
		return $sections;
	}

	/** Venues that have enough public information for the practical-info section. */
	public static function venues_for_practical_info() {
		return array_values( array_filter( self::get_venues(), static function ( $venue ) {
			$address = $venue['address'] ?? array();
			$values  = array( $venue['name'] ?? '', $venue['website'] ?? '', $venue['parking'] ?? '', $venue['accessibility'] ?? '', $venue['notes'] ?? '', $address['street'] ?? '', $address['city'] ?? '' );
			return '' !== trim( implode( '', array_map( 'strval', $values ) ) );
		} ) );
	}

	/** Get image grid cards for the homepage. */
	public static function image_grid() {
		$images = self::images();
		return apply_filters( 'taka_platform_gallery_images', array( array( 'id' => 'community', 'title' => 'Community', 'text' => 'Internationale Karate-Familie.', 'image' => $images['community_group'], 'wide' => true ), array( 'id' => 'kobudo', 'title' => 'Kobudo', 'text' => 'Bo-Arbeit, Distanz und Timing.', 'image' => $images['kobudo'] ), array( 'id' => 'softblock', 'title' => 'Soft Blocking', 'text' => 'Weiche Struktur statt roher Kraft.', 'image' => $images['softblock'] ), array( 'id' => 'together', 'title' => 'Gemeinsam üben', 'text' => 'Lernen durch Beobachten, Austausch und Wiederholung.', 'image' => $images['together_practice'] ), array( 'id' => 'kids', 'title' => 'Kinderseminar', 'text' => 'Kinderseminar Trier', 'image' => $images['kids_group'] ), array( 'id' => 'group', 'title' => 'Gruppenfoto', 'text' => 'Gemeinschaft über Dojo- und Landesgrenzen hinweg.', 'image' => $images['group_large'], 'wide' => true ) ), 'homepage' );
	}

	/** Suggest languages from country. */
	public static function languages_for_country( $country ) { $map = array( 'Finland' => array( 'fi', 'en', 'de' ), 'Germany' => array( 'de', 'en' ), 'France' => array( 'fr', 'en', 'de' ), 'Netherlands' => array( 'nl', 'en', 'de' ), 'Belgium' => array( 'fr', 'nl', 'de', 'en' ), 'Luxembourg' => array( 'fr', 'de', 'lb', 'en' ) ); return $map[ $country ] ?? array( 'en' ); }

	/** Get events enriched for active language and display. */
	public static function events_for_language( $lang = null ) {
		$lang = $lang ?: taka_tour_current_language();
		$organizers = self::get_organizers();
		$venues = self::get_venues();
		return array_map( static function ( $event ) use ( $lang, $organizers, $venues ) {
			$slug = $event['slug'] ?? '';
			$organizer = $organizers[ (string) ( $event['organizer'] ?? '' ) ] ?? null;
			$venue = $venues[ (string) ( $event['venue'] ?? '' ) ] ?? null;
			$event['languages'] = ! empty( $event['languages'] ) ? $event['languages'] : self::languages_for_country( $event['country'] ?? '' );
			$event['subtitle'] = taka_tour_translate( 'seminars.' . $slug . '.subtitle', $event['subtitle'] ?? '', $lang );
			$event['description'] = taka_tour_translate( 'seminars.' . $slug . '.description', $event['description'] ?? '', $lang );
			$event['format'] = taka_tour_translate( 'seminars.' . $slug . '.type', $event['format'] ?? '', $lang );
			$event['audience'] = taka_tour_translate( 'seminars.' . $slug . '.audience', $event['audience'] ?? '', $lang );
			$event['level'] = taka_tour_translate( 'seminars.' . $slug . '.level', $event['level'] ?? '', $lang );
			$event['parking'] = taka_tour_translate( 'seminars.' . $slug . '.parking', $event['parking'] ?? '', $lang );
			$event['type'] = $event['format'];
			$event['country_label'] = taka_tour_translate( 'country.' . sanitize_key( $event['country'] ?? '' ), $event['country'] ?? '', $lang );
			$event['date'] = self::format_event_date( $event );
			$event['organizer_data'] = is_array( $organizer ) ? $organizer : null;
			$event['organizer_name'] = is_array( $organizer ) ? ( $organizer['name'] ?? '' ) : '';
			$event['hosts'] = 'Details folgen' === $event['organizer_name'] ? taka_tour_translate( 'event.details_follow', 'Details folgen', $lang ) : $event['organizer_name'];
			$event['organizer_name'] = $event['hosts'];
			$event['venue_data'] = is_array( $venue ) ? $venue : null;
			$event['venue_full'] = is_array( $venue ) ? $venue : null;
			$event['venue_name'] = is_array( $venue ) ? ( $venue['name'] ?? '' ) : '';
			$event['address'] = is_array( $venue ) ? self::format_address( $venue['address'] ?? array() ) : '';
			$event['parking_display'] = $event['parking'] ?: ( is_array( $venue ) ? ( $venue['parking'] ?? '' ) : '' );
			$event['ticket_status_label'] = self::ticket_status_label( $event, $lang );
			$event['organizer_full'] = is_array( $organizer ) ? $organizer : null;
			$event['practical_information'] = self::build_practical_information( $event, $organizer, $venue, $lang );
			$event['info_drawers'] = self::build_info_drawers( $event, $organizer, $venue, $lang );
			return $event;
		}, self::get_public_events() );
	}

	public static function seminars_for_language( $lang = null ) { return self::events_for_language( $lang ); }

	/** Get enabled ticket widget URL for Pretix events. */
	public static function pretix_event_url( $event ) { return TAKA_Platform_Ticket_Provider_Registry::pretix_widget_url( $event ); }

	/** Get ticketed public events. */
	public static function ticketed_seminars() { return array_values( array_filter( self::events_for_language(), static fn( $event ) => '' !== self::pretix_event_url( $event ) ) ); }

	/** Build practical information rows for frontend ticket drawers. */
	private static function build_practical_information( $event, $organizer, $venue, $lang ) {
		$rows = array(
			array( 'label' => taka_tour_translate( 'event.parking', 'Parken', $lang ), 'value' => $event['parking'] ?? '' ),
			array( 'label' => taka_tour_translate( 'event.accessibility', 'Barrierefreiheit', $lang ), 'value' => $event['accessibility'] ?? '' ),
			array( 'label' => taka_tour_translate( 'event.notes', 'Hinweise', $lang ), 'value' => $event['notes'] ?? '' ),
		);

		if ( is_array( $venue ) ) {
			$rows[] = array( 'label' => taka_tour_translate( 'event.venue', 'Ort', $lang ), 'value' => $venue['name'] ?? '' );
			$rows[] = array( 'label' => taka_tour_translate( 'event.address', 'Adresse', $lang ), 'value' => self::format_address( $venue['address'] ?? array() ) );
			$rows[] = array( 'label' => taka_tour_translate( 'event.parking', 'Parken', $lang ), 'value' => $venue['parking'] ?? '' );
			$rows[] = array( 'label' => taka_tour_translate( 'event.accessibility', 'Barrierefreiheit', $lang ), 'value' => $venue['accessibility'] ?? '' );
			$rows[] = array( 'label' => taka_tour_translate( 'event.notes', 'Hinweise', $lang ), 'value' => $venue['notes'] ?? '' );
		}

		if ( is_array( $organizer ) ) {
			$rows[] = array( 'label' => taka_tour_translate( 'event.organizer', 'Veranstalter', $lang ), 'value' => $organizer['name'] ?? '' );
			$rows[] = array( 'label' => taka_tour_translate( 'event.website', 'Website', $lang ), 'value' => $organizer['website'] ?? '', 'url' => $organizer['website'] ?? '' );
			$rows[] = array( 'label' => taka_tour_translate( 'event.contact', 'Kontakt', $lang ), 'value' => self::list_to_string( $organizer['emails'] ?? array() ) );
		}

		return self::clean_info_rows( $rows );
	}

	/** Build prepared drawer view data; templates should not query raw meta. */
	private static function build_info_drawers( $event, $organizer, $venue, $lang ) {
		$time = implode( '–', array_filter( array( $event['time_start'] ?? '', $event['time_end'] ?? '' ) ) );
		$drawers = array();
		$drawers['event'] = array(
			'label' => taka_tour_translate( 'drawer.event_details', 'Event details', $lang ),
			'title' => taka_tour_translate( 'drawer.event_details', 'Event details', $lang ),
			'image' => $event['image'] ?? '',
			'rows'  => self::clean_info_rows( array(
				array( 'label' => taka_tour_translate( 'event.title', 'Title', $lang ), 'value' => $event['title'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.subtitle', 'Subtitle', $lang ), 'value' => $event['subtitle'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.description', 'Description', $lang ), 'value' => $event['description'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.date', 'Date', $lang ), 'value' => $event['date'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.time', 'Time', $lang ), 'value' => $time ),
				array( 'label' => taka_tour_translate( 'event.doors_open', 'Doors open', $lang ), 'value' => $event['doors_open'] ?? '' ),
				array( 'label' => taka_tour_translate( 'seminar.format_label', 'Format', $lang ), 'value' => $event['format'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.audience', 'Audience', $lang ), 'value' => $event['audience'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.level', 'Level', $lang ), 'value' => $event['level'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.ticket_status', 'Ticket status', $lang ), 'value' => $event['ticket_status_label'] ?? ( $event['ticket_status'] ?? '' ) ),
				array( 'label' => taka_tour_translate( 'event.ticket_provider', 'Ticket provider', $lang ), 'value' => $event['ticket_provider'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.ticket_url', 'Ticket URL', $lang ), 'value' => $event['ticket_shop_url'] ?? '', 'url' => $event['ticket_shop_url'] ?? '' ),
			) ),
		);

		if ( is_array( $organizer ) ) {
			$social = $organizer['social_links'] ?? ( $organizer['social'] ?? array() );
			$drawers['organizer'] = array(
				'label' => taka_tour_translate( 'drawer.organizer_info', 'Organizer info', $lang ),
				'title' => taka_tour_translate( 'drawer.organizer_info', 'Organizer info', $lang ),
				'image' => $organizer['logo'] ?? ( $organizer['logo_url'] ?? '' ),
				'rows'  => self::clean_info_rows( array(
					array( 'label' => taka_tour_translate( 'event.organizer', 'Organizer', $lang ), 'value' => $organizer['name'] ?? '' ),
					array( 'label' => taka_tour_translate( 'event.legal_name', 'Legal name', $lang ), 'value' => $organizer['legal_name'] ?? '' ),
					array( 'label' => taka_tour_translate( 'event.description', 'Description', $lang ), 'value' => $organizer['description'] ?? '' ),
					array( 'label' => taka_tour_translate( 'event.website', 'Website', $lang ), 'value' => $organizer['website'] ?? '', 'url' => $organizer['website'] ?? '' ),
					array( 'label' => taka_tour_translate( 'event.email', 'Email', $lang ), 'value' => self::list_to_string( $organizer['emails'] ?? array() ) ),
					array( 'label' => taka_tour_translate( 'event.contact', 'Contact', $lang ), 'value' => self::list_to_string( $organizer['contact_persons'] ?? array() ) ),
					array( 'label' => 'Instagram', 'value' => $social['instagram'] ?? '', 'url' => $social['instagram'] ?? '' ),
					array( 'label' => 'Facebook', 'value' => $social['facebook'] ?? '', 'url' => $social['facebook'] ?? '' ),
					array( 'label' => 'YouTube', 'value' => $social['youtube'] ?? '', 'url' => $social['youtube'] ?? '' ),
				) ),
			);
		}

		if ( is_array( $venue ) ) {
			$address = $venue['address'] ?? array();
			$drawers['venue'] = array(
				'label' => taka_tour_translate( 'drawer.venue_info', 'Venue info', $lang ),
				'title' => taka_tour_translate( 'drawer.venue_info', 'Venue info', $lang ),
				'image' => $venue['image'] ?? ( $venue['image_url'] ?? '' ),
				'rows'  => self::clean_info_rows( array(
					array( 'label' => taka_tour_translate( 'event.venue', 'Venue', $lang ), 'value' => $venue['name'] ?? '' ),
					array( 'label' => taka_tour_translate( 'event.address', 'Address', $lang ), 'value' => self::format_address( $address ) ),
					array( 'label' => taka_tour_translate( 'event.city', 'City', $lang ), 'value' => $address['city'] ?? '' ),
					array( 'label' => taka_tour_translate( 'event.country', 'Country', $lang ), 'value' => $address['country'] ?? '' ),
					array( 'label' => taka_tour_translate( 'event.website', 'Website', $lang ), 'value' => $venue['website'] ?? '', 'url' => $venue['website'] ?? '' ),
					array( 'label' => taka_tour_translate( 'event.parking', 'Parking', $lang ), 'value' => $venue['parking'] ?? '' ),
					array( 'label' => taka_tour_translate( 'event.accessibility', 'Accessibility', $lang ), 'value' => $venue['accessibility'] ?? '' ),
					array( 'label' => taka_tour_translate( 'event.notes', 'Notes', $lang ), 'value' => $venue['notes'] ?? '' ),
				) ),
			);
		}

		$practical = self::build_practical_information( $event, $organizer, $venue, $lang );
		if ( ! empty( $practical ) ) {
			$drawers['practical'] = array(
				'label' => taka_tour_translate( 'drawer.practical_information', 'Practical information', $lang ),
				'title' => taka_tour_translate( 'drawer.practical_information', 'Practical information', $lang ),
				'image' => '',
				'rows'  => $practical,
			);
		}

		return array_filter( $drawers, static function ( $drawer ) { return ! empty( $drawer['rows'] ); } );
	}

	/** Convert nested contact/email arrays into readable text without PHP notices. */
	private static function list_to_string( $items ) {
		$strings = array_map(
			static function ( $item ) {
				if ( is_array( $item ) ) {
					return trim( implode( ' | ', array_filter( array_map( 'strval', $item ) ) ) );
				}
				return trim( (string) $item );
			},
			(array) $items
		);

		return implode( ', ', array_filter( $strings ) );
	}

	/** Drop rows whose values are empty so drawers never show empty labels. */
	private static function clean_info_rows( $rows ) {
		return array_values( array_filter( $rows, static function ( $row ) { return '' !== trim( wp_strip_all_tags( (string) ( $row['value'] ?? '' ) ) ); } ) );
	}

	/** Export current WordPress data into config-compatible array. */
	public static function export_config_from_wp() { return array( 'organizers' => self::export_organizers(), 'venues' => self::export_venues(), 'events' => self::load_events_from_wp() ); }
	private static function export_organizers() { $items = self::load_organizers_from_wp(); $out = array(); foreach ( $items as $key => $item ) { if ( (string) $key !== (string) ( $item['id'] ?? '' ) ) { continue; } $out[ $item['config_id'] ?: $item['id'] ] = $item; } return $out; }
	private static function export_venues() { $items = self::load_venues_from_wp(); $out = array(); foreach ( $items as $key => $item ) { if ( (string) $key !== (string) ( $item['id'] ?? '' ) ) { continue; } $out[ $item['config_id'] ?: $item['id'] ] = $item; } return $out; }

	/** Helpers. */
	private static function resolve_attachment_url( $attachment_id, $size = 'large', $fallback = '' ) { $url = $attachment_id && function_exists( 'wp_get_attachment_image_url' ) ? wp_get_attachment_image_url( $attachment_id, $size ) : ''; return $url ?: $fallback; }
	private static function attachment_urls( $ids, $size = 'large' ) { return array_values( array_filter( array_map( static function ( $id ) use ( $size ) { return self::resolve_attachment_url( $id, $size ); }, (array) $ids ) ) ); }
	private static function nullable_meta( $post_id, $key ) { $value = get_post_meta( $post_id, '_taka_' . $key, true ); return '' === $value ? null : $value; }
	private static function lines_to_array( $value ) { return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $value ) ) ) ); }
	private static function csv_to_ints( $value ) { return array_values( array_filter( array_map( 'absint', preg_split( '/\s*,\s*/', (string) $value ) ) ) ); }
	private static function csv_to_strings( $value ) { return array_values( array_filter( array_map( 'trim', preg_split( '/\s*,\s*/', (string) $value ) ) ) ); }
	private static function format_event_date( $event ) { $start = $event['date_start'] ?? ''; $end = $event['date_end'] ?? ''; if ( '' === $start ) { return ''; } $start_ts = strtotime( $start ); $end_ts = '' !== $end ? strtotime( $end ) : false; if ( false === $start_ts ) { return $start; } if ( false === $end_ts || $start === $end ) { return gmdate( 'j.', $start_ts ) . ' ' . self::month_name( (int) gmdate( 'n', $start_ts ) ) . ' ' . gmdate( 'Y', $start_ts ); } if ( gmdate( 'Ym', $start_ts ) === gmdate( 'Ym', $end_ts ) ) { return gmdate( 'j.', $start_ts ) . '–' . gmdate( 'j.', $end_ts ) . ' ' . self::month_name( (int) gmdate( 'n', $end_ts ) ) . ' ' . gmdate( 'Y', $end_ts ); } return gmdate( 'j.', $start_ts ) . ' ' . self::month_name( (int) gmdate( 'n', $start_ts ) ) . ' ' . gmdate( 'Y', $start_ts ) . ' – ' . gmdate( 'j.', $end_ts ) . ' ' . self::month_name( (int) gmdate( 'n', $end_ts ) ) . ' ' . gmdate( 'Y', $end_ts ); }
	private static function month_name( $month ) { $months = array( 1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember' ); return $months[ $month ] ?? ''; }
	private static function format_address( $address ) { $street = $address['street'] ?? ''; $city_line = trim( ( $address['postal_code'] ?? '' ) . ' ' . ( $address['city'] ?? '' ) ); $country = $address['country'] ?? ''; return implode( ', ', array_filter( array( $street, $city_line, $country ) ) ); }
	private static function ticket_status_label( $event, $lang ) { return '' !== self::pretix_event_url( $event ) ? taka_tour_translate( 'seminar.ticketshop_open_pretix', 'Tickets bei Pretix öffnen', $lang ) : taka_tour_translate( 'event.ticketshop_soon', taka_tour_translate( 'seminar.ticketshop_soon', 'Ticketshop folgt', $lang ), $lang ); }
}

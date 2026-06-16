<?php
/**
 * Central data model for the TAKA European Tour 2026.
 */

defined( 'ABSPATH' ) || exit;

class Taka_Tour_Data {
	/**
	 * Load central tour configuration.
	 *
	 * @return array
	 */
	public static function load_config() {
		static $config = null;

		if ( null === $config ) {
			$path   = TAKA_TOUR_PLUGIN_DIR . 'config/tour-events.php';
			$config = file_exists( $path ) ? require $path : array();
		}

		return is_array( $config ) ? $config : array();
	}


	/** Check whether WordPress post APIs are available. */
	private static function can_use_wp_posts() {
		return function_exists( 'get_posts' ) && function_exists( 'get_post_meta' );
	}

	/** Count all WordPress event posts regardless of status. */
	public static function count_wp_events() {
		if ( ! self::can_use_wp_posts() ) {
			return 0;
		}

		$posts = get_posts( array( 'post_type' => 'taka_event', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
		return count( $posts );
	}

	/** Whether frontend data is currently sourced from WordPress events. */
	public static function is_using_wp_events() {
		return self::count_wp_events() > 0;
	}

	/** Load organizers from WordPress. */
	private static function load_organizers_from_wp() {
		if ( ! self::can_use_wp_posts() ) { return array(); }
		$posts = get_posts( array( 'post_type' => 'taka_organizer', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$items = array();
		foreach ( $posts as $post ) {
			$logo_id = absint( get_post_meta( $post->ID, '_taka_logo_id', true ) );
			$logo = $logo_id && function_exists( 'wp_get_attachment_image_url' ) ? wp_get_attachment_image_url( $logo_id, 'large' ) : '';
			$item = array(
				'id' => (string) $post->ID,
				'name' => get_the_title( $post ),
				'legal_name' => (string) get_post_meta( $post->ID, '_taka_legal_name', true ),
				'website' => (string) get_post_meta( $post->ID, '_taka_website', true ),
				'logo' => $logo,
				'logo_id' => $logo_id,
				'emails' => self::lines_to_array( get_post_meta( $post->ID, '_taka_emails', true ) ),
				'contact_persons' => self::lines_to_array( get_post_meta( $post->ID, '_taka_contact_persons', true ) ),
				'social' => array( 'instagram' => (string) get_post_meta( $post->ID, '_taka_instagram', true ), 'facebook' => (string) get_post_meta( $post->ID, '_taka_facebook', true ), 'youtube' => (string) get_post_meta( $post->ID, '_taka_youtube', true ) ),
			);
			$items[ (string) $post->ID ] = $item;
		}
		return $items;
	}

	/** Load venues from WordPress. */
	private static function load_venues_from_wp() {
		if ( ! self::can_use_wp_posts() ) { return array(); }
		$posts = get_posts( array( 'post_type' => 'taka_venue', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$items = array();
		foreach ( $posts as $post ) {
			$item = array(
				'id' => (string) $post->ID,
				'name' => get_the_title( $post ),
				'address' => array( 'street' => (string) get_post_meta( $post->ID, '_taka_street', true ), 'postal_code' => (string) get_post_meta( $post->ID, '_taka_postal_code', true ), 'city' => (string) get_post_meta( $post->ID, '_taka_city', true ), 'country' => (string) get_post_meta( $post->ID, '_taka_country', true ), 'country_code' => (string) get_post_meta( $post->ID, '_taka_country_code', true ) ),
				'timezone' => (string) get_post_meta( $post->ID, '_taka_timezone', true ),
				'website' => (string) get_post_meta( $post->ID, '_taka_website', true ),
				'parking' => (string) get_post_meta( $post->ID, '_taka_parking', true ),
				'accessibility' => (string) get_post_meta( $post->ID, '_taka_accessibility', true ),
				'notes' => (string) get_post_meta( $post->ID, '_taka_notes', true ),
				'geo' => array( 'lat' => get_post_meta( $post->ID, '_taka_lat', true ) ?: null, 'lng' => get_post_meta( $post->ID, '_taka_lng', true ) ?: null ),
			);
			$items[ (string) $post->ID ] = $item;
		}
		return $items;
	}

	/** Load published events from WordPress in config-compatible format. */
	private static function load_events_from_wp() {
		if ( ! self::can_use_wp_posts() ) { return array(); }
		$posts = get_posts( array( 'post_type' => 'taka_event', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$events = array();
		foreach ( $posts as $post ) {
			$image_id = absint( get_post_meta( $post->ID, '_taka_image_id', true ) );
			$image = $image_id && function_exists( 'wp_get_attachment_image_url' ) ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
			$venue_id = (string) absint( get_post_meta( $post->ID, '_taka_venue_id', true ) );
			$events[] = array(
				'id' => (string) $post->ID,
				'slug' => $post->post_name,
				'title' => get_the_title( $post ),
				'subtitle' => (string) get_post_meta( $post->ID, '_taka_subtitle', true ),
				'description' => $post->post_content,
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
				'venues' => array_filter( array( $venue_id ) ),
				'format' => (string) get_post_meta( $post->ID, '_taka_format', true ),
				'audience' => (string) get_post_meta( $post->ID, '_taka_audience', true ),
				'level' => (string) get_post_meta( $post->ID, '_taka_level', true ),
				'status' => 'confirmed',
				'ticket_status' => (string) get_post_meta( $post->ID, '_taka_ticket_status', true ),
				'ticket_shop_url' => (string) get_post_meta( $post->ID, '_taka_ticket_shop_url', true ),
				'ticket_provider' => strtolower( (string) get_post_meta( $post->ID, '_taka_ticket_provider', true ) ),
				'image' => $image ?: (string) get_post_meta( $post->ID, '_taka_image_url', true ),
				'image_id' => $image_id,
				'photo_credit' => (string) get_post_meta( $post->ID, '_taka_photo_credit', true ),
				'languages' => array(),
				'notes' => (string) get_post_meta( $post->ID, '_taka_notes', true ),
				'parking' => (string) get_post_meta( $post->ID, '_taka_parking', true ),
				'sort_order' => (int) get_post_meta( $post->ID, '_taka_sort_order', true ),
			);
		}
		return $events;
	}

	/** Convert textarea lines to array. */
	private static function lines_to_array( $value ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $value ) ) ) );
	}

	/** Get organizers from WordPress or config fallback. */
	public static function get_organizers() {
		$wp_organizers = self::load_organizers_from_wp();
		if ( ! empty( $wp_organizers ) ) { return $wp_organizers; }
		$config = self::load_config();
		return $config['organizers'] ?? array();
	}

	/** Get one organizer by ID. */
	public static function get_organizer( $id ) {
		$organizers = self::get_organizers();
		return $organizers[ $id ] ?? null;
	}

	/** Get venues from WordPress or config fallback. */
	public static function get_venues() {
		$wp_venues = self::load_venues_from_wp();
		if ( ! empty( $wp_venues ) ) { return $wp_venues; }
		$config = self::load_config();
		return $config['venues'] ?? array();
	}

	/** Get one venue by ID. */
	public static function get_venue( $id ) {
		$venues = self::get_venues();
		return $venues[ $id ] ?? null;
	}

	/** Get events from WordPress or config fallback. */
	public static function get_events() {
		if ( self::is_using_wp_events() ) { return self::load_events_from_wp(); }
		$config = self::load_config();
		return $config['events'] ?? array();
	}

	/** Get one event by ID. */
	public static function get_event( $id ) {
		foreach ( self::get_events() as $event ) {
			if ( $id === ( $event['id'] ?? '' ) ) {
				return $event;
			}
		}

		return null;
	}

	/** Get public, sorted events. */
	public static function get_public_events() {
		$events = array_values(
			array_filter(
				self::get_events(),
				static function ( $event ) {
					return 'draft' !== ( $event['status'] ?? '' );
				}
			)
		);

		usort(
			$events,
			static function ( $a, $b ) {
				$sort_a = (int) ( $a['sort_order'] ?? 0 );
				$sort_b = (int) ( $b['sort_order'] ?? 0 );

				if ( $sort_a !== $sort_b ) {
					return $sort_a <=> $sort_b;
				}

				$date_compare = strcmp( $a['date_start'] ?? '', $b['date_start'] ?? '' );
				if ( 0 !== $date_compare ) {
					return $date_compare;
				}

				$time_compare = strcmp( $a['time_start'] ?? '', $b['time_start'] ?? '' );
				if ( 0 !== $time_compare ) {
					return $time_compare;
				}

				return strcmp( $a['title'] ?? '', $b['title'] ?? '' );
			}
		);

		return $events;
	}

	/** Get public events by organizer. */
	public static function get_events_by_organizer( $organizer_id ) {
		return array_values(
			array_filter(
				self::get_public_events(),
				static function ( $event ) use ( $organizer_id ) {
					return $organizer_id === ( $event['organizer'] ?? '' );
				}
			)
		);
	}

	/** Get public events by venue. */
	public static function get_events_by_venue( $venue_id ) {
		return array_values(
			array_filter(
				self::get_public_events(),
				static function ( $event ) use ( $venue_id ) {
					$venues = $event['venues'] ?? array();
					return $venue_id === ( $event['venue'] ?? '' ) || in_array( $venue_id, $venues, true );
				}
			)
		);
	}

	/** Backward-compatible seminar accessor. */
	public static function seminars() {
		return self::get_public_events();
	}

	/** Get plugin-managed image URLs. */
	public static function images() {
		return array(
			'hero_image'        => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-hero.jpg',
			'group_image'       => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-group.jpg',
			'portrait_image'    => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-portrait.jpg',
			'group_large'       => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/Foto-04.10.23-20-02-21-scaled-1.jpg',
			'kids_group'        => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/Kids-Seminar-Trier.jpeg',
			'taka_portrait'     => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/Taka-Tour-2023-Berlin-Foto-30.09.23-17-00-52-1-scaled-1-e1781613695325.jpg',
			'kobudo'            => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/Kobudo-Seminar-Trier-e1781607374996.jpeg',
			'community_group'   => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-gruppe-trier-2025.jpg',
			'together_practice' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-gemeinsam-2025.jpg',
			'softblock'         => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-softblock-e1781607328699.jpeg',
			'kleiner_wald_logo' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/Logo-Kleiner-Wald.svg',
			'sponsor_logo'      => '',
		);
	}

	/** Get image grid cards for the homepage. */
	public static function image_grid() {
		$images = self::images();

		return array(
			array( 'id' => 'community', 'title' => 'Community', 'text' => 'Internationale Karate-Familie.', 'image' => $images['community_group'], 'wide' => true ),
			array( 'id' => 'kobudo', 'title' => 'Kobudo', 'text' => 'Bo-Arbeit, Distanz und Timing.', 'image' => $images['kobudo'] ),
			array( 'id' => 'softblock', 'title' => 'Soft Blocking', 'text' => 'Weiche Struktur statt roher Kraft.', 'image' => $images['softblock'] ),
			array( 'id' => 'together', 'title' => 'Gemeinsam üben', 'text' => 'Lernen durch Beobachten, Austausch und Wiederholung.', 'image' => $images['together_practice'] ),
			array( 'id' => 'kids', 'title' => 'Kinderseminar', 'text' => 'Kinderseminar Trier', 'image' => $images['kids_group'] ),
			array( 'id' => 'group', 'title' => 'Gruppenfoto', 'text' => 'Gemeinschaft über Dojo- und Landesgrenzen hinweg.', 'image' => $images['group_large'], 'wide' => true ),
		);
	}

	/** Suggest languages from a seminar country. */
	public static function languages_for_country( $country ) {
		$map = array(
			'Finland'     => array( 'fi', 'en', 'de' ),
			'Germany'     => array( 'de', 'en' ),
			'Netherlands' => array( 'nl', 'en', 'de' ),
			'Belgium'     => array( 'fr', 'nl', 'de', 'en' ),
			'Luxembourg'  => array( 'fr', 'de', 'lb', 'en' ),
		);

		return $map[ $country ] ?? array( 'en' );
	}

	/** Get events enriched for the active language. */
	public static function events_for_language( $lang = null ) {
		$lang = $lang ?: taka_tour_current_language();
		return array_map(
			static function ( $event ) use ( $lang ) {
				$slug      = $event['slug'] ?? '';
				$organizer = self::get_organizer( $event['organizer'] ?? '' );
				$venue     = self::get_venue( $event['venue'] ?? '' );

				$event['languages']        = ! empty( $event['languages'] ) ? $event['languages'] : self::languages_for_country( $event['country'] ?? '' );
				$event['subtitle']         = taka_tour_translate( 'seminars.' . $slug . '.subtitle', $event['subtitle'] ?? '', $lang );
				$event['description']      = taka_tour_translate( 'seminars.' . $slug . '.description', $event['description'] ?? '', $lang );
				$event['format']           = taka_tour_translate( 'seminars.' . $slug . '.type', $event['format'] ?? '', $lang );
				$event['audience']         = taka_tour_translate( 'seminars.' . $slug . '.audience', $event['audience'] ?? '', $lang );
				$event['level']            = taka_tour_translate( 'seminars.' . $slug . '.level', $event['level'] ?? '', $lang );
				$event['parking']          = taka_tour_translate( 'seminars.' . $slug . '.parking', $event['parking'] ?? '', $lang );
				$event['type']             = $event['format'];
				$event['country_label']    = taka_tour_translate( 'country.' . sanitize_key( $event['country'] ?? '' ), $event['country'] ?? '', $lang );
				$event['date']             = self::format_event_date( $event );
				$event['organizer_data']   = is_array( $organizer ) ? $organizer : null;
				$event['organizer_name']   = is_array( $organizer ) ? ( $organizer['name'] ?? '' ) : '';
				$event['hosts']            = 'Details folgen' === $event['organizer_name'] ? taka_tour_translate( 'event.details_follow', 'Details folgen', $lang ) : $event['organizer_name'];
				$event['organizer_name']   = $event['hosts'];
				$event['venue_data']       = is_array( $venue ) ? $venue : null;
				$event['venue_name']       = is_array( $venue ) ? ( $venue['name'] ?? '' ) : '';
				$event['address']          = is_array( $venue ) ? self::format_address( $venue['address'] ?? array() ) : '';
				$event['parking_display']  = $event['parking'] ?: ( is_array( $venue ) ? ( $venue['parking'] ?? '' ) : '' );
				$event['ticket_status_label'] = self::ticket_status_label( $event, $lang );
				return $event;
			},
			self::get_public_events()
		);
	}

	/** Backward-compatible translated seminar accessor. */
	public static function seminars_for_language( $lang = null ) {
		return self::events_for_language( $lang );
	}

	/** Get enabled Pretix event URL for an event. */
	public static function pretix_event_url( $event ) {
		if ( 'pretix' === ( $event['ticket_provider'] ?? '' ) && ! empty( $event['ticket_shop_url'] ) ) {
			return $event['ticket_shop_url'];
		}

		if ( ! empty( $event['pretix']['enabled'] ) && ! empty( $event['pretix']['event'] ) ) {
			return $event['pretix']['event'];
		}

		if ( ! empty( $event['pretix_url'] ) ) {
			return $event['pretix_url'];
		}

		return '';
	}

	/** Get ticketed public events. */
	public static function ticketed_seminars() {
		return array_values( array_filter( self::events_for_language(), static fn( $event ) => '' !== self::pretix_event_url( $event ) ) );
	}

	/** Format event date range. */
	private static function format_event_date( $event ) {
		$start = $event['date_start'] ?? '';
		$end   = $event['date_end'] ?? '';

		if ( '' === $start ) {
			return '';
		}

		$start_ts = strtotime( $start );
		$end_ts   = '' !== $end ? strtotime( $end ) : false;

		if ( false === $start_ts ) {
			return $start;
		}

		if ( false === $end_ts || $start === $end ) {
			return gmdate( 'j.', $start_ts ) . ' ' . self::month_name( (int) gmdate( 'n', $start_ts ) ) . ' ' . gmdate( 'Y', $start_ts );
		}

		if ( gmdate( 'Ym', $start_ts ) === gmdate( 'Ym', $end_ts ) ) {
			return gmdate( 'j.', $start_ts ) . '–' . gmdate( 'j.', $end_ts ) . ' ' . self::month_name( (int) gmdate( 'n', $end_ts ) ) . ' ' . gmdate( 'Y', $end_ts );
		}

		return gmdate( 'j.', $start_ts ) . ' ' . self::month_name( (int) gmdate( 'n', $start_ts ) ) . ' ' . gmdate( 'Y', $start_ts ) . ' – ' . gmdate( 'j.', $end_ts ) . ' ' . self::month_name( (int) gmdate( 'n', $end_ts ) ) . ' ' . gmdate( 'Y', $end_ts );
	}

	/** Get German month name. */
	private static function month_name( $month ) {
		$months = array( 1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember' );
		return $months[ $month ] ?? '';
	}

	/** Format a partial venue address safely. */
	private static function format_address( $address ) {
		$street    = $address['street'] ?? '';
		$city_line = trim( ( $address['postal_code'] ?? '' ) . ' ' . ( $address['city'] ?? '' ) );
		$country   = $address['country'] ?? '';
		$parts     = array_filter( array( $street, $city_line, $country ) );
		return implode( ', ', $parts );
	}

	/** Translate ticket status for display. */
	private static function ticket_status_label( $event, $lang ) {
		if ( '' !== self::pretix_event_url( $event ) ) {
			return taka_tour_translate( 'seminar.ticketshop_open_pretix', 'Tickets bei Pretix öffnen', $lang );
		}

		return taka_tour_translate( 'event.ticketshop_soon', taka_tour_translate( 'seminar.ticketshop_soon', 'Ticketshop folgt', $lang ), $lang );
	}
}

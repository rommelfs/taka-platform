<?php
/**
 * Central data model for the TAKA European Tour 2026.
 */

defined( 'ABSPATH' ) || exit;

class Taka_Tour_Data {
	/**
	 * Get central tour configuration.
	 *
	 * @return array
	 */
	public static function load_config() {
		static $config = null;

		if ( null === $config ) {
			$path = TAKA_TOUR_PLUGIN_DIR . 'config/tour-events.php';
			$config = file_exists( $path ) ? require $path : array();
		}

		return is_array( $config ) ? $config : array();
	}


	/**
	 * Check whether WordPress post APIs are available.
	 *
	 * @return bool
	 */
	private static function can_use_cpts() {
		return function_exists( 'get_posts' ) && function_exists( 'get_post_meta' );
	}

	/**
	 * Get CPT organizers keyed by post ID and imported config ID.
	 *
	 * @return array
	 */
	private static function get_cpt_organizers() {
		if ( ! self::can_use_cpts() ) {
			return array();
		}

		$posts = get_posts( array( 'post_type' => 'taka_organizer', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$items = array();
		foreach ( $posts as $post ) {
			$logo_id = absint( get_post_meta( $post->ID, '_taka_logo_id', true ) );
			$logo_url = $logo_id && function_exists( 'wp_get_attachment_image_url' ) ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
			$item = array(
				'id'              => $post->ID,
				'name'            => get_the_title( $post ),
				'legal_name'      => (string) get_post_meta( $post->ID, '_taka_legal_name', true ),
				'website'         => (string) get_post_meta( $post->ID, '_taka_website', true ),
				'logo_id'         => $logo_id,
				'logo'            => $logo_url ?: (string) get_post_meta( $post->ID, '_taka_logo_url', true ),
				'emails'          => self::lines_to_array( get_post_meta( $post->ID, '_taka_emails', true ) ),
				'contact_persons' => self::lines_to_array( get_post_meta( $post->ID, '_taka_contact_persons', true ) ),
				'description'     => $post->post_content,
				'active'          => '0' !== (string) get_post_meta( $post->ID, '_taka_active', true ),
				'social'          => array(
					'instagram' => (string) get_post_meta( $post->ID, '_taka_instagram', true ),
					'facebook'  => (string) get_post_meta( $post->ID, '_taka_facebook', true ),
					'youtube'   => (string) get_post_meta( $post->ID, '_taka_youtube', true ),
				),
			);
			$items[ (string) $post->ID ] = $item;
			$config_id = (string) get_post_meta( $post->ID, '_taka_config_id', true );
			if ( '' !== $config_id ) {
				$items[ $config_id ] = $item;
			}
		}
		return $items;
	}

	/**
	 * Get CPT venues keyed by post ID and imported config ID.
	 *
	 * @return array
	 */
	private static function get_cpt_venues() {
		if ( ! self::can_use_cpts() ) {
			return array();
		}

		$posts = get_posts( array( 'post_type' => 'taka_venue', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$items = array();
		foreach ( $posts as $post ) {
			$item = array(
				'id'            => $post->ID,
				'name'          => get_the_title( $post ),
				'address'       => array(
					'street'       => (string) get_post_meta( $post->ID, '_taka_street', true ),
					'postal_code'  => (string) get_post_meta( $post->ID, '_taka_postal_code', true ),
					'city'         => (string) get_post_meta( $post->ID, '_taka_city', true ),
					'country'      => (string) get_post_meta( $post->ID, '_taka_country', true ),
					'country_code' => (string) get_post_meta( $post->ID, '_taka_country_code', true ),
				),
				'timezone'      => (string) get_post_meta( $post->ID, '_taka_timezone', true ),
				'website'       => (string) get_post_meta( $post->ID, '_taka_website', true ),
				'parking'       => (string) get_post_meta( $post->ID, '_taka_parking', true ),
				'accessibility' => (string) get_post_meta( $post->ID, '_taka_accessibility', true ),
				'notes'         => (string) get_post_meta( $post->ID, '_taka_notes', true ),
				'geo'           => array(
					'lat' => get_post_meta( $post->ID, '_taka_lat', true ),
					'lng' => get_post_meta( $post->ID, '_taka_lng', true ),
				),
			);
			$items[ (string) $post->ID ] = $item;
			$config_id = (string) get_post_meta( $post->ID, '_taka_config_id', true );
			if ( '' !== $config_id ) {
				$items[ $config_id ] = $item;
			}
		}
		return $items;
	}

	/**
	 * Get CPT events.
	 *
	 * @return array[]
	 */
	private static function get_cpt_events() {
		if ( ! self::can_use_cpts() ) {
			return array();
		}

		$posts = get_posts( array( 'post_type' => 'taka_event', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'ASC' ) );
		$events = array();
		foreach ( $posts as $post ) {
			$image_id = absint( get_post_meta( $post->ID, '_taka_image_id', true ) );
			$image_url = $image_id && function_exists( 'wp_get_attachment_image_url' ) ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
			$venue_id = (string) absint( get_post_meta( $post->ID, '_taka_venue_id', true ) );
			$venue_ids = array_filter( array_map( 'strval', array_map( 'absint', (array) get_post_meta( $post->ID, '_taka_venue_ids', true ) ) ) );
			$events[] = array(
				'id'               => (string) ( get_post_meta( $post->ID, '_taka_config_id', true ) ?: $post->ID ),
				'post_id'          => $post->ID,
				'slug'             => (string) ( get_post_meta( $post->ID, '_taka_slug', true ) ?: $post->post_name ),
				'title'            => get_the_title( $post ),
				'subtitle'         => (string) get_post_meta( $post->ID, '_taka_subtitle', true ),
				'description'      => $post->post_content,
				'country'          => (string) get_post_meta( $post->ID, '_taka_country', true ),
				'country_code'     => (string) get_post_meta( $post->ID, '_taka_country_code', true ),
				'flag'             => (string) get_post_meta( $post->ID, '_taka_flag', true ),
				'city'             => (string) get_post_meta( $post->ID, '_taka_city', true ),
				'date_start'       => (string) get_post_meta( $post->ID, '_taka_date_start', true ),
				'date_end'         => (string) get_post_meta( $post->ID, '_taka_date_end', true ),
				'time_start'       => (string) get_post_meta( $post->ID, '_taka_time_start', true ),
				'time_end'         => (string) get_post_meta( $post->ID, '_taka_time_end', true ),
				'doors_open'       => (string) get_post_meta( $post->ID, '_taka_doors_open', true ),
				'timezone'         => (string) get_post_meta( $post->ID, '_taka_timezone', true ),
				'organizer'        => (string) absint( get_post_meta( $post->ID, '_taka_organizer_id', true ) ),
				'venue'            => $venue_id,
				'venues'           => ! empty( $venue_ids ) ? $venue_ids : array_filter( array( $venue_id ) ),
				'format'           => (string) get_post_meta( $post->ID, '_taka_format', true ),
				'audience'         => (string) get_post_meta( $post->ID, '_taka_audience', true ),
				'level'            => (string) get_post_meta( $post->ID, '_taka_level', true ),
				'status'           => 'draft' === $post->post_status ? 'draft' : 'confirmed',
				'ticket_status'    => (string) get_post_meta( $post->ID, '_taka_ticket_status', true ),
				'ticket_shop_url'  => (string) get_post_meta( $post->ID, '_taka_ticket_shop_url', true ),
				'ticket_provider'  => (string) get_post_meta( $post->ID, '_taka_ticket_provider', true ),
				'image_id'         => $image_id,
				'image'            => $image_url ?: (string) get_post_meta( $post->ID, '_taka_image_url', true ),
				'photo_credit'     => (string) get_post_meta( $post->ID, '_taka_photo_credit', true ),
				'languages'        => self::csv_to_array( get_post_meta( $post->ID, '_taka_languages', true ) ),
				'notes'            => (string) get_post_meta( $post->ID, '_taka_notes', true ),
				'parking'          => (string) get_post_meta( $post->ID, '_taka_parking', true ),
				'sort_order'       => (int) get_post_meta( $post->ID, '_taka_sort_order', true ),
			);
		}
		return $events;
	}

	/** Convert textarea lines to array. */
	private static function lines_to_array( $value ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $value ) ) ) );
	}

	/** Convert comma-separated values to array. */
	private static function csv_to_array( $value ) {
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $value ) ) ) );
	}

	/**
	 * Get organizers from config.
	 *
	 * @return array
	 */
	public static function get_organizers() {
		$cpt_organizers = self::get_cpt_organizers();
		if ( ! empty( $cpt_organizers ) ) {
			return $cpt_organizers;
		}

		$config = self::load_config();
		return $config['organizers'] ?? array();
	}

	/**
	 * Get one organizer by ID.
	 *
	 * @param string $id Organizer ID.
	 * @return array|null
	 */
	public static function get_organizer( $id ) {
		$organizers = self::get_organizers();
		return $organizers[ $id ] ?? null;
	}

	/**
	 * Get venues from config.
	 *
	 * @return array
	 */
	public static function get_venues() {
		$cpt_venues = self::get_cpt_venues();
		if ( ! empty( $cpt_venues ) ) {
			return $cpt_venues;
		}

		$config = self::load_config();
		return $config['venues'] ?? array();
	}

	/**
	 * Get one venue by ID.
	 *
	 * @param string $id Venue ID.
	 * @return array|null
	 */
	public static function get_venue( $id ) {
		$venues = self::get_venues();
		return $venues[ $id ] ?? null;
	}

	/**
	 * Get events from config.
	 *
	 * @return array[]
	 */
	public static function get_events() {
		$cpt_events = self::get_cpt_events();
		if ( ! empty( $cpt_events ) ) {
			return $cpt_events;
		}

		$config = self::load_config();
		return $config['events'] ?? array();
	}

	/**
	 * Get one event by ID.
	 *
	 * @param string $id Event ID.
	 * @return array|null
	 */
	public static function get_event( $id ) {
		foreach ( self::get_events() as $event ) {
			if ( $id === ( $event['id'] ?? '' ) ) {
				return $event;
			}
		}

		return null;
	}

	/**
	 * Get public, sorted events.
	 *
	 * @return array[]
	 */
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

				if ( $sort_a === $sort_b ) {
					return strcmp( $a['date_start'] ?? '', $b['date_start'] ?? '' );
				}

				return $sort_a <=> $sort_b;
			}
		);

		return $events;
	}

	/**
	 * Get public events by organizer.
	 *
	 * @param string $organizer_id Organizer ID.
	 * @return array[]
	 */
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

	/**
	 * Get public events by venue.
	 *
	 * @param string $venue_id Venue ID.
	 * @return array[]
	 */
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

	/**
	 * Backward-compatible seminar accessor.
	 *
	 * @return array[]
	 */
	public static function seminars() {
		return self::get_public_events();
	}

	/**
	 * Get plugin-managed image URLs.
	 *
	 * @return array
	 */
	public static function images() {
		$images = array(
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

		if ( function_exists( 'get_option' ) && function_exists( 'wp_get_attachment_image_url' ) ) {
			$media = get_option( 'taka_tour_images', array() );
			foreach ( $images as $key => $fallback ) {
				$attachment_id = absint( $media[ $key ] ?? 0 );
				$media_url = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'full' ) : '';
				if ( $media_url ) {
					$images[ $key ] = $media_url;
				}
			}
		}

		return $images;
	}

	/**
	 * Get image grid cards for the homepage.
	 *
	 * @return array[]
	 */
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

	/**
	 * Suggest languages from a seminar country.
	 *
	 * @param string $country Country name.
	 * @return array
	 */
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

	/**
	 * Get events enriched for the active language.
	 *
	 * @param string|null $lang Optional language.
	 * @return array[]
	 */
	public static function events_for_language( $lang = null ) {
		$lang = $lang ?: taka_tour_current_language();
		return array_map(
			static function ( $event ) use ( $lang ) {
				$slug = $event['slug'] ?? '';
				$organizer = self::get_organizer( $event['organizer'] ?? '' );
				$venue = self::get_venue( $event['venue'] ?? '' );
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
				if ( 'Details folgen' === $event['organizer_name'] ) {
					$event['organizer_name'] = taka_tour_translate( 'event.details_follow', 'Details folgen', $lang );
				}
				$event['hosts'] = $event['organizer_name'];
				$event['venue_data'] = is_array( $venue ) ? $venue : null;
				$event['venue_name'] = is_array( $venue ) ? ( $venue['name'] ?? '' ) : '';
				$event['address'] = is_array( $venue ) ? self::format_address( $venue['address'] ?? array() ) : '';
				$event['parking_display'] = $event['parking'] ?: ( is_array( $venue ) ? ( $venue['parking'] ?? '' ) : '' );
				$event['ticket_status_label'] = self::ticket_status_label( $event, $lang );
				return $event;
			},
			self::get_public_events()
		);
	}

	/**
	 * Backward-compatible translated seminar accessor.
	 *
	 * @param string|null $lang Optional language.
	 * @return array[]
	 */
	public static function seminars_for_language( $lang = null ) {
		return self::events_for_language( $lang );
	}

	/**
	 * Get enabled Pretix event URL for an event.
	 *
	 * @param array $event Event data.
	 * @return string
	 */
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

	/**
	 * Get ticketed public events.
	 *
	 * @return array[]
	 */
	public static function ticketed_seminars() {
		return array_values( array_filter( self::events_for_language(), static fn( $event ) => '' !== self::pretix_event_url( $event ) ) );
	}

	/**
	 * Format event date range.
	 *
	 * @param array $event Event data.
	 * @return string
	 */
	private static function format_event_date( $event ) {
		$start = $event['date_start'] ?? '';
		$end = $event['date_end'] ?? '';

		if ( '' === $start ) {
			return '';
		}

		$start_ts = strtotime( $start );
		$end_ts = '' !== $end ? strtotime( $end ) : false;

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

	/**
	 * Get German month name.
	 *
	 * @param int $month Month number.
	 * @return string
	 */
	private static function month_name( $month ) {
		$months = array( 1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember' );
		return $months[ $month ] ?? '';
	}

	/**
	 * Format a partial venue address safely.
	 *
	 * @param array $address Address data.
	 * @return string
	 */
	private static function format_address( $address ) {
		$street = $address['street'] ?? '';
		$city_line = trim( ( $address['postal_code'] ?? '' ) . ' ' . ( $address['city'] ?? '' ) );
		$country = $address['country'] ?? '';
		$parts = array_filter( array( $street, $city_line, $country ) );
		return implode( ', ', $parts );
	}

	/**
	 * Translate ticket status for display.
	 *
	 * @param array  $event Event data.
	 * @param string $lang Language code.
	 * @return string
	 */
	private static function ticket_status_label( $event, $lang ) {
		if ( '' !== self::pretix_event_url( $event ) ) {
			return taka_tour_translate( 'seminar.ticketshop_open_pretix', 'Tickets bei Pretix öffnen', $lang );
		}

		return taka_tour_translate( 'event.ticketshop_soon', taka_tour_translate( 'seminar.ticketshop_soon', 'Ticketshop folgt', $lang ), $lang );
	}
}

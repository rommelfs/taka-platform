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
	private static function config() {
		static $config = null;

		if ( null === $config ) {
			$path = TAKA_TOUR_PLUGIN_DIR . 'config/tour-events.php';
			$config = file_exists( $path ) ? require $path : array();
		}

		return is_array( $config ) ? $config : array();
	}

	/**
	 * Get organizers from config.
	 *
	 * @return array
	 */
	public static function get_organizers() {
		$config = self::config();
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
		$config = self::config();
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
		$config = self::config();
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
		);
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

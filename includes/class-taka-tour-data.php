<?php
/**
 * Central data model for the TAKA European Tour 2026.
 */

defined( 'ABSPATH' ) || exit;

class Taka_Tour_Data {
	/**
	 * Get seminar stations.
	 *
	 * @return array[]
	 */
	public static function seminars() {
		return array(
			array( 'slug' => 'helsinki', 'country' => 'Finland', 'flag' => '🇫🇮', 'date' => '29.–30. August 2026', 'title' => 'Helsinki', 'subtitle' => 'Helsinki Seminar', 'type' => '2-Tage-Seminar', 'hosts' => 'Patrik, Olga, Timo', 'description' => 'Zwei Tage Seminar in Helsinki.', 'pretix' => null, 'languages' => null, 'ticket_status' => 'Ticketshop folgt' ),
			array( 'slug' => 'berlin', 'country' => 'Germany', 'flag' => '🇩🇪', 'date' => '5.–6. September 2026', 'title' => 'Berlin', 'subtitle' => 'Berlin Seminar', 'type' => '2-Tage-Seminar', 'hosts' => 'Details folgen', 'description' => 'Zwei Tage Seminar in Berlin.', 'pretix' => null, 'languages' => null, 'ticket_status' => 'Ticketshop folgt' ),
			array( 'slug' => 'netherlands', 'country' => 'Netherlands', 'flag' => '🇳🇱', 'date' => '12.–13. September 2026', 'title' => 'Netherlands', 'subtitle' => 'Netherlands Seminar', 'type' => '2-Tage-Seminar', 'hosts' => 'Marcel, Dmitri, Albert', 'description' => 'Zwei Tage Seminar in den Niederlanden.', 'pretix' => null, 'languages' => null, 'ticket_status' => 'Ticketshop folgt' ),
			array( 'slug' => 'belgium', 'country' => 'Belgium', 'flag' => '🇧🇪', 'date' => '19. September 2026', 'title' => 'Belgium', 'subtitle' => 'Belgium Seminar', 'type' => 'Halbtagseminar', 'hosts' => 'Filip, Jos', 'description' => 'Seminar geplant von 10:00 bis 13:00 Uhr.', 'pretix' => null, 'languages' => null, 'ticket_status' => 'Ticketshop folgt' ),
			array( 'slug' => 'illange', 'country' => 'Luxembourg', 'flag' => '🇱🇺', 'date' => '21. September 2026', 'title' => 'Illange', 'subtitle' => 'Illange Seminar', 'type' => 'Seminar', 'hosts' => 'Details folgen', 'description' => 'Seminarstation in Luxemburg.', 'pretix' => null, 'languages' => null, 'ticket_status' => 'Ticketshop folgt' ),
			array( 'slug' => 'hosingen', 'country' => 'Luxembourg', 'flag' => '🇱🇺', 'date' => '22. September 2026', 'title' => 'Hosingen', 'subtitle' => 'Hosingen Seminar', 'type' => 'Seminar', 'hosts' => 'Details folgen', 'description' => 'Seminarstation in Luxemburg.', 'pretix' => null, 'languages' => null, 'ticket_status' => 'Ticketshop folgt' ),
			array( 'slug' => 'trier-kinderseminar', 'country' => 'Germany', 'flag' => '🇩🇪', 'date' => '26. September 2026', 'title' => 'Trier Kinderseminar', 'subtitle' => 'Kinderseminar', 'type' => 'Kinderseminar', 'hosts' => 'Kleiner Wald Dojo', 'description' => 'Kinderseminar in Trier im Rahmen der TAKA European Tour.', 'pretix' => array( 'event' => 'https://pretix.eu/kleinerwald/2026takakids/', 'enabled' => true ), 'languages' => null, 'ticket_status' => 'Tickets verfügbar' ),
			array( 'slug' => 'konz', 'country' => 'Germany', 'flag' => '🇩🇪', 'date' => '26.–27. September 2026', 'title' => 'Konz', 'subtitle' => 'Konz Seminar', 'type' => '2-Tage-Seminar', 'hosts' => 'Kleiner Wald Dojo', 'description' => 'Zwei Tage Seminar in Konz mit Takafumi Nakayama Sensei.', 'pretix' => array( 'event' => 'https://pretix.eu/kleinerwald/2026takakonz/', 'enabled' => true ), 'languages' => null, 'ticket_status' => 'Tickets verfügbar' ),
			array( 'slug' => 'saarwellingen', 'country' => 'Germany', 'flag' => '🇩🇪', 'date' => '28. September 2026', 'title' => 'Saarwellingen', 'subtitle' => 'Saarwellingen Seminar', 'type' => 'Seminar', 'hosts' => 'Patrick Haak', 'description' => 'Abschlussseminar der Tourstationen in der Region.', 'pretix' => null, 'languages' => null, 'ticket_status' => 'Ticketshop folgt' ),
		);
	}

	/**
	 * Get plugin-managed image URLs.
	 *
	 * @return array
	 */
		public static function images() {
		return array(
			'hero_image'     => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-hero.jpg',
			'group_image'    => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-group.jpg',
			'portrait_image' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-portrait.jpg',
			'group_large'    => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/Foto-04.10.23-20-02-21-scaled-1.jpg',
			'kids_group'     => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/Kids-Seminar-Trier.jpeg',
			'taka_portrait'  => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/Taka-Tour-2023-Berlin-Foto-30.09.23-17-00-52-1-scaled-1-e1781613695325.jpg',
			'kobudo'         => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/Kobudo-Seminar-Trier-e1781607374996.jpeg',
			'community_group' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-gruppe-trier-2025.jpg',
			'together_practice' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-gemeinsam-2025.jpg',
			'softblock'      => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-softblock-e1781607328699.jpeg',
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
	 * Get seminars enriched for the active language.
	 *
	 * @param string|null $lang Optional language.
	 * @return array[]
	 */
	public static function seminars_for_language( $lang = null ) {
		$lang = $lang ?: taka_tour_current_language();
		return array_map(
			static function ( $seminar ) use ( $lang ) {
				$slug = $seminar['slug'];
				$seminar['languages'] = ! empty( $seminar['languages'] ) ? $seminar['languages'] : self::languages_for_country( $seminar['country'] );
				$seminar['subtitle'] = taka_tour_translate( 'seminars.' . $slug . '.subtitle', $seminar['subtitle'], $lang );
				$seminar['description'] = taka_tour_translate( 'seminars.' . $slug . '.description', $seminar['description'], $lang );
				$seminar['type'] = taka_tour_translate( 'seminars.' . $slug . '.type', $seminar['type'], $lang );
				$seminar['country_label'] = taka_tour_translate( 'country.' . sanitize_key( $seminar['country'] ), $seminar['country'], $lang );
				$seminar['ticket_status'] = taka_tour_translate( 'seminar.ticketshop_soon', $seminar['ticket_status'], $lang );
				return $seminar;
			},
			self::seminars()
		);
	}

	/**
	 * Get enabled Pretix event URL for a seminar.
	 *
	 * @param array $seminar Seminar data.
	 * @return string
	 */
	public static function pretix_event_url( $seminar ) {
		if ( ! empty( $seminar['pretix']['enabled'] ) && ! empty( $seminar['pretix']['event'] ) ) {
			return $seminar['pretix']['event'];
		}

		if ( ! empty( $seminar['pretix_url'] ) ) {
			return $seminar['pretix_url'];
		}

		return '';
	}

	public static function ticketed_seminars() {
		return array_values( array_filter( self::seminars(), static fn( $seminar ) => '' !== self::pretix_event_url( $seminar ) ) );
	}
}

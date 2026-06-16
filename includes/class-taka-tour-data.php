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
			array( 'slug' => 'helsinki', 'country' => 'Finland', 'flag' => '🇫🇮', 'date' => '29.–30. August 2026', 'title' => 'Helsinki', 'subtitle' => 'Helsinki Seminar', 'type' => '2-Tage-Seminar', 'hosts' => 'Patrik, Olga, Timo', 'description' => 'Zwei Tage Seminar in Helsinki.', 'pretix_url' => null, 'ticket_status' => 'Ticketshop folgt' ),
			array( 'slug' => 'berlin', 'country' => 'Germany', 'flag' => '🇩🇪', 'date' => '5.–6. September 2026', 'title' => 'Berlin', 'subtitle' => 'Berlin Seminar', 'type' => '2-Tage-Seminar', 'hosts' => 'Details folgen', 'description' => 'Zwei Tage Seminar in Berlin.', 'pretix_url' => null, 'ticket_status' => 'Ticketshop folgt' ),
			array( 'slug' => 'netherlands', 'country' => 'Netherlands', 'flag' => '🇳🇱', 'date' => '12.–13. September 2026', 'title' => 'Netherlands', 'subtitle' => 'Netherlands Seminar', 'type' => '2-Tage-Seminar', 'hosts' => 'Marcel, Dmitri, Albert', 'description' => 'Zwei Tage Seminar in den Niederlanden.', 'pretix_url' => null, 'ticket_status' => 'Ticketshop folgt' ),
			array( 'slug' => 'belgium', 'country' => 'Belgium', 'flag' => '🇧🇪', 'date' => '19. September 2026', 'title' => 'Belgium', 'subtitle' => 'Belgium Seminar', 'type' => 'Halbtagseminar', 'hosts' => 'Filip, Jos', 'description' => 'Seminar geplant von 10:00 bis 13:00 Uhr.', 'pretix_url' => null, 'ticket_status' => 'Ticketshop folgt' ),
			array( 'slug' => 'illange', 'country' => 'Luxembourg', 'flag' => '🇱🇺', 'date' => '21. September 2026', 'title' => 'Illange', 'subtitle' => 'Illange Seminar', 'type' => 'Seminar', 'hosts' => 'Details folgen', 'description' => 'Seminarstation in Luxemburg.', 'pretix_url' => null, 'ticket_status' => 'Ticketshop folgt' ),
			array( 'slug' => 'hosingen', 'country' => 'Luxembourg', 'flag' => '🇱🇺', 'date' => '22. September 2026', 'title' => 'Hosingen', 'subtitle' => 'Hosingen Seminar', 'type' => 'Seminar', 'hosts' => 'Details folgen', 'description' => 'Seminarstation in Luxemburg.', 'pretix_url' => null, 'ticket_status' => 'Ticketshop folgt' ),
			array( 'slug' => 'trier-kinderseminar', 'country' => 'Germany', 'flag' => '🇩🇪', 'date' => '26. September 2026', 'title' => 'Trier Kinderseminar', 'subtitle' => 'Kinderseminar', 'type' => 'Kinderseminar', 'hosts' => 'Kleiner Wald Dojo', 'description' => 'Kinderseminar in Trier im Rahmen der TAKA European Tour.', 'pretix_url' => 'https://pretix.eu/kleinerwald/2026takakids/', 'ticket_status' => 'Tickets verfügbar' ),
			array( 'slug' => 'konz', 'country' => 'Germany', 'flag' => '🇩🇪', 'date' => '26.–27. September 2026', 'title' => 'Konz', 'subtitle' => 'Konz Seminar', 'type' => '2-Tage-Seminar', 'hosts' => 'Kleiner Wald Dojo', 'description' => 'Zwei Tage Seminar in Konz mit Takafumi Nakayama Sensei.', 'pretix_url' => 'https://pretix.eu/kleinerwald/2026takakonz/', 'ticket_status' => 'Tickets verfügbar' ),
			array( 'slug' => 'saarwellingen', 'country' => 'Germany', 'flag' => '🇩🇪', 'date' => '28. September 2026', 'title' => 'Saarwellingen', 'subtitle' => 'Saarwellingen Seminar', 'type' => 'Seminar', 'hosts' => 'Patrick Haak', 'description' => 'Abschlussseminar der Tourstationen in der Region.', 'pretix_url' => null, 'ticket_status' => 'Ticketshop folgt' ),
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
			array( 'title' => 'Kobudo', 'text' => 'Bo-Arbeit, Distanz und Timing.', 'image' => $images['kobudo'] ),
			array( 'title' => 'Community', 'text' => 'Internationale Karate-Familie.', 'image' => $images['community_group'] ),
			array( 'title' => 'Gemeinsam üben', 'text' => 'Lernen durch Beobachten, Austausch und Wiederholung.', 'image' => $images['together_practice'] ),
			array( 'title' => 'Soft Blocking', 'text' => 'Weiche Struktur statt roher Kraft.', 'image' => $images['softblock'] ),
		);
	}

	public static function ticketed_seminars() {
		return array_values( array_filter( self::seminars(), static fn( $seminar ) => ! empty( $seminar['pretix_url'] ) ) );
	}
}

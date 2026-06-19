<?php
/**
 * Structured tour event configuration.
 *
 * @package Taka_Tour
 */

return array(
	'organizers' => array(
		'kleiner-wald' => array(
			'name'            => 'Kleiner Wald Dojo',
			'legal_name'      => 'Kleiner Wald – Okinawa Shorin-Ryu Karate-Do Konz',
			'website'         => 'https://kleiner-wald.de',
			'logo'            => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/Logo-Kleiner-Wald.svg',
			'emails'          => array( 'info@kleiner-wald.de' ),
			'contact_persons' => array(
				array(
					'name'  => 'Sascha Rommelfangen',
					'email' => 'info@kleiner-wald.de',
					'role'  => 'Host / Organizer',
				),
			),
			'social'          => array(
				'instagram' => '',
				'facebook'  => '',
				'youtube'   => '',
			),
		),
		'helsinki-hosts' => array(
			'name'            => 'Patrik, Olga, Timo',
			'legal_name'      => '',
			'website'         => '',
			'logo'            => '',
			'emails'          => array(),
			'contact_persons' => array(),
			'social'          => array( 'instagram' => '', 'facebook' => '', 'youtube' => '' ),
		),
		'berlin-tbd' => array(
			'name'            => 'Details folgen',
			'legal_name'      => '',
			'website'         => '',
			'logo'            => '',
			'emails'          => array(),
			'contact_persons' => array(),
			'social'          => array( 'instagram' => '', 'facebook' => '', 'youtube' => '' ),
		),
		'netherlands-hosts' => array(
			'name'            => 'Marcel, Dmitri, Albert',
			'legal_name'      => '',
			'website'         => '',
			'logo'            => '',
			'emails'          => array(),
			'contact_persons' => array(),
			'social'          => array( 'instagram' => '', 'facebook' => '', 'youtube' => '' ),
		),
		'belgium-hosts' => array(
			'name'            => 'Filip, Jos',
			'legal_name'      => '',
			'website'         => '',
			'logo'            => '',
			'emails'          => array(),
			'contact_persons' => array(),
			'social'          => array( 'instagram' => '', 'facebook' => '', 'youtube' => '' ),
		),
		'luxembourg-tbd' => array(
			'name'            => 'Details folgen',
			'legal_name'      => '',
			'website'         => '',
			'logo'            => '',
			'emails'          => array(),
			'contact_persons' => array(),
			'social'          => array( 'instagram' => '', 'facebook' => '', 'youtube' => '' ),
		),
		'patrick-haak' => array(
			'name'            => 'Patrick Haak',
			'legal_name'      => '',
			'website'         => '',
			'logo'            => '',
			'emails'          => array(),
			'contact_persons' => array(),
			'social'          => array( 'instagram' => '', 'facebook' => '', 'youtube' => '' ),
		),
	),
	'venues'     => array(
		'kanso-konz' => array(
			'name'          => 'kanso – Zentrum für Körper, Geist und Seele',
			'address'       => array(
				'street'      => 'Im Weerberg 2a',
				'postal_code' => '54329',
				'city'        => 'Konz',
				'country'     => 'Germany',
				'country_code' => 'DE',
			),
			'timezone'      => 'Europe/Berlin',
			'website'       => 'https://kan.so',
			'parking'       => 'Details zur Parkplatzsituation folgen.',
			'accessibility' => '',
			'notes'         => '',
			'geo'           => array(
				'lat' => null,
				'lng' => null,
			),
		),
	),
	'events'     => array(
		array( 'id' => 'helsinki-2026', 'slug' => 'helsinki', 'title' => 'Helsinki', 'subtitle' => 'Helsinki Seminar', 'description' => 'Zwei Tage Seminar in Helsinki.', 'country' => 'Finland', 'country_code' => 'FI', 'flag' => '🇫🇮', 'map_x' => 63, 'map_y' => 26, 'city' => 'Helsinki', 'date_start' => '2026-08-29', 'date_end' => '2026-08-30', 'time_start' => '', 'time_end' => '', 'doors_open' => '', 'timezone' => 'Europe/Helsinki', 'organizer' => 'helsinki-hosts', 'venue' => null, 'venues' => array(), 'format' => '2-Tage-Seminar', 'audience' => '', 'level' => '', 'status' => 'confirmed', 'ticket_status' => 'coming_soon', 'ticket_shop_url' => '', 'ticket_provider' => '', 'image' => '', 'photo_credit' => '', 'languages' => array( 'fi', 'en', 'de' ), 'notes' => '', 'parking' => '', 'sort_order' => 10 ),
		array( 'id' => 'berlin-2026', 'slug' => 'berlin', 'title' => 'Berlin', 'subtitle' => 'Berlin Seminar', 'description' => 'Zwei Tage Seminar in Berlin.', 'country' => 'Germany', 'country_code' => 'DE', 'flag' => '🇩🇪', 'map_x' => 50, 'map_y' => 52, 'city' => 'Berlin', 'date_start' => '2026-09-05', 'date_end' => '2026-09-06', 'time_start' => '', 'time_end' => '', 'doors_open' => '', 'timezone' => 'Europe/Berlin', 'organizer' => 'berlin-tbd', 'venue' => null, 'venues' => array(), 'format' => '2-Tage-Seminar', 'audience' => '', 'level' => '', 'status' => 'confirmed', 'ticket_status' => 'coming_soon', 'ticket_shop_url' => '', 'ticket_provider' => '', 'image' => '', 'photo_credit' => '', 'languages' => array( 'de', 'en' ), 'notes' => '', 'parking' => '', 'sort_order' => 20 ),
		array( 'id' => 'netherlands-2026', 'slug' => 'netherlands', 'title' => 'Netherlands', 'subtitle' => 'Netherlands Seminar', 'description' => 'Zwei Tage Seminar in den Niederlanden.', 'country' => 'Netherlands', 'country_code' => 'NL', 'flag' => '🇳🇱', 'map_x' => 41, 'map_y' => 52, 'city' => 'Netherlands', 'date_start' => '2026-09-12', 'date_end' => '2026-09-13', 'time_start' => '', 'time_end' => '', 'doors_open' => '', 'timezone' => 'Europe/Amsterdam', 'organizer' => 'netherlands-hosts', 'venue' => null, 'venues' => array(), 'format' => '2-Tage-Seminar', 'audience' => '', 'level' => '', 'status' => 'confirmed', 'ticket_status' => 'coming_soon', 'ticket_shop_url' => '', 'ticket_provider' => '', 'image' => '', 'photo_credit' => '', 'languages' => array( 'nl', 'en', 'de' ), 'notes' => '', 'parking' => '', 'sort_order' => 30 ),
		array( 'id' => 'belgium-2026', 'slug' => 'belgium', 'title' => 'Belgium', 'subtitle' => 'Belgium Seminar', 'description' => 'Seminar geplant von 10:00 bis 13:00 Uhr.', 'country' => 'Belgium', 'country_code' => 'BE', 'flag' => '🇧🇪', 'map_x' => 40, 'map_y' => 58, 'city' => 'Belgium', 'date_start' => '2026-09-19', 'date_end' => '2026-09-19', 'time_start' => '10:00', 'time_end' => '13:00', 'doors_open' => '', 'timezone' => 'Europe/Brussels', 'organizer' => 'belgium-hosts', 'venue' => null, 'venues' => array(), 'format' => 'Halbtagseminar', 'audience' => '', 'level' => '', 'status' => 'confirmed', 'ticket_status' => 'coming_soon', 'ticket_shop_url' => '', 'ticket_provider' => '', 'image' => '', 'photo_credit' => '', 'languages' => array( 'fr', 'nl', 'de', 'en' ), 'notes' => '', 'parking' => '', 'sort_order' => 40 ),
		array( 'id' => 'illange-2026', 'slug' => 'illange', 'title' => 'Illange', 'subtitle' => 'Illange Seminar', 'description' => 'Seminarstation in Frankreich.', 'country' => 'France', 'country_code' => 'FR', 'flag' => '🇫🇷', 'map_x' => 43, 'map_y' => 66, 'city' => 'Illange', 'date_start' => '2026-09-21', 'date_end' => '2026-09-21', 'time_start' => '', 'time_end' => '', 'doors_open' => '', 'timezone' => 'Europe/Paris', 'organizer' => 'luxembourg-tbd', 'venue' => null, 'venues' => array(), 'format' => 'Seminar', 'audience' => '', 'level' => '', 'status' => 'confirmed', 'ticket_status' => 'coming_soon', 'ticket_shop_url' => '', 'ticket_provider' => '', 'image' => '', 'photo_credit' => '', 'languages' => array( 'fr', 'de', 'en' ), 'notes' => '', 'parking' => '', 'sort_order' => 50 ),
		array( 'id' => 'hosingen-2026', 'slug' => 'hosingen', 'title' => 'Hosingen', 'subtitle' => 'Hosingen Seminar', 'description' => 'Seminarstation in Luxemburg.', 'country' => 'Luxembourg', 'country_code' => 'LU', 'flag' => '🇱🇺', 'city' => 'Hosingen', 'date_start' => '2026-09-22', 'date_end' => '2026-09-22', 'time_start' => '', 'time_end' => '', 'doors_open' => '', 'timezone' => 'Europe/Luxembourg', 'organizer' => 'luxembourg-tbd', 'venue' => null, 'venues' => array(), 'format' => 'Seminar', 'audience' => '', 'level' => '', 'status' => 'confirmed', 'ticket_status' => 'coming_soon', 'ticket_shop_url' => '', 'ticket_provider' => '', 'image' => '', 'photo_credit' => '', 'languages' => array( 'fr', 'de', 'lb', 'en' ), 'notes' => '', 'parking' => '', 'sort_order' => 60 ),
		array( 'id' => 'trier-kids-2026', 'slug' => 'trier-kinderseminar', 'title' => 'Trier Kinderseminar', 'subtitle' => 'Kinderseminar', 'description' => 'Kinderseminar in Trier im Rahmen der TAKA European Tour.', 'country' => 'Germany', 'country_code' => 'DE', 'flag' => '🇩🇪', 'city' => 'Trier', 'date_start' => '2026-09-26', 'date_end' => '2026-09-26', 'time_start' => '', 'time_end' => '', 'doors_open' => '', 'timezone' => 'Europe/Berlin', 'organizer' => 'kleiner-wald', 'venue' => null, 'venues' => array(), 'format' => 'Kinderseminar', 'audience' => 'Kinder und Jugendliche', 'level' => 'Alle Level', 'status' => 'confirmed', 'ticket_status' => 'available', 'ticket_shop_url' => 'https://pretix.eu/kleinerwald/2026takakids/', 'ticket_provider' => 'pretix', 'image' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/Kids-Seminar-Trier.jpeg', 'photo_credit' => '', 'languages' => array( 'de', 'en' ), 'notes' => '', 'parking' => '', 'sort_order' => 70 ),
		array( 'id' => 'konz-2026', 'slug' => 'konz', 'title' => 'Konz', 'subtitle' => 'Konz Seminar', 'description' => 'Zwei Tage Seminar in Konz mit Takafumi Nakayama Sensei.', 'country' => 'Germany', 'country_code' => 'DE', 'flag' => '🇩🇪', 'map_x' => 44, 'map_y' => 63, 'city' => 'Konz', 'date_start' => '2026-09-26', 'date_end' => '2026-09-27', 'time_start' => '', 'time_end' => '', 'doors_open' => '', 'timezone' => 'Europe/Berlin', 'organizer' => 'kleiner-wald', 'venue' => 'kanso-konz', 'venues' => array( 'kanso-konz' ), 'format' => '2-Tage-Seminar', 'audience' => 'Erwachsene und Jugendliche', 'level' => 'Alle Stilrichtungen und Level', 'status' => 'confirmed', 'ticket_status' => 'available', 'ticket_shop_url' => 'https://pretix.eu/kleinerwald/2026takakonz/', 'ticket_provider' => 'pretix', 'image' => '', 'photo_credit' => '', 'languages' => array( 'de', 'en' ), 'notes' => '', 'parking' => 'Details zur Parkplatzsituation folgen.', 'sort_order' => 80 ),
		array( 'id' => 'saarwellingen-2026', 'slug' => 'saarwellingen', 'title' => 'Saarwellingen', 'subtitle' => 'Saarwellingen Seminar', 'description' => 'Abschlussseminar der Tourstationen in der Region.', 'country' => 'Germany', 'country_code' => 'DE', 'flag' => '🇩🇪', 'city' => 'Saarwellingen', 'date_start' => '2026-09-28', 'date_end' => '2026-09-28', 'time_start' => '', 'time_end' => '', 'doors_open' => '', 'timezone' => 'Europe/Berlin', 'organizer' => 'patrick-haak', 'venue' => null, 'venues' => array(), 'format' => 'Seminar', 'audience' => '', 'level' => '', 'status' => 'confirmed', 'ticket_status' => 'coming_soon', 'ticket_shop_url' => '', 'ticket_provider' => '', 'image' => '', 'photo_credit' => '', 'languages' => array( 'de', 'en' ), 'notes' => '', 'parking' => '', 'sort_order' => 90 ),
	),
);

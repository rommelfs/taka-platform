<?php
/**
 * Static JSON translation loader.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_I18n {
	const ENABLED_LANGUAGES_OPTION = 'taka_platform_enabled_website_languages';
	private static $instance = null;
	private $translations = array();
	private $current_language = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function get_all_languages() {
		$stored = function_exists( 'get_option' ) ? get_option( self::ENABLED_LANGUAGES_OPTION, array() ) : array();
		$languages = array_values( array_intersect( TAKA_Platform_Locale_Registry::sanitize_language_codes( is_array( $stored ) && ! empty( $stored ) ? $stored : TAKA_Platform_Locale_Registry::default_website_languages() ), array_keys( TAKA_Platform_Locale_Registry::website_language_labels() ) ) );
		return ! empty( $languages ) ? $languages : TAKA_Platform_Locale_Registry::default_website_languages();
	}

	/** All ISO 639-1 languages available to source/spoken-language fields. */
	public function get_available_languages() {
		return array_keys( TAKA_Platform_Locale_Registry::language_labels() );
	}

	public function get_available_language_labels() {
		return TAKA_Platform_Locale_Registry::language_labels();
	}

	public function get_current_language() {
		if ( null !== $this->current_language ) {
			return $this->current_language;
		}

		if ( isset( $_GET['taka_lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$lang = sanitize_key( wp_unslash( $_GET['taka_lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( in_array( $lang, $this->get_all_languages(), true ) ) {
				$this->current_language = $lang;
				return $this->current_language;
			}
		}

		if ( isset( $_COOKIE['taka_lang'] ) ) {
			$lang = sanitize_key( wp_unslash( $_COOKIE['taka_lang'] ) );
			if ( in_array( $lang, $this->get_all_languages(), true ) ) {
				$this->current_language = $lang;
				return $this->current_language;
			}
		}

		$accepted = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) : '';
		$preferences = array();
		foreach ( explode( ',', $accepted ) as $index => $part ) {
			if ( ! preg_match( '/^\s*([a-z]{2})(?:-[a-z0-9]+)?(?:\s*;\s*q=(0(?:\.\d+)?|1(?:\.0+)?))?/i', $part, $match ) ) { continue; }
			$quality = isset( $match[2] ) ? (float) $match[2] : 1.0;
			if ( $quality > 0 ) { $preferences[] = array( 'lang' => strtolower( $match[1] ), 'quality' => $quality, 'index' => $index ); }
		}
		usort( $preferences, static function ( $a, $b ) { return ( $b['quality'] <=> $a['quality'] ) ?: ( $a['index'] <=> $b['index'] ); } );
		foreach ( $preferences as $preference ) {
			if ( in_array( $preference['lang'], $this->get_all_languages(), true ) ) { $this->current_language = $preference['lang']; return $this->current_language; }
		}

		$this->current_language = $this->default_enabled_language();
		return $this->current_language;
	}

	/**
	 * Set the active language for controlled rendering contexts.
	 *
	 * Exporters use this to render a complete static archive in one selected
	 * language without relying on request cookies or browser preferences.
	 *
	 * @param string $lang Language code.
	 * @return string Active language after sanitization.
	 */
	public function set_current_language( $lang ) {
		$lang = sanitize_key( (string) $lang );
		if ( ! in_array( $lang, $this->get_all_languages(), true ) ) {
			$lang = $this->default_enabled_language();
		}
		$this->current_language = $lang;
		return $this->current_language;
	}

	public function translate( $path, $fallback = '', $lang = null ) {
		$lang  = $lang ?: $this->get_current_language();
		$value = $this->get_value( $lang, $path );
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}

		foreach ( array( 'en', 'de' ) as $fallback_lang ) {
			if ( $fallback_lang === $lang ) { continue; }
			$value = $this->get_value( $fallback_lang, $path );
			if ( is_string( $value ) && '' !== $value ) { return $value; }
		}

		return $fallback;
	}

	public function get_language_switcher_items() {
		$items = array(
			array( 'type' => 'link', 'code' => 'en', 'icon' => '🌍', 'label' => 'International – English' ),
			array( 'type' => 'link', 'code' => 'de', 'icon' => '🇩🇪', 'label' => 'Deutschland – Deutsch' ),
			array( 'type' => 'link', 'code' => 'fr', 'icon' => '🇫🇷', 'label' => 'France – Français' ),
			array( 'type' => 'link', 'code' => 'nl', 'icon' => '🇳🇱', 'label' => 'Nederland – Nederlands' ),
			array(
				'type' => 'dropdown',
				'icon' => '🇧🇪',
				'label' => 'Belgien – Sprache wählen',
				'items' => array(
					array( 'code' => 'nl', 'label' => 'Nederlands' ),
					array( 'code' => 'fr', 'label' => 'Français' ),
					array( 'code' => 'de', 'label' => 'Deutsch' ),
				),
			),
			array(
				'type' => 'dropdown',
				'icon' => '🇱🇺',
				'label' => 'Luxemburg – Sprache wählen',
				'items' => array(
					array( 'code' => 'lb', 'label' => 'Lëtzebuergesch' ),
					array( 'code' => 'fr', 'label' => 'Français' ),
					array( 'code' => 'de', 'label' => 'Deutsch' ),
				),
			),
			array( 'type' => 'link', 'code' => 'fi', 'icon' => '🇫🇮', 'label' => 'Suomi – Finnisch' ),
			array( 'type' => 'link', 'code' => 'it', 'icon' => '🇮🇹', 'label' => 'Italia – Italiano' ),
			array( 'type' => 'link', 'code' => 'ja', 'icon' => '🇯🇵', 'label' => '日本 – Japanese' ),
		);
		$enabled = $this->get_all_languages();
		$filtered = array();
		$represented = array();
		foreach ( $items as $item ) {
			if ( 'dropdown' === ( $item['type'] ?? '' ) ) {
				$item['items'] = array_values( array_filter( (array) ( $item['items'] ?? array() ), static function ( $choice ) use ( $enabled ) {
					return in_array( $choice['code'] ?? '', $enabled, true );
				} ) );
				if ( empty( $item['items'] ) ) { continue; }
				foreach ( $item['items'] as $choice ) { $represented[] = $choice['code']; }
				$filtered[] = $item;
				continue;
			}
			if ( ! in_array( $item['code'] ?? '', $enabled, true ) ) { continue; }
			$represented[] = $item['code'];
			$filtered[] = $item;
		}

		$labels = TAKA_Platform_Locale_Registry::language_labels();
		foreach ( array_diff( $enabled, array_unique( $represented ) ) as $code ) {
			$filtered[] = array(
				'type' => 'link',
				'code' => $code,
				'icon' => '🌐',
				'label' => ( $labels[ $code ] ?? strtoupper( $code ) ) . ' (' . $code . ')',
			);
		}
		return $filtered;
	}

	private function default_enabled_language() {
		$enabled = $this->get_all_languages();
		foreach ( array( 'de', 'en' ) as $preferred ) {
			if ( in_array( $preferred, $enabled, true ) ) { return $preferred; }
		}
		return (string) reset( $enabled );
	}


	/** Return decoded language data for audits/tools. */
	public function get_language_data( $lang ) {
		return $this->load_language( $lang );
	}

	/** Flatten nested arrays into dot-notation translation keys. */
	public function flatten_keys( $data, $prefix = '' ) {
		$keys = array();
		foreach ( (array) $data as $key => $value ) {
			$path = '' === $prefix ? (string) $key : $prefix . '.' . $key;
			if ( is_array( $value ) ) {
				$keys += $this->flatten_keys( $value, $path );
			} else {
				$keys[ $path ] = $value;
			}
		}
		return $keys;
	}

	/** Build a translation completeness audit against the canonical English file. */
	public function audit() {
		$base = $this->flatten_keys( $this->load_language( 'en' ) );
		$report = array();
		foreach ( $this->get_all_languages() as $lang ) {
			$flat = $this->flatten_keys( $this->load_language( $lang ) );
			$fallback_used = array();
			if ( 'en' !== $lang ) {
				foreach ( $base as $key => $value ) {
					if ( array_key_exists( $key, $flat ) && (string) $flat[ $key ] === (string) $value ) {
						$fallback_used[] = $key;
					}
				}
			}
			$report[ $lang ] = array(
				'count' => count( $flat ),
				'missing' => array_values( array_diff( array_keys( $base ), array_keys( $flat ) ) ),
				'extra' => array_values( array_diff( array_keys( $flat ), array_keys( $base ) ) ),
				'fallback_used' => $fallback_used,
			);
		}
		return array( 'base_count' => count( $base ), 'languages' => $report );
	}

	private function get_value( $lang, $path ) {
		$data = $this->load_language( $lang );
		foreach ( explode( '.', $path ) as $part ) {
			if ( ! is_array( $data ) || ! array_key_exists( $part, $data ) ) {
				return null;
			}
			$data = $data[ $part ];
		}
		return $data;
	}

	private function load_language( $lang ) {
		if ( isset( $this->translations[ $lang ] ) ) {
			return $this->translations[ $lang ];
		}

		$file = TAKA_TOUR_PLUGIN_DIR . 'translations/' . $lang . '.json';
		if ( ! file_exists( $file ) ) {
			$this->translations[ $lang ] = array();
			return array();
		}

		$decoded = json_decode( file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->translations[ $lang ] = is_array( $decoded ) ? $decoded : array();
		return $this->translations[ $lang ];
	}
}

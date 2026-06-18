<?php
/**
 * Static JSON translation loader.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_I18n {
	private static $instance = null;
	private $translations = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function get_all_languages() {
		return array( 'de', 'en', 'nl', 'fr', 'lb', 'fi', 'ja' );
	}

	public function get_current_language() {
		if ( isset( $_GET['taka_lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$lang = sanitize_key( wp_unslash( $_GET['taka_lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( in_array( $lang, $this->get_all_languages(), true ) ) {
				return $lang;
			}
		}

		$accepted = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) : '';
		foreach ( explode( ',', $accepted ) as $part ) {
			$lang = strtolower( substr( trim( $part ), 0, 2 ) );
			if ( in_array( $lang, $this->get_all_languages(), true ) ) {
				return $lang;
			}
		}

		return 'de';
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
		return array(
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
			array( 'type' => 'link', 'code' => 'ja', 'icon' => '🇯🇵', 'label' => '日本 – Japanese' ),
		);
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
			$report[ $lang ] = array(
				'count' => count( $flat ),
				'missing' => array_values( array_diff( array_keys( $base ), array_keys( $flat ) ) ),
				'extra' => array_values( array_diff( array_keys( $flat ), array_keys( $base ) ) ),
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

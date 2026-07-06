<?php
/**
 * Regression check for legacy seminar_description translation aliases.
 *
 * This script intentionally uses tiny WordPress stubs so the resolver can be
 * tested without booting a full WordPress install.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
foreach ( array(
	'TAKA_PLATFORM_CPT_EVENT' => 'taka_event',
	'TAKA_PLATFORM_CPT_ORGANIZER' => 'taka_organizer',
	'TAKA_PLATFORM_CPT_VENUE' => 'taka_venue',
	'TAKA_PLATFORM_CPT_CONTENT_BLOCK' => 'taka_content_block',
	'TAKA_PLATFORM_CPT_TOUR_PLANNING' => 'taka_tour_planning',
) as $constant => $value ) {
	if ( ! defined( $constant ) ) {
		define( $constant, $value );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) {
		return trim( (string) $value );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( (string) $value );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $value ) {
		return (string) $value;
	}
}

$GLOBALS['taka_test_post_meta'] = array();
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$value = $GLOBALS['taka_test_post_meta'][ absint( $post_id ) ][ $key ] ?? ( $single ? '' : array() );
		return $value;
	}
}

if ( ! function_exists( 'taka_tour_current_language' ) ) {
	function taka_tour_current_language() {
		return 'nl';
	}
}

if ( ! class_exists( 'TAKA_Platform_I18n' ) ) {
	class TAKA_Platform_I18n {
		public static function instance() {
			static $instance = null;
			if ( null === $instance ) {
				$instance = new self();
			}
			return $instance;
		}

		public function get_all_languages() {
			return array( 'de', 'en', 'fr', 'nl', 'lb', 'fi', 'ja' );
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/Data/class-repository.php';

$expected = 'Tweedaags seminar in Nederland met Takafumi ‘Taka’ Nakayama';
$object = array(
	'source_language' => 'de',
	'description' => "Zwei Tage Seminar in den Niederlanden mit Takafumi 'Taka' Nakayama",
	'text_translations' => array(
		'seminar_description' => array(
			'nl' => $expected,
		),
	),
);

$fields = TAKA_Platform_Data::translatable_text_fields( 'event' );
$values = TAKA_Platform_Data::object_text_values( $object, $fields );
$actual = TAKA_Platform_Data::resolve_dynamic_text( $values['description'], 'nl', 'de', array( 'object_type' => 'event', 'object_id' => 'regression', 'field' => 'description' ) );

if ( $expected !== $actual ) {
	fwrite( STDERR, "FAIL: seminar_description alias did not resolve.\nExpected: {$expected}\nActual: {$actual}\n" );
	exit( 1 );
}

if ( false !== strpos( $actual, 'Zwei Tage Seminar' ) ) {
	fwrite( STDERR, "FAIL: Dutch frontend value fell back to German original.\n" );
	exit( 1 );
}

$GLOBALS['taka_test_post_meta'][123][ TAKA_Platform_Data::TEXT_TRANSLATION_SOURCE_HASHES_META ] = array(
	'description' => array(
		'source_language' => 'de',
		'source_hash' => hash( 'sha256', 'older German source text' ),
	),
);

$reflection = new ReflectionClass( 'TAKA_Platform_Data' );
$filter = $reflection->getMethod( 'filter_stale_post_text_translations' );
$filtered = $filter->invoke(
	null,
	123,
	'de',
	array( 'description' => $object['description'] ),
	array( 'description' => array( 'nl' => $expected ) )
);

if ( $expected !== (string) ( $filtered['description']['nl'] ?? '' ) ) {
	fwrite( STDERR, "FAIL: stale source hash incorrectly suppressed saved Dutch website translation.\n" );
	exit( 1 );
}

echo "PASS: seminar_description alias resolves to Dutch frontend description.\n";

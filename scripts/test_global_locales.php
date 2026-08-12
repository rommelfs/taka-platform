<?php
/**
 * Regression tests for global country/language support and non-destructive locales.
 */

define( 'ABSPATH', __DIR__ );
define( 'TAKA_PLATFORM_CPT_EVENT', 'taka_event' );
define( 'TAKA_PLATFORM_CPT_ORGANIZER', 'taka_organizer' );
define( 'TAKA_PLATFORM_CPT_VENUE', 'taka_venue' );
define( 'TAKA_PLATFORM_CPT_CONTENT_BLOCK', 'taka_content_block' );
define( 'TAKA_PLATFORM_CPT_TOUR_PLANNING', 'taka_tour_plan' );

function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( (string) $value ); }
function wp_kses_post( $value ) { return (string) $value; }
function get_option( $key, $default = array() ) { return $GLOBALS['taka_locale_options'][ $key ] ?? $default; }
function taka_tour_current_language() { return 'it'; }

require_once dirname( __DIR__ ) . '/includes/I18n/class-locale-registry.php';
require_once dirname( __DIR__ ) . '/includes/I18n/class-i18n.php';
require_once dirname( __DIR__ ) . '/includes/ImportExport/class-translation-packages.php';
require_once dirname( __DIR__ ) . '/includes/Data/class-repository.php';

$languages = TAKA_Platform_Locale_Registry::language_labels();
if ( count( $languages ) < 180 || 'Italiano' !== ( $languages['it'] ?? '' ) || ! isset( $languages['ar'], $languages['zh'], $languages['sw'] ) ) {
	fwrite( STDERR, 'The ISO language registry is incomplete.' . PHP_EOL );
	exit( 1 );
}
if ( ! in_array( 'it', TAKA_Platform_Locale_Registry::default_website_languages(), true ) ) {
	fwrite( STDERR, 'Italian is not enabled for existing installations by default.' . PHP_EOL );
	exit( 1 );
}

$countries = TAKA_Platform_Data::country_choices( 'it' );
if ( count( $countries ) < 249 || 'Italia' !== ( $countries['IT'] ?? '' ) || ! isset( $countries['BR'], $countries['ZA'], $countries['NZ'] ) ) {
	fwrite( STDERR, 'The Event country choices do not expose the complete ISO country registry.' . PHP_EOL );
	exit( 1 );
}
$event_country_choices = TAKA_Platform_Data::option_list_choices( 'country', 'it' );
if ( false === strpos( $event_country_choices['IT'] ?? '', '🇮🇹' ) || ! isset( $event_country_choices['BR'] ) ) {
	fwrite( STDERR, 'The Event editor country field is missing Italy or global choices.' . PHP_EOL );
	exit( 1 );
}
if ( 'IT' !== TAKA_Platform_Data::country_code_for_value( 'Italy' ) || 'Europe/Rome' !== TAKA_Platform_Data::timezone_for_country( 'IT' ) || 'EUR' !== TAKA_Platform_Data::currency_for_country( 'IT' ) ) {
	fwrite( STDERR, 'Italian country defaults were not resolved correctly.' . PHP_EOL );
	exit( 1 );
}
if ( array( 'it', 'en' ) !== TAKA_Platform_Data::languages_for_country( 'IT' ) ) {
	fwrite( STDERR, 'Italian Event language defaults were not resolved correctly.' . PHP_EOL );
	exit( 1 );
}

$normalized = TAKA_Platform_Data::normalize_object_text_translations(
	array( 'subtitle' => array( 'de' => 'Deutsch', 'es' => 'Español' ) ),
	array( 'subtitle' => 'Subtitle' )
);
if ( 'Español' !== ( $normalized['subtitle']['es'] ?? '' ) ) {
	fwrite( STDERR, 'A stored translation was lost because its website language was disabled.' . PHP_EOL );
	exit( 1 );
}

$labels = TAKA_Platform_Translation_Packages::translation_language_labels( 'ar' );
if ( 'Arabic' !== ( $labels['ar'] ?? '' ) ) {
	fwrite( STDERR, 'A non-enabled original language cannot be edited with its object.' . PHP_EOL );
	exit( 1 );
}

$GLOBALS['taka_locale_options'][ TAKA_Platform_I18n::ENABLED_LANGUAGES_OPTION ] = array( 'it', 'ar' );
$switcher = TAKA_Platform_I18n::instance()->get_language_switcher_items();
$switcher_codes = array();
foreach ( $switcher as $item ) {
	if ( 'dropdown' === ( $item['type'] ?? '' ) ) {
		$switcher_codes = array_merge( $switcher_codes, array_column( $item['items'] ?? array(), 'code' ) );
	} else {
		$switcher_codes[] = $item['code'] ?? '';
	}
}
if ( ! in_array( 'it', $switcher_codes, true ) || ! in_array( 'ar', $switcher_codes, true ) || in_array( 'de', $switcher_codes, true ) ) {
	fwrite( STDERR, 'The public language switcher does not follow the enabled website languages.' . PHP_EOL );
	exit( 1 );
}
if ( 'it' !== TAKA_Platform_I18n::instance()->set_current_language( 'de' ) ) {
	fwrite( STDERR, 'The current language did not fall back to an enabled website language.' . PHP_EOL );
	exit( 1 );
}

echo 'Global locale regression tests passed.' . PHP_EOL;

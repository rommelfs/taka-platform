<?php
/**
 * Regression test for the one-time repair of stored protected names.
 */

define( 'ABSPATH', __DIR__ );
define( 'TAKA_PLATFORM_CPT_EVENT', 'taka_event' );
define( 'TAKA_PLATFORM_CPT_ORGANIZER', 'taka_organizer' );
define( 'TAKA_PLATFORM_CPT_VENUE', 'taka_venue' );
define( 'TAKA_PLATFORM_CPT_CONTENT_BLOCK', 'taka_content_block' );

$GLOBALS['taka_test_options'] = array();
$GLOBALS['taka_test_meta'] = array(
	7 => array(
		'_taka_source_language' => 'de',
		'_taka_short_description' => 'TAKAfumi "TAKA" Nakayama besucht kanso mit KANADE.',
		'_taka_text_translations' => array(
			'description' => array(
				'de' => 'TAKAfumi "TAKA" Nakayama besucht kanso mit KANADE.',
				'en' => 'TAKAfumi "TAKA" Nakayama visits however with Canada.',
			),
		),
	),
);
$GLOBALS['taka_test_post'] = (object) array(
	'ID' => 7,
	'post_title' => 'Seminar with Takafumi “Taka” Nakayama',
	'post_content' => 'Takafumi Nakayama teaches at kanso.',
);

function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( (string) $value ); }
function get_option( $key, $default = false ) { return $GLOBALS['taka_test_options'][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['taka_test_options'][ $key ] = $value; return true; }
function current_user_can() { return true; }
function get_posts( $args ) { return TAKA_PLATFORM_CPT_EVENT === $args['post_type'] ? array( 7 ) : array(); }
function get_post() { return $GLOBALS['taka_test_post']; }
function wp_update_post( $values ) {
	$GLOBALS['taka_test_post']->post_title = $values['post_title'];
	$GLOBALS['taka_test_post']->post_content = $values['post_content'];
	return 7;
}
function get_post_meta( $post_id, $key ) { return $GLOBALS['taka_test_meta'][ $post_id ][ $key ] ?? ''; }
function update_post_meta( $post_id, $key, $value ) { $GLOBALS['taka_test_meta'][ $post_id ][ $key ] = $value; return true; }
function clean_post_cache() {}

class TAKA_Platform_I18n {
	public static function instance() { return new self(); }
	public function get_all_languages() { return array( 'de', 'en', 'fr', 'nl', 'lb', 'fi', 'ja' ); }
}

require_once dirname( __DIR__ ) . '/includes/ImportExport/class-translation-packages.php';

TAKA_Platform_Translation_Packages::maybe_normalize_stored_protected_names();

$canonical = "Takafumi 'Taka' Nakayama";
$source = get_post_meta( 7, '_taka_short_description', true );
$translations = get_post_meta( 7, '_taka_text_translations', true );
if ( $canonical . ' besucht kanso mit Kanade.' !== $source ) {
	fwrite( STDERR, 'The stored source field was not normalized.' . PHP_EOL );
	exit( 1 );
}
if ( $canonical . ' visits kanso with Kanade.' !== $translations['description']['en'] ) {
	fwrite( STDERR, 'The stored website translation was not normalized against its source.' . PHP_EOL );
	exit( 1 );
}
if ( 'Seminar with ' . $canonical !== $GLOBALS['taka_test_post']->post_title ) {
	fwrite( STDERR, 'The stored post title was not normalized.' . PHP_EOL );
	exit( 1 );
}
if ( $canonical . ' teaches at kanso.' !== $GLOBALS['taka_test_post']->post_content ) {
	fwrite( STDERR, 'The stored post content was not normalized.' . PHP_EOL );
	exit( 1 );
}
if ( 1 !== (int) get_option( TAKA_Platform_Translation_Packages::PROTECTED_NAMES_MIGRATION_OPTION, 0 ) ) {
	fwrite( STDERR, 'The protected-name migration was not marked complete.' . PHP_EOL );
	exit( 1 );
}

echo 'Protected-name migration regression test passed.' . PHP_EOL;

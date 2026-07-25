<?php
/**
 * Focused regression test for Event admin field persistence without a WordPress install.
 */

define( 'ABSPATH', __DIR__ );
define( 'TAKA_PLATFORM_CPT_EVENT', 'taka_event' );
define( 'TAKA_PLATFORM_CPT_VENUE', 'taka_venue' );
define( 'TAKA_PLATFORM_CPT_ORGANIZER', 'taka_organizer' );
define( 'TAKA_PLATFORM_CPT_CONTENT_BLOCK', 'taka_content_block' );

$GLOBALS['taka_test_meta'] = array();
$GLOBALS['taka_test_title'] = '';

function wp_is_post_revision() { return false; }
function wp_is_post_autosave() { return false; }
function wp_verify_nonce() { return true; }
function current_user_can() { return true; }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( (string) $value ); }
function sanitize_email( $value ) { return filter_var( $value, FILTER_SANITIZE_EMAIL ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\\-]/', '', (string) $value ) ); }
function esc_url_raw( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_URL ); }
function absint( $value ) { return abs( (int) $value ); }
function get_the_title() { return $GLOBALS['taka_test_title']; }
function get_post_meta( $post_id, $key ) { return $GLOBALS['taka_test_meta'][ $post_id ][ $key ] ?? ''; }
function update_post_meta( $post_id, $key, $value ) { $GLOBALS['taka_test_meta'][ $post_id ][ $key ] = $value; return true; }
function delete_post_meta( $post_id, $key ) { unset( $GLOBALS['taka_test_meta'][ $post_id ][ $key ] ); return true; }

class TAKA_Platform_Translation_Packages {
	public static function sanitize_language( $value ) { return sanitize_key( $value ) ?: 'de'; }
}

class TAKA_Platform_Data {
	public static function normalize_event_option_value( $field, $value ) { return sanitize_key( $value ); }
	public static function normalize_language_codes( $value ) { return array_values( array_filter( (array) $value ) ); }
	public static function sanitize_money_value( $value ) { return (string) $value; }
	public static function normalize_program_items( $items ) { return array_values( (array) $items ); }
	public static function compare_program_items( $a, $b ) {
		return strcmp( (string) ( $a['date'] ?? '' ), (string) ( $b['date'] ?? '' ) )
			?: strcmp( (string) ( $a['time_start'] ?? '' ), (string) ( $b['time_start'] ?? '' ) );
	}
}

require_once dirname( __DIR__ ) . '/includes/Admin/class-admin.php';

$post_id = 42;
$_POST = array(
	TAKA_Platform_Admin::NONCE => 'valid',
	TAKA_Platform_Admin::EVENT_FORM_MARKER => '1',
	'post_title' => 'Persisted Event Title',
	'_taka_source_language' => 'de',
	'taka_platform_text_translations' => array( 'subtitle' => array( 'de' => 'Persisted Subtitle' ) ),
	'_taka_format' => 'seminar',
	'_taka_audience' => 'adults',
	'_taka_level' => 'advanced',
	'_taka_ticket_status' => 'available',
	'_taka_ticket_provider' => 'pretix',
	'_taka_ticket_shop_url' => 'https://tickets.example.test/event/',
	'_taka_organizer_id' => '101',
	'_taka_venue_id' => '202',
);
$GLOBALS['taka_test_title'] = sanitize_text_field( $_POST['post_title'] );

$call_private = Closure::bind(
	static function ( $method, ...$arguments ) { return TAKA_Platform_Admin::$method( ...$arguments ); },
	null,
	'TAKA_Platform_Admin'
);
$call_private( 'save', $post_id, array( 'subtitle', 'format', 'audience', 'level', 'ticket_status', 'ticket_provider', 'ticket_shop_url', 'organizer_id', 'venue_id' ) );
update_post_meta( $post_id, '_taka_subtitle', sanitize_text_field( $_POST['taka_platform_text_translations']['subtitle']['de'] ) );

if ( true !== $call_private( 'event_submitted_values_persisted', $post_id ) ) {
	fwrite( STDERR, "Event persistence verification failed.\n" );
	exit( 1 );
}

$relationships = $call_private(
	'synchronize_primary_event_organizer',
	array( array( 'organizer_id' => '999', 'relationship_type' => 'organizer', 'visible' => 1, 'sort_order' => 10 ) ),
	101
);
if ( '101' !== (string) ( $relationships[0]['organizer_id'] ?? '' ) || 'organizer' !== ( $relationships[0]['relationship_type'] ?? '' ) ) {
	fwrite( STDERR, "Primary organizer synchronization failed.\n" );
	exit( 1 );
}

$expected = array(
	'_taka_subtitle' => 'Persisted Subtitle',
	'_taka_format' => 'seminar',
	'_taka_audience' => 'adults',
	'_taka_level' => 'advanced',
	'_taka_ticket_status' => 'available',
	'_taka_ticket_provider' => 'pretix',
	'_taka_ticket_shop_url' => 'https://tickets.example.test/event/',
	'_taka_organizer_id' => 101,
	'_taka_venue_id' => 202,
);
foreach ( $expected as $key => $value ) {
	if ( (string) $value !== (string) get_post_meta( $post_id, $key, true ) ) {
		fwrite( STDERR, sprintf( "Unexpected %s value.\n", $key ) );
		exit( 1 );
	}
}

$call_private(
	'synchronize_event_date_meta_from_program_items',
	$post_id,
	array(
		array( 'date' => '2026-09-06', 'time_start' => '10:00', 'time_end' => '16:00' ),
		array( 'date' => '2026-09-05', 'time_start' => '09:00', 'time_end' => '17:00' ),
	)
);
$expected_dates = array(
	'_taka_date_start' => '2026-09-05',
	'_taka_date_end' => '2026-09-06',
	'_taka_time_start' => '09:00',
	'_taka_time_end' => '16:00',
);
foreach ( $expected_dates as $key => $value ) {
	if ( $value !== get_post_meta( $post_id, $key, true ) ) {
		fwrite( STDERR, sprintf( "Unexpected synchronized %s value.\n", $key ) );
		exit( 1 );
	}
}

echo "Event admin persistence regression test passed.\n";

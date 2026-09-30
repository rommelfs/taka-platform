<?php
/** Regression coverage for tour isolation, archive policy and scoped rendering. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['tour_posts'] = array(
	1 => (object) array( 'ID' => 1, 'post_title' => 'Tour 2026', 'post_type' => 'taka_public_tour', 'post_status' => 'publish' ),
	2 => (object) array( 'ID' => 2, 'post_title' => 'June 2027', 'post_type' => 'taka_public_tour', 'post_status' => 'publish' ),
	3 => (object) array( 'ID' => 3, 'post_title' => 'September 2027', 'post_type' => 'taka_public_tour', 'post_status' => 'publish' ),
	4 => (object) array( 'ID' => 4, 'post_title' => 'Private draft', 'post_type' => 'taka_public_tour', 'post_status' => 'draft' ),
);
$GLOBALS['tour_meta'] = array(
	1 => array( '_taka_tour_settings' => array( 'state' => 'archive', 'legacy_design' => '1' ), '_taka_tour_snapshot' => array( 'hero' => array( 'title' => 'Preserved' ) ) ),
	2 => array( '_taka_tour_settings' => array( 'title' => array( 'en' => 'June 2027' ), 'theme' => array( 'en' => 'New theme' ) ) ),
	10 => array( '_taka_public_tour' => 1 ), 20 => array( '_taka_public_tour' => 2 ),
	30 => array( '_taka_public_tour' => 3 ), 40 => array( '_taka_public_tour' => 4 ),
);
$GLOBALS['tour_options'] = array( 'taka_platform_config_event_tours' => array( 'legacy' => 1 ), 'hero' => array( 'title' => 'Global' ) );
$GLOBALS['tour_filters'] = array();
function add_filter( $name, $callback ) { $GLOBALS['tour_filters'][ $name ][] = $callback; }
function remove_filter( $name, $callback ) { $GLOBALS['tour_filters'][ $name ] = array_filter( $GLOBALS['tour_filters'][ $name ] ?? array(), static function ( $item ) use ( $callback ) { return $item !== $callback; } ); }
function apply_filters( $name, $value ) { foreach ( $GLOBALS['tour_filters'][ $name ] ?? array() as $callback ) { $value = $callback( $value ); } return $value; }
function get_posts( $args ) { return array_values( array_filter( $GLOBALS['tour_posts'], static function ( $post ) use ( $args ) { return in_array( $post->post_status, (array) $args['post_status'], true ); } ) ); }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['tour_meta'][ $id ][ $key ] ?? ''; }
function get_option( $key, $default = false ) { $pre = apply_filters( 'pre_option_' . $key, false ); return false !== $pre ? $pre : ( $GLOBALS['tour_options'][ $key ] ?? $default ); }
function get_post( $id ) { return $GLOBALS['tour_posts'][ $id ] ?? null; }
function get_post_type( $id ) { return get_post( $id )->post_type ?? ''; }
function get_post_status( $id ) { return get_post( $id )->post_status ?? ''; }
function get_the_title( $id ) { return get_post( $id )->post_title ?? ''; }
function current_user_can() { return false; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_hex_color( $value ) { return preg_match( '/^#[0-9a-f]{6}$/i', $value ) ? $value : ''; }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html( $value ) { return esc_attr( $value ); }
function esc_url( $value ) { return esc_attr( $value ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function get_permalink() { return 'https://example.org/tours/'; }
function remove_query_arg( $keys, $url ) { return $url; }
function add_query_arg( $key, $value, $url ) { return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . urlencode( $key ) . '=' . urlencode( $value ); }
function taka_tour_render_template() { return ''; }
function taka_tour_translate( $key, $fallback ) { return $fallback; }
function taka_tour_current_language() { return 'en'; }
class TAKA_Platform_Data {
	const HERO_OPTION = 'hero', SECTIONS_OPTION = 'sections', MEDIA_OPTION = 'media', BOOKING_OPTION = 'booking', TICKETS_OPTION = 'tickets';
	public static function get_events_for_translation_packages() { return array( array( 'wp_post_id' => 50, 'date_start' => '2026-09-01' ), array( 'wp_post_id' => 60, 'date_start' => '2027-06-01' ), array( 'wp_post_id' => 20, 'date_start' => '2026-09-02' ) ); }
	public static function get_hero_settings() { return array( 'title' => 'Preserved' ); }
	public static function get_content_sections() { return array(); }
	public static function get_public_events() { return array(); }
	public static function resolve_dynamic_text( $text ) { return is_array( $text ) ? ( $text['en'] ?? '' ) : $text; }
	public static function resolve_attachment_url( $id, $size, $url ) { return $url; }
}
require_once dirname( __DIR__ ) . '/includes/Tours/class-overview.php';
require_once dirname( __DIR__ ) . '/includes/Tours/class-tours.php';
function check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
$events = array_map( static function ( $id ) { return array( 'wp_post_id' => $id ); }, array( 10, 20, 30, 40 ) );
check( TAKA_Platform_Tours::event_archived( 10 ), 'Archived event detected without request context.' );
check( ! TAKA_Platform_Tours::event_archived( 20 ), 'Future tour must remain bookable.' );
check( TAKA_Platform_Tours::event_archived( array( 'id' => 'legacy' ) ), 'Config-only legacy event is archived.' );
check( count( TAKA_Platform_Tours::filter_events( $events ) ) === 3, 'Draft tour events must not leak into public endpoints.' );
foreach ( array( 1 => 10, 2 => 20, 3 => 30 ) as $tour => $event_id ) {
	TAKA_Platform_Tours::render( array( 'tour' => $tour ), static function () use ( $events, $event_id, $tour ) {
		check( array_column( TAKA_Platform_Tours::filter_events( $events ), 'wp_post_id' ) === array( $event_id ), 'Tour events mixed.' );
		check( TAKA_Platform_Tours::archive_mode( false ) === ( 1 === $tour ), 'Incorrect archive scope.' );
		if ( 1 === $tour ) { check( get_option( 'hero' )['title'] === 'Preserved', 'Snapshot not applied.' ); }
		return '';
	} );
}
try {
	TAKA_Platform_Tours::render( array( 'tour' => 1 ), static function () { throw new RuntimeException( 'Expected' ); } );
} catch ( RuntimeException $error ) { check( 'Expected' === $error->getMessage(), 'Unexpected render failure.' ); }
check( null === TAKA_Platform_Tours::context(), 'Context leaked after exception.' );
check( get_option( 'hero' )['title'] === 'Global', 'Archive snapshot leaked into other rendering.' );
check( ! TAKA_Platform_Tours::archive_mode( false ), 'Archive state leaked.' );
check( strpos( TAKA_Platform_Tours::render( array( 'tour' => 4 ), static function () { return 'SECRET'; } ), 'SECRET' ) === false, 'Draft exposed.' );
check( strpos( TAKA_Platform_Tours::render( array( 'tour' => 999 ), static function () { return 'SECRET'; } ), 'SECRET' ) === false, 'Invalid ID fell back to all events.' );
$directory = TAKA_Platform_Tours::render( array(), static function () { return 'LEGACY'; } );
check( strpos( $directory, 'June 2027' ) !== false && strpos( $directory, 'September 2027' ) !== false, 'Two tours in one year missing.' );
check( strpos( $directory, 'Tour 2026' ) === false, 'Archived tour in current directory.' );
$_GET['taka_tours'] = 'archive';
$archive = TAKA_Platform_Tours::render( array(), static function () { return ''; } );
check( strpos( $archive, 'Tour 2026' ) !== false && strpos( $archive, 'June 2027' ) === false, 'Archive directory mixed.' );
if ( isset( $argv[1] ) ) { file_put_contents( $argv[1], '<!doctype html><html lang="en"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="file://' . dirname( __DIR__ ) . '/assets/css/frontend.css"><title>Tour selection</title>' . $directory . $archive . '</html>' ); }
echo "Public tour regression checks passed.\n";

// Exercise the actual setup service against an in-memory WordPress store.
function wp_insert_post( $data, $error = false ) {
	$id = max( array_keys( $GLOBALS['tour_posts'] ) ) + 1;
	$GLOBALS['tour_posts'][ $id ] = (object) array_merge( $data, array( 'ID' => $id ) );
	return $id;
}
function update_post_meta( $id, $key, $value ) { $GLOBALS['tour_meta'][ $id ][ $key ] = $value; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['tour_options'][ $key ] = $value; }
function is_wp_error() { return false; }
require_once dirname( __DIR__ ) . '/includes/Tours/class-tours-admin.php';
$ids = TAKA_Platform_Tours_Admin::provision_initial_tours();
check( count( $ids ) === 3, 'Initial setup must create three independent tours.' );
check( get_post_status( $ids['2026'] ) === 'publish' && TAKA_Platform_Tours::archived( $ids['2026'] ), '2026 must become a public archive.' );
check( get_post_status( $ids['2027-06'] ) === 'draft' && get_post_status( $ids['2027-09'] ) === 'draft', '2027 tours must start as drafts.' );
check( TAKA_Platform_Tours::event_tour( 50 ) === $ids['2026'], 'Unassigned 2026 event not migrated.' );
check( TAKA_Platform_Tours::event_tour( 60 ) === 0, 'Future event should not be archived.' );
check( TAKA_Platform_Tours::event_tour( 20 ) === 2, 'Existing assignment overwritten.' );
$count = count( $GLOBALS['tour_posts'] );
update_post_meta( $ids['2027-06'], '_taka_tour_settings', array( 'theme' => array( 'en' => 'Edited' ) ) );
check( TAKA_Platform_Tours_Admin::provision_initial_tours() === $ids, 'Repeated setup changed IDs.' );
check( count( $GLOBALS['tour_posts'] ) === $count, 'Repeated setup created duplicates.' );
check( TAKA_Platform_Tours::settings( $ids['2027-06'] )['theme']['en'] === 'Edited', 'Repeated setup overwrote editorial changes.' );
echo "Tour migration regression checks passed.\n";

// Online seminars share the collection pipeline, but never mix in tour events.
function __( $text, $domain = '' ) { return $text; }
$online_id = TAKA_Platform_Tours_Admin::provision_online_seminars();
check( 'online' === TAKA_Platform_Tours::category( $online_id ), 'Online category missing.' );
check( 'publish' === get_post_status( $online_id ), 'Requested online collection not published.' );
check( 'tour' === TAKA_Platform_Tours::category( 2 ), 'Legacy tours changed category.' );
$GLOBALS['tour_meta'][70]['_taka_public_tour'] = $online_id;
unset( $_GET['taka_tours'] );
$directory = TAKA_Platform_Tours::render( array(), static function () { return ''; } );
check( strpos( $directory, 'Online seminars' ) !== false && strpos( $directory, 'June 2027' ) !== false, 'Online collection missing from the shared overview.' );
check( substr_count( $directory, 'taka-tour-directory__media' ) === substr_count( $directory, '<article' ), 'Image-free cards need the same reserved media slot.' );
TAKA_Platform_Tours::render( array( 'tour' => $online_id ), static function () use ( $events ) {
	$online_events = TAKA_Platform_Tours::filter_events( array_merge( $events, array( array( 'wp_post_id' => 70 ) ) ) );
	check( array_column( $online_events, 'wp_post_id' ) === array( 70 ), 'Online collection contains unrelated events.' );
	check( TAKA_Platform_Tours::hero( array() )['location_display_mode'] === 'hidden', 'Online collection must not show a geographic route.' );
	return '';
} );
$count = count( $GLOBALS['tour_posts'] );
$settings = TAKA_Platform_Tours::settings( $online_id );
$settings['title']['en'] = 'Edited online series';
$settings['state'] = 'archive';
update_post_meta( $online_id, '_taka_tour_settings', $settings );
$GLOBALS['tour_posts'][$online_id]->post_status = 'draft';
check( TAKA_Platform_Tours_Admin::provision_online_seminars() === $online_id && count( $GLOBALS['tour_posts'] ) === $count, 'Online setup duplicated the collection.' );
check( 'draft' === get_post_status( $online_id ) && 'Edited online series' === TAKA_Platform_Tours::settings( $online_id )['title']['en'], 'Online setup overwrote saved content or publication state.' );
check( TAKA_Platform_Tours::event_archived( 70 ), 'Online seminars must obey archive booking policy.' );
// Clearing the setup pointer must still find a manually created/previous collection.
update_option( 'taka_platform_online_seminars', 0 );
check( TAKA_Platform_Tours_Admin::provision_online_seminars() === $online_id, 'Existing online collection not reused.' );
echo "Online seminar regression checks passed.\n";

// Layout defaults affect new collection designs only, including existing portrait uploads.
$_GET = array( 'taka_tour_id' => 2 );
TAKA_Platform_Tours::render( array(), static function () {
	check( 'split' === TAKA_Platform_Tours::hero( array() )['layout'], 'New collection hero should show a complete photo.' );
	return '';
} );
$GLOBALS['tour_meta'][2]['_taka_tour_settings']['hero_layout'] = 'background';
TAKA_Platform_Tours::render( array(), static function () {
	check( 'background' === TAKA_Platform_Tours::hero( array() )['layout'], 'Explicit background layout was lost.' );
	return '';
} );
$_GET = array( 'taka_tour_id' => 1 );
TAKA_Platform_Tours::render( array(), static function () {
	check( array( 'title' => 'Original' ) === TAKA_Platform_Tours::hero( array( 'title' => 'Original' ) ), 'Legacy hero changed.' );
	return '';
} );
$_GET = array();
$GLOBALS['tour_options'][ TAKA_Platform_Overview::OPTION ] = array( 'heading' => array( 'en' => '<b>Custom overview</b>' ), 'archive_heading' => array( 'en' => 'Past events' ) );
check( strpos( TAKA_Platform_Overview::header( false ), '&lt;b&gt;Custom overview&lt;/b&gt;' ) !== false, 'Overview heading is not escaped.' );
check( strpos( TAKA_Platform_Overview::header( true ), 'Past events' ) !== false, 'Archive heading missing.' );
unset( $GLOBALS['tour_options'][ TAKA_Platform_Overview::OPTION ] );
echo "Overview and hero compatibility checks passed.\n";

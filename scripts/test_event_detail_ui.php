<?php
/** Real drawer-data and template regressions for the online-event screenshots. */
require __DIR__ . '/test_booking_visibility.php';
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_attr( $value ); }
function esc_attr__( $value ) { return esc_attr( $value ); }
function sanitize_html_class( $value ) { return sanitize_key( $value ); }
function wpautop( $value ) { return $value; }
function wp_kses_post( $value ) { $GLOBALS['kses_calls'] = ( $GLOBALS['kses_calls'] ?? 0 ) + 1; return $value; }
function verify_ui( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, $message . PHP_EOL ); exit( 1 ); } }
$organizers = array(
 array( 'organizer_id' => 'a', 'drawer_key' => 'organizer_a', 'organizer' => array( 'name' => 'First organizer' ) ),
 array( 'organizer_id' => 'b', 'drawer_key' => 'organizer_b', 'organizer' => array( 'name' => 'Second organizer' ) ),
);
$event = array( 'title' => 'Seminar', 'description' => '<p>A <strong>formatted</strong> description.</p>', 'ticket_mode' => 'free', 'program_summary' => '', 'ticket_organizers' => $organizers );
$build = ( new ReflectionClass( 'TAKA_Platform_Data' ) )->getMethod( 'build_info_drawers' );
$drawers = $build->invoke( null, $event, array( 'name' => 'First organizer' ), null, 'en' );
verify_ui( count( array_filter( $drawers, static function( $d ) { return 'organizer' === $d['type']; } ) ) === 2, 'Legacy organizer must not become a third drawer.' );
$legacy_event = $event; unset( $legacy_event['ticket_organizers'] );
$legacy = $build->invoke( null, $legacy_event, array( 'name' => 'Legacy organizer' ), null, 'en' );
verify_ui( isset( $legacy['organizer'] ), 'Legacy organizer fallback must remain available.' );
$rows = $drawers['event']['rows']; $drawer_id = 'test-event'; $title = 'Details';
ob_start(); include dirname( __DIR__ ) . '/templates/partials/info-drawer.php'; $html = ob_get_clean();
verify_ui( strpos( $html, '<strong>formatted</strong>' ) !== false, 'Rich description must render as HTML.' );
verify_ui( strpos( $html, '&lt;p&gt;' ) === false, 'Description markup must not appear as source text.' );
verify_ui( ! empty( $GLOBALS['kses_calls'] ), 'Rich descriptions must pass through the WordPress HTML sanitizer.' );
$rows = array( array( 'label' => 'Title', 'value' => '<strong>Plain title</strong>' ) );
ob_start(); include dirname( __DIR__ ) . '/templates/partials/info-drawer.php'; $plain = ob_get_clean();
verify_ui( strpos( $plain, '&lt;strong&gt;Plain title&lt;/strong&gt;' ) !== false, 'Unmarked fields must remain escaped.' );
$seminars = array( array( 'title' => 'Only seminar' ) ); $skip_single_event_overview = true;
ob_start(); include dirname( __DIR__ ) . '/templates/tour-schedule.php'; $overview = ob_get_clean();
verify_ui( '' === $overview, 'Single-event homepage must not render a duplicate narrow overview card.' );
echo 'Event detail UI regressions passed.' . PHP_EOL;

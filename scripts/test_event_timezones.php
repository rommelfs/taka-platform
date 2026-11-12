<?php
/** Regression coverage for wall times, daylight saving, rollover and calendar export. */
define( 'ABSPATH', __DIR__ );
function wp_timezone_string() { return 'Europe/Luxembourg'; }
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
class TAKA_Platform_Data {
	public static function timezone_for_country( $country ) { return array( 'DE' => 'Europe/Berlin', 'JP' => 'Asia/Tokyo' )[ $country ] ?? ''; }
}
require_once dirname( __DIR__ ) . '/includes/Support/class-event-time.php';
require_once dirname( __DIR__ ) . '/includes/Integrations/EventsManager/interface-event-export-provider.php';
require_once dirname( __DIR__ ) . '/includes/Integrations/EventsManager/class-ics-provider.php';
function check( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, $message . PHP_EOL ); exit( 1 ); }
}
$event = array( 'timezone' => 'Asia/Tokyo', 'country_code' => 'DE' );
foreach ( array( '2026-11-12' => '13:00 +01:00', '2026-07-12' => '14:00 +02:00' ) as $date => $expected ) {
	$instant = TAKA_Platform_Event_Time::date_time( $date, '21:00', 'Asia/Tokyo' );
	check( $instant->setTimezone( new DateTimeZone( 'Europe/Berlin' ) )->format( 'H:i P' ) === $expected, 'Fukuoka/Berlin conversion must account for European DST.' );
}
check( TAKA_Platform_Event_Time::timezone( $event ) === 'Asia/Tokyo', 'An explicit zone must override event country.' );
check( TAKA_Platform_Event_Time::timezone( array( 'country_code' => 'DE' ) ) === 'Europe/Berlin', 'Legacy country fallback lost.' );
check( TAKA_Platform_Event_Time::timezone( array( 'timezone' => 'invalid' ) ) === 'Europe/Luxembourg', 'Website timezone fallback lost.' );
check( TAKA_Platform_Event_Time::date_time( '2026-03-29', '02:30', 'Europe/Berlin' ) === null, 'Nonexistent DST-gap time silently normalized.' );
check( TAKA_Platform_Event_Time::date_time( '2026-02-30', '10:00', 'UTC' ) === null, 'Invalid date silently normalized.' );
check( TAKA_Platform_Event_Time::date_time( '', '21:00', 'Asia/Tokyo' ) === null, 'Undated sessions must not gain an invented date.' );
check( TAKA_Platform_Event_Time::date_time( '2026-11-12', '25:00', 'UTC' ) === null, 'Invalid time silently normalized.' );
list( $start, $end ) = TAKA_Platform_Event_Time::range( $event, array( 'date' => '2026-11-12', 'time_start' => '23:00', 'time_end' => '01:00' ) );
check( $end->format( 'Y-m-d H:i' ) === '2026-11-13 01:00', 'Overnight end date incorrect.' );
check( $start->setTimezone( new DateTimeZone( 'America/New_York' ) )->format( 'Y-m-d H:i' ) === '2026-11-12 09:00', 'Cross-continent conversion incorrect.' );
$early = TAKA_Platform_Event_Time::date_time( '2026-11-12', '01:00', 'Asia/Tokyo' );
check( $early->setTimezone( new DateTimeZone( 'America/New_York' ) )->format( 'Y-m-d H:i' ) === '2026-11-11 11:00', 'Previous-day conversion incorrect.' );
$export_event = array_merge( $event, array( 'external_id' => 'online', 'start_date' => '2026-11-12', 'start_time' => '21:00', 'end_date' => '2026-11-12', 'end_time' => '22:30', 'title' => 'Seminar' ) );
date_default_timezone_set( 'Pacific/Honolulu' );
$ics = ( new TAKA_Platform_ICS_Provider() )->export( array( $export_event ) );
check( strpos( $ics, 'DTSTART:20261112T120000Z' ) !== false, 'ICS must export the actual instant independent of server timezone.' );
check( strpos( $ics, 'DTEND:20261112T133000Z' ) !== false, 'ICS end time incorrect.' );
check( strpos( TAKA_Platform_Event_Time::range_label( $event, array( 'date' => '2026-11-12', 'time_start' => '21:00' ) ), 'UTC+09:00' ) !== false, 'Original time needs an explicit zone/offset.' );
echo 'Event timezone regressions passed.' . PHP_EOL;

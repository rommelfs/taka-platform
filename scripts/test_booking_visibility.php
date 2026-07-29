<?php
/**
 * Regression tests for per-Event booking-information visibility.
 */

define( 'ABSPATH', __DIR__ );

function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( (string) $value ); }
function sanitize_email( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_EMAIL ); }
function get_option() { return array(); }
function taka_tour_current_language() { return 'en'; }
function taka_tour_translate( $key, $fallback ) { return $fallback; }

class TAKA_Platform_I18n {
	public static function instance() { return new self(); }
	public function get_all_languages() { return array( 'de', 'en', 'fr', 'nl', 'lb', 'fi', 'ja' ); }
}

require_once dirname( __DIR__ ) . '/includes/ImportExport/class-translation-packages.php';
require_once dirname( __DIR__ ) . '/includes/Data/class-repository.php';

$reflection = new ReflectionClass( 'TAKA_Platform_Data' );
$normalize = $reflection->getMethod( 'normalize_booking_information' );
if ( PHP_VERSION_ID < 80100 ) { $normalize->setAccessible( true ); }
$defaults = $normalize->invoke( null, array(), true );
foreach ( array( 'enabled', 'show_group_booking', 'show_multi_event_discount', 'show_payment_methods', 'show_cancellation_policy' ) as $field ) {
	if ( '1' !== (string) ( $defaults[ $field ] ?? '' ) ) {
		fwrite( STDERR, 'Legacy booking visibility did not default to enabled.' . PHP_EOL );
		exit( 1 );
	}
}

$resolve = $reflection->getMethod( 'booking_information_for_event' );
if ( PHP_VERSION_ID < 80100 ) { $resolve->setAccessible( true ); }
$booking = $resolve->invoke(
	null,
	array(
		'booking_information' => array(
			'override' => '0',
			'enabled' => '1',
			'show_group_booking' => '0',
			'show_multi_event_discount' => '1',
			'show_payment_methods' => '0',
			'show_cancellation_policy' => '1',
		),
	),
	array(),
	'en',
	array()
);
$visible = array_column( $booking['sections'], 'key' );
if ( in_array( 'group_booking', $visible, true ) || in_array( 'payment_methods', $visible, true ) ) {
	fwrite( STDERR, 'Disabled booking topics remained visible.' . PHP_EOL );
	exit( 1 );
}
if ( ! in_array( 'multi_event_discount', $visible, true ) || ! in_array( 'cancellation_policy', $visible, true ) ) {
	fwrite( STDERR, 'Enabled booking topics were removed.' . PHP_EOL );
	exit( 1 );
}

$event_a = array( 'ticket_mode' => 'pay_at_door', 'ticket_door_price' => '40', 'currency' => 'EUR', 'ticket_door_note' => 'Week-end Pass' );
$event_b = array( 'ticket_mode' => 'pay_at_door', 'ticket_door_price' => '30', 'currency' => 'EUR', 'ticket_door_note' => 'Only Saturday' );
$event_empty = array( 'ticket_mode' => 'pay_at_door', 'ticket_door_price' => '20', 'currency' => 'EUR', 'ticket_door_note' => '' );
if ( 'Week-end Pass' !== TAKA_Platform_Data::ticket_information_card( $event_a, 'en' )['note'] ) {
	fwrite( STDERR, 'The first Event lost its own Door price additional note.' . PHP_EOL );
	exit( 1 );
}
if ( 'Only Saturday' !== TAKA_Platform_Data::ticket_information_card( $event_b, 'en' )['note'] ) {
	fwrite( STDERR, 'The second Event did not retain its separate Door price additional note.' . PHP_EOL );
	exit( 1 );
}
if ( '' !== TAKA_Platform_Data::ticket_information_card( $event_empty, 'en' )['note'] ) {
	fwrite( STDERR, 'An empty Door price additional note produced frontend content.' . PHP_EOL );
	exit( 1 );
}
$event_free = array( 'ticket_mode' => 'free', 'ticket_door_note' => 'Must not leak' );
if ( '' !== TAKA_Platform_Data::ticket_information_card( $event_free, 'en' )['note'] ) {
	fwrite( STDERR, 'A Door price additional note leaked into a non-door-price Event mode.' . PHP_EOL );
	exit( 1 );
}

$resolve_text = $reflection->getMethod( 'resolve_dynamic_text_result' );
if ( PHP_VERSION_ID < 80100 ) { $resolve_text->setAccessible( true ); }
$resolved_name = $resolve_text->invoke(
	null,
	array( 'de' => 'Kanade leitet das Seminar.', 'en' => 'Canada leads the seminar.' ),
	'en',
	'de',
	array()
);
if ( 'Kanade leads the seminar.' !== $resolved_name['value'] ) {
	fwrite( STDERR, 'An already stored website translation did not preserve the personal name Kanade.' . PHP_EOL );
	exit( 1 );
}

echo 'Booking visibility regression tests passed.' . PHP_EOL;

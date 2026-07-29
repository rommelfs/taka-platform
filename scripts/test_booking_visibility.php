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

echo 'Booking visibility regression tests passed.' . PHP_EOL;

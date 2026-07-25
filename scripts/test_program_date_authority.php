<?php
/**
 * Regression test: explicit Event program dates must survive legacy fallback.
 */

define( 'ABSPATH', __DIR__ );

function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( (string) $value ); }
function absint( $value ) { return abs( (int) $value ); }

class TAKA_Platform_I18n {
	public static function instance() { return new self(); }
	public function get_all_languages() { return array( 'de', 'en', 'fr', 'nl', 'lb', 'fi', 'ja' ); }
}

require_once dirname( __DIR__ ) . '/includes/Data/class-repository.php';

$items = array(
	array( 'id' => 'program-1', 'date' => '2026-09-27', 'time_start' => '10:00', 'title' => 'Day 2' ),
	array( 'id' => 'program-2', 'date' => '2026-09-26', 'time_start' => '14:30', 'title' => 'Day 1' ),
	array( 'id' => 'program-3', 'date' => '2026-09-28', 'time_start' => '14:00', 'title' => 'Explicit extra day' ),
);
$normalized = TAKA_Platform_Data::normalize_program_items(
	$items,
	array( 'date_start' => '2026-09-26', 'date_end' => '2026-09-27', 'source_language' => 'de' )
);

$dates_by_id = array();
foreach ( $normalized as $item ) {
	$dates_by_id[ $item['id'] ] = $item['date'];
}

$expected = array(
	'program-1' => '2026-09-27',
	'program-2' => '2026-09-26',
	'program-3' => '2026-09-28',
);
foreach ( $expected as $id => $date ) {
	if ( $date !== ( $dates_by_id[ $id ] ?? '' ) ) {
		fwrite( STDERR, 'Explicit program dates were reassigned by the legacy Event range.' . PHP_EOL );
		exit( 1 );
	}
}

echo 'Program date authority regression test passed.' . PHP_EOL;

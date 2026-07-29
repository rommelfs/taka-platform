<?php
/**
 * Regression tests for translation block boundaries and protected names.
 */

define( 'ABSPATH', __DIR__ );

function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( (string) $value ); }
function get_option() { return array(); }

class TAKA_Platform_I18n {
	public static function instance() { return new self(); }
	public function get_all_languages() { return array( 'de', 'en', 'fr', 'nl', 'lb', 'fi', 'ja' ); }
}

require_once dirname( __DIR__ ) . '/includes/ImportExport/class-translation-packages.php';

$first = TAKA_Platform_Translation_Packages::preserve_boundary_whitespace( 'Erster Satz. ', 'First sentence.' );
$second = TAKA_Platform_Translation_Packages::preserve_boundary_whitespace( 'Zweiter Satz.', ' Second sentence. ' );
if ( 'First sentence. Second sentence.' !== $first . $second ) {
	fwrite( STDERR, 'Translation block boundary whitespace was not preserved exactly.' . PHP_EOL );
	exit( 1 );
}

$protected = TAKA_Platform_Translation_Packages::protect_glossary_terms(
	'Kanade leitet das Seminar.',
	'Kanada leads the seminar.'
);
if ( 'Kanade leads the seminar.' !== $protected ) {
	fwrite( STDERR, 'The protected personal name Kanade was not restored.' . PHP_EOL );
	exit( 1 );
}

$protected_case = TAKA_Platform_Translation_Packages::protect_glossary_terms(
	'KANADE leitet das Seminar.',
	'Canada leads the seminar with KANADE.'
);
if ( 'Kanade leads the seminar with Kanade.' !== $protected_case ) {
	fwrite( STDERR, 'The personal name Kanade was not normalized independently of case or translated variant.' . PHP_EOL );
	exit( 1 );
}

$country = TAKA_Platform_Translation_Packages::protect_glossary_terms(
	'Eine Reise nach Kanada.',
	'A trip to Canada.'
);
if ( 'A trip to Canada.' !== $country ) {
	fwrite( STDERR, 'A genuine geographic reference to Canada was changed.' . PHP_EOL );
	exit( 1 );
}

echo 'Translation boundary and glossary regression tests passed.' . PHP_EOL;

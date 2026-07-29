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

$person = TAKA_Platform_Translation_Packages::protect_glossary_terms(
	'Takafumi “Taka” Nakayama leitet das Seminar.',
	'TAKAfumi "TAKA" Nakayama leads the seminar.'
);
if ( "Takafumi 'Taka' Nakayama leads the seminar." !== $person ) {
	fwrite( STDERR, 'The full personal name was not restored with canonical apostrophes and case.' . PHP_EOL );
	exit( 1 );
}

$person_plain = TAKA_Platform_Translation_Packages::protect_glossary_terms(
	'Takafumi Nakayama leitet das Seminar.',
	'Nakayama Takafumi leads the seminar.'
);
if ( "Takafumi 'Taka' Nakayama leads the seminar." !== $person_plain ) {
	fwrite( STDERR, 'A known full-name variant was not canonicalized.' . PHP_EOL );
	exit( 1 );
}

$project_and_person = TAKA_Platform_Translation_Packages::protect_glossary_terms(
	"TAKA presents Takafumi 'Taka' Nakayama.",
	"TAKA presents Takafumi 'Taka' Nakayama."
);
if ( "TAKA presents Takafumi 'Taka' Nakayama." !== $project_and_person ) {
	fwrite( STDERR, 'The TAKA project rule changed the personal nickname or Takafumi.' . PHP_EOL );
	exit( 1 );
}

$nickname_only = TAKA_Platform_Translation_Packages::protect_glossary_terms(
	'Taka teaches natural movement.',
	'Taka teaches natural movement.'
);
if ( 'Taka teaches natural movement.' !== $nickname_only ) {
	fwrite( STDERR, 'The personal nickname Taka was incorrectly uppercased as the project name.' . PHP_EOL );
	exit( 1 );
}

$mixed_project_context = TAKA_Platform_Translation_Packages::protect_glossary_terms(
	'TAKA is the platform. Taka teaches natural movement.',
	'Taka is the platform. Taka teaches natural movement.'
);
if ( 'TAKA is the platform. Taka teaches natural movement.' !== $mixed_project_context ) {
	fwrite( STDERR, 'The project spelling rule leaked into a separate personal nickname.' . PHP_EOL );
	exit( 1 );
}

$venue_name = TAKA_Platform_Translation_Packages::protect_glossary_terms(
	'kanso – Zentrum für Körper, Geist und Seele',
	'however – center for body, mind and soul'
);
if ( 'kanso – center for body, mind and soul' !== $venue_name ) {
	fwrite( STDERR, 'The venue name kanso was not restored.' . PHP_EOL );
	exit( 1 );
}

$ordinary_however = TAKA_Platform_Translation_Packages::protect_glossary_terms(
	'Der Termin änderte sich jedoch.',
	'However, the date changed.'
);
if ( 'However, the date changed.' !== $ordinary_however ) {
	fwrite( STDERR, 'An ordinary use of however was incorrectly changed to kanso.' . PHP_EOL );
	exit( 1 );
}

echo 'Translation boundary and glossary regression tests passed.' . PHP_EOL;

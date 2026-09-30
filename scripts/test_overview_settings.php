<?php
/** Input boundary checks for the independent overview configuration. */
define( 'ABSPATH', __DIR__ );
function sanitize_hex_color( $v ) { return preg_match( '/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/i', $v ) ? $v : ''; }
function absint( $v ) { return abs( (int) $v ); }
function esc_url_raw( $v ) { return preg_match( '~^https?://~', $v ) ? $v : ''; }
function sanitize_textarea_field( $v ) { return strip_tags( $v ); }
class TAKA_Platform_I18n {
	static function instance() { return new self(); }
	function get_all_languages() { return array( 'de', 'en' ); }
}
require dirname( __DIR__ ) . '/includes/Tours/class-overview.php';
$s = TAKA_Platform_Overview::sanitize( array( 'background' => 'red;display:none', 'columns' => '99', 'source_language' => 'unknown', 'heading' => array( 'de' => '<script>unsafe</script>Hallo', 'en' => array() ), 'image_url' => 'javascript:alert(1)', 'blocks' => array( 14 => '20', 15 => '', 16 => '0' ) ) );
if ( '#ffffff' !== $s['background'] || '2' !== $s['columns'] || 'en' !== $s['source_language'] || '' !== $s['image_url'] || '' !== $s['heading']['en'] || false !== strpos( $s['heading']['de'], '<' ) || array( 16, 14 ) !== array_keys( $s['blocks'] ) ) { throw new RuntimeException( 'Overview input validation or ordering failed.' ); }
$s = TAKA_Platform_Overview::sanitize( array( 'columns' => '3', 'source_language' => 'de', 'accent' => '#abc', 'image_id' => '42' ) );
if ( '3' !== $s['columns'] || 'de' !== $s['source_language'] || '#abc' !== $s['accent'] || 42 !== $s['image_id'] ) { throw new RuntimeException( 'Valid configuration was lost.' ); }
echo "Overview settings boundary checks passed.\n";

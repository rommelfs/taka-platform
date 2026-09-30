<?php
/** Archived tours must block actual provider and native ticketing entry points. */
define( 'ABSPATH', __DIR__ );
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) ); }
function sanitize_title( $value ) { return sanitize_key( $value ); }
function taka_tour_current_language() { return 'en'; }
function taka_tour_translate( $key, $fallback, $lang = null ) { return $fallback; }
function get_option( $key, $default = false ) { return $default; }
function get_post( $id ) { return (object) array( 'ID' => $id ); }
function get_post_meta( $id, $key, $single = true ) {
	if ( '_taka_public_tour' === $key && 10 === $id ) { return 1; }
	if ( '_taka_tour_settings' === $key && 1 === $id ) { return array( 'state' => 'archive' ); }
	return '';
}
class WP_Error {
	public $code;
	public function __construct( $code, $message ) { $this->code = $code; }
}
class TAKA_Platform_I18n {
	public static function instance() { return new self(); }
	public function get_all_languages() { return array( 'en', 'de' ); }
}
require_once dirname( __DIR__ ) . '/includes/Data/class-repository.php';
require_once dirname( __DIR__ ) . '/includes/Tours/class-tours.php';
require_once dirname( __DIR__ ) . '/includes/Ticketing/class-product.php';
require_once dirname( __DIR__ ) . '/includes/Ticketing/class-ticketing-module.php';
require_once dirname( __DIR__ ) . '/includes/Ticketing/class-order-service.php';
function check( $value, $message ) { if ( ! $value ) { throw new RuntimeException( $message ); } }
foreach ( array( 'online_shop', 'external', 'native_taka_ticketing' ) as $mode ) {
	$event = array( 'wp_post_id' => 10, 'ticket_mode' => $mode, 'ticket_shop_url' => 'https://example.org/shop', 'ticket_provider' => 'pretix' );
	check( TAKA_Platform_Data::ticket_mode_for_event( $event ) === 'none', 'Archived provider mode remained bookable.' );
	check( TAKA_Platform_Data::pretix_event_url( $event ) === '', 'Archived Pretix widget exposed.' );
	check( TAKA_Platform_Data::ticket_direct_url( $event ) === '', 'Archived external shop link exposed.' );
	check( TAKA_Platform_Data::ticket_information_card( $event )['mode'] === 'archive', 'Archive notice missing.' );
	check( ! TAKA_Ticketing_Module::event_uses_native_ticketing( $event ), 'Archived native widget exposed.' );
}
check( ! TAKA_Ticketing_Module::event_uses_native_ticketing( 10 ), 'Direct event ID bypassed archive gate.' );
$result = TAKA_Ticketing_Order_Service::create_order_from_post( array( 'event_id' => 10, 'ticket_type_id' => 'adult', 'language' => 'en' ) );
check( $result instanceof WP_Error, 'Direct order request was not rejected.' );
check( ( new ReflectionMethod( 'TAKA_Platform_Data', 'resolve_attachment_url' ) )->isPublic(), 'Shared media resolver must be callable by Tour rendering.' );
$form = new ReflectionMethod( 'TAKA_Ticketing_Module', 'render_standalone_product_form' );
if ( PHP_VERSION_ID < 80100 ) { $form->setAccessible( true ); }
check( strpos( $form->invoke( null, array( 'related_event_id' => 10 ) ), 'Booking is no longer available' ) !== false, 'Archived related product exposed a checkout form.' );
check( TAKA_Platform_Data::normalize_hero_location_display_mode( 'hidden' ) === 'hidden', 'Online hero hidden mode must survive template normalization.' );
echo "Tour ticket policy regression checks passed.\n";

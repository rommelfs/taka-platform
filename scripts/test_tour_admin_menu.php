<?php
/** Reproduce plugin bootstrap order and verify admin page registration. */
define( 'ABSPATH', __DIR__ );
define( 'TAKA_PLATFORM_CPT_EVENT', 'taka_event' );
$GLOBALS['test_actions'] = array();
$GLOBALS['test_menu_parents'] = array();
$GLOBALS['test_submenus'] = array();
$GLOBALS['test_manage_options'] = true;
function __( $text, $domain = '' ) { return $text; }
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['test_actions'][ $hook ][ $priority ][] = $callback; }
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function current_user_can( $cap ) { return 'manage_options' === $cap ? $GLOBALS['test_manage_options'] : true; }
function add_menu_page( $title, $label, $cap, $slug, $callback, $icon = '', $position = null ) { $GLOBALS['test_menu_parents'][ $slug ] = true; }
function add_submenu_page( $parent, $title, $label, $cap, $slug, $callback ) {
	if ( ! current_user_can( $cap ) ) { return false; }
	if ( 'taka-tour-setup' === $slug && empty( $GLOBALS['test_menu_parents'][ $parent ] ) ) {
		throw new RuntimeException( 'Tour setup registered before its parent: WordPress derives the wrong admin page hook.' );
	}
	$GLOBALS['test_submenus'][ $slug ][] = array( 'parent' => $parent, 'capability' => $cap, 'callback' => $callback );
}
require_once dirname( __DIR__ ) . '/includes/Tours/class-overview.php';
require_once dirname( __DIR__ ) . '/includes/Tours/class-tours-admin.php';
require_once dirname( __DIR__ ) . '/includes/Admin/class-admin.php';
// Same initialization order as plugins_loaded in the plugin bootstrap.
TAKA_Platform_Tours_Admin::init();
TAKA_Platform_Admin::init();
$actions = $GLOBALS['test_actions']['admin_menu'];
ksort( $actions );
foreach ( $actions as $callbacks ) {
	foreach ( $callbacks as $callback ) { call_user_func( $callback ); }
}
$pages = $GLOBALS['test_submenus']['taka-tour-setup'] ?? array();
if ( count( $pages ) !== 1 || 'taka-platform' !== $pages[0]['parent'] || 'manage_options' !== $pages[0]['capability'] || ! is_callable( $pages[0]['callback'] ) ) {
	throw new RuntimeException( 'Tour setup must register once under TAKA with administrator permissions and a valid callback.' );
}
// Registration must not grant access to a non-administrator.
$GLOBALS['test_manage_options'] = false;
$GLOBALS['test_submenus'] = array();
TAKA_Platform_Tours_Admin::menu();
if ( isset( $GLOBALS['test_submenus']['taka-tour-setup'] ) ) { throw new RuntimeException( 'Setup permissions were weakened.' ); }
echo "Tour admin menu regression checks passed.\n";

function esc_html__( $text, $domain = '' ) { return $text; }
function wp_die( $text ) { throw new LogicException( $text ); }
function check_admin_referer( $action ) { throw new RuntimeException( 'nonce:' . $action ); }
try {
	TAKA_Platform_Tours_Admin::setup_online_seminars();
	throw new RuntimeException( 'Non-administrator reached online setup.' );
} catch ( LogicException $error ) {
	if ( 'Access denied.' !== $error->getMessage() ) { throw $error; }
}
$GLOBALS['test_manage_options'] = true;
try {
	TAKA_Platform_Tours_Admin::setup_online_seminars();
	throw new RuntimeException( 'Online setup omitted nonce validation.' );
} catch ( RuntimeException $error ) {
	if ( 'nonce:taka_setup_online_seminars' !== $error->getMessage() ) { throw $error; }
}
echo "Online setup access checks passed.\n";

$GLOBALS['test_manage_options'] = false;
try { TAKA_Platform_Overview::save(); throw new RuntimeException( 'Overview save permitted non-admin.' ); }
catch ( LogicException $error ) { if ( 'Access denied.' !== $error->getMessage() ) { throw $error; } }
$GLOBALS['test_manage_options'] = true;
try { TAKA_Platform_Overview::save(); throw new LogicException( 'Overview save omitted nonce.' ); }
catch ( RuntimeException $error ) { if ( 'nonce:taka_overview' !== $error->getMessage() ) { throw $error; } }
echo "Overview access checks passed.\n";

<?php
/**
 * Plugin Name: TAKA Platform
 * Description: Ticketing, Attendance, Knowledge & Administration for reusable event and seminar tours.
 * Version: 1.4.9
 * Author: TAKA Platform
 * Text Domain: taka-platform
 */

defined( 'ABSPATH' ) || exit;

define( 'TAKA_PLATFORM_VERSION', '1.4.9' );
define( 'TAKA_PLATFORM_PLUGIN_FILE', __FILE__ );
define( 'TAKA_PLATFORM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAKA_PLATFORM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TAKA_PLATFORM_CPT_EVENT', 'taka_event' );
define( 'TAKA_PLATFORM_CPT_ORGANIZER', 'taka_organizer' );
define( 'TAKA_PLATFORM_CPT_VENUE', 'taka_venue' );

// Backward-compatible constants for existing TAKA Tour installations and templates.
define( 'TAKA_TOUR_VERSION', TAKA_PLATFORM_VERSION );
define( 'TAKA_TOUR_PLUGIN_FILE', TAKA_PLATFORM_PLUGIN_FILE );
define( 'TAKA_TOUR_PLUGIN_DIR', TAKA_PLATFORM_PLUGIN_DIR );
define( 'TAKA_TOUR_PLUGIN_URL', TAKA_PLATFORM_PLUGIN_URL );

require_once TAKA_PLATFORM_PLUGIN_DIR . 'includes/Support/helpers.php';
require_once TAKA_PLATFORM_PLUGIN_DIR . 'includes/I18n/class-i18n.php';
require_once TAKA_PLATFORM_PLUGIN_DIR . 'includes/I18n/interface-translation-service.php';
require_once TAKA_PLATFORM_PLUGIN_DIR . 'includes/I18n/class-manual-translation-service.php';
require_once TAKA_PLATFORM_PLUGIN_DIR . 'includes/Tickets/interface-ticket-provider.php';
require_once TAKA_PLATFORM_PLUGIN_DIR . 'includes/Tickets/class-pretix-provider.php';
require_once TAKA_PLATFORM_PLUGIN_DIR . 'includes/Tickets/class-ticket-provider-registry.php';
require_once TAKA_PLATFORM_PLUGIN_DIR . 'includes/Data/class-repository.php';
require_once TAKA_PLATFORM_PLUGIN_DIR . 'includes/Frontend/class-organizer-dashboard.php';
require_once TAKA_PLATFORM_PLUGIN_DIR . 'includes/Frontend/class-renderer.php';
require_once TAKA_PLATFORM_PLUGIN_DIR . 'includes/Core/class-plugin.php';
require_once TAKA_PLATFORM_PLUGIN_DIR . 'includes/Admin/class-admin.php';

register_activation_hook( TAKA_PLATFORM_PLUGIN_FILE, array( 'TAKA_Platform_Admin', 'ensure_capabilities' ) );

// Legacy class aliases preserve old integrations while new code uses TAKA_Platform_* names.
if ( ! class_exists( 'Taka_Tour_Data', false ) ) {
	class_alias( 'TAKA_Platform_Data', 'Taka_Tour_Data' );
}
if ( ! class_exists( 'Taka_Tour_Renderer', false ) ) {
	class_alias( 'TAKA_Platform_Renderer', 'Taka_Tour_Renderer' );
}
if ( ! class_exists( 'Taka_Tour_I18n', false ) ) {
	class_alias( 'TAKA_Platform_I18n', 'Taka_Tour_I18n' );
}
if ( ! class_exists( 'Taka_Tour_Plugin', false ) ) {
	class_alias( 'TAKA_Platform_Plugin', 'Taka_Tour_Plugin' );
}
if ( ! class_exists( 'Taka_Tour_Admin', false ) ) {
	class_alias( 'TAKA_Platform_Admin', 'Taka_Tour_Admin' );
}
if ( ! class_exists( 'Taka_Tour_Ticket_Providers', false ) ) {
	class_alias( 'TAKA_Platform_Ticket_Provider_Registry', 'Taka_Tour_Ticket_Providers' );
}

add_action(
	'plugins_loaded',
	static function () {
		add_action( 'init', array( 'TAKA_Platform_Admin', 'register_post_types' ) );
		TAKA_Platform_Plugin::instance();
		if ( is_admin() ) {
			TAKA_Platform_Admin::init();
		}
	}
);

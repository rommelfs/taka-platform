<?php
/**
 * Plugin Name: TAKA Tour Website Builder
 * Description: Modular website sections for the TAKA European Tour 2026.
 * Version: 1.0.2
 * Author: TAKA European Tour
 * Text Domain: taka-tour
 */

defined( 'ABSPATH' ) || exit;

define( 'TAKA_TOUR_VERSION', '1.0.2' );
define( 'TAKA_TOUR_PLUGIN_FILE', __FILE__ );
define( 'TAKA_TOUR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAKA_TOUR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TAKA_TOUR_PLUGIN_DIR . 'includes/helpers.php';
require_once TAKA_TOUR_PLUGIN_DIR . 'includes/class-taka-tour-i18n.php';
require_once TAKA_TOUR_PLUGIN_DIR . 'includes/class-taka-tour-ticket-providers.php';
require_once TAKA_TOUR_PLUGIN_DIR . 'includes/class-taka-tour-data.php';
require_once TAKA_TOUR_PLUGIN_DIR . 'includes/class-taka-tour-renderer.php';
require_once TAKA_TOUR_PLUGIN_DIR . 'includes/class-taka-tour-plugin.php';
require_once TAKA_TOUR_PLUGIN_DIR . 'includes/class-taka-tour-admin.php';

add_action(
	'plugins_loaded',
	static function () {
		add_action( 'init', array( 'Taka_Tour_Admin', 'register_post_types' ) );
		Taka_Tour_Plugin::instance();
		if ( is_admin() ) {
			Taka_Tour_Admin::init();
		}
	}
);

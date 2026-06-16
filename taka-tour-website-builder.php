<?php
/**
 * Plugin Name: TAKA Tour Website Builder
 * Description: Modular website sections for the TAKA European Tour 2026.
 * Version: 0.7.4
 * Author: TAKA European Tour
 * Text Domain: taka-tour
 */

defined( 'ABSPATH' ) || exit;

define( 'TAKA_TOUR_VERSION', '0.7.4' );
define( 'TAKA_TOUR_PLUGIN_FILE', __FILE__ );
define( 'TAKA_TOUR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAKA_TOUR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TAKA_TOUR_PLUGIN_DIR . 'includes/helpers.php';
require_once TAKA_TOUR_PLUGIN_DIR . 'includes/class-taka-tour-i18n.php';
require_once TAKA_TOUR_PLUGIN_DIR . 'includes/class-taka-tour-data.php';
require_once TAKA_TOUR_PLUGIN_DIR . 'includes/class-taka-tour-renderer.php';
require_once TAKA_TOUR_PLUGIN_DIR . 'includes/class-taka-tour-plugin.php';

add_action(
	'plugins_loaded',
	static function () {
		Taka_Tour_Plugin::instance();
	}
);

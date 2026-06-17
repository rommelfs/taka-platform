<?php
/** Legacy compatibility shim for TAKA Platform plugin. */
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'TAKA_Platform_Plugin' ) ) { require_once TAKA_PLATFORM_PLUGIN_DIR . 'includes/Core/class-plugin.php'; }
if ( ! class_exists( 'Taka_Tour_Plugin' ) ) { class_alias( 'TAKA_Platform_Plugin', 'Taka_Tour_Plugin' ); }

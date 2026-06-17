<?php
/** Legacy compatibility shim for TAKA Platform admin. */
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'TAKA_Platform_Admin' ) ) { require_once TAKA_PLATFORM_PLUGIN_DIR . 'includes/Admin/class-admin.php'; }
if ( ! class_exists( 'Taka_Tour_Admin' ) ) { class_alias( 'TAKA_Platform_Admin', 'Taka_Tour_Admin' ); }

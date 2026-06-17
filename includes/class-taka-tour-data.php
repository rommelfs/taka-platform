<?php
/** Legacy compatibility shim for TAKA Platform data. */
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'TAKA_Platform_Data' ) ) { require_once TAKA_PLATFORM_PLUGIN_DIR . 'includes/Data/class-repository.php'; }
if ( ! class_exists( 'Taka_Tour_Data' ) ) { class_alias( 'TAKA_Platform_Data', 'Taka_Tour_Data' ); }

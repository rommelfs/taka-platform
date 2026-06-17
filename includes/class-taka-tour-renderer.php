<?php
/** Legacy compatibility shim for TAKA Platform renderer. */
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'TAKA_Platform_Renderer' ) ) { require_once TAKA_PLATFORM_PLUGIN_DIR . 'includes/Frontend/class-renderer.php'; }
if ( ! class_exists( 'Taka_Tour_Renderer' ) ) { class_alias( 'TAKA_Platform_Renderer', 'Taka_Tour_Renderer' ); }

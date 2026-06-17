<?php
/** Legacy compatibility shim for TAKA Platform i18n. */
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'TAKA_Platform_I18n' ) ) { require_once TAKA_PLATFORM_PLUGIN_DIR . 'includes/I18n/class-i18n.php'; }
if ( ! class_exists( 'Taka_Tour_I18n' ) ) { class_alias( 'TAKA_Platform_I18n', 'Taka_Tour_I18n' ); }

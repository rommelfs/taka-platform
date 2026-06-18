<?php
/** Legacy compatibility shim for TAKA Platform tickets. */
defined( 'ABSPATH' ) || exit;
if ( ! interface_exists( 'TAKA_Platform_Ticket_Provider_Interface' ) ) { require_once TAKA_PLATFORM_PLUGIN_DIR . 'includes/Tickets/interface-ticket-provider.php'; }
if ( ! class_exists( 'TAKA_Platform_Pretix_Provider' ) ) { require_once TAKA_PLATFORM_PLUGIN_DIR . 'includes/Tickets/class-pretix-provider.php'; }
if ( ! class_exists( 'TAKA_Platform_Ticket_Provider_Registry' ) ) { require_once TAKA_PLATFORM_PLUGIN_DIR . 'includes/Tickets/class-ticket-provider-registry.php'; }
if ( ! class_exists( 'Taka_Tour_Ticket_Providers' ) ) { class_alias( 'TAKA_Platform_Ticket_Provider_Registry', 'Taka_Tour_Ticket_Providers' ); }

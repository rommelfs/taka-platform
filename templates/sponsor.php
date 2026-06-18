<?php
/**
 * Sponsor section shortcode template.
 */

defined( 'ABSPATH' ) || exit;

$sections = TAKA_Platform_Data::get_content_sections();
if ( ! empty( $sections['sponsor'] ) ) {
	echo taka_tour_render_template( 'partials/content-section.php', array( 'section' => $sections['sponsor'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

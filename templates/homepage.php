<?php
/**
 * Complete homepage template.
 */

defined( 'ABSPATH' ) || exit;
$sections = TAKA_Platform_Data::get_homepage_sections();
$has_ticket_details = (bool) array_filter( $sections, static function ( $section ) { return 'tickets.php' === ( $section['template'] ?? '' ) && ! empty( $section['visible'] ); } );
?>
<div class="taka-tour-page">
	<?php echo taka_tour_render_site_header(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php foreach ( $sections as $section ) : ?>
		<?php echo TAKA_Platform_Data::render_homepage_section( $section, array( 'seminars' => $seminars, 'skip_single_event_overview' => $has_ticket_details ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endforeach; ?>
</div>

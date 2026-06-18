<?php
/**
 * Complete homepage template.
 */

defined( 'ABSPATH' ) || exit;
$sections = TAKA_Platform_Data::get_content_sections();
$ticket_settings = TAKA_Platform_Data::get_ticket_section_settings();
?>
<div class="taka-tour-page">
	<?php echo taka_tour_render_template( 'partials/hero.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php if ( ! empty( $ticket_settings['show_seminar_overview'] ) && '1' === (string) $ticket_settings['show_seminar_overview'] ) : ?>
		<?php echo taka_tour_render_template( 'tour-schedule.php', array( 'seminars' => $seminars ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endif; ?>
	<?php echo taka_tour_render_template( 'tickets.php', array( 'seminars' => $seminars ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php echo taka_tour_render_template( 'partials/image-grid.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php foreach ( $sections as $section ) : ?>
		<?php echo taka_tour_render_template( 'partials/content-section.php', array( 'section' => $section ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endforeach; ?>
	<footer class="taka-footer"><?php echo esc_html( taka_tour_translate( 'footer.text', 'TAKA European Tour 2026' ) ); ?></footer>
</div>

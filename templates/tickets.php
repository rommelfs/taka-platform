<?php
/**
 * Standalone ticket block.
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="taka-section taka-tickets" id="tickets">
	<p class="taka-kicker"><?php echo esc_html__( 'Tickets', 'taka-tour' ); ?></p>
	<h2><?php echo esc_html__( 'Konz Pretix-Shops', 'taka-tour' ); ?></h2>
	<div class="taka-tabs" data-taka-tabs>
		<div class="taka-tab-buttons">
			<?php foreach ( $seminars as $index => $seminar ) : ?>
				<button class="<?php echo 0 === $index ? 'is-active' : ''; ?>" type="button" data-tab="<?php echo esc_attr( $seminar['slug'] ?? $seminar['id'] ?? $index ); ?>"><?php echo esc_html( $seminar['title'] ?? '' ); ?></button>
			<?php endforeach; ?>
		</div>
		<?php foreach ( $seminars as $index => $seminar ) : ?>
			<?php $pretix_event_url = Taka_Tour_Data::pretix_event_url( $seminar ); ?>
			<div class="taka-tab-panel <?php echo 0 === $index ? 'is-active' : ''; ?>" data-panel="<?php echo esc_attr( $seminar['slug'] ?? $seminar['id'] ?? $index ); ?>">
				<?php echo taka_tour_render_template( 'partials/pretix-widget.php', array( 'event' => $pretix_event_url, 'label' => $seminar['title'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php endforeach; ?>
	</div>
</section>

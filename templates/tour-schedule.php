<?php defined( 'ABSPATH' ) || exit; ?>
<section class="taka-section taka-tour-overview" id="tour">
	<p class="taka-kicker"><?php echo esc_html( taka_tour_translate( 'tour.kicker', 'European Tour' ) ); ?></p>
	<h2><?php echo esc_html( taka_tour_translate( 'tour.headline', 'Seminare in Europa' ) ); ?></h2>
</section>
<section class="taka-section taka-seminars" id="seminare">
	<div class="taka-card-grid">
		<?php foreach ( $seminars as $seminar ) : ?>
			<?php echo taka_tour_render_template( 'partials/seminar-card.php', array( 'seminar' => $seminar ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endforeach; ?>
	</div>
</section>

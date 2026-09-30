<?php
defined( 'ABSPATH' ) || exit;
$online_collection = class_exists( 'TAKA_Platform_Tours' ) && TAKA_Platform_Tours::context() && 'online' === TAKA_Platform_Tours::category( TAKA_Platform_Tours::context() );
?>
<section class="taka-section taka-tour-overview" id="tour">
	<p class="taka-kicker"><?php echo esc_html( $online_collection ? taka_tour_translate( 'tours.online', 'Online seminars' ) : taka_tour_translate( 'tour.kicker', 'European Tour' ) ); ?></p>
	<h2><?php echo esc_html( $online_collection ? taka_tour_translate( 'tours.online_schedule', 'Online seminar dates' ) : taka_tour_translate( 'tour.headline', 'Seminare in Europa' ) ); ?></h2>
</section>
<section class="taka-section taka-seminars" id="seminare">
	<div class="taka-card-grid">
		<?php foreach ( $seminars as $seminar ) : ?>
			<?php echo taka_tour_render_template( 'partials/seminar-card.php', array( 'seminar' => $seminar ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endforeach; ?>
	</div>
</section>

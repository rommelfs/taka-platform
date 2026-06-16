<?php
/**
 * Complete homepage template.
 */

defined( 'ABSPATH' ) || exit;

$images = Taka_Tour_Data::images();
?>
<div class="taka-tour-page">
	<?php echo taka_tour_render_template( 'partials/hero.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php echo taka_tour_render_template( 'tour-schedule.php', array( 'seminars' => $seminars ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php echo taka_tour_render_template( 'partials/image-grid.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<section class="taka-section taka-sensei">
		<div>
			<p class="taka-kicker"><?php echo esc_html__( 'Sensei', 'taka-tour' ); ?></p>
			<h2><?php echo esc_html__( 'Takafumi Nakayama', 'taka-tour' ); ?></h2>
			<p><?php echo esc_html__( 'Präzision, Ruhe und Bewegungsqualität aus der okinawanischen Tradition.', 'taka-tour' ); ?></p>
		</div>
		<div class="taka-feature-image" style="--taka-section-image: url('<?php echo esc_url( $images['portrait_image'] ); ?>');" role="img" aria-label="<?php echo esc_attr__( 'Takafumi Nakayama Sensei', 'taka-tour' ); ?>"></div>
	</section>
	<section class="taka-section taka-training"><p class="taka-kicker"><?php echo esc_html__( 'Training', 'taka-tour' ); ?></p><h2><?php echo esc_html__( 'Karate-Do, Kobujutsu und Soft Blocking', 'taka-tour' ); ?></h2><p><?php echo esc_html__( 'Die Seminare verbinden Grundlagen, Partnerarbeit, Timing, Distanz und Körperstruktur.', 'taka-tour' ); ?></p></section>
	<section class="taka-section taka-community">
		<div>
			<p class="taka-kicker"><?php echo esc_html__( 'Community', 'taka-tour' ); ?></p>
			<h2><?php echo esc_html__( 'Gemeinsam trainieren', 'taka-tour' ); ?></h2>
			<p><?php echo esc_html__( 'Ein europäisches Treffen für ernsthaftes Training und respektvollen Austausch.', 'taka-tour' ); ?></p>
		</div>
		<div class="taka-feature-image" style="--taka-section-image: url('<?php echo esc_url( $images['group_image'] ); ?>');" role="img" aria-label="<?php echo esc_attr__( 'TAKA Tour Community Gruppenfoto', 'taka-tour' ); ?>"></div>
	</section>
	<section class="taka-section taka-host taka-host-section">
		<div class="taka-centered-section-inner">
			<p class="taka-kicker"><?php echo esc_html__( 'Gastgeber', 'taka-tour' ); ?></p>
			<h2><?php echo esc_html__( '5 Jahre Kleiner Wald Dojo', 'taka-tour' ); ?></h2>
			<div class="taka-logo-card"><img src="https://takatour.eu/wp-content/uploads/sites/7/2026/06/Logo-Kleiner-Wald.svg" alt="<?php echo esc_attr__( 'Kleiner Wald Dojo', 'taka-tour' ); ?>"></div>
		</div>
	</section>
	<?php echo taka_tour_render_template( 'sponsor.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<section class="taka-section taka-place"><p class="taka-kicker"><?php echo esc_html__( 'Ort Konz', 'taka-tour' ); ?></p><h2><?php echo esc_html__( 'Praktische Infos', 'taka-tour' ); ?></h2><p><?php echo esc_html__( 'Details zu Anreise, Zeiten und lokalen Informationen folgen in den Seminarbeschreibungen.', 'taka-tour' ); ?></p></section>
	<footer class="taka-footer">TAKA European Tour 2026</footer>
</div>

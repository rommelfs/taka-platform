<?php defined( 'ABSPATH' ) || exit; ?>
<section class="taka-section taka-tickets" id="tickets">
	<p class="taka-kicker"><?php echo esc_html__( 'Tickets', 'taka-tour' ); ?></p>
	<h2><?php echo esc_html__( 'Konz Pretix-Shops', 'taka-tour' ); ?></h2>
	<div class="taka-tabs" data-taka-tabs>
		<div class="taka-tab-buttons"><button class="is-active" type="button" data-tab="adults"><?php echo esc_html__( 'Erwachsene', 'taka-tour' ); ?></button><button type="button" data-tab="kids"><?php echo esc_html__( 'Kinder', 'taka-tour' ); ?></button></div>
		<div class="taka-tab-panel is-active" data-panel="adults"><?php echo taka_tour_render_template( 'partials/pretix-widget.php', array( 'url' => 'https://pretix.eu/kleinerwald/2026takakonz/', 'label' => __( 'Konz Erwachsene', 'taka-tour' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div class="taka-tab-panel" data-panel="kids"><?php echo taka_tour_render_template( 'partials/pretix-widget.php', array( 'url' => 'https://pretix.eu/kleinerwald/2026takakids/', 'label' => __( 'Kindertraining', 'taka-tour' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	</div>
</section>

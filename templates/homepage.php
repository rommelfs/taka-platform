<?php
/**
 * Complete homepage template.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="taka-tour-page">
	<?php echo taka_tour_render_template( 'partials/hero.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php echo taka_tour_render_template( 'tour-schedule.php', array( 'seminars' => $seminars ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php echo taka_tour_render_template( 'partials/image-grid.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php
	$images = Taka_Tour_Data::images();
	$host_organizer = Taka_Tour_Data::get_organizer( 'kleiner-wald' );
	$host_logo = $host_organizer['logo'] ?? ( $images['kleiner_wald_logo'] ?? '' );
	?>
	<section class="taka-section taka-sensei taka-sensei-section">
		<div class="taka-editorial-text">
			<p class="taka-kicker"><?php echo esc_html( taka_tour_translate( 'sections.sensei.kicker', 'Sensei' ) ); ?></p>
			<h2><?php echo esc_html( taka_tour_translate( 'sections.sensei.headline', 'Takafumi Nakayama' ) ); ?></h2>
			<p><?php echo esc_html( taka_tour_translate( 'sections.sensei.text', 'Präzision, Ruhe und Bewegungsqualität aus der okinawanischen Tradition.' ) ); ?></p>
		</div>
		<figure class="taka-sensei-portrait"><img src="<?php echo esc_url( $images['taka_portrait'] ); ?>" alt="<?php echo esc_attr__( 'Takafumi Nakayama Sensei', 'taka-tour' ); ?>"></figure>
	</section>
	<section class="taka-section taka-training"><p class="taka-kicker"><?php echo esc_html( taka_tour_translate( 'sections.training.kicker', 'Training' ) ); ?></p><h2><?php echo esc_html( taka_tour_translate( 'sections.training.headline', 'Karate-Do, Kobujutsu und Soft Blocking' ) ); ?></h2><p><?php echo esc_html( taka_tour_translate( 'sections.training.text', 'Die Seminare verbinden Grundlagen, Partnerarbeit, Timing, Distanz und Körperstruktur.' ) ); ?></p></section>
	<section class="taka-section taka-community">
		<div>
			<p class="taka-kicker"><?php echo esc_html( taka_tour_translate( 'sections.community.kicker', 'Community' ) ); ?></p>
			<h2><?php echo esc_html( taka_tour_translate( 'sections.community.headline', 'Gemeinsam trainieren' ) ); ?></h2>
			<p><?php echo esc_html( taka_tour_translate( 'sections.community.text', 'Ein europäisches Treffen für ernsthaftes Training und respektvollen Austausch.' ) ); ?></p>
		</div>
	</section>
	<section class="taka-section taka-host taka-host-section">
		<div class="taka-centered-section-inner">
			<p class="taka-kicker"><?php echo esc_html( taka_tour_translate( 'sections.host.kicker', 'Gastgeber' ) ); ?></p>
			<h2><?php echo esc_html( taka_tour_translate( 'sections.host.headline', '5 Jahre Kleiner Wald Dojo' ) ); ?></h2>
			<?php if ( ! empty( $host_logo ) ) : ?>
				<div class="taka-logo-card"><img src="<?php echo esc_url( $host_logo ); ?>" alt="<?php echo esc_attr( $host_organizer['name'] ?? 'Kleiner Wald Dojo' ); ?>"></div>
			<?php endif; ?>
		</div>
	</section>
	<?php echo taka_tour_render_template( 'sponsor.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<section class="taka-section taka-place"><p class="taka-kicker"><?php echo esc_html( taka_tour_translate( 'sections.place.kicker', 'Ort Konz' ) ); ?></p><h2><?php echo esc_html( taka_tour_translate( 'sections.place.headline', 'Praktische Infos' ) ); ?></h2><p><?php echo esc_html( taka_tour_translate( 'sections.place.text', 'Details zu Anreise, Zeiten und lokalen Informationen folgen in den Seminarbeschreibungen.' ) ); ?></p></section>
	<footer class="taka-footer"><?php echo esc_html( taka_tour_translate( 'footer.text', 'TAKA European Tour 2026' ) ); ?></footer>
</div>

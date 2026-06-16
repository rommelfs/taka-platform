<?php
/**
 * Homepage hero partial.
 */

defined( 'ABSPATH' ) || exit;

$images        = Taka_Tour_Data::images();
$hero_image    = $images['hero_image'];
$hero_events   = Taka_Tour_Data::get_public_events();
$ticket_target = '#seminar-konz';
foreach ( $hero_events as $hero_event ) {
	if ( '' !== Taka_Tour_Data::pretix_event_url( $hero_event ) && 'konz' === ( $hero_event['slug'] ?? '' ) ) {
		$ticket_target = '#seminar-' . ( $hero_event['slug'] ?? 'konz' );
		break;
	}
}
?>
<section class="taka-hero" style="--taka-hero-image: url('<?php echo esc_url( $hero_image ); ?>');">
	<div class="taka-hero-content">
		<?php echo taka_tour_render_template( 'partials/language-switcher.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<p class="taka-kicker"><?php echo esc_html( taka_tour_translate( 'hero.kicker', 'TAKA European Tour 2026' ) ); ?></p>
		<h1><?php echo esc_html( taka_tour_translate( 'hero.headline', 'Harmony in Motion' ) ); ?></h1>
		<p><?php echo esc_html( taka_tour_translate( 'hero.intro', 'Eine europäische Seminarreise mit Takafumi Nakayama Sensei – von Helsinki über Berlin, die Niederlande, Belgien und Luxemburg bis in die Region Trier/Konz.' ) ); ?></p>
		<nav class="taka-tour-stations" aria-label="<?php echo esc_attr( taka_tour_translate( 'hero.stations_label', 'Tourstationen' ) ); ?>">
			<?php foreach ( $hero_events as $event ) : ?>
				<?php $label = 'trier-kinderseminar' === ( $event['slug'] ?? '' ) ? ( $event['city'] ?? $event['title'] ?? '' ) : ( $event['title'] ?? '' ); ?>
				<a class="taka-tour-station-link" href="#seminar-<?php echo esc_attr( $event['slug'] ?? '' ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<div class="taka-card-actions"><a class="taka-button" href="#tour"><?php echo esc_html( taka_tour_translate( 'hero.primary_button', 'Seminare ansehen' ) ); ?></a><a class="taka-button taka-button-secondary" href="<?php echo esc_url( $ticket_target ); ?>"><?php echo esc_html( taka_tour_translate( 'hero.secondary_button', 'Tickets' ) ); ?></a></div>
	</div>
</section>

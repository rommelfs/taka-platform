<?php
/**
 * Homepage hero partial.
 */

defined( 'ABSPATH' ) || exit;

$images     = Taka_Tour_Data::images();
$hero_image = $images['hero_image'];
$stations   = array(
	'Helsinki'      => '#seminar-helsinki',
	'Berlin'        => '#seminar-berlin',
	'Netherlands'   => '#seminar-netherlands',
	'Belgium'       => '#seminar-belgium',
	'Illange'       => '#seminar-illange',
	'Hosingen'      => '#seminar-hosingen',
	'Trier'         => '#seminar-trier-kinderseminar',
	'Konz'          => '#seminar-konz',
	'Saarwellingen' => '#seminar-saarwellingen',
);
?>
<section class="taka-hero" style="--taka-hero-image: url('<?php echo esc_url( $hero_image ); ?>');">
	<div class="taka-hero-content">
		<?php echo taka_tour_render_template( 'partials/language-switcher.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<p class="taka-kicker"><?php echo esc_html( taka_tour_translate( 'hero.kicker', 'TAKA European Tour 2026' ) ); ?></p>
		<h1><?php echo esc_html( taka_tour_translate( 'hero.headline', 'Harmony in Motion' ) ); ?></h1>
		<p><?php echo esc_html( taka_tour_translate( 'hero.intro', 'Eine europäische Seminarreise mit Takafumi Nakayama Sensei – von Helsinki über Berlin, die Niederlande, Belgien und Luxemburg bis in die Region Trier/Konz.' ) ); ?></p>
		<nav class="taka-tour-stations" aria-label="<?php echo esc_attr( taka_tour_translate( 'hero.stations_label', 'Tourstationen' ) ); ?>">
			<?php foreach ( $stations as $label => $target ) : ?>
				<a class="taka-tour-station-link" href="<?php echo esc_url( $target ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<div class="taka-card-actions"><a class="taka-button" href="#tour"><?php echo esc_html( taka_tour_translate( 'hero.primary_button', 'Seminare ansehen' ) ); ?></a><a class="taka-button taka-button-secondary" href="#seminar-konz"><?php echo esc_html( taka_tour_translate( 'hero.secondary_button', 'Tickets' ) ); ?></a></div>
	</div>
</section>

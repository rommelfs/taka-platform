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
		<p class="taka-kicker"><?php echo esc_html__( 'TAKA European Tour 2026', 'taka-tour' ); ?></p>
		<h1><?php echo esc_html__( 'Harmony in Motion', 'taka-tour' ); ?></h1>
		<p><?php echo esc_html__( 'Eine europäische Seminarreise mit Takafumi Nakayama Sensei – von Helsinki über Berlin, die Niederlande, Belgien und Luxemburg bis in die Region Trier/Konz.', 'taka-tour' ); ?></p>
		<nav class="taka-tour-stations" aria-label="<?php echo esc_attr__( 'Tourstationen', 'taka-tour' ); ?>">
			<?php foreach ( $stations as $label => $target ) : ?>
				<a class="taka-tour-station-link" href="<?php echo esc_url( $target ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<div class="taka-card-actions"><a class="taka-button" href="#tour"><?php echo esc_html__( 'Seminare ansehen', 'taka-tour' ); ?></a><a class="taka-button taka-button-secondary" href="#seminar-konz"><?php echo esc_html__( 'Tickets', 'taka-tour' ); ?></a></div>
	</div>
</section>

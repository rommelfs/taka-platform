<?php
/**
 * Editorial real-image gallery partial.
 */

defined( 'ABSPATH' ) || exit;

$cards = Taka_Tour_Data::image_grid();
?>
<section class="taka-section taka-editorial-gallery">
	<div class="taka-editorial-gallery__intro">
		<p class="taka-kicker"><?php echo esc_html( taka_tour_translate( 'gallery.kicker', 'Training in Bewegung' ) ); ?></p>
		<h2><?php echo esc_html( taka_tour_translate( 'gallery.headline', 'Kobudo, Soft Blocking und gemeinsames Lernen' ) ); ?></h2>
		<p><?php echo esc_html( taka_tour_translate( 'gallery.intro', 'Echte Eindrücke aus vergangenen Seminaren: Bo-Arbeit, Partnertraining, gemeinsames Üben und internationale Karate-Gemeinschaft.' ) ); ?></p>
	</div>
	<div class="taka-editorial-gallery__grid">
		<?php foreach ( $cards as $card ) : ?>
			<article class="taka-editorial-card<?php echo ! empty( $card['wide'] ) ? ' taka-editorial-card--wide' : ''; ?>">
				<img class="taka-editorial-card__image" src="<?php echo esc_url( $card['image'] ); ?>" alt="<?php echo esc_attr( $card['title'] ); ?>" loading="lazy">
				<div class="taka-editorial-card__caption">
					<h3><?php echo esc_html( taka_tour_translate( 'gallery.cards.' . $card['id'] . '.title', $card['title'] ) ); ?></h3>
					<p><?php echo esc_html( taka_tour_translate( 'gallery.cards.' . $card['id'] . '.text', $card['text'] ) ); ?></p>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
</section>

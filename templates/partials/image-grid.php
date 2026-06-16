<?php
/**
 * Real seminar image grid partial.
 */

defined( 'ABSPATH' ) || exit;

$cards = Taka_Tour_Data::image_grid();
?>
<section class="taka-section taka-image-grid-section">
	<div class="taka-section-heading">
		<h2><?php echo esc_html__( 'Training in Bewegung', 'taka-tour' ); ?></h2>
		<p><?php echo esc_html__( 'Kobudo, Community, gemeinsames Üben und Soft Blocking – echte Eindrücke aus vergangenen Seminaren.', 'taka-tour' ); ?></p>
	</div>
	<div class="taka-image-grid">
		<?php foreach ( $cards as $card ) : ?>
			<article class="taka-image-card">
				<img class="taka-image-card__media" src="<?php echo esc_url( $card['image'] ); ?>" alt="<?php echo esc_attr( $card['title'] ); ?>" loading="lazy">
				<div class="taka-image-card__caption">
					<h3><?php echo esc_html( $card['title'] ); ?></h3>
					<p><?php echo esc_html( $card['text'] ); ?></p>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
</section>

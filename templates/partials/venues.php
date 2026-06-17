<?php
/**
 * Dynamic venue practical information.
 */

defined( 'ABSPATH' ) || exit;

$venues = TAKA_Platform_Data::venues_for_practical_info();
if ( empty( $venues ) ) {
	return;
}
?>
<section class="taka-section taka-locations" id="locations">
	<p class="taka-kicker"><?php echo esc_html( taka_tour_translate( 'event.venue', 'Orte' ) ); ?></p>
	<h2><?php echo esc_html( taka_tour_translate( 'event.practical_information', 'Practical information' ) ); ?></h2>
	<div class="taka-location-grid">
		<?php foreach ( $venues as $venue ) : ?>
			<?php $address = $venue['address'] ?? array(); ?>
			<article class="taka-location-card">
				<h3><?php echo esc_html( $venue['name'] ?? '' ); ?></h3>
				<?php if ( ! empty( $venue['image'] ) ) : ?><img src="<?php echo esc_url( $venue['image'] ); ?>" alt="<?php echo esc_attr( $venue['name'] ?? '' ); ?>"><?php endif; ?>
				<?php $address_line = trim( implode( ' ', array_filter( array( $address['street'] ?? '', $address['postal_code'] ?? '', $address['city'] ?? '', $address['country'] ?? '' ) ) ) ); ?>
				<?php if ( '' !== $address_line ) : ?><p><?php echo esc_html( $address_line ); ?></p><?php endif; ?>
				<?php foreach ( array( 'parking' => 'event.parking', 'accessibility' => 'event.accessibility', 'notes' => 'event.notes' ) as $field => $label_key ) : ?>
					<?php if ( ! empty( $venue[ $field ] ) ) : ?><p><strong><?php echo esc_html( taka_tour_translate( $label_key, ucfirst( $field ) ) ); ?>:</strong> <?php echo esc_html( $venue[ $field ] ); ?></p><?php endif; ?>
				<?php endforeach; ?>
				<?php if ( ! empty( $venue['website'] ) ) : ?><a class="taka-text-link" href="<?php echo esc_url( $venue['website'] ); ?>" rel="noopener"><?php echo esc_html( $venue['website'] ); ?></a><?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>
</section>

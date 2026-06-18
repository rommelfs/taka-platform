<?php
/**
 * Homepage hero partial.
 */

defined( 'ABSPATH' ) || exit;

$hero          = TAKA_Platform_Data::get_hero_settings();
$hero_image    = $hero['image'] ?? '';
$hero_events   = TAKA_Platform_Data::get_public_events();
$text_position = in_array( $hero['text_position'] ?? 'left', array( 'left', 'center', 'right' ), true ) ? $hero['text_position'] : 'left';
$vertical      = in_array( $hero['vertical_alignment'] ?? 'center', array( 'top', 'center', 'bottom' ), true ) ? $hero['vertical_alignment'] : 'center';
$box_enabled   = '1' === (string) ( $hero['text_box_enabled'] ?? '1' );
$overlay       = (float) ( $hero['overlay_strength'] ?? 0.78 );
$box_opacity   = (float) ( $hero['text_box_opacity'] ?? 0.72 );
$max_width     = (string) ( $hero['text_box_max_width'] ?? '620px' );
$style         = '--taka-hero-overlay-alpha:' . esc_attr( (string) max( 0, min( 1, $overlay ) ) ) . ';--taka-hero-box-opacity:' . esc_attr( (string) max( 0, min( 1, $box_opacity ) ) ) . ';--taka-hero-content-max-width:' . esc_attr( $max_width ) . ';';
if ( '' !== $hero_image ) {
	$style .= "--taka-hero-image:url('" . esc_url( $hero_image ) . "');";
}
?>
<section class="taka-hero taka-hero--text-<?php echo esc_attr( $text_position ); ?> taka-hero--vertical-<?php echo esc_attr( $vertical ); ?>" style="<?php echo esc_attr( $style ); ?>">
	<div class="taka-hero-content <?php echo $box_enabled ? 'taka-hero-content--boxed' : ''; ?>">
		<?php echo taka_tour_render_template( 'partials/language-switcher.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php if ( '' !== trim( (string) ( $hero['kicker'] ?? '' ) ) ) : ?>
			<p class="taka-kicker"><?php echo esc_html( $hero['kicker'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== trim( (string) ( $hero['title'] ?? '' ) ) ) : ?>
			<h1><?php echo esc_html( $hero['title'] ); ?></h1>
		<?php endif; ?>
		<?php if ( '' !== trim( (string) ( $hero['description'] ?? '' ) ) ) : ?>
			<p><?php echo esc_html( $hero['description'] ); ?></p>
		<?php endif; ?>
		<nav class="taka-tour-stations" aria-label="<?php echo esc_attr( taka_tour_translate( 'hero.stations_label', 'Tourstationen' ) ); ?>">
			<?php foreach ( $hero_events as $event ) : ?>
				<?php $label = 'trier-kinderseminar' === ( $event['slug'] ?? '' ) ? ( $event['city'] ?? $event['title'] ?? '' ) : ( $event['title'] ?? '' ); ?>
				<a class="taka-tour-station-link" href="#tickets" data-taka-ticket-tab="<?php echo esc_attr( $event['slug'] ?? $event['id'] ?? '' ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<div class="taka-card-actions">
			<?php if ( '' !== trim( (string) ( $hero['primary_button_label'] ?? '' ) ) ) : ?>
				<a class="taka-button" href="<?php echo esc_url( $hero['primary_button_target'] ?? '#tour' ); ?>"><?php echo esc_html( $hero['primary_button_label'] ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php
/**
 * Homepage hero partial.
 */

defined( 'ABSPATH' ) || exit;

$hero          = TAKA_Platform_Data::get_hero_settings();
$hero_image    = $hero['image'] ?? '';
$hero_events   = TAKA_Platform_Data::events_for_language();
$text_position = in_array( $hero['text_position'] ?? 'left', array( 'left', 'center', 'right' ), true ) ? $hero['text_position'] : 'left';
$vertical      = in_array( $hero['vertical_alignment'] ?? 'center', array( 'top', 'center', 'bottom' ), true ) ? $hero['vertical_alignment'] : 'center';
$location_mode = in_array( $hero['location_display_mode'] ?? 'flags', array( 'list', 'flags', 'map', 'map_with_list' ), true ) ? $hero['location_display_mode'] : 'flags';
$show_map      = in_array( $location_mode, array( 'map', 'map_with_list' ), true );
$show_list     = in_array( $location_mode, array( 'list', 'flags', 'map_with_list' ), true );
$show_flags    = in_array( $location_mode, array( 'flags', 'map_with_list' ), true );
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
		<?php if ( $show_map ) : ?>
			<?php echo taka_tour_render_template( 'partials/hero-map.php', array( 'events' => $hero_events, 'show_list' => 'map_with_list' === $location_mode ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
		<?php if ( $show_list && ! $show_map ) : ?>
			<nav class="taka-tour-stations" aria-label="<?php echo esc_attr( taka_tour_translate( 'hero.stations_label', 'Tourstationen' ) ); ?>">
				<?php foreach ( $hero_events as $event ) : ?>
					<?php $label = 'trier-kinderseminar' === ( $event['slug'] ?? '' ) ? ( $event['city'] ?? $event['title'] ?? '' ) : ( $event['title'] ?? '' ); ?>
					<a class="taka-tour-station-link" href="#tickets" data-taka-ticket-tab="<?php echo esc_attr( $event['slug'] ?? $event['id'] ?? '' ); ?>"><?php if ( $show_flags && '' !== trim( (string) ( $event['hero_flag'] ?? '' ) ) ) : ?><span class="taka-hero-location-flag" aria-hidden="true"><?php echo esc_html( $event['hero_flag'] ); ?></span><?php endif; ?><span><?php echo esc_html( $label ); ?></span></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
		<div class="taka-card-actions">
			<?php if ( '' !== trim( (string) ( $hero['primary_button_label'] ?? '' ) ) ) : ?>
				<a class="taka-button" href="<?php echo esc_url( $hero['primary_button_target'] ?? '#tour' ); ?>"><?php echo esc_html( $hero['primary_button_label'] ); ?></a>
			<?php endif; ?>
			<?php if ( '' !== trim( (string) ( $hero['secondary_button_label'] ?? '' ) ) ) : ?>
				<a class="taka-button taka-button-secondary" href="<?php echo esc_url( $hero['secondary_button_target'] ?? '#seminar-konz' ); ?>"><?php echo esc_html( $hero['secondary_button_label'] ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</section>

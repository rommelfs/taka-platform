<?php
/**
 * Homepage hero partial.
 */

defined( 'ABSPATH' ) || exit;

$hero          = TAKA_Platform_Data::get_hero_settings();
$hero_image    = $hero['image'] ?? '';
$hero_lang     = taka_tour_current_language();
$hero_events   = TAKA_Platform_Data::events_for_language( $hero_lang );
$hero_stations = TAKA_Platform_Data::hero_route_map_stations( $hero_lang, $hero_events );
$text_position = in_array( $hero['text_position'] ?? 'left', array( 'left', 'center', 'right' ), true ) ? $hero['text_position'] : 'left';
$vertical      = in_array( $hero['vertical_alignment'] ?? 'center', array( 'top', 'center', 'bottom' ), true ) ? $hero['vertical_alignment'] : 'center';
$location_mode = TAKA_Platform_Data::normalize_hero_location_display_mode( $hero['location_display_mode'] ?? 'route_map_with_list' );
$show_map      = in_array( $location_mode, array( 'route_map', 'route_map_with_list' ), true );
$show_list     = in_array( $location_mode, array( 'list', 'flags', 'route_map_with_list' ), true );
$show_flags    = in_array( $location_mode, array( 'flags', 'route_map_with_list' ), true );
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
			<?php echo taka_tour_render_template( 'partials/hero-route-map.php', array( 'stations' => $hero_stations, 'show_list' => 'route_map_with_list' === $location_mode ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
		<?php if ( $show_list && ! $show_map ) : ?>
			<nav class="taka-tour-stations" aria-label="<?php echo esc_attr( taka_tour_translate( 'hero.stations_label', 'Tourstationen' ) ); ?>">
				<?php foreach ( $hero_stations as $station ) : ?>
					<?php $event = $station['event'] ?? array(); $label = $station['label'] ?? ( $event['title'] ?? '' ); ?>
					<?php $tab_key = TAKA_Platform_Data::event_panel_key( $event ); $share_url = TAKA_Platform_Data::event_share_url( $event, taka_tour_current_language() ) ?: '#tickets'; ?>
					<a class="taka-tour-station-link" href="<?php echo esc_url( $share_url ); ?>" data-taka-ticket-tab="<?php echo esc_attr( $tab_key ); ?>"><?php if ( $show_flags && '' !== trim( (string) ( $event['hero_flag'] ?? '' ) ) ) : ?><span class="taka-hero-location-flag" aria-hidden="true"><?php echo esc_html( $event['hero_flag'] ); ?></span><?php endif; ?><span><?php echo esc_html( $label ); ?></span></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
		<div class="taka-card-actions">
			<?php if ( '' !== trim( (string) ( $hero['primary_button_label'] ?? '' ) ) ) : ?>
				<a class="taka-button" href="<?php echo esc_url( $hero['primary_button_target'] ?? '#tour' ); ?>"><?php echo esc_html( $hero['primary_button_label'] ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</section>

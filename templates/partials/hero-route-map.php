<?php
/**
 * Dynamic hero tour route map with accessible location fallback list.
 */

defined( 'ABSPATH' ) || exit;

$stops = is_array( $stations ?? null ) ? array_values( $stations ) : TAKA_Platform_Data::hero_route_map_stations( null, $events ?? null );
if ( empty( $stops ) ) { return; }

foreach ( $stops as $position => &$stop ) {
	$stop['marker_x'] = is_numeric( $stop['marker_x'] ?? null ) ? (float) $stop['marker_x'] : (float) ( $stop['x'] ?? 0 );
	$stop['marker_y'] = is_numeric( $stop['marker_y'] ?? null ) ? (float) $stop['marker_y'] : (float) ( $stop['y'] ?? 0 );
	$stop['label_x'] = is_numeric( $stop['label_x'] ?? null ) ? (float) $stop['label_x'] : $stop['marker_x'];
	$stop['label_y'] = is_numeric( $stop['label_y'] ?? null ) ? (float) $stop['label_y'] : $stop['marker_y'];
	$anchor = sanitize_key( $stop['label_anchor'] ?? '' );
	$stop['label_anchor'] = in_array( $anchor, array( 'left', 'right', 'center' ), true ) ? $anchor : 'left';
	$stop['label_mobile_x'] = is_numeric( $stop['label_mobile_x'] ?? null ) ? (float) $stop['label_mobile_x'] : $stop['label_x'];
	$stop['label_mobile_y'] = is_numeric( $stop['label_mobile_y'] ?? null ) ? (float) $stop['label_mobile_y'] : $stop['label_y'];
	$mobile_anchor = sanitize_key( $stop['label_mobile_anchor'] ?? '' );
	$stop['label_mobile_anchor'] = in_array( $mobile_anchor, array( 'left', 'right', 'center' ), true ) ? $mobile_anchor : $stop['label_anchor'];
}
unset( $stop );

$format_route_point = static fn( $stop ) => round( $stop['marker_x'], 2 ) . ',' . round( $stop['marker_y'], 2 );
$route_points = array_map( $format_route_point, $stops );
$line_points = count( $route_points ) > 1 ? implode( ' ', $route_points ) : '';
$mobile_line_points = $line_points;

if ( count( $stops ) > 1 ) {
	$last_index = count( $stops ) - 1;
	$last_stop = $stops[ $last_index ];
	$reference_stop = $stops[ max( 0, $last_index - 2 ) ];
	$route_dx = $last_stop['marker_x'] - $reference_stop['marker_x'];
	$route_dy = $last_stop['marker_y'] - $reference_stop['marker_y'];
	$route_length = sqrt( ( $route_dx * $route_dx ) + ( $route_dy * $route_dy ) );

	if ( $route_length > 0 ) {
		$mobile_extension = 7.0;
		$mobile_edge_padding = 2.5;
		$tail_x = max( $mobile_edge_padding, min( 100 - $mobile_edge_padding, $last_stop['marker_x'] + ( $route_dx / $route_length * $mobile_extension ) ) );
		$tail_y = max( $mobile_edge_padding, min( 100 - $mobile_edge_padding, $last_stop['marker_y'] + ( $route_dy / $route_length * $mobile_extension ) ) );
		$mobile_line_points .= ' ' . round( $tail_x, 2 ) . ',' . round( $tail_y, 2 );
	}
}
?>
<div class="taka-hero-route-map" aria-label="<?php echo esc_attr( taka_tour_translate( 'hero.event_locations', 'Event locations' ) ); ?>">
	<?php if ( ! empty( $stops ) ) : ?>
		<div class="taka-hero-route-map__canvas" aria-label="<?php echo esc_attr( taka_tour_translate( 'hero.map_view', 'Map view' ) ); ?>">
			<svg class="taka-hero-route-map__svg" viewBox="0 0 100 100" aria-hidden="true" focusable="false" role="presentation">
				<path class="taka-hero-route-map__silhouette" d="M64 9 C75 12 82 23 79 34 C88 42 85 56 74 59 C70 70 58 75 48 70 C39 78 25 73 24 61 C13 55 14 39 25 34 C28 23 40 19 48 24 C51 14 57 9 64 9 Z" />
				<path class="taka-hero-route-map__silhouette taka-hero-route-map__silhouette--south" d="M50 67 C60 66 69 72 70 82 C63 88 49 88 42 80 C39 74 43 69 50 67 Z" />
				<?php if ( '' !== $line_points ) : ?>
					<polyline class="taka-hero-route-map__line taka-hero-route-map__line--desktop" points="<?php echo esc_attr( $line_points ); ?>" />
					<polyline class="taka-hero-route-map__line taka-hero-route-map__line--mobile" points="<?php echo esc_attr( $mobile_line_points ); ?>" />
				<?php endif; ?>
			</svg>
			<?php foreach ( $stops as $stop ) : ?>
				<?php
				$event = $stop['event'];
				$tab_key = TAKA_Platform_Data::event_panel_key( $event );
				$share_url = TAKA_Platform_Data::event_share_url( $event, taka_tour_current_language() ) ?: '#tickets';
				$country = trim( (string) ( $event['country_label'] ?? ( $event['country'] ?? '' ) ) );
				$flag = trim( (string) ( $event['hero_flag'] ?? '' ) );
				$aria_label = sprintf( taka_tour_translate( 'hero.show_tickets_for', 'Show tickets for %s' ), trim( $stop['label'] . ( '' !== $country ? ', ' . $country : '' ) ) );
				$label_class = 'taka-hero-route-map__label taka-hero-route-map__label--anchor-' . $stop['label_anchor'] . ' taka-hero-route-map__label--mobile-anchor-' . $stop['label_mobile_anchor'];
				?>
				<a class="taka-hero-route-map__marker" href="<?php echo esc_url( $share_url ); ?>" data-taka-ticket-tab="<?php echo esc_attr( $tab_key ); ?>" style="left:<?php echo esc_attr( (string) $stop['marker_x'] ); ?>%;top:<?php echo esc_attr( (string) $stop['marker_y'] ); ?>%;" aria-label="<?php echo esc_attr( $aria_label ); ?>">
					<span class="taka-hero-route-map__pin" aria-hidden="true"></span>
				</a>
				<a class="<?php echo esc_attr( $label_class ); ?>" href="<?php echo esc_url( $share_url ); ?>" data-taka-ticket-tab="<?php echo esc_attr( $tab_key ); ?>" style="left:<?php echo esc_attr( (string) $stop['label_x'] ); ?>%;top:<?php echo esc_attr( (string) $stop['label_y'] ); ?>%;--taka-route-label-mobile-x:<?php echo esc_attr( (string) $stop['label_mobile_x'] ); ?>%;--taka-route-label-mobile-y:<?php echo esc_attr( (string) $stop['label_mobile_y'] ); ?>%;" aria-label="<?php echo esc_attr( $aria_label ); ?>">
					<?php if ( '' !== $flag ) : ?><span class="taka-hero-location-flag" aria-hidden="true"><?php echo esc_html( $flag ); ?></span><?php endif; ?><span class="taka-hero-route-map__label-text"><?php echo esc_html( $stop['label'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

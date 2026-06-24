<?php
/**
 * Dynamic hero tour route map with accessible location fallback list.
 */

defined( 'ABSPATH' ) || exit;

$stops = is_array( $stations ?? null ) ? array_values( $stations ) : TAKA_Platform_Data::hero_route_map_stations( null, $events ?? null );
if ( empty( $stops ) ) { return; }

$label_gap = 8.5;
$last_label_y = 7;
foreach ( $stops as $position => &$stop ) {
	$preferred_y = max( 8, min( 92, (float) $stop['y'] ) );
	$label_y = max( $preferred_y, $last_label_y + $label_gap );
	$remaining = count( $stops ) - $position - 1;
	$max_y = 92 - ( $remaining * $label_gap );
	$label_y = min( $label_y, $max_y );
	$last_label_y = $label_y;
	$stop['label_y'] = max( 8, min( 92, $label_y ) );
	$stop['label_offset'] = $stop['label_y'] - (float) $stop['y'];
	if ( $stop['x'] > 64 ) {
		$stop['label_side'] = 'left';
	} elseif ( $stop['x'] < 36 ) {
		$stop['label_side'] = 'right';
	} else {
		$stop['label_side'] = 0 === $position % 2 ? 'right' : 'left';
	}
}
unset( $stop );

$line_points = count( $stops ) > 1 ? implode( ' ', array_map( static fn( $stop ) => round( $stop['x'], 2 ) . ',' . round( $stop['y'], 2 ), $stops ) ) : '';
?>
<div class="taka-hero-route-map" aria-label="<?php echo esc_attr( taka_tour_translate( 'hero.event_locations', 'Event locations' ) ); ?>">
	<?php if ( ! empty( $stops ) ) : ?>
		<div class="taka-hero-route-map__canvas" aria-label="<?php echo esc_attr( taka_tour_translate( 'hero.map_view', 'Map view' ) ); ?>">
			<svg class="taka-hero-route-map__svg" viewBox="0 0 100 100" aria-hidden="true" focusable="false" role="presentation">
				<path class="taka-hero-route-map__silhouette" d="M64 9 C75 12 82 23 79 34 C88 42 85 56 74 59 C70 70 58 75 48 70 C39 78 25 73 24 61 C13 55 14 39 25 34 C28 23 40 19 48 24 C51 14 57 9 64 9 Z" />
				<path class="taka-hero-route-map__silhouette taka-hero-route-map__silhouette--south" d="M50 67 C60 66 69 72 70 82 C63 88 49 88 42 80 C39 74 43 69 50 67 Z" />
				<?php if ( '' !== $line_points ) : ?>
					<polyline class="taka-hero-route-map__line" points="<?php echo esc_attr( $line_points ); ?>" />
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
				$stop_class = 'taka-hero-route-map__stop taka-hero-route-map__stop--' . $stop['label_side'];
				$label_offset = round( (float) $stop['label_offset'] * 0.2, 2 ) . 'rem';
				?>
				<a class="<?php echo esc_attr( $stop_class ); ?>" href="<?php echo esc_url( $share_url ); ?>" data-taka-ticket-tab="<?php echo esc_attr( $tab_key ); ?>" style="left:<?php echo esc_attr( (string) $stop['x'] ); ?>%;top:<?php echo esc_attr( (string) $stop['y'] ); ?>%;--taka-route-label-offset:<?php echo esc_attr( $label_offset ); ?>;" aria-label="<?php echo esc_attr( $aria_label ); ?>">
					<span class="taka-hero-route-map__pin" aria-hidden="true"></span>
					<span class="taka-hero-route-map__label"><?php if ( '' !== $flag ) : ?><span class="taka-hero-location-flag" aria-hidden="true"><?php echo esc_html( $flag ); ?></span><?php endif; ?><span class="taka-hero-route-map__label-text"><?php echo esc_html( $stop['label'] ); ?></span></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

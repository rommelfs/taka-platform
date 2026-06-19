<?php
/**
 * Dynamic hero tour route map with accessible location fallback list.
 */

defined( 'ABSPATH' ) || exit;

$events = is_array( $events ?? null ) ? array_values( $events ) : array();
$route_map_numeric_date = static function ( $event ) {
	$start = trim( (string) ( $event['date_start'] ?? ( $event['program_items'][0]['date'] ?? '' ) ) );
	$end = trim( (string) ( $event['date_end'] ?? '' ) );
	if ( '' === $end && ! empty( $event['program_items'] ) && is_array( $event['program_items'] ) ) {
		$last_item = end( $event['program_items'] );
		$end = is_array( $last_item ) ? trim( (string) ( $last_item['date'] ?? '' ) ) : '';
		reset( $event['program_items'] );
	}
	$start_ts = '' !== $start ? strtotime( $start ) : false;
	$end_ts = '' !== $end ? strtotime( $end ) : false;
	if ( false === $start_ts ) { return ''; }
	if ( false === $end_ts || $start === $end ) { return gmdate( 'd.m.Y', $start_ts ); }
	if ( gmdate( 'mY', $start_ts ) === gmdate( 'mY', $end_ts ) ) {
		return gmdate( 'd.', $start_ts ) . '–' . gmdate( 'd.m.Y', $end_ts );
	}
	return gmdate( 'd.m.Y', $start_ts ) . '–' . gmdate( 'd.m.Y', $end_ts );
};

usort(
	$events,
	static function ( $a, $b ) {
		$a_route = isset( $a['route_order'] ) && '' !== (string) $a['route_order'] ? (float) $a['route_order'] : null;
		$b_route = isset( $b['route_order'] ) && '' !== (string) $b['route_order'] ? (float) $b['route_order'] : null;
		if ( null !== $a_route || null !== $b_route ) {
			return ( $a_route ?? 99999 ) <=> ( $b_route ?? 99999 );
		}
		$a_sort = (int) ( $a['sort_order'] ?? 0 );
		$b_sort = (int) ( $b['sort_order'] ?? 0 );
		if ( $a_sort !== $b_sort ) { return $a_sort <=> $b_sort; }
		$a_date = (string) ( $a['date_start'] ?? ( $a['program_items'][0]['date'] ?? '' ) );
		$b_date = (string) ( $b['date_start'] ?? ( $b['program_items'][0]['date'] ?? '' ) );
		if ( $a_date !== $b_date ) { return strcmp( $a_date, $b_date ); }
		return strcmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) );
	}
);

$stops = array();
$count = max( 1, count( $events ) );
foreach ( $events as $index => $event ) {
	$point = is_array( $event['hero_route_map'] ?? null ) ? $event['hero_route_map'] : array();
	$manual_x = isset( $point['x'] ) && is_numeric( $point['x'] ) ? max( 0, min( 100, (float) $point['x'] ) ) : null;
	$manual_y = isset( $point['y'] ) && is_numeric( $point['y'] ) ? max( 0, min( 100, (float) $point['y'] ) ) : null;
	$progress = 1 === $count ? 0 : $index / ( $count - 1 );
	$auto_x = 72 - min( 42, $index * 5.4 ) + ( 0 === $index % 2 ? 0 : -4 );
	$auto_y = 13 + $progress * 72;
	$x = null !== $manual_x ? $manual_x : max( 18, min( 82, $auto_x ) );
	$y = null !== $manual_y ? $manual_y : max( 12, min( 88, $auto_y ) );
	$label = trim( (string) ( $point['label'] ?? '' ) );
	if ( '' === $label ) { $label = trim( (string) ( $event['city'] ?? '' ) ) ?: trim( (string) ( $event['title'] ?? '' ) ); }
	if ( '' === $label ) { continue; }
	$stops[] = array( 'event' => $event, 'x' => $x, 'y' => $y, 'label' => $label, 'index' => $index );
}

if ( empty( $events ) ) { return; }

$line_points = count( $stops ) > 1 ? implode( ' ', array_map( static fn( $stop ) => round( $stop['x'], 2 ) . ',' . round( $stop['y'], 2 ), $stops ) ) : '';
$list_class = 'taka-hero-route-map__list--fallback';
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
				$tab_key = (string) ( $event['slug'] ?? $event['id'] ?? '' );
				$country = trim( (string) ( $event['country_label'] ?? ( $event['country'] ?? '' ) ) );
				$flag = trim( (string) ( $event['hero_flag'] ?? '' ) );
				$date = $route_map_numeric_date( $event );
				$aria_label = sprintf( taka_tour_translate( 'hero.show_tickets_for', 'Show tickets for %s' ), trim( $stop['label'] . ( '' !== $country ? ', ' . $country : '' ) ) );
				$stop_class = $stop['x'] > 56 ? 'taka-hero-route-map__stop taka-hero-route-map__stop--align-left' : 'taka-hero-route-map__stop';
				?>
				<a class="<?php echo esc_attr( $stop_class ); ?>" href="#tickets" data-taka-ticket-tab="<?php echo esc_attr( $tab_key ); ?>" style="left:<?php echo esc_attr( (string) $stop['x'] ); ?>%;top:<?php echo esc_attr( (string) $stop['y'] ); ?>%;" aria-label="<?php echo esc_attr( $aria_label ); ?>">
					<span class="taka-hero-route-map__pin" aria-hidden="true"></span>
					<span class="taka-hero-route-map__label"><?php if ( '' !== $flag ) : ?><span class="taka-hero-location-flag" aria-hidden="true"><?php echo esc_html( $flag ); ?></span><?php endif; ?><span class="taka-hero-route-map__label-text"><?php echo esc_html( $stop['label'] ); ?></span><?php if ( '' !== $date ) : ?><small><?php echo esc_html( $date ); ?></small><?php endif; ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<nav class="taka-hero-route-map__list <?php echo esc_attr( $list_class ); ?>" aria-label="<?php echo esc_attr( taka_tour_translate( 'hero.list_view', 'List view' ) ); ?>">
		<?php foreach ( $events as $event ) : ?>
			<?php
			$label = 'trier-kinderseminar' === ( $event['slug'] ?? '' ) ? ( $event['city'] ?? $event['title'] ?? '' ) : ( $event['title'] ?? '' );
			$flag = trim( (string) ( $event['hero_flag'] ?? '' ) );
			?>
			<a class="taka-tour-station-link" href="#tickets" data-taka-ticket-tab="<?php echo esc_attr( $event['slug'] ?? $event['id'] ?? '' ); ?>"><?php if ( '' !== $flag ) : ?><span class="taka-hero-location-flag" aria-hidden="true"><?php echo esc_html( $flag ); ?></span><?php endif; ?><span><?php echo esc_html( $label ); ?></span></a>
		<?php endforeach; ?>
	</nav>
</div>

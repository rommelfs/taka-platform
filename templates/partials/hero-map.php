<?php
/**
 * Stylized hero Europe map with accessible location fallback list.
 */

defined( 'ABSPATH' ) || exit;

$events = is_array( $events ?? null ) ? $events : array();
$show_list = ! empty( $show_list );
$pins = array();

foreach ( $events as $event ) {
	$point = is_array( $event['hero_map'] ?? null ) ? $event['hero_map'] : null;
	if ( ! is_array( $point ) ) { continue; }
	$label = trim( (string) ( $point['label'] ?? '' ) );
	if ( '' === $label ) { continue; }
	$pins[] = array(
		'event' => $event,
		'x' => max( 0, min( 100, (float) ( $point['x'] ?? 0 ) ) ),
		'y' => max( 0, min( 100, (float) ( $point['y'] ?? 0 ) ) ),
		'label' => $label,
	);
}

if ( empty( $events ) ) { return; }
$list_class = $show_list || empty( $pins ) ? '' : 'taka-hero-map__list--fallback';
?>
<div class="taka-hero-map" aria-label="<?php echo esc_attr( taka_tour_translate( 'hero.event_locations', 'Event locations' ) ); ?>">
	<?php if ( ! empty( $pins ) ) : ?>
		<div class="taka-hero-map__canvas" aria-label="<?php echo esc_attr( taka_tour_translate( 'hero.map_view', 'Map view' ) ); ?>">
			<svg class="taka-hero-map__svg" viewBox="0 0 100 72" aria-hidden="true" focusable="false" role="presentation">
				<path class="taka-hero-map__land taka-hero-map__land--north" d="M45 6 C53 3 64 5 71 12 C77 18 77 27 71 31 C64 36 55 33 50 27 C44 20 39 12 45 6 Z" />
				<path class="taka-hero-map__land taka-hero-map__land--west" d="M22 27 C29 18 42 18 51 26 C58 32 56 44 49 52 C41 61 28 62 20 55 C12 48 14 36 22 27 Z" />
				<path class="taka-hero-map__land taka-hero-map__land--central" d="M40 28 C50 22 63 25 68 35 C73 45 68 57 58 62 C48 67 35 62 31 52 C27 42 31 33 40 28 Z" />
				<path class="taka-hero-map__land taka-hero-map__land--south" d="M45 52 C53 49 62 53 67 60 C70 65 66 70 58 70 C48 70 40 64 39 58 C38 55 40 53 45 52 Z" />
				<path class="taka-hero-map__coast" d="M24 55 C33 48 43 47 51 53 M52 28 C58 34 64 39 69 47 M39 26 C35 34 31 42 29 52" />
			</svg>
			<?php foreach ( $pins as $pin ) : ?>
				<?php
				$event = $pin['event'];
				$tab_key = (string) ( $event['slug'] ?? $event['id'] ?? '' );
				$country = trim( (string) ( $event['country_label'] ?? ( $event['country'] ?? '' ) ) );
				$flag = trim( (string) ( $event['hero_flag'] ?? '' ) );
				$aria_label = sprintf( taka_tour_translate( 'hero.show_tickets_for', 'Show tickets for %s' ), trim( $pin['label'] . ( '' !== $country ? ', ' . $country : '' ) ) );
				?>
				<a class="taka-hero-map__pin" href="#tickets" data-taka-ticket-tab="<?php echo esc_attr( $tab_key ); ?>" style="left:<?php echo esc_attr( (string) $pin['x'] ); ?>%;top:<?php echo esc_attr( (string) $pin['y'] ); ?>%;" aria-label="<?php echo esc_attr( $aria_label ); ?>">
					<span class="taka-hero-map__marker" aria-hidden="true"></span>
					<span class="taka-hero-map__label"><?php if ( '' !== $flag ) : ?><span class="taka-hero-location-flag" aria-hidden="true"><?php echo esc_html( $flag ); ?></span><?php endif; ?><?php echo esc_html( $pin['label'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<nav class="taka-hero-map__list <?php echo esc_attr( $list_class ); ?>" aria-label="<?php echo esc_attr( taka_tour_translate( 'hero.list_view', 'List view' ) ); ?>">
		<?php foreach ( $events as $event ) : ?>
			<?php
			$label = 'trier-kinderseminar' === ( $event['slug'] ?? '' ) ? ( $event['city'] ?? $event['title'] ?? '' ) : ( $event['title'] ?? '' );
			$flag = trim( (string) ( $event['hero_flag'] ?? '' ) );
			?>
			<a class="taka-tour-station-link" href="#tickets" data-taka-ticket-tab="<?php echo esc_attr( $event['slug'] ?? $event['id'] ?? '' ); ?>"><?php if ( '' !== $flag ) : ?><span class="taka-hero-location-flag" aria-hidden="true"><?php echo esc_html( $flag ); ?></span><?php endif; ?><span><?php echo esc_html( $label ); ?></span></a>
		<?php endforeach; ?>
	</nav>
</div>

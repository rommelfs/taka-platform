<?php
/**
 * Pretix widget partial.
 */

defined( 'ABSPATH' ) || exit;

$event_url = ! empty( $event ) ? $event : ( $url ?? '' );
?>
<div class="taka-pretix-widget">
	<p class="taka-widget-label"><?php echo esc_html( $label ); ?></p>
	<div class="taka-pretix-widget-wrap"><pretix-widget event="<?php echo esc_url( $event_url ); ?>"></pretix-widget></div>
	<noscript><a href="<?php echo esc_url( $event_url ); ?>" rel="noopener"><?php echo esc_html( taka_tour_translate( 'seminar.ticketshop_open_pretix', 'Tickets bei Pretix öffnen' ) ); ?></a></noscript>
</div>

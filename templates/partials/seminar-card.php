<?php
/**
 * Seminar card partial.
 */

defined( 'ABSPATH' ) || exit;

$pretix_event_url = Taka_Tour_Data::pretix_event_url( $seminar );
?>
<article class="taka-seminar-card" id="seminar-<?php echo esc_attr( $seminar['slug'] ); ?>">
	<div class="taka-card-meta"><span><?php echo esc_html( $seminar['flag'] ); ?></span><span><?php echo esc_html( $seminar['country_label'] ?? $seminar['country'] ); ?></span><span><?php echo esc_html( $seminar['date'] ); ?></span></div>
	<h3><?php echo esc_html( $seminar['title'] ); ?></h3>
	<p class="taka-subtitle"><?php echo esc_html( $seminar['subtitle'] ); ?></p>
	<p><?php echo esc_html( $seminar['description'] ); ?></p>
	<dl class="taka-details"><div><dt><?php echo esc_html( taka_tour_translate( 'seminar.format_label', 'Format' ) ); ?></dt><dd><?php echo esc_html( $seminar['type'] ); ?></dd></div><div><dt><?php echo esc_html( taka_tour_translate( 'seminar.host_label', 'Gastgeber' ) ); ?></dt><dd><?php echo esc_html( $seminar['hosts'] ); ?></dd></div></dl>
	<?php if ( '' !== $pretix_event_url ) : ?>
		<div class="taka-seminar-pretix">
			<?php echo taka_tour_render_template( 'partials/pretix-widget.php', array( 'event' => $pretix_event_url, 'label' => $seminar['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<a class="taka-ticket-direct-link" href="<?php echo esc_url( $pretix_event_url ); ?>" rel="noopener"><?php echo esc_html( taka_tour_translate( 'seminar.ticketshop_direct', 'Ticketshop direkt öffnen' ) ); ?></a>
		</div>
	<?php else : ?>
		<p class="taka-ticket-status"><?php echo esc_html( $seminar['ticket_status'] ); ?></p>
	<?php endif; ?>
</article>

<?php
/**
 * Event card partial.
 */

defined( 'ABSPATH' ) || exit;

$pretix_event_url = Taka_Tour_Data::pretix_event_url( $seminar );
$time_parts       = array_filter( array( $seminar['time_start'] ?? '', $seminar['time_end'] ?? '' ) );
$time_display     = implode( '–', $time_parts );
$details          = array(
	array( 'label' => taka_tour_translate( 'event.date', 'Datum' ), 'value' => $seminar['date'] ?? '' ),
	array( 'label' => taka_tour_translate( 'event.venue', 'Ort' ), 'value' => $seminar['venue_name'] ?? '' ),
	array( 'label' => taka_tour_translate( 'event.address', 'Adresse' ), 'value' => $seminar['address'] ?? '' ),
	array( 'label' => taka_tour_translate( 'event.doors_open', 'Türen öffnen' ), 'value' => $seminar['doors_open'] ?? '' ),
	array( 'label' => taka_tour_translate( 'event.time', 'Zeit' ), 'value' => $time_display ),
	array( 'label' => taka_tour_translate( 'seminar.format_label', 'Format' ), 'value' => $seminar['format'] ?? '' ),
	array( 'label' => taka_tour_translate( 'event.audience', 'Zielgruppe' ), 'value' => $seminar['audience'] ?? '' ),
	array( 'label' => taka_tour_translate( 'event.level', 'Level' ), 'value' => $seminar['level'] ?? '' ),
	array( 'label' => taka_tour_translate( 'event.organizer', 'Veranstalter' ), 'value' => $seminar['organizer_name'] ?? '' ),
	array( 'label' => taka_tour_translate( 'event.parking', 'Parken' ), 'value' => $seminar['parking_display'] ?? '' ),
);
?>
<article class="taka-seminar-card" id="seminar-<?php echo esc_attr( $seminar['slug'] ?? '' ); ?>">
	<div class="taka-card-meta"><span><?php echo esc_html( $seminar['flag'] ?? '' ); ?></span><span><?php echo esc_html( $seminar['city'] ?? $seminar['title'] ?? '' ); ?></span><span><?php echo esc_html( $seminar['country_label'] ?? $seminar['country'] ?? '' ); ?></span></div>
	<h3><?php echo esc_html( $seminar['title'] ?? '' ); ?></h3>
	<?php if ( ! empty( $seminar['subtitle'] ) ) : ?>
		<p class="taka-subtitle"><?php echo esc_html( $seminar['subtitle'] ); ?></p>
	<?php endif; ?>
	<?php if ( ! empty( $seminar['description'] ) ) : ?>
		<p><?php echo esc_html( $seminar['description'] ); ?></p>
	<?php endif; ?>
	<?php foreach ( array( 'long_description', 'ticket_card_text', 'notes', 'accessibility' ) as $text_field ) : ?>
		<?php if ( ! empty( $seminar[ $text_field ] ) ) : ?>
			<p><?php echo esc_html( $seminar[ $text_field ] ); ?></p>
		<?php endif; ?>
	<?php endforeach; ?>
	<dl class="taka-details">
		<?php foreach ( $details as $detail ) : ?>
			<?php if ( '' === trim( (string) $detail['value'] ) ) : ?>
				<?php continue; ?>
			<?php endif; ?>
			<div><dt><?php echo esc_html( $detail['label'] ); ?></dt><dd><?php echo esc_html( $detail['value'] ); ?></dd></div>
		<?php endforeach; ?>
	</dl>
	<?php if ( '' !== $pretix_event_url ) : ?>
		<div class="taka-seminar-pretix">
			<?php echo taka_tour_render_template( 'partials/pretix-widget.php', array( 'event' => $pretix_event_url, 'label' => $seminar['title'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<a class="taka-ticket-direct-link" href="<?php echo esc_url( $pretix_event_url ); ?>" rel="noopener"><?php echo esc_html( taka_tour_translate( 'event.ticketshop_direct', 'Ticketshop direkt öffnen' ) ); ?></a>
		</div>
	<?php else : ?>
		<p class="taka-ticket-status"><?php echo esc_html( $seminar['ticket_status_label'] ?? taka_tour_translate( 'event.ticketshop_soon', 'Ticketshop folgt' ) ); ?></p>
	<?php endif; ?>
</article>

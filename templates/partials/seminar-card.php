<?php
/**
 * Event card partial.
 */

defined( 'ABSPATH' ) || exit;

$pretix_event_url = Taka_Tour_Data::pretix_event_url( $seminar );
$time_parts       = array_filter( array( $seminar['time_start'] ?? '', $seminar['time_end'] ?? '' ) );
$time_display     = implode( '–', $time_parts );
$summary_details  = array(
	array( 'label' => taka_tour_translate( 'event.date', 'Datum' ), 'value' => $seminar['date'] ?? '' ),
	array( 'label' => taka_tour_translate( 'event.time', 'Zeit' ), 'value' => $time_display ),
	array( 'label' => taka_tour_translate( 'event.venue', 'Ort' ), 'value' => $seminar['venue_name'] ?? '' ),
);
?>
<article class="taka-seminar-card" id="seminar-<?php echo esc_attr( $seminar['slug'] ?? '' ); ?>">
	<div class="taka-card-meta"><span><?php echo esc_html( $seminar['flag'] ?? '' ); ?></span><span><?php echo esc_html( $seminar['city'] ?? $seminar['title'] ?? '' ); ?></span><span><?php echo esc_html( $seminar['country_label'] ?? $seminar['country'] ?? '' ); ?></span></div>
	<h3><?php echo esc_html( $seminar['title'] ?? '' ); ?></h3>
	<?php if ( ! empty( $seminar['subtitle'] ) ) : ?>
		<p class="taka-subtitle"><?php echo esc_html( $seminar['subtitle'] ); ?></p>
	<?php endif; ?>
	<?php if ( ! empty( $seminar['ticket_overview_image'] ) ) : ?>
		<figure class="taka-ticket-card-photo"><img src="<?php echo esc_url( $seminar['ticket_overview_image'] ); ?>" alt="<?php echo esc_attr( $seminar['ticket_overview_image_alt'] ?? taka_tour_translate( 'event.event_photo', 'Event photo' ) ); ?>" loading="lazy"></figure>
	<?php endif; ?>
	<dl class="taka-details">
		<?php foreach ( $summary_details as $detail ) : ?>
			<?php if ( '' === trim( (string) $detail['value'] ) ) : ?>
				<?php continue; ?>
			<?php endif; ?>
			<div><dt><?php echo esc_html( $detail['label'] ); ?></dt><dd><?php echo esc_html( $detail['value'] ); ?></dd></div>
		<?php endforeach; ?>
	</dl>
	<?php if ( '' !== $pretix_event_url ) : ?>
		<div class="taka-seminar-pretix">
			<?php echo taka_tour_render_template( 'partials/ticket-widget.php', array( 'event' => $pretix_event_url, 'label' => $seminar['title'] ?? '', 'seminar' => $seminar ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	<?php else : ?>
		<?php echo taka_tour_render_template( 'partials/ticket-widget.php', array( 'event' => '', 'label' => $seminar['title'] ?? '', 'seminar' => $seminar ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endif; ?>
</article>

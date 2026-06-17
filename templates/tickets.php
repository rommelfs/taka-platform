<?php
/**
 * Standalone ticket block.
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="taka-section taka-tickets" id="tickets">
	<p class="taka-kicker"><?php echo esc_html__( 'Tickets', 'taka-platform' ); ?></p>
	<h2><?php echo esc_html__( 'Konz Pretix-Shops', 'taka-platform' ); ?></h2>
	<div class="taka-tabs" data-taka-tabs>
		<div class="taka-tab-buttons">
			<?php foreach ( $seminars as $index => $seminar ) : ?>
				<button class="<?php echo 0 === $index ? 'is-active' : ''; ?>" type="button" data-tab="<?php echo esc_attr( $seminar['slug'] ?? $seminar['id'] ?? $index ); ?>"><?php echo esc_html( $seminar['title'] ?? '' ); ?></button>
			<?php endforeach; ?>
		</div>
		<?php foreach ( $seminars as $index => $seminar ) : ?>
			<?php
			$pretix_event_url = Taka_Tour_Data::pretix_event_url( $seminar );
			$time_display     = implode( '–', array_filter( array( $seminar['time_start'] ?? '', $seminar['time_end'] ?? '' ) ) );
			?>
			<div class="taka-tab-panel <?php echo 0 === $index ? 'is-active' : ''; ?>" data-panel="<?php echo esc_attr( $seminar['slug'] ?? $seminar['id'] ?? $index ); ?>">
				<div class="taka-ticket-layout">
					<div class="taka-ticket-summary">
						<?php if ( ! empty( $seminar['ticket_overview_image'] ) ) : ?>
							<figure class="taka-ticket-summary__image">
								<img src="<?php echo esc_url( $seminar['ticket_overview_image'] ); ?>" alt="<?php echo esc_attr( $seminar['ticket_overview_image_alt'] ?? taka_tour_translate( 'event.event_photo', 'Event photo' ) ); ?>" loading="lazy">
							</figure>
						<?php endif; ?>
						<div class="taka-ticket-summary__content">
							<h3><?php echo esc_html( $seminar['title'] ?? '' ); ?></h3>
							<div class="taka-ticket-summary__details">
								<?php foreach ( array( $seminar['date'] ?? '', $time_display, $seminar['venue_name'] ?? '' ) as $summary_value ) : ?>
									<?php if ( '' !== trim( (string) $summary_value ) ) : ?>
										<span><?php echo esc_html( $summary_value ); ?></span>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
					<div class="taka-ticket-layout__booking">
						<?php echo taka_tour_render_template( 'partials/ticket-widget.php', array( 'event' => $pretix_event_url, 'label' => $seminar['title'] ?? '', 'seminar' => $seminar ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo taka_tour_render_template( 'partials/booking-information.php', array( 'booking' => $seminar['booking_information'] ?? array() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</section>

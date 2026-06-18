<?php
/**
 * Tabbed ticket booking section.
 */

defined( 'ABSPATH' ) || exit;
$ticket_settings = TAKA_Platform_Data::get_ticket_section_settings();
?>
<section class="taka-section taka-tickets" id="tickets">
	<?php if ( '' !== trim( (string) ( $ticket_settings['kicker'] ?? '' ) ) ) : ?>
		<p class="taka-kicker"><?php echo esc_html( $ticket_settings['kicker'] ); ?></p>
	<?php endif; ?>
	<h2><?php echo esc_html( $ticket_settings['heading'] ?? taka_tour_translate( 'tickets.heading', 'Book your seminar' ) ); ?></h2>
	<?php if ( '' !== trim( (string) ( $ticket_settings['intro'] ?? '' ) ) ) : ?>
		<p class="taka-ticket-section-intro"><?php echo esc_html( $ticket_settings['intro'] ); ?></p>
	<?php endif; ?>
	<div class="taka-tabs taka-ticket-tabs" data-taka-tabs>
		<div class="taka-tab-buttons taka-ticket-tabs__buttons" role="tablist" aria-label="<?php echo esc_attr( taka_tour_translate( 'tickets.select_event', 'Select event' ) ); ?>">
			<?php foreach ( $seminars as $index => $seminar ) : ?>
				<?php $panel_key = $seminar['slug'] ?? $seminar['id'] ?? $index; ?>
				<button class="<?php echo 0 === $index ? 'is-active' : ''; ?>" type="button" role="tab" data-tab="<?php echo esc_attr( $panel_key ); ?>" aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"><?php echo esc_html( $seminar['ticket_tab_label'] ?? $seminar['title'] ?? $seminar['city'] ?? '' ); ?></button>
			<?php endforeach; ?>
		</div>
		<?php foreach ( $seminars as $index => $seminar ) : ?>
			<?php
			$panel_key        = $seminar['slug'] ?? $seminar['id'] ?? $index;
			$pretix_event_url = Taka_Tour_Data::pretix_event_url( $seminar );
			$time_display     = implode( '–', array_filter( array( $seminar['time_start'] ?? '', $seminar['time_end'] ?? '' ) ) );
			$drawers          = is_array( $seminar['info_drawers'] ?? null ) ? $seminar['info_drawers'] : array();
			$event_key        = sanitize_html_class( (string) $panel_key );
			$organizer        = is_array( $seminar['organizer_full'] ?? null ) ? $seminar['organizer_full'] : array();
			$address          = $seminar['address'] ?? '';
			$maps_url         = '' !== trim( (string) $address ) ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $address ) : '';
			$meta_items       = array(
				array( 'label' => taka_tour_translate( 'event.date', 'Date' ), 'value' => $seminar['date'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.time', 'Time' ), 'value' => $time_display ),
				array( 'label' => taka_tour_translate( 'event.doors_open', 'Doors open' ), 'value' => $seminar['doors_open'] ?? '' ),
				array( 'label' => taka_tour_translate( 'seminar.format_label', 'Format' ), 'value' => $seminar['format'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.audience', 'Audience' ), 'value' => $seminar['audience'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.level', 'Level' ), 'value' => $seminar['level'] ?? '' ),
			);
			?>
			<div class="taka-tab-panel <?php echo 0 === $index ? 'is-active' : ''; ?>" data-panel="<?php echo esc_attr( $panel_key ); ?>" role="tabpanel"<?php echo 0 === $index ? '' : ' hidden'; ?>>
				<div class="taka-ticket-booking-panel">
					<div class="taka-ticket-event-panel">
						<div class="taka-ticket-event-panel__header">
							<div>
								<h3><?php echo esc_html( $seminar['title'] ?? '' ); ?></h3>
								<?php if ( ! empty( $seminar['subtitle'] ) ) : ?><p class="taka-ticket-event-panel__subtitle"><?php echo esc_html( $seminar['subtitle'] ); ?></p><?php endif; ?>
							</div>
						</div>
						<div class="taka-ticket-event-panel__body <?php echo empty( $seminar['ticket_overview_image'] ) ? 'taka-ticket-event-panel__body--no-image' : ''; ?>">
							<?php if ( ! empty( $seminar['ticket_overview_image'] ) ) : ?>
								<figure class="taka-ticket-event-panel__image"><img src="<?php echo esc_url( $seminar['ticket_overview_image'] ); ?>" alt="<?php echo esc_attr( $seminar['ticket_overview_image_alt'] ?? taka_tour_translate( 'event.event_photo', 'Event photo' ) ); ?>" loading="lazy"></figure>
							<?php endif; ?>
							<dl class="taka-ticket-meta-list">
								<?php foreach ( $meta_items as $meta_item ) : ?>
									<?php if ( '' !== trim( (string) ( $meta_item['value'] ?? '' ) ) ) : ?>
										<div class="taka-ticket-meta-row"><dt><?php echo esc_html( $meta_item['label'] ); ?></dt><dd><?php echo esc_html( $meta_item['value'] ); ?></dd></div>
									<?php endif; ?>
								<?php endforeach; ?>
								<?php if ( ! empty( $seminar['venue_name'] ) ) : ?>
									<div class="taka-ticket-meta-row"><dt><?php echo esc_html( taka_tour_translate( 'event.venue', 'Venue' ) ); ?></dt><dd><?php echo esc_html( $seminar['venue_name'] ); ?><?php if ( ! empty( $seminar['city'] ) || ! empty( $seminar['country'] ) ) : ?><span><?php echo esc_html( trim( ( $seminar['city'] ?? '' ) . ', ' . ( $seminar['country'] ?? '' ), ', ' ) ); ?></span><?php endif; ?></dd></div>
								<?php endif; ?>
								<?php if ( ! empty( $organizer['name'] ) ) : ?>
									<div class="taka-ticket-meta-row taka-ticket-meta-row--organizer"><dt><?php echo esc_html( taka_tour_translate( 'event.organizer', 'Organizer' ) ); ?></dt><dd><?php if ( ! empty( $organizer['logo'] ) ) : ?><img src="<?php echo esc_url( $organizer['logo'] ); ?>" alt="<?php echo esc_attr( $organizer['name'] ); ?>" loading="lazy"><?php endif; ?><span><?php echo esc_html( $organizer['name'] ); ?></span></dd></div>
								<?php endif; ?>
							</dl>
						</div>
						<?php if ( '' !== $address || ! empty( $organizer['website'] ) ) : ?>
							<div class="taka-ticket-quick-links">
								<?php if ( '' !== $maps_url ) : ?><a href="<?php echo esc_url( $maps_url ); ?>" rel="noopener noreferrer"><?php echo esc_html( taka_tour_translate( 'event.google_maps', 'Open in Google Maps' ) ); ?></a><?php endif; ?>
								<?php if ( ! empty( $organizer['website'] ) ) : ?><a href="<?php echo esc_url( $organizer['website'] ); ?>" rel="noopener noreferrer"><?php echo esc_html( taka_tour_translate( 'event.website', 'Website' ) ); ?></a><?php endif; ?>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $drawers ) ) : ?>
							<div class="taka-ticket-info-actions" aria-label="<?php echo esc_attr__( 'Ticket information', 'taka-platform' ); ?>">
								<?php foreach ( $drawers as $drawer_key => $drawer ) : ?>
									<?php $drawer_id = 'taka-info-modal-' . $event_key . '-' . sanitize_html_class( (string) $drawer_key ); ?>
									<button type="button" class="taka-ticket-info-button" data-taka-info-modal-open="<?php echo esc_attr( $drawer_id ); ?>"><?php echo esc_html( $drawer['label'] ?? $drawer['title'] ?? '' ); ?></button>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
					<div class="taka-ticket-booking-panel__aside">
						<?php echo taka_tour_render_template( 'partials/ticket-widget.php', array( 'event' => $pretix_event_url, 'label' => $seminar['title'] ?? '', 'seminar' => $seminar, 'show_actions' => false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo taka_tour_render_template( 'partials/booking-information.php', array( 'booking' => $seminar['booking_information'] ?? array() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</div>
				<?php foreach ( $drawers as $drawer_key => $drawer ) : ?>
					<?php echo taka_tour_render_template( 'partials/info-drawer.php', array( 'drawer_id' => 'taka-info-modal-' . $event_key . '-' . sanitize_html_class( (string) $drawer_key ), 'title' => $drawer['title'] ?? '', 'rows' => $drawer['rows'] ?? array(), 'cards' => $drawer['cards'] ?? array(), 'cards_title' => $drawer['cards_title'] ?? '', 'image' => $drawer['image'] ?? '', 'type' => $drawer['type'] ?? $drawer_key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</div>
</section>

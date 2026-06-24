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
				<?php $panel_key = TAKA_Platform_Data::event_panel_key( $seminar ) ?: (string) $index; ?>
				<button class="<?php echo 0 === $index ? 'is-active' : ''; ?>" type="button" role="tab" data-tab="<?php echo esc_attr( $panel_key ); ?>" aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"><?php echo esc_html( $seminar['ticket_tab_label'] ?? $seminar['title'] ?? $seminar['city'] ?? '' ); ?></button>
			<?php endforeach; ?>
		</div>
		<?php foreach ( $seminars as $index => $seminar ) : ?>
			<?php
			$panel_key        = TAKA_Platform_Data::event_panel_key( $seminar ) ?: (string) $index;
			$share_event      = array_merge( $seminar, array( 'slug' => $panel_key ) );
			$share_url        = TAKA_Platform_Data::event_share_url( $share_event, taka_tour_current_language() );
			$pretix_event_url = TAKA_Platform_Data::pretix_event_url( $seminar );
			$time_display     = implode( '–', array_filter( array( $seminar['time_start'] ?? '', $seminar['time_end'] ?? '' ) ) );
			$drawers          = is_array( $seminar['info_drawers'] ?? null ) ? $seminar['info_drawers'] : array();
			$event_key        = sanitize_html_class( (string) $panel_key );
			$organizer        = is_array( $seminar['organizer_full'] ?? null ) ? $seminar['organizer_full'] : array();
			$organizer_relationships = is_array( $seminar['organizer_relationships'] ?? null ) ? $seminar['organizer_relationships'] : array();
			$ticket_organizers = is_array( $seminar['ticket_organizers'] ?? null ) ? $seminar['ticket_organizers'] : $organizer_relationships;
			$address          = $seminar['address'] ?? '';
			$organizer_drawer = isset( $drawers['organizer'] ) ? 'taka-info-modal-' . $event_key . '-organizer' : '';
			$venue_drawer     = isset( $drawers['venue'] ) ? 'taka-info-modal-' . $event_key . '-venue' : '';
			$meta_items       = array(
				array( 'label' => taka_tour_translate( 'event.date', 'Date' ), 'value' => $seminar['date'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.time', 'Time' ), 'value' => empty( $seminar['program_groups'] ) ? $time_display : '' ),
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
							<div class="taka-ticket-event-panel__heading">
								<h3><?php echo esc_html( $seminar['title'] ?? '' ); ?></h3>
								<?php if ( ! empty( $seminar['subtitle'] ) ) : ?><p class="taka-ticket-event-panel__subtitle"><?php echo esc_html( $seminar['subtitle'] ); ?></p><?php endif; ?>
							</div>
							<?php if ( '' !== $share_url ) : ?>
								<?php $share_panel_id = 'taka-share-panel-' . $event_key; $share_input_id = 'taka-share-url-' . $event_key; ?>
								<div class="taka-ticket-share-actions" data-taka-share-root aria-label="<?php echo esc_attr( taka_tour_translate( 'event.share_actions', 'Event teilen' ) ); ?>">
									<button type="button" class="taka-ticket-share-button" data-taka-share-event data-share-url="<?php echo esc_url( $share_url ); ?>" data-share-title="<?php echo esc_attr( $seminar['title'] ?? '' ); ?>" data-share-label="<?php echo esc_attr( taka_tour_translate( 'event.share_event', 'Teilen' ) ); ?>" data-share-copied-label="<?php echo esc_attr( taka_tour_translate( 'event.share_copied', 'Link kopiert' ) ); ?>" data-share-prompt-label="<?php echo esc_attr( taka_tour_translate( 'event.copy_event_link_prompt', 'Event-Link kopieren' ) ); ?>" aria-expanded="false" aria-controls="<?php echo esc_attr( $share_panel_id ); ?>"><?php echo esc_html( taka_tour_translate( 'event.share_event', 'Teilen' ) ); ?></button>
									<div id="<?php echo esc_attr( $share_panel_id ); ?>" class="taka-ticket-share-panel" data-taka-share-panel hidden>
										<div class="taka-ticket-share-qr" data-taka-share-qr data-qr-label="<?php echo esc_attr( taka_tour_translate( 'event.qr_code', 'QR-Code' ) ); ?>" aria-hidden="true"></div>
										<div class="taka-ticket-share-url-row">
											<input id="<?php echo esc_attr( $share_input_id ); ?>" type="text" readonly value="<?php echo esc_attr( $share_url ); ?>" aria-label="<?php echo esc_attr( taka_tour_translate( 'event.event_link', 'Event-Link' ) ); ?>">
											<button type="button" class="taka-ticket-share-copy" data-taka-copy-share-url><?php echo esc_html( taka_tour_translate( 'event.copy_link', 'Link kopieren' ) ); ?></button>
										</div>
									</div>
								</div>
							<?php endif; ?>
						</div>
						<div class="taka-ticket-event-panel__body">
							<figure class="taka-ticket-event-panel__image <?php echo empty( $seminar['ticket_overview_image'] ) ? 'taka-ticket-event-panel__image--placeholder' : ''; ?>">
								<?php if ( ! empty( $seminar['ticket_overview_image'] ) ) : ?>
									<img src="<?php echo esc_url( $seminar['ticket_overview_image'] ); ?>" alt="<?php echo esc_attr( $seminar['ticket_overview_image_alt'] ?? taka_tour_translate( 'event.event_photo', 'Event photo' ) ); ?>" loading="lazy">
								<?php else : ?>
									<div class="taka-ticket-event-panel__image-placeholder" role="img" aria-label="<?php echo esc_attr( taka_tour_translate( 'event.seminar_photo', 'Seminar photo' ) ); ?>"><span><?php echo esc_html( taka_tour_translate( 'event.photo_coming_soon', 'Photo coming soon' ) ); ?></span></div>
								<?php endif; ?>
							</figure>
							<dl class="taka-ticket-meta-list">
								<?php foreach ( $meta_items as $meta_item ) : ?>
									<?php if ( '' !== trim( (string) ( $meta_item['value'] ?? '' ) ) ) : ?>
										<div class="taka-ticket-meta-row"><dt><?php echo esc_html( $meta_item['label'] ); ?></dt><dd><?php echo esc_html( $meta_item['value'] ); ?></dd></div>
									<?php endif; ?>
								<?php endforeach; ?>
								<?php if ( ! empty( $seminar['venue_name'] ) ) : ?>
									<div class="taka-ticket-meta-row taka-ticket-meta-row--wide taka-ticket-meta-row--venue"><dt><?php echo esc_html( taka_tour_translate( 'event.venue', 'Venue' ) ); ?></dt><dd><?php if ( '' !== $venue_drawer ) : ?><button type="button" class="taka-ticket-meta-link" data-taka-info-modal-open="<?php echo esc_attr( $venue_drawer ); ?>"><?php echo esc_html( $seminar['venue_name'] ); ?><span aria-hidden="true">ⓘ</span></button><?php else : ?><?php echo esc_html( $seminar['venue_name'] ); ?><?php endif; ?><?php if ( ! empty( $seminar['city'] ) || ! empty( $seminar['country'] ) ) : ?><span><?php echo esc_html( trim( ( $seminar['city'] ?? '' ) . ', ' . ( $seminar['country'] ?? '' ), ', ' ) ); ?></span><?php endif; ?></dd></div>
								<?php endif; ?>
								<?php if ( ! empty( $ticket_organizers ) ) : ?>
									<div class="taka-ticket-meta-row taka-ticket-meta-row--wide taka-ticket-meta-row--organizer">
										<dt><?php echo esc_html( taka_tour_translate( 'event.event_organizers', 'Event organizers' ) ); ?></dt>
										<dd><div class="taka-ticket-organizer-list">
											<?php foreach ( $ticket_organizers as $relationship ) : ?>
												<?php $rel_org = is_array( $relationship['organizer'] ?? null ) ? $relationship['organizer'] : array(); $rel_drawer = ! empty( $relationship['drawer_key'] ) ? 'taka-info-modal-' . $event_key . '-' . sanitize_html_class( (string) $relationship['drawer_key'] ) : ''; ?>
												<?php if ( empty( $rel_org['name'] ) ) { continue; } ?>
												<div class="taka-ticket-organizer-list__item">
													<?php if ( ! empty( $rel_org['logo'] ) ) : ?><img src="<?php echo esc_url( $rel_org['logo'] ); ?>" alt="<?php echo esc_attr( $rel_org['name'] ); ?>" loading="lazy"><?php endif; ?>
													<span class="taka-ticket-organizer-list__content"><span class="taka-ticket-organizer-list__role"><?php echo esc_html( $relationship['label'] ?? taka_tour_translate( 'event.organizer', 'Organizer' ) ); ?></span><?php if ( '' !== $rel_drawer && isset( $drawers[ $relationship['drawer_key'] ] ) ) : ?><button type="button" class="taka-ticket-meta-link" data-taka-info-modal-open="<?php echo esc_attr( $rel_drawer ); ?>"><?php echo esc_html( $rel_org['name'] ); ?><span aria-hidden="true">ⓘ</span></button><?php else : ?><span><?php echo esc_html( $rel_org['name'] ); ?></span><?php endif; ?></span>
												</div>
											<?php endforeach; ?>
										</div></dd>
									</div>
									<?php elseif ( ! empty( $organizer['name'] ) ) : ?>
										<div class="taka-ticket-meta-row taka-ticket-meta-row--wide taka-ticket-meta-row--organizer"><dt><?php echo esc_html( taka_tour_translate( 'event.organizer', 'Organizer' ) ); ?></dt><dd><?php if ( ! empty( $organizer['logo'] ) ) : ?><img src="<?php echo esc_url( $organizer['logo'] ); ?>" alt="<?php echo esc_attr( $organizer['name'] ); ?>" loading="lazy"><?php endif; ?><?php if ( '' !== $organizer_drawer ) : ?><button type="button" class="taka-ticket-meta-link" data-taka-info-modal-open="<?php echo esc_attr( $organizer_drawer ); ?>"><?php echo esc_html( $organizer['name'] ); ?><span aria-hidden="true">ⓘ</span></button><?php else : ?><span><?php echo esc_html( $organizer['name'] ); ?></span><?php endif; ?></dd></div>
									<?php endif; ?>
								</dl>
								<?php echo taka_tour_render_template( 'partials/event-videos.php', array( 'seminar' => $seminar, 'videos' => $seminar['promo_videos'] ?? array() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php if ( ! empty( $seminar['program_groups'] ) ) : ?>
									<div class="taka-program-summary" aria-label="<?php echo esc_attr( taka_tour_translate( 'event.schedule', 'Schedule' ) ); ?>">
									<h4><?php echo esc_html( taka_tour_translate( 'event.seminar_plan', 'Seminar plan' ) ); ?></h4>
									<?php foreach ( $seminar['program_groups'] as $program_group ) : ?>
										<div class="taka-program-summary__day">
											<div class="taka-program-summary__date-group">
												<strong class="taka-program-summary__day-label"><?php echo esc_html( $program_group['label'] ?? '' ); ?></strong>
												<?php if ( ! empty( $program_group['date_label'] ) ) : ?><span class="taka-program-summary__date"><?php echo esc_html( $program_group['date_label'] ); ?></span><?php endif; ?>
											</div>
											<div class="taka-program-summary__items">
												<?php foreach ( $program_group['items'] as $program_item ) : ?>
													<div class="taka-program-summary__item"><span class="taka-program-summary__time"><?php echo esc_html( implode( '–', array_filter( array( $program_item['time_start'] ?? '', $program_item['time_end'] ?? '' ) ) ) ); ?></span><span class="taka-program-summary__title"><?php echo esc_html( $program_item['title'] ?? '' ); ?></span></div>
												<?php endforeach; ?>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
						<?php if ( '' !== trim( (string) ( $seminar['description'] ?? '' ) ) ) : ?><section class="taka-ticket-event-description"><h4><?php echo esc_html( taka_tour_translate( 'event.seminar_description', 'Seminar description' ) ); ?></h4><div class="taka-ticket-event-panel__description"><?php echo wp_kses_post( wpautop( $seminar['description'] ) ); ?></div></section><?php endif; ?>
						<?php if ( ! empty( $drawers ) ) : ?>
							<div class="taka-ticket-info-actions" aria-label="<?php echo esc_attr__( 'Ticket information', 'taka-platform' ); ?>">
								<?php foreach ( $drawers as $drawer_key => $drawer ) : ?>
									<?php if ( 'event' === $drawer_key || ( 'organizer' === ( $drawer['type'] ?? '' ) && ! empty( $ticket_organizers ) ) || ( 'organizer' === $drawer_key && '' !== $organizer_drawer && ! empty( $organizer['name'] ) ) || ( 'venue' === $drawer_key && '' !== $venue_drawer && ! empty( $seminar['venue_name'] ) ) ) { continue; } ?>
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

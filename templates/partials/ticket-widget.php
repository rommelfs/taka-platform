<?php
/**
 * Ticket widget with optional information drawers.
 */

defined( 'ABSPATH' ) || exit;

$event_url = ! empty( $event ) ? $event : ( $url ?? '' );
$seminar   = is_array( $seminar ?? null ) ? $seminar : array();
$drawers   = is_array( $seminar['info_drawers'] ?? null ) ? $seminar['info_drawers'] : array();
$show_actions = isset( $show_actions ) ? (bool) $show_actions : true;
$event_key = sanitize_html_class( (string) ( $seminar['slug'] ?? $seminar['id'] ?? md5( $event_url ) ) );
$ticket_card = ! empty( $seminar ) ? TAKA_Platform_Data::ticket_information_card( $seminar ) : array();
$direct_url = empty( $ticket_card ) && ! empty( $seminar ) ? TAKA_Platform_Data::ticket_direct_url( $seminar ) : '';
$native_checkout = ( empty( $ticket_card ) && ! empty( $seminar ) && class_exists( 'TAKA_Ticketing_Module' ) && TAKA_Ticketing_Module::event_uses_native_ticketing( $seminar ) ) ? TAKA_Ticketing_Module::render_booking_widget( $seminar ) : '';
?>
<div class="taka-ticket-widget" data-taka-ticket-widget>
	<?php if ( ! empty( $ticket_card ) ) : ?>
		<div class="taka-ticket-status taka-ticket-status--boxed taka-ticket-status--info taka-ticket-status--<?php echo esc_attr( sanitize_html_class( (string) ( $ticket_card['mode'] ?? 'notice' ) ) ); ?>">
			<span><?php echo esc_html( taka_tour_translate( 'event.ticket_status', 'Ticket status' ) ); ?></span>
			<strong><?php echo esc_html( $ticket_card['title'] ?? '' ); ?></strong>
			<?php if ( '' !== trim( (string) ( $ticket_card['body'] ?? '' ) ) ) : ?>
				<p><?php echo esc_html( $ticket_card['body'] ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $ticket_card['details'] ) && is_array( $ticket_card['details'] ) ) : ?>
				<ul class="taka-ticket-status__details">
					<?php foreach ( $ticket_card['details'] as $detail ) : ?>
						<li><?php echo esc_html( $detail ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( '' !== trim( (string) ( $ticket_card['note'] ?? '' ) ) ) : ?>
				<p class="taka-ticket-status__note"><?php echo esc_html( $ticket_card['note'] ); ?></p>
			<?php endif; ?>
		</div>
	<?php elseif ( '' !== $native_checkout ) : ?>
		<?php echo $native_checkout; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php elseif ( '' !== $event_url ) : ?>
		<?php echo taka_tour_render_template( 'partials/pretix-widget.php', array( 'event' => $event_url, 'label' => $label ?? ( $seminar['title'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<a class="taka-ticket-direct-link" href="<?php echo esc_url( $event_url ); ?>" rel="noopener noreferrer"><?php echo esc_html( taka_tour_translate( 'event.ticketshop_direct', 'Ticketshop direkt öffnen' ) ); ?></a>
	<?php elseif ( '' !== $direct_url ) : ?>
		<a class="taka-ticket-direct-link taka-ticket-direct-link--external" href="<?php echo esc_url( $direct_url ); ?>" rel="noopener noreferrer"><?php echo esc_html( taka_tour_translate( 'event.ticketshop_direct', 'Open ticket shop' ) ); ?></a>
	<?php else : ?>
		<div class="taka-ticket-status taka-ticket-status--boxed">
			<span><?php echo esc_html( taka_tour_translate( 'event.ticket_status', 'Ticket status' ) ); ?></span>
			<strong><?php echo esc_html( $seminar['ticket_status_label'] ?? taka_tour_translate( 'event.ticketshop_soon', 'Ticketshop folgt' ) ); ?></strong>
		</div>
	<?php endif; ?>
	<?php if ( $show_actions && ! empty( $drawers ) ) : ?>
		<div class="taka-ticket-info-actions" aria-label="<?php echo esc_attr__( 'Ticket information', 'taka-platform' ); ?>">
			<?php foreach ( $drawers as $drawer_key => $drawer ) : ?>
				<?php $drawer_id = 'taka-info-modal-' . $event_key . '-' . sanitize_html_class( (string) $drawer_key ); ?>
				<button type="button" class="taka-ticket-info-button" data-taka-info-modal-open="<?php echo esc_attr( $drawer_id ); ?>">
					<?php echo esc_html( $drawer['label'] ?? $drawer['title'] ?? '' ); ?>
				</button>
			<?php endforeach; ?>
		</div>
		<?php foreach ( $drawers as $drawer_key => $drawer ) : ?>
			<?php
			$drawer_id = 'taka-info-modal-' . $event_key . '-' . sanitize_html_class( (string) $drawer_key );
			echo taka_tour_render_template(
				'partials/info-drawer.php',
				array(
					'drawer_id' => $drawer_id,
					'title'     => $drawer['title'] ?? '',
					'rows'        => $drawer['rows'] ?? array(),
					'cards'       => $drawer['cards'] ?? array(),
					'cards_title' => $drawer['cards_title'] ?? '',
					'image'       => $drawer['image'] ?? '',
					'type'        => $drawer['type'] ?? $drawer_key,
				)
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		<?php endforeach; ?>
	<?php endif; ?>
</div>

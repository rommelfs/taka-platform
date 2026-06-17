<?php
/**
 * Ticket widget with optional information drawers.
 */

defined( 'ABSPATH' ) || exit;

$event_url = ! empty( $event ) ? $event : ( $url ?? '' );
$seminar   = is_array( $seminar ?? null ) ? $seminar : array();
$drawers   = is_array( $seminar['info_drawers'] ?? null ) ? $seminar['info_drawers'] : array();
$event_key = sanitize_html_class( (string) ( $seminar['slug'] ?? $seminar['id'] ?? md5( $event_url ) ) );
?>
<div class="taka-ticket-widget" data-taka-ticket-widget>
	<?php if ( '' !== $event_url ) : ?>
		<?php echo taka_tour_render_template( 'partials/pretix-widget.php', array( 'event' => $event_url, 'label' => $label ?? ( $seminar['title'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<a class="taka-ticket-direct-link" href="<?php echo esc_url( $event_url ); ?>" rel="noopener noreferrer"><?php echo esc_html( taka_tour_translate( 'event.ticketshop_direct', 'Ticketshop direkt öffnen' ) ); ?></a>
	<?php else : ?>
		<p class="taka-ticket-status"><?php echo esc_html( $seminar['ticket_status_label'] ?? taka_tour_translate( 'event.ticketshop_soon', 'Ticketshop folgt' ) ); ?></p>
	<?php endif; ?>
	<?php if ( ! empty( $drawers ) ) : ?>
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

<?php
/** Shared schedule with original event times and an optional viewer-timezone conversion. */
defined( 'ABSPATH' ) || exit;
$zone = TAKA_Platform_Event_Time::timezone( $seminar );
?>
<div class="taka-program-summary" data-taka-timezone-schedule data-timezone="<?php echo esc_attr( $zone ); ?>" data-locale="<?php echo esc_attr( taka_tour_current_language() ); ?>">
	<h4><?php echo esc_html( taka_tour_translate( 'event.seminar_plan', 'Seminar plan' ) ); ?></h4>
	<p class="taka-timezone-original"><?php echo esc_html( taka_tour_translate( 'event.timezone', 'Event timezone' ) . ': ' . $zone ); ?></p>
	<label class="taka-timezone-picker" hidden><?php echo esc_html( taka_tour_translate( 'event.view_timezone', 'Also show times in' ) ); ?>
		<select data-taka-view-timezone><option value=""><?php echo esc_html( taka_tour_translate( 'event.original_timezone', 'Event timezone only' ) ); ?></option></select>
	</label>
	<?php foreach ( $seminar['program_groups'] as $program_group ) : ?>
		<div class="taka-program-summary__day">
			<div class="taka-program-summary__date-group">
				<strong class="taka-program-summary__day-label"><?php echo esc_html( $program_group['label'] ?? '' ); ?></strong>
				<?php if ( ! empty( $program_group['date_label'] ) ) : ?><span class="taka-program-summary__date"><?php echo esc_html( $program_group['date_label'] ); ?></span><?php endif; ?>
			</div>
			<div class="taka-program-summary__items">
				<?php foreach ( $program_group['items'] as $program_item ) : ?>
					<?php list( $start, $end ) = TAKA_Platform_Event_Time::range( $seminar, $program_item ); ?>
					<div class="taka-program-summary__item">
						<span class="taka-program-summary__time">
							<?php echo esc_html( TAKA_Platform_Event_Time::range_label( $seminar, $program_item ) ); ?>
							<?php if ( $start || $end ) : ?><span class="taka-timezone-converted" data-taka-converted-time data-start="<?php echo esc_attr( $start ? $start->format( 'c' ) : '' ); ?>" data-end="<?php echo esc_attr( $end ? $end->format( 'c' ) : '' ); ?>" aria-live="polite" hidden></span><?php endif; ?>
						</span>
						<span class="taka-program-summary__title"><?php echo esc_html( $program_item['title'] ?? '' ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>

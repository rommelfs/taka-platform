<?php
/**
 * Configurable pre-booking information block for ticket cards.
 */

defined( 'ABSPATH' ) || exit;

$booking = is_array( $booking ?? null ) ? $booking : array();
if ( '1' !== (string) ( $booking['enabled'] ?? '1' ) ) {
	return;
}

$sections = is_array( $booking['sections'] ?? null ) ? $booking['sections'] : array();
$email    = sanitize_email( $booking['contact_email'] ?? '' );
if ( empty( $sections ) && '' === $email ) {
	return;
}
$title = (string) ( $booking['title'] ?? taka_tour_translate( 'booking.before_you_book', 'Before you book' ) );
?>
<aside class="taka-booking-info" aria-label="<?php echo esc_attr( $title ); ?>">
	<h4 class="taka-booking-info__title"><?php echo esc_html( $title ); ?></h4>
	<div class="taka-booking-info__sections">
		<?php foreach ( $sections as $section ) : ?>
			<?php
			$section_title = (string) ( $section['title'] ?? '' );
			$text          = (string) ( $section['text'] ?? '' );
			$list          = is_array( $section['list'] ?? null ) ? $section['list'] : array();
			if ( '' === trim( $text ) ) {
				continue;
			}
			?>
			<section class="taka-booking-info__section taka-booking-info__section--<?php echo esc_attr( sanitize_html_class( (string) ( $section['key'] ?? 'default' ) ) ); ?>">
				<?php if ( '' !== trim( $section_title ) ) : ?>
					<h5><?php echo esc_html( $section_title ); ?></h5>
				<?php endif; ?>
				<?php if ( ! empty( $list ) && 'cancellation_policy' === ( $section['key'] ?? '' ) ) : ?>
					<ul>
						<?php foreach ( $list as $item ) : ?>
							<li><?php echo esc_html( $item ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p><?php echo esc_html( $text ); ?></p>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	</div>
	<?php if ( '' !== $email ) : ?>
		<p class="taka-booking-info__contact"><span><?php echo esc_html( taka_tour_translate( 'booking.contact_before_booking', 'Contact us before booking' ) ); ?></span> <a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></p>
	<?php endif; ?>
</aside>

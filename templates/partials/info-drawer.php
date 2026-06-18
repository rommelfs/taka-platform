<?php
/**
 * Accessible ticket information modal.
 */

defined( 'ABSPATH' ) || exit;

$drawer_id = (string) ( $drawer_id ?? '' );
$title     = (string) ( $title ?? '' );
$rows        = is_array( $rows ?? null ) ? $rows : array();
$cards       = is_array( $cards ?? null ) ? $cards : array();
$cards_title = (string) ( $cards_title ?? taka_tour_translate( 'event.co_organizers', 'Co-organizers' ) );
$image       = (string) ( $image ?? '' );
$type        = sanitize_html_class( (string) ( $type ?? 'default' ) );

if ( '' === $drawer_id || ( empty( $rows ) && empty( $cards ) ) ) {
	return;
}
?>
<div class="taka-info-modal taka-info-modal--<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $drawer_id ); ?>" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $drawer_id ); ?>-title" hidden>
	<div class="taka-info-modal__overlay" data-taka-info-modal-close></div>
	<section class="taka-info-modal__panel" tabindex="-1">
		<header class="taka-info-modal__header">
			<h3 class="taka-info-modal__title" id="<?php echo esc_attr( $drawer_id ); ?>-title"><?php echo esc_html( $title ); ?></h3>
			<button class="taka-info-modal__close" type="button" data-taka-info-modal-close aria-label="<?php echo esc_attr__( 'Close', 'taka-platform' ); ?>">×</button>
		</header>
		<div class="taka-info-modal__body">
			<?php if ( '' !== $image ) : ?>
				<div class="taka-info-modal__media"><img class="taka-info-modal__image" src="<?php echo esc_url( $image ); ?>" alt=""></div>
			<?php endif; ?>
			<dl class="taka-info-modal__list">
				<?php foreach ( $rows as $row ) : ?>
					<?php
					$label = (string) ( $row['label'] ?? '' );
					$value = (string) ( $row['value'] ?? '' );
					$url   = (string) ( $row['url'] ?? '' );
					if ( '' === trim( $label ) || '' === trim( $value ) ) {
						continue;
					}
					?>
					<div class="taka-info-modal__row">
						<dt><?php echo esc_html( $label ); ?></dt>
						<dd>
							<?php if ( '' !== $url ) : ?>
								<a href="<?php echo esc_url( $url ); ?>" rel="noopener noreferrer"><?php echo esc_html( $value ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $value ); ?>
							<?php endif; ?>
						</dd>
					</div>
				<?php endforeach; ?>
			</dl>
			<?php if ( ! empty( $cards ) ) : ?>
				<section class="taka-info-modal__cards" aria-label="<?php echo esc_attr( $cards_title ); ?>">
					<h4 class="taka-info-modal__section-title"><?php echo esc_html( $cards_title ); ?></h4>
					<div class="taka-info-modal__card-grid">
						<?php foreach ( $cards as $card ) : ?>
							<?php
							$card_name = (string) ( $card['name'] ?? '' );
							if ( '' === trim( $card_name ) ) {
								continue;
							}
							$card_image = (string) ( $card['image'] ?? '' );
							$social_links = is_array( $card['social_links'] ?? null ) ? $card['social_links'] : array();
							?>
							<article class="taka-info-modal__card<?php echo '' === $card_image ? ' taka-info-modal__card--no-logo' : ''; ?>">
								<?php if ( '' !== $card_image ) : ?>
									<div class="taka-info-modal__card-logo"><img src="<?php echo esc_url( $card_image ); ?>" alt="<?php echo esc_attr( $card_name ); ?>" loading="lazy"></div>
								<?php endif; ?>
								<div class="taka-info-modal__card-body">
									<h5><?php echo esc_html( $card_name ); ?></h5>
									<?php if ( ! empty( $card['legal_name'] ) ) : ?><p class="taka-info-modal__card-legal"><?php echo esc_html( $card['legal_name'] ); ?></p><?php endif; ?>
									<?php if ( ! empty( $card['description'] ) ) : ?><p><?php echo esc_html( $card['description'] ); ?></p><?php endif; ?>
									<ul class="taka-info-modal__card-links">
										<?php if ( ! empty( $card['website'] ) ) : ?><li><a href="<?php echo esc_url( $card['website'] ); ?>" rel="noopener noreferrer"><?php echo esc_html( $card['website_label'] ?? taka_tour_translate( 'event.website', 'Website' ) ); ?></a></li><?php endif; ?>
										<?php if ( ! empty( $card['email'] ) ) : ?><li><a href="mailto:<?php echo esc_attr( $card['email'] ); ?>"><?php echo esc_html( $card['email'] ); ?></a></li><?php endif; ?>
										<?php foreach ( array( 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'youtube' => 'YouTube' ) as $social_key => $social_label ) : ?>
											<?php if ( ! empty( $social_links[ $social_key ] ) ) : ?><li><a href="<?php echo esc_url( $social_links[ $social_key ] ); ?>" rel="noopener noreferrer"><?php echo esc_html( $social_label ); ?></a></li><?php endif; ?>
										<?php endforeach; ?>
									</ul>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>
		</div>
	</section>
</div>

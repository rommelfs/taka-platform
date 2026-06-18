<?php
/**
 * Editable dynamic homepage content section partial.
 */

defined( 'ABSPATH' ) || exit;

$section = is_array( $section ?? null ) ? $section : array();
$key     = sanitize_html_class( $section['key'] ?? 'section' );
$layout  = sanitize_html_class( $section['layout'] ?? 'text_only' );
$bg      = sanitize_html_class( $section['background_style'] ?? 'plain' );
$custom  = sanitize_html_class( $section['css_class'] ?? '' );
$image   = (string) ( $section['image'] ?? '' );
$second  = (string) ( $section['secondary_image'] ?? '' );
$gallery = is_array( $section['gallery_images'] ?? null ) ? $section['gallery_images'] : array();
$visible = '0' !== (string) ( $section['visible'] ?? '1' );
if ( ! $visible ) {
	return;
}
$body = (string) ( $section['body'] ?? ( $section['text'] ?? '' ) );
$has_content = '' !== trim( (string) ( $section['kicker'] ?? '' ) . ( $section['title'] ?? '' ) . ( $section['subtitle'] ?? '' ) . $body . ( $section['button_url'] ?? '' ) . $image . $second . implode( '', $gallery ) );
if ( ! $has_content ) {
	return;
}
$fit = in_array( (string) ( $section['image_fit'] ?? 'contain' ), array( 'cover', 'contain', 'auto' ), true ) ? (string) $section['image_fit'] : 'contain';
$position = in_array( (string) ( $section['image_position'] ?? 'center center' ), array( 'center center', 'center top', 'center bottom', 'left center', 'right center' ), true ) ? (string) $section['image_position'] : 'center center';
$style_parts = array( '--taka-section-image-fit:' . $fit, '--taka-section-image-position:' . $position );
if ( 'full_background' === $layout && '' !== $image ) {
	$style_parts[] = '--taka-section-bg:url(\'' . esc_url( $image ) . '\')';
}
$style = ' style="' . esc_attr( implode( ';', $style_parts ) ) . '"';
?>
<?php if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) : ?>
	<!-- TAKA section image source: key=<?php echo esc_html( $key ); ?> attachment_id=<?php echo esc_html( (string) absint( $section['image_id'] ?? 0 ) ); ?> url=<?php echo esc_url( $image ); ?> fit=<?php echo esc_html( $fit ); ?> position=<?php echo esc_html( $position ); ?> -->
<?php endif; ?>
<section class="taka-section taka-content-section taka-content-section--<?php echo esc_attr( $key ); ?> taka-content-section--<?php echo esc_attr( $layout ); ?> taka-content-section--bg-<?php echo esc_attr( $bg ); ?> <?php echo esc_attr( $custom ); ?>"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="taka-content-section__inner">
		<div class="taka-content-section__text">
			<?php if ( '' !== trim( (string) ( $section['kicker'] ?? '' ) ) ) : ?>
				<p class="taka-kicker"><?php echo esc_html( $section['kicker'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== trim( (string) ( $section['title'] ?? '' ) ) ) : ?>
				<h2><?php echo esc_html( $section['title'] ); ?></h2>
			<?php endif; ?>
			<?php if ( '' !== trim( (string) ( $section['subtitle'] ?? '' ) ) ) : ?>
				<p class="taka-content-section__subtitle"><?php echo esc_html( $section['subtitle'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== trim( $body ) ) : ?>
				<div class="taka-content-section__body"><?php echo wp_kses_post( wpautop( $body ) ); ?></div>
			<?php endif; ?>
			<?php if ( '' !== trim( (string) ( $section['button_url'] ?? '' ) ) ) : ?>
				<a class="taka-text-link" href="<?php echo esc_url( $section['button_url'] ); ?>" rel="noopener"><?php echo esc_html( $section['button_label'] ?: $section['button_url'] ); ?></a>
			<?php endif; ?>
		</div>
		<?php if ( 'gallery_grid' === $layout && ! empty( $gallery ) ) : ?>
			<div class="taka-content-section__gallery">
				<?php foreach ( $gallery as $gallery_image ) : ?>
					<figure><img src="<?php echo esc_url( $gallery_image ); ?>" alt="<?php echo esc_attr( $section['title'] ?? '' ); ?>" loading="lazy"></figure>
				<?php endforeach; ?>
			</div>
		<?php elseif ( '' !== $image && ! in_array( $layout, array( 'text_only', 'full_background' ), true ) ) : ?>
			<figure class="taka-content-section__media"><img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $section['title'] ?? '' ); ?>" loading="lazy"></figure>
		<?php endif; ?>
		<?php if ( '' !== $second && in_array( $layout, array( 'two_column', 'feature_card' ), true ) ) : ?>
			<figure class="taka-content-section__media taka-content-section__media--secondary"><img src="<?php echo esc_url( $second ); ?>" alt="<?php echo esc_attr( $section['title'] ?? '' ); ?>" loading="lazy"></figure>
		<?php endif; ?>
	</div>
</section>

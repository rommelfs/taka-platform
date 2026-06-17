<?php
/**
 * Editable content section partial.
 */

defined( 'ABSPATH' ) || exit;

$key     = sanitize_html_class( $section['key'] ?? 'section' );
$layout  = sanitize_html_class( $section['layout'] ?? 'text_only' );
$image   = (string) ( $section['image'] ?? '' );
$visible = '0' !== (string) ( $section['visible'] ?? '1' );
if ( ! $visible ) {
	return;
}
$has_content = '' !== trim( (string) ( $section['kicker'] ?? '' ) . ( $section['title'] ?? '' ) . ( $section['text'] ?? '' ) . ( $section['link_url'] ?? '' ) . $image );
if ( ! $has_content ) {
	return;
}
?>
<section class="taka-section taka-content-section taka-content-section--<?php echo esc_attr( $key ); ?> taka-content-section--<?php echo esc_attr( $layout ); ?>" <?php echo 'background' === $layout && '' !== $image ? 'style="--taka-section-bg:url(\'' . esc_url( $image ) . '\');"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="taka-content-section__inner">
		<div class="taka-content-section__text">
			<?php if ( '' !== trim( (string) ( $section['kicker'] ?? '' ) ) ) : ?>
				<p class="taka-kicker"><?php echo esc_html( $section['kicker'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== trim( (string) ( $section['title'] ?? '' ) ) ) : ?>
				<h2><?php echo esc_html( $section['title'] ); ?></h2>
			<?php endif; ?>
			<?php if ( '' !== trim( (string) ( $section['text'] ?? '' ) ) ) : ?>
				<p><?php echo esc_html( $section['text'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== trim( (string) ( $section['link_url'] ?? '' ) ) ) : ?>
				<a class="taka-text-link" href="<?php echo esc_url( $section['link_url'] ); ?>" rel="noopener"><?php echo esc_html( $section['link_label'] ?: $section['link_url'] ); ?></a>
			<?php endif; ?>
		</div>
		<?php if ( '' !== $image && 'text_only' !== $layout && 'background' !== $layout ) : ?>
			<figure class="taka-content-section__media"><img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $section['title'] ?? '' ); ?>"></figure>
		<?php endif; ?>
	</div>
</section>

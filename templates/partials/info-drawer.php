<?php
/**
 * Accessible ticket information drawer.
 */

defined( 'ABSPATH' ) || exit;

$drawer_id = (string) ( $drawer_id ?? '' );
$title     = (string) ( $title ?? '' );
$rows      = is_array( $rows ?? null ) ? $rows : array();
$image     = (string) ( $image ?? '' );

if ( '' === $drawer_id || empty( $rows ) ) {
	return;
}
?>
<div class="taka-info-drawer" id="<?php echo esc_attr( $drawer_id ); ?>" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $drawer_id ); ?>-title" hidden>
	<div class="taka-info-drawer__overlay" data-taka-info-drawer-close></div>
	<aside class="taka-info-drawer__panel" tabindex="-1">
		<button type="button" class="taka-info-drawer__close" data-taka-info-drawer-close aria-label="<?php echo esc_attr__( 'Close information drawer', 'taka-platform' ); ?>">×</button>
		<?php if ( '' !== $image ) : ?>
			<img class="taka-info-drawer__image" src="<?php echo esc_url( $image ); ?>" alt="">
		<?php endif; ?>
		<h3 class="taka-info-drawer__title" id="<?php echo esc_attr( $drawer_id ); ?>-title"><?php echo esc_html( $title ); ?></h3>
		<dl class="taka-info-drawer__list">
			<?php foreach ( $rows as $row ) : ?>
				<?php
				$label = (string) ( $row['label'] ?? '' );
				$value = (string) ( $row['value'] ?? '' );
				$url   = (string) ( $row['url'] ?? '' );
				if ( '' === trim( $label ) || '' === trim( $value ) ) {
					continue;
				}
				?>
				<div class="taka-info-drawer__row">
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
	</aside>
</div>

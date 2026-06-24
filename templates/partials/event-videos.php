<?php
/**
 * Optional event promo videos for the ticket detail page.
 */

defined( 'ABSPATH' ) || exit;

$videos = is_array( $videos ?? null ) ? $videos : ( is_array( $seminar['promo_videos'] ?? null ) ? $seminar['promo_videos'] : array() );
$videos = array_values( array_filter( $videos, static function ( $video ) { return is_array( $video ) && '' !== trim( (string) ( $video['url'] ?? $video['video_url'] ?? '' ) ); } ) );
if ( empty( $videos ) ) { return; }

$allowed_embed_html = function_exists( 'taka_tour_video_embed_allowed_html' ) ? taka_tour_video_embed_allowed_html() : wp_kses_allowed_html( 'post' );
?>
<section class="taka-event-videos" aria-label="<?php echo esc_attr( taka_tour_translate( 'event.videos', 'Videos' ) ); ?>">
	<h4><?php echo esc_html( taka_tour_translate( 'event.videos', 'Videos' ) ); ?></h4>
	<div class="taka-event-videos__grid <?php echo 1 === count( $videos ) ? 'taka-event-videos__grid--single' : ''; ?>">
		<?php foreach ( $videos as $video ) : ?>
			<?php
			$url = trim( (string) ( $video['url'] ?? $video['video_url'] ?? '' ) );
			if ( '' === $url ) { continue; }
			$is_local = 'local' === (string) ( $video['source_type'] ?? '' );
			$title = trim( (string) ( $video['title'] ?? '' ) );
			$caption = trim( (string) ( $video['caption'] ?? '' ) );
			$poster = trim( (string) ( $video['poster'] ?? ( $video['thumbnail_url'] ?? '' ) ) );
			$mime_type = trim( (string) ( $video['mime_type'] ?? '' ) );
			?>
			<article class="taka-event-video">
				<div class="taka-event-video__media">
					<?php if ( $is_local ) : ?>
						<video controls preload="metadata" playsinline <?php echo '' !== $poster ? 'poster="' . esc_url( $poster ) . '"' : ''; ?>>
							<source src="<?php echo esc_url( $url ); ?>"<?php echo '' !== $mime_type ? ' type="' . esc_attr( $mime_type ) . '"' : ''; ?>>
							<a href="<?php echo esc_url( $url ); ?>" rel="noopener noreferrer"><?php echo esc_html( taka_tour_translate( 'event.open_video', 'Open video' ) ); ?></a>
						</video>
					<?php else : ?>
						<?php $embed = function_exists( 'wp_oembed_get' ) ? wp_oembed_get( $url, array( 'width' => 960 ) ) : ''; ?>
						<?php if ( '' !== (string) $embed ) : ?>
							<div class="taka-event-video__embed"><?php echo wp_kses( $embed, $allowed_embed_html ); ?></div>
						<?php else : ?>
							<a class="taka-event-video__fallback" href="<?php echo esc_url( $url ); ?>" rel="noopener noreferrer"><?php echo esc_html( taka_tour_translate( 'event.open_video', 'Open video' ) ); ?></a>
						<?php endif; ?>
					<?php endif; ?>
				</div>
				<?php if ( '' !== $title ) : ?><h5><?php echo esc_html( $title ); ?></h5><?php endif; ?>
				<?php if ( '' !== $caption ) : ?><div class="taka-event-video__caption"><?php echo wp_kses_post( wpautop( $caption ) ); ?></div><?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>
</section>

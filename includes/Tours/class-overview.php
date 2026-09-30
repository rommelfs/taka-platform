<?php
/** Independent, multilingual presentation settings for the collection directory. */
defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Overview {
	const OPTION = 'taka_platform_overview';

	public static function settings() {
		$value = get_option( self::OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	public static function colors() {
		return array( 'background' => '#ffffff', 'card' => '#f7f3ec', 'text' => '#111111', 'accent' => '#9e292b' );
	}

	public static function sanitize( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		$s = array();
		foreach ( self::colors() as $key => $default ) { $s[ $key ] = sanitize_hex_color( is_string( $raw[ $key ] ?? null ) ? $raw[ $key ] : '' ) ?: $default; }
		$s['image_id'] = is_scalar( $raw['image_id'] ?? 0 ) ? absint( $raw['image_id'] ?? 0 ) : 0;
		$s['image_url'] = esc_url_raw( is_string( $raw['image_url'] ?? null ) ? $raw['image_url'] : '' );
		$s['columns'] = in_array( $raw['columns'] ?? '', array( '2', '3' ), true ) ? $raw['columns'] : '2';
		$languages = TAKA_Platform_I18n::instance()->get_all_languages();
		$s['source_language'] = in_array( $raw['source_language'] ?? '', $languages, true ) ? $raw['source_language'] : 'en';
		foreach ( array( 'heading', 'intro', 'archive_heading', 'archive_intro' ) as $field ) {
			foreach ( $languages as $lang ) { $s[ $field ][ $lang ] = sanitize_textarea_field( is_string( $raw[ $field ][ $lang ] ?? null ) ? $raw[ $field ][ $lang ] : '' ); }
		}
		$s['blocks'] = array();
		foreach ( (array) ( $raw['blocks'] ?? array() ) as $id => $order ) {
			if ( ! is_scalar( $order ) || '' === (string) $order || ! absint( $id ) ) { continue; }
			$s['blocks'][ absint( $id ) ] = max( 0, (int) $order );
		}
		asort( $s['blocks'], SORT_NUMERIC );
		return $s;
	}

	public static function style() {
		$s = self::settings(); $style = '';
		foreach ( self::colors() as $key => $default ) { $style .= '--taka-directory-' . $key . ':' . ( sanitize_hex_color( $s[ $key ] ?? '' ) ?: $default ) . ';'; }
		return $style . '--taka-directory-columns:' . ( '3' === ( $s['columns'] ?? '' ) ? '3' : '2' ) . ';';
	}

	public static function header( $archive ) {
		$s = self::settings();
		$fallback = taka_tour_translate( $archive ? 'tours.archive' : 'tours.current', $archive ? 'Tour and seminar archive' : 'Current and upcoming tours & seminars' );
		$title = TAKA_Platform_Tours::text( $s, $archive ? 'archive_heading' : 'heading', $fallback );
		$intro = TAKA_Platform_Tours::text( $s, $archive ? 'archive_intro' : 'intro' );
		$image = TAKA_Platform_Data::resolve_attachment_url( absint( $s['image_id'] ?? 0 ), 'full', $s['image_url'] ?? '' );
		$html = '<header class="taka-tour-directory__header"><div><h1>' . esc_html( $title ) . '</h1>';
		if ( $intro ) { $html .= '<p>' . nl2br( esc_html( $intro ) ) . '</p>'; }
		$html .= '</div>';
		if ( $image ) { $html .= '<img src="' . esc_url( $image ) . '" alt="">'; }
		return $html . '</header>';
	}

	public static function blocks() {
		$html = '';
		foreach ( array_keys( self::settings()['blocks'] ?? array() ) as $id ) {
			$html .= TAKA_Platform_Data::render_content_reference( array( 'block_id' => (string) absint( $id ), 'enabled' => '1' ), 'tour_overview' );
		}
		return $html;
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'taka-platform' ) ); }
		check_admin_referer( 'taka_overview' );
		update_option( self::OPTION, self::sanitize( wp_unslash( $_POST['overview'] ?? array() ) ), false );
		wp_safe_redirect( admin_url( 'admin.php?page=taka-platform-overview&updated=1' ) );
		exit;
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'taka-platform' ) ); }
		$s = self::settings();
		echo '<div class="wrap"><h1>' . esc_html__( 'Overview design', 'taka-platform' ) . '</h1><p>' . esc_html__( 'Customize the tour and seminar directory. These settings do not change individual tour pages. Empty translated headings use the standard labels.', 'taka-platform' ) . '</p>';
		if ( isset( $_GET['updated'] ) ) { echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'taka-platform' ) . '</p></div>'; }
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="taka_overview">';
		wp_nonce_field( 'taka_overview' );
		echo '<p><label>' . esc_html__( 'Original content language', 'taka-platform' ) . ' <select name="overview[source_language]">';
		foreach ( TAKA_Platform_I18n::instance()->get_all_languages() as $lang ) { echo '<option ' . selected( $s['source_language'] ?? 'en', $lang, false ) . '>' . esc_html( $lang ) . '</option>'; }
		echo '</select></label></p>';
		foreach ( TAKA_Platform_I18n::instance()->get_all_languages() as $lang ) {
			echo '<details><summary>' . esc_html( strtoupper( $lang ) ) . '</summary>';
			foreach ( array( 'heading' => __( 'Heading', 'taka-platform' ), 'intro' => __( 'Introduction', 'taka-platform' ), 'archive_heading' => __( 'Archive heading', 'taka-platform' ), 'archive_intro' => __( 'Archive introduction', 'taka-platform' ) ) as $key => $label ) {
				echo '<p><label>' . esc_html( $label ) . '<br><textarea class="large-text" rows="3" name="overview[' . esc_attr( $key ) . '][' . esc_attr( $lang ) . ']">' . esc_textarea( $s[ $key ][ $lang ] ?? '' ) . '</textarea></label></p>';
			}
			echo '</details>';
		}
		echo '<h2>' . esc_html__( 'Header image', 'taka-platform' ) . '</h2><input id="taka-overview-image" type="hidden" name="overview[image_id]" value="' . esc_attr( $s['image_id'] ?? 0 ) . '">';
		echo '<button type="button" class="button" data-taka-media-pick data-multiple="0" data-target="taka-overview-image" data-preview="taka-overview-preview">' . esc_html__( 'Select image', 'taka-platform' ) . '</button> <button type="button" class="button" data-taka-media-remove data-target="taka-overview-image" data-preview="taka-overview-preview">' . esc_html__( 'Remove image', 'taka-platform' ) . '</button><div id="taka-overview-preview">' . wp_get_attachment_image( absint( $s['image_id'] ?? 0 ), 'thumbnail' ) . '</div>';
		echo '<p><label>' . esc_html__( 'Image fallback URL', 'taka-platform' ) . ' <input type="url" class="large-text" name="overview[image_url]" value="' . esc_attr( $s['image_url'] ?? '' ) . '"></label></p>';
		$labels = array( 'background' => __( 'Background', 'taka-platform' ), 'card' => __( 'Card background', 'taka-platform' ), 'text' => __( 'Text color', 'taka-platform' ), 'accent' => __( 'Accent color', 'taka-platform' ) );
		foreach ( self::colors() as $key => $default ) { echo '<p><label>' . esc_html( $labels[ $key ] ) . ' <input type="color" name="overview[' . esc_attr( $key ) . ']" value="' . esc_attr( $s[ $key ] ?? $default ) . '"></label></p>'; }
		echo '<p><label>' . esc_html__( 'Desktop columns (one on mobile)', 'taka-platform' ) . ' <select name="overview[columns]">';
		foreach ( array( '2', '3' ) as $n ) { echo '<option ' . selected( $s['columns'] ?? '2', $n, false ) . '>' . esc_html( $n ) . '</option>'; }
		echo '</select></label></p><h2>' . esc_html__( 'Content blocks below the cards', 'taka-platform' ) . '</h2><p>' . esc_html__( 'Enter an order number to include a block; leave blank to hide it. Edit content and translations in Content Blocks.', 'taka-platform' ) . '</p>';
		foreach ( get_posts( array( 'post_type' => TAKA_PLATFORM_CPT_CONTENT_BLOCK, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $block ) {
			echo '<p><label>' . esc_html( $block->post_title ) . ' <input type="number" min="0" name="overview[blocks][' . absint( $block->ID ) . ']" value="' . esc_attr( $s['blocks'][ $block->ID ] ?? '' ) . '"></label></p>';
		}
		submit_button(); echo '</form></div>';
	}
}

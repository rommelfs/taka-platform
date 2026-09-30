<?php
/** Public event collections, independent of private tour logistics. */
defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Tours {
	const POST_TYPE = 'taka_public_tour';
	private static $context = null;

	public static function init() {
		add_filter( 'taka_platform_events', array( __CLASS__, 'filter_events' ) );
		add_filter( 'taka_platform_archive_mode', array( __CLASS__, 'archive_mode' ) );
		add_filter( 'taka_platform_hero_settings', array( __CLASS__, 'hero' ) );
		add_filter( 'taka_platform_homepage_sections', array( __CLASS__, 'sections' ) );
		TAKA_Platform_Tours_Admin::init();
	}

	public static function context() { return self::$context; }

	public static function all( $status = 'publish' ) {
		return get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => $status, 'numberposts' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC' ) );
	}

	public static function settings( $id ) {
		$value = get_post_meta( $id, '_taka_tour_settings', true );
		return is_array( $value ) ? $value : array();
	}

	/** Existing collections remain tours when no category is stored. */
	public static function category( $id ) {
		return 'online' === ( self::settings( $id )['category'] ?? 'tour' ) ? 'online' : 'tour';
	}

	public static function category_label( $id ) {
		return 'online' === self::category( $id )
			? taka_tour_translate( 'tours.online', 'Online seminars' )
			: taka_tour_translate( 'tours.tour', 'Tour' );
	}

	public static function archived( $id ) {
		return 'archive' === ( self::settings( $id )['state'] ?? 'current' );
	}

	public static function event_tour( $event ) {
		$id = is_array( $event ) ? absint( $event['wp_post_id'] ?? 0 ) : absint( $event );
		if ( $id ) { return absint( get_post_meta( $id, '_taka_public_tour', true ) ); }
		$map = get_option( 'taka_platform_config_event_tours', array() );
		return absint( $map[ (string) ( $event['config_id'] ?? $event['id'] ?? '' ) ] ?? 0 );
	}

	public static function event_archived( $event ) {
		$id = self::event_tour( $event );
		return $id && self::archived( $id );
	}

	public static function filter_events( $events ) {
		if ( null === self::$context ) {
			return array_values( array_filter( $events, static function ( $event ) {
				$id = self::event_tour( $event );
				return ! $id || 'publish' === get_post_status( $id );
			} ) );
		}
		$id = self::$context;
		return array_values( array_filter( $events, static function ( $event ) use ( $id ) { return $id === self::event_tour( $event ); } ) );
	}

	public static function archive_mode( $archive ) {
		return $archive || ( self::$context && self::archived( self::$context ) );
	}

	public static function text( $settings, $field, $fallback = '' ) {
		return TAKA_Platform_Data::resolve_dynamic_text( $settings[ $field ] ?? $fallback, taka_tour_current_language(), $settings['source_language'] ?? 'en' ) ?: $fallback;
	}

	public static function hero( $hero ) {
		if ( ! self::$context ) { return $hero; }
		$settings = self::settings( self::$context );
		if ( 'online' === self::category( self::$context ) ) { $hero['location_display_mode'] = 'hidden'; }
		if ( ! empty( $settings['legacy_design'] ) ) { return $hero; }
		$hero['kicker'] = self::text( $settings, 'title', get_the_title( self::$context ) );
		$hero['title'] = self::text( $settings, 'theme', $hero['kicker'] );
		$hero['description'] = self::text( $settings, 'description' );
		$hero['image'] = TAKA_Platform_Data::resolve_attachment_url( absint( $settings['image_id'] ?? 0 ), 'full', $settings['image_url'] ?? '' );
		$hero['secondary_button_label'] = '';
		$hero['route_cta_enabled'] = '0';
		return $hero;
	}

	public static function url( $id = 0, $archive = false ) {
		$url = remove_query_arg( array( 'taka_tour_id', 'taka_tours', 'taka_event', 'taka_ticket_event' ), get_permalink() );
		$url = add_query_arg( 'taka_lang', taka_tour_current_language(), $url );
		return $id ? add_query_arg( 'taka_tour_id', absint( $id ), $url ) : add_query_arg( 'taka_tours', $archive ? 'archive' : 'current', $url );
	}

	/** Scope all repository reads and option snapshots to this render, including exceptions. */
	public static function render( $atts, $callback ) {
		$tours = self::all();
		if ( ! $tours && ! self::all( array( 'draft', 'pending', 'private', 'future' ) ) ) { return $callback(); }
		$atts = is_array( $atts ) ? $atts : array();
		$id = isset( $_GET['taka_tour_id'] ) && is_scalar( $_GET['taka_tour_id'] ) ? absint( $_GET['taka_tour_id'] ) : ( isset( $_GET['taka_tours'] ) ? 0 : absint( $atts['tour'] ?? 0 ) );
		$archive = isset( $_GET['taka_tours'] ) && 'archive' === $_GET['taka_tours'];
		if ( ! $id && isset( $_GET['taka_event'] ) && is_scalar( $_GET['taka_event'] ) ) {
			$event = TAKA_Platform_Data::get_event( sanitize_text_field( wp_unslash( $_GET['taka_event'] ) ) );
			$id = $event ? self::event_tour( $event ) : 0;
		}
		if ( ! $id ) { return self::directory( $tours, $archive ); }
		if ( self::POST_TYPE === get_post_type( $id ) && current_user_can( 'edit_post', $id ) && 'trash' !== get_post_status( $id ) ) {
			$tours[] = get_post( $id );
			if ( 'publish' !== get_post_status( $id ) ) { nocache_headers(); }
		}
		if ( ! in_array( $id, array_map( static function ( $tour ) { return (int) $tour->ID; }, $tours ), true ) ) {
			return '<p>' . esc_html( taka_tour_translate( 'tours.unavailable', 'This tour or seminar series is not available.' ) ) . '</p>';
		}
		$previous = self::$context;
		self::$context = $id;
		$snapshot = get_post_meta( $id, '_taka_tour_snapshot', true );
		$filters = array();
		foreach ( is_array( $snapshot ) ? $snapshot : array() as $option => $value ) {
			if ( ! in_array( $option, self::snapshot_options(), true ) ) { continue; }
			$filters[ $option ] = static function () use ( $value ) { return $value; };
			add_filter( 'pre_option_' . $option, $filters[ $option ] );
		}
		try {
			$settings = self::settings( $id );
			$color = sanitize_hex_color( $settings['accent'] ?? '' ) ?: '#9e292b';
			return '<div class="taka-tour-collection" style="--taka-tour-accent:' . esc_attr( $color ) . '">' . self::navigation() . $callback() . '</div>';
		} finally {
			foreach ( $filters as $option => $filter ) { remove_filter( 'pre_option_' . $option, $filter ); }
			self::$context = $previous;
		}
	}

	public static function sections( $sections ) {
		$id = TAKA_Platform_Tours::context();
		if ( ! $id ) { return $sections; }
		$s = TAKA_Platform_Tours::settings( $id );
		if ( ! empty( $s['legacy_design'] ) ) { return $sections; }
		if ( ! in_array( 'tour_schedule', array_column( $sections, 'key' ), true ) ) {
			$sections[] = array( 'key' => 'tour_schedule', 'type' => 'template', 'template' => 'tour-schedule.php', 'sort_order' => 10, 'visible' => '1' );
		}
		$sections = array_values( array_filter( $sections, static function ( $section ) use ( $s ) { return in_array( $section['key'], $s['sections'] ?? array( 'hero', 'tour_schedule', 'tickets', 'footer' ), true ); } ) );
		foreach ( $s['blocks'] ?? array() as $index => $block ) {
			$sections[] = array( 'key' => 'tour_block_' . $block, 'type' => 'tour_block', 'block_id' => $block, 'sort_order' => 200 + $index, 'visible' => '1' );
		}
		return $sections;
	}

	public static function snapshot_options() {
		return array( TAKA_Platform_Data::HERO_OPTION, TAKA_Platform_Data::SECTIONS_OPTION, TAKA_Platform_Data::MEDIA_OPTION, TAKA_Platform_Data::BOOKING_OPTION, TAKA_Platform_Data::TICKETS_OPTION );
	}

	public static function navigation() {
		return '<nav class="taka-tour-navigation" aria-label="' . esc_attr( taka_tour_translate( 'tours.navigation', 'Tour and seminar selection' ) ) . '"><a href="' . esc_url( self::url() ) . '">' . esc_html( taka_tour_translate( 'tours.current', 'Current and upcoming tours & seminars' ) ) . '</a><a href="' . esc_url( self::url( 0, true ) ) . '">' . esc_html( taka_tour_translate( 'tours.archive', 'Tour and seminar archive' ) ) . '</a></nav>';
	}

	private static function directory( $tours, $archive ) {
		$event_links = array();
		foreach ( TAKA_Platform_Data::get_public_events() as $event ) {
			$tour_id = self::event_tour( $event );
			if ( ! $tour_id ) { continue; }
			$key = TAKA_Platform_Data::event_panel_key( $event );
			$event_links[ $key ] = self::url( $tour_id );
		}
		ob_start();
		echo '<section class="taka-tour-directory" data-taka-tour-event-links="' . esc_attr( wp_json_encode( $event_links ) ) . '">' . self::navigation(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo taka_tour_render_template( 'partials/language-switcher.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<h1>' . esc_html( taka_tour_translate( $archive ? 'tours.archive' : 'tours.current', $archive ? 'Tour and seminar archive' : 'Current and upcoming tours & seminars' ) ) . '</h1><div class="taka-tour-directory__grid">';
		$count = 0;
		foreach ( $tours as $tour ) {
			if ( self::archived( $tour->ID ) !== $archive ) { continue; }
			++$count;
			$settings = self::settings( $tour->ID );
			$image = TAKA_Platform_Data::resolve_attachment_url( absint( $settings['image_id'] ?? 0 ), 'large', $settings['image_url'] ?? '' );
			echo '<article class="taka-tour-directory__card">';
			echo '<div class="taka-tour-directory__media" aria-hidden="true">';
			if ( $image ) { echo '<img src="' . esc_url( $image ) . '" alt="" loading="lazy">'; }
			echo '</div><p class="taka-tour-directory__category">' . esc_html( self::category_label( $tour->ID ) ) . '</p>';
			echo '<h2><a href="' . esc_url( self::url( $tour->ID ) ) . '">' . esc_html( self::text( $settings, 'title', $tour->post_title ) ) . '</a></h2>';
			echo '<p>' . esc_html( $settings['period'] ?? '' ) . '</p><p>' . esc_html( self::text( $settings, 'description' ) ) . '</p></article>';
		}
		if ( ! $count ) { echo '<p>' . esc_html( taka_tour_translate( 'tours.empty', 'No tours or seminars have been published here yet.' ) ) . '</p>'; }
		echo '</div></section>';
		return ob_get_clean();
	}
}

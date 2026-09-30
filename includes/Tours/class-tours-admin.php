<?php
/** Tour editing and opt-in migration for the initial deployment. */
defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Tours_Admin {
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_taka_setup_tours', array( __CLASS__, 'setup' ) );
	}

	public static function menu() {
		add_submenu_page( 'taka-platform', __( 'Tour setup', 'taka-platform' ), __( 'Tour setup', 'taka-platform' ), 'manage_options', 'taka-tour-setup', array( __CLASS__, 'setup_page' ) );
	}

	public static function boxes() {
		add_meta_box( 'taka_public_tour', __( 'Tour', 'taka-platform' ), array( __CLASS__, 'event_box' ), TAKA_PLATFORM_CPT_EVENT, 'side' );
		add_meta_box( 'taka_tour_settings', __( 'Tour content and design', 'taka-platform' ), array( __CLASS__, 'tour_box' ), TAKA_Platform_Tours::POST_TYPE );
	}

	public static function event_box( $post ) {
		wp_nonce_field( 'taka_tour_event', 'taka_tour_event_nonce' );
		echo '<label for="taka-public-tour">' . esc_html__( 'Tour assignment', 'taka-platform' ) . '</label><select id="taka-public-tour" name="taka_public_tour" style="width:100%"><option value="0">' . esc_html__( 'No tour', 'taka-platform' ) . '</option>';
		foreach ( TAKA_Platform_Tours::all( array( 'publish', 'draft', 'private', 'pending', 'future' ) ) as $tour ) {
			if ( ! current_user_can( 'edit_post', $tour->ID ) ) { continue; }
			echo '<option value="' . esc_attr( $tour->ID ) . '" ' . selected( TAKA_Platform_Tours::event_tour( $post->ID ), $tour->ID, false ) . '>' . esc_html( $tour->post_title ) . '</option>';
		}
		echo '</select>';
	}

	private static function input( $key, $label, $value, $type = 'text' ) {
		echo '<p><label>' . esc_html( $label ) . '<br><input class="widefat" type="' . esc_attr( $type ) . '" name="tour_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '"></label></p>';
	}

	public static function tour_box( $post ) {
		wp_nonce_field( 'taka_tour_settings', 'taka_tour_settings_nonce' );
		wp_nonce_field( TAKA_Platform_Admin::NONCE, TAKA_Platform_Admin::NONCE );
		$s = TAKA_Platform_Tours::settings( $post->ID );
		echo '<p><label>' . esc_html__( 'Lifecycle', 'taka-platform' ) . ' <select name="tour_settings[state]">';
		foreach ( array( 'current' => __( 'Current / upcoming', 'taka-platform' ), 'archive' => __( 'Archive (booking disabled)', 'taka-platform' ) ) as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $s['state'] ?? 'current', $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label></p>';
		self::input( 'period', __( 'Period (for example 2027-06; does not define event dates)', 'taka-platform' ), $s['period'] ?? '' );
		self::input( 'accent', __( 'Accent color', 'taka-platform' ), $s['accent'] ?? '#9e292b', 'color' );
		echo '<p><label for="taka-tour-image">' . esc_html__( 'Hero image', 'taka-platform' ) . '</label></p><input id="taka-tour-image" type="hidden" name="tour_settings[image_id]" value="' . esc_attr( $s['image_id'] ?? 0 ) . '">';
		echo '<button type="button" class="button" data-taka-media-pick data-multiple="0" data-target="taka-tour-image" data-preview="taka-tour-image-preview">' . esc_html__( 'Select image', 'taka-platform' ) . '</button> <button type="button" class="button" data-taka-media-remove data-target="taka-tour-image" data-preview="taka-tour-image-preview">' . esc_html__( 'Remove image', 'taka-platform' ) . '</button>';
		echo '<div id="taka-tour-image-preview">' . wp_get_attachment_image( absint( $s['image_id'] ?? 0 ), 'thumbnail' ) . '</div>';
		self::input( 'image_url', __( 'Hero image: fallback URL', 'taka-platform' ), $s['image_url'] ?? '', 'url' );
		echo '<p><label>' . esc_html__( 'Original content language', 'taka-platform' ) . ' <select name="tour_settings[source_language]">';
		foreach ( TAKA_Platform_I18n::instance()->get_all_languages() as $lang ) {
			echo '<option ' . selected( $s['source_language'] ?? 'en', $lang, false ) . '>' . esc_html( $lang ) . '</option>';
		}
		echo '</select></label></p>';
		foreach ( TAKA_Platform_I18n::instance()->get_all_languages() as $lang ) {
			echo '<details><summary>' . esc_html( strtoupper( $lang ) ) . '</summary>';
			foreach ( array( 'title' => __( 'Public tour title', 'taka-platform' ), 'theme' => __( 'Theme / hero headline', 'taka-platform' ), 'description' => __( 'Description', 'taka-platform' ) ) as $field => $label ) {
				echo '<p><label>' . esc_html( $label ) . '<br><textarea class="widefat" rows="3" name="tour_settings[' . esc_attr( $field ) . '][' . esc_attr( $lang ) . ']">' . esc_textarea( $s[ $field ][ $lang ] ?? '' ) . '</textarea></label></p>';
			}
			echo '</details>';
		}
		echo '<p><label><input type="checkbox" name="tour_settings[legacy_design]" value="1" ' . checked( ! empty( $s['legacy_design'] ), true, false ) . '> ' . esc_html__( 'Use preserved legacy hero and sections', 'taka-platform' ) . '</label></p>';
		echo '<p>' . esc_html__( 'Shared sections reuse the global content and its translations. Select the elements that belong to this tour.', 'taka-platform' ) . '</p>';
		$sections = TAKA_Platform_Data::get_homepage_sections();
		if ( ! in_array( 'tour_schedule', array_column( $sections, 'key' ), true ) ) { $sections[] = array( 'key' => 'tour_schedule' ); }
		foreach ( $sections as $section ) {
			$key = $section['key'];
			echo '<p><label><input type="checkbox" name="tour_settings[sections][]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $s['sections'] ?? array( 'hero', 'tour_schedule', 'tickets', 'footer' ), true ), true, false ) . '> ' . esc_html( $key ) . '</label></p>';
		}
		echo '<p>' . esc_html__( 'Additional content blocks (ordered IDs, comma separated). Blocks can be shared between tours or created for one tour.', 'taka-platform' ) . '</p>';
		self::input( 'blocks', __( 'Content block IDs', 'taka-platform' ), implode( ',', $s['blocks'] ?? array() ) );
		echo '<p><code>[taka_homepage tour="' . esc_html( $post->ID ) . '"]</code></p>';
	}

	public static function save( $id ) {
		if ( wp_is_post_revision( $id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $id ) ) { return; }
		if ( TAKA_PLATFORM_CPT_EVENT === get_post_type( $id ) && isset( $_POST['taka_tour_event_nonce'], $_POST['taka_public_tour'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['taka_tour_event_nonce'] ) ), 'taka_tour_event' ) ) {
			$tour = absint( $_POST['taka_public_tour'] );
			$old = TAKA_Platform_Tours::event_tour( $id );
			if ( $old && ! current_user_can( 'edit_post', $old ) ) { return; }
			if ( ! $tour || ( TAKA_Platform_Tours::POST_TYPE === get_post_type( $tour ) && current_user_can( 'edit_post', $tour ) ) ) { update_post_meta( $id, '_taka_public_tour', $tour ); }
		}
		if ( TAKA_Platform_Tours::POST_TYPE !== get_post_type( $id ) || empty( $_POST['taka_tour_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['taka_tour_settings_nonce'] ) ), 'taka_tour_settings' ) ) { return; }
		$raw = isset( $_POST['tour_settings'] ) && is_array( $_POST['tour_settings'] ) ? wp_unslash( $_POST['tour_settings'] ) : array();
		$s = array(
			'state' => 'archive' === ( $raw['state'] ?? '' ) ? 'archive' : 'current',
			'period' => sanitize_text_field( $raw['period'] ?? '' ),
			'accent' => sanitize_hex_color( $raw['accent'] ?? '' ),
			'image_id' => absint( $raw['image_id'] ?? 0 ), 'image_url' => esc_url_raw( $raw['image_url'] ?? '' ),
			'source_language' => TAKA_Platform_Translation_Packages::sanitize_language( $raw['source_language'] ?? '', 'en' ),
			'legacy_design' => empty( $raw['legacy_design'] ) ? '0' : '1',
			'sections' => array_values( array_unique( array_map( 'sanitize_key', (array) ( $raw['sections'] ?? array() ) ) ) ),
			'blocks' => array_values( array_unique( array_filter( array_map( 'absint', explode( ',', (string) ( $raw['blocks'] ?? '' ) ) ) ) ) ),
		);
		foreach ( array( 'title', 'theme', 'description' ) as $field ) {
			foreach ( TAKA_Platform_I18n::instance()->get_all_languages() as $lang ) {
				$s[ $field ][ $lang ] = sanitize_textarea_field( $raw[ $field ][ $lang ] ?? '' );
			}
		}
		update_post_meta( $id, '_taka_tour_settings', $s );
	}

	public static function setup_page() {
		echo '<div class="wrap"><h1>' . esc_html__( 'Tour setup', 'taka-platform' ) . '</h1><p>' . esc_html__( 'Create the 2026 archive and draft tours for June and September 2027. Only unassigned events dated in 2026 are assigned to the archive. Existing events, tickets and shared content are retained. Review and publish the 2027 tours when ready.', 'taka-platform' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="taka_setup_tours">';
		wp_nonce_field( 'taka_setup_tours' );
		submit_button( __( 'Set up 2026 archive and 2027 tours', 'taka-platform' ) );
		echo '</form></div>';
	}

	/** Idempotent seed migration; deployment-specific labels stay out of runtime defaults. */
	public static function setup() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'taka-platform' ) ); }
		check_admin_referer( 'taka_setup_tours' );
		if ( ! TAKA_Platform_Data::is_using_wp_events() || get_option( 'taka_platform_tour_import_pending', false ) ) {
			update_option( 'taka_platform_tour_import_pending', true, false );
			$result = TAKA_Platform_Admin::import_fallback_events_for_tours();
			if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
			delete_option( 'taka_platform_tour_import_pending' );
		}
		self::provision_initial_tours();
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . TAKA_Platform_Tours::POST_TYPE ) );
		exit;
	}

	/** Called only by the authorized setup action; safe to repeat after partial completion. */
	public static function provision_initial_tours() {
		$ids = get_option( 'taka_platform_initial_tours', array() );
		foreach ( array( '2026' => 'Tour 2026', '2027-06' => 'Tour Juni 2027', '2027-09' => 'Tour September 2027' ) as $period => $title ) {
			if ( ! empty( $ids[ $period ] ) && get_post( $ids[ $period ] ) ) { continue; }
			$id = wp_insert_post( array( 'post_type' => TAKA_Platform_Tours::POST_TYPE, 'post_title' => $title, 'post_status' => '2026' === (string) $period ? 'publish' : 'draft' ), true );
			if ( is_wp_error( $id ) ) { wp_die( esc_html( $id->get_error_message() ) ); }
			$ids[ $period ] = $id;
			$s = array( 'state' => '2026' === (string) $period ? 'archive' : 'current', 'period' => (string) $period, 'source_language' => 'de', 'title' => array( 'de' => $title, 'en' => '2027-06' === $period ? 'June 2027 Tour' : ( '2027-09' === $period ? 'September 2027 Tour' : 'Tour 2026' ) ), 'legacy_design' => '2026' === (string) $period ? '1' : '0', 'sections' => array( 'hero', 'tour_schedule', 'tickets', 'footer' ) );
			update_post_meta( $id, '_taka_tour_settings', $s );
			if ( '2026' === (string) $period ) {
				$snapshot = array();
				foreach ( TAKA_Platform_Tours::snapshot_options() as $option ) { $snapshot[ $option ] = get_option( $option, array() ); }
				$snapshot[ TAKA_Platform_Data::HERO_OPTION ] = TAKA_Platform_Data::get_hero_settings( false );
				$snapshot[ TAKA_Platform_Data::SECTIONS_OPTION ] = TAKA_Platform_Data::get_content_sections( false );
				update_post_meta( $id, '_taka_tour_snapshot', $snapshot );
			}
			update_option( 'taka_platform_initial_tours', $ids, false );
		}
		$map = get_option( 'taka_platform_config_event_tours', array() );
		foreach ( TAKA_Platform_Data::get_events_for_translation_packages() as $event ) {
			if ( '2026' !== substr( $event['date_start'] ?? '', 0, 4 ) || TAKA_Platform_Tours::event_tour( $event ) ) { continue; }
			if ( ! empty( $event['wp_post_id'] ) ) { update_post_meta( $event['wp_post_id'], '_taka_public_tour', $ids['2026'] ); }
			else { $map[ (string) ( $event['config_id'] ?? $event['id'] ) ] = $ids['2026']; }
		}
		update_option( 'taka_platform_config_event_tours', $map, false );
		return $ids;
	}
}

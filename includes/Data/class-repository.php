<?php
/**
 * WordPress-first data model for reusable international event tours.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Data {
	const EVENT_POST_TYPE = TAKA_PLATFORM_CPT_EVENT;
	const ORGANIZER_POST_TYPE = TAKA_PLATFORM_CPT_ORGANIZER;
	const VENUE_POST_TYPE = TAKA_PLATFORM_CPT_VENUE;
	const CONTENT_BLOCK_POST_TYPE = TAKA_PLATFORM_CPT_CONTENT_BLOCK;
	const MEDIA_OPTION = 'taka_tour_media_settings';
	const HERO_OPTION = 'taka_platform_hero_settings';
	const SECTIONS_OPTION = 'taka_platform_content_sections';
	const BOOKING_OPTION = 'taka_platform_booking_information';
	const TICKETS_OPTION = 'taka_platform_ticket_section_settings';
	const OPTION_LISTS_OPTION = 'taka_platform_option_lists';

	/** Custom post types required for the WordPress data source. */
	public static function required_post_types() {
		return array(
			self::EVENT_POST_TYPE => __( 'Events', 'taka-platform' ),
			self::ORGANIZER_POST_TYPE => __( 'Organizers', 'taka-platform' ),
			self::VENUE_POST_TYPE => __( 'Venues', 'taka-platform' ),
			self::CONTENT_BLOCK_POST_TYPE => __( 'Content Blocks', 'taka-platform' ),
		);
	}

	/** Event fields backed by configurable option lists. */
	public static function event_option_list_fields() {
		return array(
			'audience' => 'Audience',
			'format' => 'Format',
		);
	}

	/** Default configurable option lists. */
	public static function default_option_lists() {
		return array(
			'audience' => array(
				'label' => 'Audience',
				'options' => array(
					array( 'key' => 'adults', 'label' => 'Erwachsene', 'source_language' => 'de', 'sort_order' => 10, 'enabled' => '1' ),
					array( 'key' => 'children', 'label' => 'Kinder', 'source_language' => 'de', 'sort_order' => 20, 'enabled' => '1' ),
					array( 'key' => 'children_and_youth', 'label' => 'Kinder und Jugendliche', 'source_language' => 'de', 'sort_order' => 30, 'enabled' => '1' ),
					array( 'key' => 'youth', 'label' => 'Jugendliche', 'source_language' => 'de', 'sort_order' => 40, 'enabled' => '1' ),
					array( 'key' => 'adults_and_youth', 'label' => 'Erwachsene und Jugendliche', 'source_language' => 'de', 'sort_order' => 50, 'enabled' => '1' ),
					array( 'key' => 'all', 'label' => 'Alle', 'source_language' => 'de', 'sort_order' => 60, 'enabled' => '1' ),
				),
			),
			'format' => array(
				'label' => 'Format',
				'options' => array(
					array( 'key' => 'evening_seminar', 'label' => 'Abendseminar', 'source_language' => 'de', 'sort_order' => 10, 'enabled' => '1' ),
					array( 'key' => 'half_day_seminar', 'label' => 'Halbtagseminar', 'source_language' => 'de', 'sort_order' => 20, 'enabled' => '1' ),
					array( 'key' => 'day_seminar', 'label' => 'Tagesseminar', 'source_language' => 'de', 'sort_order' => 30, 'enabled' => '1' ),
					array( 'key' => 'two_day_seminar', 'label' => '2-Tage-Seminar', 'source_language' => 'de', 'sort_order' => 40, 'enabled' => '1' ),
					array( 'key' => 'weekend_seminar', 'label' => 'Wochenendseminar', 'source_language' => 'de', 'sort_order' => 50, 'enabled' => '1' ),
					array( 'key' => 'tour_event', 'label' => 'Tour-Event', 'source_language' => 'de', 'sort_order' => 60, 'enabled' => '1' ),
					array( 'key' => 'seminar', 'label' => 'Seminar', 'source_language' => 'de', 'sort_order' => 70, 'enabled' => '1' ),
				),
			),
		);
	}

	/** Load normalized configurable option lists. */
	public static function get_option_lists( $include_disabled = true ) {
		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION_LISTS_OPTION, array() ) : array();
		return self::normalize_option_lists( is_array( $stored ) && ! empty( $stored ) ? $stored : self::default_option_lists(), $include_disabled );
	}

	/** Normalize option-list settings from defaults or admin input. */
	public static function normalize_option_lists( $lists, $include_disabled = true ) {
		$defaults = self::default_option_lists();
		$lists = is_array( $lists ) ? $lists : array();
		$normalized = array();
		foreach ( self::event_option_list_fields() as $list_key => $fallback_label ) {
			$list = is_array( $lists[ $list_key ] ?? null ) ? $lists[ $list_key ] : ( $defaults[ $list_key ] ?? array() );
			$options = array();
			foreach ( (array) ( $list['options'] ?? array() ) as $option ) {
				if ( ! is_array( $option ) ) { continue; }
				$key = sanitize_key( $option['key'] ?? '' );
				$label = sanitize_text_field( $option['label'] ?? '' );
				if ( '' === $key || '' === $label ) { continue; }
				$source_language = in_array( $option['source_language'] ?? '', self::content_section_languages(), true ) ? sanitize_key( $option['source_language'] ) : self::platform_fallback_language();
				$translations = array();
				foreach ( self::content_section_languages() as $lang ) {
					$translations[ $lang ] = sanitize_text_field( $option['translations'][ $lang ] ?? '' );
				}
				$enabled = array_key_exists( 'enabled', $option ) ? ( ! empty( $option['enabled'] ) ? '1' : '0' ) : '1';
				if ( ! $include_disabled && '1' !== $enabled ) { continue; }
				$options[ $key ] = array(
					'key' => $key,
					'label' => $label,
					'source_language' => $source_language,
					'translations' => $translations,
					'sort_order' => (int) ( $option['sort_order'] ?? 0 ),
					'enabled' => $enabled,
				);
			}
			usort( $options, static function ( $a, $b ) { return ( (int) ( $a['sort_order'] ?? 0 ) <=> (int) ( $b['sort_order'] ?? 0 ) ) ?: strcmp( (string) ( $a['label'] ?? '' ), (string) ( $b['label'] ?? '' ) ); } );
			$normalized[ $list_key ] = array(
				'label' => sanitize_text_field( $list['label'] ?? $fallback_label ),
				'options' => array_values( $options ),
			);
		}
		return $normalized;
	}

	/** Resolve the display label for an option-list value, preserving unknown legacy text. */
	public static function resolve_option_list_label( $list_key, $value, $lang = null, $legacy_fallback = '' ) {
		$value = trim( (string) $value );
		if ( '' === $value ) { return ''; }
		$lang = $lang ?: taka_tour_current_language();
		foreach ( self::get_option_lists( false )[ $list_key ]['options'] ?? array() as $option ) {
			if ( self::option_matches_value( $option, $value ) ) {
				if ( self::option_should_use_legacy_fallback( $option, $value, $lang, $legacy_fallback ) ) {
					return (string) $legacy_fallback;
				}
				return self::option_label_for_language( $option, $lang );
			}
		}
		return '' !== trim( (string) $legacy_fallback ) ? (string) $legacy_fallback : $value;
	}

	/** Return the stable key for a configured option value or legacy label. */
	public static function option_key_for_value( $list_key, $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) { return ''; }
		foreach ( self::get_option_lists( true )[ $list_key ]['options'] ?? array() as $option ) {
			if ( self::option_matches_value( $option, $value ) ) {
				return (string) ( $option['key'] ?? '' );
			}
		}
		return '';
	}

	/** Build admin choices for an event option-list field. */
	public static function option_list_choices( $list_key, $lang = null ) {
		$lang = $lang ?: self::platform_fallback_language();
		$choices = array();
		foreach ( self::get_option_lists( false )[ $list_key ]['options'] ?? array() as $option ) {
			$choices[ (string) ( $option['key'] ?? '' ) ] = self::option_label_for_language( $option, $lang );
		}
		return array_filter( $choices, static function ( $label, $key ) { return '' !== (string) $key && '' !== trim( (string) $label ); }, ARRAY_FILTER_USE_BOTH );
	}

	/** Option list objects for translation packages. */
	public static function option_list_translation_objects() {
		$objects = array();
		foreach ( self::get_option_lists( true ) as $list_key => $list ) {
			foreach ( $list['options'] ?? array() as $option ) {
				$object_id = $list_key . '.' . ( $option['key'] ?? '' );
				$values = $option['translations'] ?? array();
				$values[ $option['source_language'] ?? self::platform_fallback_language() ] = $option['label'] ?? '';
				$objects[ $object_id ] = array(
					'label' => ( $list['label'] ?? $list_key ) . ' / ' . ( $option['label'] ?? $option['key'] ?? '' ),
					'source_language' => $option['source_language'] ?? self::platform_fallback_language(),
					'values' => array( 'label' => $values ),
				);
			}
		}
		return $objects;
	}

	/** Update option-list translations from imported translation packages. */
	public static function update_option_list_translations( $changes ) {
		$lists = self::get_option_lists( true );
		foreach ( (array) $changes as $object_id => $fields ) {
			$parts = explode( '.', (string) $object_id, 2 );
			if ( 2 !== count( $parts ) ) { continue; }
			list( $list_key, $option_key ) = $parts;
			if ( empty( $lists[ $list_key ]['options'] ) ) { continue; }
			foreach ( $lists[ $list_key ]['options'] as $index => $option ) {
				if ( (string) ( $option['key'] ?? '' ) !== $option_key ) { continue; }
				foreach ( (array) ( $fields['label'] ?? array() ) as $lang => $text ) {
					if ( in_array( $lang, self::content_section_languages(), true ) ) {
						$lists[ $list_key ]['options'][ $index ]['translations'][ $lang ] = sanitize_text_field( $text );
					}
				}
			}
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_LISTS_OPTION, $lists, false );
		}
	}

	private static function option_matches_value( $option, $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) { return false; }
		if ( $value === (string) ( $option['key'] ?? '' ) ) { return true; }
		$candidates = array_merge( array( $option['label'] ?? '' ), array_values( (array) ( $option['translations'] ?? array() ) ) );
		foreach ( $candidates as $candidate ) {
			if ( 0 === strcasecmp( $value, trim( (string) $candidate ) ) ) { return true; }
		}
		return false;
	}

	private static function option_label_for_language( $option, $lang ) {
		$source_language = $option['source_language'] ?? self::platform_fallback_language();
		$values = (array) ( $option['translations'] ?? array() );
		$values[ $source_language ] = $option['label'] ?? '';
		return self::resolve_dynamic_text( $values, $lang, $source_language );
	}

	private static function option_should_use_legacy_fallback( $option, $value, $lang, $legacy_fallback ) {
		$legacy_fallback = trim( (string) $legacy_fallback );
		if ( '' === $legacy_fallback || 0 === strcasecmp( trim( (string) $value ), $legacy_fallback ) ) {
			return false;
		}
		$source_language = $option['source_language'] ?? self::platform_fallback_language();
		if ( $lang === $source_language ) {
			return false;
		}
		return '' === trim( (string) ( $option['translations'][ $lang ] ?? '' ) );
	}


	/** User-facing text fields that support object-level translations. */
	public static function translatable_text_fields( $object_type ) {
		$fields = array(
			'content_block' => array(
				'kicker' => 'Kicker',
				'title' => 'Title',
				'subtitle' => 'Subtitle',
				'body' => 'Body',
				'button_label' => 'Button label',
				'button_url' => 'Button URL',
			),
			'event' => array(
				'description' => 'Seminar description',
				'subtitle' => 'Subtitle',
				'long_description' => 'Long description',
				'ticket_card_text' => 'Ticket card text',
				'ticket_tab_label' => 'Ticket tab label',
				'accessibility' => 'Accessibility notes',
				'notes' => 'Notes',
				'parking' => 'Parking notes',
			),
			'organizer' => array(
				'description' => 'Description',
			),
			'venue' => array(
				'parking' => 'Parking notes',
				'accessibility' => 'Accessibility',
				'notes' => 'Special notes',
			),
		);
		return $fields[ $object_type ] ?? array();
	}

	/** Normalize the source language stored on one translatable object. */
	public static function object_source_language( $object ) {
		$lang = sanitize_key( (string) ( is_array( $object ) ? ( $object['source_language'] ?? '' ) : '' ) );
		return in_array( $lang, self::content_section_languages(), true ) ? $lang : self::platform_fallback_language();
	}

	/** Normalize field/language translation arrays for object-level text fields. */
	public static function normalize_object_text_translations( $translations, $fields = array() ) {
		$translations = is_array( $translations ) ? $translations : array();
		$fields = ! empty( $fields ) ? array_keys( $fields ) : array_keys( self::translatable_text_fields( 'event' ) );
		$clean = array();
		foreach ( $fields as $field ) {
			foreach ( self::content_section_languages() as $lang ) {
				$clean[ $field ][ $lang ] = self::sanitize_translatable_text( $translations[ $field ][ $lang ] ?? '' );
			}
		}
		return $clean;
	}

	private static function sanitize_translatable_text( $value ) {
		return function_exists( 'wp_kses_post' ) ? wp_kses_post( $value ) : sanitize_textarea_field( $value );
	}

	/** Build language-keyed values for translation packages from scalar source text plus stored translations. */
	public static function object_text_values( $object, $fields ) {
		$source_language = self::object_source_language( $object );
		$translations = self::normalize_object_text_translations( $object['text_translations'] ?? array(), $fields );
		$values = array();
		foreach ( array_keys( $fields ) as $field ) {
			$values[ $field ] = $translations[ $field ] ?? array();
			if ( '' !== trim( (string) ( $object[ $field ] ?? '' ) ) ) {
				$values[ $field ][ $source_language ] = (string) $object[ $field ];
			}
		}
		return $values;
	}

	/** Resolve configured object-level text fields for the active frontend language. */
	private static function resolve_object_text_fields( $object, $object_type, $lang ) {
		$fields = self::translatable_text_fields( $object_type );
		if ( empty( $fields ) ) { return $object; }
		$values = self::object_text_values( $object, $fields );
		$source_language = self::object_source_language( $object );
		foreach ( array_keys( $fields ) as $field ) {
			$object[ $field ] = self::resolve_dynamic_text( $values[ $field ] ?? '', $lang, $source_language );
		}
		return $object;
	}

	/** Content block types for reusable editorial content. */
	public static function content_block_types() {
		return array(
			'generic' => 'Generic',
			'biography' => 'Biography',
			'booking_information' => 'Booking information',
			'policy' => 'Policy',
			'event_description' => 'Event description',
			'organizer_description' => 'Organizer description',
			'venue_information' => 'Venue information',
			'training_topic' => 'Training topic',
			'community' => 'Community',
			'sponsor' => 'Sponsor',
			'speaker' => 'Speaker',
		);
	}

	/** Content reference contexts supported in v2.1.0. */
	public static function content_reference_contexts() {
		return array(
			'homepage_section' => 'Homepage section',
			'event_description' => 'Event description',
			'event_booking' => 'Event booking',
			'event_sidebar' => 'Event sidebar',
			'organizer_profile' => 'Organizer profile',
			'venue_information' => 'Venue information',
			'ticket_panel' => 'Ticket panel',
			'modal' => 'Modal',
		);
	}

	/** Display styles supported by reusable content references. */
	public static function content_reference_display_styles() {
		return array(
			'default' => __( 'Use parent layout', 'taka-platform' ),
			'text_only' => __( 'Text only', 'taka-platform' ),
			'image_left' => __( 'Image left', 'taka-platform' ),
			'image_right' => __( 'Image right', 'taka-platform' ),
			'image_above' => __( 'Image above', 'taka-platform' ),
			'full_background' => __( 'Full width image background', 'taka-platform' ),
			'two_column' => __( 'Two column', 'taka-platform' ),
			'gallery_grid' => __( 'Gallery grid', 'taka-platform' ),
			'feature_card' => __( 'Feature card', 'taka-platform' ),
		);
	}

	/** Text fields available on one reusable content block. */
	public static function content_block_text_fields() {
		return self::translatable_text_fields( 'content_block' );
	}

	/** Load reusable content blocks from WordPress. */
	public static function get_content_blocks( $resolve_translations = false, $lang = null ) {
		$blocks = self::load_content_blocks_from_wp();
		if ( $resolve_translations ) {
			$lang = $lang ?: taka_tour_current_language();
			foreach ( $blocks as $key => $block ) {
				$blocks[ $key ] = self::resolve_content_block( $block, $lang );
			}
		}
		return $blocks;
	}

	/** Get one content block by post ID or slug/config ID. */
	public static function get_content_block( $block_id, $resolve_translations = false, $lang = null ) {
		$block_id = (string) $block_id;
		if ( '' === $block_id || '0' === $block_id ) { return null; }
		$blocks = self::get_content_blocks( $resolve_translations, $lang );
		return $blocks[ $block_id ] ?? null;
	}

	/** Resolve one content block for frontend display. */
	public static function resolve_content_block( $block, $lang = null ) {
		if ( ! is_array( $block ) ) { return array(); }
		$lang = $lang ?: taka_tour_current_language();
		$block = self::resolve_object_text_fields( $block, 'content_block', $lang );
		$block['image'] = self::resolve_attachment_url( absint( $block['image_id'] ?? 0 ), 'full', (string) ( $block['image_url'] ?? '' ) );
		$block['gallery_images'] = array_values( array_filter( array_merge( self::attachment_urls( $block['gallery_image_ids'] ?? array(), 'full' ), (array) ( $block['gallery_image_urls'] ?? array() ) ) ) );
		return $block;
	}

	/** Normalize a content-reference payload. */
	public static function normalize_content_reference( $reference, $context = '' ) {
		$reference = is_array( $reference ) ? $reference : array();
		$context = sanitize_key( $reference['context'] ?? $context );
		return array(
			'block_id' => sanitize_text_field( (string) ( $reference['block_id'] ?? '' ) ),
			'context' => $context,
			'enabled' => array_key_exists( 'enabled', $reference ) ? ( ! empty( $reference['enabled'] ) ? '1' : '0' ) : '0',
			'sort_order' => (int) ( $reference['sort_order'] ?? 0 ),
			'display_style' => array_key_exists( sanitize_key( $reference['display_style'] ?? 'default' ), self::content_reference_display_styles() ) ? sanitize_key( $reference['display_style'] ?? 'default' ) : 'default',
			'custom_title' => self::normalize_dynamic_text_value( $reference['custom_title'] ?? '' ),
			'override_translations' => self::normalize_content_section_translations( array( 'translations' => $reference['override_translations'] ?? array() ) ),
		);
	}

	/** Resolve a content reference into translated block fields. */
	public static function resolve_content_reference( $reference, $lang = null ) {
		$reference = self::normalize_content_reference( $reference );
		if ( '1' !== (string) ( $reference['enabled'] ?? '0' ) || '' === (string) ( $reference['block_id'] ?? '' ) ) {
			return null;
		}
		$block = self::get_content_block( $reference['block_id'], true, $lang );
		if ( ! is_array( $block ) || '1' !== (string) ( $block['enabled'] ?? '1' ) ) {
			return null;
		}
		$lang = $lang ?: taka_tour_current_language();
		$override_source = array(
			'source_language' => self::platform_fallback_language(),
			'translations' => $reference['override_translations'] ?? array(),
		);
		foreach ( array_keys( self::content_block_text_fields() ) as $field ) {
			$override = self::resolve_content_section_field( $override_source, $field, $lang );
			if ( '' !== trim( (string) $override ) ) {
				$block[ $field ] = $override;
			}
		}
		$custom_title = self::resolve_dynamic_text( $reference['custom_title'] ?? '', $lang, self::platform_fallback_language() );
		if ( '' !== trim( (string) $custom_title ) ) {
			$block['title'] = $custom_title;
		}
		$block['content_reference'] = $reference;
		return $block;
	}

	/** Render one content reference through the existing content-section partial. */
	public static function render_content_reference( $reference, $context = '', $lang = null ) {
		$reference = self::normalize_content_reference( $reference, $context );
		$block = self::resolve_content_reference( $reference, $lang );
		if ( ! is_array( $block ) ) { return ''; }
		$section = array_merge(
			array(
				'key' => sanitize_key( ( $context ?: 'content_block' ) . '_' . ( $block['slug'] ?? $block['id'] ?? 'block' ) ),
				'visible' => '1',
				'layout' => 'default' !== $reference['display_style'] ? $reference['display_style'] : 'text_only',
				'background_style' => 'plain',
				'image_fit' => 'contain',
				'image_position' => 'center center',
				'css_class' => '',
				'sort_order' => (int) ( $reference['sort_order'] ?? 0 ),
			),
			$block
		);
		$classes = array_filter( preg_split( '/\s+/', (string) ( $section['css_class'] ?? '' ) ) );
		array_unshift( $classes, 'taka-content-reference' );
		$section['css_class'] = implode( ' ', array_unique( $classes ) );
		return taka_tour_render_template( 'partials/content-section.php', array( 'section' => $section ) );
	}

	/** Usage contexts for admin and translation package context labels. */
	public static function content_block_usage_contexts() {
		$contexts = array();
		foreach ( self::get_content_sections( false ) as $key => $section ) {
			$reference = self::normalize_content_reference( $section['content_reference'] ?? array(), 'homepage_section' );
			if ( '1' === (string) $reference['enabled'] && '' !== $reference['block_id'] ) {
				$title = self::resolve_dynamic_text( $section['title'] ?? '', self::platform_fallback_language(), self::content_section_source_language( $section ) );
				$contexts[ $reference['block_id'] ][] = 'Homepage / ' . ( '' !== trim( (string) $title ) ? $title : $key );
			}
		}
		foreach ( self::get_events() as $event ) {
			$reference = self::normalize_content_reference( $event['content_references']['event_description'] ?? array(), 'event_description' );
			if ( '1' === (string) $reference['enabled'] && '' !== $reference['block_id'] ) {
				$contexts[ $reference['block_id'] ][] = 'Event / ' . ( $event['title'] ?? ( $event['id'] ?? '' ) ) . ' / Description';
			}
		}
		return array_map( 'array_values', $contexts );
	}

	private static function load_content_blocks_from_wp() {
		if ( ! self::can_use_wp_posts() ) { return array(); }
		$posts = get_posts( array( 'post_type' => self::CONTENT_BLOCK_POST_TYPE, 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$blocks = array();
		foreach ( $posts as $post ) {
			$id = (string) $post->ID;
			$slug = (string) get_post_meta( $post->ID, '_taka_block_slug', true );
			if ( '' === $slug ) { $slug = (string) get_post_meta( $post->ID, '_taka_config_id', true ); }
			if ( '' === $slug ) { $slug = $post->post_name; }
			$gallery_ids = self::csv_to_ints( get_post_meta( $post->ID, '_taka_gallery_image_ids', true ) );
			$gallery_urls = self::lines_to_array( get_post_meta( $post->ID, '_taka_gallery_image_urls', true ) );
			$block = array(
				'id' => $id,
				'config_id' => $slug,
				'slug' => $slug,
				'internal_name' => get_the_title( $post ),
				'type' => sanitize_key( get_post_meta( $post->ID, '_taka_block_type', true ) ?: 'generic' ),
				'category' => (string) get_post_meta( $post->ID, '_taka_category', true ),
				'source_language' => (string) get_post_meta( $post->ID, '_taka_source_language', true ),
				'text_translations' => self::normalize_object_text_translations( get_post_meta( $post->ID, '_taka_text_translations', true ), self::content_block_text_fields() ),
				'kicker' => (string) get_post_meta( $post->ID, '_taka_kicker', true ),
				'title' => (string) get_post_meta( $post->ID, '_taka_block_title', true ),
				'subtitle' => (string) get_post_meta( $post->ID, '_taka_subtitle', true ),
				'body' => $post->post_content,
				'button_label' => (string) get_post_meta( $post->ID, '_taka_button_label', true ),
				'button_url' => (string) get_post_meta( $post->ID, '_taka_button_url', true ),
				'image_id' => absint( get_post_meta( $post->ID, '_taka_image_id', true ) ),
				'image_url' => (string) get_post_meta( $post->ID, '_taka_image_url', true ),
				'gallery_image_ids' => $gallery_ids,
				'gallery_image_urls' => $gallery_urls,
				'enabled' => 'draft' !== $post->post_status && ( '' === (string) get_post_meta( $post->ID, '_taka_enabled', true ) || '1' === (string) get_post_meta( $post->ID, '_taka_enabled', true ) ) ? '1' : '0',
				'notes' => (string) get_post_meta( $post->ID, '_taka_notes', true ),
				'created_at' => $post->post_date_gmt,
				'updated_at' => $post->post_modified_gmt,
			);
			$blocks[ $id ] = $block;
			if ( '' !== $slug ) { $blocks[ $slug ] = $block; }
		}
		return $blocks;
	}


	/** Event-level organizer relationship type labels. */
	public static function organizer_relationship_type_labels( $lang = null ) {
		$lang = $lang ?: taka_tour_current_language();
		return array(
			'organizer' => taka_tour_translate( 'event.organizer', 'Organizer', $lang ),
			'co_organizer' => taka_tour_translate( 'event.relationship_co_organizer', 'Co-organizer', $lang ),
			'host' => taka_tour_translate( 'event.relationship_host', 'Host', $lang ),
			'supporting_organizer' => taka_tour_translate( 'event.relationship_supporting_organizer', 'Supporting organizer', $lang ),
			'partner' => taka_tour_translate( 'event.relationship_partner', 'Partner', $lang ),
		);
	}

	/** Normalize event organizer relationships from WP meta or config. */
	public static function normalize_event_organizer_relationships( $items, $legacy_organizer = 0 ) {
		if ( ! is_array( $items ) ) { $items = array(); }
		$clean = array();
		$seen = array();
		$allowed = array_keys( self::organizer_relationship_type_labels( 'en' ) );
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$organizer_id = sanitize_text_field( (string) ( $item['organizer_id'] ?? ( $item['id'] ?? '' ) ) );
			$type = sanitize_key( $item['relationship_type'] ?? 'organizer' );
			$type = in_array( $type, $allowed, true ) ? $type : 'organizer';
			if ( '' === $organizer_id || '0' === $organizer_id ) { continue; }
			$key = $organizer_id . '|' . $type;
			if ( isset( $seen[ $key ] ) ) { continue; }
			$seen[ $key ] = true;
			$clean[] = array(
				'organizer_id' => $organizer_id,
				'relationship_type' => $type,
				'custom_label' => sanitize_text_field( (string) ( $item['custom_label'] ?? '' ) ),
				'visible' => array_key_exists( 'visible', $item ) ? ( ! empty( $item['visible'] ) ? 1 : 0 ) : 1,
				'sort_order' => (int) ( $item['sort_order'] ?? 0 ),
			);
		}
		if ( empty( $clean ) && '' !== (string) $legacy_organizer && '0' !== (string) $legacy_organizer ) {
			$clean[] = array( 'organizer_id' => (string) $legacy_organizer, 'relationship_type' => 'organizer', 'custom_label' => '', 'visible' => 1, 'sort_order' => 10 );
		}
		usort( $clean, static function ( $a, $b ) { return ( (int) $a['sort_order'] <=> (int) $b['sort_order'] ) ?: strcmp( (string) $a['relationship_type'], (string) $b['relationship_type'] ); } );
		return $clean;
	}

	/** Load seed/fallback tour configuration. */
	public static function load_config() {
		static $config = null;
		if ( null === $config ) {
			$path = TAKA_TOUR_PLUGIN_DIR . 'config/tour-events.php';
			$config = file_exists( $path ) ? require $path : array();
		}
		return is_array( $config ) ? $config : array();
	}

	/** Check whether WordPress post APIs are available. */
	private static function can_use_wp_posts() {
		return function_exists( 'get_posts' ) && function_exists( 'get_post_meta' );
	}

	/** Count WordPress events regardless of status. */
	public static function count_wp_events() {
		return self::count_posts( self::EVENT_POST_TYPE, 'any' );
	}

	/** Count config events. */
	public static function count_config_events() {
		$config = self::load_config();
		return count( $config['events'] ?? array() );
	}

	/** Current live data source label. */
	public static function get_active_data_source() {
		return self::is_using_wp_events() ? 'database' : 'config_fallback';
	}

	/** Whether frontend data is currently sourced from WordPress events. */
	public static function is_using_wp_events() {
		return self::count_wp_events() > 0;
	}

	/** Whether all required platform post types are registered in WordPress. */
	public static function required_post_types_registered() {
		if ( ! function_exists( 'post_type_exists' ) ) { return false; }
		foreach ( array_keys( self::required_post_types() ) as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) { return false; }
		}
		return true;
	}

	/** Data-source and CPT health summary for admin/debug views. */
	public static function data_source_status() {
		$post_types = array();
		foreach ( self::required_post_types() as $post_type => $label ) {
			$post_types[ $post_type ] = array(
				'label' => $label,
				'registered' => function_exists( 'post_type_exists' ) ? post_type_exists( $post_type ) : false,
				'count_any' => self::count_posts( $post_type, 'any' ),
				'count_publish' => self::count_posts( $post_type, 'publish' ),
			);
		}
		return array(
			'active_source' => self::get_active_data_source(),
			'using_database' => self::is_using_wp_events(),
			'using_config_fallback' => ! self::is_using_wp_events(),
			'wp_event_count' => self::count_wp_events(),
			'wp_published_event_count' => self::count_posts( self::EVENT_POST_TYPE, 'publish' ),
			'config_event_count' => self::count_config_events(),
			'required_post_types_registered' => self::required_post_types_registered(),
			'post_types' => $post_types,
		);
	}

	/** Count posts safely. */
	private static function count_posts( $post_type, $status = 'publish' ) {
		if ( ! self::can_use_wp_posts() ) { return 0; }
		$posts = get_posts( array( 'post_type' => $post_type, 'post_status' => $status, 'posts_per_page' => -1, 'fields' => 'ids' ) );
		return is_array( $posts ) ? count( $posts ) : 0;
	}

	/** Get all organizers, WordPress first with config fallback. */
	public static function get_organizers() {
		$wp = self::load_organizers_from_wp();
		if ( ! empty( $wp ) ) { return $wp; }
		$config = self::load_config();
		return self::normalize_config_organizers( $config['organizers'] ?? array() );
	}

	/** Get one organizer by internal ID/config ID. */
	public static function get_organizer( $id ) {
		$organizers = self::get_organizers();
		return $organizers[ (string) $id ] ?? null;
	}

	/** Get all venues, WordPress first with config fallback. */
	public static function get_venues() {
		$wp = self::load_venues_from_wp();
		if ( ! empty( $wp ) ) { return $wp; }
		$config = self::load_config();
		return self::normalize_config_venues( $config['venues'] ?? array() );
	}

	/** Get one venue by internal ID/config ID. */
	public static function get_venue( $id ) {
		$venues = self::get_venues();
		return $venues[ (string) $id ] ?? null;
	}

	/** Get events, WordPress first if any WP event exists, otherwise config fallback. */
	public static function get_events() {
		if ( self::is_using_wp_events() ) { return self::load_events_from_wp(); }
		$config = self::load_config();
		return self::normalize_config_events( $config['events'] ?? array() );
	}

	/** Get one event by ID or slug. */
	public static function get_event( $id ) {
		foreach ( self::get_events() as $event ) {
			if ( (string) $id === (string) ( $event['id'] ?? '' ) || (string) $id === (string) ( $event['slug'] ?? '' ) ) { return $event; }
		}
		return null;
	}

	/** Get public, sorted events. */
	public static function get_public_events() {
		$events = array_values( array_filter( self::get_events(), static function ( $event ) { return 'draft' !== ( $event['status'] ?? '' ); } ) );
		usort( $events, array( __CLASS__, 'compare_events' ) );
		return apply_filters( 'taka_platform_events', $events );
	}

	/** Sort callback. */
	private static function compare_events( $a, $b ) {
		$date_compare = strcmp( self::event_sort_date( $a ), self::event_sort_date( $b ) );
		if ( 0 !== $date_compare ) { return $date_compare; }
		$time_compare = strcmp( self::event_sort_time( $a ), self::event_sort_time( $b ) );
		if ( 0 !== $time_compare ) { return $time_compare; }
		$sort_compare = (int) ( $a['sort_order'] ?? 0 ) <=> (int) ( $b['sort_order'] ?? 0 );
		if ( 0 !== $sort_compare ) { return $sort_compare; }
		return strcmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) );
	}

	private static function event_sort_date( $event ) {
		$date = (string) ( $event['date_start'] ?? '' );
		if ( '' !== $date ) { return $date; }
		$items = self::normalize_program_items( $event['program_items'] ?? array(), $event );
		$date = (string) ( $items[0]['date'] ?? '' );
		return '' !== $date ? $date : '9999-12-31';
	}

	private static function event_sort_time( $event ) {
		$time = (string) ( $event['time_start'] ?? '' );
		if ( '' !== $time ) { return $time; }
		$items = self::normalize_program_items( $event['program_items'] ?? array(), $event );
		return (string) ( $items[0]['time_start'] ?? '' );
	}

	/** Get public events by organizer. */
	public static function get_events_by_organizer( $organizer_id ) {
		return array_values( array_filter( self::get_public_events(), static function ( $event ) use ( $organizer_id ) { if ( (string) $organizer_id === (string) ( $event['organizer'] ?? '' ) ) { return true; } foreach ( $event['organizers'] ?? array() as $relationship ) { if ( (string) $organizer_id === (string) ( $relationship['organizer_id'] ?? '' ) ) { return true; } } return false; } ) );
	}

	/** Get public events by venue. */
	public static function get_events_by_venue( $venue_id ) {
		return array_values( array_filter( self::get_public_events(), static function ( $event ) use ( $venue_id ) { return (string) $venue_id === (string) ( $event['venue'] ?? '' ) || in_array( (string) $venue_id, $event['venues'] ?? array(), true ); } ) );
	}

	/** Backward-compatible seminar accessor. */
	public static function seminars() { return self::get_public_events(); }

	/** Load organizers from WordPress. */
	private static function load_organizers_from_wp() {
		if ( ! self::can_use_wp_posts() ) { return array(); }
		$posts = get_posts( array( 'post_type' => self::ORGANIZER_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$items = array();
		foreach ( $posts as $post ) {
			$id = (string) $post->ID;
			$config_id = (string) get_post_meta( $post->ID, '_taka_config_id', true );
			$logo_id = absint( get_post_meta( $post->ID, '_taka_logo_id', true ) );
			$item = array(
				'id' => $id,
				'config_id' => $config_id,
				'name' => get_the_title( $post ),
				'source_language' => (string) get_post_meta( $post->ID, '_taka_source_language', true ),
				'text_translations' => self::normalize_object_text_translations( get_post_meta( $post->ID, '_taka_text_translations', true ), self::translatable_text_fields( 'organizer' ) ),
				'legal_name' => (string) get_post_meta( $post->ID, '_taka_legal_name', true ),
				'website' => (string) get_post_meta( $post->ID, '_taka_website', true ),
				'country' => (string) get_post_meta( $post->ID, '_taka_country', true ),
				'country_code' => (string) get_post_meta( $post->ID, '_taka_country_code', true ),
				'flag' => (string) get_post_meta( $post->ID, '_taka_flag', true ),
				'logo_id' => $logo_id,
				'logo_url' => self::resolve_attachment_url( $logo_id, 'large', (string) get_post_meta( $post->ID, '_taka_logo_url', true ) ),
				'logo' => self::resolve_attachment_url( $logo_id, 'large', (string) get_post_meta( $post->ID, '_taka_logo_url', true ) ),
				'emails' => self::lines_to_array( get_post_meta( $post->ID, '_taka_emails', true ) ),
				'contact_persons' => self::lines_to_array( get_post_meta( $post->ID, '_taka_contact_persons', true ) ),
				'social_links' => array( 'instagram' => (string) get_post_meta( $post->ID, '_taka_instagram', true ), 'facebook' => (string) get_post_meta( $post->ID, '_taka_facebook', true ), 'youtube' => (string) get_post_meta( $post->ID, '_taka_youtube', true ) ),
				'social' => array( 'instagram' => (string) get_post_meta( $post->ID, '_taka_instagram', true ), 'facebook' => (string) get_post_meta( $post->ID, '_taka_facebook', true ), 'youtube' => (string) get_post_meta( $post->ID, '_taka_youtube', true ) ),
				'description' => $post->post_content,
				'co_organizers' => self::normalize_co_organizers( get_post_meta( $post->ID, '_taka_platform_co_organizers', true ) ),
				'active' => '' === (string) get_post_meta( $post->ID, '_taka_active', true ) || '1' === (string) get_post_meta( $post->ID, '_taka_active', true ),
			);
			$items[ $id ] = $item;
			if ( '' !== $config_id ) { $items[ $config_id ] = $item; }
		}
		return $items;
	}

	/** Load venues from WordPress. */
	private static function load_venues_from_wp() {
		if ( ! self::can_use_wp_posts() ) { return array(); }
		$posts = get_posts( array( 'post_type' => self::VENUE_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$items = array();
		foreach ( $posts as $post ) {
			$id = (string) $post->ID;
			$config_id = (string) get_post_meta( $post->ID, '_taka_config_id', true );
			$image_id = absint( get_post_meta( $post->ID, '_taka_image_id', true ) );
			$item = array(
				'id' => $id,
				'config_id' => $config_id,
				'name' => get_the_title( $post ),
				'source_language' => (string) get_post_meta( $post->ID, '_taka_source_language', true ),
				'text_translations' => self::normalize_object_text_translations( get_post_meta( $post->ID, '_taka_text_translations', true ), self::translatable_text_fields( 'venue' ) ),
				'address' => array( 'street' => (string) get_post_meta( $post->ID, '_taka_street', true ), 'postal_code' => (string) get_post_meta( $post->ID, '_taka_postal_code', true ), 'city' => (string) get_post_meta( $post->ID, '_taka_city', true ), 'country' => (string) get_post_meta( $post->ID, '_taka_country', true ), 'country_code' => (string) get_post_meta( $post->ID, '_taka_country_code', true ) ),
				'flag' => (string) get_post_meta( $post->ID, '_taka_flag', true ),
				'route_map_x' => self::nullable_meta( $post->ID, 'route_map_x' ),
				'route_map_y' => self::nullable_meta( $post->ID, 'route_map_y' ),
				'map_x' => self::nullable_meta( $post->ID, 'map_x' ),
				'map_y' => self::nullable_meta( $post->ID, 'map_y' ),
				'route_map_label' => (string) get_post_meta( $post->ID, '_taka_route_map_label', true ),
				'map_label' => (string) get_post_meta( $post->ID, '_taka_map_label', true ),
				'timezone' => (string) get_post_meta( $post->ID, '_taka_timezone', true ),
				'website' => (string) get_post_meta( $post->ID, '_taka_website', true ),
				'parking' => (string) get_post_meta( $post->ID, '_taka_parking', true ),
				'accessibility' => (string) get_post_meta( $post->ID, '_taka_accessibility', true ),
				'notes' => (string) get_post_meta( $post->ID, '_taka_notes', true ),
				'geo' => array( 'lat' => self::nullable_meta( $post->ID, 'lat' ), 'lng' => self::nullable_meta( $post->ID, 'lng' ) ),
				'image_id' => $image_id,
				'image_url' => self::resolve_attachment_url( $image_id, 'large', (string) get_post_meta( $post->ID, '_taka_image_url', true ) ),
				'image' => self::resolve_attachment_url( $image_id, 'large', (string) get_post_meta( $post->ID, '_taka_image_url', true ) ),
				'parking_image_id' => absint( get_post_meta( $post->ID, '_taka_parking_image_id', true ) ),
				'parking_image_url' => self::resolve_attachment_url( absint( get_post_meta( $post->ID, '_taka_parking_image_id', true ) ), 'large', (string) get_post_meta( $post->ID, '_taka_parking_image_url', true ) ),
				'gallery_image_ids' => self::csv_to_ints( get_post_meta( $post->ID, '_taka_gallery_image_ids', true ) ),
			);
			$items[ $id ] = $item;
			if ( '' !== $config_id ) { $items[ $config_id ] = $item; }
		}
		return $items;
	}

	/** Load published events from WordPress in config-compatible format. */
	private static function load_events_from_wp() {
		if ( ! self::can_use_wp_posts() ) { return array(); }
		$posts = get_posts( array( 'post_type' => self::EVENT_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$events = array();
		foreach ( $posts as $post ) {
			$image_id = absint( get_post_meta( $post->ID, '_taka_image_id', true ) );
			$venue_id = (string) absint( get_post_meta( $post->ID, '_taka_venue_id', true ) );
			$additional_venues = array_map( 'strval', self::csv_to_ints( get_post_meta( $post->ID, '_taka_venue_ids', true ) ) );
			$venues = array_values( array_unique( array_filter( array_merge( array( $venue_id ), $additional_venues ) ) ) );
			$legacy_organizer_id = (string) absint( get_post_meta( $post->ID, '_taka_organizer_id', true ) );
			$organizer_relationships = self::normalize_event_organizer_relationships( get_post_meta( $post->ID, '_taka_event_organizers', true ), $legacy_organizer_id );
			$events[] = array(
				'id' => (string) $post->ID,
				'config_id' => (string) get_post_meta( $post->ID, '_taka_config_id', true ),
				'slug' => $post->post_name,
				'source_language' => (string) get_post_meta( $post->ID, '_taka_source_language', true ),
				'text_translations' => self::normalize_object_text_translations( get_post_meta( $post->ID, '_taka_text_translations', true ), self::translatable_text_fields( 'event' ) ),
				'title' => get_the_title( $post ),
				'subtitle' => (string) get_post_meta( $post->ID, '_taka_subtitle', true ),
				'description' => (string) get_post_meta( $post->ID, '_taka_short_description', true ) ?: $post->post_content,
				'long_description' => (string) get_post_meta( $post->ID, '_taka_long_description', true ),
				'ticket_card_text' => (string) get_post_meta( $post->ID, '_taka_ticket_card_text', true ),
					'ticket_tab_label' => (string) get_post_meta( $post->ID, '_taka_ticket_tab_label', true ),
				'booking_information' => self::event_booking_information_from_meta( $post->ID ),
				'content_references' => array(
					'event_description' => self::normalize_content_reference( get_post_meta( $post->ID, '_taka_content_reference_event_description', true ), 'event_description' ),
				),
				'country' => (string) get_post_meta( $post->ID, '_taka_country', true ),
				'country_code' => (string) get_post_meta( $post->ID, '_taka_country_code', true ),
				'flag' => (string) get_post_meta( $post->ID, '_taka_flag', true ),
				'route_map_x' => self::nullable_meta( $post->ID, 'route_map_x' ),
				'route_map_y' => self::nullable_meta( $post->ID, 'route_map_y' ),
				'map_x' => self::nullable_meta( $post->ID, 'map_x' ),
				'map_y' => self::nullable_meta( $post->ID, 'map_y' ),
				'route_map_label' => (string) get_post_meta( $post->ID, '_taka_route_map_label', true ),
				'map_label' => (string) get_post_meta( $post->ID, '_taka_map_label', true ),
				'route_order' => self::nullable_meta( $post->ID, 'route_order' ),
				'city' => (string) get_post_meta( $post->ID, '_taka_city', true ),
				'date_start' => (string) get_post_meta( $post->ID, '_taka_date_start', true ),
				'date_end' => (string) get_post_meta( $post->ID, '_taka_date_end', true ),
				'time_start' => (string) get_post_meta( $post->ID, '_taka_time_start', true ),
				'time_end' => (string) get_post_meta( $post->ID, '_taka_time_end', true ),
				'doors_open' => (string) get_post_meta( $post->ID, '_taka_doors_open', true ),
				'program_items' => self::normalize_program_items( get_post_meta( $post->ID, '_taka_program_items', true ) ),
				'timezone' => (string) get_post_meta( $post->ID, '_taka_timezone', true ),
				'organizer' => $legacy_organizer_id,
				'organizers' => $organizer_relationships,
				'venue' => $venue_id,
				'venues' => $venues,
				'format' => (string) get_post_meta( $post->ID, '_taka_format', true ),
				'audience' => (string) get_post_meta( $post->ID, '_taka_audience', true ),
				'level' => (string) get_post_meta( $post->ID, '_taka_level', true ),
				'status' => 'confirmed',
				'ticket_status' => (string) get_post_meta( $post->ID, '_taka_ticket_status', true ),
				'ticket_provider' => strtolower( (string) get_post_meta( $post->ID, '_taka_ticket_provider', true ) ),
				'ticket_shop_url' => (string) get_post_meta( $post->ID, '_taka_ticket_shop_url', true ),
				'image_id' => $image_id,
				'image_url' => (string) get_post_meta( $post->ID, '_taka_image_url', true ),
				'image' => self::resolve_attachment_url( $image_id, 'large', (string) get_post_meta( $post->ID, '_taka_image_url', true ) ),
				'group_image_id' => absint( get_post_meta( $post->ID, '_taka_group_image_id', true ) ),
				'group_image_url' => self::resolve_attachment_url( absint( get_post_meta( $post->ID, '_taka_group_image_id', true ) ), 'large', (string) get_post_meta( $post->ID, '_taka_group_image_url', true ) ),
				'past_group_photo_id' => absint( get_post_meta( $post->ID, '_taka_group_image_id', true ) ),
				'past_group_photo_url' => self::resolve_attachment_url( absint( get_post_meta( $post->ID, '_taka_group_image_id', true ) ), 'large', (string) get_post_meta( $post->ID, '_taka_group_image_url', true ) ),
				'gallery_image_ids' => self::csv_to_ints( get_post_meta( $post->ID, '_taka_gallery_image_ids', true ) ),
				'gallery_urls' => self::attachment_urls( self::csv_to_ints( get_post_meta( $post->ID, '_taka_gallery_image_ids', true ) ) ),
				'gallery' => self::attachment_urls( self::csv_to_ints( get_post_meta( $post->ID, '_taka_gallery_image_ids', true ) ) ),
				'photo_credit' => (string) get_post_meta( $post->ID, '_taka_photo_credit', true ),
				'languages' => self::csv_to_strings( get_post_meta( $post->ID, '_taka_languages', true ) ),
				'notes' => (string) get_post_meta( $post->ID, '_taka_notes', true ),
				'accessibility' => (string) get_post_meta( $post->ID, '_taka_accessibility', true ),
				'parking' => (string) get_post_meta( $post->ID, '_taka_parking', true ),
				'sort_order' => (int) get_post_meta( $post->ID, '_taka_sort_order', true ),
			);
		}
		return $events;
	}

	/** Normalize config organizers. */
	private static function normalize_config_organizers( $organizers ) {
		$items = array();
		foreach ( $organizers as $id => $item ) { $item['id'] = (string) $id; $item['config_id'] = (string) $id; $item['source_language'] = $item['source_language'] ?? self::platform_fallback_language(); $item['text_translations'] = self::normalize_object_text_translations( $item['text_translations'] ?? array(), self::translatable_text_fields( 'organizer' ) ); $item['logo_url'] = $item['logo'] ?? ''; $item['logo_id'] = 0; $item['country'] = $item['country'] ?? ''; $item['country_code'] = $item['country_code'] ?? ''; $item['flag'] = $item['flag'] ?? ''; $item['social_links'] = $item['social'] ?? array(); $item['description'] = $item['description'] ?? ''; $item['co_organizers'] = self::normalize_co_organizers( $item['co_organizers'] ?? array() ); $item['active'] = $item['active'] ?? true; $items[ (string) $id ] = $item; }
		return $items;
	}

	/** Normalize config venues. */
	private static function normalize_config_venues( $venues ) {
		$items = array();
		foreach ( $venues as $id => $item ) { $item['id'] = (string) $id; $item['config_id'] = (string) $id; $item['source_language'] = $item['source_language'] ?? self::platform_fallback_language(); $item['text_translations'] = self::normalize_object_text_translations( $item['text_translations'] ?? array(), self::translatable_text_fields( 'venue' ) ); $item['flag'] = $item['flag'] ?? ''; $item['route_map_x'] = $item['route_map_x'] ?? ( $item['map_x'] ?? null ); $item['route_map_y'] = $item['route_map_y'] ?? ( $item['map_y'] ?? null ); $item['route_map_label'] = $item['route_map_label'] ?? ( $item['map_label'] ?? '' ); $item['image_id'] = $item['image_id'] ?? 0; $item['image_url'] = $item['image_url'] ?? ( $item['image'] ?? '' ); $item['parking_image_id'] = $item['parking_image_id'] ?? 0; $item['parking_image_url'] = $item['parking_image_url'] ?? ''; $item['gallery_image_ids'] = $item['gallery_image_ids'] ?? array(); $items[ (string) $id ] = $item; }
		return $items;
	}

	/** Normalize config events. */
	private static function normalize_config_events( $events ) {
		return array_map( static function ( $event ) { $event['source_language'] = $event['source_language'] ?? self::platform_fallback_language(); $event['text_translations'] = self::normalize_object_text_translations( $event['text_translations'] ?? array(), self::translatable_text_fields( 'event' ) ); $event['long_description'] = $event['long_description'] ?? ''; $event['ticket_card_text'] = $event['ticket_card_text'] ?? ''; $event['accessibility'] = $event['accessibility'] ?? ''; $event['route_map_x'] = $event['route_map_x'] ?? ( $event['map_x'] ?? null ); $event['route_map_y'] = $event['route_map_y'] ?? ( $event['map_y'] ?? null ); $event['route_map_label'] = $event['route_map_label'] ?? ( $event['map_label'] ?? '' ); $event['route_order'] = $event['route_order'] ?? null; $event['image_id'] = $event['image_id'] ?? 0; $event['image_url'] = $event['image_url'] ?? ( $event['image'] ?? '' ); $event['group_image_id'] = $event['group_image_id'] ?? ( $event['past_group_photo_id'] ?? 0 ); $event['group_image_url'] = $event['group_image_url'] ?? ( $event['group_image'] ?? ( $event['past_group_photo_url'] ?? '' ) ); $event['past_group_photo_id'] = $event['past_group_photo_id'] ?? $event['group_image_id']; $event['past_group_photo_url'] = $event['past_group_photo_url'] ?? $event['group_image_url']; $event['gallery_image_ids'] = $event['gallery_image_ids'] ?? array(); $event['gallery_urls'] = $event['gallery'] ?? array(); $event['booking_information'] = self::normalize_booking_information( $event['booking_information'] ?? array(), false ); $event['content_references'] = is_array( $event['content_references'] ?? null ) ? $event['content_references'] : array(); $event['content_references']['event_description'] = self::normalize_content_reference( $event['content_references']['event_description'] ?? array(), 'event_description' ); $event['program_items'] = self::normalize_program_items( $event['program_items'] ?? ( $event['program'] ?? array() ), $event ); $event['organizers'] = self::normalize_event_organizer_relationships( $event['organizers'] ?? array(), $event['organizer'] ?? '' ); return $event; }, $events );
	}

	/** Global media labels. */
	public static function global_media_fields() {
		return array( 'hero_image' => 'Hero image', 'past_group_photo' => 'Past group photo', 'taka_portrait' => 'Taka portrait', 'community_group' => 'Community image', 'kobudo' => 'Kobudo image', 'softblock' => 'Softblock image', 'together_practice' => 'Together practice image', 'kids_group' => 'Kids seminar image', 'kleiner_wald_logo' => 'Kleiner Wald logo', 'sponsor_logo' => 'Sponsor logo' );
	}

	/** Get global media settings option. */
	public static function get_global_media_settings() {
		$settings = function_exists( 'get_option' ) ? get_option( self::MEDIA_OPTION, array() ) : array();
		return is_array( $settings ) ? $settings : array();
	}

	/** Get plugin-managed image URLs with attachment-ID override. */
	public static function images() {
		$fallbacks = array( 'hero_image' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-hero.jpg', 'past_group_photo' => '', 'group_image' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-group.jpg', 'portrait_image' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-portrait.jpg', 'group_large' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/Foto-04.10.23-20-02-21-scaled-1.jpg', 'kids_group' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/Kids-Seminar-Trier.jpeg', 'taka_portrait' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/Taka-Tour-2023-Berlin-Foto-30.09.23-17-00-52-1-scaled-1-e1781613695325.jpg', 'kobudo' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/Kobudo-Seminar-Trier-e1781607374996.jpeg', 'community_group' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-gruppe-trier-2025.jpg', 'together_practice' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-gemeinsam-2025.jpg', 'softblock' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/taka-softblock-e1781607328699.jpeg', 'kleiner_wald_logo' => 'https://takatour.eu/wp-content/uploads/sites/7/2026/06/Logo-Kleiner-Wald.svg', 'sponsor_logo' => '' );
		$settings = self::get_global_media_settings();
		foreach ( $fallbacks as $key => $url ) { $fallbacks[ $key ] = self::resolve_attachment_url( absint( $settings[ $key . '_id' ] ?? 0 ), 'large', (string) ( $settings[ $key . '_url' ] ?? $url ) ?: $url ); }
		return $fallbacks;
	}

	public static function get_media() { return self::images(); }


	/** Default booking-information settings. */
	public static function default_booking_information( $lang = null ) {
		$lang = $lang ?: taka_tour_current_language();
		$is_de = 'de' === $lang;
		return array(
			'enabled' => '1',
			'source_language' => self::platform_fallback_language(),
			'title' => taka_tour_translate( 'booking.before_you_book', $is_de ? 'Gut zu wissen vor der Buchung' : 'Before you book', $lang ),
			'intro' => '',
			'group_booking' => $is_de ? 'Gruppen sind herzlich willkommen. Wenn Sie mehrere Teilnehmer anmelden oder als Verein/Dojo eine gemeinsame Teilnahme organisieren möchten, wenden Sie sich bitte an den jeweiligen Veranstalter.' : 'Groups are very welcome. If you would like to register several participants or organize a club delegation, please contact the local organizer.',
			'multi_event_discount' => $is_de ? 'Sie möchten mehrere Seminare der European Tour besuchen? Bitte kontaktieren Sie uns vor der Buchung. Wir erstellen gerne ein individuelles Angebot.' : 'Planning to attend multiple seminars during the European Tour? Please contact us before booking. We will gladly prepare an individual offer.',
			'contact_email' => '',
			'booking_process' => '',
			'payment_methods' => $is_de ? 'Nach Ihrer Buchung verschicken wir eine Rechnung. Die Zahlung ist per Überweisung, PayPal oder nach Absprache bar möglich. Nur vorab bezahlte Rechnungen berechtigen zur Teilnahme am Seminar.' : 'After your booking, we will send you an invoice. Payment is possible by bank transfer, PayPal or cash if agreed with the organizer. Only paid invoices confirm participation in the seminar.',
			'cancellation_policy' => $is_de ? "bis 40 Tage vor dem Seminar: kostenloser Rücktritt\n39–30 Tage vor dem Seminar: 75% Rückerstattung\n29–14 Tage vor dem Seminar: 40% Rückerstattung\nweniger als 14 Tage vor dem Seminar: leider keine Rückerstattung" : "until 40 days before the seminar: free cancellation\n39–30 days before the seminar: 75% refund\n29–14 days before the seminar: 40% refund\nless than 14 days before the seminar: unfortunately no refund",
			'additional_notes' => '',
		);
	}

	/** Get global booking-information settings. */
	public static function get_booking_information_settings( $lang = null ) {
		$stored = function_exists( 'get_option' ) ? get_option( self::BOOKING_OPTION, array() ) : array();
		return self::normalize_booking_information( array_merge( self::default_booking_information( $lang ), is_array( $stored ) ? $stored : array() ), true );
	}

	/** Prepare booking information for one event with organizer fallback. */
	private static function booking_information_for_event( $event, $organizer, $lang, $organizer_relationships = array() ) {
		$booking = self::get_booking_information_settings( $lang );
		$override = self::normalize_booking_information( $event['booking_information'] ?? array(), false );
		if ( ! empty( $override['override'] ) ) {
			$booking = array_merge( $booking, array_filter( $override, static function ( $value ) { return is_array( $value ) ? '' !== trim( implode( '', array_map( 'strval', $value ) ) ) : '' !== trim( (string) $value ); } ) );
			$booking['enabled'] = $override['enabled'] ?? $booking['enabled'];
		}

		$lang_defaults = self::default_booking_information( $lang );
		$en_defaults = self::default_booking_information( 'en' );
		$source_language = $booking['source_language'] ?? self::platform_fallback_language();
		foreach ( array( 'title', 'intro', 'group_booking', 'multi_event_discount', 'booking_process', 'payment_methods', 'cancellation_policy', 'additional_notes' ) as $field ) {
			$value = $booking[ $field ] ?? '';
			if ( is_array( $value ) ) {
				$booking[ $field ] = self::resolve_dynamic_text( $value, $lang, $source_language );
			} elseif ( '' !== (string) ( $en_defaults[ $field ] ?? '' ) && (string) $value === (string) $en_defaults[ $field ] ) {
				$booking[ $field ] = (string) ( $lang_defaults[ $field ] ?? $value );
			} else {
				$booking[ $field ] = (string) $value;
			}
		}

		if ( empty( $booking['title'] ) ) {
			$booking['title'] = taka_tour_translate( 'booking.before_you_book', 'Before you book', $lang );
		}

		$booking['contact_email'] = self::booking_contact_email_for_event( $organizer, $organizer_relationships );

		$sections = array(
			array( 'key' => 'intro', 'title' => '', 'text' => $booking['intro'] ?? '' ),
			array( 'key' => 'group_booking', 'title' => taka_tour_translate( 'booking.groups_clubs', 'Groups & clubs', $lang ), 'text' => $booking['group_booking'] ?? '' ),
			array( 'key' => 'multi_event_discount', 'title' => taka_tour_translate( 'booking.multiple_seminars', 'Multiple seminars', $lang ), 'text' => $booking['multi_event_discount'] ?? '' ),
			array( 'key' => 'booking_process', 'title' => taka_tour_translate( 'booking.booking_process', 'Booking process', $lang ), 'text' => $booking['booking_process'] ?? '' ),
			array( 'key' => 'payment_methods', 'title' => taka_tour_translate( 'booking.payment', 'Payment', $lang ), 'text' => $booking['payment_methods'] ?? '' ),
			array( 'key' => 'cancellation_policy', 'title' => taka_tour_translate( 'booking.cancellation', 'Cancellation', $lang ), 'text' => $booking['cancellation_policy'] ?? '', 'list' => self::lines_to_array( $booking['cancellation_policy'] ?? '' ) ),
			array( 'key' => 'additional_notes', 'title' => taka_tour_translate( 'event.notes', 'Notes', $lang ), 'text' => $booking['additional_notes'] ?? '' ),
		);
		$booking['sections'] = array_values( array_filter( $sections, static function ( $section ) { return '' !== trim( (string) ( $section['text'] ?? '' ) ); } ) );
		return $booking;
	}

	/** Resolve the best booking contact email for an event. */
	private static function booking_contact_email_for_event( $primary_organizer, $organizer_relationships = array() ) {
		$email = self::first_organizer_email( $primary_organizer );
		if ( '' !== $email ) {
			return $email;
		}

		$organizers = array();
		if ( is_array( $primary_organizer ) ) {
			$organizers[] = $primary_organizer;
		}
		foreach ( (array) $organizer_relationships as $relationship ) {
			$organizer = is_array( $relationship ) ? ( $relationship['organizer'] ?? null ) : null;
			if ( is_array( $organizer ) ) {
				$organizers[] = $organizer;
				$email = self::first_organizer_email( $organizer );
				if ( '' !== $email ) {
					return $email;
				}
			}
		}

		foreach ( $organizers as $organizer ) {
			foreach ( self::normalize_co_organizers( $organizer['co_organizers'] ?? array() ) as $co_organizer ) {
				if ( empty( $co_organizer['active'] ) ) {
					continue;
				}
				$email = sanitize_email( $co_organizer['email'] ?? '' );
				if ( '' !== $email ) {
					return $email;
				}
			}
		}

		return 'kontakt@kleiner-wald.de';
	}

	/** Return the first valid direct email on an organizer profile. */
	private static function first_organizer_email( $organizer ) {
		if ( ! is_array( $organizer ) ) {
			return '';
		}

		foreach ( (array) ( $organizer['emails'] ?? array() ) as $email ) {
			$email = sanitize_email( $email );
			if ( '' !== $email ) {
				return $email;
			}
		}

		return '';
	}

	/** Normalize booking information arrays. */
	private static function normalize_booking_information( $booking, $include_defaults = true ) {
		$booking = is_array( $booking ) ? $booking : array();
		$defaults = array( 'enabled' => '1', 'override' => '', 'source_language' => self::platform_fallback_language(), 'title' => '', 'intro' => '', 'group_booking' => '', 'multi_event_discount' => '', 'contact_email' => '', 'booking_process' => '', 'payment_methods' => '', 'cancellation_policy' => '', 'additional_notes' => '' );
		if ( $include_defaults ) {
			$booking = array_merge( $defaults, $booking );
		} else {
			$booking = array_merge( array( 'override' => '' ), $booking );
		}
		foreach ( array( 'enabled', 'override' ) as $key ) {
			if ( isset( $booking[ $key ] ) ) { $booking[ $key ] = ! empty( $booking[ $key ] ) ? '1' : '0'; }
		}
		$booking['source_language'] = in_array( $booking['source_language'] ?? '', self::content_section_languages(), true ) ? sanitize_key( $booking['source_language'] ) : self::platform_fallback_language();
		foreach ( array( 'title', 'intro', 'group_booking', 'multi_event_discount', 'booking_process', 'payment_methods', 'cancellation_policy', 'additional_notes' ) as $key ) {
			if ( isset( $booking[ $key ] ) ) { $booking[ $key ] = self::normalize_dynamic_text_value( $booking[ $key ] ); }
		}
		return $booking;
	}

	/** Read event-specific booking-information override fields. */
	private static function event_booking_information_from_meta( $post_id ) {
		return self::normalize_booking_information( array(
			'override' => (string) get_post_meta( $post_id, '_taka_booking_info_override', true ),
			'enabled' => '' === (string) get_post_meta( $post_id, '_taka_booking_info_enabled', true ) ? '1' : (string) get_post_meta( $post_id, '_taka_booking_info_enabled', true ),
			'title' => (string) get_post_meta( $post_id, '_taka_booking_info_title', true ),
			'intro' => (string) get_post_meta( $post_id, '_taka_booking_info_intro', true ),
			'group_booking' => (string) get_post_meta( $post_id, '_taka_booking_info_group_booking', true ),
			'multi_event_discount' => (string) get_post_meta( $post_id, '_taka_booking_info_multi_event_discount', true ),
			'contact_email' => (string) get_post_meta( $post_id, '_taka_booking_info_contact_email', true ),
			'booking_process' => (string) get_post_meta( $post_id, '_taka_booking_info_booking_process', true ),
			'payment_methods' => (string) get_post_meta( $post_id, '_taka_booking_info_payment_methods', true ),
			'cancellation_policy' => (string) get_post_meta( $post_id, '_taka_booking_info_cancellation_policy', true ),
			'additional_notes' => (string) get_post_meta( $post_id, '_taka_booking_info_additional_notes', true ),
		), false );
	}

	/** Default ticket section heading/settings. */
	public static function default_ticket_section_settings( $lang = null ) {
		$lang = $lang ?: taka_tour_current_language();
		return array(
			'source_language' => self::platform_fallback_language(),
			'kicker' => taka_tour_translate( 'tickets.kicker', 'Tickets', $lang ),
			'heading' => taka_tour_translate( 'tickets.heading', 'Book your seminar', $lang ),
			'intro' => taka_tour_translate( 'tickets.intro', '', $lang ),
			'show_seminar_overview' => '0',
		);
	}

	/** Get editable ticket section heading/settings. */
	public static function get_ticket_section_settings( $lang = null, $resolve_translations = true ) {
		$lang = $lang ?: taka_tour_current_language();
		$stored = function_exists( 'get_option' ) ? get_option( self::TICKETS_OPTION, array() ) : array();
		$settings = array_merge( self::default_ticket_section_settings( $lang ), is_array( $stored ) ? $stored : array() );
		$settings['source_language'] = in_array( $settings['source_language'] ?? '', self::content_section_languages(), true ) ? sanitize_key( $settings['source_language'] ) : self::platform_fallback_language();
		if ( $resolve_translations ) {
			$settings['kicker'] = self::translated_setting_value( $settings['kicker'] ?? '', 'tickets.kicker', 'Tickets', $lang, $settings['source_language'] );
			$settings['heading'] = self::translated_setting_value( $settings['heading'] ?? '', 'tickets.heading', 'Book your seminar', $lang, $settings['source_language'] );
			$settings['intro'] = self::translated_setting_value( $settings['intro'] ?? '', 'tickets.intro', '', $lang, $settings['source_language'] );
		}
		$settings['show_seminar_overview'] = ! empty( $settings['show_seminar_overview'] ) ? '1' : '0';
		return $settings;
	}

	/** Resolve dynamic option values while translating unchanged default strings. */
	private static function translated_setting_value( $value, $key, $fallback, $lang, $source_language = 'de' ) {
		if ( is_array( $value ) ) {
			return self::resolve_dynamic_text( $value, $lang, $source_language );
		}
		$scalar = (string) $value;
		$english = taka_tour_translate( $key, $fallback, 'en' );
		if ( '' === trim( $scalar ) || $scalar === $english || $scalar === $fallback ) {
			return taka_tour_translate( $key, $fallback, $lang );
		}
		return $scalar;
	}

	/** Default editable hero settings with translation/config fallbacks. */
	public static function default_hero_settings() {
		$images        = self::images();
		$ticket_target = '#seminar-konz';
		foreach ( self::get_public_events() as $event ) {
			if ( '' !== self::pretix_event_url( $event ) ) {
				$ticket_target = '#seminar-' . ( $event['slug'] ?? '' );
				if ( 'konz' === ( $event['slug'] ?? '' ) ) {
					break;
				}
			}
		}

		return array(
			'kicker'                 => taka_tour_translate( 'hero.kicker', 'TAKA European Tour 2026' ),
			'source_language'        => self::platform_fallback_language(),
			'title'                  => taka_tour_translate( 'hero.headline', 'Harmony in Motion' ),
			'description'            => taka_tour_translate( 'hero.intro', 'Eine europäische Seminarreise mit Takafumi Nakayama Sensei – von Helsinki über Berlin, die Niederlande, Belgien und Luxemburg bis in die Region Trier/Konz.' ),
			'primary_button_label'   => taka_tour_translate( 'hero.primary_button', 'Seminare ansehen' ),
			'primary_button_target'  => '#tickets',
			'secondary_button_label' => '',
			'secondary_button_target'=> $ticket_target,
			'image_id'               => 0,
			'image_url'              => $images['hero_image'] ?? '',
			'overlay_strength'       => '0.78',
			'text_box_enabled'       => '1',
			'text_box_opacity'       => '0.72',
			'text_box_max_width'     => '620px',
			'text_position'          => 'left',
			'vertical_alignment'     => 'center',
			'location_display_mode'  => 'route_map_with_list',
		);
	}

	/** Get editable hero settings. */
	public static function get_hero_settings( $resolve_translations = true ) {
		$settings = function_exists( 'get_option' ) ? get_option( self::HERO_OPTION, array() ) : array();
		$settings = is_array( $settings ) ? $settings : array();
		$merged   = array_merge( self::default_hero_settings(), $settings );
		$lang = taka_tour_current_language();
		$merged['source_language'] = in_array( $merged['source_language'] ?? '', self::content_section_languages(), true ) ? sanitize_key( $merged['source_language'] ) : self::platform_fallback_language();
		if ( $resolve_translations ) {
			foreach ( array( 'kicker', 'title', 'description', 'primary_button_label', 'secondary_button_label' ) as $field ) {
				$merged[ $field ] = self::resolve_dynamic_text( $merged[ $field ] ?? '', $lang, $merged['source_language'] );
			}
			$merged['kicker'] = self::translated_setting_value( $merged['kicker'] ?? '', 'hero.kicker', 'TAKA European Tour 2026', $lang, $merged['source_language'] );
			$merged['title'] = self::translated_setting_value( $merged['title'] ?? '', 'hero.headline', 'Harmony in Motion', $lang, $merged['source_language'] );
			$merged['description'] = self::translated_setting_value( $merged['description'] ?? '', 'hero.intro', '', $lang, $merged['source_language'] );
			$merged['primary_button_label'] = self::translated_setting_value( $merged['primary_button_label'] ?? '', 'hero.primary_button', 'View seminars', $lang, $merged['source_language'] );
		}
		$merged['primary_button_target'] = '#tickets';
		$merged['location_display_mode'] = self::normalize_hero_location_display_mode( $merged['location_display_mode'] ?? 'route_map_with_list' );
		$merged['image'] = self::resolve_attachment_url( absint( $merged['image_id'] ?? 0 ), 'large', (string) ( $merged['image_url'] ?? '' ) );
		return $merged;
	}

	public static function normalize_hero_location_display_mode( $mode ) {
		$mode = sanitize_key( (string) $mode );
		$legacy = array( 'map' => 'route_map', 'map_with_list' => 'route_map_with_list' );
		if ( isset( $legacy[ $mode ] ) ) { return $legacy[ $mode ]; }
		return in_array( $mode, array( 'list', 'flags', 'route_map', 'route_map_with_list' ), true ) ? $mode : 'route_map_with_list';
	}

	/** Languages supported by editable content section translations. */
	public static function content_section_languages() {
		return TAKA_Platform_I18n::instance()->get_all_languages();
	}

	/** Default language used to seed legacy scalar content section fields. */
	public static function default_content_section_language() {
		$languages = self::content_section_languages();
		$locale = function_exists( 'get_locale' ) ? strtolower( substr( (string) get_locale(), 0, 2 ) ) : '';
		return in_array( $locale, $languages, true ) ? $locale : 'de';
	}

	/** Platform fallback language for dynamic translated values. */
	public static function platform_fallback_language() {
		return 'de';
	}

	/** Resolve a scalar or language-keyed dynamic text value. */
	public static function resolve_dynamic_text( $value, $lang = null, $source_language = null ) {
		$lang = $lang ?: taka_tour_current_language();
		$source_language = $source_language ?: self::platform_fallback_language();
		if ( is_array( $value ) ) {
			$languages = array_values( array_unique( array_filter( array_merge( array( $lang, $source_language, self::platform_fallback_language(), 'en' ), self::content_section_languages() ) ) ) );
			foreach ( $languages as $language ) {
				if ( isset( $value[ $language ] ) && '' !== trim( (string) $value[ $language ] ) ) {
					return (string) $value[ $language ];
				}
			}
			foreach ( $value as $candidate ) {
				if ( ! is_array( $candidate ) && '' !== trim( (string) $candidate ) ) { return (string) $candidate; }
			}
			return '';
		}
		return (string) $value;
	}

	/** Source language for one content section. */
	public static function content_section_source_language( $section ) {
		$lang = sanitize_key( (string) ( is_array( $section ) ? ( $section['source_language'] ?? '' ) : '' ) );
		return in_array( $lang, self::content_section_languages(), true ) ? $lang : self::platform_fallback_language();
	}

	/** Save-clean defaults for editable homepage content sections. */
	public static function default_content_sections() {
		$images         = self::images();
		$host_organizer = self::get_organizer( 'kleiner-wald' );
		$sponsor_venue  = self::get_venue( 'kanso-konz' );
		$host_logo      = $host_organizer['logo'] ?? ( $images['kleiner_wald_logo'] ?? '' );

		return array(
			'sensei'   => self::normalize_content_section( array( 'key' => 'sensei', 'visible' => '1', 'sort_order' => 10, 'kicker' => taka_tour_translate( 'sections.sensei.kicker', 'Sensei' ), 'title' => taka_tour_translate( 'sections.sensei.headline', 'Takafumi Nakayama' ), 'subtitle' => '', 'body' => taka_tour_translate( 'sections.sensei.text', 'Präzision, Ruhe und Bewegungsqualität aus der okinawanischen Tradition.' ), 'image_id' => 0, 'image_url' => $images['taka_portrait'] ?? '', 'secondary_image_id' => 0, 'secondary_image_url' => '', 'gallery_image_ids' => array(), 'gallery_image_urls' => array(), 'layout' => 'image_right', 'background_style' => 'paper', 'button_url' => '', 'button_label' => '', 'css_class' => '', 'image_fit' => 'contain', 'image_position' => 'center center' ) ),
			'student'  => self::normalize_content_section( array( 'key' => 'student', 'visible' => '0', 'sort_order' => 15, 'kicker' => taka_tour_translate( 'sections.student.kicker', 'Special guest' ), 'title' => '', 'subtitle' => taka_tour_translate( 'sections.student.subtitle', 'Student of Sensei' ), 'body' => '', 'image_id' => 0, 'image_url' => '', 'secondary_image_id' => 0, 'secondary_image_url' => '', 'gallery_image_ids' => array(), 'gallery_image_urls' => array(), 'layout' => 'feature_card', 'background_style' => 'paper', 'button_url' => '', 'button_label' => '', 'css_class' => '', 'image_fit' => 'contain', 'image_position' => 'center center' ) ),
			'training' => self::normalize_content_section( array( 'key' => 'training', 'visible' => '1', 'sort_order' => 20, 'kicker' => taka_tour_translate( 'sections.training.kicker', 'Training' ), 'title' => taka_tour_translate( 'sections.training.headline', 'Karate-Do, Kobujutsu und Soft Blocking' ), 'subtitle' => '', 'body' => taka_tour_translate( 'sections.training.text', 'Die Seminare verbinden Grundlagen, Partnerarbeit, Timing, Distanz und Körperstruktur.' ), 'image_id' => 0, 'image_url' => '', 'secondary_image_id' => 0, 'secondary_image_url' => '', 'gallery_image_ids' => array(), 'gallery_image_urls' => array(), 'layout' => 'text_only', 'background_style' => 'plain', 'button_url' => '', 'button_label' => '', 'css_class' => '', 'image_fit' => 'contain', 'image_position' => 'center center' ) ),
			'community'=> self::normalize_content_section( array( 'key' => 'community', 'visible' => '1', 'sort_order' => 30, 'kicker' => taka_tour_translate( 'sections.community.kicker', 'Community' ), 'title' => taka_tour_translate( 'sections.community.headline', 'Gemeinsam trainieren' ), 'subtitle' => '', 'body' => taka_tour_translate( 'sections.community.text', 'Ein europäisches Treffen für ernsthaftes Training und respektvollen Austausch.' ), 'image_id' => 0, 'image_url' => $images['community_group'] ?? '', 'secondary_image_id' => 0, 'secondary_image_url' => '', 'gallery_image_ids' => array(), 'gallery_image_urls' => array(), 'layout' => 'full_background', 'background_style' => 'wash', 'button_url' => '', 'button_label' => '', 'css_class' => '', 'image_fit' => 'cover', 'image_position' => 'center center' ) ),
			'host'     => self::normalize_content_section( array( 'key' => 'host', 'visible' => '1', 'sort_order' => 40, 'kicker' => taka_tour_translate( 'sections.host.kicker', 'Host' ), 'title' => taka_tour_translate( 'sections.host.headline', '5 Jahre Kleiner Wald Dojo' ), 'subtitle' => '', 'body' => '', 'image_id' => 0, 'image_url' => $host_logo, 'secondary_image_id' => 0, 'secondary_image_url' => '', 'gallery_image_ids' => array(), 'gallery_image_urls' => array(), 'layout' => 'image_right', 'background_style' => 'paper', 'button_url' => $host_organizer['website'] ?? '', 'button_label' => $host_organizer['name'] ?? '' ) ),
			'sponsor'  => self::normalize_content_section( array( 'key' => 'sponsor', 'visible' => '1', 'sort_order' => 50, 'kicker' => taka_tour_translate( 'sections.sponsor.kicker', 'Sponsor' ), 'title' => taka_tour_translate( 'sections.sponsor.headline', 'kanso' ), 'subtitle' => '', 'body' => taka_tour_translate( 'sections.sponsor.text', 'Zentrum für Körper, Geist und Seele in Konz.' ), 'image_id' => 0, 'image_url' => $images['sponsor_logo'] ?? '', 'secondary_image_id' => 0, 'secondary_image_url' => '', 'gallery_image_ids' => array(), 'gallery_image_urls' => array(), 'layout' => 'feature_card', 'background_style' => 'plain', 'button_url' => $sponsor_venue['website'] ?? 'https://kan.so', 'button_label' => taka_tour_translate( 'sections.sponsor.link_text', 'kan.so' ) ) ),
		);
	}

	/** Get editable frontend content sections, including user-added sections. */
	public static function get_content_sections( $resolve_translations = true ) {
		$config   = self::load_config();
		$lang     = taka_tour_current_language();
		$stored   = function_exists( 'get_option' ) ? get_option( self::SECTIONS_OPTION, array() ) : array();
		$stored   = is_array( $stored ) ? $stored : array();
		$sections = array();
		foreach ( self::default_content_sections() as $key => $default ) {
			$sections[ $key ] = $default;
		}
		foreach ( (array) ( $config['content_sections'] ?? array() ) as $section ) {
			if ( ! is_array( $section ) ) { continue; }
			$key = sanitize_key( $section['key'] ?? '' );
			if ( '' === $key ) { continue; }
			$sections[ $key ] = array_merge( $sections[ $key ] ?? array(), self::normalize_content_section( $section ) );
		}
		foreach ( $stored as $key => $section ) {
			if ( ! is_array( $section ) ) { continue; }
			$key = sanitize_key( $section['key'] ?? $key );
			if ( '' === $key ) { continue; }
			if ( ! empty( $section['delete'] ) ) { unset( $sections[ $key ] ); continue; }
			$sections[ $key ] = array_merge( $sections[ $key ] ?? array(), self::normalize_content_section( $section ) );
			$sections[ $key ]['key'] = $key;
		}
		foreach ( $sections as $key => $section ) {
			$section = self::normalize_content_section( $section );
			if ( $resolve_translations ) {
				$section = self::resolve_dynamic_section_translations( $section, $lang );
				$block = self::resolve_content_reference( $section['content_reference'] ?? array(), $lang );
				if ( is_array( $block ) ) {
					foreach ( array_keys( self::content_block_text_fields() ) as $field ) {
						if ( '' !== trim( (string) ( $block[ $field ] ?? '' ) ) ) {
							$section[ $field ] = $block[ $field ];
						}
					}
					if ( ! empty( $block['image_id'] ) || ! empty( $block['image_url'] ) ) {
						$section['image_id'] = $block['image_id'] ?? 0;
						$section['image_url'] = $block['image_url'] ?? '';
					}
					if ( ! empty( $block['gallery_image_ids'] ) || ! empty( $block['gallery_image_urls'] ) ) {
						$section['gallery_image_ids'] = $block['gallery_image_ids'] ?? array();
						$section['gallery_image_urls'] = $block['gallery_image_urls'] ?? array();
					}
					$display_style = sanitize_key( $block['content_reference']['display_style'] ?? 'default' );
					if ( 'default' !== $display_style && array_key_exists( $display_style, self::content_reference_display_styles() ) ) {
						$section['layout'] = $display_style;
					}
					$section['referenced_block'] = $block;
				}
			}
			$section['key']             = $key;
			$section['image']           = self::resolve_attachment_url( absint( $section['image_id'] ?? 0 ), 'full', (string) ( $section['image_url'] ?? '' ) );
			$section['secondary_image'] = self::resolve_attachment_url( absint( $section['secondary_image_id'] ?? 0 ), 'full', (string) ( $section['secondary_image_url'] ?? '' ) );
			$section['gallery_images']  = array_values( array_filter( array_merge( self::attachment_urls( $section['gallery_image_ids'] ?? array(), 'full' ), (array) ( $section['gallery_image_urls'] ?? array() ) ) ) );
			$sections[ $key ] = $section;
		}
		uasort( $sections, static function ( $a, $b ) {
			$sort = (int) ( $a['sort_order'] ?? 0 ) <=> (int) ( $b['sort_order'] ?? 0 );
			return 0 !== $sort ? $sort : strcasecmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) );
		} );
		return $sections;
	}

	/** Normalize one content-section record. */
	public static function normalize_content_section( $section ) {
		$section = is_array( $section ) ? $section : array();
		$key = sanitize_key( $section['key'] ?? '' );
		$layout = sanitize_key( $section['layout'] ?? 'text_only' );
		$allowed_layouts = array( 'text_only', 'image_left', 'image_right', 'image_above', 'full_background', 'two_column', 'gallery_grid', 'feature_card', 'background' );
		if ( 'background' === $layout ) { $layout = 'full_background'; }
		if ( ! in_array( $layout, $allowed_layouts, true ) ) { $layout = 'text_only'; }
		$image_fit = sanitize_key( $section['image_fit'] ?? '' );
		if ( '' === $image_fit ) { $image_fit = in_array( $layout, array( 'full_background', 'gallery_grid' ), true ) ? 'cover' : 'contain'; }
		if ( ! in_array( $image_fit, array( 'cover', 'contain', 'auto' ), true ) ) { $image_fit = 'contain'; }
		$image_position = strtolower( trim( (string) ( $section['image_position'] ?? 'center center' ) ) );
		$allowed_positions = array( 'center center', 'center top', 'center bottom', 'left center', 'right center' );
		if ( ! in_array( $image_position, $allowed_positions, true ) ) { $image_position = 'center center'; }
		$gallery_ids = $section['gallery_image_ids'] ?? array();
		if ( is_string( $gallery_ids ) ) { $gallery_ids = self::csv_to_ints( $gallery_ids ); }
		$gallery_urls = $section['gallery_image_urls'] ?? ( $section['gallery'] ?? array() );
		if ( is_string( $gallery_urls ) ) { $gallery_urls = self::lines_to_array( $gallery_urls ); }
		$translations = self::normalize_content_section_translations( $section );
		return array(
			'key'                 => $key,
			'visible'             => ! empty( $section['visible'] ?? $section['enabled'] ?? '1' ) ? '1' : '0',
			'enabled'             => ! empty( $section['visible'] ?? $section['enabled'] ?? '1' ),
			'source_language'     => self::content_section_source_language( $section ),
			'sort_order'          => (int) ( $section['sort_order'] ?? 0 ),
			'kicker'              => self::normalize_dynamic_text_value( $section['kicker'] ?? '' ),
			'title'               => self::normalize_dynamic_text_value( $section['title'] ?? '' ),
			'subtitle'            => self::normalize_dynamic_text_value( $section['subtitle'] ?? '' ),
			'text'                => self::normalize_dynamic_text_value( $section['text'] ?? ( $section['body'] ?? '' ) ),
			'body'                => self::normalize_dynamic_text_value( $section['body'] ?? ( $section['text'] ?? '' ) ),
			'translations'        => $translations,
			'content_reference'   => self::normalize_content_reference( $section['content_reference'] ?? array(), 'homepage_section' ),
			'image_id'            => absint( $section['image_id'] ?? 0 ),
			'image_url'           => (string) ( $section['image_url'] ?? ( $section['image'] ?? '' ) ),
			'secondary_image_id'  => absint( $section['secondary_image_id'] ?? 0 ),
			'secondary_image_url' => (string) ( $section['secondary_image_url'] ?? '' ),
			'gallery_image_ids'   => array_values( array_filter( array_map( 'absint', (array) $gallery_ids ) ) ),
			'gallery_image_urls'  => array_values( array_filter( array_map( 'esc_url_raw', (array) $gallery_urls ) ) ),
			'layout'              => $layout,
			'background_style'    => sanitize_key( $section['background_style'] ?? 'plain' ),
			'button_url'          => (string) ( $section['button_url'] ?? ( $section['link_url'] ?? '' ) ),
			'button_label'        => self::normalize_dynamic_text_value( $section['button_label'] ?? ( $section['link_label'] ?? '' ) ),
			'link_url'            => (string) ( $section['button_url'] ?? ( $section['link_url'] ?? '' ) ),
			'link_label'          => self::normalize_dynamic_text_value( $section['button_label'] ?? ( $section['link_label'] ?? '' ) ),
			'css_class'           => sanitize_html_class( $section['css_class'] ?? '' ),
			'image_fit'           => $image_fit,
			'image_position'      => $image_position,
		);
	}

	/** Normalize structured content section translations and seed them from legacy scalar fields. */
	private static function normalize_content_section_translations( $section ) {
		$languages = self::content_section_languages();
		$fields = array( 'kicker', 'title', 'subtitle', 'body', 'button_label', 'button_url' );
		$translations = array();
		foreach ( $languages as $lang ) {
			foreach ( $fields as $field ) {
				$translations[ $lang ][ $field ] = '';
			}
		}

		if ( ! empty( $section['translations'] ) && is_array( $section['translations'] ) ) {
			foreach ( $languages as $lang ) {
				$item = isset( $section['translations'][ $lang ] ) && is_array( $section['translations'][ $lang ] ) ? $section['translations'][ $lang ] : array();
				foreach ( $fields as $field ) {
					$value = $item[ $field ] ?? '';
					$translations[ $lang ][ $field ] = 'button_url' === $field ? esc_url_raw( $value ) : sanitize_textarea_field( $value );
				}
			}
		}

		$default_lang = self::default_content_section_language();
		$legacy_fields = array(
			'kicker' => array( 'kicker' ),
			'title' => array( 'title' ),
			'subtitle' => array( 'subtitle' ),
			'body' => array( 'body', 'text' ),
			'button_label' => array( 'button_label', 'link_label' ),
			'button_url' => array( 'button_url', 'link_url' ),
		);

		foreach ( $legacy_fields as $field => $aliases ) {
			$value = null;
			foreach ( $aliases as $alias ) {
				if ( array_key_exists( $alias, $section ) ) {
					$value = $section[ $alias ];
					break;
				}
			}
			if ( null === $value || '' === $value ) { continue; }
			if ( is_array( $value ) ) {
				foreach ( $languages as $lang ) {
					if ( '' !== trim( (string) ( $translations[ $lang ][ $field ] ?? '' ) ) ) { continue; }
					$legacy_value = $value[ $lang ] ?? '';
					if ( '' === trim( (string) $legacy_value ) ) { continue; }
					$translations[ $lang ][ $field ] = 'button_url' === $field ? esc_url_raw( $legacy_value ) : sanitize_textarea_field( $legacy_value );
				}
				continue;
			}
			if ( '' === trim( (string) ( $translations[ $default_lang ][ $field ] ?? '' ) ) ) {
				$translations[ $default_lang ][ $field ] = 'button_url' === $field ? esc_url_raw( $value ) : sanitize_textarea_field( $value );
			}
		}

		return $translations;
	}


	/** Normalize scalar or per-language text values. */
	private static function normalize_dynamic_text_value( $value ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( TAKA_Platform_I18n::instance()->get_all_languages() as $lang ) {
				$out[ $lang ] = sanitize_textarea_field( $value[ $lang ] ?? '' );
			}
			return $out;
		}
		return (string) $value;
	}

	/** Resolve per-language section fields for the current frontend language. */
	private static function resolve_dynamic_section_translations( $section, $lang ) {
		$source_language = self::content_section_source_language( $section );
		foreach ( array( 'kicker', 'title', 'subtitle', 'body', 'button_label', 'button_url' ) as $field ) {
			$section[ $field ] = self::translated_content_section_field( $section['translations'] ?? array(), $field, $lang, $source_language, $section[ $field ] ?? '' );
		}
		$section['text'] = $section['body'];
		$section['link_label'] = $section['button_label'];
		$section['link_url'] = $section['button_url'];
		return $section;
	}

	/** Resolve one structured content-section field with site default, English and first-value fallbacks. */
	private static function translated_content_section_field( $translations, $field, $lang, $source_language = 'de', $legacy_fallback = '' ) {
		$translations = is_array( $translations ) ? $translations : array();
		$languages = array_values( array_unique( array_filter( array_merge( array( $lang, $source_language, self::platform_fallback_language(), 'en' ), self::content_section_languages() ) ) ) );
		foreach ( $languages as $language ) {
			$value = $translations[ $language ][ $field ] ?? '';
			if ( '' !== trim( (string) $value ) ) { return (string) $value; }
		}
		return self::resolve_dynamic_text( $legacy_fallback, $lang, $source_language );
	}

	/** Resolve one content-reference override field using content-section translation shape. */
	private static function resolve_content_section_field( $source, $field, $lang ) {
		$source = is_array( $source ) ? $source : array();
		return self::translated_content_section_field(
			$source['translations'] ?? array(),
			$field,
			$lang,
			self::object_source_language( $source ),
			''
		);
	}

	/** Venues that have enough public information for the practical-info section. */
	public static function venues_for_practical_info() {
		$lang = taka_tour_current_language();
		$venues = array_map( static function ( $venue ) use ( $lang ) { return self::resolve_object_text_fields( $venue, 'venue', $lang ); }, self::get_venues() );
		return array_values( array_filter( $venues, static function ( $venue ) {
			$address = $venue['address'] ?? array();
			$values  = array( $venue['name'] ?? '', $venue['website'] ?? '', $venue['parking'] ?? '', $venue['accessibility'] ?? '', $venue['notes'] ?? '', $address['street'] ?? '', $address['city'] ?? '' );
			return '' !== trim( implode( '', array_map( 'strval', $values ) ) );
		} ) );
	}

	/** Get image grid cards for the homepage. */
	public static function image_grid() {
		$images = self::images();
		return apply_filters( 'taka_platform_gallery_images', array( array( 'id' => 'community', 'title' => 'Community', 'text' => 'Internationale Karate-Familie.', 'image' => $images['community_group'], 'wide' => true ), array( 'id' => 'kobudo', 'title' => 'Kobudo', 'text' => 'Bo-Arbeit, Distanz und Timing.', 'image' => $images['kobudo'] ), array( 'id' => 'softblock', 'title' => 'Soft Blocking', 'text' => 'Weiche Struktur statt roher Kraft.', 'image' => $images['softblock'] ), array( 'id' => 'together', 'title' => 'Gemeinsam üben', 'text' => 'Lernen durch Beobachten, Austausch und Wiederholung.', 'image' => $images['together_practice'] ), array( 'id' => 'kids', 'title' => 'Kinderseminar', 'text' => 'Kinderseminar Trier', 'image' => $images['kids_group'] ), array( 'id' => 'group', 'title' => 'Gruppenfoto', 'text' => 'Gemeinschaft über Dojo- und Landesgrenzen hinweg.', 'image' => $images['group_large'], 'wide' => true ) ), 'homepage' );
	}

	/** Suggest languages from country. */
	public static function languages_for_country( $country ) { $map = array( 'Finland' => array( 'fi', 'en', 'de' ), 'Germany' => array( 'de', 'en' ), 'France' => array( 'fr', 'en', 'de' ), 'Netherlands' => array( 'nl', 'en', 'de' ), 'Belgium' => array( 'fr', 'nl', 'de', 'en' ), 'Luxembourg' => array( 'fr', 'de', 'lb', 'en' ) ); return $map[ $country ] ?? array( 'en' ); }

	/** Normalize flexible event program items with legacy date/time fallback. */
	public static function normalize_program_items( $items, $event = array() ) {
		if ( ! is_array( $items ) ) { $items = array(); }
		$normalized = array();
		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$date = sanitize_text_field( $item['date'] ?? '' );
			$start = sanitize_text_field( $item['time_start'] ?? ( $item['start_time'] ?? '' ) );
			$end = sanitize_text_field( $item['time_end'] ?? ( $item['end_time'] ?? '' ) );
			$title = sanitize_text_field( $item['title'] ?? '' );
			$notes = sanitize_textarea_field( $item['notes'] ?? ( $item['description'] ?? '' ) );
			$type = sanitize_key( $item['type'] ?? 'seminar' );
			if ( '' === $date && '' === $start && '' === $end && '' === $title && '' === $notes ) { continue; }
			$normalized[] = array( 'date' => $date, 'time_start' => $start, 'time_end' => $end, 'title' => $title, 'notes' => $notes, 'type' => $type ?: 'seminar', 'sort_order' => (int) ( $item['sort_order'] ?? $index ) );
		}
		if ( empty( $normalized ) && ( ! empty( $event['date_start'] ) || ! empty( $event['time_start'] ) || ! empty( $event['time_end'] ) ) ) {
			$normalized[] = array( 'date' => (string) ( $event['date_start'] ?? '' ), 'time_start' => (string) ( $event['time_start'] ?? '' ), 'time_end' => (string) ( $event['time_end'] ?? '' ), 'title' => '', 'notes' => '', 'type' => 'seminar', 'sort_order' => 0 );
			if ( ! empty( $event['date_end'] ) && ( $event['date_end'] !== ( $event['date_start'] ?? '' ) ) ) {
				$normalized[] = array( 'date' => (string) $event['date_end'], 'time_start' => '', 'time_end' => '', 'title' => '', 'notes' => '', 'type' => 'seminar', 'sort_order' => 1 );
			}
		}
		usort( $normalized, static function ( $a, $b ) { return array( $a['date'], $a['sort_order'], $a['time_start'] ) <=> array( $b['date'], $b['sort_order'], $b['time_start'] ); } );
		return $normalized;
	}

	private static function program_groups( $items, $lang ) {
		$groups = array();
		foreach ( self::normalize_program_items( $items ) as $item ) {
			$date = (string) ( $item['date'] ?? '' );
			if ( '' === $date ) { $date = 'unscheduled'; }
			if ( ! isset( $groups[ $date ] ) ) {
				$groups[ $date ] = array( 'date' => $date, 'label' => 'unscheduled' === $date ? taka_tour_translate( 'event.date', 'Date', $lang ) : self::weekday_name( $date, $lang ), 'items' => array() );
			}
			$groups[ $date ]['items'][] = $item;
		}
		return array_values( $groups );
	}

	private static function program_summary_text( $items, $lang ) {
		$lines = array();
		foreach ( self::program_groups( $items, $lang ) as $group ) {
			foreach ( $group['items'] as $item ) {
				$time = implode( '–', array_filter( array( $item['time_start'] ?? '', $item['time_end'] ?? '' ) ) );
				$text = trim( implode( ' ', array_filter( array( $group['label'] ?? '', $time, $item['title'] ?? '' ) ) ) );
				if ( ! empty( $item['notes'] ) ) { $text .= "\n" . $item['notes']; }
				if ( '' !== $text ) { $lines[] = $text; }
			}
		}
		return implode( "\n\n", $lines );
	}

	private static function weekday_name( $date, $lang ) {
		$ts = strtotime( $date );
		if ( false === $ts ) { return $date; }
		$index = (int) gmdate( 'w', $ts );
		$names = array(
			'en' => array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ),
			'de' => array( 'Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag' ),
			'fr' => array( 'Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi' ),
			'nl' => array( 'Zondag', 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag' ),
			'lb' => array( 'Sonndeg', 'Méindeg', 'Dënschdeg', 'Mëttwoch', 'Donneschdeg', 'Freideg', 'Samschdeg' ),
			'fi' => array( 'Sunnuntai', 'Maanantai', 'Tiistai', 'Keskiviikko', 'Torstai', 'Perjantai', 'Lauantai' ),
			'ja' => array( '日曜日', '月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日' ),
		);
		return $names[ $lang ][ $index ] ?? $names['en'][ $index ];
	}

	/** Get events enriched for active language and display. */
	public static function events_for_language( $lang = null ) {
		$lang = $lang ?: taka_tour_current_language();
		$organizers = self::get_organizers();
		$venues = self::get_venues();
		return array_map( static function ( $event ) use ( $lang, $organizers, $venues ) {
			$slug = $event['slug'] ?? '';
			$event = self::resolve_object_text_fields( $event, 'event', $lang );
			$organizer_relationships = self::enrich_event_organizer_relationships( $event['organizers'] ?? array(), $event['organizer'] ?? '', $organizers, $lang );
			$ticket_organizers = self::ticket_organizer_relationships( $organizer_relationships, $lang );
			$primary_relationship = $organizer_relationships[0] ?? null;
			$organizer = is_array( $primary_relationship ) ? ( $primary_relationship['organizer'] ?? null ) : ( $organizers[ (string) ( $event['organizer'] ?? '' ) ] ?? null );
			$organizer = is_array( $organizer ) ? self::resolve_object_text_fields( $organizer, 'organizer', $lang ) : $organizer;
			$venue = $venues[ (string) ( $event['venue'] ?? '' ) ] ?? null;
			$venue = is_array( $venue ) ? self::resolve_object_text_fields( $venue, 'venue', $lang ) : $venue;
			$event['languages'] = ! empty( $event['languages'] ) ? $event['languages'] : self::languages_for_country( $event['country'] ?? '' );
			$event['subtitle'] = taka_tour_translate( 'seminars.' . $slug . '.subtitle', $event['subtitle'] ?? '', $lang );
			if ( ! self::is_wordpress_event_record( $event ) ) {
				$event['description'] = taka_tour_translate( 'seminars.' . $slug . '.description', $event['description'] ?? '', $lang );
			}
			$description_block = self::resolve_content_reference( $event['content_references']['event_description'] ?? array(), $lang );
			if ( is_array( $description_block ) && '' !== trim( (string) ( $description_block['body'] ?? '' ) ) ) {
				$event['description'] = (string) $description_block['body'];
				$event['description_content_block'] = $description_block;
			}
			$legacy_format = self::is_wordpress_event_record( $event ) ? ( $event['format'] ?? '' ) : taka_tour_translate( 'seminars.' . $slug . '.type', $event['format'] ?? '', $lang );
			$legacy_audience = self::is_wordpress_event_record( $event ) ? ( $event['audience'] ?? '' ) : taka_tour_translate( 'seminars.' . $slug . '.audience', $event['audience'] ?? '', $lang );
			$event['format'] = self::resolve_option_list_label( 'format', $event['format'] ?? '', $lang, $legacy_format );
			$event['audience'] = self::resolve_option_list_label( 'audience', $event['audience'] ?? '', $lang, $legacy_audience );
			$event['level'] = taka_tour_translate( 'seminars.' . $slug . '.level', $event['level'] ?? '', $lang );
			$event['parking'] = taka_tour_translate( 'seminars.' . $slug . '.parking', $event['parking'] ?? '', $lang );
			$event['type'] = $event['format'];
			$event['country_label'] = taka_tour_translate( 'country.' . sanitize_key( $event['country'] ?? '' ), $event['country'] ?? '', $lang );
			$event['program_items'] = self::normalize_program_items( $event['program_items'] ?? array(), $event );
			if ( ! empty( $event['program_items'] ) ) {
				$event['date_start'] = $event['date_start'] ?: ( $event['program_items'][0]['date'] ?? '' );
				$last_program_item = end( $event['program_items'] );
				$event['date_end'] = $event['date_end'] ?: ( $last_program_item['date'] ?? '' );
				reset( $event['program_items'] );
			}
			$event['date'] = self::format_event_date( $event );
			$event['program_groups'] = self::program_groups( $event['program_items'], $lang );
			$event['program_summary'] = self::program_summary_text( $event['program_items'], $lang );
			$event['organizer_relationships'] = $organizer_relationships;
			$event['ticket_organizers'] = $ticket_organizers;
			$event['organizer_data'] = is_array( $organizer ) ? $organizer : null;
			$event['organizer_name'] = is_array( $organizer ) ? ( $organizer['name'] ?? '' ) : '';
			$event['hosts'] = 'Details folgen' === $event['organizer_name'] ? taka_tour_translate( 'event.details_follow', 'Details folgen', $lang ) : $event['organizer_name'];
			$event['organizer_name'] = $event['hosts'];
			$event['venue_data'] = is_array( $venue ) ? $venue : null;
			$event['venue_full'] = is_array( $venue ) ? $venue : null;
			$event['hero_flag'] = self::resolve_event_flag( $event, $venue, $organizer );
			$event['hero_route_map'] = self::resolve_event_route_map_point( $event, $venue );
			$event['venue_name'] = is_array( $venue ) ? ( $venue['name'] ?? '' ) : '';
			$event['address'] = is_array( $venue ) ? self::format_address( $venue['address'] ?? array() ) : '';
			$event['parking_display'] = $event['parking'] ?: ( is_array( $venue ) ? ( $venue['parking'] ?? '' ) : '' );
			$event['ticket_status_label'] = self::ticket_status_label( $event, $lang );
			$event['ticket_tab_label'] = taka_platform_get_translated_value( $event['ticket_tab_label'] ?? '', $lang, 'en' ) ?: ( $event['title'] ?? ( $event['city'] ?? '' ) );
			$event['organizer_full'] = is_array( $organizer ) ? $organizer : null;
			$event['practical_information'] = self::build_practical_information( $event, $organizer, $venue, $lang );
			$event['info_drawers'] = self::build_info_drawers( $event, $organizer, $venue, $lang );
			$event['booking_information'] = self::booking_information_for_event( $event, $organizer, $lang, $ticket_organizers );
			$event['ticket_overview_image'] = self::ticket_overview_image( $event );
			$event['ticket_overview_image_alt'] = self::ticket_overview_image_alt( $event, $organizer, $lang );
			return $event;
		}, self::get_public_events() );
	}

	public static function seminars_for_language( $lang = null ) { return self::events_for_language( $lang ); }

	/** Whether this normalized event came from the Event CPT instead of bundled config. */
	private static function is_wordpress_event_record( $event ) {
		return is_numeric( (string) ( $event['id'] ?? '' ) );
	}

	/** Convert a two-letter country code into its Unicode regional indicator flag. */
	public static function flag_for_country_code( $country_code ) {
		$code = strtoupper( preg_replace( '/[^A-Z]/', '', (string) $country_code ) );
		if ( 2 !== strlen( $code ) ) { return ''; }
		$flag = '';
		for ( $i = 0; $i < 2; $i++ ) {
			$flag .= html_entity_decode( '&#' . ( 127397 + ord( $code[ $i ] ) ) . ';', ENT_NOQUOTES, 'UTF-8' );
		}
		return $flag;
	}

	/** Resolve the small hero location flag without city-specific rules. */
	private static function resolve_event_flag( $event, $venue, $organizer ) {
		$override = trim( (string) ( $event['flag'] ?? '' ) );
		if ( '' !== $override ) { return $override; }

		$venue_address = is_array( $venue ) ? ( $venue['address'] ?? array() ) : array();
		$candidates = array(
			$event['country_code'] ?? '',
			is_array( $venue_address ) ? ( $venue_address['country_code'] ?? '' ) : '',
			is_array( $organizer ) ? ( $organizer['country_code'] ?? '' ) : '',
		);

		foreach ( $candidates as $country_code ) {
			$flag = self::flag_for_country_code( $country_code );
			if ( '' !== $flag ) { return $flag; }
		}

		if ( is_array( $venue ) && '' !== trim( (string) ( $venue['flag'] ?? '' ) ) ) { return trim( (string) $venue['flag'] ); }
		if ( is_array( $organizer ) && '' !== trim( (string) ( $organizer['flag'] ?? '' ) ) ) { return trim( (string) $organizer['flag'] ); }

		return '';
	}

	/** Resolve optional manually configured route map canvas coordinates for one event. */
	private static function resolve_event_route_map_point( $event, $venue ) {
		$x = self::map_coordinate( $event['route_map_x'] ?? ( $event['map_x'] ?? null ) );
		$y = self::map_coordinate( $event['route_map_y'] ?? ( $event['map_y'] ?? null ) );
		$venue_x = is_array( $venue ) ? self::map_coordinate( $venue['route_map_x'] ?? ( $venue['map_x'] ?? null ) ) : null;
		$venue_y = is_array( $venue ) ? self::map_coordinate( $venue['route_map_y'] ?? ( $venue['map_y'] ?? null ) ) : null;
		if ( null === $x && null !== $venue_x ) { $x = $venue_x; }
		if ( null === $y && null !== $venue_y ) { $y = $venue_y; }

		$label = trim( (string) ( $event['route_map_label'] ?? ( $event['map_label'] ?? '' ) ) );
		if ( '' === $label && is_array( $venue ) ) {
			$label = trim( (string) ( $venue['route_map_label'] ?? ( $venue['map_label'] ?? '' ) ) );
		}
		if ( '' === $label ) {
			$label = trim( (string) ( $event['city'] ?? '' ) ) ?: trim( (string) ( $event['title'] ?? '' ) );
		}

		return array( 'x' => $x, 'y' => $y, 'label' => $label );
	}

	private static function map_coordinate( $value ) {
		if ( null === $value || '' === $value ) { return null; }
		if ( ! is_numeric( $value ) ) { return null; }
		return max( 0, min( 100, (float) $value ) );
	}

	/** Get enabled ticket widget URL for Pretix events. */
	public static function pretix_event_url( $event ) { return TAKA_Platform_Ticket_Provider_Registry::pretix_widget_url( $event ); }

	/** Get ticketed public events. */
	public static function ticketed_seminars() { return array_values( array_filter( self::events_for_language(), static fn( $event ) => '' !== self::pretix_event_url( $event ) ) ); }

	/** Resolve the compact ticket overview image without hardcoded template URLs. */
	private static function ticket_overview_image( $event ) {
		foreach ( array( 'past_group_photo_url', 'group_image_url', 'image', 'image_url' ) as $key ) {
			if ( ! empty( $event[ $key ] ) ) {
				return (string) $event[ $key ];
			}
		}

		$images = self::images();
		return (string) ( $images['past_group_photo'] ?? '' );
	}

	/** Build meaningful alt text for the ticket overview image. */

	/** Enrich organizer relationships with full organizer data and translated labels. */
	private static function enrich_event_organizer_relationships( $relationships, $legacy_organizer, $organizers, $lang ) {
		$labels = self::organizer_relationship_type_labels( $lang );
		$items = self::normalize_event_organizer_relationships( $relationships, $legacy_organizer );
		$out = array();
		foreach ( $items as $item ) {
			if ( empty( $item['visible'] ) ) { continue; }
			$organizer = $organizers[ (string) $item['organizer_id'] ] ?? null;
			if ( ! is_array( $organizer ) ) { continue; }
			$organizer = self::resolve_object_text_fields( $organizer, 'organizer', $lang );
			$item['label'] = '' !== (string) $item['custom_label'] ? (string) $item['custom_label'] : ( $labels[ $item['relationship_type'] ] ?? $labels['organizer'] );
			$item['organizer'] = $organizer;
			$item['drawer_key'] = 'organizer_' . sanitize_key( (string) $item['organizer_id'] ) . '_' . sanitize_key( $item['relationship_type'] );
			$out[] = $item;
		}
		return $out;
	}

	/** Organizer rows shown in the ticket panel: event organizers plus profile co-organizers. */
	private static function ticket_organizer_relationships( $organizer_relationships, $lang ) {
		$labels = self::organizer_relationship_type_labels( $lang );
		$out = array();
		$seen = array();
		foreach ( (array) $organizer_relationships as $relationship ) {
			if ( ! is_array( $relationship ) ) { continue; }
			$organizer = $relationship['organizer'] ?? null;
			if ( ! is_array( $organizer ) || empty( $organizer['name'] ) ) { continue; }
			$key = self::organizer_display_key( $organizer );
			if ( isset( $seen[ $key ] ) ) { continue; }
			$seen[ $key ] = true;
			$out[] = $relationship;
			foreach ( self::co_organizers_as_relationships( $organizer['co_organizers'] ?? array(), $relationship, $labels ) as $co_relationship ) {
				$co_key = self::organizer_display_key( $co_relationship['organizer'] ?? array() );
				if ( isset( $seen[ $co_key ] ) ) { continue; }
				$seen[ $co_key ] = true;
				$out[] = $co_relationship;
			}
		}
		return $out;
	}

	/** Convert active profile co-organizers into ticket-display relationship rows. */
	private static function co_organizers_as_relationships( $co_organizers, $parent_relationship, $labels ) {
		$out = array();
		$parent_id = sanitize_key( (string) ( $parent_relationship['organizer_id'] ?? 'organizer' ) );
		foreach ( self::normalize_co_organizers( $co_organizers ) as $index => $co_organizer ) {
			if ( empty( $co_organizer['active'] ) ) { continue; }
			$email = trim( (string) ( $co_organizer['email'] ?? '' ) );
			$organizer = array(
				'id' => $parent_id . '_co_' . $index,
				'name' => $co_organizer['name'] ?? '',
				'legal_name' => $co_organizer['legal_name'] ?? '',
				'website' => $co_organizer['website'] ?? '',
				'logo_id' => $co_organizer['logo_id'] ?? 0,
				'logo_url' => $co_organizer['logo_url'] ?? '',
				'logo' => $co_organizer['logo_url'] ?? '',
				'emails' => '' !== $email ? array( $email ) : array(),
				'contact_persons' => array(),
				'description' => $co_organizer['description'] ?? '',
				'social_links' => $co_organizer['social_links'] ?? array(),
				'co_organizers' => array(),
			);
			$out[] = array(
				'organizer_id' => $organizer['id'],
				'relationship_type' => 'co_organizer',
				'custom_label' => '',
				'visible' => 1,
				'sort_order' => (int) ( $co_organizer['sort_order'] ?? ( 100 + $index ) ),
				'label' => $labels['co_organizer'] ?? ( $labels['organizer'] ?? 'Co-organizer' ),
				'organizer' => $organizer,
				'drawer_key' => 'organizer_' . $parent_id . '_co_' . sanitize_key( (string) $index ),
			);
		}
		return $out;
	}

	/** Stable duplicate key for organizer display rows. */
	private static function organizer_display_key( $organizer ) {
		$parts = array( $organizer['id'] ?? '', $organizer['name'] ?? '', $organizer['website'] ?? '', self::list_to_string( $organizer['emails'] ?? array() ) );
		return md5( strtolower( trim( implode( '|', array_map( 'strval', $parts ) ) ) ) );
	}

	private static function ticket_overview_image_alt( $event, $organizer, $lang ) {
		$title = trim( (string) ( $event['title'] ?? '' ) );
		if ( '' !== $title ) {
			return sprintf( taka_tour_translate( 'event.previous_seminar_photo_alt', '%s previous seminar photo', $lang ), $title );
		}

		$organizer_name = is_array( $organizer ) ? trim( (string) ( $organizer['name'] ?? '' ) ) : '';
		if ( '' !== $organizer_name ) {
			return sprintf( taka_tour_translate( 'event.organizer_event_photo_alt', '%s event photo', $lang ), $organizer_name );
		}

		return taka_tour_translate( 'event.event_photo', 'Event photo', $lang );
	}

	/** Build practical information rows for frontend ticket drawers. */
	private static function build_practical_information( $event, $organizer, $venue, $lang ) {
		$rows = array(
			array( 'label' => taka_tour_translate( 'event.parking', 'Parken', $lang ), 'value' => $event['parking'] ?? '' ),
			array( 'label' => taka_tour_translate( 'event.accessibility', 'Barrierefreiheit', $lang ), 'value' => $event['accessibility'] ?? '' ),
			array( 'label' => taka_tour_translate( 'event.notes', 'Hinweise', $lang ), 'value' => $event['notes'] ?? '' ),
		);

		if ( is_array( $venue ) ) {
			$rows[] = array( 'label' => taka_tour_translate( 'event.parking', 'Parken', $lang ), 'value' => $venue['parking'] ?? '' );
			$rows[] = array( 'label' => taka_tour_translate( 'event.accessibility', 'Barrierefreiheit', $lang ), 'value' => $venue['accessibility'] ?? '' );
			$rows[] = array( 'label' => taka_tour_translate( 'event.notes', 'Hinweise', $lang ), 'value' => $venue['notes'] ?? '' );
		}

		if ( is_array( $organizer ) ) {
			$rows[] = array( 'label' => taka_tour_translate( 'event.organizer', 'Veranstalter', $lang ), 'value' => $organizer['name'] ?? '' );
			$rows[] = array( 'label' => taka_tour_translate( 'event.website', 'Website', $lang ), 'value' => $organizer['website'] ?? '', 'url' => $organizer['website'] ?? '' );
			$rows[] = array( 'label' => taka_tour_translate( 'event.contact', 'Kontakt', $lang ), 'value' => self::list_to_string( $organizer['emails'] ?? array() ) );
			$co_contacts = self::co_organizer_contact_summary( $organizer['co_organizers'] ?? array() );
			if ( '' !== $co_contacts ) {
				$rows[] = array( 'label' => taka_tour_translate( 'event.co_organizers', 'Co-organizers', $lang ), 'value' => $co_contacts );
			}
		}

		return self::clean_info_rows( $rows );
	}

	/** Build prepared drawer view data; templates should not query raw meta. */
	private static function build_info_drawers( $event, $organizer, $venue, $lang ) {
		$time = implode( '–', array_filter( array( $event['time_start'] ?? '', $event['time_end'] ?? '' ) ) );
		$schedule = $event['program_summary'] ?? self::program_summary_text( $event['program_items'] ?? array(), $lang );
		$drawers = array();
		$drawers['event'] = array(
			'type'  => 'event',
			'label' => taka_tour_translate( 'drawer.event_details', 'Details', $lang ),
			'title' => taka_tour_translate( 'drawer.event_details', 'Event details', $lang ),
			'image' => $event['image'] ?? '',
			'rows'  => self::clean_info_rows( array(
				array( 'label' => taka_tour_translate( 'event.title', 'Title', $lang ), 'value' => $event['title'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.subtitle', 'Subtitle', $lang ), 'value' => $event['subtitle'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.description', 'Description', $lang ), 'value' => $event['description'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.date', 'Date', $lang ), 'value' => $event['date'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.schedule', 'Schedule', $lang ), 'value' => $schedule ?: $time ),
				array( 'label' => taka_tour_translate( 'event.doors_open', 'Doors open', $lang ), 'value' => $event['doors_open'] ?? '' ),
				array( 'label' => taka_tour_translate( 'seminar.format_label', 'Format', $lang ), 'value' => $event['format'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.audience', 'Audience', $lang ), 'value' => $event['audience'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.level', 'Level', $lang ), 'value' => $event['level'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.ticket_status', 'Ticket status', $lang ), 'value' => $event['ticket_status_label'] ?? ( $event['ticket_status'] ?? '' ) ),
				array( 'label' => taka_tour_translate( 'event.ticket_provider', 'Ticket provider', $lang ), 'value' => $event['ticket_provider'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.ticket_url', 'Ticket URL', $lang ), 'value' => $event['ticket_shop_url'] ?? '', 'url' => $event['ticket_shop_url'] ?? '' ),
			) ),
		);

		if ( is_array( $organizer ) ) {
			$social = $organizer['social_links'] ?? ( $organizer['social'] ?? array() );
			$drawers['organizer'] = array(
				'type'  => 'organizer',
				'label' => taka_tour_translate( 'drawer.organizer_info', 'Organizer', $lang ),
				'title' => taka_tour_translate( 'drawer.organizer_info', 'Organizer info', $lang ),
				'image' => $organizer['logo'] ?? ( $organizer['logo_url'] ?? '' ),
				'cards_title' => taka_tour_translate( 'event.co_organizers', 'Co-organizers', $lang ),
				'cards' => self::co_organizer_cards( $organizer['co_organizers'] ?? array(), $lang ),
				'rows'  => self::clean_info_rows( array(
					array( 'label' => taka_tour_translate( 'event.organizer', 'Organizer', $lang ), 'value' => $organizer['name'] ?? '' ),
					array( 'label' => taka_tour_translate( 'event.legal_name', 'Legal name', $lang ), 'value' => $organizer['legal_name'] ?? '' ),
					array( 'label' => taka_tour_translate( 'event.description', 'Description', $lang ), 'value' => $organizer['description'] ?? '' ),
					array( 'label' => taka_tour_translate( 'event.website', 'Website', $lang ), 'value' => $organizer['website'] ?? '', 'url' => $organizer['website'] ?? '' ),
					array( 'label' => taka_tour_translate( 'event.email', 'Email', $lang ), 'value' => self::list_to_string( $organizer['emails'] ?? array() ) ),
					array( 'label' => taka_tour_translate( 'event.contact', 'Contact', $lang ), 'value' => self::list_to_string( $organizer['contact_persons'] ?? array() ) ),
					array( 'label' => 'Instagram', 'value' => $social['instagram'] ?? '', 'url' => $social['instagram'] ?? '' ),
					array( 'label' => 'Facebook', 'value' => $social['facebook'] ?? '', 'url' => $social['facebook'] ?? '' ),
					array( 'label' => 'YouTube', 'value' => $social['youtube'] ?? '', 'url' => $social['youtube'] ?? '' ),
				) ),
			);
		}

		foreach ( $event['ticket_organizers'] ?? ( $event['organizer_relationships'] ?? array() ) as $relationship ) {
			$rel_organizer = $relationship['organizer'] ?? null;
			if ( ! is_array( $rel_organizer ) ) { continue; }
			$key = $relationship['drawer_key'] ?? ( 'organizer_' . sanitize_key( (string) ( $relationship['organizer_id'] ?? '' ) ) );
			$social = $rel_organizer['social_links'] ?? ( $rel_organizer['social'] ?? array() );
			$drawers[ $key ] = array(
				'type'  => 'organizer',
				'label' => $relationship['label'] ?? taka_tour_translate( 'event.organizer', 'Organizer', $lang ),
				'title' => $rel_organizer['name'] ?? ( $relationship['label'] ?? '' ),
				'image' => $rel_organizer['logo'] ?? ( $rel_organizer['logo_url'] ?? '' ),
				'cards_title' => taka_tour_translate( 'event.co_organizers', 'Co-organizers', $lang ),
				'cards' => self::co_organizer_cards( $rel_organizer['co_organizers'] ?? array(), $lang ),
				'rows'  => self::clean_info_rows( array(
					array( 'label' => taka_tour_translate( 'event.relationship', 'Relationship', $lang ), 'value' => $relationship['label'] ?? '' ),
					array( 'label' => taka_tour_translate( 'event.organizer', 'Organizer', $lang ), 'value' => $rel_organizer['name'] ?? '' ),
					array( 'label' => taka_tour_translate( 'event.legal_name', 'Legal name', $lang ), 'value' => $rel_organizer['legal_name'] ?? '' ),
					array( 'label' => taka_tour_translate( 'event.description', 'Description', $lang ), 'value' => $rel_organizer['description'] ?? '' ),
					array( 'label' => taka_tour_translate( 'event.website', 'Website', $lang ), 'value' => $rel_organizer['website'] ?? '', 'url' => $rel_organizer['website'] ?? '' ),
					array( 'label' => taka_tour_translate( 'event.email', 'Email', $lang ), 'value' => self::list_to_string( $rel_organizer['emails'] ?? array() ) ),
					array( 'label' => taka_tour_translate( 'event.contact', 'Contact', $lang ), 'value' => self::list_to_string( $rel_organizer['contact_persons'] ?? array() ) ),
					array( 'label' => 'Instagram', 'value' => $social['instagram'] ?? '', 'url' => $social['instagram'] ?? '' ),
					array( 'label' => 'Facebook', 'value' => $social['facebook'] ?? '', 'url' => $social['facebook'] ?? '' ),
					array( 'label' => 'YouTube', 'value' => $social['youtube'] ?? '', 'url' => $social['youtube'] ?? '' ),
				) ),
			);
		}

		if ( is_array( $venue ) ) {
			$address = $venue['address'] ?? array();
			$venue_address = self::format_address( $address );
			$venue_rows = array(
				array( 'label' => taka_tour_translate( 'event.venue', 'Venue', $lang ), 'value' => $venue['name'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.address', 'Address', $lang ), 'value' => $venue_address ),
				array( 'label' => taka_tour_translate( 'event.city', 'City', $lang ), 'value' => $address['city'] ?? '' ),
				array( 'label' => taka_tour_translate( 'event.country', 'Country', $lang ), 'value' => $address['country'] ?? '' ),
			);
			if ( '' !== $venue_address ) {
				$venue_rows[] = array( 'label' => taka_tour_translate( 'event.google_maps', 'Open in Google Maps', $lang ), 'value' => taka_tour_translate( 'event.google_maps', 'Open in Google Maps', $lang ), 'url' => self::google_maps_url( $venue_address ) );
			}
			$venue_rows[] = array( 'label' => taka_tour_translate( 'event.venue_website', 'Venue website', $lang ), 'value' => $venue['website'] ?? '', 'url' => $venue['website'] ?? '' );
			$drawers['venue'] = array(
				'type'  => 'venue',
				'label' => taka_tour_translate( 'drawer.venue_info', 'Venue & info', $lang ),
				'title' => taka_tour_translate( 'drawer.venue_info', 'Venue & practical information', $lang ),
				'image' => $venue['image'] ?? ( $venue['image_url'] ?? '' ),
				'rows'  => array_merge( self::clean_info_rows( $venue_rows ), self::build_practical_information( $event, null, $venue, $lang ) ),
			);
		} else {
			$practical = self::build_practical_information( $event, null, $venue, $lang );
			if ( ! empty( $practical ) ) {
				$drawers['venue'] = array(
					'type'  => 'venue',
					'label' => taka_tour_translate( 'drawer.venue_info', 'Venue & info', $lang ),
					'title' => taka_tour_translate( 'drawer.venue_info', 'Venue & practical information', $lang ),
					'image' => '',
					'rows'  => $practical,
				);
			}
		}

		return array_filter( $drawers, static function ( $drawer ) { return ! empty( $drawer['rows'] ) || ! empty( $drawer['cards'] ); } );
	}

	/** Normalize repeatable co-organizer entries from WordPress meta or config. */
	private static function normalize_co_organizers( $items ) {
		if ( ! is_array( $items ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$name = trim( (string) ( $item['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}

			$logo_id = absint( $item['logo_id'] ?? 0 );
			$social  = is_array( $item['social_links'] ?? null ) ? $item['social_links'] : ( is_array( $item['social'] ?? null ) ? $item['social'] : array() );
			$normalized[] = array(
				'name' => $name,
				'legal_name' => trim( (string) ( $item['legal_name'] ?? '' ) ),
				'website' => trim( (string) ( $item['website'] ?? '' ) ),
				'logo_id' => $logo_id,
				'logo_url' => self::resolve_attachment_url( $logo_id, 'large', (string) ( $item['logo_url'] ?? ( $item['logo'] ?? '' ) ) ),
				'email' => trim( (string) ( $item['email'] ?? '' ) ),
				'description' => trim( (string) ( $item['description'] ?? '' ) ),
				'social_links' => array(
					'instagram' => trim( (string) ( $social['instagram'] ?? '' ) ),
					'facebook' => trim( (string) ( $social['facebook'] ?? '' ) ),
					'youtube' => trim( (string) ( $social['youtube'] ?? '' ) ),
				),
				'sort_order' => (int) ( $item['sort_order'] ?? 0 ),
				'active' => ! array_key_exists( 'active', $item ) || (bool) $item['active'],
			);
		}

		usort( $normalized, static function ( $a, $b ) { return ( (int) ( $a['sort_order'] ?? 0 ) <=> (int) ( $b['sort_order'] ?? 0 ) ) ?: strcmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) ); } );
		return $normalized;
	}

	/** Build compact frontend cards for active co-organizers. */
	private static function co_organizer_cards( $co_organizers, $lang ) {
		$cards = array();
		foreach ( self::normalize_co_organizers( $co_organizers ) as $co_organizer ) {
			if ( empty( $co_organizer['active'] ) ) {
				continue;
			}
			$cards[] = array(
				'image' => $co_organizer['logo_url'] ?? '',
				'name' => $co_organizer['name'] ?? '',
				'legal_name' => $co_organizer['legal_name'] ?? '',
				'website' => $co_organizer['website'] ?? '',
				'email' => $co_organizer['email'] ?? '',
				'description' => $co_organizer['description'] ?? '',
				'social_links' => $co_organizer['social_links'] ?? array(),
				'website_label' => taka_tour_translate( 'event.website', 'Website', $lang ),
				'email_label' => taka_tour_translate( 'event.email', 'Email', $lang ),
			);
		}

		return $cards;
	}

	/** Summarize co-organizer contact data for practical information. */
	private static function co_organizer_contact_summary( $co_organizers ) {
		$parts = array();
		foreach ( self::normalize_co_organizers( $co_organizers ) as $co_organizer ) {
			if ( empty( $co_organizer['active'] ) ) {
				continue;
			}
			$bits = array_filter( array( $co_organizer['name'] ?? '', $co_organizer['email'] ?? '', $co_organizer['website'] ?? '' ) );
			if ( ! empty( $bits ) ) {
				$parts[] = implode( ' | ', $bits );
			}
		}

		return implode( ', ', $parts );
	}

	/** Convert nested contact/email arrays into readable text without PHP notices. */
	private static function list_to_string( $items ) {
		$strings = array_map(
			static function ( $item ) {
				if ( is_array( $item ) ) {
					return trim( implode( ' | ', array_filter( array_map( 'strval', $item ) ) ) );
				}
				return trim( (string) $item );
			},
			(array) $items
		);

		return implode( ', ', array_filter( $strings ) );
	}

	/** Drop rows whose values are empty so drawers never show empty labels. */
	private static function clean_info_rows( $rows ) {
		return array_values( array_filter( $rows, static function ( $row ) { return '' !== trim( wp_strip_all_tags( (string) ( $row['value'] ?? '' ) ) ); } ) );
	}

	/** Generate a simple Google Maps search URL for an address string. */
	private static function google_maps_url( $address ) {
		$address = trim( (string) $address );
		return '' === $address ? '' : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $address );
	}

	/** Export current WordPress data into config-compatible array. */
	public static function export_config_from_wp() { return array( 'organizers' => self::export_organizers(), 'venues' => self::export_venues(), 'events' => self::load_events_from_wp(), 'content_sections' => array_values( self::get_content_sections( false ) ) ); }
	private static function export_organizers() { $items = self::load_organizers_from_wp(); $out = array(); foreach ( $items as $key => $item ) { if ( (string) $key !== (string) ( $item['id'] ?? '' ) ) { continue; } $out[ $item['config_id'] ?: $item['id'] ] = $item; } return $out; }
	private static function export_venues() { $items = self::load_venues_from_wp(); $out = array(); foreach ( $items as $key => $item ) { if ( (string) $key !== (string) ( $item['id'] ?? '' ) ) { continue; } $out[ $item['config_id'] ?: $item['id'] ] = $item; } return $out; }

	/** Helpers. */
	private static function resolve_attachment_url( $attachment_id, $size = 'large', $fallback = '' ) { $url = $attachment_id && function_exists( 'wp_get_attachment_image_url' ) ? wp_get_attachment_image_url( $attachment_id, $size ) : ''; return $url ?: $fallback; }
	private static function attachment_urls( $ids, $size = 'large' ) { return array_values( array_filter( array_map( static function ( $id ) use ( $size ) { return self::resolve_attachment_url( $id, $size ); }, (array) $ids ) ) ); }
	private static function nullable_meta( $post_id, $key ) { $value = get_post_meta( $post_id, '_taka_' . $key, true ); return '' === $value ? null : $value; }
	private static function lines_to_array( $value ) { return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $value ) ) ) ); }
	private static function csv_to_ints( $value ) { return array_values( array_filter( array_map( 'absint', preg_split( '/\s*,\s*/', (string) $value ) ) ) ); }
	private static function csv_to_strings( $value ) { return array_values( array_filter( array_map( 'trim', preg_split( '/\s*,\s*/', (string) $value ) ) ) ); }
	private static function format_event_date( $event ) { $start = $event['date_start'] ?? ''; $end = $event['date_end'] ?? ''; if ( '' === $start ) { return ''; } $start_ts = strtotime( $start ); $end_ts = '' !== $end ? strtotime( $end ) : false; if ( false === $start_ts ) { return $start; } if ( false === $end_ts || $start === $end ) { return gmdate( 'j.', $start_ts ) . ' ' . self::month_name( (int) gmdate( 'n', $start_ts ) ) . ' ' . gmdate( 'Y', $start_ts ); } if ( gmdate( 'Ym', $start_ts ) === gmdate( 'Ym', $end_ts ) ) { return gmdate( 'j.', $start_ts ) . '–' . gmdate( 'j.', $end_ts ) . ' ' . self::month_name( (int) gmdate( 'n', $end_ts ) ) . ' ' . gmdate( 'Y', $end_ts ); } return gmdate( 'j.', $start_ts ) . ' ' . self::month_name( (int) gmdate( 'n', $start_ts ) ) . ' ' . gmdate( 'Y', $start_ts ) . ' – ' . gmdate( 'j.', $end_ts ) . ' ' . self::month_name( (int) gmdate( 'n', $end_ts ) ) . ' ' . gmdate( 'Y', $end_ts ); }
	private static function month_name( $month ) { $months = array( 1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember' ); return $months[ $month ] ?? ''; }
	private static function format_address( $address ) { $street = $address['street'] ?? ''; $city_line = trim( ( $address['postal_code'] ?? '' ) . ' ' . ( $address['city'] ?? '' ) ); $country = $address['country'] ?? ''; return implode( ', ', array_filter( array( $street, $city_line, $country ) ) ); }
	private static function ticket_status_label( $event, $lang ) { return '' !== self::pretix_event_url( $event ) ? taka_tour_translate( 'seminar.ticketshop_open_pretix', 'Tickets bei Pretix öffnen', $lang ) : taka_tour_translate( 'event.ticketshop_soon', taka_tour_translate( 'seminar.ticketshop_soon', 'Ticketshop folgt', $lang ), $lang ); }
}

<?php
/**
 * Provider-independent translation package workflow.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Translation_Packages {
	const PACKAGE_TYPE = 'taka_translation_package';
	const FORMAT_VERSION = 1;
	const GLOSSARY_OPTION = 'taka_platform_translation_glossary';

	/** Supported language labels for admin controls. */
	public static function language_labels() {
		return array(
			'de' => 'Deutsch',
			'en' => 'English',
			'fr' => 'Français',
			'nl' => 'Nederlands',
			'lb' => 'Lëtzebuergesch',
			'fi' => 'Suomi',
			'ja' => '日本語',
		);
	}

	/** Default platform source language for objects without an override. */
	public static function default_source_language() {
		return 'de';
	}

	/** Sanitize one language code against supported languages. */
	public static function sanitize_language( $lang, $fallback = 'de' ) {
		$lang = sanitize_key( (string) $lang );
		return in_array( $lang, TAKA_Platform_I18n::instance()->get_all_languages(), true ) ? $lang : $fallback;
	}

	/** Normalize target language list. */
	public static function sanitize_target_languages( $langs, $source_language = 'de' ) {
		$targets = self::sanitize_selected_languages( $langs );
		if ( empty( $targets ) ) {
			$targets = TAKA_Platform_I18n::instance()->get_all_languages();
		}
		return array_values( array_diff( $targets, array( $source_language ) ) );
	}

	/** Normalize selected translation languages without removing a global source language. */
	private static function sanitize_selected_languages( $langs ) {
		$langs = is_array( $langs ) ? $langs : array();
		$targets = array();
		foreach ( $langs as $lang ) {
			$lang = self::sanitize_language( $lang, '' );
			if ( '' !== $lang ) { $targets[] = $lang; }
		}
		return array_values( array_unique( $targets ) );
	}

	/** Default glossary terms. */
	public static function default_glossary() {
		return array(
			array( 'term' => 'TAKA', 'note' => 'Platform name; keep unchanged.', 'translate' => '0', 'preferred_translations' => array() ),
			array( 'term' => 'Sensei', 'note' => 'Japanese title; usually keep untranslated.', 'translate' => '0', 'preferred_translations' => array() ),
			array( 'term' => 'Dojo', 'note' => 'Training place; usually keep untranslated.', 'translate' => '0', 'preferred_translations' => array() ),
			array( 'term' => 'Karate-Do', 'note' => 'Martial art term; keep spelling unless a local convention exists.', 'translate' => '0', 'preferred_translations' => array() ),
			array( 'term' => 'Kobujutsu', 'note' => 'Martial art term; keep spelling unless a local convention exists.', 'translate' => '0', 'preferred_translations' => array() ),
			array( 'term' => 'Soft Blocking', 'note' => 'Training concept; translate only if the site defines a preferred local form.', 'translate' => '1', 'preferred_translations' => array() ),
			array( 'term' => 'Shorin-Ryu', 'note' => 'Style name; keep unchanged.', 'translate' => '0', 'preferred_translations' => array() ),
			array( 'term' => 'Okinawa', 'note' => 'Place name.', 'translate' => '0', 'preferred_translations' => array() ),
			array( 'term' => 'Kata', 'note' => 'Martial art term; usually keep untranslated.', 'translate' => '0', 'preferred_translations' => array() ),
			array( 'term' => 'Kumite', 'note' => 'Martial art term; usually keep untranslated.', 'translate' => '0', 'preferred_translations' => array() ),
		);
	}

	/** Load editable glossary with defaults. */
	public static function get_glossary() {
		$stored = function_exists( 'get_option' ) ? get_option( self::GLOSSARY_OPTION, array() ) : array();
		$stored = is_array( $stored ) ? $stored : array();
		return empty( $stored ) ? self::default_glossary() : self::sanitize_glossary( $stored );
	}

	/** Sanitize glossary entries. */
	public static function sanitize_glossary( $items ) {
		$clean = array();
		foreach ( (array) $items as $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$term = sanitize_text_field( $item['term'] ?? '' );
			if ( '' === $term ) { continue; }
			$preferred = $item['preferred_translations'] ?? array();
			if ( is_string( $preferred ) ) { $preferred = self::lines_to_array( $preferred ); }
			$clean[] = array(
				'term' => $term,
				'note' => sanitize_textarea_field( $item['note'] ?? '' ),
				'translate' => ! empty( $item['translate'] ) ? '1' : '0',
				'preferred_translations' => array_values( array_filter( array_map( 'sanitize_text_field', (array) $preferred ) ) ),
			);
		}
		return $clean;
	}

	/** Translator prompt embedded in every package. */
	public static function translator_prompt() {
		return "Please translate this TAKA Translation Package.\nRules:\n- Preserve JSON structure.\n- Preserve all IDs.\n- Fill only the translations object.\n- Do not change source_text.\n- Preserve HTML tags.\n- Preserve placeholders such as {event}, %s, {{name}}.\n- Keep glossary terms untranslated unless the target language requires an accepted local form.\n- Return valid JSON only.";
	}

	/** Export a downloadable package array. */
	public static function build_package( $args = array() ) {
		$source_language = self::sanitize_language( $args['source_language'] ?? self::default_source_language(), self::default_source_language() );
		$use_object_sources = ! empty( $args['use_object_source_languages'] );
		$targets = self::sanitize_target_languages( $args['target_languages'] ?? array(), $source_language );
		$item_language_scope = $use_object_sources ? array_values( array_unique( array_merge( $targets, array( $source_language ) ) ) ) : $targets;
		$include_existing = ! empty( $args['include_existing_translations'] );
		$include_context = ! empty( $args['include_context'] );
		$only_missing = ! empty( $args['only_missing_translations'] );
		$items = self::collect_items( array(
			'source_language' => $source_language,
			'use_object_source_languages' => $use_object_sources,
			'target_languages' => $item_language_scope,
			'include_existing_translations' => $include_existing,
			'include_context' => $include_context,
			'only_missing_translations' => $only_missing,
		) );
		$glossary = ! empty( $args['include_glossary'] ) ? self::get_glossary() : array();
		return array(
			'package_type' => self::PACKAGE_TYPE,
			'format_version' => self::FORMAT_VERSION,
			'platform_version' => TAKA_PLATFORM_VERSION,
			'created_at' => gmdate( 'c' ),
			'source_language' => $source_language,
			'use_object_source_languages' => $use_object_sources,
			'target_languages' => $targets,
			'instructions' => array(
				'preserve_html' => ! empty( $args['include_html'] ),
				'preserve_placeholders' => true,
				'do_not_translate_terms' => array_values( array_map( static function ( $item ) { return $item['term']; }, array_filter( $glossary, static function ( $item ) { return empty( $item['translate'] ); } ) ) ),
			),
			'translator_prompt' => self::translator_prompt(),
			'glossary' => $glossary,
			'warnings' => self::validation_warnings( $items ),
			'items' => $items,
		);
	}

	/** File name for a package download. */
	public static function filename( $source_language, $target_languages ) {
		$targets = implode( '-', self::sanitize_target_languages( $target_languages, $source_language ) );
		return 'taka-translation-package-' . gmdate( 'Y-m-d' ) . '-' . self::sanitize_language( $source_language ) . '-to-' . $targets . '.json';
	}

	/** Status counters for the admin overview. */
	public static function status() {
		$items = self::collect_items( array( 'include_existing_translations' => true, 'only_missing_translations' => false, 'use_object_source_languages' => true, 'include_context' => false ) );
		$langs = TAKA_Platform_I18n::instance()->get_all_languages();
		$status = array_fill_keys( $langs, array( 'translated' => 0, 'missing' => 0 ) );
		foreach ( $langs as $lang ) { $status[ $lang ] = array( 'translated' => 0, 'missing' => 0 ); }
		foreach ( $items as $item ) {
			foreach ( $langs as $lang ) {
				if ( $lang === ( $item['source_language'] ?? '' ) ) { continue; }
				$value = $item['existing_translations'][ $lang ] ?? '';
				if ( '' !== trim( (string) $value ) ) { $status[ $lang ]['translated']++; }
				else { $status[ $lang ]['missing']++; }
			}
		}
		return array( 'total_items' => count( $items ), 'languages' => $status, 'warnings' => self::validation_warnings( $items ) );
	}

	/** Collect package items from supported dynamic content scopes. */
	public static function collect_items( $args = array() ) {
		$source_language = self::sanitize_language( $args['source_language'] ?? self::default_source_language(), self::default_source_language() );
		$use_object_sources = array_key_exists( 'use_object_source_languages', $args ) ? ! empty( $args['use_object_source_languages'] ) : true;
		$targets = self::sanitize_selected_languages( $args['target_languages'] ?? array() );
		if ( empty( $targets ) ) {
			$targets = TAKA_Platform_I18n::instance()->get_all_languages();
		}
		$include_existing = ! empty( $args['include_existing_translations'] );
		$include_context = array_key_exists( 'include_context', $args ) ? ! empty( $args['include_context'] ) : true;
		$only_missing = ! empty( $args['only_missing_translations'] );
		$items = array();
		foreach ( self::object_definitions( array(), $include_context ) as $definition ) {
			foreach ( $definition['objects'] as $object_id => $object ) {
				$object_source = $use_object_sources ? self::sanitize_language( $object['source_language'] ?? $source_language, $source_language ) : $source_language;
				$item_targets = array_values( array_diff( $targets, array( $object_source ) ) );
				foreach ( $definition['fields'] as $field => $field_label ) {
					$value = $object['values'][ $field ] ?? '';
					$source_text = self::value_for_language( $value, $object_source );
					if ( '' === trim( (string) $source_text ) ) { continue; }
					$existing = array();
					$translations = array();
					$has_missing = false;
					foreach ( $item_targets as $target ) {
						$current = self::value_for_language( $value, $target, false );
						$existing[ $target ] = $include_existing ? $current : '';
						$translations[ $target ] = '';
						if ( '' === trim( (string) $current ) ) { $has_missing = true; }
					}
					if ( $only_missing && ! $has_missing ) { continue; }
					$item = array(
						'id' => $definition['type'] . ':' . $object_id . ':' . $field,
						'object_type' => $definition['type'],
						'object_id' => (string) $object_id,
						'field' => $field,
						'source_language' => $object_source,
						'source_text' => (string) $source_text,
						'source_hash' => self::hash( $source_text ),
						'existing_translations' => $existing,
						'translations' => $translations,
					);
					if ( $include_context ) {
						$item['context'] = $definition['context_prefix'] . ' / ' . $object['label'] . ' / ' . $field_label;
						if ( ! empty( $object['contexts'] ) && is_array( $object['contexts'] ) ) {
							$item['contexts'] = array_values( array_unique( array_map( 'strval', $object['contexts'] ) ) );
						}
					}
					$items[] = $item;
				}
			}
		}
		return $items;
	}

	private static function validation_warnings( $items ) {
		$warnings = array();
		if ( ! in_array( 'de', TAKA_Platform_I18n::instance()->get_all_languages(), true ) ) {
			return $warnings;
		}
		foreach ( $items as $item ) {
			if ( 'en' !== ( $item['source_language'] ?? '' ) ) {
				continue;
			}
			$translations = is_array( $item['translations'] ?? null ) ? $item['translations'] : array();
			$existing = is_array( $item['existing_translations'] ?? null ) ? $item['existing_translations'] : array();
			if ( ! array_key_exists( 'de', $translations ) && ! array_key_exists( 'de', $existing ) ) {
				$warnings[] = 'Missing German translation target for English source item: ' . ( $item['id'] ?? '' );
			}
		}
		return $warnings;
	}

	/** Import a package from decoded JSON. */
	public static function import_package( $package, $args = array() ) {
		$summary = array( 'imported' => 0, 'created' => 0, 'updated' => 0, 'skipped_existing' => 0, 'skipped_changed_source' => 0, 'errors' => array(), 'warnings' => array(), 'report' => array() );
		if ( ! is_array( $package ) || ( $package['package_type'] ?? '' ) !== self::PACKAGE_TYPE ) {
			$summary['errors'][] = 'Invalid package_type.';
			return $summary;
		}
		if ( (int) ( $package['format_version'] ?? 0 ) !== self::FORMAT_VERSION ) {
			$summary['errors'][] = 'Unsupported format_version.';
			return $summary;
		}
		$overwrite = ! empty( $args['overwrite_existing'] );
		$allow_changed = ! empty( $args['allow_changed_source'] );
		$index = self::current_item_index( (array) ( $package['items'] ?? array() ) );
		$changes = array();
		foreach ( (array) ( $package['items'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) || empty( $item['translations'] ) || ! is_array( $item['translations'] ) ) {
				$summary['errors'][] = 'Invalid item in package.';
				continue;
			}
			$id = (string) $item['id'];
			if ( empty( $index[ $id ] ) ) {
				$summary['warnings'][] = 'Unknown item skipped: ' . $id;
				foreach ( array_keys( (array) ( $item['translations'] ?? array() ) ) as $lang ) {
					$summary['report'][] = self::import_report_row( $item, $lang, 'skipped_unknown_item' );
				}
				continue;
			}
			$current = $index[ $id ];
			$translations = (array) ( $item['translations'] ?? array() );
			$raw_source_language = trim( (string) ( $item['source_language'] ?? '' ) );
			$current_source_language = self::sanitize_language( $current['source_language'] ?? self::default_source_language(), self::default_source_language() );
			$source_language = self::sanitize_language( $raw_source_language, '' );
			if ( '' === $source_language ) {
				$source_language = $current_source_language;
				$summary['warnings'][] = ( '' === $raw_source_language ? 'Missing' : 'Unsupported' ) . ' source language for ' . $id . '. Falling back to current object source language ' . $source_language . '.';
			}
			if ( $source_language !== $current_source_language ) {
				$summary['warnings'][] = 'Source language changed for ' . $id . '.';
			}
			$current_source = self::value_for_language( $current['value'], $source_language );
			if ( ! $allow_changed && ! hash_equals( (string) ( $item['source_hash'] ?? '' ), self::hash( $current_source ) ) ) {
				$summary['skipped_changed_source']++;
				$summary['warnings'][] = 'Source text changed since export: ' . $id;
				foreach ( array_keys( $translations ) as $lang ) {
					$summary['report'][] = self::import_report_row( $item, $lang, 'skipped_changed_source' );
				}
				continue;
			}
			foreach ( $translations as $lang => $translation ) {
				$raw_lang = (string) $lang;
				$lang = self::sanitize_language( $lang, '' );
				if ( '' === $lang ) {
					$summary['warnings'][] = 'Unsupported target language for ' . $id . ': ' . $raw_lang;
					$summary['report'][] = self::import_report_row( $item, $raw_lang, 'skipped_unsupported_language' );
					continue;
				}
				if ( $lang === $source_language ) {
					$summary['report'][] = self::import_report_row( $item, $lang, 'skipped_source_language' );
					continue;
				}
				if ( '' === trim( (string) $translation ) ) {
					$summary['report'][] = self::import_report_row( $item, $lang, 'skipped_empty_translation' );
					continue;
				}
				$existing = self::value_for_language( $current['value'], $lang, false );
				if ( ! $overwrite && '' !== trim( (string) $existing ) ) {
					$summary['skipped_existing']++;
					$summary['report'][] = self::import_report_row( $item, $lang, 'skipped_existing' );
					continue;
				}
				$changes[ $current['object_type'] ][ $current['object_id'] ][ $current['field'] ][ $lang ] = function_exists( 'wp_kses_post' ) ? wp_kses_post( $translation ) : sanitize_textarea_field( $translation );
				$summary['imported']++;
				if ( '' === trim( (string) $existing ) ) {
					$summary['created']++;
					$summary['report'][] = self::import_report_row( $item, $lang, 'created' );
				} else {
					$summary['updated']++;
					$summary['report'][] = self::import_report_row( $item, $lang, 'imported' );
				}
			}
		}
		if ( $summary['imported'] > 0 ) {
			self::apply_changes( $changes );
		}
		return $summary;
	}

	private static function import_report_row( $item, $target_language, $status ) {
		return array(
			'item_id' => (string) ( $item['id'] ?? '' ),
			'object_type' => (string) ( $item['object_type'] ?? '' ),
			'object_id' => (string) ( $item['object_id'] ?? '' ),
			'field' => (string) ( $item['field'] ?? '' ),
			'source_language' => (string) ( $item['source_language'] ?? '' ),
			'target_language' => (string) $target_language,
			'status' => (string) $status,
		);
	}

	/** Decode JSON safely. */
	public static function decode_json( $json ) {
		$decoded = json_decode( (string) $json, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	private static function object_definitions( $types = array(), $include_contexts = true ) {
		$types = array_values( array_filter( array_map( 'sanitize_key', (array) $types ) ) );
		$include_all = empty( $types );
		$wants = static function ( $type ) use ( $types, $include_all ) {
			return $include_all || in_array( $type, $types, true );
		};
		$definitions = array();

		if ( $wants( 'option_list' ) ) {
			$definitions[] = array(
				'type' => 'option_list',
				'context_prefix' => 'Option List',
				'fields' => array( 'label' => 'Label' ),
				'objects' => TAKA_Platform_Data::option_list_translation_objects(),
			);
		}

		if ( $wants( 'content_section' ) ) {
			$sections = array();
			foreach ( TAKA_Platform_Data::get_content_sections( false ) as $key => $section ) {
				$sections[ $key ] = array(
					'label' => self::value_for_language( $section['title'] ?? '', TAKA_Platform_Data::content_section_source_language( $section ) ) ?: (string) $key,
					'source_language' => TAKA_Platform_Data::content_section_source_language( $section ),
					'values' => array(),
				);
				foreach ( array( 'kicker', 'title', 'subtitle', 'body', 'button_label' ) as $field ) {
					$sections[ $key ]['values'][ $field ] = self::field_values_from_section( $section, $field );
				}
			}
			$definitions[] = array(
				'type' => 'content_section',
				'context_prefix' => 'Homepage / Content Section',
				'fields' => array( 'kicker' => 'Kicker', 'title' => 'Title', 'subtitle' => 'Subtitle', 'body' => 'Body', 'button_label' => 'Button label' ),
				'objects' => $sections,
			);
		}

		if ( $wants( 'content_block' ) ) {
			$definitions[] = array(
				'type' => 'content_block',
				'context_prefix' => 'Content Block',
				'fields' => TAKA_Platform_Data::content_block_text_fields(),
				'objects' => self::content_block_objects( $include_contexts ),
			);
		}

		if ( $wants( 'booking_information' ) ) {
			$booking = TAKA_Platform_Data::get_booking_information_settings( null, false );
			$definitions[] = array(
				'type' => 'booking_information',
				'context_prefix' => 'Booking Information',
				'fields' => array( 'title' => 'Title', 'intro' => 'Intro', 'group_booking' => 'Group booking', 'multi_event_discount' => 'Multi-event discount', 'booking_process' => 'Booking process', 'payment_methods' => 'Payment methods', 'cancellation_policy' => 'Cancellation policy', 'additional_notes' => 'Additional notes' ),
				'objects' => array( 'global' => array( 'label' => 'Global booking information', 'source_language' => self::sanitize_language( $booking['source_language'] ?? 'de' ), 'values' => $booking ) ),
			);
		}

		if ( $wants( 'ticket_section' ) ) {
			$tickets = TAKA_Platform_Data::get_ticket_section_settings( null, false );
			$definitions[] = array(
				'type' => 'ticket_section',
				'context_prefix' => 'Ticket Section',
				'fields' => array( 'kicker' => 'Kicker', 'title' => 'Title', 'intro' => 'Intro' ),
				'objects' => array( 'global' => array( 'label' => 'Global ticket section', 'source_language' => self::sanitize_language( $tickets['source_language'] ?? 'de' ), 'values' => array( 'kicker' => $tickets['kicker'] ?? '', 'title' => $tickets['heading'] ?? '', 'intro' => $tickets['intro'] ?? '' ) ) ),
			);
		}

		if ( $wants( 'hero' ) ) {
			$hero = TAKA_Platform_Data::get_hero_settings( false );
			$definitions[] = array(
				'type' => 'hero',
				'context_prefix' => 'Hero',
				'fields' => array( 'kicker' => 'Kicker', 'title' => 'Title', 'subtitle' => 'Subtitle', 'primary_button_label' => 'Primary button label', 'secondary_button_label' => 'Secondary button label' ),
				'objects' => array( 'global' => array( 'label' => 'Homepage hero', 'source_language' => self::sanitize_language( $hero['source_language'] ?? 'de' ), 'values' => array( 'kicker' => $hero['kicker'] ?? '', 'title' => $hero['title'] ?? '', 'subtitle' => $hero['description'] ?? '', 'primary_button_label' => $hero['primary_button_label'] ?? '', 'secondary_button_label' => $hero['secondary_button_label'] ?? '' ) ) ),
			);
		}

		if ( $wants( 'event' ) ) {
			$definitions[] = array(
				'type' => 'event',
				'context_prefix' => 'Event',
				'fields' => TAKA_Platform_Data::translatable_text_fields( 'event' ),
				'objects' => self::post_text_objects( TAKA_Platform_Data::get_events(), 'event' ),
			);
		}

		if ( $wants( 'organizer' ) ) {
			$definitions[] = array(
				'type' => 'organizer',
				'context_prefix' => 'Organizer',
				'fields' => TAKA_Platform_Data::translatable_text_fields( 'organizer' ),
				'objects' => self::post_text_objects( TAKA_Platform_Data::get_organizers(), 'organizer' ),
			);
		}

		if ( $wants( 'venue' ) ) {
			$definitions[] = array(
				'type' => 'venue',
				'context_prefix' => 'Venue',
				'fields' => TAKA_Platform_Data::translatable_text_fields( 'venue' ),
				'objects' => self::post_text_objects( TAKA_Platform_Data::get_venues(), 'venue' ),
			);
		}

		return $definitions;
	}

	private static function post_text_objects( $objects, $object_type ) {
		$out = array();
		$fields = TAKA_Platform_Data::translatable_text_fields( $object_type );
		foreach ( $objects as $object ) {
			if ( ! is_array( $object ) ) { continue; }
			$object_id = (string) ( $object['config_id'] ?? '' );
			if ( '' === $object_id ) { $object_id = (string) ( $object['id'] ?? '' ); }
			if ( '' === $object_id ) { continue; }
			$out[ $object_id ] = array(
				'label' => (string) ( $object['title'] ?? ( $object['name'] ?? ( $object['city'] ?? $object_id ) ) ),
				'source_language' => TAKA_Platform_Data::object_source_language( $object ),
				'values' => TAKA_Platform_Data::object_text_values( $object, $fields ),
			);
		}
		return $out;
	}

	private static function content_block_objects( $include_contexts = true ) {
		$out = array();
		$usage_contexts = $include_contexts ? TAKA_Platform_Data::content_block_usage_contexts() : array();
		foreach ( TAKA_Platform_Data::get_content_blocks( false ) as $key => $block ) {
			if ( ! is_array( $block ) || (string) ( $block['id'] ?? '' ) !== (string) $key ) { continue; }
			$object_id = (string) ( $block['slug'] ?? '' );
			if ( '' === $object_id ) { $object_id = (string) ( $block['id'] ?? '' ); }
			if ( '' === $object_id ) { continue; }
			$contexts = array_merge(
				$usage_contexts[ (string) ( $block['id'] ?? '' ) ] ?? array(),
				$usage_contexts[ (string) ( $block['slug'] ?? '' ) ] ?? array()
			);
			$out[ $object_id ] = array(
				'label' => (string) ( $block['internal_name'] ?? ( $block['title'] ?? $object_id ) ),
				'source_language' => TAKA_Platform_Data::object_source_language( $block ),
				'values' => TAKA_Platform_Data::object_text_values( $block, TAKA_Platform_Data::content_block_text_fields() ),
				'contexts' => $contexts,
			);
		}
		return $out;
	}

	private static function current_item_index( $package_items = array() ) {
		$wanted_ids = array();
		$wanted_types = array();
		foreach ( (array) $package_items as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) ) { continue; }
			$id = (string) $item['id'];
			$parts = explode( ':', $id, 3 );
			if ( 3 !== count( $parts ) ) { continue; }
			$wanted_ids[ $id ] = true;
			$wanted_types[ sanitize_key( $parts[0] ) ] = true;
		}
		$index = array();
		foreach ( self::object_definitions( array_keys( $wanted_types ), false ) as $definition ) {
			foreach ( $definition['objects'] as $object_id => $object ) {
				foreach ( array_keys( $definition['fields'] ) as $field ) {
					$id = $definition['type'] . ':' . $object_id . ':' . $field;
					if ( ! empty( $wanted_ids ) && empty( $wanted_ids[ $id ] ) ) { continue; }
					$index[ $id ] = array(
						'object_type' => $definition['type'],
						'object_id' => (string) $object_id,
						'field' => $field,
						'source_language' => $object['source_language'] ?? self::default_source_language(),
						'value' => $object['values'][ $field ] ?? '',
					);
				}
			}
		}
		return $index;
	}

	private static function current_value_for_item( $object_type, $object_id, $field ) {
		foreach ( self::object_definitions( array( $object_type ), false ) as $definition ) {
			if ( $definition['type'] !== $object_type || empty( $definition['objects'][ $object_id ] ) ) { continue; }
			return $definition['objects'][ $object_id ]['values'][ $field ] ?? '';
		}
		return '';
	}

	private static function apply_changes( $changes ) {
		if ( ! empty( $changes['content_section'] ) ) {
			$sections = get_option( TAKA_Platform_Data::SECTIONS_OPTION, array() );
			$sections = is_array( $sections ) ? $sections : array();
			foreach ( TAKA_Platform_Data::get_content_sections( false ) as $key => $section ) {
				$sections[ $key ] = array_merge( $section, $sections[ $key ] ?? array() );
			}
			foreach ( $changes['content_section'] as $key => $fields ) {
				if ( empty( $sections[ $key ] ) ) { continue; }
				$sections[ $key ] = TAKA_Platform_Data::normalize_content_section( $sections[ $key ] );
				foreach ( $fields as $field => $translations ) {
					foreach ( $translations as $lang => $text ) {
						$sections[ $key ]['translations'][ $lang ][ $field ] = $text;
					}
				}
			}
			update_option( TAKA_Platform_Data::SECTIONS_OPTION, $sections, false );
		}
		foreach ( array( 'booking_information' => TAKA_Platform_Data::BOOKING_OPTION, 'ticket_section' => TAKA_Platform_Data::TICKETS_OPTION, 'hero' => TAKA_Platform_Data::HERO_OPTION ) as $type => $option ) {
			if ( empty( $changes[ $type ]['global'] ) ) { continue; }
			$data = get_option( $option, array() );
			$data = is_array( $data ) ? $data : array();
			foreach ( $changes[ $type ]['global'] as $field => $translations ) {
				$internal_field = self::internal_field_name( $type, $field );
				$value = $data[ $internal_field ] ?? '';
				$value = is_array( $value ) ? $value : array( self::sanitize_language( $data['source_language'] ?? 'de' ) => (string) $value );
				foreach ( $translations as $lang => $text ) { $value[ $lang ] = $text; }
				$data[ $internal_field ] = $value;
			}
			update_option( $option, $data, false );
		}
		foreach ( array( 'event' => TAKA_PLATFORM_CPT_EVENT, 'organizer' => TAKA_PLATFORM_CPT_ORGANIZER, 'venue' => TAKA_PLATFORM_CPT_VENUE, 'content_block' => TAKA_PLATFORM_CPT_CONTENT_BLOCK ) as $type => $post_type ) {
			if ( ! empty( $changes[ $type ] ) ) {
				self::apply_post_text_changes( $post_type, $changes[ $type ] );
			}
		}
		if ( ! empty( $changes['option_list'] ) ) {
			TAKA_Platform_Data::update_option_list_translations( $changes['option_list'] );
		}
	}

	private static function apply_post_text_changes( $post_type, $objects ) {
		foreach ( $objects as $object_id => $fields ) {
			$post_id = self::find_post_id( $post_type, $object_id );
			if ( ! $post_id ) { continue; }
			$stored = get_post_meta( $post_id, '_taka_text_translations', true );
			$stored = is_array( $stored ) ? $stored : array();
			foreach ( $fields as $field => $translations ) {
				foreach ( $translations as $lang => $text ) {
					$stored[ $field ][ $lang ] = $text;
				}
			}
			update_post_meta( $post_id, '_taka_text_translations', $stored );
		}
	}

	private static function find_post_id( $post_type, $object_id ) {
		if ( is_numeric( $object_id ) && get_post( (int) $object_id ) ) {
			$post = get_post( (int) $object_id );
			if ( $post && $post_type === $post->post_type ) { return (int) $post->ID; }
		}
		$posts = get_posts( array( 'post_type' => $post_type, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_taka_config_id', 'meta_value' => (string) $object_id ) );
		if ( empty( $posts ) && defined( 'TAKA_PLATFORM_CPT_CONTENT_BLOCK' ) && TAKA_PLATFORM_CPT_CONTENT_BLOCK === $post_type ) {
			$posts = get_posts( array( 'post_type' => $post_type, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_taka_block_slug', 'meta_value' => (string) $object_id ) );
		}
		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}

	private static function internal_field_name( $type, $field ) {
		if ( 'ticket_section' === $type && 'title' === $field ) { return 'heading'; }
		if ( 'hero' === $type && 'subtitle' === $field ) { return 'description'; }
		return $field;
	}

	private static function field_values_from_section( $section, $field ) {
		$values = array();
		foreach ( TAKA_Platform_I18n::instance()->get_all_languages() as $lang ) {
			$values[ $lang ] = $section['translations'][ $lang ][ $field ] ?? '';
		}
		return $values;
	}

	private static function value_for_language( $value, $lang, $fallback = true ) {
		$lang = self::sanitize_language( $lang, self::default_source_language() );
		if ( is_array( $value ) ) {
			if ( isset( $value[ $lang ] ) && '' !== trim( (string) $value[ $lang ] ) ) { return (string) $value[ $lang ]; }
			if ( ! $fallback ) { return ''; }
			return TAKA_Platform_Data::resolve_dynamic_text( $value, $lang, $lang );
		}
		return (string) $value;
	}

	private static function hash( $text ) {
		return hash( 'sha256', (string) $text );
	}

	private static function lines_to_array( $value ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $value ) ) ) );
	}
}

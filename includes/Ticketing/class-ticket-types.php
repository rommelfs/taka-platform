<?php
/**
 * Native TAKA ticket type configuration for Phase 1.
 *
 * Phase 1 stores ticket types as structured event meta. The shape is kept
 * intentionally close to the future order/ticket table model so a later
 * migration can move this configuration without changing admin UI callers.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Ticketing_Ticket_Types {
	const META_KEY = '_taka_native_ticket_types';

	/** Ticket type status labels for admin UI. */
	public static function statuses() {
		return array(
			'active'   => __( 'Active', 'taka-platform' ),
			'hidden'   => __( 'Hidden', 'taka-platform' ),
			'sold_out' => __( 'Sold out', 'taka-platform' ),
			'disabled' => __( 'Disabled', 'taka-platform' ),
		);
	}

	/** Load normalized ticket types from an event. */
	public static function get_for_event( $event_id ) {
		return self::normalize_ticket_types( get_post_meta( absint( $event_id ), self::META_KEY, true ) );
	}

	/** Normalize a list of ticket type arrays from admin, import or stored meta. */
	public static function normalize_ticket_types( $items ) {
		$items = is_array( $items ) ? $items : array();
		$clean = array();

		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) || ! empty( $item['remove'] ) ) {
				continue;
			}
			if ( ! self::row_has_content( $item ) ) {
				continue;
			}

			$ticket_type = self::normalize_ticket_type( $item, $index );
			if ( '' === $ticket_type['name'] ) {
				continue;
			}
			$clean[] = $ticket_type;
		}

		usort(
			$clean,
			static function ( $a, $b ) {
				return ( (int) ( $a['sort_order'] ?? 0 ) <=> (int) ( $b['sort_order'] ?? 0 ) ) ?: strcmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
			}
		);

		return array_values( $clean );
	}

	private static function normalize_ticket_type( $item, $index ) {
		$source_language = self::sanitize_language( $item['source_language'] ?? '', '' );
		$name_translations = self::normalize_text_translations( $item['name_translations'] ?? ( is_array( $item['name'] ?? null ) ? $item['name'] : array() ), false );
		$description_translations = self::normalize_text_translations( $item['description_translations'] ?? ( is_array( $item['description'] ?? null ) ? $item['description'] : array() ), true );
		$name = is_array( $item['name'] ?? null ) ? self::source_value( $name_translations, $source_language ) : sanitize_text_field( $item['name'] ?? '' );
		$id = sanitize_key( $item['id'] ?? '' );
		if ( '' === $id ) {
			$id = self::generated_id( $name, $index );
		}

		$status = sanitize_key( $item['status'] ?? 'active' );
		if ( ! isset( self::statuses()[ $status ] ) ) {
			$status = 'active';
		}

		$currency = strtoupper( sanitize_text_field( $item['currency'] ?? '' ) );
		$currency = '' !== $currency ? TAKA_Platform_Data::normalize_event_option_value( 'currency', $currency ) : 'EUR';
		$capacity = $item['capacity'] ?? ( $item['quantity'] ?? '' );

		return array(
			'id'              => $id,
			'source_language' => $source_language,
			'name'            => $name,
			'name_translations' => $name_translations,
			'description'     => is_array( $item['description'] ?? null ) ? self::source_value( $description_translations, $source_language ) : sanitize_textarea_field( $item['description'] ?? '' ),
			'description_translations' => $description_translations,
			'price'           => TAKA_Platform_Data::sanitize_money_value( $item['price'] ?? '' ),
			'currency'        => '' !== $currency ? $currency : 'EUR',
			'capacity'        => '' === trim( (string) $capacity ) ? '' : (string) absint( $capacity ),
			'sale_start_date' => self::sanitize_date( $item['sale_start_date'] ?? '' ),
			'sale_start_time' => self::sanitize_time( $item['sale_start_time'] ?? '' ),
			'sale_end_date'   => self::sanitize_date( $item['sale_end_date'] ?? '' ),
			'sale_end_time'   => self::sanitize_time( $item['sale_end_time'] ?? '' ),
			'status'          => $status,
			'sort_order'      => (int) ( $item['sort_order'] ?? 0 ),
		);
	}

	private static function row_has_content( $item ) {
		foreach ( array( 'id', 'name', 'name_translations', 'description', 'description_translations', 'price', 'capacity', 'quantity', 'sale_start_date', 'sale_end_date' ) as $field ) {
			$value = $item[ $field ] ?? '';
			if ( is_array( $value ) ? self::has_translation_value( $value ) : '' !== trim( (string) $value ) ) {
				return true;
			}
		}
		return false;
	}

	/** Resolve ticket type names/descriptions for the public frontend language. */
	public static function resolve_for_language( $items, $lang, $event_source_language = 'de', $event_id = 0 ) {
		$lang = self::sanitize_language( $lang ?: taka_tour_current_language(), TAKA_Platform_Data::platform_fallback_language() );
		$event_source_language = self::sanitize_language( $event_source_language, TAKA_Platform_Data::platform_fallback_language() );
		$resolved = array();
		foreach ( self::normalize_ticket_types( $items ) as $item ) {
			$source_language = self::sanitize_language( $item['source_language'] ?? '', $event_source_language );
			foreach ( array( 'name', 'description' ) as $field ) {
				$translations_key = $field . '_translations';
				$values = is_array( $item[ $translations_key ] ?? null ) ? $item[ $translations_key ] : array();
				if ( '' !== trim( (string) ( $item[ $field ] ?? '' ) ) ) {
					$values[ $source_language ] = (string) $item[ $field ];
				}
				$item[ $field ] = TAKA_Platform_Data::resolve_dynamic_text(
					$values,
					$lang,
					$source_language,
					array(
						'object_type' => 'native_ticket_type',
						'object_id'   => absint( $event_id ) . ':' . (string) ( $item['id'] ?? '' ),
						'field'       => $field,
					)
				);
			}
			$resolved[] = $item;
		}
		return $resolved;
	}

	/** Preserve stored ticket type translations when the scalar admin editor is saved again. */
	public static function merge_translation_state( $items, $existing_items ) {
		$existing_by_id = array();
		foreach ( self::normalize_ticket_types( $existing_items ) as $existing ) {
			$id = sanitize_key( $existing['id'] ?? '' );
			if ( '' !== $id ) {
				$existing_by_id[ $id ] = $existing;
			}
		}

		$merged = array();
		foreach ( self::normalize_ticket_types( $items ) as $item ) {
			$id = sanitize_key( $item['id'] ?? '' );
			$existing = '' !== $id && isset( $existing_by_id[ $id ] ) ? $existing_by_id[ $id ] : array();
			if ( '' === (string) ( $item['source_language'] ?? '' ) && '' !== (string) ( $existing['source_language'] ?? '' ) ) {
				$item['source_language'] = (string) $existing['source_language'];
			}
			foreach ( array( 'name_translations', 'description_translations' ) as $field ) {
				if ( ! self::has_translation_value( $item[ $field ] ?? array() ) && self::has_translation_value( $existing[ $field ] ?? array() ) ) {
					$item[ $field ] = $existing[ $field ];
				}
			}
			$merged[] = $item;
		}

		return $merged;
	}

	private static function generated_id( $name, $index ) {
		$base = sanitize_key( sanitize_title( $name ) );
		if ( '' === $base ) {
			$base = 'ticket';
		}
		return substr( $base, 0, 40 ) . '-' . absint( $index + 1 );
	}

	private static function normalize_text_translations( $value, $textarea = false ) {
		$value = is_array( $value ) ? $value : array();
		$out = array();
		foreach ( TAKA_Platform_Data::content_section_languages() as $lang ) {
			$text = $value[ $lang ] ?? '';
			$out[ $lang ] = $textarea ? sanitize_textarea_field( $text ) : sanitize_text_field( $text );
		}
		return $out;
	}

	private static function source_value( $translations, $source_language ) {
		$source_language = self::sanitize_language( $source_language, TAKA_Platform_Data::platform_fallback_language() );
		$value = $translations[ $source_language ] ?? '';
		if ( '' !== trim( (string) $value ) ) {
			return (string) $value;
		}
		foreach ( (array) $translations as $candidate ) {
			if ( '' !== trim( (string) $candidate ) ) {
				return (string) $candidate;
			}
		}
		return '';
	}

	private static function has_translation_value( $translations ) {
		foreach ( (array) $translations as $candidate ) {
			if ( '' !== trim( (string) $candidate ) ) {
				return true;
			}
		}
		return false;
	}

	private static function sanitize_language( $lang, $fallback = 'de' ) {
		$lang = sanitize_key( (string) $lang );
		return in_array( $lang, TAKA_Platform_Data::content_section_languages(), true ) ? $lang : $fallback;
	}

	private static function sanitize_date( $value ) {
		$value = trim( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}

	private static function sanitize_time( $value ) {
		$value = trim( (string) $value );
		return preg_match( '/^\d{2}:\d{2}$/', $value ) ? $value : '';
	}
}

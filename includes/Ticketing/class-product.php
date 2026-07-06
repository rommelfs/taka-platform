<?php
/**
 * Generic ticketing product normalization for native TAKA Ticketing.
 *
 * Products are not ticket types. They represent add-ons, standalone purchases,
 * meals, merch, donations and future purchasable units that can be converted
 * into order line items by the pricing service.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Ticketing_Product {
	public static function types() {
		return array(
			'event_ticket' => __( 'Event ticket', 'taka-platform' ),
			'add_on'       => __( 'Add-on', 'taka-platform' ),
			'standalone'   => __( 'Standalone', 'taka-platform' ),
			'meal'         => __( 'Meal', 'taka-platform' ),
			'party'        => __( 'Party', 'taka-platform' ),
			'merch'        => __( 'Merch', 'taka-platform' ),
			'donation'     => __( 'Donation', 'taka-platform' ),
			'other'        => __( 'Other', 'taka-platform' ),
		);
	}

	public static function statuses() {
		return array(
			'active'   => __( 'Active', 'taka-platform' ),
			'hidden'   => __( 'Hidden', 'taka-platform' ),
			'sold_out' => __( 'Sold out', 'taka-platform' ),
			'disabled' => __( 'Disabled', 'taka-platform' ),
		);
	}

	public static function normalize( $data ) {
		$data = is_array( $data ) ? $data : array();
		$type = sanitize_key( $data['type'] ?? 'add_on' );
		if ( ! isset( self::types()[ $type ] ) ) {
			$type = 'add_on';
		}

		$status = sanitize_key( $data['status'] ?? 'active' );
		if ( ! isset( self::statuses()[ $status ] ) ) {
			$status = 'active';
		}

		$currency = strtoupper( sanitize_text_field( $data['currency'] ?? '' ) );
		$currency = '' !== $currency ? TAKA_Platform_Data::normalize_event_option_value( 'currency', $currency ) : 'EUR';

		return array(
			'id'                         => absint( $data['id'] ?? 0 ),
			'product_id'                 => self::normalize_product_id( $data['product_id'] ?? ( $data['slug'] ?? '' ) ),
			'source_language'            => self::sanitize_language( $data['source_language'] ?? '', '' ),
			'title'                      => is_array( $data['title'] ?? null ) ? self::source_value( self::normalize_text_translations( $data['title'], false ), self::sanitize_language( $data['source_language'] ?? '' ) ) : sanitize_text_field( $data['title'] ?? '' ),
			'title_translations'         => self::normalize_text_translations( $data['title_translations'] ?? ( is_array( $data['title'] ?? null ) ? $data['title'] : array() ), false ),
			'description'                => is_array( $data['description'] ?? null ) ? self::source_value( self::normalize_text_translations( $data['description'], true ), self::sanitize_language( $data['source_language'] ?? '' ) ) : sanitize_textarea_field( $data['description'] ?? '' ),
			'description_translations'   => self::normalize_text_translations( $data['description_translations'] ?? ( is_array( $data['description'] ?? null ) ? $data['description'] : array() ), true ),
			'type'                       => $type,
			'price'                      => TAKA_Platform_Data::sanitize_money_value( $data['price'] ?? '' ),
			'currency'                   => '' !== $currency ? $currency : 'EUR',
			'capacity'                   => self::positive_int_or_empty( $data['capacity'] ?? ( $data['stock'] ?? '' ) ),
			'sale_start_date'            => self::sanitize_date( $data['sale_start_date'] ?? '' ),
			'sale_start_time'            => self::sanitize_time( $data['sale_start_time'] ?? '' ),
			'sale_end_date'              => self::sanitize_date( $data['sale_end_date'] ?? '' ),
			'sale_end_time'              => self::sanitize_time( $data['sale_end_time'] ?? '' ),
			'related_event_id'           => absint( $data['related_event_id'] ?? 0 ),
			'related_tour_id'            => sanitize_text_field( $data['related_tour_id'] ?? '' ),
			'requires_event_ticket'      => ! empty( $data['requires_event_ticket'] ) ? '1' : '0',
			'can_purchase_standalone'    => ! empty( $data['can_purchase_standalone'] ) ? '1' : '0',
			'visible_in_checkout'        => isset( $data['visible_in_checkout'] ) ? ( ! empty( $data['visible_in_checkout'] ) ? '1' : '0' ) : '1',
			'max_quantity_per_order'     => max( 1, absint( $data['max_quantity_per_order'] ?? 1 ) ),
			'sort_order'                 => (int) ( $data['sort_order'] ?? 0 ),
			'status'                     => $status,
		);
	}

	/** Resolve title/description with the shared public translation fallback chain. */
	public static function resolve_for_language( $product, $lang = null, $fallback_source_language = 'de' ) {
		$product = self::normalize( $product );
		$lang = self::sanitize_language( $lang ?: taka_tour_current_language(), TAKA_Platform_Data::platform_fallback_language() );
		$source_language = self::sanitize_language( $product['source_language'] ?? '', self::sanitize_language( $fallback_source_language, TAKA_Platform_Data::platform_fallback_language() ) );
		foreach ( array( 'title', 'description' ) as $field ) {
			$translations_key = $field . '_translations';
			$values = is_array( $product[ $translations_key ] ?? null ) ? $product[ $translations_key ] : array();
			if ( '' !== trim( (string) ( $product[ $field ] ?? '' ) ) ) {
				$values[ $source_language ] = (string) $product[ $field ];
			}
			$product[ $field ] = TAKA_Platform_Data::resolve_dynamic_text(
				$values,
				$lang,
				$source_language,
				array(
					'object_type' => 'ticket_product',
					'object_id'   => (string) ( $product['product_id'] ?? ( $product['id'] ?? '' ) ),
					'field'       => $field,
				)
			);
		}
		return $product;
	}

	public static function normalize_product_id( $product_id ) {
		return sanitize_key( sanitize_title( (string) $product_id ) );
	}

	public static function line_item_from_product( $product, $quantity, $event_id = 0 ) {
		$product = self::normalize( $product );
		$quantity = max( 0, absint( $quantity ) );
		$unit = TAKA_Ticketing_Pricing_Service::normalize_money( $product['price'] ?? '0' );
		$total = TAKA_Ticketing_Pricing_Service::normalize_money( TAKA_Ticketing_Pricing_Service::money_to_float( $unit ) * $quantity );
		return array(
			'item_type'       => 'product',
			'product_id'      => $product['product_id'],
			'product_post_id' => absint( $product['id'] ?? 0 ),
			'title'           => $product['title'],
			'quantity'        => $quantity,
			'unit_price'      => $unit,
			'total_price'     => $total,
			'currency'        => $product['currency'],
			'related_event_id' => absint( $event_id ?: ( $product['related_event_id'] ?? 0 ) ),
		);
	}

	private static function positive_int_or_empty( $value ) {
		return '' === trim( (string) $value ) ? '' : (string) max( 0, absint( $value ) );
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

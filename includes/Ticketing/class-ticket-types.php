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
		$name = sanitize_text_field( $item['name'] ?? '' );
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
			'name'            => $name,
			'description'     => sanitize_textarea_field( $item['description'] ?? '' ),
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
		foreach ( array( 'id', 'name', 'description', 'price', 'capacity', 'quantity', 'sale_start_date', 'sale_end_date' ) as $field ) {
			if ( '' !== trim( (string) ( $item[ $field ] ?? '' ) ) ) {
				return true;
			}
		}
		return false;
	}

	private static function generated_id( $name, $index ) {
		$base = sanitize_key( sanitize_title( $name ) );
		if ( '' === $base ) {
			$base = 'ticket';
		}
		return substr( $base, 0, 40 ) . '-' . absint( $index + 1 );
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

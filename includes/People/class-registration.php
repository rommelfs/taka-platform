<?php
/**
 * Registration value normalization for People -> Event attendance links.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_People_Registration {
	public static function normalize( $data ) {
		$data = is_array( $data ) ? $data : array();
		return array(
			'id'                  => absint( $data['id'] ?? 0 ),
			'person_id'           => absint( $data['person_id'] ?? 0 ),
			'event_id'            => absint( $data['event_id'] ?? 0 ),
			'event_title'         => sanitize_text_field( $data['event_title'] ?? '' ),
			'ticket_type_id'      => sanitize_key( $data['ticket_type_id'] ?? '' ),
			'ticket_type_name'    => sanitize_text_field( $data['ticket_type_name'] ?? '' ),
			'order_id'            => absint( $data['order_id'] ?? 0 ),
			'order_number'        => sanitize_text_field( $data['order_number'] ?? '' ),
			'payment_method'      => sanitize_key( $data['payment_method'] ?? '' ),
			'payment_status'      => sanitize_key( $data['payment_status'] ?? 'pending' ),
			'registration_status' => sanitize_key( $data['registration_status'] ?? 'confirmed' ),
			'checkin_status'      => sanitize_key( $data['checkin_status'] ?? 'not_checked_in' ),
			'attendance_state'    => self::attendance_state( $data ),
			'validation_token'    => sanitize_text_field( $data['validation_token'] ?? '' ),
			'checked_in_at'       => sanitize_text_field( $data['checked_in_at'] ?? '' ),
			'checked_in_by'       => absint( $data['checked_in_by'] ?? 0 ),
			'internal_notes'      => sanitize_textarea_field( $data['internal_notes'] ?? '' ),
			'walk_in'             => ! empty( $data['walk_in'] ) ? '1' : '0',
			'attendance_sessions' => self::normalize_attendance_sessions( $data['attendance_sessions'] ?? array() ),
			'operations_timeline' => self::normalize_operations_timeline( $data['operations_timeline'] ?? array() ),
			'line_items'          => self::normalize_line_items( $data['line_items'] ?? array() ),
			'created_at'          => sanitize_text_field( $data['created_at'] ?? '' ),
			'updated_at'          => sanitize_text_field( $data['updated_at'] ?? '' ),
		);
	}

	public static function from_order_data( $order_data, $person_id ) {
		$order_data = is_array( $order_data ) ? $order_data : array();
		return self::normalize(
			array(
				'person_id'           => $person_id,
				'event_id'            => $order_data['event_id'] ?? 0,
				'event_title'         => $order_data['event_title'] ?? '',
				'ticket_type_id'      => $order_data['ticket_type_id'] ?? '',
				'ticket_type_name'    => $order_data['ticket_type_name'] ?? '',
				'order_id'            => $order_data['id'] ?? 0,
				'order_number'        => $order_data['order_number'] ?? '',
				'payment_method'      => $order_data['payment_method'] ?? '',
				'payment_status'      => $order_data['payment_status'] ?? 'pending',
				'registration_status' => self::registration_status_from_order( $order_data ),
				'checkin_status'      => $order_data['checkin_status'] ?? 'not_checked_in',
				'attendance_state'    => self::attendance_state_from_order( $order_data ),
				'walk_in'             => ! empty( $order_data['walk_in'] ) ? '1' : '0',
				'line_items'          => $order_data['line_items'] ?? array(),
				'created_at'          => $order_data['created_at'] ?? '',
			)
		);
	}

	public static function unique_key( $registration ) {
		$registration = self::normalize( $registration );
		if ( ! $registration['person_id'] || ! $registration['order_id'] ) {
			return '';
		}
		return sanitize_key( implode( '|', array( $registration['person_id'], $registration['event_id'], $registration['order_id'], $registration['ticket_type_id'] ) ) );
	}

	private static function registration_status_from_order( $order_data ) {
		$status = sanitize_key( $order_data['order_status'] ?? 'confirmed' );
		if ( 'cancelled' === $status ) {
			return 'cancelled';
		}
		return 'confirmed';
	}

	private static function attendance_state_from_order( $order_data ) {
		$checkin = sanitize_key( $order_data['checkin_status'] ?? 'not_checked_in' );
		if ( 'checked_in' === $checkin ) {
			return 'checked_in';
		}
		if ( 'cancelled' === sanitize_key( $order_data['order_status'] ?? '' ) ) {
			return 'cancelled';
		}
		return 'registered';
	}

	private static function attendance_state( $data ) {
		$state = sanitize_key( $data['attendance_state'] ?? '' );
		if ( ! in_array( $state, self::attendance_states(), true ) ) {
			$state = '';
		}
		if ( '' !== $state ) {
			return $state;
		}
		if ( 'checked_in' === sanitize_key( $data['checkin_status'] ?? '' ) ) {
			return 'checked_in';
		}
		if ( 'cancelled' === sanitize_key( $data['registration_status'] ?? '' ) ) {
			return 'cancelled';
		}
		return ! empty( $data['walk_in'] ) ? 'walk_in' : 'registered';
	}

	private static function attendance_states() {
		return array( 'registered', 'checked_in', 'checked_out', 'cancelled', 'no_show', 'walk_in' );
	}

	private static function normalize_attendance_sessions( $sessions ) {
		$out = array();
		foreach ( (array) $sessions as $session ) {
			if ( ! is_array( $session ) ) {
				continue;
			}
			$out[] = array(
				'id'            => sanitize_key( $session['id'] ?? '' ),
				'label'         => sanitize_text_field( $session['label'] ?? '' ),
				'state'         => sanitize_key( $session['state'] ?? '' ),
				'checked_in_at' => sanitize_text_field( $session['checked_in_at'] ?? '' ),
				'checked_in_by' => absint( $session['checked_in_by'] ?? 0 ),
			);
		}
		return $out;
	}

	private static function normalize_operations_timeline( $items ) {
		$out = array();
		foreach ( (array) $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$label = sanitize_text_field( $item['label'] ?? '' );
			if ( '' === $label ) {
				continue;
			}
			$out[] = array(
				'time'    => sanitize_text_field( $item['time'] ?? '' ),
				'label'   => $label,
				'user_id' => absint( $item['user_id'] ?? 0 ),
			);
		}
		return $out;
	}

	private static function normalize_line_items( $items ) {
		$out = array();
		foreach ( (array) $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$out[] = array(
				'item_type'   => sanitize_key( $item['item_type'] ?? '' ),
				'product_id'  => class_exists( 'TAKA_Ticketing_Product' ) ? TAKA_Ticketing_Product::normalize_product_id( $item['product_id'] ?? '' ) : sanitize_key( $item['product_id'] ?? '' ),
				'title'       => sanitize_text_field( $item['title'] ?? '' ),
				'quantity'    => max( 1, absint( $item['quantity'] ?? 1 ) ),
				'total_price' => class_exists( 'TAKA_Ticketing_Pricing_Service' ) ? TAKA_Ticketing_Pricing_Service::normalize_money( $item['total_price'] ?? '0' ) : sanitize_text_field( $item['total_price'] ?? '0' ),
				'currency'    => class_exists( 'TAKA_Platform_Data' ) ? TAKA_Platform_Data::normalize_event_option_value( 'currency', $item['currency'] ?? 'EUR' ) : sanitize_text_field( $item['currency'] ?? 'EUR' ),
			);
		}
		return $out;
	}
}

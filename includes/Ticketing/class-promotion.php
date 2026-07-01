<?php
/**
 * Promotion and voucher normalization for native TAKA Ticketing.
 *
 * Promotions are deliberately richer than discount codes: a voucher can carry
 * one or more benefits, including monetary discounts and non-ticket benefits.
 * Checkout and admin callers should use this normalizer and the pricing service
 * instead of implementing local promotion rules.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Ticketing_Promotion {
	public static function categories() {
		return array(
			'discount'   => __( 'Discount', 'taka-platform' ),
			'invitation' => __( 'Invitation', 'taka-platform' ),
			'staff'      => __( 'Staff', 'taka-platform' ),
			'sponsor'    => __( 'Sponsor', 'taka-platform' ),
			'press'      => __( 'Press', 'taka-platform' ),
			'vip'        => __( 'VIP', 'taka-platform' ),
			'internal'   => __( 'Internal', 'taka-platform' ),
		);
	}

	public static function statuses() {
		return array(
			'active'   => __( 'Active', 'taka-platform' ),
			'disabled' => __( 'Disabled', 'taka-platform' ),
			'expired'  => __( 'Expired', 'taka-platform' ),
		);
	}

	public static function scope_types() {
		return array(
			'all'         => __( 'All events', 'taka-platform' ),
			'tour'        => __( 'Selected tour', 'taka-platform' ),
			'event'       => __( 'Selected event', 'taka-platform' ),
			'ticket_type' => __( 'Selected ticket type', 'taka-platform' ),
		);
	}

	public static function applies_to_choices() {
		return array(
			'entire_order' => __( 'Entire order', 'taka-platform' ),
			'ticket_only'  => __( 'Ticket only', 'taka-platform' ),
			'add_ons_only' => __( 'Add-ons only', 'taka-platform' ),
			'product'      => __( 'Specific product', 'taka-platform' ),
		);
	}

	public static function benefit_types() {
		return array(
			'free_ticket'              => __( 'Free ticket', 'taka-platform' ),
			'percentage_discount'      => __( 'Percentage discount', 'taka-platform' ),
			'fixed_discount'           => __( 'Fixed amount discount', 'taka-platform' ),
			'included_meal'            => __( 'Included meal', 'taka-platform' ),
			'included_merch'           => __( 'Included merch', 'taka-platform' ),
			'special_access'           => __( 'Special access', 'taka-platform' ),
			'manual_note'              => __( 'Manual note', 'taka-platform' ),
			'manual_approval_required' => __( 'Manual approval required', 'taka-platform' ),
		);
	}

	public static function frontend_benefit_label( $type, $lang = null ) {
		$labels = array(
			'free_ticket'              => TAKA_Ticketing_Module::text( 'ticketing.benefit_free_ticket', 'Free ticket', $lang ),
			'percentage_discount'      => TAKA_Ticketing_Module::text( 'ticketing.benefit_percentage_discount', 'Percentage discount', $lang ),
			'fixed_discount'           => TAKA_Ticketing_Module::text( 'ticketing.benefit_fixed_discount', 'Fixed amount discount', $lang ),
			'included_meal'            => TAKA_Ticketing_Module::text( 'ticketing.benefit_included_meal', 'Included meal', $lang ),
			'included_merch'           => TAKA_Ticketing_Module::text( 'ticketing.benefit_included_merch', 'Included merch', $lang ),
			'special_access'           => TAKA_Ticketing_Module::text( 'ticketing.benefit_special_access', 'Special access', $lang ),
			'manual_note'              => TAKA_Ticketing_Module::text( 'ticketing.benefit_manual_note', 'Special note', $lang ),
			'manual_approval_required' => TAKA_Ticketing_Module::text( 'ticketing.benefit_manual_approval_required', 'Manual approval required', $lang ),
		);
		return $labels[ $type ] ?? sanitize_text_field( $type );
	}

	public static function normalize( $data ) {
		$data = is_array( $data ) ? $data : array();
		$category = sanitize_key( $data['category'] ?? 'discount' );
		if ( ! isset( self::categories()[ $category ] ) ) {
			$category = 'discount';
		}

		$status = sanitize_key( $data['status'] ?? 'active' );
		if ( ! isset( self::statuses()[ $status ] ) ) {
			$status = 'active';
		}

		$scope_type = sanitize_key( $data['scope_type'] ?? ( $data['scope'] ?? 'all' ) );
		if ( ! isset( self::scope_types()[ $scope_type ] ) ) {
			$scope_type = 'all';
		}

		$applies_to = sanitize_key( $data['applies_to'] ?? 'entire_order' );
		if ( ! isset( self::applies_to_choices()[ $applies_to ] ) ) {
			$applies_to = 'entire_order';
		}

		return array(
			'id'                   => absint( $data['id'] ?? 0 ),
			'code'                 => self::normalize_code( $data['code'] ?? '' ),
			'title'                => sanitize_text_field( $data['title'] ?? '' ),
			'description'          => sanitize_textarea_field( $data['description'] ?? '' ),
			'category'             => $category,
			'valid_from'           => self::sanitize_date( $data['valid_from'] ?? '' ),
			'valid_until'          => self::sanitize_date( $data['valid_until'] ?? '' ),
			'max_total_uses'       => self::positive_int_or_empty( $data['max_total_uses'] ?? '' ),
			'max_uses_per_person'  => self::positive_int_or_empty( $data['max_uses_per_person'] ?? ( $data['max_uses_per_email'] ?? '' ) ),
			'scope_type'           => $scope_type,
			'scope_tour_id'        => sanitize_text_field( $data['scope_tour_id'] ?? '' ),
			'scope_event_id'       => absint( $data['scope_event_id'] ?? 0 ),
			'scope_ticket_type_id' => sanitize_key( $data['scope_ticket_type_id'] ?? '' ),
			'scope_product_id'     => TAKA_Ticketing_Product::normalize_product_id( $data['scope_product_id'] ?? '' ),
			'applies_to'           => $applies_to,
			'status'               => $status,
			'benefits'             => self::normalize_benefits( $data['benefits'] ?? array() ),
		);
	}

	public static function normalize_code( $code ) {
		return strtoupper( preg_replace( '/[^A-Z0-9_-]/', '', strtoupper( sanitize_text_field( (string) $code ) ) ) );
	}

	public static function snapshot_for_order( $promotion, $pricing, $lang = null ) {
		$promotion = self::normalize( $promotion );
		$benefits = array();
		foreach ( $promotion['benefits'] as $benefit ) {
			$benefits[] = array(
				'type'  => $benefit['type'],
				'label' => self::frontend_benefit_label( $benefit['type'], $lang ),
				'value' => $benefit['value'],
				'note'  => $benefit['note'],
			);
		}

		return array(
			'id'              => absint( $promotion['id'] ?? 0 ),
			'code'            => $promotion['code'],
			'title'           => $promotion['title'],
			'category'        => $promotion['category'],
			'discount_amount' => TAKA_Ticketing_Pricing_Service::normalize_money( $pricing['discount_amount'] ?? '0' ),
			'final_amount'    => TAKA_Ticketing_Pricing_Service::normalize_money( $pricing['final_amount'] ?? '0' ),
			'benefits'        => $benefits,
		);
	}

	private static function normalize_benefits( $benefits ) {
		$benefits = is_array( $benefits ) ? $benefits : array();
		$rows = array();

		foreach ( $benefits as $key => $benefit ) {
			$is_keyed_admin_row = isset( self::benefit_types()[ $key ] );
			if ( $is_keyed_admin_row && ( ! is_array( $benefit ) || empty( $benefit['enabled'] ) ) ) {
				continue;
			}

			if ( is_array( $benefit ) && $is_keyed_admin_row ) {
				$benefit['type'] = $key;
			} elseif ( ! is_array( $benefit ) && $is_keyed_admin_row ) {
				$benefit = array( 'type' => $key, 'value' => $benefit );
			} elseif ( ! is_array( $benefit ) ) {
				continue;
			}

			$type = sanitize_key( $benefit['type'] ?? '' );
			if ( ! isset( self::benefit_types()[ $type ] ) ) {
				continue;
			}

			$value = '';
			if ( 'percentage_discount' === $type ) {
				$value = self::percentage_value( $benefit['value'] ?? '' );
				if ( '' === $value ) {
					continue;
				}
			} elseif ( 'fixed_discount' === $type ) {
				$value = TAKA_Platform_Data::sanitize_money_value( $benefit['value'] ?? '' );
				if ( '' === $value ) {
					continue;
				}
			} else {
				$value = sanitize_text_field( $benefit['value'] ?? '' );
			}

			$rows[] = array(
				'type'  => $type,
				'value' => $value,
				'note'  => sanitize_text_field( $benefit['note'] ?? '' ),
			);
		}

		return array_values( $rows );
	}

	private static function sanitize_date( $value ) {
		$value = trim( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}

	private static function positive_int_or_empty( $value ) {
		return '' === trim( (string) $value ) ? '' : (string) max( 0, absint( $value ) );
	}

	private static function percentage_value( $value ) {
		$value = trim( str_replace( ',', '.', (string) $value ) );
		if ( '' === $value || ! is_numeric( $value ) ) {
			return '';
		}
		$value = max( 0, min( 100, (float) $value ) );
		return rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' );
	}
}

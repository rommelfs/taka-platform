<?php
/**
 * Native ticketing pricing service.
 *
 * Checkout must call this service to apply promotions. Keeping the pricing
 * pipeline here avoids hardcoded voucher behavior in templates, JavaScript or
 * order creation code.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Ticketing_Pricing_Service {
	public static function quote( $event_id, $ticket_type, $buyer_email = '', $promotion_code = '', $lang = null, $product_items = array() ) {
		$ticket_type = is_array( $ticket_type ) ? $ticket_type : array();
		$line_items = array();
		if ( ! empty( $ticket_type ) ) {
			$line_items[] = self::ticket_line_item( $event_id, $ticket_type );
		}
		foreach ( (array) $product_items as $item ) {
			if ( is_array( $item ) && 'product' === (string) ( $item['item_type'] ?? '' ) ) {
				$line_items[] = $item;
			}
		}
		return self::quote_line_items( $line_items, $buyer_email, $promotion_code, $lang, array( 'event_id' => $event_id, 'ticket_type' => $ticket_type ) );
	}

	public static function quote_line_items( $line_items, $buyer_email = '', $promotion_code = '', $lang = null, $context = array() ) {
		$line_items = self::normalize_line_items( $line_items );
		$context_ticket_type = is_array( $context['ticket_type'] ?? null ) ? $context['ticket_type'] : array();
		$currency = TAKA_Platform_Data::normalize_event_option_value( 'currency', $context_ticket_type['currency'] ?? 'EUR' );
		$currency = self::currency_from_line_items( $line_items, $currency );
		$base_amount = self::normalize_money( self::line_items_total( $line_items ) );
		$result = array(
			'original_amount'   => $base_amount,
			'discount_amount'   => '0',
			'final_amount'      => $base_amount,
			'currency'          => '' !== $currency ? $currency : 'EUR',
			'line_items'        => $line_items,
			'payment_required'  => self::money_to_float( $base_amount ) > 0,
			'promotion_code'    => '',
			'promotion_id'      => 0,
			'promotion_title'   => '',
			'promotion_snapshot'=> null,
			'benefits'          => array(),
			'messages'          => array(),
		);

		$promotion_code = TAKA_Ticketing_Promotion::normalize_code( $promotion_code );
		if ( '' === $promotion_code ) {
			return $result;
		}

		$repository = TAKA_Ticketing_Module::promotion_repository();
		$promotion = $repository->find_by_code( $promotion_code );
		if ( ! $promotion ) {
			return new WP_Error( 'taka_ticketing_promotion_invalid', TAKA_Ticketing_Module::text( 'ticketing.error_promotion_invalid', 'This promotion code is not valid.', $lang ) );
		}

		$error = self::validate_promotion( $promotion, absint( $context['event_id'] ?? 0 ), is_array( $context['ticket_type'] ?? null ) ? $context['ticket_type'] : array(), $buyer_email, $lang, $line_items );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$eligible_base = self::eligible_promotion_base( $promotion, $line_items );
		$discount = 0.0;
		$benefits = array();
		foreach ( $promotion['benefits'] as $benefit ) {
			$type = (string) ( $benefit['type'] ?? '' );
			$value = (string) ( $benefit['value'] ?? '' );
			if ( 'free_ticket' === $type ) {
				$discount = $eligible_base;
			} elseif ( 'percentage_discount' === $type ) {
				$discount += $eligible_base * min( 100, max( 0, (float) $value ) ) / 100;
			} elseif ( 'fixed_discount' === $type ) {
				$discount += self::money_to_float( $value );
			}
			$benefits[] = array(
				'type'  => $type,
				'label' => TAKA_Ticketing_Promotion::frontend_benefit_label( $type, $lang ),
				'value' => $value,
				'note'  => sanitize_text_field( $benefit['note'] ?? '' ),
			);
		}

		$base = self::money_to_float( $base_amount );
		$discount = min( $base, max( 0, $discount ) );
		$final = max( 0, $base - $discount );

		$result['discount_amount'] = self::normalize_money( $discount );
		$result['final_amount'] = self::normalize_money( $final );
		$result['payment_required'] = $final > 0;
		$result['promotion_code'] = $promotion['code'];
		$result['promotion_id'] = absint( $promotion['id'] ?? 0 );
		$result['promotion_title'] = $promotion['title'];
		$result['benefits'] = $benefits;
		$result['promotion_snapshot'] = TAKA_Ticketing_Promotion::snapshot_for_order( $promotion, $result, $lang );
		$result['messages'][] = TAKA_Ticketing_Module::text( 'ticketing.promotion_applied', 'Promotion applied.', $lang );

		return $result;
	}

	public static function normalize_money( $amount ) {
		$amount = TAKA_Platform_Data::sanitize_money_value( $amount );
		return '' === $amount ? '0' : $amount;
	}

	public static function money_to_float( $amount ) {
		$amount = self::normalize_money( $amount );
		return (float) $amount;
	}

	public static function ticket_line_item( $event_id, $ticket_type ) {
		$ticket_type = is_array( $ticket_type ) ? $ticket_type : array();
		$unit = self::normalize_money( $ticket_type['price'] ?? '0' );
		return array(
			'item_type'        => 'ticket',
			'ticket_type_id'   => sanitize_key( $ticket_type['id'] ?? '' ),
			'title'            => sanitize_text_field( $ticket_type['name'] ?? '' ),
			'quantity'         => 1,
			'unit_price'       => $unit,
			'total_price'      => $unit,
			'currency'         => TAKA_Platform_Data::normalize_event_option_value( 'currency', $ticket_type['currency'] ?? 'EUR' ) ?: 'EUR',
			'related_event_id' => absint( $event_id ),
		);
	}

	public static function normalize_line_items( $line_items ) {
		$items = array();
		foreach ( (array) $line_items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$type = sanitize_key( $item['item_type'] ?? '' );
			if ( ! in_array( $type, array( 'ticket', 'product', 'discount' ), true ) ) {
				continue;
			}
			$quantity = max( 1, absint( $item['quantity'] ?? 1 ) );
			$unit = self::normalize_money( $item['unit_price'] ?? '0' );
			$total = self::normalize_money( isset( $item['total_price'] ) ? $item['total_price'] : self::money_to_float( $unit ) * $quantity );
			$items[] = array(
				'item_type'        => $type,
				'ticket_type_id'   => sanitize_key( $item['ticket_type_id'] ?? '' ),
				'product_id'       => TAKA_Ticketing_Product::normalize_product_id( $item['product_id'] ?? '' ),
				'product_post_id'  => absint( $item['product_post_id'] ?? 0 ),
				'title'            => sanitize_text_field( $item['title'] ?? '' ),
				'quantity'         => $quantity,
				'unit_price'       => $unit,
				'total_price'      => $total,
				'currency'         => TAKA_Platform_Data::normalize_event_option_value( 'currency', $item['currency'] ?? 'EUR' ) ?: 'EUR',
				'related_event_id' => absint( $item['related_event_id'] ?? 0 ),
			);
		}
		return $items;
	}

	private static function line_items_total( $line_items ) {
		$total = 0.0;
		foreach ( (array) $line_items as $item ) {
			$total += self::money_to_float( $item['total_price'] ?? '0' );
		}
		return $total;
	}

	private static function currency_from_line_items( $line_items, $fallback ) {
		foreach ( (array) $line_items as $item ) {
			if ( '' !== trim( (string) ( $item['currency'] ?? '' ) ) ) {
				return TAKA_Platform_Data::normalize_event_option_value( 'currency', $item['currency'] );
			}
		}
		return $fallback ?: 'EUR';
	}

	private static function eligible_promotion_base( $promotion, $line_items ) {
		$applies_to = sanitize_key( $promotion['applies_to'] ?? 'entire_order' );
		$product_id = TAKA_Ticketing_Product::normalize_product_id( $promotion['scope_product_id'] ?? '' );
		$total = 0.0;
		foreach ( (array) $line_items as $item ) {
			$type = (string) ( $item['item_type'] ?? '' );
			if ( 'ticket_only' === $applies_to && 'ticket' !== $type ) {
				continue;
			}
			if ( 'add_ons_only' === $applies_to && 'product' !== $type ) {
				continue;
			}
			if ( 'product' === $applies_to && ( 'product' !== $type || (string) ( $item['product_id'] ?? '' ) !== $product_id ) ) {
				continue;
			}
			$total += self::money_to_float( $item['total_price'] ?? '0' );
		}
		return $total;
	}

	private static function validate_promotion( $promotion, $event_id, $ticket_type, $buyer_email, $lang, $line_items = array() ) {
		if ( 'active' !== (string) ( $promotion['status'] ?? '' ) ) {
			return new WP_Error( 'taka_ticketing_promotion_inactive', TAKA_Ticketing_Module::text( 'ticketing.error_promotion_invalid', 'This promotion code is not valid.', $lang ) );
		}

		$today = current_time( 'Y-m-d' );
		if ( '' !== (string) ( $promotion['valid_from'] ?? '' ) && $today < (string) $promotion['valid_from'] ) {
			return new WP_Error( 'taka_ticketing_promotion_not_started', TAKA_Ticketing_Module::text( 'ticketing.error_promotion_not_started', 'This promotion is not active yet.', $lang ) );
		}
		if ( '' !== (string) ( $promotion['valid_until'] ?? '' ) && $today > (string) $promotion['valid_until'] ) {
			return new WP_Error( 'taka_ticketing_promotion_expired', TAKA_Ticketing_Module::text( 'ticketing.error_promotion_expired', 'This promotion has expired.', $lang ) );
		}

		$scope_type = (string) ( $promotion['scope_type'] ?? 'all' );
		if ( 'event' === $scope_type && absint( $promotion['scope_event_id'] ?? 0 ) !== absint( $event_id ) ) {
			return new WP_Error( 'taka_ticketing_promotion_scope', TAKA_Ticketing_Module::text( 'ticketing.error_promotion_scope', 'This promotion cannot be used for the selected ticket.', $lang ) );
		}
		if ( 'ticket_type' === $scope_type && (string) ( $promotion['scope_ticket_type_id'] ?? '' ) !== (string) ( $ticket_type['id'] ?? '' ) ) {
			return new WP_Error( 'taka_ticketing_promotion_scope', TAKA_Ticketing_Module::text( 'ticketing.error_promotion_scope', 'This promotion cannot be used for the selected ticket.', $lang ) );
		}
		if ( 'product' === sanitize_key( $promotion['applies_to'] ?? '' ) ) {
			$product_id = TAKA_Ticketing_Product::normalize_product_id( $promotion['scope_product_id'] ?? '' );
			$has_product = false;
			foreach ( (array) $line_items as $item ) {
				if ( 'product' === (string) ( $item['item_type'] ?? '' ) && (string) ( $item['product_id'] ?? '' ) === $product_id ) {
					$has_product = true;
					break;
				}
			}
			if ( '' === $product_id || ! $has_product ) {
				return new WP_Error( 'taka_ticketing_promotion_scope', TAKA_Ticketing_Module::text( 'ticketing.error_promotion_scope', 'This promotion cannot be used for the selected ticket.', $lang ) );
			}
		}
		if ( 'tour' === $scope_type && '' !== trim( (string) ( $promotion['scope_tour_id'] ?? '' ) ) ) {
			$event_tour = (string) get_post_meta( absint( $event_id ), '_taka_tour_id', true );
			if ( '' !== $event_tour && $event_tour !== (string) $promotion['scope_tour_id'] ) {
				return new WP_Error( 'taka_ticketing_promotion_scope', TAKA_Ticketing_Module::text( 'ticketing.error_promotion_scope', 'This promotion cannot be used for the selected ticket.', $lang ) );
			}
		}

		$repository = TAKA_Ticketing_Module::promotion_repository();
		$max_total = '' === trim( (string) ( $promotion['max_total_uses'] ?? '' ) ) ? 0 : absint( $promotion['max_total_uses'] );
		if ( $max_total > 0 && $repository->count_uses( $promotion['id'] ?? 0 ) >= $max_total ) {
			return new WP_Error( 'taka_ticketing_promotion_used_up', TAKA_Ticketing_Module::text( 'ticketing.error_promotion_used_up', 'This promotion has already been used up.', $lang ) );
		}

		$max_person = '' === trim( (string) ( $promotion['max_uses_per_person'] ?? '' ) ) ? 0 : absint( $promotion['max_uses_per_person'] );
		$buyer_email = sanitize_email( $buyer_email );
		if ( $max_person > 0 && '' !== $buyer_email && $repository->count_uses_for_email( $promotion['id'] ?? 0, $buyer_email ) >= $max_person ) {
			return new WP_Error( 'taka_ticketing_promotion_person_limit', TAKA_Ticketing_Module::text( 'ticketing.error_promotion_person_limit', 'This promotion was already used for this email address.', $lang ) );
		}

		return true;
	}
}

<?php
/**
 * Native ticketing order business logic.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Ticketing_Order_Service {
	public static function create_order_from_post( $posted ) {
		$posted = is_array( $posted ) ? $posted : array();
		$event_id = absint( $posted['event_id'] ?? 0 );
		$ticket_type_id = sanitize_key( $posted['ticket_type_id'] ?? '' );
		$payment_method = sanitize_key( $posted['payment_method'] ?? '' );
		$lang = self::language_from_post( $posted );

		if ( ! $event_id || ! get_post( $event_id ) ) {
			return new WP_Error( 'taka_ticketing_event_missing', TAKA_Ticketing_Module::text( 'ticketing.error_event_missing', 'Event not found.', $lang ) );
		}
		if ( ! TAKA_Ticketing_Module::event_uses_native_ticketing( $event_id ) ) {
			return new WP_Error( 'taka_ticketing_not_native', TAKA_Ticketing_Module::text( 'ticketing.error_not_native', 'This event does not use native ticketing.', $lang ) );
		}

		$ticket_type = TAKA_Ticketing_Module::find_ticket_type( $event_id, $ticket_type_id );
		if ( ! $ticket_type ) {
			return new WP_Error( 'taka_ticketing_ticket_missing', TAKA_Ticketing_Module::text( 'ticketing.error_ticket_missing', 'Ticket type not found.', $lang ) );
		}
		$availability = TAKA_Ticketing_Module::ticket_availability( $event_id, $ticket_type );
		if ( empty( $availability['available'] ) ) {
			return new WP_Error( 'taka_ticketing_ticket_unavailable', TAKA_Ticketing_Module::text( 'ticketing.error_ticket_unavailable', 'This ticket type is no longer available.', $lang ) );
		}

		$buyer = self::buyer_from_post( $posted );
		$participant = self::participant_from_post( $posted, $buyer );
		$error = self::validate_people( $buyer, $participant, ! empty( $posted['participant_is_buyer'] ), $lang );
		if ( is_wp_error( $error ) ) {
			return $error;
		}
		if ( empty( $posted['terms_accepted'] ) || empty( $posted['privacy_accepted'] ) ) {
			return new WP_Error( 'taka_ticketing_terms', TAKA_Ticketing_Module::text( 'ticketing.error_terms', 'Please accept the terms and privacy notice.', $lang ) );
		}

		$pricing = TAKA_Ticketing_Pricing_Service::quote( $event_id, $ticket_type, $buyer['email'] ?? '', $posted['promotion_code'] ?? '', $lang );
		if ( is_wp_error( $pricing ) ) {
			return $pricing;
		}

		$payment_required = ! empty( $pricing['payment_required'] );
		if ( $payment_required ) {
			$enabled_methods = TAKA_Ticketing_Module::enabled_payment_methods_for_event( $event_id );
			if ( ! in_array( $payment_method, $enabled_methods, true ) ) {
				return new WP_Error( 'taka_ticketing_payment_method', TAKA_Ticketing_Module::text( 'ticketing.error_payment_method', 'Please choose an available payment method.', $lang ) );
			}
		} else {
			$payment_method = '' !== (string) ( $pricing['promotion_code'] ?? '' ) ? 'promotion' : 'free';
		}

		$timeline = array(
			array(
				'time'  => current_time( 'mysql' ),
				'label' => __( 'Order submitted', 'taka-platform' ),
			),
		);
		if ( '' !== (string) ( $pricing['promotion_code'] ?? '' ) ) {
			$timeline[] = array(
				'time'  => current_time( 'mysql' ),
				'label' => sprintf( __( 'Promotion applied: %s', 'taka-platform' ), sanitize_text_field( $pricing['promotion_code'] ) ),
			);
		}

		$order = new TAKA_Ticketing_Order(
			array(
				'order_number'        => self::generate_order_number(),
				'public_token'        => wp_generate_password( 32, false, false ),
				'event_id'            => $event_id,
				'event_title'         => get_the_title( $event_id ),
				'ticket_type_id'      => $ticket_type['id'],
				'ticket_type_name'    => $ticket_type['name'],
				'buyer'               => $buyer,
				'participant'         => $participant,
				'original_amount'     => $pricing['original_amount'],
				'discount_amount'     => $pricing['discount_amount'],
				'amount'              => $pricing['final_amount'],
				'final_amount'        => $pricing['final_amount'],
				'currency'            => $pricing['currency'],
				'payment_method'      => $payment_method,
				'payment_status'      => $payment_required ? 'pending' : 'paid',
				'order_status'        => 'confirmed',
				'checkin_status'      => 'not_checked_in',
				'payment_required'    => $payment_required ? '1' : '0',
				'applied_voucher_code' => $pricing['promotion_code'] ?? '',
				'applied_promotion_id' => absint( $pricing['promotion_id'] ?? 0 ),
				'applied_promotion'   => $pricing['promotion_snapshot'] ?? null,
				'applied_benefits'    => is_array( $pricing['benefits'] ?? null ) ? $pricing['benefits'] : array(),
				'language'            => $lang,
				'created_at'          => current_time( 'mysql' ),
				'updated_at'          => current_time( 'mysql' ),
				'timeline'            => $timeline,
			)
		);

		$provider = TAKA_Ticketing_Module::payment_provider( $payment_method );
		if ( $payment_required && $provider ) {
			$data = $order->to_array();
			$data['payment'] = $provider->create_payment( $order );
			$order = new TAKA_Ticketing_Order( $data );
		}

		$saved = TAKA_Ticketing_Module::order_repository()->save( $order );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		TAKA_Ticketing_Email_Service::send_order_confirmation( $saved );
		TAKA_Ticketing_Email_Service::send_admin_notification( $saved );
		return $saved;
	}

	public static function mark_paid( $order_id ) {
		$repository = TAKA_Ticketing_Module::order_repository();
		$order = $repository->find_by_id( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'taka_ticketing_order_missing', __( 'Order not found.', 'taka-platform' ) );
		}
		$data = $order->to_array();
		$data['payment_status'] = 'paid';
		$data['updated_at'] = current_time( 'mysql' );
		$data['timeline'][] = array( 'time' => current_time( 'mysql' ), 'label' => __( 'Payment marked paid', 'taka-platform' ) );
		$saved = $repository->save( new TAKA_Ticketing_Order( $data ) );
		if ( ! is_wp_error( $saved ) ) {
			TAKA_Ticketing_Email_Service::send_payment_confirmation( $saved );
		}
		return $saved;
	}

	public static function cancel( $order_id ) {
		$repository = TAKA_Ticketing_Module::order_repository();
		$order = $repository->find_by_id( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'taka_ticketing_order_missing', __( 'Order not found.', 'taka-platform' ) );
		}
		$data = $order->to_array();
		$data['order_status'] = 'cancelled';
		$data['payment_status'] = 'cancelled';
		$data['updated_at'] = current_time( 'mysql' );
		$data['timeline'][] = array( 'time' => current_time( 'mysql' ), 'label' => __( 'Order cancelled', 'taka-platform' ) );
		return $repository->save( new TAKA_Ticketing_Order( $data ) );
	}

	private static function buyer_from_post( $posted ) {
		return array(
			'first_name' => sanitize_text_field( $posted['buyer_first_name'] ?? '' ),
			'last_name'  => sanitize_text_field( $posted['buyer_last_name'] ?? '' ),
			'email'      => sanitize_email( $posted['buyer_email'] ?? '' ),
			'country'    => self::country_from_post( $posted['buyer_country'] ?? '' ),
			'phone'      => sanitize_text_field( $posted['buyer_phone'] ?? '' ),
		);
	}

	private static function participant_from_post( $posted, $buyer ) {
		$extra = self::participant_extra_from_post( $posted );
		if ( ! empty( $posted['participant_is_buyer'] ) ) {
			return array_merge(
				array(
					'first_name' => $buyer['first_name'],
					'last_name'  => $buyer['last_name'],
					'email'      => $buyer['email'],
					'country'    => $buyer['country'],
				),
				$extra
			);
		}

		return array_merge(
			array(
				'first_name' => sanitize_text_field( $posted['participant_first_name'] ?? '' ),
				'last_name'  => sanitize_text_field( $posted['participant_last_name'] ?? '' ),
				'email'      => sanitize_email( $posted['participant_email'] ?? '' ),
				'country'    => self::country_from_post( $posted['participant_country'] ?? '' ),
			),
			$extra
		);
	}

	private static function participant_extra_from_post( $posted ) {
		$dietary_preference = sanitize_key( $posted['participant_dietary_preference'] ?? 'none' );
		if ( ! in_array( $dietary_preference, array( 'none', 'vegetarian', 'vegan', 'other' ), true ) ) {
			$dietary_preference = 'none';
		}
		return array(
			'dojo'               => sanitize_text_field( $posted['participant_dojo'] ?? '' ),
			'association'        => sanitize_text_field( $posted['participant_association'] ?? '' ),
			'style'              => sanitize_text_field( $posted['participant_style'] ?? '' ),
			'rank'               => sanitize_text_field( $posted['participant_rank'] ?? '' ),
			'dietary_preference' => $dietary_preference,
			'dietary_notes'      => 'other' === $dietary_preference ? sanitize_textarea_field( $posted['participant_dietary_notes'] ?? '' ) : '',
			'allergies'          => sanitize_textarea_field( $posted['participant_allergies'] ?? '' ),
			'notes'              => sanitize_textarea_field( $posted['participant_notes'] ?? '' ),
		);
	}

	private static function validate_people( $buyer, $participant, $participant_is_buyer, $lang ) {
		foreach ( array( 'first_name', 'last_name', 'email', 'country' ) as $field ) {
			if ( '' === trim( (string) ( $buyer[ $field ] ?? '' ) ) ) {
				return new WP_Error( 'taka_ticketing_buyer_missing', TAKA_Ticketing_Module::text( 'ticketing.error_buyer_missing', 'Please complete all required buyer fields.', $lang ) );
			}
		}
		if ( ! is_email( $buyer['email'] ) ) {
			return new WP_Error( 'taka_ticketing_buyer_email', TAKA_Ticketing_Module::text( 'ticketing.error_buyer_email', 'Please enter a valid buyer email address.', $lang ) );
		}
		if ( ! $participant_is_buyer ) {
			foreach ( array( 'first_name', 'last_name', 'country' ) as $field ) {
				if ( '' === trim( (string) ( $participant[ $field ] ?? '' ) ) ) {
					return new WP_Error( 'taka_ticketing_participant_missing', TAKA_Ticketing_Module::text( 'ticketing.error_participant_missing', 'Please complete all required participant fields.', $lang ) );
				}
			}
			if ( '' !== trim( (string) ( $participant['email'] ?? '' ) ) && ! is_email( $participant['email'] ) ) {
				return new WP_Error( 'taka_ticketing_participant_email', TAKA_Ticketing_Module::text( 'ticketing.error_participant_email', 'Please enter a valid participant email address.', $lang ) );
			}
		}
		return true;
	}

	private static function country_from_post( $value ) {
		return TAKA_Platform_Data::normalize_event_option_value( 'country', sanitize_text_field( $value ) );
	}

	private static function language_from_post( $posted ) {
		$lang = sanitize_key( $posted['language'] ?? '' );
		return in_array( $lang, TAKA_Platform_Data::content_section_languages(), true ) ? $lang : taka_tour_current_language();
	}

	private static function generate_order_number() {
		return 'TAKA-' . gmdate( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 6, false, false ) );
	}
}

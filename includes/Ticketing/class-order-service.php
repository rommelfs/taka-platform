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

		if ( ! $event_id || ! get_post( $event_id ) ) {
			return new WP_Error( 'taka_ticketing_event_missing', __( 'Event not found.', 'taka-platform' ) );
		}
		if ( ! TAKA_Ticketing_Module::event_uses_native_ticketing( $event_id ) ) {
			return new WP_Error( 'taka_ticketing_not_native', __( 'This event does not use native ticketing.', 'taka-platform' ) );
		}

		$ticket_type = TAKA_Ticketing_Module::find_ticket_type( $event_id, $ticket_type_id );
		if ( ! $ticket_type ) {
			return new WP_Error( 'taka_ticketing_ticket_missing', __( 'Ticket type not found.', 'taka-platform' ) );
		}
		$availability = TAKA_Ticketing_Module::ticket_availability( $event_id, $ticket_type );
		if ( empty( $availability['available'] ) ) {
			return new WP_Error( 'taka_ticketing_ticket_unavailable', __( 'This ticket type is no longer available.', 'taka-platform' ) );
		}

		$enabled_methods = TAKA_Ticketing_Module::enabled_payment_methods_for_event( $event_id );
		if ( ! in_array( $payment_method, $enabled_methods, true ) ) {
			return new WP_Error( 'taka_ticketing_payment_method', __( 'Please choose an available payment method.', 'taka-platform' ) );
		}

		$buyer = self::buyer_from_post( $posted );
		$participant = self::participant_from_post( $posted, $buyer );
		$error = self::validate_people( $buyer, $participant, ! empty( $posted['participant_is_buyer'] ) );
		if ( is_wp_error( $error ) ) {
			return $error;
		}
		if ( empty( $posted['terms_accepted'] ) || empty( $posted['privacy_accepted'] ) ) {
			return new WP_Error( 'taka_ticketing_terms', __( 'Please accept the terms and privacy notice.', 'taka-platform' ) );
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
				'amount'              => $ticket_type['price'],
				'currency'            => $ticket_type['currency'],
				'payment_method'      => $payment_method,
				'payment_status'      => 'pending',
				'order_status'        => 'confirmed',
				'checkin_status'      => 'not_checked_in',
				'created_at'          => current_time( 'mysql' ),
				'updated_at'          => current_time( 'mysql' ),
				'timeline'            => array(
					array(
						'time'  => current_time( 'mysql' ),
						'label' => __( 'Order submitted', 'taka-platform' ),
					),
				),
			)
		);

		$provider = TAKA_Ticketing_Module::payment_provider( $payment_method );
		if ( $provider ) {
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
			'country'    => sanitize_text_field( $posted['buyer_country'] ?? '' ),
			'phone'      => sanitize_text_field( $posted['buyer_phone'] ?? '' ),
			'company'    => sanitize_text_field( $posted['buyer_company'] ?? '' ),
		);
	}

	private static function participant_from_post( $posted, $buyer ) {
		if ( ! empty( $posted['participant_is_buyer'] ) ) {
			return array(
				'first_name' => $buyer['first_name'],
				'last_name'  => $buyer['last_name'],
				'email'      => $buyer['email'],
				'country'    => $buyer['country'],
			);
		}

		return array(
			'first_name'    => sanitize_text_field( $posted['participant_first_name'] ?? '' ),
			'last_name'     => sanitize_text_field( $posted['participant_last_name'] ?? '' ),
			'email'         => sanitize_email( $posted['participant_email'] ?? '' ),
			'country'       => sanitize_text_field( $posted['participant_country'] ?? '' ),
			'dojo'          => sanitize_text_field( $posted['participant_dojo'] ?? '' ),
			'association'   => sanitize_text_field( $posted['participant_association'] ?? '' ),
			'style'         => sanitize_text_field( $posted['participant_style'] ?? '' ),
			'rank'          => sanitize_text_field( $posted['participant_rank'] ?? '' ),
			'dietary_notes' => sanitize_textarea_field( $posted['participant_dietary_notes'] ?? '' ),
			'allergies'     => sanitize_textarea_field( $posted['participant_allergies'] ?? '' ),
			'notes'         => sanitize_textarea_field( $posted['participant_notes'] ?? '' ),
		);
	}

	private static function validate_people( $buyer, $participant, $participant_is_buyer ) {
		foreach ( array( 'first_name', 'last_name', 'email', 'country' ) as $field ) {
			if ( '' === trim( (string) ( $buyer[ $field ] ?? '' ) ) ) {
				return new WP_Error( 'taka_ticketing_buyer_missing', __( 'Please complete all required buyer fields.', 'taka-platform' ) );
			}
		}
		if ( ! is_email( $buyer['email'] ) ) {
			return new WP_Error( 'taka_ticketing_buyer_email', __( 'Please enter a valid buyer email address.', 'taka-platform' ) );
		}
		if ( ! $participant_is_buyer ) {
			foreach ( array( 'first_name', 'last_name', 'country' ) as $field ) {
				if ( '' === trim( (string) ( $participant[ $field ] ?? '' ) ) ) {
					return new WP_Error( 'taka_ticketing_participant_missing', __( 'Please complete all required participant fields.', 'taka-platform' ) );
				}
			}
			if ( '' !== trim( (string) ( $participant['email'] ?? '' ) ) && ! is_email( $participant['email'] ) ) {
				return new WP_Error( 'taka_ticketing_participant_email', __( 'Please enter a valid participant email address.', 'taka-platform' ) );
			}
		}
		return true;
	}

	private static function generate_order_number() {
		return 'TAKA-' . gmdate( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 6, false, false ) );
	}
}

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
		$ticket_quantity = max( 1, absint( $posted['ticket_quantity'] ?? 1 ) );
		$standalone_product_id = TAKA_Ticketing_Product::normalize_product_id( $posted['standalone_product_id'] ?? '' );
		$payment_method = sanitize_key( $posted['payment_method'] ?? '' );
		$lang = self::language_from_post( $posted );
		$ticket_type = array();
		$standalone_product = null;
		$product_items = array();

			if ( '' !== $standalone_product_id ) {
				$standalone_product = TAKA_Ticketing_Module::product_repository()->find_by_product_id( $standalone_product_id );
				if ( ! $standalone_product || '1' !== (string) ( $standalone_product['can_purchase_standalone'] ?? '0' ) ) {
					return new WP_Error( 'taka_ticketing_product_missing', TAKA_Ticketing_Module::text( 'ticketing.error_product_missing', 'Product not found.', $lang ) );
				}
				$event_id = absint( $standalone_product['related_event_id'] ?? 0 );
				$standalone_product = TAKA_Ticketing_Product::resolve_for_language( $standalone_product, $lang, $event_id ? (string) get_post_meta( $event_id, '_taka_source_language', true ) : TAKA_Platform_Data::platform_fallback_language() );
				$availability = TAKA_Ticketing_Module::product_repository()->availability( $standalone_product );
			if ( empty( $availability['available'] ) ) {
				return new WP_Error( 'taka_ticketing_product_unavailable', TAKA_Ticketing_Module::text( 'ticketing.error_product_unavailable', 'This product is no longer available.', $lang ) );
			}
			$quantity = max( 1, absint( $posted['standalone_product_quantity'] ?? 1 ) );
			$max = max( 1, absint( $standalone_product['max_quantity_per_order'] ?? 1 ) );
			if ( null !== ( $availability['remaining'] ?? null ) ) {
				$max = min( $max, max( 0, absint( $availability['remaining'] ) ) );
			}
			if ( $quantity > $max ) {
				return new WP_Error( 'taka_ticketing_product_capacity', TAKA_Ticketing_Module::text( 'ticketing.error_product_capacity', 'The selected add-on quantity is no longer available.', $lang ) );
			}
			$product_items[] = TAKA_Ticketing_Product::line_item_from_product( $standalone_product, $quantity, $event_id );
		} else {
			if ( ! $event_id || ! get_post( $event_id ) ) {
				return new WP_Error( 'taka_ticketing_event_missing', TAKA_Ticketing_Module::text( 'ticketing.error_event_missing', 'Event not found.', $lang ) );
			}
			if ( ! TAKA_Ticketing_Module::event_uses_native_ticketing( $event_id ) ) {
				return new WP_Error( 'taka_ticketing_not_native', TAKA_Ticketing_Module::text( 'ticketing.error_not_native', 'This event does not use native ticketing.', $lang ) );
			}

			$ticket_type = TAKA_Ticketing_Module::find_ticket_type( $event_id, $ticket_type_id, $lang );
			if ( ! $ticket_type ) {
				return new WP_Error( 'taka_ticketing_ticket_missing', TAKA_Ticketing_Module::text( 'ticketing.error_ticket_missing', 'Ticket type not found.', $lang ) );
			}
			$availability = TAKA_Ticketing_Module::ticket_availability( $event_id, $ticket_type );
			if ( empty( $availability['available'] ) ) {
				return new WP_Error( 'taka_ticketing_ticket_unavailable', TAKA_Ticketing_Module::text( 'ticketing.error_ticket_unavailable', 'This ticket type is no longer available.', $lang ) );
			}
			if ( null !== ( $availability['remaining'] ?? null ) && $ticket_quantity > max( 0, absint( $availability['remaining'] ) ) ) {
				return new WP_Error( 'taka_ticketing_ticket_capacity', TAKA_Ticketing_Module::text( 'ticketing.error_ticket_capacity', 'The selected ticket quantity is no longer available.', $lang ) );
			}
			$product_items = self::product_line_items_from_post( $posted, $event_id, $lang );
			if ( is_wp_error( $product_items ) ) {
				return $product_items;
			}
		}

		$buyer = self::buyer_from_post( $posted );
		$collect_dietary = $event_id ? TAKA_Ticketing_Module::event_collects_dietary_preferences( $event_id ) : false;
		$participant_posted = self::participant_post_data( $posted, $standalone_product_id, $ticket_quantity );
		$participants = self::participants_from_post( $participant_posted, $buyer, '' === $standalone_product_id ? $ticket_quantity : 1, $collect_dietary );
		$participant = $participants[0] ?? self::participant_from_post( $participant_posted, $buyer, $collect_dietary );
		$error = self::validate_people( $buyer, $participant, ! empty( $participant_posted['participant_is_buyer'] ), $lang, $participants );
		if ( is_wp_error( $error ) ) {
			return $error;
		}
		$terms_accepted = ! empty( $posted['accept_terms'] ) || ! empty( $posted['terms_accepted'] );
		$privacy_accepted = ! empty( $posted['accept_privacy'] ) || ! empty( $posted['privacy_accepted'] );
		if ( ! $terms_accepted || ! $privacy_accepted ) {
			return new WP_Error( 'taka_ticketing_terms', TAKA_Ticketing_Module::text( 'ticketing.error_terms', 'Please accept the terms and privacy notice.', $lang ) );
		}

		$pricing = TAKA_Ticketing_Pricing_Service::quote( $event_id, $ticket_type, $buyer['email'] ?? '', $posted['promotion_code'] ?? '', $lang, $product_items, $ticket_quantity );
		if ( is_wp_error( $pricing ) ) {
			return $pricing;
		}

		$payment_required = ! empty( $pricing['payment_required'] );
		if ( $payment_required ) {
			$enabled_methods = $event_id ? TAKA_Ticketing_Module::enabled_payment_methods_for_event( $event_id ) : array_keys( TAKA_Ticketing_Module::payment_providers() );
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
		$line_items = is_array( $pricing['line_items'] ?? null ) ? $pricing['line_items'] : array();
		$line_items = self::line_items_with_ticket_recipients( $line_items, $buyer, $participant, self::participant_emails( $participants ) );
		if ( TAKA_Ticketing_Pricing_Service::money_to_float( $pricing['discount_amount'] ?? '0' ) > 0 ) {
			$line_items[] = array(
				'item_type'        => 'discount',
				'title'            => sprintf( __( 'Promotion discount %s', 'taka-platform' ), sanitize_text_field( $pricing['promotion_code'] ?? '' ) ),
				'quantity'         => 1,
				'unit_price'       => $pricing['discount_amount'],
				'total_price'      => $pricing['discount_amount'],
				'currency'         => $pricing['currency'],
				'related_event_id' => $event_id,
			);
		}
		$billing_context = $event_id ? TAKA_Ticketing_Module::billing_context_for_event( $event_id ) : TAKA_Ticketing_Module::organizer_billing_snapshot( 0 );

		$order = new TAKA_Ticketing_Order(
			array(
				'order_number'        => self::generate_order_number(),
				'public_token'        => wp_generate_password( 32, false, false ),
				'event_id'            => $event_id,
				'event_title'         => $event_id ? get_the_title( $event_id ) : '',
				'event_details'       => $event_id ? TAKA_Ticketing_Module::event_ticket_details( $event_id, $lang ) : array(),
				'organizer_id'        => absint( $billing_context['organizer_id'] ?? 0 ),
				'organizer_name'      => sanitize_text_field( $billing_context['organizer_name'] ?? '' ),
				'billing_organizer'   => $billing_context,
				'ticket_type_id'      => $ticket_type['id'] ?? '',
				'ticket_type_name'    => $ticket_type['name'] ?? '',
				'line_items'          => $line_items,
				'buyer'               => $buyer,
				'participant'         => $participant,
				'participants'        => $participants,
				'dietary_preferences_enabled' => $collect_dietary ? '1' : '0',
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
				'checkout_return_url' => TAKA_Ticketing_Module::clean_checkout_return_url( $posted['redirect_to'] ?? '' ),
				'created_at'          => current_time( 'mysql' ),
				'updated_at'          => current_time( 'mysql' ),
				'timeline'            => $timeline,
			)
		);

		$provider = TAKA_Ticketing_Module::payment_provider( $payment_method );
		if ( $payment_required && $provider ) {
			$data = $order->to_array();
			$payment = $provider->create_payment( $order );
			if ( is_wp_error( $payment ) ) {
				return $payment;
			}
			$data['payment'] = $payment;
			$order = new TAKA_Ticketing_Order( $data );
		}

		$saved = TAKA_Ticketing_Module::order_repository()->save( $order );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		if ( class_exists( 'TAKA_People_Module' ) ) {
			$people_synced = TAKA_People_Module::sync_order_people_and_registrations( $saved );
			if ( $people_synced instanceof TAKA_Ticketing_Order ) {
				$saved = $people_synced;
			}
		}

		if ( class_exists( 'TAKA_Ticketing_Ticket_Artifact_Service' ) ) {
			$artifact_order = TAKA_Ticketing_Ticket_Artifact_Service::ensure_order_artifacts( $saved, true );
			if ( $artifact_order instanceof TAKA_Ticketing_Order ) {
				$saved = $artifact_order;
			}
		}

		if ( TAKA_Ticketing_Email_Service::send_order_confirmation( $saved ) ) {
			$data = $saved->to_array();
			$data['timeline'][] = array( 'time' => current_time( 'mysql' ), 'label' => __( 'Confirmation email sent', 'taka-platform' ) );
			$timeline_saved = TAKA_Ticketing_Module::order_repository()->save( new TAKA_Ticketing_Order( $data ) );
			if ( ! is_wp_error( $timeline_saved ) ) {
				$saved = $timeline_saved;
			}
			if ( class_exists( 'TAKA_People_Module' ) && $saved instanceof TAKA_Ticketing_Order ) {
				$people_synced = TAKA_People_Module::sync_order_people_and_registrations( $saved );
				if ( $people_synced instanceof TAKA_Ticketing_Order ) {
					$saved = $people_synced;
				}
			}
		}
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
		$data['timeline'][] = array( 'time' => current_time( 'mysql' ), 'label' => __( 'Payment received', 'taka-platform' ) );
		$saved = $repository->save( new TAKA_Ticketing_Order( $data ) );
		if ( class_exists( 'TAKA_People_Module' ) && $saved instanceof TAKA_Ticketing_Order ) {
			$people_synced = TAKA_People_Module::sync_order_people_and_registrations( $saved );
			if ( $people_synced instanceof TAKA_Ticketing_Order ) {
				$saved = $people_synced;
			}
		}
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
		$already_cancelled = in_array( (string) ( $data['order_status'] ?? '' ), array( 'cancelled' ), true ) || in_array( (string) ( $data['payment_status'] ?? '' ), array( 'cancelled', 'refunded' ), true );
		$data['order_status'] = 'cancelled';
		$data['payment_status'] = 'cancelled';
		$data['updated_at'] = current_time( 'mysql' );
		$data['timeline'][] = array( 'time' => current_time( 'mysql' ), 'label' => __( 'Order cancelled', 'taka-platform' ) );
		$saved = $repository->save( new TAKA_Ticketing_Order( $data ) );
		if ( class_exists( 'TAKA_People_Module' ) && $saved instanceof TAKA_Ticketing_Order ) {
			$people_synced = TAKA_People_Module::sync_order_people_and_registrations( $saved );
			if ( $people_synced instanceof TAKA_Ticketing_Order ) {
				$saved = $people_synced;
			}
		}
		if ( ! $already_cancelled && $saved instanceof TAKA_Ticketing_Order && TAKA_Ticketing_Email_Service::send_order_cancellation( $saved ) ) {
			$data = $saved->to_array();
			$data['timeline'][] = array( 'time' => current_time( 'mysql' ), 'label' => __( 'Cancellation email sent', 'taka-platform' ) );
			$email_saved = $repository->save( new TAKA_Ticketing_Order( $data ) );
			if ( ! is_wp_error( $email_saved ) ) {
				$saved = $email_saved;
			}
		}
		return $saved;
	}

	public static function refund( $order_id ) {
		$repository = TAKA_Ticketing_Module::order_repository();
		$order = $repository->find_by_id( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'taka_ticketing_order_missing', __( 'Order not found.', 'taka-platform' ) );
		}
		$provider = TAKA_Ticketing_Module::payment_provider( $order->get( 'payment_method', '' ) );
		if ( ! $provider ) {
			return new WP_Error( 'taka_ticketing_refund_provider', __( 'Payment provider not found for this order.', 'taka-platform' ) );
		}
		$result = $provider->refund( $order );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $result instanceof TAKA_Ticketing_Order ? $result : $repository->find_by_id( $order_id );
	}

	private static function product_line_items_from_post( $posted, $event_id, $lang ) {
		$quantities = isset( $posted['product_quantities'] ) && is_array( $posted['product_quantities'] ) ? $posted['product_quantities'] : array();
		$items = array();
		foreach ( $quantities as $product_id => $quantity ) {
			$product_id = TAKA_Ticketing_Product::normalize_product_id( $product_id );
			$quantity = absint( $quantity );
			if ( '' === $product_id || $quantity <= 0 ) {
				continue;
			}
			$product = TAKA_Ticketing_Module::product_repository()->find_by_product_id( $product_id );
			if ( ! $product || '1' !== (string) ( $product['visible_in_checkout'] ?? '1' ) || '1' !== (string) ( $product['requires_event_ticket'] ?? '0' ) || absint( $product['related_event_id'] ?? 0 ) !== absint( $event_id ) ) {
				return new WP_Error( 'taka_ticketing_product_missing', TAKA_Ticketing_Module::text( 'ticketing.error_product_missing', 'Product not found.', $lang ) );
			}
			$availability = TAKA_Ticketing_Module::product_repository()->availability( $product );
			if ( empty( $availability['available'] ) ) {
				return new WP_Error( 'taka_ticketing_product_unavailable', TAKA_Ticketing_Module::text( 'ticketing.error_product_unavailable', 'This product is no longer available.', $lang ) );
			}
			$max = max( 1, absint( $product['max_quantity_per_order'] ?? 1 ) );
			if ( null !== ( $availability['remaining'] ?? null ) ) {
				$max = min( $max, max( 0, absint( $availability['remaining'] ) ) );
			}
			if ( $quantity > $max ) {
				return new WP_Error( 'taka_ticketing_product_capacity', TAKA_Ticketing_Module::text( 'ticketing.error_product_capacity', 'The selected add-on quantity is no longer available.', $lang ) );
			}
				$product = TAKA_Ticketing_Product::resolve_for_language( $product, $lang, (string) get_post_meta( absint( $event_id ), '_taka_source_language', true ) );
				$items[] = TAKA_Ticketing_Product::line_item_from_product( $product, $quantity, $event_id );
		}
		return $items;
	}

	private static function line_items_with_ticket_recipients( $line_items, $buyer, $participant, $participant_emails ) {
		foreach ( $line_items as $index => $item ) {
			if ( 'ticket' !== (string) ( $item['item_type'] ?? '' ) || ! empty( $item['recipient_emails'] ) ) {
				continue;
			}
			$email = sanitize_email( $participant['email'] ?? '' );
			if ( '' === $email ) {
				$email = sanitize_email( $buyer['email'] ?? '' );
			}
			$recipients = (array) $participant_emails;
			if ( '' !== $email ) {
				$recipients[] = $email;
			}
			$line_items[ $index ]['recipient_emails'] = array_values( array_unique( array_filter( $recipients ) ) );
		}
		return $line_items;
	}

	private static function participant_post_data( $posted, $standalone_product_id, $ticket_quantity ) {
		$posted = is_array( $posted ) ? $posted : array();
		if ( '' !== $standalone_product_id || $ticket_quantity <= 1 ) {
			if ( ! empty( $posted['self_participates'] ) ) {
				$posted['participant_is_buyer'] = '1';
			}
			if ( '' !== $standalone_product_id && ! isset( $posted['participant_is_buyer'] ) ) {
				$posted['participant_is_buyer'] = '1';
			}
		} else {
			unset( $posted['participant_is_buyer'], $posted['self_participates'] );
		}
		return $posted;
	}

	private static function participants_from_post( $posted, $buyer, $ticket_quantity, $collect_dietary = true ) {
		$ticket_quantity = max( 1, absint( $ticket_quantity ) );
		if ( $ticket_quantity <= 1 ) {
			return array( self::participant_from_post( $posted, $buyer, $collect_dietary ) );
		}

		$items = isset( $posted['ticket_participants'] ) && is_array( $posted['ticket_participants'] ) ? $posted['ticket_participants'] : array();
		$participants = array();
		for ( $index = 0; $index < $ticket_quantity; $index++ ) {
			$row = is_array( $items[ $index ] ?? null ) ? $items[ $index ] : array();
			$participants[] = self::participant_from_row( $row, $collect_dietary );
		}
		return $participants;
	}

	private static function participant_from_row( $row, $collect_dietary = true ) {
		$row = is_array( $row ) ? $row : array();
		return array_merge(
			array(
				'first_name' => sanitize_text_field( $row['first_name'] ?? '' ),
				'last_name'  => sanitize_text_field( $row['last_name'] ?? '' ),
				'email'      => sanitize_email( $row['email'] ?? '' ),
				'country'    => self::country_from_post( $row['country'] ?? '' ),
			),
			self::participant_extra_from_post(
				array(
					'participant_dojo'               => $row['dojo'] ?? '',
					'participant_association'        => $row['association'] ?? '',
					'participant_style'              => $row['style'] ?? '',
					'participant_rank'               => $row['rank'] ?? '',
					'participant_dietary_preference' => $row['dietary_preference'] ?? 'none',
					'participant_dietary_notes'      => $row['dietary_notes'] ?? '',
					'participant_allergies'          => $row['allergies'] ?? '',
					'participant_notes'              => $row['notes'] ?? '',
				),
				$collect_dietary
			)
		);
	}

	private static function participant_emails( $participants ) {
		$emails = array();
		foreach ( (array) $participants as $participant ) {
			$email = sanitize_email( is_array( $participant ) ? ( $participant['email'] ?? '' ) : '' );
			if ( '' !== $email && is_email( $email ) ) {
				$emails[] = $email;
			}
		}
		return array_values( array_unique( $emails ) );
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

	private static function participant_from_post( $posted, $buyer, $collect_dietary = true ) {
		$extra = self::participant_extra_from_post( $posted, $collect_dietary );
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

	private static function participant_extra_from_post( $posted, $collect_dietary = true ) {
		if ( ! $collect_dietary ) {
			$posted['participant_dietary_preference'] = 'none';
			$posted['participant_dietary_notes'] = '';
			$posted['participant_allergies'] = '';
		}
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

	private static function validate_people( $buyer, $participant, $participant_is_buyer, $lang, $participants = array() ) {
		foreach ( array( 'first_name', 'last_name', 'email', 'country' ) as $field ) {
			if ( '' === trim( (string) ( $buyer[ $field ] ?? '' ) ) ) {
				return new WP_Error( 'taka_ticketing_buyer_missing', TAKA_Ticketing_Module::text( 'ticketing.error_buyer_missing', 'Please complete all required buyer fields.', $lang ) );
			}
		}
		if ( ! is_email( $buyer['email'] ) ) {
			return new WP_Error( 'taka_ticketing_buyer_email', TAKA_Ticketing_Module::text( 'ticketing.error_buyer_email', 'Please enter a valid buyer email address.', $lang ) );
		}
		if ( count( (array) $participants ) > 1 ) {
			foreach ( (array) $participants as $item ) {
				foreach ( array( 'first_name', 'last_name', 'country' ) as $field ) {
					if ( '' === trim( (string) ( $item[ $field ] ?? '' ) ) ) {
						return new WP_Error( 'taka_ticketing_participants_missing', TAKA_Ticketing_Module::text( 'ticketing.error_participants_missing', 'Please complete the required participant fields for every ticket.', $lang ) );
					}
				}
				if ( '' !== trim( (string) ( $item['email'] ?? '' ) ) && ! is_email( $item['email'] ) ) {
					return new WP_Error( 'taka_ticketing_participant_email', TAKA_Ticketing_Module::text( 'ticketing.error_participant_email', 'Please enter a valid participant email address.', $lang ) );
				}
			}
			return true;
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
		$value = sanitize_text_field( $value );
		$country_code = TAKA_Platform_Data::country_code_for_value( $value );
		return '' !== $country_code ? $country_code : TAKA_Platform_Data::normalize_event_option_value( 'country', $value );
	}

	private static function language_from_post( $posted ) {
		$lang = sanitize_key( $posted['language'] ?? '' );
		return in_array( $lang, TAKA_Platform_Data::content_section_languages(), true ) ? $lang : taka_tour_current_language();
	}

	private static function generate_order_number() {
		return 'TAKA-' . gmdate( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 6, false, false ) );
	}
}

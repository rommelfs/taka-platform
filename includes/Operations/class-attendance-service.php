<?php
/**
 * Event-day attendance operations for private registrations.
 *
 * Attendance is intentionally separate from Orders. Orders stay financial,
 * Registrations connect People to Events, and this service records operational
 * state such as check-in, walk-ins and event-day payment actions.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Event_Operations_Attendance_Service {
	public static function dashboard_metrics( $event_id ) {
		$event_id = absint( $event_id );
		$registrations = self::registrations_for_event( $event_id );
		$metrics = array(
			'registered'           => 0,
			'checked_in'           => 0,
			'remaining'            => 0,
			'payment_pending'      => 0,
			'walk_ins'             => 0,
			'cancelled'            => 0,
			'no_shows'             => 0,
			'capacity'             => self::event_capacity( $event_id ),
			'available'            => null,
			'revenue'              => 0.0,
			'currency'             => self::event_currency( $event_id ),
			'products'             => array(),
			'dietary'              => array(),
			'allergies'            => 0,
			'tags'                 => array(),
			'outstanding_payments' => array(),
		);
		$seen_orders = array();

		foreach ( $registrations as $registration ) {
			$status = (string) ( $registration['registration_status'] ?? 'confirmed' );
			if ( 'cancelled' === $status ) {
				$metrics['cancelled']++;
				continue;
			}

			$metrics['registered']++;
			if ( 'checked_in' === (string) ( $registration['checkin_status'] ?? '' ) || 'checked_in' === (string) ( $registration['attendance_state'] ?? '' ) ) {
				$metrics['checked_in']++;
			}
			if ( 'no_show' === (string) ( $registration['attendance_state'] ?? '' ) ) {
				$metrics['no_shows']++;
			}
			if ( ! empty( $registration['walk_in'] ) && '0' !== (string) $registration['walk_in'] ) {
				$metrics['walk_ins']++;
			}
			if ( 'paid' !== (string) ( $registration['payment_status'] ?? '' ) && 'cancelled' !== (string) ( $registration['payment_status'] ?? '' ) ) {
				$metrics['payment_pending']++;
				$metrics['outstanding_payments'][] = $registration;
			}

			foreach ( self::registration_products( $registration ) as $product ) {
				$title = $product['title'];
				if ( '' === $title ) {
					continue;
				}
				$metrics['products'][ $title ] = ( $metrics['products'][ $title ] ?? 0 ) + max( 1, absint( $product['quantity'] ?? 1 ) );
			}

			$person = self::person_for_registration( $registration );
			if ( $person ) {
				$dietary = sanitize_key( $person['dietary_preference'] ?? 'none' );
				if ( '' !== $dietary && 'none' !== $dietary ) {
					$metrics['dietary'][ $dietary ] = ( $metrics['dietary'][ $dietary ] ?? 0 ) + 1;
				}
				if ( '' !== trim( (string) ( $person['allergies'] ?? '' ) ) ) {
					$metrics['allergies']++;
				}
				foreach ( (array) ( $person['tags'] ?? array() ) as $tag ) {
					$tag = sanitize_text_field( $tag );
					if ( '' !== $tag ) {
						$metrics['tags'][ $tag ] = ( $metrics['tags'][ $tag ] ?? 0 ) + 1;
					}
				}
			}

			$order_id = absint( $registration['order_id'] ?? 0 );
			if ( $order_id && empty( $seen_orders[ $order_id ] ) ) {
				$seen_orders[ $order_id ] = true;
				$order = self::order_for_registration( $registration );
				if ( $order && 'paid' === (string) $order->get( 'payment_status' ) ) {
					$metrics['revenue'] += class_exists( 'TAKA_Ticketing_Pricing_Service' ) ? TAKA_Ticketing_Pricing_Service::money_to_float( $order->get( 'amount', '0' ) ) : (float) $order->get( 'amount', 0 );
				}
			}
		}

		$metrics['remaining'] = max( 0, $metrics['registered'] - $metrics['checked_in'] );
		if ( null !== $metrics['capacity'] ) {
			$metrics['available'] = max( 0, $metrics['capacity'] - $metrics['registered'] );
		}

		arsort( $metrics['products'] );
		arsort( $metrics['dietary'] );
		arsort( $metrics['tags'] );
		return $metrics;
	}

	public static function registrations_for_event( $event_id ) {
		if ( ! class_exists( 'TAKA_People_Module' ) ) {
			return array();
		}
		$items = TAKA_People_Module::registration_repository()->query( array( 'event_id' => absint( $event_id ), 'per_page' => -1 ) );
		usort(
			$items,
			static function ( $a, $b ) {
				return strcmp( self::person_sort_key( $a ), self::person_sort_key( $b ) );
			}
		);
		return $items;
	}

	public static function search_registrations( $event_id, $search ) {
		$search = trim( sanitize_text_field( $search ) );
		if ( '' === $search ) {
			return self::registrations_for_event( $event_id );
		}

		$qr_registration = self::find_registration_from_qr( $search );
		if ( $qr_registration && absint( $qr_registration['event_id'] ?? 0 ) === absint( $event_id ) ) {
			return array( $qr_registration );
		}

		$needle = strtolower( remove_accents( $search ) );
		return array_values(
			array_filter(
				self::registrations_for_event( $event_id ),
				static function ( $registration ) use ( $needle ) {
					return false !== strpos( strtolower( remove_accents( self::registration_search_blob( $registration ) ) ), $needle );
				}
			)
		);
	}

	public static function find_registration_from_qr( $payload ) {
		$payload = trim( sanitize_text_field( $payload ) );
		if ( '' === $payload ) {
			return null;
		}

		$registration_id = 0;
		$token = '';
		if ( preg_match( '/^TAKA-REG:(\d+):([A-Za-z0-9]+)/', $payload, $matches ) ) {
			$registration_id = absint( $matches[1] );
			$token = sanitize_text_field( $matches[2] );
		} elseif ( preg_match( '/^\d+$/', $payload ) ) {
			$registration_id = absint( $payload );
		}

		if ( ! $registration_id || ! class_exists( 'TAKA_People_Module' ) ) {
			return null;
		}

		$registration = TAKA_People_Module::registration_repository()->find_by_id( $registration_id );
		if ( ! $registration ) {
			return null;
		}
		if ( '' !== $token && ! hash_equals( self::validation_token( $registration ), $token ) ) {
			return null;
		}
		return $registration;
	}

	public static function qr_payload( $registration ) {
		$registration = TAKA_People_Registration::normalize( $registration );
		return 'TAKA-REG:' . absint( $registration['id'] ?? 0 ) . ':' . self::validation_token( $registration );
	}

	public static function validation_token( $registration ) {
		$registration = TAKA_People_Registration::normalize( $registration );
		if ( '' !== trim( (string) ( $registration['validation_token'] ?? '' ) ) ) {
			return sanitize_text_field( $registration['validation_token'] );
		}
		return substr( preg_replace( '/[^A-Za-z0-9]/', '', wp_hash( 'taka-registration|' . absint( $registration['id'] ?? 0 ) . '|' . absint( $registration['person_id'] ?? 0 ) . '|' . absint( $registration['order_id'] ?? 0 ) ) ), 0, 24 );
	}

	public static function participant_card_data( $registration ) {
		$registration = TAKA_People_Registration::normalize( $registration );
		$person = self::person_for_registration( $registration );
		$order = self::order_for_registration( $registration );
		$order_data = $order ? $order->to_array() : array();

		return array(
			'registration'  => $registration,
			'person'        => $person ? $person : TAKA_People_Person::normalize( array() ),
			'order'         => $order_data,
			'products'      => self::registration_products( $registration ),
			'benefits'      => is_array( $order_data['applied_benefits'] ?? null ) ? $order_data['applied_benefits'] : array(),
			'warnings'      => self::warnings_for_registration( $registration, $person, $order_data ),
			'qr_payload'    => self::qr_payload( $registration ),
			'payment_label' => class_exists( 'TAKA_Ticketing_Module' ) ? TAKA_Ticketing_Module::payment_method_admin_label( $registration['payment_method'] ?? '' ) : sanitize_text_field( $registration['payment_method'] ?? '' ),
			'ticket_label'  => self::ticket_label( $registration, $order_data ),
		);
	}

	public static function check_in( $registration_id, $actor_id = 0 ) {
		$registration = self::registration_by_id( $registration_id );
		if ( ! $registration ) {
			return new WP_Error( 'taka_operations_registration_missing', __( 'Registration not found.', 'taka-platform' ) );
		}
		if ( 'cancelled' === (string) ( $registration['registration_status'] ?? '' ) ) {
			return new WP_Error( 'taka_operations_registration_cancelled', __( 'Cancelled registrations cannot be checked in.', 'taka-platform' ) );
		}

		$registration['checkin_status'] = 'checked_in';
		$registration['attendance_state'] = 'checked_in';
		$registration['checked_in_at'] = current_time( 'mysql' );
		$registration['checked_in_by'] = absint( $actor_id );
		$registration['validation_token'] = self::validation_token( $registration );
		self::append_registration_timeline( $registration, __( 'Checked in', 'taka-platform' ), $actor_id );
		$saved = self::save_registration( $registration );
		if ( ! is_wp_error( $saved ) ) {
			self::update_linked_order( $saved, array( 'checkin_status' => 'checked_in' ), __( 'Checked in', 'taka-platform' ), $actor_id );
		}
		return $saved;
	}

	public static function undo_check_in( $registration_id, $actor_id = 0 ) {
		$registration = self::registration_by_id( $registration_id );
		if ( ! $registration ) {
			return new WP_Error( 'taka_operations_registration_missing', __( 'Registration not found.', 'taka-platform' ) );
		}
		$registration['checkin_status'] = 'not_checked_in';
		$registration['attendance_state'] = ! empty( $registration['walk_in'] ) && '0' !== (string) $registration['walk_in'] ? 'walk_in' : 'registered';
		$registration['checked_in_at'] = '';
		$registration['checked_in_by'] = 0;
		self::append_registration_timeline( $registration, __( 'Undo check-in', 'taka-platform' ), $actor_id );
		$saved = self::save_registration( $registration );
		if ( ! is_wp_error( $saved ) ) {
			self::update_linked_order( $saved, array( 'checkin_status' => 'not_checked_in' ), __( 'Undo check-in', 'taka-platform' ), $actor_id );
		}
		return $saved;
	}

	public static function mark_no_show( $registration_id, $actor_id = 0 ) {
		$registration = self::registration_by_id( $registration_id );
		if ( ! $registration ) {
			return new WP_Error( 'taka_operations_registration_missing', __( 'Registration not found.', 'taka-platform' ) );
		}
		$registration['checkin_status'] = 'no_show';
		$registration['attendance_state'] = 'no_show';
		self::append_registration_timeline( $registration, __( 'Marked as no-show', 'taka-platform' ), $actor_id );
		$saved = self::save_registration( $registration );
		if ( ! is_wp_error( $saved ) ) {
			self::update_linked_order( $saved, array( 'checkin_status' => 'no_show' ), __( 'Marked as no-show', 'taka-platform' ), $actor_id );
		}
		return $saved;
	}

	public static function receive_payment( $registration_id, $actor_id = 0 ) {
		$registration = self::registration_by_id( $registration_id );
		if ( ! $registration ) {
			return new WP_Error( 'taka_operations_registration_missing', __( 'Registration not found.', 'taka-platform' ) );
		}
		if ( 'paid' === (string) ( $registration['payment_status'] ?? '' ) ) {
			return $registration;
		}
		if ( ! class_exists( 'TAKA_Ticketing_Order_Service' ) ) {
			return new WP_Error( 'taka_operations_ticketing_missing', __( 'Ticketing is not available.', 'taka-platform' ) );
		}

		$order_id = absint( $registration['order_id'] ?? 0 );
		if ( ! $order_id ) {
			return new WP_Error( 'taka_operations_order_missing', __( 'Linked order not found.', 'taka-platform' ) );
		}
		$result = TAKA_Ticketing_Order_Service::mark_paid( $order_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$registration = self::registration_by_id( $registration_id );
		if ( ! $registration ) {
			return new WP_Error( 'taka_operations_registration_missing', __( 'Registration not found after payment update.', 'taka-platform' ) );
		}
		$registration['payment_status'] = 'paid';
		self::append_registration_timeline( $registration, __( 'Payment received at event operations', 'taka-platform' ), $actor_id );
		return self::save_registration( $registration );
	}

	public static function create_walk_in( $event_id, $posted, $actor_id = 0 ) {
		$event_id = absint( $event_id );
		if ( ! $event_id || TAKA_PLATFORM_CPT_EVENT !== get_post_type( $event_id ) ) {
			return new WP_Error( 'taka_operations_event_missing', __( 'Event not found.', 'taka-platform' ) );
		}
		if ( ! class_exists( 'TAKA_People_Module' ) || ! class_exists( 'TAKA_Ticketing_Module' ) ) {
			return new WP_Error( 'taka_operations_dependencies_missing', __( 'People and ticketing modules are required for walk-ins.', 'taka-platform' ) );
		}

		$posted = is_array( $posted ) ? $posted : array();
		$ticket_type_id = sanitize_key( $posted['ticket_type_id'] ?? '' );
		$ticket_type = TAKA_Ticketing_Module::find_ticket_type( $event_id, $ticket_type_id );
		if ( ! $ticket_type ) {
			return new WP_Error( 'taka_operations_ticket_missing', __( 'Please select a ticket type.', 'taka-platform' ) );
		}
		$availability = TAKA_Ticketing_Module::ticket_availability( $event_id, $ticket_type );
		if ( empty( $availability['available'] ) ) {
			return new WP_Error( 'taka_operations_ticket_unavailable', __( 'The selected ticket type is no longer available.', 'taka-platform' ) );
		}

		$person = TAKA_People_Person::normalize(
			array(
				'first_name'         => $posted['first_name'] ?? '',
				'last_name'          => $posted['last_name'] ?? '',
				'email'              => $posted['email'] ?? '',
				'phone'              => $posted['phone'] ?? '',
				'country'            => $posted['country'] ?? '',
				'dojo'               => $posted['dojo'] ?? '',
				'association'        => $posted['association'] ?? '',
				'style'              => $posted['style'] ?? '',
				'rank'               => $posted['rank'] ?? '',
				'dietary_preference' => $posted['dietary_preference'] ?? 'none',
				'allergies'          => $posted['allergies'] ?? '',
				'notes'              => $posted['notes'] ?? '',
				'tags'               => TAKA_People_Person::normalize_tags( $posted['tags'] ?? array() ),
			)
		);
		if ( '' === TAKA_People_Person::full_name( $person ) && '' === $person['email'] ) {
			return new WP_Error( 'taka_operations_person_missing', __( 'Walk-ins need at least a name or email address.', 'taka-platform' ) );
		}

		$saved_person = TAKA_People_Module::person_repository()->create_or_update_from_person_data( $person );
		if ( is_wp_error( $saved_person ) ) {
			return $saved_person;
		}

		$payment_method = sanitize_key( $posted['payment_method'] ?? '' );
		if ( '' === $payment_method ) {
			$payment_method = isset( TAKA_Ticketing_Module::payment_providers()['pay_at_door'] ) ? 'pay_at_door' : 'bank_transfer';
		}
		$pricing = TAKA_Ticketing_Pricing_Service::quote( $event_id, $ticket_type, $person['email'] ?? '', '', TAKA_Platform_Data::platform_fallback_language(), array() );
		if ( is_wp_error( $pricing ) ) {
			return $pricing;
		}

		$payment_received = ! empty( $posted['payment_received'] );
		$amount_float = TAKA_Ticketing_Pricing_Service::money_to_float( $pricing['final_amount'] ?? '0' );
		$payment_status = ( $payment_received || $amount_float <= 0 ) ? 'paid' : 'pending';
		$now = current_time( 'mysql' );
		$order_data = array(
			'order_number'        => self::generate_walk_in_order_number(),
			'public_token'        => wp_generate_password( 32, false, false ),
			'event_id'            => $event_id,
			'event_title'         => get_the_title( $event_id ),
			'ticket_type_id'      => $ticket_type['id'] ?? '',
			'ticket_type_name'    => $ticket_type['name'] ?? '',
			'line_items'          => is_array( $pricing['line_items'] ?? null ) ? $pricing['line_items'] : array(),
			'buyer'               => $person,
			'participant'         => $person,
			'original_amount'     => $pricing['original_amount'] ?? '0',
			'discount_amount'     => $pricing['discount_amount'] ?? '0',
			'amount'              => $pricing['final_amount'] ?? '0',
			'final_amount'        => $pricing['final_amount'] ?? '0',
			'currency'            => $pricing['currency'] ?? self::event_currency( $event_id ),
			'payment_method'      => $amount_float <= 0 ? 'free' : $payment_method,
			'payment_status'      => $payment_status,
			'order_status'        => 'confirmed',
			'checkin_status'      => 'not_checked_in',
			'payment_required'    => $amount_float > 0 ? '1' : '0',
			'buyer_person_id'     => absint( $saved_person['id'] ?? 0 ),
			'participant_person_id' => absint( $saved_person['id'] ?? 0 ),
			'walk_in'             => '1',
			'language'            => TAKA_Platform_Data::platform_fallback_language(),
			'created_at'          => $now,
			'updated_at'          => $now,
			'timeline'            => array(
				array( 'time' => $now, 'label' => __( 'Walk-in registration created', 'taka-platform' ) ),
			),
		);
		if ( 'paid' === $payment_status ) {
			$order_data['timeline'][] = array( 'time' => $now, 'label' => __( 'Payment received', 'taka-platform' ) );
		}

		$saved_order = TAKA_Ticketing_Module::order_repository()->save( new TAKA_Ticketing_Order( $order_data ) );
		if ( is_wp_error( $saved_order ) ) {
			return $saved_order;
		}
		$synced_order = TAKA_People_Module::sync_order_people_and_registrations( $saved_order );
		if ( $synced_order instanceof TAKA_Ticketing_Order ) {
			$saved_order = $synced_order;
		}

		$data = $saved_order->to_array();
		$registration_id = absint( (array_values( (array) ( $data['registration_ids'] ?? array() ) )[0] ) ?? 0 );
		$registration = $registration_id ? self::registration_by_id( $registration_id ) : null;
		if ( ! $registration ) {
			return new WP_Error( 'taka_operations_registration_missing', __( 'Walk-in registration could not be created.', 'taka-platform' ) );
		}
		$registration['walk_in'] = '1';
		$registration['attendance_state'] = 'walk_in';
		$registration['internal_notes'] = sanitize_textarea_field( $posted['internal_notes'] ?? '' );
		$registration['validation_token'] = self::validation_token( $registration );
		self::append_registration_timeline( $registration, __( 'Walk-in created', 'taka-platform' ), $actor_id );
		return self::save_registration( $registration );
	}

	public static function status_label( $status ) {
		$labels = array(
			'registered'     => __( 'Registered', 'taka-platform' ),
			'checked_in'     => __( 'Checked in', 'taka-platform' ),
			'checked_out'    => __( 'Checked out', 'taka-platform' ),
			'cancelled'      => __( 'Cancelled', 'taka-platform' ),
			'no_show'        => __( 'No-show', 'taka-platform' ),
			'walk_in'        => __( 'Walk-in', 'taka-platform' ),
			'not_checked_in' => __( 'Not checked in', 'taka-platform' ),
		);
		return $labels[ $status ] ?? sanitize_text_field( $status );
	}

	public static function dietary_label( $dietary ) {
		$labels = array(
			'none'       => __( 'None', 'taka-platform' ),
			'vegetarian' => __( 'Vegetarian', 'taka-platform' ),
			'vegan'      => __( 'Vegan', 'taka-platform' ),
			'other'      => __( 'Other / note', 'taka-platform' ),
		);
		return $labels[ $dietary ] ?? sanitize_text_field( $dietary );
	}

	private static function registration_by_id( $registration_id ) {
		if ( ! class_exists( 'TAKA_People_Module' ) ) {
			return null;
		}
		return TAKA_People_Module::registration_repository()->find_by_id( absint( $registration_id ) );
	}

	private static function save_registration( $registration ) {
		if ( ! class_exists( 'TAKA_People_Module' ) ) {
			return new WP_Error( 'taka_operations_people_missing', __( 'People module is not available.', 'taka-platform' ) );
		}
		return TAKA_People_Module::registration_repository()->save( $registration );
	}

	private static function update_linked_order( $registration, $updates, $timeline_label, $actor_id ) {
		if ( ! class_exists( 'TAKA_Ticketing_Module' ) ) {
			return null;
		}
		$order = self::order_for_registration( $registration );
		if ( ! $order ) {
			return null;
		}
		$data = $order->to_array();
		foreach ( (array) $updates as $key => $value ) {
			$data[ sanitize_key( $key ) ] = $value;
		}
		$data['updated_at'] = current_time( 'mysql' );
		$data['timeline'] = is_array( $data['timeline'] ?? null ) ? $data['timeline'] : array();
		$data['timeline'][] = array(
			'time'    => current_time( 'mysql' ),
			'label'   => sanitize_text_field( $timeline_label ),
			'user_id' => absint( $actor_id ),
		);
		$saved = TAKA_Ticketing_Module::order_repository()->save( new TAKA_Ticketing_Order( $data ) );
		if ( ! is_wp_error( $saved ) && class_exists( 'TAKA_People_Module' ) ) {
			return TAKA_People_Module::sync_order_people_and_registrations( $saved );
		}
		return $saved;
	}

	private static function append_registration_timeline( &$registration, $label, $actor_id = 0 ) {
		$registration['operations_timeline'] = is_array( $registration['operations_timeline'] ?? null ) ? $registration['operations_timeline'] : array();
		$registration['operations_timeline'][] = array(
			'time'    => current_time( 'mysql' ),
			'label'   => sanitize_text_field( $label ),
			'user_id' => absint( $actor_id ),
		);
	}

	private static function person_for_registration( $registration ) {
		if ( ! class_exists( 'TAKA_People_Module' ) ) {
			return null;
		}
		$person_id = absint( $registration['person_id'] ?? 0 );
		return $person_id ? TAKA_People_Module::person_repository()->find_by_id( $person_id ) : null;
	}

	private static function order_for_registration( $registration ) {
		if ( ! class_exists( 'TAKA_Ticketing_Module' ) ) {
			return null;
		}
		$order_id = absint( $registration['order_id'] ?? 0 );
		return $order_id ? TAKA_Ticketing_Module::order_repository()->find_by_id( $order_id ) : null;
	}

	private static function registration_products( $registration ) {
		$items = is_array( $registration['line_items'] ?? null ) ? $registration['line_items'] : array();
		$products = array();
		foreach ( $items as $item ) {
			if ( 'product' !== (string) ( $item['item_type'] ?? '' ) ) {
				continue;
			}
			$products[] = array(
				'title'    => sanitize_text_field( $item['title'] ?? '' ),
				'quantity' => max( 1, absint( $item['quantity'] ?? 1 ) ),
			);
		}
		return $products;
	}

	private static function warnings_for_registration( $registration, $person, $order_data ) {
		$warnings = array();
		if ( 'paid' !== (string) ( $registration['payment_status'] ?? '' ) && 'cancelled' !== (string) ( $registration['payment_status'] ?? '' ) ) {
			$warnings[] = array( 'type' => 'payment', 'label' => __( 'Payment pending', 'taka-platform' ) );
		}
		if ( 'walk_in' === (string) ( $registration['attendance_state'] ?? '' ) || ( ! empty( $registration['walk_in'] ) && '0' !== (string) $registration['walk_in'] ) ) {
			$warnings[] = array( 'type' => 'walk_in', 'label' => __( 'Walk-in', 'taka-platform' ) );
		}
		if ( ! empty( $order_data['applied_benefits'] ) ) {
			foreach ( (array) $order_data['applied_benefits'] as $benefit ) {
				if ( 'special_access' === (string) ( $benefit['type'] ?? '' ) || 'manual_approval_required' === (string) ( $benefit['type'] ?? '' ) ) {
					$warnings[] = array( 'type' => 'benefit', 'label' => sanitize_text_field( $benefit['label'] ?? __( 'Special access', 'taka-platform' ) ) );
				}
			}
		}
		if ( $person ) {
			foreach ( (array) ( $person['tags'] ?? array() ) as $tag ) {
				$tag = sanitize_text_field( $tag );
				if ( '' !== $tag ) {
					$warnings[] = array( 'type' => 'tag', 'label' => $tag );
				}
			}
			if ( '' !== trim( (string) ( $person['allergies'] ?? '' ) ) ) {
				$warnings[] = array( 'type' => 'allergy', 'label' => __( 'Allergy', 'taka-platform' ) );
			}
			if ( '' !== trim( (string) ( $person['notes'] ?? '' ) ) ) {
				$warnings[] = array( 'type' => 'note', 'label' => __( 'Internal note', 'taka-platform' ) );
			}
		}
		if ( '' !== trim( (string) ( $registration['internal_notes'] ?? '' ) ) ) {
			$warnings[] = array( 'type' => 'note', 'label' => __( 'Operations note', 'taka-platform' ) );
		}
		return $warnings;
	}

	private static function ticket_label( $registration, $order_data ) {
		if ( '' !== trim( (string) ( $registration['ticket_type_name'] ?? '' ) ) ) {
			return sanitize_text_field( $registration['ticket_type_name'] );
		}
		$titles = array();
		foreach ( (array) ( $order_data['line_items'] ?? array() ) as $item ) {
			if ( '' !== trim( (string) ( $item['title'] ?? '' ) ) ) {
				$titles[] = sanitize_text_field( $item['title'] );
			}
		}
		return implode( ', ', array_slice( $titles, 0, 2 ) );
	}

	private static function registration_search_blob( $registration ) {
		$person = self::person_for_registration( $registration );
		$order = self::order_for_registration( $registration );
		$order_data = $order ? $order->to_array() : array();
		$parts = array(
			$registration['order_number'] ?? '',
			$registration['ticket_type_name'] ?? '',
			$registration['event_title'] ?? '',
			self::qr_payload( $registration ),
		);
		if ( $person ) {
			$parts[] = TAKA_People_Person::full_name( $person );
			$parts[] = $person['email'] ?? '';
			$parts[] = $person['dojo'] ?? '';
			$parts[] = $person['country'] ?? '';
			$parts[] = $person['rank'] ?? '';
			$parts[] = implode( ' ', (array) ( $person['tags'] ?? array() ) );
		}
		if ( ! empty( $order_data['order_number'] ) ) {
			$parts[] = $order_data['order_number'];
		}
		return implode( ' ', array_filter( array_map( 'sanitize_text_field', $parts ) ) );
	}

	private static function person_sort_key( $registration ) {
		$person = self::person_for_registration( $registration );
		if ( ! $person ) {
			return strtolower( sanitize_text_field( $registration['order_number'] ?? '' ) );
		}
		return strtolower( remove_accents( TAKA_People_Person::full_name( $person ) . ' ' . ( $person['email'] ?? '' ) ) );
	}

	private static function event_capacity( $event_id ) {
		if ( ! class_exists( 'TAKA_Ticketing_Module' ) ) {
			return null;
		}
		$total = 0;
		$has_capacity = false;
		foreach ( TAKA_Ticketing_Module::ticket_types_for_event( absint( $event_id ) ) as $ticket_type ) {
			if ( '' === trim( (string) ( $ticket_type['capacity'] ?? '' ) ) ) {
				continue;
			}
			$total += absint( $ticket_type['capacity'] );
			$has_capacity = true;
		}
		return $has_capacity ? $total : null;
	}

	private static function event_currency( $event_id ) {
		$currency = get_post_meta( absint( $event_id ), '_taka_currency', true );
		return class_exists( 'TAKA_Platform_Data' ) ? TAKA_Platform_Data::normalize_event_option_value( 'currency', $currency ?: 'EUR' ) : 'EUR';
	}

	private static function generate_walk_in_order_number() {
		return 'TAKA-WALKIN-' . gmdate( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 5, false, false ) );
	}
}

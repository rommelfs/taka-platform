<?php
/**
 * Native ticketing email notifications.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Ticketing_Email_Service {
	public static function send_order_confirmation( TAKA_Ticketing_Order $order ) {
		$data = $order->to_array();
		$buyer = is_array( $data['buyer'] ?? null ) ? $data['buyer'] : array();
		$email = sanitize_email( $buyer['email'] ?? '' );
		$lang = self::order_language( $data );
		if ( '' === $email ) {
			return false;
		}

		$attachments = self::order_confirmation_attachments( $order );
		$sent = wp_mail(
			$email,
			sprintf( self::label( 'ticketing.email_subject_registration', 'Your registration %s', $lang ), $data['order_number'] ?? '' ),
			self::order_message( $order, false, $lang ),
			'',
			$attachments
		);
		if ( $sent && ! empty( $attachments ) ) {
			self::send_recipient_ticket_emails( $order, $email, $lang );
		}
		return $sent;
	}

	public static function send_admin_notification( TAKA_Ticketing_Order $order ) {
		$data = $order->to_array();
		$lang = self::order_language( $data );
		$email = get_option( 'admin_email' );
		if ( '' === sanitize_email( $email ) ) {
			return false;
		}

		return wp_mail(
			$email,
			sprintf( self::label( 'ticketing.email_subject_admin', 'New ticket order %s', $lang ), $order->get( 'order_number', '' ) ),
			self::order_message( $order, true, $lang )
		);
	}

	public static function send_payment_confirmation( TAKA_Ticketing_Order $order ) {
		$data = $order->to_array();
		$buyer = is_array( $data['buyer'] ?? null ) ? $data['buyer'] : array();
		$email = sanitize_email( $buyer['email'] ?? '' );
		$lang = self::order_language( $data );
		if ( '' === $email ) {
			return false;
		}

		$attachments = 'paypal' === (string) ( $data['payment_method'] ?? '' ) && class_exists( 'TAKA_Ticketing_Ticket_Artifact_Service' ) ? TAKA_Ticketing_Ticket_Artifact_Service::buyer_attachments( $order ) : array();
		$sent = wp_mail(
			$email,
			sprintf( self::label( 'ticketing.email_subject_payment', 'Payment received for %s', $lang ), $data['order_number'] ?? '' ),
			sprintf(
				"%s\n\n%s: %s\n%s: %s\n%s: %s",
				self::label( 'ticketing.email_payment_received', 'Thank you. Your payment has been marked as received.', $lang ),
				self::label( 'ticketing.order_number', 'Order number', $lang ),
				$data['order_number'] ?? '',
				self::label( 'ticketing.event', 'Event', $lang ),
				$data['event_title'] ?? '',
				self::label( 'ticketing.amount', 'Amount', $lang ),
				TAKA_Ticketing_Module::format_money( $data['amount'] ?? '', $data['currency'] ?? 'EUR' )
			),
			'',
			$attachments
		);
		if ( $sent && ! empty( $attachments ) ) {
			self::send_recipient_ticket_emails( $order, $email, $lang );
		}
		return $sent;
	}

	public static function send_order_cancellation( TAKA_Ticketing_Order $order ) {
		$data = $order->to_array();
		$buyer = is_array( $data['buyer'] ?? null ) ? $data['buyer'] : array();
		$email = sanitize_email( $buyer['email'] ?? '' );
		$lang = self::order_language( $data );
		if ( '' === $email ) {
			return false;
		}

		return wp_mail(
			$email,
			sprintf( self::label( 'ticketing.email_subject_cancelled', 'Your registration %s was cancelled', $lang ), $data['order_number'] ?? '' ),
			self::cancellation_message( $order, $lang )
		);
	}

	private static function order_message( TAKA_Ticketing_Order $order, $admin, $lang ) {
		$data = $order->to_array();
		$buyer = is_array( $data['buyer'] ?? null ) ? $data['buyer'] : array();
		$participant = is_array( $data['participant'] ?? null ) ? $data['participant'] : array();
		$participants = is_array( $data['participants'] ?? null ) ? array_values( $data['participants'] ) : array();
		$provider = TAKA_Ticketing_Module::payment_provider( $data['payment_method'] ?? '' );
		$instructions = $provider ? $provider->get_public_instructions( $order ) : array();
		$line_items = is_array( $data['line_items'] ?? null ) ? $data['line_items'] : array();
		$include_dietary = ! empty( $data['dietary_preferences_enabled'] );

		$lines = array(
			$admin ? self::label( 'ticketing.email_intro_admin', 'A new ticket order has been received.', $lang ) : self::label( 'ticketing.email_intro_buyer', 'Your registration has been received.', $lang ),
			'',
			self::label( 'ticketing.order_number', 'Order number', $lang ) . ': ' . ( $data['order_number'] ?? '' ),
			self::label( 'ticketing.buyer', 'Buyer', $lang ) . ': ' . self::person_line( $buyer, $lang, true, $include_dietary ),
			self::label( 'ticketing.participant', 'Participant', $lang ) . ': ' . self::person_line( $participant, $lang, false, $include_dietary ),
			self::label( 'ticketing.payment_method', 'Payment method', $lang ) . ': ' . TAKA_Ticketing_Module::payment_method_label( $data['payment_method'] ?? '', $lang ),
			self::label( 'ticketing.amount', 'Amount', $lang ) . ': ' . TAKA_Ticketing_Module::format_money( $data['amount'] ?? '', $data['currency'] ?? 'EUR' ),
			self::label( 'ticketing.payment_status', 'Payment status', $lang ) . ': ' . self::payment_status_label( $data['payment_status'] ?? 'pending', $lang ),
		);
		if ( '' !== trim( (string) ( $data['ticket_type_name'] ?? '' ) ) ) {
			array_splice(
				$lines,
				3,
				0,
				array(
					self::label( 'ticketing.event', 'Event', $lang ) . ': ' . ( $data['event_title'] ?? '' ),
					self::label( 'ticketing.ticket', 'Ticket', $lang ) . ': ' . ( $data['ticket_type_name'] ?? '' ),
				)
			);
		} elseif ( '' !== trim( (string) ( $data['event_title'] ?? '' ) ) ) {
			array_splice( $lines, 3, 0, array( self::label( 'ticketing.event', 'Event', $lang ) . ': ' . ( $data['event_title'] ?? '' ) ) );
		}

		if ( ! empty( $line_items ) ) {
			$lines[] = '';
			$lines[] = self::label( 'ticketing.order_items', 'Order items', $lang ) . ':';
			foreach ( $line_items as $item ) {
				$lines[] = '- ' . TAKA_Ticketing_Module::line_item_label( $item );
			}
		}
		if ( count( $participants ) > 1 ) {
			$lines[] = '';
			$lines[] = self::label( 'ticketing.participants', 'Participants', $lang ) . ':';
			foreach ( $participants as $item ) {
				$lines[] = '- ' . self::person_line( is_array( $item ) ? $item : array(), $lang, true, $include_dietary );
			}
		}
		if ( ! $admin && ! empty( self::order_confirmation_attachments( $order ) ) ) {
			$lines[] = '';
			$lines[] = self::label( 'ticketing.email_attachments_note', 'Your booking confirmation, invoice and ticket QR codes are attached to this email.', $lang );
		}

		if ( '' !== trim( (string) ( $data['applied_voucher_code'] ?? '' ) ) ) {
			array_splice(
				$lines,
				8,
				0,
				array(
					self::label( 'ticketing.voucher_applied', 'Voucher applied', $lang ) . ': ' . $data['applied_voucher_code'],
					self::label( 'ticketing.original_amount', 'Original amount', $lang ) . ': ' . TAKA_Ticketing_Module::format_money( $data['original_amount'] ?? $data['amount'] ?? '0', $data['currency'] ?? 'EUR' ),
					self::label( 'ticketing.discount', 'Discount', $lang ) . ': ' . TAKA_Ticketing_Module::format_money( $data['discount_amount'] ?? '0', $data['currency'] ?? 'EUR' ),
					self::label( 'ticketing.final_amount', 'Final amount', $lang ) . ': ' . TAKA_Ticketing_Module::format_money( $data['amount'] ?? '0', $data['currency'] ?? 'EUR' ),
				)
			);
		}

		if ( ! empty( $data['applied_benefits'] ) && is_array( $data['applied_benefits'] ) ) {
			$lines[] = '';
			$lines[] = self::label( 'ticketing.included_benefits', 'Included benefits', $lang ) . ':';
			foreach ( $data['applied_benefits'] as $benefit ) {
				$lines[] = '- ' . TAKA_Ticketing_Module::benefit_line( $benefit );
			}
		}

		if ( 'bank_transfer' === (string) ( $data['payment_method'] ?? '' ) ) {
			$lines[] = '';
			$lines[] = self::label( 'ticketing.bank_transfer_instructions', 'Bank transfer instructions', $lang );
			foreach ( array( 'account_holder', 'bank_name', 'iban', 'bic', 'amount', 'payment_reference', 'due_date' ) as $field ) {
				if ( '' !== trim( (string) ( $instructions[ $field ] ?? '' ) ) ) {
					$lines[] = self::label( 'ticketing.' . $field, ucwords( str_replace( '_', ' ', $field ) ), $lang ) . ': ' . $instructions[ $field ];
				}
			}
			if ( '' !== trim( (string) ( $instructions['instructions'] ?? '' ) ) ) {
				$lines[] = $instructions['instructions'];
			}
		} elseif ( 'pay_at_door' === (string) ( $data['payment_method'] ?? '' ) ) {
			$lines[] = '';
			$lines[] = self::label( 'ticketing.pay_at_door_email_instructions', 'Payment will be collected during registration on site.', $lang );
			if ( '' !== trim( (string) ( $instructions['instructions'] ?? '' ) ) ) {
				$lines[] = $instructions['instructions'];
			}
		} elseif ( 'paypal' === (string) ( $data['payment_method'] ?? '' ) ) {
			$lines[] = '';
			$lines[] = 'paid' === (string) ( $data['payment_status'] ?? '' )
				? self::label( 'ticketing.paypal_payment_received', 'Your PayPal payment has been received.', $lang )
				: self::label( 'ticketing.paypal_email_instructions', 'Please complete payment securely with PayPal.', $lang );
		} elseif ( in_array( (string) ( $data['payment_method'] ?? '' ), array( 'promotion', 'free' ), true ) ) {
			$lines[] = '';
			$lines[] = self::label( 'ticketing.no_payment_required', 'No payment required.', $lang );
		}

		return implode( "\n", array_filter( $lines, static function ( $line ) { return null !== $line; } ) );
	}

	private static function order_confirmation_attachments( TAKA_Ticketing_Order $order ) {
		if ( ! class_exists( 'TAKA_Ticketing_Ticket_Artifact_Service' ) ) {
			return array();
		}
		if ( 'paypal' === (string) $order->get( 'payment_method', '' ) && 'paid' !== (string) $order->get( 'payment_status', '' ) ) {
			return array();
		}
		return TAKA_Ticketing_Ticket_Artifact_Service::buyer_attachments( $order );
	}

	private static function send_recipient_ticket_emails( TAKA_Ticketing_Order $order, $buyer_email, $lang ) {
		if ( ! class_exists( 'TAKA_Ticketing_Ticket_Artifact_Service' ) ) {
			return;
		}

		$buyer_email = sanitize_email( $buyer_email );
		$data = $order->to_array();
		$map = TAKA_Ticketing_Ticket_Artifact_Service::recipient_attachment_map( $order );
		foreach ( $map as $recipient_email => $attachments ) {
			$recipient_email = sanitize_email( $recipient_email );
			if ( '' === $recipient_email || 0 === strcasecmp( $recipient_email, $buyer_email ) || empty( $attachments ) ) {
				continue;
			}
			wp_mail(
				$recipient_email,
				sprintf( self::label( 'ticketing.email_subject_ticket', 'Your ticket for order %s', $lang ), $data['order_number'] ?? '' ),
				self::recipient_ticket_message( $order, $lang ),
				'',
				$attachments
			);
		}
	}

	private static function recipient_ticket_message( TAKA_Ticketing_Order $order, $lang ) {
		$data = $order->to_array();
		$lines = array(
			self::label( 'ticketing.email_ticket_intro', 'A ticket has been issued for you.', $lang ),
			'',
			self::label( 'ticketing.order_number', 'Order number', $lang ) . ': ' . ( $data['order_number'] ?? '' ),
		);
		if ( '' !== trim( (string) ( $data['event_title'] ?? '' ) ) ) {
			$lines[] = self::label( 'ticketing.event', 'Event', $lang ) . ': ' . $data['event_title'];
		}
		$lines[] = '';
		$lines[] = self::label( 'ticketing.email_ticket_attached', 'Your ticket with QR code is attached to this email.', $lang );
		return implode( "\n", $lines );
	}

	private static function cancellation_message( TAKA_Ticketing_Order $order, $lang ) {
		$data = $order->to_array();
		$line_items = is_array( $data['line_items'] ?? null ) ? $data['line_items'] : array();
		$lines = array(
			self::label( 'ticketing.email_cancelled_intro', 'Your registration has been cancelled.', $lang ),
			'',
			self::label( 'ticketing.order_number', 'Order number', $lang ) . ': ' . ( $data['order_number'] ?? '' ),
		);

		if ( '' !== trim( (string) ( $data['event_title'] ?? '' ) ) ) {
			$lines[] = self::label( 'ticketing.event', 'Event', $lang ) . ': ' . $data['event_title'];
		}
		if ( '' !== trim( (string) ( $data['ticket_type_name'] ?? '' ) ) ) {
			$lines[] = self::label( 'ticketing.ticket', 'Ticket', $lang ) . ': ' . $data['ticket_type_name'];
		}
		if ( ! empty( $line_items ) ) {
			$lines[] = '';
			$lines[] = self::label( 'ticketing.order_items', 'Order items', $lang ) . ':';
			foreach ( $line_items as $item ) {
				$lines[] = '- ' . TAKA_Ticketing_Module::line_item_label( $item );
			}
		}

		$lines[] = '';
		$lines[] = self::label( 'ticketing.amount', 'Amount', $lang ) . ': ' . TAKA_Ticketing_Module::format_money( $data['amount'] ?? '', $data['currency'] ?? 'EUR' );
		$lines[] = self::label( 'ticketing.payment_method', 'Payment method', $lang ) . ': ' . TAKA_Ticketing_Module::payment_method_label( $data['payment_method'] ?? '', $lang );
		$lines[] = self::label( 'ticketing.payment_status', 'Payment status', $lang ) . ': ' . self::payment_status_label( $data['payment_status'] ?? 'cancelled', $lang );
		$lines[] = '';

		if ( 'refunded' === (string) ( $data['payment_status'] ?? '' ) ) {
			$lines[] = self::label( 'ticketing.email_refund_processed', 'A refund has been processed for this order.', $lang );
		} else {
			$lines[] = self::label( 'ticketing.email_cancelled_follow_up', 'If payment or refund handling is needed, the organizer will follow up separately.', $lang );
		}

		return implode( "\n", array_filter( $lines, static function ( $line ) { return null !== $line; } ) );
	}

	private static function label( $key, $fallback, $lang ) {
		return TAKA_Ticketing_Module::text( $key, $fallback, $lang );
	}

	private static function order_language( $data ) {
		$lang = sanitize_key( $data['language'] ?? '' );
		return in_array( $lang, TAKA_Platform_Data::content_section_languages(), true ) ? $lang : TAKA_Platform_Data::platform_fallback_language();
	}

	private static function person_line( $person, $lang, $include_email, $include_dietary = true ) {
		$name = trim( ( $person['first_name'] ?? '' ) . ' ' . ( $person['last_name'] ?? '' ) );
		$parts = array( $name );
		if ( $include_email && '' !== trim( (string) ( $person['email'] ?? '' ) ) ) {
			$parts[] = '<' . $person['email'] . '>';
		}
		if ( '' !== trim( (string) ( $person['country'] ?? '' ) ) ) {
			$parts[] = TAKA_Platform_Data::country_label( $person['country'], $lang );
		}
		if ( $include_dietary && '' !== trim( (string) ( $person['dietary_preference'] ?? '' ) ) && 'none' !== (string) $person['dietary_preference'] ) {
			$parts[] = self::dietary_label( $person['dietary_preference'], $lang );
		}
		return trim( implode( ' / ', array_filter( $parts ) ) );
	}

	private static function dietary_label( $value, $lang ) {
		$labels = array(
			'vegetarian' => self::label( 'ticketing.dietary_vegetarian', 'Vegetarian', $lang ),
			'vegan'      => self::label( 'ticketing.dietary_vegan', 'Vegan', $lang ),
			'other'      => self::label( 'ticketing.dietary_other', 'Other / note', $lang ),
		);
		return $labels[ $value ] ?? sanitize_text_field( $value );
	}

	private static function payment_status_label( $status, $lang ) {
		$labels = array(
			'pending'   => self::label( 'ticketing.payment_status_pending', 'Pending', $lang ),
			'paid'      => self::label( 'ticketing.payment_status_paid', 'Paid', $lang ),
			'cancelled' => self::label( 'ticketing.payment_status_cancelled', 'Cancelled', $lang ),
			'refunded'  => self::label( 'ticketing.payment_status_refunded', 'Refunded', $lang ),
		);
		return $labels[ $status ] ?? sanitize_text_field( $status );
	}
}

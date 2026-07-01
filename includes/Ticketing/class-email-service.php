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
		if ( '' === $email ) {
			return false;
		}

		return wp_mail(
			$email,
			sprintf( __( 'Your registration %s', 'taka-platform' ), $data['order_number'] ?? '' ),
			self::order_message( $order, false )
		);
	}

	public static function send_admin_notification( TAKA_Ticketing_Order $order ) {
		$email = get_option( 'admin_email' );
		if ( '' === sanitize_email( $email ) ) {
			return false;
		}

		return wp_mail(
			$email,
			sprintf( __( 'New ticket order %s', 'taka-platform' ), $order->get( 'order_number', '' ) ),
			self::order_message( $order, true )
		);
	}

	public static function send_payment_confirmation( TAKA_Ticketing_Order $order ) {
		$data = $order->to_array();
		$buyer = is_array( $data['buyer'] ?? null ) ? $data['buyer'] : array();
		$email = sanitize_email( $buyer['email'] ?? '' );
		if ( '' === $email ) {
			return false;
		}

		return wp_mail(
			$email,
			sprintf( __( 'Payment received for %s', 'taka-platform' ), $data['order_number'] ?? '' ),
			sprintf(
				"%s\n\n%s: %s\n%s: %s\n%s: %s",
				__( 'Thank you. Your payment has been marked as received.', 'taka-platform' ),
				__( 'Order number', 'taka-platform' ),
				$data['order_number'] ?? '',
				__( 'Event', 'taka-platform' ),
				$data['event_title'] ?? '',
				__( 'Amount', 'taka-platform' ),
				TAKA_Ticketing_Module::format_money( $data['amount'] ?? '', $data['currency'] ?? 'EUR' )
			)
		);
	}

	private static function order_message( TAKA_Ticketing_Order $order, $admin ) {
		$data = $order->to_array();
		$buyer = is_array( $data['buyer'] ?? null ) ? $data['buyer'] : array();
		$participant = is_array( $data['participant'] ?? null ) ? $data['participant'] : array();
		$provider = TAKA_Ticketing_Module::payment_provider( $data['payment_method'] ?? '' );
		$instructions = $provider ? $provider->get_public_instructions( $order ) : array();

		$lines = array(
			$admin ? __( 'A new ticket order has been received.', 'taka-platform' ) : __( 'Your registration has been received.', 'taka-platform' ),
			'',
			__( 'Order number', 'taka-platform' ) . ': ' . ( $data['order_number'] ?? '' ),
			__( 'Event', 'taka-platform' ) . ': ' . ( $data['event_title'] ?? '' ),
			__( 'Ticket', 'taka-platform' ) . ': ' . ( $data['ticket_type_name'] ?? '' ),
			__( 'Buyer', 'taka-platform' ) . ': ' . trim( ( $buyer['first_name'] ?? '' ) . ' ' . ( $buyer['last_name'] ?? '' ) ) . ' <' . ( $buyer['email'] ?? '' ) . '>',
			__( 'Participant', 'taka-platform' ) . ': ' . trim( ( $participant['first_name'] ?? '' ) . ' ' . ( $participant['last_name'] ?? '' ) ),
			__( 'Payment method', 'taka-platform' ) . ': ' . TAKA_Ticketing_Module::payment_method_label( $data['payment_method'] ?? '' ),
			__( 'Amount', 'taka-platform' ) . ': ' . TAKA_Ticketing_Module::format_money( $data['amount'] ?? '', $data['currency'] ?? 'EUR' ),
			__( 'Payment status', 'taka-platform' ) . ': ' . ( $data['payment_status'] ?? 'pending' ),
		);

		if ( 'bank_transfer' === (string) ( $data['payment_method'] ?? '' ) ) {
			$lines[] = '';
			$lines[] = __( 'Bank transfer instructions', 'taka-platform' );
			foreach ( array( 'account_holder', 'iban', 'bic', 'bank_name', 'payment_reference' ) as $field ) {
				if ( '' !== trim( (string) ( $instructions[ $field ] ?? '' ) ) ) {
					$lines[] = ucwords( str_replace( '_', ' ', $field ) ) . ': ' . $instructions[ $field ];
				}
			}
			if ( '' !== trim( (string) ( $instructions['instructions'] ?? '' ) ) ) {
				$lines[] = $instructions['instructions'];
			}
		} elseif ( 'pay_at_door' === (string) ( $data['payment_method'] ?? '' ) ) {
			$lines[] = '';
			$lines[] = __( 'Payment will be collected during registration on site.', 'taka-platform' );
			if ( '' !== trim( (string) ( $instructions['instructions'] ?? '' ) ) ) {
				$lines[] = $instructions['instructions'];
			}
		}

		return implode( "\n", array_filter( $lines, static function ( $line ) { return null !== $line; } ) );
	}
}

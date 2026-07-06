<?php
/**
 * Bank transfer payment provider for native TAKA Ticketing.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Ticketing_Bank_Transfer_Provider implements TAKA_Ticketing_Payment_Provider_Interface {
	public function get_id() {
		return 'bank_transfer';
	}

	public function get_label() {
		return __( 'Bank transfer', 'taka-platform' );
	}

	public function is_enabled() {
		return true;
	}

	public function get_public_instructions( $order ) {
		$order_data = is_object( $order ) && method_exists( $order, 'to_array' ) ? $order->to_array() : (array) $order;
		$settings = $this->settings_for_event( absint( $order_data['event_id'] ?? 0 ) );
		$reference = $this->payment_reference( $order, $settings['payment_reference_template'] ?? '' );
		$lang = sanitize_key( $order_data['language'] ?? '' );
		$due_days = absint( $settings['payment_due_days'] ?? 0 );
		$due_date = '';
		if ( $due_days > 0 ) {
			$created = strtotime( (string) ( $order_data['created_at'] ?? current_time( 'mysql' ) ) );
			$due_date = date_i18n( get_option( 'date_format' ), ( $created ?: time() ) + ( DAY_IN_SECONDS * $due_days ) );
		}
		$instructions = trim( (string) ( $settings['instructions_text'] ?? '' ) );
		if ( '' === $instructions && class_exists( 'TAKA_Ticketing_Module' ) ) {
			$instructions = TAKA_Ticketing_Module::text( 'ticketing.bank_transfer_default_explanation', 'Please transfer the total amount using the payment reference within the stated payment period. Your booking will be confirmed after payment has been received.', $lang );
		}

		return array(
			'account_holder'    => $settings['account_holder'] ?? '',
			'iban'              => $settings['iban'] ?? '',
			'bic'               => $settings['bic'] ?? '',
			'bank_name'         => $settings['bank_name'] ?? '',
			'amount'            => class_exists( 'TAKA_Ticketing_Module' ) ? TAKA_Ticketing_Module::format_money( $order_data['amount'] ?? '0', $order_data['currency'] ?? 'EUR' ) : (string) ( $order_data['amount'] ?? '' ),
			'payment_reference' => $reference,
			'due_date'          => $due_date,
			'instructions'      => $instructions,
		);
	}

	public function create_payment( $order ) {
		$order_data = is_object( $order ) && method_exists( $order, 'to_array' ) ? $order->to_array() : (array) $order;
		$settings = $this->settings_for_event( absint( $order_data['event_id'] ?? 0 ) );
		return array(
			'provider'      => $this->get_id(),
			'status'        => 'pending',
			'account_scope' => sanitize_key( $settings['account_scope'] ?? 'global' ),
			'organizer_id'  => absint( $settings['organizer_id'] ?? ( $order_data['organizer_id'] ?? 0 ) ),
			'created_at'    => current_time( 'mysql' ),
		);
	}

	public function handle_return( $request ) {
		return null;
	}

	public function handle_webhook( $request ) {
		return null;
	}

	public function mark_paid( $order, $transaction_id ) {
		return array(
			'order'          => $order,
			'transaction_id' => sanitize_text_field( $transaction_id ),
			'payment_status' => 'paid',
		);
	}

	public function refund( $order ) {
		return new WP_Error( 'taka_ticketing_refund_not_supported', __( 'Bank transfer refunds are not implemented yet.', 'taka-platform' ) );
	}

	public function get_admin_fields() {
		return array(
			'enabled'                    => array( 'type' => 'checkbox', 'label' => __( 'Enable bank transfer', 'taka-platform' ) ),
			'account_holder'             => array( 'type' => 'text', 'label' => __( 'Account holder', 'taka-platform' ) ),
			'iban'                       => array( 'type' => 'text', 'label' => __( 'IBAN', 'taka-platform' ) ),
			'bic'                        => array( 'type' => 'text', 'label' => __( 'BIC', 'taka-platform' ) ),
			'bank_name'                  => array( 'type' => 'text', 'label' => __( 'Bank name', 'taka-platform' ) ),
			'payment_reference_template' => array( 'type' => 'text', 'label' => __( 'Payment reference template', 'taka-platform' ) ),
			'payment_due_days'           => array( 'type' => 'number', 'label' => __( 'Payment due after days', 'taka-platform' ) ),
			'instructions_text'          => array( 'type' => 'textarea', 'label' => __( 'Instructions text', 'taka-platform' ) ),
		);
	}

	public function settings() {
		$stored = get_option( TAKA_Ticketing_Module::BANK_TRANSFER_OPTION, array() );
		return TAKA_Ticketing_Module::normalize_bank_transfer_settings( is_array( $stored ) ? $stored : array() );
	}

	public function settings_for_event( $event_id ) {
		return TAKA_Ticketing_Module::bank_transfer_settings_for_event( $event_id );
	}

	private function payment_reference( $order, $template ) {
		$template = '' !== trim( (string) $template ) ? (string) $template : 'TAKA-{order_number}';
		$order_data = is_object( $order ) && method_exists( $order, 'to_array' ) ? $order->to_array() : (array) $order;
		return strtr(
			$template,
			array(
				'{order_id}'     => sanitize_text_field( $order_data['id'] ?? '' ),
				'{order_number}' => sanitize_text_field( $order_data['order_number'] ?? '' ),
				'{event_id}'     => sanitize_text_field( $order_data['event_id'] ?? '' ),
			)
		);
	}
}

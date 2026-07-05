<?php
/**
 * PayPal payment provider for native TAKA Ticketing.
 *
 * The provider follows the same interface as bank transfer and pay-at-the-door.
 * Checkout creates a PayPal order server-side and redirects the visitor to the
 * approval URL; the client secret is never exposed to frontend JavaScript.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Ticketing_PayPal_Provider implements TAKA_Ticketing_Payment_Provider_Interface {
	public function get_id() {
		return 'paypal';
	}

	public function get_label() {
		return __( 'PayPal', 'taka-platform' );
	}

	public function is_enabled() {
		$settings = $this->settings();
		return ! empty( $settings['enabled'] ) && '' !== trim( (string) $settings['client_id'] ) && '' !== trim( (string) $settings['secret'] );
	}

	public function get_public_instructions( $order ) {
		$data = is_object( $order ) && method_exists( $order, 'to_array' ) ? $order->to_array() : (array) $order;
		$lang = sanitize_key( $data['language'] ?? '' );
		return array(
			'message'       => TAKA_Ticketing_Module::text( 'ticketing.paypal_next_steps', 'Complete payment securely with PayPal.', $lang ),
			'paypal_order_id' => sanitize_text_field( $data['payment']['paypal_order_id'] ?? '' ),
			'transaction_id'  => sanitize_text_field( $data['payment']['transaction_id'] ?? '' ),
			'approval_url'    => esc_url_raw( $data['payment']['approval_url'] ?? '' ),
		);
	}

	public function create_payment( $order ) {
		if ( ! $this->is_enabled() ) {
			return new WP_Error( 'taka_ticketing_paypal_disabled', __( 'PayPal is not configured yet.', 'taka-platform' ) );
		}

		$order_data = is_object( $order ) && method_exists( $order, 'to_array' ) ? $order->to_array() : (array) $order;
		$token = sanitize_text_field( $order_data['public_token'] ?? '' );
		$return_url = esc_url_raw( $order_data['checkout_return_url'] ?? home_url( '/' ) );
		$settings = $this->settings();
		$currency = TAKA_Platform_Data::normalize_event_option_value( 'currency', $settings['currency'] ?? ( $order_data['currency'] ?? 'EUR' ) ) ?: 'EUR';
		$amount = $this->paypal_amount( $order_data['amount'] ?? '0' );
		$access_token = $this->access_token();
		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$response = wp_remote_post(
			$this->api_base_url() . '/v2/checkout/orders',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'intent'              => 'CAPTURE',
						'purchase_units'      => array(
							array(
								'reference_id' => sanitize_text_field( $order_data['order_number'] ?? $token ),
								'description'  => sanitize_text_field( $order_data['event_title'] ?? __( 'TAKA registration', 'taka-platform' ) ),
								'custom_id'    => $token,
								'amount'       => array(
									'currency_code' => $currency,
									'value'         => $amount,
								),
							),
						),
						'application_context' => array(
							'brand_name'  => sanitize_text_field( get_bloginfo( 'name' ) ),
							'user_action' => 'PAY_NOW',
							'return_url'  => TAKA_Ticketing_Module::paypal_return_url( $token, $return_url ),
							'cancel_url'  => TAKA_Ticketing_Module::paypal_cancel_url( $token, $return_url ),
						),
					)
				),
			)
		);

		$payload = $this->decode_response( $response );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$approval_url = $this->approval_url_from_payload( $payload );
		if ( '' === $approval_url ) {
			return new WP_Error( 'taka_ticketing_paypal_approval_missing', __( 'PayPal did not return an approval URL.', 'taka-platform' ) );
		}

		return array(
			'provider'        => $this->get_id(),
			'status'          => 'pending',
			'paypal_order_id' => sanitize_text_field( $payload['id'] ?? '' ),
			'approval_url'    => $approval_url,
			'created_at'      => current_time( 'mysql' ),
		);
	}

	public function handle_return( $request ) {
		$request = is_array( $request ) ? $request : array();
		$token = sanitize_text_field( $request['taka_token'] ?? '' );
		$paypal_order_id = sanitize_text_field( $request['token'] ?? '' );
		$order = '' !== $token ? TAKA_Ticketing_Module::order_repository()->find_by_public_token( $token ) : null;
		if ( ! $order ) {
			return new WP_Error( 'taka_ticketing_paypal_order_missing', __( 'PayPal order could not be matched to a TAKA order.', 'taka-platform' ) );
		}

		$data = $order->to_array();
		if ( 'paid' === (string) ( $data['payment_status'] ?? '' ) ) {
			return $order;
		}
		$payment = is_array( $data['payment'] ?? null ) ? $data['payment'] : array();
		if ( '' !== (string) ( $payment['paypal_order_id'] ?? '' ) && (string) $payment['paypal_order_id'] !== $paypal_order_id ) {
			return new WP_Error( 'taka_ticketing_paypal_order_mismatch', __( 'PayPal returned a different order than expected.', 'taka-platform' ) );
		}

		$capture = $this->capture_order( $paypal_order_id );
		if ( is_wp_error( $capture ) ) {
			return $capture;
		}

		return $this->mark_order_paid( $order, $capture['transaction_id'] ?? '', $capture );
	}

	public function handle_webhook( $request ) {
		$body = file_get_contents( 'php://input' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$event = json_decode( (string) $body, true );
		if ( ! is_array( $event ) ) {
			return new WP_Error( 'taka_ticketing_paypal_webhook_invalid', __( 'Invalid PayPal webhook payload.', 'taka-platform' ) );
		}
		$verified = $this->verify_webhook_signature( $event, is_array( $request ) ? $request : array() );
		if ( is_wp_error( $verified ) ) {
			return $verified;
		}

		$event_type = sanitize_text_field( $event['event_type'] ?? '' );
		$resource = is_array( $event['resource'] ?? null ) ? $event['resource'] : array();
		if ( 'CHECKOUT.ORDER.APPROVED' === $event_type ) {
			$paypal_order_id = sanitize_text_field( $resource['id'] ?? '' );
			$order = $this->find_order_by_paypal_order_id( $paypal_order_id );
			if ( ! $order ) {
				return new WP_Error( 'taka_ticketing_paypal_order_missing', __( 'Webhook order could not be matched.', 'taka-platform' ) );
			}
			$capture = $this->capture_order( $paypal_order_id );
			if ( is_wp_error( $capture ) ) {
				return $capture;
			}
			return $this->mark_order_paid( $order, $capture['transaction_id'] ?? '', $capture );
		}

		if ( 'PAYMENT.CAPTURE.COMPLETED' === $event_type ) {
			$paypal_order_id = sanitize_text_field( $resource['supplementary_data']['related_ids']['order_id'] ?? '' );
			$transaction_id = sanitize_text_field( $resource['id'] ?? '' );
			$order = $this->find_order_by_paypal_order_id( $paypal_order_id );
			if ( ! $order ) {
				return new WP_Error( 'taka_ticketing_paypal_order_missing', __( 'Webhook capture could not be matched.', 'taka-platform' ) );
			}
			return $this->mark_order_paid( $order, $transaction_id, array( 'status' => sanitize_text_field( $resource['status'] ?? 'COMPLETED' ) ) );
		}

		return true;
	}

	public function mark_paid( $order, $transaction_id ) {
		return $this->mark_order_paid( $order, $transaction_id, array() );
	}

	public function refund( $order ) {
		return new WP_Error( 'taka_ticketing_refund_not_supported', __( 'PayPal refunds are not implemented yet.', 'taka-platform' ) );
	}

	public function get_admin_fields() {
		return array(
			'enabled'    => array( 'type' => 'checkbox', 'label' => __( 'Enable PayPal', 'taka-platform' ) ),
			'client_id'  => array( 'type' => 'text', 'label' => __( 'PayPal client ID', 'taka-platform' ) ),
			'secret'     => array( 'type' => 'password', 'label' => __( 'PayPal secret', 'taka-platform' ) ),
			'mode'       => array( 'type' => 'select', 'label' => __( 'Mode', 'taka-platform' ) ),
			'currency'   => array( 'type' => 'select', 'label' => __( 'Currency', 'taka-platform' ) ),
			'webhook_id' => array( 'type' => 'text', 'label' => __( 'Webhook ID', 'taka-platform' ) ),
		);
	}

	private function settings() {
		return TAKA_Ticketing_Module::paypal_settings();
	}

	private function api_base_url() {
		$settings = $this->settings();
		return 'live' === (string) ( $settings['mode'] ?? 'sandbox' ) ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
	}

	private function access_token() {
		$settings = $this->settings();
		$cache_key = 'taka_ticketing_paypal_access_' . md5( (string) ( $settings['mode'] ?? 'sandbox' ) . '|' . (string) ( $settings['client_id'] ?? '' ) );
		$cached = get_transient( $cache_key );
		if ( '' !== (string) $cached ) {
			return (string) $cached;
		}

		$response = wp_remote_post(
			$this->api_base_url() . '/v1/oauth2/token',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( (string) $settings['client_id'] . ':' . (string) $settings['secret'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				),
				'body'    => array( 'grant_type' => 'client_credentials' ),
			)
		);

		$payload = $this->decode_response( $response );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
		$token = sanitize_text_field( $payload['access_token'] ?? '' );
		if ( '' === $token ) {
			return new WP_Error( 'taka_ticketing_paypal_token_missing', __( 'PayPal did not return an access token.', 'taka-platform' ) );
		}
		set_transient( $cache_key, $token, max( 60, absint( $payload['expires_in'] ?? 3600 ) - 60 ) );
		return $token;
	}

	private function capture_order( $paypal_order_id ) {
		$paypal_order_id = sanitize_text_field( $paypal_order_id );
		if ( '' === $paypal_order_id ) {
			return new WP_Error( 'taka_ticketing_paypal_order_missing', __( 'Missing PayPal order ID.', 'taka-platform' ) );
		}
		$access_token = $this->access_token();
		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}
		$response = wp_remote_post(
			$this->api_base_url() . '/v2/checkout/orders/' . rawurlencode( $paypal_order_id ) . '/capture',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
			)
		);
		$payload = $this->decode_response( $response, array( 200, 201 ) );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
		$transaction_id = $this->transaction_id_from_capture( $payload );
		return array(
			'status'         => sanitize_text_field( $payload['status'] ?? '' ),
			'transaction_id' => $transaction_id,
			'paypal_order_id'=> $paypal_order_id,
			'captured_at'    => current_time( 'mysql' ),
		);
	}

	private function verify_webhook_signature( $event, $server ) {
		$settings = $this->settings();
		if ( '' === trim( (string) ( $settings['webhook_id'] ?? '' ) ) ) {
			return new WP_Error( 'taka_ticketing_paypal_webhook_unconfigured', __( 'Configure the PayPal webhook ID before webhook confirmations are accepted.', 'taka-platform' ) );
		}
		$access_token = $this->access_token();
		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$headers = array(
			'transmission_id'   => sanitize_text_field( $server['HTTP_PAYPAL_TRANSMISSION_ID'] ?? '' ),
			'transmission_time' => sanitize_text_field( $server['HTTP_PAYPAL_TRANSMISSION_TIME'] ?? '' ),
			'cert_url'          => esc_url_raw( $server['HTTP_PAYPAL_CERT_URL'] ?? '' ),
			'auth_algo'         => sanitize_text_field( $server['HTTP_PAYPAL_AUTH_ALGO'] ?? '' ),
			'transmission_sig'  => sanitize_text_field( $server['HTTP_PAYPAL_TRANSMISSION_SIG'] ?? '' ),
		);
		foreach ( $headers as $value ) {
			if ( '' === trim( (string) $value ) ) {
				return new WP_Error( 'taka_ticketing_paypal_webhook_headers', __( 'PayPal webhook signature headers are missing.', 'taka-platform' ) );
			}
		}

		$response = wp_remote_post(
			$this->api_base_url() . '/v1/notifications/verify-webhook-signature',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array_merge(
						$headers,
						array(
							'webhook_id'    => sanitize_text_field( $settings['webhook_id'] ),
							'webhook_event' => $event,
						)
					)
				),
			)
		);
		$payload = $this->decode_response( $response );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
		return 'SUCCESS' === (string) ( $payload['verification_status'] ?? '' ) ? true : new WP_Error( 'taka_ticketing_paypal_webhook_unverified', __( 'PayPal webhook signature could not be verified.', 'taka-platform' ) );
	}

	private function decode_response( $response, $expected_codes = array( 200, 201 ) ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		$payload = json_decode( $body, true );
		$payload = is_array( $payload ) ? $payload : array();
		if ( ! in_array( $code, (array) $expected_codes, true ) ) {
			$message = sanitize_text_field( $payload['message'] ?? ( $payload['name'] ?? __( 'PayPal request failed.', 'taka-platform' ) ) );
			return new WP_Error( 'taka_ticketing_paypal_api', $message );
		}
		return $payload;
	}

	private function approval_url_from_payload( $payload ) {
		foreach ( (array) ( $payload['links'] ?? array() ) as $link ) {
			if ( 'approve' === (string) ( $link['rel'] ?? '' ) && ! empty( $link['href'] ) ) {
				return esc_url_raw( $link['href'] );
			}
		}
		return '';
	}

	private function transaction_id_from_capture( $payload ) {
		foreach ( (array) ( $payload['purchase_units'] ?? array() ) as $unit ) {
			$payments = is_array( $unit['payments'] ?? null ) ? $unit['payments'] : array();
			foreach ( (array) ( $payments['captures'] ?? array() ) as $capture ) {
				if ( ! empty( $capture['id'] ) ) {
					return sanitize_text_field( $capture['id'] );
				}
			}
		}
		return '';
	}

	private function mark_order_paid( $order, $transaction_id, $capture ) {
		if ( ! $order instanceof TAKA_Ticketing_Order ) {
			return new WP_Error( 'taka_ticketing_paypal_order_missing', __( 'Order not found.', 'taka-platform' ) );
		}
		$data = $order->to_array();
		if ( 'paid' === (string) ( $data['payment_status'] ?? '' ) ) {
			return $order;
		}
		$payment = is_array( $data['payment'] ?? null ) ? $data['payment'] : array();
		$data['payment_status'] = 'paid';
		$data['order_status'] = 'confirmed';
		$data['updated_at'] = current_time( 'mysql' );
		$data['payment'] = array_merge(
			$payment,
			array(
				'provider'       => $this->get_id(),
				'status'         => 'paid',
				'transaction_id' => sanitize_text_field( $transaction_id ),
				'captured_at'    => sanitize_text_field( $capture['captured_at'] ?? current_time( 'mysql' ) ),
			)
		);
		$data['timeline'][] = array( 'time' => current_time( 'mysql' ), 'label' => __( 'PayPal payment received', 'taka-platform' ) );
		$saved = TAKA_Ticketing_Module::order_repository()->save( new TAKA_Ticketing_Order( $data ) );
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

	private function find_order_by_paypal_order_id( $paypal_order_id ) {
		$paypal_order_id = sanitize_text_field( $paypal_order_id );
		if ( '' === $paypal_order_id ) {
			return null;
		}
		foreach ( TAKA_Ticketing_Module::order_repository()->query( array( 'per_page' => -1 ) ) as $order ) {
			$payment = is_array( $order->get( 'payment', array() ) ) ? $order->get( 'payment', array() ) : array();
			if ( (string) ( $payment['paypal_order_id'] ?? '' ) === $paypal_order_id ) {
				return $order;
			}
		}
		return null;
	}

	private function paypal_amount( $amount ) {
		return number_format( TAKA_Ticketing_Pricing_Service::money_to_float( $amount ), 2, '.', '' );
	}
}

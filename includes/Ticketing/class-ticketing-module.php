<?php
/**
 * Native TAKA Ticketing module.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Ticketing_Module {
	const MODE                 = 'native_taka_ticketing';
	const BANK_TRANSFER_OPTION = 'taka_ticketing_bank_transfer_settings';
	const SETTINGS_OPTION      = 'taka_native_ticketing_settings';
	const PAYMENT_METHODS_META = '_taka_native_payment_methods';
	const PAYMENT_METHODS_CONFIGURED_META = '_taka_native_payment_methods_configured';
	const BANK_TRANSFER_META   = '_taka_native_bank_transfer_settings';
	const ORGANIZER_BANK_TRANSFER_META = '_taka_organizer_bank_transfer_settings';
	const ORGANIZER_PAYPAL_META = '_taka_organizer_paypal_settings';
	const PAY_AT_DOOR_INSTRUCTIONS_META = '_taka_native_pay_at_door_instructions';
	const DIETARY_PREFERENCES_META = '_taka_native_dietary_preferences_enabled';
	const CHECKOUT_ACTION      = 'taka_ticketing_checkout';
	const DOWNLOAD_ACTION      = 'taka_ticketing_download_document';
	const PAYPAL_RETURN_ACTION = 'taka_ticketing_paypal_return';
	const PAYPAL_CANCEL_ACTION = 'taka_ticketing_paypal_cancel';
	const PAYPAL_WEBHOOK_ACTION = 'taka_ticketing_paypal_webhook';
	const PROMOTION_AJAX_ACTION = 'taka_ticketing_apply_promotion';
	const ADMIN_ACTION         = 'taka_ticketing_order_action';
	const SETTINGS_ACTION      = 'taka_ticketing_save_settings';
	const PROMOTION_ACTION     = 'taka_ticketing_save_promotion';
	const PROMOTION_DELETE_ACTION = 'taka_ticketing_delete_promotion';
	const PRODUCT_ACTION       = 'taka_ticketing_save_product';
	const PRODUCT_DELETE_ACTION = 'taka_ticketing_delete_product';
	const ADMIN_PAGE_SLUG      = 'taka-platform-ticketing';

	private static $payment_providers = array();
	private static $order_repository = null;
	private static $promotion_repository = null;
	private static $product_repository = null;

	/** Register native ticketing hooks and provider implementations. */
	public static function init() {
		self::register_payment_provider( new TAKA_Ticketing_Bank_Transfer_Provider() );
		self::register_payment_provider( new TAKA_Ticketing_Pay_At_Door_Provider() );
		self::register_payment_provider( new TAKA_Ticketing_PayPal_Provider() );
		add_action( 'init', array( __CLASS__, 'register_post_types' ), 0 );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'ensure_capabilities' ) );
		add_action( 'admin_post_' . self::CHECKOUT_ACTION, array( __CLASS__, 'handle_checkout' ) );
		add_action( 'admin_post_nopriv_' . self::CHECKOUT_ACTION, array( __CLASS__, 'handle_checkout' ) );
		add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( __CLASS__, 'handle_document_download' ) );
		add_action( 'admin_post_nopriv_' . self::DOWNLOAD_ACTION, array( __CLASS__, 'handle_document_download' ) );
		add_action( 'admin_post_' . self::PAYPAL_RETURN_ACTION, array( __CLASS__, 'handle_paypal_return' ) );
		add_action( 'admin_post_nopriv_' . self::PAYPAL_RETURN_ACTION, array( __CLASS__, 'handle_paypal_return' ) );
		add_action( 'admin_post_' . self::PAYPAL_CANCEL_ACTION, array( __CLASS__, 'handle_paypal_cancel' ) );
		add_action( 'admin_post_nopriv_' . self::PAYPAL_CANCEL_ACTION, array( __CLASS__, 'handle_paypal_cancel' ) );
		add_action( 'admin_post_' . self::PAYPAL_WEBHOOK_ACTION, array( __CLASS__, 'handle_paypal_webhook' ) );
		add_action( 'admin_post_nopriv_' . self::PAYPAL_WEBHOOK_ACTION, array( __CLASS__, 'handle_paypal_webhook' ) );
		add_action( 'wp_ajax_' . self::PROMOTION_AJAX_ACTION, array( __CLASS__, 'handle_apply_promotion_ajax' ) );
		add_action( 'wp_ajax_nopriv_' . self::PROMOTION_AJAX_ACTION, array( __CLASS__, 'handle_apply_promotion_ajax' ) );
		add_action( 'admin_post_' . self::ADMIN_ACTION, array( __CLASS__, 'handle_admin_order_action' ) );
		add_action( 'admin_post_' . self::SETTINGS_ACTION, array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_' . self::PROMOTION_ACTION, array( __CLASS__, 'handle_save_promotion' ) );
		add_action( 'admin_post_' . self::PROMOTION_DELETE_ACTION, array( __CLASS__, 'handle_delete_promotion' ) );
		add_action( 'admin_post_' . self::PRODUCT_ACTION, array( __CLASS__, 'handle_save_product' ) );
		add_action( 'admin_post_' . self::PRODUCT_DELETE_ACTION, array( __CLASS__, 'handle_delete_product' ) );
		add_shortcode( 'taka_ticketing_product', array( __CLASS__, 'product_shortcode' ) );
		add_filter( 'taka_platform_event_assistant_sections', array( __CLASS__, 'register_event_assistant_section' ) );
	}

	public static function register_post_types() {
		register_post_type(
			TAKA_PLATFORM_CPT_TICKET_ORDER,
			array(
				'labels'              => array(
					'name'          => __( 'Ticket Orders', 'taka-platform' ),
					'singular_name' => __( 'Ticket Order', 'taka-platform' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'capability_type'     => 'post',
				'supports'            => array( 'title' ),
			)
		);

		register_post_type(
			TAKA_PLATFORM_CPT_TICKET_PROMOTION,
			array(
				'labels'              => array(
					'name'          => __( 'Ticket Promotions', 'taka-platform' ),
					'singular_name' => __( 'Ticket Promotion', 'taka-platform' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'capability_type'     => 'post',
				'supports'            => array( 'title' ),
			)
		);

		register_post_type(
			TAKA_PLATFORM_CPT_TICKETING_PRODUCT,
			array(
				'labels'              => array(
					'name'          => __( 'Ticketing Products', 'taka-platform' ),
					'singular_name' => __( 'Ticketing Product', 'taka-platform' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'capability_type'     => 'post',
				'supports'            => array( 'title' ),
			)
		);
	}

	public static function register_admin_menu() {
		add_submenu_page(
			'taka-platform',
			__( 'Ticketing', 'taka-platform' ),
			__( 'Ticketing', 'taka-platform' ),
			'view_taka_orders',
			self::ADMIN_PAGE_SLUG,
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/** Reserve private ticketing capabilities for current and future phases. */
	public static function ensure_capabilities() {
		if ( ! function_exists( 'get_role' ) ) {
			return;
		}

		$admin_role = get_role( 'administrator' );
		if ( ! $admin_role ) {
			return;
		}

		foreach ( self::capabilities() as $cap ) {
			$admin_role->add_cap( $cap );
		}
	}

	public static function capabilities() {
		return array(
			'manage_taka_ticketing',
			'view_taka_orders',
			'edit_taka_orders',
			'checkin_taka_participants',
			'manage_taka_promotions',
			'manage_taka_products',
		);
	}

	public static function register_payment_provider( $provider ) {
		if ( ! $provider instanceof TAKA_Ticketing_Payment_Provider_Interface ) {
			return;
		}
		self::$payment_providers[ $provider->get_id() ] = $provider;
	}

	public static function payment_providers() {
		return self::$payment_providers;
	}

	public static function payment_provider( $provider_id ) {
		$provider_id = sanitize_key( $provider_id );
		return self::$payment_providers[ $provider_id ] ?? null;
	}

	public static function order_repository() {
		if ( null === self::$order_repository ) {
			self::$order_repository = new TAKA_Ticketing_Order_Repository();
		}
		return self::$order_repository;
	}

	public static function promotion_repository() {
		if ( null === self::$promotion_repository ) {
			self::$promotion_repository = new TAKA_Ticketing_Promotion_Repository();
		}
		return self::$promotion_repository;
	}

	public static function product_repository() {
		if ( null === self::$product_repository ) {
			self::$product_repository = new TAKA_Ticketing_Product_Repository();
		}
		return self::$product_repository;
	}

	public static function normalize_bank_transfer_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		return array(
			'enabled'                    => ! empty( $settings['enabled'] ) ? '1' : '0',
			'account_holder'             => sanitize_text_field( $settings['account_holder'] ?? '' ),
			'iban'                       => strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( $settings['iban'] ?? '' ) ) ),
			'bic'                        => strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( $settings['bic'] ?? '' ) ) ),
			'bank_name'                  => sanitize_text_field( $settings['bank_name'] ?? '' ),
			'payment_reference_template' => sanitize_text_field( $settings['payment_reference_template'] ?? 'TAKA-{order_number}' ),
			'instructions_text'          => sanitize_textarea_field( $settings['instructions_text'] ?? '' ),
			'payment_due_days'           => absint( $settings['payment_due_days'] ?? 0 ),
		);
	}

	public static function normalize_paypal_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		return array(
			'enabled'    => ! empty( $settings['enabled'] ) ? '1' : '0',
			'client_id'  => sanitize_text_field( $settings['client_id'] ?? '' ),
			'secret'     => sanitize_text_field( $settings['secret'] ?? '' ),
			'mode'       => 'live' === sanitize_key( $settings['mode'] ?? 'sandbox' ) ? 'live' : 'sandbox',
			'currency'   => TAKA_Platform_Data::normalize_event_option_value( 'currency', $settings['currency'] ?? 'EUR' ) ?: 'EUR',
			'webhook_id' => sanitize_text_field( $settings['webhook_id'] ?? '' ),
		);
	}

	public static function sanitize_ticket_types( $items ) {
		return TAKA_Ticketing_Ticket_Types::normalize_ticket_types( $items );
	}

	public static function ticket_types_for_event( $event_id ) {
		return TAKA_Ticketing_Ticket_Types::get_for_event( $event_id );
	}

	public static function event_uses_native_ticketing( $event_or_id ) {
		if ( is_array( $event_or_id ) ) {
			return self::MODE === TAKA_Platform_Data::ticket_mode_for_event( $event_or_id );
		}
		$event_id = absint( $event_or_id );
		return self::MODE === TAKA_Platform_Data::ticket_mode_for_event(
			array(
				'ticket_mode'     => get_post_meta( $event_id, '_taka_ticket_mode', true ),
				'ticket_status'   => get_post_meta( $event_id, '_taka_ticket_status', true ),
				'ticket_provider' => get_post_meta( $event_id, '_taka_ticket_provider', true ),
				'ticket_shop_url' => get_post_meta( $event_id, '_taka_ticket_shop_url', true ),
			)
		);
	}

	public static function enabled_payment_methods_for_event( $event_id, $only_active = true ) {
		$event_id = absint( $event_id );
		$stored = get_post_meta( $event_id, self::PAYMENT_METHODS_META, true );
		$items = is_array( $stored ) ? $stored : preg_split( '/\s*,\s*/', (string) $stored );
		$items = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $items ) ) ) );
		$was_configured_after_provider_selection = '1' === (string) get_post_meta( $event_id, self::PAYMENT_METHODS_CONFIGURED_META, true );
		if ( empty( $items ) || ( ! $was_configured_after_provider_selection && self::is_legacy_default_payment_methods( $items ) ) ) {
			$items = self::default_payment_methods_for_event( $event_id );
		}
		return array_values(
			array_filter(
				$items,
				static function ( $method ) use ( $event_id, $only_active ) {
					if ( ! isset( self::$payment_providers[ $method ] ) ) {
						return false;
					}
					return ! $only_active || self::payment_provider_enabled_for_event( $method, $event_id );
				}
			)
		);
	}

	private static function default_payment_methods_for_event( $event_id = 0 ) {
		$methods = array( 'bank_transfer' );
		if ( isset( self::$payment_providers['paypal'] ) && self::payment_provider_enabled_for_event( 'paypal', $event_id ) ) {
			$methods[] = 'paypal';
		}
		return array_values( array_unique( $methods ) );
	}

	public static function payment_provider_enabled_for_event( $method, $event_id = 0 ) {
		$method = sanitize_key( $method );
		if ( ! isset( self::$payment_providers[ $method ] ) ) {
			return false;
		}
		if ( 'paypal' === $method ) {
			$settings = self::paypal_settings_for_event( $event_id );
			return ! empty( $settings['enabled'] ) && '' !== trim( (string) ( $settings['client_id'] ?? '' ) ) && '' !== trim( (string) ( $settings['secret'] ?? '' ) );
		}
		return self::$payment_providers[ $method ]->is_enabled();
	}

	private static function is_legacy_default_payment_methods( $methods ) {
		$methods = array_values( array_filter( array_map( 'sanitize_key', (array) $methods ) ) );
		return 1 === count( $methods ) && 'bank_transfer' === $methods[0];
	}

	public static function event_bank_transfer_settings( $event_id ) {
		$stored = get_post_meta( absint( $event_id ), self::BANK_TRANSFER_META, true );
		return self::normalize_bank_transfer_settings( is_array( $stored ) ? $stored : array() );
	}

	public static function organizer_bank_transfer_settings( $organizer_id ) {
		$stored = get_post_meta( absint( $organizer_id ), self::ORGANIZER_BANK_TRANSFER_META, true );
		return self::normalize_bank_transfer_settings( is_array( $stored ) ? $stored : array() );
	}

	public static function bank_transfer_settings_for_event( $event_id ) {
		$event_id = absint( $event_id );
		$global = self::normalize_bank_transfer_settings( get_option( self::BANK_TRANSFER_OPTION, array() ) );
		$organizer_id = self::event_billing_organizer_id( $event_id );
		$organizer = $organizer_id ? self::organizer_bank_transfer_settings( $organizer_id ) : array();
		$event = $event_id ? self::event_bank_transfer_settings( $event_id ) : array();

		if ( $organizer_id ) {
			$effective = $organizer;
			$effective['account_scope'] = 'organizer';
			$effective['organizer_id'] = $organizer_id;
		} else {
			$effective = $global;
			$effective['account_scope'] = 'global';
			$effective['organizer_id'] = 0;
		}
		if ( self::payment_settings_have_values( $event, array( 'account_holder', 'iban', 'bic', 'bank_name', 'instructions_text' ) ) ) {
			$effective = array_merge( $effective, self::non_empty_settings( $event ) );
			$effective['account_scope'] = 'event';
			$effective['organizer_id'] = $organizer_id;
		}
		return self::normalize_bank_transfer_settings( $effective ) + array(
			'account_scope' => sanitize_key( $effective['account_scope'] ?? 'global' ),
			'organizer_id'  => absint( $effective['organizer_id'] ?? 0 ),
		);
	}

	public static function default_settings() {
		return array(
			'terms_url'         => '',
			'privacy_url'       => function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '',
			'terms_label'       => array(
				'de' => 'Ich akzeptiere die {link}.',
				'en' => 'I accept the {link}.',
				'nl' => 'Ik ga akkoord met de {link}.',
				'fr' => 'J’accepte les {link}.',
				'lb' => 'Ech akzeptéieren d’{link}.',
				'fi' => 'Hyväksyn {link}.',
				'ja' => '{link}に同意します。',
			),
			'terms_link_text'   => array(
				'de' => 'Buchungsbedingungen',
				'en' => 'booking terms',
				'nl' => 'boekingsvoorwaarden',
				'fr' => 'conditions de réservation',
				'lb' => 'Buchungsbedingungen',
				'fi' => 'varausehdot',
				'ja' => '予約条件',
			),
			'privacy_label'     => array(
				'de' => 'Ich akzeptiere die {link}.',
				'en' => 'I accept the {link}.',
				'nl' => 'Ik ga akkoord met de {link}.',
				'fr' => 'J’accepte la {link}.',
				'lb' => 'Ech akzeptéieren d’{link}.',
				'fi' => 'Hyväksyn {link}.',
				'ja' => '{link}に同意します。',
			),
			'privacy_link_text' => array(
				'de' => 'Datenschutzerklärung',
				'en' => 'privacy notice',
				'nl' => 'privacyverklaring',
				'fr' => 'politique de confidentialité',
				'lb' => 'Dateschutzerklärung',
				'fi' => 'tietosuojailmoituksen',
				'ja' => 'プライバシー通知',
			),
			'paypal_enabled'    => '0',
			'paypal_client_id'  => '',
			'paypal_secret'     => '',
			'paypal_mode'       => 'sandbox',
			'paypal_currency'   => 'EUR',
			'paypal_webhook_id' => '',
		);
	}

	public static function normalize_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$defaults = self::default_settings();
		return array(
			'terms_url'         => esc_url_raw( $settings['terms_url'] ?? $defaults['terms_url'] ),
			'privacy_url'       => esc_url_raw( $settings['privacy_url'] ?? $defaults['privacy_url'] ),
			'terms_label'       => self::normalize_language_texts( $settings['terms_label'] ?? array(), $defaults['terms_label'] ),
			'terms_link_text'   => self::normalize_language_texts( $settings['terms_link_text'] ?? array(), $defaults['terms_link_text'] ),
			'privacy_label'     => self::normalize_language_texts( $settings['privacy_label'] ?? array(), $defaults['privacy_label'] ),
			'privacy_link_text' => self::normalize_language_texts( $settings['privacy_link_text'] ?? array(), $defaults['privacy_link_text'] ),
			'paypal_enabled'    => ! empty( $settings['paypal_enabled'] ) ? '1' : '0',
			'paypal_client_id'  => sanitize_text_field( $settings['paypal_client_id'] ?? $defaults['paypal_client_id'] ),
			'paypal_secret'     => sanitize_text_field( $settings['paypal_secret'] ?? $defaults['paypal_secret'] ),
			'paypal_mode'       => 'live' === sanitize_key( $settings['paypal_mode'] ?? $defaults['paypal_mode'] ) ? 'live' : 'sandbox',
			'paypal_currency'   => TAKA_Platform_Data::normalize_event_option_value( 'currency', $settings['paypal_currency'] ?? $defaults['paypal_currency'] ) ?: 'EUR',
			'paypal_webhook_id' => sanitize_text_field( $settings['paypal_webhook_id'] ?? $defaults['paypal_webhook_id'] ),
		);
	}

	public static function ticketing_settings() {
		$stored = get_option( self::SETTINGS_OPTION, array() );
		return self::normalize_settings( is_array( $stored ) ? $stored : array() );
	}

	public static function paypal_settings() {
		$settings = self::ticketing_settings();
		return self::normalize_paypal_settings(
			array(
				'enabled'    => $settings['paypal_enabled'] ?? '0',
				'client_id'  => $settings['paypal_client_id'] ?? '',
				'secret'     => $settings['paypal_secret'] ?? '',
				'mode'       => $settings['paypal_mode'] ?? 'sandbox',
				'currency'   => $settings['paypal_currency'] ?? 'EUR',
				'webhook_id' => $settings['paypal_webhook_id'] ?? '',
			)
		) + array(
			'account_scope' => 'global',
			'organizer_id'  => 0,
		);
	}

	public static function organizer_paypal_settings( $organizer_id ) {
		$stored = get_post_meta( absint( $organizer_id ), self::ORGANIZER_PAYPAL_META, true );
		return self::normalize_paypal_settings( is_array( $stored ) ? $stored : array() );
	}

	public static function paypal_settings_for_event( $event_id ) {
		$event_id = absint( $event_id );
		$organizer_id = self::event_billing_organizer_id( $event_id );
		if ( $organizer_id ) {
			$organizer = self::organizer_paypal_settings( $organizer_id );
			return $organizer + array(
				'account_scope' => 'organizer',
				'organizer_id'  => $organizer_id,
			);
		}
		return self::paypal_settings();
	}

	public static function paypal_settings_for_order( $order ) {
		$data = is_object( $order ) && method_exists( $order, 'to_array' ) ? $order->to_array() : (array) $order;
		$payment = is_array( $data['payment'] ?? null ) ? $data['payment'] : array();
		$organizer_id = absint( $payment['organizer_id'] ?? ( $data['organizer_id'] ?? 0 ) );
		if ( $organizer_id ) {
			$organizer = self::organizer_paypal_settings( $organizer_id );
			return $organizer + array(
				'account_scope' => 'organizer',
				'organizer_id'  => $organizer_id,
			);
		}
		return self::paypal_settings();
	}

	public static function paypal_webhook_settings_candidates() {
		$candidates = array();
		$global = self::paypal_settings();
		if ( self::payment_settings_have_values( $global, array( 'client_id', 'secret', 'webhook_id' ) ) ) {
			$candidates[] = $global;
		}
		$organizers = get_posts(
			array(
				'post_type'        => TAKA_PLATFORM_CPT_ORGANIZER,
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		foreach ( $organizers as $organizer_id ) {
			$settings = self::organizer_paypal_settings( $organizer_id );
			if ( ! self::payment_settings_have_values( $settings, array( 'client_id', 'secret', 'webhook_id' ) ) ) {
				continue;
			}
			$candidates[] = $settings + array(
				'account_scope' => 'organizer',
				'organizer_id'  => absint( $organizer_id ),
			);
		}
		return $candidates;
	}

	public static function event_billing_organizer_id( $event_id ) {
		$event_id = absint( $event_id );
		if ( ! $event_id ) {
			return 0;
		}
		$legacy = absint( get_post_meta( $event_id, '_taka_organizer_id', true ) );
		if ( $legacy ) {
			return $legacy;
		}
		$relationships = TAKA_Platform_Data::normalize_event_organizer_relationships( get_post_meta( $event_id, '_taka_event_organizers', true ), 0 );
		foreach ( $relationships as $relationship ) {
			$organizer_id = absint( $relationship['organizer_id'] ?? 0 );
			if ( $organizer_id ) {
				return $organizer_id;
			}
		}
		return 0;
	}

	public static function organizer_billing_snapshot( $organizer_id ) {
		$organizer_id = absint( $organizer_id );
		$post = $organizer_id ? get_post( $organizer_id ) : null;
		if ( ! $post || TAKA_PLATFORM_CPT_ORGANIZER !== $post->post_type ) {
			return array(
				'organizer_id'         => 0,
				'organizer_name'       => '',
				'organizer_legal_name' => '',
				'organizer_email'      => '',
			);
		}
		$emails = preg_split( '/\r\n|\r|\n/', (string) get_post_meta( $organizer_id, '_taka_emails', true ) );
		$email = sanitize_email( $emails[0] ?? '' );
		$legal_name = sanitize_text_field( get_post_meta( $organizer_id, '_taka_legal_name', true ) );
		return array(
			'organizer_id'         => $organizer_id,
			'organizer_name'       => get_the_title( $organizer_id ),
			'organizer_legal_name' => $legal_name,
			'organizer_billing_name' => '' !== $legal_name ? $legal_name : get_the_title( $organizer_id ),
			'organizer_address'    => sanitize_textarea_field( get_post_meta( $organizer_id, '_taka_billing_address', true ) ),
			'organizer_email'      => $email,
			'organizer_phone'      => sanitize_text_field( get_post_meta( $organizer_id, '_taka_phone', true ) ),
			'organizer_website'    => esc_url_raw( get_post_meta( $organizer_id, '_taka_website', true ) ),
			'organizer_tax_id'     => sanitize_text_field( get_post_meta( $organizer_id, '_taka_tax_id', true ) ),
		);
	}

	public static function billing_context_for_event( $event_id ) {
		$organizer_id = self::event_billing_organizer_id( $event_id );
		return self::organizer_billing_snapshot( $organizer_id ) + array(
			'event_id' => absint( $event_id ),
			'source'   => $organizer_id ? 'event_primary_organizer' : 'unassigned',
		);
	}

	private static function payment_settings_have_values( $settings, $fields ) {
		$settings = is_array( $settings ) ? $settings : array();
		foreach ( (array) $fields as $field ) {
			if ( '' !== trim( (string) ( $settings[ $field ] ?? '' ) ) ) {
				return true;
			}
		}
		return false;
	}

	private static function non_empty_settings( $settings ) {
		return array_filter(
			is_array( $settings ) ? $settings : array(),
			static function ( $value ) {
				return '' !== trim( (string) $value );
			}
		);
	}

	private static function normalize_language_texts( $values, $defaults ) {
		$values = is_array( $values ) ? $values : array();
		$out = array();
		foreach ( TAKA_Platform_Data::content_section_languages() as $lang ) {
			$value = sanitize_text_field( $values[ $lang ] ?? ( $defaults[ $lang ] ?? '' ) );
			$out[ $lang ] = '' !== trim( $value ) ? $value : sanitize_text_field( $defaults[ $lang ] ?? '' );
		}
		return $out;
	}

	private static function setting_text( $settings, $field, $lang = null ) {
		$lang = $lang ?: taka_tour_current_language();
		$values = is_array( $settings[ $field ] ?? null ) ? $settings[ $field ] : array();
		return TAKA_Platform_Data::resolve_dynamic_text( $values, $lang, TAKA_Platform_Data::platform_fallback_language() );
	}

	public static function text( $key, $fallback, $lang = null ) {
		return taka_tour_translate( $key, $fallback, $lang );
	}

	public static function event_collects_dietary_preferences( $event_id ) {
		return '1' === (string) get_post_meta( absint( $event_id ), self::DIETARY_PREFERENCES_META, true );
	}

	public static function organizer_invoice_missing_fields( $billing ) {
		$billing = is_array( $billing ) ? $billing : array();
		$missing = array();
		if ( '' === trim( (string) ( $billing['organizer_billing_name'] ?? $billing['organizer_legal_name'] ?? $billing['organizer_name'] ?? '' ) ) ) {
			$missing[] = __( 'Billing name', 'taka-platform' );
		}
		if ( '' === trim( (string) ( $billing['organizer_address'] ?? '' ) ) ) {
			$missing[] = __( 'Billing address', 'taka-platform' );
		}
		if ( '' === trim( (string) ( $billing['organizer_email'] ?? '' ) ) ) {
			$missing[] = __( 'Billing email', 'taka-platform' );
		}
		return $missing;
	}

	public static function event_ticket_details( $event_id, $lang = null ) {
		$event_id = absint( $event_id );
		$lang = $lang ?: taka_tour_current_language();
		$details = array(
			'event_id'      => $event_id,
			'event_title'   => $event_id ? get_the_title( $event_id ) : '',
			'date'          => '',
			'start_time'    => '',
			'end_time'      => '',
			'doors_open'    => $event_id ? sanitize_text_field( get_post_meta( $event_id, '_taka_doors_open', true ) ) : '',
			'venue_name'    => '',
			'venue_address' => '',
			'room'          => $event_id ? sanitize_text_field( get_post_meta( $event_id, '_taka_ticket_location_detail', true ) ) : '',
			'schedule'      => array(),
		);
		if ( ! $event_id ) {
			return $details;
		}

		$program_items = TAKA_Platform_Data::normalize_program_items(
			get_post_meta( $event_id, '_taka_program_items', true ),
			array(
				'date_start' => get_post_meta( $event_id, '_taka_date_start', true ),
				'time_start' => get_post_meta( $event_id, '_taka_time_start', true ),
				'time_end'   => get_post_meta( $event_id, '_taka_time_end', true ),
			)
		);
		$program_items = array_values( $program_items );
		if ( ! empty( $program_items ) ) {
			usort( $program_items, array( 'TAKA_Platform_Data', 'compare_program_items' ) );
			$first = $program_items[0];
			$last = $program_items[ count( $program_items ) - 1 ];
			$details['date'] = sanitize_text_field( $first['date'] ?? '' );
			$details['start_time'] = sanitize_text_field( $first['time_start'] ?? '' );
			$details['end_time'] = sanitize_text_field( $last['time_end'] ?? ( $last['time_start'] ?? '' ) );
			foreach ( $program_items as $item ) {
				$details['schedule'][] = array(
					'date'       => sanitize_text_field( $item['date'] ?? '' ),
					'time_start' => sanitize_text_field( $item['time_start'] ?? '' ),
					'time_end'   => sanitize_text_field( $item['time_end'] ?? '' ),
					'title'      => sanitize_text_field( $item['title'] ?? '' ),
				);
			}
		}

		$venue_id = absint( get_post_meta( $event_id, '_taka_venue_id', true ) );
		if ( $venue_id ) {
			$details['venue_name'] = get_the_title( $venue_id );
			$country = get_post_meta( $venue_id, '_taka_country', true );
			$details['venue_address'] = trim(
				implode(
					', ',
					array_filter(
						array(
							sanitize_text_field( get_post_meta( $venue_id, '_taka_street', true ) ),
							trim( sanitize_text_field( get_post_meta( $venue_id, '_taka_postal_code', true ) ) . ' ' . sanitize_text_field( get_post_meta( $venue_id, '_taka_city', true ) ) ),
							TAKA_Platform_Data::country_label( $country, $lang ),
						)
					)
				)
			);
		}
		return $details;
	}

	/** Save the shared native ticket type config when the Event editor posted it. */
	public static function save_event_ticket_types( $post_id ) {
		if ( ! isset( $_POST['taka_native_ticket_types'] ) ) {
			return;
		}

		$ticket_types = self::sanitize_ticket_types( wp_unslash( $_POST['taka_native_ticket_types'] ) );
		if ( empty( $ticket_types ) ) {
			delete_post_meta( $post_id, TAKA_Ticketing_Ticket_Types::META_KEY );
			self::save_event_payment_settings( $post_id );
			return;
		}

		update_post_meta( $post_id, TAKA_Ticketing_Ticket_Types::META_KEY, $ticket_types );
		self::save_event_payment_settings( $post_id );
	}

	private static function save_event_payment_settings( $post_id ) {
		$methods = array();
		foreach ( (array) wp_unslash( $_POST['taka_native_payment_methods'] ?? array() ) as $method ) {
			$method = sanitize_key( $method );
			if ( isset( self::$payment_providers[ $method ] ) ) {
				$methods[] = $method;
			}
		}
		update_post_meta( $post_id, self::PAYMENT_METHODS_META, array_values( array_unique( $methods ) ) );
		update_post_meta( $post_id, self::PAYMENT_METHODS_CONFIGURED_META, '1' );
		update_post_meta( $post_id, self::DIETARY_PREFERENCES_META, ! empty( $_POST['taka_native_dietary_preferences_enabled'] ) ? '1' : '0' );

		$bank_settings = self::normalize_bank_transfer_settings( wp_unslash( $_POST['taka_native_bank_transfer'] ?? array() ) );
		update_post_meta( $post_id, self::BANK_TRANSFER_META, $bank_settings );
		update_post_meta( $post_id, self::PAY_AT_DOOR_INSTRUCTIONS_META, sanitize_textarea_field( wp_unslash( $_POST['taka_native_pay_at_door_instructions'] ?? '' ) ) );
	}

	/** Render the native ticket type and payment editor on Event edit screens. */
	public static function render_event_ticket_types_section( $post_id ) {
		$post_id = absint( $post_id );
		$mode = TAKA_Platform_Data::ticket_mode_for_event(
			array(
				'ticket_mode'      => get_post_meta( $post_id, '_taka_ticket_mode', true ),
				'ticket_status'    => get_post_meta( $post_id, '_taka_ticket_status', true ),
				'ticket_provider'  => get_post_meta( $post_id, '_taka_ticket_provider', true ),
				'ticket_shop_url'  => get_post_meta( $post_id, '_taka_ticket_shop_url', true ),
			)
		);
		$is_native = self::MODE === $mode;
		$ticket_types = self::ticket_types_for_event( $post_id );

		TAKA_Platform_Admin_Collapsible_Section::open(
			array(
				'id'            => 'event-native-ticketing',
				'title'         => __( 'Native TAKA Ticketing', 'taka-platform' ),
				'help_text'     => __( 'Native checkout configuration for ticket types, payment methods and per-event payment instructions.', 'taka-platform' ),
				'default_state' => $is_native ? TAKA_Platform_Admin_Collapsible_Section::STATE_EXPANDED : TAKA_Platform_Admin_Collapsible_Section::STATE_COLLAPSED,
				'class'         => 'taka-admin-section--advanced',
				'attributes'    => array( 'id' => 'taka-native-ticketing-section' ),
			)
		);

		if ( ! $is_native ) {
			echo '<p class="description">' . esc_html__( 'Select Native TAKA Ticketing as the ticket mode to use these ticket types later.', 'taka-platform' ) . '</p>';
		}

		echo '<p class="description">' . esc_html__( 'Configure one or more ticket types for this event. Native checkout uses these prices and reserves capacity immediately after registration.', 'taka-platform' ) . '</p>';
		self::render_ticket_type_rows( $ticket_types, (string) get_post_meta( $post_id, '_taka_currency', true ) );
		self::render_payment_method_settings( $post_id );
		self::render_event_product_summary( $post_id );
		if ( class_exists( 'TAKA_Event_Operations_Module' ) ) {
			echo '<div class="taka-native-payment-settings"><h3>' . esc_html__( 'Event-day operations', 'taka-platform' ) . '</h3>';
			echo '<p class="description">' . esc_html__( 'Open the private operations center for registrations, payments, walk-ins and check-in.', 'taka-platform' ) . '</p>';
			echo TAKA_Event_Operations_Module::render_event_link( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div>';
		}
		TAKA_Platform_Admin_Collapsible_Section::close();
	}

	private static function render_ticket_type_rows( $ticket_types, $event_currency ) {
		$rows = array_values( is_array( $ticket_types ) ? $ticket_types : array() );
		$blank_count = empty( $rows ) ? 2 : 1;
		for ( $i = 0; $i < $blank_count; $i++ ) {
			$rows[] = array();
		}

		echo '<div class="taka-native-ticket-types">';
		foreach ( $rows as $index => $ticket_type ) {
			self::render_ticket_type_row( $index, $ticket_type, $event_currency );
		}
		echo '</div>';
	}

	private static function render_ticket_type_row( $index, $ticket_type, $event_currency ) {
		$ticket_type = is_array( $ticket_type ) ? $ticket_type : array();
		$name = (string) ( $ticket_type['name'] ?? '' );
		$prefix = 'taka_native_ticket_types[' . absint( $index ) . ']';
		$currency = (string) ( $ticket_type['currency'] ?? $event_currency );
		$currency = '' !== $currency ? $currency : 'EUR';
		$title = '' !== $name ? $name : __( 'New ticket type', 'taka-platform' );
		?>
		<div class="taka-native-ticket-type">
			<div class="taka-native-ticket-type__header">
				<strong><?php echo esc_html( $title ); ?></strong>
				<label><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[remove]" value="1"> <?php echo esc_html__( 'Remove', 'taka-platform' ); ?></label>
			</div>
			<div class="taka-native-ticket-type__grid">
				<?php self::input( $prefix, 'id', __( 'Internal ID', 'taka-platform' ), $ticket_type['id'] ?? '', 'text' ); ?>
				<?php self::input( $prefix, 'name', __( 'Name', 'taka-platform' ), $name, 'text' ); ?>
				<?php self::textarea( $prefix, 'description', __( 'Description', 'taka-platform' ), $ticket_type['description'] ?? '' ); ?>
				<?php self::input( $prefix, 'price', __( 'Price', 'taka-platform' ), $ticket_type['price'] ?? '', 'text' ); ?>
				<?php self::currency_select( $prefix, $currency ); ?>
				<?php self::input( $prefix, 'capacity', __( 'Quantity / capacity', 'taka-platform' ), $ticket_type['capacity'] ?? '', 'number' ); ?>
				<?php self::input( $prefix, 'sale_start_date', __( 'Sale start date', 'taka-platform' ), $ticket_type['sale_start_date'] ?? '', 'date' ); ?>
				<?php self::input( $prefix, 'sale_start_time', __( 'Sale start time', 'taka-platform' ), $ticket_type['sale_start_time'] ?? '', 'time' ); ?>
				<?php self::input( $prefix, 'sale_end_date', __( 'Sale end date', 'taka-platform' ), $ticket_type['sale_end_date'] ?? '', 'date' ); ?>
				<?php self::input( $prefix, 'sale_end_time', __( 'Sale end time', 'taka-platform' ), $ticket_type['sale_end_time'] ?? '', 'time' ); ?>
				<?php self::status_select( $prefix, $ticket_type['status'] ?? 'active' ); ?>
				<?php self::input( $prefix, 'sort_order', __( 'Sort order', 'taka-platform' ), $ticket_type['sort_order'] ?? '', 'number' ); ?>
			</div>
		</div>
		<?php
	}

	private static function input( $prefix, $field, $label, $value, $type ) {
		echo '<label><strong>' . esc_html( $label ) . '</strong><input class="widefat" type="' . esc_attr( $type ) . '" name="' . esc_attr( $prefix . '[' . $field . ']' ) . '" value="' . esc_attr( (string) $value ) . '"></label>';
	}

	private static function textarea( $prefix, $field, $label, $value ) {
		echo '<label class="taka-native-ticket-type__wide"><strong>' . esc_html( $label ) . '</strong><textarea class="widefat" rows="2" name="' . esc_attr( $prefix . '[' . $field . ']' ) . '">' . esc_textarea( (string) $value ) . '</textarea></label>';
	}

	private static function currency_select( $prefix, $current ) {
		$choices = TAKA_Platform_Data::option_list_choices( 'currency', TAKA_Platform_Data::platform_fallback_language() );
		if ( ! isset( $choices[ $current ] ) ) {
			$choices[ $current ] = $current;
		}
		echo '<label><strong>' . esc_html__( 'Currency', 'taka-platform' ) . '</strong><select class="widefat" name="' . esc_attr( $prefix . '[currency]' ) . '">';
		foreach ( $choices as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( (string) $current, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label>';
	}

	private static function status_select( $prefix, $current ) {
		echo '<label><strong>' . esc_html__( 'Status', 'taka-platform' ) . '</strong><select class="widefat" name="' . esc_attr( $prefix . '[status]' ) . '">';
		foreach ( TAKA_Ticketing_Ticket_Types::statuses() as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( (string) $current, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label>';
	}

	private static function render_payment_method_settings( $post_id ) {
		$enabled = self::enabled_payment_methods_for_event( $post_id, false );
		$bank = self::event_bank_transfer_settings( $post_id );
		$effective_bank = self::bank_transfer_settings_for_event( $post_id );
		$billing = self::billing_context_for_event( $post_id );
		$pay_at_door_instructions = (string) get_post_meta( $post_id, self::PAY_AT_DOOR_INSTRUCTIONS_META, true );
		?>
		<div class="taka-native-payment-settings">
			<h3><?php echo esc_html__( 'Payment methods', 'taka-platform' ); ?></h3>
			<p class="description"><?php echo esc_html__( 'Choose which native payment methods visitors may select for this event.', 'taka-platform' ); ?></p>
			<?php if ( ! empty( $billing['organizer_id'] ) ) : ?>
				<p class="description"><?php echo esc_html( sprintf( __( 'Payments are processed in the name of the event organizer: %s.', 'taka-platform' ), $billing['organizer_legal_name'] ?: $billing['organizer_name'] ) ); ?></p>
				<?php $missing_invoice_fields = self::organizer_invoice_missing_fields( $billing ); ?>
				<?php if ( ! empty( $missing_invoice_fields ) ) : ?>
					<p class="description notice notice-warning inline"><?php echo esc_html( sprintf( __( 'Organizer invoice data is incomplete: %s.', 'taka-platform' ), implode( ', ', $missing_invoice_fields ) ) ); ?></p>
				<?php endif; ?>
			<?php else : ?>
				<p class="description"><?php echo esc_html__( 'Assign a primary organizer so native payments can use organizer-specific financial accounts.', 'taka-platform' ); ?></p>
			<?php endif; ?>
			<div class="taka-native-payment-settings__methods">
				<?php foreach ( self::payment_providers() as $provider_id => $provider ) : ?>
					<label><input type="checkbox" name="taka_native_payment_methods[]" value="<?php echo esc_attr( $provider_id ); ?>" <?php checked( in_array( $provider_id, $enabled, true ) ); ?>> <?php echo esc_html( self::payment_method_label( $provider_id ) ); ?><?php if ( ! self::payment_provider_enabled_for_event( $provider_id, $post_id ) ) : ?> <span class="description"><?php echo esc_html__( 'Configure the organizer account before public checkout uses it.', 'taka-platform' ); ?></span><?php endif; ?></label>
				<?php endforeach; ?>
			</div>
			<div class="taka-native-payment-settings__grid">
				<label class="taka-native-ticket-type__wide"><strong><?php echo esc_html__( 'Bank transfer account source', 'taka-platform' ); ?></strong><input class="widefat" type="text" readonly value="<?php echo esc_attr( self::bank_account_source_label( $effective_bank ) ); ?>"><span class="description"><?php echo esc_html__( 'Use the organizer finance profile for normal operation. Fill the fields below only for a deliberate event-specific override.', 'taka-platform' ); ?></span></label>
				<?php self::payment_input( 'taka_native_bank_transfer', 'account_holder', __( 'Account holder', 'taka-platform' ), $bank['account_holder'] ?? '' ); ?>
				<?php self::payment_input( 'taka_native_bank_transfer', 'iban', __( 'IBAN', 'taka-platform' ), $bank['iban'] ?? '' ); ?>
				<?php self::payment_input( 'taka_native_bank_transfer', 'bic', __( 'BIC', 'taka-platform' ), $bank['bic'] ?? '' ); ?>
				<?php self::payment_input( 'taka_native_bank_transfer', 'bank_name', __( 'Bank name', 'taka-platform' ), $bank['bank_name'] ?? '' ); ?>
				<?php self::payment_input( 'taka_native_bank_transfer', 'payment_reference_template', __( 'Payment reference template', 'taka-platform' ), $bank['payment_reference_template'] ?? 'TAKA-{order_number}' ); ?>
				<?php self::payment_input( 'taka_native_bank_transfer', 'payment_due_days', __( 'Payment due after days', 'taka-platform' ), $bank['payment_due_days'] ?? '' ); ?>
				<label class="taka-native-ticket-type__wide"><strong><?php echo esc_html__( 'Bank transfer instructions', 'taka-platform' ); ?></strong><textarea class="widefat" rows="3" name="taka_native_bank_transfer[instructions_text]"><?php echo esc_textarea( $bank['instructions_text'] ?? '' ); ?></textarea></label>
				<label class="taka-native-ticket-type__wide"><strong><?php echo esc_html__( 'Pay-at-the-door instructions', 'taka-platform' ); ?></strong><textarea class="widefat" rows="3" name="taka_native_pay_at_door_instructions"><?php echo esc_textarea( $pay_at_door_instructions ); ?></textarea><span class="description"><?php echo esc_html__( 'Optional event-specific note, for example cash only or card accepted.', 'taka-platform' ); ?></span></label>
				<label class="taka-native-ticket-type__wide"><strong><?php echo esc_html__( 'Checkout participant options', 'taka-platform' ); ?></strong><br><input type="checkbox" name="taka_native_dietary_preferences_enabled" value="1" <?php checked( self::event_collects_dietary_preferences( $post_id ) ); ?>> <?php echo esc_html__( 'Ask for dietary preferences and allergies during checkout', 'taka-platform' ); ?><span class="description"><?php echo esc_html__( 'Disabled by default for normal seminars. Enable it for parties, meals or add-on events where this information is useful.', 'taka-platform' ); ?></span></label>
			</div>
		</div>
		<?php
	}

	private static function render_event_product_summary( $post_id ) {
		$products = self::product_repository()->checkout_add_ons_for_event( $post_id );
		?>
		<div class="taka-native-payment-settings">
			<h3><?php echo esc_html__( 'Checkout add-ons', 'taka-platform' ); ?></h3>
			<p class="description"><?php echo esc_html__( 'Attach optional products such as dinner, dojo party participation or merch to this event from Ticketing -> Products.', 'taka-platform' ); ?></p>
			<?php if ( empty( $products ) ) : ?>
				<p><?php echo esc_html__( 'No add-on products are attached to this event yet.', 'taka-platform' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $products as $product ) : ?>
						<li><strong><?php echo esc_html( $product['title'] ); ?></strong> - <?php echo esc_html( self::format_money( $product['price'], $product['currency'] ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<p><a class="button" href="<?php echo esc_url( self::admin_url( array( 'section' => 'products' ) ) ); ?>"><?php echo esc_html__( 'Manage ticketing products', 'taka-platform' ); ?></a></p>
		</div>
		<?php
	}

	private static function payment_input( $prefix, $field, $label, $value ) {
		echo '<label><strong>' . esc_html( $label ) . '</strong><input class="widefat" type="text" name="' . esc_attr( $prefix . '[' . $field . ']' ) . '" value="' . esc_attr( (string) $value ) . '"></label>';
	}

	private static function bank_account_source_label( $settings ) {
		$scope = sanitize_key( $settings['account_scope'] ?? 'global' );
		if ( 'event' === $scope ) {
			return __( 'Event-specific override', 'taka-platform' );
		}
		if ( 'organizer' === $scope ) {
			$organizer_id = absint( $settings['organizer_id'] ?? 0 );
			return $organizer_id ? sprintf( __( 'Organizer account: %s', 'taka-platform' ), get_the_title( $organizer_id ) ) : __( 'Organizer account', 'taka-platform' );
		}
		return __( 'Legacy global fallback', 'taka-platform' );
	}

	public static function render_organizer_financial_settings( $organizer_id ) {
		$organizer_id = absint( $organizer_id );
		$bank = self::organizer_bank_transfer_settings( $organizer_id );
		$paypal = self::organizer_paypal_settings( $organizer_id );
		$currencies = TAKA_Platform_Data::option_list_choices( 'currency', TAKA_Platform_Data::platform_fallback_language() );
		if ( ! isset( $currencies[ $paypal['currency'] ?? 'EUR' ] ) ) {
			$currencies[ $paypal['currency'] ?? 'EUR' ] = $paypal['currency'] ?? 'EUR';
		}
		?>
		<div class="taka-native-payment-settings">
			<p class="description"><?php echo esc_html__( 'Private finance profile for native ticketing. Orders for events whose primary organizer is this organizer use these accounts and keep a billing snapshot on the order.', 'taka-platform' ); ?></p>
			<h3><?php echo esc_html__( 'Bank account', 'taka-platform' ); ?></h3>
			<div class="taka-native-payment-settings__grid">
				<label><strong><?php echo esc_html__( 'Enable bank transfer account', 'taka-platform' ); ?></strong><input type="checkbox" name="taka_organizer_bank_transfer[enabled]" value="1" <?php checked( '1', (string) ( $bank['enabled'] ?? '0' ) ); ?>></label>
				<?php self::payment_input( 'taka_organizer_bank_transfer', 'account_holder', __( 'Account holder', 'taka-platform' ), $bank['account_holder'] ?? '' ); ?>
				<?php self::payment_input( 'taka_organizer_bank_transfer', 'iban', __( 'IBAN', 'taka-platform' ), $bank['iban'] ?? '' ); ?>
				<?php self::payment_input( 'taka_organizer_bank_transfer', 'bic', __( 'BIC', 'taka-platform' ), $bank['bic'] ?? '' ); ?>
				<?php self::payment_input( 'taka_organizer_bank_transfer', 'bank_name', __( 'Bank name', 'taka-platform' ), $bank['bank_name'] ?? '' ); ?>
				<?php self::payment_input( 'taka_organizer_bank_transfer', 'payment_reference_template', __( 'Payment reference template', 'taka-platform' ), $bank['payment_reference_template'] ?? 'TAKA-{order_number}' ); ?>
				<?php self::payment_input( 'taka_organizer_bank_transfer', 'payment_due_days', __( 'Payment due after days', 'taka-platform' ), $bank['payment_due_days'] ?? '' ); ?>
				<label class="taka-native-ticket-type__wide"><strong><?php echo esc_html__( 'Bank transfer instructions', 'taka-platform' ); ?></strong><textarea class="widefat" rows="3" name="taka_organizer_bank_transfer[instructions_text]"><?php echo esc_textarea( $bank['instructions_text'] ?? '' ); ?></textarea></label>
			</div>
			<h3><?php echo esc_html__( 'PayPal account', 'taka-platform' ); ?></h3>
			<div class="taka-native-payment-settings__grid">
				<label><strong><?php echo esc_html__( 'Enable PayPal checkout', 'taka-platform' ); ?></strong><input type="checkbox" name="taka_organizer_paypal[enabled]" value="1" <?php checked( '1', (string) ( $paypal['enabled'] ?? '0' ) ); ?>></label>
				<label><strong><?php echo esc_html__( 'PayPal client ID', 'taka-platform' ); ?></strong><input class="widefat" type="text" name="taka_organizer_paypal[client_id]" value="<?php echo esc_attr( $paypal['client_id'] ?? '' ); ?>"></label>
				<label><strong><?php echo esc_html__( 'PayPal secret', 'taka-platform' ); ?></strong><input class="widefat" type="password" autocomplete="new-password" name="taka_organizer_paypal[secret]" value="<?php echo esc_attr( $paypal['secret'] ?? '' ); ?>"></label>
				<label><strong><?php echo esc_html__( 'Mode', 'taka-platform' ); ?></strong><select class="widefat" name="taka_organizer_paypal[mode]"><option value="sandbox" <?php selected( 'sandbox', $paypal['mode'] ?? 'sandbox' ); ?>><?php echo esc_html__( 'Sandbox', 'taka-platform' ); ?></option><option value="live" <?php selected( 'live', $paypal['mode'] ?? 'sandbox' ); ?>><?php echo esc_html__( 'Live', 'taka-platform' ); ?></option></select></label>
				<label><strong><?php echo esc_html__( 'Currency', 'taka-platform' ); ?></strong><select class="widefat" name="taka_organizer_paypal[currency]"><?php foreach ( $currencies as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) ( $paypal['currency'] ?? 'EUR' ), (string) $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
				<label><strong><?php echo esc_html__( 'Webhook ID', 'taka-platform' ); ?></strong><input class="widefat" type="text" name="taka_organizer_paypal[webhook_id]" value="<?php echo esc_attr( $paypal['webhook_id'] ?? '' ); ?>"></label>
				<label class="taka-native-ticket-type__wide"><strong><?php echo esc_html__( 'Webhook URL', 'taka-platform' ); ?></strong><input class="widefat" type="text" readonly value="<?php echo esc_attr( self::paypal_webhook_url() ); ?>"><span class="description"><?php echo esc_html__( 'Use the same endpoint when configuring this organizer account in PayPal.', 'taka-platform' ); ?></span></label>
			</div>
		</div>
		<?php
	}

	public static function save_organizer_financial_settings( $organizer_id ) {
		$organizer_id = absint( $organizer_id );
		if ( ! $organizer_id || ! current_user_can( 'edit_post', $organizer_id ) ) {
			return;
		}
		if ( isset( $_POST['taka_organizer_bank_transfer'] ) && is_array( $_POST['taka_organizer_bank_transfer'] ) ) {
			update_post_meta( $organizer_id, self::ORGANIZER_BANK_TRANSFER_META, self::normalize_bank_transfer_settings( wp_unslash( $_POST['taka_organizer_bank_transfer'] ) ) );
		}
		if ( isset( $_POST['taka_organizer_paypal'] ) && is_array( $_POST['taka_organizer_paypal'] ) ) {
			update_post_meta( $organizer_id, self::ORGANIZER_PAYPAL_META, self::normalize_paypal_settings( wp_unslash( $_POST['taka_organizer_paypal'] ) ) );
		}
	}

	public static function render_booking_widget( $event ) {
		$event = is_array( $event ) ? $event : array();
		$event_id = absint( $event['wp_post_id'] ?? 0 );
		if ( ! $event_id || ! self::event_uses_native_ticketing( $event ) ) {
			return '';
		}

		$order = self::order_from_request_for_event( $event_id );
		ob_start();
		echo '<div class="taka-native-checkout" data-taka-native-checkout>';
		if ( $order && self::payment_cancelled_from_request() ) {
			self::render_checkout_form( $event, $event_id, $order );
		} elseif ( $order ) {
			self::render_order_confirmation( $order );
		} else {
			self::render_checkout_form( $event, $event_id );
		}
		echo '</div>';
		return ob_get_clean();
	}

	public static function product_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => '', 'product_id' => '' ), (array) $atts, 'taka_ticketing_product' );
		$product_id = TAKA_Ticketing_Product::normalize_product_id( $atts['id'] ?: $atts['product_id'] );
		$product = self::product_repository()->find_by_product_id( $product_id );
		if ( ! $product || '1' !== (string) ( $product['can_purchase_standalone'] ?? '0' ) ) {
			return '';
		}

		$order = self::order_from_request_for_product( $product['product_id'] );
		ob_start();
		echo '<div class="taka-native-checkout" data-taka-native-checkout>';
		if ( $order && self::payment_cancelled_from_request() ) {
			self::render_standalone_product_form( $product, $order );
		} elseif ( $order ) {
			self::render_order_confirmation( $order );
		} else {
			self::render_standalone_product_form( $product );
		}
		echo '</div>';
		return ob_get_clean();
	}

	private static function checkout_prefill_from_order( $order = null ) {
		$prefill = array(
			'ticket_type_id'      => '',
			'ticket_quantity'     => 1,
			'payment_method'      => '',
			'promotion_code'      => '',
			'buyer'               => array(),
			'participant'         => array(),
			'participants'        => array(),
			'participant_is_buyer' => true,
			'product_quantities'  => array(),
			'standalone_product_quantity' => 1,
		);
		if ( ! $order instanceof TAKA_Ticketing_Order ) {
			return $prefill;
		}

		$data = $order->to_array();
		$buyer = is_array( $data['buyer'] ?? null ) ? $data['buyer'] : array();
		$participant = is_array( $data['participant'] ?? null ) ? $data['participant'] : array();
		$participants = is_array( $data['participants'] ?? null ) && ! empty( $data['participants'] ) ? array_values( $data['participants'] ) : array( $participant );
		$ticket_quantity = 1;
		$product_quantities = array();
		foreach ( (array) ( $data['line_items'] ?? array() ) as $item ) {
			if ( 'ticket' === (string) ( $item['item_type'] ?? '' ) ) {
				$ticket_quantity = max( 1, absint( $item['quantity'] ?? 1 ) );
				continue;
			}
			if ( 'product' !== (string) ( $item['item_type'] ?? '' ) ) {
				continue;
			}
			$product_id = TAKA_Ticketing_Product::normalize_product_id( $item['product_id'] ?? '' );
			if ( '' === $product_id ) {
				continue;
			}
			$product_quantities[ $product_id ] = absint( $product_quantities[ $product_id ] ?? 0 ) + max( 1, absint( $item['quantity'] ?? 1 ) );
		}

		$payment_method = (string) ( $data['payment_method'] ?? '' );
		if ( in_array( $payment_method, array( 'free', 'promotion' ), true ) ) {
			$payment_method = '';
		}

		return array(
			'ticket_type_id'      => (string) ( $data['ticket_type_id'] ?? '' ),
			'ticket_quantity'     => $ticket_quantity,
			'payment_method'      => $payment_method,
			'promotion_code'      => (string) ( $data['applied_voucher_code'] ?? '' ),
			'buyer'               => $buyer,
			'participant'         => $participant,
			'participants'        => $participants,
			'participant_is_buyer' => self::prefill_participant_is_buyer( $buyer, $participant ),
			'product_quantities'  => $product_quantities,
			'standalone_product_quantity' => max( 1, absint( reset( $product_quantities ) ?: 1 ) ),
		);
	}

	private static function prefill_participant_is_buyer( $buyer, $participant ) {
		foreach ( array( 'first_name', 'last_name', 'email', 'country' ) as $field ) {
			if ( 0 !== strcasecmp( trim( (string) ( $buyer[ $field ] ?? '' ) ), trim( (string) ( $participant[ $field ] ?? '' ) ) ) ) {
				return false;
			}
		}
		return true;
	}

	private static function render_standalone_product_form( $product, $prefill_order = null ) {
		$product = TAKA_Ticketing_Product::normalize( $product );
		$lang = taka_tour_current_language();
		$settings = self::ticketing_settings();
		$prefill = self::checkout_prefill_from_order( $prefill_order );
		$country_choices = array( '' => self::text( 'ticketing.select_country', 'Select country', $lang ) ) + TAKA_Platform_Data::country_choices( $lang );
		$payment_methods = ! empty( $product['related_event_id'] ) ? self::enabled_payment_methods_for_event( absint( $product['related_event_id'] ) ) : array_keys( array_filter( self::payment_providers(), static function ( $provider ) { return $provider->is_enabled(); } ) );
		$availability = self::product_repository()->availability( $product );
		$errors = self::checkout_errors_from_request();
		if ( empty( $availability['available'] ) || empty( $payment_methods ) ) {
			echo '<div class="taka-ticket-status taka-ticket-status--boxed"><strong>' . esc_html( self::text( 'ticketing.not_available', 'Native ticket booking is not available yet.', $lang ) ) . '</strong></div>';
			return;
		}
		$form_id = 'taka-product-checkout-form-' . esc_attr( $product['product_id'] );
		$max_quantity = self::max_product_quantity_for_checkout( $product, $availability );
		?>
		<form id="<?php echo esc_attr( $form_id ); ?>" class="taka-native-checkout__form is-open" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" data-taka-checkout-form data-taka-initial-step="<?php echo esc_attr( ! empty( $errors ) ? '3' : '1' ); ?>" data-taka-error-required="<?php echo esc_attr( self::text( 'ticketing.error_required_step', 'Please complete the required fields before continuing.', $lang ) ); ?>" data-taka-error-terms="<?php echo esc_attr( self::text( 'ticketing.error_terms', 'Please accept the terms and privacy notice.', $lang ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::CHECKOUT_ACTION ); ?>">
			<input type="hidden" name="standalone_product_id" value="<?php echo esc_attr( $product['product_id'] ); ?>">
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( self::current_url() ); ?>">
			<input type="hidden" name="language" value="<?php echo esc_attr( $lang ); ?>">
			<input type="hidden" name="taka_ticketing_nonce" value="<?php echo esc_attr( wp_create_nonce( self::CHECKOUT_ACTION ) ); ?>">
			<label class="taka-native-checkout__honeypot"><?php echo esc_html( self::text( 'ticketing.website', 'Website', $lang ) ); ?><input type="text" name="company_website" value="" tabindex="-1" autocomplete="off"></label>
			<div class="taka-native-checkout__errors" role="alert" data-taka-checkout-errors <?php echo empty( $errors ) ? 'hidden' : ''; ?>><?php foreach ( $errors as $error ) : ?><p><?php echo esc_html( $error ); ?></p><?php endforeach; ?></div>
			<?php self::render_checkout_progress( ! empty( $errors ) ? 3 : 1, $lang, true ); ?>
			<section class="taka-native-checkout__step" data-taka-checkout-step-panel="1">
				<h4><?php echo esc_html( $product['title'] ); ?></h4>
				<?php if ( '' !== trim( (string) $product['description'] ) ) : ?><p><?php echo esc_html( $product['description'] ); ?></p><?php endif; ?>
				<p><strong><?php echo esc_html( self::format_money( $product['price'], $product['currency'] ) ); ?></strong></p>
				<?php if ( $max_quantity > 1 ) : ?>
					<label><span><?php echo esc_html( self::text( 'ticketing.quantity', 'Quantity', $lang ) ); ?></span><input type="number" min="1" max="<?php echo esc_attr( (string) $max_quantity ); ?>" name="standalone_product_quantity" value="<?php echo esc_attr( (string) min( $max_quantity, max( 1, absint( $prefill['standalone_product_quantity'] ?? 1 ) ) ) ); ?>"></label>
				<?php else : ?>
					<input type="hidden" name="standalone_product_quantity" value="1">
				<?php endif; ?>
			</section>
			<section class="taka-native-checkout__step" data-taka-checkout-step-panel="2">
				<h4><?php echo esc_html( self::text( 'ticketing.buyer_information', 'Buyer information', $lang ) ); ?></h4>
				<div class="taka-native-checkout__grid">
					<?php self::frontend_input( 'buyer_first_name', self::text( 'ticketing.first_name', 'First name', $lang ), 'text', true, $prefill['buyer']['first_name'] ?? '', array( 'autocomplete' => 'given-name' ) ); ?>
					<?php self::frontend_input( 'buyer_last_name', self::text( 'ticketing.last_name', 'Last name', $lang ), 'text', true, $prefill['buyer']['last_name'] ?? '', array( 'autocomplete' => 'family-name' ) ); ?>
					<?php self::frontend_input( 'buyer_email', self::text( 'ticketing.email', 'Email', $lang ), 'email', true, $prefill['buyer']['email'] ?? '', array( 'autocomplete' => 'email' ) ); ?>
					<?php self::frontend_select( 'buyer_country', self::text( 'ticketing.country', 'Country', $lang ), $country_choices, true, array( 'autocomplete' => 'country-name', 'data-taka-country-select' => '1' ), $prefill['buyer']['country'] ?? '' ); ?>
					<?php self::frontend_input( 'buyer_phone', self::text( 'ticketing.phone', 'Phone', $lang ), 'text', false, $prefill['buyer']['phone'] ?? '', array( 'autocomplete' => 'tel' ) ); ?>
				</div>
			</section>
			<section class="taka-native-checkout__step" data-taka-payment-section data-taka-checkout-step-panel="3">
				<h4><?php echo esc_html( self::text( 'ticketing.payment_method', 'Payment method', $lang ) ); ?></h4>
				<div class="taka-native-payment-options">
					<?php foreach ( $payment_methods as $index => $method ) : ?>
						<label><input type="radio" name="payment_method" value="<?php echo esc_attr( $method ); ?>" data-taka-payment-label="<?php echo esc_attr( self::payment_method_label( $method, $lang ) ); ?>" <?php checked( ( '' !== $prefill['payment_method'] && $prefill['payment_method'] === $method ) || ( '' === $prefill['payment_method'] && 0 === $index ) ); ?> required> <?php echo esc_html( self::payment_method_label( $method, $lang ) ); ?></label>
					<?php endforeach; ?>
				</div>
			</section>
			<section class="taka-native-checkout__step taka-native-checkout__review" data-taka-checkout-step-panel="3">
				<h4><?php echo esc_html( self::text( 'ticketing.review_order', 'Review order', $lang ) ); ?></h4>
				<dl>
					<div><dt><?php echo esc_html( self::text( 'ticketing.product', 'Product', $lang ) ); ?></dt><dd><?php echo esc_html( $product['title'] ); ?></dd></div>
					<div><dt><?php echo esc_html( self::text( 'ticketing.total', 'Total', $lang ) ); ?></dt><dd data-taka-standalone-total data-taka-standalone-unit="<?php echo esc_attr( TAKA_Ticketing_Pricing_Service::normalize_money( $product['price'] ) ); ?>" data-taka-standalone-currency="<?php echo esc_attr( $product['currency'] ); ?>"><?php echo esc_html( self::format_money( $product['price'], $product['currency'] ) ); ?></dd></div>
				</dl>
				<?php self::frontend_consent_checkbox( 'accept_terms', self::setting_text( $settings, 'terms_label', $lang ), self::setting_text( $settings, 'terms_link_text', $lang ), $settings['terms_url'] ?? '' ); ?>
				<?php self::frontend_consent_checkbox( 'accept_privacy', self::setting_text( $settings, 'privacy_label', $lang ), self::setting_text( $settings, 'privacy_link_text', $lang ), $settings['privacy_url'] ?? '' ); ?>
				<button class="taka-native-checkout__submit" type="submit" data-taka-checkout-submit><?php echo esc_html( self::text( 'ticketing.submit_order', 'Submit Order', $lang ) ); ?></button>
			</section>
			<?php self::render_checkout_navigation( $lang ); ?>
		</form>
		<?php
	}

	private static function render_checkout_form( $event, $event_id, $prefill_order = null ) {
		$ticket_types = self::available_ticket_types_for_event( $event_id );
		$add_on_products = self::available_add_on_products_for_event( $event_id );
		$payment_methods = self::enabled_payment_methods_for_event( $event_id );
		$errors = self::checkout_errors_from_request();
		$prefill = self::checkout_prefill_from_order( $prefill_order );
		if ( empty( $ticket_types ) || empty( $payment_methods ) ) {
			echo '<div class="taka-ticket-status taka-ticket-status--boxed"><strong>' . esc_html( taka_tour_translate( 'ticketing.not_available', 'Native ticket booking is not available yet.' ) ) . '</strong></div>';
			return;
		}
		$form_id = 'taka-native-checkout-form-' . absint( $event_id );
		$lang = taka_tour_current_language();
		$settings = self::ticketing_settings();
		$country_choices = array( '' => self::text( 'ticketing.select_country', 'Select country', $lang ) ) + TAKA_Platform_Data::country_choices( $lang );
		$single_ticket = 1 === count( $ticket_types );
		$event_title = (string) ( $event['title'] ?? get_the_title( $event_id ) );
		$collect_dietary = self::event_collects_dietary_preferences( $event_id );
		?>
		<button class="taka-native-checkout__toggle" type="button" data-taka-native-checkout-toggle aria-expanded="<?php echo empty( $errors ) ? 'false' : 'true'; ?>" aria-controls="<?php echo esc_attr( $form_id ); ?>"><?php echo esc_html( taka_tour_translate( 'ticketing.book_tickets', 'Book Tickets' ) ); ?></button>
		<form id="<?php echo esc_attr( $form_id ); ?>" class="taka-native-checkout__form<?php echo empty( $errors ) ? '' : ' is-open'; ?>" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" data-taka-checkout-form data-taka-initial-step="<?php echo esc_attr( ! empty( $errors ) ? '3' : '1' ); ?>" data-taka-error-required="<?php echo esc_attr( self::text( 'ticketing.error_required_step', 'Please complete the required fields before continuing.', $lang ) ); ?>" data-taka-error-terms="<?php echo esc_attr( self::text( 'ticketing.error_terms', 'Please accept the terms and privacy notice.', $lang ) ); ?>" data-taka-promotion-endpoint="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-taka-promotion-action="<?php echo esc_attr( self::PROMOTION_AJAX_ACTION ); ?>" data-taka-promotion-nonce="<?php echo esc_attr( wp_create_nonce( self::CHECKOUT_ACTION ) ); ?>" data-taka-promotion-empty="<?php echo esc_attr( self::text( 'ticketing.error_promotion_empty', 'Enter a promotion code first.', $lang ) ); ?>" data-taka-promotion-cleared="<?php echo esc_attr( self::text( 'ticketing.promotion_reapply', 'Apply the promotion code again after changing the ticket.', $lang ) ); ?>" data-taka-no-payment-label="<?php echo esc_attr( self::text( 'ticketing.no_payment_required', 'No payment required.', $lang ) ); ?>" <?php echo empty( $errors ) ? 'hidden' : ''; ?>>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::CHECKOUT_ACTION ); ?>">
			<input type="hidden" name="event_id" value="<?php echo esc_attr( (string) $event_id ); ?>">
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( self::current_url() ); ?>">
			<input type="hidden" name="language" value="<?php echo esc_attr( $lang ); ?>">
			<input type="hidden" name="taka_ticketing_nonce" value="<?php echo esc_attr( wp_create_nonce( self::CHECKOUT_ACTION ) ); ?>">
			<label class="taka-native-checkout__honeypot"><?php echo esc_html( self::text( 'ticketing.website', 'Website', $lang ) ); ?><input type="text" name="company_website" value="" tabindex="-1" autocomplete="off"></label>
			<div class="taka-native-checkout__errors" role="alert" data-taka-checkout-errors <?php echo empty( $errors ) ? 'hidden' : ''; ?>><?php foreach ( $errors as $error ) : ?><p><?php echo esc_html( $error ); ?></p><?php endforeach; ?></div>
			<?php self::render_checkout_progress( ! empty( $errors ) ? 3 : 1, $lang, true ); ?>
			<section class="taka-native-checkout__step" data-taka-checkout-step-panel="1">
				<h4><?php echo esc_html( taka_tour_translate( 'ticketing.buyer_information', 'Buyer information' ) ); ?></h4>
				<div class="taka-native-checkout__grid">
					<?php self::frontend_input( 'buyer_first_name', taka_tour_translate( 'ticketing.first_name', 'First name' ), 'text', true, $prefill['buyer']['first_name'] ?? '', array( 'autocomplete' => 'given-name' ) ); ?>
					<?php self::frontend_input( 'buyer_last_name', taka_tour_translate( 'ticketing.last_name', 'Last name' ), 'text', true, $prefill['buyer']['last_name'] ?? '', array( 'autocomplete' => 'family-name' ) ); ?>
					<?php self::frontend_input( 'buyer_email', taka_tour_translate( 'ticketing.email', 'Email' ), 'email', true, $prefill['buyer']['email'] ?? '', array( 'autocomplete' => 'email' ) ); ?>
					<?php self::frontend_select( 'buyer_country', taka_tour_translate( 'ticketing.country', 'Country' ), $country_choices, true, array( 'autocomplete' => 'country-name', 'data-taka-country-select' => '1' ), $prefill['buyer']['country'] ?? '' ); ?>
					<?php self::frontend_input( 'buyer_phone', taka_tour_translate( 'ticketing.phone', 'Phone' ), 'text', false, $prefill['buyer']['phone'] ?? '', array( 'autocomplete' => 'tel' ) ); ?>
				</div>
			</section>
			<section class="taka-native-checkout__step" data-taka-checkout-step-panel="1">
				<h4><?php echo esc_html( taka_tour_translate( 'ticketing.select_ticket_type', 'Select ticket type' ) ); ?></h4>
				<div class="taka-native-ticket-options">
					<?php foreach ( $ticket_types as $index => $ticket_type ) : ?>
						<?php $availability = self::ticket_availability( $event_id, $ticket_type ); ?>
						<label class="taka-native-ticket-option">
							<input type="radio" name="ticket_type_id" value="<?php echo esc_attr( $ticket_type['id'] ); ?>" data-taka-ticket-name="<?php echo esc_attr( $ticket_type['name'] ); ?>" data-taka-ticket-price="<?php echo esc_attr( self::format_money( $ticket_type['price'], $ticket_type['currency'] ) ); ?>" data-taka-ticket-unit="<?php echo esc_attr( TAKA_Ticketing_Pricing_Service::normalize_money( $ticket_type['price'] ) ); ?>" data-taka-ticket-currency="<?php echo esc_attr( $ticket_type['currency'] ); ?>" data-taka-ticket-max="<?php echo esc_attr( null === ( $availability['remaining'] ?? null ) ? '' : (string) max( 1, absint( $availability['remaining'] ) ) ); ?>" <?php checked( ( '' !== $prefill['ticket_type_id'] && $prefill['ticket_type_id'] === (string) $ticket_type['id'] ) || ( '' === $prefill['ticket_type_id'] && ( $single_ticket || 0 === $index ) ) ); ?> required>
							<span><strong><?php echo esc_html( $ticket_type['name'] ); ?></strong><?php if ( '' !== trim( (string) $ticket_type['description'] ) ) : ?><em><?php echo esc_html( $ticket_type['description'] ); ?></em><?php endif; ?></span>
							<span><?php echo esc_html( self::format_money( $ticket_type['price'], $ticket_type['currency'] ) ); ?></span>
							<small><?php echo esc_html( self::availability_label( $availability ) ); ?></small>
						</label>
					<?php endforeach; ?>
				</div>
				<div class="taka-native-checkout__grid">
					<?php self::frontend_input( 'ticket_quantity', self::text( 'ticketing.ticket_quantity', 'Number of tickets', $lang ), 'number', true, $prefill['ticket_quantity'] ?? 1, array( 'min' => '1', 'step' => '1', 'data-taka-ticket-quantity' => '1' ) ); ?>
				</div>
			</section>
			<?php if ( ! empty( $add_on_products ) ) : ?>
				<section class="taka-native-checkout__step" data-taka-add-ons-section data-taka-checkout-step-panel="1">
					<h4><?php echo esc_html( self::text( 'ticketing.optional_add_ons', 'Optional add-ons', $lang ) ); ?></h4>
					<div class="taka-native-ticket-options taka-native-product-options">
						<?php foreach ( $add_on_products as $product ) : ?>
							<?php $availability = self::product_repository()->availability( $product ); $max_quantity = self::max_product_quantity_for_checkout( $product, $availability ); $prefill_quantity = min( $max_quantity, max( 0, absint( $prefill['product_quantities'][ $product['product_id'] ] ?? 0 ) ) ); ?>
							<label class="taka-native-ticket-option taka-native-product-option">
								<?php if ( $max_quantity > 1 ) : ?>
									<input type="number" min="0" max="<?php echo esc_attr( (string) $max_quantity ); ?>" name="<?php echo esc_attr( 'product_quantities[' . $product['product_id'] . ']' ); ?>" value="<?php echo esc_attr( (string) $prefill_quantity ); ?>" data-taka-product-quantity data-taka-product-id="<?php echo esc_attr( $product['product_id'] ); ?>" data-taka-product-name="<?php echo esc_attr( $product['title'] ); ?>" data-taka-product-price="<?php echo esc_attr( self::format_money( $product['price'], $product['currency'] ) ); ?>" data-taka-product-unit="<?php echo esc_attr( TAKA_Ticketing_Pricing_Service::normalize_money( $product['price'] ) ); ?>">
								<?php else : ?>
									<input type="checkbox" name="<?php echo esc_attr( 'product_quantities[' . $product['product_id'] . ']' ); ?>" value="1" data-taka-product-quantity data-taka-product-id="<?php echo esc_attr( $product['product_id'] ); ?>" data-taka-product-name="<?php echo esc_attr( $product['title'] ); ?>" data-taka-product-price="<?php echo esc_attr( self::format_money( $product['price'], $product['currency'] ) ); ?>" data-taka-product-unit="<?php echo esc_attr( TAKA_Ticketing_Pricing_Service::normalize_money( $product['price'] ) ); ?>" <?php checked( $prefill_quantity > 0 ); ?>>
								<?php endif; ?>
								<span><strong><?php echo esc_html( $product['title'] ); ?></strong><?php if ( '' !== trim( (string) $product['description'] ) ) : ?><em><?php echo esc_html( $product['description'] ); ?></em><?php endif; ?></span>
								<span><?php echo esc_html( self::format_money( $product['price'], $product['currency'] ) ); ?></span>
								<small><?php echo esc_html( self::availability_label( $availability ) ); ?></small>
							</label>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>
			<section class="taka-native-checkout__step" data-taka-checkout-step-panel="2" data-taka-participant-copy-section>
				<label class="taka-native-checkout__checkbox"><input type="checkbox" name="self_participates" value="1" <?php checked( ! empty( $prefill['participant_is_buyer'] ) ); ?> data-taka-participant-self data-taka-label-single="<?php echo esc_attr( taka_tour_translate( 'ticketing.participating_myself', 'I am participating myself.' ) ); ?>" data-taka-label-multi="<?php echo esc_attr( self::text( 'ticketing.copy_buyer_to_first_participant', 'Use buyer data for the first participant.', $lang ) ); ?>"> <span data-taka-participant-self-label><?php echo esc_html( taka_tour_translate( 'ticketing.participating_myself', 'I am participating myself.' ) ); ?></span></label>
			</section>
			<section class="taka-native-checkout__step" data-taka-checkout-step-panel="2" data-taka-single-participant-section>
				<h4><?php echo esc_html( taka_tour_translate( 'ticketing.participant_information', 'Participant information' ) ); ?></h4>
				<div class="taka-native-checkout__grid" data-taka-participant-identity-fields>
					<?php self::frontend_input( 'participant_first_name', taka_tour_translate( 'ticketing.first_name', 'First name' ), 'text', false, $prefill['participant']['first_name'] ?? '', array( 'autocomplete' => 'given-name' ) ); ?>
					<?php self::frontend_input( 'participant_last_name', taka_tour_translate( 'ticketing.last_name', 'Last name' ), 'text', false, $prefill['participant']['last_name'] ?? '', array( 'autocomplete' => 'family-name' ) ); ?>
					<?php self::frontend_input( 'participant_email', taka_tour_translate( 'ticketing.email_optional', 'Email (optional)' ), 'email', false, $prefill['participant']['email'] ?? '', array( 'autocomplete' => 'email' ) ); ?>
					<?php self::frontend_select( 'participant_country', taka_tour_translate( 'ticketing.country', 'Country' ), $country_choices, false, array( 'autocomplete' => 'country-name', 'data-taka-country-select' => '1' ), $prefill['participant']['country'] ?? '' ); ?>
				</div>
				<div class="taka-native-checkout__grid" data-taka-participant-extra-fields>
					<?php self::frontend_input( 'participant_dojo', taka_tour_translate( 'ticketing.dojo', 'Dojo / Club' ), 'text', false, $prefill['participant']['dojo'] ?? '' ); ?>
					<?php self::frontend_input( 'participant_association', taka_tour_translate( 'ticketing.association', 'Association' ), 'text', false, $prefill['participant']['association'] ?? '' ); ?>
					<?php self::frontend_input( 'participant_style', taka_tour_translate( 'ticketing.style', 'Style' ), 'text', false, $prefill['participant']['style'] ?? '' ); ?>
					<?php self::frontend_input( 'participant_rank', taka_tour_translate( 'ticketing.rank', 'Rank / Belt' ), 'text', false, $prefill['participant']['rank'] ?? '' ); ?>
					<?php if ( $collect_dietary ) : ?>
						<?php self::frontend_select( 'participant_dietary_preference', taka_tour_translate( 'ticketing.dietary_preference', 'Dietary preference' ), self::dietary_choices( $lang ), false, array( 'data-taka-dietary-preference' => '1' ), $prefill['participant']['dietary_preference'] ?? 'none' ); ?>
						<?php self::frontend_textarea( 'participant_dietary_notes', taka_tour_translate( 'ticketing.dietary_note', 'Dietary note' ), array( 'data-taka-dietary-note-field' => '1' ), $prefill['participant']['dietary_notes'] ?? '' ); ?>
						<?php self::frontend_textarea( 'participant_allergies', taka_tour_translate( 'ticketing.allergies', 'Allergies' ), array(), $prefill['participant']['allergies'] ?? '' ); ?>
					<?php endif; ?>
					<?php self::frontend_textarea( 'participant_notes', taka_tour_translate( 'ticketing.notes', 'Notes' ), array(), $prefill['participant']['notes'] ?? '' ); ?>
				</div>
			</section>
			<section class="taka-native-checkout__step" data-taka-checkout-step-panel="2" data-taka-multi-participant-section data-taka-dietary-enabled="<?php echo esc_attr( $collect_dietary ? '1' : '0' ); ?>" data-taka-participants-prefill="<?php echo esc_attr( wp_json_encode( self::participant_prefill_rows( $prefill['participants'] ?? array() ) ) ); ?>" data-taka-country-options="<?php echo esc_attr( wp_json_encode( $country_choices ) ); ?>" data-taka-dietary-options="<?php echo esc_attr( wp_json_encode( self::dietary_choices( $lang ) ) ); ?>" data-taka-label-participant="<?php echo esc_attr( self::text( 'ticketing.participant', 'Participant', $lang ) ); ?>" data-taka-label-first-name="<?php echo esc_attr( self::text( 'ticketing.first_name', 'First name', $lang ) ); ?>" data-taka-label-last-name="<?php echo esc_attr( self::text( 'ticketing.last_name', 'Last name', $lang ) ); ?>" data-taka-label-email="<?php echo esc_attr( self::text( 'ticketing.email_optional', 'Email (optional)', $lang ) ); ?>" data-taka-label-country="<?php echo esc_attr( self::text( 'ticketing.country', 'Country', $lang ) ); ?>" data-taka-label-dojo="<?php echo esc_attr( self::text( 'ticketing.dojo', 'Dojo / Club', $lang ) ); ?>" data-taka-label-rank="<?php echo esc_attr( self::text( 'ticketing.rank', 'Rank / Belt', $lang ) ); ?>" data-taka-label-dietary="<?php echo esc_attr( self::text( 'ticketing.dietary_preference', 'Dietary preference', $lang ) ); ?>">
				<h4><?php echo esc_html( self::text( 'ticketing.participants', 'Participants', $lang ) ); ?></h4>
				<div class="taka-native-participants" data-taka-ticket-participants></div>
			</section>
			<section class="taka-native-checkout__step" data-taka-promotion-section data-taka-checkout-step-panel="2">
				<h4><?php echo esc_html( self::text( 'ticketing.promotion_code', 'Promotion code', $lang ) ); ?></h4>
				<div class="taka-native-promotion">
					<label><span><?php echo esc_html( self::text( 'ticketing.voucher_code', 'Voucher code', $lang ) ); ?></span><input type="text" name="promotion_code" value="<?php echo esc_attr( $prefill['promotion_code'] ?? '' ); ?>" data-taka-promotion-code autocomplete="off" placeholder="<?php echo esc_attr( self::text( 'ticketing.promotion_code_placeholder', 'Enter code', $lang ) ); ?>"></label>
					<button type="button" data-taka-apply-promotion><?php echo esc_html( self::text( 'ticketing.apply_promotion', 'Apply', $lang ) ); ?></button>
				</div>
				<div class="taka-native-promotion__message" data-taka-promotion-message aria-live="polite"></div>
				<ul class="taka-native-promotion__benefits" data-taka-promotion-benefits hidden></ul>
			</section>
			<section class="taka-native-checkout__step" data-taka-payment-section data-taka-checkout-step-panel="3">
				<h4><?php echo esc_html( taka_tour_translate( 'ticketing.payment_method', 'Payment method' ) ); ?></h4>
				<div class="taka-native-payment-options">
					<?php foreach ( $payment_methods as $index => $method ) : ?>
						<label><input type="radio" name="payment_method" value="<?php echo esc_attr( $method ); ?>" data-taka-payment-label="<?php echo esc_attr( self::payment_method_label( $method ) ); ?>" <?php checked( ( '' !== $prefill['payment_method'] && $prefill['payment_method'] === $method ) || ( '' === $prefill['payment_method'] && 0 === $index ) ); ?> required> <?php echo esc_html( self::payment_method_label( $method ) ); ?></label>
					<?php endforeach; ?>
				</div>
			</section>
			<section class="taka-native-checkout__step taka-native-checkout__review" data-taka-checkout-review data-taka-checkout-step-panel="3">
				<h4><?php echo esc_html( taka_tour_translate( 'ticketing.review_order', 'Review order' ) ); ?></h4>
				<dl>
					<div><dt><?php echo esc_html( taka_tour_translate( 'ticketing.event', 'Event' ) ); ?></dt><dd><?php echo esc_html( $event_title ); ?></dd></div>
					<div><dt><?php echo esc_html( taka_tour_translate( 'ticketing.ticket', 'Ticket' ) ); ?></dt><dd data-taka-review-ticket>-</dd></div>
					<div><dt><?php echo esc_html( taka_tour_translate( 'ticketing.price', 'Price' ) ); ?></dt><dd data-taka-review-price>-</dd></div>
					<div data-taka-review-line-items-row hidden><dt><?php echo esc_html( self::text( 'ticketing.order_items', 'Order items', $lang ) ); ?></dt><dd data-taka-review-line-items>-</dd></div>
					<div data-taka-review-promotion-row hidden><dt><?php echo esc_html( self::text( 'ticketing.voucher_applied', 'Voucher applied', $lang ) ); ?></dt><dd data-taka-review-promotion>-</dd></div>
					<div data-taka-review-discount-row hidden><dt><?php echo esc_html( self::text( 'ticketing.discount', 'Discount', $lang ) ); ?></dt><dd data-taka-review-discount>-</dd></div>
					<div><dt><?php echo esc_html( taka_tour_translate( 'ticketing.buyer', 'Buyer' ) ); ?></dt><dd data-taka-review-buyer>-</dd></div>
					<div><dt><?php echo esc_html( taka_tour_translate( 'ticketing.participant', 'Participant' ) ); ?></dt><dd data-taka-review-participant>-</dd></div>
					<div><dt><?php echo esc_html( taka_tour_translate( 'ticketing.payment_method', 'Payment method' ) ); ?></dt><dd data-taka-review-payment>-</dd></div>
					<div><dt><?php echo esc_html( taka_tour_translate( 'ticketing.total', 'Total' ) ); ?></dt><dd data-taka-review-total>-</dd></div>
				</dl>
				<?php self::frontend_consent_checkbox( 'accept_terms', self::setting_text( $settings, 'terms_label', $lang ), self::setting_text( $settings, 'terms_link_text', $lang ), $settings['terms_url'] ?? '' ); ?>
				<?php self::frontend_consent_checkbox( 'accept_privacy', self::setting_text( $settings, 'privacy_label', $lang ), self::setting_text( $settings, 'privacy_link_text', $lang ), $settings['privacy_url'] ?? '' ); ?>
				<button class="taka-native-checkout__submit" type="submit" data-taka-checkout-submit><?php echo esc_html( taka_tour_translate( 'ticketing.submit_order', 'Submit Order' ) ); ?></button>
			</section>
			<?php self::render_checkout_navigation( $lang ); ?>
		</form>
		<?php
	}

	private static function render_checkout_progress( $active_step = 1, $lang = null, $interactive = true ) {
		$active_step = min( 4, max( 1, absint( $active_step ) ) );
		$steps = array(
			1 => self::text( 'ticketing.step_select_ticket', 'Booking', $lang ),
			2 => self::text( 'ticketing.step_participant', 'Participant', $lang ),
			3 => self::text( 'ticketing.step_review', 'Review', $lang ),
			4 => self::text( 'ticketing.step_confirmation', 'Confirmation', $lang ),
		);
		echo '<ol class="taka-native-checkout__progress" aria-label="' . esc_attr( self::text( 'ticketing.booking_steps', 'Booking steps', $lang ) ) . '" data-taka-checkout-progress>';
		foreach ( $steps as $step => $label ) {
			$classes = array();
			if ( $step === $active_step ) {
				$classes[] = 'is-active';
			} elseif ( $step < $active_step ) {
				$classes[] = 'is-complete';
			}
			echo '<li class="' . esc_attr( implode( ' ', $classes ) ) . '" data-taka-checkout-step-indicator="' . esc_attr( (string) $step ) . '"' . ( $step === $active_step ? ' aria-current="step"' : '' ) . '>';
			if ( $interactive && $step < 4 ) {
				echo '<button type="button" data-taka-checkout-step-target="' . esc_attr( (string) $step ) . '">' . esc_html( $label ) . '</button>';
			} else {
				echo '<span>' . esc_html( $label ) . '</span>';
			}
			echo '</li>';
		}
		echo '</ol>';
	}

	private static function render_checkout_navigation( $lang = null ) {
		echo '<div class="taka-native-checkout__navigation" data-taka-checkout-navigation>';
		echo '<button class="taka-native-checkout__nav-button" type="button" data-taka-checkout-prev>' . esc_html( self::text( 'ticketing.back', 'Back', $lang ) ) . '</button>';
		echo '<button class="taka-native-checkout__nav-button taka-native-checkout__nav-button--primary" type="button" data-taka-checkout-next>' . esc_html( self::text( 'ticketing.continue', 'Continue', $lang ) ) . '</button>';
		echo '</div>';
	}

	private static function frontend_input( $name, $label, $type, $required, $value = '', $attributes = array() ) {
		echo '<label><span>' . self::required_label( $label, $required ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" ' . ( $required ? 'required aria-required="true"' : '' ) . self::html_attributes( $attributes ) . '></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private static function frontend_select( $name, $label, $choices, $required, $attributes = array(), $selected = '' ) {
		echo '<label><span>' . self::required_label( $label, $required ) . '</span><select name="' . esc_attr( $name ) . '" ' . ( $required ? 'required aria-required="true"' : '' ) . self::html_attributes( $attributes ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( (array) $choices as $value => $choice_label ) {
			echo '<option value="' . esc_attr( (string) $value ) . '" ' . selected( (string) $selected, (string) $value, false ) . '>' . esc_html( (string) $choice_label ) . '</option>';
		}
		echo '</select></label>';
	}

	private static function frontend_textarea( $name, $label, $attributes = array(), $value = '' ) {
		echo '<label class="taka-native-checkout__wide" ' . ( ! empty( $attributes['data-taka-dietary-note-field'] ) ? 'data-taka-dietary-note-wrap' : '' ) . '><span>' . esc_html( $label ) . '</span><textarea name="' . esc_attr( $name ) . '" rows="2"' . self::html_attributes( $attributes ) . '>' . esc_textarea( (string) $value ) . '</textarea></label>';
	}

	private static function frontend_consent_checkbox( $name, $label, $link_text, $url ) {
		echo '<label class="taka-native-checkout__checkbox taka-native-checkout__consent"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" required aria-required="true"> <span>';
		self::render_linked_label( $label, $link_text, $url );
		echo ' <span class="taka-native-checkout__required" aria-hidden="true">*</span><span class="screen-reader-text">' . esc_html__( 'Required', 'taka-platform' ) . '</span></span></label>';
	}

	private static function required_label( $label, $required ) {
		$html = esc_html( $label );
		if ( $required ) {
			$html .= ' <span class="taka-native-checkout__required" aria-hidden="true">*</span><span class="screen-reader-text">' . esc_html__( 'Required', 'taka-platform' ) . '</span>';
		}
		return $html;
	}

	private static function render_linked_label( $label, $link_text, $url ) {
		$label = '' !== trim( (string) $label ) ? (string) $label : '{link}';
		$link_text = '' !== trim( (string) $link_text ) ? (string) $link_text : $label;
		$url = esc_url( $url );
		$link_html = '' !== $url ? '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html( $link_text ) . '</a>' : esc_html( $link_text );

		if ( false !== strpos( $label, '{link}' ) ) {
			$parts = explode( '{link}', $label );
			echo esc_html( $parts[0] ) . $link_html . esc_html( $parts[1] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		echo esc_html( $label ) . ' ' . $link_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private static function dietary_choices( $lang = null ) {
		return array(
			'none'       => self::text( 'ticketing.dietary_none', 'No dietary preference', $lang ),
			'vegetarian' => self::text( 'ticketing.dietary_vegetarian', 'Vegetarian', $lang ),
			'vegan'      => self::text( 'ticketing.dietary_vegan', 'Vegan', $lang ),
			'other'      => self::text( 'ticketing.dietary_other', 'Other / note', $lang ),
		);
	}

	private static function participant_prefill_rows( $participants ) {
		$rows = array();
		foreach ( (array) $participants as $participant ) {
			$participant = is_array( $participant ) ? $participant : array();
			$rows[] = array(
				'first_name'         => sanitize_text_field( $participant['first_name'] ?? '' ),
				'last_name'          => sanitize_text_field( $participant['last_name'] ?? '' ),
				'email'              => sanitize_email( $participant['email'] ?? '' ),
				'country'            => sanitize_text_field( $participant['country'] ?? '' ),
				'dojo'               => sanitize_text_field( $participant['dojo'] ?? '' ),
				'association'        => sanitize_text_field( $participant['association'] ?? '' ),
				'style'              => sanitize_text_field( $participant['style'] ?? '' ),
				'rank'               => sanitize_text_field( $participant['rank'] ?? '' ),
				'dietary_preference' => sanitize_key( $participant['dietary_preference'] ?? 'none' ),
				'dietary_notes'      => sanitize_textarea_field( $participant['dietary_notes'] ?? '' ),
				'allergies'          => sanitize_textarea_field( $participant['allergies'] ?? '' ),
				'notes'              => sanitize_textarea_field( $participant['notes'] ?? '' ),
			);
		}
		return $rows;
	}

	private static function html_attributes( $attributes ) {
		$out = '';
		foreach ( (array) $attributes as $name => $value ) {
			if ( '' === (string) $name ) {
				continue;
			}
			$out .= ' ' . esc_attr( (string) $name ) . '="' . esc_attr( (string) $value ) . '"';
		}
		return $out;
	}

	public static function handle_checkout() {
		$redirect = esc_url_raw( wp_unslash( $_POST['redirect_to'] ?? wp_get_referer() ) );
		$redirect = '' !== $redirect ? $redirect : home_url( '/' );

		if ( ! empty( $_POST['company_website'] ) ) {
			wp_safe_redirect( $redirect );
			exit;
		}
		if ( empty( $_POST['taka_ticketing_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['taka_ticketing_nonce'] ) ), self::CHECKOUT_ACTION ) ) {
			$lang = sanitize_key( wp_unslash( $_POST['language'] ?? taka_tour_current_language() ) );
			self::redirect_with_errors( $redirect, array( self::text( 'ticketing.error_session_expired', 'Your booking session expired. Please try again.', $lang ) ) );
		}

		$order = TAKA_Ticketing_Order_Service::create_order_from_post( wp_unslash( $_POST ) );
		if ( is_wp_error( $order ) ) {
			self::redirect_with_errors( $redirect, $order->get_error_messages() );
		}

		$payment = is_array( $order->get( 'payment', array() ) ) ? $order->get( 'payment', array() ) : array();
		if ( 'paypal' === (string) $order->get( 'payment_method', '' ) && ! empty( $payment['approval_url'] ) ) {
			wp_redirect( esc_url_raw( $payment['approval_url'] ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}

		wp_safe_redirect( add_query_arg( 'taka_ticket_order', rawurlencode( $order->get( 'public_token' ) ), $redirect ) );
		exit;
	}

	public static function handle_document_download() {
		$token = sanitize_text_field( wp_unslash( $_GET['taka_token'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$document = sanitize_key( wp_unslash( $_GET['document'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = '' !== $token ? self::order_repository()->find_by_public_token( $token ) : null;
		if ( ! $order || ! class_exists( 'TAKA_Ticketing_Ticket_Artifact_Service' ) ) {
			wp_die( esc_html__( 'Document not found.', 'taka-platform' ), esc_html__( 'Document not found', 'taka-platform' ), array( 'response' => 404 ) );
		}
		$path = TAKA_Ticketing_Ticket_Artifact_Service::document_path( $order, $document );
		if ( '' === $path || ! file_exists( $path ) ) {
			wp_die( esc_html__( 'Document not found.', 'taka-platform' ), esc_html__( 'Document not found', 'taka-platform' ), array( 'response' => 404 ) );
		}
		self::serve_private_file( $path, 'invoice' === $document ? 'Rechnung.pdf' : 'Ticket.pdf', 'application/pdf' );
	}

	private static function serve_private_file( $path, $filename, $content_type ) {
		if ( headers_sent() ) {
			wp_die( esc_html__( 'Download could not be started.', 'taka-platform' ) );
		}
		nocache_headers();
		header( 'Content-Type: ' . $content_type );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	public static function handle_paypal_return() {
		$provider = self::payment_provider( 'paypal' );
		$token = sanitize_text_field( wp_unslash( $_GET['taka_token'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$request_redirect = self::clean_checkout_return_url( wp_unslash( $_GET['redirect_to'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$redirect = self::checkout_redirect_for_token( $token, $request_redirect );
		if ( ! $provider ) {
			self::redirect_with_errors( $redirect, array( self::text( 'ticketing.error_payment_method', 'Please choose an available payment method.' ) ) );
		}

		$result = $provider->handle_return( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( is_wp_error( $result ) ) {
			self::redirect_with_errors( $redirect, $result->get_error_messages() );
		}

		if ( $result instanceof TAKA_Ticketing_Order ) {
			$token = $result->get( 'public_token' );
			$order_redirect = self::clean_checkout_return_url( $result->get( 'checkout_return_url', '' ) );
			if ( '' !== $order_redirect ) {
				$redirect = $order_redirect;
			}
		}
		wp_safe_redirect( add_query_arg( 'taka_ticket_order', rawurlencode( $token ), $redirect ) );
		exit;
	}

	public static function handle_paypal_cancel() {
		$token = sanitize_text_field( wp_unslash( $_GET['taka_token'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$redirect = self::checkout_redirect_for_token( $token, self::clean_checkout_return_url( wp_unslash( $_GET['redirect_to'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = '' !== $token ? self::order_repository()->find_by_public_token( $token ) : null;
		if ( $order ) {
			$data = $order->to_array();
			$data['order_status'] = 'cancelled';
			$data['payment_status'] = 'cancelled';
			$data['updated_at'] = current_time( 'mysql' );
			$data['timeline'][] = array( 'time' => current_time( 'mysql' ), 'label' => __( 'PayPal checkout cancelled', 'taka-platform' ) );
			self::order_repository()->save( new TAKA_Ticketing_Order( $data ) );
		}
		$redirect = '' !== $redirect ? $redirect : home_url( '/' );
		if ( '' !== $token ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'taka_ticket_order' => rawurlencode( $token ),
						'taka_ticket_payment_cancelled' => '1',
					),
					$redirect
				)
			);
			exit;
		}
		self::redirect_with_errors( $redirect, array( self::text( 'ticketing.paypal_cancelled', 'PayPal checkout was cancelled. Please start the booking again if you still want to register.' ) ) );
	}

	public static function handle_paypal_webhook() {
		$provider = self::payment_provider( 'paypal' );
		if ( ! $provider ) {
			status_header( 400 );
			exit;
		}
		$result = $provider->handle_webhook( wp_unslash( $_SERVER ) );
		if ( is_wp_error( $result ) ) {
			status_header( 400 );
			echo esc_html( $result->get_error_message() );
			exit;
		}
		status_header( 200 );
		echo 'OK';
		exit;
	}

	public static function paypal_return_url( $public_token, $redirect_to ) {
		$args = array(
			'action'     => self::PAYPAL_RETURN_ACTION,
			'taka_token' => sanitize_text_field( $public_token ),
		);
		$redirect_to = self::url_without_fragment( self::clean_checkout_return_url( $redirect_to ) );
		if ( '' !== $redirect_to ) {
			$args['redirect_to'] = $redirect_to;
		}
		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	public static function paypal_cancel_url( $public_token, $redirect_to ) {
		$args = array(
			'action'     => self::PAYPAL_CANCEL_ACTION,
			'taka_token' => sanitize_text_field( $public_token ),
		);
		$redirect_to = self::url_without_fragment( self::clean_checkout_return_url( $redirect_to ) );
		if ( '' !== $redirect_to ) {
			$args['redirect_to'] = $redirect_to;
		}
		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	public static function paypal_webhook_url() {
		return add_query_arg( 'action', self::PAYPAL_WEBHOOK_ACTION, admin_url( 'admin-post.php' ) );
	}

	public static function handle_apply_promotion_ajax() {
		$lang = sanitize_key( wp_unslash( $_POST['language'] ?? taka_tour_current_language() ) );
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::CHECKOUT_ACTION ) ) {
			wp_send_json_error( array( 'message' => self::text( 'ticketing.error_session_expired', 'Your booking session expired. Please try again.', $lang ) ), 403 );
		}

		$event_id = absint( $_POST['event_id'] ?? 0 );
		$ticket_type_id = sanitize_key( wp_unslash( $_POST['ticket_type_id'] ?? '' ) );
		$promotion_code = sanitize_text_field( wp_unslash( $_POST['promotion_code'] ?? '' ) );
		$buyer_email = sanitize_email( wp_unslash( $_POST['buyer_email'] ?? '' ) );
		$product_quantities = isset( $_POST['product_quantities'] ) && is_array( $_POST['product_quantities'] ) ? wp_unslash( $_POST['product_quantities'] ) : array();
		$ticket_quantity = max( 1, absint( $_POST['ticket_quantity'] ?? 1 ) );

		$ticket_type = self::find_ticket_type( $event_id, $ticket_type_id );
		if ( ! $ticket_type ) {
			wp_send_json_error( array( 'message' => self::text( 'ticketing.error_ticket_missing', 'Ticket type not found.', $lang ) ), 404 );
		}
		$ticket_availability = self::ticket_availability( $event_id, $ticket_type );
		if ( null !== ( $ticket_availability['remaining'] ?? null ) && $ticket_quantity > max( 0, absint( $ticket_availability['remaining'] ) ) ) {
			wp_send_json_error( array( 'message' => self::text( 'ticketing.error_ticket_capacity', 'The selected ticket quantity is no longer available.', $lang ) ), 400 );
		}
		$product_items = self::product_line_items_from_quantities( $event_id, $product_quantities, $lang );
		if ( is_wp_error( $product_items ) ) {
			wp_send_json_error( array( 'message' => $product_items->get_error_message() ), 400 );
		}

		$quote = TAKA_Ticketing_Pricing_Service::quote( $event_id, $ticket_type, $buyer_email, $promotion_code, $lang, $product_items, $ticket_quantity );
		if ( is_wp_error( $quote ) ) {
			wp_send_json_error( array( 'message' => $quote->get_error_message() ), 400 );
		}

		wp_send_json_success( self::pricing_response_payload( $quote, $lang ) );
	}

	private static function product_line_items_from_quantities( $event_id, $quantities, $lang ) {
		$items = array();
		foreach ( (array) $quantities as $product_id => $quantity ) {
			$product_id = TAKA_Ticketing_Product::normalize_product_id( $product_id );
			$quantity = absint( $quantity );
			if ( '' === $product_id || $quantity <= 0 ) {
				continue;
			}
			$product = self::product_repository()->find_by_product_id( $product_id );
			if ( ! $product || '1' !== (string) ( $product['visible_in_checkout'] ?? '1' ) || '1' !== (string) ( $product['requires_event_ticket'] ?? '0' ) || absint( $product['related_event_id'] ?? 0 ) !== absint( $event_id ) ) {
				return new WP_Error( 'taka_ticketing_product_missing', self::text( 'ticketing.error_product_missing', 'Product not found.', $lang ) );
			}
			$availability = self::product_repository()->availability( $product );
			if ( empty( $availability['available'] ) ) {
				return new WP_Error( 'taka_ticketing_product_unavailable', self::text( 'ticketing.error_product_unavailable', 'This product is no longer available.', $lang ) );
			}
			$max = max( 1, absint( $product['max_quantity_per_order'] ?? 1 ) );
			if ( null !== ( $availability['remaining'] ?? null ) ) {
				$max = min( $max, max( 0, absint( $availability['remaining'] ) ) );
			}
			if ( $quantity > $max ) {
				return new WP_Error( 'taka_ticketing_product_capacity', self::text( 'ticketing.error_product_capacity', 'The selected add-on quantity is no longer available.', $lang ) );
			}
			$items[] = TAKA_Ticketing_Product::line_item_from_product( $product, $quantity, $event_id );
		}
		return $items;
	}

	public static function handle_admin_order_action() {
		if ( ! current_user_can( 'edit_taka_orders' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::ADMIN_ACTION, '_wpnonce' );
		$order_id = absint( $_POST['order_id'] ?? 0 );
		$task = sanitize_key( wp_unslash( $_POST['task'] ?? '' ) );
		$order = self::order_repository()->find_by_id( $order_id );
		if ( ! $order || ! self::current_user_can_access_order( $order, true ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		$result = null;

		if ( 'mark_paid' === $task ) {
			$result = TAKA_Ticketing_Order_Service::mark_paid( $order_id );
		} elseif ( 'cancel' === $task ) {
			$result = TAKA_Ticketing_Order_Service::cancel( $order_id );
		} elseif ( 'refund' === $task ) {
			$result = TAKA_Ticketing_Order_Service::refund( $order_id );
		} elseif ( 'delete' === $task ) {
			self::order_repository()->delete_order( $order_id );
			wp_safe_redirect( self::admin_url( array( 'deleted' => '1' ) ) );
			exit;
		}

		$args = array( 'order_id' => $order_id );
		if ( is_wp_error( $result ) ) {
			$args['order_error'] = $result->get_error_message();
		} else {
			$args['updated'] = '1';
			$args['order_action'] = $task;
		}
		wp_safe_redirect( self::admin_url( $args ) );
		exit;
	}

	public static function handle_save_settings() {
		if ( ! current_user_can( 'manage_taka_ticketing' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::SETTINGS_ACTION, '_wpnonce' );
		$settings = isset( $_POST['taka_ticketing_settings'] ) && is_array( $_POST['taka_ticketing_settings'] ) ? wp_unslash( $_POST['taka_ticketing_settings'] ) : array();
		update_option( self::SETTINGS_OPTION, self::normalize_settings( $settings ), false );
		wp_safe_redirect( self::admin_url( array( 'settings_updated' => '1' ) ) );
		exit;
	}

	public static function handle_save_promotion() {
		if ( ! current_user_can( 'manage_taka_promotions' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::PROMOTION_ACTION, '_wpnonce' );
		$promotion = isset( $_POST['promotion'] ) && is_array( $_POST['promotion'] ) ? wp_unslash( $_POST['promotion'] ) : array();
		$result = self::promotion_repository()->save( $promotion );
		$args = array( 'section' => 'promotions' );
		if ( is_wp_error( $result ) ) {
			$args['promotion_error'] = $result->get_error_message();
			if ( ! empty( $promotion['id'] ) ) {
				$args['promotion_id'] = absint( $promotion['id'] );
			}
		} else {
			$args['promotion_saved'] = '1';
			$args['promotion_id'] = absint( $result['id'] ?? 0 );
		}
		wp_safe_redirect( self::admin_url( $args ) );
		exit;
	}

	public static function handle_delete_promotion() {
		if ( ! current_user_can( 'manage_taka_promotions' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::PROMOTION_DELETE_ACTION, '_wpnonce' );
		self::promotion_repository()->delete( absint( $_POST['promotion_id'] ?? 0 ) );
		wp_safe_redirect( self::admin_url( array( 'section' => 'promotions', 'promotion_deleted' => '1' ) ) );
		exit;
	}

	public static function handle_save_product() {
		if ( ! current_user_can( 'manage_taka_products' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::PRODUCT_ACTION, '_wpnonce' );
		$product = isset( $_POST['product'] ) && is_array( $_POST['product'] ) ? wp_unslash( $_POST['product'] ) : array();
		$result = self::product_repository()->save( $product );
		$args = array( 'section' => 'products' );
		if ( is_wp_error( $result ) ) {
			$args['product_error'] = $result->get_error_message();
			if ( ! empty( $product['id'] ) ) {
				$args['product_id'] = absint( $product['id'] );
			}
		} else {
			$args['product_saved'] = '1';
			$args['product_id'] = absint( $result['id'] ?? 0 );
		}
		wp_safe_redirect( self::admin_url( $args ) );
		exit;
	}

	public static function handle_delete_product() {
		if ( ! current_user_can( 'manage_taka_products' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::PRODUCT_DELETE_ACTION, '_wpnonce' );
		self::product_repository()->delete( absint( $_POST['product_id'] ?? 0 ) );
		wp_safe_redirect( self::admin_url( array( 'section' => 'products', 'product_deleted' => '1' ) ) );
		exit;
	}

	private static function redirect_with_errors( $redirect, $messages ) {
		$key = wp_generate_password( 16, false, false );
		set_transient( 'taka_ticketing_errors_' . $key, array_values( array_map( 'sanitize_text_field', (array) $messages ) ), 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'taka_ticketing_error', rawurlencode( $key ), $redirect ) );
		exit;
	}

	private static function checkout_errors_from_request() {
		$messages = array();
		if ( self::payment_cancelled_from_request() ) {
			$messages[] = self::text( 'ticketing.paypal_cancelled', 'PayPal checkout was cancelled. Your booking details have been kept so you can choose a payment method again.' );
		}
		$key = sanitize_text_field( wp_unslash( $_GET['taka_ticketing_error'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $key ) {
			return $messages;
		}
		$stored = get_transient( 'taka_ticketing_errors_' . $key );
		delete_transient( 'taka_ticketing_errors_' . $key );
		return array_values( array_unique( array_merge( $messages, is_array( $stored ) ? $stored : array() ) ) );
	}

	private static function payment_cancelled_from_request() {
		return ! empty( $_GET['taka_ticket_payment_cancelled'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	private static function order_from_request_for_event( $event_id ) {
		$token = sanitize_text_field( wp_unslash( $_GET['taka_ticket_order'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $token ) {
			return null;
		}
		$order = self::order_repository()->find_by_public_token( $token );
		return $order && absint( $order->get( 'event_id' ) ) === absint( $event_id ) ? $order : null;
	}

	private static function order_from_request_for_product( $product_id ) {
		$token = sanitize_text_field( wp_unslash( $_GET['taka_ticket_order'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $token ) {
			return null;
		}
		$product_id = TAKA_Ticketing_Product::normalize_product_id( $product_id );
		$order = self::order_repository()->find_by_public_token( $token );
		if ( ! $order ) {
			return null;
		}
		foreach ( (array) ( $order->get( 'line_items', array() ) ) as $item ) {
			if ( 'product' === (string) ( $item['item_type'] ?? '' ) && (string) ( $item['product_id'] ?? '' ) === $product_id ) {
				return $order;
			}
		}
		return null;
	}

	public static function clean_checkout_return_url( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		$parts = explode( '#', $url, 2 );
		$base = remove_query_arg( array( 'taka_ticket_order', 'taka_ticketing_error', 'taka_ticket_payment_cancelled', 'token', 'PayerID' ), $parts[0] );
		if ( ! isset( $parts[1] ) || '' === trim( (string) $parts[1] ) ) {
			return $base;
		}
		$fragment = self::clean_checkout_fragment( $parts[1] );
		return '' !== $fragment ? $base . '#' . $fragment : $base;
	}

	private static function clean_checkout_fragment( $fragment ) {
		$fragment = ltrim( (string) $fragment, '#' );
		if ( '' === $fragment ) {
			return '';
		}
		$fragment = preg_replace( '/([?&])(?:token|PayerID)=[^&]*/i', '', $fragment );
		$fragment = preg_replace( '/\?&/', '?', (string) $fragment );
		$fragment = preg_replace( '/[?&]+$/', '', (string) $fragment );
		return trim( (string) $fragment );
	}

	private static function url_without_fragment( $url ) {
		$parts = explode( '#', (string) $url, 2 );
		return esc_url_raw( $parts[0] ?? '' );
	}

	private static function checkout_redirect_for_token( $token, $fallback = '' ) {
		$token = sanitize_text_field( $token );
		if ( '' !== $token ) {
			$order = self::order_repository()->find_by_public_token( $token );
			if ( $order ) {
				$redirect = self::clean_checkout_return_url( $order->get( 'checkout_return_url', '' ) );
				if ( '' !== $redirect ) {
					return $redirect;
				}
			}
		}
		$fallback = self::clean_checkout_return_url( $fallback );
		return '' !== $fallback ? $fallback : home_url( '/' );
	}

	private static function current_url() {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) );
		$uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
		return self::clean_checkout_return_url( $scheme . $host . $uri );
	}

	private static function render_order_confirmation( TAKA_Ticketing_Order $order ) {
		$data = $order->to_array();
		$lang = sanitize_key( $data['language'] ?? taka_tour_current_language() );
		$provider = self::payment_provider( $data['payment_method'] ?? '' );
		$instructions = $provider ? $provider->get_public_instructions( $order ) : array();
		$benefits = is_array( $data['applied_benefits'] ?? null ) ? $data['applied_benefits'] : array();
		$line_items = is_array( $data['line_items'] ?? null ) ? $data['line_items'] : array();
		?>
		<section class="taka-native-confirmation">
			<?php self::render_checkout_progress( 4, $lang, false ); ?>
			<h3><?php echo esc_html( self::text( 'ticketing.registration_received', 'Registration received', $lang ) ); ?></h3>
			<dl>
				<div><dt><?php echo esc_html( self::text( 'ticketing.order_number', 'Order number', $lang ) ); ?></dt><dd><?php echo esc_html( $data['order_number'] ?? '' ); ?></dd></div>
				<?php if ( '' !== trim( (string) ( $data['event_title'] ?? '' ) ) ) : ?><div><dt><?php echo esc_html( self::text( 'ticketing.event', 'Event', $lang ) ); ?></dt><dd><?php echo esc_html( $data['event_title'] ?? '' ); ?></dd></div><?php endif; ?>
				<?php if ( '' !== trim( (string) ( $data['ticket_type_name'] ?? '' ) ) ) : ?><div><dt><?php echo esc_html( self::text( 'ticketing.ticket', 'Ticket', $lang ) ); ?></dt><dd><?php echo esc_html( $data['ticket_type_name'] ?? '' ); ?></dd></div><?php endif; ?>
				<?php if ( ! empty( $line_items ) ) : ?>
					<div><dt><?php echo esc_html( self::text( 'ticketing.order_items', 'Order items', $lang ) ); ?></dt><dd><ul class="taka-native-confirmation__items"><?php foreach ( $line_items as $item ) : ?><li><?php echo esc_html( self::line_item_label( $item ) ); ?></li><?php endforeach; ?></ul></dd></div>
				<?php endif; ?>
				<?php if ( '' !== trim( (string) ( $data['applied_voucher_code'] ?? '' ) ) ) : ?>
					<div><dt><?php echo esc_html( self::text( 'ticketing.voucher_applied', 'Voucher applied', $lang ) ); ?></dt><dd><?php echo esc_html( $data['applied_voucher_code'] ); ?></dd></div>
					<div><dt><?php echo esc_html( self::text( 'ticketing.discount', 'Discount', $lang ) ); ?></dt><dd><?php echo esc_html( self::format_money( $data['discount_amount'] ?? '0', $data['currency'] ?? 'EUR' ) ); ?></dd></div>
				<?php endif; ?>
				<div><dt><?php echo esc_html( self::text( 'ticketing.amount', 'Amount', $lang ) ); ?></dt><dd><?php echo esc_html( self::format_money( $data['amount'] ?? '', $data['currency'] ?? 'EUR' ) ); ?></dd></div>
				<div><dt><?php echo esc_html( self::text( 'ticketing.payment_method', 'Payment method', $lang ) ); ?></dt><dd><?php echo esc_html( self::payment_method_label( $data['payment_method'] ?? '', $lang ) ); ?></dd></div>
			</dl>
			<?php if ( ! empty( $benefits ) ) : ?>
				<div class="taka-native-confirmation__instructions">
					<h4><?php echo esc_html( self::text( 'ticketing.included_benefits', 'Included benefits', $lang ) ); ?></h4>
					<ul>
						<?php foreach ( $benefits as $benefit ) : ?>
							<li><?php echo esc_html( self::benefit_line( $benefit ) ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			<h4><?php echo esc_html( self::text( 'ticketing.next_steps', 'Next steps', $lang ) ); ?></h4>
			<?php if ( 'bank_transfer' === (string) ( $data['payment_method'] ?? '' ) ) : ?>
				<div class="taka-native-confirmation__instructions">
					<p><?php echo esc_html( self::text( 'ticketing.bank_transfer_next_steps', 'Please transfer the amount using the payment reference below.', $lang ) ); ?></p>
					<h4><?php echo esc_html( self::text( 'ticketing.bank_transfer_instructions', 'Bank transfer instructions', $lang ) ); ?></h4>
					<?php foreach ( array( 'account_holder' => 'Account holder', 'bank_name' => 'Bank name', 'iban' => 'IBAN', 'bic' => 'BIC', 'amount' => 'Amount', 'payment_reference' => 'Payment reference', 'due_date' => 'Payment due date' ) as $field => $label ) : ?>
						<?php if ( '' !== trim( (string) ( $instructions[ $field ] ?? '' ) ) ) : ?><p><strong><?php echo esc_html( self::text( 'ticketing.' . $field, $label, $lang ) ); ?>:</strong> <?php echo esc_html( $instructions[ $field ] ); ?></p><?php endif; ?>
					<?php endforeach; ?>
					<?php if ( '' !== trim( (string) ( $instructions['instructions'] ?? '' ) ) ) : ?><p><?php echo esc_html( $instructions['instructions'] ); ?></p><?php endif; ?>
				</div>
			<?php elseif ( 'pay_at_door' === (string) ( $data['payment_method'] ?? '' ) ) : ?>
				<div class="taka-native-confirmation__instructions">
					<p><?php echo esc_html( $instructions['message'] ?? self::text( 'ticketing.pay_at_door_message', 'Please pay your admission at the registration desk before entering the seminar. Payment is required before participation.', $lang ) ); ?></p>
					<?php if ( '' !== trim( (string) ( $instructions['instructions'] ?? '' ) ) ) : ?><p><?php echo esc_html( $instructions['instructions'] ); ?></p><?php endif; ?>
				</div>
			<?php elseif ( 'paypal' === (string) ( $data['payment_method'] ?? '' ) ) : ?>
				<div class="taka-native-confirmation__instructions">
					<?php if ( 'paid' === (string) ( $data['payment_status'] ?? '' ) ) : ?>
						<p><?php echo esc_html( self::text( 'ticketing.paypal_payment_received', 'Your PayPal payment has been received.', $lang ) ); ?></p>
					<?php else : ?>
						<p><?php echo esc_html( self::text( 'ticketing.paypal_payment_pending', 'Your PayPal payment has not been completed yet.', $lang ) ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $data['payment']['paypal_order_id'] ) ) : ?><p><strong><?php echo esc_html( self::text( 'ticketing.paypal_order_id', 'PayPal order ID', $lang ) ); ?>:</strong> <?php echo esc_html( $data['payment']['paypal_order_id'] ); ?></p><?php endif; ?>
					<?php if ( ! empty( $data['payment']['transaction_id'] ) ) : ?><p><strong><?php echo esc_html( self::text( 'ticketing.transaction_id', 'Transaction ID', $lang ) ); ?>:</strong> <?php echo esc_html( $data['payment']['transaction_id'] ); ?></p><?php endif; ?>
				</div>
			<?php elseif ( in_array( (string) ( $data['payment_method'] ?? '' ), array( 'promotion', 'free' ), true ) ) : ?>
				<div class="taka-native-confirmation__instructions">
					<p><?php echo esc_html( self::text( 'ticketing.no_payment_required', 'No payment required.', $lang ) ); ?></p>
				</div>
			<?php endif; ?>
			<div class="taka-native-confirmation__actions">
				<button type="button" onclick="window.print()"><?php echo esc_html( self::text( 'ticketing.print', 'Print', $lang ) ); ?></button>
				<?php if ( class_exists( 'TAKA_Ticketing_Ticket_Artifact_Service' ) ) : ?>
					<?php $invoice_url = self::document_download_url( $order, 'invoice' ); $ticket_url = self::document_download_url( $order, 'ticket' ); ?>
					<?php if ( '' !== $invoice_url ) : ?><a class="button" href="<?php echo esc_url( $invoice_url ); ?>"><?php echo esc_html( self::text( 'ticketing.download_invoice', 'Download invoice', $lang ) ); ?></a><?php endif; ?>
					<?php if ( '' !== $ticket_url ) : ?><a class="button" href="<?php echo esc_url( $ticket_url ); ?>"><?php echo esc_html( self::text( 'ticketing.download_ticket', 'Download ticket', $lang ) ); ?></a><?php endif; ?>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}

	private static function document_download_url( TAKA_Ticketing_Order $order, $document ) {
		$token = sanitize_text_field( $order->get( 'public_token', '' ) );
		if ( '' === $token ) {
			return '';
		}
		return add_query_arg(
			array(
				'action'     => self::DOWNLOAD_ACTION,
				'taka_token' => $token,
				'document'   => sanitize_key( $document ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	public static function available_ticket_types_for_event( $event_id ) {
		return array_values(
			array_filter(
				self::ticket_types_for_event( $event_id ),
				static function ( $ticket_type ) use ( $event_id ) {
					return ! empty( self::ticket_availability( $event_id, $ticket_type )['available'] );
				}
			)
		);
	}

	public static function available_add_on_products_for_event( $event_id ) {
		return array_values(
			array_filter(
				self::product_repository()->checkout_add_ons_for_event( absint( $event_id ) ),
				static function ( $product ) {
					return ! empty( TAKA_Ticketing_Module::product_repository()->availability( $product )['available'] );
				}
			)
		);
	}

	private static function max_product_quantity_for_checkout( $product, $availability ) {
		$max = max( 1, absint( $product['max_quantity_per_order'] ?? 1 ) );
		if ( null !== ( $availability['remaining'] ?? null ) ) {
			$max = min( $max, max( 0, absint( $availability['remaining'] ) ) );
		}
		return max( 1, $max );
	}

	public static function find_ticket_type( $event_id, $ticket_type_id ) {
		foreach ( self::ticket_types_for_event( $event_id ) as $ticket_type ) {
			if ( (string) ( $ticket_type['id'] ?? '' ) === (string) $ticket_type_id ) {
				return $ticket_type;
			}
		}
		return null;
	}

	public static function ticket_availability( $event_id, $ticket_type ) {
		$status = (string) ( $ticket_type['status'] ?? 'active' );
		$available = 'active' === $status;
		$reason = '';
		if ( ! $available ) {
			$reason = 'sold_out' === $status ? self::text( 'ticketing.sold_out', 'Sold out' ) : self::text( 'ticketing.unavailable', 'Unavailable' );
		}

		$now = current_time( 'timestamp' );
		$start = self::sale_timestamp( $ticket_type['sale_start_date'] ?? '', $ticket_type['sale_start_time'] ?? '00:00' );
		$end = self::sale_timestamp( $ticket_type['sale_end_date'] ?? '', $ticket_type['sale_end_time'] ?? '23:59' );
		if ( $available && $start && $now < $start ) {
			$available = false;
			$reason = self::text( 'ticketing.sales_not_started', 'Sales have not started yet.' );
		}
		if ( $available && $end && $now > $end ) {
			$available = false;
			$reason = self::text( 'ticketing.sales_ended', 'Sales have ended.' );
		}

		$capacity = '' === trim( (string) ( $ticket_type['capacity'] ?? '' ) ) ? null : absint( $ticket_type['capacity'] );
		$reserved = self::order_repository()->count_reserved_for_ticket( $event_id, $ticket_type['id'] ?? '' );
		$remaining = null === $capacity ? null : max( 0, $capacity - $reserved );
		if ( $available && null !== $remaining && $remaining <= 0 ) {
			$available = false;
			$reason = self::text( 'ticketing.sold_out', 'Sold out' );
		}

		return array( 'available' => $available, 'capacity' => $capacity, 'reserved' => $reserved, 'remaining' => $remaining, 'reason' => $reason );
	}

	private static function sale_timestamp( $date, $time ) {
		$date = trim( (string) $date );
		if ( '' === $date ) {
			return 0;
		}
		$time = preg_match( '/^\d{2}:\d{2}$/', (string) $time ) ? (string) $time : '00:00';
		return strtotime( $date . ' ' . $time );
	}

	private static function availability_label( $availability ) {
		if ( ! empty( $availability['reason'] ) ) {
			return $availability['reason'];
		}
		if ( null === ( $availability['remaining'] ?? null ) ) {
			return taka_tour_translate( 'ticketing.available', 'Available' );
		}
		return sprintf( taka_tour_translate( 'ticketing.remaining_capacity', '%d remaining' ), (int) $availability['remaining'] );
	}

	public static function format_money( $amount, $currency ) {
		$amount = TAKA_Platform_Data::sanitize_money_value( $amount );
		$currency = TAKA_Platform_Data::normalize_event_option_value( 'currency', $currency ?: 'EUR' );
		if ( '' === $amount ) {
			$amount = '0';
		}
		return trim( ( 'EUR' === $currency ? '€' : $currency . ' ' ) . $amount );
	}

	public static function pricing_response_payload( $quote, $lang = null ) {
		$quote = is_array( $quote ) ? $quote : array();
		$currency = $quote['currency'] ?? 'EUR';
		$benefits = array();
		foreach ( (array) ( $quote['benefits'] ?? array() ) as $benefit ) {
			$benefits[] = array(
				'type'  => sanitize_key( $benefit['type'] ?? '' ),
				'label' => sanitize_text_field( $benefit['label'] ?? '' ),
				'value' => sanitize_text_field( $benefit['value'] ?? '' ),
				'note'  => sanitize_text_field( $benefit['note'] ?? '' ),
			);
		}
		$line_items = array();
		foreach ( (array) ( $quote['line_items'] ?? array() ) as $item ) {
			$line_items[] = array(
				'item_type'     => sanitize_key( $item['item_type'] ?? '' ),
				'title'         => sanitize_text_field( $item['title'] ?? '' ),
				'quantity'      => max( 1, absint( $item['quantity'] ?? 1 ) ),
				'unit_display'  => self::format_money( $item['unit_price'] ?? '0', $item['currency'] ?? $currency ),
				'total_display' => self::format_money( $item['total_price'] ?? '0', $item['currency'] ?? $currency ),
			);
		}

		return array(
			'message'                 => self::text( 'ticketing.promotion_applied', 'Promotion applied.', $lang ),
			'promotion_code'          => sanitize_text_field( $quote['promotion_code'] ?? '' ),
			'promotion_title'         => sanitize_text_field( $quote['promotion_title'] ?? '' ),
			'original_amount'         => TAKA_Ticketing_Pricing_Service::normalize_money( $quote['original_amount'] ?? '0' ),
			'original_amount_display' => self::format_money( $quote['original_amount'] ?? '0', $currency ),
			'discount_amount'         => TAKA_Ticketing_Pricing_Service::normalize_money( $quote['discount_amount'] ?? '0' ),
			'discount_display'        => self::format_money( $quote['discount_amount'] ?? '0', $currency ),
			'final_amount'            => TAKA_Ticketing_Pricing_Service::normalize_money( $quote['final_amount'] ?? '0' ),
			'final_amount_display'    => self::format_money( $quote['final_amount'] ?? '0', $currency ),
			'payment_required'        => ! empty( $quote['payment_required'] ),
			'no_payment_label'        => self::text( 'ticketing.no_payment_required', 'No payment required.', $lang ),
			'benefits'                => $benefits,
			'line_items'              => $line_items,
		);
	}

	public static function payment_method_label( $method, $lang = null ) {
		$labels = array(
			'bank_transfer' => self::text( 'ticketing.payment_bank_transfer', 'Bank Transfer', $lang ),
			'pay_at_door'   => self::text( 'ticketing.payment_pay_at_door', 'Pay at the Door', $lang ),
			'paypal'        => self::text( 'ticketing.payment_paypal', 'PayPal', $lang ),
			'promotion'     => self::text( 'ticketing.payment_promotion', 'Voucher / promotion', $lang ),
			'free'          => self::text( 'ticketing.no_payment_required', 'No payment required.', $lang ),
		);
		return $labels[ $method ] ?? sanitize_text_field( $method );
	}

	public static function payment_method_admin_label( $method ) {
		$icons = array(
			'bank_transfer' => '🏦',
			'pay_at_door'   => '💶',
			'paypal'        => 'PayPal',
			'promotion'     => '%',
		);
		return trim( ( $icons[ $method ] ?? '' ) . ' ' . self::payment_method_label( $method ) );
	}

	public static function admin_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::ADMIN_PAGE_SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	public static function current_user_can_manage_all_ticketing() {
		return current_user_can( 'manage_options' );
	}

	public static function current_user_ticketing_organizer_ids() {
		$ids = get_user_meta( get_current_user_id(), '_taka_platform_organizer_ids', true );
		if ( ! is_array( $ids ) ) {
			$ids = array_filter( preg_split( '/\s*,\s*/', (string) $ids ) );
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	public static function order_billing_organizer_id( $order ) {
		$data = $order instanceof TAKA_Ticketing_Order ? $order->to_array() : (array) $order;
		$organizer_id = absint( $data['organizer_id'] ?? 0 );
		if ( $organizer_id ) {
			return $organizer_id;
		}
		$billing = is_array( $data['billing_organizer'] ?? null ) ? $data['billing_organizer'] : array();
		$organizer_id = absint( $billing['organizer_id'] ?? 0 );
		if ( $organizer_id ) {
			return $organizer_id;
		}
		return self::event_billing_organizer_id( absint( $data['event_id'] ?? 0 ) );
	}

	public static function current_user_can_access_order( $order, $edit = false ) {
		if ( self::current_user_can_manage_all_ticketing() ) {
			return true;
		}
		if ( $edit && ! current_user_can( 'edit_taka_orders' ) ) {
			return false;
		}
		if ( ! $edit && ! current_user_can( 'view_taka_orders' ) && ! current_user_can( 'view_taka_finance' ) ) {
			return false;
		}
		$organizer_id = self::order_billing_organizer_id( $order );
		return $organizer_id && in_array( $organizer_id, self::current_user_ticketing_organizer_ids(), true );
	}

	public static function orders_visible_to_current_user( $orders ) {
		$selected_organizer = self::selected_order_organizer_filter();
		return array_values(
			array_filter(
				(array) $orders,
				static function ( $order ) use ( $selected_organizer ) {
					if ( ! self::current_user_can_access_order( $order ) ) {
						return false;
					}
					return ! $selected_organizer || self::order_billing_organizer_id( $order ) === $selected_organizer;
				}
			)
		);
	}

	private static function selected_order_organizer_filter() {
		if ( ! self::current_user_can_manage_all_ticketing() ) {
			return 0;
		}
		return absint( $_GET['organizer_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'view_taka_orders' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}

		$order_id = absint( $_GET['order_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$section = sanitize_key( $_GET['section'] ?? 'orders' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap taka-ticketing-admin"><h1>' . esc_html__( 'Ticketing', 'taka-platform' ) . '</h1>';
		if ( ! empty( $_GET['settings_updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Ticketing settings saved.', 'taka-platform' ) . '</p></div>';
		}
		if ( ! empty( $_GET['promotion_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Promotion saved.', 'taka-platform' ) . '</p></div>';
		}
		if ( ! empty( $_GET['promotion_deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Promotion deleted.', 'taka-platform' ) . '</p></div>';
		}
		if ( ! empty( $_GET['promotion_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['promotion_error'] ) ) ) . '</p></div>';
		}
		if ( ! empty( $_GET['product_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Product saved.', 'taka-platform' ) . '</p></div>';
		}
		if ( ! empty( $_GET['product_deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Product deleted.', 'taka-platform' ) . '</p></div>';
		}
		if ( ! empty( $_GET['product_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['product_error'] ) ) ) . '</p></div>';
		}
		if ( ! empty( $_GET['order_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['order_error'] ) ) ) . '</p></div>';
		}
		if ( ! empty( $_GET['updated'] ) && ! empty( $_GET['order_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_action = sanitize_key( wp_unslash( $_GET['order_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$message = 'refund' === $order_action ? __( 'PayPal refund issued.', 'taka-platform' ) : __( 'Order updated.', 'taka-platform' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		}
		if ( $order_id ) {
			self::render_admin_nav( 'orders' );
			self::render_order_detail( $order_id );
		} elseif ( 'products' === $section ) {
			self::render_admin_nav( 'products' );
			self::render_products_page();
		} elseif ( 'promotions' === $section ) {
			self::render_admin_nav( 'promotions' );
			self::render_promotions_page();
		} else {
			self::render_admin_nav( 'orders' );
			self::render_settings_box();
			self::render_order_list();
		}
		echo '</div>';
	}

	private static function render_admin_nav( $active ) {
		$items = array(
			'orders'     => array( 'label' => __( 'Orders', 'taka-platform' ), 'url' => self::admin_url() ),
			'products'   => array( 'label' => __( 'Products', 'taka-platform' ), 'url' => self::admin_url( array( 'section' => 'products' ) ) ),
			'promotions' => array( 'label' => __( 'Promotions / Vouchers', 'taka-platform' ), 'url' => self::admin_url( array( 'section' => 'promotions' ) ) ),
		);
		echo '<nav class="nav-tab-wrapper taka-ticketing-admin-tabs" aria-label="' . esc_attr__( 'Ticketing sections', 'taka-platform' ) . '">';
		foreach ( $items as $key => $item ) {
			if ( 'promotions' === $key && ! current_user_can( 'manage_taka_promotions' ) ) {
				continue;
			}
			if ( 'products' === $key && ! current_user_can( 'manage_taka_products' ) ) {
				continue;
			}
			echo '<a class="nav-tab ' . ( $active === $key ? 'nav-tab-active' : '' ) . '" href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
		}
		echo '</nav>';
	}

	private static function render_settings_box() {
		if ( ! current_user_can( 'manage_taka_ticketing' ) ) {
			return;
		}
		$settings = self::ticketing_settings();
		$languages = TAKA_Platform_Data::content_section_languages();
		$currencies = TAKA_Platform_Data::option_list_choices( 'currency', TAKA_Platform_Data::platform_fallback_language() );
		if ( ! isset( $currencies[ $settings['paypal_currency'] ?? 'EUR' ] ) ) {
			$currencies[ $settings['paypal_currency'] ?? 'EUR' ] = $settings['paypal_currency'] ?? 'EUR';
		}
		?>
		<div class="taka-ticketing-settings">
			<h2><?php echo esc_html__( 'Booking form settings', 'taka-platform' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Configure the legal links and localized checkbox labels used by native ticketing checkout.', 'taka-platform' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SETTINGS_ACTION ); ?>">
				<?php wp_nonce_field( self::SETTINGS_ACTION ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="taka-ticketing-terms-url"><?php echo esc_html__( 'Booking terms URL', 'taka-platform' ); ?></label></th>
						<td><input id="taka-ticketing-terms-url" class="regular-text" type="url" name="taka_ticketing_settings[terms_url]" value="<?php echo esc_attr( $settings['terms_url'] ?? '' ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="taka-ticketing-privacy-url"><?php echo esc_html__( 'Privacy notice URL', 'taka-platform' ); ?></label></th>
						<td><input id="taka-ticketing-privacy-url" class="regular-text" type="url" name="taka_ticketing_settings[privacy_url]" value="<?php echo esc_attr( $settings['privacy_url'] ?? '' ); ?>"></td>
					</tr>
				</table>
				<div class="taka-ticketing-settings__grid">
					<?php self::render_localized_setting_inputs( $languages, 'terms_label', __( 'Booking terms checkbox label', 'taka-platform' ), $settings ); ?>
					<?php self::render_localized_setting_inputs( $languages, 'terms_link_text', __( 'Booking terms link text', 'taka-platform' ), $settings ); ?>
					<?php self::render_localized_setting_inputs( $languages, 'privacy_label', __( 'Privacy checkbox label', 'taka-platform' ), $settings ); ?>
					<?php self::render_localized_setting_inputs( $languages, 'privacy_link_text', __( 'Privacy link text', 'taka-platform' ), $settings ); ?>
				</div>
				<p class="description"><?php echo esc_html__( 'Use {link} in checkbox labels where the configured link text should appear.', 'taka-platform' ); ?></p>
				<section class="taka-ticketing-settings__panel">
					<h3><?php echo esc_html__( 'Legacy PayPal fallback', 'taka-platform' ); ?></h3>
					<p class="description"><?php echo esc_html__( 'Organizer finance profiles are used for normal native ticketing payments. Keep this global PayPal account only as a backwards-compatible fallback for unassigned events and legacy orders. The secret is only used server-side.', 'taka-platform' ); ?></p>
					<label><input type="checkbox" name="taka_ticketing_settings[paypal_enabled]" value="1" <?php checked( '1', (string) ( $settings['paypal_enabled'] ?? '0' ) ); ?>> <?php echo esc_html__( 'Enable PayPal checkout', 'taka-platform' ); ?></label>
					<div class="taka-ticketing-settings__grid">
						<label><span><?php echo esc_html__( 'PayPal client ID', 'taka-platform' ); ?></span><input class="regular-text" type="text" name="taka_ticketing_settings[paypal_client_id]" value="<?php echo esc_attr( $settings['paypal_client_id'] ?? '' ); ?>"></label>
						<label><span><?php echo esc_html__( 'PayPal secret', 'taka-platform' ); ?></span><input class="regular-text" type="password" autocomplete="new-password" name="taka_ticketing_settings[paypal_secret]" value="<?php echo esc_attr( $settings['paypal_secret'] ?? '' ); ?>"></label>
						<label><span><?php echo esc_html__( 'Mode', 'taka-platform' ); ?></span><select name="taka_ticketing_settings[paypal_mode]"><option value="sandbox" <?php selected( 'sandbox', $settings['paypal_mode'] ?? 'sandbox' ); ?>><?php echo esc_html__( 'Sandbox', 'taka-platform' ); ?></option><option value="live" <?php selected( 'live', $settings['paypal_mode'] ?? 'sandbox' ); ?>><?php echo esc_html__( 'Live', 'taka-platform' ); ?></option></select></label>
						<label><span><?php echo esc_html__( 'Currency', 'taka-platform' ); ?></span><select name="taka_ticketing_settings[paypal_currency]"><?php foreach ( $currencies as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) ( $settings['paypal_currency'] ?? 'EUR' ), (string) $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
						<label><span><?php echo esc_html__( 'Webhook ID', 'taka-platform' ); ?></span><input class="regular-text" type="text" name="taka_ticketing_settings[paypal_webhook_id]" value="<?php echo esc_attr( $settings['paypal_webhook_id'] ?? '' ); ?>"></label>
						<label><span><?php echo esc_html__( 'Webhook URL', 'taka-platform' ); ?></span><input class="regular-text" type="text" readonly value="<?php echo esc_attr( self::paypal_webhook_url() ); ?>"></label>
					</div>
				</section>
				<?php submit_button( __( 'Save ticketing settings', 'taka-platform' ) ); ?>
			</form>
		</div>
		<?php
	}

	private static function render_localized_setting_inputs( $languages, $field, $title, $settings ) {
		echo '<section class="taka-ticketing-settings__panel"><h3>' . esc_html( $title ) . '</h3>';
		foreach ( $languages as $lang ) {
			$value = (string) ( $settings[ $field ][ $lang ] ?? '' );
			echo '<label><span>' . esc_html( strtoupper( $lang ) ) . '</span><input class="regular-text" type="text" name="' . esc_attr( 'taka_ticketing_settings[' . $field . '][' . $lang . ']' ) . '" value="' . esc_attr( $value ) . '"></label>';
		}
		echo '</section>';
	}

	private static function render_products_page() {
		if ( ! current_user_can( 'manage_taka_products' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		$product_id = absint( $_GET['product_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$product = $product_id ? self::product_repository()->find_by_id( $product_id ) : null;
		$product = $product ? $product : TAKA_Ticketing_Product::normalize( array( 'type' => 'add_on', 'status' => 'active', 'visible_in_checkout' => '1', 'requires_event_ticket' => '1' ) );
		echo '<h2>' . esc_html__( 'Products', 'taka-platform' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Create add-ons and standalone products such as dojo party participation, meals, merch, donations or transport contributions.', 'taka-platform' ) . '</p>';
		if ( $product_id ) {
			echo '<p><a class="button" href="' . esc_url( self::admin_url( array( 'section' => 'products' ) ) ) . '">' . esc_html__( 'Add new product', 'taka-platform' ) . '</a></p>';
		}
		self::render_product_form( $product );
		self::render_product_list();
	}

	private static function render_product_form( $product ) {
		$product = TAKA_Ticketing_Product::normalize( $product );
		?>
		<div class="taka-ticketing-settings taka-ticketing-promotion-form">
			<h3><?php echo esc_html( ! empty( $product['id'] ) ? __( 'Edit product', 'taka-platform' ) : __( 'Add product', 'taka-platform' ) ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::PRODUCT_ACTION ); ?>">
				<input type="hidden" name="product[id]" value="<?php echo esc_attr( (string) absint( $product['id'] ?? 0 ) ); ?>">
				<?php wp_nonce_field( self::PRODUCT_ACTION ); ?>
				<div class="taka-ticketing-promotion-form__grid">
					<label><span><?php echo esc_html__( 'Product ID', 'taka-platform' ); ?></span><input class="regular-text" type="text" name="product[product_id]" value="<?php echo esc_attr( $product['product_id'] ?? '' ); ?>"></label>
					<label><span><?php echo esc_html__( 'Title', 'taka-platform' ); ?></span><input class="regular-text" type="text" name="product[title]" value="<?php echo esc_attr( $product['title'] ?? '' ); ?>" required></label>
					<label><span><?php echo esc_html__( 'Type', 'taka-platform' ); ?></span><?php self::admin_select( 'product[type]', TAKA_Ticketing_Product::types(), $product['type'] ?? 'add_on' ); ?></label>
					<label><span><?php echo esc_html__( 'Status', 'taka-platform' ); ?></span><?php self::admin_select( 'product[status]', TAKA_Ticketing_Product::statuses(), $product['status'] ?? 'active' ); ?></label>
					<label><span><?php echo esc_html__( 'Price', 'taka-platform' ); ?></span><input type="text" name="product[price]" value="<?php echo esc_attr( $product['price'] ?? '' ); ?>"></label>
					<?php self::product_currency_select( $product['currency'] ?? 'EUR' ); ?>
					<label><span><?php echo esc_html__( 'Capacity / stock', 'taka-platform' ); ?></span><input type="number" min="0" name="product[capacity]" value="<?php echo esc_attr( $product['capacity'] ?? '' ); ?>"></label>
					<label><span><?php echo esc_html__( 'Max quantity per order', 'taka-platform' ); ?></span><input type="number" min="1" name="product[max_quantity_per_order]" value="<?php echo esc_attr( $product['max_quantity_per_order'] ?? '1' ); ?>"></label>
					<label><span><?php echo esc_html__( 'Sale start date', 'taka-platform' ); ?></span><input type="date" name="product[sale_start_date]" value="<?php echo esc_attr( $product['sale_start_date'] ?? '' ); ?>"></label>
					<label><span><?php echo esc_html__( 'Sale start time', 'taka-platform' ); ?></span><input type="time" name="product[sale_start_time]" value="<?php echo esc_attr( $product['sale_start_time'] ?? '' ); ?>"></label>
					<label><span><?php echo esc_html__( 'Sale end date', 'taka-platform' ); ?></span><input type="date" name="product[sale_end_date]" value="<?php echo esc_attr( $product['sale_end_date'] ?? '' ); ?>"></label>
					<label><span><?php echo esc_html__( 'Sale end time', 'taka-platform' ); ?></span><input type="time" name="product[sale_end_time]" value="<?php echo esc_attr( $product['sale_end_time'] ?? '' ); ?>"></label>
					<label><span><?php echo esc_html__( 'Related event', 'taka-platform' ); ?></span><?php self::admin_event_select( 'product[related_event_id]', absint( $product['related_event_id'] ?? 0 ) ); ?></label>
					<label><span><?php echo esc_html__( 'Related tour ID', 'taka-platform' ); ?></span><input type="text" name="product[related_tour_id]" value="<?php echo esc_attr( $product['related_tour_id'] ?? '' ); ?>"></label>
					<label><span><?php echo esc_html__( 'Sort order', 'taka-platform' ); ?></span><input type="number" name="product[sort_order]" value="<?php echo esc_attr( $product['sort_order'] ?? '0' ); ?>"></label>
					<label class="taka-ticketing-promotion-form__wide"><span><?php echo esc_html__( 'Description', 'taka-platform' ); ?></span><textarea rows="2" name="product[description]"><?php echo esc_textarea( $product['description'] ?? '' ); ?></textarea></label>
				</div>
				<div class="taka-ticketing-product-flags">
					<input type="hidden" name="product[requires_event_ticket]" value="0">
					<label><input type="checkbox" name="product[requires_event_ticket]" value="1" <?php checked( '1', (string) ( $product['requires_event_ticket'] ?? '0' ) ); ?>> <?php echo esc_html__( 'Requires event ticket', 'taka-platform' ); ?></label>
					<input type="hidden" name="product[can_purchase_standalone]" value="0">
					<label><input type="checkbox" name="product[can_purchase_standalone]" value="1" <?php checked( '1', (string) ( $product['can_purchase_standalone'] ?? '0' ) ); ?>> <?php echo esc_html__( 'Can be purchased standalone', 'taka-platform' ); ?></label>
					<input type="hidden" name="product[visible_in_checkout]" value="0">
					<label><input type="checkbox" name="product[visible_in_checkout]" value="1" <?php checked( '1', (string) ( $product['visible_in_checkout'] ?? '1' ) ); ?>> <?php echo esc_html__( 'Visible in checkout', 'taka-platform' ); ?></label>
				</div>
				<?php if ( ! empty( $product['id'] ) ) : ?>
					<p class="description"><?php echo esc_html__( 'Standalone shortcode:', 'taka-platform' ); ?> <code>[taka_ticketing_product id="<?php echo esc_html( $product['product_id'] ); ?>"]</code></p>
				<?php endif; ?>
				<?php submit_button( __( 'Save product', 'taka-platform' ) ); ?>
			</form>
		</div>
		<?php
	}

	private static function render_product_list() {
		$products = self::product_repository()->query( array( 'per_page' => -1 ) );
		?>
		<h3><?php echo esc_html__( 'Existing products', 'taka-platform' ); ?></h3>
		<table class="widefat striped">
			<thead><tr>
				<th><?php echo esc_html__( 'Product ID', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Title', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Type', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Related event', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Price', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Capacity', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Standalone', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Status', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Actions', 'taka-platform' ); ?></th>
			</tr></thead>
			<tbody>
				<?php if ( empty( $products ) ) : ?>
					<tr><td colspan="9"><?php echo esc_html__( 'No ticketing products yet.', 'taka-platform' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $products as $product ) : ?>
					<?php $availability = self::product_repository()->availability( $product ); ?>
					<tr>
						<td><code><?php echo esc_html( $product['product_id'] ?? '' ); ?></code></td>
						<td><?php echo esc_html( $product['title'] ?? '' ); ?></td>
						<td><?php echo esc_html( TAKA_Ticketing_Product::types()[ $product['type'] ?? '' ] ?? '' ); ?></td>
						<td><?php echo ! empty( $product['related_event_id'] ) ? esc_html( get_the_title( absint( $product['related_event_id'] ) ) ) : '&mdash;'; ?></td>
						<td><?php echo esc_html( self::format_money( $product['price'] ?? '', $product['currency'] ?? 'EUR' ) ); ?></td>
						<td><?php echo esc_html( null === ( $availability['remaining'] ?? null ) ? __( 'Unlimited', 'taka-platform' ) : (string) $availability['remaining'] ); ?></td>
						<td><?php echo '1' === (string) ( $product['can_purchase_standalone'] ?? '0' ) ? esc_html__( 'Yes', 'taka-platform' ) : esc_html__( 'No', 'taka-platform' ); ?></td>
						<td><?php echo esc_html( TAKA_Ticketing_Product::statuses()[ $product['status'] ?? '' ] ?? '' ); ?></td>
						<td class="taka-ticketing-promotion-actions">
							<a class="button button-small" href="<?php echo esc_url( self::admin_url( array( 'section' => 'products', 'product_id' => absint( $product['id'] ?? 0 ) ) ) ); ?>"><?php echo esc_html__( 'Edit', 'taka-platform' ); ?></a>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="<?php echo esc_attr( self::PRODUCT_DELETE_ACTION ); ?>">
								<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) absint( $product['id'] ?? 0 ) ); ?>">
								<?php wp_nonce_field( self::PRODUCT_DELETE_ACTION ); ?>
								<button class="button button-small button-link-delete" type="submit"><?php echo esc_html__( 'Delete', 'taka-platform' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private static function render_promotions_page() {
		if ( ! current_user_can( 'manage_taka_promotions' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		$promotion_id = absint( $_GET['promotion_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$promotion = $promotion_id ? self::promotion_repository()->find_by_id( $promotion_id ) : null;
		$promotion = $promotion ? $promotion : TAKA_Ticketing_Promotion::normalize( array( 'status' => 'active', 'category' => 'discount', 'scope_type' => 'all' ) );
		echo '<h2>' . esc_html__( 'Promotions / Vouchers', 'taka-platform' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Create voucher codes that can grant monetary discounts and non-ticket benefits such as meals, merch, special access or manual approval flags.', 'taka-platform' ) . '</p>';
		if ( $promotion_id ) {
			echo '<p><a class="button" href="' . esc_url( self::admin_url( array( 'section' => 'promotions' ) ) ) . '">' . esc_html__( 'Add new promotion', 'taka-platform' ) . '</a></p>';
		}
		self::render_promotion_form( $promotion );
		self::render_promotion_list();
	}

	private static function render_promotion_form( $promotion ) {
		$promotion = TAKA_Ticketing_Promotion::normalize( $promotion );
		$benefits = array();
		foreach ( (array) ( $promotion['benefits'] ?? array() ) as $benefit ) {
			$benefits[ $benefit['type'] ] = $benefit;
		}
		?>
		<div class="taka-ticketing-settings taka-ticketing-promotion-form">
			<h3><?php echo esc_html( ! empty( $promotion['id'] ) ? __( 'Edit promotion', 'taka-platform' ) : __( 'Add promotion', 'taka-platform' ) ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::PROMOTION_ACTION ); ?>">
				<input type="hidden" name="promotion[id]" value="<?php echo esc_attr( (string) absint( $promotion['id'] ?? 0 ) ); ?>">
				<?php wp_nonce_field( self::PROMOTION_ACTION ); ?>
				<div class="taka-ticketing-promotion-form__grid">
					<label><span><?php echo esc_html__( 'Code', 'taka-platform' ); ?></span><input class="regular-text" type="text" name="promotion[code]" value="<?php echo esc_attr( $promotion['code'] ?? '' ); ?>" required></label>
					<label><span><?php echo esc_html__( 'Title', 'taka-platform' ); ?></span><input class="regular-text" type="text" name="promotion[title]" value="<?php echo esc_attr( $promotion['title'] ?? '' ); ?>" required></label>
					<label><span><?php echo esc_html__( 'Category', 'taka-platform' ); ?></span><?php self::admin_select( 'promotion[category]', TAKA_Ticketing_Promotion::categories(), $promotion['category'] ?? 'discount' ); ?></label>
					<label><span><?php echo esc_html__( 'Status', 'taka-platform' ); ?></span><?php self::admin_select( 'promotion[status]', TAKA_Ticketing_Promotion::statuses(), $promotion['status'] ?? 'active' ); ?></label>
					<label><span><?php echo esc_html__( 'Valid from', 'taka-platform' ); ?></span><input type="date" name="promotion[valid_from]" value="<?php echo esc_attr( $promotion['valid_from'] ?? '' ); ?>"></label>
					<label><span><?php echo esc_html__( 'Valid until', 'taka-platform' ); ?></span><input type="date" name="promotion[valid_until]" value="<?php echo esc_attr( $promotion['valid_until'] ?? '' ); ?>"></label>
					<label><span><?php echo esc_html__( 'Max total uses', 'taka-platform' ); ?></span><input type="number" min="0" name="promotion[max_total_uses]" value="<?php echo esc_attr( $promotion['max_total_uses'] ?? '' ); ?>"></label>
					<label><span><?php echo esc_html__( 'Max uses per email/person', 'taka-platform' ); ?></span><input type="number" min="0" name="promotion[max_uses_per_person]" value="<?php echo esc_attr( $promotion['max_uses_per_person'] ?? '' ); ?>"></label>
					<label class="taka-ticketing-promotion-form__wide"><span><?php echo esc_html__( 'Description', 'taka-platform' ); ?></span><textarea rows="2" name="promotion[description]"><?php echo esc_textarea( $promotion['description'] ?? '' ); ?></textarea></label>
				</div>
				<h4><?php echo esc_html__( 'Scope', 'taka-platform' ); ?></h4>
				<div class="taka-ticketing-promotion-form__grid">
					<label><span><?php echo esc_html__( 'Scope', 'taka-platform' ); ?></span><?php self::admin_select( 'promotion[scope_type]', TAKA_Ticketing_Promotion::scope_types(), $promotion['scope_type'] ?? 'all' ); ?></label>
					<label><span><?php echo esc_html__( 'Applies to', 'taka-platform' ); ?></span><?php self::admin_select( 'promotion[applies_to]', TAKA_Ticketing_Promotion::applies_to_choices(), $promotion['applies_to'] ?? 'entire_order' ); ?></label>
					<label><span><?php echo esc_html__( 'Selected tour ID', 'taka-platform' ); ?></span><input type="text" name="promotion[scope_tour_id]" value="<?php echo esc_attr( $promotion['scope_tour_id'] ?? '' ); ?>"></label>
					<label><span><?php echo esc_html__( 'Selected event', 'taka-platform' ); ?></span><?php self::admin_event_select( 'promotion[scope_event_id]', absint( $promotion['scope_event_id'] ?? 0 ) ); ?></label>
					<label><span><?php echo esc_html__( 'Selected ticket type ID', 'taka-platform' ); ?></span><input type="text" name="promotion[scope_ticket_type_id]" value="<?php echo esc_attr( $promotion['scope_ticket_type_id'] ?? '' ); ?>"></label>
					<label><span><?php echo esc_html__( 'Selected product', 'taka-platform' ); ?></span><?php self::admin_product_select( 'promotion[scope_product_id]', $promotion['scope_product_id'] ?? '' ); ?></label>
				</div>
				<h4><?php echo esc_html__( 'Benefits', 'taka-platform' ); ?></h4>
				<div class="taka-ticketing-promotion-benefits">
					<?php foreach ( TAKA_Ticketing_Promotion::benefit_types() as $type => $label ) : ?>
						<?php $benefit = $benefits[ $type ] ?? array(); ?>
						<div class="taka-ticketing-promotion-benefit">
							<label><input type="checkbox" name="<?php echo esc_attr( 'promotion[benefits][' . $type . '][enabled]' ); ?>" value="1" <?php checked( isset( $benefits[ $type ] ) ); ?>> <strong><?php echo esc_html( $label ); ?></strong></label>
							<?php if ( 'percentage_discount' === $type ) : ?>
								<label><span><?php echo esc_html__( 'Percent', 'taka-platform' ); ?></span><input type="number" min="0" max="100" step="0.01" name="<?php echo esc_attr( 'promotion[benefits][' . $type . '][value]' ); ?>" value="<?php echo esc_attr( $benefit['value'] ?? '' ); ?>"></label>
							<?php elseif ( 'fixed_discount' === $type ) : ?>
								<label><span><?php echo esc_html__( 'Amount', 'taka-platform' ); ?></span><input type="text" name="<?php echo esc_attr( 'promotion[benefits][' . $type . '][value]' ); ?>" value="<?php echo esc_attr( $benefit['value'] ?? '' ); ?>"></label>
							<?php else : ?>
								<label><span><?php echo esc_html__( 'Optional value', 'taka-platform' ); ?></span><input type="text" name="<?php echo esc_attr( 'promotion[benefits][' . $type . '][value]' ); ?>" value="<?php echo esc_attr( $benefit['value'] ?? '' ); ?>"></label>
							<?php endif; ?>
							<label><span><?php echo esc_html__( 'Note', 'taka-platform' ); ?></span><input type="text" name="<?php echo esc_attr( 'promotion[benefits][' . $type . '][note]' ); ?>" value="<?php echo esc_attr( $benefit['note'] ?? '' ); ?>"></label>
						</div>
					<?php endforeach; ?>
				</div>
				<?php submit_button( __( 'Save promotion', 'taka-platform' ) ); ?>
			</form>
		</div>
		<?php
	}

	private static function render_promotion_list() {
		$promotions = self::promotion_repository()->query( array( 'per_page' => -1 ) );
		?>
		<h3><?php echo esc_html__( 'Existing promotions', 'taka-platform' ); ?></h3>
		<table class="widefat striped">
			<thead><tr>
				<th><?php echo esc_html__( 'Code', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Title', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Category', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Benefits', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Scope', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Uses', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Remaining', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Valid until', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Status', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Actions', 'taka-platform' ); ?></th>
			</tr></thead>
			<tbody>
				<?php if ( empty( $promotions ) ) : ?>
					<tr><td colspan="10"><?php echo esc_html__( 'No promotions yet.', 'taka-platform' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $promotions as $promotion ) : ?>
					<?php $uses = self::promotion_repository()->count_uses( $promotion['id'] ?? 0 ); ?>
					<tr>
						<td><code><?php echo esc_html( $promotion['code'] ?? '' ); ?></code></td>
						<td><?php echo esc_html( $promotion['title'] ?? '' ); ?></td>
						<td><?php echo esc_html( TAKA_Ticketing_Promotion::categories()[ $promotion['category'] ?? '' ] ?? '' ); ?></td>
						<td><?php echo esc_html( self::promotion_benefits_summary( $promotion ) ); ?></td>
						<td><?php echo esc_html( self::promotion_scope_summary( $promotion ) ); ?></td>
						<td><?php echo esc_html( (string) $uses ); ?></td>
						<td><?php echo esc_html( self::promotion_remaining_label( $promotion, $uses ) ); ?></td>
						<td><?php echo esc_html( $promotion['valid_until'] ?? '' ); ?></td>
						<td><?php echo esc_html( TAKA_Ticketing_Promotion::statuses()[ $promotion['status'] ?? '' ] ?? '' ); ?></td>
						<td class="taka-ticketing-promotion-actions">
							<a class="button button-small" href="<?php echo esc_url( self::admin_url( array( 'section' => 'promotions', 'promotion_id' => absint( $promotion['id'] ?? 0 ) ) ) ); ?>"><?php echo esc_html__( 'Edit', 'taka-platform' ); ?></a>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="<?php echo esc_attr( self::PROMOTION_DELETE_ACTION ); ?>">
								<input type="hidden" name="promotion_id" value="<?php echo esc_attr( (string) absint( $promotion['id'] ?? 0 ) ); ?>">
								<?php wp_nonce_field( self::PROMOTION_DELETE_ACTION ); ?>
								<button class="button button-small button-link-delete" type="submit"><?php echo esc_html__( 'Delete', 'taka-platform' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private static function admin_select( $name, $choices, $current ) {
		echo '<select name="' . esc_attr( $name ) . '">';
		foreach ( (array) $choices as $value => $label ) {
			echo '<option value="' . esc_attr( (string) $value ) . '" ' . selected( (string) $current, (string) $value, false ) . '>' . esc_html( (string) $label ) . '</option>';
		}
		echo '</select>';
	}

	private static function admin_event_select( $name, $current ) {
		$events = get_posts(
			array(
				'post_type'        => TAKA_PLATFORM_CPT_EVENT,
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);
		echo '<select name="' . esc_attr( $name ) . '"><option value="0">' . esc_html__( 'Any event', 'taka-platform' ) . '</option>';
		foreach ( $events as $event ) {
			echo '<option value="' . esc_attr( (string) $event->ID ) . '" ' . selected( absint( $current ), absint( $event->ID ), false ) . '>' . esc_html( get_the_title( $event ) ) . '</option>';
		}
		echo '</select>';
	}

	private static function product_currency_select( $current ) {
		$choices = TAKA_Platform_Data::option_list_choices( 'currency', TAKA_Platform_Data::platform_fallback_language() );
		if ( ! isset( $choices[ $current ] ) ) {
			$choices[ $current ] = $current;
		}
		echo '<label><span>' . esc_html__( 'Currency', 'taka-platform' ) . '</span><select name="product[currency]">';
		foreach ( $choices as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( (string) $current, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label>';
	}

	private static function admin_product_select( $name, $current ) {
		$products = self::product_repository()->query( array( 'per_page' => -1 ) );
		echo '<select name="' . esc_attr( $name ) . '"><option value="">' . esc_html__( 'Any product', 'taka-platform' ) . '</option>';
		foreach ( $products as $product ) {
			echo '<option value="' . esc_attr( $product['product_id'] ?? '' ) . '" ' . selected( (string) $current, (string) ( $product['product_id'] ?? '' ), false ) . '>' . esc_html( $product['title'] ?? '' ) . '</option>';
		}
		echo '</select>';
	}

	private static function promotion_benefits_summary( $promotion ) {
		$parts = array();
		foreach ( (array) ( $promotion['benefits'] ?? array() ) as $benefit ) {
			$label = TAKA_Ticketing_Promotion::benefit_types()[ $benefit['type'] ?? '' ] ?? sanitize_text_field( $benefit['type'] ?? '' );
			$value = sanitize_text_field( $benefit['value'] ?? '' );
			$parts[] = '' === $value ? $label : $label . ' ' . $value;
		}
		return implode( ', ', array_filter( $parts ) );
	}

	private static function promotion_scope_summary( $promotion ) {
		$scope = (string) ( $promotion['scope_type'] ?? 'all' );
		$label = TAKA_Ticketing_Promotion::scope_types()[ $scope ] ?? $scope;
		$applies_to = TAKA_Ticketing_Promotion::applies_to_choices()[ $promotion['applies_to'] ?? 'entire_order' ] ?? '';
		if ( 'event' === $scope && ! empty( $promotion['scope_event_id'] ) ) {
			$label .= ': ' . get_the_title( absint( $promotion['scope_event_id'] ) );
		}
		if ( 'ticket_type' === $scope && '' !== trim( (string) ( $promotion['scope_ticket_type_id'] ?? '' ) ) ) {
			$label .= ': ' . $promotion['scope_ticket_type_id'];
		}
		if ( 'tour' === $scope && '' !== trim( (string) ( $promotion['scope_tour_id'] ?? '' ) ) ) {
			$label .= ': ' . $promotion['scope_tour_id'];
		}
		if ( 'product' === (string) ( $promotion['applies_to'] ?? '' ) && '' !== trim( (string) ( $promotion['scope_product_id'] ?? '' ) ) ) {
			$label .= ' / ' . $promotion['scope_product_id'];
		}
		return trim( $label . ( '' !== $applies_to ? ' / ' . $applies_to : '' ) );
	}

	private static function promotion_remaining_label( $promotion, $uses ) {
		$max = '' === trim( (string) ( $promotion['max_total_uses'] ?? '' ) ) ? 0 : absint( $promotion['max_total_uses'] );
		if ( $max <= 0 ) {
			return __( 'Unlimited', 'taka-platform' );
		}
		return (string) max( 0, $max - absint( $uses ) );
	}

	public static function export_ticketing_config() {
		return array(
			'settings'   => self::ticketing_settings(),
			'products'   => self::product_repository()->export_products(),
			'promotions' => self::promotion_repository()->export_promotions(),
		);
	}

	public static function import_ticketing_config( $config, $mode = 'update', $dry_run = false ) {
		$config = is_array( $config ) ? $config : array();
		$settings = is_array( $config['settings'] ?? null ) ? $config['settings'] : $config;
		if ( ! $dry_run ) {
			update_option( self::SETTINGS_OPTION, self::normalize_settings( $settings ), false );
		}
		$summary = array( 'settings' => $dry_run ? 'dry_run' : 'updated', 'products' => array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array() ), 'promotions' => array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array() ) );
		if ( ! empty( $config['products'] ) && is_array( $config['products'] ) ) {
			$summary['products'] = self::product_repository()->import_products( $config['products'], $mode, $dry_run );
		}
		if ( ! empty( $config['promotions'] ) && is_array( $config['promotions'] ) ) {
			$summary['promotions'] = self::promotion_repository()->import_promotions( $config['promotions'], $mode, $dry_run );
		}
		return $summary;
	}

	private static function render_order_organizer_filter() {
		if ( ! self::current_user_can_manage_all_ticketing() ) {
			$organizer_ids = self::current_user_ticketing_organizer_ids();
			if ( ! empty( $organizer_ids ) ) {
				echo '<p class="description">' . esc_html( sprintf( __( 'Showing orders for: %s', 'taka-platform' ), self::organizer_titles_list( $organizer_ids ) ) ) . '</p>';
			}
			return;
		}
		$selected = self::selected_order_organizer_filter();
		$organizers = get_posts(
			array(
				'post_type'        => TAKA_PLATFORM_CPT_ORGANIZER,
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);
		echo '<form method="get" class="taka-ticketing-order-filter">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::ADMIN_PAGE_SLUG ) . '">';
		echo '<label><span>' . esc_html__( 'Organizer view', 'taka-platform' ) . '</span> <select name="organizer_id">';
		echo '<option value="0">' . esc_html__( 'All organizers', 'taka-platform' ) . '</option>';
		foreach ( $organizers as $organizer ) {
			echo '<option value="' . esc_attr( (string) $organizer->ID ) . '" ' . selected( $selected, $organizer->ID, false ) . '>' . esc_html( get_the_title( $organizer ) ) . '</option>';
		}
		echo '</select></label> ';
		echo '<button class="button" type="submit">' . esc_html__( 'Filter', 'taka-platform' ) . '</button>';
		echo '</form>';
	}

	private static function render_order_revenue_summary( $orders ) {
		$paid = array();
		$pending = array();
		$refunded = array();
		foreach ( (array) $orders as $order ) {
			$data = $order instanceof TAKA_Ticketing_Order ? $order->to_array() : array();
			$currency = TAKA_Platform_Data::normalize_event_option_value( 'currency', $data['currency'] ?? 'EUR' ) ?: 'EUR';
			$amount = TAKA_Ticketing_Pricing_Service::money_to_float( $data['amount'] ?? $data['final_amount'] ?? '0' );
			$status = sanitize_key( $data['payment_status'] ?? 'pending' );
			if ( 'paid' === $status ) {
				self::add_money_total( $paid, $currency, $amount );
			} elseif ( 'refunded' === $status ) {
				self::add_money_total( $refunded, $currency, $amount );
			} elseif ( ! in_array( $status, array( 'cancelled' ), true ) ) {
				self::add_money_total( $pending, $currency, $amount );
			}
		}
		echo '<div class="taka-finance-metrics taka-ticketing-order-metrics">';
		self::metric_card( __( 'Orders', 'taka-platform' ), (string) count( (array) $orders ) );
		self::metric_card( __( 'Paid revenue', 'taka-platform' ), self::format_money_totals( $paid ) );
		self::metric_card( __( 'Outstanding payments', 'taka-platform' ), self::format_money_totals( $pending ) );
		self::metric_card( __( 'Refunded', 'taka-platform' ), self::format_money_totals( $refunded ) );
		echo '</div>';
	}

	private static function add_money_total( &$totals, $currency, $amount ) {
		$currency = TAKA_Platform_Data::normalize_event_option_value( 'currency', $currency ?: 'EUR' ) ?: 'EUR';
		if ( ! isset( $totals[ $currency ] ) ) {
			$totals[ $currency ] = 0.0;
		}
		$totals[ $currency ] += (float) $amount;
	}

	private static function format_money_totals( $totals ) {
		if ( empty( $totals ) ) {
			return self::format_money( '0', 'EUR' );
		}
		$out = array();
		foreach ( $totals as $currency => $amount ) {
			$out[] = self::format_money( (string) round( (float) $amount, 2 ), $currency );
		}
		return implode( ' / ', $out );
	}

	private static function metric_card( $label, $value ) {
		echo '<div class="taka-finance-card"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></div>';
	}

	private static function organizer_titles_list( $organizer_ids ) {
		$titles = array();
		foreach ( array_filter( array_map( 'absint', (array) $organizer_ids ) ) as $organizer_id ) {
			$title = get_the_title( $organizer_id );
			if ( '' !== trim( (string) $title ) ) {
				$titles[] = $title;
			}
		}
		return implode( ', ', $titles );
	}

	private static function render_order_list() {
		$orders = self::orders_visible_to_current_user( self::order_repository()->query( array( 'per_page' => 100 ) ) );
		?>
		<h2><?php echo esc_html__( 'Orders', 'taka-platform' ); ?></h2>
		<?php self::render_order_organizer_filter(); ?>
		<?php self::render_order_revenue_summary( $orders ); ?>
		<table class="widefat striped">
			<thead><tr>
				<th><?php echo esc_html__( 'Order number', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Date', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Buyer', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Participant', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Event', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Organizer', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Ticket', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Promotion', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Amount', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Payment Method', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Payment status', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Order status', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Actions', 'taka-platform' ); ?></th>
			</tr></thead>
			<tbody>
				<?php if ( empty( $orders ) ) : ?>
					<tr><td colspan="13"><?php echo esc_html__( 'No native ticket orders yet.', 'taka-platform' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $orders as $order ) : ?>
					<?php $data = $order->to_array(); $buyer = (array) ( $data['buyer'] ?? array() ); $participant = (array) ( $data['participant'] ?? array() ); $organizer_id = self::order_billing_organizer_id( $order ); ?>
					<tr>
						<td><a href="<?php echo esc_url( self::admin_url( array( 'order_id' => $order->get( 'id' ) ) ) ); ?>"><?php echo esc_html( $data['order_number'] ?? '' ); ?></a></td>
						<td><?php echo esc_html( $data['created_at'] ?? '' ); ?></td>
						<td><?php echo self::person_admin_link_or_text( $data['buyer_person_id'] ?? 0, trim( ( $buyer['first_name'] ?? '' ) . ' ' . ( $buyer['last_name'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td><?php echo self::person_admin_link_or_text( $data['participant_person_id'] ?? 0, trim( ( $participant['first_name'] ?? '' ) . ' ' . ( $participant['last_name'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td><?php echo esc_html( $data['event_title'] ?? '' ); ?></td>
						<td><?php echo esc_html( $organizer_id ? get_the_title( $organizer_id ) : ( $data['organizer_name'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( self::order_items_summary( $data ) ); ?></td>
						<td><?php echo esc_html( $data['applied_voucher_code'] ?? '' ); ?></td>
						<td><?php echo esc_html( self::format_money( $data['amount'] ?? '', $data['currency'] ?? 'EUR' ) ); ?></td>
						<td><?php echo esc_html( self::payment_method_admin_label( $data['payment_method'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( $data['payment_status'] ?? '' ); ?></td>
						<td><?php echo esc_html( $data['order_status'] ?? '' ); ?></td>
						<td><a class="button button-small" href="<?php echo esc_url( self::admin_url( array( 'order_id' => $order->get( 'id' ) ) ) ); ?>"><?php echo esc_html__( 'Open', 'taka-platform' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private static function render_order_detail( $order_id ) {
		$order = self::order_repository()->find_by_id( $order_id );
		if ( ! $order ) {
			echo '<p>' . esc_html__( 'Order not found.', 'taka-platform' ) . '</p>';
			return;
		}
		if ( ! self::current_user_can_access_order( $order ) ) {
			echo '<p>' . esc_html__( 'You do not have access to this order.', 'taka-platform' ) . '</p>';
			return;
		}
		$data = $order->to_array();
		$buyer = (array) ( $data['buyer'] ?? array() );
		$participant = (array) ( $data['participant'] ?? array() );
		$participants = is_array( $data['participants'] ?? null ) ? array_values( $data['participants'] ) : array();
		$organizer_id = self::order_billing_organizer_id( $order );
		$show_dietary = ! empty( $data['dietary_preferences_enabled'] );
		?>
		<p><a href="<?php echo esc_url( self::admin_url() ); ?>">&larr; <?php echo esc_html__( 'Back to orders', 'taka-platform' ); ?></a></p>
		<h2><?php echo esc_html( $data['order_number'] ?? '' ); ?></h2>
		<div class="taka-ticketing-admin-detail">
			<section><h3><?php echo esc_html__( 'Buyer', 'taka-platform' ); ?></h3><?php self::admin_person_reference( $data['buyer_person_id'] ?? 0, $buyer ); ?><?php self::admin_person_details( $buyer, $show_dietary ); ?></section>
			<section><h3><?php echo esc_html__( 'Participant', 'taka-platform' ); ?></h3><?php self::admin_person_reference( $data['participant_person_id'] ?? 0, $participant ); ?><?php self::admin_person_details( $participant, $show_dietary ); ?></section>
			<?php if ( count( $participants ) > 1 ) : ?>
				<section><h3><?php echo esc_html__( 'Participants', 'taka-platform' ); ?></h3>
					<ol>
						<?php foreach ( $participants as $index => $item ) : ?>
							<?php $person_id = absint( (array_values( (array) ( $data['participant_person_ids'] ?? array() ) )[ $index ] ) ?? 0 ); ?>
							<li><?php echo self::person_admin_link_or_text( $person_id, trim( ( $item['first_name'] ?? '' ) . ' ' . ( $item['last_name'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php if ( ! empty( $item['email'] ) ) : ?> &lt;<?php echo esc_html( $item['email'] ); ?>&gt;<?php endif; ?></li>
						<?php endforeach; ?>
					</ol>
				</section>
			<?php endif; ?>
			<section><h3><?php echo esc_html__( 'Order', 'taka-platform' ); ?></h3>
				<p><strong><?php echo esc_html__( 'Event', 'taka-platform' ); ?>:</strong> <?php echo esc_html( $data['event_title'] ?? '' ); ?></p>
				<p><strong><?php echo esc_html__( 'Billing organizer', 'taka-platform' ); ?>:</strong> <?php echo esc_html( $organizer_id ? get_the_title( $organizer_id ) : ( $data['organizer_name'] ?? '' ) ); ?></p>
				<?php if ( '' !== trim( (string) ( $data['ticket_type_name'] ?? '' ) ) ) : ?><p><strong><?php echo esc_html__( 'Ticket', 'taka-platform' ); ?>:</strong> <?php echo esc_html( $data['ticket_type_name'] ?? '' ); ?></p><?php endif; ?>
				<?php if ( ! empty( $data['line_items'] ) && is_array( $data['line_items'] ) ) : ?>
					<p><strong><?php echo esc_html__( 'Line items', 'taka-platform' ); ?>:</strong></p>
					<ul><?php foreach ( $data['line_items'] as $item ) : ?><li><?php echo esc_html( self::line_item_label( $item ) ); ?></li><?php endforeach; ?></ul>
				<?php endif; ?>
				<?php if ( ! empty( $data['ticket_artifacts']['tickets'] ) && is_array( $data['ticket_artifacts']['tickets'] ) ) : ?>
					<p><strong><?php echo esc_html__( 'Issued tickets', 'taka-platform' ); ?>:</strong></p>
					<ul>
						<?php foreach ( $data['ticket_artifacts']['tickets'] as $ticket ) : ?>
							<li>
								<?php echo esc_html( trim( ( $ticket['ticket_id'] ?? '' ) . ' - ' . ( $ticket['title'] ?? '' ) ) ); ?>
								<?php if ( '' !== trim( (string) ( $ticket['recipient_email'] ?? '' ) ) ) : ?>
									<?php echo esc_html( ' <' . $ticket['recipient_email'] . '>' ); ?>
								<?php endif; ?>
								<br><code><?php echo esc_html( $ticket['payload'] ?? '' ); ?></code>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( '' !== trim( (string) ( $data['applied_voucher_code'] ?? '' ) ) ) : ?>
					<p><strong><?php echo esc_html__( 'Voucher applied', 'taka-platform' ); ?>:</strong> <?php echo esc_html( $data['applied_voucher_code'] ); ?></p>
					<p><strong><?php echo esc_html__( 'Original amount', 'taka-platform' ); ?>:</strong> <?php echo esc_html( self::format_money( $data['original_amount'] ?? $data['amount'] ?? '', $data['currency'] ?? 'EUR' ) ); ?></p>
					<p><strong><?php echo esc_html__( 'Discount', 'taka-platform' ); ?>:</strong> <?php echo esc_html( self::format_money( $data['discount_amount'] ?? '0', $data['currency'] ?? 'EUR' ) ); ?></p>
				<?php endif; ?>
				<p><strong><?php echo esc_html__( 'Amount', 'taka-platform' ); ?>:</strong> <?php echo esc_html( self::format_money( $data['amount'] ?? '', $data['currency'] ?? 'EUR' ) ); ?></p>
				<?php if ( ! empty( $data['applied_benefits'] ) && is_array( $data['applied_benefits'] ) ) : ?>
					<p><strong><?php echo esc_html__( 'Benefits', 'taka-platform' ); ?>:</strong></p>
					<ul><?php foreach ( $data['applied_benefits'] as $benefit ) : ?><li><?php echo esc_html( self::benefit_line( $benefit ) ); ?></li><?php endforeach; ?></ul>
				<?php endif; ?>
				<p><strong><?php echo esc_html__( 'Order status', 'taka-platform' ); ?>:</strong> <?php echo esc_html( $data['order_status'] ?? '' ); ?></p>
				<p><strong><?php echo esc_html__( 'Payment status', 'taka-platform' ); ?>:</strong> <?php echo esc_html( $data['payment_status'] ?? '' ); ?></p>
				<p><strong><?php echo esc_html__( 'Payment Method', 'taka-platform' ); ?>:</strong> <?php echo esc_html( self::payment_method_admin_label( $data['payment_method'] ?? '' ) ); ?></p>
				<?php if ( ! empty( $data['payment'] ) && is_array( $data['payment'] ) ) : ?>
					<?php if ( ! empty( $data['payment']['paypal_order_id'] ) ) : ?><p><strong><?php echo esc_html__( 'PayPal order ID', 'taka-platform' ); ?>:</strong> <?php echo esc_html( $data['payment']['paypal_order_id'] ); ?></p><?php endif; ?>
					<?php if ( ! empty( $data['payment']['transaction_id'] ) ) : ?><p><strong><?php echo esc_html__( 'Transaction ID', 'taka-platform' ); ?>:</strong> <?php echo esc_html( $data['payment']['transaction_id'] ); ?></p><?php endif; ?>
					<?php if ( ! empty( $data['payment']['refund_id'] ) ) : ?><p><strong><?php echo esc_html__( 'Refund ID', 'taka-platform' ); ?>:</strong> <?php echo esc_html( $data['payment']['refund_id'] ); ?></p><?php endif; ?>
					<?php if ( ! empty( $data['payment']['refund_status'] ) ) : ?><p><strong><?php echo esc_html__( 'Refund status', 'taka-platform' ); ?>:</strong> <?php echo esc_html( $data['payment']['refund_status'] ); ?></p><?php endif; ?>
				<?php endif; ?>
				<p><strong><?php echo esc_html__( 'Check-in', 'taka-platform' ); ?>:</strong> <?php echo esc_html( $data['checkin_status'] ?? 'not_checked_in' ); ?></p>
			</section>
			<section><h3><?php echo esc_html__( 'Timeline', 'taka-platform' ); ?></h3>
				<ul><?php foreach ( (array) ( $data['timeline'] ?? array() ) as $item ) : ?><li><?php echo esc_html( ( $item['time'] ?? '' ) . ' - ' . ( $item['label'] ?? '' ) ); ?></li><?php endforeach; ?></ul>
			</section>
		</div>
		<div class="taka-ticketing-admin-actions">
			<?php if ( current_user_can( 'edit_taka_orders' ) && self::current_user_can_access_order( $order, true ) ) : ?>
				<?php self::admin_action_form( $order_id, 'mark_paid', __( 'Mark Paid', 'taka-platform' ), 'button-primary' ); ?>
				<?php if ( self::order_can_refund_paypal( $data ) ) : ?>
					<?php self::admin_action_form( $order_id, 'refund', __( 'Refund PayPal payment', 'taka-platform' ), '', __( 'Refund this PayPal payment and cancel the order?', 'taka-platform' ) ); ?>
				<?php endif; ?>
				<?php self::admin_action_form( $order_id, 'cancel', __( 'Cancel', 'taka-platform' ), '' ); ?>
				<?php self::admin_action_form( $order_id, 'delete', __( 'Delete', 'taka-platform' ), 'button-link-delete' ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function admin_person_details( $person, $show_dietary = true ) {
		foreach ( $person as $key => $value ) {
			if ( ! $show_dietary && in_array( (string) $key, array( 'dietary_preference', 'dietary_notes', 'allergies' ), true ) ) {
				continue;
			}
			if ( '' === trim( (string) $value ) ) {
				continue;
			}
			echo '<p><strong>' . esc_html( ucwords( str_replace( '_', ' ', $key ) ) ) . ':</strong> ' . esc_html( $value ) . '</p>';
		}
	}

	private static function admin_person_reference( $person_id, $person ) {
		$label = trim( (string) ( $person['first_name'] ?? '' ) . ' ' . (string) ( $person['last_name'] ?? '' ) );
		$link = self::person_admin_link_or_text( $person_id, $label );
		if ( '' !== trim( wp_strip_all_tags( $link ) ) ) {
			echo '<p><strong>' . esc_html__( 'Person profile', 'taka-platform' ) . ':</strong> ' . $link . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	private static function person_admin_link_or_text( $person_id, $fallback ) {
		$fallback = sanitize_text_field( $fallback );
		if ( class_exists( 'TAKA_People_Module' ) && current_user_can( 'view_taka_people' ) ) {
			$link = TAKA_People_Module::person_link( absint( $person_id ), $fallback );
			if ( '' !== $link ) {
				return $link;
			}
		}
		return esc_html( $fallback );
	}

	private static function order_can_refund_paypal( $data ) {
		$data = is_array( $data ) ? $data : array();
		$payment = is_array( $data['payment'] ?? null ) ? $data['payment'] : array();
		return 'paypal' === (string) ( $data['payment_method'] ?? '' )
			&& 'paid' === (string) ( $data['payment_status'] ?? '' )
			&& '' !== trim( (string) ( $payment['transaction_id'] ?? '' ) );
	}

	public static function benefit_line( $benefit ) {
		$benefit = is_array( $benefit ) ? $benefit : array();
		$label = sanitize_text_field( $benefit['label'] ?? TAKA_Ticketing_Promotion::frontend_benefit_label( sanitize_key( $benefit['type'] ?? '' ) ) );
		$value = sanitize_text_field( $benefit['value'] ?? '' );
		$note = sanitize_text_field( $benefit['note'] ?? '' );
		$parts = array_filter( array( $label, $value, $note ), static function ( $part ) { return '' !== trim( (string) $part ); } );
		return implode( ' - ', $parts );
	}

	public static function line_item_label( $item ) {
		$item = is_array( $item ) ? $item : array();
		$title = sanitize_text_field( $item['title'] ?? '' );
		$quantity = max( 1, absint( $item['quantity'] ?? 1 ) );
		$total = self::format_money( $item['total_price'] ?? '0', $item['currency'] ?? 'EUR' );
		if ( 'discount' === (string) ( $item['item_type'] ?? '' ) ) {
			return trim( $title . ' - ' . $total );
		}
		if ( '' === $title ) {
			$title = __( 'Order item', 'taka-platform' );
		}
		return trim( $quantity . ' x ' . $title . ' - ' . $total );
	}

	private static function order_items_summary( $data ) {
		if ( '' !== trim( (string) ( $data['ticket_type_name'] ?? '' ) ) ) {
			return (string) $data['ticket_type_name'];
		}
		$items = is_array( $data['line_items'] ?? null ) ? $data['line_items'] : array();
		$titles = array();
		foreach ( $items as $item ) {
			if ( '' !== trim( (string) ( $item['title'] ?? '' ) ) ) {
				$titles[] = (string) $item['title'];
			}
		}
		return implode( ', ', array_slice( $titles, 0, 3 ) );
	}

	private static function admin_action_form( $order_id, $task, $label, $class, $confirm = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="taka-ticketing-admin-action">';
		wp_nonce_field( self::ADMIN_ACTION );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ADMIN_ACTION ) . '">';
		echo '<input type="hidden" name="order_id" value="' . esc_attr( (string) absint( $order_id ) ) . '">';
		echo '<input type="hidden" name="task" value="' . esc_attr( $task ) . '">';
		echo '<button type="submit" class="button ' . esc_attr( $class ) . '"' . ( '' !== (string) $confirm ? ' onclick="return confirm(\'' . esc_js( $confirm ) . '\');"' : '' ) . '>' . esc_html( $label ) . '</button>';
		echo '</form>';
	}

	public static function register_event_assistant_section( $sections ) {
		if ( ! class_exists( 'TAKA_Platform_Admin_Event_Assistant_Section' ) ) {
			return $sections;
		}

		$sections[] = new TAKA_Platform_Admin_Event_Assistant_Section(
			array(
				'id'                => 'native-ticketing',
				'title'             => __( 'Native TAKA Ticketing', 'taka-platform' ),
				'help_text'         => __( 'Ticket type readiness for the native checkout and payment-provider workflow.', 'taka-platform' ),
				'default_state'     => TAKA_Platform_Admin_Collapsible_Section::STATE_COLLAPSED,
				'weight'            => 5,
				'render_callback'   => array( __CLASS__, 'render_event_assistant_section' ),
				'required_callback' => array( __CLASS__, 'missing_native_ticket_types' ),
			)
		);

		return $sections;
	}

	public static function render_event_assistant_section( $context ) {
		$mode = self::ticket_mode_for_context( $context );
		$ticket_types = is_array( $context['native_ticket_types'] ?? null ) ? $context['native_ticket_types'] : array();
		$count = count( $ticket_types );

		if ( self::MODE !== $mode ) {
			echo '<p class="description">' . esc_html__( 'Native ticketing is inactive for this event. Select Native TAKA Ticketing in the Tickets section when this event should use the built-in checkout.', 'taka-platform' ) . '</p>';
			return;
		}

		printf(
			'<p>%s</p>',
			esc_html( sprintf( _n( '%d native ticket type is configured.', '%d native ticket types are configured.', $count, 'taka-platform' ), $count ) )
		);
		if ( ! empty( $context['post_id'] ) ) {
			$product_count = count( self::product_repository()->checkout_add_ons_for_event( absint( $context['post_id'] ) ) );
			printf(
				'<p>%s</p>',
				esc_html( sprintf( _n( '%d optional checkout add-on is attached.', '%d optional checkout add-ons are attached.', $product_count, 'taka-platform' ), $product_count ) )
			);
		}

		if ( empty( $context['post_id'] ) ) {
			echo '<p class="description">' . esc_html__( 'Save the draft first, then configure repeatable ticket types and payment methods in the shared Event editor section.', 'taka-platform' ) . '</p>';
			return;
		}

		$url = get_edit_post_link( absint( $context['post_id'] ), '' );
		if ( $url ) {
			echo '<p><a class="button" href="' . esc_url( $url . '#taka-native-ticketing-section' ) . '">' . esc_html__( 'Edit native ticket types', 'taka-platform' ) . '</a></p>';
		}
		echo '<p><a class="button" href="' . esc_url( self::admin_url( array( 'section' => 'products' ) ) ) . '">' . esc_html__( 'Manage add-on products', 'taka-platform' ) . '</a></p>';
	}

	public static function missing_native_ticket_types( $context ) {
		if ( self::MODE !== self::ticket_mode_for_context( $context ) ) {
			return array();
		}

		$ticket_types = is_array( $context['native_ticket_types'] ?? null ) ? $context['native_ticket_types'] : array();
		return empty( $ticket_types ) ? array( __( 'At least one native ticket type', 'taka-platform' ) ) : array();
	}

	private static function ticket_mode_for_context( $context ) {
		$values = is_array( $context['values'] ?? null ) ? $context['values'] : array();
		return TAKA_Platform_Data::ticket_mode_for_event(
			array(
				'ticket_mode'     => $values['ticket_mode'] ?? '',
				'ticket_status'   => $values['ticket_status'] ?? '',
				'ticket_provider' => $values['ticket_provider'] ?? '',
				'ticket_shop_url' => $values['ticket_shop_url'] ?? '',
			)
		);
	}
}

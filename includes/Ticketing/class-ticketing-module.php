<?php
/**
 * Native TAKA Ticketing module.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Ticketing_Module {
	const MODE                 = 'native_taka_ticketing';
	const BANK_TRANSFER_OPTION = 'taka_ticketing_bank_transfer_settings';
	const PAYMENT_METHODS_META = '_taka_native_payment_methods';
	const BANK_TRANSFER_META   = '_taka_native_bank_transfer_settings';
	const PAY_AT_DOOR_INSTRUCTIONS_META = '_taka_native_pay_at_door_instructions';
	const CHECKOUT_ACTION      = 'taka_ticketing_checkout';
	const ADMIN_ACTION         = 'taka_ticketing_order_action';
	const ADMIN_PAGE_SLUG      = 'taka-platform-ticketing';

	private static $payment_providers = array();
	private static $order_repository = null;

	/** Register native ticketing hooks and provider implementations. */
	public static function init() {
		self::register_payment_provider( new TAKA_Ticketing_Bank_Transfer_Provider() );
		self::register_payment_provider( new TAKA_Ticketing_Pay_At_Door_Provider() );
		add_action( 'init', array( __CLASS__, 'register_post_types' ), 0 );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'ensure_capabilities' ) );
		add_action( 'admin_post_' . self::CHECKOUT_ACTION, array( __CLASS__, 'handle_checkout' ) );
		add_action( 'admin_post_nopriv_' . self::CHECKOUT_ACTION, array( __CLASS__, 'handle_checkout' ) );
		add_action( 'admin_post_' . self::ADMIN_ACTION, array( __CLASS__, 'handle_admin_order_action' ) );
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

	public static function enabled_payment_methods_for_event( $event_id ) {
		$stored = get_post_meta( absint( $event_id ), self::PAYMENT_METHODS_META, true );
		$items = is_array( $stored ) ? $stored : preg_split( '/\s*,\s*/', (string) $stored );
		$items = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $items ) ) ) );
		if ( empty( $items ) ) {
			$items = array( 'bank_transfer' );
		}
		return array_values( array_filter( $items, static function ( $method ) { return isset( self::$payment_providers[ $method ] ); } ) );
	}

	public static function event_bank_transfer_settings( $event_id ) {
		$stored = get_post_meta( absint( $event_id ), self::BANK_TRANSFER_META, true );
		return self::normalize_bank_transfer_settings( is_array( $stored ) ? $stored : array() );
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
		$enabled = self::enabled_payment_methods_for_event( $post_id );
		$bank = self::event_bank_transfer_settings( $post_id );
		$pay_at_door_instructions = (string) get_post_meta( $post_id, self::PAY_AT_DOOR_INSTRUCTIONS_META, true );
		?>
		<div class="taka-native-payment-settings">
			<h3><?php echo esc_html__( 'Payment methods', 'taka-platform' ); ?></h3>
			<p class="description"><?php echo esc_html__( 'Choose which native payment methods visitors may select for this event.', 'taka-platform' ); ?></p>
			<div class="taka-native-payment-settings__methods">
				<?php foreach ( self::payment_providers() as $provider_id => $provider ) : ?>
					<label><input type="checkbox" name="taka_native_payment_methods[]" value="<?php echo esc_attr( $provider_id ); ?>" <?php checked( in_array( $provider_id, $enabled, true ) ); ?>> <?php echo esc_html( self::payment_method_label( $provider_id ) ); ?></label>
				<?php endforeach; ?>
			</div>
			<div class="taka-native-payment-settings__grid">
				<?php self::payment_input( 'taka_native_bank_transfer', 'account_holder', __( 'Account holder', 'taka-platform' ), $bank['account_holder'] ?? '' ); ?>
				<?php self::payment_input( 'taka_native_bank_transfer', 'iban', __( 'IBAN', 'taka-platform' ), $bank['iban'] ?? '' ); ?>
				<?php self::payment_input( 'taka_native_bank_transfer', 'bic', __( 'BIC', 'taka-platform' ), $bank['bic'] ?? '' ); ?>
				<?php self::payment_input( 'taka_native_bank_transfer', 'bank_name', __( 'Bank name', 'taka-platform' ), $bank['bank_name'] ?? '' ); ?>
				<?php self::payment_input( 'taka_native_bank_transfer', 'payment_reference_template', __( 'Payment reference template', 'taka-platform' ), $bank['payment_reference_template'] ?? 'TAKA-{order_number}' ); ?>
				<label class="taka-native-ticket-type__wide"><strong><?php echo esc_html__( 'Bank transfer instructions', 'taka-platform' ); ?></strong><textarea class="widefat" rows="3" name="taka_native_bank_transfer[instructions_text]"><?php echo esc_textarea( $bank['instructions_text'] ?? '' ); ?></textarea></label>
				<label class="taka-native-ticket-type__wide"><strong><?php echo esc_html__( 'Pay-at-the-door instructions', 'taka-platform' ); ?></strong><textarea class="widefat" rows="3" name="taka_native_pay_at_door_instructions"><?php echo esc_textarea( $pay_at_door_instructions ); ?></textarea><span class="description"><?php echo esc_html__( 'Optional event-specific note, for example cash only or card accepted.', 'taka-platform' ); ?></span></label>
			</div>
		</div>
		<?php
	}

	private static function payment_input( $prefix, $field, $label, $value ) {
		echo '<label><strong>' . esc_html( $label ) . '</strong><input class="widefat" type="text" name="' . esc_attr( $prefix . '[' . $field . ']' ) . '" value="' . esc_attr( (string) $value ) . '"></label>';
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
		if ( $order ) {
			self::render_order_confirmation( $order );
		} else {
			self::render_checkout_form( $event, $event_id );
		}
		echo '</div>';
		return ob_get_clean();
	}

	private static function render_checkout_form( $event, $event_id ) {
		$ticket_types = self::available_ticket_types_for_event( $event_id );
		$payment_methods = self::enabled_payment_methods_for_event( $event_id );
		$errors = self::checkout_errors_from_request();
		if ( empty( $ticket_types ) || empty( $payment_methods ) ) {
			echo '<div class="taka-ticket-status taka-ticket-status--boxed"><strong>' . esc_html( taka_tour_translate( 'ticketing.not_available', 'Native ticket booking is not available yet.' ) ) . '</strong></div>';
			return;
		}
		$form_id = 'taka-native-checkout-form-' . absint( $event_id );
		$single_ticket = 1 === count( $ticket_types );
		$event_title = (string) ( $event['title'] ?? get_the_title( $event_id ) );
		?>
		<button class="taka-native-checkout__toggle" type="button" data-taka-native-checkout-toggle aria-expanded="<?php echo empty( $errors ) ? 'false' : 'true'; ?>" aria-controls="<?php echo esc_attr( $form_id ); ?>"><?php echo esc_html( taka_tour_translate( 'ticketing.book_tickets', 'Book Tickets' ) ); ?></button>
		<form id="<?php echo esc_attr( $form_id ); ?>" class="taka-native-checkout__form<?php echo empty( $errors ) ? '' : ' is-open'; ?>" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" <?php echo empty( $errors ) ? 'hidden' : ''; ?>>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::CHECKOUT_ACTION ); ?>">
			<input type="hidden" name="event_id" value="<?php echo esc_attr( (string) $event_id ); ?>">
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( self::current_url() ); ?>">
			<input type="hidden" name="taka_ticketing_nonce" value="<?php echo esc_attr( wp_create_nonce( self::CHECKOUT_ACTION ) ); ?>">
			<label class="taka-native-checkout__honeypot"><?php echo esc_html__( 'Website', 'taka-platform' ); ?><input type="text" name="company_website" value="" tabindex="-1" autocomplete="off"></label>
			<?php if ( ! empty( $errors ) ) : ?><div class="taka-native-checkout__errors" role="alert"><?php foreach ( $errors as $error ) : ?><p><?php echo esc_html( $error ); ?></p><?php endforeach; ?></div><?php endif; ?>
			<ol class="taka-native-checkout__progress" aria-label="<?php echo esc_attr( taka_tour_translate( 'ticketing.booking_steps', 'Booking steps' ) ); ?>">
				<li><?php echo esc_html( taka_tour_translate( 'ticketing.step_select_ticket', 'Select ticket' ) ); ?></li>
				<li><?php echo esc_html( taka_tour_translate( 'ticketing.step_participant', 'Participant' ) ); ?></li>
				<li><?php echo esc_html( taka_tour_translate( 'ticketing.step_review', 'Review' ) ); ?></li>
				<li><?php echo esc_html( taka_tour_translate( 'ticketing.step_confirmation', 'Confirmation' ) ); ?></li>
			</ol>
			<section class="taka-native-checkout__step">
				<h4><?php echo esc_html( taka_tour_translate( 'ticketing.select_ticket_type', 'Select ticket type' ) ); ?></h4>
				<div class="taka-native-ticket-options">
					<?php foreach ( $ticket_types as $index => $ticket_type ) : ?>
						<?php $availability = self::ticket_availability( $event_id, $ticket_type ); ?>
						<label class="taka-native-ticket-option">
							<input type="radio" name="ticket_type_id" value="<?php echo esc_attr( $ticket_type['id'] ); ?>" data-taka-ticket-name="<?php echo esc_attr( $ticket_type['name'] ); ?>" data-taka-ticket-price="<?php echo esc_attr( self::format_money( $ticket_type['price'], $ticket_type['currency'] ) ); ?>" <?php checked( $single_ticket || 0 === $index ); ?> required>
							<span><strong><?php echo esc_html( $ticket_type['name'] ); ?></strong><?php if ( '' !== trim( (string) $ticket_type['description'] ) ) : ?><em><?php echo esc_html( $ticket_type['description'] ); ?></em><?php endif; ?></span>
							<span><?php echo esc_html( self::format_money( $ticket_type['price'], $ticket_type['currency'] ) ); ?></span>
							<small><?php echo esc_html( self::availability_label( $availability ) ); ?></small>
						</label>
					<?php endforeach; ?>
				</div>
			</section>
			<section class="taka-native-checkout__step">
				<h4><?php echo esc_html( taka_tour_translate( 'ticketing.buyer_information', 'Buyer information' ) ); ?></h4>
				<div class="taka-native-checkout__grid">
					<?php self::frontend_input( 'buyer_first_name', taka_tour_translate( 'ticketing.first_name', 'First name' ), 'text', true ); ?>
					<?php self::frontend_input( 'buyer_last_name', taka_tour_translate( 'ticketing.last_name', 'Last name' ), 'text', true ); ?>
					<?php self::frontend_input( 'buyer_email', taka_tour_translate( 'ticketing.email', 'Email' ), 'email', true ); ?>
					<?php self::frontend_input( 'buyer_country', taka_tour_translate( 'ticketing.country', 'Country' ), 'text', true ); ?>
					<?php self::frontend_input( 'buyer_phone', taka_tour_translate( 'ticketing.phone', 'Phone' ), 'text', false ); ?>
					<?php self::frontend_input( 'buyer_company', taka_tour_translate( 'ticketing.company', 'Company' ), 'text', false ); ?>
				</div>
			</section>
			<section class="taka-native-checkout__step">
				<h4><?php echo esc_html( taka_tour_translate( 'ticketing.participant_information', 'Participant information' ) ); ?></h4>
				<label class="taka-native-checkout__checkbox"><input type="checkbox" name="participant_is_buyer" value="1" checked data-taka-participant-self> <?php echo esc_html( taka_tour_translate( 'ticketing.participating_myself', 'I am participating myself.' ) ); ?></label>
				<div class="taka-native-checkout__grid" data-taka-participant-fields>
					<?php self::frontend_input( 'participant_first_name', taka_tour_translate( 'ticketing.first_name', 'First name' ), 'text', false ); ?>
					<?php self::frontend_input( 'participant_last_name', taka_tour_translate( 'ticketing.last_name', 'Last name' ), 'text', false ); ?>
					<?php self::frontend_input( 'participant_email', taka_tour_translate( 'ticketing.email_optional', 'Email (optional)' ), 'email', false ); ?>
					<?php self::frontend_input( 'participant_country', taka_tour_translate( 'ticketing.country', 'Country' ), 'text', false ); ?>
					<?php self::frontend_input( 'participant_dojo', taka_tour_translate( 'ticketing.dojo', 'Dojo / Club' ), 'text', false ); ?>
					<?php self::frontend_input( 'participant_association', taka_tour_translate( 'ticketing.association', 'Association' ), 'text', false ); ?>
					<?php self::frontend_input( 'participant_style', taka_tour_translate( 'ticketing.style', 'Style' ), 'text', false ); ?>
					<?php self::frontend_input( 'participant_rank', taka_tour_translate( 'ticketing.rank', 'Rank / Belt' ), 'text', false ); ?>
					<?php self::frontend_textarea( 'participant_dietary_notes', taka_tour_translate( 'ticketing.dietary_notes', 'Dietary notes' ) ); ?>
					<?php self::frontend_textarea( 'participant_allergies', taka_tour_translate( 'ticketing.allergies', 'Allergies' ) ); ?>
					<?php self::frontend_textarea( 'participant_notes', taka_tour_translate( 'ticketing.notes', 'Notes' ) ); ?>
				</div>
			</section>
			<section class="taka-native-checkout__step">
				<h4><?php echo esc_html( taka_tour_translate( 'ticketing.payment_method', 'Payment method' ) ); ?></h4>
				<div class="taka-native-payment-options">
					<?php foreach ( $payment_methods as $index => $method ) : ?>
						<label><input type="radio" name="payment_method" value="<?php echo esc_attr( $method ); ?>" data-taka-payment-label="<?php echo esc_attr( self::payment_method_label( $method ) ); ?>" <?php checked( 0 === $index ); ?> required> <?php echo esc_html( self::payment_method_label( $method ) ); ?></label>
					<?php endforeach; ?>
				</div>
			</section>
			<section class="taka-native-checkout__step taka-native-checkout__review" data-taka-checkout-review>
				<h4><?php echo esc_html( taka_tour_translate( 'ticketing.review_order', 'Review order' ) ); ?></h4>
				<dl>
					<div><dt><?php echo esc_html( taka_tour_translate( 'ticketing.event', 'Event' ) ); ?></dt><dd><?php echo esc_html( $event_title ); ?></dd></div>
					<div><dt><?php echo esc_html( taka_tour_translate( 'ticketing.ticket', 'Ticket' ) ); ?></dt><dd data-taka-review-ticket>-</dd></div>
					<div><dt><?php echo esc_html( taka_tour_translate( 'ticketing.price', 'Price' ) ); ?></dt><dd data-taka-review-price>-</dd></div>
					<div><dt><?php echo esc_html( taka_tour_translate( 'ticketing.buyer', 'Buyer' ) ); ?></dt><dd data-taka-review-buyer>-</dd></div>
					<div><dt><?php echo esc_html( taka_tour_translate( 'ticketing.participant', 'Participant' ) ); ?></dt><dd data-taka-review-participant>-</dd></div>
					<div><dt><?php echo esc_html( taka_tour_translate( 'ticketing.payment_method', 'Payment method' ) ); ?></dt><dd data-taka-review-payment>-</dd></div>
					<div><dt><?php echo esc_html( taka_tour_translate( 'ticketing.total', 'Total' ) ); ?></dt><dd data-taka-review-total>-</dd></div>
				</dl>
				<label class="taka-native-checkout__checkbox"><input type="checkbox" name="terms_accepted" value="1" required> <?php echo esc_html( taka_tour_translate( 'ticketing.accept_terms', 'I accept the booking terms.' ) ); ?></label>
				<label class="taka-native-checkout__checkbox"><input type="checkbox" name="privacy_accepted" value="1" required> <?php echo esc_html( taka_tour_translate( 'ticketing.accept_privacy', 'I accept the privacy notice.' ) ); ?></label>
				<button class="taka-native-checkout__submit" type="submit"><?php echo esc_html( taka_tour_translate( 'ticketing.submit_order', 'Submit Order' ) ); ?></button>
			</section>
		</form>
		<?php
	}

	private static function frontend_input( $name, $label, $type, $required ) {
		echo '<label><span>' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" ' . ( $required ? 'required' : '' ) . '></label>';
	}

	private static function frontend_textarea( $name, $label ) {
		echo '<label class="taka-native-checkout__wide"><span>' . esc_html( $label ) . '</span><textarea name="' . esc_attr( $name ) . '" rows="2"></textarea></label>';
	}

	public static function handle_checkout() {
		$redirect = esc_url_raw( wp_unslash( $_POST['redirect_to'] ?? wp_get_referer() ) );
		$redirect = '' !== $redirect ? $redirect : home_url( '/' );

		if ( ! empty( $_POST['company_website'] ) ) {
			wp_safe_redirect( $redirect );
			exit;
		}
		if ( empty( $_POST['taka_ticketing_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['taka_ticketing_nonce'] ) ), self::CHECKOUT_ACTION ) ) {
			self::redirect_with_errors( $redirect, array( __( 'Your booking session expired. Please try again.', 'taka-platform' ) ) );
		}

		$order = TAKA_Ticketing_Order_Service::create_order_from_post( wp_unslash( $_POST ) );
		if ( is_wp_error( $order ) ) {
			self::redirect_with_errors( $redirect, $order->get_error_messages() );
		}

		wp_safe_redirect( add_query_arg( 'taka_ticket_order', rawurlencode( $order->get( 'public_token' ) ), $redirect ) );
		exit;
	}

	public static function handle_admin_order_action() {
		if ( ! current_user_can( 'edit_taka_orders' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::ADMIN_ACTION, '_wpnonce' );
		$order_id = absint( $_POST['order_id'] ?? 0 );
		$task = sanitize_key( wp_unslash( $_POST['task'] ?? '' ) );

		if ( 'mark_paid' === $task ) {
			TAKA_Ticketing_Order_Service::mark_paid( $order_id );
		} elseif ( 'cancel' === $task ) {
			TAKA_Ticketing_Order_Service::cancel( $order_id );
		} elseif ( 'delete' === $task ) {
			self::order_repository()->delete_order( $order_id );
			wp_safe_redirect( self::admin_url( array( 'deleted' => '1' ) ) );
			exit;
		}

		wp_safe_redirect( self::admin_url( array( 'order_id' => $order_id, 'updated' => '1' ) ) );
		exit;
	}

	private static function redirect_with_errors( $redirect, $messages ) {
		$key = wp_generate_password( 16, false, false );
		set_transient( 'taka_ticketing_errors_' . $key, array_values( array_map( 'sanitize_text_field', (array) $messages ) ), 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'taka_ticketing_error', rawurlencode( $key ), $redirect ) );
		exit;
	}

	private static function checkout_errors_from_request() {
		$key = sanitize_text_field( wp_unslash( $_GET['taka_ticketing_error'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $key ) {
			return array();
		}
		$messages = get_transient( 'taka_ticketing_errors_' . $key );
		delete_transient( 'taka_ticketing_errors_' . $key );
		return is_array( $messages ) ? $messages : array();
	}

	private static function order_from_request_for_event( $event_id ) {
		$token = sanitize_text_field( wp_unslash( $_GET['taka_ticket_order'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $token ) {
			return null;
		}
		$order = self::order_repository()->find_by_public_token( $token );
		return $order && absint( $order->get( 'event_id' ) ) === absint( $event_id ) ? $order : null;
	}

	private static function current_url() {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) );
		$uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
		return remove_query_arg( array( 'taka_ticket_order', 'taka_ticketing_error' ), $scheme . $host . $uri );
	}

	private static function render_order_confirmation( TAKA_Ticketing_Order $order ) {
		$data = $order->to_array();
		$provider = self::payment_provider( $data['payment_method'] ?? '' );
		$instructions = $provider ? $provider->get_public_instructions( $order ) : array();
		?>
		<section class="taka-native-confirmation">
			<h3><?php echo esc_html( taka_tour_translate( 'ticketing.registration_successful', 'Registration successful' ) ); ?></h3>
			<dl>
				<div><dt><?php echo esc_html( taka_tour_translate( 'ticketing.order_number', 'Order number' ) ); ?></dt><dd><?php echo esc_html( $data['order_number'] ?? '' ); ?></dd></div>
				<div><dt><?php echo esc_html( taka_tour_translate( 'ticketing.payment_method', 'Payment method' ) ); ?></dt><dd><?php echo esc_html( self::payment_method_label( $data['payment_method'] ?? '' ) ); ?></dd></div>
				<div><dt><?php echo esc_html( taka_tour_translate( 'ticketing.amount', 'Amount' ) ); ?></dt><dd><?php echo esc_html( self::format_money( $data['amount'] ?? '', $data['currency'] ?? 'EUR' ) ); ?></dd></div>
			</dl>
			<?php if ( 'bank_transfer' === (string) ( $data['payment_method'] ?? '' ) ) : ?>
				<div class="taka-native-confirmation__instructions">
					<h4><?php echo esc_html( taka_tour_translate( 'ticketing.bank_transfer_instructions', 'Bank transfer instructions' ) ); ?></h4>
					<?php foreach ( array( 'account_holder' => 'Account holder', 'iban' => 'IBAN', 'bic' => 'BIC', 'bank_name' => 'Bank name', 'payment_reference' => 'Payment reference' ) as $field => $label ) : ?>
						<?php if ( '' !== trim( (string) ( $instructions[ $field ] ?? '' ) ) ) : ?><p><strong><?php echo esc_html( taka_tour_translate( 'ticketing.' . $field, $label ) ); ?>:</strong> <?php echo esc_html( $instructions[ $field ] ); ?></p><?php endif; ?>
					<?php endforeach; ?>
					<?php if ( '' !== trim( (string) ( $instructions['instructions'] ?? '' ) ) ) : ?><p><?php echo esc_html( $instructions['instructions'] ); ?></p><?php endif; ?>
				</div>
			<?php elseif ( 'pay_at_door' === (string) ( $data['payment_method'] ?? '' ) ) : ?>
				<div class="taka-native-confirmation__instructions">
					<p><?php echo esc_html( $instructions['message'] ?? taka_tour_translate( 'ticketing.pay_at_door_message', 'Please pay your admission at the registration desk before entering the seminar. Payment is required before participation.' ) ); ?></p>
					<?php if ( '' !== trim( (string) ( $instructions['instructions'] ?? '' ) ) ) : ?><p><?php echo esc_html( $instructions['instructions'] ); ?></p><?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="taka-native-confirmation__actions">
				<button type="button" onclick="window.print()"><?php echo esc_html( taka_tour_translate( 'ticketing.print', 'Print' ) ); ?></button>
				<button type="button" disabled><?php echo esc_html( taka_tour_translate( 'ticketing.download_pdf', 'Download PDF' ) ); ?></button>
			</div>
		</section>
		<?php
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
			$reason = 'sold_out' === $status ? __( 'Sold out', 'taka-platform' ) : __( 'Unavailable', 'taka-platform' );
		}

		$now = current_time( 'timestamp' );
		$start = self::sale_timestamp( $ticket_type['sale_start_date'] ?? '', $ticket_type['sale_start_time'] ?? '00:00' );
		$end = self::sale_timestamp( $ticket_type['sale_end_date'] ?? '', $ticket_type['sale_end_time'] ?? '23:59' );
		if ( $available && $start && $now < $start ) {
			$available = false;
			$reason = __( 'Sales have not started yet.', 'taka-platform' );
		}
		if ( $available && $end && $now > $end ) {
			$available = false;
			$reason = __( 'Sales have ended.', 'taka-platform' );
		}

		$capacity = '' === trim( (string) ( $ticket_type['capacity'] ?? '' ) ) ? null : absint( $ticket_type['capacity'] );
		$reserved = self::order_repository()->count_reserved_for_ticket( $event_id, $ticket_type['id'] ?? '' );
		$remaining = null === $capacity ? null : max( 0, $capacity - $reserved );
		if ( $available && null !== $remaining && $remaining <= 0 ) {
			$available = false;
			$reason = __( 'Sold out', 'taka-platform' );
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

	public static function payment_method_label( $method ) {
		$labels = array(
			'bank_transfer' => __( 'Bank Transfer', 'taka-platform' ),
			'pay_at_door'   => __( 'Pay at the Door', 'taka-platform' ),
		);
		return $labels[ $method ] ?? sanitize_text_field( $method );
	}

	public static function payment_method_admin_label( $method ) {
		$icons = array(
			'bank_transfer' => '🏦',
			'pay_at_door'   => '💶',
		);
		return trim( ( $icons[ $method ] ?? '' ) . ' ' . self::payment_method_label( $method ) );
	}

	public static function admin_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::ADMIN_PAGE_SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'view_taka_orders' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}

		$order_id = absint( $_GET['order_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap taka-ticketing-admin"><h1>' . esc_html__( 'Ticketing', 'taka-platform' ) . '</h1>';
		if ( $order_id ) {
			self::render_order_detail( $order_id );
		} else {
			self::render_order_list();
		}
		echo '</div>';
	}

	private static function render_order_list() {
		$orders = self::order_repository()->query( array( 'per_page' => 100 ) );
		?>
		<h2><?php echo esc_html__( 'Orders', 'taka-platform' ); ?></h2>
		<table class="widefat striped">
			<thead><tr>
				<th><?php echo esc_html__( 'Order number', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Date', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Buyer', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Participant', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Event', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Ticket', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Amount', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Payment Method', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Payment status', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Order status', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Actions', 'taka-platform' ); ?></th>
			</tr></thead>
			<tbody>
				<?php if ( empty( $orders ) ) : ?>
					<tr><td colspan="11"><?php echo esc_html__( 'No native ticket orders yet.', 'taka-platform' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $orders as $order ) : ?>
					<?php $data = $order->to_array(); $buyer = (array) ( $data['buyer'] ?? array() ); $participant = (array) ( $data['participant'] ?? array() ); ?>
					<tr>
						<td><a href="<?php echo esc_url( self::admin_url( array( 'order_id' => $order->get( 'id' ) ) ) ); ?>"><?php echo esc_html( $data['order_number'] ?? '' ); ?></a></td>
						<td><?php echo esc_html( $data['created_at'] ?? '' ); ?></td>
						<td><?php echo esc_html( trim( ( $buyer['first_name'] ?? '' ) . ' ' . ( $buyer['last_name'] ?? '' ) ) ); ?></td>
						<td><?php echo esc_html( trim( ( $participant['first_name'] ?? '' ) . ' ' . ( $participant['last_name'] ?? '' ) ) ); ?></td>
						<td><?php echo esc_html( $data['event_title'] ?? '' ); ?></td>
						<td><?php echo esc_html( $data['ticket_type_name'] ?? '' ); ?></td>
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
		$data = $order->to_array();
		$buyer = (array) ( $data['buyer'] ?? array() );
		$participant = (array) ( $data['participant'] ?? array() );
		?>
		<p><a href="<?php echo esc_url( self::admin_url() ); ?>">&larr; <?php echo esc_html__( 'Back to orders', 'taka-platform' ); ?></a></p>
		<h2><?php echo esc_html( $data['order_number'] ?? '' ); ?></h2>
		<div class="taka-ticketing-admin-detail">
			<section><h3><?php echo esc_html__( 'Buyer', 'taka-platform' ); ?></h3><?php self::admin_person_details( $buyer ); ?></section>
			<section><h3><?php echo esc_html__( 'Participant', 'taka-platform' ); ?></h3><?php self::admin_person_details( $participant ); ?></section>
			<section><h3><?php echo esc_html__( 'Order', 'taka-platform' ); ?></h3>
				<p><strong><?php echo esc_html__( 'Event', 'taka-platform' ); ?>:</strong> <?php echo esc_html( $data['event_title'] ?? '' ); ?></p>
				<p><strong><?php echo esc_html__( 'Ticket', 'taka-platform' ); ?>:</strong> <?php echo esc_html( $data['ticket_type_name'] ?? '' ); ?></p>
				<p><strong><?php echo esc_html__( 'Amount', 'taka-platform' ); ?>:</strong> <?php echo esc_html( self::format_money( $data['amount'] ?? '', $data['currency'] ?? 'EUR' ) ); ?></p>
				<p><strong><?php echo esc_html__( 'Order status', 'taka-platform' ); ?>:</strong> <?php echo esc_html( $data['order_status'] ?? '' ); ?></p>
				<p><strong><?php echo esc_html__( 'Payment status', 'taka-platform' ); ?>:</strong> <?php echo esc_html( $data['payment_status'] ?? '' ); ?></p>
				<p><strong><?php echo esc_html__( 'Payment Method', 'taka-platform' ); ?>:</strong> <?php echo esc_html( self::payment_method_admin_label( $data['payment_method'] ?? '' ) ); ?></p>
				<p><strong><?php echo esc_html__( 'Check-in', 'taka-platform' ); ?>:</strong> <?php echo esc_html( $data['checkin_status'] ?? 'not_checked_in' ); ?></p>
			</section>
			<section><h3><?php echo esc_html__( 'Timeline', 'taka-platform' ); ?></h3>
				<ul><?php foreach ( (array) ( $data['timeline'] ?? array() ) as $item ) : ?><li><?php echo esc_html( ( $item['time'] ?? '' ) . ' - ' . ( $item['label'] ?? '' ) ); ?></li><?php endforeach; ?></ul>
			</section>
		</div>
		<div class="taka-ticketing-admin-actions">
			<?php if ( current_user_can( 'edit_taka_orders' ) ) : ?>
				<?php self::admin_action_form( $order_id, 'mark_paid', __( 'Mark Paid', 'taka-platform' ), 'button-primary' ); ?>
				<?php self::admin_action_form( $order_id, 'cancel', __( 'Cancel', 'taka-platform' ), '' ); ?>
				<?php self::admin_action_form( $order_id, 'delete', __( 'Delete', 'taka-platform' ), 'button-link-delete' ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function admin_person_details( $person ) {
		foreach ( $person as $key => $value ) {
			if ( '' === trim( (string) $value ) ) {
				continue;
			}
			echo '<p><strong>' . esc_html( ucwords( str_replace( '_', ' ', $key ) ) ) . ':</strong> ' . esc_html( $value ) . '</p>';
		}
	}

	private static function admin_action_form( $order_id, $task, $label, $class ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="taka-ticketing-admin-action">';
		wp_nonce_field( self::ADMIN_ACTION );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ADMIN_ACTION ) . '">';
		echo '<input type="hidden" name="order_id" value="' . esc_attr( (string) absint( $order_id ) ) . '">';
		echo '<input type="hidden" name="task" value="' . esc_attr( $task ) . '">';
		echo '<button type="submit" class="button ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button>';
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

		if ( empty( $context['post_id'] ) ) {
			echo '<p class="description">' . esc_html__( 'Save the draft first, then configure repeatable ticket types and payment methods in the shared Event editor section.', 'taka-platform' ) . '</p>';
			return;
		}

		$url = get_edit_post_link( absint( $context['post_id'] ), '' );
		if ( $url ) {
			echo '<p><a class="button" href="' . esc_url( $url . '#taka-native-ticketing-section' ) . '">' . esc_html__( 'Edit native ticket types', 'taka-platform' ) . '</a></p>';
		}
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

<?php
/**
 * Native TAKA Ticketing Phase 1 module.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Ticketing_Module {
	const MODE                 = 'native_taka_ticketing';
	const BANK_TRANSFER_OPTION = 'taka_ticketing_bank_transfer_settings';

	private static $payment_providers = array();

	/** Register Phase 1 hooks and provider scaffolds. */
	public static function init() {
		self::register_payment_provider( new TAKA_Ticketing_Bank_Transfer_Provider() );
		add_action( 'admin_init', array( __CLASS__, 'ensure_capabilities' ) );
		add_filter( 'taka_platform_event_assistant_sections', array( __CLASS__, 'register_event_assistant_section' ) );
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

	/** Save the shared native ticket type config when the Event editor posted it. */
	public static function save_event_ticket_types( $post_id ) {
		if ( ! isset( $_POST['taka_native_ticket_types'] ) ) {
			return;
		}

		$ticket_types = self::sanitize_ticket_types( wp_unslash( $_POST['taka_native_ticket_types'] ) );
		if ( empty( $ticket_types ) ) {
			delete_post_meta( $post_id, TAKA_Ticketing_Ticket_Types::META_KEY );
			return;
		}

		update_post_meta( $post_id, TAKA_Ticketing_Ticket_Types::META_KEY, $ticket_types );
	}

	/** Render the Phase 1 native ticket type editor on Event edit screens. */
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
				'help_text'     => __( 'Phase 1 event ticket type configuration. Public checkout and order handling are intentionally not active yet.', 'taka-platform' ),
				'default_state' => $is_native ? TAKA_Platform_Admin_Collapsible_Section::STATE_EXPANDED : TAKA_Platform_Admin_Collapsible_Section::STATE_COLLAPSED,
				'class'         => 'taka-admin-section--advanced',
				'attributes'    => array( 'id' => 'taka-native-ticketing-section' ),
			)
		);

		if ( ! $is_native ) {
			echo '<p class="description">' . esc_html__( 'Select Native TAKA Ticketing as the ticket mode to use these ticket types later.', 'taka-platform' ) . '</p>';
		}

		echo '<p class="description">' . esc_html__( 'Configure one or more ticket types for this event. These settings are included in backup/export data and are ready for the later checkout phase.', 'taka-platform' ) . '</p>';
		self::render_ticket_type_rows( $ticket_types, (string) get_post_meta( $post_id, '_taka_currency', true ) );
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

	public static function register_event_assistant_section( $sections ) {
		if ( ! class_exists( 'TAKA_Platform_Admin_Event_Assistant_Section' ) ) {
			return $sections;
		}

		$sections[] = new TAKA_Platform_Admin_Event_Assistant_Section(
			array(
				'id'                => 'native-ticketing',
				'title'             => __( 'Native TAKA Ticketing', 'taka-platform' ),
				'help_text'         => __( 'Ticket type readiness for the native checkout architecture. Public checkout is planned for a later phase.', 'taka-platform' ),
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
			echo '<p class="description">' . esc_html__( 'Native ticketing is inactive for this event. Select Native TAKA Ticketing in the Tickets section when this event should use TAKA checkout later.', 'taka-platform' ) . '</p>';
			return;
		}

		printf(
			'<p>%s</p>',
			esc_html( sprintf( _n( '%d native ticket type is configured.', '%d native ticket types are configured.', $count, 'taka-platform' ), $count ) )
		);

		if ( empty( $context['post_id'] ) ) {
			echo '<p class="description">' . esc_html__( 'Save the draft first, then configure repeatable ticket types in the shared Event editor section.', 'taka-platform' ) . '</p>';
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

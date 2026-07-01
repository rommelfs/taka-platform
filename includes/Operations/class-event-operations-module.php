<?php
/**
 * Private Event Operations admin module.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Event_Operations_Module {
	const ADMIN_PAGE_SLUG = 'taka-platform-event-operations';
	const ACTION = 'taka_event_operations_action';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ), 19 );
		add_action( 'admin_init', array( __CLASS__, 'ensure_capabilities' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_action' ) );
	}

	public static function register_admin_menu() {
		add_submenu_page(
			'taka-platform',
			__( 'Event Operations', 'taka-platform' ),
			__( 'Event Operations', 'taka-platform' ),
			'view_taka_operations',
			self::ADMIN_PAGE_SLUG,
			array( __CLASS__, 'render_admin_page' )
		);
	}

	public static function ensure_capabilities() {
		if ( ! function_exists( 'get_role' ) ) {
			return;
		}
		$admin = get_role( 'administrator' );
		if ( ! $admin ) {
			return;
		}
		foreach ( self::capabilities() as $cap ) {
			$admin->add_cap( $cap );
		}
	}

	public static function capabilities() {
		return array(
			'view_taka_operations',
			'manage_taka_operations',
			'checkin_taka_participants',
		);
	}

	public static function admin_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::ADMIN_PAGE_SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	public static function render_event_link( $event_id, $label = '' ) {
		$event_id = absint( $event_id );
		if ( ! $event_id || ! current_user_can( 'checkin_taka_participants' ) ) {
			return '';
		}
		$label = '' !== trim( (string) $label ) ? $label : __( 'Open Event Operations', 'taka-platform' );
		return '<a class="button" href="' . esc_url( self::admin_url( array( 'event_id' => $event_id ) ) ) . '">' . esc_html( $label ) . '</a>';
	}

	public static function handle_action() {
		if ( ! current_user_can( 'checkin_taka_participants' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::ACTION, '_wpnonce' );

		$event_id = absint( $_POST['event_id'] ?? 0 );
		$registration_id = absint( $_POST['registration_id'] ?? 0 );
		$task = sanitize_key( wp_unslash( $_POST['task'] ?? '' ) );
		$args = array( 'event_id' => $event_id );
		$result = null;

		if ( 'check_in' === $task ) {
			$result = TAKA_Event_Operations_Attendance_Service::check_in( $registration_id, get_current_user_id() );
			$args['registration_id'] = $registration_id;
		} elseif ( 'undo_check_in' === $task ) {
			$result = TAKA_Event_Operations_Attendance_Service::undo_check_in( $registration_id, get_current_user_id() );
			$args['registration_id'] = $registration_id;
		} elseif ( 'receive_payment' === $task ) {
			$result = TAKA_Event_Operations_Attendance_Service::receive_payment( $registration_id, get_current_user_id() );
			$args['registration_id'] = $registration_id;
		} elseif ( 'mark_no_show' === $task ) {
			$result = TAKA_Event_Operations_Attendance_Service::mark_no_show( $registration_id, get_current_user_id() );
			$args['registration_id'] = $registration_id;
		} elseif ( 'create_walk_in' === $task ) {
			$walk_in = isset( $_POST['walk_in'] ) && is_array( $_POST['walk_in'] ) ? wp_unslash( $_POST['walk_in'] ) : array();
			$result = TAKA_Event_Operations_Attendance_Service::create_walk_in( $event_id, $walk_in, get_current_user_id() );
			if ( ! is_wp_error( $result ) && ! empty( $result['id'] ) ) {
				$args['registration_id'] = absint( $result['id'] );
			}
		}

		if ( is_wp_error( $result ) ) {
			$args['operations_error'] = $result->get_error_message();
		} elseif ( null !== $result ) {
			$args['operations_updated'] = '1';
		}

		wp_safe_redirect( self::admin_url( $args ) );
		exit;
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'view_taka_operations' ) && ! current_user_can( 'checkin_taka_participants' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}

		$events = self::events_for_operations();
		$event_id = absint( $_GET['event_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $event_id && ! empty( $events ) ) {
			$event_id = absint( $events[0]->ID );
		}
		$event_id = self::user_can_operate_event( $event_id ) ? $event_id : 0;
		$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$qr = sanitize_text_field( wp_unslash( $_GET['qr'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$registration_id = absint( $_GET['registration_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search_query = '' !== $qr ? $qr : $search;
		$registrations = $event_id ? TAKA_Event_Operations_Attendance_Service::search_registrations( $event_id, $search_query ) : array();
		if ( ! $registration_id && 1 === count( $registrations ) ) {
			$registration_id = absint( $registrations[0]['id'] ?? 0 );
		}
		$selected_registration = $registration_id && class_exists( 'TAKA_People_Module' ) ? TAKA_People_Module::registration_repository()->find_by_id( $registration_id ) : null;
		if ( $selected_registration && absint( $selected_registration['event_id'] ?? 0 ) !== $event_id ) {
			$selected_registration = null;
		}

		echo '<div class="wrap taka-operations-admin"><h1>' . esc_html__( 'Event Operations', 'taka-platform' ) . '</h1>';
		self::render_notices();
		if ( empty( $events ) ) {
			self::render_empty_state();
			echo '</div>';
			return;
		}
		self::render_event_selector( $events, $event_id );
		if ( ! $event_id ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Select an event you are allowed to operate.', 'taka-platform' ) . '</p></div></div>';
			return;
		}

		$metrics = TAKA_Event_Operations_Attendance_Service::dashboard_metrics( $event_id );
		self::render_dashboard( $event_id, $metrics );
		self::render_quick_actions();
		echo '<div class="taka-operations-layout">';
		echo '<main class="taka-operations-layout__main">';
		self::render_search_tools( $event_id, $search, $qr );
		self::render_registration_list( $event_id, $registrations, $selected_registration );
		self::render_walk_in_form( $event_id );
		echo '</main>';
		echo '<aside class="taka-operations-layout__side">';
		self::render_participant_panel( $event_id, $selected_registration );
		self::render_operational_summary( $metrics );
		echo '</aside>';
		echo '</div>';
		echo '</div>';
	}

	private static function render_notices() {
		if ( ! empty( $_GET['operations_updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Event operation saved.', 'taka-platform' ) . '</p></div>';
		}
		if ( ! empty( $_GET['operations_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['operations_error'] ) ) ) . '</p></div>';
		}
	}

	private static function render_empty_state() {
		echo '<div class="taka-operations-empty">';
		echo '<h2>' . esc_html__( 'No events available for operations yet.', 'taka-platform' ) . '</h2>';
		echo '<p>' . esc_html__( 'Create an event and native ticket registrations first. This screen will then become the live event-day control center.', 'taka-platform' ) . '</p>';
		echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'post-new.php?post_type=' . TAKA_PLATFORM_CPT_EVENT ) ) . '">' . esc_html__( 'Create event', 'taka-platform' ) . '</a></p>';
		echo '</div>';
	}

	private static function render_event_selector( $events, $current_event_id ) {
		?>
		<form class="taka-operations-event-selector" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::ADMIN_PAGE_SLUG ); ?>">
			<label for="taka-operations-event"><strong><?php echo esc_html__( 'Event', 'taka-platform' ); ?></strong></label>
			<select id="taka-operations-event" name="event_id">
				<?php foreach ( $events as $event ) : ?>
					<option value="<?php echo esc_attr( (string) $event->ID ); ?>" <?php selected( absint( $current_event_id ), absint( $event->ID ) ); ?>><?php echo esc_html( self::event_option_label( $event ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Open operations', 'taka-platform' ), '', '', false ); ?>
		</form>
		<?php
	}

	private static function render_dashboard( $event_id, $metrics ) {
		$event_title = get_the_title( $event_id );
		?>
		<section class="taka-operations-dashboard" aria-labelledby="taka-operations-dashboard-title">
			<div class="taka-operations-dashboard__heading">
				<h2 id="taka-operations-dashboard-title"><?php echo esc_html( $event_title ); ?></h2>
				<p><?php echo esc_html( self::event_datetime_label( get_post( $event_id ) ) ); ?></p>
			</div>
			<div class="taka-operations-metrics">
				<?php self::metric_card( __( 'Registered', 'taka-platform' ), $metrics['registered'] ); ?>
				<?php self::metric_card( __( 'Checked in', 'taka-platform' ), $metrics['checked_in'] ); ?>
				<?php self::metric_card( __( 'Remaining', 'taka-platform' ), $metrics['remaining'] ); ?>
				<?php self::metric_card( __( 'Payment pending', 'taka-platform' ), $metrics['payment_pending'] ); ?>
				<?php self::metric_card( __( 'Walk-ins', 'taka-platform' ), $metrics['walk_ins'] ); ?>
				<?php self::metric_card( __( 'Capacity', 'taka-platform' ), null === $metrics['capacity'] ? __( 'Unlimited', 'taka-platform' ) : $metrics['capacity'] ); ?>
				<?php self::metric_card( __( 'Available', 'taka-platform' ), null === $metrics['available'] ? __( 'Open', 'taka-platform' ) : $metrics['available'] ); ?>
				<?php self::metric_card( __( 'Revenue', 'taka-platform' ), class_exists( 'TAKA_Ticketing_Module' ) ? TAKA_Ticketing_Module::format_money( (string) $metrics['revenue'], $metrics['currency'] ) : (string) $metrics['revenue'] ); ?>
			</div>
		</section>
		<?php
	}

	private static function metric_card( $label, $value ) {
		echo '<div class="taka-operations-metric"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( (string) $value ) . '</strong></div>';
	}

	private static function render_quick_actions() {
		?>
		<nav class="taka-operations-quick-actions" aria-label="<?php echo esc_attr__( 'Event operation quick actions', 'taka-platform' ); ?>">
			<a class="button button-primary" href="#taka-operations-walk-in"><?php echo esc_html__( 'Walk-in registration', 'taka-platform' ); ?></a>
			<a class="button" href="#taka-operations-search"><?php echo esc_html__( 'Find participant', 'taka-platform' ); ?></a>
			<a class="button" href="#taka-operations-qr"><?php echo esc_html__( 'Scan QR code', 'taka-platform' ); ?></a>
			<a class="button" href="#taka-operations-participant"><?php echo esc_html__( 'Receive payment', 'taka-platform' ); ?></a>
			<button class="button" type="button" disabled><?php echo esc_html__( 'Print badge', 'taka-platform' ); ?></button>
			<button class="button" type="button" disabled><?php echo esc_html__( 'Send announcement', 'taka-platform' ); ?></button>
		</nav>
		<?php
	}

	private static function render_search_tools( $event_id, $search, $qr ) {
		?>
		<section class="taka-operations-panel taka-operations-search-panel" id="taka-operations-search">
			<h2><?php echo esc_html__( 'Find participant', 'taka-platform' ); ?></h2>
			<form class="taka-operations-search" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::ADMIN_PAGE_SLUG ); ?>">
				<input type="hidden" name="event_id" value="<?php echo esc_attr( (string) absint( $event_id ) ); ?>">
				<label><span><?php echo esc_html__( 'Search', 'taka-platform' ); ?></span><input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Name, email, order number, dojo or country', 'taka-platform' ); ?>"></label>
				<?php submit_button( __( 'Search', 'taka-platform' ), '', '', false ); ?>
			</form>
			<form class="taka-operations-search" id="taka-operations-qr" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::ADMIN_PAGE_SLUG ); ?>">
				<input type="hidden" name="event_id" value="<?php echo esc_attr( (string) absint( $event_id ) ); ?>">
				<label><span><?php echo esc_html__( 'QR payload', 'taka-platform' ); ?></span><input type="search" name="qr" value="<?php echo esc_attr( $qr ); ?>" placeholder="<?php echo esc_attr__( 'Scan or paste registration QR payload', 'taka-platform' ); ?>"></label>
				<?php submit_button( __( 'Find QR', 'taka-platform' ), '', '', false ); ?>
			</form>
		</section>
		<?php
	}

	private static function render_registration_list( $event_id, $registrations, $selected_registration ) {
		?>
		<section class="taka-operations-panel">
			<h2><?php echo esc_html__( 'Manual list', 'taka-platform' ); ?></h2>
			<table class="widefat striped taka-operations-table">
				<thead><tr>
					<th><?php echo esc_html__( 'Participant', 'taka-platform' ); ?></th>
					<th><?php echo esc_html__( 'Ticket', 'taka-platform' ); ?></th>
					<th><?php echo esc_html__( 'Payment', 'taka-platform' ); ?></th>
					<th><?php echo esc_html__( 'Attendance', 'taka-platform' ); ?></th>
					<th><?php echo esc_html__( 'Products', 'taka-platform' ); ?></th>
					<th><?php echo esc_html__( 'Actions', 'taka-platform' ); ?></th>
				</tr></thead>
				<tbody>
					<?php if ( empty( $registrations ) ) : ?>
						<tr><td colspan="6"><?php echo esc_html__( 'No registrations found for this event.', 'taka-platform' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $registrations as $registration ) : ?>
						<?php $card = TAKA_Event_Operations_Attendance_Service::participant_card_data( $registration ); $person = $card['person']; ?>
						<tr class="<?php echo $selected_registration && absint( $selected_registration['id'] ?? 0 ) === absint( $registration['id'] ?? 0 ) ? 'is-selected' : ''; ?>">
							<td><strong><?php echo esc_html( TAKA_People_Person::full_name( $person ) ?: $person['email'] ); ?></strong><br><span class="description"><?php echo esc_html( trim( (string) ( $person['country'] ?? '' ) . ' ' . (string) ( $person['dojo'] ?? '' ) ) ); ?></span></td>
							<td><?php echo esc_html( $card['ticket_label'] ); ?></td>
							<td><?php echo esc_html( ucfirst( (string) ( $registration['payment_status'] ?? '' ) ) ); ?></td>
							<td><?php echo esc_html( TAKA_Event_Operations_Attendance_Service::status_label( $registration['attendance_state'] ?? $registration['checkin_status'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( self::products_summary( $card['products'] ) ); ?></td>
							<td><a class="button button-small" href="<?php echo esc_url( self::admin_url( array( 'event_id' => $event_id, 'registration_id' => absint( $registration['id'] ?? 0 ) ) ) ); ?>"><?php echo esc_html__( 'Open', 'taka-platform' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
		<?php
	}

	private static function render_participant_panel( $event_id, $registration ) {
		echo '<section class="taka-operations-participant taka-operations-panel" id="taka-operations-participant">';
		echo '<h2>' . esc_html__( 'Participant card', 'taka-platform' ) . '</h2>';
		if ( ! $registration ) {
			echo '<p class="description">' . esc_html__( 'Select a participant from search results or scan a registration QR payload.', 'taka-platform' ) . '</p>';
			echo '</section>';
			return;
		}

		$card = TAKA_Event_Operations_Attendance_Service::participant_card_data( $registration );
		$person = $card['person'];
		$order = $card['order'];
		$payment_status = (string) ( $registration['payment_status'] ?? 'pending' );
		$attendance_state = (string) ( $registration['attendance_state'] ?? $registration['checkin_status'] ?? 'registered' );
		?>
		<div class="taka-operations-person-card">
			<h3><?php echo esc_html( TAKA_People_Person::full_name( $person ) ?: $person['email'] ); ?></h3>
			<p class="taka-operations-person-card__meta"><?php echo esc_html( implode( ' / ', array_filter( array( self::country_label( $person['country'] ?? '' ), $person['dojo'] ?? '', $person['rank'] ?? '' ) ) ) ); ?></p>
			<div class="taka-operations-badges">
				<span class="taka-operations-badge"><?php echo esc_html( $card['ticket_label'] ); ?></span>
				<span class="taka-operations-badge <?php echo 'paid' === $payment_status ? 'is-ok' : 'is-warning'; ?>"><?php echo esc_html__( 'Payment', 'taka-platform' ); ?>: <?php echo esc_html( ucfirst( $payment_status ) ); ?></span>
				<span class="taka-operations-badge <?php echo 'checked_in' === $attendance_state ? 'is-ok' : ''; ?>"><?php echo esc_html__( 'Status', 'taka-platform' ); ?>: <?php echo esc_html( TAKA_Event_Operations_Attendance_Service::status_label( $attendance_state ) ); ?></span>
			</div>
			<?php if ( ! empty( $card['warnings'] ) ) : ?>
				<div class="taka-operations-warnings">
					<?php foreach ( $card['warnings'] as $warning ) : ?><span><?php echo esc_html( $warning['label'] ); ?></span><?php endforeach; ?>
				</div>
			<?php endif; ?>
			<dl class="taka-operations-person-card__details">
				<div><dt><?php echo esc_html__( 'Payment method', 'taka-platform' ); ?></dt><dd><?php echo esc_html( $card['payment_label'] ); ?></dd></div>
				<div><dt><?php echo esc_html__( 'Amount', 'taka-platform' ); ?></dt><dd><?php echo esc_html( class_exists( 'TAKA_Ticketing_Module' ) ? TAKA_Ticketing_Module::format_money( $order['amount'] ?? '0', $order['currency'] ?? 'EUR' ) : ( $order['amount'] ?? '' ) ); ?></dd></div>
				<div><dt><?php echo esc_html__( 'Products', 'taka-platform' ); ?></dt><dd><?php echo esc_html( self::products_summary( $card['products'] ) ?: __( 'None', 'taka-platform' ) ); ?></dd></div>
				<div><dt><?php echo esc_html__( 'Diet', 'taka-platform' ); ?></dt><dd><?php echo esc_html( TAKA_Event_Operations_Attendance_Service::dietary_label( $person['dietary_preference'] ?? 'none' ) ); ?></dd></div>
				<div><dt><?php echo esc_html__( 'Allergies', 'taka-platform' ); ?></dt><dd><?php echo esc_html( '' !== trim( (string) ( $person['allergies'] ?? '' ) ) ? $person['allergies'] : __( 'None', 'taka-platform' ) ); ?></dd></div>
				<?php if ( '' !== trim( (string) ( $person['notes'] ?? '' ) ) || '' !== trim( (string) ( $registration['internal_notes'] ?? '' ) ) ) : ?>
					<div><dt><?php echo esc_html__( 'Internal notes', 'taka-platform' ); ?></dt><dd><?php echo esc_html( trim( (string) ( $person['notes'] ?? '' ) . ' ' . (string) ( $registration['internal_notes'] ?? '' ) ) ); ?></dd></div>
				<?php endif; ?>
			</dl>
			<div class="taka-operations-card-actions">
				<?php if ( 'checked_in' !== $attendance_state ) : ?>
					<?php self::action_form( $event_id, $registration, 'check_in', __( 'Check in', 'taka-platform' ), 'button-primary' ); ?>
				<?php else : ?>
					<?php self::action_form( $event_id, $registration, 'undo_check_in', __( 'Undo', 'taka-platform' ), '' ); ?>
				<?php endif; ?>
				<?php if ( 'paid' !== $payment_status ) : ?>
					<?php self::action_form( $event_id, $registration, 'receive_payment', __( 'Receive payment', 'taka-platform' ), '' ); ?>
				<?php endif; ?>
				<?php self::action_form( $event_id, $registration, 'mark_no_show', __( 'No-show', 'taka-platform' ), '' ); ?>
			</div>
			<div class="taka-operations-qr-card">
				<h4><?php echo esc_html__( 'Registration QR payload', 'taka-platform' ); ?></h4>
				<?php echo self::qr_markup( $card['qr_payload'], $registration ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<code><?php echo esc_html( $card['qr_payload'] ); ?></code>
			</div>
			<?php self::render_activity_timeline( $registration, $order ); ?>
		</div>
		<?php
		echo '</section>';
	}

	private static function action_form( $event_id, $registration, $task, $label, $class ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="taka-operations-action">';
		wp_nonce_field( self::ACTION );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		echo '<input type="hidden" name="event_id" value="' . esc_attr( (string) absint( $event_id ) ) . '">';
		echo '<input type="hidden" name="registration_id" value="' . esc_attr( (string) absint( $registration['id'] ?? 0 ) ) . '">';
		echo '<input type="hidden" name="task" value="' . esc_attr( $task ) . '">';
		echo '<button class="button ' . esc_attr( $class ) . '" type="submit">' . esc_html( $label ) . '</button>';
		echo '</form>';
	}

	private static function qr_markup( $payload, $registration ) {
		$markup = apply_filters( 'taka_event_operations_qr_markup', '', $payload, $registration );
		if ( '' !== trim( (string) $markup ) ) {
			return $markup;
		}
		return '<div class="taka-operations-qr-placeholder" data-taka-qr-payload="' . esc_attr( $payload ) . '">' . esc_html__( 'QR renderer hook available', 'taka-platform' ) . '</div>';
	}

	private static function render_activity_timeline( $registration, $order ) {
		$items = array();
		foreach ( (array) ( $order['timeline'] ?? array() ) as $item ) {
			$items[] = $item;
		}
		foreach ( (array) ( $registration['operations_timeline'] ?? array() ) as $item ) {
			$items[] = $item;
		}
		usort(
			$items,
			static function ( $a, $b ) {
				return strcmp( (string) ( $b['time'] ?? '' ), (string) ( $a['time'] ?? '' ) );
			}
		);
		echo '<div class="taka-operations-timeline"><h4>' . esc_html__( 'Activity timeline', 'taka-platform' ) . '</h4>';
		if ( empty( $items ) ) {
			echo '<p class="description">' . esc_html__( 'No activity recorded yet.', 'taka-platform' ) . '</p></div>';
			return;
		}
		echo '<ul>';
		foreach ( array_slice( $items, 0, 12 ) as $item ) {
			echo '<li><span>' . esc_html( $item['time'] ?? '' ) . '</span> ' . esc_html( $item['label'] ?? '' ) . '</li>';
		}
		echo '</ul></div>';
	}

	private static function render_walk_in_form( $event_id ) {
		if ( ! current_user_can( 'manage_taka_operations' ) && ! current_user_can( 'edit_taka_registrations' ) ) {
			return;
		}
		$ticket_types = class_exists( 'TAKA_Ticketing_Module' ) ? TAKA_Ticketing_Module::available_ticket_types_for_event( $event_id ) : array();
		$countries = array( '' => __( 'Select country', 'taka-platform' ) ) + TAKA_Platform_Data::option_list_choices( 'country', TAKA_Platform_Data::platform_fallback_language() );
		$payment_methods = class_exists( 'TAKA_Ticketing_Module' ) ? TAKA_Ticketing_Module::enabled_payment_methods_for_event( $event_id ) : array();
		?>
		<section class="taka-operations-panel taka-operations-walk-in" id="taka-operations-walk-in">
			<h2><?php echo esc_html__( 'Walk-in registration', 'taka-platform' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Create or reuse a person, reserve capacity immediately and make the registration available for check-in.', 'taka-platform' ); ?></p>
			<?php if ( empty( $ticket_types ) ) : ?>
				<p><?php echo esc_html__( 'No available native ticket types exist for this event.', 'taka-platform' ); ?></p>
				<?php return; ?>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="taka-operations-walk-in-form">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<input type="hidden" name="event_id" value="<?php echo esc_attr( (string) absint( $event_id ) ); ?>">
				<input type="hidden" name="task" value="create_walk_in">
				<?php wp_nonce_field( self::ACTION ); ?>
				<div class="taka-operations-form-grid">
					<?php self::walk_in_input( 'first_name', __( 'First name', 'taka-platform' ), true ); ?>
					<?php self::walk_in_input( 'last_name', __( 'Last name', 'taka-platform' ), true ); ?>
					<?php self::walk_in_input( 'email', __( 'Email', 'taka-platform' ), false, 'email' ); ?>
					<?php self::walk_in_input( 'phone', __( 'Phone', 'taka-platform' ) ); ?>
					<?php self::walk_in_select( 'country', __( 'Country', 'taka-platform' ), $countries, true ); ?>
					<?php self::walk_in_input( 'dojo', __( 'Dojo / Club', 'taka-platform' ) ); ?>
					<?php self::walk_in_input( 'rank', __( 'Rank / Belt', 'taka-platform' ) ); ?>
					<label><span><?php echo esc_html__( 'Ticket', 'taka-platform' ); ?></span><select name="walk_in[ticket_type_id]" required>
						<?php foreach ( $ticket_types as $ticket_type ) : ?>
							<option value="<?php echo esc_attr( $ticket_type['id'] ); ?>"><?php echo esc_html( $ticket_type['name'] . ' - ' . TAKA_Ticketing_Module::format_money( $ticket_type['price'], $ticket_type['currency'] ) ); ?></option>
						<?php endforeach; ?>
					</select></label>
					<label><span><?php echo esc_html__( 'Payment method', 'taka-platform' ); ?></span><select name="walk_in[payment_method]">
						<?php foreach ( $payment_methods as $method ) : ?>
							<option value="<?php echo esc_attr( $method ); ?>"><?php echo esc_html( TAKA_Ticketing_Module::payment_method_label( $method ) ); ?></option>
						<?php endforeach; ?>
					</select></label>
					<?php self::walk_in_select( 'dietary_preference', __( 'Dietary preference', 'taka-platform' ), array( 'none' => __( 'None', 'taka-platform' ), 'vegetarian' => __( 'Vegetarian', 'taka-platform' ), 'vegan' => __( 'Vegan', 'taka-platform' ), 'other' => __( 'Other / note', 'taka-platform' ) ) ); ?>
					<?php self::walk_in_input( 'tags', __( 'Tags', 'taka-platform' ) ); ?>
					<label class="taka-operations-form-grid__wide"><span><?php echo esc_html__( 'Allergies', 'taka-platform' ); ?></span><textarea name="walk_in[allergies]" rows="2"></textarea></label>
					<label class="taka-operations-form-grid__wide"><span><?php echo esc_html__( 'Internal notes', 'taka-platform' ); ?></span><textarea name="walk_in[internal_notes]" rows="2"></textarea></label>
				</div>
				<label class="taka-operations-checkbox"><input type="checkbox" name="walk_in[payment_received]" value="1"> <?php echo esc_html__( 'Payment received now', 'taka-platform' ); ?></label>
				<?php submit_button( __( 'Register walk-in', 'taka-platform' ), 'primary', '', false ); ?>
			</form>
		</section>
		<?php
	}

	private static function walk_in_input( $field, $label, $required = false, $type = 'text' ) {
		echo '<label><span>' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( 'walk_in[' . $field . ']' ) . '" ' . ( $required ? 'required' : '' ) . '></label>';
	}

	private static function walk_in_select( $field, $label, $choices, $required = false ) {
		echo '<label><span>' . esc_html( $label ) . '</span><select name="' . esc_attr( 'walk_in[' . $field . ']' ) . '" ' . ( $required ? 'required' : '' ) . '>';
		foreach ( (array) $choices as $value => $choice_label ) {
			echo '<option value="' . esc_attr( (string) $value ) . '">' . esc_html( (string) $choice_label ) . '</option>';
		}
		echo '</select></label>';
	}

	private static function render_operational_summary( $metrics ) {
		?>
		<section class="taka-operations-panel">
			<h2><?php echo esc_html__( 'Seminar overview', 'taka-platform' ); ?></h2>
			<?php self::summary_group( __( 'Dietary requirements', 'taka-platform' ), $metrics['dietary'], array( 'allergies' => $metrics['allergies'] ) ); ?>
			<?php self::summary_group( __( 'Products booked', 'taka-platform' ), $metrics['products'] ); ?>
			<?php self::summary_group( __( 'Warnings and roles', 'taka-platform' ), $metrics['tags'] ); ?>
		</section>
		<?php
	}

	private static function summary_group( $title, $items, $extra = array() ) {
		echo '<div class="taka-operations-summary-group"><h3>' . esc_html( $title ) . '</h3>';
		$items = array_merge( (array) $items, (array) $extra );
		if ( empty( $items ) ) {
			echo '<p class="description">' . esc_html__( 'Nothing recorded yet.', 'taka-platform' ) . '</p></div>';
			return;
		}
		echo '<dl>';
		foreach ( $items as $label => $value ) {
			echo '<div><dt>' . esc_html( ucwords( str_replace( '_', ' ', (string) $label ) ) ) . '</dt><dd>' . esc_html( (string) $value ) . '</dd></div>';
		}
		echo '</dl></div>';
	}

	private static function products_summary( $products ) {
		$parts = array();
		foreach ( (array) $products as $product ) {
			$parts[] = max( 1, absint( $product['quantity'] ?? 1 ) ) . 'x ' . sanitize_text_field( $product['title'] ?? '' );
		}
		return implode( ', ', array_filter( $parts ) );
	}

	private static function events_for_operations() {
		$events = get_posts(
			array(
				'post_type'        => TAKA_PLATFORM_CPT_EVENT,
				'post_status'      => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page'   => -1,
				'suppress_filters' => true,
			)
		);
		$events = array_values( array_filter( $events, static function ( $event ) { return self::user_can_operate_event( $event->ID ); } ) );
		usort(
			$events,
			static function ( $a, $b ) {
				return strcmp( self::event_sort_key( $a ), self::event_sort_key( $b ) );
			}
		);
		return $events;
	}

	private static function user_can_operate_event( $event_id ) {
		$event_id = absint( $event_id );
		return $event_id && ( current_user_can( 'manage_taka_operations' ) || current_user_can( 'edit_post', $event_id ) || current_user_can( 'checkin_taka_participants' ) );
	}

	private static function event_option_label( $event ) {
		return trim( self::event_datetime_label( $event ) . ' - ' . get_the_title( $event ) );
	}

	private static function event_datetime_label( $event ) {
		$event_id = $event instanceof WP_Post ? $event->ID : absint( $event );
		$date = sanitize_text_field( get_post_meta( $event_id, '_taka_date_start', true ) );
		$time = sanitize_text_field( get_post_meta( $event_id, '_taka_time_start', true ) );
		return trim( $date . ' ' . $time );
	}

	private static function event_sort_key( $event ) {
		$key = self::event_datetime_label( $event );
		return '' !== $key ? $key : get_the_title( $event );
	}

	private static function country_label( $country ) {
		$country = sanitize_text_field( $country );
		return '' !== $country && class_exists( 'TAKA_Platform_Data' ) ? TAKA_Platform_Data::country_label( $country, TAKA_Platform_Data::platform_fallback_language() ) : $country;
	}
}

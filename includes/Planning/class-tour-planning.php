<?php
/**
 * Private tour logistics planning for TAKA Platform.
 *
 * Planning items are intentionally stored as a private internal post type in
 * phase 1. This keeps editorial event data separate from sensitive logistics
 * while preserving a clear migration path to dedicated planning/order tables
 * if future phases need higher-volume workflows or reminders.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Tour_Planning {
	const NONCE = 'taka_platform_tour_planning_nonce';
	const CONFIG_META = '_taka_planning_config_id';

	/** Register admin hooks for the private planning module. */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . TAKA_PLATFORM_CPT_TOUR_PLANNING, array( __CLASS__, 'save_item' ) );
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_caps' ), 10, 4 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'force_private_status' ), 10, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_admin_query' ) );
		add_action( 'admin_init', array( __CLASS__, 'guard_direct_access' ) );
		add_filter( 'taka_platform_event_assistant_sections', array( __CLASS__, 'add_event_assistant_section' ) );
	}

	/** Register the private planning CPT. It has no public frontend or REST exposure. */
	public static function register_post_type() {
		register_post_type(
			TAKA_PLATFORM_CPT_TOUR_PLANNING,
			array(
				'labels'              => array(
					'name'          => __( 'Tour Planning', 'taka-platform' ),
					'singular_name' => __( 'Tour Planning Item', 'taka-platform' ),
					'add_new_item'  => __( 'Add Tour Planning Item', 'taka-platform' ),
					'edit_item'     => __( 'Edit Tour Planning Item', 'taka-platform' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'supports'            => array( 'title' ),
				'map_meta_cap'        => true,
				'capabilities'        => self::post_type_capabilities(),
			)
		);
	}

	/** Register a dedicated private agenda page under TAKA Platform. */
	public static function register_menu() {
		add_submenu_page(
			'taka-platform',
			__( 'Tour Planning', 'taka-platform' ),
			__( 'Tour Planning', 'taka-platform' ),
			'view_taka_tour_planning',
			'taka-platform-tour-planning',
			array( __CLASS__, 'render_agenda_page' )
		);
	}

	/** Ensure platform admins and explicit tour planners receive the right caps. */
	public static function ensure_capabilities() {
		$planner_caps = array(
			'read',
			'upload_files',
			'view_taka_tour_planning',
			'edit_taka_tour_planning',
			'edit_assigned_taka_tour_planning',
			'delete_taka_tour_planning',
			'delete_assigned_taka_tour_planning',
			'read_taka_tour_plan',
			'edit_taka_tour_plan',
			'delete_taka_tour_plan',
		);

		if ( ! get_role( 'taka_tour_planner' ) && function_exists( 'add_role' ) ) {
			add_role(
				'taka_tour_planner',
				__( 'TAKA Tour Planner', 'taka-platform' ),
				array_fill_keys( $planner_caps, true )
			);
		}

		$planner_role = get_role( 'taka_tour_planner' );
		if ( $planner_role ) {
			foreach ( $planner_caps as $cap ) {
				$planner_role->add_cap( $cap );
			}
		}

		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			foreach ( self::administrator_capabilities() as $cap ) {
				$admin_role->add_cap( $cap );
			}
		}
	}

	/** Capabilities used by the private planning CPT. */
	private static function post_type_capabilities() {
		return array(
			'edit_post'              => 'edit_taka_tour_plan',
			'read_post'              => 'read_taka_tour_plan',
			'delete_post'            => 'delete_taka_tour_plan',
			'edit_posts'             => 'edit_taka_tour_planning',
			'edit_others_posts'      => 'edit_others_taka_tour_planning',
			'publish_posts'          => 'edit_taka_tour_planning',
			'read_private_posts'     => 'view_taka_tour_planning',
			'delete_posts'           => 'delete_taka_tour_planning',
			'delete_others_posts'    => 'delete_others_taka_tour_planning',
			'delete_published_posts' => 'delete_taka_tour_planning',
			'create_posts'           => 'edit_taka_tour_planning',
		);
	}

	/** Full private planning cap set for administrators. */
	private static function administrator_capabilities() {
		return array(
			'manage_taka_tour_planning',
			'view_taka_tour_planning',
			'edit_taka_tour_planning',
			'edit_assigned_taka_tour_planning',
			'edit_others_taka_tour_planning',
			'delete_taka_tour_planning',
			'delete_assigned_taka_tour_planning',
			'delete_others_taka_tour_planning',
			'read_taka_tour_plan',
			'edit_taka_tour_plan',
			'delete_taka_tour_plan',
		);
	}

	/** Force planning items to remain private even if the publish box says Publish. */
	public static function force_private_status( $data, $postarr ) {
		if ( TAKA_PLATFORM_CPT_TOUR_PLANNING !== ( $data['post_type'] ?? ( $postarr['post_type'] ?? '' ) ) ) {
			return $data;
		}
		if ( in_array( (string) ( $data['post_status'] ?? '' ), array( 'auto-draft', 'trash' ), true ) ) {
			return $data;
		}
		$data['post_status'] = 'private';
		return $data;
	}

	/** Enforce private planning item permissions at capability level. */
	public static function map_meta_caps( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'edit_post', 'delete_post', 'read_post' ), true ) || empty( $args[0] ) ) {
			return $caps;
		}
		$post = get_post( absint( $args[0] ) );
		if ( ! $post || TAKA_PLATFORM_CPT_TOUR_PLANNING !== $post->post_type ) {
			return $caps;
		}
		if ( user_can( $user_id, 'manage_options' ) ) {
			return array( 'manage_options' );
		}
		if ( user_can( $user_id, 'manage_taka_tour_planning' ) ) {
			return array( 'manage_taka_tour_planning' );
		}

		$action = 'delete_post' === $cap ? 'delete' : ( 'read_post' === $cap ? 'read' : 'edit' );
		if ( ! self::user_can_access_item( $user_id, $post->ID, $action ) ) {
			return array( 'do_not_allow' );
		}
		if ( 'read' === $action ) {
			return array( 'view_taka_tour_planning' );
		}
		if ( 'delete' === $action ) {
			return array( 'delete_taka_tour_planning' );
		}
		return array( 'edit_taka_tour_planning' );
	}

	/** Limit planning list queries to accessible private items. */
	public static function filter_admin_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || TAKA_PLATFORM_CPT_TOUR_PLANNING !== $query->get( 'post_type' ) ) {
			return;
		}
		if ( current_user_can( 'manage_taka_tour_planning' ) || current_user_can( 'manage_options' ) ) {
			return;
		}
		$ids = self::accessible_item_ids_for_user( get_current_user_id(), 'edit' );
		$query->set( 'post__in', ! empty( $ids ) ? $ids : array( 0 ) );
	}

	/** Block direct edit URLs for non-privileged users. */
	public static function guard_direct_access() {
		if ( empty( $_GET['post'] ) ) {
			return;
		}
		$post_id = absint( $_GET['post'] );
		$post = get_post( $post_id );
		if ( $post && TAKA_PLATFORM_CPT_TOUR_PLANNING === $post->post_type && ! self::user_can_access_item( get_current_user_id(), $post_id, 'edit' ) ) {
			wp_die( esc_html__( 'You are not allowed to edit this private tour planning item.', 'taka-platform' ) );
		}
	}

	/** Whether the current user may use the private planning module. */
	public static function current_user_can_view() {
		return current_user_can( 'view_taka_tour_planning' ) || current_user_can( 'manage_taka_tour_planning' ) || current_user_can( 'manage_options' );
	}

	/** Whether a user can read/edit/delete one planning item. */
	public static function user_can_access_item( $user_id, $post_id, $action = 'read' ) {
		$post = get_post( $post_id );
		if ( ! $post || TAKA_PLATFORM_CPT_TOUR_PLANNING !== $post->post_type ) {
			return false;
		}
		if ( user_can( $user_id, 'manage_taka_tour_planning' ) || user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		$is_delete = 'delete' === $action;
		$is_edit = 'edit' === $action || $is_delete;
		if ( ! user_can( $user_id, 'view_taka_tour_planning' ) ) {
			return false;
		}
		if ( $is_edit && ! user_can( $user_id, 'edit_taka_tour_planning' ) ) {
			return false;
		}
		if ( $is_delete && ! user_can( $user_id, 'delete_taka_tour_planning' ) ) {
			return false;
		}

		$access_group = self::meta( $post_id, 'access_group' ) ?: 'assigned_users';
		if ( 'admin_only' === $access_group ) {
			return false;
		}
		if ( (int) $post->post_author === (int) $user_id ) {
			return true;
		}
		if ( 'all_planners' === $access_group ) {
			return true;
		}
		if ( in_array( $access_group, array( 'assigned_users', 'organizer_members', 'related_event_editors' ), true ) && in_array( (int) $user_id, self::assigned_user_ids( $post_id ), true ) ) {
			return user_can( $user_id, $is_delete ? 'delete_assigned_taka_tour_planning' : 'edit_assigned_taka_tour_planning' );
		}
		if ( in_array( $access_group, array( 'organizer_members', 'related_event_editors' ), true ) && self::user_has_organizer_overlap( $user_id, $post_id ) ) {
			return user_can( $user_id, $is_delete ? 'delete_assigned_taka_tour_planning' : 'edit_assigned_taka_tour_planning' );
		}
		if ( 'related_event_editors' === $access_group && self::user_can_access_related_event( $user_id, $post_id ) ) {
			return user_can( $user_id, $is_delete ? 'delete_assigned_taka_tour_planning' : 'edit_assigned_taka_tour_planning' );
		}
		return false;
	}

	/** Add metaboxes for planning item editing. */
	public static function add_meta_boxes() {
		add_meta_box( 'taka_tour_planning_details', __( 'Tour planning details', 'taka-platform' ), array( __CLASS__, 'render_meta_box' ), TAKA_PLATFORM_CPT_TOUR_PLANNING, 'normal', 'high' );
	}

	/** Render the private planning item editor. */
	public static function render_meta_box( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE );
		$item = self::item_from_post( $post );
		if ( empty( $item['related_event_id'] ) && ! empty( $_GET['taka_related_event'] ) ) {
			$item['related_event_id'] = absint( $_GET['taka_related_event'] );
		}

		TAKA_Platform_Admin_Collapsible_Section::open( array(
			'id'            => 'tour-planning-basic',
			'title'         => __( 'Agenda basics', 'taka-platform' ),
			'help_text'     => __( 'Private logistical timing, status and event relationship. This is never rendered on public event pages.', 'taka-platform' ),
			'default_state' => TAKA_Platform_Admin_Collapsible_Section::STATE_EXPANDED,
			'class'         => 'taka-admin-section--essential',
		) );
		self::select_field( 'type', __( 'Planning item type', 'taka-platform' ), $item['type'], self::type_labels() );
		self::text_field( 'tour_key', __( 'Tour / agenda key', 'taka-platform' ), $item['tour_key'] );
		self::date_field( 'start_date', __( 'Start date', 'taka-platform' ), $item['start_date'] );
		self::time_field( 'start_time', __( 'Start time', 'taka-platform' ), $item['start_time'] );
		self::date_field( 'end_date', __( 'End date', 'taka-platform' ), $item['end_date'] );
		self::time_field( 'end_time', __( 'End time', 'taka-platform' ), $item['end_time'] );
		self::text_field( 'location', __( 'Location', 'taka-platform' ), $item['location'] );
		self::event_select_field( 'related_event_id', __( 'Related event', 'taka-platform' ), $item['related_event_id'] );
		self::select_field( 'status', __( 'Status', 'taka-platform' ), $item['status'], self::status_labels() );
		TAKA_Platform_Admin_Collapsible_Section::close();

		TAKA_Platform_Admin_Collapsible_Section::open( array(
			'id'            => 'tour-planning-responsibility',
			'title'         => __( 'Responsibilities and costs', 'taka-platform' ),
			'help_text'     => __( 'Internal responsibility and financial ownership for tour logistics.', 'taka-platform' ),
			'default_state' => TAKA_Platform_Admin_Collapsible_Section::STATE_EXPANDED,
			'class'         => 'taka-admin-section--essential',
		) );
		self::text_field( 'responsible_person', __( 'Responsible person', 'taka-platform' ), $item['responsible_person'] );
		self::text_field( 'financial_responsible_person', __( 'Financially responsible person', 'taka-platform' ), $item['financial_responsible_person'] );
		self::money_field( 'estimated_cost', __( 'Estimated cost', 'taka-platform' ), $item['estimated_cost'] );
		self::money_field( 'actual_cost', __( 'Actual cost', 'taka-platform' ), $item['actual_cost'] );
		self::select_field( 'currency', __( 'Currency', 'taka-platform' ), $item['currency'], self::currency_choices() );
		TAKA_Platform_Admin_Collapsible_Section::close();

		TAKA_Platform_Admin_Collapsible_Section::open( array(
			'id'            => 'tour-planning-accommodation',
			'title'         => __( 'Accommodation / overnight stay', 'taka-platform' ),
			'help_text'     => __( 'Hotel, room and booking-reference details for overnight stays.', 'taka-platform' ),
			'default_state' => 'accommodation' === $item['type'] || self::has_accommodation_data( $item ) ? TAKA_Platform_Admin_Collapsible_Section::STATE_EXPANDED : TAKA_Platform_Admin_Collapsible_Section::STATE_COLLAPSED,
			'class'         => 'taka-admin-section--advanced',
		) );
		self::text_field( 'accommodation_name', __( 'Hotel / accommodation name', 'taka-platform' ), $item['accommodation_name'] );
		self::textarea_field( 'address', __( 'Address', 'taka-platform' ), $item['address'] );
		self::date_field( 'checkin_date', __( 'Check-in date', 'taka-platform' ), $item['checkin_date'] );
		self::time_field( 'checkin_time', __( 'Check-in time', 'taka-platform' ), $item['checkin_time'] );
		self::date_field( 'checkout_date', __( 'Check-out date', 'taka-platform' ), $item['checkout_date'] );
		self::time_field( 'checkout_time', __( 'Check-out time', 'taka-platform' ), $item['checkout_time'] );
		self::number_field( 'rooms', __( 'Number of rooms', 'taka-platform' ), $item['rooms'] );
		self::textarea_field( 'room_types', __( 'Room types', 'taka-platform' ), $item['room_types'] );
		self::textarea_field( 'guests', __( 'Guests / persons', 'taka-platform' ), $item['guests'] );
		self::text_field( 'booking_reference', __( 'Booking reference', 'taka-platform' ), $item['booking_reference'] );
		TAKA_Platform_Admin_Collapsible_Section::close();

		TAKA_Platform_Admin_Collapsible_Section::open( array(
			'id'            => 'tour-planning-transfer',
			'title'         => __( 'Transfer', 'taka-platform' ),
			'help_text'     => __( 'Internal travel legs such as car, train, flight, taxi or public transport.', 'taka-platform' ),
			'default_state' => 'transfer' === $item['type'] || self::has_transfer_data( $item ) ? TAKA_Platform_Admin_Collapsible_Section::STATE_EXPANDED : TAKA_Platform_Admin_Collapsible_Section::STATE_COLLAPSED,
			'class'         => 'taka-admin-section--advanced',
		) );
		self::select_field( 'transfer_type', __( 'Transfer type', 'taka-platform' ), $item['transfer_type'], self::transfer_type_labels() );
		self::text_field( 'departure_location', __( 'Departure location', 'taka-platform' ), $item['departure_location'] );
		self::text_field( 'arrival_location', __( 'Arrival location', 'taka-platform' ), $item['arrival_location'] );
		self::date_field( 'departure_date', __( 'Departure date', 'taka-platform' ), $item['departure_date'] );
		self::time_field( 'departure_time', __( 'Departure time', 'taka-platform' ), $item['departure_time'] );
		self::date_field( 'arrival_date', __( 'Arrival date', 'taka-platform' ), $item['arrival_date'] );
		self::time_field( 'arrival_time', __( 'Arrival time', 'taka-platform' ), $item['arrival_time'] );
		self::text_field( 'carrier_provider', __( 'Carrier / provider', 'taka-platform' ), $item['carrier_provider'] );
		self::text_field( 'transfer_booking_reference', __( 'Booking reference', 'taka-platform' ), $item['transfer_booking_reference'] );
		self::text_field( 'driver_responsible_person', __( 'Driver / responsible person', 'taka-platform' ), $item['driver_responsible_person'] );
		TAKA_Platform_Admin_Collapsible_Section::close();

		TAKA_Platform_Admin_Collapsible_Section::open( array(
			'id'            => 'tour-planning-meal',
			'title'         => __( 'Meal / catering / restaurant', 'taka-platform' ),
			'help_text'     => __( 'Meals, restaurants, catering and private invitations for the tour group.', 'taka-platform' ),
			'default_state' => 'meal' === $item['type'] || self::has_meal_data( $item ) ? TAKA_Platform_Admin_Collapsible_Section::STATE_EXPANDED : TAKA_Platform_Admin_Collapsible_Section::STATE_COLLAPSED,
			'class'         => 'taka-admin-section--advanced',
		) );
		self::select_field( 'meal_type', __( 'Meal type', 'taka-platform' ), $item['meal_type'], self::meal_type_labels() );
		self::text_field( 'restaurant_location', __( 'Restaurant / location', 'taka-platform' ), $item['restaurant_location'] );
		self::date_field( 'meal_date', __( 'Meal date', 'taka-platform' ), $item['meal_date'] );
		self::time_field( 'meal_time', __( 'Meal time', 'taka-platform' ), $item['meal_time'] );
		self::number_field( 'people_count', __( 'Number of people', 'taka-platform' ), $item['people_count'] );
		TAKA_Platform_Admin_Collapsible_Section::close();

		TAKA_Platform_Admin_Collapsible_Section::open( array(
			'id'            => 'tour-planning-notes-access',
			'title'         => __( 'Notes and private access', 'taka-platform' ),
			'help_text'     => __( 'Private notes and server-enforced access rules for this planning item.', 'taka-platform' ),
			'default_state' => TAKA_Platform_Admin_Collapsible_Section::STATE_COLLAPSED,
			'class'         => 'taka-admin-section--advanced',
		) );
		self::textarea_field( 'notes', __( 'Internal notes', 'taka-platform' ), $item['notes'] );
		self::select_field( 'access_group', __( 'Visibility / access group', 'taka-platform' ), $item['access_group'], self::access_group_labels() );
		self::user_multiselect_field( 'assigned_user_ids', __( 'Assigned users', 'taka-platform' ), $item['assigned_user_ids'] );
		self::organizer_multiselect_field( 'assigned_organizer_ids', __( 'Assigned organizer members', 'taka-platform' ), $item['assigned_organizer_ids'] );
		echo '<p class="description">' . esc_html__( 'Planning items are private. Access is enforced server-side; hiding UI is not used as the only protection.', 'taka-platform' ) . '</p>';
		TAKA_Platform_Admin_Collapsible_Section::close();
	}

	/** Save one planning item. */
	public static function save_item( $post_id ) {
		if ( ! self::can_save_item( $post_id ) ) {
			return;
		}
		$posted = isset( $_POST['taka_planning'] ) && is_array( $_POST['taka_planning'] ) ? wp_unslash( $_POST['taka_planning'] ) : array();
		$clean = self::sanitize_item_fields( $posted );
		foreach ( $clean as $field => $value ) {
			update_post_meta( $post_id, '_taka_planning_' . $field, $value );
		}
		if ( '' === (string) get_post_meta( $post_id, self::CONFIG_META, true ) ) {
			update_post_meta( $post_id, self::CONFIG_META, 'planning-' . absint( $post_id ) );
		}
	}

	/** Render the private agenda page. */
	public static function render_agenda_page() {
		if ( ! self::current_user_can_view() ) {
			wp_die( esc_html__( 'You are not allowed to view private tour planning.', 'taka-platform' ) );
		}

		$filters = self::agenda_filters_from_request();
		$items = self::query_items( $filters );
		$view = sanitize_key( $filters['view'] ?? 'timeline' );
		?>
		<div class="wrap taka-tour-planning">
			<h1><?php echo esc_html__( 'Tour Planning', 'taka-platform' ); ?></h1>
			<p><?php echo esc_html__( 'Private logistical agenda for travel, accommodation, transfers, meals, free time, excursions and internal responsibilities. This data is never rendered on public pages.', 'taka-platform' ); ?></p>
			<?php if ( current_user_can( 'edit_taka_tour_planning' ) ) : ?>
				<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . TAKA_PLATFORM_CPT_TOUR_PLANNING ) ); ?>"><?php echo esc_html__( 'Add planning item', 'taka-platform' ); ?></a></p>
			<?php endif; ?>

			<?php self::render_agenda_filters( $filters ); ?>

			<?php if ( 'costs' === $view ) : ?>
				<?php self::render_cost_overview( $items ); ?>
			<?php elseif ( 'by_day' === $view ) : ?>
				<?php self::render_grouped_items( $items, __( 'No date', 'taka-platform' ), static function ( $item ) { return $item['start_date'] ?: ''; } ); ?>
			<?php elseif ( 'by_event' === $view ) : ?>
				<?php self::render_grouped_items( $items, __( 'No related event', 'taka-platform' ), static function ( $item ) { return $item['related_event_title'] ?: ''; } ); ?>
			<?php elseif ( 'by_type' === $view ) : ?>
				<?php self::render_grouped_items( $items, __( 'Other', 'taka-platform' ), static function ( $item ) { $labels = self::type_labels(); return $labels[ $item['type'] ] ?? $item['type']; } ); ?>
			<?php else : ?>
				<?php self::render_items_table( $items ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Render private event-editor section with linked planning items. */
	public static function render_event_section( $event_id ) {
		if ( ! self::current_user_can_view() ) {
			return;
		}
		$items = self::query_items( array( 'related_event_id' => absint( $event_id ) ) );
		TAKA_Platform_Admin_Collapsible_Section::open( array(
			'id'            => 'event-private-tour-planning',
			'title'         => __( 'Private Tour Planning', 'taka-platform' ),
			'help_text'     => __( 'Internal logistics linked to this event. This section is private and never shown on public event pages.', 'taka-platform' ),
			'default_state' => TAKA_Platform_Admin_Collapsible_Section::STATE_COLLAPSED,
			'class'         => 'taka-admin-section--advanced',
		) );
		self::render_event_item_list( $event_id, $items );
		TAKA_Platform_Admin_Collapsible_Section::close();
	}

	/** Add optional Event Assistant logistics section without affecting publication readiness. */
	public static function add_event_assistant_section( $sections ) {
		if ( ! self::current_user_can_view() || ! class_exists( 'TAKA_Platform_Admin_Event_Assistant_Section' ) ) {
			return $sections;
		}
		$sections[] = new TAKA_Platform_Admin_Event_Assistant_Section( array(
			'id'              => 'private-logistics',
			'title'           => __( 'Private logistics', 'taka-platform' ),
			'help_text'       => __( 'Optional private tour planning for accommodation, transfers, meals and internal notes. This never blocks publication.', 'taka-platform' ),
			'default_state'   => TAKA_Platform_Admin_Collapsible_Section::STATE_COLLAPSED,
			'weight'          => 2,
			'render_callback' => array( __CLASS__, 'render_event_assistant_section' ),
		) );
		return $sections;
	}

	/** Render Event Assistant private logistics link/list. */
	public static function render_event_assistant_section( $context ) {
		$event_id = absint( $context['post_id'] ?? 0 );
		if ( ! $event_id ) {
			echo '<p class="description">' . esc_html__( 'Save the event first, then private planning items can be linked to it.', 'taka-platform' ) . '</p>';
			return;
		}
		self::render_event_item_list( $event_id, self::query_items( array( 'related_event_id' => $event_id ) ) );
	}

	/** Export all private planning items for explicit backup/export workflows. */
	public static function export_items() {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}
		$posts = get_posts( array( 'post_type' => TAKA_PLATFORM_CPT_TOUR_PLANNING, 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
		$out = array();
		foreach ( $posts as $post ) {
			$item = self::item_from_post( $post );
			$config_id = (string) get_post_meta( $post->ID, self::CONFIG_META, true );
			$key = '' !== $config_id ? $config_id : 'planning-' . $post->ID;
			$related_event_id = absint( $item['related_event_id'] ?? 0 );
			unset( $item['edit_url'] );
			$item['id'] = $key;
			$item['config_id'] = $config_id;
			$item['wp_post_id'] = (string) $post->ID;
			$item['title'] = get_the_title( $post );
			$item['related_event_config_id'] = $related_event_id ? (string) get_post_meta( $related_event_id, '_taka_config_id', true ) : '';
			$out[ $key ] = $item;
		}
		return $out;
	}

	/** Import private planning items from backup data. */
	public static function import_items( $items, $mode, $dry_run, &$summary ) {
		$items = is_array( $items ) ? $items : array();
		foreach ( $items as $key => $item ) {
			if ( ! is_array( $item ) ) {
				$summary['skipped']++;
				continue;
			}
			$config_id = sanitize_key( $item['config_id'] ?? ( $item['id'] ?? $key ) );
			if ( '' === $config_id ) {
				$config_id = sanitize_key( 'planning-' . md5( wp_json_encode( $item ) ) );
			}
			$existing = self::find_item_by_config_id( $config_id );
			if ( $existing && 'missing' === $mode ) {
				$summary['skipped']++;
				continue;
			}
			if ( $dry_run ) {
				$summary[ $existing ? 'updated' : 'created' ]++;
				continue;
			}

			$post_data = array(
				'post_type'   => TAKA_PLATFORM_CPT_TOUR_PLANNING,
				'post_status' => 'private',
				'post_title'  => sanitize_text_field( $item['title'] ?? $config_id ),
			);
			if ( $existing ) {
				$post_data['ID'] = $existing;
				$post_id = 'overwrite' === $mode ? wp_update_post( $post_data, true ) : $existing;
				$summary['updated']++;
			} else {
				$post_id = wp_insert_post( $post_data, true );
				$summary['created']++;
			}
			if ( is_wp_error( $post_id ) ) {
				$summary['skipped']++;
				continue;
			}
			update_post_meta( $post_id, self::CONFIG_META, $config_id );
			$item['related_event_id'] = self::resolve_import_event_id( $item );
			foreach ( self::sanitize_item_fields( $item ) as $field => $value ) {
				update_post_meta( $post_id, '_taka_planning_' . $field, $value );
			}
		}
	}

	/** Query accessible planning items and apply private agenda filters. */
	private static function query_items( $filters = array() ) {
		$posts = get_posts( array( 'post_type' => TAKA_PLATFORM_CPT_TOUR_PLANNING, 'post_status' => 'any', 'posts_per_page' => -1 ) );
		$items = array();
		foreach ( $posts as $post ) {
			if ( ! self::user_can_access_item( get_current_user_id(), $post->ID, 'read' ) ) {
				continue;
			}
			$item = self::item_from_post( $post );
			if ( ! self::item_matches_filters( $item, $filters ) ) {
				continue;
			}
			$items[] = $item;
		}
		usort( $items, array( __CLASS__, 'compare_items' ) );
		return $items;
	}

	private static function item_matches_filters( $item, $filters ) {
		foreach ( array( 'tour_key', 'type', 'status', 'responsible_person' ) as $field ) {
			if ( '' !== trim( (string) ( $filters[ $field ] ?? '' ) ) && (string) ( $item[ $field ] ?? '' ) !== (string) $filters[ $field ] ) {
				return false;
			}
		}
		if ( ! empty( $filters['related_event_id'] ) && absint( $filters['related_event_id'] ) !== absint( $item['related_event_id'] ?? 0 ) ) {
			return false;
		}
		if ( ! empty( $filters['date_from'] ) && '' !== (string) ( $item['start_date'] ?? '' ) && strcmp( (string) $item['start_date'], (string) $filters['date_from'] ) < 0 ) {
			return false;
		}
		if ( ! empty( $filters['date_to'] ) && '' !== (string) ( $item['start_date'] ?? '' ) && strcmp( (string) $item['start_date'], (string) $filters['date_to'] ) > 0 ) {
			return false;
		}
		return true;
	}

	private static function item_from_post( $post ) {
		$post_id = absint( $post->ID ?? 0 );
		$item = self::default_item();
		$item['post_id'] = $post_id;
		$item['title'] = get_the_title( $post );
		foreach ( array_keys( $item ) as $field ) {
			if ( in_array( $field, array( 'post_id', 'title', 'related_event_title' ), true ) ) {
				continue;
			}
			$value = get_post_meta( $post_id, '_taka_planning_' . $field, true );
			if ( '' !== $value && array() !== $value ) {
				$item[ $field ] = $value;
			}
		}
		$item = array_merge( $item, self::sanitize_item_fields( $item ) );
		$item['post_id'] = $post_id;
		$item['title'] = get_the_title( $post );
		$item['related_event_title'] = absint( $item['related_event_id'] ) ? get_the_title( absint( $item['related_event_id'] ) ) : '';
		$item['edit_url'] = get_edit_post_link( $post_id, '' );
		return $item;
	}

	private static function default_item() {
		return array(
			'post_id' => 0,
			'title' => '',
			'tour_key' => '',
			'type' => 'other',
			'start_date' => '',
			'start_time' => '',
			'end_date' => '',
			'end_time' => '',
			'location' => '',
			'notes' => '',
			'responsible_person' => '',
			'financial_responsible_person' => '',
			'estimated_cost' => '',
			'actual_cost' => '',
			'currency' => 'EUR',
			'access_group' => 'assigned_users',
			'assigned_user_ids' => array(),
			'assigned_organizer_ids' => array(),
			'related_event_id' => 0,
			'related_event_title' => '',
			'status' => 'planned',
			'accommodation_name' => '',
			'address' => '',
			'checkin_date' => '',
			'checkin_time' => '',
			'checkout_date' => '',
			'checkout_time' => '',
			'rooms' => '',
			'room_types' => '',
			'guests' => '',
			'booking_reference' => '',
			'transfer_type' => '',
			'departure_location' => '',
			'arrival_location' => '',
			'departure_date' => '',
			'departure_time' => '',
			'arrival_date' => '',
			'arrival_time' => '',
			'carrier_provider' => '',
			'transfer_booking_reference' => '',
			'driver_responsible_person' => '',
			'meal_type' => '',
			'restaurant_location' => '',
			'meal_date' => '',
			'meal_time' => '',
			'people_count' => '',
		);
	}

	private static function sanitize_item_fields( $posted ) {
		$posted = is_array( $posted ) ? $posted : array();
		$clean = self::default_item();
		$clean['tour_key'] = sanitize_key( $posted['tour_key'] ?? '' );
		$clean['type'] = self::allowed_key( $posted['type'] ?? 'other', array_keys( self::type_labels() ), 'other' );
		$clean['start_date'] = self::sanitize_date( $posted['start_date'] ?? '' );
		$clean['start_time'] = self::sanitize_time( $posted['start_time'] ?? '' );
		$clean['end_date'] = self::sanitize_date( $posted['end_date'] ?? '' );
		$clean['end_time'] = self::sanitize_time( $posted['end_time'] ?? '' );
		$clean['location'] = sanitize_text_field( $posted['location'] ?? '' );
		$clean['notes'] = sanitize_textarea_field( $posted['notes'] ?? '' );
		$clean['responsible_person'] = sanitize_text_field( $posted['responsible_person'] ?? '' );
		$clean['financial_responsible_person'] = sanitize_text_field( $posted['financial_responsible_person'] ?? '' );
		$clean['estimated_cost'] = self::sanitize_money( $posted['estimated_cost'] ?? '' );
		$clean['actual_cost'] = self::sanitize_money( $posted['actual_cost'] ?? '' );
		$clean['currency'] = self::sanitize_currency( $posted['currency'] ?? 'EUR' );
		$clean['access_group'] = self::allowed_key( $posted['access_group'] ?? 'assigned_users', array_keys( self::access_group_labels() ), 'assigned_users' );
		$clean['assigned_user_ids'] = self::sanitize_id_list( $posted['assigned_user_ids'] ?? array() );
		$clean['assigned_organizer_ids'] = self::sanitize_id_list( $posted['assigned_organizer_ids'] ?? array() );
		$clean['related_event_id'] = absint( $posted['related_event_id'] ?? 0 );
		$clean['status'] = self::allowed_key( $posted['status'] ?? 'planned', array_keys( self::status_labels() ), 'planned' );
		$clean['accommodation_name'] = sanitize_text_field( $posted['accommodation_name'] ?? '' );
		$clean['address'] = sanitize_textarea_field( $posted['address'] ?? '' );
		$clean['checkin_date'] = self::sanitize_date( $posted['checkin_date'] ?? '' );
		$clean['checkin_time'] = self::sanitize_time( $posted['checkin_time'] ?? '' );
		$clean['checkout_date'] = self::sanitize_date( $posted['checkout_date'] ?? '' );
		$clean['checkout_time'] = self::sanitize_time( $posted['checkout_time'] ?? '' );
		$clean['rooms'] = '' === (string) ( $posted['rooms'] ?? '' ) ? '' : (string) max( 0, absint( $posted['rooms'] ) );
		$clean['room_types'] = sanitize_textarea_field( $posted['room_types'] ?? '' );
		$clean['guests'] = sanitize_textarea_field( $posted['guests'] ?? '' );
		$clean['booking_reference'] = sanitize_text_field( $posted['booking_reference'] ?? '' );
		$clean['transfer_type'] = self::allowed_key( $posted['transfer_type'] ?? '', array_keys( self::transfer_type_labels() ), '' );
		$clean['departure_location'] = sanitize_text_field( $posted['departure_location'] ?? '' );
		$clean['arrival_location'] = sanitize_text_field( $posted['arrival_location'] ?? '' );
		$clean['departure_date'] = self::sanitize_date( $posted['departure_date'] ?? '' );
		$clean['departure_time'] = self::sanitize_time( $posted['departure_time'] ?? '' );
		$clean['arrival_date'] = self::sanitize_date( $posted['arrival_date'] ?? '' );
		$clean['arrival_time'] = self::sanitize_time( $posted['arrival_time'] ?? '' );
		$clean['carrier_provider'] = sanitize_text_field( $posted['carrier_provider'] ?? '' );
		$clean['transfer_booking_reference'] = sanitize_text_field( $posted['transfer_booking_reference'] ?? '' );
		$clean['driver_responsible_person'] = sanitize_text_field( $posted['driver_responsible_person'] ?? '' );
		$clean['meal_type'] = self::allowed_key( $posted['meal_type'] ?? '', array_keys( self::meal_type_labels() ), '' );
		$clean['restaurant_location'] = sanitize_text_field( $posted['restaurant_location'] ?? '' );
		$clean['meal_date'] = self::sanitize_date( $posted['meal_date'] ?? '' );
		$clean['meal_time'] = self::sanitize_time( $posted['meal_time'] ?? '' );
		$clean['people_count'] = '' === (string) ( $posted['people_count'] ?? '' ) ? '' : (string) max( 0, absint( $posted['people_count'] ) );
		unset( $clean['post_id'], $clean['title'], $clean['related_event_title'] );
		return $clean;
	}

	private static function render_agenda_filters( $filters ) {
		?>
		<form method="get" class="taka-tour-planning-filters">
			<input type="hidden" name="page" value="taka-platform-tour-planning">
			<label><?php echo esc_html__( 'Tour', 'taka-platform' ); ?><br><input type="text" name="tour_key" value="<?php echo esc_attr( $filters['tour_key'] ?? '' ); ?>"></label>
			<label><?php echo esc_html__( 'From', 'taka-platform' ); ?><br><input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ?? '' ); ?>"></label>
			<label><?php echo esc_html__( 'To', 'taka-platform' ); ?><br><input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ?? '' ); ?>"></label>
			<label><?php echo esc_html__( 'Event', 'taka-platform' ); ?><br><?php self::event_select_html( 'related_event_id', absint( $filters['related_event_id'] ?? 0 ), true ); ?></label>
			<label><?php echo esc_html__( 'Type', 'taka-platform' ); ?><br><?php self::select_html( 'type', $filters['type'] ?? '', array( '' => __( 'All types', 'taka-platform' ) ) + self::type_labels() ); ?></label>
			<label><?php echo esc_html__( 'Responsible', 'taka-platform' ); ?><br><input type="text" name="responsible_person" value="<?php echo esc_attr( $filters['responsible_person'] ?? '' ); ?>"></label>
			<label><?php echo esc_html__( 'Status', 'taka-platform' ); ?><br><?php self::select_html( 'status', $filters['status'] ?? '', array( '' => __( 'All statuses', 'taka-platform' ) ) + self::status_labels() ); ?></label>
			<label><?php echo esc_html__( 'View', 'taka-platform' ); ?><br><?php self::select_html( 'view', $filters['view'] ?? 'timeline', self::view_labels() ); ?></label>
			<button class="button"><?php echo esc_html__( 'Filter', 'taka-platform' ); ?></button>
		</form>
		<?php
	}

	private static function render_grouped_items( $items, $empty_label, $callback ) {
		$groups = array();
		foreach ( $items as $item ) {
			$key = call_user_func( $callback, $item );
			$key = '' !== trim( (string) $key ) ? (string) $key : (string) $empty_label;
			$groups[ $key ][] = $item;
		}
		if ( empty( $groups ) ) {
			self::render_items_table( array() );
			return;
		}
		foreach ( $groups as $label => $group_items ) {
			echo '<h2>' . esc_html( $label ) . '</h2>';
			self::render_items_table( $group_items );
		}
	}

	private static function render_cost_overview( $items ) {
		$totals = array();
		foreach ( $items as $item ) {
			$currency = $item['currency'] ?: 'EUR';
			if ( ! isset( $totals[ $currency ] ) ) {
				$totals[ $currency ] = array( 'estimated' => 0.0, 'actual' => 0.0 );
			}
			$totals[ $currency ]['estimated'] += (float) str_replace( ',', '.', (string) $item['estimated_cost'] );
			$totals[ $currency ]['actual'] += (float) str_replace( ',', '.', (string) $item['actual_cost'] );
		}
		TAKA_Platform_Admin_Collapsible_Section::open( array(
			'id'            => 'tour-planning-cost-overview',
			'title'         => __( 'Cost overview', 'taka-platform' ),
			'help_text'     => __( 'Private estimated and actual cost totals for the filtered planning items.', 'taka-platform' ),
			'default_state' => TAKA_Platform_Admin_Collapsible_Section::STATE_EXPANDED,
		) );
		if ( empty( $totals ) ) {
			echo '<p>' . esc_html__( 'No costs found for the current filters.', 'taka-platform' ) . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Currency', 'taka-platform' ) . '</th><th>' . esc_html__( 'Estimated', 'taka-platform' ) . '</th><th>' . esc_html__( 'Actual', 'taka-platform' ) . '</th></tr></thead><tbody>';
			foreach ( $totals as $currency => $row ) {
				echo '<tr><td>' . esc_html( $currency ) . '</td><td>' . esc_html( number_format_i18n( $row['estimated'], 2 ) ) . '</td><td>' . esc_html( number_format_i18n( $row['actual'], 2 ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		TAKA_Platform_Admin_Collapsible_Section::close();
		self::render_items_table( $items );
	}

	private static function render_items_table( $items ) {
		?>
		<table class="widefat striped taka-tour-planning-table">
			<thead><tr>
				<th><?php echo esc_html__( 'Date', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Time', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Type', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Title', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Location', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Related event', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Responsible', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Financial owner', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Status', 'taka-platform' ); ?></th>
			</tr></thead>
			<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr><td colspan="9"><?php echo esc_html__( 'No private planning items found.', 'taka-platform' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $items as $item ) : ?>
						<tr>
							<td><?php echo esc_html( $item['start_date'] ?: '—' ); ?></td>
							<td><?php echo esc_html( self::time_range_label( $item ) ); ?></td>
							<td><?php echo wp_kses_post( self::type_icon( $item['type'] ) ); ?> <?php echo esc_html( self::type_labels()[ $item['type'] ] ?? $item['type'] ); ?></td>
							<td><strong><?php if ( ! empty( $item['edit_url'] ) && current_user_can( 'edit_post', absint( $item['post_id'] ?? 0 ) ) ) : ?><a href="<?php echo esc_url( $item['edit_url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a><?php else : ?><?php echo esc_html( $item['title'] ); ?><?php endif; ?></strong></td>
							<td><?php echo esc_html( $item['location'] ); ?></td>
							<td><?php echo esc_html( $item['related_event_title'] ); ?></td>
							<td><?php echo esc_html( $item['responsible_person'] ); ?></td>
							<td><?php echo esc_html( $item['financial_responsible_person'] ); ?></td>
							<td><?php echo esc_html( self::status_labels()[ $item['status'] ] ?? $item['status'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	private static function render_event_item_list( $event_id, $items ) {
		$add_url = add_query_arg(
			array(
				'post_type'          => TAKA_PLATFORM_CPT_TOUR_PLANNING,
				'taka_related_event' => absint( $event_id ),
			),
			admin_url( 'post-new.php' )
		);
		echo '<p>';
		if ( current_user_can( 'edit_taka_tour_planning' ) ) {
			echo '<a class="button" href="' . esc_url( $add_url ) . '">' . esc_html__( 'Add planning item for this event', 'taka-platform' ) . '</a> ';
		}
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=taka-platform-tour-planning' ) ) . '">' . esc_html__( 'Open Tour Planning agenda', 'taka-platform' ) . '</a></p>';
		if ( empty( $items ) ) {
			echo '<p class="description">' . esc_html__( 'No private planning items are linked to this event yet.', 'taka-platform' ) . '</p>';
			return;
		}
		self::render_items_table( $items );
	}

	private static function compare_items( $a, $b ) {
		$a_key = ( (string) ( $a['start_date'] ?? '' ) ) . ' ' . ( (string) ( $a['start_time'] ?? '' ) );
		$b_key = ( (string) ( $b['start_date'] ?? '' ) ) . ' ' . ( (string) ( $b['start_time'] ?? '' ) );
		return strcmp( trim( $a_key ), trim( $b_key ) ) ?: strcmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) );
	}

	private static function agenda_filters_from_request() {
		return array(
			'tour_key' => sanitize_key( wp_unslash( $_GET['tour_key'] ?? '' ) ),
			'date_from' => self::sanitize_date( wp_unslash( $_GET['date_from'] ?? '' ) ),
			'date_to' => self::sanitize_date( wp_unslash( $_GET['date_to'] ?? '' ) ),
			'related_event_id' => absint( $_GET['related_event_id'] ?? 0 ),
			'type' => self::allowed_key( wp_unslash( $_GET['type'] ?? '' ), array_merge( array( '' ), array_keys( self::type_labels() ) ), '' ),
			'responsible_person' => sanitize_text_field( wp_unslash( $_GET['responsible_person'] ?? '' ) ),
			'status' => self::allowed_key( wp_unslash( $_GET['status'] ?? '' ), array_merge( array( '' ), array_keys( self::status_labels() ) ), '' ),
			'view' => self::allowed_key( wp_unslash( $_GET['view'] ?? 'timeline' ), array_keys( self::view_labels() ), 'timeline' ),
		);
	}

	private static function accessible_item_ids_for_user( $user_id, $action = 'edit' ) {
		$ids = get_posts( array( 'post_type' => TAKA_PLATFORM_CPT_TOUR_PLANNING, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
		$out = array();
		foreach ( $ids as $post_id ) {
			if ( self::user_can_access_item( $user_id, absint( $post_id ), $action ) ) {
				$out[] = absint( $post_id );
			}
		}
		return $out;
	}

	private static function user_has_organizer_overlap( $user_id, $post_id ) {
		return ! empty( array_intersect( self::user_organizer_ids( $user_id ), self::assigned_organizer_ids( $post_id ) ) );
	}

	private static function user_can_access_related_event( $user_id, $post_id ) {
		$event_id = absint( self::meta( $post_id, 'related_event_id' ) );
		return $event_id && class_exists( 'TAKA_Platform_Admin' ) && TAKA_Platform_Admin::user_can_access_content( $user_id, $event_id, 'edit' );
	}

	private static function user_organizer_ids( $user_id ) {
		$ids = get_user_meta( $user_id, '_taka_platform_organizer_ids', true );
		if ( ! is_array( $ids ) ) {
			$ids = array_filter( preg_split( '/\s*,\s*/', (string) $ids ) );
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	private static function assigned_user_ids( $post_id ) {
		return self::sanitize_id_list( self::meta( $post_id, 'assigned_user_ids' ) );
	}

	private static function assigned_organizer_ids( $post_id ) {
		return self::sanitize_id_list( self::meta( $post_id, 'assigned_organizer_ids' ) );
	}

	private static function can_save_item( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return false; }
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { return false; }
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) { return false; }
		return current_user_can( 'edit_post', $post_id );
	}

	private static function find_item_by_config_id( $config_id ) {
		$ids = get_posts( array( 'post_type' => TAKA_PLATFORM_CPT_TOUR_PLANNING, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => self::CONFIG_META, 'meta_value' => sanitize_key( $config_id ) ) );
		return ! empty( $ids ) ? absint( $ids[0] ) : 0;
	}

	private static function resolve_import_event_id( $item ) {
		$direct = absint( $item['related_event_id'] ?? 0 );
		if ( $direct && TAKA_PLATFORM_CPT_EVENT === get_post_type( $direct ) ) {
			return $direct;
		}
		$config_id = sanitize_text_field( $item['related_event_config_id'] ?? '' );
		if ( '' === $config_id ) {
			return 0;
		}
		$ids = get_posts( array( 'post_type' => TAKA_PLATFORM_CPT_EVENT, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_taka_config_id', 'meta_value' => $config_id ) );
		return ! empty( $ids ) ? absint( $ids[0] ) : 0;
	}

	private static function meta( $post_id, $field ) {
		return get_post_meta( $post_id, '_taka_planning_' . $field, true );
	}

	private static function type_labels() {
		return array(
			'accommodation' => __( 'Accommodation / overnight stay', 'taka-platform' ),
			'transfer' => __( 'Transfer', 'taka-platform' ),
			'meal' => __( 'Meal / catering / restaurant', 'taka-platform' ),
			'free_time' => __( 'Free time', 'taka-platform' ),
			'excursion' => __( 'Excursion', 'taka-platform' ),
			'internal_appointment' => __( 'Internal appointment', 'taka-platform' ),
			'other' => __( 'Other', 'taka-platform' ),
		);
	}

	private static function status_labels() {
		return array(
			'planned' => __( 'Planned', 'taka-platform' ),
			'requested' => __( 'Requested', 'taka-platform' ),
			'confirmed' => __( 'Confirmed', 'taka-platform' ),
			'paid' => __( 'Paid', 'taka-platform' ),
			'cancelled' => __( 'Cancelled', 'taka-platform' ),
		);
	}

	private static function transfer_type_labels() {
		return array(
			'' => __( 'Select transfer type', 'taka-platform' ),
			'car' => __( 'Car', 'taka-platform' ),
			'train' => __( 'Train', 'taka-platform' ),
			'flight' => __( 'Flight', 'taka-platform' ),
			'taxi' => __( 'Taxi', 'taka-platform' ),
			'public_transport' => __( 'Public transport', 'taka-platform' ),
			'other' => __( 'Other', 'taka-platform' ),
		);
	}

	private static function meal_type_labels() {
		return array(
			'' => __( 'Select meal type', 'taka-platform' ),
			'breakfast' => __( 'Breakfast', 'taka-platform' ),
			'lunch' => __( 'Lunch', 'taka-platform' ),
			'dinner' => __( 'Dinner', 'taka-platform' ),
			'restaurant' => __( 'Restaurant', 'taka-platform' ),
			'catering' => __( 'Catering', 'taka-platform' ),
			'private_invitation' => __( 'Private invitation', 'taka-platform' ),
			'other' => __( 'Other', 'taka-platform' ),
		);
	}

	private static function access_group_labels() {
		return array(
			'admin_only' => __( 'Admins only', 'taka-platform' ),
			'assigned_users' => __( 'Assigned users', 'taka-platform' ),
			'organizer_members' => __( 'Assigned organizer members', 'taka-platform' ),
			'related_event_editors' => __( 'Related event editors', 'taka-platform' ),
			'all_planners' => __( 'All tour planners', 'taka-platform' ),
		);
	}

	private static function view_labels() {
		return array(
			'timeline' => __( 'Timeline / agenda', 'taka-platform' ),
			'by_day' => __( 'Grouped by day', 'taka-platform' ),
			'by_event' => __( 'Grouped by station / event', 'taka-platform' ),
			'by_type' => __( 'Grouped by type', 'taka-platform' ),
			'costs' => __( 'Cost overview', 'taka-platform' ),
		);
	}

	private static function currency_choices() {
		$choices = class_exists( 'TAKA_Platform_Data' ) ? TAKA_Platform_Data::option_list_choices( 'currency', TAKA_Platform_Data::platform_fallback_language() ) : array();
		return ! empty( $choices ) ? $choices : array( 'EUR' => 'EUR', 'USD' => 'USD', 'GBP' => 'GBP' );
	}

	private static function type_icon( $type ) {
		$icons = array(
			'accommodation' => 'dashicons-building',
			'transfer' => 'dashicons-airplane',
			'meal' => 'dashicons-food',
			'free_time' => 'dashicons-clock',
			'excursion' => 'dashicons-location-alt',
			'internal_appointment' => 'dashicons-calendar-alt',
			'other' => 'dashicons-clipboard',
		);
		return '<span class="dashicons ' . esc_attr( $icons[ $type ] ?? 'dashicons-clipboard' ) . '" aria-hidden="true"></span>';
	}

	private static function time_range_label( $item ) {
		$start = trim( (string) ( $item['start_time'] ?? '' ) );
		$end = trim( (string) ( $item['end_time'] ?? '' ) );
		return '' !== $start && '' !== $end ? $start . ' - ' . $end : ( $start ?: $end );
	}

	private static function has_accommodation_data( $item ) {
		return self::has_any_item_value( $item, array( 'accommodation_name', 'address', 'checkin_date', 'checkout_date', 'rooms', 'room_types', 'guests', 'booking_reference' ) );
	}

	private static function has_transfer_data( $item ) {
		return self::has_any_item_value( $item, array( 'transfer_type', 'departure_location', 'arrival_location', 'departure_date', 'arrival_date', 'carrier_provider', 'transfer_booking_reference', 'driver_responsible_person' ) );
	}

	private static function has_meal_data( $item ) {
		return self::has_any_item_value( $item, array( 'meal_type', 'restaurant_location', 'meal_date', 'meal_time', 'people_count' ) );
	}

	private static function has_any_item_value( $item, $fields ) {
		foreach ( $fields as $field ) {
			if ( '' !== trim( (string) ( $item[ $field ] ?? '' ) ) ) {
				return true;
			}
		}
		return false;
	}

	private static function allowed_key( $value, $allowed, $fallback ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	private static function sanitize_date( $value ) {
		$value = trim( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}

	private static function sanitize_time( $value ) {
		$value = trim( (string) $value );
		return preg_match( '/^\d{2}:\d{2}$/', $value ) ? $value : '';
	}

	private static function sanitize_money( $value ) {
		return class_exists( 'TAKA_Platform_Data' ) ? TAKA_Platform_Data::sanitize_money_value( $value ) : preg_replace( '/[^0-9.,-]/', '', (string) $value );
	}

	private static function sanitize_currency( $value ) {
		$value = strtoupper( sanitize_key( (string) $value ) );
		return '' !== $value ? $value : 'EUR';
	}

	private static function sanitize_id_list( $value ) {
		if ( ! is_array( $value ) ) {
			$value = array_filter( preg_split( '/\s*,\s*/', (string) $value ) );
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
	}

	private static function text_field( $field, $label, $value ) {
		self::field( $label, '<input class="widefat" type="text" name="taka_planning[' . esc_attr( $field ) . ']" value="' . esc_attr( $value ) . '">' );
	}

	private static function textarea_field( $field, $label, $value ) {
		self::field( $label, '<textarea class="widefat" rows="3" name="taka_planning[' . esc_attr( $field ) . ']">' . esc_textarea( $value ) . '</textarea>' );
	}

	private static function date_field( $field, $label, $value ) {
		self::field( $label, '<input type="date" name="taka_planning[' . esc_attr( $field ) . ']" value="' . esc_attr( $value ) . '">' );
	}

	private static function time_field( $field, $label, $value ) {
		self::field( $label, '<input type="time" name="taka_planning[' . esc_attr( $field ) . ']" value="' . esc_attr( $value ) . '">' );
	}

	private static function number_field( $field, $label, $value ) {
		self::field( $label, '<input type="number" min="0" name="taka_planning[' . esc_attr( $field ) . ']" value="' . esc_attr( $value ) . '">' );
	}

	private static function money_field( $field, $label, $value ) {
		self::field( $label, '<input type="text" inputmode="decimal" name="taka_planning[' . esc_attr( $field ) . ']" value="' . esc_attr( $value ) . '">' );
	}

	private static function select_field( $field, $label, $current, $choices ) {
		ob_start();
		self::select_html( 'taka_planning[' . $field . ']', $current, $choices );
		self::field( $label, ob_get_clean() );
	}

	private static function event_select_field( $field, $label, $current ) {
		ob_start();
		self::event_select_html( 'taka_planning[' . $field . ']', $current, false );
		self::field( $label, ob_get_clean() );
	}

	private static function user_multiselect_field( $field, $label, $selected ) {
		$users = get_users( array( 'orderby' => 'display_name', 'order' => 'ASC', 'fields' => array( 'ID', 'display_name', 'user_login' ) ) );
		$html = '<select class="widefat" name="taka_planning[' . esc_attr( $field ) . '][]" multiple size="6">';
		foreach ( $users as $user ) {
			$html .= '<option value="' . esc_attr( (string) $user->ID ) . '" ' . selected( in_array( (int) $user->ID, (array) $selected, true ), true, false ) . '>' . esc_html( $user->display_name ?: $user->user_login ) . '</option>';
		}
		$html .= '</select>';
		self::field( $label, $html );
	}

	private static function organizer_multiselect_field( $field, $label, $selected ) {
		$posts = get_posts( array( 'post_type' => TAKA_PLATFORM_CPT_ORGANIZER, 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$html = '<select class="widefat" name="taka_planning[' . esc_attr( $field ) . '][]" multiple size="6">';
		foreach ( $posts as $post ) {
			$html .= '<option value="' . esc_attr( (string) $post->ID ) . '" ' . selected( in_array( (int) $post->ID, (array) $selected, true ), true, false ) . '>' . esc_html( get_the_title( $post ) ) . '</option>';
		}
		$html .= '</select>';
		self::field( $label, $html );
	}

	private static function field( $label, $html ) {
		echo '<p><label><strong>' . esc_html( $label ) . '</strong><br>' . $html . '</label></p>';
	}

	private static function select_html( $name, $current, $choices ) {
		echo '<select class="widefat" name="' . esc_attr( $name ) . '">';
		foreach ( $choices as $value => $label ) {
			echo '<option value="' . esc_attr( (string) $value ) . '" ' . selected( (string) $current, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	private static function event_select_html( $name, $current, $include_all ) {
		$events = get_posts( array( 'post_type' => TAKA_PLATFORM_CPT_EVENT, 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		echo '<select class="widefat" name="' . esc_attr( $name ) . '">';
		echo '<option value="">' . esc_html( $include_all ? __( 'All events', 'taka-platform' ) : __( 'No related event', 'taka-platform' ) ) . '</option>';
		foreach ( $events as $event ) {
			echo '<option value="' . esc_attr( (string) $event->ID ) . '" ' . selected( absint( $current ), (int) $event->ID, false ) . '>' . esc_html( get_the_title( $event ) ) . '</option>';
		}
		echo '</select>';
	}
}

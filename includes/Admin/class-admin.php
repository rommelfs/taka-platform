<?php
/**
 * Native WordPress admin CMS for tour events.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Admin {
	const NONCE = 'taka_tour_admin_nonce';
	const IMPORT_NONCE = 'taka_tour_import_export_nonce';
	const MEDIA_OPTION = 'taka_tour_media_settings';

	/** Register admin hooks. */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_menu', array( __CLASS__, 'limit_organizer_admin_menu' ), 999 );
		add_action( 'admin_init', array( __CLASS__, 'ensure_capabilities' ) );
		add_action( 'admin_init', array( __CLASS__, 'guard_event_edit_screen' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'show_user_profile', array( __CLASS__, 'render_user_organizer_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_user_organizer_fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_user_organizer_fields' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_user_organizer_fields' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_event_admin_query' ) );
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_event_meta_caps' ), 10, 4 );
		add_action( 'save_post_taka_organizer', array( __CLASS__, 'save_organizer' ) );
		add_action( 'save_post_taka_venue', array( __CLASS__, 'save_venue' ) );
		add_action( 'save_post_taka_event', array( __CLASS__, 'save_event' ) );
		add_action( 'admin_post_taka_tour_save_media', array( __CLASS__, 'handle_save_media' ) );
		add_action( 'admin_post_taka_tour_import_config', array( __CLASS__, 'handle_import_config' ) );
		add_action( 'admin_post_taka_platform_save_hero', array( __CLASS__, 'handle_save_hero' ) );
		add_action( 'admin_post_taka_platform_save_sections', array( __CLASS__, 'handle_save_sections' ) );
		add_action( 'admin_post_taka_platform_save_dashboard_settings', array( __CLASS__, 'handle_save_dashboard_settings' ) );
		add_action( 'admin_post_taka_platform_save_booking_information', array( __CLASS__, 'handle_save_booking_information' ) );
		add_action( 'admin_post_taka_platform_sync_translations', array( __CLASS__, 'handle_sync_translations' ) );
		add_action( 'admin_post_taka_platform_export_translation_audit', array( __CLASS__, 'handle_export_translation_audit' ) );
	}


	/** Ensure platform roles and capabilities are available. */
	public static function ensure_capabilities() {
		if ( ! function_exists( 'get_role' ) ) { return; }
		$organizer_caps = array(
			'read',
			'upload_files',
			'edit_taka_events',
			'edit_taka_event',
			'read_taka_event',
			'publish_taka_events',
			'edit_published_taka_events',
			'edit_taka_organizer_profile',
		);

		if ( ! get_role( 'taka_organizer' ) && function_exists( 'add_role' ) ) {
			add_role(
				'taka_organizer',
				__( 'TAKA Organizer', 'taka-platform' ),
				array_fill_keys( $organizer_caps, true )
			);
		}

		$organizer_role = get_role( 'taka_organizer' );
		if ( $organizer_role ) {
			foreach ( $organizer_caps as $cap ) {
				$organizer_role->add_cap( $cap );
			}
			foreach ( array( 'manage_options', 'edit_users', 'activate_plugins', 'switch_themes', 'delete_users', 'edit_others_taka_events', 'delete_taka_event', 'delete_taka_events', 'delete_others_taka_events', 'read_private_taka_events' ) as $cap ) {
				$organizer_role->remove_cap( $cap );
			}
		}

		$role = get_role( 'administrator' );
		if ( ! $role ) { return; }
		foreach ( array( 'manage_taka_tour', 'edit_taka_events', 'edit_taka_event', 'edit_others_taka_events', 'publish_taka_events', 'edit_published_taka_events', 'read_taka_event', 'read_private_taka_events', 'delete_taka_event', 'delete_taka_events', 'delete_others_taka_events', 'edit_taka_organizers', 'edit_taka_venues', 'edit_taka_organizer_profile' ) as $cap ) {
			$role->add_cap( $cap );
		}
	}

	/** Register admin CPTs. */
	public static function register_post_types() {
		self::register_post_type( TAKA_PLATFORM_CPT_EVENT, __( 'Events', 'taka-platform' ), __( 'Event hinzufügen', 'taka-platform' ), 'dashicons-calendar-alt' );
		self::register_post_type( TAKA_PLATFORM_CPT_ORGANIZER, __( 'Organizers', 'taka-platform' ), __( 'Organizer hinzufügen', 'taka-platform' ), 'dashicons-groups' );
		self::register_post_type( TAKA_PLATFORM_CPT_VENUE, __( 'Venues', 'taka-platform' ), __( 'Venue hinzufügen', 'taka-platform' ), 'dashicons-location-alt' );
	}

	/** Register one event-tour CPT. */
	private static function register_post_type( $post_type, $name, $add_new_item, $icon ) {
		$args = array(
			'labels'       => array(
				'name'          => $name,
				'singular_name' => $name,
				'add_new_item'  => $add_new_item,
				'edit_item'     => sprintf( __( '%s bearbeiten', 'taka-platform' ), $name ),
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'taka-platform',
			'menu_icon'    => $icon,
			'supports'     => array( 'title', 'editor' ),
			'capability_type' => 'post',
		);

		if ( TAKA_PLATFORM_CPT_EVENT === $post_type ) {
			$args['map_meta_cap'] = true;
			$args['capabilities'] = array(
				'edit_post'              => 'edit_taka_event',
				'read_post'              => 'read_taka_event',
				'delete_post'            => 'delete_taka_event',
				'edit_posts'             => 'edit_taka_events',
				'edit_others_posts'      => 'edit_others_taka_events',
				'publish_posts'          => 'publish_taka_events',
				'edit_published_posts'   => 'edit_published_taka_events',
				'read_private_posts'     => 'read_private_taka_events',
				'delete_posts'           => 'delete_taka_events',
				'delete_others_posts'    => 'delete_others_taka_events',
				'create_posts'           => 'edit_taka_events',
			);
		}

		if ( TAKA_PLATFORM_CPT_ORGANIZER === $post_type ) {
			$args['map_meta_cap'] = true;
			$args['capabilities'] = array(
				'edit_post'              => 'edit_taka_organizer_profile',
				'read_post'              => 'edit_taka_organizer_profile',
				'delete_post'            => 'manage_options',
				'edit_posts'             => 'edit_taka_organizer_profile',
				'edit_others_posts'      => 'manage_options',
				'publish_posts'          => 'manage_options',
				'edit_published_posts'   => 'edit_taka_organizer_profile',
				'read_private_posts'     => 'manage_options',
				'delete_posts'           => 'manage_options',
				'delete_others_posts'    => 'manage_options',
				'create_posts'           => 'manage_options',
			);
		}

		register_post_type(
			$post_type,
			$args
		);
	}

	/** Register menu pages. */
	public static function register_menu() {
		add_menu_page( __( 'TAKA Platform', 'taka-platform' ), __( 'TAKA Platform', 'taka-platform' ), 'edit_taka_events', 'taka-platform', array( __CLASS__, 'render_dashboard' ), 'dashicons-tickets-alt', 28 );
		add_submenu_page( 'taka-platform', __( 'Dashboard', 'taka-platform' ), __( 'Dashboard', 'taka-platform' ), 'edit_taka_events', 'taka-platform', array( __CLASS__, 'render_dashboard' ) );
		add_submenu_page( 'taka-platform', __( 'Media', 'taka-platform' ), __( 'Media', 'taka-platform' ), 'manage_options', 'taka-tour-media', array( __CLASS__, 'render_media' ) );
		add_submenu_page( 'taka-platform', __( 'Content Sections', 'taka-platform' ), __( 'Content Sections', 'taka-platform' ), 'manage_options', 'taka-platform-content-sections', array( __CLASS__, 'render_content_sections' ) );
		add_submenu_page( 'taka-platform', __( 'Import / Export', 'taka-platform' ), __( 'Import / Export', 'taka-platform' ), 'manage_options', 'taka-tour-import-export', array( __CLASS__, 'render_import_export' ) );
		add_submenu_page( 'taka-platform', __( 'Settings', 'taka-platform' ), __( 'Settings', 'taka-platform' ), 'manage_options', 'taka-tour-settings', array( __CLASS__, 'render_settings' ) );
		add_submenu_page( 'taka-platform', __( 'Translations', 'taka-platform' ), __( 'Translations', 'taka-platform' ), 'manage_options', 'taka-platform-translations', array( __CLASS__, 'render_translations' ) );
	}


	/** Keep wp-admin focused for organizer users without removing backend access. */
	public static function limit_organizer_admin_menu() {
		if ( self::current_user_is_platform_admin() || ! current_user_can( 'edit_taka_events' ) ) {
			return;
		}

		foreach ( array( 'edit.php', 'edit.php?post_type=page', 'edit-comments.php', 'tools.php', 'options-general.php', 'themes.php', 'plugins.php', 'users.php' ) as $menu_slug ) {
			remove_menu_page( $menu_slug );
		}
	}

	/** Enqueue WordPress media picker for plugin admin screens. */
	public static function enqueue_admin_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_taka_screen = $screen && ( 0 === strpos( (string) $screen->id, 'taka_' ) || false !== strpos( (string) $screen->id, 'taka-platform' ) );
		if ( ! $is_taka_screen && false === strpos( (string) $hook, 'taka-platform' ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'taka-platform-admin', TAKA_PLATFORM_PLUGIN_URL . 'assets/css/admin.css', array(), TAKA_PLATFORM_VERSION );
		wp_enqueue_script( 'taka-platform-admin', TAKA_PLATFORM_PLUGIN_URL . 'assets/js/admin.js', array(), TAKA_PLATFORM_VERSION, true );
		wp_enqueue_script( 'taka-platform-media-fields', TAKA_PLATFORM_PLUGIN_URL . 'assets/js/media-fields.js', array(), TAKA_PLATFORM_VERSION, true );
	}

	/** Register meta boxes. */
	public static function add_meta_boxes() {
		add_meta_box( 'taka_organizer_details', __( 'Organizer details', 'taka-platform' ), array( __CLASS__, 'render_organizer_meta_box' ), TAKA_PLATFORM_CPT_ORGANIZER, 'normal', 'high' );
		add_meta_box( 'taka_venue_details', __( 'Venue details', 'taka-platform' ), array( __CLASS__, 'render_venue_meta_box' ), TAKA_PLATFORM_CPT_VENUE, 'normal', 'high' );
		add_meta_box( 'taka_event_details', __( 'Event details', 'taka-platform' ), array( __CLASS__, 'render_event_meta_box' ), TAKA_PLATFORM_CPT_EVENT, 'normal', 'high' );
	}

	/** Render dashboard. */
	public static function render_dashboard() {
		if ( ! current_user_can( 'edit_taka_events' ) ) { return; }
		if ( ! current_user_can( 'manage_options' ) ) {
			$organizers = self::get_current_user_organizer_ids();
			$events     = self::get_events_for_organizers( $organizers );
			?>
			<div class="wrap">
				<h1><?php echo esc_html__( 'TAKA Platform Dashboard', 'taka-platform' ); ?></h1>
				<h2><?php echo esc_html__( 'My Organizer(s)', 'taka-platform' ); ?></h2>
				<ul>
					<?php foreach ( $organizers as $organizer_id ) : ?>
						<li><?php echo esc_html( get_the_title( $organizer_id ) ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p><strong><?php echo esc_html__( 'My Events', 'taka-platform' ); ?>:</strong> <?php echo esc_html( (string) count( $events ) ); ?></p>
				<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . TAKA_PLATFORM_CPT_EVENT ) ); ?>"><?php echo esc_html__( 'Create Event', 'taka-platform' ); ?></a></p>
			</div>
			<?php
			return;
		}
		$config             = TAKA_Platform_Data::load_config();
		$wp_event_count     = TAKA_Platform_Data::count_wp_events();
		$config_event_count = count( $config['events'] ?? array() );
		$translations       = glob( TAKA_PLATFORM_PLUGIN_DIR . 'translations/*.json' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'TAKA Platform Dashboard', 'taka-platform' ); ?></h1>
			<table class="widefat striped" style="max-width: 860px;"><tbody>
				<tr><th><?php echo esc_html__( 'Plugin version', 'taka-platform' ); ?></th><td><?php echo esc_html( TAKA_PLATFORM_VERSION ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Config file', 'taka-platform' ); ?></th><td><?php echo file_exists( TAKA_PLATFORM_PLUGIN_DIR . 'config/tour-events.php' ) ? esc_html__( 'found', 'taka-platform' ) : esc_html__( 'missing', 'taka-platform' ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'WordPress events', 'taka-platform' ); ?></th><td><?php echo esc_html( (string) $wp_event_count ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Config events', 'taka-platform' ); ?></th><td><?php echo esc_html( (string) $config_event_count ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Active frontend source', 'taka-platform' ); ?></th><td><?php echo esc_html( TAKA_Platform_Data::is_using_wp_events() ? __( 'WordPress', 'taka-platform' ) : __( 'Config fallback', 'taka-platform' ) ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Translation files', 'taka-platform' ); ?></th><td><?php echo esc_html( (string) count( is_array( $translations ) ? $translations : array() ) ); ?></td></tr>
			</tbody></table>
			<p><?php echo esc_html__( 'TAKA – Ticketing, Attendance, Knowledge & Administration. Use the Events, Organizers, Venues, Media and Import / Export screens to manage reusable international event tours.', 'taka-platform' ); ?></p>
		</div>
		<?php
	}

	/** Get organizer IDs assigned to a user. */
	private static function get_user_organizer_ids( $user_id ) {
		$ids = get_user_meta( $user_id, '_taka_platform_organizer_ids', true );
		if ( ! is_array( $ids ) ) {
			$ids = array_filter( preg_split( '/\s*,\s*/', (string) $ids ) );
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	/** Get organizer IDs for the current user. */
	private static function get_current_user_organizer_ids() {
		return self::get_user_organizer_ids( get_current_user_id() );
	}

	/** Whether the current user has unrestricted platform admin access. */
	private static function current_user_is_platform_admin() {
		return current_user_can( 'manage_options' );
	}

	/** Whether a user can access an organizer-owned event. */
	private static function user_can_access_event( $user_id, $post_id ) {
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}
		$organizer_id = absint( get_post_meta( $post_id, '_taka_organizer_id', true ) );
		if ( ! $organizer_id ) {
			return false;
		}
		return in_array( $organizer_id, self::get_user_organizer_ids( $user_id ), true );
	}

	/** Load event posts for assigned organizer IDs. */
	private static function get_events_for_organizers( $organizer_ids ) {
		if ( empty( $organizer_ids ) ) {
			return array();
		}
		return get_posts(
			array(
				'post_type'      => TAKA_PLATFORM_CPT_EVENT,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => '_taka_organizer_id',
						'value'   => array_map( 'strval', $organizer_ids ),
						'compare' => 'IN',
					),
				),
			)
		);
	}

	/** Render user-profile organizer assignment field for administrators. */
	public static function render_user_organizer_fields( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$selected   = self::get_user_organizer_ids( $user->ID );
		$organizers = get_posts( array( 'post_type' => TAKA_PLATFORM_CPT_ORGANIZER, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		?>
		<h2><?php echo esc_html__( 'TAKA Platform', 'taka-platform' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="taka-platform-organizers"><?php echo esc_html__( 'Assigned organizers', 'taka-platform' ); ?></label></th>
				<td>
					<?php wp_nonce_field( 'taka_platform_user_organizers', 'taka_platform_user_organizers_nonce' ); ?>
					<select id="taka-platform-organizers" name="taka_platform_organizer_ids[]" multiple size="6" style="min-width: 320px;">
						<?php foreach ( $organizers as $organizer ) : ?>
							<option value="<?php echo esc_attr( (string) $organizer->ID ); ?>" <?php selected( in_array( (int) $organizer->ID, $selected, true ) ); ?>><?php echo esc_html( get_the_title( $organizer ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php echo esc_html__( 'TAKA Organizer users may create and edit events only for these organizers.', 'taka-platform' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/** Save user-profile organizer assignments. */
	public static function save_user_organizer_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_POST['taka_platform_user_organizers_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['taka_platform_user_organizers_nonce'] ) ), 'taka_platform_user_organizers' ) ) {
			return;
		}
		$ids = isset( $_POST['taka_platform_organizer_ids'] ) && is_array( $_POST['taka_platform_organizer_ids'] ) ? wp_unslash( $_POST['taka_platform_organizer_ids'] ) : array();
		update_user_meta( $user_id, '_taka_platform_organizer_ids', array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) );
	}

	/** Limit event list table to assigned organizers for non-admin organizer users. */
	public static function filter_event_admin_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || self::current_user_is_platform_admin() ) {
			return;
		}
		$post_type = $query->get( 'post_type' ) ?: '';
		if ( ! in_array( $post_type, array( TAKA_PLATFORM_CPT_EVENT, TAKA_PLATFORM_CPT_ORGANIZER ), true ) ) {
			return;
		}
		$organizer_ids = self::get_current_user_organizer_ids();
		if ( TAKA_PLATFORM_CPT_ORGANIZER === $post_type ) {
			$query->set( 'post__in', ! empty( $organizer_ids ) ? $organizer_ids : array( 0 ) );
			return;
		}
		if ( empty( $organizer_ids ) ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}
		$query->set(
			'meta_query',
			array(
				array(
					'key'     => '_taka_organizer_id',
					'value'   => array_map( 'strval', $organizer_ids ),
					'compare' => 'IN',
				),
			)
		);
	}

	/** Enforce organizer-scoped event editing at capability level. */
	public static function map_event_meta_caps( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'edit_post', 'delete_post', 'read_post' ), true ) || empty( $args[0] ) ) {
			return $caps;
		}
		$post = get_post( (int) $args[0] );
		if ( ! $post || ! in_array( $post->post_type, array( TAKA_PLATFORM_CPT_EVENT, TAKA_PLATFORM_CPT_ORGANIZER ), true ) ) {
			return $caps;
		}
		if ( user_can( $user_id, 'manage_options' ) ) {
			return $caps;
		}
		if ( 'delete_post' === $cap ) {
			return array( 'do_not_allow' );
		}
		if ( TAKA_PLATFORM_CPT_ORGANIZER === $post->post_type ) {
			return in_array( (int) $post->ID, self::get_user_organizer_ids( $user_id ), true ) ? array( 'edit_taka_organizer_profile' ) : array( 'do_not_allow' );
		}
		return self::user_can_access_event( $user_id, $post->ID ) ? array( 'edit_taka_events' ) : array( 'do_not_allow' );
	}

	/** Block direct access to foreign event edit screens for organizer users. */
	public static function guard_event_edit_screen() {
		if ( self::current_user_is_platform_admin() || empty( $_GET['post'] ) ) {
			return;
		}
		$post_id = absint( $_GET['post'] );
		$post    = get_post( $post_id );
		if ( $post && TAKA_PLATFORM_CPT_EVENT === $post->post_type && ! self::user_can_access_event( get_current_user_id(), $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to edit this event.', 'taka-platform' ) );
		}
		if ( $post && TAKA_PLATFORM_CPT_ORGANIZER === $post->post_type && ! in_array( $post_id, self::get_current_user_organizer_ids(), true ) ) {
			wp_die( esc_html__( 'You are not allowed to edit this organizer.', 'taka-platform' ) );
		}
	}

	/** Render global media settings. */
	public static function render_media() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$media = TAKA_Platform_Data::get_global_media_settings();
		$fields = TAKA_Platform_Data::global_media_fields();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'TAKA Platform Media', 'taka-platform' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="taka_tour_save_media">
				<?php wp_nonce_field( self::MEDIA_OPTION, self::NONCE ); ?>
				<table class="form-table" role="presentation"><tbody>
				<?php foreach ( $fields as $key => $label ) : ?>
					<?php $id = absint( $media[ $key . '_id' ] ?? 0 ); $url = (string) ( $media[ $key . '_url' ] ?? '' ); ?>
					<tr>
						<th scope="row"><label for="taka_media_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td>
							<input id="taka_media_<?php echo esc_attr( $key ); ?>" type="hidden" name="media[<?php echo esc_attr( $key ); ?>_id]" value="<?php echo esc_attr( (string) $id ); ?>">
							<button type="button" class="button" data-taka-media-pick data-target="taka_media_<?php echo esc_attr( $key ); ?>" data-preview="taka_media_preview_<?php echo esc_attr( $key ); ?>"><?php echo esc_html__( 'Select image', 'taka-platform' ); ?></button>
							<button type="button" class="button" data-taka-media-remove data-target="taka_media_<?php echo esc_attr( $key ); ?>" data-preview="taka_media_preview_<?php echo esc_attr( $key ); ?>"><?php echo esc_html__( 'Remove image', 'taka-platform' ); ?></button>
							<div id="taka_media_preview_<?php echo esc_attr( $key ); ?>"><?php self::image_preview( $id, $url ); ?></div>
							<p><label><?php echo esc_html__( 'Fallback URL', 'taka-platform' ); ?><br><input class="regular-text" type="url" name="media[<?php echo esc_attr( $key ); ?>_url]" value="<?php echo esc_attr( $url ); ?>"></label></p>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
				<?php submit_button( __( 'Save media settings', 'taka-platform' ) ); ?>
			</form>
		</div>
		<?php
	}

	/** Save global media settings. */
	public static function handle_save_media() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) ); }
		check_admin_referer( self::MEDIA_OPTION, self::NONCE );
		$posted = isset( $_POST['media'] ) && is_array( $_POST['media'] ) ? wp_unslash( $_POST['media'] ) : array();
		$clean = array();
		foreach ( TAKA_Platform_Data::global_media_fields() as $key => $label ) {
			$clean[ $key . '_id' ] = absint( $posted[ $key . '_id' ] ?? 0 );
			$clean[ $key . '_url' ] = esc_url_raw( $posted[ $key . '_url' ] ?? '' );
		}
		update_option( self::MEDIA_OPTION, $clean, false );
		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=taka-tour-media' ) ) );
		exit;
	}

	/** Render import/export screen. */
	public static function render_import_export() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$result = get_transient( 'taka_tour_import_result' );
		if ( $result ) { delete_transient( 'taka_tour_import_result' ); }
		$export = TAKA_Platform_Data::export_config_from_wp();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'TAKA Platform Import / Export', 'taka-platform' ); ?></h1>
			<?php if ( is_array( $result ) ) : ?>
				<div class="notice notice-info"><p><?php echo esc_html( $result['message'] ?? '' ); ?></p><pre><?php echo esc_html( print_r( $result['summary'] ?? array(), true ) ); ?></pre></div>
			<?php endif; ?>
			<h2><?php echo esc_html__( 'Import config/tour-events.php', 'taka-platform' ); ?></h2>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="taka_tour_import_config">
				<?php wp_nonce_field( 'taka_tour_import_config', self::IMPORT_NONCE ); ?>
				<table class="form-table" role="presentation"><tbody>
					<tr><th scope="row"><?php echo esc_html__( 'Import source', 'taka-platform' ); ?></th><td>
						<p><label><input type="radio" name="source" value="bundled" checked> <?php echo esc_html__( 'Bundled config/tour-events.php', 'taka-platform' ); ?></label></p>
						<p><label><input type="radio" name="source" value="upload"> <?php echo esc_html__( 'Upload PHP config file', 'taka-platform' ); ?></label><br><input type="file" name="config_file" accept=".php"></p>
						<p><label><input type="radio" name="source" value="json"> <?php echo esc_html__( 'Paste JSON', 'taka-platform' ); ?></label><br><textarea class="large-text code" rows="8" name="config_json" placeholder="{ &quot;organizers&quot;: {}, &quot;venues&quot;: {}, &quot;events&quot;: [] }"></textarea></p>
					</td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Options', 'taka-platform' ); ?></th><td>
						<p><label><input type="checkbox" name="dry_run" value="1" checked> <?php echo esc_html__( 'Dry run / preview only', 'taka-platform' ); ?></label></p>
						<p><label><?php echo esc_html__( 'Import mode', 'taka-platform' ); ?> <select name="mode"><option value="missing"><?php echo esc_html__( 'Import missing only', 'taka-platform' ); ?></option><option value="update"><?php echo esc_html__( 'Update existing', 'taka-platform' ); ?></option><option value="overwrite"><?php echo esc_html__( 'Overwrite existing', 'taka-platform' ); ?></option></select></label></p>
						<p><label><input type="checkbox" name="delete_existing" value="1"> <?php echo esc_html__( 'Delete existing plugin data before import', 'taka-platform' ); ?></label></p>
					</td></tr>
				</tbody></table>
				<?php submit_button( __( 'Run import', 'taka-platform' ) ); ?>
			</form>
			<h2><?php echo esc_html__( 'Export WordPress data', 'taka-platform' ); ?></h2>
			<p><?php echo esc_html__( 'Copy this PHP array into a backup config file, or use the JSON representation for external tools.', 'taka-platform' ); ?></p>
			<textarea class="large-text code" rows="12" readonly><?php echo esc_textarea( "<?php\nreturn " . var_export( $export, true ) . ";\n" ); ?></textarea>
			<h3><?php echo esc_html__( 'JSON', 'taka-platform' ); ?></h3>
			<textarea class="large-text code" rows="8" readonly><?php echo esc_textarea( wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></textarea>
		</div>
		<?php
	}

	/** Render translation audit and workflow tools. */
	public static function render_translations() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$audit = TAKA_Platform_I18n::instance()->audit();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'TAKA Platform Translations', 'taka-platform' ); ?></h1>
			<p><?php echo esc_html__( 'English is the canonical source. Missing static keys fall back to English and can be synced into language files.', 'taka-platform' ); ?></p>
			<p><strong><?php echo esc_html__( 'Canonical key count', 'taka-platform' ); ?>:</strong> <?php echo esc_html( (string) ( $audit['base_count'] ?? 0 ) ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
				<input type="hidden" name="action" value="taka_platform_sync_translations">
				<?php wp_nonce_field( 'taka_platform_sync_translations', self::NONCE ); ?>
				<?php submit_button( __( 'Regenerate / sync missing keys', 'taka-platform' ), 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
				<input type="hidden" name="action" value="taka_platform_export_translation_audit">
				<?php wp_nonce_field( 'taka_platform_export_translation_audit', self::NONCE ); ?>
				<?php submit_button( __( 'Export audit JSON', 'taka-platform' ), 'secondary', 'submit', false ); ?>
			</form>
			<table class="widefat striped" style="margin-top:16px;"><thead><tr><th><?php echo esc_html__( 'Language', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Keys', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Missing keys', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Extra keys', 'taka-platform' ); ?></th></tr></thead><tbody>
			<?php foreach ( $audit['languages'] as $lang => $row ) : ?>
				<tr><td><strong><?php echo esc_html( strtoupper( $lang ) ); ?></strong></td><td><?php echo esc_html( (string) $row['count'] ); ?></td><td><?php echo empty( $row['missing'] ) ? esc_html__( 'Complete', 'taka-platform' ) : '<code>' . esc_html( implode( ', ', $row['missing'] ) ) . '</code>'; ?></td><td><?php echo empty( $row['extra'] ) ? '—' : '<code>' . esc_html( implode( ', ', $row['extra'] ) ) . '</code>'; ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<h2><?php echo esc_html__( 'Dynamic content translation workflow', 'taka-platform' ); ?></h2>
			<p><?php echo esc_html__( 'Dynamic fields can store per-language arrays. The current manual translation provider fills missing values from the default language; external AI providers can hook into taka_platform_translate_text later.', 'taka-platform' ); ?></p>
		</div>
		<?php
	}

	/** Export translation audit JSON. */
	public static function handle_export_translation_audit() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) ); }
		check_admin_referer( 'taka_platform_export_translation_audit', self::NONCE );
		$audit = TAKA_Platform_I18n::instance()->audit();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="taka-platform-translation-audit.json"' );
		echo wp_json_encode( $audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/** Sync static translation JSON keys and dynamic option fallbacks. */
	public static function handle_sync_translations() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) ); }
		check_admin_referer( 'taka_platform_sync_translations', self::NONCE );
		self::sync_static_translation_files();
		self::fill_dynamic_translation_fallbacks();
		wp_safe_redirect( add_query_arg( 'translations_synced', '1', admin_url( 'admin.php?page=taka-platform-translations' ) ) );
		exit;
	}

	/** Add missing keys to language JSON files using English values as fallback. */
	private static function sync_static_translation_files() {
		$dir = TAKA_PLATFORM_PLUGIN_DIR . 'translations/';
		$base_file = $dir . 'en.json';
		$base = file_exists( $base_file ) ? json_decode( file_get_contents( $base_file ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		foreach ( TAKA_Platform_I18n::instance()->get_all_languages() as $lang ) {
			$file = $dir . $lang . '.json';
			$data = file_exists( $file ) ? json_decode( file_get_contents( $file ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$data = self::array_replace_missing_recursive( is_array( $data ) ? $data : array(), $base );
			file_put_contents( $file, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	private static function array_replace_missing_recursive( $data, $fallback ) {
		foreach ( (array) $fallback as $key => $value ) {
			if ( is_array( $value ) ) { $data[ $key ] = self::array_replace_missing_recursive( is_array( $data[ $key ] ?? null ) ? $data[ $key ] : array(), $value ); }
			elseif ( ! array_key_exists( $key, $data ) || '' === (string) $data[ $key ] ) { $data[ $key ] = $value; }
		}
		return $data;
	}

	/** Fill missing dynamic translation arrays from English/default values. */
	private static function fill_dynamic_translation_fallbacks() {
		$service = new TAKA_Platform_Manual_Translation_Service();
		$langs = TAKA_Platform_I18n::instance()->get_all_languages();
		$sections = get_option( TAKA_Platform_Data::SECTIONS_OPTION, array() );
		if ( is_array( $sections ) ) {
			foreach ( $sections as &$section ) {
				foreach ( array( 'kicker', 'title', 'subtitle', 'body', 'text', 'button_label' ) as $field ) {
					if ( isset( $section[ $field ] ) ) { $section[ $field ] = $service->translate_fields( array( $field => $section[ $field ] ), 'en', $langs )[ $field ]; }
				}
			}
			update_option( TAKA_Platform_Data::SECTIONS_OPTION, $sections, false );
		}
		$booking = get_option( TAKA_Platform_Data::BOOKING_OPTION, array() );
		if ( is_array( $booking ) ) {
			foreach ( array( 'title', 'intro', 'group_booking', 'multi_event_discount', 'booking_process', 'payment_methods', 'cancellation_policy', 'additional_notes' ) as $field ) {
				if ( isset( $booking[ $field ] ) ) { $booking[ $field ] = $service->translate_fields( array( $field => $booking[ $field ] ), 'en', $langs )[ $field ]; }
			}
			update_option( TAKA_Platform_Data::BOOKING_OPTION, $booking, false );
		}
	}

	/** Render editable hero/layout settings. */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$hero = TAKA_Platform_Data::get_hero_settings();
		$booking = TAKA_Platform_Data::get_booking_information_settings();
		$positions = array( 'left' => __( 'Left', 'taka-platform' ), 'center' => __( 'Center', 'taka-platform' ), 'right' => __( 'Right', 'taka-platform' ) );
		$verticals = array( 'top' => __( 'Top', 'taka-platform' ), 'center' => __( 'Center', 'taka-platform' ), 'bottom' => __( 'Bottom', 'taka-platform' ) );
		?>
			<div class="wrap">
				<h1><?php echo esc_html__( 'TAKA Platform Settings', 'taka-platform' ); ?></h1>
				<p><?php echo esc_html__( 'The plugin uses WordPress events as the primary source and config/tour-events.php as seed, fallback and backup format.', 'taka-platform' ); ?></p>
				<h2><?php echo esc_html__( 'Organizer dashboard', 'taka-platform' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="taka_platform_save_dashboard_settings">
					<?php wp_nonce_field( TAKA_Platform_Organizer_Dashboard::DASHBOARD_PAGE_OPTION, self::NONCE ); ?>
					<table class="form-table" role="presentation"><tbody>
						<tr><th scope="row"><?php echo esc_html__( 'Organizer dashboard page', 'taka-platform' ); ?></th><td><?php wp_dropdown_pages( array( 'name' => 'organizer_dashboard_page_id', 'selected' => absint( get_option( TAKA_Platform_Organizer_Dashboard::DASHBOARD_PAGE_OPTION, 0 ) ), 'show_option_none' => __( '— Select —', 'taka-platform' ) ) ); ?><p class="description"><?php echo esc_html__( 'Select the page containing [taka_platform_organizer_dashboard].', 'taka-platform' ); ?></p></td></tr>
					</tbody></table>
					<?php submit_button( __( 'Save dashboard settings', 'taka-platform' ) ); ?>
				</form>
				<h2><?php echo esc_html__( 'Hero section', 'taka-platform' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="taka_platform_save_hero">
				<?php wp_nonce_field( TAKA_Platform_Data::HERO_OPTION, self::NONCE ); ?>
				<table class="form-table" role="presentation"><tbody>
					<?php self::settings_text_row( 'hero[kicker]', __( 'Hero kicker', 'taka-platform' ), $hero['kicker'] ?? '' ); ?>
					<?php self::settings_text_row( 'hero[title]', __( 'Hero title', 'taka-platform' ), $hero['title'] ?? '' ); ?>
					<?php self::settings_textarea_row( 'hero[description]', __( 'Hero subtitle / description', 'taka-platform' ), $hero['description'] ?? '' ); ?>
					<?php self::settings_text_row( 'hero[primary_button_label]', __( 'Primary button label', 'taka-platform' ), $hero['primary_button_label'] ?? '' ); ?>
					<?php self::settings_text_row( 'hero[primary_button_target]', __( 'Primary button target', 'taka-platform' ), $hero['primary_button_target'] ?? '' ); ?>
					<?php self::settings_text_row( 'hero[secondary_button_label]', __( 'Secondary button label', 'taka-platform' ), $hero['secondary_button_label'] ?? '' ); ?>
					<?php self::settings_text_row( 'hero[secondary_button_target]', __( 'Secondary button target', 'taka-platform' ), $hero['secondary_button_target'] ?? '' ); ?>
					<?php self::settings_media_row( 'hero[image_id]', 'hero[image_url]', 'taka_platform_hero_image', __( 'Hero image', 'taka-platform' ), absint( $hero['image_id'] ?? 0 ), (string) ( $hero['image_url'] ?? '' ) ); ?>
					<?php self::settings_text_row( 'hero[overlay_strength]', __( 'Hero overlay strength (0–1)', 'taka-platform' ), $hero['overlay_strength'] ?? '0.78' ); ?>
					<tr><th scope="row"><?php echo esc_html__( 'Hero text box', 'taka-platform' ); ?></th><td><label><input type="checkbox" name="hero[text_box_enabled]" value="1" <?php checked( (string) ( $hero['text_box_enabled'] ?? '1' ), '1' ); ?>> <?php echo esc_html__( 'Show readable text box', 'taka-platform' ); ?></label></td></tr>
					<?php self::settings_text_row( 'hero[text_box_opacity]', __( 'Text box opacity (0–1)', 'taka-platform' ), $hero['text_box_opacity'] ?? '0.72' ); ?>
					<?php self::settings_text_row( 'hero[text_box_max_width]', __( 'Text box max width', 'taka-platform' ), $hero['text_box_max_width'] ?? '620px' ); ?>
					<?php self::settings_select_row( 'hero[text_position]', __( 'Hero text position', 'taka-platform' ), $hero['text_position'] ?? 'left', $positions ); ?>
					<?php self::settings_select_row( 'hero[vertical_alignment]', __( 'Hero vertical alignment', 'taka-platform' ), $hero['vertical_alignment'] ?? 'center', $verticals ); ?>
				</tbody></table>
				<?php submit_button( __( 'Save hero settings', 'taka-platform' ) ); ?>
			</form>
			<h2><?php echo esc_html__( 'Booking Information', 'taka-platform' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="taka_platform_save_booking_information">
				<?php wp_nonce_field( TAKA_Platform_Data::BOOKING_OPTION, self::NONCE ); ?>
				<table class="form-table" role="presentation"><tbody>
					<tr><th scope="row"><?php echo esc_html__( 'Section enabled', 'taka-platform' ); ?></th><td><label><input type="checkbox" name="booking_info[enabled]" value="1" <?php checked( (string) ( $booking['enabled'] ?? '1' ), '1' ); ?>> <?php echo esc_html__( 'Show Before you book section near tickets', 'taka-platform' ); ?></label></td></tr>
					<?php self::settings_multilingual_text_row( 'booking_info[title]', __( 'Title', 'taka-platform' ), $booking['title'] ?? '' ); ?>
					<?php self::settings_multilingual_textarea_row( 'booking_info[intro]', __( 'Intro text', 'taka-platform' ), $booking['intro'] ?? '' ); ?>
					<?php self::settings_multilingual_textarea_row( 'booking_info[group_booking]', __( 'Group booking text', 'taka-platform' ), $booking['group_booking'] ?? '' ); ?>
					<?php self::settings_multilingual_textarea_row( 'booking_info[multi_event_discount]', __( 'Multi-event discount text', 'taka-platform' ), $booking['multi_event_discount'] ?? '' ); ?>
					<?php self::settings_text_row( 'booking_info[contact_email]', __( 'Contact email', 'taka-platform' ), $booking['contact_email'] ?? '' ); ?>
					<?php self::settings_multilingual_textarea_row( 'booking_info[booking_process]', __( 'Booking process text', 'taka-platform' ), $booking['booking_process'] ?? '' ); ?>
					<?php self::settings_multilingual_textarea_row( 'booking_info[payment_methods]', __( 'Payment methods', 'taka-platform' ), $booking['payment_methods'] ?? '' ); ?>
					<?php self::settings_multilingual_textarea_row( 'booking_info[cancellation_policy]', __( 'Cancellation policy text', 'taka-platform' ), $booking['cancellation_policy'] ?? '' ); ?>
					<?php self::settings_multilingual_textarea_row( 'booking_info[additional_notes]', __( 'Additional notes', 'taka-platform' ), $booking['additional_notes'] ?? '' ); ?>
				</tbody></table>
				<?php submit_button( __( 'Save booking information', 'taka-platform' ) ); ?>
			</form>
		</div>
		<?php
	}

	/** Render editable frontend content sections. */
	public static function render_content_sections() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$sections = TAKA_Platform_Data::get_content_sections( false );
		$layouts = array(
			'text_only' => __( 'Text only', 'taka-platform' ),
			'image_left' => __( 'Image left', 'taka-platform' ),
			'image_right' => __( 'Image right', 'taka-platform' ),
			'image_above' => __( 'Image above', 'taka-platform' ),
			'full_background' => __( 'Full width image background', 'taka-platform' ),
			'two_column' => __( 'Two column', 'taka-platform' ),
			'gallery_grid' => __( 'Gallery grid', 'taka-platform' ),
			'feature_card' => __( 'Feature card', 'taka-platform' ),
		);
		$backgrounds = array( 'plain' => __( 'Plain', 'taka-platform' ), 'paper' => __( 'Paper', 'taka-platform' ), 'wash' => __( 'Washi', 'taka-platform' ), 'ink' => __( 'Ink', 'taka-platform' ) );
		$fits = array( 'contain' => __( 'Contain', 'taka-platform' ), 'cover' => __( 'Cover', 'taka-platform' ), 'auto' => __( 'Auto', 'taka-platform' ) );
		$positions = array( 'center center' => __( 'Center', 'taka-platform' ), 'center top' => __( 'Top center', 'taka-platform' ), 'center bottom' => __( 'Bottom center', 'taka-platform' ), 'left center' => __( 'Left center', 'taka-platform' ), 'right center' => __( 'Right center', 'taka-platform' ) );
		$new_key = 'new_' . time();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'TAKA Platform Content Sections', 'taka-platform' ); ?></h1>
			<p><?php echo esc_html__( 'Add, reorder and edit homepage editorial sections without changing templates. Disabled or empty sections are not rendered.', 'taka-platform' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="taka_platform_save_sections">
				<?php wp_nonce_field( TAKA_Platform_Data::SECTIONS_OPTION, self::NONCE ); ?>
				<?php foreach ( $sections as $key => $section ) : ?>
					<?php self::render_content_section_editor( $key, $section, $layouts, $backgrounds, $fits, $positions, false ); ?>
				<?php endforeach; ?>
				<h2><?php echo esc_html__( 'Add section', 'taka-platform' ); ?></h2>
				<p><?php echo esc_html__( 'Fill the fields below and save to add another homepage section. Leave the key empty to skip.', 'taka-platform' ); ?></p>
				<?php self::render_content_section_editor( $new_key, array( 'key' => '', 'visible' => '0', 'layout' => 'image_right', 'background_style' => 'paper', 'image_fit' => 'contain', 'image_position' => 'center center', 'sort_order' => 90 ), $layouts, $backgrounds, $fits, $positions, true ); ?>
				<?php submit_button( __( 'Save content sections', 'taka-platform' ) ); ?>
			</form>
		</div>
		<?php
	}

	/** Render one content-section editor block. */
	private static function render_content_section_editor( $key, $section, $layouts, $backgrounds, $fits, $positions, $is_new = false ) {
		$key = sanitize_key( $key );
		$label = $is_new ? __( 'New section', 'taka-platform' ) : ( ( $section['title'] ?? '' ) ?: $key );
		?>
		<div class="postbox" style="padding:1rem;max-width:1080px;">
			<h2><?php echo esc_html( $label ); ?></h2>
			<table class="form-table" role="presentation"><tbody>
				<?php self::settings_text_row( 'sections[' . $key . '][key]', __( 'Internal key / slug', 'taka-platform' ), $section['key'] ?? $key ); ?>
				<tr><th scope="row"><?php echo esc_html__( 'Enabled', 'taka-platform' ); ?></th><td><label><input type="checkbox" name="sections[<?php echo esc_attr( $key ); ?>][visible]" value="1" <?php checked( (string) ( $section['visible'] ?? '1' ), '1' ); ?>> <?php echo esc_html__( 'Show section', 'taka-platform' ); ?></label><?php if ( ! $is_new ) : ?><br><label><input type="checkbox" name="sections[<?php echo esc_attr( $key ); ?>][delete]" value="1"> <?php echo esc_html__( 'Delete section', 'taka-platform' ); ?></label><?php endif; ?></td></tr>
				<?php self::settings_text_row( 'sections[' . $key . '][sort_order]', __( 'Sort order', 'taka-platform' ), $section['sort_order'] ?? 0 ); ?>
				<?php self::settings_multilingual_text_row( 'sections[' . $key . '][kicker]', __( 'Kicker', 'taka-platform' ), $section['kicker'] ?? '' ); ?>
				<?php self::settings_multilingual_text_row( 'sections[' . $key . '][title]', __( 'Title', 'taka-platform' ), $section['title'] ?? '' ); ?>
				<?php self::settings_multilingual_text_row( 'sections[' . $key . '][subtitle]', __( 'Subtitle', 'taka-platform' ), $section['subtitle'] ?? '' ); ?>
				<?php self::settings_multilingual_textarea_row( 'sections[' . $key . '][body]', __( 'Body', 'taka-platform' ), $section['body'] ?? ( $section['text'] ?? '' ) ); ?>
				<?php self::settings_media_row( 'sections[' . $key . '][image_id]', 'sections[' . $key . '][image_url]', 'taka_section_' . $key . '_image', __( 'Main image', 'taka-platform' ), absint( $section['image_id'] ?? 0 ), (string) ( $section['image_url'] ?? '' ) ); ?>
				<?php self::settings_media_row( 'sections[' . $key . '][secondary_image_id]', 'sections[' . $key . '][secondary_image_url]', 'taka_section_' . $key . '_secondary_image', __( 'Secondary image', 'taka-platform' ), absint( $section['secondary_image_id'] ?? 0 ), (string) ( $section['secondary_image_url'] ?? '' ) ); ?>
				<?php self::settings_media_row( 'sections[' . $key . '][gallery_image_ids]', 'sections[' . $key . '][gallery_image_urls]', 'taka_section_' . $key . '_gallery', __( 'Gallery', 'taka-platform' ), $section['gallery_image_ids'] ?? array(), implode( "\n", (array) ( $section['gallery_image_urls'] ?? array() ) ), true ); ?>
				<?php self::settings_select_row( 'sections[' . $key . '][layout]', __( 'Layout', 'taka-platform' ), $section['layout'] ?? 'text_only', $layouts ); ?>
				<?php self::settings_select_row( 'sections[' . $key . '][background_style]', __( 'Background style', 'taka-platform' ), $section['background_style'] ?? 'plain', $backgrounds ); ?>
				<?php self::settings_select_row( 'sections[' . $key . '][image_fit]', __( 'Image fit', 'taka-platform' ), $section['image_fit'] ?? 'contain', $fits ); ?>
				<?php self::settings_select_row( 'sections[' . $key . '][image_position]', __( 'Image focus / position', 'taka-platform' ), $section['image_position'] ?? 'center center', $positions ); ?>
				<?php self::settings_multilingual_text_row( 'sections[' . $key . '][button_label]', __( 'Button label', 'taka-platform' ), $section['button_label'] ?? ( $section['link_label'] ?? '' ) ); ?>
				<?php self::settings_text_row( 'sections[' . $key . '][button_url]', __( 'Button URL', 'taka-platform' ), $section['button_url'] ?? ( $section['link_url'] ?? '' ) ); ?>
				<?php self::settings_text_row( 'sections[' . $key . '][css_class]', __( 'CSS modifier/class', 'taka-platform' ), $section['css_class'] ?? '' ); ?>
			</tbody></table>
		</div>
		<?php
	}

	/** Save editable hero settings. */
	public static function handle_save_hero() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) ); }
		check_admin_referer( TAKA_Platform_Data::HERO_OPTION, self::NONCE );
		$posted = isset( $_POST['hero'] ) && is_array( $_POST['hero'] ) ? wp_unslash( $_POST['hero'] ) : array();
		$clean  = array(
			'kicker'                 => sanitize_text_field( $posted['kicker'] ?? '' ),
			'title'                  => sanitize_text_field( $posted['title'] ?? '' ),
			'description'            => sanitize_textarea_field( $posted['description'] ?? '' ),
			'primary_button_label'   => sanitize_text_field( $posted['primary_button_label'] ?? '' ),
			'primary_button_target'  => sanitize_text_field( $posted['primary_button_target'] ?? '' ),
			'secondary_button_label' => sanitize_text_field( $posted['secondary_button_label'] ?? '' ),
			'secondary_button_target'=> sanitize_text_field( $posted['secondary_button_target'] ?? '' ),
			'image_id'               => absint( $posted['image_id'] ?? 0 ),
			'image_url'              => esc_url_raw( $posted['image_url'] ?? '' ),
			'overlay_strength'       => self::sanitize_decimal( $posted['overlay_strength'] ?? '0.78', '0.78' ),
			'text_box_enabled'       => ! empty( $posted['text_box_enabled'] ) ? '1' : '0',
			'text_box_opacity'       => self::sanitize_decimal( $posted['text_box_opacity'] ?? '0.72', '0.72' ),
			'text_box_max_width'     => sanitize_text_field( $posted['text_box_max_width'] ?? '620px' ),
			'text_position'          => in_array( $posted['text_position'] ?? 'left', array( 'left', 'center', 'right' ), true ) ? sanitize_key( $posted['text_position'] ) : 'left',
			'vertical_alignment'     => in_array( $posted['vertical_alignment'] ?? 'center', array( 'top', 'center', 'bottom' ), true ) ? sanitize_key( $posted['vertical_alignment'] ) : 'center',
		);
		update_option( TAKA_Platform_Data::HERO_OPTION, $clean, false );
		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=taka-tour-settings' ) ) );
		exit;
	}

	/** Save editable content sections. */
	public static function handle_save_sections() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) ); }
		check_admin_referer( TAKA_Platform_Data::SECTIONS_OPTION, self::NONCE );
		$posted = isset( $_POST['sections'] ) && is_array( $_POST['sections'] ) ? wp_unslash( $_POST['sections'] ) : array();
		$clean  = array();
		foreach ( $posted as $fallback_key => $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$key = sanitize_key( $item['key'] ?? $fallback_key );
			if ( '' === $key ) { continue; }
			if ( ! empty( $item['delete'] ) ) { $clean[ $key ] = array( 'key' => $key, 'delete' => '1' ); continue; }
			$section = self::sanitize_content_section( $item );
			$section['key'] = $key;
			$clean[ $key ] = $section;
		}
		update_option( TAKA_Platform_Data::SECTIONS_OPTION, $clean, false );
		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=taka-platform-content-sections' ) ) );
		exit;
	}

	/** Sanitize one content-section admin payload. */
	private static function sanitize_content_section( $item ) {
		$allowed_layouts = array( 'text_only', 'image_left', 'image_right', 'image_above', 'full_background', 'two_column', 'gallery_grid', 'feature_card' );
		$layout = sanitize_key( $item['layout'] ?? 'text_only' );
		if ( ! in_array( $layout, $allowed_layouts, true ) ) { $layout = 'text_only'; }
		$allowed_backgrounds = array( 'plain', 'paper', 'wash', 'ink' );
		$background = sanitize_key( $item['background_style'] ?? 'plain' );
		if ( ! in_array( $background, $allowed_backgrounds, true ) ) { $background = 'plain'; }
		$image_fit = sanitize_key( $item['image_fit'] ?? 'contain' );
		if ( ! in_array( $image_fit, array( 'cover', 'contain', 'auto' ), true ) ) { $image_fit = 'contain'; }
		$image_position = strtolower( trim( (string) ( $item['image_position'] ?? 'center center' ) ) );
		if ( ! in_array( $image_position, array( 'center center', 'center top', 'center bottom', 'left center', 'right center' ), true ) ) { $image_position = 'center center'; }
		return array(
			'visible'             => ! empty( $item['visible'] ) ? '1' : '0',
			'sort_order'          => (int) ( $item['sort_order'] ?? 0 ),
			'kicker'              => self::sanitize_dynamic_text( $item['kicker'] ?? '', false ),
			'title'               => self::sanitize_dynamic_text( $item['title'] ?? '', false ),
			'subtitle'            => self::sanitize_dynamic_text( $item['subtitle'] ?? '', false ),
			'body'                => self::sanitize_dynamic_text( $item['body'] ?? ( $item['text'] ?? '' ), true ),
			'text'                => self::sanitize_dynamic_text( $item['body'] ?? ( $item['text'] ?? '' ), true ),
			'image_id'            => absint( $item['image_id'] ?? 0 ),
			'image_url'           => esc_url_raw( $item['image_url'] ?? '' ),
			'secondary_image_id'  => absint( $item['secondary_image_id'] ?? 0 ),
			'secondary_image_url' => esc_url_raw( $item['secondary_image_url'] ?? '' ),
			'gallery_image_ids'   => implode( ',', self::csv_to_absints( $item['gallery_image_ids'] ?? '' ) ),
			'gallery_image_urls'  => implode( "\n", array_map( 'esc_url_raw', self::lines_to_array( $item['gallery_image_urls'] ?? '' ) ) ),
			'layout'              => $layout,
			'background_style'    => $background,
			'image_fit'           => $image_fit,
			'image_position'      => $image_position,
			'button_url'          => esc_url_raw( $item['button_url'] ?? ( $item['link_url'] ?? '' ) ),
			'button_label'        => self::sanitize_dynamic_text( $item['button_label'] ?? ( $item['link_label'] ?? '' ), false ),
			'css_class'           => sanitize_html_class( $item['css_class'] ?? '' ),
		);
	}


	/** Save global booking-information settings. */
	public static function handle_save_booking_information() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) ); }
		check_admin_referer( TAKA_Platform_Data::BOOKING_OPTION, self::NONCE );
		$posted = isset( $_POST['booking_info'] ) && is_array( $_POST['booking_info'] ) ? wp_unslash( $_POST['booking_info'] ) : array();
		$clean  = self::sanitize_booking_information( $posted, true );
		update_option( TAKA_Platform_Data::BOOKING_OPTION, $clean, false );
		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=taka-tour-settings' ) ) );
		exit;
	}

	/** Save organizer dashboard settings. */
	public static function handle_save_dashboard_settings() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) ); }
		check_admin_referer( TAKA_Platform_Organizer_Dashboard::DASHBOARD_PAGE_OPTION, self::NONCE );
		update_option( TAKA_Platform_Organizer_Dashboard::DASHBOARD_PAGE_OPTION, absint( $_POST['organizer_dashboard_page_id'] ?? 0 ), false );
		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=taka-tour-settings' ) ) );
		exit;
	}

	/** Process config import. */
	public static function handle_import_config() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) ); }
		check_admin_referer( 'taka_tour_import_config', self::IMPORT_NONCE );
		$dry_run = ! empty( $_POST['dry_run'] );
		$mode = sanitize_key( wp_unslash( $_POST['mode'] ?? 'missing' ) );
		if ( ! in_array( $mode, array( 'missing', 'update', 'overwrite' ), true ) ) { $mode = 'missing'; }
		$delete_existing = ! empty( $_POST['delete_existing'] );
		$source = sanitize_key( wp_unslash( $_POST['source'] ?? 'bundled' ) );
		$loaded = self::load_import_source( $source );
		if ( is_wp_error( $loaded ) ) {
			set_transient( 'taka_tour_import_result', array( 'message' => $loaded->get_error_message(), 'summary' => array() ), 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=taka-tour-import-export' ) );
			exit;
		}
		$summary = self::import_config( $mode, $dry_run, $delete_existing, $loaded );
		set_transient( 'taka_tour_import_result', array( 'message' => $dry_run ? __( 'Dry run completed.', 'taka-platform' ) : __( 'Import completed.', 'taka-platform' ), 'summary' => $summary ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=taka-tour-import-export' ) );
		exit;
	}

	/** Load a selected import source and validate the event-tour structure. */
	private static function load_import_source( $source ) {
		if ( 'bundled' === $source ) {
			return self::validate_import_config( TAKA_Platform_Data::load_config() );
		}

		if ( 'json' === $source ) {
			$json = isset( $_POST['config_json'] ) ? wp_unslash( $_POST['config_json'] ) : '';
			$data = json_decode( (string) $json, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				return new WP_Error( 'taka_tour_invalid_json', __( 'Invalid JSON import data.', 'taka-platform' ) );
			}
			return self::validate_import_config( $data );
		}

		if ( 'upload' === $source ) {
			if ( empty( $_FILES['config_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['config_file']['tmp_name'] ) ) {
				return new WP_Error( 'taka_tour_missing_upload', __( 'No PHP config file uploaded.', 'taka-platform' ) );
			}
			$name = sanitize_file_name( $_FILES['config_file']['name'] ?? '' );
			if ( 'php' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
				return new WP_Error( 'taka_tour_invalid_upload', __( 'Uploaded config must be a PHP file.', 'taka-platform' ) );
			}
			if ( function_exists( 'exec' ) && defined( 'PHP_BINARY' ) ) {
				$output = array();
				$status = 0;
				@exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $_FILES['config_file']['tmp_name'] ), $output, $status );
				if ( 0 !== (int) $status ) {
					return new WP_Error( 'taka_tour_invalid_php_config', __( 'Uploaded PHP config failed syntax validation.', 'taka-platform' ) );
				}
			}
			ob_start();
			$data = ( static function ( $file ) { return require $file; } )( $_FILES['config_file']['tmp_name'] );
			ob_end_clean();
			return self::validate_import_config( $data );
		}

		return new WP_Error( 'taka_tour_unknown_source', __( 'Unknown import source.', 'taka-platform' ) );
	}

	/** Validate import structure. */
	private static function validate_import_config( $data ) {
		if ( ! is_array( $data ) || ! isset( $data['organizers'], $data['venues'], $data['events'] ) || ! is_array( $data['organizers'] ) || ! is_array( $data['venues'] ) || ! is_array( $data['events'] ) ) {
			return new WP_Error( 'taka_tour_invalid_config', __( 'Import data must contain organizers, venues and events arrays.', 'taka-platform' ) );
		}
		return $data;
	}

	/** Import config data idempotently. */
	private static function import_config( $mode, $dry_run, $delete_existing, $config = null ) {
		$config = is_array( $config ) ? $config : TAKA_Platform_Data::load_config();
		$summary = array( 'organizers' => array( 'created' => 0, 'updated' => 0, 'skipped' => 0 ), 'venues' => array( 'created' => 0, 'updated' => 0, 'skipped' => 0 ), 'events' => array( 'created' => 0, 'updated' => 0, 'skipped' => 0 ), 'warnings' => array() );
		if ( $delete_existing && ! $dry_run ) { self::delete_plugin_posts(); }
		foreach ( $config['organizers'] ?? array() as $id => $item ) { self::upsert_config_post( TAKA_PLATFORM_CPT_ORGANIZER, $id, $item['name'] ?? $id, '', self::organizer_meta_from_config( $item ), $mode, $dry_run, $summary['organizers'] ); }
		foreach ( $config['venues'] ?? array() as $id => $item ) { self::upsert_config_post( TAKA_PLATFORM_CPT_VENUE, $id, $item['name'] ?? $id, $item['notes'] ?? '', self::venue_meta_from_config( $item ), $mode, $dry_run, $summary['venues'] ); }
		foreach ( $config['events'] ?? array() as $item ) {
			$id = $item['id'] ?? ( $item['slug'] ?? '' );
			$meta = self::event_meta_from_config( $item );
			$meta['_taka_organizer_id'] = self::find_post_id_by_config_id( TAKA_PLATFORM_CPT_ORGANIZER, $item['organizer'] ?? '' );
			$meta['_taka_venue_id'] = self::find_post_id_by_config_id( TAKA_PLATFORM_CPT_VENUE, $item['venue'] ?? '' );
			self::upsert_config_post( TAKA_PLATFORM_CPT_EVENT, $id, $item['title'] ?? $id, $item['description'] ?? '', $meta, $mode, $dry_run, $summary['events'], $item['slug'] ?? '' );
		}
		if ( ! empty( $config['content_sections'] ) && is_array( $config['content_sections'] ) && ! $dry_run ) {
			$existing_sections = get_option( TAKA_Platform_Data::SECTIONS_OPTION, array() );
			$existing_sections = is_array( $existing_sections ) ? $existing_sections : array();
			foreach ( $config['content_sections'] as $section ) {
				if ( ! is_array( $section ) ) { continue; }
				$key = sanitize_key( $section['key'] ?? '' );
				if ( '' === $key ) { continue; }
				if ( 'missing' === $mode && isset( $existing_sections[ $key ] ) ) { continue; }
				$existing_sections[ $key ] = self::sanitize_content_section( $section );
				$existing_sections[ $key ]['key'] = $key;
			}
			update_option( TAKA_Platform_Data::SECTIONS_OPTION, $existing_sections, false );
		}
		return $summary;
	}

	/** Upsert one config-backed post. */
	private static function upsert_config_post( $post_type, $config_id, $title, $content, $meta, $mode, $dry_run, &$summary, $slug = '' ) {
		if ( '' === (string) $config_id ) { $summary['skipped']++; return 0; }
		$existing = self::find_post_id_by_config_id( $post_type, $config_id );
		if ( $existing && 'missing' === $mode ) { $summary['skipped']++; return $existing; }
		if ( $dry_run ) { $summary[ $existing ? 'updated' : 'created' ]++; return $existing; }
		$post_data = array( 'post_type' => $post_type, 'post_title' => sanitize_text_field( $title ), 'post_content' => wp_kses_post( $content ), 'post_status' => 'publish' );
		if ( '' !== $slug ) { $post_data['post_name'] = sanitize_title( $slug ); }
		if ( $existing ) { $post_data['ID'] = $existing; $post_id = wp_update_post( $post_data, true ); $summary['updated']++; } else { $post_id = wp_insert_post( $post_data, true ); $summary['created']++; }
		if ( is_wp_error( $post_id ) ) { $summary['skipped']++; return 0; }
		update_post_meta( $post_id, '_taka_config_id', sanitize_text_field( $config_id ) );
		foreach ( $meta as $key => $value ) {
			$is_media_id = in_array( $key, array( '_taka_logo_id', '_taka_image_id', '_taka_group_image_id', '_taka_parking_image_id', '_taka_gallery_image_ids' ), true );
			if ( $existing && 'overwrite' !== $mode && $is_media_id && '' !== (string) get_post_meta( $post_id, $key, true ) ) { continue; }
			update_post_meta( $post_id, $key, $value );
		}
		return (int) $post_id;
	}

	private static function organizer_meta_from_config( $item ) { $social = is_array( $item['social'] ?? null ) ? $item['social'] : ( is_array( $item['social_links'] ?? null ) ? $item['social_links'] : array() ); return array( '_taka_legal_name' => $item['legal_name'] ?? '', '_taka_website' => $item['website'] ?? '', '_taka_logo_id' => (int) ( $item['logo_id'] ?? 0 ), '_taka_logo_url' => $item['logo_url'] ?? ( $item['logo'] ?? '' ), '_taka_emails' => implode( "\n", $item['emails'] ?? array() ), '_taka_contact_persons' => self::contact_persons_to_lines( $item['contact_persons'] ?? array() ), '_taka_instagram' => $social['instagram'] ?? '', '_taka_facebook' => $social['facebook'] ?? '', '_taka_youtube' => $social['youtube'] ?? '', '_taka_platform_co_organizers' => self::sanitize_co_organizers( $item['co_organizers'] ?? array() ), '_taka_active' => 1 ); }
	private static function venue_meta_from_config( $item ) { $a = $item['address'] ?? array(); $g = $item['geo'] ?? array(); return array( '_taka_image_id' => (int) ( $item['image_id'] ?? 0 ), '_taka_image_url' => $item['image_url'] ?? ( $item['image'] ?? '' ), '_taka_parking_image_id' => (int) ( $item['parking_image_id'] ?? 0 ), '_taka_parking_image_url' => $item['parking_image_url'] ?? '', '_taka_gallery_image_ids' => implode( ',', $item['gallery_image_ids'] ?? array() ), '_taka_street' => $a['street'] ?? '', '_taka_postal_code' => $a['postal_code'] ?? '', '_taka_city' => $a['city'] ?? '', '_taka_country' => $a['country'] ?? '', '_taka_country_code' => $a['country_code'] ?? '', '_taka_timezone' => $item['timezone'] ?? '', '_taka_website' => $item['website'] ?? '', '_taka_parking' => $item['parking'] ?? '', '_taka_accessibility' => $item['accessibility'] ?? '', '_taka_notes' => $item['notes'] ?? '', '_taka_lat' => $g['lat'] ?? '', '_taka_lng' => $g['lng'] ?? '' ); }
	private static function event_meta_from_config( $item ) { $raw_booking = is_array( $item['booking_information'] ?? null ) ? $item['booking_information'] : array(); if ( ! empty( $raw_booking ) && ! isset( $raw_booking['override'] ) ) { $raw_booking['override'] = '1'; } if ( ! empty( $raw_booking ) && ! isset( $raw_booking['enabled'] ) ) { $raw_booking['enabled'] = '1'; } $booking = self::sanitize_booking_information( $raw_booking ); return array( '_taka_subtitle' => $item['subtitle'] ?? '', '_taka_country' => $item['country'] ?? '', '_taka_country_code' => $item['country_code'] ?? '', '_taka_flag' => $item['flag'] ?? '', '_taka_city' => $item['city'] ?? '', '_taka_date_start' => $item['date_start'] ?? '', '_taka_date_end' => $item['date_end'] ?? '', '_taka_time_start' => $item['time_start'] ?? '', '_taka_time_end' => $item['time_end'] ?? '', '_taka_doors_open' => $item['doors_open'] ?? '', '_taka_timezone' => $item['timezone'] ?? '', '_taka_format' => $item['format'] ?? '', '_taka_audience' => $item['audience'] ?? '', '_taka_level' => $item['level'] ?? '', '_taka_ticket_status' => $item['ticket_status'] ?? '', '_taka_ticket_provider' => $item['ticket_provider'] ?? '', '_taka_ticket_shop_url' => $item['ticket_shop_url'] ?? '', '_taka_image_id' => (int) ( $item['image_id'] ?? 0 ), '_taka_image_url' => $item['image_url'] ?? ( $item['image'] ?? '' ), '_taka_group_image_id' => (int) ( $item['group_image_id'] ?? 0 ), '_taka_group_image_url' => $item['group_image_url'] ?? ( $item['group_image'] ?? '' ), '_taka_gallery_image_ids' => implode( ',', $item['gallery_image_ids'] ?? array() ), '_taka_photo_credit' => $item['photo_credit'] ?? '', '_taka_languages' => implode( ',', $item['languages'] ?? array() ), '_taka_booking_info_override' => $booking['override'] ?? '', '_taka_booking_info_enabled' => $booking['enabled'] ?? '1', '_taka_booking_info_title' => $booking['title'] ?? '', '_taka_booking_info_intro' => $booking['intro'] ?? '', '_taka_booking_info_group_booking' => $booking['group_booking'] ?? '', '_taka_booking_info_multi_event_discount' => $booking['multi_event_discount'] ?? '', '_taka_booking_info_contact_email' => $booking['contact_email'] ?? '', '_taka_booking_info_booking_process' => $booking['booking_process'] ?? '', '_taka_booking_info_payment_methods' => $booking['payment_methods'] ?? '', '_taka_booking_info_cancellation_policy' => $booking['cancellation_policy'] ?? '', '_taka_booking_info_additional_notes' => $booking['additional_notes'] ?? '', '_taka_notes' => $item['notes'] ?? '', '_taka_parking' => $item['parking'] ?? '', '_taka_sort_order' => (int) ( $item['sort_order'] ?? 0 ) ); }

	private static function contact_persons_to_lines( $people ) { return implode( "\n", array_map( static function ( $person ) { return is_array( $person ) ? trim( ( $person['name'] ?? '' ) . ' | ' . ( $person['email'] ?? '' ) . ' | ' . ( $person['role'] ?? '' ) ) : (string) $person; }, $people ) ); }
	private static function find_post_id_by_config_id( $post_type, $config_id ) { if ( '' === (string) $config_id ) { return 0; } $posts = get_posts( array( 'post_type' => $post_type, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_taka_config_id', 'meta_value' => $config_id ) ); return ! empty( $posts ) ? (int) $posts[0] : 0; }
	private static function delete_plugin_posts() { foreach ( array( TAKA_PLATFORM_CPT_EVENT, TAKA_PLATFORM_CPT_ORGANIZER, TAKA_PLATFORM_CPT_VENUE ) as $type ) { $ids = get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ); foreach ( $ids as $id ) { wp_delete_post( $id, true ); } } }

	/** Organizer meta. */
	public static function render_organizer_meta_box( $post ) {
		self::nonce();
		self::text( $post->ID, 'legal_name', __( 'Legal name', 'taka-platform' ) );
		self::url( $post->ID, 'website', __( 'Website', 'taka-platform' ) );
		self::media_field( $post->ID, 'logo_id', __( 'Logo', 'taka-platform' ), false, __( 'Select logo', 'taka-platform' ) );
		self::url( $post->ID, 'logo_url', __( 'Fallback logo URL', 'taka-platform' ) );
		self::textarea( $post->ID, 'emails', __( 'Email addresses (one per line)', 'taka-platform' ) );
		self::textarea( $post->ID, 'contact_persons', __( 'Contact persons (one per line)', 'taka-platform' ) );
		self::text( $post->ID, 'instagram', __( 'Instagram', 'taka-platform' ) );
		self::text( $post->ID, 'facebook', __( 'Facebook', 'taka-platform' ) );
		self::text( $post->ID, 'youtube', __( 'YouTube', 'taka-platform' ) );
		self::checkbox( $post->ID, 'active', __( 'Active', 'taka-platform' ) );
		self::render_co_organizers( $post->ID );
	}

	/** Venue meta. */
	public static function render_venue_meta_box( $post ) {
		self::nonce();
		foreach ( array( 'street' => 'Street', 'postal_code' => 'Postal code', 'city' => 'City', 'country' => 'Country', 'country_code' => 'Country code', 'timezone' => 'Timezone', 'lat' => 'Geo lat', 'lng' => 'Geo lng' ) as $key => $label ) { self::text( $post->ID, $key, __( $label, 'taka-platform' ) ); }
		self::url( $post->ID, 'website', __( 'Website', 'taka-platform' ) );
		self::media_field( $post->ID, 'image_id', __( 'Venue photo', 'taka-platform' ), false, __( 'Select venue photo', 'taka-platform' ) );
		self::url( $post->ID, 'image_url', __( 'Fallback venue photo URL', 'taka-platform' ) );
		self::media_field( $post->ID, 'parking_image_id', __( 'Parking/arrival photo', 'taka-platform' ), false, __( 'Select parking photo', 'taka-platform' ) );
		self::url( $post->ID, 'parking_image_url', __( 'Fallback parking photo URL', 'taka-platform' ) );
		self::media_field( $post->ID, 'gallery_image_ids', __( 'Gallery images', 'taka-platform' ), true, __( 'Select gallery images', 'taka-platform' ) );
		self::textarea( $post->ID, 'parking', __( 'Parking notes', 'taka-platform' ) );
		self::textarea( $post->ID, 'accessibility', __( 'Accessibility', 'taka-platform' ) );
		self::textarea( $post->ID, 'notes', __( 'Special notes', 'taka-platform' ) );
	}

	/** Event meta. */
	public static function render_event_meta_box( $post ) {
		self::nonce();
		foreach ( array( 'subtitle' => 'Subtitle', 'country' => 'Country', 'country_code' => 'Country code', 'flag' => 'Flag', 'city' => 'City', 'date_start' => 'Start date', 'date_end' => 'End date', 'time_start' => 'Start time', 'time_end' => 'End time', 'doors_open' => 'Doors open', 'timezone' => 'Timezone', 'format' => 'Format', 'audience' => 'Audience', 'level' => 'Level', 'ticket_provider' => 'Ticket provider', 'ticket_status' => 'Ticket status', 'photo_credit' => 'Photo credit', 'languages' => 'Languages, comma-separated' ) as $key => $label ) { self::text( $post->ID, $key, __( $label, 'taka-platform' ) ); }
		self::organizer_relation( $post->ID, 'organizer_id', __( 'Organizer', 'taka-platform' ) );
		self::relation( $post->ID, 'venue_id', __( 'Primary venue', 'taka-platform' ), TAKA_PLATFORM_CPT_VENUE );
		self::text( $post->ID, 'venue_ids', __( 'Additional venue IDs, comma-separated', 'taka-platform' ) );
		self::url( $post->ID, 'ticket_shop_url', __( 'Ticket shop URL', 'taka-platform' ) );
		self::media_field( $post->ID, 'image_id', __( 'Event action photo', 'taka-platform' ), false, __( 'Select action photo', 'taka-platform' ) );
		self::url( $post->ID, 'image_url', __( 'Fallback action photo URL', 'taka-platform' ) );
		self::media_field( $post->ID, 'group_image_id', __( 'Past group photo', 'taka-platform' ), false, __( 'Select group photo', 'taka-platform' ) );
		self::url( $post->ID, 'group_image_url', __( 'Fallback group photo URL', 'taka-platform' ) );
		self::media_field( $post->ID, 'gallery_image_ids', __( 'Gallery images', 'taka-platform' ), true, __( 'Select gallery images', 'taka-platform' ) );
		self::textarea( $post->ID, 'short_description', __( 'Short description', 'taka-platform' ) );
		self::textarea( $post->ID, 'long_description', __( 'Long description', 'taka-platform' ) );
		self::textarea( $post->ID, 'ticket_card_text', __( 'Ticket card text', 'taka-platform' ) );
		self::render_event_booking_information_fields( $post->ID );
		self::textarea( $post->ID, 'accessibility', __( 'Accessibility notes', 'taka-platform' ) );
		self::number( $post->ID, 'sort_order', __( 'Sort order', 'taka-platform' ) );
		self::textarea( $post->ID, 'notes', __( 'Notes', 'taka-platform' ) );
		self::textarea( $post->ID, 'parking', __( 'Parking notes', 'taka-platform' ) );
	}

	public static function save_organizer( $post_id ) {
		self::save( $post_id, array( 'legal_name', 'website', 'logo_id', 'logo_url', 'emails', 'contact_persons', 'instagram', 'facebook', 'youtube', 'active' ) );
		self::save_co_organizers( $post_id );
	}
	public static function save_venue( $post_id ) { self::save( $post_id, array( 'street', 'postal_code', 'city', 'country', 'country_code', 'timezone', 'lat', 'lng', 'website', 'image_id', 'image_url', 'parking_image_id', 'parking_image_url', 'gallery_image_ids', 'parking', 'accessibility', 'notes' ) ); }
	public static function save_event( $post_id ) {
		if ( ! self::current_user_is_platform_admin() ) {
			$assigned = self::get_current_user_organizer_ids();
			$existing = absint( get_post_meta( $post_id, '_taka_organizer_id', true ) );
			$posted   = isset( $_POST['_taka_organizer_id'] ) ? absint( wp_unslash( $_POST['_taka_organizer_id'] ) ) : 0;
			if ( 0 === $posted && 1 === count( $assigned ) ) {
				$posted = (int) $assigned[0];
				$_POST['_taka_organizer_id'] = (string) $posted;
			}
			if ( empty( $assigned ) || ( $existing && ! in_array( $existing, $assigned, true ) ) || ! in_array( $posted, $assigned, true ) ) {
				return;
			}
			update_post_meta( $post_id, '_taka_organizer_id', $posted );
		}
		self::save( $post_id, array( 'subtitle', 'country', 'country_code', 'flag', 'city', 'date_start', 'date_end', 'time_start', 'time_end', 'doors_open', 'timezone', 'format', 'audience', 'level', 'ticket_provider', 'ticket_status', 'photo_credit', 'languages', 'organizer_id', 'venue_id', 'venue_ids', 'ticket_shop_url', 'image_id', 'image_url', 'group_image_id', 'group_image_url', 'gallery_image_ids', 'short_description', 'long_description', 'ticket_card_text', 'booking_info_override', 'booking_info_enabled', 'booking_info_title', 'booking_info_intro', 'booking_info_group_booking', 'booking_info_multi_event_discount', 'booking_info_contact_email', 'booking_info_booking_process', 'booking_info_payment_methods', 'booking_info_cancellation_policy', 'booking_info_additional_notes', 'accessibility', 'sort_order', 'notes', 'parking' ) );
	}

	/** Save repeatable co-organizer entries for an organizer. */
	private static function save_co_organizers( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		$posted = isset( $_POST['taka_platform_co_organizers'] ) && is_array( $_POST['taka_platform_co_organizers'] ) ? wp_unslash( $_POST['taka_platform_co_organizers'] ) : array();
		update_post_meta( $post_id, '_taka_platform_co_organizers', self::sanitize_co_organizers( $posted ) );
	}

	/** Sanitize co-organizer entries from admin forms or imported config. */
	private static function sanitize_co_organizers( $items ) {
		if ( ! is_array( $items ) ) {
			return array();
		}

		$clean = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$name = sanitize_text_field( $item['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$social = is_array( $item['social_links'] ?? null ) ? $item['social_links'] : array();
			$clean[] = array(
				'name' => $name,
				'legal_name' => sanitize_text_field( $item['legal_name'] ?? '' ),
				'website' => esc_url_raw( $item['website'] ?? '' ),
				'logo_id' => absint( $item['logo_id'] ?? 0 ),
				'logo_url' => esc_url_raw( $item['logo_url'] ?? ( $item['logo'] ?? '' ) ),
				'email' => sanitize_email( $item['email'] ?? '' ),
				'description' => sanitize_textarea_field( $item['description'] ?? '' ),
				'social_links' => array(
					'instagram' => esc_url_raw( $social['instagram'] ?? '' ),
					'facebook' => esc_url_raw( $social['facebook'] ?? '' ),
					'youtube' => esc_url_raw( $social['youtube'] ?? '' ),
				),
				'sort_order' => (int) ( $item['sort_order'] ?? 0 ),
				'active' => array_key_exists( 'active', $item ) ? ( ! empty( $item['active'] ) ? 1 : 0 ) : 1,
			);
		}

		usort( $clean, static function ( $a, $b ) { return ( (int) ( $a['sort_order'] ?? 0 ) <=> (int) ( $b['sort_order'] ?? 0 ) ) ?: strcmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) ); } );
		return $clean;
	}

	/** Save fields. */
	private static function save( $post_id, $fields ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		foreach ( $fields as $field ) {
			$key = '_taka_' . $field;
			if ( ! isset( $_POST[ $key ] ) ) { delete_post_meta( $post_id, $key ); continue; }
			$value = wp_unslash( $_POST[ $key ] );
			if ( in_array( $field, array( 'logo_id', 'image_id', 'group_image_id', 'parking_image_id', 'organizer_id', 'venue_id', 'sort_order' ), true ) ) { $value = absint( $value ); }
			elseif ( in_array( $field, array( 'website', 'ticket_shop_url', 'image_url', 'group_image_url', 'parking_image_url', 'logo_url' ), true ) ) { $value = esc_url_raw( $value ); }
			elseif ( 'booking_info_contact_email' === $field ) { $value = sanitize_email( $value ); }
			elseif ( in_array( $field, array( 'emails', 'contact_persons', 'short_description', 'long_description', 'ticket_card_text', 'booking_info_intro', 'booking_info_group_booking', 'booking_info_multi_event_discount', 'booking_info_booking_process', 'booking_info_payment_methods', 'booking_info_cancellation_policy', 'booking_info_additional_notes', 'parking', 'accessibility', 'notes' ), true ) ) { $value = sanitize_textarea_field( $value ); }
			elseif ( in_array( $field, array( 'gallery_image_ids', 'venue_ids' ), true ) ) { $value = implode( ',', array_map( 'absint', preg_split( '/\s*,\s*/', (string) $value ) ) ); }
			else { $value = sanitize_text_field( $value ); }
			update_post_meta( $post_id, $key, $value );
		}
	}

	/** Render repeatable co-organizer UI on organizer edit screens. */
	private static function render_co_organizers( $post_id ) {
		$items = get_post_meta( $post_id, '_taka_platform_co_organizers', true );
		$items = self::sanitize_co_organizers( is_array( $items ) ? $items : array() );
		?>
		<div class="taka-co-organizers" data-taka-co-organizers>
			<h3><?php echo esc_html__( 'Co-organizers', 'taka-platform' ); ?></h3>
			<p class="description"><?php echo esc_html__( 'Add partner organizers that should appear below the main organizer in frontend organizer information.', 'taka-platform' ); ?></p>
			<div data-taka-co-organizer-list>
				<?php foreach ( $items as $index => $item ) : ?>
					<?php self::render_co_organizer_row( (int) $index, $item ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button" data-taka-co-organizer-add><?php echo esc_html__( 'Add co-organizer', 'taka-platform' ); ?></button>
			<template data-taka-co-organizer-template>
				<?php self::render_co_organizer_row( '__index__', array( 'active' => 1 ) ); ?>
			</template>
		</div>
		<?php
	}

	/** Render one co-organizer row. */
	private static function render_co_organizer_row( $index, $item ) {
		$index_attr = (string) $index;
		$prefix = 'taka_platform_co_organizers[' . $index_attr . ']';
		$logo_id = absint( $item['logo_id'] ?? 0 );
		$logo_url = (string) ( $item['logo_url'] ?? '' );
		$input_id = 'taka_co_organizer_logo_' . sanitize_html_class( $index_attr );
		$preview_id = $input_id . '_preview';
		$social = is_array( $item['social_links'] ?? null ) ? $item['social_links'] : array();
		?>
		<div class="taka-co-organizer-item" data-taka-co-organizer-item style="border:1px solid #dcdcde;padding:12px;margin:12px 0;background:#fff;">
			<p><strong><?php echo esc_html__( 'Co-organizer', 'taka-platform' ); ?></strong> <button type="button" class="button-link-delete" data-taka-co-organizer-remove><?php echo esc_html__( 'Remove co-organizer', 'taka-platform' ); ?></button></p>
			<p><label><?php echo esc_html__( 'Name', 'taka-platform' ); ?><br><input class="widefat" type="text" name="<?php echo esc_attr( $prefix ); ?>[name]" value="<?php echo esc_attr( $item['name'] ?? '' ); ?>"></label></p>
			<p><label><?php echo esc_html__( 'Legal name', 'taka-platform' ); ?><br><input class="widefat" type="text" name="<?php echo esc_attr( $prefix ); ?>[legal_name]" value="<?php echo esc_attr( $item['legal_name'] ?? '' ); ?>"></label></p>
			<p><label><?php echo esc_html__( 'Website', 'taka-platform' ); ?><br><input class="widefat" type="url" name="<?php echo esc_attr( $prefix ); ?>[website]" value="<?php echo esc_attr( $item['website'] ?? '' ); ?>"></label></p>
			<p><label><?php echo esc_html__( 'Email', 'taka-platform' ); ?><br><input class="widefat" type="email" name="<?php echo esc_attr( $prefix ); ?>[email]" value="<?php echo esc_attr( $item['email'] ?? '' ); ?>"></label></p>
			<p>
				<strong><?php echo esc_html__( 'Logo', 'taka-platform' ); ?></strong><br>
				<input id="<?php echo esc_attr( $input_id ); ?>" type="hidden" name="<?php echo esc_attr( $prefix ); ?>[logo_id]" value="<?php echo esc_attr( (string) $logo_id ); ?>">
				<button type="button" class="button" data-taka-media-pick data-target="<?php echo esc_attr( $input_id ); ?>" data-preview="<?php echo esc_attr( $preview_id ); ?>"><?php echo esc_html__( 'Select logo', 'taka-platform' ); ?></button>
				<button type="button" class="button" data-taka-media-remove data-target="<?php echo esc_attr( $input_id ); ?>" data-preview="<?php echo esc_attr( $preview_id ); ?>"><?php echo esc_html__( 'Remove image', 'taka-platform' ); ?></button>
				<div id="<?php echo esc_attr( $preview_id ); ?>"><?php self::image_preview( $logo_id, $logo_url ); ?></div>
			</p>
			<p><label><?php echo esc_html__( 'Fallback logo URL', 'taka-platform' ); ?><br><input class="widefat" type="url" name="<?php echo esc_attr( $prefix ); ?>[logo_url]" value="<?php echo esc_attr( $logo_url ); ?>"></label></p>
			<p><label><?php echo esc_html__( 'Description', 'taka-platform' ); ?><br><textarea class="widefat" rows="3" name="<?php echo esc_attr( $prefix ); ?>[description]"><?php echo esc_textarea( $item['description'] ?? '' ); ?></textarea></label></p>
			<p><label><?php echo esc_html__( 'Instagram', 'taka-platform' ); ?><br><input class="widefat" type="url" name="<?php echo esc_attr( $prefix ); ?>[social_links][instagram]" value="<?php echo esc_attr( $social['instagram'] ?? '' ); ?>"></label></p>
			<p><label><?php echo esc_html__( 'Facebook', 'taka-platform' ); ?><br><input class="widefat" type="url" name="<?php echo esc_attr( $prefix ); ?>[social_links][facebook]" value="<?php echo esc_attr( $social['facebook'] ?? '' ); ?>"></label></p>
			<p><label><?php echo esc_html__( 'YouTube', 'taka-platform' ); ?><br><input class="widefat" type="url" name="<?php echo esc_attr( $prefix ); ?>[social_links][youtube]" value="<?php echo esc_attr( $social['youtube'] ?? '' ); ?>"></label></p>
			<p><label><?php echo esc_html__( 'Sort order', 'taka-platform' ); ?><br><input type="number" name="<?php echo esc_attr( $prefix ); ?>[sort_order]" value="<?php echo esc_attr( (string) ( $item['sort_order'] ?? 0 ) ); ?>"></label></p>
			<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[active]" value="0">
			<p><label><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[active]" value="1" <?php checked( ! array_key_exists( 'active', $item ) || ! empty( $item['active'] ) ); ?>> <?php echo esc_html__( 'Active', 'taka-platform' ); ?></label></p>
		</div>
		<?php
	}


	/** Render event-specific booking-information override fields. */
	private static function render_event_booking_information_fields( $post_id ) {
		$prefix = '_taka_booking_info_';
		?>
		<div class="taka-event-booking-info" style="border:1px solid #dcdcde;padding:12px;margin:12px 0;background:#fff;">
			<h3><?php echo esc_html__( 'Booking information override', 'taka-platform' ); ?></h3>
			<p><label><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>override" value="1" <?php checked( (string) self::meta( $post_id, 'booking_info_override' ), '1' ); ?>> <?php echo esc_html__( 'Use custom booking information for this event', 'taka-platform' ); ?></label></p>
			<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>enabled" value="0">
			<p><label><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>enabled" value="1" <?php checked( '' === (string) self::meta( $post_id, 'booking_info_enabled' ) || '1' === (string) self::meta( $post_id, 'booking_info_enabled' ) ); ?>> <?php echo esc_html__( 'Show booking information for this event', 'taka-platform' ); ?></label></p>
			<?php self::text( $post_id, 'booking_info_title', __( 'Booking information title', 'taka-platform' ) ); ?>
			<?php self::textarea( $post_id, 'booking_info_intro', __( 'Intro text', 'taka-platform' ) ); ?>
			<?php self::textarea( $post_id, 'booking_info_group_booking', __( 'Group booking text', 'taka-platform' ) ); ?>
			<?php self::textarea( $post_id, 'booking_info_multi_event_discount', __( 'Multi-event discount text', 'taka-platform' ) ); ?>
			<?php self::text( $post_id, 'booking_info_contact_email', __( 'Contact email', 'taka-platform' ) ); ?>
			<?php self::textarea( $post_id, 'booking_info_booking_process', __( 'Booking process text', 'taka-platform' ) ); ?>
			<?php self::textarea( $post_id, 'booking_info_payment_methods', __( 'Payment methods', 'taka-platform' ) ); ?>
			<?php self::textarea( $post_id, 'booking_info_cancellation_policy', __( 'Cancellation policy text', 'taka-platform' ) ); ?>
			<?php self::textarea( $post_id, 'booking_info_additional_notes', __( 'Additional notes', 'taka-platform' ) ); ?>
		</div>
		<?php
	}

	/** Sanitize booking-information settings. */
	private static function sanitize_booking_information( $posted, $global = false ) {
		$posted = is_array( $posted ) ? $posted : array();
		return array(
			'override' => ! empty( $posted['override'] ) ? '1' : '0',
			'enabled' => ! empty( $posted['enabled'] ) ? '1' : '0',
			'title' => self::sanitize_dynamic_text( $posted['title'] ?? '', false ),
			'intro' => self::sanitize_dynamic_text( $posted['intro'] ?? '', true ),
			'group_booking' => self::sanitize_dynamic_text( $posted['group_booking'] ?? '', true ),
			'multi_event_discount' => self::sanitize_dynamic_text( $posted['multi_event_discount'] ?? '', true ),
			'contact_email' => sanitize_email( $posted['contact_email'] ?? '' ),
			'booking_process' => self::sanitize_dynamic_text( $posted['booking_process'] ?? '', true ),
			'payment_methods' => self::sanitize_dynamic_text( $posted['payment_methods'] ?? '', true ),
			'cancellation_policy' => self::sanitize_dynamic_text( $posted['cancellation_policy'] ?? '', true ),
			'additional_notes' => self::sanitize_dynamic_text( $posted['additional_notes'] ?? '', true ),
		);
	}

	private static function nonce() { wp_nonce_field( self::NONCE, self::NONCE ); }
	private static function meta( $post_id, $field ) { return get_post_meta( $post_id, '_taka_' . $field, true ); }
	private static function field( $label, $html ) { echo '<p><label><strong>' . esc_html( $label ) . '</strong><br>' . $html . '</label></p>'; }
	private static function text( $post_id, $field, $label ) { self::field( $label, '<input class="widefat" type="text" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( self::meta( $post_id, $field ) ) . '">' ); }
	private static function number( $post_id, $field, $label ) { self::field( $label, '<input type="number" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( self::meta( $post_id, $field ) ) . '">' ); }
	private static function url( $post_id, $field, $label ) { self::field( $label, '<input class="widefat" type="url" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( self::meta( $post_id, $field ) ) . '">' ); }
	private static function textarea( $post_id, $field, $label ) { self::field( $label, '<textarea class="widefat" rows="3" name="_taka_' . esc_attr( $field ) . '">' . esc_textarea( self::meta( $post_id, $field ) ) . '</textarea>' ); }
	private static function checkbox( $post_id, $field, $label ) { self::field( $label, '<input type="checkbox" name="_taka_' . esc_attr( $field ) . '" value="1" ' . checked( (string) self::meta( $post_id, $field ), '1', false ) . '>' ); }

	private static function sanitize_dynamic_text( $value, $textarea = false ) {
		$callback = $textarea ? 'sanitize_textarea_field' : 'sanitize_text_field';
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( TAKA_Platform_I18n::instance()->get_all_languages() as $lang ) { $out[ $lang ] = $callback( $value[ $lang ] ?? '' ); }
			return $out;
		}
		return $callback( $value );
	}
	private static function settings_multilingual_text_row( $name, $label, $value ) { self::settings_multilingual_row( $name, $label, $value, false ); }
	private static function settings_multilingual_textarea_row( $name, $label, $value ) { self::settings_multilingual_row( $name, $label, $value, true ); }
	private static function settings_multilingual_row( $name, $label, $value, $textarea = false ) { echo '<tr><th scope="row"><label>' . esc_html( $label ) . '</label></th><td>'; foreach ( TAKA_Platform_I18n::instance()->get_all_languages() as $lang ) { $field_name = $name . '[' . $lang . ']'; $field_value = is_array( $value ) ? ( $value[ $lang ] ?? '' ) : ( 'en' === $lang ? $value : '' ); echo '<p><label><strong>' . esc_html( strtoupper( $lang ) ) . '</strong><br>'; if ( $textarea ) { echo '<textarea class="large-text" rows="3" name="' . esc_attr( $field_name ) . '">' . esc_textarea( (string) $field_value ) . '</textarea>'; } else { echo '<input class="regular-text" type="text" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( (string) $field_value ) . '">'; } echo '</label></p>'; } echo '</td></tr>'; }
	private static function settings_text_row( $name, $label, $value ) { echo '<tr><th scope="row"><label>' . esc_html( $label ) . '</label></th><td><input class="regular-text" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"></td></tr>'; }
	private static function settings_textarea_row( $name, $label, $value ) { echo '<tr><th scope="row"><label>' . esc_html( $label ) . '</label></th><td><textarea class="large-text" rows="4" name="' . esc_attr( $name ) . '">' . esc_textarea( (string) $value ) . '</textarea></td></tr>'; }
	private static function settings_select_row( $name, $label, $value, $options ) { echo '<tr><th scope="row"><label>' . esc_html( $label ) . '</label></th><td><select name="' . esc_attr( $name ) . '">'; foreach ( $options as $key => $option_label ) { echo '<option value="' . esc_attr( $key ) . '" ' . selected( (string) $value, (string) $key, false ) . '>' . esc_html( $option_label ) . '</option>'; } echo '</select></td></tr>'; }
	private static function settings_media_row( $id_name, $url_name, $input_id, $label, $id, $url, $multiple = false ) { $id_value = is_array( $id ) ? implode( ',', array_map( 'absint', $id ) ) : (string) $id; $url_value = is_array( $url ) ? implode( "\n", array_map( 'esc_url_raw', $url ) ) : (string) $url; echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><input id="' . esc_attr( $input_id ) . '" type="hidden" name="' . esc_attr( $id_name ) . '" value="' . esc_attr( $id_value ) . '"> <button type="button" class="button" data-taka-media-pick data-multiple="' . ( $multiple ? '1' : '0' ) . '" data-target="' . esc_attr( $input_id ) . '" data-preview="' . esc_attr( $input_id . '_preview' ) . '">' . esc_html__( 'Select image', 'taka-platform' ) . '</button> <button type="button" class="button" data-taka-media-remove data-target="' . esc_attr( $input_id ) . '" data-preview="' . esc_attr( $input_id . '_preview' ) . '">' . esc_html__( 'Remove image', 'taka-platform' ) . '</button><div id="' . esc_attr( $input_id . '_preview' ) . '">'; $multiple ? self::image_previews( $id_value ) : self::image_preview( absint( $id_value ), $url_value ); echo '</div><p><label>' . esc_html__( 'Fallback URL', 'taka-platform' ) . '<br>'; if ( $multiple ) { echo '<textarea class="large-text" rows="3" name="' . esc_attr( $url_name ) . '">' . esc_textarea( $url_value ) . '</textarea>'; } else { echo '<input class="regular-text" type="url" name="' . esc_attr( $url_name ) . '" value="' . esc_attr( $url_value ) . '">'; } echo '</label></p></td></tr>'; }

	private static function csv_to_absints( $value ) { return array_values( array_filter( array_map( 'absint', preg_split( '/\s*,\s*/', (string) $value ) ) ) ); }
	private static function lines_to_array( $value ) { return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $value ) ) ) ); }
	private static function sanitize_decimal( $value, $default ) { $value = (string) $value; return preg_match( '/^(0(\.\d+)?|1(\.0+)?)$/', $value ) ? $value : $default; }
	private static function media_field( $post_id, $field, $label, $multiple = false, $button_label = null ) { $value = (string) self::meta( $post_id, $field ); $input_id = 'taka_' . $field . '_' . $post_id; $button_label = $button_label ?: __( 'Select image', 'taka-platform' ); $html = '<input id="' . esc_attr( $input_id ) . '" type="hidden" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( $value ) . '"> <button type="button" class="button" data-taka-media-pick data-multiple="' . ( $multiple ? '1' : '0' ) . '" data-target="' . esc_attr( $input_id ) . '" data-preview="' . esc_attr( $input_id . '_preview' ) . '">' . esc_html( $button_label ) . '</button> <button type="button" class="button" data-taka-media-remove data-target="' . esc_attr( $input_id ) . '" data-preview="' . esc_attr( $input_id . '_preview' ) . '">' . esc_html__( 'Remove image', 'taka-platform' ) . '</button><div id="' . esc_attr( $input_id . '_preview' ) . '">'; ob_start(); self::image_previews( $value ); $html .= ob_get_clean() . '</div>'; self::field( $label, $html ); }
	private static function organizer_relation( $post_id, $field, $label ) { $current = (int) self::meta( $post_id, $field ); $assigned = self::current_user_is_platform_admin() ? array() : self::get_current_user_organizer_ids(); if ( ! self::current_user_is_platform_admin() && 0 === $current && 1 === count( $assigned ) ) { $current = (int) $assigned[0]; } $args = array( 'post_type' => TAKA_PLATFORM_CPT_ORGANIZER, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ); if ( ! self::current_user_is_platform_admin() ) { $args['post__in'] = ! empty( $assigned ) ? $assigned : array( 0 ); } $posts = get_posts( $args ); $html = '<select name="_taka_' . esc_attr( $field ) . '"><option value="">—</option>'; foreach ( $posts as $post ) { $html .= '<option value="' . esc_attr( $post->ID ) . '" ' . selected( $current, $post->ID, false ) . '>' . esc_html( get_the_title( $post ) ) . '</option>'; } $html .= '</select>'; self::field( $label, $html ); }
	private static function relation( $post_id, $field, $label, $post_type ) { $current = (int) self::meta( $post_id, $field ); $posts = get_posts( array( 'post_type' => $post_type, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ); $html = '<select name="_taka_' . esc_attr( $field ) . '"><option value="">—</option>'; foreach ( $posts as $post ) { $html .= '<option value="' . esc_attr( $post->ID ) . '" ' . selected( $current, $post->ID, false ) . '>' . esc_html( get_the_title( $post ) ) . '</option>'; } $html .= '</select>'; self::field( $label, $html ); }
	private static function image_preview( $id, $fallback_url = '' ) { $url = $id && function_exists( 'wp_get_attachment_image_url' ) ? wp_get_attachment_image_url( $id, 'thumbnail' ) : $fallback_url; if ( $url ) { echo '<img src="' . esc_url( $url ) . '" style="max-width:180px;height:auto;display:block;margin-top:8px;" alt="">'; } }
	private static function image_previews( $ids ) { foreach ( array_filter( array_map( 'absint', preg_split( '/\s*,\s*/', (string) $ids ) ) ) as $id ) { self::image_preview( $id ); } }
}

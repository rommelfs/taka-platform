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
		add_action( 'admin_init', array( __CLASS__, 'guard_content_edit_screen' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_data_source_notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'show_user_profile', array( __CLASS__, 'render_user_organizer_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_user_organizer_fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_user_organizer_fields' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_user_organizer_fields' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_event_admin_query' ) );
		add_filter( 'ajax_query_attachments_args', array( __CLASS__, 'filter_media_library_args' ) );
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_event_meta_caps' ), 10, 4 );
		add_action( 'save_post_taka_organizer', array( __CLASS__, 'save_organizer' ) );
		add_action( 'save_post_taka_venue', array( __CLASS__, 'save_venue' ) );
		add_action( 'save_post_taka_event', array( __CLASS__, 'save_event' ) );
		add_action( 'save_post_taka_content_block', array( __CLASS__, 'save_content_block' ) );
		add_action( 'admin_post_taka_tour_save_media', array( __CLASS__, 'handle_save_media' ) );
		add_action( 'admin_post_taka_tour_import_config', array( __CLASS__, 'handle_import_config' ) );
		add_action( 'admin_post_taka_platform_save_hero', array( __CLASS__, 'handle_save_hero' ) );
		add_action( 'admin_post_taka_platform_save_sections', array( __CLASS__, 'handle_save_sections' ) );
		add_action( 'admin_post_taka_platform_save_dashboard_settings', array( __CLASS__, 'handle_save_dashboard_settings' ) );
		add_action( 'admin_post_taka_platform_save_booking_information', array( __CLASS__, 'handle_save_booking_information' ) );
		add_action( 'admin_post_taka_platform_save_ticket_section', array( __CLASS__, 'handle_save_ticket_section' ) );
		add_action( 'admin_post_taka_platform_save_option_lists', array( __CLASS__, 'handle_save_option_lists' ) );
		add_action( 'admin_post_taka_platform_import_option_lists', array( __CLASS__, 'handle_import_option_lists' ) );
		add_action( 'admin_post_taka_platform_sync_translations', array( __CLASS__, 'handle_sync_translations' ) );
		add_action( 'admin_post_taka_platform_export_translation_audit', array( __CLASS__, 'handle_export_translation_audit' ) );
		add_action( 'admin_post_taka_platform_export_translation_package', array( __CLASS__, 'handle_export_translation_package' ) );
		add_action( 'admin_post_taka_platform_import_translation_package', array( __CLASS__, 'handle_import_translation_package' ) );
		add_action( 'admin_post_taka_platform_save_translation_glossary', array( __CLASS__, 'handle_save_translation_glossary' ) );
	}


	/** Ensure platform roles and capabilities are available. */
	public static function ensure_capabilities() {
		if ( ! function_exists( 'get_role' ) ) { return; }
		$editor_caps = array(
			'read',
			'upload_files',
		);
		foreach ( self::managed_post_type_cap_bases() as $base ) {
			$editor_caps = array_merge(
				$editor_caps,
				array(
					'edit_' . $base['plural'],
					'edit_' . $base['singular'],
					'edit_assigned_' . $base['plural'],
					'read_' . $base['singular'],
					'publish_' . $base['plural'],
					'edit_published_' . $base['plural'],
					'delete_' . $base['plural'],
					'delete_' . $base['singular'],
				)
			);
		}
		$editor_caps[] = 'edit_taka_organizer_profile';
		$editor_caps = array_values( array_unique( $editor_caps ) );

		if ( ! get_role( 'taka_organizer' ) && function_exists( 'add_role' ) ) {
			add_role(
				'taka_organizer',
				__( 'TAKA Organizer', 'taka-platform' ),
				array_fill_keys( $editor_caps, true )
			);
		}
		if ( ! get_role( 'taka_editor' ) && function_exists( 'add_role' ) ) {
			add_role(
				'taka_editor',
				__( 'TAKA Editor', 'taka-platform' ),
				array_fill_keys( $editor_caps, true )
			);
		}

		foreach ( array( 'taka_organizer', 'taka_editor' ) as $role_name ) {
			$editor_role = get_role( $role_name );
			if ( ! $editor_role ) { continue; }
			foreach ( $editor_caps as $cap ) {
				$editor_role->add_cap( $cap );
			}
			foreach ( self::restricted_editor_caps() as $cap ) {
				$editor_role->remove_cap( $cap );
			}
		}

		$role = get_role( 'administrator' );
		if ( ! $role ) { return; }
		foreach ( array_merge( array( 'manage_taka_tour', 'edit_taka_organizer_profile', 'upload_files' ), self::administrator_caps() ) as $cap ) {
			$role->add_cap( $cap );
		}
	}

	/** Register admin CPTs. */
	public static function register_post_types() {
		self::register_post_type( TAKA_PLATFORM_CPT_EVENT, __( 'Events', 'taka-platform' ), __( 'Event hinzufügen', 'taka-platform' ), 'dashicons-calendar-alt' );
		self::register_post_type( TAKA_PLATFORM_CPT_ORGANIZER, __( 'Organizers', 'taka-platform' ), __( 'Organizer hinzufügen', 'taka-platform' ), 'dashicons-groups' );
		self::register_post_type( TAKA_PLATFORM_CPT_VENUE, __( 'Venues', 'taka-platform' ), __( 'Venue hinzufügen', 'taka-platform' ), 'dashicons-location-alt' );
		self::register_post_type( TAKA_PLATFORM_CPT_CONTENT_BLOCK, __( 'Content Blocks', 'taka-platform' ), __( 'Content Block hinzufügen', 'taka-platform' ), 'dashicons-editor-table' );
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
			'supports'     => array( 'title' ),
			'capability_type' => 'post',
		);

		if ( isset( self::managed_post_types()[ $post_type ] ) ) {
			$args['map_meta_cap'] = true;
			$args['capabilities'] = self::post_type_capabilities( $post_type );
		}

		if ( TAKA_PLATFORM_CPT_EVENT === $post_type ) {
			$args['supports'] = array( 'title' );
		}

		if ( TAKA_PLATFORM_CPT_CONTENT_BLOCK === $post_type ) {
			$args['supports'] = array( 'title', 'editor' );
		}

		if ( TAKA_PLATFORM_CPT_VENUE === $post_type ) {
			$args['supports'] = array( 'title' );
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
		add_submenu_page( 'taka-platform', __( 'Status', 'taka-platform' ), __( 'Status', 'taka-platform' ), 'manage_options', 'taka-platform-status', array( __CLASS__, 'render_status' ) );
		add_submenu_page( 'taka-platform', __( 'Diagnostics', 'taka-platform' ), __( 'Diagnostics', 'taka-platform' ), 'manage_options', 'taka-platform-diagnostics', array( __CLASS__, 'render_diagnostics' ) );
		add_submenu_page( 'taka-platform', __( 'Option Lists', 'taka-platform' ), __( 'Option Lists', 'taka-platform' ), 'manage_options', 'taka-platform-option-lists', array( __CLASS__, 'render_option_lists' ) );
		add_submenu_page( 'taka-platform', __( 'Settings', 'taka-platform' ), __( 'Settings', 'taka-platform' ), 'manage_options', 'taka-tour-settings', array( __CLASS__, 'render_settings' ) );
		add_submenu_page( 'taka-platform', __( 'Website translations', 'taka-platform' ), __( 'Website translations', 'taka-platform' ), 'manage_options', 'taka-platform-translations', array( __CLASS__, 'render_translations' ) );
		add_submenu_page( 'taka-platform', __( 'Integrations / Events Manager', 'taka-platform' ), __( 'Events Manager', 'taka-platform' ), 'manage_options', 'taka-platform-events-manager', array( __CLASS__, 'render_events_manager_integration' ) );
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
		wp_localize_script(
			'taka-platform-admin',
			'takaPlatformAdminI18n',
			array(
				'sourceTabLabel' => __( '%s original', 'taka-platform' ),
				'sourceTextLabel' => __( '%s — Original text', 'taka-platform' ),
				'translationTextLabel' => __( '%s — Website translation', 'taka-platform' ),
				'languageTranslationLabel' => __( '%s website translation', 'taka-platform' ),
				'sourceLanguageHelp' => __( 'Original content language: %s. Enter the original text here; website translations are managed separately.', 'taka-platform' ),
				'sourcePanelHelp' => __( 'This is the original content language. Edit the original text here. Website translations are entered in the other language tabs.', 'taka-platform' ),
				'editableSourcePanelHelp' => __( 'This is the original content language. Edit the original text here. Website translations are entered in the other language tabs.', 'taka-platform' ),
				'translationPanelHelp' => __( 'Enter the website translation for this language based on the original text.', 'taka-platform' ),
				'thisIsSourceLanguage' => __( 'This is the original content language.', 'taka-platform' ),
				'editSourceColumn' => __( 'Edit the original label column.', 'taka-platform' ),
			)
		);
		wp_enqueue_script( 'taka-platform-media-fields', TAKA_PLATFORM_PLUGIN_URL . 'assets/js/media-fields.js', array(), TAKA_PLATFORM_VERSION, true );
	}

	/** Register meta boxes. */
	public static function add_meta_boxes() {
		add_meta_box( 'taka_organizer_details', __( 'Organizer details', 'taka-platform' ), array( __CLASS__, 'render_organizer_meta_box' ), TAKA_PLATFORM_CPT_ORGANIZER, 'normal', 'high' );
		add_meta_box( 'taka_venue_details', __( 'Venue details', 'taka-platform' ), array( __CLASS__, 'render_venue_meta_box' ), TAKA_PLATFORM_CPT_VENUE, 'normal', 'high' );
		add_meta_box( 'taka_event_details', __( 'Event details', 'taka-platform' ), array( __CLASS__, 'render_event_meta_box' ), TAKA_PLATFORM_CPT_EVENT, 'normal', 'high' );
		add_meta_box( 'taka_content_block_details', __( 'Reusable content', 'taka-platform' ), array( __CLASS__, 'render_content_block_meta_box' ), TAKA_PLATFORM_CPT_CONTENT_BLOCK, 'normal', 'high' );
		foreach ( array_keys( self::managed_post_types() ) as $post_type ) {
			add_meta_box( 'taka_content_access', __( 'TAKA access', 'taka-platform' ), array( __CLASS__, 'render_access_meta_box' ), $post_type, 'side', 'default' );
		}
	}

	/** Render object-level access controls. */
	public static function render_access_meta_box( $post ) {
		$owner_id               = (int) $post->post_author;
		$assigned_user_ids      = self::get_post_assigned_user_ids( $post->ID );
		$assigned_organizer_ids = self::get_post_assigned_organizer_ids( $post->ID );
		$mode                   = self::post_permission_mode( $post->ID );
		$owner                  = $owner_id ? get_user_by( 'id', $owner_id ) : null;
		$can_manage_access      = self::current_user_is_platform_admin();

		if ( ! $can_manage_access ) {
			?>
			<p><strong><?php echo esc_html__( 'Owner', 'taka-platform' ); ?></strong><br><?php echo esc_html( $owner ? $owner->display_name : __( 'Unknown user', 'taka-platform' ) ); ?></p>
			<p><strong><?php echo esc_html__( 'Permission mode', 'taka-platform' ); ?></strong><br><?php echo esc_html( self::access_modes()[ $mode ] ?? self::access_modes()['owner'] ); ?></p>
			<?php if ( ! empty( $assigned_user_ids ) ) : ?>
				<p><strong><?php echo esc_html__( 'Assigned users', 'taka-platform' ); ?></strong><br><?php echo esc_html( self::user_names_list( $assigned_user_ids ) ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $assigned_organizer_ids ) ) : ?>
				<p><strong><?php echo esc_html__( 'Assigned organizers', 'taka-platform' ); ?></strong><br><?php echo esc_html( self::post_titles_list( $assigned_organizer_ids ) ); ?></p>
			<?php endif; ?>
			<p class="description"><?php echo esc_html__( 'Administrators manage access assignments. Your edit access comes from ownership or an explicit assignment.', 'taka-platform' ); ?></p>
			<?php
			return;
		}

		$users = get_users(
			array(
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => array( 'ID', 'display_name', 'user_login' ),
			)
		);
		$organizers = get_posts(
			array(
				'post_type'      => TAKA_PLATFORM_CPT_ORGANIZER,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<p>
			<label for="taka-access-owner-user-id"><strong><?php echo esc_html__( 'Owner', 'taka-platform' ); ?></strong></label><br>
			<select id="taka-access-owner-user-id" name="taka_access_owner_user_id" style="width:100%;">
				<?php foreach ( $users as $user ) : ?>
					<option value="<?php echo esc_attr( (string) $user->ID ); ?>" <?php selected( $owner_id, (int) $user->ID ); ?>><?php echo esc_html( $user->display_name ?: $user->user_login ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="taka-access-permission-mode"><strong><?php echo esc_html__( 'Permission mode', 'taka-platform' ); ?></strong></label><br>
			<select id="taka-access-permission-mode" name="taka_access_permission_mode" style="width:100%;">
				<?php foreach ( self::access_modes() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $mode, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="taka-access-assigned-user-ids"><strong><?php echo esc_html__( 'Assigned users', 'taka-platform' ); ?></strong></label><br>
			<select id="taka-access-assigned-user-ids" name="taka_access_assigned_user_ids[]" multiple size="6" style="width:100%;">
				<?php foreach ( $users as $user ) : ?>
					<option value="<?php echo esc_attr( (string) $user->ID ); ?>" <?php selected( in_array( (int) $user->ID, $assigned_user_ids, true ) ); ?>><?php echo esc_html( $user->display_name ?: $user->user_login ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="taka-access-assigned-organizer-ids"><strong><?php echo esc_html__( 'Assigned organizer members', 'taka-platform' ); ?></strong></label><br>
			<select id="taka-access-assigned-organizer-ids" name="taka_access_assigned_organizer_ids[]" multiple size="6" style="width:100%;">
				<?php foreach ( $organizers as $organizer ) : ?>
					<option value="<?php echo esc_attr( (string) $organizer->ID ); ?>" <?php selected( in_array( (int) $organizer->ID, $assigned_organizer_ids, true ) ); ?>><?php echo esc_html( get_the_title( $organizer ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description"><?php echo esc_html__( 'Object access is enforced server-side. Non-admin editors see their own content by default; assigned users or organizer members need a matching permission mode.', 'taka-platform' ); ?></p>
		<?php
	}

	/** Render dashboard. */
	public static function render_dashboard() {
		if ( ! current_user_can( 'edit_taka_events' ) ) { return; }
		if ( ! current_user_can( 'manage_options' ) ) {
			$organizers = self::get_current_user_organizer_ids();
			$event_ids  = self::accessible_post_ids_for_user( TAKA_PLATFORM_CPT_EVENT, get_current_user_id(), 'edit' );
			$events     = empty( $event_ids ) ? array() : get_posts( array( 'post_type' => TAKA_PLATFORM_CPT_EVENT, 'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ), 'posts_per_page' => -1, 'post__in' => $event_ids ) );
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
		$status             = TAKA_Platform_Data::data_source_status();
		$wp_event_count     = (int) ( $status['wp_event_count'] ?? 0 );
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
				<tr><th><?php echo esc_html__( 'Active frontend source', 'taka-platform' ); ?></th><td><?php echo esc_html( ! empty( $status['using_database'] ) ? __( 'Database', 'taka-platform' ) : __( 'Config fallback', 'taka-platform' ) ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Website translation files', 'taka-platform' ); ?></th><td><?php echo esc_html( (string) count( is_array( $translations ) ? $translations : array() ) ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Required CPTs registered', 'taka-platform' ); ?></th><td><?php echo ! empty( $status['required_post_types_registered'] ) ? esc_html__( 'Yes', 'taka-platform' ) : esc_html__( 'No', 'taka-platform' ); ?></td></tr>
			</tbody></table>
			<p><?php echo esc_html__( 'TAKA – Ticketing, Attendance, Knowledge & Administration. Use the Events, Organizers, Venues, Media and Import / Export screens to manage reusable international event tours.', 'taka-platform' ); ?></p>
		</div>
		<?php
	}

	/** Show when frontend is intentionally using bundled config fallback. */
	public static function render_data_source_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! TAKA_Platform_Data::data_source_status()['using_config_fallback'] ) {
			return;
		}
		$status = TAKA_Platform_Data::data_source_status();
		if ( empty( $status['config_event_count'] ) ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php echo esc_html__( 'TAKA Platform is using config fallback data.', 'taka-platform' ); ?></strong>
				<?php echo esc_html__( 'No database events exist yet, so frontend event output comes from config/tour-events.php. Import the config to make admin event edits drive the frontend.', 'taka-platform' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=taka-tour-import-export' ) ); ?>"><?php echo esc_html__( 'Open import', 'taka-platform' ); ?></a>
			</p>
		</div>
		<?php
	}

	/** Render active data source and CPT status. */
	public static function render_status() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$status = TAKA_Platform_Data::data_source_status();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'TAKA Platform Status', 'taka-platform' ); ?></h1>
			<table class="widefat striped" style="max-width: 900px;"><tbody>
				<tr><th><?php echo esc_html__( 'Active frontend data source', 'taka-platform' ); ?></th><td><?php echo esc_html( $status['using_database'] ? __( 'Database', 'taka-platform' ) : __( 'Config fallback', 'taka-platform' ) ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Database events', 'taka-platform' ); ?></th><td><?php echo esc_html( (string) ( $status['wp_event_count'] ?? 0 ) ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Published database events', 'taka-platform' ); ?></th><td><?php echo esc_html( (string) ( $status['wp_published_event_count'] ?? 0 ) ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Config fallback events', 'taka-platform' ); ?></th><td><?php echo esc_html( (string) ( $status['config_event_count'] ?? 0 ) ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Required CPTs registered', 'taka-platform' ); ?></th><td><?php echo ! empty( $status['required_post_types_registered'] ) ? esc_html__( 'Yes', 'taka-platform' ) : esc_html__( 'No', 'taka-platform' ); ?></td></tr>
			</tbody></table>
			<h2><?php echo esc_html__( 'Custom Post Types', 'taka-platform' ); ?></h2>
			<table class="widefat striped" style="max-width: 900px;">
				<thead><tr><th><?php echo esc_html__( 'Post type', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Registered', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Published', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'All statuses', 'taka-platform' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $status['post_types'] as $post_type => $row ) : ?>
						<tr>
							<td><code><?php echo esc_html( $post_type ); ?></code> <?php echo esc_html( $row['label'] ?? '' ); ?></td>
							<td><?php echo ! empty( $row['registered'] ) ? esc_html__( 'Yes', 'taka-platform' ) : esc_html__( 'No', 'taka-platform' ); ?></td>
							<td><?php echo esc_html( (string) ( $row['count_publish'] ?? 0 ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['count_any'] ?? 0 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=taka-tour-import-export' ) ); ?>"><?php echo esc_html__( 'Import config seed data', 'taka-platform' ); ?></a></p>
		</div>
		<?php
	}

	/** Render per-event source-of-truth diagnostics. */
	public static function render_diagnostics() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$lang = sanitize_key( $_GET['lang'] ?? taka_tour_current_language() ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $lang, TAKA_Platform_I18n::instance()->get_all_languages(), true ) ) { $lang = TAKA_Platform_Data::platform_fallback_language(); }
		$status = TAKA_Platform_Data::data_source_status();
		$rows = TAKA_Platform_Data::event_diagnostics( $lang );
		$section_rows = TAKA_Platform_Data::content_section_diagnostics( $lang );
		$route_rows = TAKA_Platform_Data::hero_route_map_diagnostics( $lang );
		$date_debug_rows = TAKA_Platform_Data::program_date_debug_check( 'de' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'TAKA Platform Diagnostics', 'taka-platform' ); ?></h1>
			<p><?php echo esc_html__( 'This page shows the event source of truth and final ticket values used by the frontend.', 'taka-platform' ); ?></p>
			<p><strong><?php echo esc_html__( 'Active frontend data source', 'taka-platform' ); ?>:</strong> <?php echo esc_html( ! empty( $status['using_database'] ) ? __( 'Database', 'taka-platform' ) : __( 'Config fallback', 'taka-platform' ) ); ?></p>
			<h2><?php echo esc_html__( 'Content Sections', 'taka-platform' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Section', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Visible', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Final source', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Reference', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Block found', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Block ID', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Block slug', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Block status', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Block enabled', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Final title', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Final body excerpt', 'taka-platform' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $section_rows as $row ) : ?>
						<tr>
							<td><code><?php echo esc_html( $row['key'] ?? '' ); ?></code></td>
							<td><?php echo esc_html( $row['visible'] ?? '' ); ?></td>
							<td><?php echo esc_html( $row['content_source'] ?? '' ); ?></td>
							<td><code><?php echo esc_html( $row['reference_block'] ?? '' ); ?></code></td>
							<td><?php echo ! empty( $row['block_found'] ) ? esc_html__( 'Yes', 'taka-platform' ) : esc_html__( 'No', 'taka-platform' ); ?></td>
							<td><?php echo esc_html( $row['block_id'] ?? '' ); ?></td>
							<td><code><?php echo esc_html( $row['block_slug'] ?? '' ); ?></code></td>
							<td><?php echo esc_html( $row['block_status'] ?? '' ); ?></td>
							<td><?php echo esc_html( $row['block_enabled'] ?? '' ); ?></td>
							<td><?php echo esc_html( $row['final_title'] ?? '' ); ?></td>
							<td><?php echo esc_html( $row['final_body_excerpt'] ?? '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<h2><?php echo esc_html__( 'Hero Route Map', 'taka-platform' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Route index', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Type', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Event ID', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Event title', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Location name', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Country', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Start date', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Start time', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Start datetime', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Assigned marker coordinates', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Coordinate source', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Final map label', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Label source', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Label coordinates', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Label anchor', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Label width', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Leader line', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Layout source', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Final sort key', 'taka-platform' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $route_rows as $row ) : ?>
						<tr>
							<td><code><?php echo esc_html( $row['route_index'] ?? '' ); ?></code></td>
							<td><code><?php echo esc_html( $row['station_type'] ?? 'event' ); ?></code></td>
							<td><code><?php echo esc_html( $row['event_id'] ?? '' ); ?></code></td>
							<td><?php echo esc_html( $row['event_title'] ?? '' ); ?></td>
							<td><?php echo esc_html( $row['location_name'] ?? '' ); ?></td>
							<td><?php echo esc_html( $row['country'] ?? '' ); ?></td>
							<td><code><?php echo esc_html( $row['event_start_date'] ?? '' ); ?></code></td>
							<td><code><?php echo esc_html( $row['event_start_time'] ?? '' ); ?></code></td>
							<td><code><?php echo esc_html( $row['start_datetime'] ?? '' ); ?></code></td>
							<td><code><?php echo esc_html( $row['coordinates'] ?? '' ); ?></code></td>
							<td><?php echo esc_html( $row['coordinate_source'] ?? '' ); ?></td>
							<td><?php echo esc_html( $row['final_map_label'] ?? '' ); ?></td>
							<td><?php echo esc_html( $row['label_source'] ?? '' ); ?></td>
							<td><code><?php echo esc_html( $row['label_coordinates'] ?? '' ); ?></code></td>
							<td><?php echo esc_html( $row['label_anchor'] ?? '' ); ?></td>
							<td><code><?php echo esc_html( $row['label_width'] ?? '' ); ?></code></td>
							<td><?php echo esc_html( $row['leader_line'] ?? '' ); ?></td>
							<td><?php echo esc_html( $row['label_layout_source'] ?? '' ); ?></td>
							<td><code><?php echo esc_html( $row['sort_key'] ?? '' ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<h2><?php echo esc_html__( 'Program Date Parsing', 'taka-platform' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Input', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Canonical date', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Date label', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'German weekday', 'taka-platform' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $date_debug_rows as $row ) : ?>
						<tr>
							<td><code><?php echo esc_html( $row['input'] ?? '' ); ?></code></td>
							<td><code><?php echo esc_html( $row['canonical'] ?? '' ); ?></code></td>
							<td><?php echo esc_html( $row['date_label'] ?? '' ); ?></td>
							<td><?php echo esc_html( $row['weekday'] ?? '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<h2><?php echo esc_html__( 'Events', 'taka-platform' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Event', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Data source', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Config ID', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'WP post ID', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Final ticket provider', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Final ticket status', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Final ticket URL', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Final Pretix URL', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Final label', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Database ticket', 'taka-platform' ); ?></th>
						<th><?php echo esc_html__( 'Config ticket', 'taka-platform' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $row['title'] ?? '' ); ?></strong></td>
							<td><?php echo esc_html( $row['data_source'] ?? '' ); ?></td>
							<td><code><?php echo esc_html( $row['config_id'] ?? '' ); ?></code></td>
							<td><?php echo esc_html( trim( (string) ( $row['wp_post_id'] ?? '' ) . ' ' . ( ! empty( $row['wp_post_status'] ) ? '(' . $row['wp_post_status'] . ')' : '' ) ) ); ?></td>
							<td><?php echo esc_html( $row['ticket_provider'] ?? '' ); ?></td>
							<td><?php echo esc_html( $row['ticket_status'] ?? '' ); ?></td>
							<td><?php echo '' !== (string) ( $row['ticket_shop_url'] ?? '' ) ? '<a href="' . esc_url( $row['ticket_shop_url'] ) . '">' . esc_html( $row['ticket_shop_url'] ) . '</a>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td><?php echo '' !== (string) ( $row['pretix_event_url'] ?? '' ) ? '<a href="' . esc_url( $row['pretix_event_url'] ) . '">' . esc_html( $row['pretix_event_url'] ) . '</a>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td><?php echo esc_html( $row['ticket_status_label'] ?? '' ); ?></td>
							<td><code><?php echo esc_html( trim( (string) ( $row['database_ticket_provider'] ?? '' ) . ' / ' . (string) ( $row['database_ticket_status'] ?? '' ) . ' / ' . (string) ( $row['database_ticket_shop_url'] ?? '' ) ) ); ?></code></td>
							<td><code><?php echo esc_html( trim( (string) ( $row['config_ticket_provider'] ?? '' ) . ' / ' . (string) ( $row['config_ticket_status'] ?? '' ) . ' / ' . (string) ( $row['config_ticket_shop_url'] ?? '' ) ) ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/** Render editable structured option lists. */
	public static function render_option_lists() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$option_lists = TAKA_Platform_Data::get_option_lists( true );
		$warnings = TAKA_Platform_Data::option_list_warnings();
		$export_json = wp_json_encode( $option_lists, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'TAKA Platform Option Lists', 'taka-platform' ); ?></h1>
			<p><?php echo esc_html__( 'Manage stable IDs and translated labels for structured event fields such as ticket provider, ticket status, format, audience, level, country and currency.', 'taka-platform' ); ?></p>
			<?php if ( ! empty( $warnings ) ) : ?>
				<div class="notice notice-warning">
					<p><strong><?php echo esc_html__( 'Unknown legacy option values found.', 'taka-platform' ); ?></strong></p>
					<ul>
						<?php foreach ( $warnings as $warning ) : ?>
							<li><?php echo esc_html( $warning ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="taka_platform_save_option_lists">
				<input type="hidden" name="redirect_to" value="taka-platform-option-lists">
				<?php wp_nonce_field( TAKA_Platform_Data::OPTION_LISTS_OPTION, self::NONCE ); ?>
				<?php self::render_option_lists_settings( $option_lists ); ?>
				<?php submit_button( __( 'Save option lists', 'taka-platform' ) ); ?>
			</form>
			<h2><?php echo esc_html__( 'Export option lists', 'taka-platform' ); ?></h2>
			<textarea class="large-text code" rows="12" readonly><?php echo esc_textarea( (string) $export_json ); ?></textarea>
			<h2><?php echo esc_html__( 'Import option lists', 'taka-platform' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="taka_platform_import_option_lists">
				<?php wp_nonce_field( TAKA_Platform_Data::OPTION_LISTS_OPTION, self::NONCE ); ?>
				<p><label><strong><?php echo esc_html__( 'Option list JSON', 'taka-platform' ); ?></strong><br><textarea class="large-text code" rows="10" name="option_lists_json"></textarea></label></p>
				<p class="description"><?php echo esc_html__( 'Imported lists are merged by stable list and option IDs. Existing unmentioned lists remain unchanged.', 'taka-platform' ); ?></p>
				<?php submit_button( __( 'Import option lists', 'taka-platform' ) ); ?>
			</form>
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

	/** Managed object types and their capability bases. */
	private static function managed_post_types() {
		return array(
			TAKA_PLATFORM_CPT_EVENT => array( 'singular' => 'taka_event', 'plural' => 'taka_events' ),
			TAKA_PLATFORM_CPT_VENUE => array( 'singular' => 'taka_venue', 'plural' => 'taka_venues' ),
			TAKA_PLATFORM_CPT_ORGANIZER => array( 'singular' => 'taka_organizer', 'plural' => 'taka_organizers' ),
			TAKA_PLATFORM_CPT_CONTENT_BLOCK => array( 'singular' => 'taka_content_block', 'plural' => 'taka_content_blocks' ),
		);
	}

	private static function managed_post_type_cap_bases() {
		return array_values( self::managed_post_types() );
	}

	private static function post_type_capabilities( $post_type ) {
		$base = self::managed_post_types()[ $post_type ] ?? null;
		if ( ! $base ) { return array(); }
		return array(
			'edit_post'              => 'edit_' . $base['singular'],
			'read_post'              => 'read_' . $base['singular'],
			'delete_post'            => 'delete_' . $base['singular'],
			'edit_posts'             => 'edit_' . $base['plural'],
			'edit_others_posts'      => 'edit_others_' . $base['plural'],
			'publish_posts'          => 'publish_' . $base['plural'],
			'edit_published_posts'   => 'edit_published_' . $base['plural'],
			'read_private_posts'     => 'read_private_' . $base['plural'],
			'delete_posts'           => 'delete_' . $base['plural'],
			'delete_others_posts'    => 'delete_others_' . $base['plural'],
			'delete_published_posts' => 'delete_published_' . $base['plural'],
			'create_posts'           => 'edit_' . $base['plural'],
		);
	}

	private static function administrator_caps() {
		$caps = array();
		foreach ( self::managed_post_type_cap_bases() as $base ) {
			$caps = array_merge(
				$caps,
				array(
					'edit_' . $base['plural'],
					'edit_' . $base['singular'],
					'edit_assigned_' . $base['plural'],
					'edit_others_' . $base['plural'],
					'edit_all_' . $base['plural'],
					'publish_' . $base['plural'],
					'edit_published_' . $base['plural'],
					'read_' . $base['singular'],
					'read_private_' . $base['plural'],
					'delete_' . $base['plural'],
					'delete_' . $base['singular'],
					'delete_assigned_' . $base['plural'],
					'delete_others_' . $base['plural'],
					'delete_all_' . $base['plural'],
					'delete_published_' . $base['plural'],
				)
			);
		}
		return array_values( array_unique( $caps ) );
	}

	private static function restricted_editor_caps() {
		$caps = array( 'manage_options', 'edit_users', 'activate_plugins', 'switch_themes', 'delete_users' );
		foreach ( self::managed_post_type_cap_bases() as $base ) {
			$caps = array_merge(
				$caps,
				array(
					'edit_others_' . $base['plural'],
					'edit_all_' . $base['plural'],
					'read_private_' . $base['plural'],
					'delete_assigned_' . $base['plural'],
					'delete_others_' . $base['plural'],
					'delete_all_' . $base['plural'],
					'delete_published_' . $base['plural'],
				)
			);
		}
		return array_values( array_unique( $caps ) );
	}

	private static function access_modes() {
		return array(
			'owner' => __( 'Owner only', 'taka-platform' ),
			'assigned_users' => __( 'Owner and assigned users', 'taka-platform' ),
			'organizer_members' => __( 'Owner, assigned users and assigned organizer members', 'taka-platform' ),
			'all_editors' => __( 'All TAKA editors', 'taka-platform' ),
			'admin_only' => __( 'Admin only', 'taka-platform' ),
		);
	}

	private static function post_permission_mode( $post_id ) {
		$mode = sanitize_key( (string) get_post_meta( $post_id, '_taka_permission_mode', true ) );
		return isset( self::access_modes()[ $mode ] ) ? $mode : 'owner';
	}

	private static function get_post_assigned_user_ids( $post_id ) {
		$ids = get_post_meta( $post_id, '_taka_assigned_user_ids', true );
		if ( ! is_array( $ids ) ) {
			$ids = array_filter( preg_split( '/\s*,\s*/', (string) $ids ) );
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	private static function get_post_assigned_organizer_ids( $post_id ) {
		$ids = get_post_meta( $post_id, '_taka_assigned_organizer_ids', true );
		if ( ! is_array( $ids ) ) {
			$ids = array_filter( preg_split( '/\s*,\s*/', (string) $ids ) );
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	private static function user_names_list( $user_ids ) {
		$names = array();
		foreach ( array_filter( array_map( 'absint', (array) $user_ids ) ) as $user_id ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user ) {
				$names[] = $user->display_name ?: $user->user_login;
			}
		}
		return implode( ', ', $names );
	}

	private static function post_titles_list( $post_ids ) {
		$titles = array();
		foreach ( array_filter( array_map( 'absint', (array) $post_ids ) ) as $post_id ) {
			$title = get_the_title( $post_id );
			if ( '' !== trim( (string) $title ) ) {
				$titles[] = $title;
			}
		}
		return implode( ', ', $titles );
	}

	private static function get_post_related_organizer_ids( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) { return array(); }
		$ids = self::get_post_assigned_organizer_ids( $post_id );
		if ( TAKA_PLATFORM_CPT_ORGANIZER === $post->post_type ) {
			$ids[] = (int) $post_id;
		}
		if ( TAKA_PLATFORM_CPT_EVENT === $post->post_type ) {
			$legacy = absint( get_post_meta( $post_id, '_taka_organizer_id', true ) );
			if ( $legacy ) { $ids[] = $legacy; }
			foreach ( TAKA_Platform_Data::normalize_event_organizer_relationships( get_post_meta( $post_id, '_taka_event_organizers', true ), $legacy ) as $relationship ) {
				$ids[] = absint( $relationship['organizer_id'] ?? 0 );
			}
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	private static function user_has_organizer_overlap( $user_id, $post_id ) {
		return ! empty( array_intersect( self::get_user_organizer_ids( $user_id ), self::get_post_related_organizer_ids( $post_id ) ) );
	}

	private static function user_can_access_post_action( $user_id, $post_id, $action = 'edit' ) {
		$post = get_post( $post_id );
		if ( ! $post || ! isset( self::managed_post_types()[ $post->post_type ] ) ) { return false; }
		if ( user_can( $user_id, 'manage_options' ) ) { return true; }

		$base = self::managed_post_types()[ $post->post_type ];
		$mode = self::post_permission_mode( $post_id );
		if ( 'admin_only' === $mode ) { return false; }

		$is_delete = 'delete' === $action;
		$own_cap = ( $is_delete ? 'delete_' : 'edit_' ) . $base['plural'];
		$assigned_cap = ( $is_delete ? 'delete_assigned_' : 'edit_assigned_' ) . $base['plural'];
		$all_cap = ( $is_delete ? 'delete_all_' : 'edit_all_' ) . $base['plural'];
		$base_cap = ( $is_delete ? 'delete_' : 'edit_' ) . $base['plural'];

		$others_cap = $is_delete ? 'delete_others_' . $base['plural'] : 'edit_others_' . $base['plural'];
		if ( user_can( $user_id, $all_cap ) || user_can( $user_id, $others_cap ) ) {
			return true;
		}
		if ( 'read' === $action && (int) $post->post_author === (int) $user_id && user_can( $user_id, 'read_' . $base['singular'] ) ) {
			return true;
		}
		if ( (int) $post->post_author === (int) $user_id && user_can( $user_id, $own_cap ) ) {
			return true;
		}
		if ( 'all_editors' === $mode && ! $is_delete && user_can( $user_id, $base_cap ) ) {
			return true;
		}
		if ( in_array( $mode, array( 'assigned_users', 'organizer_members', 'all_editors' ), true ) && in_array( (int) $user_id, self::get_post_assigned_user_ids( $post_id ), true ) && user_can( $user_id, $assigned_cap ) ) {
			return true;
		}
		if ( in_array( $mode, array( 'organizer_members', 'all_editors' ), true ) && self::user_has_organizer_overlap( $user_id, $post_id ) && user_can( $user_id, $assigned_cap ) ) {
			return true;
		}
		return false;
	}

	/** Public permission check for frontend dashboards and integrations. */
	public static function user_can_access_content( $user_id, $post_id, $action = 'edit' ) {
		return self::user_can_access_post_action( $user_id, $post_id, $action );
	}

	/** Whether a user can access an organizer-owned event. */
	private static function user_can_access_event( $user_id, $post_id ) {
		return self::user_can_access_post_action( $user_id, $post_id, 'edit' );
	}

	/** Load event posts for assigned organizer IDs. */
	private static function get_events_for_organizers( $organizer_ids ) {
		if ( empty( $organizer_ids ) ) {
			return array();
		}
		$event_ids = self::get_event_ids_for_organizers( $organizer_ids );
		if ( empty( $event_ids ) ) { return array(); }
		return get_posts( array( 'post_type' => TAKA_PLATFORM_CPT_EVENT, 'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ), 'posts_per_page' => -1, 'post__in' => $event_ids ) );
	}


	/** Find event IDs related to any of the given organizer IDs. */
	private static function get_event_ids_for_organizers( $organizer_ids ) {
		$organizer_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $organizer_ids ) ) ) );
		if ( empty( $organizer_ids ) ) { return array(); }
		$ids = get_posts( array( 'post_type' => TAKA_PLATFORM_CPT_EVENT, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
		$out = array();
		foreach ( $ids as $event_id ) {
			if ( self::event_has_any_organizer( (int) $event_id, $organizer_ids ) ) { $out[] = (int) $event_id; }
		}
		return $out;
	}

	private static function event_has_any_organizer( $event_id, $organizer_ids ) {
		$legacy = absint( get_post_meta( $event_id, '_taka_organizer_id', true ) );
		if ( $legacy && in_array( $legacy, $organizer_ids, true ) ) { return true; }
		foreach ( TAKA_Platform_Data::normalize_event_organizer_relationships( get_post_meta( $event_id, '_taka_event_organizers', true ), $legacy ) as $relationship ) {
			if ( in_array( absint( $relationship['organizer_id'] ?? 0 ), $organizer_ids, true ) ) { return true; }
		}
		return false;
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
					<p class="description"><?php echo esc_html__( 'Organizer membership can grant access to Events, Venues, Organizers and Content Blocks when the object permission mode allows assigned organizer members.', 'taka-platform' ); ?></p>
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

	private static function accessible_post_ids_for_user( $post_type, $user_id, $action = 'edit' ) {
		if ( ! isset( self::managed_post_types()[ $post_type ] ) ) { return array(); }
		if ( user_can( $user_id, 'manage_options' ) ) {
			return get_posts( array( 'post_type' => $post_type, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
		}
		$ids = get_posts( array( 'post_type' => $post_type, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
		$out = array();
		foreach ( $ids as $post_id ) {
			if ( self::user_can_access_post_action( $user_id, (int) $post_id, $action ) ) {
				$out[] = (int) $post_id;
			}
		}
		return $out;
	}

	/** Limit managed admin list tables to own/assigned objects for non-admin users. */
	public static function filter_event_admin_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || self::current_user_is_platform_admin() ) {
			return;
		}
		$post_type = $query->get( 'post_type' ) ?: '';
		if ( 'attachment' === $post_type || ( empty( $post_type ) && 'upload.php' === ( $GLOBALS['pagenow'] ?? '' ) ) ) {
			$query->set( 'author', get_current_user_id() );
			return;
		}
		if ( ! isset( self::managed_post_types()[ $post_type ] ) ) {
			return;
		}
		$ids = self::accessible_post_ids_for_user( $post_type, get_current_user_id(), 'edit' );
		$query->set( 'post__in', ! empty( $ids ) ? $ids : array( 0 ) );
	}

	/** Limit media modal attachment choices for non-admin editors to their uploads. */
	public static function filter_media_library_args( $args ) {
		if ( self::current_user_is_platform_admin() ) {
			return $args;
		}
		$args['author'] = get_current_user_id();
		return $args;
	}

	/** Enforce granular object access at capability level. */
	public static function map_event_meta_caps( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'edit_post', 'delete_post', 'read_post' ), true ) || empty( $args[0] ) ) {
			return $caps;
		}
		$post = get_post( (int) $args[0] );
		if ( ! $post ) {
			return $caps;
		}
		if ( user_can( $user_id, 'manage_options' ) ) {
			return $caps;
		}
		if ( 'attachment' === $post->post_type ) {
			if ( 'read_post' === $cap ) { return $caps; }
			return (int) $post->post_author === (int) $user_id && user_can( $user_id, 'upload_files' ) ? array( 'upload_files' ) : array( 'do_not_allow' );
		}
		if ( ! isset( self::managed_post_types()[ $post->post_type ] ) ) {
			return $caps;
		}
		$action = 'delete_post' === $cap ? 'delete' : ( 'read_post' === $cap ? 'read' : 'edit' );
		if ( ! self::user_can_access_post_action( $user_id, $post->ID, $action ) ) {
			return array( 'do_not_allow' );
		}
		$base = self::managed_post_types()[ $post->post_type ];
		if ( 'delete' === $action ) { return array( 'delete_' . $base['plural'] ); }
		if ( 'read' === $action ) { return array( 'read_' . $base['singular'] ); }
		return array( 'edit_' . $base['plural'] );
	}

	/** Block direct access to foreign managed-content edit screens for non-admin users. */
	public static function guard_content_edit_screen() {
		if ( self::current_user_is_platform_admin() || empty( $_GET['post'] ) ) {
			return;
		}
		$post_id = absint( $_GET['post'] );
		$post    = get_post( $post_id );
		if ( $post && isset( self::managed_post_types()[ $post->post_type ] ) && ! self::user_can_access_post_action( get_current_user_id(), $post_id, 'edit' ) ) {
			wp_die( esc_html__( 'You are not allowed to edit this TAKA content item.', 'taka-platform' ) );
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
				<div class="notice notice-info"><p><?php echo esc_html( $result['message'] ?? '' ); ?></p><?php self::render_import_summary( $result['summary'] ?? array() ); ?></div>
			<?php endif; ?>
			<h2><?php echo esc_html__( 'Import config/tour-events.php', 'taka-platform' ); ?></h2>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="taka_tour_import_config">
				<?php wp_nonce_field( 'taka_tour_import_config', self::IMPORT_NONCE ); ?>
				<table class="form-table" role="presentation"><tbody>
					<tr><th scope="row"><?php echo esc_html__( 'Import source', 'taka-platform' ); ?></th><td>
						<p><label><input type="radio" name="source" value="bundled" checked> <?php echo esc_html__( 'Bundled config/tour-events.php', 'taka-platform' ); ?></label></p>
						<p><label><input type="radio" name="source" value="upload"> <?php echo esc_html__( 'Upload JSON config file', 'taka-platform' ); ?></label><br><input type="file" name="config_file" accept=".json"></p>
						<p><label><input type="radio" name="source" value="json"> <?php echo esc_html__( 'Paste JSON', 'taka-platform' ); ?></label><br><textarea class="large-text code" rows="8" name="config_json" placeholder="{ &quot;organizers&quot;: {}, &quot;venues&quot;: {}, &quot;events&quot;: [] }"></textarea></p>
					</td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Options', 'taka-platform' ); ?></th><td>
						<p><label><input type="checkbox" name="dry_run" value="1"> <?php echo esc_html__( 'Dry run / preview only', 'taka-platform' ); ?></label></p>
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
		$status = TAKA_Platform_Translation_Packages::status();
		$langs = TAKA_Platform_Translation_Packages::language_labels();
		$result = get_transient( 'taka_platform_translation_import_result' );
		if ( false !== $result ) { delete_transient( 'taka_platform_translation_import_result' ); }
		$default_targets = array_values( array_diff( array_keys( $langs ), array( 'de' ) ) );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'TAKA Platform website translations', 'taka-platform' ); ?></h1>
			<p><?php echo esc_html__( 'TAKA Translation Packages export dynamic content as provider-independent JSON for ChatGPT, Claude, Gemini, DeepL, human translators and future API providers.', 'taka-platform' ); ?></p>
			<p><?php echo esc_html__( 'Terminology: original content language means the language of the editable original text; website translations are the public language versions; spoken / teaching languages on events describe what happens during the event.', 'taka-platform' ); ?></p>
			<?php if ( is_array( $result ) ) : ?>
				<div class="notice notice-info"><p><strong><?php echo esc_html__( 'Import summary', 'taka-platform' ); ?>:</strong> <?php echo esc_html( sprintf( __( 'Imported website translations: %1$d. Created: %2$d. Updated: %3$d. Skipped existing website translations: %4$d. Skipped changed original texts: %5$d. Errors: %6$d. Warnings: %7$d.', 'taka-platform' ), (int) ( $result['imported'] ?? 0 ), (int) ( $result['created'] ?? 0 ), (int) ( $result['updated'] ?? 0 ), (int) ( $result['skipped_existing'] ?? 0 ), (int) ( $result['skipped_changed_source'] ?? 0 ), count( $result['errors'] ?? array() ), count( $result['warnings'] ?? array() ) ) ); ?></p>
				<?php foreach ( array_merge( $result['errors'] ?? array(), $result['warnings'] ?? array() ) as $message ) : ?><p><?php echo esc_html( $message ); ?></p><?php endforeach; ?></div>
				<?php if ( ! empty( $result['report'] ) && is_array( $result['report'] ) ) : ?>
					<details open>
						<summary><strong><?php echo esc_html__( 'Import report', 'taka-platform' ); ?></strong></summary>
						<table class="widefat striped">
							<thead><tr><th><?php echo esc_html__( 'Item ID', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Object type', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Object ID', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Field', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Original language', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Website translation language', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Status', 'taka-platform' ); ?></th></tr></thead>
							<tbody>
								<?php foreach ( $result['report'] as $row ) : ?>
									<tr>
										<td><code><?php echo esc_html( $row['item_id'] ?? '' ); ?></code></td>
										<td><?php echo esc_html( $row['object_type'] ?? '' ); ?></td>
										<td><code><?php echo esc_html( $row['object_id'] ?? '' ); ?></code></td>
										<td><code><?php echo esc_html( $row['field'] ?? '' ); ?></code></td>
										<td><code><?php echo esc_html( $row['source_language'] ?? '' ); ?></code></td>
										<td><code><?php echo esc_html( $row['target_language'] ?? '' ); ?></code></td>
										<td><?php echo esc_html( $row['status'] ?? '' ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</details>
				<?php endif; ?>
			<?php endif; ?>
			<h2><?php echo esc_html__( 'Website translation overview', 'taka-platform' ); ?></h2>
			<p><strong><?php echo esc_html__( 'Canonical key count', 'taka-platform' ); ?>:</strong> <?php echo esc_html( (string) ( $audit['base_count'] ?? 0 ) ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
				<input type="hidden" name="action" value="taka_platform_sync_translations">
				<?php wp_nonce_field( 'taka_platform_sync_translations', self::NONCE ); ?>
				<?php submit_button( __( 'Sync missing keys', 'taka-platform' ), 'secondary', 'sync_static_keys', false ); ?>
				<?php submit_button( __( 'Generate fallback website translations', 'taka-platform' ), 'secondary', 'generate_fallbacks', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
				<input type="hidden" name="action" value="taka_platform_export_translation_audit">
				<?php wp_nonce_field( 'taka_platform_export_translation_audit', self::NONCE ); ?>
				<?php submit_button( __( 'Export audit JSON', 'taka-platform' ), 'secondary', 'submit', false ); ?>
			</form>
			<h2><?php echo esc_html__( 'Website translation status', 'taka-platform' ); ?></h2>
			<p><strong><?php echo esc_html__( 'Dynamic translatable items', 'taka-platform' ); ?>:</strong> <?php echo esc_html( (string) ( $status['total_items'] ?? 0 ) ); ?></p>
			<table class="widefat striped" style="max-width:720px;"><thead><tr><th><?php echo esc_html__( 'Website language', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Translated', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Missing', 'taka-platform' ); ?></th></tr></thead><tbody>
				<?php foreach ( $status['languages'] as $lang => $row ) : ?>
					<tr><td><strong><?php echo esc_html( strtoupper( $lang ) ); ?></strong></td><td><?php echo esc_html( (string) $row['translated'] ); ?></td><td><?php echo esc_html( (string) $row['missing'] ); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>
			<?php if ( ! empty( $status['warnings'] ) ) : ?>
				<div class="notice notice-warning inline"><p><strong><?php echo esc_html__( 'Website translation package warnings', 'taka-platform' ); ?></strong></p><?php foreach ( $status['warnings'] as $warning ) : ?><p><?php echo esc_html( $warning ); ?></p><?php endforeach; ?></div>
			<?php endif; ?>
			<h2><?php echo esc_html__( 'Export website translation package', 'taka-platform' ); ?></h2>
			<textarea class="large-text code" rows="9" readonly><?php echo esc_textarea( TAKA_Platform_Translation_Packages::translator_prompt() ); ?></textarea>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="taka_platform_export_translation_package">
				<?php wp_nonce_field( 'taka_platform_export_translation_package', self::NONCE ); ?>
				<table class="form-table" role="presentation"><tbody>
					<?php self::settings_select_row( 'source_language', __( 'Fallback original content language', 'taka-platform' ), 'de', $langs ); ?>
					<tr><th scope="row"><?php echo esc_html__( 'Website translation languages', 'taka-platform' ); ?></th><td><?php foreach ( $langs as $lang => $label ) : ?><label style="display:inline-block;margin-right:12px;"><input type="checkbox" name="target_languages[]" value="<?php echo esc_attr( $lang ); ?>" <?php checked( in_array( $lang, $default_targets, true ) ); ?>> <?php echo esc_html( $label ); ?></label><?php endforeach; ?><p class="description"><?php echo esc_html__( 'For per-object original content languages, each item exports all selected website languages except that item’s own original language.', 'taka-platform' ); ?></p></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Original language behavior', 'taka-platform' ); ?></th><td><label><input type="checkbox" name="use_object_source_languages" value="1" checked> <?php echo esc_html__( 'Use each object’s original content language', 'taka-platform' ); ?></label><p class="description"><?php echo esc_html__( 'Disable to export every item as if it used the fallback original content language selected above.', 'taka-platform' ); ?></p></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Package options', 'taka-platform' ); ?></th><td>
						<p><label><input type="checkbox" name="only_missing_translations" value="1" checked> <?php echo esc_html__( 'Only missing website translations', 'taka-platform' ); ?></label></p>
						<p><label><input type="checkbox" name="only_changed_source_texts" value="1" checked> <?php echo esc_html__( 'Only changed original texts', 'taka-platform' ); ?></label></p>
						<p><label><input type="checkbox" name="include_existing_translations" value="1"> <?php echo esc_html__( 'Include existing website translations', 'taka-platform' ); ?></label></p>
						<p><label><input type="checkbox" name="include_context" value="1" checked> <?php echo esc_html__( 'Include context', 'taka-platform' ); ?></label></p>
						<p><label><input type="checkbox" name="include_glossary" value="1" checked> <?php echo esc_html__( 'Include glossary', 'taka-platform' ); ?></label></p>
						<p><label><input type="checkbox" name="include_html" value="1" checked> <?php echo esc_html__( 'Include HTML', 'taka-platform' ); ?></label></p>
					</td></tr>
				</tbody></table>
				<?php submit_button( __( 'Export website translation package', 'taka-platform' ) ); ?>
			</form>
			<h2><?php echo esc_html__( 'Import website translation package', 'taka-platform' ); ?></h2>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="taka_platform_import_translation_package">
				<?php wp_nonce_field( 'taka_platform_import_translation_package', self::NONCE ); ?>
				<table class="form-table" role="presentation"><tbody>
					<tr><th scope="row"><?php echo esc_html__( 'JSON file', 'taka-platform' ); ?></th><td><input type="file" name="translation_package_file" accept="application/json,.json"></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Paste JSON', 'taka-platform' ); ?></th><td><textarea class="large-text code" rows="10" name="translation_package_json"></textarea></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Import options', 'taka-platform' ); ?></th><td><p><label><input type="checkbox" name="overwrite_existing" value="1"> <?php echo esc_html__( 'Overwrite existing website translations', 'taka-platform' ); ?></label></p><p><label><input type="checkbox" name="allow_changed_source" value="1"> <?php echo esc_html__( 'Import even if original text changed', 'taka-platform' ); ?></label></p></td></tr>
				</tbody></table>
				<?php submit_button( __( 'Import website translation package', 'taka-platform' ) ); ?>
			</form>
			<h2><?php echo esc_html__( 'Website translation glossary', 'taka-platform' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="taka_platform_save_translation_glossary">
				<?php wp_nonce_field( 'taka_platform_save_translation_glossary', self::NONCE ); ?>
				<table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Term', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Note', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Translate term', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Preferred website translations', 'taka-platform' ); ?></th></tr></thead><tbody>
					<?php foreach ( array_merge( TAKA_Platform_Translation_Packages::get_glossary(), array( array() ) ) as $index => $entry ) : ?>
						<tr><td><input class="regular-text" type="text" name="glossary[<?php echo esc_attr( (string) $index ); ?>][term]" value="<?php echo esc_attr( $entry['term'] ?? '' ); ?>"></td><td><textarea name="glossary[<?php echo esc_attr( (string) $index ); ?>][note]" rows="2"><?php echo esc_textarea( $entry['note'] ?? '' ); ?></textarea></td><td><label><input type="checkbox" name="glossary[<?php echo esc_attr( (string) $index ); ?>][translate]" value="1" <?php checked( ! empty( $entry['translate'] ) ); ?>> <?php echo esc_html__( 'Yes', 'taka-platform' ); ?></label></td><td><textarea name="glossary[<?php echo esc_attr( (string) $index ); ?>][preferred_translations]" rows="2"><?php echo esc_textarea( implode( "\n", (array) ( $entry['preferred_translations'] ?? array() ) ) ); ?></textarea></td></tr>
					<?php endforeach; ?>
				</tbody></table>
				<?php submit_button( __( 'Save glossary', 'taka-platform' ) ); ?>
			</form>
			<h2><?php echo esc_html__( 'Static website translation audit', 'taka-platform' ); ?></h2>
			<table class="widefat striped" style="margin-top:16px;"><thead><tr><th><?php echo esc_html__( 'Website language', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Keys', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Missing keys', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Extra keys', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Fallback-used keys', 'taka-platform' ); ?></th></tr></thead><tbody>
			<?php foreach ( $audit['languages'] as $lang => $row ) : ?>
				<tr><td><strong><?php echo esc_html( strtoupper( $lang ) ); ?></strong></td><td><?php echo esc_html( (string) $row['count'] ); ?></td><td><?php echo empty( $row['missing'] ) ? esc_html__( 'Complete', 'taka-platform' ) : '<code>' . esc_html( implode( ', ', $row['missing'] ) ) . '</code>'; ?></td><td><?php echo empty( $row['extra'] ) ? '—' : '<code>' . esc_html( implode( ', ', $row['extra'] ) ) . '</code>'; ?></td><td><?php echo empty( $row['fallback_used'] ) ? '—' : esc_html( (string) count( $row['fallback_used'] ) ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<h2><?php echo esc_html__( 'Dynamic website translation workflow', 'taka-platform' ); ?></h2>
			<p><?php echo esc_html__( 'Dynamic content fields can store one original text plus website translations. The current manual translation provider fills missing website translations from configured fallback content; external AI providers can hook into taka_platform_translate_text later.', 'taka-platform' ); ?></p>
		</div>
		<?php
	}

	/** Render import summary without debug-style output. */
	private static function render_import_summary( $summary ) {
		if ( ! is_array( $summary ) || empty( $summary ) ) { return; }
		echo '<ul>';
		foreach ( $summary as $key => $value ) {
			if ( is_array( $value ) ) {
				$parts = array();
				foreach ( $value as $sub_key => $sub_value ) {
					$parts[] = sanitize_key( $sub_key ) . ': ' . ( is_scalar( $sub_value ) ? (string) $sub_value : wp_json_encode( $sub_value ) );
				}
				$value = implode( ', ', $parts );
			}
			echo '<li><strong>' . esc_html( sanitize_key( $key ) ) . ':</strong> ' . esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ) . '</li>';
		}
		echo '</ul>';
	}

	/** Render Events Manager export integration page. */
	public static function render_events_manager_integration() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$lang = taka_tour_current_language();
		$rest_url = rest_url( TAKA_Platform_Events_Manager_Integration::REST_NAMESPACE . TAKA_Platform_Events_Manager_Integration::REST_ROUTE );
		$providers = TAKA_Platform_Events_Manager_Integration::providers();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Integrations / Events Manager', 'taka-platform' ); ?></h1>
			<p><?php echo esc_html__( 'TAKA Platform remains the source of truth. These exports provide normalized event data for Events Manager and other external tools without deleting or syncing external events automatically.', 'taka-platform' ); ?></p>

			<h2><?php echo esc_html__( 'REST event feed', 'taka-platform' ); ?></h2>
			<p><code><?php echo esc_html( $rest_url ); ?></code></p>
			<p class="description"><?php echo esc_html__( 'Optional query parameter: ?lang=de, en, fr, nl, lb, fi or ja.', 'taka-platform' ); ?></p>

			<h2><?php echo esc_html__( 'Export formats', 'taka-platform' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php echo esc_html__( 'Format', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Action', 'taka-platform' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $providers as $provider ) : ?>
						<tr>
							<td><?php echo esc_html( $provider->label() ); ?></td>
							<td><a class="button" href="<?php echo esc_url( TAKA_Platform_Events_Manager_Integration::export_url( $provider->key(), $lang ) ); ?>"><?php echo esc_html__( 'Download', 'taka-platform' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php echo esc_html__( 'Events Manager mapping', 'taka-platform' ); ?></h2>
			<ul>
				<li><?php echo esc_html__( 'Event title maps to event_name and post_title.', 'taka-platform' ); ?></li>
				<li><?php echo esc_html__( 'Description maps to post_content.', 'taka-platform' ); ?></li>
				<li><?php echo esc_html__( 'First and last program items provide start and end date/time.', 'taka-platform' ); ?></li>
				<li><?php echo esc_html__( 'Venue fields map to Events Manager location columns.', 'taka-platform' ); ?></li>
				<li><?php echo esc_html__( 'Ticket URL, organizers and TAKA event ID are exported as custom attributes/fields.', 'taka-platform' ); ?></li>
			</ul>
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

	/** Export a provider-independent TAKA Translation Package JSON file. */
	public static function handle_export_translation_package() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) ); }
		check_admin_referer( 'taka_platform_export_translation_package', self::NONCE );
		$source_language = TAKA_Platform_Translation_Packages::sanitize_language( wp_unslash( $_POST['source_language'] ?? 'de' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$targets = isset( $_POST['target_languages'] ) && is_array( $_POST['target_languages'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['target_languages'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$package = TAKA_Platform_Translation_Packages::build_package( array(
			'source_language' => $source_language,
			'target_languages' => $targets,
			'use_object_source_languages' => ! empty( $_POST['use_object_source_languages'] ),
			'only_missing_translations' => ! empty( $_POST['only_missing_translations'] ),
			'only_changed_source_texts' => ! empty( $_POST['only_changed_source_texts'] ),
			'include_existing_translations' => ! empty( $_POST['include_existing_translations'] ),
			'include_context' => ! empty( $_POST['include_context'] ),
			'include_glossary' => ! empty( $_POST['include_glossary'] ),
			'include_html' => ! empty( $_POST['include_html'] ),
		) );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . TAKA_Platform_Translation_Packages::filename( $source_language, $targets ) . '"' );
		echo wp_json_encode( $package, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/** Import a provider-independent TAKA Translation Package. */
	public static function handle_import_translation_package() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) ); }
		check_admin_referer( 'taka_platform_import_translation_package', self::NONCE );
		$json = '';
		if ( ! empty( $_FILES['translation_package_file']['tmp_name'] ) && is_uploaded_file( $_FILES['translation_package_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$json = file_get_contents( $_FILES['translation_package_file']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
		if ( '' === trim( (string) $json ) && isset( $_POST['translation_package_json'] ) ) {
			$json = wp_unslash( $_POST['translation_package_json'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		$package = TAKA_Platform_Translation_Packages::decode_json( $json );
		$result = is_array( $package ) ? TAKA_Platform_Translation_Packages::import_package( $package, array( 'overwrite_existing' => ! empty( $_POST['overwrite_existing'] ), 'allow_changed_source' => ! empty( $_POST['allow_changed_source'] ) ) ) : array( 'imported' => 0, 'skipped_existing' => 0, 'skipped_changed_source' => 0, 'errors' => array( 'Invalid JSON package.' ), 'warnings' => array() );
		set_transient( 'taka_platform_translation_import_result', $result, 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=taka-platform-translations' ) );
		exit;
	}

	/** Save editable translation glossary entries. */
	public static function handle_save_translation_glossary() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) ); }
		check_admin_referer( 'taka_platform_save_translation_glossary', self::NONCE );
		$posted = isset( $_POST['glossary'] ) && is_array( $_POST['glossary'] ) ? wp_unslash( $_POST['glossary'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		update_option( TAKA_Platform_Translation_Packages::GLOSSARY_OPTION, TAKA_Platform_Translation_Packages::sanitize_glossary( $posted ), false );
		wp_safe_redirect( add_query_arg( 'glossary_saved', '1', admin_url( 'admin.php?page=taka-platform-translations' ) ) );
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
		$hero = TAKA_Platform_Data::get_hero_settings( false );
		$booking = TAKA_Platform_Data::get_booking_information_settings();
		$tickets = TAKA_Platform_Data::get_ticket_section_settings( null, false );
		$option_lists = TAKA_Platform_Data::get_option_lists( true );
		$positions = array( 'left' => __( 'Left', 'taka-platform' ), 'center' => __( 'Center', 'taka-platform' ), 'right' => __( 'Right', 'taka-platform' ) );
		$verticals = array( 'top' => __( 'Top', 'taka-platform' ), 'center' => __( 'Center', 'taka-platform' ), 'bottom' => __( 'Bottom', 'taka-platform' ) );
		$location_modes = array( 'list' => __( 'List', 'taka-platform' ), 'flags' => __( 'Flags', 'taka-platform' ), 'route_map' => __( 'Map view', 'taka-platform' ), 'route_map_with_list' => __( 'Map with list', 'taka-platform' ) );
		$language_options = TAKA_Platform_Translation_Packages::language_labels();
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
					<?php self::settings_select_row( 'hero[source_language]', __( 'Original content language', 'taka-platform' ), $hero['source_language'] ?? 'de', $language_options, array( 'data-taka-source-language-select' => '1' ) ); ?>
					<?php self::settings_source_text_row( 'hero[kicker]', __( 'Hero kicker', 'taka-platform' ), $hero['kicker'] ?? '', $hero['source_language'] ?? 'de' ); ?>
					<?php self::settings_source_text_row( 'hero[title]', __( 'Hero title', 'taka-platform' ), $hero['title'] ?? '', $hero['source_language'] ?? 'de' ); ?>
					<?php self::settings_source_textarea_row( 'hero[description]', __( 'Hero subtitle / description', 'taka-platform' ), $hero['description'] ?? '', $hero['source_language'] ?? 'de' ); ?>
					<?php self::settings_source_text_row( 'hero[primary_button_label]', __( 'Primary button label', 'taka-platform' ), $hero['primary_button_label'] ?? '', $hero['source_language'] ?? 'de' ); ?>
					<?php self::settings_text_row( 'hero[primary_button_target]', __( 'Primary button target', 'taka-platform' ), $hero['primary_button_target'] ?? '' ); ?>
					<?php self::settings_source_text_row( 'hero[secondary_button_label]', __( 'Secondary button label', 'taka-platform' ), $hero['secondary_button_label'] ?? '', $hero['source_language'] ?? 'de' ); ?>
					<?php self::settings_text_row( 'hero[secondary_button_target]', __( 'Secondary button target', 'taka-platform' ), $hero['secondary_button_target'] ?? '' ); ?>
					<?php self::settings_media_row( 'hero[image_id]', 'hero[image_url]', 'taka_platform_hero_image', __( 'Hero image', 'taka-platform' ), absint( $hero['image_id'] ?? 0 ), (string) ( $hero['image_url'] ?? '' ) ); ?>
					<?php self::settings_text_row( 'hero[overlay_strength]', __( 'Hero overlay strength (0–1)', 'taka-platform' ), $hero['overlay_strength'] ?? '0.78' ); ?>
					<tr><th scope="row"><?php echo esc_html__( 'Hero text box', 'taka-platform' ); ?></th><td><label><input type="checkbox" name="hero[text_box_enabled]" value="1" <?php checked( (string) ( $hero['text_box_enabled'] ?? '1' ), '1' ); ?>> <?php echo esc_html__( 'Show readable text box', 'taka-platform' ); ?></label></td></tr>
					<?php self::settings_text_row( 'hero[text_box_opacity]', __( 'Text box opacity (0–1)', 'taka-platform' ), $hero['text_box_opacity'] ?? '0.72' ); ?>
					<?php self::settings_text_row( 'hero[text_box_max_width]', __( 'Text box max width', 'taka-platform' ), $hero['text_box_max_width'] ?? '620px' ); ?>
					<?php self::settings_select_row( 'hero[text_position]', __( 'Hero text position', 'taka-platform' ), $hero['text_position'] ?? 'left', $positions ); ?>
					<?php self::settings_select_row( 'hero[vertical_alignment]', __( 'Hero vertical alignment', 'taka-platform' ), $hero['vertical_alignment'] ?? 'center', $verticals ); ?>
					<?php self::settings_select_row( 'hero[location_display_mode]', __( 'Hero location display mode', 'taka-platform' ), $hero['location_display_mode'] ?? 'route_map_with_list', $location_modes ); ?>
					<tr><th scope="row"><?php echo esc_html__( 'Route CTA station', 'taka-platform' ); ?></th><td><label><input type="checkbox" name="hero[route_cta_enabled]" value="1" <?php checked( (string) ( $hero['route_cta_enabled'] ?? '1' ), '1' ); ?>> <?php echo esc_html__( 'Show final “become a host” station on the route map', 'taka-platform' ); ?></label></td></tr>
					<?php self::settings_multilingual_text_row( 'hero[route_cta_label]', __( 'Route CTA label', 'taka-platform' ), $hero['route_cta_label'] ?? '', $hero['source_language'] ?? 'de' ); ?>
					<?php self::settings_multilingual_text_row( 'hero[route_cta_sublabel]', __( 'Route CTA sublabel', 'taka-platform' ), $hero['route_cta_sublabel'] ?? '', $hero['source_language'] ?? 'de' ); ?>
					<?php self::settings_text_row( 'hero[route_cta_target]', __( 'Route CTA target', 'taka-platform' ), $hero['route_cta_target'] ?? '#become-a-host' ); ?>
					<?php self::settings_text_row( 'hero[route_cta_context]', __( 'Route CTA context / year', 'taka-platform' ), $hero['route_cta_context'] ?? '2027' ); ?>
				</tbody></table>
				<?php submit_button( __( 'Save hero settings', 'taka-platform' ) ); ?>
			</form>
			<h2><?php echo esc_html__( 'Ticket section', 'taka-platform' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="taka_platform_save_ticket_section">
				<?php wp_nonce_field( TAKA_Platform_Data::TICKETS_OPTION, self::NONCE ); ?>
				<table class="form-table" role="presentation"><tbody>
					<?php self::settings_select_row( 'tickets[source_language]', __( 'Original content language', 'taka-platform' ), $tickets['source_language'] ?? 'de', $language_options, array( 'data-taka-source-language-select' => '1' ) ); ?>
					<?php self::settings_multilingual_text_row( 'tickets[kicker]', __( 'Ticket section kicker', 'taka-platform' ), $tickets['kicker'] ?? '', $tickets['source_language'] ?? 'de' ); ?>
					<?php self::settings_multilingual_text_row( 'tickets[heading]', __( 'Ticket section heading', 'taka-platform' ), $tickets['heading'] ?? '', $tickets['source_language'] ?? 'de' ); ?>
					<?php self::settings_multilingual_textarea_row( 'tickets[intro]', __( 'Ticket section intro text', 'taka-platform' ), $tickets['intro'] ?? '', $tickets['source_language'] ?? 'de' ); ?>
					<tr><th scope="row"><?php echo esc_html__( 'Seminar overview section', 'taka-platform' ); ?></th><td><label><input type="checkbox" name="tickets[show_seminar_overview]" value="1" <?php checked( (string) ( $tickets['show_seminar_overview'] ?? '0' ), '1' ); ?>> <?php echo esc_html__( 'Show the legacy Seminars in Europe overview on the homepage', 'taka-platform' ); ?></label><p class="description"><?php echo esc_html__( 'Disabled by default because the tabbed ticket section is now the primary event selector.', 'taka-platform' ); ?></p></td></tr>
				</tbody></table>
				<?php submit_button( __( 'Save ticket section', 'taka-platform' ) ); ?>
			</form>
			<h2><?php echo esc_html__( 'Option lists', 'taka-platform' ); ?></h2>
			<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=taka-platform-option-lists' ) ); ?>"><?php echo esc_html__( 'Open option lists', 'taka-platform' ); ?></a></p>
			<h2><?php echo esc_html__( 'Booking Information', 'taka-platform' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="taka_platform_save_booking_information">
				<?php wp_nonce_field( TAKA_Platform_Data::BOOKING_OPTION, self::NONCE ); ?>
				<table class="form-table" role="presentation"><tbody>
					<tr><th scope="row"><?php echo esc_html__( 'Section enabled', 'taka-platform' ); ?></th><td><label><input type="checkbox" name="booking_info[enabled]" value="1" <?php checked( (string) ( $booking['enabled'] ?? '1' ), '1' ); ?>> <?php echo esc_html__( 'Show Before you book section near tickets', 'taka-platform' ); ?></label></td></tr>
					<?php self::settings_select_row( 'booking_info[source_language]', __( 'Original content language', 'taka-platform' ), $booking['source_language'] ?? 'de', $language_options, array( 'data-taka-source-language-select' => '1' ) ); ?>
					<?php self::settings_multilingual_text_row( 'booking_info[title]', __( 'Title', 'taka-platform' ), $booking['title'] ?? '', $booking['source_language'] ?? 'de' ); ?>
					<?php self::settings_multilingual_textarea_row( 'booking_info[intro]', __( 'Intro text', 'taka-platform' ), $booking['intro'] ?? '', $booking['source_language'] ?? 'de' ); ?>
					<?php self::settings_multilingual_textarea_row( 'booking_info[group_booking]', __( 'Group booking text', 'taka-platform' ), $booking['group_booking'] ?? '', $booking['source_language'] ?? 'de' ); ?>
					<?php self::settings_multilingual_textarea_row( 'booking_info[multi_event_discount]', __( 'Multi-event discount text', 'taka-platform' ), $booking['multi_event_discount'] ?? '', $booking['source_language'] ?? 'de' ); ?>
					<?php self::settings_text_row( 'booking_info[contact_email]', __( 'Contact email', 'taka-platform' ), $booking['contact_email'] ?? '' ); ?>
					<?php self::settings_multilingual_textarea_row( 'booking_info[booking_process]', __( 'Booking process text', 'taka-platform' ), $booking['booking_process'] ?? '', $booking['source_language'] ?? 'de' ); ?>
					<?php self::settings_multilingual_textarea_row( 'booking_info[payment_methods]', __( 'Payment methods', 'taka-platform' ), $booking['payment_methods'] ?? '', $booking['source_language'] ?? 'de' ); ?>
					<?php self::settings_multilingual_textarea_row( 'booking_info[cancellation_policy]', __( 'Cancellation policy text', 'taka-platform' ), $booking['cancellation_policy'] ?? '', $booking['source_language'] ?? 'de' ); ?>
					<?php self::settings_multilingual_textarea_row( 'booking_info[additional_notes]', __( 'Additional notes', 'taka-platform' ), $booking['additional_notes'] ?? '', $booking['source_language'] ?? 'de' ); ?>
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
		$translations = TAKA_Platform_Data::normalize_content_section( $section )['translations'] ?? array();
		$label_title = self::content_section_admin_translation_value( $translations, 'title' );
		$label = $is_new ? __( 'New section', 'taka-platform' ) : ( $label_title ?: $key );
		?>
		<div class="postbox taka-content-section-editor" style="padding:1rem;max-width:1080px;" data-taka-content-section-editor data-taka-source-language-scope>
			<h2><?php echo esc_html( $label ); ?></h2>
			<table class="form-table" role="presentation"><tbody>
				<?php self::settings_text_row( 'sections[' . $key . '][key]', __( 'Internal key / slug', 'taka-platform' ), $section['key'] ?? $key ); ?>
				<tr><th scope="row"><?php echo esc_html__( 'Enabled', 'taka-platform' ); ?></th><td><label><input type="checkbox" name="sections[<?php echo esc_attr( $key ); ?>][visible]" value="1" <?php checked( (string) ( $section['visible'] ?? '1' ), '1' ); ?>> <?php echo esc_html__( 'Show section', 'taka-platform' ); ?></label><?php if ( ! $is_new ) : ?><br><label><input type="checkbox" name="sections[<?php echo esc_attr( $key ); ?>][delete]" value="1"> <?php echo esc_html__( 'Delete section', 'taka-platform' ); ?></label><?php endif; ?></td></tr>
				<?php self::settings_select_row( 'sections[' . $key . '][source_language]', __( 'Original content language', 'taka-platform' ), $section['source_language'] ?? 'de', TAKA_Platform_Translation_Packages::language_labels(), array( 'data-taka-source-language-select' => '1' ) ); ?>
				<?php self::settings_text_row( 'sections[' . $key . '][sort_order]', __( 'Sort order', 'taka-platform' ), $section['sort_order'] ?? 0 ); ?>
				<tr class="taka-content-section-translations-row"><th scope="row"><?php echo esc_html__( 'Original text and website translations', 'taka-platform' ); ?></th><td><?php self::render_content_section_translation_tabs( $key, $translations, $section['source_language'] ?? 'de' ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html__( 'Reusable content', 'taka-platform' ); ?></th><td><?php self::render_content_section_reference_fields( $key, $section['content_reference'] ?? array(), $section['source_language'] ?? 'de' ); ?></td></tr>
				<?php self::settings_media_row( 'sections[' . $key . '][image_id]', 'sections[' . $key . '][image_url]', 'taka_section_' . $key . '_image', __( 'Main image', 'taka-platform' ), absint( $section['image_id'] ?? 0 ), (string) ( $section['image_url'] ?? '' ) ); ?>
				<?php self::settings_media_row( 'sections[' . $key . '][secondary_image_id]', 'sections[' . $key . '][secondary_image_url]', 'taka_section_' . $key . '_secondary_image', __( 'Secondary image', 'taka-platform' ), absint( $section['secondary_image_id'] ?? 0 ), (string) ( $section['secondary_image_url'] ?? '' ) ); ?>
				<?php self::settings_media_row( 'sections[' . $key . '][gallery_image_ids]', 'sections[' . $key . '][gallery_image_urls]', 'taka_section_' . $key . '_gallery', __( 'Gallery', 'taka-platform' ), $section['gallery_image_ids'] ?? array(), implode( "\n", (array) ( $section['gallery_image_urls'] ?? array() ) ), true ); ?>
				<?php self::settings_select_row( 'sections[' . $key . '][layout]', __( 'Layout', 'taka-platform' ), $section['layout'] ?? 'text_only', $layouts ); ?>
				<?php self::settings_select_row( 'sections[' . $key . '][background_style]', __( 'Background style', 'taka-platform' ), $section['background_style'] ?? 'plain', $backgrounds ); ?>
				<?php self::settings_select_row( 'sections[' . $key . '][image_fit]', __( 'Image fit', 'taka-platform' ), $section['image_fit'] ?? 'contain', $fits ); ?>
				<?php self::settings_select_row( 'sections[' . $key . '][image_position]', __( 'Image focus / position', 'taka-platform' ), $section['image_position'] ?? 'center center', $positions ); ?>
				<?php self::settings_text_row( 'sections[' . $key . '][css_class]', __( 'CSS modifier/class', 'taka-platform' ), $section['css_class'] ?? '' ); ?>
			</tbody></table>
		</div>
		<?php
	}

	/** Render language tabs for structured content-section translation data. */
	private static function render_content_section_translation_tabs( $key, $translations, $source_language = 'de' ) {
		$languages = self::content_section_language_labels();
		$source_language = TAKA_Platform_Translation_Packages::sanitize_language( $source_language );
		$default_lang = isset( $languages[ $source_language ] ) ? $source_language : TAKA_Platform_Data::default_content_section_language();
		$fields = array(
			'kicker' => array( 'label' => __( 'Kicker', 'taka-platform' ), 'textarea' => false ),
			'title' => array( 'label' => __( 'Title', 'taka-platform' ), 'textarea' => false ),
			'subtitle' => array( 'label' => __( 'Subtitle', 'taka-platform' ), 'textarea' => false ),
			'body' => array( 'label' => __( 'Body', 'taka-platform' ), 'textarea' => true ),
			'button_label' => array( 'label' => __( 'Button label', 'taka-platform' ), 'textarea' => false ),
			'button_url' => array( 'label' => __( 'Button URL', 'taka-platform' ), 'textarea' => false, 'type' => 'url' ),
		);
		$tab_group = 'taka_section_language_' . $key;
		?>
		<div class="taka-content-section-translations" data-taka-content-section-translations data-taka-source-aware data-source-language="<?php echo esc_attr( $default_lang ); ?>" data-source-mode="editable" data-default-lang="<?php echo esc_attr( $default_lang ); ?>">
			<p class="description"><?php echo esc_html__( 'Enter original content in the original-language tab. Other tabs are website translations for the same fields.', 'taka-platform' ); ?></p>
			<p><button type="button" class="button" data-taka-copy-default-translations><?php echo esc_html__( 'Copy original text to empty website translations', 'taka-platform' ); ?></button></p>
			<div class="taka-content-section-tabs">
				<?php foreach ( $languages as $lang => $label ) : ?>
					<?php $is_source_language = $lang === $default_lang; ?>
					<input class="taka-content-section-tabs__radio" type="radio" name="<?php echo esc_attr( $tab_group ); ?>" id="<?php echo esc_attr( $tab_group . '_' . $lang ); ?>" <?php checked( $lang, $default_lang ); ?>>
					<label class="taka-content-section-tabs__tab" for="<?php echo esc_attr( $tab_group . '_' . $lang ); ?>" data-taka-language-tab data-taka-i18n-lang="<?php echo esc_attr( $lang ); ?>" data-language-label="<?php echo esc_attr( $label ); ?>"><?php echo esc_html( $is_source_language ? sprintf( __( '%s original', 'taka-platform' ), $label ) : $label ); ?></label>
					<div class="taka-content-section-tabs__panel" data-taka-language-panel data-taka-i18n-lang="<?php echo esc_attr( $lang ); ?>">
						<?php if ( $is_source_language ) : ?>
							<p class="description" data-taka-source-panel-help><?php echo esc_html__( 'This is the original content language. Edit the original text here. Website translations are entered in the other language tabs.', 'taka-platform' ); ?></p>
						<?php else : ?>
							<p class="description" data-taka-source-panel-help><?php echo esc_html__( 'Enter the website translation for this language based on the original text.', 'taka-platform' ); ?></p>
						<?php endif; ?>
						<?php foreach ( $fields as $field => $settings ) : ?>
							<?php
							$name = 'sections[' . $key . '][translations][' . $lang . '][' . $field . ']';
							$value = $translations[ $lang ][ $field ] ?? '';
							$is_textarea = ! empty( $settings['textarea'] );
							$type = $settings['type'] ?? 'text';
							?>
							<p class="taka-content-section-tabs__field">
								<label><strong data-taka-language-field-label data-source-label="<?php echo esc_attr( self::source_text_label( $settings['label'] ) ); ?>" data-translation-label="<?php echo esc_attr( self::translation_text_label( $settings['label'] ) ); ?>"><?php echo esc_html( $is_source_language ? self::source_text_label( $settings['label'] ) : self::translation_text_label( $settings['label'] ) ); ?></strong><br>
									<?php if ( $is_textarea ) : ?>
										<textarea class="large-text" rows="5" name="<?php echo esc_attr( $name ); ?>" data-taka-i18n-lang="<?php echo esc_attr( $lang ); ?>" data-taka-i18n-field="<?php echo esc_attr( $field ); ?>"><?php echo esc_textarea( (string) $value ); ?></textarea>
									<?php else : ?>
										<input class="regular-text" type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" data-taka-i18n-lang="<?php echo esc_attr( $lang ); ?>" data-taka-i18n-field="<?php echo esc_attr( $field ); ?>">
									<?php endif; ?>
								</label>
							</p>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/** Save editable hero settings. */
	public static function handle_save_hero() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) ); }
		check_admin_referer( TAKA_Platform_Data::HERO_OPTION, self::NONCE );
		$posted = isset( $_POST['hero'] ) && is_array( $_POST['hero'] ) ? wp_unslash( $_POST['hero'] ) : array();
		$current = TAKA_Platform_Data::get_hero_settings( false );
		$source_language = TAKA_Platform_Translation_Packages::sanitize_language( $posted['source_language'] ?? ( $current['source_language'] ?? 'de' ) );
		$clean  = array(
			'kicker'                 => self::sanitize_dynamic_source_text( $posted['kicker'] ?? '', $current['kicker'] ?? '', $source_language, false ),
			'source_language'        => $source_language,
			'title'                  => self::sanitize_dynamic_source_text( $posted['title'] ?? '', $current['title'] ?? '', $source_language, false ),
			'description'            => self::sanitize_dynamic_source_text( $posted['description'] ?? '', $current['description'] ?? '', $source_language, true ),
			'primary_button_label'   => self::sanitize_dynamic_source_text( $posted['primary_button_label'] ?? '', $current['primary_button_label'] ?? '', $source_language, false ),
			'primary_button_target'  => sanitize_text_field( $posted['primary_button_target'] ?? '' ),
			'secondary_button_label' => self::sanitize_dynamic_source_text( $posted['secondary_button_label'] ?? '', $current['secondary_button_label'] ?? '', $source_language, false ),
			'secondary_button_target'=> sanitize_text_field( $posted['secondary_button_target'] ?? '' ),
			'image_id'               => absint( $posted['image_id'] ?? 0 ),
			'image_url'              => esc_url_raw( $posted['image_url'] ?? '' ),
			'overlay_strength'       => self::sanitize_decimal( $posted['overlay_strength'] ?? '0.78', '0.78' ),
			'text_box_enabled'       => ! empty( $posted['text_box_enabled'] ) ? '1' : '0',
			'text_box_opacity'       => self::sanitize_decimal( $posted['text_box_opacity'] ?? '0.72', '0.72' ),
			'text_box_max_width'     => sanitize_text_field( $posted['text_box_max_width'] ?? '620px' ),
			'text_position'          => in_array( $posted['text_position'] ?? 'left', array( 'left', 'center', 'right' ), true ) ? sanitize_key( $posted['text_position'] ) : 'left',
			'vertical_alignment'     => in_array( $posted['vertical_alignment'] ?? 'center', array( 'top', 'center', 'bottom' ), true ) ? sanitize_key( $posted['vertical_alignment'] ) : 'center',
			'location_display_mode'  => TAKA_Platform_Data::normalize_hero_location_display_mode( $posted['location_display_mode'] ?? 'route_map_with_list' ),
			'route_cta_enabled'      => ! empty( $posted['route_cta_enabled'] ) ? '1' : '0',
			'route_cta_label'        => self::sanitize_dynamic_text( $posted['route_cta_label'] ?? ( $current['route_cta_label'] ?? '' ), false ),
			'route_cta_sublabel'     => self::sanitize_dynamic_text( $posted['route_cta_sublabel'] ?? ( $current['route_cta_sublabel'] ?? '' ), false ),
			'route_cta_target'       => sanitize_text_field( $posted['route_cta_target'] ?? ( $current['route_cta_target'] ?? '#become-a-host' ) ),
			'route_cta_context'      => sanitize_text_field( $posted['route_cta_context'] ?? ( $current['route_cta_context'] ?? '2027' ) ),
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
		$translations = self::sanitize_content_section_translations( $item );
		$default_lang = TAKA_Platform_Data::default_content_section_language();
		$default = $translations[ $default_lang ] ?? array();
		$body = self::content_section_admin_translation_value( $translations, 'body' );
		$button_label = self::content_section_admin_translation_value( $translations, 'button_label' );
		$button_url = self::content_section_admin_translation_value( $translations, 'button_url' );
		$normalized = TAKA_Platform_Data::normalize_content_section( $item );
		return array(
			'visible'             => ! empty( $item['visible'] ) ? '1' : '0',
			'source_language'     => TAKA_Platform_Translation_Packages::sanitize_language( $item['source_language'] ?? 'de' ),
			'sort_order'          => (int) ( $item['sort_order'] ?? 0 ),
			'kicker'              => $default['kicker'] ?? '',
			'title'               => $default['title'] ?? '',
			'subtitle'            => $default['subtitle'] ?? '',
			'body'                => $body,
			'text'                => $body,
			'translations'        => $translations,
			'content_reference'   => $normalized['content_reference'] ?? TAKA_Platform_Data::normalize_content_reference( $item['content_reference'] ?? array(), 'homepage_section' ),
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
			'button_url'          => esc_url_raw( $button_url ),
			'button_label'        => $button_label,
			'css_class'           => sanitize_html_class( $item['css_class'] ?? '' ),
		);
	}

	/** Sanitize structured content-section translations, preserving legacy scalar input. */
	private static function sanitize_content_section_translations( $item ) {
		$normalized = TAKA_Platform_Data::normalize_content_section( $item );
		$translations = $normalized['translations'] ?? array();
		$clean = array();
		$fields = array( 'kicker', 'title', 'subtitle', 'body', 'button_label', 'button_url' );
		foreach ( TAKA_Platform_Data::content_section_languages() as $lang ) {
			foreach ( $fields as $field ) {
				$value = $translations[ $lang ][ $field ] ?? '';
				$clean[ $lang ][ $field ] = 'button_url' === $field ? esc_url_raw( $value ) : sanitize_textarea_field( $value );
			}
		}
		return $clean;
	}


	/** Save ticket section heading/settings. */
	public static function handle_save_ticket_section() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) ); }
		check_admin_referer( TAKA_Platform_Data::TICKETS_OPTION, self::NONCE );
		$posted = isset( $_POST['tickets'] ) && is_array( $_POST['tickets'] ) ? wp_unslash( $_POST['tickets'] ) : array();
		$clean = array(
			'source_language' => TAKA_Platform_Translation_Packages::sanitize_language( $posted['source_language'] ?? 'de' ),
			'kicker' => self::sanitize_dynamic_text( $posted['kicker'] ?? '', false ),
			'heading' => self::sanitize_dynamic_text( $posted['heading'] ?? '', false ),
			'intro' => self::sanitize_dynamic_text( $posted['intro'] ?? '', true ),
			'show_seminar_overview' => ! empty( $posted['show_seminar_overview'] ) ? '1' : '0',
		);
		update_option( TAKA_Platform_Data::TICKETS_OPTION, $clean, false );
		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=taka-tour-settings' ) ) );
		exit;
	}

	/** Save configurable option lists. */
	public static function handle_save_option_lists() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) ); }
		check_admin_referer( TAKA_Platform_Data::OPTION_LISTS_OPTION, self::NONCE );
		$posted = isset( $_POST['option_lists'] ) && is_array( $_POST['option_lists'] ) ? wp_unslash( $_POST['option_lists'] ) : array();
		update_option( TAKA_Platform_Data::OPTION_LISTS_OPTION, TAKA_Platform_Data::normalize_option_lists( $posted, true ), false );
		$page = 'taka-platform-option-lists' === sanitize_key( $_POST['redirect_to'] ?? '' ) ? 'taka-platform-option-lists' : 'taka-tour-settings';
		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=' . $page ) ) );
		exit;
	}

	/** Import configurable option lists from JSON. */
	public static function handle_import_option_lists() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) ); }
		check_admin_referer( TAKA_Platform_Data::OPTION_LISTS_OPTION, self::NONCE );
		$json = isset( $_POST['option_lists_json'] ) ? wp_unslash( $_POST['option_lists_json'] ) : '';
		$decoded = json_decode( (string) $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			wp_safe_redirect( add_query_arg( 'option_lists_error', rawurlencode( __( 'Invalid option list JSON.', 'taka-platform' ) ), admin_url( 'admin.php?page=taka-platform-option-lists' ) ) );
			exit;
		}
		$current = TAKA_Platform_Data::get_option_lists( true );
		update_option( TAKA_Platform_Data::OPTION_LISTS_OPTION, TAKA_Platform_Data::merge_option_lists( $current, $decoded ), false );
		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=taka-platform-option-lists' ) ) );
		exit;
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
				return new WP_Error( 'taka_tour_missing_upload', __( 'No JSON config file uploaded.', 'taka-platform' ) );
			}
			$name = sanitize_file_name( $_FILES['config_file']['name'] ?? '' );
			if ( 'json' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
				return new WP_Error( 'taka_tour_invalid_upload', __( 'Uploaded config must be a JSON file.', 'taka-platform' ) );
			}
			$type = function_exists( 'wp_check_filetype' ) ? wp_check_filetype( $name, array( 'json' => 'application/json' ) ) : array( 'ext' => 'json' );
			if ( 'json' !== ( $type['ext'] ?? '' ) ) {
				return new WP_Error( 'taka_tour_invalid_upload', __( 'Uploaded config must be a JSON file.', 'taka-platform' ) );
			}
			$json = file_get_contents( $_FILES['config_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$data = json_decode( (string) $json, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				return new WP_Error( 'taka_tour_invalid_json', __( 'Invalid JSON import data.', 'taka-platform' ) );
			}
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
		self::register_post_types();
		$config = is_array( $config ) ? $config : TAKA_Platform_Data::load_config();
		$summary = array( 'organizers' => array( 'created' => 0, 'updated' => 0, 'skipped' => 0 ), 'venues' => array( 'created' => 0, 'updated' => 0, 'skipped' => 0 ), 'events' => array( 'created' => 0, 'updated' => 0, 'skipped' => 0 ), 'warnings' => array() );
		if ( $delete_existing && ! $dry_run ) { self::delete_plugin_posts(); }
		foreach ( $config['organizers'] ?? array() as $id => $item ) { self::upsert_config_post( TAKA_PLATFORM_CPT_ORGANIZER, $id, $item['name'] ?? $id, '', self::organizer_meta_from_config( $item ), $mode, $dry_run, $summary['organizers'] ); }
		foreach ( $config['venues'] ?? array() as $id => $item ) { self::upsert_config_post( TAKA_PLATFORM_CPT_VENUE, $id, $item['name'] ?? $id, $item['notes'] ?? '', self::venue_meta_from_config( $item ), $mode, $dry_run, $summary['venues'] ); }
		foreach ( $config['events'] ?? array() as $item ) {
			$id = $item['id'] ?? ( $item['slug'] ?? '' );
			$meta = self::event_meta_from_config( $item );
			$relationships = self::event_organizer_relationships_from_config( $item );
			foreach ( $relationships as &$relationship ) { $relationship['organizer_id'] = (string) self::find_post_id_by_config_id( TAKA_PLATFORM_CPT_ORGANIZER, $relationship['organizer_id'] ?? '' ); }
			unset( $relationship );
			$relationships = TAKA_Platform_Data::normalize_event_organizer_relationships( $relationships, self::find_post_id_by_config_id( TAKA_PLATFORM_CPT_ORGANIZER, $item['organizer'] ?? '' ) );
			$meta['_taka_event_organizers'] = $relationships;
			$meta['_taka_organizer_id'] = ! empty( $relationships ) ? absint( $relationships[0]['organizer_id'] ?? 0 ) : self::find_post_id_by_config_id( TAKA_PLATFORM_CPT_ORGANIZER, $item['organizer'] ?? '' );
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
		$post_data = array( 'post_type' => $post_type, 'post_title' => sanitize_text_field( $title ), 'post_status' => 'publish' );
		if ( self::post_type_uses_default_content_editor( $post_type ) ) {
			$post_data['post_content'] = wp_kses_post( $content );
		}
		if ( '' !== $slug ) { $post_data['post_name'] = sanitize_title( $slug ); }
		if ( $existing ) {
			if ( 'overwrite' === $mode ) {
				$post_data['ID'] = $existing;
				$post_id = wp_update_post( $post_data, true );
			} else {
				$post_id = $existing;
			}
			$summary['updated']++;
		} else {
			$post_id = wp_insert_post( $post_data, true );
			$summary['created']++;
		}
		if ( is_wp_error( $post_id ) ) { $summary['skipped']++; return 0; }
		update_post_meta( $post_id, '_taka_config_id', sanitize_text_field( $config_id ) );
		foreach ( $meta as $key => $value ) {
			$is_media_id = in_array( $key, array( '_taka_logo_id', '_taka_image_id', '_taka_group_image_id', '_taka_parking_image_id', '_taka_gallery_image_ids' ), true );
			if ( $existing && 'overwrite' !== $mode && '' !== self::meta_to_string( get_post_meta( $post_id, $key, true ) ) ) { continue; }
			if ( $existing && 'overwrite' !== $mode && $is_media_id && '' !== (string) get_post_meta( $post_id, $key, true ) ) { continue; }
			update_post_meta( $post_id, $key, $value );
		}
		return (int) $post_id;
	}

	private static function meta_to_string( $value ) {
		if ( is_array( $value ) ) { return empty( $value ) ? '' : wp_json_encode( $value ); }
		return trim( (string) $value );
	}

	private static function post_type_uses_default_content_editor( $post_type ) {
		return TAKA_PLATFORM_CPT_CONTENT_BLOCK === $post_type;
	}


	private static function event_organizer_relationships_from_config( $item ) {
		$relationships = is_array( $item['organizers'] ?? null ) ? $item['organizers'] : array();
		if ( empty( $relationships ) && ! empty( $item['organizer'] ) ) {
			$relationships[] = array( 'organizer_id' => $item['organizer'], 'relationship_type' => 'organizer', 'custom_label' => '', 'visible' => true, 'sort_order' => 10 );
		}
		return TAKA_Platform_Data::normalize_event_organizer_relationships( $relationships, '' );
	}

	private static function organizer_meta_from_config( $item ) {
		$social = is_array( $item['social'] ?? null ) ? $item['social'] : ( is_array( $item['social_links'] ?? null ) ? $item['social_links'] : array() );
		$country = TAKA_Platform_Data::normalize_event_option_value( 'country', $item['country_code'] ?? ( $item['country'] ?? '' ) );
		$country_code = TAKA_Platform_Data::country_code_for_value( $country );
		return array(
			'_taka_legal_name' => $item['legal_name'] ?? '',
			'_taka_website' => $item['website'] ?? '',
			'_taka_country' => $country,
			'_taka_country_code' => $country_code,
			'_taka_flag' => TAKA_Platform_Data::flag_for_country_code( $country_code ) ?: ( $item['flag'] ?? '' ),
			'_taka_logo_id' => (int) ( $item['logo_id'] ?? 0 ),
			'_taka_logo_url' => $item['logo_url'] ?? ( $item['logo'] ?? '' ),
			'_taka_description' => $item['description'] ?? '',
			'_taka_emails' => implode( "\n", $item['emails'] ?? array() ),
			'_taka_contact_persons' => self::contact_persons_to_lines( $item['contact_persons'] ?? array() ),
			'_taka_instagram' => $social['instagram'] ?? '',
			'_taka_facebook' => $social['facebook'] ?? '',
			'_taka_youtube' => $social['youtube'] ?? '',
			'_taka_platform_co_organizers' => self::sanitize_co_organizers( $item['co_organizers'] ?? array() ),
			'_taka_active' => 1,
		);
	}

	private static function venue_meta_from_config( $item ) {
		$a = $item['address'] ?? array();
		$g = $item['geo'] ?? array();
		$country = TAKA_Platform_Data::normalize_event_option_value( 'country', $a['country_code'] ?? ( $a['country'] ?? '' ) );
		$country_code = TAKA_Platform_Data::country_code_for_value( $country );
		return array(
			'_taka_image_id' => (int) ( $item['image_id'] ?? 0 ),
			'_taka_image_url' => $item['image_url'] ?? ( $item['image'] ?? '' ),
			'_taka_parking_image_id' => (int) ( $item['parking_image_id'] ?? 0 ),
			'_taka_parking_image_url' => $item['parking_image_url'] ?? '',
			'_taka_gallery_image_ids' => implode( ',', $item['gallery_image_ids'] ?? array() ),
			'_taka_street' => $a['street'] ?? '',
			'_taka_postal_code' => $a['postal_code'] ?? '',
			'_taka_city' => $a['city'] ?? '',
			'_taka_country' => $country,
			'_taka_country_code' => $country_code,
			'_taka_flag' => TAKA_Platform_Data::flag_for_country_code( $country_code ) ?: ( $item['flag'] ?? ( $a['flag'] ?? '' ) ),
			'_taka_route_map_x' => $item['route_map_x'] ?? ( $item['map_x'] ?? '' ),
			'_taka_route_map_y' => $item['route_map_y'] ?? ( $item['map_y'] ?? '' ),
			'_taka_route_map_label' => $item['route_map_label'] ?? ( $item['map_label'] ?? '' ),
			'_taka_route_map_label_placement' => $item['route_map_label_placement'] ?? '',
			'_taka_route_map_label_dx' => $item['route_map_label_dx'] ?? '',
			'_taka_route_map_label_dy' => $item['route_map_label_dy'] ?? '',
			'_taka_route_map_label_x' => $item['route_map_label_x'] ?? '',
			'_taka_route_map_label_y' => $item['route_map_label_y'] ?? '',
			'_taka_route_map_label_anchor' => $item['route_map_label_anchor'] ?? '',
			'_taka_route_map_label_width' => $item['route_map_label_width'] ?? '',
			'_taka_route_map_leader_line' => $item['route_map_leader_line'] ?? '',
			'_taka_timezone' => $item['timezone'] ?? TAKA_Platform_Data::timezone_for_country( $country_code ),
			'_taka_website' => $item['website'] ?? '',
			'_taka_parking' => $item['parking'] ?? '',
			'_taka_accessibility' => $item['accessibility'] ?? '',
			'_taka_notes' => $item['notes'] ?? '',
			'_taka_lat' => $g['lat'] ?? '',
			'_taka_lng' => $g['lng'] ?? '',
		);
	}
	private static function event_meta_from_config( $item ) {
		$raw_booking = is_array( $item['booking_information'] ?? null ) ? $item['booking_information'] : array();
		if ( ! empty( $raw_booking ) && ! isset( $raw_booking['override'] ) ) { $raw_booking['override'] = '1'; }
		if ( ! empty( $raw_booking ) && ! isset( $raw_booking['enabled'] ) ) { $raw_booking['enabled'] = '1'; }
		$booking = self::sanitize_booking_information( $raw_booking );
		$country = TAKA_Platform_Data::normalize_event_option_value( 'country', $item['country_code'] ?? ( $item['country'] ?? '' ) );
		$country_code = TAKA_Platform_Data::country_code_for_value( $country );
		return array(
			'_taka_subtitle' => $item['subtitle'] ?? '',
			'_taka_short_description' => $item['description'] ?? '',
			'_taka_country' => $country,
			'_taka_country_code' => $country_code,
			'_taka_flag' => TAKA_Platform_Data::flag_for_country_code( $country_code ),
			'_taka_route_map_x' => $item['route_map_x'] ?? ( $item['map_x'] ?? '' ),
			'_taka_route_map_y' => $item['route_map_y'] ?? ( $item['map_y'] ?? '' ),
			'_taka_route_map_label' => $item['route_map_label'] ?? ( $item['map_label'] ?? '' ),
			'_taka_route_map_label_placement' => $item['route_map_label_placement'] ?? '',
			'_taka_route_map_label_dx' => $item['route_map_label_dx'] ?? '',
			'_taka_route_map_label_dy' => $item['route_map_label_dy'] ?? '',
			'_taka_route_map_label_x' => $item['route_map_label_x'] ?? '',
			'_taka_route_map_label_y' => $item['route_map_label_y'] ?? '',
			'_taka_route_map_label_anchor' => $item['route_map_label_anchor'] ?? '',
			'_taka_route_map_label_width' => $item['route_map_label_width'] ?? '',
			'_taka_route_map_leader_line' => $item['route_map_leader_line'] ?? '',
			'_taka_tour_order' => $item['tour_order'] ?? '',
			'_taka_route_order' => $item['route_order'] ?? '',
			'_taka_city' => $item['city'] ?? '',
			'_taka_date_start' => $item['date_start'] ?? '',
			'_taka_date_end' => $item['date_end'] ?? '',
			'_taka_time_start' => $item['time_start'] ?? '',
			'_taka_time_end' => $item['time_end'] ?? '',
			'_taka_doors_open' => $item['doors_open'] ?? '',
			'_taka_program_items' => TAKA_Platform_Data::normalize_program_items( $item['program_items'] ?? ( $item['program'] ?? array() ), $item ),
			'_taka_timezone' => $item['timezone'] ?? TAKA_Platform_Data::timezone_for_country( $country_code ),
			'_taka_currency' => TAKA_Platform_Data::normalize_event_option_value( 'currency', $item['currency'] ?? TAKA_Platform_Data::currency_for_country( $country_code ) ),
			'_taka_format' => TAKA_Platform_Data::normalize_event_option_value( 'format', $item['format'] ?? '' ),
			'_taka_audience' => TAKA_Platform_Data::normalize_event_option_value( 'audience', $item['audience'] ?? '' ),
			'_taka_level' => TAKA_Platform_Data::normalize_event_option_value( 'level', $item['level'] ?? '' ),
			'_taka_ticket_status' => TAKA_Platform_Data::normalize_event_option_value( 'ticket_status', $item['ticket_status'] ?? '' ),
			'_taka_ticket_provider' => TAKA_Platform_Data::normalize_event_option_value( 'ticket_provider', $item['ticket_provider'] ?? '' ),
			'_taka_ticket_shop_url' => $item['ticket_shop_url'] ?? '',
			'_taka_image_id' => (int) ( $item['image_id'] ?? 0 ),
			'_taka_image_url' => $item['image_url'] ?? ( $item['image'] ?? '' ),
			'_taka_group_image_id' => (int) ( $item['group_image_id'] ?? 0 ),
			'_taka_group_image_url' => $item['group_image_url'] ?? ( $item['group_image'] ?? '' ),
			'_taka_gallery_image_ids' => implode( ',', $item['gallery_image_ids'] ?? array() ),
			'_taka_promo_videos' => TAKA_Platform_Data::normalize_event_videos( $item['promo_videos'] ?? ( $item['videos'] ?? array() ) ),
			'_taka_photo_credit' => $item['photo_credit'] ?? '',
			'_taka_languages' => implode( ',', ! empty( $item['languages'] ) ? TAKA_Platform_Data::normalize_language_codes( $item['languages'] ) : TAKA_Platform_Data::languages_for_country( $country_code ) ),
			'_taka_booking_info_override' => $booking['override'] ?? '',
			'_taka_booking_info_enabled' => $booking['enabled'] ?? '1',
			'_taka_booking_info_title' => $booking['title'] ?? '',
			'_taka_booking_info_intro' => $booking['intro'] ?? '',
			'_taka_booking_info_group_booking' => $booking['group_booking'] ?? '',
			'_taka_booking_info_multi_event_discount' => $booking['multi_event_discount'] ?? '',
			'_taka_booking_info_contact_email' => $booking['contact_email'] ?? '',
			'_taka_booking_info_booking_process' => $booking['booking_process'] ?? '',
			'_taka_booking_info_payment_methods' => $booking['payment_methods'] ?? '',
			'_taka_booking_info_cancellation_policy' => $booking['cancellation_policy'] ?? '',
			'_taka_booking_info_additional_notes' => $booking['additional_notes'] ?? '',
			'_taka_notes' => $item['notes'] ?? '',
			'_taka_parking' => $item['parking'] ?? '',
			'_taka_sort_order' => (int) ( $item['sort_order'] ?? 0 ),
		);
	}

	private static function contact_persons_to_lines( $people ) { return implode( "\n", array_map( static function ( $person ) { return is_array( $person ) ? trim( ( $person['name'] ?? '' ) . ' | ' . ( $person['email'] ?? '' ) . ' | ' . ( $person['role'] ?? '' ) ) : (string) $person; }, $people ) ); }
	private static function find_post_id_by_config_id( $post_type, $config_id ) { if ( '' === (string) $config_id ) { return 0; } $posts = get_posts( array( 'post_type' => $post_type, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_taka_config_id', 'meta_value' => $config_id ) ); return ! empty( $posts ) ? (int) $posts[0] : 0; }
	private static function delete_plugin_posts() { foreach ( array( TAKA_PLATFORM_CPT_EVENT, TAKA_PLATFORM_CPT_ORGANIZER, TAKA_PLATFORM_CPT_VENUE ) as $type ) { $ids = get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ); foreach ( $ids as $id ) { wp_delete_post( $id, true ); } } }

	/** Organizer meta. */
	public static function render_organizer_meta_box( $post ) {
		self::nonce();
		self::render_object_source_language_field( $post->ID );
		self::text( $post->ID, 'legal_name', __( 'Legal name', 'taka-platform' ) );
		self::url( $post->ID, 'website', __( 'Website', 'taka-platform' ) );
		self::event_option_select( $post->ID, 'country', __( 'Country', 'taka-platform' ), __( 'organizer', 'taka-platform' ) );
		self::render_derived_country_fields( $post->ID, false );
		self::media_field( $post->ID, 'logo_id', __( 'Logo', 'taka-platform' ), false, __( 'Select logo', 'taka-platform' ) );
		self::url( $post->ID, 'logo_url', __( 'Fallback logo URL', 'taka-platform' ) );
		self::textarea( $post->ID, 'emails', __( 'Email addresses (one per line)', 'taka-platform' ) );
		self::textarea( $post->ID, 'contact_persons', __( 'Contact persons (one per line)', 'taka-platform' ) );
		self::text( $post->ID, 'instagram', __( 'Instagram', 'taka-platform' ) );
		self::text( $post->ID, 'facebook', __( 'Facebook', 'taka-platform' ) );
		self::text( $post->ID, 'youtube', __( 'YouTube', 'taka-platform' ) );
		self::checkbox( $post->ID, 'active', __( 'Active', 'taka-platform' ) );
		self::render_object_text_translation_fields( $post->ID, 'organizer', array( 'description' => self::organizer_description_source_text( $post ) ) );
		self::render_co_organizers( $post->ID );
	}

	/** Venue meta. */
	public static function render_venue_meta_box( $post ) {
		self::nonce();
		self::render_object_source_language_field( $post->ID );
		foreach ( array( 'street' => 'Street', 'postal_code' => 'Postal code', 'city' => 'City' ) as $key => $label ) { self::text( $post->ID, $key, __( $label, 'taka-platform' ) ); }
		self::event_option_select( $post->ID, 'country', __( 'Country', 'taka-platform' ), __( 'venue', 'taka-platform' ) );
		self::render_derived_country_fields( $post->ID );
		foreach ( array( 'route_map_x' => 'Route marker X (0–100)', 'route_map_y' => 'Route marker Y (0–100)', 'route_map_label' => 'Route map label', 'route_map_label_x' => 'Route label X (0–100)', 'route_map_label_y' => 'Route label Y (0–100)' ) as $key => $label ) { self::text( $post->ID, $key, __( $label, 'taka-platform' ) ); }
		self::route_map_label_anchor_field( $post->ID );
		foreach ( array( 'route_map_label_width' => 'Route label width', 'timezone' => 'Timezone override', 'lat' => 'Geo lat', 'lng' => 'Geo lng' ) as $key => $label ) { self::text( $post->ID, $key, __( $label, 'taka-platform' ) ); }
		self::checkbox_with_hidden( $post->ID, 'route_map_leader_line', __( 'Show route label leader line', 'taka-platform' ) );
		self::url( $post->ID, 'website', __( 'Website', 'taka-platform' ) );
		self::media_field( $post->ID, 'image_id', __( 'Venue photo', 'taka-platform' ), false, __( 'Select venue photo', 'taka-platform' ) );
		self::url( $post->ID, 'image_url', __( 'Fallback venue photo URL', 'taka-platform' ) );
		self::media_field( $post->ID, 'parking_image_id', __( 'Parking/arrival photo', 'taka-platform' ), false, __( 'Select parking photo', 'taka-platform' ) );
		self::url( $post->ID, 'parking_image_url', __( 'Fallback parking photo URL', 'taka-platform' ) );
		self::media_field( $post->ID, 'gallery_image_ids', __( 'Gallery images', 'taka-platform' ), true, __( 'Select gallery images', 'taka-platform' ) );
		self::textarea_source( $post->ID, 'parking', __( 'Parking notes', 'taka-platform' ) );
		self::textarea_source( $post->ID, 'accessibility', __( 'Accessibility', 'taka-platform' ) );
		self::textarea_source( $post->ID, 'notes', __( 'Special notes', 'taka-platform' ) );
		self::render_object_text_translation_fields( $post->ID, 'venue' );
	}

	/** Event meta. */
	public static function render_event_meta_box( $post ) {
		self::nonce();
		self::render_object_source_language_field( $post->ID );
		self::text_source( $post->ID, 'subtitle', __( 'Subtitle', 'taka-platform' ) );
		foreach ( array( 'route_map_x' => 'Route marker X (0–100)', 'route_map_y' => 'Route marker Y (0–100)', 'route_map_label' => 'Route map label', 'route_map_label_x' => 'Route label X (0–100)', 'route_map_label_y' => 'Route label Y (0–100)' ) as $key => $label ) { self::text( $post->ID, $key, __( $label, 'taka-platform' ) ); }
		self::route_map_label_anchor_field( $post->ID );
		foreach ( array( 'route_map_label_width' => 'Route label width', 'tour_order' => 'Tour order', 'city' => 'City', 'doors_open' => 'Doors open' ) as $key => $label ) { self::text( $post->ID, $key, __( $label, 'taka-platform' ) ); }
		self::checkbox_with_hidden( $post->ID, 'route_map_leader_line', __( 'Show route label leader line', 'taka-platform' ) );
		self::event_option_select( $post->ID, 'country', __( 'Country', 'taka-platform' ) );
		self::render_derived_country_fields( $post->ID );
		self::text( $post->ID, 'timezone', __( 'Timezone override', 'taka-platform' ) );
		self::event_option_select( $post->ID, 'currency', __( 'Currency override', 'taka-platform' ) );
		self::event_option_select( $post->ID, 'format', __( 'Format', 'taka-platform' ) );
		self::event_option_select( $post->ID, 'audience', __( 'Audience', 'taka-platform' ) );
		self::event_option_select( $post->ID, 'level', __( 'Level', 'taka-platform' ) );
		self::event_option_select( $post->ID, 'ticket_provider', __( 'Ticket provider', 'taka-platform' ) );
		self::event_option_select( $post->ID, 'ticket_status', __( 'Ticket status', 'taka-platform' ) );
		self::language_multiselect( $post->ID, 'languages', __( 'Spoken / teaching languages', 'taka-platform' ) );
		self::render_event_program_fields( $post->ID );
		self::organizer_relation( $post->ID, 'organizer_id', __( 'Primary organizer', 'taka-platform' ) );
		self::render_event_organizer_relationship_fields( $post->ID );
		self::relation( $post->ID, 'venue_id', __( 'Primary venue', 'taka-platform' ), TAKA_PLATFORM_CPT_VENUE );
		self::text( $post->ID, 'venue_ids', __( 'Additional venue IDs, comma-separated', 'taka-platform' ) );
		self::url( $post->ID, 'ticket_shop_url', __( 'Ticket shop URL', 'taka-platform' ) );
		self::media_field( $post->ID, 'image_id', __( 'Event action photo', 'taka-platform' ), false, __( 'Select action photo', 'taka-platform' ) );
		self::url( $post->ID, 'image_url', __( 'Fallback action photo URL', 'taka-platform' ) );
		self::media_field( $post->ID, 'group_image_id', __( 'Past group photo', 'taka-platform' ), false, __( 'Select group photo', 'taka-platform' ) );
		self::url( $post->ID, 'group_image_url', __( 'Fallback group photo URL', 'taka-platform' ) );
		self::render_event_video_fields( $post->ID );
		self::textarea_with_description_source( $post->ID, 'short_description', __( 'Seminar description', 'taka-platform' ), __( 'Canonical text shown on the public ticket page under “Seminar description”.', 'taka-platform' ) );
		self::render_content_reference_fields( 'content_reference_event_description', get_post_meta( $post->ID, '_taka_content_reference_event_description', true ), 'event_description', __( 'Reusable seminar description block', 'taka-platform' ), get_post_meta( $post->ID, '_taka_source_language', true ) ?: 'de' );
		self::text_source( $post->ID, 'ticket_tab_label', __( 'Ticket tab label', 'taka-platform' ) );
		self::render_object_text_translation_fields( $post->ID, 'event', array( 'description' => (string) self::meta( $post->ID, 'short_description' ) ?: $post->post_content ), array( 'long_description', 'ticket_card_text' ) );
		self::render_event_booking_information_fields( $post->ID );
		self::textarea_source( $post->ID, 'accessibility', __( 'Accessibility notes', 'taka-platform' ) );
		self::number( $post->ID, 'sort_order', __( 'Sort order', 'taka-platform' ) );
		self::textarea_source( $post->ID, 'notes', __( 'Notes', 'taka-platform' ) );
		self::textarea_source( $post->ID, 'parking', __( 'Parking notes', 'taka-platform' ) );
		self::render_event_advanced_unused_fields( $post->ID );
	}

	/** Reusable Content Block editor fields. */
	public static function render_content_block_meta_box( $post ) {
		self::nonce();
		self::render_object_source_language_field( $post->ID );
		self::render_main_editor_source_text_notice( __( 'Body', 'taka-platform' ) );
		self::text( $post->ID, 'block_slug', __( 'Slug', 'taka-platform' ) );
		self::select_field( '_taka_block_type', __( 'Block type', 'taka-platform' ), (string) self::meta( $post->ID, 'block_type' ) ?: 'generic', TAKA_Platform_Data::content_block_types() );
		self::text( $post->ID, 'category', __( 'Category', 'taka-platform' ) );
		echo '<input type="hidden" name="_taka_enabled" value="0">';
		self::checkbox( $post->ID, 'enabled', __( 'Enabled', 'taka-platform' ) );
		self::text_source( $post->ID, 'kicker', __( 'Kicker', 'taka-platform' ) );
		self::text_source( $post->ID, 'block_title', __( 'Content title', 'taka-platform' ) );
		self::text_source( $post->ID, 'subtitle', __( 'Subtitle', 'taka-platform' ) );
		self::text_source( $post->ID, 'button_label', __( 'Button label', 'taka-platform' ) );
		self::url_source( $post->ID, 'button_url', __( 'Button URL', 'taka-platform' ) );
		self::media_field( $post->ID, 'image_id', __( 'Image', 'taka-platform' ), false, __( 'Select image', 'taka-platform' ) );
		self::url( $post->ID, 'image_url', __( 'Fallback image URL', 'taka-platform' ) );
		self::media_field( $post->ID, 'gallery_image_ids', __( 'Gallery images', 'taka-platform' ), true, __( 'Select gallery images', 'taka-platform' ) );
		self::textarea( $post->ID, 'gallery_image_urls', __( 'Fallback gallery image URLs', 'taka-platform' ) );
		self::textarea( $post->ID, 'notes', __( 'Admin notes', 'taka-platform' ) );
		self::render_object_text_translation_fields( $post->ID, 'content_block', array(
			'kicker' => (string) self::meta( $post->ID, 'kicker' ),
			'title' => (string) self::meta( $post->ID, 'block_title' ),
			'subtitle' => (string) self::meta( $post->ID, 'subtitle' ),
			'body' => $post->post_content,
			'button_label' => (string) self::meta( $post->ID, 'button_label' ),
			'button_url' => (string) self::meta( $post->ID, 'button_url' ),
		) );
		self::render_content_block_used_by( $post->ID );
	}

	public static function save_organizer( $post_id ) {
		self::save_access_fields( $post_id );
		self::save( $post_id, array( 'legal_name', 'website', 'country', 'country_code', 'flag', 'logo_id', 'logo_url', 'emails', 'contact_persons', 'instagram', 'facebook', 'youtube', 'active' ) );
		self::save_object_country_meta( $post_id );
		self::save_object_text_translations( $post_id, 'organizer' );
		self::save_co_organizers( $post_id );
	}

	private static function organizer_description_source_text( $post ) {
		$description = (string) get_post_meta( $post->ID, '_taka_description', true );
		if ( '' !== trim( $description ) ) {
			return $description;
		}
		return (string) ( $post->post_content ?? '' );
	}
	public static function save_venue( $post_id ) { self::save_access_fields( $post_id ); self::save( $post_id, array( 'street', 'postal_code', 'city', 'country', 'country_code', 'flag', 'route_map_x', 'route_map_y', 'route_map_label', 'route_map_label_x', 'route_map_label_y', 'route_map_label_anchor', 'route_map_label_width', 'route_map_leader_line', 'timezone', 'lat', 'lng', 'website', 'image_id', 'image_url', 'parking_image_id', 'parking_image_url', 'gallery_image_ids', 'parking', 'accessibility', 'notes' ) ); self::save_object_country_meta( $post_id, true ); self::save_object_text_translations( $post_id, 'venue' ); }
	public static function save_content_block( $post_id ) {
		self::save_access_fields( $post_id );
		self::save( $post_id, array( 'block_slug', 'block_type', 'category', 'enabled', 'kicker', 'block_title', 'subtitle', 'button_label', 'button_url', 'image_id', 'image_url', 'gallery_image_ids', 'gallery_image_urls', 'notes' ) );
		self::save_object_text_translations( $post_id, 'content_block' );
		if ( isset( $_POST[ self::NONCE ], $_POST['_taka_block_slug'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) && current_user_can( 'edit_post', $post_id ) ) {
			$slug = sanitize_key( wp_unslash( $_POST['_taka_block_slug'] ) );
			if ( '' !== $slug ) {
				update_post_meta( $post_id, '_taka_config_id', $slug );
			}
		}
	}
	public static function save_event( $post_id ) {
		if ( ! self::can_save_post_meta( $post_id ) ) { return; }
		self::save_access_fields( $post_id );
		$posted_relationships = self::sanitize_event_organizer_relationships( $_POST['taka_platform_event_organizers'] ?? array() );
		if ( ! empty( $posted_relationships ) ) {
			$_POST['_taka_organizer_id'] = (string) absint( $posted_relationships[0]['organizer_id'] ?? 0 );
		}
		if ( ! self::current_user_is_platform_admin() ) {
			$assigned = self::get_current_user_organizer_ids();
			$existing = absint( get_post_meta( $post_id, '_taka_organizer_id', true ) );
			$posted   = isset( $_POST['_taka_organizer_id'] ) ? absint( wp_unslash( $_POST['_taka_organizer_id'] ) ) : 0;
			if ( $posted && ! in_array( $posted, $assigned, true ) ) {
				$posted = $existing;
			}
			if ( 0 === $posted && $existing ) {
				$posted = $existing;
			}
			if ( 0 === $posted && 1 === count( $assigned ) ) {
				$posted = (int) $assigned[0];
			}
			$_POST['_taka_organizer_id'] = (string) $posted;
			if ( ! empty( $posted_relationships ) ) {
				$posted_relationships = array_values(
					array_filter(
						$posted_relationships,
						static function ( $relationship ) use ( $assigned ) {
							return in_array( absint( $relationship['organizer_id'] ?? 0 ), $assigned, true );
						}
					)
				);
			}
			if ( empty( $posted_relationships ) ) {
				$posted_relationships = TAKA_Platform_Data::normalize_event_organizer_relationships( get_post_meta( $post_id, '_taka_event_organizers', true ), $existing );
			}
		}
		self::save( $post_id, array( 'subtitle', 'country', 'country_code', 'flag', 'route_map_x', 'route_map_y', 'route_map_label', 'route_map_label_x', 'route_map_label_y', 'route_map_label_anchor', 'route_map_label_width', 'route_map_leader_line', 'tour_order', 'city', 'doors_open', 'timezone', 'currency', 'format', 'audience', 'level', 'ticket_provider', 'ticket_status', 'photo_credit', 'languages', 'organizer_id', 'venue_id', 'venue_ids', 'ticket_shop_url', 'image_id', 'image_url', 'group_image_id', 'group_image_url', 'gallery_image_ids', 'short_description', 'long_description', 'ticket_card_text', 'ticket_tab_label', 'booking_info_override', 'booking_info_enabled', 'booking_info_title', 'booking_info_intro', 'booking_info_group_booking', 'booking_info_multi_event_discount', 'booking_info_contact_email', 'booking_info_booking_process', 'booking_info_payment_methods', 'booking_info_cancellation_policy', 'booking_info_additional_notes', 'accessibility', 'sort_order', 'notes', 'parking' ) );
		self::save_content_reference_meta( $post_id, 'content_reference_event_description', 'event_description' );
		self::save_object_text_translations( $post_id, 'event' );
		self::save_event_organizer_relationships( $post_id, $posted_relationships );
		self::save_event_program_items( $post_id );
		self::save_event_videos( $post_id );
		self::save_event_structured_meta( $post_id );
	}

	private static function save_access_fields( $post_id ) {
		if ( ! self::can_save_post_meta( $post_id ) ) { return; }
		$post = get_post( $post_id );
		if ( ! $post || ! isset( self::managed_post_types()[ $post->post_type ] ) ) { return; }

		if ( ! self::current_user_is_platform_admin() ) {
			if ( '' === (string) get_post_meta( $post_id, '_taka_permission_mode', true ) ) {
				update_post_meta( $post_id, '_taka_permission_mode', 'owner' );
			}
			if ( ! metadata_exists( 'post', $post_id, '_taka_assigned_organizer_ids' ) ) {
				$organizer_ids = self::get_current_user_organizer_ids();
				if ( ! empty( $organizer_ids ) ) {
					update_post_meta( $post_id, '_taka_assigned_organizer_ids', $organizer_ids );
				}
			}
			return;
		}

		$mode = sanitize_key( wp_unslash( $_POST['taka_access_permission_mode'] ?? 'owner' ) );
		update_post_meta( $post_id, '_taka_permission_mode', isset( self::access_modes()[ $mode ] ) ? $mode : 'owner' );
		update_post_meta( $post_id, '_taka_assigned_user_ids', self::sanitize_posted_id_list( $_POST['taka_access_assigned_user_ids'] ?? array() ) );
		update_post_meta( $post_id, '_taka_assigned_organizer_ids', self::sanitize_posted_id_list( $_POST['taka_access_assigned_organizer_ids'] ?? array() ) );

		$owner_id = absint( wp_unslash( $_POST['taka_access_owner_user_id'] ?? 0 ) );
		if ( $owner_id && get_user_by( 'id', $owner_id ) && (int) $post->post_author !== $owner_id ) {
			self::update_post_author_without_recursion( $post_id, $post->post_type, $owner_id );
		}
	}

	private static function sanitize_posted_id_list( $value ) {
		if ( ! is_array( $value ) ) {
			$value = array_filter( preg_split( '/\s*,\s*/', (string) $value ) );
		}
		return array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $value ) ) ) ) );
	}

	private static function update_post_author_without_recursion( $post_id, $post_type, $author_id ) {
		$callbacks = array(
			TAKA_PLATFORM_CPT_EVENT         => 'save_event',
			TAKA_PLATFORM_CPT_VENUE         => 'save_venue',
			TAKA_PLATFORM_CPT_ORGANIZER     => 'save_organizer',
			TAKA_PLATFORM_CPT_CONTENT_BLOCK => 'save_content_block',
		);
		if ( empty( $callbacks[ $post_type ] ) ) { return; }
		remove_action( 'save_post_' . $post_type, array( __CLASS__, $callbacks[ $post_type ] ) );
		wp_update_post( array( 'ID' => $post_id, 'post_author' => $author_id ) );
		add_action( 'save_post_' . $post_type, array( __CLASS__, $callbacks[ $post_type ] ) );
	}

	private static function render_event_organizer_relationship_fields( $post_id ) {
		$legacy = absint( get_post_meta( $post_id, '_taka_organizer_id', true ) );
		$items = TAKA_Platform_Data::normalize_event_organizer_relationships( get_post_meta( $post_id, '_taka_event_organizers', true ), $legacy );
		$types = TAKA_Platform_Data::organizer_relationship_type_labels();
		$assigned = self::current_user_is_platform_admin() ? array() : self::get_current_user_organizer_ids();
		$posts_args = array( 'post_type' => TAKA_PLATFORM_CPT_ORGANIZER, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' );
		if ( ! self::current_user_is_platform_admin() ) { $posts_args['post__in'] = ! empty( $assigned ) ? $assigned : array( 0 ); }
		$organizers = get_posts( $posts_args );
		if ( empty( $items ) && 1 === count( $assigned ) ) { $items[] = array( 'organizer_id' => (string) $assigned[0], 'relationship_type' => 'organizer', 'custom_label' => '', 'visible' => 1, 'sort_order' => 10 ); }
		?>
		<div class="taka-event-organizers" data-taka-event-organizers>
			<h3><?php echo esc_html__( 'Event organizers', 'taka-platform' ); ?></h3>
			<p class="description"><?php echo esc_html__( 'Assign one or more organizer profiles with a role for this event.', 'taka-platform' ); ?> <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . TAKA_PLATFORM_CPT_ORGANIZER ) ); ?>"><?php echo esc_html__( 'Add new organizer', 'taka-platform' ); ?></a></p>
			<div class="taka-event-organizer-list" data-taka-event-organizer-list>
				<?php foreach ( $items as $index => $item ) : ?><?php self::render_event_organizer_relationship_row( (int) $index, $item, $organizers, $types ); ?><?php endforeach; ?>
			</div>
			<button type="button" class="button" data-taka-event-organizer-add><?php echo esc_html__( 'Add organizer', 'taka-platform' ); ?></button>
			<template data-taka-event-organizer-template><?php self::render_event_organizer_relationship_row( '__index__', array( 'relationship_type' => 'organizer', 'visible' => 1, 'sort_order' => 10 ), $organizers, $types ); ?></template>
		</div>
		<?php
	}

	private static function render_event_organizer_relationship_row( $index, $item, $organizers, $types ) {
		$index_attr = esc_attr( (string) $index );
		$prefix = 'taka_platform_event_organizers[' . $index_attr . ']';
		?>
		<div class="taka-event-organizer-item" data-taka-event-organizer-item>
			<div class="taka-event-organizer-item__header"><strong><?php echo esc_html__( 'Event organizer', 'taka-platform' ); ?></strong> <button type="button" class="button-link-delete" data-taka-event-organizer-remove><?php echo esc_html__( 'Remove organizer', 'taka-platform' ); ?></button></div>
			<p><label><?php echo esc_html__( 'Organizer', 'taka-platform' ); ?><br><select name="<?php echo esc_attr( $prefix ); ?>[organizer_id]"><option value="">—</option><?php foreach ( $organizers as $organizer ) : ?><option value="<?php echo esc_attr( (string) $organizer->ID ); ?>" <?php selected( (string) ( $item['organizer_id'] ?? '' ), (string) $organizer->ID ); ?>><?php echo esc_html( get_the_title( $organizer ) ); ?></option><?php endforeach; ?></select></label></p>
			<p><label><?php echo esc_html__( 'Relationship', 'taka-platform' ); ?><br><select name="<?php echo esc_attr( $prefix ); ?>[relationship_type]"><?php foreach ( $types as $type => $label ) : ?><option value="<?php echo esc_attr( $type ); ?>" <?php selected( $item['relationship_type'] ?? 'organizer', $type ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label></p>
			<p><label><?php echo esc_html__( 'Custom label', 'taka-platform' ); ?><br><input type="text" class="regular-text" name="<?php echo esc_attr( $prefix ); ?>[custom_label]" value="<?php echo esc_attr( $item['custom_label'] ?? '' ); ?>"></label></p>
			<p><label><?php echo esc_html__( 'Sort order', 'taka-platform' ); ?><br><input type="number" name="<?php echo esc_attr( $prefix ); ?>[sort_order]" value="<?php echo esc_attr( (string) ( $item['sort_order'] ?? 0 ) ); ?>" style="width:90px"></label> <label><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[visible]" value="1" <?php checked( ! isset( $item['visible'] ) || ! empty( $item['visible'] ) ); ?>> <?php echo esc_html__( 'Visible', 'taka-platform' ); ?></label></p>
		</div>
		<?php
	}

	private static function render_event_program_fields( $post_id ) {
		$items = TAKA_Platform_Data::normalize_program_items( get_post_meta( $post_id, '_taka_program_items', true ), array( 'date_start' => get_post_meta( $post_id, '_taka_date_start', true ), 'time_start' => get_post_meta( $post_id, '_taka_time_start', true ), 'time_end' => get_post_meta( $post_id, '_taka_time_end', true ) ) );
		$types = array( 'seminar', 'training', 'workshop', 'break', 'lunch', 'grading', 'social', 'dinner', 'travel', 'other' );
		?>
		<div class="taka-program-items" data-taka-program-items>
			<h3><?php echo esc_html__( 'Program', 'taka-platform' ); ?></h3>
			<p class="description"><?php echo esc_html__( 'Add one or more dated program items. Items are grouped by date on the frontend.', 'taka-platform' ); ?></p>
			<div data-taka-program-list>
				<?php foreach ( array_values( $items ) as $index => $item ) : ?>
					<?php self::program_item_row( $index, $item, $types ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button" data-taka-program-add><?php echo esc_html__( 'Add program item', 'taka-platform' ); ?></button>
			<script type="text/template" data-taka-program-template><?php self::program_item_row( '__index__', array(), $types ); ?></script>
		</div>
		<?php
	}

	private static function program_item_row( $index, $item, $types ) {
		$name = 'taka_program_items[' . esc_attr( (string) $index ) . ']';
		?>
		<div class="taka-program-item" data-taka-program-item style="border:1px solid #dcdcde;padding:10px;margin:0 0 10px;background:#fff;">
			<p>
				<label><?php echo esc_html__( 'Sort order', 'taka-platform' ); ?> <input type="number" name="<?php echo esc_attr( $name ); ?>[sort_order]" value="<?php echo esc_attr( (string) ( $item['sort_order'] ?? $index ) ); ?>" style="width:80px"></label>
				<label><?php echo esc_html__( 'Date', 'taka-platform' ); ?> <input type="date" name="<?php echo esc_attr( $name ); ?>[date]" value="<?php echo esc_attr( $item['date'] ?? '' ); ?>"></label>
				<label><?php echo esc_html__( 'Start time', 'taka-platform' ); ?> <input type="time" name="<?php echo esc_attr( $name ); ?>[time_start]" value="<?php echo esc_attr( $item['time_start'] ?? '' ); ?>"></label>
				<label><?php echo esc_html__( 'End time', 'taka-platform' ); ?> <input type="time" name="<?php echo esc_attr( $name ); ?>[time_end]" value="<?php echo esc_attr( $item['time_end'] ?? '' ); ?>"></label>
				<label><?php echo esc_html__( 'Type', 'taka-platform' ); ?> <select name="<?php echo esc_attr( $name ); ?>[type]"><?php foreach ( $types as $type ) : ?><option value="<?php echo esc_attr( $type ); ?>" <?php selected( $item['type'] ?? 'seminar', $type ); ?>><?php echo esc_html( ucfirst( $type ) ); ?></option><?php endforeach; ?></select></label>
				<button type="button" class="button-link-delete" data-taka-program-remove><?php echo esc_html__( 'Remove', 'taka-platform' ); ?></button>
			</p>
			<p><label><?php echo esc_html__( 'Title', 'taka-platform' ); ?> <input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>[title]" value="<?php echo esc_attr( $item['title'] ?? '' ); ?>"></label></p>
			<p><label><?php echo esc_html__( 'Notes', 'taka-platform' ); ?> <textarea class="widefat" rows="2" name="<?php echo esc_attr( $name ); ?>[notes]"><?php echo esc_textarea( $item['notes'] ?? '' ); ?></textarea></label></p>
		</div>
			<?php
	}

	private static function render_event_video_fields( $post_id ) {
		$items = TAKA_Platform_Data::normalize_event_videos( get_post_meta( $post_id, '_taka_promo_videos', true ) );
		?>
		<div class="taka-event-videos-admin" data-taka-event-videos>
			<h3><?php echo esc_html__( 'Promo videos', 'taka-platform' ); ?></h3>
			<p class="description"><?php echo esc_html__( 'Add optional local videos or YouTube/Vimeo/oEmbed links for this event. Videos are shown on the ticket detail page after the core event facts.', 'taka-platform' ); ?></p>
			<div class="taka-event-video-list" data-taka-event-video-list>
				<?php foreach ( array_values( $items ) as $index => $item ) : ?>
					<?php self::event_video_row( $post_id, $index, $item ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button" data-taka-event-video-add><?php echo esc_html__( 'Add video', 'taka-platform' ); ?></button>
			<template data-taka-event-video-template><?php self::event_video_row( $post_id, '__index__', array() ); ?></template>
		</div>
		<?php
	}

	private static function event_video_row( $post_id, $index, $item ) {
		$index_attr = esc_attr( (string) $index );
		$index_key = sanitize_key( (string) $index );
		$name = 'taka_platform_event_videos[' . $index_attr . ']';
		$video_input_id = 'taka_event_video_' . absint( $post_id ) . '_' . $index_key . '_attachment_id';
		$thumbnail_input_id = 'taka_event_video_' . absint( $post_id ) . '_' . $index_key . '_thumbnail_id';
		?>
		<div class="taka-event-video-item" data-taka-event-video-item>
			<div class="taka-event-video-item__header"><strong><?php echo esc_html__( 'Promo video', 'taka-platform' ); ?></strong> <button type="button" class="button-link-delete" data-taka-event-video-remove><?php echo esc_html__( 'Remove video', 'taka-platform' ); ?></button></div>
			<p><label><?php echo esc_html__( 'Title', 'taka-platform' ); ?><br><input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[title]" value="<?php echo esc_attr( $item['title'] ?? '' ); ?>"></label></p>
			<p><label><?php echo esc_html__( 'Caption', 'taka-platform' ); ?><br><textarea class="widefat" rows="2" name="<?php echo esc_attr( $name ); ?>[caption]"><?php echo esc_textarea( $item['caption'] ?? '' ); ?></textarea></label></p>
			<p>
				<label><?php echo esc_html__( 'Local video file', 'taka-platform' ); ?></label><br>
				<input id="<?php echo esc_attr( $video_input_id ); ?>" type="hidden" name="<?php echo esc_attr( $name ); ?>[attachment_id]" value="<?php echo esc_attr( (string) absint( $item['attachment_id'] ?? 0 ) ); ?>">
				<button type="button" class="button" data-taka-media-pick data-media-type="video" data-multiple="0" data-target="<?php echo esc_attr( $video_input_id ); ?>" data-preview="<?php echo esc_attr( $video_input_id . '_preview' ); ?>"><?php echo esc_html__( 'Select video', 'taka-platform' ); ?></button>
				<button type="button" class="button" data-taka-media-remove data-target="<?php echo esc_attr( $video_input_id ); ?>" data-preview="<?php echo esc_attr( $video_input_id . '_preview' ); ?>"><?php echo esc_html__( 'Remove video', 'taka-platform' ); ?></button>
				<span id="<?php echo esc_attr( $video_input_id . '_preview' ); ?>"><?php self::video_preview( absint( $item['attachment_id'] ?? 0 ), absint( $item['attachment_id'] ?? 0 ) ? (string) ( $item['url'] ?? '' ) : '' ); ?></span>
			</p>
			<p><label><?php echo esc_html__( 'Video/oEmbed URL', 'taka-platform' ); ?><br><input class="widefat" type="url" name="<?php echo esc_attr( $name ); ?>[video_url]" value="<?php echo esc_attr( $item['video_url'] ?? '' ); ?>" placeholder="https://www.youtube.com/watch?v=..."></label></p>
			<p>
				<label><?php echo esc_html__( 'Preview image', 'taka-platform' ); ?></label><br>
				<input id="<?php echo esc_attr( $thumbnail_input_id ); ?>" type="hidden" name="<?php echo esc_attr( $name ); ?>[thumbnail_id]" value="<?php echo esc_attr( (string) absint( $item['thumbnail_id'] ?? 0 ) ); ?>">
				<button type="button" class="button" data-taka-media-pick data-media-type="image" data-multiple="0" data-target="<?php echo esc_attr( $thumbnail_input_id ); ?>" data-preview="<?php echo esc_attr( $thumbnail_input_id . '_preview' ); ?>"><?php echo esc_html__( 'Select preview image', 'taka-platform' ); ?></button>
				<button type="button" class="button" data-taka-media-remove data-target="<?php echo esc_attr( $thumbnail_input_id ); ?>" data-preview="<?php echo esc_attr( $thumbnail_input_id . '_preview' ); ?>"><?php echo esc_html__( 'Remove image', 'taka-platform' ); ?></button>
				<span id="<?php echo esc_attr( $thumbnail_input_id . '_preview' ); ?>"><?php self::image_preview( absint( $item['thumbnail_id'] ?? 0 ), (string) ( $item['poster'] ?? ( $item['thumbnail_url'] ?? '' ) ) ); ?></span>
			</p>
			<p><label><?php echo esc_html__( 'Fallback preview image URL', 'taka-platform' ); ?><br><input class="widefat" type="url" name="<?php echo esc_attr( $name ); ?>[thumbnail_url]" value="<?php echo esc_attr( $item['thumbnail_url'] ?? '' ); ?>"></label></p>
			<p><label><?php echo esc_html__( 'Sort order', 'taka-platform' ); ?><br><input type="number" name="<?php echo esc_attr( $name ); ?>[sort_order]" value="<?php echo esc_attr( (string) ( $item['sort_order'] ?? $index ) ); ?>" style="width:90px"></label></p>
		</div>
		<?php
	}

	private static function save_event_organizer_relationships( $post_id, $items = null ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		$items = is_array( $items ) ? $items : self::sanitize_event_organizer_relationships( $_POST['taka_platform_event_organizers'] ?? array() );
		if ( ! self::current_user_is_platform_admin() ) {
			$assigned = self::get_current_user_organizer_ids();
			$items = array_values( array_filter( $items, static function ( $item ) use ( $assigned ) { return in_array( absint( $item['organizer_id'] ?? 0 ), $assigned, true ); } ) );
		}
		if ( empty( $items ) ) { return; }
		update_post_meta( $post_id, '_taka_event_organizers', $items );
		update_post_meta( $post_id, '_taka_organizer_id', absint( $items[0]['organizer_id'] ?? 0 ) );
	}

	private static function sanitize_event_organizer_relationships( $items ) {
		$items = is_array( $items ) ? wp_unslash( $items ) : array();
		$allowed = array_keys( TAKA_Platform_Data::organizer_relationship_type_labels( 'en' ) );
		$clean = array();
		$seen = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$organizer_id = absint( $item['organizer_id'] ?? 0 );
			$type = sanitize_key( $item['relationship_type'] ?? 'organizer' );
			$type = in_array( $type, $allowed, true ) ? $type : 'organizer';
			if ( ! $organizer_id ) { continue; }
			$key = $organizer_id . '|' . $type;
			if ( isset( $seen[ $key ] ) ) { continue; }
			$seen[ $key ] = true;
			$clean[] = array( 'organizer_id' => (string) $organizer_id, 'relationship_type' => $type, 'custom_label' => sanitize_text_field( $item['custom_label'] ?? '' ), 'visible' => ! empty( $item['visible'] ) ? 1 : 0, 'sort_order' => (int) ( $item['sort_order'] ?? 0 ) );
		}
		usort( $clean, array( 'TAKA_Platform_Data', 'compare_event_organizer_relationships' ) );
		return $clean;
	}

	private static function save_event_program_items( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		$posted = isset( $_POST['taka_program_items'] ) && is_array( $_POST['taka_program_items'] ) ? wp_unslash( $_POST['taka_program_items'] ) : array();
		update_post_meta( $post_id, '_taka_program_items', TAKA_Platform_Data::normalize_program_items( $posted ) );
	}

	private static function save_event_videos( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		$posted = isset( $_POST['taka_platform_event_videos'] ) && is_array( $_POST['taka_platform_event_videos'] ) ? wp_unslash( $_POST['taka_platform_event_videos'] ) : array();
		$videos = TAKA_Platform_Data::normalize_event_videos( $posted );
		if ( empty( $videos ) ) {
			delete_post_meta( $post_id, '_taka_promo_videos' );
			return;
		}
		update_post_meta( $post_id, '_taka_promo_videos', $videos );
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
		if ( ! self::can_save_post_meta( $post_id ) ) { return; }
		foreach ( $fields as $field ) {
			$key = '_taka_' . $field;
			if ( ! isset( $_POST[ $key ] ) ) { delete_post_meta( $post_id, $key ); continue; }
			$value = wp_unslash( $_POST[ $key ] );
			if ( in_array( $field, array( 'logo_id', 'image_id', 'group_image_id', 'parking_image_id', 'organizer_id', 'venue_id', 'sort_order', 'tour_order', 'route_order' ), true ) ) { $value = absint( $value ); }
			elseif ( in_array( $field, array( 'route_map_x', 'route_map_y', 'route_map_label_x', 'route_map_label_y' ), true ) ) { $value = is_numeric( $value ) ? (string) max( 0, min( 100, (float) $value ) ) : ''; }
			elseif ( 'route_map_label_anchor' === $field ) { $value = in_array( sanitize_key( $value ), array( 'left', 'right', 'center' ), true ) ? sanitize_key( $value ) : ''; }
			elseif ( 'route_map_label_width' === $field ) { $value = preg_match( '/^\d+(?:\.\d+)?(rem|em|px|%)$/', (string) $value ) || preg_match( '/^min\([^)]*\)$/', (string) $value ) ? sanitize_text_field( $value ) : ''; }
			elseif ( 'route_map_leader_line' === $field ) { $value = ! empty( $value ) ? '1' : '0'; }
			elseif ( in_array( $field, array( 'website', 'ticket_shop_url', 'image_url', 'group_image_url', 'parking_image_url', 'logo_url', 'button_url' ), true ) ) { $value = esc_url_raw( $value ); }
			elseif ( 'languages' === $field ) { $value = implode( ',', TAKA_Platform_Data::normalize_language_codes( $value ) ); }
			elseif ( in_array( $field, array( 'ticket_provider', 'ticket_status', 'format', 'audience', 'level', 'country', 'currency' ), true ) ) { $value = TAKA_Platform_Data::normalize_event_option_value( $field, $value ); }
			elseif ( 'booking_info_contact_email' === $field ) { $value = sanitize_email( $value ); }
			elseif ( in_array( $field, array( 'emails', 'contact_persons', 'short_description', 'long_description', 'ticket_card_text', 'booking_info_intro', 'booking_info_group_booking', 'booking_info_multi_event_discount', 'booking_info_booking_process', 'booking_info_payment_methods', 'booking_info_cancellation_policy', 'booking_info_additional_notes', 'parking', 'accessibility', 'notes', 'gallery_image_urls' ), true ) ) { $value = sanitize_textarea_field( $value ); }
			elseif ( in_array( $field, array( 'gallery_image_ids', 'venue_ids' ), true ) ) { $value = implode( ',', array_map( 'absint', preg_split( '/\s*,\s*/', (string) $value ) ) ); }
			else { $value = sanitize_text_field( $value ); }
			update_post_meta( $post_id, $key, $value );
		}
	}

	private static function can_save_post_meta( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return false; }
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { return false; }
		if ( ! isset( $_POST[ self::NONCE ] ) ) { return false; }
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) { return false; }
		return current_user_can( 'edit_post', $post_id );
	}

	private static function save_object_country_meta( $post_id, $suggest_timezone = false ) {
		if ( ! self::can_save_post_meta( $post_id ) ) { return; }
		$country_key = self::posted_event_field_key( 'country' );
		if ( '' === $country_key ) { return; }
		$country = TAKA_Platform_Data::normalize_event_option_value( 'country', wp_unslash( $_POST[ $country_key ] ) );
		$country_code = TAKA_Platform_Data::country_code_for_value( $country );
		update_post_meta( $post_id, '_taka_country', $country );
		update_post_meta( $post_id, '_taka_country_code', $country_code );
		update_post_meta( $post_id, '_taka_flag', TAKA_Platform_Data::flag_for_country_code( $country_code ) );
		if ( $suggest_timezone && '' === trim( (string) get_post_meta( $post_id, '_taka_timezone', true ) ) ) {
			update_post_meta( $post_id, '_taka_timezone', TAKA_Platform_Data::timezone_for_country( $country_code ) );
		}
	}

	private static function save_event_structured_meta( $post_id ) {
		foreach ( array( 'ticket_provider', 'ticket_status', 'ticket_shop_url', 'format', 'audience', 'level', 'currency' ) as $field ) {
			$posted_key = self::posted_event_field_key( $field );
			if ( '' === $posted_key ) { continue; }
			$value = wp_unslash( $_POST[ $posted_key ] );
			$value = 'ticket_shop_url' === $field ? esc_url_raw( $value ) : TAKA_Platform_Data::normalize_event_option_value( $field, $value );
			update_post_meta( $post_id, '_taka_' . $field, $value );
		}
		$country_key = self::posted_event_field_key( 'country' );
		if ( '' !== $country_key ) {
			$country = TAKA_Platform_Data::normalize_event_option_value( 'country', wp_unslash( $_POST[ $country_key ] ) );
			$country_code = TAKA_Platform_Data::country_code_for_value( $country );
			update_post_meta( $post_id, '_taka_country', $country );
			update_post_meta( $post_id, '_taka_country_code', $country_code );
			update_post_meta( $post_id, '_taka_flag', TAKA_Platform_Data::flag_for_country_code( $country_code ) );
			if ( '' === trim( (string) get_post_meta( $post_id, '_taka_timezone', true ) ) ) {
				update_post_meta( $post_id, '_taka_timezone', TAKA_Platform_Data::timezone_for_country( $country_code ) );
			}
			if ( '' === trim( (string) get_post_meta( $post_id, '_taka_currency', true ) ) ) {
				update_post_meta( $post_id, '_taka_currency', TAKA_Platform_Data::currency_for_country( $country_code ) );
			}
		}
		$languages_key = self::posted_event_field_key( 'languages' );
		if ( '' !== $languages_key ) {
			update_post_meta( $post_id, '_taka_languages', implode( ',', TAKA_Platform_Data::normalize_language_codes( wp_unslash( $_POST[ $languages_key ] ) ) ) );
		}
	}

	private static function posted_event_field_key( $field ) {
		$prefixed = '_taka_' . $field;
		if ( isset( $_POST[ $prefixed ] ) ) { return $prefixed; }
		if ( isset( $_POST[ $field ] ) ) { return $field; }
		return '';
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
			<p class="description"><?php echo esc_html__( 'These override fields use the event’s original content language. Use the global Booking Information settings for multilingual default website text.', 'taka-platform' ); ?></p>
			<?php self::text_source( $post_id, 'booking_info_title', __( 'Booking information title', 'taka-platform' ) ); ?>
			<?php self::textarea_source( $post_id, 'booking_info_intro', __( 'Intro text', 'taka-platform' ) ); ?>
			<?php self::textarea_source( $post_id, 'booking_info_group_booking', __( 'Group booking text', 'taka-platform' ) ); ?>
			<?php self::textarea_source( $post_id, 'booking_info_multi_event_discount', __( 'Multi-event discount text', 'taka-platform' ) ); ?>
			<?php self::text( $post_id, 'booking_info_contact_email', __( 'Contact email', 'taka-platform' ) ); ?>
			<?php self::textarea_source( $post_id, 'booking_info_booking_process', __( 'Booking process text', 'taka-platform' ) ); ?>
			<?php self::textarea_source( $post_id, 'booking_info_payment_methods', __( 'Payment methods', 'taka-platform' ) ); ?>
			<?php self::textarea_source( $post_id, 'booking_info_cancellation_policy', __( 'Cancellation policy text', 'taka-platform' ) ); ?>
			<?php self::textarea_source( $post_id, 'booking_info_additional_notes', __( 'Additional notes', 'taka-platform' ) ); ?>
		</div>
		<?php
	}

	/** Sanitize booking-information settings. */
	private static function sanitize_booking_information( $posted, $global = false ) {
		$posted = is_array( $posted ) ? $posted : array();
		return array(
			'override' => ! empty( $posted['override'] ) ? '1' : '0',
			'enabled' => ! empty( $posted['enabled'] ) ? '1' : '0',
			'source_language' => TAKA_Platform_Translation_Packages::sanitize_language( $posted['source_language'] ?? 'de' ),
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
	private static function source_text_label( $label ) { return sprintf( __( '%s — Original text', 'taka-platform' ), $label ); }
	private static function translation_text_label( $label ) { return sprintf( __( '%s — Website translation', 'taka-platform' ), $label ); }
	private static function source_text_help() { return __( 'Enter the original text here. Website translations are managed in the language fields below.', 'taka-platform' ); }
	private static function render_main_editor_source_text_notice( $label ) {
		echo '<p class="description"><strong>' . esc_html( self::source_text_label( $label ) ) . '</strong><br>' . esc_html__( 'Use the main WordPress editor for this original text. Website translations are managed in the translation fields below.', 'taka-platform' ) . '</p>';
	}
	private static function text( $post_id, $field, $label ) { self::field( $label, '<input class="widefat" type="text" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( self::meta( $post_id, $field ) ) . '">' ); }
	private static function text_source( $post_id, $field, $label ) { self::field( self::source_text_label( $label ), '<input class="widefat" type="text" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( self::meta( $post_id, $field ) ) . '"><p class="description">' . esc_html( self::source_text_help() ) . '</p>' ); }
	private static function number( $post_id, $field, $label ) { self::field( $label, '<input type="number" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( self::meta( $post_id, $field ) ) . '">' ); }
	private static function url( $post_id, $field, $label ) { self::field( $label, '<input class="widefat" type="url" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( self::meta( $post_id, $field ) ) . '">' ); }
	private static function url_source( $post_id, $field, $label ) { self::field( self::source_text_label( $label ), '<input class="widefat" type="url" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( self::meta( $post_id, $field ) ) . '"><p class="description">' . esc_html( self::source_text_help() ) . '</p>' ); }
	private static function textarea( $post_id, $field, $label ) { self::field( $label, '<textarea class="widefat" rows="3" name="_taka_' . esc_attr( $field ) . '">' . esc_textarea( self::meta( $post_id, $field ) ) . '</textarea>' ); }
	private static function textarea_source( $post_id, $field, $label ) { self::field( self::source_text_label( $label ), '<textarea class="widefat" rows="3" name="_taka_' . esc_attr( $field ) . '">' . esc_textarea( self::meta( $post_id, $field ) ) . '</textarea><p class="description">' . esc_html( self::source_text_help() ) . '</p>' ); }
	private static function textarea_with_description( $post_id, $field, $label, $description ) { self::field( $label, '<textarea class="widefat" rows="4" name="_taka_' . esc_attr( $field ) . '">' . esc_textarea( self::meta( $post_id, $field ) ) . '</textarea><p class="description">' . esc_html( $description ) . '</p>' ); }
	private static function textarea_with_description_source( $post_id, $field, $label, $description ) { self::field( self::source_text_label( $label ), '<textarea class="widefat" rows="4" name="_taka_' . esc_attr( $field ) . '">' . esc_textarea( self::meta( $post_id, $field ) ) . '</textarea><p class="description">' . esc_html( $description . ' ' . self::source_text_help() ) . '</p>' ); }
	private static function checkbox( $post_id, $field, $label ) { self::field( $label, '<input type="checkbox" name="_taka_' . esc_attr( $field ) . '" value="1" ' . checked( (string) self::meta( $post_id, $field ), '1', false ) . '>' ); }
	private static function checkbox_with_hidden( $post_id, $field, $label ) { self::field( $label, '<input type="hidden" name="_taka_' . esc_attr( $field ) . '" value="0"><input type="checkbox" name="_taka_' . esc_attr( $field ) . '" value="1" ' . checked( (string) self::meta( $post_id, $field ), '1', false ) . '>' ); }
	private static function select_field( $name, $label, $current, $choices ) { $html = '<select class="widefat" name="' . esc_attr( $name ) . '">'; foreach ( $choices as $value => $choice_label ) { $html .= '<option value="' . esc_attr( $value ) . '" ' . selected( (string) $current, (string) $value, false ) . '>' . esc_html( $choice_label ) . '</option>'; } $html .= '</select>'; self::field( $label, $html ); }
	private static function route_map_label_anchor_field( $post_id ) { self::select_field( '_taka_route_map_label_anchor', __( 'Route label anchor', 'taka-platform' ), (string) self::meta( $post_id, 'route_map_label_anchor' ), array( '' => __( 'Automatic', 'taka-platform' ), 'left' => __( 'Left', 'taka-platform' ), 'center' => __( 'Center', 'taka-platform' ), 'right' => __( 'Right', 'taka-platform' ) ) ); }

	private static function content_block_choices() {
		$choices = array( '' => __( '— No reusable block —', 'taka-platform' ) );
		foreach ( TAKA_Platform_Data::get_content_blocks( false ) as $id => $block ) {
			if ( (string) ( $block['id'] ?? '' ) !== (string) $id ) { continue; }
			$label = trim( (string) ( $block['internal_name'] ?? '' ) );
			$slug = trim( (string) ( $block['slug'] ?? '' ) );
			$value = '' !== $slug ? $slug : (string) $id;
			if ( '' === $label ) { $label = '' !== $slug ? $slug : (string) $id; }
			$choices[ $value ] = $label . ( '' !== $slug ? ' (' . $slug . ')' : '' );
		}
		return $choices;
	}

	private static function content_block_choice_selected( $current, $value ) {
		$current = trim( (string) $current );
		$value = trim( (string) $value );
		if ( $current === $value ) { return true; }
		if ( '' === $current || '' === $value ) { return false; }
		$current_block = TAKA_Platform_Data::get_content_block( $current, false );
		$value_block = TAKA_Platform_Data::get_content_block( $value, false );
		return is_array( $current_block ) && is_array( $value_block ) && (string) ( $current_block['id'] ?? '' ) === (string) ( $value_block['id'] ?? '' );
	}

	private static function render_content_reference_fields( $field, $reference, $context, $label, $source_language = 'de' ) {
		$reference = TAKA_Platform_Data::normalize_content_reference( $reference, $context );
		$source_language = TAKA_Platform_Translation_Packages::sanitize_language( $source_language );
		$prefix = '_taka_' . $field;
		?>
		<div class="taka-content-reference-fields" style="border:1px solid #dcdcde;padding:12px;margin:12px 0;background:#fff;">
			<h3><?php echo esc_html( $label ); ?></h3>
			<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[context]" value="<?php echo esc_attr( $context ); ?>">
			<p><label><strong><?php echo esc_html__( 'Content block', 'taka-platform' ); ?></strong><br><select class="widefat" name="<?php echo esc_attr( $prefix ); ?>[block_id]"><?php foreach ( self::content_block_choices() as $value => $choice_label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( self::content_block_choice_selected( $reference['block_id'] ?? '', $value ) ); ?>><?php echo esc_html( $choice_label ); ?></option><?php endforeach; ?></select></label></p>
			<p><label><strong><?php echo esc_html__( 'Sort order', 'taka-platform' ); ?></strong><br><input type="number" name="<?php echo esc_attr( $prefix ); ?>[sort_order]" value="<?php echo esc_attr( (string) ( $reference['sort_order'] ?? 0 ) ); ?>"></label></p>
			<p><label><strong><?php echo esc_html__( 'Display style', 'taka-platform' ); ?></strong><br><select class="widefat" name="<?php echo esc_attr( $prefix ); ?>[display_style]"><?php foreach ( TAKA_Platform_Data::content_reference_display_styles() as $value => $choice_label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) ( $reference['display_style'] ?? 'default' ), (string) $value ); ?>><?php echo esc_html( $choice_label ); ?></option><?php endforeach; ?></select></label></p>
			<?php self::render_content_reference_custom_title_fields( $prefix, $reference['custom_title'] ?? '', $source_language ); ?>
			<?php self::render_content_reference_override_tabs( $prefix, $reference['override_translations'] ?? array(), $source_language ); ?>
			<p class="description"><?php echo esc_html__( 'Select a block to use reusable content. Choose No reusable block to use the saved local content.', 'taka-platform' ); ?></p>
		</div>
		<?php
	}

	private static function render_content_section_reference_fields( $section_key, $reference, $source_language = 'de' ) {
		$reference = TAKA_Platform_Data::normalize_content_reference( $reference, 'homepage_section' );
		$source_language = TAKA_Platform_Translation_Packages::sanitize_language( $source_language );
		$prefix = 'sections[' . sanitize_key( $section_key ) . '][content_reference]';
		?>
		<div class="taka-content-reference-fields">
			<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[context]" value="homepage_section">
			<p><label><strong><?php echo esc_html__( 'Content block', 'taka-platform' ); ?></strong><br><select class="regular-text" name="<?php echo esc_attr( $prefix ); ?>[block_id]"><?php foreach ( self::content_block_choices() as $value => $choice_label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( self::content_block_choice_selected( $reference['block_id'] ?? '', $value ) ); ?>><?php echo esc_html( $choice_label ); ?></option><?php endforeach; ?></select></label></p>
			<p><label><strong><?php echo esc_html__( 'Sort order', 'taka-platform' ); ?></strong><br><input type="number" name="<?php echo esc_attr( $prefix ); ?>[sort_order]" value="<?php echo esc_attr( (string) ( $reference['sort_order'] ?? 0 ) ); ?>"></label></p>
			<p><label><strong><?php echo esc_html__( 'Display style', 'taka-platform' ); ?></strong><br><select class="regular-text" name="<?php echo esc_attr( $prefix ); ?>[display_style]"><?php foreach ( TAKA_Platform_Data::content_reference_display_styles() as $value => $choice_label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) ( $reference['display_style'] ?? 'default' ), (string) $value ); ?>><?php echo esc_html( $choice_label ); ?></option><?php endforeach; ?></select></label></p>
			<?php self::render_content_reference_custom_title_fields( $prefix, $reference['custom_title'] ?? '', $source_language ); ?>
			<?php self::render_content_reference_override_tabs( $prefix, $reference['override_translations'] ?? array(), $source_language ); ?>
			<p class="description"><?php echo esc_html__( 'Select a block to use reusable content. Choose No reusable block to use the saved local content.', 'taka-platform' ); ?></p>
		</div>
		<?php
	}

	private static function render_content_reference_custom_title_fields( $prefix, $value, $source_language = 'de' ) {
		$default_lang = TAKA_Platform_Translation_Packages::sanitize_language( $source_language );
		echo '<details class="taka-content-reference-overrides" data-taka-source-aware data-source-language="' . esc_attr( $default_lang ) . '" data-source-mode="editable"><summary><strong>' . esc_html__( 'Custom title', 'taka-platform' ) . '</strong></summary>';
		foreach ( self::content_section_language_labels() as $lang => $label ) {
			$field_value = is_array( $value ) ? ( $value[ $lang ] ?? '' ) : ( $default_lang === $lang ? (string) $value : '' );
			$is_source_language = $lang === $default_lang;
			echo '<p data-taka-language-field-row data-taka-i18n-lang="' . esc_attr( $lang ) . '"><label><span style="display:inline-block;min-width:9rem;" data-taka-language-field-label data-source-label="' . esc_attr( self::source_text_label( $label ) ) . '" data-translation-label="' . esc_attr( sprintf( __( '%s website translation', 'taka-platform' ), $label ) ) . '">' . esc_html( $is_source_language ? self::source_text_label( $label ) : sprintf( __( '%s website translation', 'taka-platform' ), $label ) ) . '</span><br><input class="regular-text" type="text" name="' . esc_attr( $prefix . '[custom_title][' . $lang . ']' ) . '" value="' . esc_attr( (string) $field_value ) . '" data-taka-i18n-lang="' . esc_attr( $lang ) . '"></label>' . ( $is_source_language ? '<span class="description" data-taka-source-inline-note> ' . esc_html__( 'This is the original content language.', 'taka-platform' ) . '</span>' : '' ) . '</p>';
		}
		echo '</details>';
	}

	private static function render_content_reference_override_tabs( $prefix, $translations, $source_language = 'de' ) {
		$translations = TAKA_Platform_Data::normalize_content_reference( array( 'override_translations' => $translations ) )['override_translations'];
		$fields = TAKA_Platform_Data::content_block_text_fields();
		$tab_group = sanitize_key( str_replace( array( '[', ']' ), '_', $prefix ) ) . '_overrides';
		$source_language = TAKA_Platform_Translation_Packages::sanitize_language( $source_language );
		?>
		<details class="taka-content-reference-overrides">
			<summary><strong><?php echo esc_html__( 'Local text overrides', 'taka-platform' ); ?></strong></summary>
			<p class="description"><?php echo esc_html__( 'Leave fields empty to use the reusable block text. Filled values override only this reference; the original-language tab stores original text and the other tabs store website translations.', 'taka-platform' ); ?></p>
			<div class="taka-content-section-translations" data-taka-content-section-translations data-taka-source-aware data-source-language="<?php echo esc_attr( $source_language ); ?>" data-source-mode="editable" data-default-lang="<?php echo esc_attr( $source_language ); ?>">
				<div class="taka-content-section-tabs">
					<?php foreach ( self::content_section_language_labels() as $lang => $label ) : ?>
						<?php $is_source_language = $lang === $source_language; ?>
						<input class="taka-content-section-tabs__radio" type="radio" name="<?php echo esc_attr( $tab_group ); ?>" id="<?php echo esc_attr( $tab_group . '_' . $lang ); ?>" <?php checked( $lang, $source_language ); ?>>
						<label class="taka-content-section-tabs__tab" for="<?php echo esc_attr( $tab_group . '_' . $lang ); ?>" data-taka-language-tab data-taka-i18n-lang="<?php echo esc_attr( $lang ); ?>" data-language-label="<?php echo esc_attr( $label ); ?>"><?php echo esc_html( $is_source_language ? sprintf( __( '%s original', 'taka-platform' ), $label ) : $label ); ?></label>
						<div class="taka-content-section-tabs__panel" data-taka-language-panel data-taka-i18n-lang="<?php echo esc_attr( $lang ); ?>">
							<p class="description" data-taka-source-panel-help><?php echo esc_html( $is_source_language ? __( 'This is the original content language. Edit the original text here. Website translations are entered in the other language tabs.', 'taka-platform' ) : __( 'Enter the website translation for this language based on the original text.', 'taka-platform' ) ); ?></p>
							<?php foreach ( $fields as $field => $field_label ) : ?>
								<?php $name = $prefix . '[override_translations][' . $lang . '][' . $field . ']'; ?>
								<?php $value = $translations[ $lang ][ $field ] ?? ''; ?>
								<p class="taka-content-section-tabs__field">
									<label><strong data-taka-language-field-label data-source-label="<?php echo esc_attr( self::source_text_label( $field_label ) ); ?>" data-translation-label="<?php echo esc_attr( self::translation_text_label( $field_label ) ); ?>"><?php echo esc_html( $is_source_language ? self::source_text_label( $field_label ) : self::translation_text_label( $field_label ) ); ?></strong><br>
										<?php if ( 'body' === $field ) : ?>
											<textarea class="large-text" rows="4" name="<?php echo esc_attr( $name ); ?>"><?php echo esc_textarea( (string) $value ); ?></textarea>
										<?php else : ?>
											<input class="regular-text" type="<?php echo esc_attr( 'button_url' === $field ? 'url' : 'text' ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>">
										<?php endif; ?>
									</label>
								</p>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</details>
		<?php
	}

	private static function save_content_reference_meta( $post_id, $field, $context ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		$key = '_taka_' . $field;
		$posted = isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : array();
		update_post_meta( $post_id, $key, TAKA_Platform_Data::normalize_content_reference( $posted, $context ) );
	}

	private static function render_content_block_used_by( $post_id ) {
		$contexts = TAKA_Platform_Data::content_block_usage_contexts();
		$block = TAKA_Platform_Data::get_content_block( (string) $post_id, false );
		$ids = array_filter( array( (string) $post_id, $block['slug'] ?? '' ) );
		$used = array();
		foreach ( $ids as $id ) {
			$used = array_merge( $used, $contexts[ $id ] ?? array() );
		}
		echo '<div class="taka-content-block-used-by"><h3>' . esc_html__( 'Used by', 'taka-platform' ) . '</h3>';
		if ( empty( $used ) ) {
			echo '<p class="description">' . esc_html__( 'No references found yet.', 'taka-platform' ) . '</p>';
		} else {
			echo '<ul>';
			foreach ( array_unique( $used ) as $context ) { echo '<li>' . esc_html( $context ) . '</li>'; }
			echo '</ul>';
		}
		echo '</div>';
	}

	private static function event_option_select( $post_id, $field, $label, $legacy_context = '' ) {
		$raw = (string) self::meta( $post_id, $field );
		$matched_key = TAKA_Platform_Data::option_key_for_value( $field, $raw );
		$current = '' !== $matched_key ? $matched_key : $raw;
		$choices = array( '' => __( '— Select —', 'taka-platform' ) ) + TAKA_Platform_Data::option_list_choices( $field, TAKA_Platform_Data::platform_fallback_language() );
		if ( '' !== $raw && '' === $matched_key && ! isset( $choices[ $raw ] ) ) {
			$choices[ $raw ] = sprintf( __( 'Custom / legacy: %s', 'taka-platform' ), $raw );
		}
		$html = '<select class="widefat" name="_taka_' . esc_attr( $field ) . '">';
		foreach ( $choices as $value => $choice_label ) {
			$html .= '<option value="' . esc_attr( $value ) . '" ' . selected( $current, (string) $value, false ) . '>' . esc_html( $choice_label ) . '</option>';
		}
		$html .= '</select>';
		if ( '' !== $raw && '' === $matched_key ) {
			$legacy_context = '' !== trim( (string) $legacy_context ) ? (string) $legacy_context : __( 'event', 'taka-platform' );
			$html .= '<p class="description">' . esc_html( sprintf( __( 'This %s uses a custom legacy value. It will continue to display unless you choose a configured option.', 'taka-platform' ), $legacy_context ) ) . '</p>';
		}
		self::field( $label, $html );
	}

	private static function render_derived_country_fields( $post_id, $show_suggestions = true ) {
		$country = (string) self::meta( $post_id, 'country' );
		$code = TAKA_Platform_Data::country_code_for_value( self::meta( $post_id, 'country_code' ) ?: $country );
		$flag = TAKA_Platform_Data::flag_for_country_code( $code );
		$timezone = TAKA_Platform_Data::timezone_for_country( $code );
		$currency = TAKA_Platform_Data::currency_for_country( $code );
		$html = '<code>' . esc_html( $code ?: '—' ) . '</code> ' . esc_html( $flag );
		if ( $show_suggestions && ( '' !== $timezone || '' !== $currency ) ) {
			$html .= '<p class="description">' . esc_html( trim( sprintf( __( 'Suggested timezone: %1$s. Suggested currency: %2$s.', 'taka-platform' ), $timezone ?: '—', $currency ?: '—' ) ) ) . '</p>';
		}
		self::field( __( 'Derived country data', 'taka-platform' ), $html );
	}

	private static function language_multiselect( $post_id, $field, $label ) {
		$current = TAKA_Platform_Data::normalize_language_codes( self::meta( $post_id, $field ) );
		$html = '<select class="widefat" name="_taka_' . esc_attr( $field ) . '[]" multiple size="7">';
		foreach ( TAKA_Platform_Data::language_choices() as $code => $language_label ) {
			$html .= '<option value="' . esc_attr( $code ) . '" ' . selected( in_array( (string) $code, $current, true ), true, false ) . '>' . esc_html( $language_label ) . '</option>';
		}
		$html .= '</select><p class="description">' . esc_html__( 'Select languages used during the event. This does not control website translations or the original content language. Hold Command/Ctrl to select multiple languages.', 'taka-platform' ) . '</p>';
		self::field( $label, $html );
	}

	private static function render_option_lists_settings( $option_lists ) {
		$languages = TAKA_Platform_Translation_Packages::language_labels();
		foreach ( TAKA_Platform_Data::event_option_list_fields() as $list_key => $list_label ) :
			$list = $option_lists[ $list_key ] ?? array( 'label' => $list_label, 'options' => array() );
			$options = $list['options'] ?? array();
			$options[] = array( 'key' => '', 'label' => '', 'source_language' => 'de', 'translations' => array(), 'sort_order' => 100, 'enabled' => '1', 'icon' => '', 'aliases' => array() );
			?>
			<div class="postbox" style="padding:1rem;max-width:1080px;">
				<h3><?php echo esc_html( $list['label'] ?? $list_label ); ?></h3>
				<input type="hidden" name="option_lists[<?php echo esc_attr( $list_key ); ?>][label]" value="<?php echo esc_attr( $list['label'] ?? $list_label ); ?>">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Key', 'taka-platform' ); ?></th>
							<th><?php echo esc_html__( 'Label — Original text', 'taka-platform' ); ?></th>
							<th><?php echo esc_html__( 'Icon', 'taka-platform' ); ?></th>
							<th><?php echo esc_html__( 'Aliases', 'taka-platform' ); ?></th>
							<th><?php echo esc_html__( 'Original label language', 'taka-platform' ); ?></th>
							<th><?php echo esc_html__( 'Website translations', 'taka-platform' ); ?></th>
							<th><?php echo esc_html__( 'Sort order', 'taka-platform' ); ?></th>
							<th><?php echo esc_html__( 'Enabled', 'taka-platform' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $options as $index => $option ) : ?>
							<?php
							$prefix = 'option_lists[' . $list_key . '][options][' . $index . ']';
							$source_language = TAKA_Platform_Translation_Packages::sanitize_language( $option['source_language'] ?? 'de' );
							?>
							<tr data-taka-source-language-scope>
								<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[key]" value="<?php echo esc_attr( $option['key'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'new_option_key', 'taka-platform' ); ?>"></td>
								<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[label]" value="<?php echo esc_attr( $option['label'] ?? '' ); ?>"></td>
								<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[icon]" value="<?php echo esc_attr( $option['icon'] ?? '' ); ?>" style="width:5rem;"></td>
								<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[aliases]" value="<?php echo esc_attr( implode( ', ', (array) ( $option['aliases'] ?? array() ) ) ); ?>"></td>
								<td>
									<select name="<?php echo esc_attr( $prefix ); ?>[source_language]" data-taka-source-language-select="1">
										<?php foreach ( $languages as $lang => $label ) : ?>
											<option value="<?php echo esc_attr( $lang ); ?>" <?php selected( $source_language, $lang ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td data-taka-source-aware data-source-language="<?php echo esc_attr( $source_language ); ?>" data-source-mode="disabled-source">
									<?php foreach ( $languages as $lang => $label ) : ?>
										<?php
										$is_source_language = $lang === $source_language;
										$translation_name = $prefix . '[translations][' . $lang . ']';
										$translation_value = (string) ( $option['translations'][ $lang ] ?? '' );
										?>
										<label style="display:block;margin-bottom:4px;" data-taka-language-field-row data-taka-i18n-lang="<?php echo esc_attr( $lang ); ?>">
											<span style="display:inline-block;min-width:7rem;" data-taka-language-field-label data-source-label="<?php echo esc_attr( sprintf( __( '%s original', 'taka-platform' ), $label ) ); ?>" data-translation-label="<?php echo esc_attr( sprintf( __( '%s website translation', 'taka-platform' ), $label ) ); ?>"><?php echo esc_html( $is_source_language ? sprintf( __( '%s original', 'taka-platform' ), $label ) : sprintf( __( '%s website translation', 'taka-platform' ), $label ) ); ?></span>
											<?php if ( $is_source_language ) : ?>
												<input type="hidden" name="<?php echo esc_attr( $translation_name ); ?>" value="<?php echo esc_attr( $translation_value ); ?>" data-taka-source-hidden>
											<?php endif; ?>
											<input type="text" name="<?php echo esc_attr( $translation_name ); ?>" value="<?php echo esc_attr( $is_source_language && '' === $translation_value ? ( $option['label'] ?? '' ) : $translation_value ); ?>" data-taka-i18n-lang="<?php echo esc_attr( $lang ); ?>" data-taka-source-disable-when-source="1" <?php disabled( $is_source_language ); ?>>
											<?php if ( $is_source_language ) : ?>
												<span class="description" data-taka-source-inline-note><?php echo esc_html__( 'Edit the original label column.', 'taka-platform' ); ?></span>
											<?php endif; ?>
										</label>
									<?php endforeach; ?>
								</td>
								<td><input type="number" name="<?php echo esc_attr( $prefix ); ?>[sort_order]" value="<?php echo esc_attr( (string) ( $option['sort_order'] ?? 0 ) ); ?>"></td>
								<td><label><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[enabled]" value="1" <?php checked( '1', (string) ( $option['enabled'] ?? '1' ) ); ?>> <?php echo esc_html__( 'Enabled', 'taka-platform' ); ?></label></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description"><?php echo esc_html__( 'Leave the final blank row empty, or fill it to add a new option.', 'taka-platform' ); ?></p>
			</div>
			<?php
		endforeach;
	}

	private static function render_event_advanced_unused_fields( $post_id ) {
		?>
		<details class="taka-event-advanced-fields">
			<summary><strong><?php echo esc_html__( 'Advanced / currently unused', 'taka-platform' ); ?></strong></summary>
			<p class="description"><?php echo esc_html__( 'These fields are saved for compatibility but are not currently shown in the public ticket detail layout.', 'taka-platform' ); ?></p>
			<?php self::textarea_source( $post_id, 'long_description', __( 'Long description', 'taka-platform' ) ); ?>
			<?php self::textarea_source( $post_id, 'ticket_card_text', __( 'Ticket card text', 'taka-platform' ) ); ?>
			<?php self::media_field( $post_id, 'gallery_image_ids', __( 'Gallery images', 'taka-platform' ), true, __( 'Select gallery images', 'taka-platform' ) ); ?>
			<?php self::text( $post_id, 'photo_credit', __( 'Photo credit', 'taka-platform' ) ); ?>
		</details>
		<?php
	}

	private static function render_object_source_language_field( $post_id ) {
		$current = (string) get_post_meta( $post_id, '_taka_source_language', true ) ?: 'de';
		$html = '<select name="_taka_source_language" data-taka-source-language-select="1">';
		foreach ( TAKA_Platform_Translation_Packages::language_labels() as $lang => $label ) {
			$html .= '<option value="' . esc_attr( $lang ) . '" ' . selected( $current, $lang, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$html .= '</select><p class="description">' . esc_html__( 'Select the language used by the original text fields on this screen. Website translations are edited separately.', 'taka-platform' ) . '</p>';
		self::field( __( 'Original content language', 'taka-platform' ), $html );
	}

	private static function render_object_text_translation_fields( $post_id, $object_type, $source_values = array(), $advanced_fields = array() ) {
		$fields = TAKA_Platform_Data::translatable_text_fields( $object_type );
		if ( empty( $fields ) ) { return; }
		$advanced_keys = array_fill_keys( array_map( 'sanitize_key', (array) $advanced_fields ), true );
		$primary_fields = array_diff_key( $fields, $advanced_keys );
		$advanced_fields = array_intersect_key( $fields, $advanced_keys );
		$translations = TAKA_Platform_Data::normalize_object_text_translations( get_post_meta( $post_id, '_taka_text_translations', true ), $fields );
		$source_language = TAKA_Platform_Translation_Packages::sanitize_language( get_post_meta( $post_id, '_taka_source_language', true ) ?: 'de' );
		$languages = self::content_section_language_labels();
		$default_tab_language = isset( $languages[ $source_language ] ) ? $source_language : TAKA_Platform_Data::platform_fallback_language();
		?>
		<div class="taka-content-section-translations" data-taka-content-section-translations data-taka-source-aware data-source-language="<?php echo esc_attr( $default_tab_language ); ?>" data-source-mode="editable" data-default-lang="<?php echo esc_attr( $default_tab_language ); ?>">
			<h3><?php echo esc_html__( 'Website text translations', 'taka-platform' ); ?></h3>
			<p class="description"><?php echo esc_html__( 'Edit original text in the original-language tab. Other tabs hold website translations.', 'taka-platform' ); ?></p>
			<div class="taka-content-section-tabs">
				<?php $tab_group = 'taka_object_text_' . $object_type . '_' . $post_id; ?>
				<?php foreach ( $languages as $lang => $label ) : ?>
					<?php $is_source_language = $lang === $source_language; ?>
					<input class="taka-content-section-tabs__radio" type="radio" name="<?php echo esc_attr( $tab_group ); ?>" id="<?php echo esc_attr( $tab_group . '_' . $lang ); ?>" <?php checked( $lang, $default_tab_language ); ?>>
					<label class="taka-content-section-tabs__tab" for="<?php echo esc_attr( $tab_group . '_' . $lang ); ?>" data-taka-language-tab data-taka-i18n-lang="<?php echo esc_attr( $lang ); ?>" data-language-label="<?php echo esc_attr( $label ); ?>"><?php echo esc_html( $is_source_language ? sprintf( __( '%s original', 'taka-platform' ), $label ) : $label ); ?></label>
					<div class="taka-content-section-tabs__panel" data-taka-language-panel data-taka-i18n-lang="<?php echo esc_attr( $lang ); ?>">
						<?php if ( $is_source_language ) : ?>
							<p class="description" data-taka-source-panel-help><?php echo esc_html__( 'This is the original content language. Edit the original text here. Website translations are entered in the other language tabs.', 'taka-platform' ); ?></p>
						<?php else : ?>
							<p class="description" data-taka-source-panel-help><?php echo esc_html__( 'Enter the website translation for this language based on the original text.', 'taka-platform' ); ?></p>
						<?php endif; ?>
						<?php self::render_object_translation_textareas( $post_id, $lang, $primary_fields, $translations, $source_values, $is_source_language ); ?>
						<?php if ( ! empty( $advanced_fields ) ) : ?>
							<details class="taka-event-advanced-fields">
								<summary><strong><?php echo esc_html__( 'Advanced / currently unused website translations', 'taka-platform' ); ?></strong></summary>
								<p class="description"><?php echo esc_html__( 'These website translation fields are kept for compatibility but are not currently shown in the public ticket detail layout.', 'taka-platform' ); ?></p>
								<?php self::render_object_translation_textareas( $post_id, $lang, $advanced_fields, $translations, $source_values, $is_source_language ); ?>
							</details>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private static function render_object_translation_textareas( $post_id, $lang, $fields, $translations, $source_values, $is_source_language = false ) {
		foreach ( $fields as $field => $field_label ) :
			$placeholder = $source_values[ $field ] ?? self::meta( $post_id, 'description' === $field ? 'short_description' : $field );
			$value = $is_source_language ? $placeholder : ( $translations[ $field ][ $lang ] ?? '' );
			$name = 'taka_platform_text_translations[' . $field . '][' . $lang . ']';
			$display_value = (string) $value;
			?>
			<p class="taka-content-section-tabs__field">
				<label><strong data-taka-language-field-label data-source-label="<?php echo esc_attr( self::source_text_label( $field_label ) ); ?>" data-translation-label="<?php echo esc_attr( self::translation_text_label( $field_label ) ); ?>"><?php echo esc_html( $is_source_language ? self::source_text_label( $field_label ) : self::translation_text_label( $field_label ) ); ?></strong><br>
					<?php if ( $is_source_language ) : ?>
						<input type="hidden" name="taka_platform_text_source_previous[<?php echo esc_attr( $field ); ?>][<?php echo esc_attr( $lang ); ?>]" value="<?php echo esc_attr( $display_value ); ?>">
					<?php endif; ?>
					<textarea class="large-text" rows="3" name="<?php echo esc_attr( $name ); ?>" placeholder="<?php echo esc_attr( (string) $placeholder ); ?>" data-taka-i18n-lang="<?php echo esc_attr( $lang ); ?>"><?php echo esc_textarea( $display_value ); ?></textarea>
				</label>
			</p>
			<?php
		endforeach;
	}

	private static function save_object_text_translations( $post_id, $object_type ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		$fields = TAKA_Platform_Data::translatable_text_fields( $object_type );
		$old_source_language = TAKA_Platform_Translation_Packages::sanitize_language( get_post_meta( $post_id, '_taka_source_language', true ) ?: 'de' );
		$source_language = TAKA_Platform_Translation_Packages::sanitize_language( wp_unslash( $_POST['_taka_source_language'] ?? 'de' ) );
		update_post_meta( $post_id, '_taka_source_language', $source_language );
		$posted = isset( $_POST['taka_platform_text_translations'] ) && is_array( $_POST['taka_platform_text_translations'] ) ? wp_unslash( $_POST['taka_platform_text_translations'] ) : array();
		$previous = isset( $_POST['taka_platform_text_source_previous'] ) && is_array( $_POST['taka_platform_text_source_previous'] ) ? wp_unslash( $_POST['taka_platform_text_source_previous'] ) : array();
		self::save_object_source_text_from_translations( $post_id, $object_type, $fields, $posted, $previous, $source_language, $old_source_language );
		update_post_meta( $post_id, '_taka_text_translations', TAKA_Platform_Data::normalize_object_text_translations( $posted, $fields ) );
	}

	private static function save_object_source_text_from_translations( $post_id, $object_type, $fields, $posted, $previous, $source_language, $old_source_language ) {
		foreach ( array_keys( $fields ) as $field ) {
			if ( ! isset( $posted[ $field ][ $source_language ] ) ) { continue; }
			$value = (string) $posted[ $field ][ $source_language ];
			$previous_value = isset( $previous[ $field ][ $source_language ] ) ? (string) $previous[ $field ][ $source_language ] : null;
			$source_language_changed = $source_language !== $old_source_language;
			if ( ! $source_language_changed && null !== $previous_value && $value === $previous_value ) {
				continue;
			}
			if ( $source_language_changed && '' === trim( $value ) ) {
				continue;
			}
			self::update_object_source_text_field( $post_id, $object_type, $field, $value );
		}
	}

	private static function update_object_source_text_field( $post_id, $object_type, $field, $value ) {
		$post_content_fields = array(
			'content_block' => array( 'body' => true ),
		);
		if ( ! empty( $post_content_fields[ $object_type ][ $field ] ) ) {
			self::update_post_content_source_text( $post_id, $object_type, wp_kses_post( $value ) );
			return;
		}

		$meta_fields = array(
			'event' => array(
				'description' => 'short_description',
				'subtitle' => 'subtitle',
				'long_description' => 'long_description',
				'ticket_card_text' => 'ticket_card_text',
				'ticket_tab_label' => 'ticket_tab_label',
				'accessibility' => 'accessibility',
				'notes' => 'notes',
				'parking' => 'parking',
			),
			'venue' => array(
				'parking' => 'parking',
				'accessibility' => 'accessibility',
				'notes' => 'notes',
			),
			'organizer' => array(
				'description' => 'description',
			),
			'content_block' => array(
				'kicker' => 'kicker',
				'title' => 'block_title',
				'subtitle' => 'subtitle',
				'button_label' => 'button_label',
				'button_url' => 'button_url',
			),
		);
		if ( empty( $meta_fields[ $object_type ][ $field ] ) ) { return; }
		$meta_field = $meta_fields[ $object_type ][ $field ];
		if ( 'organizer' === $object_type && 'description' === $field ) {
			$clean = wp_kses_post( $value );
		} else {
			$clean = 'button_url' === $field ? esc_url_raw( $value ) : ( in_array( $field, array( 'subtitle', 'ticket_tab_label', 'kicker', 'title', 'button_label' ), true ) ? sanitize_text_field( $value ) : sanitize_textarea_field( $value ) );
		}
		update_post_meta( $post_id, '_taka_' . $meta_field, $clean );
	}

	private static function update_post_content_source_text( $post_id, $object_type, $content ) {
		$post_type = get_post_type( $post_id );
		$callbacks = array(
			'organizer' => 'save_organizer',
			'content_block' => 'save_content_block',
		);
		if ( empty( $callbacks[ $object_type ] ) || ! $post_type ) { return; }
		remove_action( 'save_post_' . $post_type, array( __CLASS__, $callbacks[ $object_type ] ) );
		wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );
		add_action( 'save_post_' . $post_type, array( __CLASS__, $callbacks[ $object_type ] ) );
	}

	private static function sanitize_dynamic_text( $value, $textarea = false ) {
		$callback = $textarea ? 'sanitize_textarea_field' : 'sanitize_text_field';
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( TAKA_Platform_I18n::instance()->get_all_languages() as $lang ) { $out[ $lang ] = $callback( $value[ $lang ] ?? '' ); }
			return $out;
		}
		return $callback( $value );
	}
	private static function sanitize_dynamic_source_text( $source_text, $existing_value, $source_language = 'de', $textarea = false ) {
		$callback = $textarea ? 'sanitize_textarea_field' : 'sanitize_text_field';
		$source_language = TAKA_Platform_Translation_Packages::sanitize_language( $source_language );
		$source_text = $callback( $source_text );
		if ( is_array( $existing_value ) ) {
			$out = self::sanitize_dynamic_text( $existing_value, $textarea );
			$out[ $source_language ] = $source_text;
			return $out;
		}
		return $source_text;
	}
	private static function content_section_language_labels() {
		return array(
			'de' => 'Deutsch',
			'en' => 'English',
			'fr' => 'Français',
			'nl' => 'Nederlands',
			'lb' => 'Lëtzebuergesch',
			'fi' => 'Suomi',
			'ja' => '日本語',
		);
	}
	private static function content_section_admin_translation_value( $translations, $field ) {
		$languages = array_values( array_unique( array_filter( array_merge( array( TAKA_Platform_Data::default_content_section_language(), 'en' ), TAKA_Platform_Data::content_section_languages() ) ) ) );
		foreach ( $languages as $lang ) {
			$value = $translations[ $lang ][ $field ] ?? '';
			if ( '' !== trim( (string) $value ) ) { return (string) $value; }
		}
		return '';
	}
	private static function settings_source_text_row( $name, $label, $value, $source_language = 'de' ) { self::settings_source_text_setting_row( $name, $label, $value, $source_language, false ); }
	private static function settings_source_textarea_row( $name, $label, $value, $source_language = 'de' ) { self::settings_source_text_setting_row( $name, $label, $value, $source_language, true ); }
	private static function settings_source_text_setting_row( $name, $label, $value, $source_language = 'de', $textarea = false ) {
		$source_language = TAKA_Platform_Translation_Packages::sanitize_language( $source_language );
		$field_value = self::dynamic_text_source_value( $value, $source_language );
		echo '<tr data-taka-source-aware data-source-language="' . esc_attr( $source_language ) . '"><th scope="row"><label>' . esc_html( self::source_text_label( $label ) ) . '</label></th><td>';
		if ( $textarea ) {
			echo '<textarea class="large-text" rows="4" name="' . esc_attr( $name ) . '">' . esc_textarea( (string) $field_value ) . '</textarea>';
		} else {
			echo '<input class="regular-text" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $field_value ) . '">';
		}
		echo '<p class="description" data-taka-source-help>' . esc_html( sprintf( __( 'Original content language: %s. Enter the original text here; website translations are managed separately.', 'taka-platform' ), strtoupper( $source_language ) ) ) . '</p></td></tr>';
	}
	private static function dynamic_text_source_value( $value, $source_language = 'de' ) {
		$source_language = TAKA_Platform_Translation_Packages::sanitize_language( $source_language );
		if ( is_array( $value ) ) {
			if ( '' !== trim( (string) ( $value[ $source_language ] ?? '' ) ) ) {
				return (string) $value[ $source_language ];
			}
			foreach ( array( TAKA_Platform_Data::platform_fallback_language(), 'en' ) as $fallback_language ) {
				if ( '' !== trim( (string) ( $value[ $fallback_language ] ?? '' ) ) ) {
					return (string) $value[ $fallback_language ];
				}
			}
			foreach ( $value as $candidate ) {
				if ( '' !== trim( (string) $candidate ) ) {
					return (string) $candidate;
				}
			}
			return '';
		}
		return (string) $value;
	}
	private static function settings_multilingual_text_row( $name, $label, $value, $source_language = 'de' ) { self::settings_multilingual_row( $name, $label, $value, false, $source_language ); }
	private static function settings_multilingual_textarea_row( $name, $label, $value, $source_language = 'de' ) { self::settings_multilingual_row( $name, $label, $value, true, $source_language ); }
	private static function settings_multilingual_row( $name, $label, $value, $textarea = false, $source_language = 'de' ) {
		$source_language = TAKA_Platform_Translation_Packages::sanitize_language( $source_language );
		echo '<tr data-taka-source-aware data-source-language="' . esc_attr( $source_language ) . '"><th scope="row"><label>' . esc_html( $label ) . '</label></th><td>';
		foreach ( TAKA_Platform_Translation_Packages::language_labels() as $lang => $language_label ) {
			$is_source_language = $lang === $source_language;
			$field_name = $name . '[' . $lang . ']';
			$field_value = is_array( $value ) ? ( $value[ $lang ] ?? '' ) : ( $is_source_language ? $value : '' );
			$field_label = $is_source_language ? self::source_text_label( $language_label ) : sprintf( __( '%s website translation', 'taka-platform' ), $language_label );
			echo '<p data-taka-language-field-row data-taka-i18n-lang="' . esc_attr( $lang ) . '"><label><strong data-taka-language-field-label data-source-label="' . esc_attr( self::source_text_label( $language_label ) ) . '" data-translation-label="' . esc_attr( sprintf( __( '%s website translation', 'taka-platform' ), $language_label ) ) . '">' . esc_html( $field_label ) . '</strong><br>';
			if ( $textarea ) {
				echo '<textarea class="large-text" rows="3" name="' . esc_attr( $field_name ) . '" data-taka-i18n-lang="' . esc_attr( $lang ) . '">' . esc_textarea( (string) $field_value ) . '</textarea>';
			} else {
				echo '<input class="regular-text" type="text" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( (string) $field_value ) . '" data-taka-i18n-lang="' . esc_attr( $lang ) . '">';
			}
			echo '</label>';
			if ( $is_source_language ) {
				echo '<span class="description" data-taka-source-inline-note> ' . esc_html__( 'This is the original content language.', 'taka-platform' ) . '</span>';
			}
			echo '</p>';
		}
		echo '<p class="description">' . esc_html__( 'Only the original-language field stores original text. All other fields are website translations.', 'taka-platform' ) . '</p></td></tr>';
	}
	private static function settings_text_row( $name, $label, $value ) { echo '<tr><th scope="row"><label>' . esc_html( $label ) . '</label></th><td><input class="regular-text" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"></td></tr>'; }
	private static function settings_textarea_row( $name, $label, $value ) { echo '<tr><th scope="row"><label>' . esc_html( $label ) . '</label></th><td><textarea class="large-text" rows="4" name="' . esc_attr( $name ) . '">' . esc_textarea( (string) $value ) . '</textarea></td></tr>'; }
	private static function html_attrs( $attributes ) {
		$html = '';
		foreach ( (array) $attributes as $name => $value ) {
			if ( false === $value || null === $value ) { continue; }
			$html .= ' ' . esc_attr( $name );
			if ( true !== $value ) {
				$html .= '="' . esc_attr( (string) $value ) . '"';
			}
		}
		return $html;
	}
	private static function settings_select_row( $name, $label, $value, $options, $attributes = array() ) { echo '<tr><th scope="row"><label>' . esc_html( $label ) . '</label></th><td><select name="' . esc_attr( $name ) . '"' . self::html_attrs( $attributes ) . '>'; foreach ( $options as $key => $option_label ) { echo '<option value="' . esc_attr( $key ) . '" ' . selected( (string) $value, (string) $key, false ) . '>' . esc_html( $option_label ) . '</option>'; } echo '</select></td></tr>'; }
	private static function settings_media_row( $id_name, $url_name, $input_id, $label, $id, $url, $multiple = false ) { $id_value = is_array( $id ) ? implode( ',', array_map( 'absint', $id ) ) : (string) $id; $url_value = is_array( $url ) ? implode( "\n", array_map( 'esc_url_raw', $url ) ) : (string) $url; echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><input id="' . esc_attr( $input_id ) . '" type="hidden" name="' . esc_attr( $id_name ) . '" value="' . esc_attr( $id_value ) . '"> <button type="button" class="button" data-taka-media-pick data-multiple="' . ( $multiple ? '1' : '0' ) . '" data-target="' . esc_attr( $input_id ) . '" data-preview="' . esc_attr( $input_id . '_preview' ) . '">' . esc_html__( 'Select image', 'taka-platform' ) . '</button> <button type="button" class="button" data-taka-media-remove data-target="' . esc_attr( $input_id ) . '" data-preview="' . esc_attr( $input_id . '_preview' ) . '">' . esc_html__( 'Remove image', 'taka-platform' ) . '</button><div id="' . esc_attr( $input_id . '_preview' ) . '">'; $multiple ? self::image_previews( $id_value ) : self::image_preview( absint( $id_value ), $url_value ); echo '</div><p><label>' . esc_html__( 'Fallback URL', 'taka-platform' ) . '<br>'; if ( $multiple ) { echo '<textarea class="large-text" rows="3" name="' . esc_attr( $url_name ) . '">' . esc_textarea( $url_value ) . '</textarea>'; } else { echo '<input class="regular-text" type="url" name="' . esc_attr( $url_name ) . '" value="' . esc_attr( $url_value ) . '">'; } echo '</label></p></td></tr>'; }

	private static function csv_to_absints( $value ) { return array_values( array_filter( array_map( 'absint', preg_split( '/\s*,\s*/', (string) $value ) ) ) ); }
	private static function lines_to_array( $value ) { return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $value ) ) ) ); }
	private static function sanitize_decimal( $value, $default ) { $value = (string) $value; return preg_match( '/^(0(\.\d+)?|1(\.0+)?)$/', $value ) ? $value : $default; }
	private static function media_field( $post_id, $field, $label, $multiple = false, $button_label = null ) { $value = (string) self::meta( $post_id, $field ); $input_id = 'taka_' . $field . '_' . $post_id; $button_label = $button_label ?: __( 'Select image', 'taka-platform' ); $html = '<input id="' . esc_attr( $input_id ) . '" type="hidden" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( $value ) . '"> <button type="button" class="button" data-taka-media-pick data-multiple="' . ( $multiple ? '1' : '0' ) . '" data-target="' . esc_attr( $input_id ) . '" data-preview="' . esc_attr( $input_id . '_preview' ) . '">' . esc_html( $button_label ) . '</button> <button type="button" class="button" data-taka-media-remove data-target="' . esc_attr( $input_id ) . '" data-preview="' . esc_attr( $input_id . '_preview' ) . '">' . esc_html__( 'Remove image', 'taka-platform' ) . '</button><div id="' . esc_attr( $input_id . '_preview' ) . '">'; ob_start(); self::image_previews( $value ); $html .= ob_get_clean() . '</div>'; self::field( $label, $html ); }
	private static function organizer_relation( $post_id, $field, $label ) { $current = (int) self::meta( $post_id, $field ); $assigned = self::current_user_is_platform_admin() ? array() : self::get_current_user_organizer_ids(); if ( ! self::current_user_is_platform_admin() && 0 === $current && 1 === count( $assigned ) ) { $current = (int) $assigned[0]; } $args = array( 'post_type' => TAKA_PLATFORM_CPT_ORGANIZER, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ); if ( ! self::current_user_is_platform_admin() ) { $args['post__in'] = ! empty( $assigned ) ? $assigned : array( 0 ); } $posts = get_posts( $args ); $html = '<select name="_taka_' . esc_attr( $field ) . '"><option value="">—</option>'; foreach ( $posts as $post ) { $html .= '<option value="' . esc_attr( $post->ID ) . '" ' . selected( $current, $post->ID, false ) . '>' . esc_html( get_the_title( $post ) ) . '</option>'; } $html .= '</select>'; self::field( $label, $html ); }
	private static function relation( $post_id, $field, $label, $post_type ) { $current = (int) self::meta( $post_id, $field ); $posts = get_posts( array( 'post_type' => $post_type, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ); $html = '<select name="_taka_' . esc_attr( $field ) . '"><option value="">—</option>'; foreach ( $posts as $post ) { $html .= '<option value="' . esc_attr( $post->ID ) . '" ' . selected( $current, $post->ID, false ) . '>' . esc_html( get_the_title( $post ) ) . '</option>'; } $html .= '</select>'; self::field( $label, $html ); }
	private static function image_preview( $id, $fallback_url = '' ) { $url = $id && function_exists( 'wp_get_attachment_image_url' ) ? wp_get_attachment_image_url( $id, 'thumbnail' ) : $fallback_url; if ( $url ) { echo '<img src="' . esc_url( $url ) . '" style="max-width:180px;height:auto;display:block;margin-top:8px;" alt="">'; } }
	private static function image_previews( $ids ) { foreach ( array_filter( array_map( 'absint', preg_split( '/\s*,\s*/', (string) $ids ) ) ) as $id ) { self::image_preview( $id ); } }
	private static function video_preview( $id, $fallback_url = '' ) { $url = $id && function_exists( 'wp_get_attachment_url' ) ? wp_get_attachment_url( $id ) : $fallback_url; if ( $url ) { $label = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ); echo '<span class="taka-admin-media-preview taka-admin-media-preview--video"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ?: __( 'Selected video', 'taka-platform' ) ) . '</a></span>'; } }
	}

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
		add_action( 'admin_init', array( __CLASS__, 'ensure_capabilities' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_taka_organizer', array( __CLASS__, 'save_organizer' ) );
		add_action( 'save_post_taka_venue', array( __CLASS__, 'save_venue' ) );
			add_action( 'save_post_taka_event', array( __CLASS__, 'save_event' ) );
			add_action( 'admin_post_taka_tour_save_media', array( __CLASS__, 'handle_save_media' ) );
			add_action( 'admin_post_taka_tour_import_config', array( __CLASS__, 'handle_import_config' ) );
			add_action( 'admin_post_taka_platform_save_hero', array( __CLASS__, 'handle_save_hero' ) );
			add_action( 'admin_post_taka_platform_save_sections', array( __CLASS__, 'handle_save_sections' ) );
	}


	/** Ensure administrator capabilities are ready for future organizer self-service. */
	public static function ensure_capabilities() {
		if ( ! function_exists( 'get_role' ) ) { return; }
		$role = get_role( 'administrator' );
		if ( ! $role ) { return; }
		foreach ( array( 'manage_taka_tour', 'edit_taka_events', 'edit_taka_organizers', 'edit_taka_venues' ) as $cap ) {
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
		register_post_type(
			$post_type,
			array(
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
			)
		);
	}

	/** Register menu pages. */
	public static function register_menu() {
		add_menu_page( __( 'TAKA Platform', 'taka-platform' ), __( 'TAKA Platform', 'taka-platform' ), 'manage_options', 'taka-platform', array( __CLASS__, 'render_dashboard' ), 'dashicons-tickets-alt', 28 );
			add_submenu_page( 'taka-platform', __( 'Dashboard', 'taka-platform' ), __( 'Dashboard', 'taka-platform' ), 'manage_options', 'taka-platform', array( __CLASS__, 'render_dashboard' ) );
			add_submenu_page( 'taka-platform', __( 'Media', 'taka-platform' ), __( 'Media', 'taka-platform' ), 'manage_options', 'taka-tour-media', array( __CLASS__, 'render_media' ) );
			add_submenu_page( 'taka-platform', __( 'Content Sections', 'taka-platform' ), __( 'Content Sections', 'taka-platform' ), 'manage_options', 'taka-platform-content-sections', array( __CLASS__, 'render_content_sections' ) );
			add_submenu_page( 'taka-platform', __( 'Import / Export', 'taka-platform' ), __( 'Import / Export', 'taka-platform' ), 'manage_options', 'taka-tour-import-export', array( __CLASS__, 'render_import_export' ) );
		add_submenu_page( 'taka-platform', __( 'Settings', 'taka-platform' ), __( 'Settings', 'taka-platform' ), 'manage_options', 'taka-tour-settings', array( __CLASS__, 'render_settings' ) );
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
		if ( ! current_user_can( 'manage_options' ) ) { return; }
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

	/** Render editable hero/layout settings. */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$hero = TAKA_Platform_Data::get_hero_settings();
		$positions = array( 'left' => __( 'Left', 'taka-platform' ), 'center' => __( 'Center', 'taka-platform' ), 'right' => __( 'Right', 'taka-platform' ) );
		$verticals = array( 'top' => __( 'Top', 'taka-platform' ), 'center' => __( 'Center', 'taka-platform' ), 'bottom' => __( 'Bottom', 'taka-platform' ) );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'TAKA Platform Settings', 'taka-platform' ); ?></h1>
			<p><?php echo esc_html__( 'The plugin uses WordPress events as the primary source and config/tour-events.php as seed, fallback and backup format.', 'taka-platform' ); ?></p>
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
		</div>
		<?php
	}

	/** Render editable frontend content sections. */
	public static function render_content_sections() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$sections = TAKA_Platform_Data::get_content_sections();
		$layouts = array( 'text_only' => __( 'Text only', 'taka-platform' ), 'image_left' => __( 'Image left', 'taka-platform' ), 'image_right' => __( 'Image right', 'taka-platform' ), 'background' => __( 'Full background image', 'taka-platform' ) );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'TAKA Platform Content Sections', 'taka-platform' ); ?></h1>
			<p><?php echo esc_html__( 'Edit reusable homepage sections without changing templates. Empty hidden sections are not rendered.', 'taka-platform' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="taka_platform_save_sections">
				<?php wp_nonce_field( TAKA_Platform_Data::SECTIONS_OPTION, self::NONCE ); ?>
				<?php foreach ( $sections as $key => $section ) : ?>
					<div class="postbox" style="padding: 1rem; max-width: 960px;">
						<h2><?php echo esc_html( $key ); ?></h2>
						<table class="form-table" role="presentation"><tbody>
							<tr><th scope="row"><?php echo esc_html__( 'Visible', 'taka-platform' ); ?></th><td><label><input type="checkbox" name="sections[<?php echo esc_attr( $key ); ?>][visible]" value="1" <?php checked( (string) ( $section['visible'] ?? '1' ), '1' ); ?>> <?php echo esc_html__( 'Show section', 'taka-platform' ); ?></label></td></tr>
							<?php self::settings_text_row( 'sections[' . $key . '][kicker]', __( 'Kicker', 'taka-platform' ), $section['kicker'] ?? '' ); ?>
							<?php self::settings_text_row( 'sections[' . $key . '][title]', __( 'Title', 'taka-platform' ), $section['title'] ?? '' ); ?>
							<?php self::settings_textarea_row( 'sections[' . $key . '][text]', __( 'Text', 'taka-platform' ), $section['text'] ?? '' ); ?>
							<?php self::settings_media_row( 'sections[' . $key . '][image_id]', 'sections[' . $key . '][image_url]', 'taka_section_' . $key . '_image', __( 'Image', 'taka-platform' ), absint( $section['image_id'] ?? 0 ), (string) ( $section['image_url'] ?? '' ) ); ?>
							<?php self::settings_select_row( 'sections[' . $key . '][layout]', __( 'Layout', 'taka-platform' ), $section['layout'] ?? 'text_only', $layouts ); ?>
							<?php self::settings_text_row( 'sections[' . $key . '][sort_order]', __( 'Sort order', 'taka-platform' ), $section['sort_order'] ?? 0 ); ?>
							<?php self::settings_text_row( 'sections[' . $key . '][link_url]', __( 'Optional link URL', 'taka-platform' ), $section['link_url'] ?? '' ); ?>
							<?php self::settings_text_row( 'sections[' . $key . '][link_label]', __( 'Optional link label', 'taka-platform' ), $section['link_label'] ?? '' ); ?>
						</tbody></table>
					</div>
				<?php endforeach; ?>
				<?php submit_button( __( 'Save content sections', 'taka-platform' ) ); ?>
			</form>
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
		$posted   = isset( $_POST['sections'] ) && is_array( $_POST['sections'] ) ? wp_unslash( $_POST['sections'] ) : array();
		$defaults = TAKA_Platform_Data::default_content_sections();
		$clean    = array();
		foreach ( $defaults as $key => $default ) {
			$item = is_array( $posted[ $key ] ?? null ) ? $posted[ $key ] : array();
			$layout = sanitize_key( $item['layout'] ?? ( $default['layout'] ?? 'text_only' ) );
			if ( ! in_array( $layout, array( 'text_only', 'image_left', 'image_right', 'background' ), true ) ) {
				$layout = 'text_only';
			}
			$clean[ $key ] = array(
				'visible'    => ! empty( $item['visible'] ) ? '1' : '0',
				'kicker'     => sanitize_text_field( $item['kicker'] ?? '' ),
				'title'      => sanitize_text_field( $item['title'] ?? '' ),
				'text'       => sanitize_textarea_field( $item['text'] ?? '' ),
				'image_id'   => absint( $item['image_id'] ?? 0 ),
				'image_url'  => esc_url_raw( $item['image_url'] ?? '' ),
				'layout'     => $layout,
				'sort_order' => (int) ( $item['sort_order'] ?? 0 ),
				'link_url'   => esc_url_raw( $item['link_url'] ?? '' ),
				'link_label' => sanitize_text_field( $item['link_label'] ?? '' ),
			);
		}
		update_option( TAKA_Platform_Data::SECTIONS_OPTION, $clean, false );
		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=taka-platform-content-sections' ) ) );
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

	private static function organizer_meta_from_config( $item ) { return array( '_taka_legal_name' => $item['legal_name'] ?? '', '_taka_website' => $item['website'] ?? '', '_taka_logo_id' => (int) ( $item['logo_id'] ?? 0 ), '_taka_logo_url' => $item['logo_url'] ?? ( $item['logo'] ?? '' ), '_taka_emails' => implode( "\n", $item['emails'] ?? array() ), '_taka_contact_persons' => self::contact_persons_to_lines( $item['contact_persons'] ?? array() ), '_taka_instagram' => $item['social']['instagram'] ?? '', '_taka_facebook' => $item['social']['facebook'] ?? '', '_taka_youtube' => $item['social']['youtube'] ?? '', '_taka_active' => 1 ); }
	private static function venue_meta_from_config( $item ) { $a = $item['address'] ?? array(); $g = $item['geo'] ?? array(); return array( '_taka_image_id' => (int) ( $item['image_id'] ?? 0 ), '_taka_image_url' => $item['image_url'] ?? ( $item['image'] ?? '' ), '_taka_parking_image_id' => (int) ( $item['parking_image_id'] ?? 0 ), '_taka_parking_image_url' => $item['parking_image_url'] ?? '', '_taka_gallery_image_ids' => implode( ',', $item['gallery_image_ids'] ?? array() ), '_taka_street' => $a['street'] ?? '', '_taka_postal_code' => $a['postal_code'] ?? '', '_taka_city' => $a['city'] ?? '', '_taka_country' => $a['country'] ?? '', '_taka_country_code' => $a['country_code'] ?? '', '_taka_timezone' => $item['timezone'] ?? '', '_taka_website' => $item['website'] ?? '', '_taka_parking' => $item['parking'] ?? '', '_taka_accessibility' => $item['accessibility'] ?? '', '_taka_notes' => $item['notes'] ?? '', '_taka_lat' => $g['lat'] ?? '', '_taka_lng' => $g['lng'] ?? '' ); }
	private static function event_meta_from_config( $item ) { return array( '_taka_subtitle' => $item['subtitle'] ?? '', '_taka_country' => $item['country'] ?? '', '_taka_country_code' => $item['country_code'] ?? '', '_taka_flag' => $item['flag'] ?? '', '_taka_city' => $item['city'] ?? '', '_taka_date_start' => $item['date_start'] ?? '', '_taka_date_end' => $item['date_end'] ?? '', '_taka_time_start' => $item['time_start'] ?? '', '_taka_time_end' => $item['time_end'] ?? '', '_taka_doors_open' => $item['doors_open'] ?? '', '_taka_timezone' => $item['timezone'] ?? '', '_taka_format' => $item['format'] ?? '', '_taka_audience' => $item['audience'] ?? '', '_taka_level' => $item['level'] ?? '', '_taka_ticket_status' => $item['ticket_status'] ?? '', '_taka_ticket_provider' => $item['ticket_provider'] ?? '', '_taka_ticket_shop_url' => $item['ticket_shop_url'] ?? '', '_taka_image_id' => (int) ( $item['image_id'] ?? 0 ), '_taka_image_url' => $item['image_url'] ?? ( $item['image'] ?? '' ), '_taka_group_image_id' => (int) ( $item['group_image_id'] ?? 0 ), '_taka_group_image_url' => $item['group_image_url'] ?? ( $item['group_image'] ?? '' ), '_taka_gallery_image_ids' => implode( ',', $item['gallery_image_ids'] ?? array() ), '_taka_photo_credit' => $item['photo_credit'] ?? '', '_taka_languages' => implode( ',', $item['languages'] ?? array() ), '_taka_notes' => $item['notes'] ?? '', '_taka_parking' => $item['parking'] ?? '', '_taka_sort_order' => (int) ( $item['sort_order'] ?? 0 ) ); }

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
		self::relation( $post->ID, 'organizer_id', __( 'Organizer', 'taka-platform' ), TAKA_PLATFORM_CPT_ORGANIZER );
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
		self::textarea( $post->ID, 'accessibility', __( 'Accessibility notes', 'taka-platform' ) );
		self::number( $post->ID, 'sort_order', __( 'Sort order', 'taka-platform' ) );
		self::textarea( $post->ID, 'notes', __( 'Notes', 'taka-platform' ) );
		self::textarea( $post->ID, 'parking', __( 'Parking notes', 'taka-platform' ) );
	}

	public static function save_organizer( $post_id ) { self::save( $post_id, array( 'legal_name', 'website', 'logo_id', 'logo_url', 'emails', 'contact_persons', 'instagram', 'facebook', 'youtube', 'active' ) ); }
	public static function save_venue( $post_id ) { self::save( $post_id, array( 'street', 'postal_code', 'city', 'country', 'country_code', 'timezone', 'lat', 'lng', 'website', 'image_id', 'image_url', 'parking_image_id', 'parking_image_url', 'gallery_image_ids', 'parking', 'accessibility', 'notes' ) ); }
	public static function save_event( $post_id ) { self::save( $post_id, array( 'subtitle', 'country', 'country_code', 'flag', 'city', 'date_start', 'date_end', 'time_start', 'time_end', 'doors_open', 'timezone', 'format', 'audience', 'level', 'ticket_provider', 'ticket_status', 'photo_credit', 'languages', 'organizer_id', 'venue_id', 'venue_ids', 'ticket_shop_url', 'image_id', 'image_url', 'group_image_id', 'group_image_url', 'gallery_image_ids', 'sort_order', 'notes', 'parking' ) ); }

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
			elseif ( in_array( $field, array( 'emails', 'contact_persons', 'short_description', 'long_description', 'ticket_card_text', 'parking', 'accessibility', 'notes' ), true ) ) { $value = sanitize_textarea_field( $value ); }
			elseif ( in_array( $field, array( 'gallery_image_ids', 'venue_ids' ), true ) ) { $value = implode( ',', array_map( 'absint', preg_split( '/\s*,\s*/', (string) $value ) ) ); }
			else { $value = sanitize_text_field( $value ); }
			update_post_meta( $post_id, $key, $value );
		}
	}

	private static function nonce() { wp_nonce_field( self::NONCE, self::NONCE ); }
	private static function meta( $post_id, $field ) { return get_post_meta( $post_id, '_taka_' . $field, true ); }
	private static function field( $label, $html ) { echo '<p><label><strong>' . esc_html( $label ) . '</strong><br>' . $html . '</label></p>'; }
	private static function text( $post_id, $field, $label ) { self::field( $label, '<input class="widefat" type="text" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( self::meta( $post_id, $field ) ) . '">' ); }
	private static function number( $post_id, $field, $label ) { self::field( $label, '<input type="number" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( self::meta( $post_id, $field ) ) . '">' ); }
	private static function url( $post_id, $field, $label ) { self::field( $label, '<input class="widefat" type="url" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( self::meta( $post_id, $field ) ) . '">' ); }
	private static function textarea( $post_id, $field, $label ) { self::field( $label, '<textarea class="widefat" rows="3" name="_taka_' . esc_attr( $field ) . '">' . esc_textarea( self::meta( $post_id, $field ) ) . '</textarea>' ); }
	private static function checkbox( $post_id, $field, $label ) { self::field( $label, '<input type="checkbox" name="_taka_' . esc_attr( $field ) . '" value="1" ' . checked( (string) self::meta( $post_id, $field ), '1', false ) . '>' ); }
	private static function settings_text_row( $name, $label, $value ) { echo '<tr><th scope="row"><label>' . esc_html( $label ) . '</label></th><td><input class="regular-text" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"></td></tr>'; }
	private static function settings_textarea_row( $name, $label, $value ) { echo '<tr><th scope="row"><label>' . esc_html( $label ) . '</label></th><td><textarea class="large-text" rows="4" name="' . esc_attr( $name ) . '">' . esc_textarea( (string) $value ) . '</textarea></td></tr>'; }
	private static function settings_select_row( $name, $label, $value, $options ) { echo '<tr><th scope="row"><label>' . esc_html( $label ) . '</label></th><td><select name="' . esc_attr( $name ) . '">'; foreach ( $options as $key => $option_label ) { echo '<option value="' . esc_attr( $key ) . '" ' . selected( (string) $value, (string) $key, false ) . '>' . esc_html( $option_label ) . '</option>'; } echo '</select></td></tr>'; }
	private static function settings_media_row( $id_name, $url_name, $input_id, $label, $id, $url ) { echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><input id="' . esc_attr( $input_id ) . '" type="hidden" name="' . esc_attr( $id_name ) . '" value="' . esc_attr( (string) $id ) . '"> <button type="button" class="button" data-taka-media-pick data-target="' . esc_attr( $input_id ) . '" data-preview="' . esc_attr( $input_id . '_preview' ) . '">' . esc_html__( 'Select image', 'taka-platform' ) . '</button> <button type="button" class="button" data-taka-media-remove data-target="' . esc_attr( $input_id ) . '" data-preview="' . esc_attr( $input_id . '_preview' ) . '">' . esc_html__( 'Remove image', 'taka-platform' ) . '</button><div id="' . esc_attr( $input_id . '_preview' ) . '">'; self::image_preview( $id, $url ); echo '</div><p><label>' . esc_html__( 'Fallback URL', 'taka-platform' ) . '<br><input class="regular-text" type="url" name="' . esc_attr( $url_name ) . '" value="' . esc_attr( $url ) . '"></label></p></td></tr>'; }
	private static function sanitize_decimal( $value, $default ) { $value = (string) $value; return preg_match( '/^(0(\.\d+)?|1(\.0+)?)$/', $value ) ? $value : $default; }
	private static function media_field( $post_id, $field, $label, $multiple = false, $button_label = null ) { $value = (string) self::meta( $post_id, $field ); $input_id = 'taka_' . $field . '_' . $post_id; $button_label = $button_label ?: __( 'Select image', 'taka-platform' ); $html = '<input id="' . esc_attr( $input_id ) . '" type="hidden" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( $value ) . '"> <button type="button" class="button" data-taka-media-pick data-multiple="' . ( $multiple ? '1' : '0' ) . '" data-target="' . esc_attr( $input_id ) . '" data-preview="' . esc_attr( $input_id . '_preview' ) . '">' . esc_html( $button_label ) . '</button> <button type="button" class="button" data-taka-media-remove data-target="' . esc_attr( $input_id ) . '" data-preview="' . esc_attr( $input_id . '_preview' ) . '">' . esc_html__( 'Remove image', 'taka-platform' ) . '</button><div id="' . esc_attr( $input_id . '_preview' ) . '">'; ob_start(); self::image_previews( $value ); $html .= ob_get_clean() . '</div>'; self::field( $label, $html ); }
	private static function relation( $post_id, $field, $label, $post_type ) { $current = (int) self::meta( $post_id, $field ); $posts = get_posts( array( 'post_type' => $post_type, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ); $html = '<select name="_taka_' . esc_attr( $field ) . '"><option value="">—</option>'; foreach ( $posts as $post ) { $html .= '<option value="' . esc_attr( $post->ID ) . '" ' . selected( $current, $post->ID, false ) . '>' . esc_html( get_the_title( $post ) ) . '</option>'; } $html .= '</select>'; self::field( $label, $html ); }
	private static function image_preview( $id, $fallback_url = '' ) { $url = $id && function_exists( 'wp_get_attachment_image_url' ) ? wp_get_attachment_image_url( $id, 'thumbnail' ) : $fallback_url; if ( $url ) { echo '<img src="' . esc_url( $url ) . '" style="max-width:180px;height:auto;display:block;margin-top:8px;" alt="">'; } }
	private static function image_previews( $ids ) { foreach ( array_filter( array_map( 'absint', preg_split( '/\s*,\s*/', (string) $ids ) ) ) as $id ) { self::image_preview( $id ); } }
}

<?php
/**
 * WordPress admin screens for TAKA Tour.
 */

defined( 'ABSPATH' ) || exit;

class Taka_Tour_Admin {
	const NONCE = 'taka_tour_admin_nonce';

	/** Register admin hooks. */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_taka_organizer', array( __CLASS__, 'save_organizer' ) );
		add_action( 'save_post_taka_venue', array( __CLASS__, 'save_venue' ) );
		add_action( 'save_post_taka_event', array( __CLASS__, 'save_event' ) );
	}

	/** Register admin CPTs. */
	public static function register_post_types() {
		self::register_post_type( 'taka_organizer', __( 'Veranstalter', 'taka-tour' ), __( 'Veranstalter hinzufügen', 'taka-tour' ) );
		self::register_post_type( 'taka_venue', __( 'Veranstaltungsorte', 'taka-tour' ), __( 'Veranstaltungsort hinzufügen', 'taka-tour' ) );
		self::register_post_type( 'taka_event', __( 'Veranstaltungen', 'taka-tour' ), __( 'Veranstaltung hinzufügen', 'taka-tour' ) );
	}

	/** Register one private CPT. */
	private static function register_post_type( $post_type, $name, $add_new_item ) {
		register_post_type(
			$post_type,
			array(
				'labels'       => array( 'name' => $name, 'singular_name' => $name, 'add_new_item' => $add_new_item ),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'taka-tour',
				'supports'     => array( 'title', 'editor' ),
			)
		);
	}

	/** Register menu pages. */
	public static function register_menu() {
		add_menu_page( __( 'TAKA Tour', 'taka-tour' ), __( 'TAKA Tour', 'taka-tour' ), 'manage_options', 'taka-tour', array( __CLASS__, 'render_dashboard' ), 'dashicons-tickets-alt', 28 );
		add_submenu_page( 'taka-tour', __( 'Dashboard', 'taka-tour' ), __( 'Dashboard', 'taka-tour' ), 'manage_options', 'taka-tour', array( __CLASS__, 'render_dashboard' ) );
		add_submenu_page( 'taka-tour', __( 'Einstellungen', 'taka-tour' ), __( 'Einstellungen', 'taka-tour' ), 'manage_options', 'taka-tour-settings', array( __CLASS__, 'render_settings' ) );
	}

	/** Register meta boxes. */
	public static function add_meta_boxes() {
		add_meta_box( 'taka_organizer_details', __( 'Veranstalter-Daten', 'taka-tour' ), array( __CLASS__, 'render_organizer_meta_box' ), 'taka_organizer', 'normal', 'high' );
		add_meta_box( 'taka_venue_details', __( 'Ortsdaten', 'taka-tour' ), array( __CLASS__, 'render_venue_meta_box' ), 'taka_venue', 'normal', 'high' );
		add_meta_box( 'taka_event_details', __( 'Veranstaltungsdaten', 'taka-tour' ), array( __CLASS__, 'render_event_meta_box' ), 'taka_event', 'normal', 'high' );
	}

	/** Render dashboard. */
	public static function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$config_path        = TAKA_TOUR_PLUGIN_DIR . 'config/tour-events.php';
		$config             = Taka_Tour_Data::load_config();
		$wp_event_count     = Taka_Tour_Data::count_wp_events();
		$config_event_count = count( $config['events'] ?? array() );
		$active_source      = Taka_Tour_Data::is_using_wp_events() ? __( 'WordPress', 'taka-tour' ) : __( 'Config fallback', 'taka-tour' );
		$translations       = glob( TAKA_TOUR_PLUGIN_DIR . 'translations/*.json' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'TAKA Tour Dashboard', 'taka-tour' ); ?></h1>
			<table class="widefat striped" style="max-width: 760px;"><tbody>
				<tr><th><?php echo esc_html__( 'Plugin-Version', 'taka-tour' ); ?></th><td><?php echo esc_html( TAKA_TOUR_VERSION ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Config-Datei', 'taka-tour' ); ?></th><td><?php echo file_exists( $config_path ) ? esc_html__( 'gefunden', 'taka-tour' ) : esc_html__( 'nicht gefunden', 'taka-tour' ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Events aus WordPress', 'taka-tour' ); ?></th><td><?php echo esc_html( (string) $wp_event_count ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Events aus Config', 'taka-tour' ); ?></th><td><?php echo esc_html( (string) $config_event_count ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Aktive Datenquelle', 'taka-tour' ); ?></th><td><?php echo esc_html( $active_source ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Übersetzungsdateien', 'taka-tour' ); ?></th><td><?php echo esc_html( (string) count( is_array( $translations ) ? $translations : array() ) ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Status', 'taka-tour' ); ?></th><td><?php echo esc_html__( 'Admin Interface funktioniert.', 'taka-tour' ); ?></td></tr>
			</tbody></table>
		</div>
		<?php
	}

	/** Render settings placeholder. */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap"><h1><?php echo esc_html__( 'TAKA Tour Einstellungen', 'taka-tour' ); ?></h1><p><?php echo esc_html__( 'Media- und Event-Verwaltung folgt in v0.9.0.', 'taka-tour' ); ?></p></div>
		<?php
	}

	/** Organizer meta. */
	public static function render_organizer_meta_box( $post ) {
		self::nonce();
		self::text( $post->ID, 'legal_name', __( 'Rechtlicher Name', 'taka-tour' ) );
		self::url( $post->ID, 'website', __( 'Website', 'taka-tour' ) );
		self::number( $post->ID, 'logo_id', __( 'Logo Media ID', 'taka-tour' ) );
		self::textarea( $post->ID, 'emails', __( 'E-Mail-Adressen (eine pro Zeile)', 'taka-tour' ) );
		self::textarea( $post->ID, 'contact_persons', __( 'Kontaktpersonen (eine pro Zeile)', 'taka-tour' ) );
		self::text( $post->ID, 'instagram', __( 'Instagram', 'taka-tour' ) );
		self::text( $post->ID, 'facebook', __( 'Facebook', 'taka-tour' ) );
		self::text( $post->ID, 'youtube', __( 'YouTube', 'taka-tour' ) );
	}

	/** Venue meta. */
	public static function render_venue_meta_box( $post ) {
		self::nonce();
		foreach ( array( 'street' => 'Straße', 'postal_code' => 'PLZ', 'city' => 'Stadt', 'country' => 'Land', 'country_code' => 'Ländercode', 'timezone' => 'Zeitzone', 'lat' => 'Geo lat', 'lng' => 'Geo lng' ) as $key => $label ) {
			self::text( $post->ID, $key, __( $label, 'taka-tour' ) );
		}
		self::url( $post->ID, 'website', __( 'Website', 'taka-tour' ) );
		self::textarea( $post->ID, 'parking', __( 'Parkplatzhinweise', 'taka-tour' ) );
		self::textarea( $post->ID, 'accessibility', __( 'Barrierefreiheit', 'taka-tour' ) );
		self::textarea( $post->ID, 'notes', __( 'Besonderheiten / Notizen', 'taka-tour' ) );
	}

	/** Event meta. */
	public static function render_event_meta_box( $post ) {
		self::nonce();
		foreach ( array( 'subtitle' => 'Untertitel', 'country' => 'Land', 'country_code' => 'Ländercode', 'flag' => 'Flagge', 'city' => 'Stadt', 'date_start' => 'Startdatum', 'date_end' => 'Enddatum', 'time_start' => 'Uhrzeit Beginn', 'time_end' => 'Uhrzeit Ende', 'doors_open' => 'Türen öffnen', 'timezone' => 'Zeitzone', 'format' => 'Format', 'audience' => 'Zielgruppe', 'level' => 'Level', 'ticket_provider' => 'Ticketanbieter', 'photo_credit' => 'Fotocredit' ) as $key => $label ) {
			self::text( $post->ID, $key, __( $label, 'taka-tour' ) );
		}
		self::relation( $post->ID, 'organizer_id', __( 'Veranstalter', 'taka-tour' ), 'taka_organizer' );
		self::relation( $post->ID, 'venue_id', __( 'Veranstaltungsort', 'taka-tour' ), 'taka_venue' );
		self::url( $post->ID, 'ticket_shop_url', __( 'Ticketshop-URL', 'taka-tour' ) );
		self::text( $post->ID, 'ticket_status', __( 'Ticketstatus', 'taka-tour' ) );
		self::number( $post->ID, 'image_id', __( 'Bild Media ID', 'taka-tour' ) );
		self::url( $post->ID, 'image_url', __( 'Fallback-Bild-URL', 'taka-tour' ) );
		self::number( $post->ID, 'sort_order', __( 'Sortierreihenfolge', 'taka-tour' ) );
		self::textarea( $post->ID, 'notes', __( 'Notizen', 'taka-tour' ) );
		self::textarea( $post->ID, 'parking', __( 'Parkhinweise', 'taka-tour' ) );
	}

	/** Save organizer. */
	public static function save_organizer( $post_id ) { self::save( $post_id, array( 'legal_name', 'website', 'logo_id', 'emails', 'contact_persons', 'instagram', 'facebook', 'youtube' ) ); }
	/** Save venue. */
	public static function save_venue( $post_id ) { self::save( $post_id, array( 'street', 'postal_code', 'city', 'country', 'country_code', 'timezone', 'lat', 'lng', 'website', 'parking', 'accessibility', 'notes' ) ); }
	/** Save event. */
	public static function save_event( $post_id ) { self::save( $post_id, array( 'subtitle', 'country', 'country_code', 'flag', 'city', 'date_start', 'date_end', 'time_start', 'time_end', 'doors_open', 'timezone', 'format', 'audience', 'level', 'ticket_provider', 'photo_credit', 'organizer_id', 'venue_id', 'ticket_shop_url', 'ticket_status', 'image_id', 'image_url', 'sort_order', 'notes', 'parking' ) ); }

	/** Save fields. */
	private static function save( $post_id, $fields ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		foreach ( $fields as $field ) {
			$key = '_taka_' . $field;
			if ( ! isset( $_POST[ $key ] ) ) { delete_post_meta( $post_id, $key ); continue; }
			$value = wp_unslash( $_POST[ $key ] );
			if ( in_array( $field, array( 'logo_id', 'image_id', 'organizer_id', 'venue_id', 'sort_order' ), true ) ) { $value = absint( $value ); }
			elseif ( in_array( $field, array( 'website', 'ticket_shop_url', 'image_url' ), true ) ) { $value = esc_url_raw( $value ); }
			elseif ( in_array( $field, array( 'emails', 'contact_persons', 'parking', 'accessibility', 'notes' ), true ) ) { $value = sanitize_textarea_field( $value ); }
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
	private static function relation( $post_id, $field, $label, $post_type ) { $current = (int) self::meta( $post_id, $field ); $posts = get_posts( array( 'post_type' => $post_type, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ); $html = '<select name="_taka_' . esc_attr( $field ) . '"><option value="">—</option>'; foreach ( $posts as $post ) { $html .= '<option value="' . esc_attr( $post->ID ) . '" ' . selected( $current, $post->ID, false ) . '>' . esc_html( get_the_title( $post ) ) . '</option>'; } $html .= '</select>'; self::field( $label, $html ); }
}

<?php
/**
 * WordPress admin CMS for TAKA Tour data.
 */

defined( 'ABSPATH' ) || exit;

class Taka_Tour_Admin {
	const CAP_MANAGE = 'manage_taka_tour';
	const NONCE = 'taka_tour_meta_nonce';
	const OPTION_IMAGES = 'taka_tour_images';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_taka_organizer', array( __CLASS__, 'save_organizer' ) );
		add_action( 'save_post_taka_venue', array( __CLASS__, 'save_venue' ) );
		add_action( 'save_post_taka_event', array( __CLASS__, 'save_event' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_pages' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_taka_tour_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_taka_tour_import_config', array( __CLASS__, 'import_config' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_ensure_caps' ) );
	}

	/**
	 * Grant TAKA Tour capabilities to administrators.
	 *
	 * @return void
	 */
	public static function activate() {
		self::maybe_ensure_caps();
	}

	/** Ensure administrator capabilities after updates too. */
	public static function maybe_ensure_caps() {
		$role = get_role( 'administrator' );
		if ( ! $role ) {
			return;
		}

		foreach ( self::capabilities() as $capability ) {
			$role->add_cap( $capability );
		}
	}

	/**
	 * Capabilities prepared for future organizer self-service.
	 *
	 * @return array
	 */
	private static function capabilities() {
		return array( 'manage_taka_tour', 'edit_taka_events', 'edit_taka_organizers', 'edit_taka_venues' );
	}

	/**
	 * Register custom post types.
	 *
	 * @return void
	 */
	public static function register_post_types() {
		register_post_type(
			'taka_organizer',
			array(
				'labels'       => array(
					'name'          => __( 'Veranstalter', 'taka-tour' ),
					'singular_name' => __( 'Veranstalter', 'taka-tour' ),
					'add_new_item'  => __( 'Veranstalter hinzufügen', 'taka-tour' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'taka-tour',
				'supports'     => array( 'title', 'editor' ),
				'capability_type' => 'post',
			)
		);

		register_post_type(
			'taka_venue',
			array(
				'labels'       => array(
					'name'          => __( 'Veranstaltungsorte', 'taka-tour' ),
					'singular_name' => __( 'Veranstaltungsort', 'taka-tour' ),
					'add_new_item'  => __( 'Veranstaltungsort hinzufügen', 'taka-tour' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'taka-tour',
				'supports'     => array( 'title', 'editor' ),
				'capability_type' => 'post',
			)
		);

		register_post_type(
			'taka_event',
			array(
				'labels'       => array(
					'name'          => __( 'Veranstaltungen', 'taka-tour' ),
					'singular_name' => __( 'Veranstaltung', 'taka-tour' ),
					'add_new_item'  => __( 'Veranstaltung hinzufügen', 'taka-tour' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'taka-tour',
				'supports'     => array( 'title', 'editor' ),
				'capability_type' => 'post',
			)
		);
	}

	/**
	 * Register top-level and utility pages.
	 *
	 * @return void
	 */
	public static function register_admin_pages() {
		add_menu_page( __( 'TAKA Tour', 'taka-tour' ), __( 'TAKA Tour', 'taka-tour' ), self::CAP_MANAGE, 'taka-tour', array( __CLASS__, 'render_settings_page' ), 'dashicons-tickets-alt', 28 );
		add_submenu_page( 'taka-tour', __( 'Einstellungen', 'taka-tour' ), __( 'Einstellungen', 'taka-tour' ), self::CAP_MANAGE, 'taka-tour', array( __CLASS__, 'render_settings_page' ) );
		add_submenu_page( 'taka-tour', __( 'Import', 'taka-tour' ), __( 'Import', 'taka-tour' ), self::CAP_MANAGE, 'taka-tour-import', array( __CLASS__, 'render_import_page' ) );
	}

	/**
	 * Add meta boxes.
	 *
	 * @return void
	 */
	public static function add_meta_boxes() {
		add_meta_box( 'taka_organizer_details', __( 'Veranstalter-Daten', 'taka-tour' ), array( __CLASS__, 'render_organizer_meta_box' ), 'taka_organizer', 'normal', 'high' );
		add_meta_box( 'taka_venue_details', __( 'Ortsdaten', 'taka-tour' ), array( __CLASS__, 'render_venue_meta_box' ), 'taka_venue', 'normal', 'high' );
		add_meta_box( 'taka_event_details', __( 'Veranstaltungsdaten', 'taka-tour' ), array( __CLASS__, 'render_event_meta_box' ), 'taka_event', 'normal', 'high' );
	}

	/**
	 * Enqueue media picker helpers.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook ) {
		if ( false === strpos( $hook, 'taka' ) && ! in_array( get_post_type(), array( 'taka_organizer', 'taka_venue', 'taka_event' ), true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_add_inline_script(
			'jquery-core',
			"document.addEventListener('click',function(e){var b=e.target.closest('[data-taka-media-select]');if(!b){return;}e.preventDefault();var target=document.getElementById(b.dataset.takaMediaSelect);var preview=document.getElementById(b.dataset.takaMediaPreview);var frame=wp.media({title:'Bild auswählen',multiple:false,library:{type:'image'},button:{text:'Bild verwenden'}});frame.on('select',function(){var a=frame.state().get('selection').first().toJSON();target.value=a.id;if(preview){preview.src=a.sizes&&a.sizes.thumbnail?a.sizes.thumbnail.url:a.url;preview.style.display='block';}});frame.open();});document.addEventListener('click',function(e){var b=e.target.closest('[data-taka-media-clear]');if(!b){return;}e.preventDefault();var target=document.getElementById(b.dataset.takaMediaClear);var preview=document.getElementById(b.dataset.takaMediaPreview);target.value='';if(preview){preview.removeAttribute('src');preview.style.display='none';}});"
		);
	}

	/**
	 * Render organizer fields.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public static function render_organizer_meta_box( $post ) {
		self::nonce_field();
		self::text_field( 'legal_name', __( 'Rechtlicher Name', 'taka-tour' ), $post->ID );
		self::url_field( 'website', __( 'Website', 'taka-tour' ), $post->ID );
		self::media_field( 'logo_id', __( 'Logo', 'taka-tour' ), $post->ID );
		self::textarea_field( 'emails', __( 'E-Mail-Adressen (eine pro Zeile)', 'taka-tour' ), $post->ID );
		self::textarea_field( 'contact_persons', __( 'Kontaktpersonen (eine pro Zeile)', 'taka-tour' ), $post->ID );
		self::text_field( 'instagram', __( 'Instagram', 'taka-tour' ), $post->ID );
		self::text_field( 'facebook', __( 'Facebook', 'taka-tour' ), $post->ID );
		self::text_field( 'youtube', __( 'YouTube', 'taka-tour' ), $post->ID );
		self::checkbox_field( 'active', __( 'Aktiv', 'taka-tour' ), $post->ID, true );
	}

	/**
	 * Render venue fields.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public static function render_venue_meta_box( $post ) {
		self::nonce_field();
		self::text_field( 'street', __( 'Straße', 'taka-tour' ), $post->ID );
		self::text_field( 'postal_code', __( 'PLZ', 'taka-tour' ), $post->ID );
		self::text_field( 'city', __( 'Stadt', 'taka-tour' ), $post->ID );
		self::text_field( 'country', __( 'Land', 'taka-tour' ), $post->ID );
		self::text_field( 'country_code', __( 'Ländercode', 'taka-tour' ), $post->ID );
		self::url_field( 'website', __( 'Website', 'taka-tour' ), $post->ID );
		self::text_field( 'timezone', __( 'Zeitzone', 'taka-tour' ), $post->ID );
		self::textarea_field( 'parking', __( 'Parkplatzhinweise', 'taka-tour' ), $post->ID );
		self::textarea_field( 'accessibility', __( 'Barrierefreiheit', 'taka-tour' ), $post->ID );
		self::textarea_field( 'notes', __( 'Besonderheiten / Notizen', 'taka-tour' ), $post->ID );
		self::text_field( 'lat', __( 'Geo lat', 'taka-tour' ), $post->ID );
		self::text_field( 'lng', __( 'Geo lng', 'taka-tour' ), $post->ID );
	}

	/**
	 * Render event fields.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public static function render_event_meta_box( $post ) {
		self::nonce_field();
		self::text_field( 'subtitle', __( 'Untertitel', 'taka-tour' ), $post->ID );
		self::text_field( 'country', __( 'Land', 'taka-tour' ), $post->ID );
		self::text_field( 'country_code', __( 'Ländercode', 'taka-tour' ), $post->ID );
		self::text_field( 'flag', __( 'Flagge', 'taka-tour' ), $post->ID );
		self::text_field( 'city', __( 'Stadt', 'taka-tour' ), $post->ID );
		self::date_field( 'date_start', __( 'Startdatum', 'taka-tour' ), $post->ID );
		self::date_field( 'date_end', __( 'Enddatum', 'taka-tour' ), $post->ID );
		self::text_field( 'time_start', __( 'Uhrzeit Beginn', 'taka-tour' ), $post->ID );
		self::text_field( 'time_end', __( 'Uhrzeit Ende', 'taka-tour' ), $post->ID );
		self::text_field( 'doors_open', __( 'Türen öffnen', 'taka-tour' ), $post->ID );
		self::text_field( 'timezone', __( 'Zeitzone', 'taka-tour' ), $post->ID );
		self::text_field( 'format', __( 'Format', 'taka-tour' ), $post->ID );
		self::text_field( 'audience', __( 'Zielgruppe', 'taka-tour' ), $post->ID );
		self::text_field( 'level', __( 'Level', 'taka-tour' ), $post->ID );
		self::relation_select( 'organizer_id', __( 'Veranstalter', 'taka-tour' ), $post->ID, 'taka_organizer' );
		self::relation_select( 'venue_id', __( 'Veranstaltungsort', 'taka-tour' ), $post->ID, 'taka_venue' );
		self::relation_select( 'venue_ids', __( 'Weitere Veranstaltungsorte', 'taka-tour' ), $post->ID, 'taka_venue', true );
		self::select_field( 'ticket_status', __( 'Ticketstatus', 'taka-tour' ), $post->ID, array( 'coming_soon' => __( 'Ticketshop folgt', 'taka-tour' ), 'available' => __( 'Tickets verfügbar', 'taka-tour' ) ) );
		self::url_field( 'ticket_shop_url', __( 'Ticketshop-URL', 'taka-tour' ), $post->ID );
		self::text_field( 'ticket_provider', __( 'Ticketanbieter', 'taka-tour' ), $post->ID );
		self::media_field( 'image_id', __( 'Beispielbild', 'taka-tour' ), $post->ID );
		self::text_field( 'photo_credit', __( 'Fotocredit', 'taka-tour' ), $post->ID );
		self::number_field( 'sort_order', __( 'Sortierreihenfolge', 'taka-tour' ), $post->ID );
		self::textarea_field( 'notes', __( 'Notizen', 'taka-tour' ), $post->ID );
		self::textarea_field( 'parking', __( 'Parkhinweise', 'taka-tour' ), $post->ID );
	}

	/** Save organizer meta. */
	public static function save_organizer( $post_id ) {
		if ( ! self::can_save( $post_id ) ) {
			return;
		}
		self::save_fields( $post_id, array( 'legal_name', 'website', 'logo_id', 'emails', 'contact_persons', 'instagram', 'facebook', 'youtube', 'active' ) );
	}

	/** Save venue meta. */
	public static function save_venue( $post_id ) {
		if ( ! self::can_save( $post_id ) ) {
			return;
		}
		self::save_fields( $post_id, array( 'street', 'postal_code', 'city', 'country', 'country_code', 'website', 'timezone', 'parking', 'accessibility', 'notes', 'lat', 'lng' ) );
	}

	/** Save event meta. */
	public static function save_event( $post_id ) {
		if ( ! self::can_save( $post_id ) ) {
			return;
		}
		self::save_fields( $post_id, array( 'subtitle', 'country', 'country_code', 'flag', 'city', 'date_start', 'date_end', 'time_start', 'time_end', 'doors_open', 'timezone', 'format', 'audience', 'level', 'organizer_id', 'venue_id', 'venue_ids', 'ticket_status', 'ticket_shop_url', 'ticket_provider', 'image_id', 'photo_credit', 'sort_order', 'notes', 'parking' ) );
	}

	/**
	 * Save posted meta fields.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $fields Field keys.
	 * @return void
	 */
	private static function save_fields( $post_id, $fields ) {
		foreach ( $fields as $field ) {
			$key = '_taka_' . $field;
			if ( 'active' === $field ) {
				update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) ? '1' : '0' );
				continue;
			}
			if ( ! isset( $_POST[ $key ] ) ) {
				delete_post_meta( $post_id, $key );
				continue;
			}
			$value = wp_unslash( $_POST[ $key ] );
			if ( in_array( $field, array( 'logo_id', 'image_id', 'organizer_id', 'venue_id', 'sort_order' ), true ) ) {
				$value = absint( $value );
			} elseif ( 'venue_ids' === $field ) {
				$value = array_map( 'absint', (array) $value );
			} elseif ( in_array( $field, array( 'website', 'ticket_shop_url' ), true ) ) {
				$value = esc_url_raw( $value );
			} elseif ( in_array( $field, array( 'emails', 'contact_persons', 'parking', 'accessibility', 'notes' ), true ) ) {
				$value = sanitize_textarea_field( $value );
			} else {
				$value = sanitize_text_field( $value );
			}
			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Check save permission and nonce.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private static function can_save( $post_id ) {
		return ! ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) && isset( $_POST[ self::NONCE ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) && current_user_can( 'edit_post', $post_id );
	}

	/** Render settings page. */
	public static function render_settings_page() {
		if ( ! current_user_can( self::CAP_MANAGE ) ) {
			return;
		}
		$images = get_option( self::OPTION_IMAGES, array() );
		echo '<div class="wrap"><h1>' . esc_html__( 'TAKA Tour Einstellungen', 'taka-tour' ) . '</h1><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'taka_tour_save_settings' );
		echo '<input type="hidden" name="action" value="taka_tour_save_settings">';
		foreach ( self::global_image_fields() as $key => $label ) {
			self::media_field_raw( 'taka_tour_images[' . esc_attr( $key ) . ']', $key, $label, absint( $images[ $key ] ?? 0 ) );
		}
		submit_button( __( 'Speichern', 'taka-tour' ) );
		echo '</form></div>';
	}

	/** Save settings page. */
	public static function save_settings() {
		if ( ! current_user_can( self::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'taka-tour' ) );
		}
		check_admin_referer( 'taka_tour_save_settings' );
		$posted = isset( $_POST['taka_tour_images'] ) ? (array) wp_unslash( $_POST['taka_tour_images'] ) : array();
		$images = array();
		foreach ( self::global_image_fields() as $key => $label ) {
			$images[ $key ] = absint( $posted[ $key ] ?? 0 );
		}
		update_option( self::OPTION_IMAGES, $images );
		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=taka-tour' ) ) );
		exit;
	}

	/** Render import page. */
	public static function render_import_page() {
		if ( ! current_user_can( self::CAP_MANAGE ) ) {
			return;
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'TAKA Tour Import', 'taka-tour' ) . '</h1><p>' . esc_html__( 'Importiert oder aktualisiert Organizer, Orte und Veranstaltungen aus config/tour-events.php ohne Duplikate.', 'taka-tour' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'taka_tour_import_config' );
		echo '<input type="hidden" name="action" value="taka_tour_import_config">';
		submit_button( __( 'Konfiguration importieren', 'taka-tour' ) );
		echo '</form></div>';
	}

	/** Import config data into CPTs. */
	public static function import_config() {
		if ( ! current_user_can( self::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'taka-tour' ) );
		}
		check_admin_referer( 'taka_tour_import_config' );
		$config = Taka_Tour_Data::load_config();
		$organizer_map = array();
		$venue_map = array();
		foreach ( $config['organizers'] ?? array() as $config_id => $organizer ) {
			$organizer_map[ $config_id ] = self::upsert_config_post( 'taka_organizer', $config_id, $organizer['name'] ?? $config_id, '', array( 'legal_name' => $organizer['legal_name'] ?? '', 'website' => $organizer['website'] ?? '', 'logo_url' => $organizer['logo'] ?? '', 'emails' => implode( "\n", $organizer['emails'] ?? array() ), 'contact_persons' => self::contact_lines( $organizer['contact_persons'] ?? array() ), 'instagram' => $organizer['social']['instagram'] ?? '', 'facebook' => $organizer['social']['facebook'] ?? '', 'youtube' => $organizer['social']['youtube'] ?? '', 'active' => '1' ) );
		}
		foreach ( $config['venues'] ?? array() as $config_id => $venue ) {
			$venue_map[ $config_id ] = self::upsert_config_post( 'taka_venue', $config_id, $venue['name'] ?? $config_id, $venue['notes'] ?? '', array( 'street' => $venue['address']['street'] ?? '', 'postal_code' => $venue['address']['postal_code'] ?? '', 'city' => $venue['address']['city'] ?? '', 'country' => $venue['address']['country'] ?? '', 'country_code' => $venue['address']['country_code'] ?? '', 'website' => $venue['website'] ?? '', 'timezone' => $venue['timezone'] ?? '', 'parking' => $venue['parking'] ?? '', 'accessibility' => $venue['accessibility'] ?? '', 'notes' => $venue['notes'] ?? '', 'lat' => $venue['geo']['lat'] ?? '', 'lng' => $venue['geo']['lng'] ?? '' ) );
		}
		foreach ( $config['events'] ?? array() as $event ) {
			$venue_ids = array();
			foreach ( $event['venues'] ?? array() as $venue_config_id ) {
				if ( isset( $venue_map[ $venue_config_id ] ) ) {
					$venue_ids[] = $venue_map[ $venue_config_id ];
				}
			}
			self::upsert_config_post( 'taka_event', $event['id'] ?? $event['slug'], $event['title'] ?? '', $event['description'] ?? '', array( 'slug' => $event['slug'] ?? '', 'subtitle' => $event['subtitle'] ?? '', 'country' => $event['country'] ?? '', 'country_code' => $event['country_code'] ?? '', 'flag' => $event['flag'] ?? '', 'city' => $event['city'] ?? '', 'date_start' => $event['date_start'] ?? '', 'date_end' => $event['date_end'] ?? '', 'time_start' => $event['time_start'] ?? '', 'time_end' => $event['time_end'] ?? '', 'doors_open' => $event['doors_open'] ?? '', 'timezone' => $event['timezone'] ?? '', 'format' => $event['format'] ?? '', 'audience' => $event['audience'] ?? '', 'level' => $event['level'] ?? '', 'organizer_id' => $organizer_map[ $event['organizer'] ?? '' ] ?? 0, 'venue_id' => $venue_map[ $event['venue'] ?? '' ] ?? 0, 'venue_ids' => $venue_ids, 'ticket_status' => $event['ticket_status'] ?? '', 'ticket_shop_url' => $event['ticket_shop_url'] ?? '', 'ticket_provider' => $event['ticket_provider'] ?? '', 'image_url' => $event['image'] ?? '', 'photo_credit' => $event['photo_credit'] ?? '', 'sort_order' => $event['sort_order'] ?? 0, 'notes' => $event['notes'] ?? '', 'parking' => $event['parking'] ?? '', 'languages' => implode( ',', $event['languages'] ?? array() ) ) );
		}
		wp_safe_redirect( add_query_arg( 'imported', '1', admin_url( 'admin.php?page=taka-tour-import' ) ) );
		exit;
	}

	/** Upsert a post by config ID. */
	private static function upsert_config_post( $post_type, $config_id, $title, $content, $meta ) {
		$existing = get_posts( array( 'post_type' => $post_type, 'post_status' => 'any', 'meta_key' => '_taka_config_id', 'meta_value' => $config_id, 'fields' => 'ids', 'posts_per_page' => 1 ) );
		$post_id = ! empty( $existing ) ? absint( $existing[0] ) : 0;
		$post_data = array( 'ID' => $post_id, 'post_type' => $post_type, 'post_status' => 'publish', 'post_title' => sanitize_text_field( $title ), 'post_content' => wp_kses_post( $content ) );
		$post_id = $post_id ? wp_update_post( $post_data ) : wp_insert_post( $post_data );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}
		update_post_meta( $post_id, '_taka_config_id', sanitize_key( $config_id ) );
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, '_taka_' . $key, $value );
		}
		return absint( $post_id );
	}

	/** Convert contact config to textarea lines. */
	private static function contact_lines( $contacts ) {
		$lines = array();
		foreach ( $contacts as $contact ) {
			$lines[] = trim( ( $contact['name'] ?? '' ) . ' | ' . ( $contact['email'] ?? '' ) . ' | ' . ( $contact['role'] ?? '' ) );
		}
		return implode( "\n", array_filter( $lines ) );
	}

	/** Global image settings. */
	private static function global_image_fields() {
		return array( 'hero_image' => __( 'Hero-Bild', 'taka-tour' ), 'taka_portrait' => __( 'Taka-Portrait', 'taka-tour' ), 'community_group' => __( 'Community-Bild', 'taka-tour' ), 'kobudo' => __( 'Kobudo-Bild', 'taka-tour' ), 'softblock' => __( 'Softblock-Bild', 'taka-tour' ), 'together_practice' => __( 'Gemeinsam-üben-Bild', 'taka-tour' ), 'kids_group' => __( 'Kinderseminar-Bild', 'taka-tour' ), 'kleiner_wald_logo' => __( 'Kleiner-Wald-Logo', 'taka-tour' ), 'sponsor_logo' => __( 'Sponsor-Logo', 'taka-tour' ) );
	}

	/** Field helpers. */
	private static function nonce_field() { wp_nonce_field( self::NONCE, self::NONCE ); }
	private static function meta( $post_id, $field, $default = '' ) { $value = get_post_meta( $post_id, '_taka_' . $field, true ); return '' === $value ? $default : $value; }
	private static function field_wrap( $label, $field ) { echo '<p><label><strong>' . esc_html( $label ) . '</strong><br>' . $field . '</label></p>'; }
	private static function text_field( $field, $label, $post_id ) { self::field_wrap( $label, '<input class="widefat" type="text" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( self::meta( $post_id, $field ) ) . '">' ); }
	private static function date_field( $field, $label, $post_id ) { self::field_wrap( $label, '<input type="date" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( self::meta( $post_id, $field ) ) . '">' ); }
	private static function number_field( $field, $label, $post_id ) { self::field_wrap( $label, '<input type="number" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( self::meta( $post_id, $field ) ) . '">' ); }
	private static function url_field( $field, $label, $post_id ) { self::field_wrap( $label, '<input class="widefat" type="url" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( self::meta( $post_id, $field ) ) . '">' ); }
	private static function textarea_field( $field, $label, $post_id ) { self::field_wrap( $label, '<textarea class="widefat" rows="4" name="_taka_' . esc_attr( $field ) . '">' . esc_textarea( self::meta( $post_id, $field ) ) . '</textarea>' ); }
	private static function checkbox_field( $field, $label, $post_id, $default = false ) { $checked = checked( '1', self::meta( $post_id, $field, $default ? '1' : '0' ), false ); echo '<p><label><input type="checkbox" name="_taka_' . esc_attr( $field ) . '" value="1" ' . $checked . '> ' . esc_html( $label ) . '</label></p>'; }
	private static function select_field( $field, $label, $post_id, $options ) { $current = self::meta( $post_id, $field ); $html = '<select name="_taka_' . esc_attr( $field ) . '">'; foreach ( $options as $value => $text ) { $html .= '<option value="' . esc_attr( $value ) . '" ' . selected( $current, $value, false ) . '>' . esc_html( $text ) . '</option>'; } $html .= '</select>'; self::field_wrap( $label, $html ); }
	private static function media_field( $field, $label, $post_id ) { self::media_field_raw( '_taka_' . $field, '_taka_' . $field, $label, absint( self::meta( $post_id, $field ) ) ); }
	private static function media_field_raw( $name, $id, $label, $value ) { $src = $value ? wp_get_attachment_image_url( $value, 'thumbnail' ) : ''; echo '<p><strong>' . esc_html( $label ) . '</strong><br><input type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"><img id="' . esc_attr( $id ) . '_preview" src="' . esc_url( $src ) . '" style="' . ( $src ? '' : 'display:none;' ) . 'max-width:120px;height:auto;margin:8px 0;display:block;"><button class="button" data-taka-media-select="' . esc_attr( $id ) . '" data-taka-media-preview="' . esc_attr( $id ) . '_preview">' . esc_html__( 'Bild auswählen', 'taka-tour' ) . '</button> <button class="button" data-taka-media-clear="' . esc_attr( $id ) . '" data-taka-media-preview="' . esc_attr( $id ) . '_preview">' . esc_html__( 'Entfernen', 'taka-tour' ) . '</button></p>'; }
	private static function relation_select( $field, $label, $post_id, $post_type, $multiple = false ) { $current = self::meta( $post_id, $field, $multiple ? array() : 0 ); $posts = get_posts( array( 'post_type' => $post_type, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ); $html = '<select ' . ( $multiple ? 'multiple size="5"' : '' ) . ' name="_taka_' . esc_attr( $field ) . ( $multiple ? '[]' : '' ) . '"><option value="">—</option>'; foreach ( $posts as $item ) { $selected = $multiple ? selected( in_array( $item->ID, (array) $current, true ), true, false ) : selected( (int) $current, $item->ID, false ); $html .= '<option value="' . esc_attr( $item->ID ) . '" ' . $selected . '>' . esc_html( get_the_title( $item ) ) . '</option>'; } $html .= '</select>'; self::field_wrap( $label, $html ); }
}

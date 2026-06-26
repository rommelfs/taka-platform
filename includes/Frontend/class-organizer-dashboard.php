<?php
/**
 * Frontend organizer self-service dashboard.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Organizer_Dashboard {
	const NONCE = 'taka_platform_organizer_dashboard_nonce';
	const DASHBOARD_PAGE_OPTION = 'taka_platform_organizer_dashboard_page_id';

	/** Render dashboard shortcode. */
	public static function render() {
		if ( ! is_user_logged_in() ) {
			return self::render_login_required();
		}

		if ( current_user_can( 'upload_files' ) && function_exists( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'taka-platform-media-fields' );
		}

		$message = self::handle_post_actions();
		$action  = sanitize_key( wp_unslash( $_GET['taka_dashboard_action'] ?? '' ) );
		$event_id = absint( $_GET['event_id'] ?? 0 );

		if ( in_array( $action, array( 'new', 'edit' ), true ) ) {
			return self::wrap( self::render_event_form( $action, $event_id, $message ) );
		}

		return self::wrap( self::render_event_list( $message ) );
	}

	private static function wrap( $content ) {
		return '<div class="taka-organizer-dashboard">' . $content . '</div>';
	}

	private static function render_login_required() {
		$redirect = self::dashboard_url();
		return self::wrap( '<p>' . esc_html( taka_tour_translate( 'dashboard.login_required', 'Please log in to manage your events.' ) ) . '</p><p><a class="taka-button" href="' . esc_url( wp_login_url( $redirect ) ) . '">' . esc_html__( 'Log in', 'taka-platform' ) . '</a></p>' );
	}

	private static function render_event_list( $message = '' ) {
		$is_admin   = current_user_can( 'manage_options' );
		$organizers = self::current_user_organizers();
		$events = self::dashboard_events();
		if ( ! $is_admin && empty( $organizers ) && empty( $events ) ) {
			return self::notice( taka_tour_translate( 'dashboard.no_organizer_assigned', 'No organizer profile is assigned to your user account. Please contact an administrator.' ), 'warning' );
		}

		$out  = self::notice( $message, 'success' );
		$out .= $is_admin ? '<p class="taka-dashboard-notice">' . esc_html__( 'Admin mode: showing all organizers and events.', 'taka-platform' ) . '</p>' : '';
		$out .= '<h2>' . esc_html( taka_tour_translate( 'dashboard.organizer_dashboard', 'Organizer dashboard' ) ) . '</h2>';
		$out .= '<h3>' . esc_html__( 'Assigned organizers', 'taka-platform' ) . '</h3><ul>';
		foreach ( $organizers as $organizer ) {
			$out .= '<li>' . esc_html( get_the_title( $organizer ) ) . '</li>';
		}
		$out .= '</ul>';
		$out .= '<p><a class="taka-button" href="' . esc_url( add_query_arg( 'taka_dashboard_action', 'new' ) ) . '">' . esc_html( taka_tour_translate( 'dashboard.create_event', 'Create event' ) ) . '</a></p>';
		$out .= '<h3>' . esc_html( taka_tour_translate( 'dashboard.my_events', 'My events' ) ) . '</h3>';

		if ( empty( $events ) ) {
			return $out . '<p>' . esc_html__( 'No events found.', 'taka-platform' ) . '</p>';
		}

		$out .= '<div class="taka-dashboard-table-wrap"><table class="taka-dashboard-table"><thead><tr><th>' . esc_html__( 'Event', 'taka-platform' ) . '</th><th>' . esc_html__( 'Date', 'taka-platform' ) . '</th><th>' . esc_html__( 'Status', 'taka-platform' ) . '</th><th>' . esc_html__( 'Organizer', 'taka-platform' ) . '</th><th>' . esc_html__( 'Venue', 'taka-platform' ) . '</th><th>' . esc_html__( 'Tickets', 'taka-platform' ) . '</th><th>' . esc_html__( 'Modified', 'taka-platform' ) . '</th><th>' . esc_html__( 'Actions', 'taka-platform' ) . '</th></tr></thead><tbody>';
		foreach ( $events as $event ) {
			$event_id = (int) $event->ID;
			$organizer_id = absint( get_post_meta( $event_id, '_taka_organizer_id', true ) );
			$venue_id = absint( get_post_meta( $event_id, '_taka_venue_id', true ) );
			$out .= '<tr><td>' . esc_html( get_the_title( $event ) ) . '</td><td>' . esc_html( get_post_meta( $event_id, '_taka_date_start', true ) ) . '</td><td>' . esc_html( $event->post_status ) . '</td><td>' . esc_html( $organizer_id ? get_the_title( $organizer_id ) : '' ) . '</td><td>' . esc_html( $venue_id ? get_the_title( $venue_id ) : '' ) . '</td><td>' . esc_html( get_post_meta( $event_id, '_taka_ticket_status', true ) ) . '</td><td>' . esc_html( get_post_modified_time( get_option( 'date_format' ), false, $event, true ) ) . '</td><td>' . self::event_actions( $event_id ) . '</td></tr>';
		}
		$out .= '</tbody></table></div>';
		return $out;
	}

	private static function event_actions( $event_id ) {
		$edit = add_query_arg( array( 'taka_dashboard_action' => 'edit', 'event_id' => $event_id ) );
		$anchor = '#seminar-' . get_post_field( 'post_name', $event_id );
		$out = '<a class="button" href="' . esc_url( $edit ) . '">' . esc_html( taka_tour_translate( 'dashboard.edit_event', 'Edit event' ) ) . '</a> ';
		$out .= '<form method="post" style="display:inline">' . wp_nonce_field( self::NONCE, self::NONCE, true, false ) . '<input type="hidden" name="taka_dashboard_action" value="duplicate"><input type="hidden" name="event_id" value="' . esc_attr( (string) $event_id ) . '"><button class="button" type="submit">' . esc_html( taka_tour_translate( 'dashboard.duplicate', 'Duplicate' ) ) . '</button></form> ';
		$out .= '<a class="button" href="' . esc_url( home_url( '/' ) . $anchor ) . '">' . esc_html( taka_tour_translate( 'dashboard.view_frontend_section', 'View frontend section' ) ) . '</a>';
		return $out;
	}

	private static function render_event_form( $action, $event_id, $message = '' ) {
		$is_edit = 'edit' === $action;
		if ( $is_edit && ! self::current_user_can_manage_event( $event_id ) ) {
			return self::notice( taka_tour_translate( 'dashboard.validation_error', 'Validation error.' ), 'error' );
		}
		$event = $is_edit ? get_post( $event_id ) : null;
		$allowed_organizers = self::current_user_organizers();
		$organizer_id = $is_edit ? absint( get_post_meta( $event_id, '_taka_organizer_id', true ) ) : ( 1 === count( $allowed_organizers ) ? (int) $allowed_organizers[0]->ID : 0 );
		$venues = get_posts( array( 'post_type' => TAKA_PLATFORM_CPT_VENUE, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$out = self::notice( $message, 'success' );
		$out .= '<h2>' . esc_html( $is_edit ? taka_tour_translate( 'dashboard.edit_event', 'Edit event' ) : taka_tour_translate( 'dashboard.create_event', 'Create event' ) ) . '</h2>';
		$out .= '<form method="post" class="taka-dashboard-form">' . wp_nonce_field( self::NONCE, self::NONCE, true, false ) . '<input type="hidden" name="taka_dashboard_action" value="save"><input type="hidden" name="event_id" value="' . esc_attr( (string) $event_id ) . '">';
		$out .= self::input( 'title', __( 'Title', 'taka-platform' ), $event ? get_the_title( $event ) : '', true );
		$out .= self::input( 'subtitle', __( 'Subtitle', 'taka-platform' ), self::meta_value( $event_id, 'subtitle' ) );
		$out .= self::textarea_input( 'short_description', __( 'Short description', 'taka-platform' ), self::meta_value( $event_id, 'short_description' ) ?: ( $event ? $event->post_content : '' ) );
		$out .= self::textarea_input( 'long_description', __( 'Long description', 'taka-platform' ), self::meta_value( $event_id, 'long_description' ) );
		$out .= self::organizer_select( $allowed_organizers, $organizer_id );
		$out .= self::venue_select( $venues, absint( self::meta_value( $event_id, 'venue_id' ) ) );
		foreach ( array( 'date_start' => __( 'Start date', 'taka-platform' ), 'date_end' => __( 'End date', 'taka-platform' ), 'time_start' => __( 'Start time', 'taka-platform' ), 'time_end' => __( 'End time', 'taka-platform' ), 'doors_open' => __( 'Doors open', 'taka-platform' ), 'format' => __( 'Format', 'taka-platform' ), 'audience' => __( 'Audience', 'taka-platform' ), 'level' => __( 'Level', 'taka-platform' ), 'ticket_provider' => __( 'Ticket provider', 'taka-platform' ), 'ticket_shop_url' => __( 'Ticket shop URL', 'taka-platform' ), 'ticket_status' => __( 'Ticket status', 'taka-platform' ) ) as $field => $label ) {
			$out .= self::input( $field, $label, self::meta_value( $event_id, $field ) );
		}
		$out .= self::media_field( 'image_id', __( 'Action image', 'taka-platform' ), self::meta_value( $event_id, 'image_id' ), false );
		$out .= self::media_field( 'group_image_id', __( 'Group image', 'taka-platform' ), self::meta_value( $event_id, 'group_image_id' ), false );
		$out .= self::media_field( 'gallery_image_ids', __( 'Gallery images', 'taka-platform' ), self::meta_value( $event_id, 'gallery_image_ids' ), true );
		foreach ( array( 'ticket_card_text' => __( 'Ticket card text', 'taka-platform' ), 'notes' => __( 'Practical notes', 'taka-platform' ), 'parking' => __( 'Parking notes', 'taka-platform' ), 'accessibility' => __( 'Accessibility notes', 'taka-platform' ) ) as $field => $label ) {
			$out .= self::textarea_input( $field, $label, self::meta_value( $event_id, $field ) );
		}
		$out .= '<p><label>' . esc_html__( 'Status', 'taka-platform' ) . '<br><select name="event_status"><option value="draft" ' . selected( $event ? $event->post_status : 'draft', 'draft', false ) . '>' . esc_html__( 'Draft', 'taka-platform' ) . '</option><option value="publish" ' . selected( $event ? $event->post_status : 'draft', 'publish', false ) . '>' . esc_html__( 'Publish', 'taka-platform' ) . '</option></select></label></p>';
		$out .= '<p><button class="taka-button" type="submit">' . esc_html( taka_tour_translate( 'dashboard.save', 'Save' ) ) . '</button> <a class="taka-button taka-button-secondary" href="' . esc_url( remove_query_arg( array( 'taka_dashboard_action', 'event_id' ) ) ) . '">' . esc_html( taka_tour_translate( 'dashboard.cancel', 'Cancel' ) ) . '</a></p></form>';
		return $out;
	}

	private static function handle_post_actions() {
		if ( empty( $_POST['taka_dashboard_action'] ) ) {
			return '';
		}
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return taka_tour_translate( 'dashboard.validation_error', 'Validation error.' );
		}
		$action = sanitize_key( wp_unslash( $_POST['taka_dashboard_action'] ) );
		if ( 'duplicate' === $action ) {
			return self::duplicate_event( absint( $_POST['event_id'] ?? 0 ) );
		}
		if ( 'save' === $action ) {
			return self::save_event();
		}
		return '';
	}

	private static function duplicate_event( $event_id ) {
		if ( ! self::current_user_can_manage_event( $event_id ) ) {
			return taka_tour_translate( 'dashboard.validation_error', 'Validation error.' );
		}
		$event = get_post( $event_id );
		$new_id = wp_insert_post( array( 'post_type' => TAKA_PLATFORM_CPT_EVENT, 'post_status' => 'draft', 'post_title' => sprintf( __( 'Copy of %s', 'taka-platform' ), get_the_title( $event ) ), 'post_content' => $event->post_content, 'post_author' => get_current_user_id() ), true );
		if ( is_wp_error( $new_id ) ) {
			return $new_id->get_error_message();
		}
		foreach ( self::event_meta_fields() as $field ) {
			update_post_meta( $new_id, '_taka_' . $field, get_post_meta( $event_id, '_taka_' . $field, true ) );
		}
		return taka_tour_translate( 'dashboard.event_saved', 'Event saved.' );
	}

	private static function save_event() {
		$event_id = absint( $_POST['event_id'] ?? 0 );
		if ( $event_id && ! self::current_user_can_manage_event( $event_id ) ) {
			return taka_tour_translate( 'dashboard.validation_error', 'Validation error.' );
		}
		$allowed = self::current_user_organizer_ids();
		$organizer_id = current_user_can( 'manage_options' ) ? absint( $_POST['organizer_id'] ?? 0 ) : ( 1 === count( $allowed ) ? (int) $allowed[0] : absint( $_POST['organizer_id'] ?? 0 ) );
		if ( ! current_user_can( 'manage_options' ) && $organizer_id && ! in_array( $organizer_id, $allowed, true ) ) {
			return taka_tour_translate( 'dashboard.validation_error', 'Validation error.' );
		}
		$status = in_array( sanitize_key( $_POST['event_status'] ?? 'draft' ), array( 'draft', 'publish' ), true ) ? sanitize_key( $_POST['event_status'] ) : 'draft';
		$post_data = array( 'post_type' => TAKA_PLATFORM_CPT_EVENT, 'post_status' => $status, 'post_title' => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ), 'post_content' => sanitize_textarea_field( wp_unslash( $_POST['short_description'] ?? '' ) ), 'post_author' => get_current_user_id() );
		if ( '' === $post_data['post_title'] ) {
			return taka_tour_translate( 'dashboard.validation_error', 'Validation error.' );
		}
		if ( $event_id ) { $post_data['ID'] = $event_id; $saved_id = wp_update_post( $post_data, true ); } else { $saved_id = wp_insert_post( $post_data, true ); }
		if ( is_wp_error( $saved_id ) ) { return $saved_id->get_error_message(); }
		update_post_meta( $saved_id, '_taka_organizer_id', $organizer_id );
		foreach ( self::event_meta_fields() as $field ) {
			if ( 'organizer_id' === $field ) { continue; }
			$value = wp_unslash( $_POST[ $field ] ?? '' );
			if ( in_array( $field, array( 'venue_id', 'image_id', 'group_image_id' ), true ) ) { $value = absint( $value ); }
			elseif ( 'gallery_image_ids' === $field ) { $value = implode( ',', array_map( 'absint', preg_split( '/\s*,\s*/', (string) $value ) ) ); }
			elseif ( 'ticket_shop_url' === $field ) { $value = esc_url_raw( $value ); }
			elseif ( in_array( $field, array( 'short_description', 'long_description', 'ticket_card_text', 'notes', 'parking', 'accessibility' ), true ) ) { $value = sanitize_textarea_field( $value ); }
			else { $value = sanitize_text_field( $value ); }
			update_post_meta( $saved_id, '_taka_' . $field, $value );
		}
		return taka_tour_translate( 'dashboard.event_saved', 'Event saved.' );
	}

	private static function event_meta_fields() { return array( 'subtitle', 'organizer_id', 'venue_id', 'date_start', 'date_end', 'time_start', 'time_end', 'doors_open', 'format', 'audience', 'level', 'ticket_provider', 'ticket_shop_url', 'ticket_status', 'image_id', 'group_image_id', 'gallery_image_ids', 'short_description', 'long_description', 'ticket_card_text', 'notes', 'parking', 'accessibility' ); }
	private static function dashboard_events() {
		$args = array( 'post_type' => TAKA_PLATFORM_CPT_EVENT, 'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ), 'posts_per_page' => -1, 'orderby' => 'modified', 'order' => 'DESC' );
		if ( ! current_user_can( 'manage_options' ) ) {
			$ids = get_posts( array( 'post_type' => TAKA_PLATFORM_CPT_EVENT, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
			$ids = array_values( array_filter( array_map( 'absint', $ids ), static function ( $event_id ) { return TAKA_Platform_Admin::user_can_access_content( get_current_user_id(), $event_id, 'edit' ); } ) );
			if ( empty( $ids ) ) {
				return array();
			}
			$args['post__in'] = $ids;
		}
		return get_posts( $args );
	}
	private static function current_user_organizer_ids() { $ids = get_user_meta( get_current_user_id(), '_taka_platform_organizer_ids', true ); if ( ! is_array( $ids ) ) { $ids = array_filter( preg_split( '/\s*,\s*/', (string) $ids ) ); } return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ); }
	private static function current_user_organizers() { $args = array( 'post_type' => TAKA_PLATFORM_CPT_ORGANIZER, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ); if ( ! current_user_can( 'manage_options' ) ) { $ids = self::current_user_organizer_ids(); if ( empty( $ids ) ) { return array(); } $args['post__in'] = $ids; } return get_posts( $args ); }
	private static function current_user_can_manage_event( $event_id ) { return TAKA_Platform_Admin::user_can_access_content( get_current_user_id(), $event_id, 'edit' ); }
	private static function meta_value( $event_id, $field ) { return $event_id ? (string) get_post_meta( $event_id, '_taka_' . $field, true ) : ''; }
	private static function input( $name, $label, $value, $required = false ) { return '<p><label>' . esc_html( $label ) . '<br><input class="widefat" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" ' . ( $required ? 'required' : '' ) . '></label></p>'; }
	private static function textarea_input( $name, $label, $value ) { return '<p><label>' . esc_html( $label ) . '<br><textarea class="widefat" rows="4" name="' . esc_attr( $name ) . '">' . esc_textarea( (string) $value ) . '</textarea></label></p>'; }
	private static function organizer_select( $organizers, $selected ) { $out = '<p><label>' . esc_html__( 'Organizer', 'taka-platform' ) . '<br><select name="organizer_id"><option value="0">—</option>'; foreach ( $organizers as $organizer ) { $out .= '<option value="' . esc_attr( (string) $organizer->ID ) . '" ' . selected( $selected, $organizer->ID, false ) . '>' . esc_html( get_the_title( $organizer ) ) . '</option>'; } return $out . '</select></label></p>'; }
	private static function venue_select( $venues, $selected ) { $out = '<p><label>' . esc_html__( 'Venue', 'taka-platform' ) . '<br><select name="venue_id"><option value="0">—</option>'; foreach ( $venues as $venue ) { $out .= '<option value="' . esc_attr( (string) $venue->ID ) . '" ' . selected( $selected, $venue->ID, false ) . '>' . esc_html( get_the_title( $venue ) ) . '</option>'; } return $out . '</select></label></p>'; }
	private static function media_field( $name, $label, $value, $multiple ) { $input_id = 'taka_dashboard_' . sanitize_key( $name ); $preview = ''; foreach ( array_filter( array_map( 'absint', preg_split( '/\s*,\s*/', (string) $value ) ) ) as $id ) { $url = wp_get_attachment_image_url( $id, 'thumbnail' ); if ( $url ) { $preview .= '<img src="' . esc_url( $url ) . '" alt="" style="max-width:90px;height:auto;margin:4px;">'; } } return '<p><label>' . esc_html( $label ) . '</label><br><input id="' . esc_attr( $input_id ) . '" type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"> <button type="button" class="button" data-taka-media-pick data-multiple="' . ( $multiple ? '1' : '0' ) . '" data-target="' . esc_attr( $input_id ) . '" data-preview="' . esc_attr( $input_id . '_preview' ) . '">' . esc_html__( 'Select image', 'taka-platform' ) . '</button> <button type="button" class="button" data-taka-media-remove data-target="' . esc_attr( $input_id ) . '" data-preview="' . esc_attr( $input_id . '_preview' ) . '">' . esc_html__( 'Remove image', 'taka-platform' ) . '</button><span id="' . esc_attr( $input_id . '_preview' ) . '">' . $preview . '</span></p>'; }
	private static function dashboard_url() { $page_id = absint( get_option( self::DASHBOARD_PAGE_OPTION, 0 ) ); if ( $page_id && function_exists( 'get_permalink' ) ) { $url = get_permalink( $page_id ); if ( $url ) { return $url; } } return self::current_url(); }
	private static function current_url() { return ( is_ssl() ? 'https://' : 'http://' ) . ( $_SERVER['HTTP_HOST'] ?? '' ) . ( $_SERVER['REQUEST_URI'] ?? '' ); }
	private static function notice( $message, $type = 'success' ) { return '' === trim( (string) $message ) ? '' : '<div class="taka-dashboard-message taka-dashboard-message--' . esc_attr( $type ) . '">' . esc_html( $message ) . '</div>'; }
}

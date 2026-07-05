<?php
/**
 * Private People and Registration module for TAKA Platform.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_People_Module {
	const ADMIN_PAGE_SLUG = 'taka-platform-people';
	const SAVE_ACTION = 'taka_people_save_person';
	const MERGE_ACTION = 'taka_people_merge_person';
	const PRIVACY_ACTION = 'taka_people_privacy_action';

	private static $person_repository = null;
	private static $registration_repository = null;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ), 0 );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ), 18 );
		add_action( 'admin_init', array( __CLASS__, 'ensure_capabilities' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_migrate_existing_ticket_orders' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( __CLASS__, 'handle_save_person' ) );
		add_action( 'admin_post_' . self::MERGE_ACTION, array( __CLASS__, 'handle_merge_person' ) );
		add_action( 'admin_post_' . self::PRIVACY_ACTION, array( __CLASS__, 'handle_privacy_action' ) );
	}

	public static function register_post_types() {
		register_post_type(
			TAKA_PLATFORM_CPT_PERSON,
			array(
				'labels'              => array(
					'name'          => __( 'People', 'taka-platform' ),
					'singular_name' => __( 'Person', 'taka-platform' ),
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

		register_post_type(
			TAKA_PLATFORM_CPT_REGISTRATION,
			array(
				'labels'              => array(
					'name'          => __( 'Registrations', 'taka-platform' ),
					'singular_name' => __( 'Registration', 'taka-platform' ),
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
			__( 'People', 'taka-platform' ),
			__( 'People', 'taka-platform' ),
			'view_taka_people',
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
			'manage_taka_people',
			'view_taka_people',
			'edit_taka_people',
			'view_taka_registrations',
			'edit_taka_registrations',
		);
	}

	public static function person_repository() {
		if ( null === self::$person_repository ) {
			self::$person_repository = new TAKA_People_Person_Repository();
		}
		return self::$person_repository;
	}

	public static function registration_repository() {
		if ( null === self::$registration_repository ) {
			self::$registration_repository = new TAKA_People_Registration_Repository();
		}
		return self::$registration_repository;
	}

	public static function admin_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::ADMIN_PAGE_SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	public static function person_link( $person_id, $label = '' ) {
		$person_id = absint( $person_id );
		if ( ! $person_id || ! current_user_can( 'view_taka_people' ) ) {
			return '';
		}
		$person = self::person_repository()->find_by_id( $person_id );
		$label = '' !== trim( (string) $label ) ? $label : ( $person ? TAKA_People_Person::full_name( $person ) : sprintf( __( 'Person #%d', 'taka-platform' ), $person_id ) );
		return '<a href="' . esc_url( self::admin_url( array( 'person_id' => $person_id ) ) ) . '">' . esc_html( $label ) . '</a>';
	}

	public static function sync_order_people_and_registrations( $order ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'to_array' ) || ! class_exists( 'TAKA_Ticketing_Module' ) || ! class_exists( 'TAKA_Ticketing_Order' ) ) {
			return $order;
		}

		$data = $order->to_array();
		$buyer = is_array( $data['buyer'] ?? null ) ? $data['buyer'] : array();
		$participant = is_array( $data['participant'] ?? null ) ? $data['participant'] : array();
		$participants = is_array( $data['participants'] ?? null ) && ! empty( $data['participants'] ) ? array_values( $data['participants'] ) : array( $participant );
		$buyer_person = self::person_repository()->create_or_update_from_person_data( TAKA_People_Person::from_buyer( $buyer ) );

		if ( is_wp_error( $buyer_person ) ) {
			return $order;
		}

		$data['buyer_person_id'] = absint( $buyer_person['id'] ?? 0 );
		$data['participant_person_ids'] = array();
		$registration_ids = array_values( array_filter( array_map( 'absint', (array) ( $data['registration_ids'] ?? array() ) ) ) );

		foreach ( $participants as $index => $participant_item ) {
			$participant_item = is_array( $participant_item ) ? $participant_item : array();
			$participant_person = self::person_repository()->create_or_update_from_person_data( TAKA_People_Person::from_participant( $participant_item ) );
			if ( is_wp_error( $participant_person ) ) {
				continue;
			}
			$person_id = absint( $participant_person['id'] ?? 0 );
			if ( ! $person_id ) {
				continue;
			}
			if ( 0 === $index ) {
				$data['participant_person_id'] = $person_id;
				$data['participant'] = $participant_item;
			}
			$data['participant_person_ids'][] = $person_id;

			if ( ! empty( $data['event_id'] ) ) {
				$registration_data = $data;
				$registration_data['participant_index'] = $index;
				$registration = TAKA_People_Registration::from_order_data( $registration_data, $person_id );
				$saved_registration = self::registration_repository()->save( $registration );
				if ( ! is_wp_error( $saved_registration ) && ! empty( $saved_registration['id'] ) ) {
					$registration_ids[] = absint( $saved_registration['id'] );
				}
			}
		}

		$data['participant_person_ids'] = array_values( array_filter( array_map( 'absint', $data['participant_person_ids'] ) ) );
		$data['registration_ids'] = array_values( array_unique( $registration_ids ) );
		return TAKA_Ticketing_Module::order_repository()->save( new TAKA_Ticketing_Order( $data ) );
	}

	public static function maybe_migrate_existing_ticket_orders() {
		if ( ! is_admin() || ! current_user_can( 'manage_taka_people' ) || ! class_exists( 'TAKA_Ticketing_Module' ) ) {
			return;
		}

		$count = 0;
		foreach ( TAKA_Ticketing_Module::order_repository()->query( array( 'per_page' => 50 ) ) as $order ) {
			$data = $order->to_array();
			$needs_registration = ! empty( $data['event_id'] );
			if ( ! empty( $data['buyer_person_id'] ) && ! empty( $data['participant_person_id'] ) && ( ! $needs_registration || ! empty( $data['registration_ids'] ) ) ) {
				continue;
			}
			self::sync_order_people_and_registrations( $order );
			$count++;
			if ( $count >= 20 ) {
				break;
			}
		}
	}

	public static function handle_save_person() {
		if ( ! current_user_can( 'edit_taka_people' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::SAVE_ACTION, '_wpnonce' );
		$person = isset( $_POST['person'] ) && is_array( $_POST['person'] ) ? wp_unslash( $_POST['person'] ) : array();
		$result = self::person_repository()->save( $person );
		$args = array();
		if ( is_wp_error( $result ) ) {
			$args['person_error'] = $result->get_error_message();
			if ( ! empty( $person['id'] ) ) {
				$args['person_id'] = absint( $person['id'] );
			}
		} else {
			$args['person_saved'] = '1';
			$args['person_id'] = absint( $result['id'] ?? 0 );
		}
		wp_safe_redirect( self::admin_url( $args ) );
		exit;
	}

	public static function handle_merge_person() {
		if ( ! current_user_can( 'manage_taka_people' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::MERGE_ACTION, '_wpnonce' );

		$target_id = absint( $_POST['target_person_id'] ?? 0 );
		$source_id = absint( $_POST['source_person_id'] ?? 0 );
		$result = self::merge_people( $target_id, $source_id );
		$args = array( 'person_id' => $target_id );
		if ( is_wp_error( $result ) ) {
			$args['person_error'] = $result->get_error_message();
		} else {
			$args['person_merged'] = '1';
		}
		wp_safe_redirect( self::admin_url( $args ) );
		exit;
	}

	public static function handle_privacy_action() {
		if ( ! current_user_can( 'manage_taka_people' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::PRIVACY_ACTION, '_wpnonce' );

		$person_id = absint( $_POST['person_id'] ?? 0 );
		$task = sanitize_key( wp_unslash( $_POST['task'] ?? '' ) );
		if ( 'export' === $task ) {
			self::export_person_data( $person_id );
		}

		$result = null;
		if ( 'anonymize' === $task ) {
			$result = self::anonymize_person( $person_id );
		} elseif ( 'delete' === $task ) {
			$result = self::delete_person_if_empty( $person_id );
		} else {
			$result = new WP_Error( 'taka_people_unknown_privacy_action', __( 'Unknown privacy action.', 'taka-platform' ) );
		}

		$args = array( 'person_id' => $person_id );
		if ( is_wp_error( $result ) ) {
			$args['person_error'] = $result->get_error_message();
		} else {
			$args['privacy_updated'] = $task;
			if ( 'delete' === $task ) {
				unset( $args['person_id'] );
			}
		}
		wp_safe_redirect( self::admin_url( $args ) );
		exit;
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'view_taka_people' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}

		$person_id = absint( $_GET['person_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap taka-people-admin"><h1>' . esc_html__( 'People', 'taka-platform' ) . '</h1>';
		if ( ! empty( $_GET['person_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Person saved.', 'taka-platform' ) . '</p></div>';
		}
		if ( ! empty( $_GET['person_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['person_error'] ) ) ) . '</p></div>';
		}
		if ( ! empty( $_GET['person_merged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'People merged. Registration history and order references were moved to the remaining profile.', 'taka-platform' ) . '</p></div>';
		}
		if ( ! empty( $_GET['privacy_updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Privacy action completed.', 'taka-platform' ) . '</p></div>';
		}

		if ( $person_id ) {
			self::render_person_profile( $person_id );
		} elseif ( ! empty( $_GET['new'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::render_person_profile( 0 );
		} else {
			self::render_people_overview();
		}
		echo '</div>';
	}

	private static function render_people_overview() {
		$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$people = self::person_repository()->query( array( 'search' => $search, 'per_page' => 200 ) );
		$dashboard = self::community_dashboard_data();
		echo '<p class="description">' . esc_html__( 'People are private community records reused across orders, registrations, organizer work and future CRM features.', 'taka-platform' ) . '</p>';
		self::render_people_dashboard( $dashboard );
		echo '<p><a class="button button-primary" href="' . esc_url( self::admin_url( array( 'new' => '1' ) ) ) . '">' . esc_html__( 'Add person', 'taka-platform' ) . '</a></p>';
		?>
		<form class="taka-people-search" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::ADMIN_PAGE_SLUG ); ?>">
			<label class="screen-reader-text" for="taka-people-search-input"><?php echo esc_html__( 'Search people', 'taka-platform' ); ?></label>
			<input id="taka-people-search-input" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search by name, email, dojo, country, rank or association', 'taka-platform' ); ?>">
			<?php submit_button( __( 'Search', 'taka-platform' ), '', '', false ); ?>
		</form>
		<table class="widefat striped taka-people-table">
			<thead><tr>
				<th><?php echo esc_html__( 'Name', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Country', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Dojo', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Events attended', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Upcoming', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Last participation', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Tags', 'taka-platform' ); ?></th>
			</tr></thead>
			<tbody>
				<?php if ( empty( $people ) ) : ?>
					<tr><td colspan="7"><?php echo esc_html__( 'No people found yet.', 'taka-platform' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $people as $person ) : ?>
					<?php $stats = self::person_registration_stats( absint( $person['id'] ?? 0 ) ); ?>
					<tr>
						<td><strong><?php echo self::person_link( $person['id'], TAKA_People_Person::full_name( $person ) ?: ( $person['email'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong><br><span class="description"><?php echo esc_html( $person['email'] ?? '' ); ?></span></td>
						<td><?php echo esc_html( self::country_label( $person['country'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( $person['dojo'] ?? '' ); ?></td>
						<td><?php echo esc_html( (string) $stats['attended'] ); ?></td>
						<td><?php echo esc_html( (string) $stats['upcoming'] ); ?></td>
						<td><?php echo esc_html( $stats['last_participation'] ); ?></td>
						<td><?php echo esc_html( implode( ', ', (array) ( $person['tags'] ?? array() ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private static function render_people_dashboard( $dashboard ) {
		?>
		<div class="taka-people-dashboard">
			<?php self::people_metric_card( __( 'Total people', 'taka-platform' ), $dashboard['total_people'] ); ?>
			<?php self::people_metric_card( __( 'Active this year', 'taka-platform' ), $dashboard['active_this_year'] ); ?>
			<?php self::people_metric_card( __( 'New people', 'taka-platform' ), $dashboard['new_people'] ); ?>
			<?php self::people_metric_card( __( 'Returning participants', 'taka-platform' ), $dashboard['returning_participants'] ); ?>
			<?php self::people_metric_card( __( 'Countries represented', 'taka-platform' ), $dashboard['countries_represented'] ); ?>
		</div>
		<div class="taka-admin-grid taka-admin-grid--two">
			<section class="taka-admin-panel">
				<h2><?php echo esc_html__( 'Most active dojos', 'taka-platform' ); ?></h2>
				<?php self::render_ranked_list( $dashboard['most_active_dojos'] ); ?>
			</section>
			<section class="taka-admin-panel">
				<h2><?php echo esc_html__( 'Most active organizers', 'taka-platform' ); ?></h2>
				<?php self::render_ranked_list( $dashboard['most_active_organizers'] ); ?>
				<h3><?php echo esc_html__( 'Upcoming birthdays', 'taka-platform' ); ?></h3>
				<?php self::render_ranked_list( $dashboard['upcoming_birthdays'], false ); ?>
			</section>
		</div>
		<?php
	}

	private static function people_metric_card( $label, $value ) {
		?>
		<div class="taka-people-card">
			<span><?php echo esc_html( $label ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( absint( $value ) ) ); ?></strong>
		</div>
		<?php
	}

	private static function render_ranked_list( $items, $show_count = true ) {
		if ( empty( $items ) ) {
			echo '<p class="description">' . esc_html__( 'No data yet.', 'taka-platform' ) . '</p>';
			return;
		}
		echo '<ol class="taka-people-ranked-list">';
		foreach ( $items as $label => $value ) {
			$text = $show_count ? sprintf( '%1$s (%2$d)', $label, absint( $value ) ) : (string) $value;
			echo '<li>' . esc_html( $text ) . '</li>';
		}
		echo '</ol>';
	}

	private static function render_person_profile( $person_id ) {
		$person = $person_id ? self::person_repository()->find_by_id( $person_id ) : null;
		if ( $person_id && ! $person ) {
			echo '<p>' . esc_html__( 'Person not found.', 'taka-platform' ) . '</p>';
			return;
		}
		$person = $person ? $person : TAKA_People_Person::normalize( array() );
		echo '<p><a href="' . esc_url( self::admin_url() ) . '">&larr; ' . esc_html__( 'Back to people', 'taka-platform' ) . '</a></p>';
		echo '<h2>' . esc_html( TAKA_People_Person::full_name( $person ) ?: __( 'New person', 'taka-platform' ) ) . '</h2>';
		$tab = sanitize_key( $_GET['tab'] ?? 'overview' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! array_key_exists( $tab, self::person_profile_tabs() ) ) {
			$tab = 'overview';
		}
		self::render_person_profile_tabs( absint( $person['id'] ?? 0 ), $tab );
		if ( 'overview' === $tab ) {
			self::render_person_overview_tab( $person );
		} elseif ( empty( $person['id'] ) ) {
			echo '<p class="description">' . esc_html__( 'Save the person first to unlock history and community tabs.', 'taka-platform' ) . '</p>';
		} elseif ( 'history' === $tab ) {
			self::render_participation_history_tab( $person );
		} elseif ( 'orders' === $tab ) {
			self::render_orders_tab( $person );
		} elseif ( 'payments' === $tab ) {
			self::render_payments_tab( $person );
		} elseif ( 'products' === $tab ) {
			self::render_products_tab( $person );
		} elseif ( 'vouchers' === $tab ) {
			self::render_vouchers_tab( $person );
		} elseif ( 'volunteer' === $tab ) {
			self::render_activity_tab( $person, 'Volunteer' );
		} elseif ( 'organizer' === $tab ) {
			self::render_activity_tab( $person, 'Organizer' );
		} elseif ( 'notes' === $tab ) {
			self::render_notes_tab( $person );
		} else {
			self::render_future_tab( $tab );
		}
	}

	private static function person_profile_tabs() {
		return array(
			'overview'     => __( 'Overview', 'taka-platform' ),
			'history'      => __( 'Participation History', 'taka-platform' ),
			'orders'       => __( 'Orders', 'taka-platform' ),
			'payments'     => __( 'Payments', 'taka-platform' ),
			'products'     => __( 'Products', 'taka-platform' ),
			'vouchers'     => __( 'Vouchers', 'taka-platform' ),
			'volunteer'    => __( 'Volunteer Activities', 'taka-platform' ),
			'organizer'    => __( 'Organizer Activities', 'taka-platform' ),
			'notes'        => __( 'Notes', 'taka-platform' ),
			'documents'    => __( 'Documents', 'taka-platform' ),
			'certificates' => __( 'Certificates', 'taka-platform' ),
		);
	}

	private static function render_person_profile_tabs( $person_id, $current ) {
		if ( ! $person_id ) {
			return;
		}
		echo '<nav class="nav-tab-wrapper taka-people-profile-tabs">';
		foreach ( self::person_profile_tabs() as $tab => $label ) {
			$url = self::admin_url( array( 'person_id' => $person_id, 'tab' => $tab ) );
			echo '<a class="nav-tab ' . ( $current === $tab ? 'nav-tab-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	private static function render_person_overview_tab( $person ) {
		self::render_person_stats_cards( $person );
		self::render_person_form( $person );
		if ( ! empty( $person['id'] ) ) {
			self::render_relationships_panel( $person );
			self::render_merge_panel( $person );
			self::render_privacy_panel( $person );
		}
	}

	private static function render_person_form( $person ) {
		if ( ! current_user_can( 'edit_taka_people' ) ) {
			self::render_person_readonly( $person );
			return;
		}
		$countries = array( '' => __( 'Select country', 'taka-platform' ) ) + TAKA_Platform_Data::option_list_choices( 'country', TAKA_Platform_Data::platform_fallback_language() );
		?>
		<form class="taka-people-profile-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
			<input type="hidden" name="person[id]" value="<?php echo esc_attr( (string) absint( $person['id'] ?? 0 ) ); ?>">
			<?php wp_nonce_field( self::SAVE_ACTION ); ?>
			<div class="taka-people-profile-grid">
				<section><h3><?php echo esc_html__( 'Basic', 'taka-platform' ); ?></h3>
					<?php self::input( 'first_name', __( 'First name', 'taka-platform' ), $person ); ?>
					<?php self::input( 'last_name', __( 'Last name', 'taka-platform' ), $person ); ?>
					<?php self::input( 'email', __( 'Email', 'taka-platform' ), $person, 'email' ); ?>
					<?php self::input( 'phone', __( 'Phone', 'taka-platform' ), $person ); ?>
					<?php self::select( 'country', __( 'Country', 'taka-platform' ), $countries, $person['country'] ?? '' ); ?>
					<?php self::input( 'birth_date', __( 'Birth date', 'taka-platform' ), $person, 'date' ); ?>
				</section>
				<section><h3><?php echo esc_html__( 'Seminar', 'taka-platform' ); ?></h3>
					<?php self::input( 'dojo', __( 'Dojo / Club', 'taka-platform' ), $person ); ?>
					<?php self::input( 'association', __( 'Association', 'taka-platform' ), $person ); ?>
					<?php self::input( 'style', __( 'Style', 'taka-platform' ), $person ); ?>
					<?php self::input( 'rank', __( 'Rank / Belt', 'taka-platform' ), $person ); ?>
				</section>
				<section><h3><?php echo esc_html__( 'Preferences', 'taka-platform' ); ?></h3>
					<?php self::select( 'dietary_preference', __( 'Dietary preference', 'taka-platform' ), self::dietary_labels(), $person['dietary_preference'] ?? 'none' ); ?>
					<?php self::textarea( 'allergies', __( 'Allergies', 'taka-platform' ), $person ); ?>
				</section>
				<section><h3><?php echo esc_html__( 'Administration', 'taka-platform' ); ?></h3>
					<?php self::textarea( 'notes', __( 'Notes', 'taka-platform' ), $person ); ?>
					<label><span><?php echo esc_html__( 'Tags', 'taka-platform' ); ?></span><input class="regular-text" type="text" name="person[tags]" value="<?php echo esc_attr( implode( ', ', (array) ( $person['tags'] ?? array() ) ) ); ?>" placeholder="<?php echo esc_attr__( 'VIP, Instructor, Volunteer', 'taka-platform' ); ?>"></label>
					<label><input type="checkbox" name="person[gdpr_consent]" value="1" <?php checked( '1', (string) ( $person['gdpr_consent'] ?? '0' ) ); ?>> <?php echo esc_html__( 'GDPR consent recorded', 'taka-platform' ); ?></label>
					<label><input type="checkbox" name="person[newsletter_consent]" value="1" <?php checked( '1', (string) ( $person['newsletter_consent'] ?? '0' ) ); ?>> <?php echo esc_html__( 'Newsletter consent recorded', 'taka-platform' ); ?></label>
				</section>
				<section class="taka-people-relationships"><h3><?php echo esc_html__( 'Relationships', 'taka-platform' ); ?></h3>
					<p class="description"><?php echo esc_html__( 'Examples: member of dojo, instructor, organizer, volunteer, speaker, sponsor, press or VIP.', 'taka-platform' ); ?></p>
					<?php self::render_relationship_fields( $person ); ?>
				</section>
				<section><h3><?php echo esc_html__( 'System', 'taka-platform' ); ?></h3>
					<p><strong><?php echo esc_html__( 'Created', 'taka-platform' ); ?>:</strong> <?php echo esc_html( $person['created_at'] ?? '' ); ?></p>
					<p><strong><?php echo esc_html__( 'Updated', 'taka-platform' ); ?>:</strong> <?php echo esc_html( $person['updated_at'] ?? '' ); ?></p>
				</section>
			</div>
			<?php submit_button( __( 'Save person', 'taka-platform' ) ); ?>
		</form>
		<?php
	}

	private static function render_person_stats_cards( $person ) {
		if ( empty( $person['id'] ) ) {
			return;
		}
		$stats = self::community_stats_for_person( absint( $person['id'] ) );
		?>
		<div class="taka-people-dashboard taka-people-dashboard--profile">
			<?php self::people_metric_card( __( 'Events attended', 'taka-platform' ), $stats['events_attended'] ); ?>
			<?php self::people_metric_card( __( 'Tours attended', 'taka-platform' ), $stats['tours_attended'] ); ?>
			<?php self::people_metric_card( __( 'Countries visited', 'taka-platform' ), $stats['countries_visited'] ); ?>
			<?php self::people_metric_card( __( 'Products purchased', 'taka-platform' ), $stats['products_purchased'] ); ?>
			<div class="taka-people-card">
				<span><?php echo esc_html__( 'Last participation', 'taka-platform' ); ?></span>
				<strong><?php echo esc_html( $stats['last_participation'] ?: '-' ); ?></strong>
			</div>
			<div class="taka-people-card">
				<span><?php echo esc_html__( 'Average registrations / year', 'taka-platform' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $stats['average_registrations_per_year'], 1 ) ); ?></strong>
			</div>
		</div>
		<?php
	}

	private static function render_relationship_fields( $person ) {
		$relationships = TAKA_People_Person::normalize_relationships( $person['relationships'] ?? array() );
		$rows = max( 4, count( $relationships ) + 1 );
		for ( $i = 0; $i < $rows; $i++ ) {
			$relationship = $relationships[ $i ] ?? array();
			?>
			<div class="taka-people-relationship-row">
				<select name="person[relationships][<?php echo esc_attr( $i ); ?>][type]">
					<option value=""><?php echo esc_html__( 'Relationship type', 'taka-platform' ); ?></option>
					<?php foreach ( self::relationship_types() as $type => $label ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>" <?php selected( (string) ( $relationship['type'] ?? '' ), $type ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="text" name="person[relationships][<?php echo esc_attr( $i ); ?>][label]" value="<?php echo esc_attr( $relationship['label'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'Dojo, organization or person', 'taka-platform' ); ?>">
				<input type="text" name="person[relationships][<?php echo esc_attr( $i ); ?>][notes]" value="<?php echo esc_attr( $relationship['notes'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'Notes', 'taka-platform' ); ?>">
				<input type="hidden" name="person[relationships][<?php echo esc_attr( $i ); ?>][related_person_id]" value="<?php echo esc_attr( absint( $relationship['related_person_id'] ?? 0 ) ); ?>">
				<input type="hidden" name="person[relationships][<?php echo esc_attr( $i ); ?>][related_object_id]" value="<?php echo esc_attr( absint( $relationship['related_object_id'] ?? 0 ) ); ?>">
			</div>
			<?php
		}
	}

	private static function render_relationships_panel( $person ) {
		$relationships = TAKA_People_Person::normalize_relationships( $person['relationships'] ?? array() );
		echo '<section class="taka-admin-panel taka-admin-panel--full"><h3>' . esc_html__( 'Relationship summary', 'taka-platform' ) . '</h3>';
		if ( empty( $relationships ) ) {
			echo '<p class="description">' . esc_html__( 'No relationships recorded yet.', 'taka-platform' ) . '</p></section>';
			return;
		}
		echo '<ul class="taka-people-relationship-list">';
		foreach ( $relationships as $relationship ) {
			$type = self::relationship_types()[ $relationship['type'] ] ?? $relationship['type'];
			echo '<li><strong>' . esc_html( $type ) . '</strong>: ' . esc_html( $relationship['label'] ) . ( '' !== $relationship['notes'] ? ' <span class="description">' . esc_html( $relationship['notes'] ) . '</span>' : '' ) . '</li>';
		}
		echo '</ul></section>';
	}

	private static function render_merge_panel( $person ) {
		if ( ! current_user_can( 'manage_taka_people' ) ) {
			return;
		}
		$person_id = absint( $person['id'] ?? 0 );
		$people = self::person_repository()->query( array( 'per_page' => 250 ) );
		?>
		<section class="taka-admin-panel taka-admin-panel--full">
			<h3><?php echo esc_html__( 'Merge duplicate person', 'taka-platform' ); ?></h3>
			<p class="description"><?php echo esc_html__( 'Merging moves registrations and order references to this profile, combines tags, relationships and notes, then removes the duplicate person record.', 'taka-platform' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::MERGE_ACTION ); ?>">
				<input type="hidden" name="target_person_id" value="<?php echo esc_attr( $person_id ); ?>">
				<?php wp_nonce_field( self::MERGE_ACTION, '_wpnonce' ); ?>
				<select name="source_person_id" required>
					<option value=""><?php echo esc_html__( 'Select duplicate profile', 'taka-platform' ); ?></option>
					<?php foreach ( $people as $candidate ) : ?>
						<?php if ( absint( $candidate['id'] ?? 0 ) === $person_id ) { continue; } ?>
						<option value="<?php echo esc_attr( $candidate['id'] ); ?>"><?php echo esc_html( TAKA_People_Person::full_name( $candidate ) ?: $candidate['email'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<button class="button" type="submit"><?php echo esc_html__( 'Merge into this person', 'taka-platform' ); ?></button>
			</form>
		</section>
		<?php
	}

	private static function render_privacy_panel( $person ) {
		if ( ! current_user_can( 'manage_taka_people' ) ) {
			return;
		}
		$person_id = absint( $person['id'] ?? 0 );
		?>
		<section class="taka-admin-panel taka-admin-panel--full">
			<h3><?php echo esc_html__( 'Privacy / GDPR', 'taka-platform' ); ?></h3>
			<p class="description"><?php echo esc_html__( 'Export or anonymize personal data. Financial records remain intact and continue to reference anonymized history where required.', 'taka-platform' ); ?></p>
			<form class="taka-people-privacy-actions" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::PRIVACY_ACTION ); ?>">
				<input type="hidden" name="person_id" value="<?php echo esc_attr( $person_id ); ?>">
				<?php wp_nonce_field( self::PRIVACY_ACTION, '_wpnonce' ); ?>
				<button class="button" type="submit" name="task" value="export"><?php echo esc_html__( 'Export personal data', 'taka-platform' ); ?></button>
				<button class="button" type="submit" name="task" value="anonymize"><?php echo esc_html__( 'Anonymize person', 'taka-platform' ); ?></button>
				<button class="button button-link-delete" type="submit" name="task" value="delete"><?php echo esc_html__( 'Delete empty person', 'taka-platform' ); ?></button>
			</form>
		</section>
		<?php
	}

	private static function render_participation_history_tab( $person ) {
		$registrations = self::registration_repository()->query( array( 'person_id' => absint( $person['id'] ), 'per_page' => -1 ) );
		self::render_chronological_history( $registrations );
	}

	private static function render_orders_tab( $person ) {
		$orders = self::orders_for_person( absint( $person['id'] ) );
		echo '<section class="taka-admin-panel taka-admin-panel--full"><h3>' . esc_html__( 'Orders', 'taka-platform' ) . '</h3>';
		if ( empty( $orders ) ) {
			echo '<p class="description">' . esc_html__( 'No orders recorded.', 'taka-platform' ) . '</p></section>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Order', 'taka-platform' ) . '</th><th>' . esc_html__( 'Event', 'taka-platform' ) . '</th><th>' . esc_html__( 'Amount', 'taka-platform' ) . '</th><th>' . esc_html__( 'Status', 'taka-platform' ) . '</th></tr></thead><tbody>';
		foreach ( $orders as $order ) {
			$data = $order->to_array();
			$link = class_exists( 'TAKA_Ticketing_Module' ) ? '<a href="' . esc_url( TAKA_Ticketing_Module::admin_url( array( 'order_id' => absint( $data['id'] ?? 0 ) ) ) ) . '">' . esc_html( $data['order_number'] ?? '' ) . '</a>' : esc_html( $data['order_number'] ?? '' );
			echo '<tr><td>' . $link . '</td><td>' . esc_html( $data['event_title'] ?? '' ) . '</td><td>' . esc_html( self::order_amount_label( $data ) ) . '</td><td>' . esc_html( $data['order_status'] ?? '' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</tbody></table></section>';
	}

	private static function render_payments_tab( $person ) {
		$orders = self::orders_for_person( absint( $person['id'] ) );
		echo '<section class="taka-admin-panel taka-admin-panel--full"><h3>' . esc_html__( 'Payments', 'taka-platform' ) . '</h3>';
		if ( empty( $orders ) ) {
			echo '<p class="description">' . esc_html__( 'No payments recorded.', 'taka-platform' ) . '</p></section>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Order', 'taka-platform' ) . '</th><th>' . esc_html__( 'Payment method', 'taka-platform' ) . '</th><th>' . esc_html__( 'Payment status', 'taka-platform' ) . '</th><th>' . esc_html__( 'Amount', 'taka-platform' ) . '</th></tr></thead><tbody>';
		foreach ( $orders as $order ) {
			$data = $order->to_array();
			$method = class_exists( 'TAKA_Ticketing_Module' ) ? TAKA_Ticketing_Module::payment_method_admin_label( $data['payment_method'] ?? '' ) : ( $data['payment_method'] ?? '' );
			echo '<tr><td>' . esc_html( $data['order_number'] ?? '' ) . '</td><td>' . esc_html( $method ) . '</td><td>' . esc_html( $data['payment_status'] ?? '' ) . '</td><td>' . esc_html( self::order_amount_label( $data ) ) . '</td></tr>';
		}
		echo '</tbody></table></section>';
	}

	private static function render_products_tab( $person ) {
		echo '<section class="taka-admin-panel taka-admin-panel--full"><h3>' . esc_html__( 'Products purchased', 'taka-platform' ) . '</h3>';
		self::render_products_purchased( self::orders_for_person( absint( $person['id'] ) ) );
		echo '</section>';
	}

	private static function render_vouchers_tab( $person ) {
		echo '<section class="taka-admin-panel taka-admin-panel--full"><h3>' . esc_html__( 'Vouchers used', 'taka-platform' ) . '</h3>';
		self::render_vouchers_used( self::orders_for_person( absint( $person['id'] ) ) );
		echo '</section>';
	}

	private static function render_activity_tab( $person, $tag ) {
		echo '<section class="taka-admin-panel taka-admin-panel--full"><h3>' . esc_html( sprintf( __( '%s activities', 'taka-platform' ), $tag ) ) . '</h3>';
		echo '<p>' . esc_html( self::activity_note( $person, $tag ) ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Future modules can write structured activities here without changing the People profile shell.', 'taka-platform' ) . '</p></section>';
	}

	private static function render_notes_tab( $person ) {
		echo '<section class="taka-admin-panel taka-admin-panel--full"><h3>' . esc_html__( 'Private notes', 'taka-platform' ) . '</h3>';
		echo '<p>' . nl2br( esc_html( $person['notes'] ?? '' ) ) . '</p>';
		echo '<h4>' . esc_html__( 'Tags', 'taka-platform' ) . '</h4><p>' . esc_html( implode( ', ', (array) ( $person['tags'] ?? array() ) ) ) . '</p></section>';
	}

	private static function render_future_tab( $tab ) {
		$label = self::person_profile_tabs()[ $tab ] ?? __( 'Future module', 'taka-platform' );
		echo '<section class="taka-admin-panel taka-admin-panel--full"><h3>' . esc_html( $label ) . '</h3><p class="description">' . esc_html__( 'Reserved for a future People module extension.', 'taka-platform' ) . '</p></section>';
	}

	private static function render_person_history( $person ) {
		$person_id = absint( $person['id'] ?? 0 );
		$registrations = self::registration_repository()->query( array( 'person_id' => $person_id, 'per_page' => -1 ) );
		$orders = self::orders_for_person( $person_id );
		?>
		<div class="taka-people-history">
			<section><?php self::render_registration_table( __( 'Upcoming registrations', 'taka-platform' ), self::filter_registrations_by_time( $registrations, 'upcoming' ) ); ?></section>
			<section><?php self::render_registration_table( __( 'Events attended', 'taka-platform' ), self::filter_registrations_by_time( $registrations, 'past' ) ); ?></section>
			<section><?php self::render_previous_tours( $registrations ); ?></section>
			<section><?php self::render_products_purchased( $orders ); ?></section>
			<section><?php self::render_vouchers_used( $orders ); ?></section>
			<section><h3><?php echo esc_html__( 'Organizer activities', 'taka-platform' ); ?></h3><p class="description"><?php echo esc_html( self::activity_note( $person, 'Organizer' ) ); ?></p></section>
			<section><h3><?php echo esc_html__( 'Volunteer activities', 'taka-platform' ); ?></h3><p class="description"><?php echo esc_html( self::activity_note( $person, 'Volunteer' ) ); ?></p></section>
		</div>
		<?php
	}

	private static function render_registration_table( $title, $registrations ) {
		echo '<h3>' . esc_html( $title ) . '</h3>';
		if ( empty( $registrations ) ) {
			echo '<p class="description">' . esc_html__( 'No registrations recorded.', 'taka-platform' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date', 'taka-platform' ) . '</th><th>' . esc_html__( 'Event', 'taka-platform' ) . '</th><th>' . esc_html__( 'Ticket', 'taka-platform' ) . '</th><th>' . esc_html__( 'Payment', 'taka-platform' ) . '</th><th>' . esc_html__( 'Check-in', 'taka-platform' ) . '</th><th>' . esc_html__( 'Order', 'taka-platform' ) . '</th></tr></thead><tbody>';
		foreach ( $registrations as $registration ) {
			$order_link = class_exists( 'TAKA_Ticketing_Module' ) && ! empty( $registration['order_id'] ) ? '<a href="' . esc_url( TAKA_Ticketing_Module::admin_url( array( 'order_id' => absint( $registration['order_id'] ) ) ) ) . '">' . esc_html( $registration['order_number'] ) . '</a>' : esc_html( $registration['order_number'] );
			$event_link = ! empty( $registration['event_id'] ) && get_edit_post_link( absint( $registration['event_id'] ), '' ) ? '<a href="' . esc_url( get_edit_post_link( absint( $registration['event_id'] ), '' ) ) . '">' . esc_html( $registration['event_title'] ) . '</a>' : esc_html( $registration['event_title'] );
			echo '<tr><td>' . esc_html( self::registration_event_date( $registration ) ) . '</td><td>' . $event_link . '</td><td>' . esc_html( $registration['ticket_type_name'] ) . '</td><td>' . esc_html( $registration['payment_status'] ) . '</td><td>' . esc_html( $registration['checkin_status'] ) . '</td><td>' . $order_link . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</tbody></table>';
	}

	private static function render_previous_tours( $registrations ) {
		$tours = array();
		foreach ( $registrations as $registration ) {
			$tour_id = ! empty( $registration['event_id'] ) ? (string) get_post_meta( absint( $registration['event_id'] ), '_taka_tour_id', true ) : '';
			if ( '' !== trim( $tour_id ) ) {
				$tours[] = $tour_id;
			}
		}
		$tours = array_values( array_unique( $tours ) );
		echo '<h3>' . esc_html__( 'Previous tours', 'taka-platform' ) . '</h3>';
		echo empty( $tours ) ? '<p class="description">' . esc_html__( 'No tour history recorded.', 'taka-platform' ) . '</p>' : '<p>' . esc_html( implode( ', ', $tours ) ) . '</p>';
	}

	private static function render_products_purchased( $orders ) {
		$items = array();
		foreach ( $orders as $order ) {
			$data = $order->to_array();
			foreach ( (array) ( $data['line_items'] ?? array() ) as $item ) {
				if ( ! in_array( (string) ( $item['item_type'] ?? '' ), array( 'event_ticket', 'ticket', 'discount' ), true ) ) {
					$items[] = trim( (string) ( $item['quantity'] ?? 1 ) . ' x ' . (string) ( $item['title'] ?? '' ) );
				}
			}
		}
		echo '<h3>' . esc_html__( 'Products purchased', 'taka-platform' ) . '</h3>';
		echo empty( $items ) ? '<p class="description">' . esc_html__( 'No products recorded.', 'taka-platform' ) . '</p>' : '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $items ) ) . '</li></ul>';
	}

	private static function render_vouchers_used( $orders ) {
		$codes = array();
		foreach ( $orders as $order ) {
			$code = trim( (string) $order->get( 'applied_voucher_code', '' ) );
			if ( '' !== $code ) {
				$codes[] = $code;
			}
		}
		echo '<h3>' . esc_html__( 'Vouchers used', 'taka-platform' ) . '</h3>';
		echo empty( $codes ) ? '<p class="description">' . esc_html__( 'No vouchers recorded.', 'taka-platform' ) . '</p>' : '<p>' . esc_html( implode( ', ', array_unique( $codes ) ) ) . '</p>';
	}

	private static function render_chronological_history( $registrations ) {
		$groups = array();
		foreach ( (array) $registrations as $registration ) {
			$date = self::registration_event_date( $registration );
			$year = '' !== $date ? substr( $date, 0, 4 ) : __( 'Unknown year', 'taka-platform' );
			if ( ! isset( $groups[ $year ] ) ) {
				$groups[ $year ] = array();
			}
			$groups[ $year ][] = $registration;
		}
		krsort( $groups );
		echo '<section class="taka-admin-panel taka-admin-panel--full"><h3>' . esc_html__( 'Participation History', 'taka-platform' ) . '</h3>';
		if ( empty( $groups ) ) {
			echo '<p class="description">' . esc_html__( 'No participation history recorded yet.', 'taka-platform' ) . '</p></section>';
			return;
		}
		foreach ( $groups as $year => $items ) {
			echo '<h4>' . esc_html( $year ) . '</h4>';
			echo '<ul class="taka-people-timeline">';
			foreach ( $items as $registration ) {
				$event_link = ! empty( $registration['event_id'] ) && get_edit_post_link( absint( $registration['event_id'] ), '' ) ? '<a href="' . esc_url( get_edit_post_link( absint( $registration['event_id'] ), '' ) ) . '">' . esc_html( $registration['event_title'] ) . '</a>' : esc_html( $registration['event_title'] );
				$order_link = class_exists( 'TAKA_Ticketing_Module' ) && ! empty( $registration['order_id'] ) ? ' <a href="' . esc_url( TAKA_Ticketing_Module::admin_url( array( 'order_id' => absint( $registration['order_id'] ) ) ) ) . '">' . esc_html( $registration['order_number'] ) . '</a>' : '';
				echo '<li><span>' . esc_html( self::registration_event_date( $registration ) ) . '</span> ' . $event_link . ' <em>' . esc_html( $registration['ticket_type_name'] ) . '</em>' . $order_link . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</ul>';
		}
		echo '</section>';
	}

	private static function community_dashboard_data() {
		$people = self::person_repository()->query( array( 'per_page' => -1 ) );
		$registrations = self::registration_repository()->query( array( 'per_page' => -1 ) );
		$current_year = current_time( 'Y' );
		$active_people = array();
		$registration_counts = array();
		$countries = array();
		$dojos = array();
		$organizers = array();
		foreach ( $people as $person ) {
			if ( '' !== (string) ( $person['country'] ?? '' ) ) {
				$countries[ $person['country'] ] = true;
			}
			if ( self::person_created_year( $person ) === $current_year ) {
				$active_people['new-' . absint( $person['id'] ?? 0 )] = true;
			}
			if ( in_array( 'organizer', array_map( 'strtolower', (array) ( $person['tags'] ?? array() ) ), true ) ) {
				$name = TAKA_People_Person::full_name( $person ) ?: ( $person['email'] ?? __( 'Organizer', 'taka-platform' ) );
				$organizers[ $name ] = ( $organizers[ $name ] ?? 0 ) + 1;
			}
		}
		foreach ( $registrations as $registration ) {
			$person_id = absint( $registration['person_id'] ?? 0 );
			if ( ! $person_id || 'cancelled' === (string) ( $registration['registration_status'] ?? '' ) ) {
				continue;
			}
			$date = self::registration_event_date( $registration );
			if ( 0 === strpos( $date, $current_year ) ) {
				$active_people[ $person_id ] = true;
			}
			$registration_counts[ $person_id ] = ( $registration_counts[ $person_id ] ?? 0 ) + 1;
			$person = self::person_repository()->find_by_id( $person_id );
			if ( $person && '' !== trim( (string) ( $person['dojo'] ?? '' ) ) ) {
				$dojos[ $person['dojo'] ] = ( $dojos[ $person['dojo'] ] ?? 0 ) + 1;
			}
		}
		arsort( $dojos );
		arsort( $organizers );
		return array(
			'total_people'            => count( $people ),
			'active_this_year'        => count( array_filter( array_keys( $active_people ), 'is_numeric' ) ),
			'new_people'              => count( array_filter( array_keys( $active_people ), static function ( $key ) { return 0 === strpos( (string) $key, 'new-' ); } ) ),
			'returning_participants'  => count( array_filter( $registration_counts, static function ( $count ) { return (int) $count > 1; } ) ),
			'countries_represented'   => count( $countries ),
			'most_active_dojos'       => array_slice( $dojos, 0, 5, true ),
			'most_active_organizers'  => array_slice( $organizers, 0, 5, true ),
			'upcoming_birthdays'      => self::upcoming_birthdays( $people ),
		);
	}

	private static function community_stats_for_person( $person_id ) {
		$registrations = self::registration_repository()->query( array( 'person_id' => $person_id, 'per_page' => -1 ) );
		$past = self::filter_registrations_by_time( $registrations, 'past' );
		$tours = array();
		$countries = array();
		$years = array();
		$dates = array();
		foreach ( $past as $registration ) {
			$date = self::registration_event_date( $registration );
			if ( '' !== $date ) {
				$dates[] = $date;
				$years[ substr( $date, 0, 4 ) ] = true;
			}
			$event_id = absint( $registration['event_id'] ?? 0 );
			if ( $event_id ) {
				$tour = sanitize_text_field( get_post_meta( $event_id, '_taka_tour_id', true ) );
				if ( '' !== $tour ) {
					$tours[ $tour ] = true;
				}
				$country = sanitize_text_field( get_post_meta( $event_id, '_taka_country', true ) );
				if ( '' !== $country ) {
					$countries[ $country ] = true;
				}
			}
		}
		rsort( $dates );
		$product_count = 0;
		foreach ( self::orders_for_person( $person_id ) as $order ) {
			foreach ( (array) ( $order->get( 'line_items', array() ) ) as $item ) {
				if ( ! in_array( (string) ( $item['item_type'] ?? '' ), array( 'event_ticket', 'ticket', 'discount' ), true ) ) {
					$product_count += max( 1, absint( $item['quantity'] ?? 1 ) );
				}
			}
		}
		return array(
			'events_attended'                => count( $past ),
			'tours_attended'                 => count( $tours ),
			'countries_visited'              => count( $countries ),
			'last_participation'             => $dates[0] ?? '',
			'products_purchased'             => $product_count,
			'average_registrations_per_year' => ! empty( $years ) ? count( $registrations ) / count( $years ) : 0,
		);
	}

	private static function upcoming_birthdays( $people ) {
		$out = array();
		$today = current_time( 'md' );
		foreach ( $people as $person ) {
			$birth_date = (string) ( $person['birth_date'] ?? '' );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $birth_date ) ) {
				continue;
			}
			$md = substr( $birth_date, 5, 2 ) . substr( $birth_date, 8, 2 );
			if ( $md >= $today ) {
				$name = TAKA_People_Person::full_name( $person ) ?: ( $person['email'] ?? '' );
				$out[ $md . '-' . absint( $person['id'] ) ] = trim( $name . ' - ' . substr( $birth_date, 5 ) );
			}
		}
		ksort( $out );
		return array_slice( $out, 0, 5, true );
	}

	private static function person_created_year( $person ) {
		$created = (string) ( $person['created_at'] ?? '' );
		return preg_match( '/^\d{4}/', $created ) ? substr( $created, 0, 4 ) : '';
	}

	private static function relationship_types() {
		return array(
			'member_of_dojo'     => __( 'Member of Dojo', 'taka-platform' ),
			'instructor_of_dojo' => __( 'Instructor of Dojo', 'taka-platform' ),
			'organizer'          => __( 'Organizer', 'taka-platform' ),
			'volunteer'          => __( 'Volunteer', 'taka-platform' ),
			'speaker'            => __( 'Speaker', 'taka-platform' ),
			'sponsor'            => __( 'Sponsor', 'taka-platform' ),
			'press'              => __( 'Press', 'taka-platform' ),
			'vip'                => __( 'VIP', 'taka-platform' ),
			'other'              => __( 'Other', 'taka-platform' ),
		);
	}

	private static function merge_people( $target_id, $source_id ) {
		if ( ! $target_id || ! $source_id || $target_id === $source_id ) {
			return new WP_Error( 'taka_people_invalid_merge', __( 'Choose two different people to merge.', 'taka-platform' ) );
		}
		$target = self::person_repository()->find_by_id( $target_id );
		$source = self::person_repository()->find_by_id( $source_id );
		if ( ! $target || ! $source ) {
			return new WP_Error( 'taka_people_merge_missing_person', __( 'One of the selected people no longer exists.', 'taka-platform' ) );
		}

		$merged = self::merge_person_data( $target, $source );
		$saved = self::person_repository()->save( $merged );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		foreach ( self::registration_repository()->query( array( 'person_id' => $source_id, 'per_page' => -1 ) ) as $registration ) {
			$original_registration_id = absint( $registration['id'] ?? 0 );
			$registration['person_id'] = $target_id;
			$saved_registration = self::registration_repository()->save( $registration );
			if ( ! is_wp_error( $saved_registration ) && $original_registration_id && absint( $saved_registration['id'] ?? 0 ) !== $original_registration_id ) {
				wp_delete_post( $original_registration_id, true );
			}
		}

		if ( class_exists( 'TAKA_Ticketing_Module' ) ) {
			foreach ( TAKA_Ticketing_Module::order_repository()->query( array( 'per_page' => -1 ) ) as $order ) {
				$data = $order->to_array();
				$changed = false;
				foreach ( array( 'buyer_person_id', 'participant_person_id' ) as $field ) {
					if ( absint( $data[ $field ] ?? 0 ) === $source_id ) {
						$data[ $field ] = $target_id;
						$changed = true;
					}
				}
				if ( $changed ) {
					TAKA_Ticketing_Module::order_repository()->save( new TAKA_Ticketing_Order( $data ) );
				}
			}
		}

		wp_delete_post( $source_id, true );
		return $saved;
	}

	private static function merge_person_data( $target, $source ) {
		$merged = TAKA_People_Person::normalize( $target );
		$source = TAKA_People_Person::normalize( $source );
		foreach ( array( 'first_name', 'last_name', 'email', 'phone', 'country', 'dojo', 'association', 'style', 'rank', 'dietary_preference', 'allergies', 'birth_date' ) as $field ) {
			if ( '' === trim( (string) ( $merged[ $field ] ?? '' ) ) || 'none' === (string) ( $merged[ $field ] ?? '' ) ) {
				$merged[ $field ] = $source[ $field ] ?? '';
			}
		}
		$notes = array_filter( array( $merged['notes'] ?? '', $source['notes'] ?? '' ) );
		$merged['notes'] = implode( "\n\n--- " . __( 'Merged notes', 'taka-platform' ) . " ---\n", $notes );
		$merged['tags'] = TAKA_People_Person::normalize_tags( array_merge( (array) ( $merged['tags'] ?? array() ), (array) ( $source['tags'] ?? array() ) ) );
		$merged['relationships'] = TAKA_People_Person::normalize_relationships( array_merge( (array) ( $merged['relationships'] ?? array() ), (array) ( $source['relationships'] ?? array() ) ) );
		if ( '1' === (string) ( $source['gdpr_consent'] ?? '0' ) ) {
			$merged['gdpr_consent'] = '1';
		}
		if ( '1' === (string) ( $source['newsletter_consent'] ?? '0' ) ) {
			$merged['newsletter_consent'] = '1';
		}
		return $merged;
	}

	private static function export_person_data( $person_id ) {
		$person = self::person_repository()->find_by_id( $person_id );
		if ( ! $person ) {
			wp_die( esc_html__( 'Person not found.', 'taka-platform' ) );
		}
		$data = array(
			'person'        => $person,
			'registrations' => self::registration_repository()->query( array( 'person_id' => $person_id, 'per_page' => -1 ) ),
			'orders'        => array_map(
				static function ( $order ) {
					return $order->to_array();
				},
				self::orders_for_person( $person_id )
			),
		);
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=taka-person-' . absint( $person_id ) . '-export.json' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT );
		exit;
	}

	private static function anonymize_person( $person_id ) {
		$person = self::person_repository()->find_by_id( $person_id );
		if ( ! $person ) {
			return new WP_Error( 'taka_people_missing_person', __( 'Person not found.', 'taka-platform' ) );
		}
		$anonymous = TAKA_People_Person::normalize(
			array(
				'id'         => $person_id,
				'first_name' => __( 'Anonymized', 'taka-platform' ),
				'last_name'  => '#' . $person_id,
				'notes'      => __( 'Personal data anonymized by GDPR/privacy action. Financial and registration history retained.', 'taka-platform' ),
				'tags'       => array( 'Anonymized' ),
				'created_at' => $person['created_at'] ?? '',
			)
		);
		return self::person_repository()->save( $anonymous );
	}

	private static function delete_person_if_empty( $person_id ) {
		if ( ! self::person_repository()->find_by_id( $person_id ) ) {
			return new WP_Error( 'taka_people_missing_person', __( 'Person not found.', 'taka-platform' ) );
		}
		$registrations = self::registration_repository()->query( array( 'person_id' => $person_id, 'per_page' => -1 ) );
		$orders = self::orders_for_person( $person_id );
		if ( ! empty( $registrations ) || ! empty( $orders ) ) {
			return new WP_Error( 'taka_people_delete_has_history', __( 'This person has registration or financial history. Use anonymize instead.', 'taka-platform' ) );
		}
		wp_delete_post( $person_id, true );
		return true;
	}

	private static function order_amount_label( $data ) {
		$amount = (string) ( $data['amount'] ?? $data['final_amount'] ?? '0' );
		$currency = (string) ( $data['currency'] ?? 'EUR' );
		return class_exists( 'TAKA_Ticketing_Module' ) ? TAKA_Ticketing_Module::format_money( $amount, $currency ) : trim( $currency . ' ' . $amount );
	}

	private static function filter_registrations_by_time( $registrations, $bucket ) {
		$today = current_time( 'Y-m-d' );
		return array_values(
			array_filter(
				(array) $registrations,
				static function ( $registration ) use ( $bucket, $today ) {
					if ( 'cancelled' === (string) ( $registration['registration_status'] ?? '' ) ) {
						return false;
					}
					$date = self::registration_event_date( $registration );
					return 'upcoming' === $bucket ? ( '' !== $date && $date >= $today ) : ( '' === $date || $date < $today );
				}
			)
		);
	}

	private static function person_registration_stats( $person_id ) {
		$registrations = self::registration_repository()->query( array( 'person_id' => $person_id, 'per_page' => -1 ) );
		$past = self::filter_registrations_by_time( $registrations, 'past' );
		$upcoming = self::filter_registrations_by_time( $registrations, 'upcoming' );
		$dates = array();
		foreach ( $past as $registration ) {
			$date = self::registration_event_date( $registration );
			if ( '' !== $date ) {
				$dates[] = $date;
			}
		}
		rsort( $dates );
		return array(
			'attended'           => count( $past ),
			'upcoming'           => count( $upcoming ),
			'last_participation' => $dates[0] ?? '',
		);
	}

	private static function registration_event_date( $registration ) {
		$event_id = absint( $registration['event_id'] ?? 0 );
		if ( ! $event_id ) {
			return '';
		}
		return sanitize_text_field( get_post_meta( $event_id, '_taka_date_start', true ) );
	}

	private static function orders_for_person( $person_id ) {
		if ( ! class_exists( 'TAKA_Ticketing_Module' ) ) {
			return array();
		}
		$orders = array();
		foreach ( TAKA_Ticketing_Module::order_repository()->query( array( 'per_page' => -1 ) ) as $order ) {
			if ( absint( $order->get( 'buyer_person_id' ) ) === absint( $person_id ) || absint( $order->get( 'participant_person_id' ) ) === absint( $person_id ) ) {
				$orders[] = $order;
			}
		}
		return $orders;
	}

	private static function activity_note( $person, $tag ) {
		$tags = array_map( 'strtolower', (array) ( $person['tags'] ?? array() ) );
		return in_array( strtolower( $tag ), $tags, true ) ? sprintf( __( '%s tag is assigned. Structured activity history can be added by future modules.', 'taka-platform' ), $tag ) : __( 'No structured activity recorded yet.', 'taka-platform' );
	}

	private static function input( $field, $label, $person, $type = 'text' ) {
		echo '<label><span>' . esc_html( $label ) . '</span><input class="regular-text" type="' . esc_attr( $type ) . '" name="' . esc_attr( 'person[' . $field . ']' ) . '" value="' . esc_attr( $person[ $field ] ?? '' ) . '"></label>';
	}

	private static function textarea( $field, $label, $person ) {
		echo '<label><span>' . esc_html( $label ) . '</span><textarea name="' . esc_attr( 'person[' . $field . ']' ) . '" rows="3">' . esc_textarea( $person[ $field ] ?? '' ) . '</textarea></label>';
	}

	private static function select( $field, $label, $choices, $current ) {
		echo '<label><span>' . esc_html( $label ) . '</span><select name="' . esc_attr( 'person[' . $field . ']' ) . '">';
		foreach ( (array) $choices as $value => $choice_label ) {
			echo '<option value="' . esc_attr( (string) $value ) . '" ' . selected( (string) $current, (string) $value, false ) . '>' . esc_html( (string) $choice_label ) . '</option>';
		}
		echo '</select></label>';
	}

	private static function dietary_labels() {
		return array(
			'none'       => __( 'No dietary preference', 'taka-platform' ),
			'vegetarian' => __( 'Vegetarian', 'taka-platform' ),
			'vegan'      => __( 'Vegan', 'taka-platform' ),
			'other'      => __( 'Other / note', 'taka-platform' ),
		);
	}

	private static function country_label( $country ) {
		$country = sanitize_text_field( $country );
		return '' !== $country ? TAKA_Platform_Data::country_label( $country, TAKA_Platform_Data::platform_fallback_language() ) : '';
	}

	private static function render_person_readonly( $person ) {
		echo '<div class="taka-people-profile-grid"><section>';
		foreach ( $person as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}
			if ( '' === trim( (string) $value ) ) {
				continue;
			}
			echo '<p><strong>' . esc_html( ucwords( str_replace( '_', ' ', $key ) ) ) . ':</strong> ' . esc_html( $value ) . '</p>';
		}
		echo '</section></div>';
	}
}

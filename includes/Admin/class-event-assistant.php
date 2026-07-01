<?php
/**
 * Reusable Event Assistant admin framework.
 *
 * Event Assistant sections are intentionally modular. New event lifecycle
 * modules should register a section here (or via the filter) instead of adding
 * a one-off editor screen, so rendering, validation and save routing stay
 * consistent with the wider TAKA Platform admin UI.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Admin_Event_Assistant_Section {
	private $id;
	private $title;
	private $help_text;
	private $default_state;
	private $weight;
	private $render_callback;
	private $required_callback;
	private $optional_callback;

	public function __construct( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'id'                => '',
				'title'             => '',
				'help_text'         => '',
				'default_state'     => TAKA_Platform_Admin_Collapsible_Section::STATE_COLLAPSED,
				'weight'            => 10,
				'render_callback'   => null,
				'required_callback' => null,
				'optional_callback' => null,
			)
		);

		$this->id                = sanitize_key( $args['id'] );
		$this->title             = (string) $args['title'];
		$this->help_text         = (string) $args['help_text'];
		$this->default_state     = TAKA_Platform_Admin_Collapsible_Section::STATE_EXPANDED === $args['default_state'] ? TAKA_Platform_Admin_Collapsible_Section::STATE_EXPANDED : TAKA_Platform_Admin_Collapsible_Section::STATE_COLLAPSED;
		$this->weight            = max( 1, absint( $args['weight'] ) );
		$this->render_callback   = $args['render_callback'];
		$this->required_callback = $args['required_callback'];
		$this->optional_callback = $args['optional_callback'];
	}

	public function getId() {
		return $this->id;
	}

	public function getTitle() {
		return $this->title;
	}

	public function getHelpText() {
		return $this->help_text;
	}

	public function getDefaultState() {
		return $this->default_state;
	}

	public function getWeight() {
		return $this->weight;
	}

	public function load( $context ) {
		return $context;
	}

	public function render( $context ) {
		if ( is_callable( $this->render_callback ) ) {
			call_user_func( $this->render_callback, $context, $this );
		}
	}

	public function validate( $context ) {
		return $this->evaluate( $context );
	}

	public function isComplete( $context ) {
		$evaluation = $this->evaluate( $context );
		return 'complete' === $evaluation['status'];
	}

	public function save( $post_id, $posted ) {
		return null;
	}

	public function evaluate( $context ) {
		$required = is_callable( $this->required_callback ) ? (array) call_user_func( $this->required_callback, $context, $this ) : array();
		$optional = is_callable( $this->optional_callback ) ? (array) call_user_func( $this->optional_callback, $context, $this ) : array();
		$required = $this->normalize_missing_items( $required, true );
		$optional = $this->normalize_missing_items( $optional, false );

		if ( ! empty( $required ) ) {
			$status = 'incomplete';
			$score  = (int) round( $this->weight * 0.25 );
		} elseif ( ! empty( $optional ) ) {
			$status = 'optional';
			$score  = (int) round( $this->weight * 0.65 );
		} else {
			$status = 'complete';
			$score  = $this->weight;
		}

		return array(
			'section_id'       => $this->id,
			'title'            => $this->title,
			'status'           => $status,
			'score'            => min( $this->weight, max( 0, $score ) ),
			'score_total'      => $this->weight,
			'missing_required' => $required,
			'missing_optional' => $optional,
			'blocks_publish'   => ! empty( $required ),
		);
	}

	private function normalize_missing_items( $items, $blocks_publish ) {
		$out = array();
		foreach ( $items as $item ) {
			if ( is_string( $item ) ) {
				$item = array( 'label' => $item );
			}
			if ( ! is_array( $item ) || '' === trim( (string) ( $item['label'] ?? '' ) ) ) {
				continue;
			}
			$out[] = array(
				'label'          => (string) $item['label'],
				'section_id'     => sanitize_key( $item['section_id'] ?? $this->id ),
				'blocks_publish' => array_key_exists( 'blocks_publish', $item ) ? ! empty( $item['blocks_publish'] ) : $blocks_publish,
			);
		}
		return $out;
	}
}

class TAKA_Platform_Admin_Event_Assistant {
	const PAGE_SLUG        = 'taka-platform-event-assistant';
	const ACTION_SAVE      = 'taka_platform_save_event_assistant';
	const RECENT_USER_META = '_taka_event_assistant_recent_settings';

	private static $section_cache = null;

	public static function init() {
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_edit_screen_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_saved_list_notice' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'event_row_actions' ), 10, 2 );
	}

	public static function register_menu() {
		add_submenu_page(
			'taka-platform',
			__( 'New Event Assistant', 'taka-platform' ),
			__( 'New Event Assistant', 'taka-platform' ),
			'edit_taka_events',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function assistant_url( $event_id = 0, $args = array() ) {
		$query = array_merge(
			array(
				'page' => self::PAGE_SLUG,
			),
			(array) $args
		);
		if ( $event_id ) {
			$query['event_id'] = absint( $event_id );
		}
		return add_query_arg( $query, admin_url( 'admin.php' ) );
	}

	public static function event_row_actions( $actions, $post ) {
		if ( ! $post || TAKA_PLATFORM_CPT_EVENT !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$actions['taka_event_assistant'] = '<a href="' . esc_url( self::assistant_url( $post->ID ) ) . '">' . esc_html__( 'Open Event Assistant', 'taka-platform' ) . '</a>';
		return $actions;
	}

	public static function render_edit_screen_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || TAKA_PLATFORM_CPT_EVENT !== ( $screen->post_type ?? '' ) || 'post' !== ( $screen->base ?? '' ) ) {
			return;
		}

		$post_id = absint( $_GET['post'] ?? 0 );
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$url   = $post_id ? self::assistant_url( $post_id ) : self::assistant_url();
		$label = $post_id ? __( 'Open Event Assistant', 'taka-platform' ) : __( 'Use Event Assistant', 'taka-platform' );
		$text  = $post_id
			? __( 'This event can also be maintained in the guided Event Assistant.', 'taka-platform' )
			: __( 'Create this event with the guided Event Assistant if you prefer the checklist workflow.', 'taka-platform' );
		?>
		<div class="notice notice-info taka-event-assistant-entry-notice">
			<p><?php echo esc_html( $text ); ?> <a class="button button-secondary" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a></p>
		</div>
		<?php
	}

	public static function render_saved_list_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit' !== ( $screen->base ?? '' ) || TAKA_PLATFORM_CPT_EVENT !== ( $screen->post_type ?? '' ) || empty( $_GET['event_assistant_saved'] ) ) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html__( 'Event draft saved. You can continue editing later from the Event Assistant.', 'taka-platform' ); ?></p>
		</div>
		<?php
	}

	public static function render_page() {
		if ( ! current_user_can( 'edit_taka_events' ) ) {
			wp_die( esc_html__( 'You do not have permission to edit events.', 'taka-platform' ) );
		}

		$event_id = absint( $_GET['event_id'] ?? 0 );
		if ( $event_id && ! current_user_can( 'edit_post', $event_id ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this event.', 'taka-platform' ) );
		}

		$context = self::load_context( $event_id, ! empty( $_GET['reuse_recent'] ) );
		$sections = self::sections();
		$health = self::evaluate_event( $context, $sections );
		?>
		<div class="wrap taka-event-assistant">
			<h1><?php echo esc_html__( 'Event Assistant', 'taka-platform' ); ?></h1>
			<?php if ( ! empty( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Event saved. Completeness has been updated from the current saved state.', 'taka-platform' ); ?></p></div>
			<?php endif; ?>
			<?php self::render_inline_object_notices(); ?>
			<p class="description taka-event-assistant__intro">
				<?php echo esc_html__( 'Use this checklist editor to create and maintain events throughout their lifecycle. You can save early, return later and jump between sections at any time.', 'taka-platform' ); ?>
			</p>
			<?php self::render_status_card( $health ); ?>
			<?php self::render_recent_reuse_prompt( $context ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="taka-event-assistant__form">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SAVE ); ?>">
				<input type="hidden" name="event_id" value="<?php echo esc_attr( (string) $context['post_id'] ); ?>">
				<?php wp_nonce_field( TAKA_Platform_Admin::NONCE, TAKA_Platform_Admin::NONCE ); ?>
				<?php self::render_hidden_preserved_fields( $context ); ?>
				<div class="taka-event-assistant__layout">
					<aside class="taka-event-assistant__sidebar" aria-label="<?php echo esc_attr__( 'Event Assistant sections', 'taka-platform' ); ?>">
						<?php self::render_sidebar( $sections, $health ); ?>
					</aside>
					<div class="taka-event-assistant__sections">
						<?php foreach ( $sections as $section ) : ?>
							<?php self::render_section( $section, $context, $health['sections'][ $section->getId() ] ?? array() ); ?>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="taka-event-assistant__actions">
					<button type="submit" class="button button-primary" name="assistant_action" value="save_draft"><?php echo esc_html__( 'Save Draft', 'taka-platform' ); ?></button>
					<button type="submit" class="button" name="assistant_action" value="continue_later"><?php echo esc_html__( 'Continue later', 'taka-platform' ); ?></button>
					<?php if ( current_user_can( 'publish_taka_events' ) ) : ?>
						<button type="submit" class="button button-secondary" name="assistant_action" value="publish"><?php echo esc_html__( 'Publish', 'taka-platform' ); ?></button>
					<?php endif; ?>
					<?php if ( $context['post_id'] ) : ?>
						<a class="button-link" href="<?php echo esc_url( get_edit_post_link( $context['post_id'], '' ) ); ?>"><?php echo esc_html__( 'Open classic editor', 'taka-platform' ); ?></a>
					<?php endif; ?>
				</div>
			</form>
		</div>
		<?php
	}

	public static function handle_save() {
		if ( ! current_user_can( 'edit_taka_events' ) ) {
			wp_die( esc_html__( 'You do not have permission to edit events.', 'taka-platform' ) );
		}

		check_admin_referer( TAKA_Platform_Admin::NONCE, TAKA_Platform_Admin::NONCE );

		$event_id = absint( $_POST['event_id'] ?? 0 );
		if ( $event_id && ! current_user_can( 'edit_post', $event_id ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this event.', 'taka-platform' ) );
		}

		$assistant_action = sanitize_key( wp_unslash( $_POST['assistant_action'] ?? 'save_draft' ) );
		$title = sanitize_text_field( wp_unslash( $_POST['post_title'] ?? '' ) );
		if ( '' === trim( $title ) ) {
			$title = __( 'Untitled event draft', 'taka-platform' );
		}

		$current_status = $event_id ? get_post_status( $event_id ) : '';
		$post_status = ( $event_id && $current_status ) ? $current_status : 'draft';
		if ( 'publish' === $assistant_action && current_user_can( 'publish_taka_events' ) ) {
			$post_status = 'publish';
		}

		$post_data = array(
			'post_type'    => TAKA_PLATFORM_CPT_EVENT,
			'post_title'   => $title,
			'post_status'  => $post_status,
		);
		if ( $event_id ) {
			$post_data['post_content'] = (string) get_post_field( 'post_content', $event_id, 'raw' );
		}

		remove_action( 'save_post_' . TAKA_PLATFORM_CPT_EVENT, array( 'TAKA_Platform_Admin', 'save_event' ) );
		if ( $event_id ) {
			$post_data['ID'] = $event_id;
			$result = wp_update_post( $post_data, true );
		} else {
			$post_data['post_author'] = get_current_user_id();
			$result = wp_insert_post( $post_data, true );
		}
		add_action( 'save_post_' . TAKA_PLATFORM_CPT_EVENT, array( 'TAKA_Platform_Admin', 'save_event' ) );

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		$event_id = absint( $result );
		$inline_results = self::process_inline_object_creates( $event_id );
		TAKA_Platform_Admin::save_event( $event_id );
		self::remember_recent_settings( $event_id );
		$redirect_args = array_merge( array( 'saved' => '1' ), self::inline_redirect_args( $inline_results ) );

		if ( 'continue_later' === $assistant_action ) {
			wp_safe_redirect( add_query_arg( 'event_assistant_saved', '1', admin_url( 'edit.php?post_type=' . TAKA_PLATFORM_CPT_EVENT ) ) );
			exit;
		}

		wp_safe_redirect( self::assistant_url( $event_id, $redirect_args ) );
		exit;
	}

	private static function process_inline_object_creates( $event_id ) {
		$results = array(
			'notices' => array(),
			'errors'  => array(),
		);

		if ( self::should_create_inline_object( 'taka_event_assistant_venue_mode', 'create_venue', 'taka_event_assistant_new_venue' ) ) {
			$venue = self::create_inline_venue( self::posted_array( 'taka_event_assistant_new_venue' ) );
			self::record_inline_result( $results, $venue );
			if ( ! empty( $venue['id'] ) ) {
				$_POST['_taka_venue_id'] = (string) absint( $venue['id'] );
			}
		}

		if ( self::should_create_inline_object( 'taka_event_assistant_organizer_mode', 'create_organizer', 'taka_event_assistant_new_organizer' ) ) {
			$organizer = self::create_inline_organizer( self::posted_array( 'taka_event_assistant_new_organizer' ), 'organizer' );
			self::record_inline_result( $results, $organizer );
			if ( ! empty( $organizer['id'] ) ) {
				$organizer_id = absint( $organizer['id'] );
				$_POST['_taka_organizer_id'] = (string) $organizer_id;
				self::ensure_posted_event_organizer_relationship( $organizer_id, 'organizer' );
			}
		}

		$co_organizer_payload = self::posted_array( 'taka_event_assistant_new_co_organizer' );
		if ( self::has_named_payload( $co_organizer_payload ) || self::inline_action_is( 'create_co_organizer' ) ) {
			$co_organizer = self::create_inline_organizer( $co_organizer_payload, 'co_organizer' );
			self::record_inline_result( $results, $co_organizer );
			if ( ! empty( $co_organizer['id'] ) ) {
				self::ensure_posted_event_organizer_relationship( absint( $co_organizer['id'] ), 'co_organizer' );
			}
		}

		return $results;
	}

	private static function create_inline_venue( $posted ) {
		if ( ! current_user_can( 'edit_taka_venues' ) ) {
			return array( 'error' => 'venue_forbidden' );
		}

		$name = sanitize_text_field( $posted['name'] ?? '' );
		if ( '' === trim( $name ) ) {
			return array( 'error' => 'venue_missing_name' );
		}

		$existing_id = self::find_similar_post_id( TAKA_PLATFORM_CPT_VENUE, $name );
		if ( $existing_id ) {
			return array(
				'id'     => $existing_id,
				'notice' => 'venue_existing',
			);
		}

		$venue_id = self::insert_inline_object_post( TAKA_PLATFORM_CPT_VENUE, $name );
		if ( is_wp_error( $venue_id ) ) {
			return array( 'error' => 'venue_create_failed' );
		}

		self::save_inline_venue_meta( absint( $venue_id ), $posted );
		return array(
			'id'     => absint( $venue_id ),
			'notice' => 'venue_created',
		);
	}

	private static function create_inline_organizer( $posted, $role ) {
		if ( ! current_user_can( 'edit_taka_organizers' ) ) {
			return array( 'error' => 'co_organizer' === $role ? 'co_organizer_forbidden' : 'organizer_forbidden' );
		}

		$name = sanitize_text_field( $posted['name'] ?? '' );
		if ( '' === trim( $name ) ) {
			return array( 'error' => 'co_organizer' === $role ? 'co_organizer_missing_name' : 'organizer_missing_name' );
		}

		$existing_id = self::find_similar_post_id( TAKA_PLATFORM_CPT_ORGANIZER, $name );
		if ( $existing_id ) {
			return array(
				'id'     => $existing_id,
				'notice' => 'co_organizer' === $role ? 'co_organizer_existing' : 'organizer_existing',
			);
		}

		$organizer_id = self::insert_inline_object_post( TAKA_PLATFORM_CPT_ORGANIZER, $name );
		if ( is_wp_error( $organizer_id ) ) {
			return array( 'error' => 'co_organizer' === $role ? 'co_organizer_create_failed' : 'organizer_create_failed' );
		}

		self::save_inline_organizer_meta( absint( $organizer_id ), $posted );
		return array(
			'id'     => absint( $organizer_id ),
			'notice' => 'co_organizer' === $role ? 'co_organizer_created' : 'organizer_created',
		);
	}

	private static function save_inline_venue_meta( $venue_id, $posted ) {
		$source_language = self::posted_source_language( $posted );
		$temp_post = array(
			TAKA_Platform_Admin::NONCE => sanitize_text_field( wp_unslash( $_POST[ TAKA_Platform_Admin::NONCE ] ?? '' ) ),
			'_taka_source_language'    => $source_language,
			'_taka_street'             => sanitize_text_field( $posted['street'] ?? '' ),
			'_taka_postal_code'        => sanitize_text_field( $posted['postal_code'] ?? '' ),
			'_taka_city'               => sanitize_text_field( $posted['city'] ?? '' ),
			'_taka_country'            => sanitize_text_field( $posted['country'] ?? '' ),
			'_taka_website'            => esc_url_raw( $posted['website'] ?? '' ),
			'taka_platform_text_translations' => array(
				'parking'       => array( $source_language => sanitize_textarea_field( $posted['parking'] ?? '' ) ),
				'accessibility' => array( $source_language => sanitize_textarea_field( $posted['accessibility'] ?? '' ) ),
			),
			'taka_platform_text_source_previous' => array(),
		);

		self::with_temporary_post( $temp_post, static function () use ( $venue_id ) {
			TAKA_Platform_Admin::save_venue( $venue_id );
		} );
	}

	private static function save_inline_organizer_meta( $organizer_id, $posted ) {
		$source_language = self::posted_source_language( $posted );
		$temp_post = array(
			TAKA_Platform_Admin::NONCE => sanitize_text_field( wp_unslash( $_POST[ TAKA_Platform_Admin::NONCE ] ?? '' ) ),
			'_taka_source_language'    => $source_language,
			'_taka_website'            => esc_url_raw( $posted['website'] ?? '' ),
			'_taka_emails'             => sanitize_email( $posted['email'] ?? '' ),
			'_taka_logo_id'            => absint( $posted['logo_id'] ?? 0 ),
			'_taka_logo_url'           => '',
			'_taka_active'             => '1',
			'taka_platform_text_translations' => array(
				'description' => array( $source_language => '' ),
			),
			'taka_platform_text_source_previous' => array(),
		);

		self::with_temporary_post( $temp_post, static function () use ( $organizer_id ) {
			TAKA_Platform_Admin::save_organizer( $organizer_id );
		} );
	}

	private static function insert_inline_object_post( $post_type, $title ) {
		$callback = TAKA_PLATFORM_CPT_VENUE === $post_type ? 'save_venue' : 'save_organizer';
		remove_action( 'save_post_' . $post_type, array( 'TAKA_Platform_Admin', $callback ) );
		$result = wp_insert_post(
			array(
				'post_type'   => $post_type,
				'post_title'  => sanitize_text_field( $title ),
				'post_status' => self::new_inline_object_status( $post_type ),
				'post_author' => get_current_user_id(),
			),
			true
		);
		add_action( 'save_post_' . $post_type, array( 'TAKA_Platform_Admin', $callback ) );
		return $result;
	}

	private static function new_inline_object_status( $post_type ) {
		$publish_cap = TAKA_PLATFORM_CPT_VENUE === $post_type ? 'publish_taka_venues' : 'publish_taka_organizers';
		return current_user_can( $publish_cap ) ? 'publish' : 'draft';
	}

	private static function find_similar_post_id( $post_type, $title ) {
		$needle = self::normalized_match_name( $title );
		if ( '' === $needle ) {
			return 0;
		}

		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		foreach ( $posts as $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}
			if ( $needle === self::normalized_match_name( get_the_title( $post ) ) ) {
				return absint( $post->ID );
			}
		}
		return 0;
	}

	private static function normalized_match_name( $title ) {
		return sanitize_title( remove_accents( trim( (string) $title ) ) );
	}

	private static function ensure_posted_event_organizer_relationship( $organizer_id, $relationship_type ) {
		if ( ! $organizer_id ) {
			return;
		}

		$items = isset( $_POST['taka_platform_event_organizers'] ) && is_array( $_POST['taka_platform_event_organizers'] ) ? wp_unslash( $_POST['taka_platform_event_organizers'] ) : array();
		foreach ( $items as $item ) {
			if ( absint( $item['organizer_id'] ?? 0 ) === $organizer_id && (string) ( $item['relationship_type'] ?? 'organizer' ) === $relationship_type ) {
				return;
			}
		}

		$items[] = array(
			'organizer_id'      => (string) $organizer_id,
			'relationship_type' => $relationship_type,
			'custom_label'      => '',
			'visible'           => 1,
			'sort_order'        => 'co_organizer' === $relationship_type ? 20 : 10,
		);
		$_POST['taka_platform_event_organizers'] = wp_slash( $items );
	}

	private static function with_temporary_post( $post, $callback ) {
		$original_post = $_POST;
		$_POST = $post;
		try {
			call_user_func( $callback );
		} finally {
			$_POST = $original_post;
		}
	}

	private static function should_create_inline_object( $mode_key, $action, $payload_key ) {
		$mode = sanitize_key( wp_unslash( $_POST[ $mode_key ] ?? 'select' ) );
		if ( 'create' === $mode || self::inline_action_is( $action ) ) {
			return true;
		}
		return self::has_named_payload( self::posted_array( $payload_key ) );
	}

	private static function inline_action_is( $action ) {
		return $action === sanitize_key( wp_unslash( $_POST['assistant_inline_action'] ?? '' ) );
	}

	private static function has_named_payload( $payload ) {
		return '' !== trim( (string) ( $payload['name'] ?? '' ) );
	}

	private static function posted_array( $key ) {
		$value = $_POST[ $key ] ?? array();
		$value = is_array( $value ) ? wp_unslash( $value ) : array();
		return $value;
	}

	private static function posted_source_language( $posted ) {
		$fallback = TAKA_Platform_Translation_Packages::sanitize_language( wp_unslash( $_POST['_taka_source_language'] ?? 'de' ) );
		return TAKA_Platform_Translation_Packages::sanitize_language( $posted['source_language'] ?? $fallback );
	}

	private static function record_inline_result( &$results, $result ) {
		if ( ! empty( $result['notice'] ) ) {
			$results['notices'][] = sanitize_key( $result['notice'] );
		}
		if ( ! empty( $result['error'] ) ) {
			$results['errors'][] = sanitize_key( $result['error'] );
		}
	}

	private static function inline_redirect_args( $results ) {
		$args = array();
		if ( ! empty( $results['notices'] ) ) {
			$args['assistant_notice'] = implode( ',', array_unique( array_map( 'sanitize_key', $results['notices'] ) ) );
		}
		if ( ! empty( $results['errors'] ) ) {
			$args['assistant_error'] = implode( ',', array_unique( array_map( 'sanitize_key', $results['errors'] ) ) );
		}
		return $args;
	}

	private static function render_inline_object_notices() {
		$notice_messages = array(
			'venue_created'         => __( 'New venue created and selected for this event.', 'taka-platform' ),
			'venue_existing'        => __( 'A matching existing venue was selected instead of creating a duplicate.', 'taka-platform' ),
			'organizer_created'     => __( 'New organizer created and selected for this event.', 'taka-platform' ),
			'organizer_existing'    => __( 'A matching existing organizer was selected instead of creating a duplicate.', 'taka-platform' ),
			'co_organizer_created'  => __( 'New co-organizer created and added to this event.', 'taka-platform' ),
			'co_organizer_existing' => __( 'A matching existing co-organizer was added instead of creating a duplicate.', 'taka-platform' ),
		);
		$error_messages = array(
			'venue_forbidden'             => __( 'You do not have permission to create venues. Select an existing venue instead.', 'taka-platform' ),
			'venue_missing_name'          => __( 'Venue name is required before a new venue can be created.', 'taka-platform' ),
			'venue_create_failed'         => __( 'The new venue could not be created. Please try again or select an existing venue.', 'taka-platform' ),
			'organizer_forbidden'         => __( 'You do not have permission to create organizers. Select an existing organizer instead.', 'taka-platform' ),
			'organizer_missing_name'      => __( 'Organizer name is required before a new organizer can be created.', 'taka-platform' ),
			'organizer_create_failed'     => __( 'The new organizer could not be created. Please try again or select an existing organizer.', 'taka-platform' ),
			'co_organizer_forbidden'      => __( 'You do not have permission to create co-organizers. Select an existing organizer instead.', 'taka-platform' ),
			'co_organizer_missing_name'   => __( 'Co-organizer name is required before a new co-organizer can be created.', 'taka-platform' ),
			'co_organizer_create_failed'  => __( 'The new co-organizer could not be created. Please try again or select an existing organizer.', 'taka-platform' ),
		);

		self::render_inline_messages_from_query( 'assistant_notice', $notice_messages, 'success' );
		self::render_inline_messages_from_query( 'assistant_error', $error_messages, 'error' );
	}

	private static function render_inline_messages_from_query( $query_key, $messages, $type ) {
		$raw = sanitize_text_field( wp_unslash( $_GET[ $query_key ] ?? '' ) );
		if ( '' === $raw ) {
			return;
		}
		foreach ( array_filter( array_map( 'sanitize_key', explode( ',', $raw ) ) ) as $key ) {
			if ( empty( $messages[ $key ] ) ) {
				continue;
			}
			$class = 'error' === $type ? 'notice notice-error is-dismissible' : 'notice notice-success is-dismissible';
			echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $messages[ $key ] ) . '</p></div>';
		}
	}

	public static function sections() {
		if ( null !== self::$section_cache ) {
			return self::$section_cache;
		}

		$expanded = TAKA_Platform_Admin_Collapsible_Section::STATE_EXPANDED;
		$collapsed = TAKA_Platform_Admin_Collapsible_Section::STATE_COLLAPSED;

		$sections = array(
			new TAKA_Platform_Admin_Event_Assistant_Section( array(
				'id'                => 'basic',
				'title'             => __( 'Basic information', 'taka-platform' ),
				'help_text'         => __( 'The editorial identity used in event lists, detail pages and internal admin views.', 'taka-platform' ),
				'default_state'     => $expanded,
				'weight'            => 10,
				'render_callback'   => array( __CLASS__, 'render_basic_section' ),
				'required_callback' => array( __CLASS__, 'missing_basic' ),
				'optional_callback' => array( __CLASS__, 'optional_basic' ),
			) ),
			new TAKA_Platform_Admin_Event_Assistant_Section( array(
				'id'                => 'dates',
				'title'             => __( 'Dates & schedule', 'taka-platform' ),
				'help_text'         => __( 'Program items define when the event happens and how it is grouped on the public schedule.', 'taka-platform' ),
				'default_state'     => $expanded,
				'weight'            => 10,
				'render_callback'   => array( __CLASS__, 'render_dates_section' ),
				'required_callback' => array( __CLASS__, 'missing_dates' ),
				'optional_callback' => array( __CLASS__, 'optional_dates' ),
			) ),
			new TAKA_Platform_Admin_Event_Assistant_Section( array(
				'id'                => 'venue',
				'title'             => __( 'Venue', 'taka-platform' ),
				'help_text'         => __( 'The venue connects the event to address, arrival and map information.', 'taka-platform' ),
				'default_state'     => $expanded,
				'weight'            => 9,
				'render_callback'   => array( __CLASS__, 'render_venue_section' ),
				'required_callback' => array( __CLASS__, 'missing_venue' ),
			) ),
			new TAKA_Platform_Admin_Event_Assistant_Section( array(
				'id'                => 'organizer',
				'title'             => __( 'Organizer', 'taka-platform' ),
				'help_text'         => __( 'Organizer relationships decide who is shown as host, partner or co-organizer.', 'taka-platform' ),
				'default_state'     => $expanded,
				'weight'            => 9,
				'render_callback'   => array( __CLASS__, 'render_organizer_section' ),
				'required_callback' => array( __CLASS__, 'missing_organizer' ),
			) ),
			new TAKA_Platform_Admin_Event_Assistant_Section( array(
				'id'                => 'co-organizers',
				'title'             => __( 'Co-organizers', 'taka-platform' ),
				'help_text'         => __( 'Optional additional organizer roles for events with partners, hosts or supporting organizations.', 'taka-platform' ),
				'default_state'     => $collapsed,
				'weight'            => 4,
				'render_callback'   => array( __CLASS__, 'render_co_organizers_section' ),
				'optional_callback' => array( __CLASS__, 'optional_co_organizers' ),
			) ),
			new TAKA_Platform_Admin_Event_Assistant_Section( array(
				'id'                => 'description',
				'title'             => __( 'Seminar description', 'taka-platform' ),
				'help_text'         => __( 'The original public description and optional reusable content block shown on the event page.', 'taka-platform' ),
				'default_state'     => $expanded,
				'weight'            => 10,
				'render_callback'   => array( __CLASS__, 'render_description_section' ),
				'required_callback' => array( __CLASS__, 'missing_description' ),
			) ),
			new TAKA_Platform_Admin_Event_Assistant_Section( array(
				'id'                => 'media',
				'title'             => __( 'Media', 'taka-platform' ),
				'help_text'         => __( 'Images improve event cards and detail pages. They can be added later without blocking publication.', 'taka-platform' ),
				'default_state'     => $collapsed,
				'weight'            => 8,
				'render_callback'   => array( __CLASS__, 'render_media_section' ),
				'optional_callback' => array( __CLASS__, 'optional_media' ),
			) ),
			new TAKA_Platform_Admin_Event_Assistant_Section( array(
				'id'                => 'promo-videos',
				'title'             => __( 'Promo videos', 'taka-platform' ),
				'help_text'         => __( 'Optional local videos or oEmbed links shown after the core event facts.', 'taka-platform' ),
				'default_state'     => $collapsed,
				'weight'            => 5,
				'render_callback'   => array( __CLASS__, 'render_promo_videos_section' ),
				'optional_callback' => array( __CLASS__, 'optional_promo_videos' ),
			) ),
			new TAKA_Platform_Admin_Event_Assistant_Section( array(
				'id'                => 'tickets',
				'title'             => __( 'Tickets', 'taka-platform' ),
				'help_text'         => __( 'Ticket provider and booking link shown wherever visitors can register or buy tickets.', 'taka-platform' ),
				'default_state'     => $expanded,
				'weight'            => 10,
				'render_callback'   => array( __CLASS__, 'render_tickets_section' ),
				'required_callback' => array( __CLASS__, 'missing_tickets' ),
				'optional_callback' => array( __CLASS__, 'optional_tickets' ),
			) ),
			new TAKA_Platform_Admin_Event_Assistant_Section( array(
				'id'                => 'translations',
				'title'             => __( 'Website translations', 'taka-platform' ),
				'help_text'         => __( 'Website translations use the same source-language workflow as the classic editor.', 'taka-platform' ),
				'default_state'     => $expanded,
				'weight'            => 8,
				'render_callback'   => array( __CLASS__, 'render_translations_section' ),
				'optional_callback' => array( __CLASS__, 'optional_translations' ),
			) ),
			new TAKA_Platform_Admin_Event_Assistant_Section( array(
				'id'                => 'route-map',
				'title'             => __( 'Route map', 'taka-platform' ),
				'help_text'         => __( 'Advanced visual controls for map markers and route labels.', 'taka-platform' ),
				'default_state'     => $collapsed,
				'weight'            => 5,
				'render_callback'   => array( __CLASS__, 'render_route_map_section' ),
				'optional_callback' => array( __CLASS__, 'optional_route_map' ),
			) ),
			new TAKA_Platform_Admin_Event_Assistant_Section( array(
				'id'                => 'booking',
				'title'             => __( 'Booking information', 'taka-platform' ),
				'help_text'         => __( 'Optional event-specific booking copy that overrides global booking-information defaults.', 'taka-platform' ),
				'default_state'     => $collapsed,
				'weight'            => 5,
				'render_callback'   => array( __CLASS__, 'render_booking_section' ),
				'optional_callback' => array( __CLASS__, 'optional_booking' ),
			) ),
			new TAKA_Platform_Admin_Event_Assistant_Section( array(
				'id'                => 'permissions',
				'title'             => __( 'Permissions', 'taka-platform' ),
				'help_text'         => __( 'Object-level access controls for multi-role platform editing.', 'taka-platform' ),
				'default_state'     => $collapsed,
				'weight'            => 4,
				'render_callback'   => array( __CLASS__, 'render_permissions_section' ),
			) ),
			new TAKA_Platform_Admin_Event_Assistant_Section( array(
				'id'                => 'advanced',
				'title'             => __( 'Advanced settings', 'taka-platform' ),
				'help_text'         => __( 'Compatibility and internal metadata retained for older events and integrations.', 'taka-platform' ),
				'default_state'     => $collapsed,
				'weight'            => 3,
				'render_callback'   => array( __CLASS__, 'render_advanced_section' ),
			) ),
		);

		self::$section_cache = apply_filters( 'taka_platform_event_assistant_sections', $sections );
		return self::$section_cache;
	}

	public static function missing_basic( $context ) {
		$missing = array();
		if ( '' === trim( (string) $context['title'] ) ) {
			$missing[] = __( 'Event title', 'taka-platform' );
		}
		if ( '' === trim( (string) $context['source_language'] ) ) {
			$missing[] = __( 'Source language', 'taka-platform' );
		}
		return $missing;
	}

	public static function optional_basic( $context ) {
		$missing = array();
		if ( '' === trim( (string) self::value( $context, 'country' ) ) ) {
			$missing[] = __( 'Country', 'taka-platform' );
		}
		if ( '' === trim( (string) self::value( $context, 'city' ) ) ) {
			$missing[] = __( 'City', 'taka-platform' );
		}
		return $missing;
	}

	public static function missing_dates( $context ) {
		foreach ( self::program_items( $context ) as $item ) {
			if ( '' !== trim( (string) ( $item['date'] ?? '' ) ) ) {
				return array();
			}
		}
		return array( __( 'At least one dated program item', 'taka-platform' ) );
	}

	public static function optional_dates( $context ) {
		return '' === trim( (string) self::value( $context, 'doors_open' ) ) ? array( __( 'Doors-open time', 'taka-platform' ) ) : array();
	}

	public static function missing_venue( $context ) {
		return absint( self::value( $context, 'venue_id' ) ) ? array() : array( __( 'Primary venue', 'taka-platform' ) );
	}

	public static function missing_organizer( $context ) {
		return absint( self::value( $context, 'organizer_id' ) ) ? array() : array( __( 'Primary organizer', 'taka-platform' ) );
	}

	public static function optional_co_organizers( $context ) {
		foreach ( self::organizer_relationships( $context ) as $relationship ) {
			if ( 'organizer' !== (string) ( $relationship['relationship_type'] ?? 'organizer' ) ) {
				return array();
			}
		}
		return array( __( 'Co-organizer roles, if this event has partners', 'taka-platform' ) );
	}

	public static function missing_description( $context ) {
		$reference = TAKA_Platform_Data::normalize_content_reference( $context['content_reference'] ?? array(), 'event_description' );
		if ( '' !== trim( (string) ( $reference['block_id'] ?? '' ) ) ) {
			return array();
		}
		return '' !== trim( (string) self::source_text_value( $context, 'description' ) ) ? array() : array( __( 'Seminar description', 'taka-platform' ) );
	}

	public static function optional_media( $context ) {
		return self::has_any_value( self::value( $context, 'image_id' ) ) || self::has_any_value( self::value( $context, 'image_url' ) ) ? array() : array( __( 'Hero image', 'taka-platform' ) );
	}

	public static function optional_promo_videos( $context ) {
		return empty( self::event_videos( $context ) ) ? array( __( 'Promo video', 'taka-platform' ) ) : array();
	}

	public static function missing_tickets( $context ) {
		$missing = array();
		$mode = self::ticket_mode_for_context( $context );
		if ( '' === $mode ) {
			$missing[] = __( 'Ticket mode', 'taka-platform' );
			return $missing;
		}
		if ( 'online_shop' === $mode && '' === trim( (string) self::value( $context, 'ticket_provider' ) ) ) {
			$missing[] = __( 'Ticket provider', 'taka-platform' );
		}
		if ( TAKA_Platform_Data::ticket_mode_has_online_url( $mode ) && '' === trim( (string) self::value( $context, 'ticket_shop_url' ) ) ) {
			$missing[] = __( 'Booking URL', 'taka-platform' );
		}
		return $missing;
	}

	public static function optional_tickets( $context ) {
		if ( 'pay_at_door' !== self::ticket_mode_for_context( $context ) ) {
			return array();
		}
		return '' === trim( (string) self::value( $context, 'ticket_door_price' ) ) ? array( __( 'Door price / admission on site', 'taka-platform' ) ) : array();
	}

	public static function optional_translations( $context ) {
		$missing = array();
		$source_language = (string) $context['source_language'];
		$translations = (array) ( $context['translations']['description'] ?? array() );
		foreach ( TAKA_Platform_Translation_Packages::language_labels() as $lang => $label ) {
			if ( $lang === $source_language ) {
				continue;
			}
			if ( '' === trim( (string) ( $translations[ $lang ] ?? '' ) ) ) {
				$missing[] = sprintf( __( '%s translation', 'taka-platform' ), $label );
			}
		}
		return $missing;
	}

	public static function optional_route_map( $context ) {
		return self::has_any_value( self::value( $context, 'route_map_x' ) ) && self::has_any_value( self::value( $context, 'route_map_y' ) ) ? array() : array( __( 'Route map marker coordinates', 'taka-platform' ) );
	}

	public static function optional_booking( $context ) {
		if ( '1' !== (string) self::value( $context, 'booking_info_override' ) ) {
			return array( __( 'Event-specific booking information', 'taka-platform' ) );
		}
		return '' === trim( (string) self::value( $context, 'booking_info_intro' ) ) ? array( __( 'Booking intro text', 'taka-platform' ) ) : array();
	}

	public static function render_basic_section( $context ) {
		self::field( __( 'Event title', 'taka-platform' ), '<input class="widefat" type="text" name="post_title" value="' . esc_attr( (string) $context['title'] ) . '" required>' );
		self::source_language_select( $context );
		self::option_select( $context, 'country', __( 'Country', 'taka-platform' ) );
		self::derived_country_hint( $context );
		self::text_field( $context, 'city', __( 'City', 'taka-platform' ) );
		self::text_field( $context, 'timezone', __( 'Timezone override', 'taka-platform' ) );
		self::option_select( $context, 'format', __( 'Format', 'taka-platform' ) );
		self::option_select( $context, 'audience', __( 'Audience', 'taka-platform' ) );
		self::option_select( $context, 'level', __( 'Level', 'taka-platform' ) );
		self::language_multiselect( $context, 'languages', __( 'Spoken / teaching languages', 'taka-platform' ) );
	}

	public static function render_dates_section( $context ) {
		self::text_field( $context, 'doors_open', __( 'Doors open', 'taka-platform' ) );
		self::render_program_items( $context );
	}

	public static function render_venue_section( $context ) {
		self::inline_choice_radios( 'taka_event_assistant_venue_mode', self::default_inline_mode( $context, TAKA_PLATFORM_CPT_VENUE, 'venue_id' ), __( 'Venue workflow', 'taka-platform' ) );
		self::relation_select( $context, 'venue_id', __( 'Primary venue', 'taka-platform' ), TAKA_PLATFORM_CPT_VENUE );
		self::text_field( $context, 'venue_ids', __( 'Additional venue IDs, comma-separated', 'taka-platform' ) );
		self::selected_object_later_link( absint( self::value( $context, 'venue_id' ) ), __( 'Edit full venue details later', 'taka-platform' ) );
		self::render_inline_venue_create_form( $context );
	}

	public static function render_organizer_section( $context ) {
		self::inline_choice_radios( 'taka_event_assistant_organizer_mode', self::default_inline_mode( $context, TAKA_PLATFORM_CPT_ORGANIZER, 'organizer_id' ), __( 'Organizer workflow', 'taka-platform' ) );
		self::organizer_relation_select( $context, 'organizer_id', __( 'Primary organizer', 'taka-platform' ) );
		self::selected_object_later_link( absint( self::value( $context, 'organizer_id' ) ), __( 'Edit full organizer details later', 'taka-platform' ) );
		?>
		<p class="description"><?php echo esc_html__( 'Primary organizer is kept for backwards compatibility. The visible event organizer list below controls current frontend display.', 'taka-platform' ); ?></p>
		<?php
		self::render_organizer_relationships( $context, true );
		self::render_inline_organizer_create_form( $context, 'primary' );
	}

	public static function render_co_organizers_section( $context ) {
		self::render_organizer_relationships( $context, false );
		self::render_inline_organizer_create_form( $context, 'co_organizer' );
	}

	public static function render_description_section( $context ) {
		self::content_reference_fields( $context );
		self::translation_textareas( $context, array( 'description' => __( 'Seminar description', 'taka-platform' ) ), true );
	}

	public static function render_media_section( $context ) {
		self::media_field( $context, 'image_id', __( 'Event action photo', 'taka-platform' ), false, __( 'Select action photo', 'taka-platform' ) );
		self::url_field( $context, 'image_url', __( 'Fallback action photo URL', 'taka-platform' ) );
		self::media_field( $context, 'group_image_id', __( 'Past group photo', 'taka-platform' ), false, __( 'Select group photo', 'taka-platform' ) );
		self::url_field( $context, 'group_image_url', __( 'Fallback group photo URL', 'taka-platform' ) );
		self::media_field( $context, 'gallery_image_ids', __( 'Gallery images', 'taka-platform' ), true, __( 'Select gallery images', 'taka-platform' ) );
		self::text_field( $context, 'photo_credit', __( 'Photo credit', 'taka-platform' ) );
	}

	public static function render_promo_videos_section( $context ) {
		self::render_event_video_fields( $context );
	}

	public static function render_tickets_section( $context ) {
		self::option_select( $context, 'currency', __( 'Currency override', 'taka-platform' ) );
		self::option_select( $context, 'ticket_mode', __( 'Ticket mode', 'taka-platform' ) );
		self::option_select( $context, 'ticket_provider', __( 'Ticket provider', 'taka-platform' ) );
		self::option_select( $context, 'ticket_status', __( 'Ticket status', 'taka-platform' ) );
		self::url_field( $context, 'ticket_shop_url', __( 'Ticket shop URL', 'taka-platform' ) );
		self::text_field( $context, 'ticket_door_price', __( 'Door price / admission on site', 'taka-platform' ) );
		self::text_field( $context, 'ticket_door_price_reduced', __( 'Reduced door price', 'taka-platform' ) );
		self::text_field( $context, 'ticket_door_price_child', __( 'Child door price', 'taka-platform' ) );
		self::text_field( $context, 'ticket_door_price_member', __( 'Member door price', 'taka-platform' ) );
		echo '<p class="description">' . esc_html__( 'For pay-at-door events, the booking URL is not required. Add the event-specific note in Website translations so it can be translated per language.', 'taka-platform' ) . '</p>';
		if ( class_exists( 'TAKA_Ticketing_Module' ) ) {
			echo '<p class="description">' . esc_html__( 'Native TAKA Ticketing can be selected here. Repeatable ticket types and payment methods are configured in the shared native ticketing section after the draft exists.', 'taka-platform' ) . '</p>';
		}
	}

	public static function render_translations_section( $context ) {
		$fields = TAKA_Platform_Data::translatable_text_fields( 'event' );
		unset( $fields['description'] );
		self::translation_textareas( $context, $fields, false, array( 'long_description', 'ticket_card_text' ) );
	}

	public static function render_route_map_section( $context ) {
		foreach ( array( 'route_map_x' => __( 'Route marker X (0-100)', 'taka-platform' ), 'route_map_y' => __( 'Route marker Y (0-100)', 'taka-platform' ), 'route_map_label' => __( 'Route map label', 'taka-platform' ), 'route_map_label_x' => __( 'Route label X (0-100)', 'taka-platform' ), 'route_map_label_y' => __( 'Route label Y (0-100)', 'taka-platform' ) ) as $field => $label ) {
			self::text_field( $context, $field, $label );
		}
		self::select_field( '_taka_route_map_label_anchor', __( 'Route label anchor', 'taka-platform' ), self::value( $context, 'route_map_label_anchor' ), array( '' => __( 'Automatic', 'taka-platform' ), 'left' => __( 'Left', 'taka-platform' ), 'center' => __( 'Center', 'taka-platform' ), 'right' => __( 'Right', 'taka-platform' ) ) );
		self::text_field( $context, 'route_map_label_width', __( 'Route label width', 'taka-platform' ) );
		self::number_field( $context, 'tour_order', __( 'Tour order', 'taka-platform' ) );
		self::checkbox_field( $context, 'route_map_leader_line', __( 'Show route label leader line', 'taka-platform' ) );
	}

	public static function render_booking_section( $context ) {
		self::checkbox_field( $context, 'booking_info_override', __( 'Use custom booking information for this event', 'taka-platform' ) );
		self::checkbox_field( $context, 'booking_info_enabled', __( 'Show booking information for this event', 'taka-platform' ), true );
		self::text_field( $context, 'booking_info_title', __( 'Booking information title', 'taka-platform' ) );
		foreach ( array(
			'booking_info_intro' => __( 'Intro text', 'taka-platform' ),
			'booking_info_group_booking' => __( 'Group booking text', 'taka-platform' ),
			'booking_info_multi_event_discount' => __( 'Multi-event discount text', 'taka-platform' ),
			'booking_info_booking_process' => __( 'Booking process text', 'taka-platform' ),
			'booking_info_payment_methods' => __( 'Payment methods', 'taka-platform' ),
			'booking_info_cancellation_policy' => __( 'Cancellation policy text', 'taka-platform' ),
			'booking_info_additional_notes' => __( 'Additional notes', 'taka-platform' ),
		) as $field => $label ) {
			self::textarea_field( $context, $field, $label );
		}
		self::email_field( $context, 'booking_info_contact_email', __( 'Contact email', 'taka-platform' ) );
	}

	public static function render_permissions_section( $context ) {
		$owner_id = absint( $context['post_author'] ?: get_current_user_id() );
		$mode = sanitize_key( (string) ( $context['permission_mode'] ?: 'owner' ) );

		if ( ! self::is_platform_admin() ) {
			self::field( __( 'Owner', 'taka-platform' ), esc_html( self::user_label( $owner_id ) ) );
			self::field( __( 'Permission mode', 'taka-platform' ), esc_html( self::access_modes()[ $mode ] ?? self::access_modes()['owner'] ) );
			echo '<p class="description">' . esc_html__( 'Administrators manage access assignments. Your edit access comes from ownership or an explicit assignment.', 'taka-platform' ) . '</p>';
			return;
		}

		$users = get_users(
			array(
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => array( 'ID', 'display_name', 'user_login' ),
			)
		);
		$user_options = array();
		foreach ( $users as $user ) {
			$user_options[ (string) $user->ID ] = $user->display_name ?: $user->user_login;
		}
		self::select_field( 'taka_access_owner_user_id', __( 'Owner', 'taka-platform' ), (string) $owner_id, $user_options );
		self::select_field( 'taka_access_permission_mode', __( 'Permission mode', 'taka-platform' ), $mode, self::access_modes() );
		self::multi_post_select( 'taka_access_assigned_user_ids[]', __( 'Assigned users', 'taka-platform' ), $context['assigned_user_ids'], $users, 'user' );
		self::multi_post_select( 'taka_access_assigned_organizer_ids[]', __( 'Assigned organizer members', 'taka-platform' ), $context['assigned_organizer_ids'], self::posts( TAKA_PLATFORM_CPT_ORGANIZER, 'any' ), 'post' );
	}

	public static function render_advanced_section( $context ) {
		self::number_field( $context, 'sort_order', __( 'Sort order', 'taka-platform' ) );
		echo '<p class="description">' . esc_html__( 'Technical compatibility fields stay available here when they affect sorting or integrations. Public text should be edited through source-language and website translation fields.', 'taka-platform' ) . '</p>';
	}

	private static function render_section( $section, $context, $evaluation ) {
		$anchor_id = self::section_anchor_id( $section->getId() );
		TAKA_Platform_Admin_Collapsible_Section::open(
			array(
				'id'            => 'event-assistant-' . $section->getId(),
				'title'         => $section->getTitle(),
				'help_text'     => $section->getHelpText(),
				'default_state' => $section->getDefaultState(),
				'class'         => 'taka-event-assistant-section taka-event-assistant-section--' . sanitize_html_class( (string) ( $evaluation['status'] ?? 'complete' ) ),
				'attributes'    => array(
					'id' => $anchor_id,
					'data-taka-event-assistant-section' => $section->getId(),
				),
			)
		);
		self::render_section_status( $evaluation );
		$section->render( $section->load( $context ) );
		TAKA_Platform_Admin_Collapsible_Section::close();
	}

	private static function render_section_status( $evaluation ) {
		if ( empty( $evaluation ) ) {
			return;
		}
		?>
		<p class="taka-event-assistant-section__status">
			<span class="taka-event-assistant-status taka-event-assistant-status--<?php echo esc_attr( $evaluation['status'] ); ?>"><?php echo esc_html( self::status_label( $evaluation['status'] ) ); ?></span>
		</p>
		<?php
	}

	private static function render_status_card( $health ) {
		?>
		<section class="taka-event-assistant-health" aria-labelledby="taka-event-assistant-health-title">
			<div class="taka-event-assistant-health__summary">
				<div>
					<h2 id="taka-event-assistant-health-title"><?php echo esc_html__( 'Event completeness', 'taka-platform' ); ?></h2>
					<div class="taka-event-assistant-health__bar" aria-hidden="true"><span style="width: <?php echo esc_attr( (string) $health['percent'] ); ?>%;"></span></div>
				</div>
				<div class="taka-event-assistant-health__score">
					<strong><?php echo esc_html( (string) $health['percent'] ); ?>%</strong>
					<span class="taka-event-assistant-health__badge taka-event-assistant-health__badge--<?php echo esc_attr( $health['tone'] ); ?>"><?php echo esc_html( $health['label'] ); ?></span>
				</div>
			</div>
			<?php if ( empty( $health['missing_required'] ) && empty( $health['missing_optional'] ) ) : ?>
				<p class="taka-event-assistant-health__empty"><?php echo esc_html__( 'All tracked information is complete.', 'taka-platform' ); ?></p>
			<?php else : ?>
				<div class="taka-event-assistant-health__lists">
					<?php self::render_missing_list( __( 'Missing required information', 'taka-platform' ), $health['missing_required'], 'required' ); ?>
					<?php self::render_missing_list( __( 'Optional improvements', 'taka-platform' ), $health['missing_optional'], 'optional' ); ?>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function render_missing_list( $title, $items, $type ) {
		if ( empty( $items ) ) {
			return;
		}
		?>
		<div class="taka-event-assistant-health__list taka-event-assistant-health__list--<?php echo esc_attr( $type ); ?>">
			<h3><?php echo esc_html( $title ); ?></h3>
			<ul>
				<?php foreach ( $items as $item ) : ?>
					<li><a href="#<?php echo esc_attr( self::section_anchor_id( $item['section_id'] ) ); ?>"><?php echo esc_html( $item['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	private static function render_sidebar( $sections, $health ) {
		?>
		<nav class="taka-event-assistant-nav">
			<h2><?php echo esc_html__( 'Checklist', 'taka-platform' ); ?></h2>
			<ol>
				<?php foreach ( $sections as $section ) : ?>
					<?php $evaluation = $health['sections'][ $section->getId() ] ?? array( 'status' => 'complete' ); ?>
					<li>
						<a class="taka-event-assistant-nav__item taka-event-assistant-nav__item--<?php echo esc_attr( $evaluation['status'] ); ?>" href="#<?php echo esc_attr( self::section_anchor_id( $section->getId() ) ); ?>">
							<span class="taka-event-assistant-nav__mark" aria-hidden="true"><?php echo esc_html( self::status_mark( $evaluation['status'] ) ); ?></span>
							<span><?php echo esc_html( $section->getTitle() ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ol>
		</nav>
		<?php
	}

	private static function evaluate_event( $context, $sections ) {
		$evaluations = array();
		$total = 0;
		$score = 0;
		$missing_required = array();
		$missing_optional = array();

		foreach ( $sections as $section ) {
			$evaluation = $section->evaluate( $context );
			$evaluations[ $section->getId() ] = $evaluation;
			$total += (int) $evaluation['score_total'];
			$score += (int) $evaluation['score'];
			$missing_required = array_merge( $missing_required, $evaluation['missing_required'] );
			$missing_optional = array_merge( $missing_optional, $evaluation['missing_optional'] );
		}

		$percent = $total ? (int) round( ( $score / $total ) * 100 ) : 0;
		if ( ! empty( $missing_required ) ) {
			$label = $percent >= 70 ? __( 'Almost ready', 'taka-platform' ) : __( 'Draft incomplete', 'taka-platform' );
			$tone = $percent >= 70 ? 'warning' : 'critical';
		} elseif ( 'publish' === (string) $context['post_status'] && ! empty( $missing_optional ) ) {
			$label = __( 'Published but missing optional improvements', 'taka-platform' );
			$tone = 'warning';
		} else {
			$label = __( 'Ready for publication', 'taka-platform' );
			$tone = 'success';
		}

		return array(
			'percent'          => $percent,
			'label'            => $label,
			'tone'             => $tone,
			'sections'         => $evaluations,
			'missing_required' => $missing_required,
			'missing_optional' => $missing_optional,
		);
	}

	private static function load_context( $event_id = 0, $reuse_recent = false ) {
		$post = $event_id ? get_post( $event_id ) : null;
		if ( $event_id && ( ! $post || TAKA_PLATFORM_CPT_EVENT !== $post->post_type ) ) {
			wp_die( esc_html__( 'Event not found.', 'taka-platform' ) );
		}

		$values = array();
		foreach ( self::event_meta_fields() as $field ) {
			$values[ $field ] = $event_id ? get_post_meta( $event_id, '_taka_' . $field, true ) : '';
		}
		if ( ! $event_id ) {
			$values['booking_info_enabled'] = '1';
		}

		$recent = self::recent_settings();
		if ( ! $event_id && $reuse_recent && ! empty( $recent ) ) {
			foreach ( self::recent_setting_fields() as $field ) {
				if ( isset( $recent[ $field ] ) && array_key_exists( $field, $values ) ) {
					$values[ $field ] = $recent[ $field ];
				}
			}
		}

		if ( ! $event_id && empty( $values['organizer_id'] ) ) {
			$organizers = self::posts( TAKA_PLATFORM_CPT_ORGANIZER );
			if ( 1 === count( $organizers ) ) {
				$values['organizer_id'] = (string) $organizers[0]->ID;
			}
		}

		$source_language = $event_id ? (string) get_post_meta( $event_id, '_taka_source_language', true ) : (string) ( $recent['source_language'] ?? 'de' );
		$source_language = TAKA_Platform_Translation_Packages::sanitize_language( $source_language ?: 'de' );
		$fields = TAKA_Platform_Data::translatable_text_fields( 'event' );
		$translations = $event_id ? TAKA_Platform_Data::normalize_object_text_translations( get_post_meta( $event_id, '_taka_text_translations', true ), $fields ) : TAKA_Platform_Data::normalize_object_text_translations( array(), $fields );

		return array(
			'post_id'                => $event_id,
			'mode'                   => $event_id ? 'edit' : 'create',
			'post'                   => $post,
			'title'                  => $post ? get_the_title( $post ) : '',
			'post_status'            => $post ? $post->post_status : 'draft',
			'post_author'            => $post ? (int) $post->post_author : get_current_user_id(),
			'post_content'           => $post ? (string) $post->post_content : '',
			'values'                 => $values,
			'source_language'        => $source_language,
			'translations'           => $translations,
			'program_items'          => $event_id ? TAKA_Platform_Data::normalize_program_items( get_post_meta( $event_id, '_taka_program_items', true ), array() ) : array(),
			'native_ticket_types'    => ( $event_id && class_exists( 'TAKA_Ticketing_Module' ) ) ? TAKA_Ticketing_Module::ticket_types_for_event( $event_id ) : array(),
			'organizer_relationships' => $event_id ? TAKA_Platform_Data::normalize_event_organizer_relationships( get_post_meta( $event_id, '_taka_event_organizers', true ), $values['organizer_id'] ) : TAKA_Platform_Data::normalize_event_organizer_relationships( array(), $values['organizer_id'] ),
			'event_videos'           => $event_id ? TAKA_Platform_Data::normalize_event_videos( get_post_meta( $event_id, '_taka_promo_videos', true ) ) : array(),
			'content_reference'      => $event_id ? TAKA_Platform_Data::normalize_content_reference( get_post_meta( $event_id, '_taka_content_reference_event_description', true ), 'event_description' ) : TAKA_Platform_Data::normalize_content_reference( array(), 'event_description' ),
			'permission_mode'        => $event_id ? get_post_meta( $event_id, '_taka_permission_mode', true ) : 'owner',
			'assigned_user_ids'      => $event_id ? self::id_list_meta( $event_id, '_taka_assigned_user_ids' ) : array(),
			'assigned_organizer_ids' => $event_id ? self::id_list_meta( $event_id, '_taka_assigned_organizer_ids' ) : array(),
			'recent_available'       => ! empty( $recent ),
			'reuse_recent'           => $reuse_recent,
		);
	}

	private static function event_meta_fields() {
		return array( 'country', 'country_code', 'flag', 'route_map_x', 'route_map_y', 'route_map_label', 'route_map_label_x', 'route_map_label_y', 'route_map_label_anchor', 'route_map_label_width', 'route_map_leader_line', 'tour_order', 'city', 'doors_open', 'timezone', 'currency', 'format', 'audience', 'level', 'ticket_mode', 'ticket_provider', 'ticket_status', 'ticket_door_price', 'ticket_door_price_reduced', 'ticket_door_price_child', 'ticket_door_price_member', 'photo_credit', 'languages', 'organizer_id', 'venue_id', 'venue_ids', 'ticket_shop_url', 'image_id', 'image_url', 'group_image_id', 'group_image_url', 'gallery_image_ids', 'booking_info_override', 'booking_info_enabled', 'booking_info_title', 'booking_info_intro', 'booking_info_group_booking', 'booking_info_multi_event_discount', 'booking_info_contact_email', 'booking_info_booking_process', 'booking_info_payment_methods', 'booking_info_cancellation_policy', 'booking_info_additional_notes', 'sort_order', 'short_description', 'subtitle', 'long_description', 'ticket_card_text', 'ticket_tab_label', 'ticket_door_note', 'accessibility', 'notes', 'parking' );
	}

	private static function render_hidden_preserved_fields( $context ) {
		foreach ( array( 'country_code', 'flag' ) as $field ) {
			echo '<input type="hidden" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( (string) self::value( $context, $field ) ) . '">';
		}
	}

	private static function render_recent_reuse_prompt( $context ) {
		if ( 'create' !== $context['mode'] || empty( $context['recent_available'] ) || ! empty( $context['reuse_recent'] ) ) {
			return;
		}
		?>
		<div class="notice notice-info inline taka-event-assistant__reuse">
			<p><?php echo esc_html__( 'Recently used organizer, venue, ticket and language settings are available.', 'taka-platform' ); ?> <a class="button" href="<?php echo esc_url( self::assistant_url( 0, array( 'reuse_recent' => '1' ) ) ); ?>"><?php echo esc_html__( 'Reuse settings from previous event', 'taka-platform' ); ?></a></p>
		</div>
		<?php
	}

	private static function render_inline_venue_create_form( $context ) {
		if ( ! current_user_can( 'edit_taka_venues' ) ) {
			echo '<p class="description taka-event-assistant-inline-create__message">' . esc_html__( 'You do not have permission to create venues from here. Select an existing venue.', 'taka-platform' ) . '</p>';
			return;
		}
		$prefix = 'taka_event_assistant_new_venue';
		?>
		<div class="taka-event-assistant-inline-create" data-taka-inline-create-panel="taka_event_assistant_venue_mode">
			<h3><?php echo esc_html__( 'Create new venue', 'taka-platform' ); ?></h3>
			<p class="description"><?php echo esc_html__( 'Enter only the essentials now. Full venue details can be completed later.', 'taka-platform' ); ?></p>
			<p class="description"><?php echo esc_html__( 'Matching existing venue names are reused automatically to avoid duplicates.', 'taka-platform' ); ?></p>
			<div class="taka-event-assistant-inline-create__grid">
				<?php self::inline_text_input( $prefix . '[name]', __( 'Venue name', 'taka-platform' ), '' ); ?>
				<?php self::inline_language_select( $prefix . '[source_language]', __( 'Source language', 'taka-platform' ), $context['source_language'] ); ?>
				<?php self::inline_text_input( $prefix . '[street]', __( 'Street/address', 'taka-platform' ), '' ); ?>
				<?php self::inline_text_input( $prefix . '[postal_code]', __( 'ZIP/postal code', 'taka-platform' ), '' ); ?>
				<?php self::inline_text_input( $prefix . '[city]', __( 'City', 'taka-platform' ), '' ); ?>
				<?php self::inline_option_select( $prefix . '[country]', __( 'Country', 'taka-platform' ), 'country', self::value( $context, 'country' ) ); ?>
				<?php self::inline_url_input( $prefix . '[website]', __( 'Website', 'taka-platform' ), '' ); ?>
				<?php self::inline_textarea( $prefix . '[parking]', __( 'Parking notes', 'taka-platform' ), '' ); ?>
				<?php self::inline_textarea( $prefix . '[accessibility]', __( 'Accessibility notes', 'taka-platform' ), '' ); ?>
			</div>
			<p class="taka-event-assistant-inline-create__actions"><button type="submit" class="button" name="assistant_inline_action" value="create_venue"><?php echo esc_html__( 'Create and select', 'taka-platform' ); ?></button></p>
		</div>
		<?php
	}

	private static function render_inline_organizer_create_form( $context, $role ) {
		if ( ! current_user_can( 'edit_taka_organizers' ) ) {
			echo '<p class="description taka-event-assistant-inline-create__message">' . esc_html__( 'You do not have permission to create organizers from here. Select an existing organizer.', 'taka-platform' ) . '</p>';
			return;
		}
		$is_co_organizer = 'co_organizer' === $role;
		$prefix = $is_co_organizer ? 'taka_event_assistant_new_co_organizer' : 'taka_event_assistant_new_organizer';
		$button_value = $is_co_organizer ? 'create_co_organizer' : 'create_organizer';
		?>
		<div class="taka-event-assistant-inline-create" <?php echo $is_co_organizer ? '' : 'data-taka-inline-create-panel="taka_event_assistant_organizer_mode"'; ?>>
			<h3><?php echo esc_html( $is_co_organizer ? __( 'Create new co-organizer', 'taka-platform' ) : __( 'Create new organizer', 'taka-platform' ) ); ?></h3>
			<p class="description"><?php echo esc_html__( 'Create a reusable organizer profile with essentials only. Advanced organizer details can be completed later.', 'taka-platform' ); ?></p>
			<p class="description"><?php echo esc_html__( 'Matching existing organizer names are reused automatically to avoid duplicates.', 'taka-platform' ); ?></p>
			<div class="taka-event-assistant-inline-create__grid">
				<?php self::inline_text_input( $prefix . '[name]', __( 'Organizer name', 'taka-platform' ), '' ); ?>
				<?php self::inline_language_select( $prefix . '[source_language]', __( 'Source language', 'taka-platform' ), $context['source_language'] ); ?>
				<?php self::inline_email_input( $prefix . '[email]', __( 'Contact email', 'taka-platform' ), '' ); ?>
				<?php self::inline_url_input( $prefix . '[website]', __( 'Website', 'taka-platform' ), '' ); ?>
				<?php self::inline_media_input( $prefix . '[logo_id]', $is_co_organizer ? 'taka_event_assistant_new_co_organizer_logo_id' : 'taka_event_assistant_new_organizer_logo_id', __( 'Logo', 'taka-platform' ), __( 'Select logo', 'taka-platform' ) ); ?>
			</div>
			<p class="taka-event-assistant-inline-create__actions"><button type="submit" class="button" name="assistant_inline_action" value="<?php echo esc_attr( $button_value ); ?>"><?php echo esc_html( $is_co_organizer ? __( 'Create and add', 'taka-platform' ) : __( 'Create and select', 'taka-platform' ) ); ?></button></p>
		</div>
		<?php
	}

	private static function inline_choice_radios( $name, $current, $label ) {
		?>
		<fieldset class="taka-event-assistant-choice" data-taka-inline-create-toggle="<?php echo esc_attr( $name ); ?>">
			<legend><?php echo esc_html( $label ); ?></legend>
			<label><input type="radio" name="<?php echo esc_attr( $name ); ?>" value="select" <?php checked( 'select', $current ); ?>> <?php echo esc_html__( 'Select existing', 'taka-platform' ); ?></label>
			<label><input type="radio" name="<?php echo esc_attr( $name ); ?>" value="create" <?php checked( 'create', $current ); ?>> <?php echo esc_html__( 'Create new', 'taka-platform' ); ?></label>
		</fieldset>
		<?php
	}

	private static function default_inline_mode( $context, $post_type, $field ) {
		if ( absint( self::value( $context, $field ) ) ) {
			return 'select';
		}
		$create_cap = TAKA_PLATFORM_CPT_VENUE === $post_type ? 'edit_taka_venues' : 'edit_taka_organizers';
		return current_user_can( $create_cap ) && empty( self::posts( $post_type ) ) ? 'create' : 'select';
	}

	private static function selected_object_later_link( $post_id, $label ) {
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$url = get_edit_post_link( $post_id, '' );
		if ( ! $url ) {
			return;
		}
		echo '<p class="description taka-event-assistant-inline-create__message"><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></p>';
	}

	private static function inline_text_input( $name, $label, $value ) {
		echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><input class="widefat" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"></label></p>';
	}

	private static function inline_email_input( $name, $label, $value ) {
		echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><input class="widefat" type="email" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"></label></p>';
	}

	private static function inline_url_input( $name, $label, $value ) {
		echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><input class="widefat" type="url" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"></label></p>';
	}

	private static function inline_textarea( $name, $label, $value ) {
		echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><textarea class="widefat" rows="2" name="' . esc_attr( $name ) . '">' . esc_textarea( (string) $value ) . '</textarea></label></p>';
	}

	private static function inline_language_select( $name, $label, $current ) {
		self::select_field( $name, $label, $current, TAKA_Platform_Translation_Packages::language_labels() );
	}

	private static function inline_option_select( $name, $label, $field, $current ) {
		$raw = (string) $current;
		$matched_key = TAKA_Platform_Data::option_key_for_value( $field, $raw );
		$current = '' !== $matched_key ? $matched_key : $raw;
		$choices = array( '' => __( '- Select -', 'taka-platform' ) ) + TAKA_Platform_Data::option_list_choices( $field, TAKA_Platform_Data::platform_fallback_language() );
		self::select_field( $name, $label, $current, $choices );
	}

	private static function inline_media_input( $name, $input_id, $label, $button_label ) {
		$html = '<input id="' . esc_attr( $input_id ) . '" type="hidden" name="' . esc_attr( $name ) . '" value=""> ';
		$html .= '<button type="button" class="button" data-taka-media-pick data-multiple="0" data-target="' . esc_attr( $input_id ) . '" data-preview="' . esc_attr( $input_id . '_preview' ) . '">' . esc_html( $button_label ) . '</button> ';
		$html .= '<button type="button" class="button" data-taka-media-remove data-target="' . esc_attr( $input_id ) . '" data-preview="' . esc_attr( $input_id . '_preview' ) . '">' . esc_html__( 'Remove image', 'taka-platform' ) . '</button>';
		$html .= '<div id="' . esc_attr( $input_id . '_preview' ) . '"></div>';
		self::field( $label, $html );
	}

	private static function remember_recent_settings( $event_id ) {
		$settings = array( 'source_language' => TAKA_Platform_Translation_Packages::sanitize_language( wp_unslash( $_POST['_taka_source_language'] ?? 'de' ) ) );
		foreach ( self::recent_setting_fields() as $field ) {
			$key = '_taka_' . $field;
			if ( isset( $_POST[ $key ] ) ) {
				$value = wp_unslash( $_POST[ $key ] );
				$settings[ $field ] = is_array( $value ) ? array_values( array_map( 'sanitize_text_field', $value ) ) : sanitize_text_field( $value );
			}
		}
		update_user_meta( get_current_user_id(), self::RECENT_USER_META, $settings );
	}

	private static function recent_setting_fields() {
		return array( 'venue_id', 'organizer_id', 'currency', 'ticket_mode', 'ticket_provider', 'ticket_status', 'ticket_door_price', 'languages', 'booking_info_enabled', 'booking_info_title', 'booking_info_intro', 'booking_info_group_booking', 'booking_info_multi_event_discount', 'booking_info_contact_email', 'booking_info_booking_process', 'booking_info_payment_methods', 'booking_info_cancellation_policy', 'booking_info_additional_notes' );
	}

	private static function recent_settings() {
		$recent = get_user_meta( get_current_user_id(), self::RECENT_USER_META, true );
		return is_array( $recent ) ? $recent : array();
	}

	private static function value( $context, $field ) {
		return $context['values'][ $field ] ?? '';
	}

	private static function ticket_mode_for_context( $context ) {
		return TAKA_Platform_Data::ticket_mode_for_event(
			array(
				'ticket_mode' => self::value( $context, 'ticket_mode' ),
				'ticket_status' => self::value( $context, 'ticket_status' ),
				'ticket_provider' => self::value( $context, 'ticket_provider' ),
				'ticket_shop_url' => self::value( $context, 'ticket_shop_url' ),
			)
		);
	}

	private static function has_any_value( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( self::has_any_value( $item ) ) {
					return true;
				}
			}
			return false;
		}
		$value = trim( (string) $value );
		return '' !== $value && '0' !== $value;
	}

	private static function source_text_value( $context, $field ) {
		$source_language = (string) $context['source_language'];
		$translations = (array) ( $context['translations'][ $field ] ?? array() );
		if ( '' !== trim( (string) ( $translations[ $source_language ] ?? '' ) ) ) {
			return (string) $translations[ $source_language ];
		}
		if ( 'description' === $field ) {
			return (string) ( self::value( $context, 'short_description' ) ?: $context['post_content'] );
		}
		return (string) self::value( $context, $field );
	}

	private static function program_items( $context ) {
		return is_array( $context['program_items'] ?? null ) ? $context['program_items'] : array();
	}

	private static function organizer_relationships( $context ) {
		return is_array( $context['organizer_relationships'] ?? null ) ? $context['organizer_relationships'] : array();
	}

	private static function event_videos( $context ) {
		return is_array( $context['event_videos'] ?? null ) ? $context['event_videos'] : array();
	}

	private static function field( $label, $html ) {
		echo '<p class="taka-event-assistant-field"><label><strong>' . esc_html( $label ) . '</strong><br>' . $html . '</label></p>';
	}

	private static function text_field( $context, $field, $label ) {
		self::field( $label, '<input class="widefat" type="text" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( (string) self::value( $context, $field ) ) . '">' );
	}

	private static function email_field( $context, $field, $label ) {
		self::field( $label, '<input class="widefat" type="email" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( (string) self::value( $context, $field ) ) . '">' );
	}

	private static function url_field( $context, $field, $label ) {
		self::field( $label, '<input class="widefat" type="url" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( (string) self::value( $context, $field ) ) . '">' );
	}

	private static function number_field( $context, $field, $label ) {
		self::field( $label, '<input type="number" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( (string) self::value( $context, $field ) ) . '">' );
	}

	private static function textarea_field( $context, $field, $label ) {
		self::field( $label, '<textarea class="widefat" rows="3" name="_taka_' . esc_attr( $field ) . '">' . esc_textarea( (string) self::value( $context, $field ) ) . '</textarea>' );
	}

	private static function checkbox_field( $context, $field, $label, $default_checked = false ) {
		$value = (string) self::value( $context, $field );
		$checked = '' === $value ? $default_checked : '1' === $value;
		self::field( $label, '<input type="hidden" name="_taka_' . esc_attr( $field ) . '" value="0"><input type="checkbox" name="_taka_' . esc_attr( $field ) . '" value="1" ' . checked( $checked, true, false ) . '>' );
	}

	private static function select_field( $name, $label, $current, $choices, $attributes = array() ) {
		$html = '<select class="widefat" name="' . esc_attr( $name ) . '"' . self::html_attrs( $attributes ) . '>';
		foreach ( $choices as $value => $choice_label ) {
			$html .= '<option value="' . esc_attr( (string) $value ) . '" ' . selected( (string) $current, (string) $value, false ) . '>' . esc_html( $choice_label ) . '</option>';
		}
		$html .= '</select>';
		self::field( $label, $html );
	}

	private static function option_select( $context, $field, $label ) {
		$raw = (string) self::value( $context, $field );
		$matched_key = TAKA_Platform_Data::option_key_for_value( $field, $raw );
		$current = '' !== $matched_key ? $matched_key : $raw;
		$choices = array( '' => __( '- Select -', 'taka-platform' ) ) + TAKA_Platform_Data::option_list_choices( $field, TAKA_Platform_Data::platform_fallback_language() );
		if ( '' !== $raw && '' === $matched_key && ! isset( $choices[ $raw ] ) ) {
			$choices[ $raw ] = sprintf( __( 'Custom / legacy: %s', 'taka-platform' ), $raw );
		}
		self::select_field( '_taka_' . $field, $label, $current, $choices );
	}

	private static function source_language_select( $context ) {
		self::select_field( '_taka_source_language', __( 'Original content language', 'taka-platform' ), $context['source_language'], TAKA_Platform_Translation_Packages::language_labels(), array( 'data-taka-source-language-select' => '1' ) );
		echo '<p class="description">' . esc_html__( 'Select the language used by original text fields. Website translations are edited in the translation section.', 'taka-platform' ) . '</p>';
	}

	private static function language_multiselect( $context, $field, $label ) {
		$current = TAKA_Platform_Data::normalize_language_codes( self::value( $context, $field ) );
		$html = '<select class="widefat" name="_taka_' . esc_attr( $field ) . '[]" multiple size="7">';
		foreach ( TAKA_Platform_Data::language_choices() as $code => $language_label ) {
			$html .= '<option value="' . esc_attr( $code ) . '" ' . selected( in_array( (string) $code, $current, true ), true, false ) . '>' . esc_html( $language_label ) . '</option>';
		}
		$html .= '</select><p class="description">' . esc_html__( 'Select languages used during the event. Hold Command/Ctrl to select multiple languages.', 'taka-platform' ) . '</p>';
		self::field( $label, $html );
	}

	private static function relation_select( $context, $field, $label, $post_type ) {
		$current = absint( self::value( $context, $field ) );
		$html = '<select class="widefat" name="_taka_' . esc_attr( $field ) . '"><option value="">-</option>';
		foreach ( self::posts_with_current( $post_type, $current ) as $post ) {
			$html .= '<option value="' . esc_attr( (string) $post->ID ) . '" ' . selected( $current, (int) $post->ID, false ) . '>' . esc_html( get_the_title( $post ) ) . '</option>';
		}
		$html .= '</select>';
		self::field( $label, $html );
	}

	private static function organizer_relation_select( $context, $field, $label ) {
		$current = absint( self::value( $context, $field ) );
		$args = array(
			'post_type'      => TAKA_PLATFORM_CPT_ORGANIZER,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( ! self::is_platform_admin() ) {
			$assigned = self::current_user_organizer_ids();
			$args['post__in'] = ! empty( $assigned ) ? $assigned : array( 0 );
			if ( 0 === $current && 1 === count( $assigned ) ) {
				$current = (int) $assigned[0];
			}
		}
		$html = '<select class="widefat" name="_taka_' . esc_attr( $field ) . '"><option value="">-</option>';
		foreach ( self::append_current_post( get_posts( $args ), $current, TAKA_PLATFORM_CPT_ORGANIZER ) as $post ) {
			$html .= '<option value="' . esc_attr( (string) $post->ID ) . '" ' . selected( $current, (int) $post->ID, false ) . '>' . esc_html( get_the_title( $post ) ) . '</option>';
		}
		$html .= '</select>';
		self::field( $label, $html );
	}

	private static function derived_country_hint( $context ) {
		$country = (string) self::value( $context, 'country' );
		$code = TAKA_Platform_Data::country_code_for_value( self::value( $context, 'country_code' ) ?: $country );
		$flag = TAKA_Platform_Data::flag_for_country_code( $code );
		$timezone = TAKA_Platform_Data::timezone_for_country( $code );
		$currency = TAKA_Platform_Data::currency_for_country( $code );
		$html = '<code>' . esc_html( $code ?: '-' ) . '</code> ' . esc_html( $flag );
		if ( '' !== $timezone || '' !== $currency ) {
			$html .= '<p class="description">' . esc_html( sprintf( __( 'Suggested timezone: %1$s. Suggested currency: %2$s.', 'taka-platform' ), $timezone ?: '-', $currency ?: '-' ) ) . '</p>';
		}
		self::field( __( 'Derived country data', 'taka-platform' ), $html );
	}

	private static function render_program_items( $context ) {
		$items = self::program_items( $context );
		if ( empty( $items ) ) {
			$items = array( array( 'type' => 'seminar', 'sort_order' => 0 ) );
		}
		$types = array( 'seminar', 'training', 'workshop', 'break', 'lunch', 'grading', 'social', 'dinner', 'travel', 'other' );
		?>
		<div class="taka-program-items" data-taka-program-items>
			<h3><?php echo esc_html__( 'Program', 'taka-platform' ); ?></h3>
			<p class="description"><?php echo esc_html__( 'Add one or more dated program items. Items are grouped by date on the frontend.', 'taka-platform' ); ?></p>
			<div data-taka-program-list>
				<?php foreach ( array_values( $items ) as $index => $item ) : ?>
					<?php self::program_item_row( (string) $index, $item, $types ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button" data-taka-program-add><?php echo esc_html__( 'Add program item', 'taka-platform' ); ?></button>
			<script type="text/template" data-taka-program-template><?php self::program_item_row( '__index__', array( 'type' => 'seminar' ), $types ); ?></script>
		</div>
		<?php
	}

	private static function program_item_row( $index, $item, $types ) {
		$name = 'taka_program_items[' . esc_attr( (string) $index ) . ']';
		?>
		<div class="taka-program-item" data-taka-program-item>
			<p>
				<label><?php echo esc_html__( 'Sort order', 'taka-platform' ); ?> <input type="number" name="<?php echo esc_attr( $name ); ?>[sort_order]" value="<?php echo esc_attr( (string) ( $item['sort_order'] ?? $index ) ); ?>"></label>
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

	private static function render_organizer_relationships( $context, $primary_only = false ) {
		$items = self::organizer_relationships( $context );
		if ( $primary_only ) {
			$items = array_values( array_filter( $items, static function ( $item ) { return 'organizer' === (string) ( $item['relationship_type'] ?? 'organizer' ); } ) );
		} else {
			$items = array_values( array_filter( $items, static function ( $item ) { return 'organizer' !== (string) ( $item['relationship_type'] ?? 'organizer' ); } ) );
		}
		if ( empty( $items ) && $primary_only && absint( self::value( $context, 'organizer_id' ) ) ) {
			$items[] = array( 'organizer_id' => (string) absint( self::value( $context, 'organizer_id' ) ), 'relationship_type' => 'organizer', 'visible' => 1, 'sort_order' => 10 );
		}
		$types = TAKA_Platform_Data::organizer_relationship_type_labels();
		$organizers = self::organizer_posts_for_current_user();
		?>
		<div class="taka-event-organizers" data-taka-event-organizers>
			<h3><?php echo esc_html( $primary_only ? __( 'Visible organizer relationship', 'taka-platform' ) : __( 'Additional organizer relationships', 'taka-platform' ) ); ?></h3>
			<div class="taka-event-organizer-list" data-taka-event-organizer-list>
				<?php foreach ( $items as $index => $item ) : ?>
					<?php self::organizer_relationship_row( (string) $index . ( $primary_only ? '_primary' : '_extra' ), $item, $organizers, $types ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button" data-taka-event-organizer-add><?php echo esc_html__( 'Add organizer', 'taka-platform' ); ?></button>
			<template data-taka-event-organizer-template><?php self::organizer_relationship_row( '__index__', array( 'relationship_type' => $primary_only ? 'organizer' : 'co_organizer', 'visible' => 1, 'sort_order' => 10 ), $organizers, $types ); ?></template>
		</div>
		<?php
	}

	private static function organizer_relationship_row( $index, $item, $organizers, $types ) {
		$index_attr = esc_attr( (string) $index );
		$prefix = 'taka_platform_event_organizers[' . $index_attr . ']';
		?>
		<div class="taka-event-organizer-item" data-taka-event-organizer-item>
			<div class="taka-event-organizer-item__header"><strong><?php echo esc_html__( 'Event organizer', 'taka-platform' ); ?></strong> <button type="button" class="button-link-delete" data-taka-event-organizer-remove><?php echo esc_html__( 'Remove organizer', 'taka-platform' ); ?></button></div>
			<p><label><?php echo esc_html__( 'Organizer', 'taka-platform' ); ?><br><select name="<?php echo esc_attr( $prefix ); ?>[organizer_id]"><option value="">-</option><?php foreach ( $organizers as $organizer ) : ?><option value="<?php echo esc_attr( (string) $organizer->ID ); ?>" <?php selected( (string) ( $item['organizer_id'] ?? '' ), (string) $organizer->ID ); ?>><?php echo esc_html( get_the_title( $organizer ) ); ?></option><?php endforeach; ?></select></label></p>
			<p><label><?php echo esc_html__( 'Relationship', 'taka-platform' ); ?><br><select name="<?php echo esc_attr( $prefix ); ?>[relationship_type]"><?php foreach ( $types as $type => $label ) : ?><option value="<?php echo esc_attr( $type ); ?>" <?php selected( $item['relationship_type'] ?? 'organizer', $type ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label></p>
			<p><label><?php echo esc_html__( 'Custom label', 'taka-platform' ); ?><br><input type="text" class="regular-text" name="<?php echo esc_attr( $prefix ); ?>[custom_label]" value="<?php echo esc_attr( $item['custom_label'] ?? '' ); ?>"></label></p>
			<p><label><?php echo esc_html__( 'Sort order', 'taka-platform' ); ?><br><input type="number" name="<?php echo esc_attr( $prefix ); ?>[sort_order]" value="<?php echo esc_attr( (string) ( $item['sort_order'] ?? 0 ) ); ?>"></label> <label><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[visible]" value="1" <?php checked( ! isset( $item['visible'] ) || ! empty( $item['visible'] ) ); ?>> <?php echo esc_html__( 'Visible', 'taka-platform' ); ?></label></p>
		</div>
		<?php
	}

	private static function content_reference_fields( $context ) {
		$reference = TAKA_Platform_Data::normalize_content_reference( $context['content_reference'] ?? array(), 'event_description' );
		$prefix = '_taka_content_reference_event_description';
		?>
		<div class="taka-content-reference-fields">
			<h3><?php echo esc_html__( 'Reusable seminar description block', 'taka-platform' ); ?></h3>
			<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[context]" value="event_description">
			<?php self::hidden_array_fields( $prefix . '[custom_title]', $reference['custom_title'] ?? array() ); ?>
			<?php self::hidden_array_fields( $prefix . '[override_translations]', $reference['override_translations'] ?? array() ); ?>
			<?php self::select_field( $prefix . '[block_id]', __( 'Content block', 'taka-platform' ), $reference['block_id'] ?? '', self::content_block_choices() ); ?>
			<?php self::select_field( $prefix . '[display_style]', __( 'Display style', 'taka-platform' ), $reference['display_style'] ?? 'default', TAKA_Platform_Data::content_reference_display_styles() ); ?>
			<p><label><strong><?php echo esc_html__( 'Sort order', 'taka-platform' ); ?></strong><br><input type="number" name="<?php echo esc_attr( $prefix ); ?>[sort_order]" value="<?php echo esc_attr( (string) ( $reference['sort_order'] ?? 0 ) ); ?>"></label></p>
			<p class="description"><?php echo esc_html__( 'Select a reusable block to override the local description on the public event page.', 'taka-platform' ); ?></p>
		</div>
		<?php
	}

	private static function translation_textareas( $context, $fields, $description_only = false, $advanced_fields = array() ) {
		$source_language = (string) $context['source_language'];
		$languages = TAKA_Platform_Translation_Packages::language_labels();
		$translations = (array) $context['translations'];
		$advanced = array_fill_keys( (array) $advanced_fields, true );
		$tab_group = 'taka_event_assistant_text_' . ( $context['post_id'] ?: 'new' ) . ( $description_only ? '_description' : '_translations' );
		?>
		<div class="taka-content-section-translations" data-taka-content-section-translations data-taka-source-aware data-source-language="<?php echo esc_attr( $source_language ); ?>" data-source-mode="editable" data-default-lang="<?php echo esc_attr( $source_language ); ?>">
			<div class="taka-content-section-tabs">
				<?php foreach ( $languages as $lang => $label ) : ?>
					<?php $is_source_language = $lang === $source_language; ?>
					<input class="taka-content-section-tabs__radio" type="radio" name="<?php echo esc_attr( $tab_group ); ?>" id="<?php echo esc_attr( $tab_group . '_' . $lang ); ?>" <?php checked( $lang, $source_language ); ?>>
					<label class="taka-content-section-tabs__tab" for="<?php echo esc_attr( $tab_group . '_' . $lang ); ?>" data-taka-language-tab data-taka-i18n-lang="<?php echo esc_attr( $lang ); ?>" data-language-label="<?php echo esc_attr( $label ); ?>"><?php echo esc_html( $is_source_language ? sprintf( __( '%s original', 'taka-platform' ), $label ) : $label ); ?></label>
					<div class="taka-content-section-tabs__panel" data-taka-language-panel data-taka-i18n-lang="<?php echo esc_attr( $lang ); ?>">
						<p class="description" data-taka-source-panel-help><?php echo esc_html( $is_source_language ? __( 'This is the original content language. Edit the original text here.', 'taka-platform' ) : __( 'Enter the website translation for this language based on the original text.', 'taka-platform' ) ); ?></p>
						<?php foreach ( $fields as $field => $field_label ) : ?>
							<?php if ( ! $description_only && isset( $advanced[ $field ] ) ) { continue; } ?>
							<?php self::translation_textarea( $context, $field, $field_label, $lang, $is_source_language ); ?>
						<?php endforeach; ?>
						<?php if ( ! $description_only && ! empty( $advanced_fields ) ) : ?>
							<?php TAKA_Platform_Admin_Collapsible_Section::open( array( 'id' => 'event-assistant-advanced-translations-' . $lang, 'title' => __( 'Advanced website translations', 'taka-platform' ), 'help_text' => __( 'Compatibility translation fields retained for older frontend layouts and integrations.', 'taka-platform' ), 'default_state' => TAKA_Platform_Admin_Collapsible_Section::STATE_COLLAPSED, 'class' => 'taka-admin-section--nested' ) ); ?>
								<?php foreach ( $advanced_fields as $field ) : ?>
									<?php if ( isset( $fields[ $field ] ) ) : ?>
										<?php self::translation_textarea( $context, $field, $fields[ $field ], $lang, $is_source_language ); ?>
									<?php endif; ?>
								<?php endforeach; ?>
							<?php TAKA_Platform_Admin_Collapsible_Section::close(); ?>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private static function translation_textarea( $context, $field, $field_label, $lang, $is_source_language ) {
		$source_values = array(
			'description' => (string) ( self::value( $context, 'short_description' ) ?: $context['post_content'] ),
		);
		$placeholder = $source_values[ $field ] ?? self::value( $context, 'description' === $field ? 'short_description' : $field );
		$value = $is_source_language ? self::source_text_value( $context, $field ) : (string) ( $context['translations'][ $field ][ $lang ] ?? '' );
		$name = 'taka_platform_text_translations[' . $field . '][' . $lang . ']';
		?>
		<p class="taka-content-section-tabs__field">
			<label><strong data-taka-language-field-label data-source-label="<?php echo esc_attr( sprintf( __( '%s - Original text', 'taka-platform' ), $field_label ) ); ?>" data-translation-label="<?php echo esc_attr( sprintf( __( '%s - Website translation', 'taka-platform' ), $field_label ) ); ?>"><?php echo esc_html( $is_source_language ? sprintf( __( '%s - Original text', 'taka-platform' ), $field_label ) : sprintf( __( '%s - Website translation', 'taka-platform' ), $field_label ) ); ?></strong><br>
				<?php if ( $is_source_language ) : ?>
					<input type="hidden" name="taka_platform_text_source_previous[<?php echo esc_attr( $field ); ?>][<?php echo esc_attr( $lang ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>">
				<?php endif; ?>
				<textarea class="large-text" rows="3" name="<?php echo esc_attr( $name ); ?>" placeholder="<?php echo esc_attr( (string) $placeholder ); ?>" data-taka-i18n-lang="<?php echo esc_attr( $lang ); ?>"><?php echo esc_textarea( (string) $value ); ?></textarea>
			</label>
		</p>
		<?php
	}

	private static function media_field( $context, $field, $label, $multiple = false, $button_label = null ) {
		$value = (string) self::value( $context, $field );
		$input_id = 'taka_event_assistant_' . $field . '_' . ( $context['post_id'] ?: 'new' );
		$button_label = $button_label ?: __( 'Select image', 'taka-platform' );
		$html = '<input id="' . esc_attr( $input_id ) . '" type="hidden" name="_taka_' . esc_attr( $field ) . '" value="' . esc_attr( $value ) . '"> ';
		$html .= '<button type="button" class="button" data-taka-media-pick data-multiple="' . ( $multiple ? '1' : '0' ) . '" data-target="' . esc_attr( $input_id ) . '" data-preview="' . esc_attr( $input_id . '_preview' ) . '">' . esc_html( $button_label ) . '</button> ';
		$html .= '<button type="button" class="button" data-taka-media-remove data-target="' . esc_attr( $input_id ) . '" data-preview="' . esc_attr( $input_id . '_preview' ) . '">' . esc_html__( 'Remove image', 'taka-platform' ) . '</button>';
		$html .= '<div id="' . esc_attr( $input_id . '_preview' ) . '">';
		ob_start();
		self::image_previews( $value );
		$html .= ob_get_clean() . '</div>';
		self::field( $label, $html );
	}

	private static function render_event_video_fields( $context ) {
		$items = self::event_videos( $context );
		?>
		<div class="taka-event-videos-admin" data-taka-event-videos>
			<h3><?php echo esc_html__( 'Promo videos', 'taka-platform' ); ?></h3>
			<div class="taka-event-video-list" data-taka-event-video-list>
				<?php foreach ( array_values( $items ) as $index => $item ) : ?>
					<?php self::event_video_row( $context, (string) $index, $item ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button" data-taka-event-video-add><?php echo esc_html__( 'Add video', 'taka-platform' ); ?></button>
			<template data-taka-event-video-template><?php self::event_video_row( $context, '__index__', array() ); ?></template>
		</div>
		<?php
	}

	private static function event_video_row( $context, $index, $item ) {
		$index_attr = esc_attr( (string) $index );
		$index_key = sanitize_key( (string) $index );
		$name = 'taka_platform_event_videos[' . $index_attr . ']';
		$video_input_id = 'taka_event_assistant_video_' . ( $context['post_id'] ?: 'new' ) . '_' . $index_key . '_attachment_id';
		$thumbnail_input_id = 'taka_event_assistant_video_' . ( $context['post_id'] ?: 'new' ) . '_' . $index_key . '_thumbnail_id';
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
			<p><label><?php echo esc_html__( 'Sort order', 'taka-platform' ); ?><br><input type="number" name="<?php echo esc_attr( $name ); ?>[sort_order]" value="<?php echo esc_attr( (string) ( $item['sort_order'] ?? $index ) ); ?>"></label></p>
		</div>
		<?php
	}

	private static function content_block_choices() {
		$choices = array( '' => __( '- No reusable block -', 'taka-platform' ) );
		foreach ( TAKA_Platform_Data::get_content_blocks( false ) as $id => $block ) {
			if ( (string) ( $block['id'] ?? '' ) !== (string) $id ) {
				continue;
			}
			$label = trim( (string) ( $block['internal_name'] ?? '' ) );
			$slug = trim( (string) ( $block['slug'] ?? '' ) );
			$value = '' !== $slug ? $slug : (string) $id;
			if ( '' === $label ) {
				$label = '' !== $slug ? $slug : (string) $id;
			}
			$choices[ $value ] = $label . ( '' !== $slug ? ' (' . $slug . ')' : '' );
		}
		return $choices;
	}

	private static function hidden_array_fields( $prefix, $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				self::hidden_array_fields( $prefix . '[' . sanitize_key( (string) $key ) . ']', $item );
			}
			return;
		}
		echo '<input type="hidden" name="' . esc_attr( $prefix ) . '" value="' . esc_attr( (string) $value ) . '">';
	}

	private static function multi_post_select( $name, $label, $current, $items, $type ) {
		$current = array_map( 'absint', (array) $current );
		$html = '<select class="widefat" name="' . esc_attr( $name ) . '" multiple size="6">';
		foreach ( $items as $item ) {
			$id = 'user' === $type ? (int) $item->ID : (int) $item->ID;
			$text = 'user' === $type ? ( $item->display_name ?: $item->user_login ) : get_the_title( $item );
			$html .= '<option value="' . esc_attr( (string) $id ) . '" ' . selected( in_array( $id, $current, true ), true, false ) . '>' . esc_html( $text ) . '</option>';
		}
		$html .= '</select>';
		self::field( $label, $html );
	}

	private static function posts( $post_type, $status = 'publish' ) {
		return get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => $status,
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	private static function posts_with_current( $post_type, $current ) {
		return self::append_current_post( self::posts( $post_type ), $current, $post_type );
	}

	private static function append_current_post( $posts, $current, $post_type ) {
		$current = absint( $current );
		if ( ! $current ) {
			return $posts;
		}
		foreach ( $posts as $post ) {
			if ( (int) $post->ID === $current ) {
				return $posts;
			}
		}
		$current_post = get_post( $current );
		if ( $current_post && $post_type === $current_post->post_type ) {
			$posts[] = $current_post;
		}
		return $posts;
	}

	private static function organizer_posts_for_current_user() {
		$args = array(
			'post_type'      => TAKA_PLATFORM_CPT_ORGANIZER,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( ! self::is_platform_admin() ) {
			$assigned = self::current_user_organizer_ids();
			$args['post__in'] = ! empty( $assigned ) ? $assigned : array( 0 );
		}
		return get_posts( $args );
	}

	private static function current_user_organizer_ids() {
		$ids = get_user_meta( get_current_user_id(), '_taka_platform_organizer_ids', true );
		if ( ! is_array( $ids ) ) {
			$ids = array_filter( preg_split( '/\s*,\s*/', (string) $ids ) );
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	private static function is_platform_admin() {
		return current_user_can( 'manage_options' );
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

	private static function id_list_meta( $post_id, $key ) {
		$ids = get_post_meta( $post_id, $key, true );
		if ( ! is_array( $ids ) ) {
			$ids = array_filter( preg_split( '/\s*,\s*/', (string) $ids ) );
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	private static function user_label( $user_id ) {
		$user = $user_id ? get_user_by( 'id', $user_id ) : null;
		return $user ? ( $user->display_name ?: $user->user_login ) : __( 'Unknown user', 'taka-platform' );
	}

	private static function image_preview( $id, $fallback_url = '' ) {
		$url = $id && function_exists( 'wp_get_attachment_image_url' ) ? wp_get_attachment_image_url( $id, 'thumbnail' ) : $fallback_url;
		if ( $url ) {
			echo '<img src="' . esc_url( $url ) . '" style="max-width:180px;height:auto;display:block;margin-top:8px;" alt="">';
		}
	}

	private static function image_previews( $ids ) {
		foreach ( array_filter( array_map( 'absint', preg_split( '/\s*,\s*/', (string) $ids ) ) ) as $id ) {
			self::image_preview( $id );
		}
	}

	private static function video_preview( $id, $fallback_url = '' ) {
		$url = $id && function_exists( 'wp_get_attachment_url' ) ? wp_get_attachment_url( $id ) : $fallback_url;
		if ( $url ) {
			$label = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
			echo '<span class="taka-admin-media-preview taka-admin-media-preview--video"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ?: __( 'Selected video', 'taka-platform' ) ) . '</a></span>';
		}
	}

	private static function status_label( $status ) {
		$labels = array(
			'complete'   => __( 'Complete', 'taka-platform' ),
			'incomplete' => __( 'Incomplete', 'taka-platform' ),
			'optional'   => __( 'Optional', 'taka-platform' ),
			'warning'    => __( 'Warning', 'taka-platform' ),
		);
		return $labels[ $status ] ?? $labels['complete'];
	}

	private static function status_mark( $status ) {
		if ( 'complete' === $status ) {
			return 'OK';
		}
		if ( 'incomplete' === $status ) {
			return '!';
		}
		return 'o';
	}

	private static function section_anchor_id( $section_id ) {
		return 'taka-event-assistant-section-' . sanitize_key( $section_id );
	}

	private static function html_attrs( $attributes ) {
		$html = '';
		foreach ( (array) $attributes as $name => $value ) {
			if ( false === $value || null === $value ) {
				continue;
			}
			$html .= ' ' . sanitize_key( $name );
			if ( true !== $value ) {
				$html .= '="' . esc_attr( (string) $value ) . '"';
			}
		}
		return $html;
	}
}

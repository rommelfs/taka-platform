<?php
/**
 * Private Communication Center for targeted TAKA Platform messages.
 *
 * The module stores reusable templates, campaigns and delivery history in
 * private post types. Keep recipient selection in the resolver methods below
 * so future channels and automations reuse the same targeting rules.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Communication_Module {
	const ADMIN_PAGE_SLUG = 'taka-platform-communication';
	const TEMPLATE_META   = '_taka_communication_template';
	const CAMPAIGN_META   = '_taka_communication_campaign';
	const MESSAGE_META    = '_taka_communication_message';

	const TEMPLATE_ACTION = 'taka_communication_save_template';
	const CAMPAIGN_ACTION = 'taka_communication_save_campaign';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ), 0 );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ), 22 );
		add_action( 'admin_init', array( __CLASS__, 'ensure_capabilities' ) );
		add_action( 'admin_post_' . self::TEMPLATE_ACTION, array( __CLASS__, 'handle_save_template' ) );
		add_action( 'admin_post_' . self::CAMPAIGN_ACTION, array( __CLASS__, 'handle_save_campaign' ) );
	}

	public static function register_post_types() {
		register_post_type(
			TAKA_PLATFORM_CPT_COMMUNICATION_TEMPLATE,
			array(
				'labels'              => array(
					'name'          => __( 'Communication Templates', 'taka-platform' ),
					'singular_name' => __( 'Communication Template', 'taka-platform' ),
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
			TAKA_PLATFORM_CPT_COMMUNICATION_CAMPAIGN,
			array(
				'labels'              => array(
					'name'          => __( 'Communication Campaigns', 'taka-platform' ),
					'singular_name' => __( 'Communication Campaign', 'taka-platform' ),
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
			TAKA_PLATFORM_CPT_COMMUNICATION_MESSAGE,
			array(
				'labels'              => array(
					'name'          => __( 'Outgoing Messages', 'taka-platform' ),
					'singular_name' => __( 'Outgoing Message', 'taka-platform' ),
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
			__( 'Communication', 'taka-platform' ),
			__( 'Communication', 'taka-platform' ),
			'view_taka_communication',
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
			'view_taka_communication',
			'manage_taka_communication',
			'send_taka_communication',
		);
	}

	public static function admin_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::ADMIN_PAGE_SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	public static function handle_save_template() {
		if ( ! current_user_can( 'manage_taka_communication' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::TEMPLATE_ACTION, '_wpnonce' );

		$raw = isset( $_POST['template'] ) && is_array( $_POST['template'] ) ? wp_unslash( $_POST['template'] ) : array();
		$data = self::normalize_template( $raw );
		$result = self::save_private_post( TAKA_PLATFORM_CPT_COMMUNICATION_TEMPLATE, self::TEMPLATE_META, $data, __( 'Communication template', 'taka-platform' ) );

		$args = array( 'section' => 'templates' );
		if ( is_wp_error( $result ) ) {
			$args['communication_error'] = $result->get_error_message();
		} else {
			$args['communication_saved'] = 'template';
		}
		wp_safe_redirect( self::admin_url( $args ) );
		exit;
	}

	public static function handle_save_campaign() {
		if ( ! current_user_can( 'manage_taka_communication' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::CAMPAIGN_ACTION, '_wpnonce' );

		$task = sanitize_key( wp_unslash( $_POST['task'] ?? 'draft' ) );
		if ( in_array( $task, array( 'send_now', 'schedule' ), true ) && ! current_user_can( 'send_taka_communication' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}

		$raw = isset( $_POST['campaign'] ) && is_array( $_POST['campaign'] ) ? wp_unslash( $_POST['campaign'] ) : array();
		$filters = isset( $_POST['filters'] ) && is_array( $_POST['filters'] ) ? wp_unslash( $_POST['filters'] ) : array();
		$data = self::normalize_campaign( array_merge( $raw, array( 'filters' => $filters ) ) );
		$recipients = self::resolve_recipients( $data['filters'] );
		$data['recipient_count'] = count( $recipients );

		if ( 'schedule' === $task ) {
			$data['status'] = 'scheduled';
			if ( '' === $data['scheduled_at'] ) {
				$data['status'] = 'draft';
				$data['warnings'][] = __( 'Scheduled campaigns need a send date and time.', 'taka-platform' );
			}
		} elseif ( 'send_now' === $task ) {
			$data['status'] = 'sending';
		} else {
			$data['status'] = 'draft';
		}

		$result = self::save_private_post( TAKA_PLATFORM_CPT_COMMUNICATION_CAMPAIGN, self::CAMPAIGN_META, $data, __( 'Communication campaign', 'taka-platform' ) );
		$args = array( 'section' => 'campaigns' );
		if ( is_wp_error( $result ) ) {
			$args['communication_error'] = $result->get_error_message();
			wp_safe_redirect( self::admin_url( $args ) );
			exit;
		}

		$campaign_id = absint( $result['id'] );
		if ( 'send_now' === $task ) {
			$sent = self::send_campaign( $campaign_id, $data, $recipients );
			$args['communication_sent'] = (string) absint( $sent['sent'] );
			$args['communication_failed'] = (string) absint( $sent['failed'] );
			$args['section'] = 'history';
		} else {
			$args['communication_saved'] = 'campaign';
		}

		wp_safe_redirect( self::admin_url( $args ) );
		exit;
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'view_taka_communication' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}

		$section = sanitize_key( $_GET['section'] ?? 'campaigns' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $section, array( 'campaigns', 'templates', 'outgoing', 'history' ), true ) ) {
			$section = 'campaigns';
		}

		?>
		<div class="wrap taka-communication-admin">
			<h1><?php esc_html_e( 'Communication Center', 'taka-platform' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Send targeted event and tour emails without exporting participant lists.', 'taka-platform' ); ?></p>
			<?php self::render_notices(); ?>
			<nav class="nav-tab-wrapper taka-admin-tabs">
				<?php foreach ( self::tabs() as $key => $label ) : ?>
					<a class="nav-tab <?php echo $section === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( self::admin_url( array( 'section' => $key ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php
			if ( 'templates' === $section ) {
				self::render_templates_section();
			} elseif ( 'outgoing' === $section ) {
				self::render_outgoing_section();
			} elseif ( 'history' === $section ) {
				self::render_history_section();
			} else {
				self::render_campaigns_section();
			}
			?>
		</div>
		<?php
	}

	private static function tabs() {
		return array(
			'campaigns' => __( 'Campaigns', 'taka-platform' ),
			'templates' => __( 'Templates', 'taka-platform' ),
			'outgoing'  => __( 'Outgoing Messages', 'taka-platform' ),
			'history'   => __( 'History', 'taka-platform' ),
		);
	}

	private static function render_notices() {
		if ( ! empty( $_GET['communication_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-error"><p>' . esc_html( wp_unslash( $_GET['communication_error'] ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! empty( $_GET['communication_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Communication item saved.', 'taka-platform' ) . '</p></div>';
		}
		if ( isset( $_GET['communication_sent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$sent = absint( $_GET['communication_sent'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$failed = absint( $_GET['communication_failed'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( '%1$d message(s) sent. %2$d failed.', 'taka-platform' ), $sent, $failed ) ) . '</p></div>';
		}
	}

	private static function render_campaigns_section() {
		$templates = self::get_templates();
		$campaigns = self::get_campaigns( array( 'per_page' => 20 ) );
		$filters = self::filters_from_request();
		$recipients = self::resolve_recipients( $filters );
		$first = ! empty( $recipients ) ? reset( $recipients ) : self::sample_recipient();
		$preview_subject = self::render_variables( __( 'Seminar information for {{EventName}}', 'taka-platform' ), $first );
		$preview_body = self::render_variables( __( 'Hello {{FirstName}}, this is a preview for {{EventName}} at {{Venue}}.', 'taka-platform' ), $first );
		?>
		<div class="taka-admin-grid taka-admin-grid--two">
			<section class="taka-admin-panel">
				<h2><?php esc_html_e( 'Create campaign', 'taka-platform' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::CAMPAIGN_ACTION ); ?>">
					<?php wp_nonce_field( self::CAMPAIGN_ACTION, '_wpnonce' ); ?>
					<div class="taka-admin-field-grid">
						<label>
							<span><?php esc_html_e( 'Campaign title', 'taka-platform' ); ?></span>
							<input type="text" name="campaign[title]" class="regular-text" required>
						</label>
						<label>
							<span><?php esc_html_e( 'Template', 'taka-platform' ); ?></span>
							<select name="campaign[template_id]">
								<option value="0"><?php esc_html_e( 'No template / custom email', 'taka-platform' ); ?></option>
								<?php foreach ( $templates as $template ) : ?>
									<option value="<?php echo esc_attr( $template['id'] ); ?>"><?php echo esc_html( $template['title'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="taka-admin-field-grid__wide">
							<span><?php esc_html_e( 'Email subject', 'taka-platform' ); ?></span>
							<input type="text" name="campaign[subject]" class="large-text" value="<?php echo esc_attr__( 'Seminar information for {{EventName}}', 'taka-platform' ); ?>" required>
						</label>
						<label class="taka-admin-field-grid__wide">
							<span><?php esc_html_e( 'Email body', 'taka-platform' ); ?></span>
							<textarea name="campaign[body]" class="large-text" rows="8" required><?php echo esc_textarea( __( "Hello {{FirstName}},\n\nthis is a message about {{EventName}}.\n\nBest regards", 'taka-platform' ) ); ?></textarea>
						</label>
						<label>
							<span><?php esc_html_e( 'Schedule for later', 'taka-platform' ); ?></span>
							<input type="datetime-local" name="campaign[scheduled_at]">
						</label>
					</div>
					<?php self::render_recipient_filters( $filters ); ?>
					<div class="taka-communication-preview">
						<h3><?php esc_html_e( 'Preview', 'taka-platform' ); ?></h3>
						<p><strong><?php esc_html_e( 'Recipient count:', 'taka-platform' ); ?></strong> <?php echo esc_html( number_format_i18n( count( $recipients ) ) ); ?></p>
						<p><strong><?php esc_html_e( 'Subject:', 'taka-platform' ); ?></strong> <?php echo esc_html( $preview_subject ); ?></p>
						<pre><?php echo esc_html( $preview_body ); ?></pre>
					</div>
					<p class="submit taka-admin-actions">
						<button class="button" type="submit" name="task" value="draft"><?php esc_html_e( 'Save Draft', 'taka-platform' ); ?></button>
						<button class="button" type="submit" name="task" value="schedule"><?php esc_html_e( 'Schedule later', 'taka-platform' ); ?></button>
						<button class="button button-primary" type="submit" name="task" value="send_now"><?php esc_html_e( 'Send now', 'taka-platform' ); ?></button>
					</p>
				</form>
			</section>
			<section class="taka-admin-panel">
				<h2><?php esc_html_e( 'Recent campaigns', 'taka-platform' ); ?></h2>
				<?php self::render_campaign_table( $campaigns ); ?>
			</section>
		</div>
		<?php
	}

	private static function render_templates_section() {
		$templates = self::get_templates();
		?>
		<div class="taka-admin-grid taka-admin-grid--two">
			<section class="taka-admin-panel">
				<h2><?php esc_html_e( 'Reusable email template', 'taka-platform' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::TEMPLATE_ACTION ); ?>">
					<?php wp_nonce_field( self::TEMPLATE_ACTION, '_wpnonce' ); ?>
					<div class="taka-admin-field-grid">
						<label>
							<span><?php esc_html_e( 'Template title', 'taka-platform' ); ?></span>
							<input type="text" name="template[title]" class="regular-text" required>
						</label>
						<label>
							<span><?php esc_html_e( 'Template key', 'taka-platform' ); ?></span>
							<input type="text" name="template[key]" class="regular-text" placeholder="payment-reminder">
						</label>
						<label>
							<span><?php esc_html_e( 'Type', 'taka-platform' ); ?></span>
							<select name="template[type]">
								<?php foreach ( self::template_types() as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="taka-admin-field-grid__wide">
							<span><?php esc_html_e( 'Subject', 'taka-platform' ); ?></span>
							<input type="text" name="template[subject]" class="large-text" required>
						</label>
						<label class="taka-admin-field-grid__wide">
							<span><?php esc_html_e( 'Body', 'taka-platform' ); ?></span>
							<textarea name="template[body]" class="large-text" rows="10" required></textarea>
						</label>
						<label class="taka-admin-field-grid__wide">
							<span><?php esc_html_e( 'Internal description', 'taka-platform' ); ?></span>
							<textarea name="template[description]" class="large-text" rows="3"></textarea>
						</label>
					</div>
					<p class="description"><?php esc_html_e( 'Available variables include {{FirstName}}, {{LastName}}, {{EventName}}, {{Venue}}, {{Organizer}}, {{PaymentStatus}}, {{OrderNumber}}, {{TicketType}} and {{QRCode}}.', 'taka-platform' ); ?></p>
					<p class="submit"><button class="button button-primary" type="submit"><?php esc_html_e( 'Save template', 'taka-platform' ); ?></button></p>
				</form>
			</section>
			<section class="taka-admin-panel">
				<h2><?php esc_html_e( 'Templates', 'taka-platform' ); ?></h2>
				<?php self::render_template_table( $templates ); ?>
			</section>
		</div>
		<?php
	}

	private static function render_outgoing_section() {
		$campaigns = self::get_campaigns( array( 'status' => array( 'draft', 'scheduled' ), 'per_page' => 50 ) );
		?>
		<section class="taka-admin-panel">
			<h2><?php esc_html_e( 'Drafts and scheduled messages', 'taka-platform' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Scheduled campaigns are stored here for the upcoming automation worker. Phase 6 keeps sending advisory and manual.', 'taka-platform' ); ?></p>
			<?php self::render_campaign_table( $campaigns ); ?>
		</section>
		<?php
	}

	private static function render_history_section() {
		$messages = self::get_messages( array( 'per_page' => 100 ) );
		?>
		<section class="taka-admin-panel">
			<h2><?php esc_html_e( 'Delivery history', 'taka-platform' ); ?></h2>
			<?php if ( empty( $messages ) ) : ?>
				<p><?php esc_html_e( 'No outgoing messages have been recorded yet.', 'taka-platform' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Sent', 'taka-platform' ); ?></th>
							<th><?php esc_html_e( 'Recipient', 'taka-platform' ); ?></th>
							<th><?php esc_html_e( 'Subject', 'taka-platform' ); ?></th>
							<th><?php esc_html_e( 'Status', 'taka-platform' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $messages as $message ) : ?>
							<tr>
								<td><?php echo esc_html( $message['sent_at'] ?: $message['created_at'] ); ?></td>
								<td><?php echo esc_html( trim( $message['recipient_name'] . ' <' . $message['recipient_email'] . '>' ) ); ?></td>
								<td><?php echo esc_html( $message['subject'] ); ?></td>
								<td><?php echo esc_html( self::message_status_label( $message['status'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function render_recipient_filters( $filters ) {
		$events = get_posts(
			array(
				'post_type'        => TAKA_PLATFORM_CPT_EVENT,
				'post_status'      => array( 'publish', 'draft', 'future', 'private' ),
				'posts_per_page'   => 250,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);
		?>
		<div class="taka-communication-filters">
			<h3><?php esc_html_e( 'Recipients', 'taka-platform' ); ?></h3>
			<div class="taka-admin-field-grid">
				<label>
					<span><?php esc_html_e( 'Audience', 'taka-platform' ); ?></span>
					<select name="filters[recipient_group]">
						<?php foreach ( self::recipient_groups() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['recipient_group'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Tour key', 'taka-platform' ); ?></span>
					<input type="text" name="filters[tour_key]" value="<?php echo esc_attr( $filters['tour_key'] ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Specific country', 'taka-platform' ); ?></span>
					<input type="text" name="filters[country]" value="<?php echo esc_attr( $filters['country'] ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Dojo / club', 'taka-platform' ); ?></span>
					<input type="text" name="filters[dojo]" value="<?php echo esc_attr( $filters['dojo'] ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Rank / belt', 'taka-platform' ); ?></span>
					<input type="text" name="filters[rank]" value="<?php echo esc_attr( $filters['rank'] ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Ticket type ID', 'taka-platform' ); ?></span>
					<input type="text" name="filters[ticket_type_id]" value="<?php echo esc_attr( $filters['ticket_type_id'] ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Product ID', 'taka-platform' ); ?></span>
					<input type="text" name="filters[product_id]" value="<?php echo esc_attr( $filters['product_id'] ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Dietary preference', 'taka-platform' ); ?></span>
					<select name="filters[dietary_preference]">
						<option value=""><?php esc_html_e( 'Any', 'taka-platform' ); ?></option>
						<?php foreach ( TAKA_People_Person::dietary_choices() as $choice ) : ?>
							<option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $filters['dietary_preference'], $choice ); ?>><?php echo esc_html( ucfirst( str_replace( '_', ' ', $choice ) ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Voucher / promotion code', 'taka-platform' ); ?></span>
					<input type="text" name="filters[voucher_code]" value="<?php echo esc_attr( $filters['voucher_code'] ); ?>">
				</label>
				<label class="taka-admin-checkbox">
					<input type="checkbox" name="filters[allergy_flag]" value="1" <?php checked( $filters['allergy_flag'], '1' ); ?>>
					<span><?php esc_html_e( 'Only people with allergy notes', 'taka-platform' ); ?></span>
				</label>
				<label class="taka-admin-field-grid__wide">
					<span><?php esc_html_e( 'Selected events', 'taka-platform' ); ?></span>
					<select name="filters[event_ids][]" multiple size="6">
						<?php foreach ( $events as $event ) : ?>
							<option value="<?php echo esc_attr( $event->ID ); ?>" <?php selected( in_array( (int) $event->ID, $filters['event_ids'], true ) ); ?>><?php echo esc_html( get_the_title( $event ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
		</div>
		<?php
	}

	private static function render_template_table( $templates ) {
		if ( empty( $templates ) ) {
			echo '<p>' . esc_html__( 'No templates have been created yet.', 'taka-platform' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Title', 'taka-platform' ); ?></th>
					<th><?php esc_html_e( 'Type', 'taka-platform' ); ?></th>
					<th><?php esc_html_e( 'Subject', 'taka-platform' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $templates as $template ) : ?>
					<tr>
						<td><?php echo esc_html( $template['title'] ); ?></td>
						<td><?php echo esc_html( self::template_types()[ $template['type'] ] ?? $template['type'] ); ?></td>
						<td><?php echo esc_html( $template['subject'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private static function render_campaign_table( $campaigns ) {
		if ( empty( $campaigns ) ) {
			echo '<p>' . esc_html__( 'No campaigns found.', 'taka-platform' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Title', 'taka-platform' ); ?></th>
					<th><?php esc_html_e( 'Status', 'taka-platform' ); ?></th>
					<th><?php esc_html_e( 'Recipients', 'taka-platform' ); ?></th>
					<th><?php esc_html_e( 'Scheduled', 'taka-platform' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $campaigns as $campaign ) : ?>
					<tr>
						<td><?php echo esc_html( $campaign['title'] ); ?></td>
						<td><?php echo esc_html( self::campaign_status_label( $campaign['status'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( absint( $campaign['recipient_count'] ) ) ); ?></td>
						<td><?php echo esc_html( $campaign['scheduled_at'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	public static function resolve_recipients( $filters ) {
		$filters = self::normalize_filters( $filters );
		if ( ! class_exists( 'TAKA_People_Module' ) ) {
			return array();
		}

		if ( 'all_people' === $filters['recipient_group'] ) {
			return self::recipients_from_people( TAKA_People_Module::person_repository()->query( array( 'per_page' => -1 ) ), $filters );
		}

		$registrations = TAKA_People_Module::registration_repository()->query( array( 'per_page' => -1 ) );
		$recipients = array();
		foreach ( $registrations as $registration ) {
			$person = TAKA_People_Module::person_repository()->find_by_id( $registration['person_id'] ?? 0 );
			if ( ! $person || ! self::person_matches_filters( $person, $filters ) || ! self::registration_matches_filters( $registration, $filters ) ) {
				continue;
			}
			$order = self::order_for_registration( $registration );
			if ( ! self::order_matches_filters( $order, $registration, $filters ) ) {
				continue;
			}
			$email = sanitize_email( $person['email'] ?? '' );
			if ( '' === $email ) {
				continue;
			}
			$recipients[ strtolower( $email ) ] = self::build_recipient( $person, $registration, $order );
		}
		return array_values( $recipients );
	}

	private static function recipients_from_people( $people, $filters ) {
		$recipients = array();
		foreach ( (array) $people as $person ) {
			if ( ! self::person_matches_filters( $person, $filters ) ) {
				continue;
			}
			$email = sanitize_email( $person['email'] ?? '' );
			if ( '' === $email ) {
				continue;
			}
			$recipients[ strtolower( $email ) ] = self::build_recipient( $person, array(), null );
		}
		return array_values( $recipients );
	}

	private static function build_recipient( $person, $registration, $order ) {
		$order_data = $order instanceof TAKA_Ticketing_Order ? $order->to_array() : array();
		$event_id = absint( $registration['event_id'] ?? $order_data['event_id'] ?? 0 );
		$event_title = (string) ( $registration['event_title'] ?? $order_data['event_title'] ?? '' );
		if ( '' === $event_title && $event_id ) {
			$event_title = get_the_title( $event_id );
		}
		$venue = self::event_venue_label( $event_id );
		$organizer = self::event_organizer_label( $event_id );
		$name = TAKA_People_Person::full_name( $person );

		return array(
			'email'        => sanitize_email( $person['email'] ?? '' ),
			'name'         => $name,
			'person'       => $person,
			'registration' => $registration,
			'order'        => $order_data,
			'variables'    => array(
				'FirstName'     => (string) ( $person['first_name'] ?? '' ),
				'LastName'      => (string) ( $person['last_name'] ?? '' ),
				'EventName'     => $event_title,
				'Venue'         => $venue,
				'Organizer'     => $organizer,
				'PaymentStatus' => self::payment_status_label( $registration['payment_status'] ?? $order_data['payment_status'] ?? '' ),
				'OrderNumber'   => (string) ( $registration['order_number'] ?? $order_data['order_number'] ?? '' ),
				'TicketType'    => (string) ( $registration['ticket_type_name'] ?? $order_data['ticket_type_name'] ?? '' ),
				'QRCode'        => self::qr_payload_for_registration( $registration ),
			),
		);
	}

	private static function person_matches_filters( $person, $filters ) {
		if ( '' !== $filters['country'] && strtolower( $filters['country'] ) !== strtolower( (string) ( $person['country'] ?? '' ) ) ) {
			return false;
		}
		if ( '' !== $filters['dojo'] && false === strpos( strtolower( (string) ( $person['dojo'] ?? '' ) ), strtolower( $filters['dojo'] ) ) ) {
			return false;
		}
		if ( '' !== $filters['rank'] && false === strpos( strtolower( (string) ( $person['rank'] ?? '' ) ), strtolower( $filters['rank'] ) ) ) {
			return false;
		}
		if ( '' !== $filters['dietary_preference'] && $filters['dietary_preference'] !== (string) ( $person['dietary_preference'] ?? '' ) ) {
			return false;
		}
		if ( '1' === $filters['allergy_flag'] && '' === trim( (string) ( $person['allergies'] ?? '' ) ) ) {
			return false;
		}

		$tag_group = self::tag_group_for_recipient_group( $filters['recipient_group'] );
		if ( '' !== $tag_group ) {
			$tags = array_map( 'strtolower', (array) ( $person['tags'] ?? array() ) );
			return in_array( strtolower( $tag_group ), $tags, true );
		}
		return true;
	}

	private static function registration_matches_filters( $registration, $filters ) {
		if ( ! empty( $filters['event_ids'] ) && ! in_array( absint( $registration['event_id'] ?? 0 ), $filters['event_ids'], true ) ) {
			return false;
		}
		if ( '' !== $filters['tour_key'] && strtolower( $filters['tour_key'] ) !== strtolower( self::event_tour_key( absint( $registration['event_id'] ?? 0 ) ) ) ) {
			return false;
		}
		if ( '' !== $filters['ticket_type_id'] && $filters['ticket_type_id'] !== (string) ( $registration['ticket_type_id'] ?? '' ) ) {
			return false;
		}
		if ( ! self::recipient_group_matches_registration( $filters['recipient_group'], $registration ) ) {
			return false;
		}
		if ( '' !== $filters['product_id'] && ! self::registration_has_product( $registration, $filters['product_id'] ) ) {
			return false;
		}
		if ( in_array( $filters['recipient_group'], array( 'party_participants', 'dinner_participants' ), true ) && ! self::registration_has_named_product( $registration, str_replace( '_participants', '', $filters['recipient_group'] ) ) ) {
			return false;
		}
		return true;
	}

	private static function order_matches_filters( $order, $registration, $filters ) {
		$order_data = $order instanceof TAKA_Ticketing_Order ? $order->to_array() : array();
		if ( '' !== $filters['voucher_code'] && strtolower( $filters['voucher_code'] ) !== strtolower( (string) ( $order_data['applied_voucher_code'] ?? '' ) ) ) {
			return false;
		}
		if ( 'paid' === $filters['recipient_group'] ) {
			return 'paid' === (string) ( $registration['payment_status'] ?? $order_data['payment_status'] ?? '' );
		}
		if ( 'unpaid' === $filters['recipient_group'] ) {
			return 'paid' !== (string) ( $registration['payment_status'] ?? $order_data['payment_status'] ?? '' ) && 'cancelled' !== (string) ( $registration['payment_status'] ?? $order_data['payment_status'] ?? '' );
		}
		return true;
	}

	private static function recipient_group_matches_registration( $group, $registration ) {
		if ( 'registered' === $group || 'paid' === $group || 'unpaid' === $group ) {
			return 'cancelled' !== (string) ( $registration['registration_status'] ?? '' );
		}
		if ( 'checked_in' === $group ) {
			return 'checked_in' === (string) ( $registration['checkin_status'] ?? '' ) || 'checked_in' === (string) ( $registration['attendance_state'] ?? '' );
		}
		if ( 'no_shows' === $group ) {
			return 'no_show' === (string) ( $registration['attendance_state'] ?? '' );
		}
		return true;
	}

	private static function registration_has_product( $registration, $product_id ) {
		foreach ( (array) ( $registration['line_items'] ?? array() ) as $item ) {
			if ( (string) ( $item['product_id'] ?? '' ) === (string) $product_id ) {
				return true;
			}
		}
		return false;
	}

	private static function registration_has_named_product( $registration, $needle ) {
		foreach ( (array) ( $registration['line_items'] ?? array() ) as $item ) {
			$title = strtolower( (string) ( $item['title'] ?? '' ) );
			$type = strtolower( (string) ( $item['product_id'] ?? '' ) );
			if ( false !== strpos( $title, $needle ) || false !== strpos( $type, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	private static function send_campaign( $campaign_id, $campaign, $recipients ) {
		$sent = 0;
		$failed = 0;
		foreach ( $recipients as $recipient ) {
			$subject = self::render_variables( $campaign['subject'], $recipient );
			$body = self::render_variables( $campaign['body'], $recipient );
			$ok = function_exists( 'wp_mail' ) ? wp_mail( $recipient['email'], wp_specialchars_decode( $subject ), $body ) : false;
			$message = self::normalize_message(
				array(
					'campaign_id'     => $campaign_id,
					'template_id'      => $campaign['template_id'],
					'recipient_email' => $recipient['email'],
					'recipient_name'  => $recipient['name'],
					'subject'         => $subject,
					'body'            => $body,
					'status'          => $ok ? 'sent' : 'failed',
					'sent_at'         => current_time( 'mysql' ),
				)
			);
			self::save_private_post( TAKA_PLATFORM_CPT_COMMUNICATION_MESSAGE, self::MESSAGE_META, $message, __( 'Outgoing message', 'taka-platform' ) );
			if ( $ok ) {
				$sent++;
			} else {
				$failed++;
			}
		}

		$campaign['id'] = $campaign_id;
		$campaign['status'] = 'sent';
		$campaign['sent_at'] = current_time( 'mysql' );
		$campaign['recipient_count'] = count( $recipients );
		self::save_private_post( TAKA_PLATFORM_CPT_COMMUNICATION_CAMPAIGN, self::CAMPAIGN_META, $campaign, __( 'Communication campaign', 'taka-platform' ) );
		return array( 'sent' => $sent, 'failed' => $failed );
	}

	private static function render_variables( $text, $recipient ) {
		$variables = is_array( $recipient['variables'] ?? null ) ? $recipient['variables'] : array();
		$replace = array();
		foreach ( $variables as $key => $value ) {
			$replace[ '{{' . $key . '}}' ] = (string) $value;
		}
		return strtr( (string) $text, $replace );
	}

	private static function save_private_post( $post_type, $meta_key, $data, $fallback_title ) {
		$post_id = absint( $data['id'] ?? 0 );
		$title = sanitize_text_field( $data['title'] ?? '' );
		if ( '' === $title ) {
			$title = $fallback_title;
		}
		$post_data = array(
			'post_type'   => $post_type,
			'post_status' => 'private',
			'post_title'  => $title,
		);
		if ( $post_id ) {
			$post_data['ID'] = $post_id;
			$result = wp_update_post( $post_data, true );
		} else {
			$result = wp_insert_post( $post_data, true );
			$post_id = is_wp_error( $result ) ? 0 : absint( $result );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$data['id'] = $post_id;
		$data['updated_at'] = current_time( 'mysql' );
		if ( empty( $data['created_at'] ) ) {
			$data['created_at'] = get_post_time( 'Y-m-d H:i:s', false, $post_id );
		}
		update_post_meta( $post_id, $meta_key, $data );
		return $data;
	}

	private static function get_templates() {
		return array_map( array( __CLASS__, 'template_from_post' ), self::get_private_posts( TAKA_PLATFORM_CPT_COMMUNICATION_TEMPLATE, 100 ) );
	}

	private static function get_campaigns( $args = array() ) {
		$items = array_map( array( __CLASS__, 'campaign_from_post' ), self::get_private_posts( TAKA_PLATFORM_CPT_COMMUNICATION_CAMPAIGN, absint( $args['per_page'] ?? 50 ) ) );
		if ( empty( $args['status'] ) ) {
			return $items;
		}
		$statuses = (array) $args['status'];
		return array_values(
			array_filter(
				$items,
				function ( $item ) use ( $statuses ) {
					return in_array( $item['status'], $statuses, true );
				}
			)
		);
	}

	private static function get_messages( $args = array() ) {
		return array_map( array( __CLASS__, 'message_from_post' ), self::get_private_posts( TAKA_PLATFORM_CPT_COMMUNICATION_MESSAGE, absint( $args['per_page'] ?? 50 ) ) );
	}

	private static function get_private_posts( $post_type, $per_page ) {
		return get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => 'private',
				'posts_per_page'   => $per_page > 0 ? $per_page : 50,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => true,
			)
		);
	}

	private static function template_from_post( $post ) {
		$data = get_post_meta( $post->ID, self::TEMPLATE_META, true );
		$data = is_array( $data ) ? $data : array();
		$data['id'] = absint( $post->ID );
		return self::normalize_template( $data );
	}

	private static function campaign_from_post( $post ) {
		$data = get_post_meta( $post->ID, self::CAMPAIGN_META, true );
		$data = is_array( $data ) ? $data : array();
		$data['id'] = absint( $post->ID );
		return self::normalize_campaign( $data );
	}

	private static function message_from_post( $post ) {
		$data = get_post_meta( $post->ID, self::MESSAGE_META, true );
		$data = is_array( $data ) ? $data : array();
		$data['id'] = absint( $post->ID );
		$data['created_at'] = $data['created_at'] ?? get_post_time( 'Y-m-d H:i:s', false, $post );
		return self::normalize_message( $data );
	}

	private static function normalize_template( $data ) {
		$data = is_array( $data ) ? $data : array();
		$type = sanitize_key( $data['type'] ?? 'general' );
		if ( ! array_key_exists( $type, self::template_types() ) ) {
			$type = 'general';
		}
		return array(
			'id'          => absint( $data['id'] ?? 0 ),
			'title'       => sanitize_text_field( $data['title'] ?? '' ),
			'key'         => sanitize_key( $data['key'] ?? '' ),
			'type'        => $type,
			'channel'     => 'email',
			'subject'     => sanitize_text_field( $data['subject'] ?? '' ),
			'body'        => sanitize_textarea_field( $data['body'] ?? '' ),
			'description' => sanitize_textarea_field( $data['description'] ?? '' ),
			'created_at'  => sanitize_text_field( $data['created_at'] ?? '' ),
			'updated_at'  => sanitize_text_field( $data['updated_at'] ?? '' ),
		);
	}

	private static function normalize_campaign( $data ) {
		$data = is_array( $data ) ? $data : array();
		$status = sanitize_key( $data['status'] ?? 'draft' );
		if ( ! in_array( $status, array( 'draft', 'scheduled', 'sending', 'sent', 'cancelled' ), true ) ) {
			$status = 'draft';
		}
		return array(
			'id'              => absint( $data['id'] ?? 0 ),
			'title'           => sanitize_text_field( $data['title'] ?? '' ),
			'template_id'     => absint( $data['template_id'] ?? 0 ),
			'channel'         => 'email',
			'subject'         => sanitize_text_field( $data['subject'] ?? '' ),
			'body'            => sanitize_textarea_field( $data['body'] ?? '' ),
			'filters'         => self::normalize_filters( $data['filters'] ?? array() ),
			'status'          => $status,
			'scheduled_at'    => sanitize_text_field( $data['scheduled_at'] ?? '' ),
			'sent_at'         => sanitize_text_field( $data['sent_at'] ?? '' ),
			'recipient_count' => absint( $data['recipient_count'] ?? 0 ),
			'warnings'        => array_map( 'sanitize_text_field', (array) ( $data['warnings'] ?? array() ) ),
			'created_at'      => sanitize_text_field( $data['created_at'] ?? '' ),
			'updated_at'      => sanitize_text_field( $data['updated_at'] ?? '' ),
		);
	}

	private static function normalize_message( $data ) {
		$data = is_array( $data ) ? $data : array();
		$status = sanitize_key( $data['status'] ?? 'queued' );
		if ( ! in_array( $status, array( 'queued', 'sent', 'failed' ), true ) ) {
			$status = 'queued';
		}
		return array(
			'id'              => absint( $data['id'] ?? 0 ),
			'title'           => sanitize_text_field( $data['title'] ?? '' ),
			'campaign_id'     => absint( $data['campaign_id'] ?? 0 ),
			'template_id'     => absint( $data['template_id'] ?? 0 ),
			'recipient_email' => sanitize_email( $data['recipient_email'] ?? '' ),
			'recipient_name'  => sanitize_text_field( $data['recipient_name'] ?? '' ),
			'subject'         => sanitize_text_field( $data['subject'] ?? '' ),
			'body'            => sanitize_textarea_field( $data['body'] ?? '' ),
			'status'          => $status,
			'error'           => sanitize_text_field( $data['error'] ?? '' ),
			'sent_at'         => sanitize_text_field( $data['sent_at'] ?? '' ),
			'created_at'      => sanitize_text_field( $data['created_at'] ?? '' ),
			'updated_at'      => sanitize_text_field( $data['updated_at'] ?? '' ),
		);
	}

	private static function normalize_filters( $filters ) {
		$filters = is_array( $filters ) ? $filters : array();
		$group = sanitize_key( $filters['recipient_group'] ?? 'registered' );
		if ( ! array_key_exists( $group, self::recipient_groups() ) ) {
			$group = 'registered';
		}
		$event_ids = array_filter( array_map( 'absint', (array) ( $filters['event_ids'] ?? array() ) ) );
		return array(
			'recipient_group'    => $group,
			'event_ids'          => array_values( array_unique( $event_ids ) ),
			'tour_key'           => sanitize_key( $filters['tour_key'] ?? '' ),
			'country'            => sanitize_text_field( $filters['country'] ?? '' ),
			'dojo'               => sanitize_text_field( $filters['dojo'] ?? '' ),
			'rank'               => sanitize_text_field( $filters['rank'] ?? '' ),
			'ticket_type_id'     => sanitize_key( $filters['ticket_type_id'] ?? '' ),
			'product_id'         => sanitize_key( $filters['product_id'] ?? '' ),
			'dietary_preference' => sanitize_key( $filters['dietary_preference'] ?? '' ),
			'allergy_flag'       => ! empty( $filters['allergy_flag'] ) ? '1' : '0',
			'voucher_code'       => sanitize_text_field( $filters['voucher_code'] ?? '' ),
		);
	}

	private static function filters_from_request() {
		$raw = isset( $_GET['filters'] ) && is_array( $_GET['filters'] ) ? wp_unslash( $_GET['filters'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return self::normalize_filters( $raw );
	}

	private static function template_types() {
		return array(
			'general'          => __( 'General', 'taka-platform' ),
			'welcome'          => __( 'Welcome', 'taka-platform' ),
			'parking'          => __( 'Parking', 'taka-platform' ),
			'venue_changed'    => __( 'Venue changed', 'taka-platform' ),
			'bring_gi'         => __( 'Bring Gi', 'taka-platform' ),
			'bring_weapons'    => __( 'Bring weapons', 'taka-platform' ),
			'reminder'         => __( 'Reminder', 'taka-platform' ),
			'payment_reminder' => __( 'Payment reminder', 'taka-platform' ),
			'thank_you'        => __( 'Thank you', 'taka-platform' ),
			'tomorrow'         => __( 'Seminar starts tomorrow', 'taka-platform' ),
			'dinner_reminder'  => __( 'Dinner reminder', 'taka-platform' ),
		);
	}

	private static function recipient_groups() {
		return array(
			'registered'         => __( 'Registered participants', 'taka-platform' ),
			'paid'               => __( 'Paid participants', 'taka-platform' ),
			'unpaid'             => __( 'Unpaid participants', 'taka-platform' ),
			'checked_in'         => __( 'Checked-in participants', 'taka-platform' ),
			'no_shows'           => __( 'No-shows', 'taka-platform' ),
			'volunteers'         => __( 'Volunteers', 'taka-platform' ),
			'organizers'         => __( 'Organizers', 'taka-platform' ),
			'vips'               => __( 'VIPs', 'taka-platform' ),
			'instructors'        => __( 'Instructors', 'taka-platform' ),
			'sponsors'           => __( 'Sponsors', 'taka-platform' ),
			'press'              => __( 'Press', 'taka-platform' ),
			'party_participants' => __( 'Party participants', 'taka-platform' ),
			'dinner_participants' => __( 'Dinner participants', 'taka-platform' ),
			'all_people'         => __( 'All people', 'taka-platform' ),
		);
	}

	private static function tag_group_for_recipient_group( $group ) {
		$map = array(
			'volunteers'  => 'Volunteer',
			'organizers'  => 'Organizer',
			'vips'        => 'VIP',
			'instructors' => 'Instructor',
			'sponsors'    => 'Sponsor',
			'press'       => 'Press',
		);
		return $map[ $group ] ?? '';
	}

	private static function campaign_status_label( $status ) {
		$labels = array(
			'draft'     => __( 'Draft', 'taka-platform' ),
			'scheduled' => __( 'Scheduled', 'taka-platform' ),
			'sending'   => __( 'Sending', 'taka-platform' ),
			'sent'      => __( 'Sent', 'taka-platform' ),
			'cancelled' => __( 'Cancelled', 'taka-platform' ),
		);
		return $labels[ $status ] ?? $status;
	}

	private static function message_status_label( $status ) {
		$labels = array(
			'queued' => __( 'Queued', 'taka-platform' ),
			'sent'   => __( 'Sent', 'taka-platform' ),
			'failed' => __( 'Failed', 'taka-platform' ),
		);
		return $labels[ $status ] ?? $status;
	}

	private static function payment_status_label( $status ) {
		$labels = array(
			'pending'   => __( 'Pending', 'taka-platform' ),
			'paid'      => __( 'Paid', 'taka-platform' ),
			'cancelled' => __( 'Cancelled', 'taka-platform' ),
			'refunded'  => __( 'Refunded', 'taka-platform' ),
		);
		return $labels[ $status ] ?? $status;
	}

	private static function order_for_registration( $registration ) {
		if ( ! class_exists( 'TAKA_Ticketing_Module' ) || empty( $registration['order_id'] ) ) {
			return null;
		}
		return TAKA_Ticketing_Module::order_repository()->find_by_id( absint( $registration['order_id'] ) );
	}

	private static function event_tour_key( $event_id ) {
		$event_id = absint( $event_id );
		if ( ! $event_id ) {
			return '';
		}
		foreach ( array( '_taka_tour_key', 'tour_key', '_taka_tour_id', 'tour_id' ) as $meta_key ) {
			$value = sanitize_key( get_post_meta( $event_id, $meta_key, true ) );
			if ( '' !== $value ) {
				return $value;
			}
		}
		return '';
	}

	private static function event_venue_label( $event_id ) {
		$event_id = absint( $event_id );
		if ( ! $event_id ) {
			return '';
		}
		$venue_id = absint( get_post_meta( $event_id, 'venue_id', true ) );
		if ( ! $venue_id ) {
			$venue_id = absint( get_post_meta( $event_id, '_taka_venue_id', true ) );
		}
		if ( $venue_id ) {
			return get_the_title( $venue_id );
		}
		return sanitize_text_field( get_post_meta( $event_id, 'venue', true ) );
	}

	private static function event_organizer_label( $event_id ) {
		$event_id = absint( $event_id );
		if ( ! $event_id ) {
			return '';
		}
		$organizer_id = absint( get_post_meta( $event_id, 'organizer_id', true ) );
		if ( ! $organizer_id ) {
			$organizer_id = absint( get_post_meta( $event_id, '_taka_organizer_id', true ) );
		}
		if ( $organizer_id ) {
			return get_the_title( $organizer_id );
		}
		return sanitize_text_field( get_post_meta( $event_id, 'organizer', true ) );
	}

	private static function qr_payload_for_registration( $registration ) {
		if ( class_exists( 'TAKA_Event_Operations_Attendance_Service' ) && ! empty( $registration['id'] ) ) {
			return TAKA_Event_Operations_Attendance_Service::qr_payload( $registration );
		}
		return '';
	}

	private static function sample_recipient() {
		return array(
			'email'     => 'participant@example.org',
			'name'      => __( 'Sample Participant', 'taka-platform' ),
			'variables' => array(
				'FirstName'     => __( 'Sample', 'taka-platform' ),
				'LastName'      => __( 'Participant', 'taka-platform' ),
				'EventName'     => __( 'Weekend Seminar', 'taka-platform' ),
				'Venue'         => __( 'Main Dojo', 'taka-platform' ),
				'Organizer'     => __( 'Organizer Team', 'taka-platform' ),
				'PaymentStatus' => __( 'Pending', 'taka-platform' ),
				'OrderNumber'   => 'TAKA-0001',
				'TicketType'    => __( 'Seminar Ticket', 'taka-platform' ),
				'QRCode'        => '',
			),
		);
	}
}

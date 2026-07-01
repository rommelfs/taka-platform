<?php
/**
 * Private Finance & Tour Budget dashboard.
 *
 * Finance combines paid ticketing revenue with private tour-planning expenses.
 * Orders and planning items remain the source of truth; this module adds only
 * explicit expense entries and read-only reporting over existing data.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Finance_Module {
	const ADMIN_PAGE_SLUG = 'taka-platform-finance';
	const EXPENSE_META    = '_taka_finance_expense';
	const SAVE_ACTION     = 'taka_finance_save_expense';
	const EXPORT_ACTION   = 'taka_finance_export_csv';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ), 0 );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ), 23 );
		add_action( 'admin_init', array( __CLASS__, 'ensure_capabilities' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( __CLASS__, 'handle_save_expense' ) );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( __CLASS__, 'handle_export_csv' ) );
	}

	public static function register_post_types() {
		register_post_type(
			TAKA_PLATFORM_CPT_FINANCE_EXPENSE,
			array(
				'labels'              => array(
					'name'          => __( 'Finance Expenses', 'taka-platform' ),
					'singular_name' => __( 'Finance Expense', 'taka-platform' ),
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
			__( 'Finance', 'taka-platform' ),
			__( 'Finance', 'taka-platform' ),
			'view_taka_finance',
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
			'view_taka_finance',
			'manage_taka_finance',
		);
	}

	public static function admin_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::ADMIN_PAGE_SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	public static function handle_save_expense() {
		if ( ! current_user_can( 'manage_taka_finance' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::SAVE_ACTION, '_wpnonce' );

		$raw = isset( $_POST['expense'] ) && is_array( $_POST['expense'] ) ? wp_unslash( $_POST['expense'] ) : array();
		$expense = self::normalize_expense( $raw );
		$result = self::save_expense( $expense );
		$args = array();
		if ( is_wp_error( $result ) ) {
			$args['finance_error'] = $result->get_error_message();
		} else {
			$args['finance_saved'] = '1';
		}
		wp_safe_redirect( self::admin_url( $args ) );
		exit;
	}

	public static function handle_export_csv() {
		if ( ! current_user_can( 'view_taka_finance' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::EXPORT_ACTION, '_wpnonce' );

		$data = self::dashboard_data();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=taka-finance-export-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'type', 'title', 'date', 'category', 'amount', 'currency', 'status', 'tour', 'event', 'responsible', 'financial_owner', 'source' ) );
		foreach ( $data['revenue_items'] as $item ) {
			fputcsv( $out, array( 'revenue', $item['title'], $item['date'], $item['category'], $item['amount'], $item['currency'], $item['status'], $item['tour_key'], $item['event_title'], '', '', $item['source'] ) );
		}
		foreach ( $data['expenses'] as $expense ) {
			fputcsv( $out, array( 'expense', $expense['title'], $expense['date'], $expense['category'], $expense['amount'], $expense['currency'], $expense['status'], $expense['tour_key'], $expense['event_title'], $expense['responsible'], $expense['financial_owner'], $expense['source'] ) );
		}
		fclose( $out );
		exit;
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'view_taka_finance' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}

		$data = self::dashboard_data();
		?>
		<div class="wrap taka-finance-admin">
			<h1><?php esc_html_e( 'Finance & Tour Budget', 'taka-platform' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Ticketing revenue, tour-planning expenses and cash-flow visibility for private tour operations.', 'taka-platform' ); ?></p>
			<?php self::render_notices(); ?>
			<?php self::render_dashboard_cards( $data ); ?>
			<div class="taka-admin-grid taka-admin-grid--two">
				<section class="taka-admin-panel">
					<h2><?php esc_html_e( 'Expense entry', 'taka-platform' ); ?></h2>
					<?php self::render_expense_form(); ?>
				</section>
				<section class="taka-admin-panel">
					<h2><?php esc_html_e( 'Payment overview', 'taka-platform' ); ?></h2>
					<?php self::render_payment_overview( $data['payment_overview'] ); ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::EXPORT_ACTION ); ?>">
						<?php wp_nonce_field( self::EXPORT_ACTION, '_wpnonce' ); ?>
						<p><button class="button" type="submit"><?php esc_html_e( 'Export CSV', 'taka-platform' ); ?></button></p>
					</form>
				</section>
			</div>
			<div class="taka-admin-grid taka-admin-grid--two">
				<section class="taka-admin-panel">
					<h2><?php esc_html_e( 'Revenue reports', 'taka-platform' ); ?></h2>
					<?php self::render_report_tables( $data['revenue_reports'] ); ?>
				</section>
				<section class="taka-admin-panel">
					<h2><?php esc_html_e( 'Expense reports', 'taka-platform' ); ?></h2>
					<?php self::render_report_tables( $data['expense_reports'] ); ?>
				</section>
			</div>
			<section class="taka-admin-panel taka-admin-panel--full">
				<h2><?php esc_html_e( 'Expenses', 'taka-platform' ); ?></h2>
				<?php self::render_expense_table( $data['expenses'] ); ?>
			</section>
		</div>
		<?php
	}

	private static function render_notices() {
		if ( ! empty( $_GET['finance_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-error"><p>' . esc_html( wp_unslash( $_GET['finance_error'] ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! empty( $_GET['finance_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Expense saved.', 'taka-platform' ) . '</p></div>';
		}
	}

	private static function render_dashboard_cards( $data ) {
		$metrics = $data['metrics'];
		?>
		<div class="taka-finance-metrics">
			<?php self::metric_card( __( 'Revenue', 'taka-platform' ), self::format_totals( $metrics['revenue'] ) ); ?>
			<?php self::metric_card( __( 'Expenses', 'taka-platform' ), self::format_totals( $metrics['expenses'] ) ); ?>
			<?php self::metric_card( __( 'Profit', 'taka-platform' ), self::format_totals( $metrics['profit'] ) ); ?>
			<?php self::metric_card( __( 'Outstanding payments', 'taka-platform' ), self::format_totals( $metrics['outstanding'] ) ); ?>
			<?php self::metric_card( __( 'Cash flow', 'taka-platform' ), self::format_totals( $metrics['cash_flow'] ) ); ?>
		</div>
		<?php
	}

	private static function metric_card( $label, $value ) {
		?>
		<div class="taka-finance-card">
			<span><?php echo esc_html( $label ); ?></span>
			<strong><?php echo esc_html( $value ); ?></strong>
		</div>
		<?php
	}

	private static function render_expense_form() {
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
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
			<?php wp_nonce_field( self::SAVE_ACTION, '_wpnonce' ); ?>
			<div class="taka-admin-field-grid">
				<label>
					<span><?php esc_html_e( 'Title', 'taka-platform' ); ?></span>
					<input type="text" name="expense[title]" required>
				</label>
				<label>
					<span><?php esc_html_e( 'Category', 'taka-platform' ); ?></span>
					<select name="expense[category]">
						<?php foreach ( self::expense_categories() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Date', 'taka-platform' ); ?></span>
					<input type="date" name="expense[date]">
				</label>
				<label>
					<span><?php esc_html_e( 'Amount', 'taka-platform' ); ?></span>
					<input type="text" name="expense[amount]" inputmode="decimal" required>
				</label>
				<label>
					<span><?php esc_html_e( 'Currency', 'taka-platform' ); ?></span>
					<input type="text" name="expense[currency]" value="EUR" maxlength="3">
				</label>
				<label>
					<span><?php esc_html_e( 'Status', 'taka-platform' ); ?></span>
					<select name="expense[status]">
						<?php foreach ( self::expense_statuses() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Responsible', 'taka-platform' ); ?></span>
					<input type="text" name="expense[responsible]">
				</label>
				<label>
					<span><?php esc_html_e( 'Financial owner', 'taka-platform' ); ?></span>
					<input type="text" name="expense[financial_owner]">
				</label>
				<label>
					<span><?php esc_html_e( 'Tour', 'taka-platform' ); ?></span>
					<input type="text" name="expense[tour_key]" placeholder="taka-tour">
				</label>
				<label>
					<span><?php esc_html_e( 'Event', 'taka-platform' ); ?></span>
					<select name="expense[event_id]">
						<option value="0"><?php esc_html_e( 'No event', 'taka-platform' ); ?></option>
						<?php foreach ( $events as $event ) : ?>
							<option value="<?php echo esc_attr( $event->ID ); ?>"><?php echo esc_html( get_the_title( $event ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Planning item ID', 'taka-platform' ); ?></span>
					<input type="text" name="expense[planning_item_id]">
				</label>
				<label class="taka-admin-checkbox">
					<input type="checkbox" name="expense[invoice_available]" value="1">
					<span><?php esc_html_e( 'Invoice available', 'taka-platform' ); ?></span>
				</label>
				<label class="taka-admin-field-grid__wide">
					<span><?php esc_html_e( 'Notes', 'taka-platform' ); ?></span>
					<textarea name="expense[notes]" rows="3"></textarea>
				</label>
			</div>
			<p class="submit"><button class="button button-primary" type="submit"><?php esc_html_e( 'Save expense', 'taka-platform' ); ?></button></p>
		</form>
		<?php
	}

	private static function render_payment_overview( $overview ) {
		if ( empty( $overview ) ) {
			echo '<p>' . esc_html__( 'No payments recorded yet.', 'taka-platform' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Payment method', 'taka-platform' ); ?></th><th><?php esc_html_e( 'Status', 'taka-platform' ); ?></th><th><?php esc_html_e( 'Amount', 'taka-platform' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $overview as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['method_label'] ); ?></td>
						<td><?php echo esc_html( ucfirst( $row['status'] ) ); ?></td>
						<td><?php echo esc_html( self::format_totals( $row['totals'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private static function render_report_tables( $reports ) {
		foreach ( $reports as $label => $rows ) {
			echo '<h3>' . esc_html( $label ) . '</h3>';
			if ( empty( $rows ) ) {
				echo '<p>' . esc_html__( 'No data yet.', 'taka-platform' ) . '</p>';
				continue;
			}
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Group', 'taka-platform' ) . '</th><th>' . esc_html__( 'Amount', 'taka-platform' ) . '</th></tr></thead><tbody>';
			foreach ( $rows as $name => $totals ) {
				echo '<tr><td>' . esc_html( $name ) . '</td><td>' . esc_html( self::format_totals( $totals ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
	}

	private static function render_expense_table( $expenses ) {
		if ( empty( $expenses ) ) {
			echo '<p>' . esc_html__( 'No expenses recorded yet.', 'taka-platform' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'taka-platform' ); ?></th>
					<th><?php esc_html_e( 'Title', 'taka-platform' ); ?></th>
					<th><?php esc_html_e( 'Category', 'taka-platform' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'taka-platform' ); ?></th>
					<th><?php esc_html_e( 'Status', 'taka-platform' ); ?></th>
					<th><?php esc_html_e( 'Financial owner', 'taka-platform' ); ?></th>
					<th><?php esc_html_e( 'Source', 'taka-platform' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $expenses as $expense ) : ?>
					<tr>
						<td><?php echo esc_html( $expense['date'] ); ?></td>
						<td><?php echo esc_html( $expense['title'] ); ?></td>
						<td><?php echo esc_html( self::expense_categories()[ $expense['category'] ] ?? $expense['category'] ); ?></td>
						<td><?php echo esc_html( self::format_money( $expense['amount'], $expense['currency'] ) ); ?></td>
						<td><?php echo esc_html( self::expense_statuses()[ $expense['status'] ] ?? $expense['status'] ); ?></td>
						<td><?php echo esc_html( $expense['financial_owner'] ); ?></td>
						<td><?php echo esc_html( ucfirst( $expense['source'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	public static function dashboard_data() {
		$revenue = self::collect_revenue();
		$expenses = self::collect_expenses();

		$expense_totals = array();
		$paid_expense_totals = array();
		foreach ( $expenses as $expense ) {
			if ( 'cancelled' === $expense['status'] ) {
				continue;
			}
			self::add_total( $expense_totals, $expense['currency'], self::money_to_float( $expense['amount'] ) );
			if ( 'paid' === $expense['status'] ) {
				self::add_total( $paid_expense_totals, $expense['currency'], self::money_to_float( $expense['amount'] ) );
			}
		}

		$metrics = array(
			'revenue'     => $revenue['paid_totals'],
			'expenses'    => $expense_totals,
			'profit'      => self::subtract_totals( $revenue['paid_totals'], $expense_totals ),
			'outstanding' => $revenue['outstanding_totals'],
			'cash_flow'   => self::subtract_totals( $revenue['paid_totals'], $paid_expense_totals ),
		);

		return array(
			'metrics'          => $metrics,
			'revenue_items'    => $revenue['items'],
			'expenses'         => $expenses,
			'revenue_reports'  => $revenue['reports'],
			'expense_reports'  => self::expense_reports( $expenses ),
			'payment_overview' => $revenue['payment_overview'],
		);
	}

	private static function collect_revenue() {
		$result = array(
			'items'              => array(),
			'paid_totals'        => array(),
			'outstanding_totals' => array(),
			'reports'            => array(
				__( 'By tour', 'taka-platform' )        => array(),
				__( 'By event', 'taka-platform' )       => array(),
				__( 'By organizer', 'taka-platform' )   => array(),
				__( 'By ticket type', 'taka-platform' ) => array(),
				__( 'By product', 'taka-platform' )     => array(),
				__( 'By country', 'taka-platform' )     => array(),
			),
			'payment_overview'   => array(),
		);
		if ( ! class_exists( 'TAKA_Ticketing_Module' ) ) {
			return $result;
		}

		foreach ( TAKA_Ticketing_Module::order_repository()->query( array( 'per_page' => -1 ) ) as $order ) {
			$data = $order instanceof TAKA_Ticketing_Order ? $order->to_array() : array();
			$currency = self::sanitize_currency( $data['currency'] ?? 'EUR' );
			$amount = self::money_to_float( $data['amount'] ?? $data['final_amount'] ?? '0' );
			$status = sanitize_key( $data['payment_status'] ?? 'pending' );
			$event_id = absint( $data['event_id'] ?? 0 );
			$event_title = (string) ( $data['event_title'] ?? '' );
			if ( '' === $event_title && $event_id ) {
				$event_title = get_the_title( $event_id );
			}
			$tour_key = self::event_tour_key( $event_id );
			$organizer = self::event_organizer_label( $event_id );
			$ticket_type = (string) ( $data['ticket_type_name'] ?? $data['ticket_type_id'] ?? __( 'Ticket', 'taka-platform' ) );
			$country = self::order_country( $data );
			$method = sanitize_key( $data['payment_method'] ?? 'unknown' );

			if ( 'paid' === $status ) {
				self::add_total( $result['paid_totals'], $currency, $amount );
				self::add_report_total( $result['reports'][ __( 'By tour', 'taka-platform' ) ], $tour_key ?: __( 'Unassigned', 'taka-platform' ), $currency, $amount );
				self::add_report_total( $result['reports'][ __( 'By event', 'taka-platform' ) ], $event_title ?: __( 'Unassigned', 'taka-platform' ), $currency, $amount );
				self::add_report_total( $result['reports'][ __( 'By organizer', 'taka-platform' ) ], $organizer ?: __( 'Unassigned', 'taka-platform' ), $currency, $amount );
				self::add_report_total( $result['reports'][ __( 'By ticket type', 'taka-platform' ) ], $ticket_type ?: __( 'Unassigned', 'taka-platform' ), $currency, $amount );
				self::add_report_total( $result['reports'][ __( 'By country', 'taka-platform' ) ], $country ?: __( 'Unassigned', 'taka-platform' ), $currency, $amount );
			} elseif ( ! in_array( $status, array( 'cancelled', 'refunded' ), true ) ) {
				self::add_total( $result['outstanding_totals'], $currency, $amount );
			}

			$key = $method . '|' . $status;
			if ( empty( $result['payment_overview'][ $key ] ) ) {
				$result['payment_overview'][ $key ] = array(
					'method_label' => class_exists( 'TAKA_Ticketing_Module' ) ? TAKA_Ticketing_Module::payment_method_admin_label( $method ) : $method,
					'status'       => $status,
					'totals'       => array(),
				);
			}
			self::add_total( $result['payment_overview'][ $key ]['totals'], $currency, $amount );

			$result['items'][] = array(
				'title'       => (string) ( $data['order_number'] ?? __( 'Ticket order', 'taka-platform' ) ),
				'date'        => (string) ( $data['created_at'] ?? '' ),
				'category'    => 'ticketing',
				'amount'      => (string) ( $data['amount'] ?? $data['final_amount'] ?? '0' ),
				'currency'    => $currency,
				'status'      => $status,
				'tour_key'    => $tour_key,
				'event_title' => $event_title,
				'source'      => 'order',
			);

			if ( 'paid' === $status ) {
				foreach ( (array) ( $data['line_items'] ?? array() ) as $item ) {
					if ( in_array( (string) ( $item['item_type'] ?? '' ), array( 'event_ticket', 'ticket', 'discount' ), true ) ) {
						continue;
					}
					self::add_report_total( $result['reports'][ __( 'By product', 'taka-platform' ) ], (string) ( $item['title'] ?? __( 'Product', 'taka-platform' ) ), $currency, self::money_to_float( $item['total_price'] ?? '0' ) );
				}
			}
		}
		$result['payment_overview'] = array_values( $result['payment_overview'] );
		return $result;
	}

	private static function collect_expenses() {
		$expenses = self::explicit_expenses();
		if ( class_exists( 'TAKA_Platform_Tour_Planning' ) ) {
			foreach ( TAKA_Platform_Tour_Planning::export_items() as $item ) {
				$amount = '' !== (string) ( $item['actual_cost'] ?? '' ) ? $item['actual_cost'] : ( $item['estimated_cost'] ?? '' );
				if ( '' === (string) $amount ) {
					continue;
				}
				$expenses[] = self::normalize_expense(
					array(
						'id'               => 0,
						'title'            => $item['title'] ?? __( 'Planning expense', 'taka-platform' ),
						'category'         => self::planning_type_to_category( $item['type'] ?? 'other' ),
						'date'             => $item['start_date'] ?? '',
						'amount'           => $amount,
						'currency'         => $item['currency'] ?? 'EUR',
						'responsible'      => $item['responsible_person'] ?? '',
						'financial_owner'  => $item['financial_responsible_person'] ?? '',
						'tour_key'         => $item['tour_key'] ?? '',
						'event_id'         => absint( $item['related_event_id'] ?? 0 ),
						'planning_item_id' => $item['id'] ?? '',
						'status'           => self::planning_status_to_expense_status( $item['status'] ?? 'estimated' ),
						'source'           => 'planning',
					)
				);
			}
		}
		usort(
			$expenses,
			function ( $a, $b ) {
				return strcmp( (string) ( $b['date'] ?? '' ), (string) ( $a['date'] ?? '' ) );
			}
		);
		return $expenses;
	}

	private static function explicit_expenses() {
		$posts = get_posts(
			array(
				'post_type'        => TAKA_PLATFORM_CPT_FINANCE_EXPENSE,
				'post_status'      => 'private',
				'posts_per_page'   => -1,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => true,
			)
		);
		$out = array();
		foreach ( $posts as $post ) {
			$data = get_post_meta( $post->ID, self::EXPENSE_META, true );
			$data = is_array( $data ) ? $data : array();
			$data['id'] = absint( $post->ID );
			$data['title'] = $data['title'] ?? get_the_title( $post );
			$out[] = self::normalize_expense( $data );
		}
		return $out;
	}

	private static function expense_reports( $expenses ) {
		$reports = array(
			__( 'By category', 'taka-platform' )        => array(),
			__( 'By responsible', 'taka-platform' )     => array(),
			__( 'By financial owner', 'taka-platform' ) => array(),
		);
		foreach ( $expenses as $expense ) {
			if ( 'cancelled' === $expense['status'] ) {
				continue;
			}
			$amount = self::money_to_float( $expense['amount'] );
			self::add_report_total( $reports[ __( 'By category', 'taka-platform' ) ], self::expense_categories()[ $expense['category'] ] ?? $expense['category'], $expense['currency'], $amount );
			self::add_report_total( $reports[ __( 'By responsible', 'taka-platform' ) ], $expense['responsible'] ?: __( 'Unassigned', 'taka-platform' ), $expense['currency'], $amount );
			self::add_report_total( $reports[ __( 'By financial owner', 'taka-platform' ) ], $expense['financial_owner'] ?: __( 'Unassigned', 'taka-platform' ), $expense['currency'], $amount );
		}
		return $reports;
	}

	private static function save_expense( $expense ) {
		if ( '' === $expense['title'] ) {
			return new WP_Error( 'taka_finance_missing_title', __( 'Expense title is required.', 'taka-platform' ) );
		}
		if ( self::money_to_float( $expense['amount'] ) <= 0 ) {
			return new WP_Error( 'taka_finance_missing_amount', __( 'Expense amount is required.', 'taka-platform' ) );
		}

		$post_data = array(
			'post_type'   => TAKA_PLATFORM_CPT_FINANCE_EXPENSE,
			'post_status' => 'private',
			'post_title'  => $expense['title'],
		);
		if ( ! empty( $expense['id'] ) ) {
			$post_data['ID'] = absint( $expense['id'] );
			$result = wp_update_post( $post_data, true );
			$post_id = absint( $expense['id'] );
		} else {
			$result = wp_insert_post( $post_data, true );
			$post_id = is_wp_error( $result ) ? 0 : absint( $result );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$expense['id'] = $post_id;
		$expense['source'] = 'manual';
		$expense['updated_at'] = current_time( 'mysql' );
		if ( empty( $expense['created_at'] ) ) {
			$expense['created_at'] = get_post_time( 'Y-m-d H:i:s', false, $post_id );
		}
		update_post_meta( $post_id, self::EXPENSE_META, $expense );
		return $expense;
	}

	private static function normalize_expense( $data ) {
		$data = is_array( $data ) ? $data : array();
		$category = sanitize_key( $data['category'] ?? 'other' );
		if ( ! array_key_exists( $category, self::expense_categories() ) ) {
			$category = 'other';
		}
		$status = sanitize_key( $data['status'] ?? 'estimated' );
		if ( ! array_key_exists( $status, self::expense_statuses() ) ) {
			$status = 'estimated';
		}
		$event_id = absint( $data['event_id'] ?? 0 );
		return array(
			'id'                => absint( $data['id'] ?? 0 ),
			'title'             => sanitize_text_field( $data['title'] ?? '' ),
			'category'          => $category,
			'date'              => self::sanitize_date( $data['date'] ?? '' ),
			'amount'            => self::sanitize_money( $data['amount'] ?? '' ),
			'currency'          => self::sanitize_currency( $data['currency'] ?? 'EUR' ),
			'responsible'       => sanitize_text_field( $data['responsible'] ?? '' ),
			'financial_owner'   => sanitize_text_field( $data['financial_owner'] ?? '' ),
			'tour_key'          => sanitize_key( $data['tour_key'] ?? '' ),
			'event_id'          => $event_id,
			'event_title'       => $event_id ? get_the_title( $event_id ) : '',
			'planning_item_id'  => sanitize_text_field( $data['planning_item_id'] ?? '' ),
			'status'            => $status,
			'invoice_available' => ! empty( $data['invoice_available'] ) ? '1' : '0',
			'notes'             => sanitize_textarea_field( $data['notes'] ?? '' ),
			'source'            => sanitize_key( $data['source'] ?? 'manual' ),
			'created_at'        => sanitize_text_field( $data['created_at'] ?? '' ),
			'updated_at'        => sanitize_text_field( $data['updated_at'] ?? '' ),
		);
	}

	private static function expense_categories() {
		return array(
			'hotel'        => __( 'Hotels', 'taka-platform' ),
			'flight'       => __( 'Flights', 'taka-platform' ),
			'transport'    => __( 'Transport', 'taka-platform' ),
			'restaurant'   => __( 'Restaurants', 'taka-platform' ),
			'fuel'         => __( 'Fuel', 'taka-platform' ),
			'equipment'    => __( 'Equipment', 'taka-platform' ),
			'venue_rental' => __( 'Venue rental', 'taka-platform' ),
			'marketing'    => __( 'Marketing', 'taka-platform' ),
			'merchandise'  => __( 'Merchandise', 'taka-platform' ),
			'other'        => __( 'Other', 'taka-platform' ),
		);
	}

	private static function expense_statuses() {
		return array(
			'estimated' => __( 'Estimated', 'taka-platform' ),
			'confirmed' => __( 'Confirmed', 'taka-platform' ),
			'paid'      => __( 'Paid', 'taka-platform' ),
			'cancelled' => __( 'Cancelled', 'taka-platform' ),
		);
	}

	private static function planning_type_to_category( $type ) {
		$map = array(
			'accommodation' => 'hotel',
			'transfer'      => 'transport',
			'meal'          => 'restaurant',
			'excursion'     => 'transport',
			'logistics'     => 'equipment',
		);
		return $map[ sanitize_key( $type ) ] ?? 'other';
	}

	private static function planning_status_to_expense_status( $status ) {
		$status = sanitize_key( $status );
		if ( 'paid' === $status ) {
			return 'paid';
		}
		if ( 'cancelled' === $status ) {
			return 'cancelled';
		}
		if ( 'confirmed' === $status ) {
			return 'confirmed';
		}
		return 'estimated';
	}

	private static function add_total( &$totals, $currency, $amount ) {
		$currency = self::sanitize_currency( $currency );
		if ( ! isset( $totals[ $currency ] ) ) {
			$totals[ $currency ] = 0.0;
		}
		$totals[ $currency ] += (float) $amount;
	}

	private static function add_report_total( &$report, $label, $currency, $amount ) {
		$label = '' !== trim( (string) $label ) ? (string) $label : __( 'Unassigned', 'taka-platform' );
		if ( ! isset( $report[ $label ] ) ) {
			$report[ $label ] = array();
		}
		self::add_total( $report[ $label ], $currency, $amount );
	}

	private static function subtract_totals( $left, $right ) {
		$out = $left;
		foreach ( $right as $currency => $amount ) {
			self::add_total( $out, $currency, -1 * (float) $amount );
		}
		return $out;
	}

	private static function format_totals( $totals ) {
		if ( empty( $totals ) ) {
			return self::format_money( '0', 'EUR' );
		}
		$out = array();
		foreach ( $totals as $currency => $amount ) {
			$out[] = self::format_money( (string) round( (float) $amount, 2 ), $currency );
		}
		return implode( ' / ', $out );
	}

	private static function format_money( $amount, $currency ) {
		$value = (float) $amount;
		$negative = $value < 0;
		$value = abs( $value );
		$currency = self::sanitize_currency( $currency );
		$formatted = number_format_i18n( $value, 2 );
		if ( 'EUR' === $currency ) {
			return ( $negative ? '-' : '' ) . '€' . $formatted;
		}
		return ( $negative ? '-' : '' ) . $currency . ' ' . $formatted;
	}

	private static function money_to_float( $amount ) {
		if ( class_exists( 'TAKA_Ticketing_Pricing_Service' ) ) {
			return TAKA_Ticketing_Pricing_Service::money_to_float( $amount );
		}
		return (float) str_replace( ',', '.', preg_replace( '/[^0-9,.-]/', '', (string) $amount ) );
	}

	private static function sanitize_money( $amount ) {
		if ( class_exists( 'TAKA_Platform_Data' ) ) {
			return TAKA_Platform_Data::sanitize_money_value( $amount );
		}
		$amount = str_replace( ',', '.', sanitize_text_field( $amount ) );
		return preg_match( '/^-?\d+(\.\d{1,2})?$/', $amount ) ? $amount : '';
	}

	private static function sanitize_currency( $currency ) {
		$currency = strtoupper( sanitize_text_field( $currency ) );
		if ( class_exists( 'TAKA_Platform_Data' ) ) {
			$currency = TAKA_Platform_Data::normalize_event_option_value( 'currency', $currency ?: 'EUR' );
		}
		return '' !== $currency ? $currency : 'EUR';
	}

	private static function sanitize_date( $date ) {
		$date = sanitize_text_field( $date );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '';
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

	private static function event_organizer_label( $event_id ) {
		$event_id = absint( $event_id );
		if ( ! $event_id ) {
			return '';
		}
		$organizer_id = absint( get_post_meta( $event_id, 'organizer_id', true ) );
		if ( ! $organizer_id ) {
			$organizer_id = absint( get_post_meta( $event_id, '_taka_organizer_id', true ) );
		}
		return $organizer_id ? get_the_title( $organizer_id ) : sanitize_text_field( get_post_meta( $event_id, 'organizer', true ) );
	}

	private static function order_country( $order ) {
		$participant = is_array( $order['participant'] ?? null ) ? $order['participant'] : array();
		$buyer = is_array( $order['buyer'] ?? null ) ? $order['buyer'] : array();
		return sanitize_text_field( $participant['country'] ?? $buyer['country'] ?? '' );
	}
}

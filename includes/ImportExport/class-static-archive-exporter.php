<?php
/**
 * Static archive exporter for public TAKA Platform pages.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Static_Archive_Exporter {
	const ACTION = 'taka_platform_export_static_archive';
	const NONCE  = 'taka_platform_export_static_archive_nonce';
	const RESULT_TRANSIENT = 'taka_platform_static_archive_result';

	/**
	 * Render the admin form on the Import / Export screen.
	 */
	public static function render_admin_section() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$result = get_transient( self::RESULT_TRANSIENT );
		if ( false !== $result ) {
			delete_transient( self::RESULT_TRANSIENT );
		}

		$years     = self::available_years();
		$languages = class_exists( 'TAKA_Platform_Translation_Packages' ) ? TAKA_Platform_Translation_Packages::language_labels() : array( 'de' => 'Deutsch', 'en' => 'English' );
		?>
		<?php if ( is_array( $result ) ) : ?>
			<div class="notice notice-<?php echo ! empty( $result['success'] ) ? 'success' : 'error'; ?>">
				<p><?php echo esc_html( $result['message'] ?? '' ); ?></p>
				<?php if ( ! empty( $result['log'] ) && is_array( $result['log'] ) ) : ?>
					<ul>
						<?php foreach ( array_slice( $result['log'], 0, 12 ) as $line ) : ?>
							<li><code><?php echo esc_html( $line ); ?></code></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php TAKA_Platform_Admin::admin_section_open( __( 'Export static archive', 'taka-platform' ), __( 'Create a ZIP archive with public TAKA pages that can be hosted without WordPress, PHP or live booking features.', 'taka-platform' ), false, 'taka-admin-section--technical', 'import-export-static-archive' ); ?>
			<?php if ( ! class_exists( 'ZipArchive' ) ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html__( 'Static archive export requires the PHP ZipArchive extension.', 'taka-platform' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::ACTION, self::NONCE ); ?>
				<table class="form-table" role="presentation"><tbody>
					<tr>
						<th scope="row"><label for="taka_static_archive_year"><?php echo esc_html__( 'Tour year', 'taka-platform' ); ?></label></th>
						<td>
							<select id="taka_static_archive_year" name="archive_year">
								<option value=""><?php echo esc_html__( 'All public events', 'taka-platform' ); ?></option>
								<?php foreach ( $years as $year ) : ?>
									<option value="<?php echo esc_attr( $year ); ?>"><?php echo esc_html( $year ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php echo esc_html__( 'Choose a past tour year or export all public event pages.', 'taka-platform' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="taka_static_archive_language"><?php echo esc_html__( 'Archive language', 'taka-platform' ); ?></label></th>
						<td>
							<select id="taka_static_archive_language" name="archive_language">
								<?php foreach ( $languages as $code => $label ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( 'de', $code ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</tbody></table>
				<?php submit_button( __( 'Export static archive', 'taka-platform' ), 'secondary', 'submit', false, class_exists( 'ZipArchive' ) ? array() : array( 'disabled' => 'disabled' ) ); ?>
			</form>
		<?php TAKA_Platform_Admin::admin_section_close(); ?>
		<?php
	}

	/**
	 * Handle the admin-post export action.
	 */
	public static function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::ACTION, self::NONCE );

		if ( ! class_exists( 'ZipArchive' ) ) {
			self::store_result( false, __( 'Static archive export requires the PHP ZipArchive extension.', 'taka-platform' ) );
			wp_safe_redirect( admin_url( 'admin.php?page=taka-tour-import-export' ) );
			exit;
		}

		$year = isset( $_POST['archive_year'] ) ? preg_replace( '/[^0-9]/', '', (string) wp_unslash( $_POST['archive_year'] ) ) : '';
		$lang = class_exists( 'TAKA_Platform_Translation_Packages' )
			? TAKA_Platform_Translation_Packages::sanitize_language( wp_unslash( $_POST['archive_language'] ?? 'de' ), 'de' )
			: sanitize_key( wp_unslash( $_POST['archive_language'] ?? 'de' ) );

		try {
			$result = self::build_archive( $year, $lang );
			self::store_result( true, sprintf( __( 'Static archive generated: %s', 'taka-platform' ), $result['filename'] ), $result['log'] );
			self::stream_zip( $result['zip_path'], $result['filename'], $result['cleanup_dir'] );
		} catch ( Exception $exception ) {
			self::store_result( false, $exception->getMessage() );
			wp_safe_redirect( admin_url( 'admin.php?page=taka-tour-import-export' ) );
			exit;
		}
	}

	/**
	 * Build the archive folder and ZIP.
	 *
	 * @param string $year Optional four-digit year filter.
	 * @param string $lang Language code.
	 * @return array
	 * @throws Exception When export fails.
	 */
	private static function build_archive( $year, $lang ) {
		if ( ! defined( 'TAKA_ARCHIVE_MODE' ) ) {
			define( 'TAKA_ARCHIVE_MODE', true );
		}
		if ( class_exists( 'TAKA_Platform_I18n' ) && method_exists( TAKA_Platform_I18n::instance(), 'set_current_language' ) ) {
			TAKA_Platform_I18n::instance()->set_current_language( $lang );
		}

		$events = self::events_for_archive( $year, $lang );
		if ( empty( $events ) ) {
			throw new Exception( esc_html__( 'No public events found for the selected archive scope.', 'taka-platform' ) );
		}

		$archive_name = 'taka-static-archive-' . ( '' !== $year ? $year : gmdate( 'Ymd-His' ) ) . '-' . $lang;
		$root_dir     = trailingslashit( get_temp_dir() ) . $archive_name . '-' . wp_generate_uuid4();
		$site_dir     = trailingslashit( $root_dir ) . $archive_name;
		$log          = array();

		if ( ! wp_mkdir_p( $site_dir ) ) {
			throw new Exception( esc_html__( 'Could not create temporary archive directory.', 'taka-platform' ) );
		}

		$organizers = self::collect_organizers( $events );
		$venues     = self::collect_venues( $events );
		$asset_map  = array();

		try {
			self::copy_known_assets( $site_dir, $log );

			self::write_page( $site_dir, 'index.html', self::render_index_page( $events, $year, $lang ), $asset_map, $log );
			self::write_page( $site_dir, 'events.html', self::render_schedule_page( $events, $lang ), $asset_map, $log );
			self::write_page( $site_dir, 'tickets.html', self::render_tickets_page( $events, $lang ), $asset_map, $log );

			foreach ( $events as $event ) {
				$relative = 'events/' . self::event_slug( $event ) . '.html';
				self::write_page( $site_dir, $relative, self::render_event_page( $event, $organizers, $venues, $lang ), $asset_map, $log );
			}

			foreach ( $organizers['items'] as $organizer ) {
				self::write_page( $site_dir, 'organizers/' . $organizer['_archive_slug'] . '.html', self::render_organizer_page( $organizer, $events, $lang ), $asset_map, $log );
			}

			foreach ( $venues['items'] as $venue ) {
				self::write_page( $site_dir, 'venues/' . $venue['_archive_slug'] . '.html', self::render_venue_page( $venue, $events, $lang ), $asset_map, $log );
			}

			$zip_path = trailingslashit( $root_dir ) . $archive_name . '.zip';
			self::zip_directory( $site_dir, $zip_path );
			$log[] = sprintf( 'ZIP created with %d event page(s).', count( $events ) );

			return array(
				'zip_path'    => $zip_path,
				'filename'    => basename( $zip_path ),
				'cleanup_dir' => $root_dir,
				'log'         => $log,
			);
		} catch ( Exception $exception ) {
			self::cleanup_dir( $root_dir );
			throw $exception;
		}
	}

	/**
	 * Get public events for one archive scope.
	 */
	private static function events_for_archive( $year, $lang ) {
		$events = TAKA_Platform_Data::events_for_language( $lang );
		$events = array_values(
			array_filter(
				$events,
				static function ( $event ) use ( $year ) {
					if ( 'draft' === ( $event['status'] ?? '' ) || ! empty( $event['private'] ) ) {
						return false;
					}
					if ( '' === $year ) {
						return true;
					}
					$date = (string) ( $event['date_start'] ?? $event['date'] ?? '' );
					return 0 === strpos( $date, $year );
				}
			)
		);
		return $events;
	}

	/**
	 * Render the static archive index.
	 */
	private static function render_index_page( $events, $year, $lang ) {
		$title = '' !== $year ? sprintf( __( 'TAKA archive %s', 'taka-platform' ), $year ) : __( 'TAKA static archive', 'taka-platform' );
		$body  = '<main class="taka-tour-page taka-static-archive">';
		$body .= self::render_archive_notice( $lang );
		$sections = TAKA_Platform_Data::get_homepage_sections();
		if ( ! empty( $sections ) ) {
			foreach ( $sections as $section ) {
				$body .= TAKA_Platform_Data::render_homepage_section( $section, array( 'seminars' => $events ) );
			}
		} else {
			$body .= '<section class="taka-section taka-archive-hero">';
			$body .= '<p class="taka-kicker">' . esc_html( taka_tour_translate( 'archive.kicker', 'Static archive', $lang ) ) . '</p>';
			$body .= '<h1>' . esc_html( $title ) . '</h1>';
			$body .= '<p>' . esc_html( taka_tour_translate( 'archive.intro', 'This is a read-only archive of a completed TAKA tour. Booking, checkout and live account features are no longer available.', $lang ) ) . '</p>';
			$body .= '</section>';
		}
		$body .= self::render_event_link_grid( $events, '.', $lang );
		$body .= '</main>';

		return self::render_shell( $title, $body, 'index.html', $lang );
	}

	/**
	 * Render the static event overview page.
	 */
	private static function render_schedule_page( $events, $lang ) {
		$title = taka_tour_translate( 'tour.headline', 'Seminars in Europe', $lang );
		$body  = '<main class="taka-tour-page taka-static-archive">';
		$body .= self::render_archive_notice( $lang );
		$body .= taka_tour_render_template( 'tour-schedule.php', array( 'seminars' => $events ) );
		$body .= self::render_event_link_grid( $events, '.', $lang );
		$body .= '</main>';
		return self::render_shell( $title, $body, 'events.html', $lang );
	}

	/**
	 * Render the read-only ticket overview.
	 */
	private static function render_tickets_page( $events, $lang ) {
		$title = taka_tour_translate( 'tickets.heading', 'Book your seminar', $lang );
		$body  = '<main class="taka-tour-page taka-static-archive">';
		$body .= self::render_archive_notice( $lang );
		$body .= taka_tour_render_template( 'tickets.php', array( 'seminars' => $events ) );
		$body .= '</main>';
		return self::render_shell( $title, $body, 'tickets.html', $lang );
	}

	/**
	 * Render one event detail page.
	 */
	private static function render_event_page( $event, $organizers, $venues, $lang ) {
		$title       = (string) ( $event['title'] ?? '' );
		$image       = (string) ( $event['ticket_overview_image'] ?? $event['image'] ?? '' );
		$time        = implode( ' - ', array_filter( array( $event['time_start'] ?? '', $event['time_end'] ?? '' ) ) );
		$organizer   = is_array( $event['organizer_full'] ?? null ) ? $event['organizer_full'] : array();
		$venue       = is_array( $event['venue_full'] ?? null ) ? $event['venue_full'] : array();
		$body        = '<main class="taka-tour-page taka-static-archive taka-static-event">';
		$body       .= self::render_archive_notice( $lang );
		$body       .= '<p class="taka-archive-back-link"><a href="' . esc_url( self::relative_url( 'events', 'index.html' ) ) . '">' . esc_html__( 'Back to archive overview', 'taka-platform' ) . '</a></p>';
		$body       .= '<article class="taka-section taka-static-event__article">';
		$body       .= '<p class="taka-kicker">' . esc_html( $event['city'] ?? $event['country'] ?? '' ) . '</p>';
		$body       .= '<h1>' . esc_html( $title ) . '</h1>';
		if ( ! empty( $event['subtitle'] ) ) {
			$body .= '<p class="taka-subtitle">' . esc_html( $event['subtitle'] ) . '</p>';
		}
		if ( '' !== $image ) {
			$body .= '<figure class="taka-ticket-event-panel__image"><img src="' . esc_url( $image ) . '" alt="' . esc_attr( $event['ticket_overview_image_alt'] ?? $title ) . '" loading="lazy"></figure>';
		}
		$body .= '<dl class="taka-ticket-meta-list">';
		$body .= self::definition_row( taka_tour_translate( 'event.date', 'Date', $lang ), $event['date'] ?? '' );
		$body .= self::definition_row( taka_tour_translate( 'event.time', 'Time', $lang ), $time );
		$body .= self::definition_row( taka_tour_translate( 'event.doors_open', 'Doors open', $lang ), $event['doors_open'] ?? '' );
		$body .= self::definition_row( taka_tour_translate( 'event.venue', 'Venue', $lang ), self::entity_link_or_text( $venue, $venues, 'venues', 'events' ) );
		$body .= self::definition_row( taka_tour_translate( 'event.address', 'Address', $lang ), $event['address'] ?? ( $venue['address'] ?? '' ) );
		$body .= self::definition_row( taka_tour_translate( 'event.organizer', 'Organizer', $lang ), self::entity_link_or_text( $organizer, $organizers, 'organizers', 'events' ) );
		$body .= '</dl>';
		if ( '' !== trim( (string) ( $event['description'] ?? '' ) ) ) {
			$body .= '<section class="taka-ticket-event-description"><h2>' . esc_html( taka_tour_translate( 'event.seminar_description', 'Seminar description', $lang ) ) . '</h2>' . wp_kses_post( wpautop( $event['description'] ) ) . '</section>';
		}
		if ( ! empty( $event['program_groups'] ) ) {
			$body .= self::render_program_groups( $event['program_groups'], $lang );
		}
		if ( ! empty( $event['gallery_urls'] ) && is_array( $event['gallery_urls'] ) ) {
			$body .= '<section class="taka-section taka-archive-gallery"><h2>' . esc_html__( 'Gallery', 'taka-platform' ) . '</h2><div class="taka-card-grid">';
			foreach ( $event['gallery_urls'] as $gallery_url ) {
				$body .= '<figure class="taka-ticket-card-photo"><img src="' . esc_url( $gallery_url ) . '" alt="" loading="lazy"></figure>';
			}
			$body .= '</div></section>';
		}
		$body .= taka_tour_render_template( 'partials/ticket-widget.php', array( 'event' => '', 'seminar' => $event, 'show_actions' => false ) );
		$body .= '</article></main>';

		return self::render_shell( $title, $body, 'events/' . self::event_slug( $event ) . '.html', $lang );
	}

	/**
	 * Render one organizer page.
	 */
	private static function render_organizer_page( $organizer, $events, $lang ) {
		$title = (string) ( $organizer['name'] ?? __( 'Organizer', 'taka-platform' ) );
		$body  = '<main class="taka-tour-page taka-static-archive"><article class="taka-section">';
		$body .= '<p class="taka-kicker">' . esc_html( taka_tour_translate( 'event.organizer', 'Organizer', $lang ) ) . '</p><h1>' . esc_html( $title ) . '</h1>';
		if ( ! empty( $organizer['logo'] ) ) {
			$body .= '<figure class="taka-ticket-card-photo"><img src="' . esc_url( $organizer['logo'] ) . '" alt="' . esc_attr( $title ) . '" loading="lazy"></figure>';
		}
		if ( ! empty( $organizer['description'] ) ) {
			$body .= wp_kses_post( wpautop( $organizer['description'] ) );
		}
		$body .= self::render_entity_details( $organizer, $lang );
		$body .= self::render_related_events( $events, $organizer, 'organizer_full', 'organizers', $lang );
		$body .= '</article></main>';
		return self::render_shell( $title, $body, 'organizers/' . $organizer['_archive_slug'] . '.html', $lang );
	}

	/**
	 * Render one venue page.
	 */
	private static function render_venue_page( $venue, $events, $lang ) {
		$title = (string) ( $venue['name'] ?? __( 'Venue', 'taka-platform' ) );
		$body  = '<main class="taka-tour-page taka-static-archive"><article class="taka-section">';
		$body .= '<p class="taka-kicker">' . esc_html( taka_tour_translate( 'event.venue', 'Venue', $lang ) ) . '</p><h1>' . esc_html( $title ) . '</h1>';
		if ( ! empty( $venue['image'] ) ) {
			$body .= '<figure class="taka-ticket-card-photo"><img src="' . esc_url( $venue['image'] ) . '" alt="' . esc_attr( $title ) . '" loading="lazy"></figure>';
		}
		if ( ! empty( $venue['description'] ) ) {
			$body .= wp_kses_post( wpautop( $venue['description'] ) );
		}
		$body .= self::render_entity_details( $venue, $lang );
		$body .= self::render_related_events( $events, $venue, 'venue_full', 'venues', $lang );
		$body .= '</article></main>';
		return self::render_shell( $title, $body, 'venues/' . $venue['_archive_slug'] . '.html', $lang );
	}

	/**
	 * Wrap a rendered body in a static HTML shell.
	 */
	private static function render_shell( $title, $body, $relative_page, $lang ) {
		$current_dir = dirname( $relative_page );
		$styles = array(
			self::relative_url( $current_dir, 'assets/css/frontend.css' ),
			self::relative_url( $current_dir, 'assets/css/tickets.css' ),
			self::relative_url( $current_dir, 'assets/css/taka-tour.css' ),
		);
		$scripts = array(
			self::relative_url( $current_dir, 'assets/js/frontend.js' ),
		);
		$home = self::relative_url( $current_dir, 'index.html' );
		$events = self::relative_url( $current_dir, 'events.html' );
		$tickets = self::relative_url( $current_dir, 'tickets.html' );

		$html  = '<!doctype html><html lang="' . esc_attr( $lang ) . '"><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ?: 'UTF-8' ) . '">';
		$html .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
		$html .= '<meta name="robots" content="noindex">';
		$html .= '<title>' . esc_html( wp_strip_all_tags( $title ) ) . '</title>';
		foreach ( $styles as $style ) {
			$html .= '<link rel="stylesheet" href="' . esc_url( $style ) . '">';
		}
		$html .= '<style>.taka-archive-banner{padding:12px 16px;margin:16px auto;max-width:1120px;border:1px solid #d7dbe3;border-radius:8px;background:#f7f8fa;color:#1f2937}.taka-static-nav{display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:center;padding:16px}.taka-static-nav a{font-weight:700}.taka-archive-event-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.taka-archive-event-list a{display:block;padding:14px;border:1px solid #d7dbe3;border-radius:8px;text-decoration:none}.taka-archive-back-link{max-width:1120px;margin:16px auto}.taka-static-event__article{max-width:1120px;margin-left:auto;margin-right:auto}.taka-static-archive .taka-native-checkout,.taka-static-archive pretix-widget{display:none!important}</style>';
		$html .= '</head><body class="taka-static-archive-body">';
		$html .= '<nav class="taka-static-nav" aria-label="' . esc_attr__( 'Archive navigation', 'taka-platform' ) . '"><a href="' . esc_url( $home ) . '">' . esc_html__( 'Archive home', 'taka-platform' ) . '</a><a href="' . esc_url( $events ) . '">' . esc_html__( 'Events', 'taka-platform' ) . '</a><a href="' . esc_url( $tickets ) . '">' . esc_html__( 'Tickets', 'taka-platform' ) . '</a></nav>';
		$html .= $body;
		foreach ( $scripts as $script ) {
			$html .= '<script src="' . esc_url( $script ) . '" defer></script>';
		}
		$html .= '</body></html>';
		return $html;
	}

	/**
	 * Render an archive-safe booking notice.
	 */
	private static function render_archive_notice( $lang ) {
		return '<div class="taka-archive-banner"><strong>' . esc_html( taka_tour_translate( 'archive.notice_title', 'Archived page', $lang ) ) . '</strong><br>' . esc_html( taka_tour_translate( 'archive.booking_unavailable', 'This event is archived. Booking is no longer available.', $lang ) ) . '</div>';
	}

	/**
	 * Render a grid linking to all exported event pages.
	 */
	private static function render_event_link_grid( $events, $current_dir, $lang ) {
		$html  = '<section class="taka-section taka-archive-events"><p class="taka-kicker">' . esc_html( taka_tour_translate( 'archive.events', 'Archived events', $lang ) ) . '</p><h2>' . esc_html__( 'Event pages', 'taka-platform' ) . '</h2><div class="taka-archive-event-list">';
		foreach ( $events as $event ) {
			$link = self::relative_url( $current_dir, 'events/' . self::event_slug( $event ) . '.html' );
			$html .= '<a href="' . esc_url( $link ) . '"><strong>' . esc_html( $event['title'] ?? '' ) . '</strong><span>' . esc_html( trim( (string) ( $event['date'] ?? '' ) . ' ' . ( $event['city'] ?? '' ) ) ) . '</span></a>';
		}
		$html .= '</div></section>';
		return $html;
	}

	/**
	 * Render program groups from an event.
	 */
	private static function render_program_groups( $groups, $lang ) {
		$html = '<section class="taka-program-summary"><h2>' . esc_html( taka_tour_translate( 'event.seminar_plan', 'Seminar plan', $lang ) ) . '</h2>';
		foreach ( $groups as $group ) {
			$html .= '<div class="taka-program-summary__day"><strong>' . esc_html( $group['label'] ?? '' ) . '</strong>';
			if ( ! empty( $group['date_label'] ) ) {
				$html .= '<span class="taka-program-summary__date">' . esc_html( $group['date_label'] ) . '</span>';
			}
			$html .= '<div class="taka-program-summary__items">';
			foreach ( $group['items'] ?? array() as $item ) {
				$time = implode( ' - ', array_filter( array( $item['time_start'] ?? '', $item['time_end'] ?? '' ) ) );
				$html .= '<div class="taka-program-summary__item"><span class="taka-program-summary__time">' . esc_html( $time ) . '</span><span class="taka-program-summary__title">' . esc_html( $item['title'] ?? '' ) . '</span></div>';
			}
			$html .= '</div></div>';
		}
		$html .= '</section>';
		return $html;
	}

	/**
	 * Render public entity rows.
	 */
	private static function render_entity_details( $entity, $lang ) {
		$rows = array(
			taka_tour_translate( 'event.address', 'Address', $lang ) => $entity['address'] ?? '',
			__( 'Email', 'taka-platform' ) => $entity['email'] ?? '',
			__( 'Phone', 'taka-platform' ) => $entity['phone'] ?? '',
			__( 'Website', 'taka-platform' ) => $entity['website'] ?? '',
		);
		$html = '<dl class="taka-ticket-meta-list">';
		foreach ( $rows as $label => $value ) {
			$html .= self::definition_row( $label, $value );
		}
		$html .= '</dl>';
		return $html;
	}

	/**
	 * Render events related to one entity.
	 */
	private static function render_related_events( $events, $entity, $field, $current_dir, $lang ) {
		$key = self::entity_key( $entity );
		$related = array_values(
			array_filter(
				$events,
				static function ( $event ) use ( $key, $field ) {
					return $key && $key === self::entity_key( is_array( $event[ $field ] ?? null ) ? $event[ $field ] : array() );
				}
			)
		);
		if ( empty( $related ) ) {
			return '';
		}
		return self::render_event_link_grid( $related, $current_dir, $lang );
	}

	/**
	 * Render one definition list row. The value may contain trusted archive HTML.
	 */
	private static function definition_row( $label, $value ) {
		if ( '' === trim( wp_strip_all_tags( (string) $value ) ) ) {
			return '';
		}
		return '<div class="taka-ticket-meta-row"><dt>' . esc_html( $label ) . '</dt><dd>' . wp_kses_post( (string) $value ) . '</dd></div>';
	}

	/**
	 * Link an entity to its exported page when possible.
	 */
	private static function entity_link_or_text( $entity, $collection, $folder, $current_dir ) {
		if ( empty( $entity['name'] ) ) {
			return '';
		}
		$key = self::entity_key( $entity );
		$slug = $collection['keys'][ $key ] ?? '';
		if ( '' === $slug ) {
			return esc_html( $entity['name'] );
		}
		$link = self::relative_url( $current_dir, $folder . '/' . $slug . '.html' );
		return '<a href="' . esc_url( $link ) . '">' . esc_html( $entity['name'] ) . '</a>';
	}

	/**
	 * Write one page and localize its asset URLs.
	 */
	private static function write_page( $site_dir, $relative, $html, &$asset_map, &$log ) {
		$relative    = ltrim( str_replace( '\\', '/', $relative ), '/' );
		$current_dir = dirname( $relative );
		$html        = self::strip_archive_comments( $html );
		$html        = self::localize_asset_urls( $html, $site_dir, $current_dir, $asset_map, $log );
		$path        = trailingslashit( $site_dir ) . $relative;
		$dir         = dirname( $path );
		if ( ! wp_mkdir_p( $dir ) ) {
			throw new Exception( sprintf( esc_html__( 'Could not create directory for %s.', 'taka-platform' ), $relative ) );
		}
		if ( false === file_put_contents( $path, $html ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			throw new Exception( sprintf( esc_html__( 'Could not write archive page %s.', 'taka-platform' ), $relative ) );
		}
		$log[] = 'Wrote ' . $relative;
	}

	/**
	 * Remove admin/debug comments before files are written to the public archive.
	 */
	private static function strip_archive_comments( $html ) {
		return preg_replace( '/<!--.*?-->/s', '', (string) $html );
	}

	/**
	 * Copy selected static frontend assets into the archive.
	 */
	private static function copy_known_assets( $site_dir, &$log ) {
		$assets = array(
			'assets/css/frontend.css',
			'assets/css/tickets.css',
			'assets/css/taka-tour.css',
			'assets/js/frontend.js',
		);
		foreach ( $assets as $asset ) {
			$source = TAKA_PLATFORM_PLUGIN_DIR . $asset;
			if ( ! file_exists( $source ) ) {
				$log[] = 'Skipped missing asset ' . $asset;
				continue;
			}
			$target = trailingslashit( $site_dir ) . $asset;
			wp_mkdir_p( dirname( $target ) );
			copy( $source, $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
			$log[] = 'Copied ' . $asset;
		}
	}

	/**
	 * Copy local media/plugin assets referenced in generated HTML and rewrite URLs.
	 */
	private static function localize_asset_urls( $html, $site_dir, $current_dir, &$asset_map, &$log ) {
		$html = preg_replace_callback(
			'/\b(src|href)=([\'"])([^\'"]+)\2/i',
			static function ( $matches ) use ( $site_dir, $current_dir, &$asset_map, &$log ) {
				$attribute = $matches[1];
				$quote     = $matches[2];
				$url       = html_entity_decode( $matches[3], ENT_QUOTES, 'UTF-8' );
				$local     = self::local_path_for_url( $url );
				if ( '' === $local ) {
					return $matches[0];
				}
				if ( ! isset( $asset_map[ $url ] ) ) {
					$basename = sanitize_file_name( basename( wp_parse_url( $url, PHP_URL_PATH ) ?: 'asset' ) );
					if ( '' === $basename ) {
						$basename = 'asset';
					}
					$target_rel = 'assets/media/' . substr( md5( $url ), 0, 10 ) . '-' . $basename;
					$target_abs = trailingslashit( $site_dir ) . $target_rel;
					wp_mkdir_p( dirname( $target_abs ) );
					if ( @copy( $local, $target_abs ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_copy
						$asset_map[ $url ] = $target_rel;
						$log[] = 'Copied media ' . $target_rel;
					} else {
						$log[] = 'Could not copy media ' . $url;
						return $matches[0];
					}
				}
				$rewritten = self::relative_url( $current_dir, $asset_map[ $url ] );
				return $attribute . '=' . $quote . esc_url( $rewritten ) . $quote;
			},
			$html
		);

		return preg_replace_callback(
			'/url\((["\']?)([^)"\']+)\1\)/i',
			static function ( $matches ) use ( $site_dir, $current_dir, &$asset_map, &$log ) {
				$quote = '' !== $matches[1] ? $matches[1] : "'";
				$url   = html_entity_decode( trim( $matches[2] ), ENT_QUOTES, 'UTF-8' );
				$local = self::local_path_for_url( $url );
				if ( '' === $local ) {
					return $matches[0];
				}
				if ( ! isset( $asset_map[ $url ] ) ) {
					$basename = sanitize_file_name( basename( wp_parse_url( $url, PHP_URL_PATH ) ?: 'asset' ) );
					if ( '' === $basename ) {
						$basename = 'asset';
					}
					$target_rel = 'assets/media/' . substr( md5( $url ), 0, 10 ) . '-' . $basename;
					$target_abs = trailingslashit( $site_dir ) . $target_rel;
					wp_mkdir_p( dirname( $target_abs ) );
					if ( @copy( $local, $target_abs ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_copy
						$asset_map[ $url ] = $target_rel;
						$log[] = 'Copied media ' . $target_rel;
					} else {
						$log[] = 'Could not copy media ' . $url;
						return $matches[0];
					}
				}
				$rewritten = self::relative_url( $current_dir, $asset_map[ $url ] );
				return 'url(' . $quote . esc_url( $rewritten ) . $quote . ')';
			},
			$html
		);
	}

	/**
	 * Resolve a URL to a readable local file path, when it belongs to this site.
	 */
	private static function local_path_for_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || 0 === strpos( $url, '#' ) || preg_match( '/^(mailto|tel|data|javascript):/i', $url ) ) {
			return '';
		}

		$clean_url = strtok( $url, '?' );
		$path      = wp_parse_url( $clean_url, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return '';
		}

		$uploads = wp_get_upload_dir();
		if ( ! empty( $uploads['baseurl'] ) && 0 === strpos( $clean_url, $uploads['baseurl'] ) ) {
			$relative = ltrim( substr( $clean_url, strlen( $uploads['baseurl'] ) ), '/' );
			$file     = trailingslashit( $uploads['basedir'] ) . rawurldecode( $relative );
			return is_readable( $file ) ? $file : '';
		}

		if ( 0 === strpos( $clean_url, TAKA_PLATFORM_PLUGIN_URL ) ) {
			$relative = ltrim( substr( $clean_url, strlen( TAKA_PLATFORM_PLUGIN_URL ) ), '/' );
			$file     = TAKA_PLATFORM_PLUGIN_DIR . rawurldecode( $relative );
			return is_readable( $file ) ? $file : '';
		}

		$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$url_host  = wp_parse_url( $clean_url, PHP_URL_HOST );
		if ( $url_host && $home_host && strtolower( $url_host ) !== strtolower( $home_host ) ) {
			return '';
		}

		if ( defined( 'ABSPATH' ) ) {
			$file = trailingslashit( ABSPATH ) . ltrim( rawurldecode( $path ), '/' );
			return is_readable( $file ) ? $file : '';
		}

		return '';
	}

	/**
	 * Create a relative URL from one exported page directory to another file.
	 */
	private static function relative_url( $from_dir, $to ) {
		$from_dir = trim( str_replace( '\\', '/', (string) $from_dir ), '/' );
		$to       = ltrim( str_replace( '\\', '/', (string) $to ), '/' );
		if ( '' === $from_dir || '.' === $from_dir ) {
			return $to;
		}
		$levels = substr_count( $from_dir, '/' ) + 1;
		return str_repeat( '../', $levels ) . $to;
	}

	/**
	 * Collect organizers referenced by exported events.
	 */
	private static function collect_organizers( $events ) {
		$items = array();
		$keys  = array();
		foreach ( $events as $event ) {
			$candidates = array();
			if ( is_array( $event['organizer_full'] ?? null ) ) {
				$candidates[] = $event['organizer_full'];
			}
			foreach ( (array) ( $event['organizer_relationships'] ?? array() ) as $relationship ) {
				if ( is_array( $relationship['organizer'] ?? null ) ) {
					$candidates[] = $relationship['organizer'];
				}
			}
			foreach ( $candidates as $organizer ) {
				self::add_entity_to_collection( $organizer, $items, $keys, 'organizer' );
			}
		}
		return array( 'items' => $items, 'keys' => $keys );
	}

	/**
	 * Collect venues referenced by exported events.
	 */
	private static function collect_venues( $events ) {
		$items = array();
		$keys  = array();
		foreach ( $events as $event ) {
			if ( is_array( $event['venue_full'] ?? null ) ) {
				self::add_entity_to_collection( $event['venue_full'], $items, $keys, 'venue' );
			}
		}
		return array( 'items' => $items, 'keys' => $keys );
	}

	/**
	 * Add one entity to a collection.
	 */
	private static function add_entity_to_collection( $entity, &$items, &$keys, $fallback ) {
		if ( empty( $entity['name'] ) ) {
			return;
		}
		$key = self::entity_key( $entity );
		if ( isset( $keys[ $key ] ) ) {
			return;
		}
		$slug = sanitize_title( $entity['slug'] ?? $entity['id'] ?? $entity['name'] ?? $fallback );
		if ( '' === $slug ) {
			$slug = $fallback;
		}
		$base = $slug;
		$index = 2;
		while ( isset( $items[ $slug ] ) ) {
			$slug = $base . '-' . $index;
			$index++;
		}
		$entity['_archive_key']  = $key;
		$entity['_archive_slug'] = $slug;
		$items[ $slug ] = $entity;
		$keys[ $key ] = $slug;
	}

	/**
	 * Return a stable entity key.
	 */
	private static function entity_key( $entity ) {
		if ( ! is_array( $entity ) || empty( $entity ) ) {
			return '';
		}
		foreach ( array( 'id', 'config_id', 'wp_post_id', 'slug', 'name' ) as $field ) {
			if ( '' !== trim( (string) ( $entity[ $field ] ?? '' ) ) ) {
				return strtolower( trim( (string) $field . ':' . (string) $entity[ $field ] ) );
			}
		}
		return '';
	}

	/**
	 * Return a safe event slug.
	 */
	private static function event_slug( $event ) {
		$slug = sanitize_title( $event['slug'] ?? $event['id'] ?? $event['title'] ?? 'event' );
		return '' !== $slug ? $slug : 'event';
	}

	/**
	 * Return archive year options.
	 */
	private static function available_years() {
		$years = array();
		foreach ( TAKA_Platform_Data::events_for_language( 'de' ) as $event ) {
			$date = (string) ( $event['date_start'] ?? $event['date'] ?? '' );
			if ( preg_match( '/^([0-9]{4})/', $date, $match ) ) {
				$years[] = $match[1];
			}
		}
		$years = array_values( array_unique( $years ) );
		rsort( $years );
		return $years;
	}

	/**
	 * Zip the export directory.
	 */
	private static function zip_directory( $source_dir, $zip_path ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			throw new Exception( esc_html__( 'Could not create ZIP file.', 'taka-platform' ) );
		}
		$source_dir = rtrim( $source_dir, '/\\' );
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source_dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ( $files as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}
			$file_path = $file->getRealPath();
			$relative  = substr( $file_path, strlen( $source_dir ) + 1 );
			$zip->addFile( $file_path, $relative );
		}
		$zip->close();
	}

	/**
	 * Stream the generated ZIP and clean temporary files.
	 */
	private static function stream_zip( $zip_path, $filename, $cleanup_dir ) {
		if ( ! is_readable( $zip_path ) ) {
			self::cleanup_dir( $cleanup_dir );
			wp_die( esc_html__( 'The generated ZIP file is not readable.', 'taka-platform' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . filesize( $zip_path ) );
		readfile( $zip_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		self::cleanup_dir( $cleanup_dir );
		exit;
	}

	/**
	 * Remove a temporary export directory.
	 */
	private static function cleanup_dir( $dir ) {
		$dir = (string) $dir;
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return;
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $file ) {
			if ( $file->isDir() ) {
				rmdir( $file->getRealPath() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			} else {
				unlink( $file->getRealPath() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	/**
	 * Store one admin result message.
	 */
	private static function store_result( $success, $message, $log = array() ) {
		set_transient(
			self::RESULT_TRANSIENT,
			array(
				'success' => (bool) $success,
				'message' => (string) $message,
				'log'     => array_values( (array) $log ),
			),
			MINUTE_IN_SECONDS * 10
		);
	}
}

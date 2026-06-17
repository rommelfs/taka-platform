<?php
/**
 * Plugin bootstrap and shortcode registration.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Plugin {
	private static $instance = null;
	private $renderer;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->renderer = new TAKA_Platform_Renderer();
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_shortcode_assets' ), 20 );
		add_action( 'wp_head', array( $this, 'print_hreflang_links' ) );
		$this->register_shortcodes();
	}


	private function register_shortcodes() {
		$map = array(
			'taka_homepage' => 'homepage',
			'taka_tour_schedule' => 'tour_schedule',
			'taka_tickets' => 'tickets',
			'taka_sponsor' => 'sponsor',
			'taka_language_switcher' => 'language_switcher',
			'taka_platform_homepage' => 'homepage',
			'taka_platform_schedule' => 'tour_schedule',
			'taka_platform_tickets' => 'tickets',
			'taka_platform_sponsor' => 'sponsor',
			'taka_platform_language_switcher' => 'language_switcher',
			'event_tour_homepage' => 'homepage',
			'event_tour_schedule' => 'tour_schedule',
			'event_tour_tickets' => 'tickets',
			'event_tour_sponsor' => 'sponsor',
			'event_tour_language_switcher' => 'language_switcher',
			'taka_organizer_dashboard' => 'organizer_dashboard',
			'taka_platform_organizer_dashboard' => 'organizer_dashboard',
			'event_tour_organizer_dashboard' => 'organizer_dashboard',
		);
		foreach ( $map as $shortcode => $method ) {
			add_shortcode( $shortcode, array( $this->renderer, $method ) );
		}
	}

	public function register_assets() {
		wp_register_style( 'taka-platform', TAKA_PLATFORM_PLUGIN_URL . 'assets/css/frontend.css', array(), TAKA_PLATFORM_VERSION );
		wp_register_style( 'taka-platform-language-switcher', TAKA_PLATFORM_PLUGIN_URL . 'assets/css/language-switcher.css', array( 'taka-platform' ), TAKA_PLATFORM_VERSION );
		wp_register_style( 'taka-platform-tickets', TAKA_PLATFORM_PLUGIN_URL . 'assets/css/tickets.css', array( 'taka-platform' ), TAKA_PLATFORM_VERSION );
		wp_register_script( 'taka-platform', TAKA_PLATFORM_PLUGIN_URL . 'assets/js/frontend.js', array(), TAKA_PLATFORM_VERSION, true );
		wp_register_script( 'taka-platform-language-switcher', TAKA_PLATFORM_PLUGIN_URL . 'assets/js/language-switcher.js', array( 'taka-platform' ), TAKA_PLATFORM_VERSION, true );
		wp_register_script( 'taka-platform-tickets', TAKA_PLATFORM_PLUGIN_URL . 'assets/js/tickets.js', array( 'taka-platform' ), TAKA_PLATFORM_VERSION, true );
		wp_register_script( 'taka-platform-media-fields', TAKA_PLATFORM_PLUGIN_URL . 'assets/js/media-fields.js', array(), TAKA_PLATFORM_VERSION, true );
		wp_register_style( 'taka-tour-pretix', 'https://pretix.eu/kleinerwald/2026takakonz/widget/v2.css', array(), TAKA_PLATFORM_VERSION );
		wp_register_script( 'taka-tour-pretix', 'https://pretix.eu/widget/v2.de.js', array(), TAKA_PLATFORM_VERSION, true );
	}

	/**
	 * Enqueue assets early for posts/pages that contain plugin shortcodes.
	 *
	 * This ensures the Pretix widget loader is printed by WordPress even when the
	 * widgets are rendered later from shortcode templates.
	 *
	 * @return void
	 */
	public function enqueue_shortcode_assets() {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$shortcodes = array( 'taka_homepage', 'taka_tour_schedule', 'taka_tickets', 'taka_sponsor', 'taka_language_switcher', 'taka_platform_homepage', 'taka_platform_schedule', 'taka_platform_tickets', 'taka_platform_sponsor', 'taka_platform_language_switcher', 'event_tour_homepage', 'event_tour_schedule', 'event_tour_tickets', 'event_tour_sponsor', 'event_tour_language_switcher', 'taka_organizer_dashboard', 'taka_platform_organizer_dashboard', 'event_tour_organizer_dashboard' );
		$matches    = array_filter(
			$shortcodes,
			static function ( $shortcode ) use ( $post ) {
				return has_shortcode( $post->post_content, $shortcode );
			}
		);

		if ( empty( $matches ) ) {
			return;
		}

		wp_enqueue_style( 'taka-platform' );
		wp_enqueue_style( 'taka-platform-language-switcher' );
		wp_enqueue_script( 'taka-platform' );
		wp_enqueue_script( 'taka-platform-language-switcher' );

		if ( has_shortcode( $post->post_content, 'taka_homepage' ) || has_shortcode( $post->post_content, 'taka_tour_schedule' ) || has_shortcode( $post->post_content, 'taka_tickets' ) || has_shortcode( $post->post_content, 'taka_platform_homepage' ) || has_shortcode( $post->post_content, 'taka_platform_schedule' ) || has_shortcode( $post->post_content, 'taka_platform_tickets' ) || has_shortcode( $post->post_content, 'event_tour_homepage' ) || has_shortcode( $post->post_content, 'event_tour_schedule' ) || has_shortcode( $post->post_content, 'event_tour_tickets' ) ) {
			wp_enqueue_style( 'taka-platform-tickets' );
			wp_enqueue_script( 'taka-platform-tickets' );
			wp_enqueue_style( 'taka-tour-pretix' );
			wp_enqueue_script( 'taka-tour-pretix' );
		}
	}
	/**
	 * Print simple query-parameter hreflang links.
	 *
	 * @return void
	 */
	public function print_hreflang_links() {
		if ( ! is_singular() ) {
			return;
		}

		foreach ( TAKA_Platform_I18n::instance()->get_all_languages() as $lang ) {
			echo '<link rel="alternate" hreflang="' . esc_attr( $lang ) . '" href="' . esc_url( add_query_arg( 'taka_lang', $lang ) ) . '" />' . "\n";
		}
	}
}

<?php
/**
 * Plugin bootstrap and shortcode registration.
 */

defined( 'ABSPATH' ) || exit;

class Taka_Tour_Plugin {
	private static $instance = null;
	private $renderer;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->renderer = new Taka_Tour_Renderer();
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_shortcode_assets' ), 20 );
		add_action( 'wp_head', array( $this, 'print_hreflang_links' ) );
		add_shortcode( 'taka_homepage', array( $this->renderer, 'homepage' ) );
		add_shortcode( 'taka_tour_schedule', array( $this->renderer, 'tour_schedule' ) );
		add_shortcode( 'taka_tickets', array( $this->renderer, 'tickets' ) );
		add_shortcode( 'taka_sponsor', array( $this->renderer, 'sponsor' ) );
		add_shortcode( 'taka_language_switcher', array( $this->renderer, 'language_switcher' ) );
	}

	public function register_assets() {
		wp_register_style( 'taka-tour', TAKA_TOUR_PLUGIN_URL . 'assets/css/taka-tour.css', array(), TAKA_TOUR_VERSION );
		wp_register_script( 'taka-tour', TAKA_TOUR_PLUGIN_URL . 'assets/js/taka-tour.js', array(), TAKA_TOUR_VERSION, true );
		wp_register_style( 'taka-tour-pretix', 'https://pretix.eu/kleinerwald/2026takakonz/widget/v2.css', array(), TAKA_TOUR_VERSION );
		wp_register_script( 'taka-tour-pretix', 'https://pretix.eu/widget/v2.de.js', array(), TAKA_TOUR_VERSION, true );
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

		$shortcodes = array( 'taka_homepage', 'taka_tour_schedule', 'taka_tickets', 'taka_sponsor', 'taka_language_switcher' );
		$matches    = array_filter(
			$shortcodes,
			static function ( $shortcode ) use ( $post ) {
				return has_shortcode( $post->post_content, $shortcode );
			}
		);

		if ( empty( $matches ) ) {
			return;
		}

		wp_enqueue_style( 'taka-tour' );
		wp_enqueue_script( 'taka-tour' );

		if ( has_shortcode( $post->post_content, 'taka_homepage' ) || has_shortcode( $post->post_content, 'taka_tour_schedule' ) || has_shortcode( $post->post_content, 'taka_tickets' ) ) {
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

		foreach ( Taka_Tour_I18n::instance()->get_all_languages() as $lang ) {
			echo '<link rel="alternate" hreflang="' . esc_attr( $lang ) . '" href="' . esc_url( add_query_arg( 'taka_lang', $lang ) ) . '" />' . "\n";
		}
	}
}

<?php
/**
 * Shortcode renderer.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Renderer {
	private function enqueue_base() {
		wp_enqueue_style( 'taka-platform' );
		wp_enqueue_style( 'taka-platform-language-switcher' );
		wp_enqueue_script( 'taka-platform' );
		wp_enqueue_script( 'taka-platform-language-switcher' );
	}

	private function enqueue_pretix() {
		wp_enqueue_style( 'taka-platform-tickets' );
		wp_enqueue_script( 'taka-platform-tickets' );
		wp_enqueue_style( 'taka-tour-pretix' );
		wp_enqueue_script( 'taka-tour-pretix' );
	}

	private function render_page_shell( $template, $args = array() ) {
		return '<div class="taka-tour-page taka-tour-page--shortcode">'
			. taka_tour_render_site_header()
			. taka_tour_render_template( $template, $args )
			. '</div>';
	}

	public function homepage() {
		$this->enqueue_base();
		$this->enqueue_pretix();
		return taka_tour_render_template( 'homepage.php', array( 'seminars' => TAKA_Platform_Data::events_for_language() ) );
	}

	public function tour_schedule() {
		$this->enqueue_base();
		$this->enqueue_pretix();
		ob_start();
		do_action( 'taka_platform_before_schedule' );
		echo $this->render_page_shell( 'tour-schedule.php', array( 'seminars' => TAKA_Platform_Data::events_for_language() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		do_action( 'taka_platform_after_schedule' );
		return ob_get_clean();
	}

	public function tickets() {
		$this->enqueue_base();
		$this->enqueue_pretix();
		return $this->render_page_shell( 'tickets.php', array( 'seminars' => TAKA_Platform_Data::ticketed_seminars() ) );
	}

	public function sponsor() {
		$this->enqueue_base();
		return $this->render_page_shell( 'sponsor.php' );
	}

	public function language_switcher() {
		$this->enqueue_base();
		return taka_tour_render_template( 'partials/language-switcher.php' );
	}

	public function organizer_dashboard() {
		$this->enqueue_base();
		return TAKA_Platform_Organizer_Dashboard::render();
	}
}

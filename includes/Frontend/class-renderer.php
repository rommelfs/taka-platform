<?php
/**
 * Shortcode renderer.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Renderer {
	private function enqueue_base() {
		wp_enqueue_style( 'taka-platform' );
		wp_enqueue_script( 'taka-platform' );
	}

	private function enqueue_pretix() {
		wp_enqueue_style( 'taka-tour-pretix' );
		wp_enqueue_script( 'taka-tour-pretix' );
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
		echo taka_tour_render_template( 'tour-schedule.php', array( 'seminars' => TAKA_Platform_Data::events_for_language() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		do_action( 'taka_platform_after_schedule' );
		return ob_get_clean();
	}

	public function tickets() {
		$this->enqueue_base();
		$this->enqueue_pretix();
		return taka_tour_render_template( 'tickets.php', array( 'seminars' => TAKA_Platform_Data::ticketed_seminars() ) );
	}

	public function sponsor() {
		$this->enqueue_base();
		return taka_tour_render_template( 'sponsor.php' );
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

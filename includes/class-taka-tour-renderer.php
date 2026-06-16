<?php
/**
 * Shortcode renderer.
 */

defined( 'ABSPATH' ) || exit;

class Taka_Tour_Renderer {
	private function enqueue_base() {
		wp_enqueue_style( 'taka-tour' );
		wp_enqueue_script( 'taka-tour' );
	}

	private function enqueue_pretix() {
		wp_enqueue_style( 'taka-tour-pretix' );
		wp_enqueue_script( 'taka-tour-pretix' );
	}

	public function homepage() {
		$this->enqueue_base();
		$this->enqueue_pretix();
		return taka_tour_render_template( 'homepage.php', array( 'seminars' => Taka_Tour_Data::seminars_for_language() ) );
	}

	public function tour_schedule() {
		$this->enqueue_base();
		$this->enqueue_pretix();
		return taka_tour_render_template( 'tour-schedule.php', array( 'seminars' => Taka_Tour_Data::seminars_for_language() ) );
	}

	public function tickets() {
		$this->enqueue_base();
		$this->enqueue_pretix();
		return taka_tour_render_template( 'tickets.php', array( 'seminars' => Taka_Tour_Data::ticketed_seminars() ) );
	}

	public function sponsor() {
		$this->enqueue_base();
		return taka_tour_render_template( 'sponsor.php' );
	}

	public function language_switcher() {
		$this->enqueue_base();
		return taka_tour_render_template( 'partials/language-switcher.php' );
	}
}

<?php
/**
 * Frontend header and future menu bar.
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="top" class="taka-page-top" aria-hidden="true"></div>
<header class="taka-site-header" data-taka-site-header>
	<div class="taka-site-header__inner">
		<nav class="taka-site-nav" aria-label="<?php echo esc_attr( taka_tour_translate( 'navigation.primary', 'Hauptnavigation' ) ); ?>">
			<a class="taka-site-nav__link" href="#top" data-taka-scroll-top><?php echo esc_html( taka_tour_translate( 'navigation.home', 'Home' ) ); ?></a>
		</nav>
		<div class="taka-site-header__language">
			<?php echo taka_tour_render_template( 'partials/language-switcher.php', array( 'context' => 'header' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
</header>

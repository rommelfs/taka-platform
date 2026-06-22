<?php
/**
 * Complete homepage template.
 */

defined( 'ABSPATH' ) || exit;
$sections = TAKA_Platform_Data::get_homepage_sections();
?>
<div class="taka-tour-page">
	<?php foreach ( $sections as $section ) : ?>
		<?php echo TAKA_Platform_Data::render_homepage_section( $section, array( 'seminars' => $seminars ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endforeach; ?>
</div>

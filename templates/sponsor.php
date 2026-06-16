<?php
/**
 * Sponsor section.
 */

defined( 'ABSPATH' ) || exit;

$sponsor_venue = Taka_Tour_Data::get_venue( 'kanso-konz' );
$sponsor_url   = $sponsor_venue['website'] ?? 'https://kan.so';
?>
<section class="taka-section taka-sponsor taka-sponsor-section" id="sponsor">
	<div class="taka-centered-section-inner">
		<p class="taka-kicker"><?php echo esc_html( taka_tour_translate( 'sections.sponsor.kicker', 'Sponsor' ) ); ?></p>
		<h2><?php echo esc_html( taka_tour_translate( 'sections.sponsor.headline', 'kanso' ) ); ?></h2>
		<p><?php echo esc_html( taka_tour_translate( 'sections.sponsor.text', 'Zentrum für Körper, Geist und Seele in Konz.' ) ); ?></p>
		<?php if ( '' !== $sponsor_url ) : ?>
			<a href="<?php echo esc_url( $sponsor_url ); ?>" class="taka-text-link" rel="noopener"><?php echo esc_html( taka_tour_translate( 'sections.sponsor.link_text', 'kan.so' ) ); ?></a>
		<?php endif; ?>
	</div>
</section>

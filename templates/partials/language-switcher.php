<?php
/**
 * Language switcher partial.
 */

defined( 'ABSPATH' ) || exit;

$current = taka_tour_current_language();
$items   = Taka_Tour_I18n::instance()->get_language_switcher_items();
?>
<nav class="taka-language-switcher" aria-label="<?php echo esc_attr( taka_tour_translate( 'language.switcher_label', 'Sprache wählen' ) ); ?>">
	<?php foreach ( $items as $item ) : ?>
		<?php $url = add_query_arg( 'taka_lang', $item['code'] ); ?>
		<a class="taka-language-switcher__link<?php echo $current === $item['code'] ? ' is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>" lang="<?php echo esc_attr( $item['code'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
	<?php endforeach; ?>
</nav>

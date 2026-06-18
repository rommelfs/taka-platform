<?php
/**
 * Compact icon language switcher partial.
 */

defined( 'ABSPATH' ) || exit;

$current = taka_tour_current_language();
$items   = Taka_Tour_I18n::instance()->get_language_switcher_items();
?>
<nav class="taka-language-menu" aria-label="<?php echo esc_attr( taka_tour_translate( 'language.switcher_label', 'Sprache wählen' ) ); ?>">
	<?php foreach ( $items as $index => $item ) : ?>
		<?php if ( 'dropdown' === $item['type'] ) : ?>
			<?php $active = in_array( $current, wp_list_pluck( $item['items'], 'code' ), true ); ?>
			<div class="taka-language-dropdown<?php echo $active ? ' is-active' : ''; ?>">
				<button class="taka-language-icon taka-language-dropdown-trigger<?php echo $active ? ' is-active' : ''; ?>" type="button" aria-label="<?php echo esc_attr( $item['label'] ); ?>" title="<?php echo esc_attr( strtok( $item['label'], '–' ) ); ?>" aria-expanded="false" data-taka-language-dropdown>
					<span aria-hidden="true"><?php echo esc_html( $item['icon'] ); ?>▼</span>
				</button>
				<div class="taka-language-dropdown-menu">
					<?php foreach ( $item['items'] as $dropdown_item ) : ?>
						<a class="taka-language-dropdown-item<?php echo $current === $dropdown_item['code'] ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'taka_lang', $dropdown_item['code'] ) ); ?>" lang="<?php echo esc_attr( $dropdown_item['code'] ); ?>"><?php echo esc_html( $dropdown_item['label'] ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>
		<?php else : ?>
			<a class="taka-language-icon<?php echo $current === $item['code'] ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'taka_lang', $item['code'] ) ); ?>" lang="<?php echo esc_attr( $item['code'] ); ?>" aria-label="<?php echo esc_attr( $item['label'] ); ?>" title="<?php echo esc_attr( $item['label'] ); ?>"><span aria-hidden="true"><?php echo esc_html( $item['icon'] ); ?></span></a>
		<?php endif; ?>
	<?php endforeach; ?>
</nav>

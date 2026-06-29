<?php
/**
 * Shared TAKA admin collapsible section renderer.
 *
 * Use this component for every collapsible admin UI section instead of writing
 * custom <details> markup, so defaults, validation expansion, persistence and
 * visual styling stay consistent across all platform screens.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Admin_Collapsible_Section {
	const STATE_EXPANDED  = 'expanded';
	const STATE_COLLAPSED = 'collapsed';

	/**
	 * Open a shared collapsible admin section.
	 *
	 * @param array $args Section arguments.
	 * @return void
	 */
	public static function open( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'id'                   => '',
				'title'                => '',
				'icon'                 => '',
				'help_text'            => '',
				'default_state'        => self::STATE_EXPANDED,
				'auto_expand_on_error' => true,
				'remember_preference'  => true,
				'class'                => '',
				'attributes'           => array(),
			)
		);

		$default_state = self::normalize_state( $args['default_state'] );
		$is_open       = self::STATE_EXPANDED === $default_state;
		$section_id    = self::section_id( $args['id'], $args['title'] );
		$classes       = self::classes( $args['class'], $default_state );
		$attributes    = array_merge(
			is_array( $args['attributes'] ) ? $args['attributes'] : array(),
			array(
				'class'                                       => $classes,
				'data-taka-admin-section'                     => '1',
				'data-taka-admin-section-key'                 => $section_id,
				'data-taka-admin-section-default-state'       => $default_state,
				'data-taka-admin-section-auto-expand-error'   => ! empty( $args['auto_expand_on_error'] ) ? '1' : '0',
				'data-taka-admin-section-remember-preference' => ! empty( $args['remember_preference'] ) ? '1' : '0',
			)
		);

		echo '<details' . self::attributes( $attributes ) . ( $is_open ? ' open' : '' ) . '>';
		echo '<summary class="taka-admin-section__summary">';
		echo '<span class="taka-admin-section__chevron" aria-hidden="true"></span>';
		if ( '' !== trim( (string) $args['icon'] ) ) {
			echo '<span class="taka-admin-section__icon" aria-hidden="true">' . esc_html( $args['icon'] ) . '</span>';
		}
		echo '<span class="taka-admin-section__title">' . esc_html( $args['title'] ) . '</span>';
		echo '</summary>';
		if ( '' !== trim( (string) $args['help_text'] ) ) {
			echo '<p class="description taka-admin-section__description">' . esc_html( $args['help_text'] ) . '</p>';
		}
		echo '<div class="taka-admin-section__body">';
	}

	/**
	 * Close a shared collapsible admin section.
	 *
	 * @return void
	 */
	public static function close() {
		echo '</div></details>';
	}

	private static function normalize_state( $state ) {
		return self::STATE_COLLAPSED === $state || false === $state ? self::STATE_COLLAPSED : self::STATE_EXPANDED;
	}

	private static function section_id( $id, $title ) {
		$id = '' !== trim( (string) $id ) ? (string) $id : 'section-' . substr( md5( wp_strip_all_tags( (string) $title ) ), 0, 12 );
		return sanitize_key( $id );
	}

	private static function classes( $class, $default_state ) {
		$classes = array(
			'taka-admin-section',
			'taka-admin-section--default-' . $default_state,
		);

		foreach ( preg_split( '/\s+/', (string) $class ) as $item ) {
			$item = sanitize_html_class( $item );
			if ( '' !== $item ) {
				$classes[] = $item;
			}
		}

		return implode( ' ', array_unique( $classes ) );
	}

	private static function attributes( $attributes ) {
		$html = '';
		foreach ( $attributes as $name => $value ) {
			$html .= ' ' . sanitize_key( $name ) . '="' . esc_attr( (string) $value ) . '"';
		}
		return $html;
	}
}

<?php
/**
 * Support helpers for TAKA Platform.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render a template with scoped variables.
 *
 * @param string $template Template file relative to templates directory.
 * @param array  $args     Template variables.
 * @return string
 */
function taka_tour_render_template( $template, $args = array() ) {
	$template_path = apply_filters( 'taka_platform_template_path', TAKA_PLATFORM_PLUGIN_DIR . 'templates/' . ltrim( $template, '/' ), $template );

	if ( ! file_exists( $template_path ) ) {
		return '';
	}

	ob_start();
	extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
	include $template_path;
	return ob_get_clean();
}

/**
 * Render a TAKA Platform template with scoped variables.
 *
 * The legacy taka_tour_* helper remains available for existing templates and
 * third-party customizations.
 *
 * @param string $template Template file relative to templates directory.
 * @param array  $args     Template variables.
 * @return string
 */
function taka_platform_render_template( $template, $args = array() ) {
	return taka_tour_render_template( $template, $args );
}

/**
 * Return allowed HTML for rich template text.
 *
 * @return array
 */
function taka_tour_allowed_html() {
	return array(
		'a'      => array(
			'href'   => array(),
			'target' => array(),
			'rel'    => array(),
			'class'  => array(),
		),
		'br'     => array(),
		'em'     => array(),
		'strong' => array(),
		'span'          => array( 'class' => array() ),
		'pretix-widget' => array( 'event' => array() ),
	);

}

/**
 * Return allowed HTML for rich TAKA Platform text.
 *
 * @return array
 */
function taka_platform_allowed_html() {
	return taka_tour_allowed_html();
}

/**
 * Return allowed HTML for trusted WordPress oEmbed video output.
 *
 * @return array
 */
function taka_tour_video_embed_allowed_html() {
	$allowed = taka_tour_allowed_html();
	$allowed['iframe'] = array(
		'allow'           => array(),
		'allowfullscreen' => array(),
		'class'           => array(),
		'frameborder'     => array(),
		'height'          => array(),
		'loading'         => array(),
		'referrerpolicy'  => array(),
		'src'             => array(),
		'title'           => array(),
		'width'           => array(),
	);
	$allowed['div'] = array( 'class' => array(), 'style' => array() );
	$allowed['figure'] = array( 'class' => array() );
	$allowed['figcaption'] = array( 'class' => array() );
	$allowed['p'] = array( 'class' => array() );
	$allowed['blockquote'] = array( 'class' => array(), 'cite' => array() );
	return $allowed;
}

/**
 * Return allowed HTML for trusted TAKA Platform oEmbed video output.
 *
 * @return array
 */
function taka_platform_video_embed_allowed_html() {
	return taka_tour_video_embed_allowed_html();
}

/**
 * Translate a plain-text value for the active TAKA Platform language.
 *
 * @param string      $key      Translation key.
 * @param string      $fallback German fallback text.
 * @param string|null $lang     Optional language code.
 * @return string
 */
function taka_tour_translate( $key, $fallback, $lang = null ) {
	return TAKA_Platform_I18n::instance()->translate( $key, $fallback, $lang );
}

/**
 * Generic platform translation helper.
 *
 * @param string $key      Translation key.
 * @param string $fallback Fallback text.
 * @param string|null $lang Optional language code.
 * @return string
 */
function taka_platform_translate( $key, $fallback = '', $lang = null ) {
	return taka_tour_translate( $key, $fallback, $lang );
}

/**
 * Return the active TAKA Platform language.
 *
 * @return string
 */
function taka_tour_current_language() {
	return TAKA_Platform_I18n::instance()->get_current_language();
}

/**
 * Return the active TAKA Platform language.
 *
 * @return string
 */
function taka_platform_current_language() {
	return taka_tour_current_language();
}

/**
 * Resolve scalar or per-language dynamic content values.
 *
 * @param mixed  $value             Scalar string or array keyed by language.
 * @param string $language          Desired language.
 * @param string $fallback_language Fallback language.
 * @return string
 */
function taka_platform_get_translated_value( $value, $language = null, $fallback_language = 'en' ) {
	$language = $language ?: taka_tour_current_language();
	if ( is_array( $value ) ) {
		foreach ( array( $language, $fallback_language, 'de', 'en' ) as $lang ) {
			if ( isset( $value[ $lang ] ) && '' !== trim( (string) $value[ $lang ] ) ) {
				return (string) $value[ $lang ];
			}
		}
		foreach ( $value as $candidate ) {
			if ( ! is_array( $candidate ) && '' !== trim( (string) $candidate ) ) {
				return (string) $candidate;
			}
		}
		return '';
	}
	return (string) $value;
}

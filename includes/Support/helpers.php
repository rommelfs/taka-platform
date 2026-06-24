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
 * Render the shared frontend header once per request.
 *
 * @param bool $once Whether repeated calls should return an empty string.
 * @return string
 */
function taka_tour_render_site_header( $once = true ) {
	static $rendered = false;
	if ( $once && $rendered ) {
		return '';
	}
	$rendered = true;
	return taka_tour_render_template( 'partials/site-header.php' );
}

/**
 * Render the shared frontend header once per request.
 *
 * @param bool $once Whether repeated calls should return an empty string.
 * @return string
 */
function taka_platform_render_site_header( $once = true ) {
	return taka_tour_render_site_header( $once );
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
 * Build a language switch URL from the current frontend request.
 *
 * WordPress/PHP cannot see URL fragments such as #tickets/helsinki, so the
 * frontend enhancer appends the active fragment right before navigation.
 *
 * @param string      $language Target language code.
 * @param string|null $url      Optional base URL.
 * @return string
 */
function taka_tour_language_url( $language, $url = null ) {
	$language = sanitize_key( $language );
	if ( '' === $language ) {
		return '';
	}

	if ( null === $url ) {
		$request_uri = '/';
		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}
		$url = home_url( $request_uri );
	}

	return add_query_arg( 'taka_lang', $language, remove_query_arg( 'taka_lang', $url ) );
}

/**
 * Build a language switch URL from the current frontend request.
 *
 * @param string      $language Target language code.
 * @param string|null $url      Optional base URL.
 * @return string
 */
function taka_platform_language_url( $language, $url = null ) {
	return taka_tour_language_url( $language, $url );
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

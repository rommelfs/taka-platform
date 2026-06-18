<?php
/**
 * Manual/fallback translation service that copies source text into missing targets.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Manual_Translation_Service implements TAKA_Platform_Translation_Service_Interface {
	public function translate_text( $text, $source_lang, $target_lang ) {
		$translated = (string) $text;
		return apply_filters( 'taka_platform_translate_text', $translated, $text, $source_lang, $target_lang );
	}

	public function translate_fields( $fields, $source_lang, $target_langs ) {
		$out = array();
		foreach ( (array) $fields as $key => $value ) {
			$out[ $key ] = is_array( $value ) ? $value : array( $source_lang => (string) $value );
			$source = $out[ $key ][ $source_lang ] ?? taka_platform_get_translated_value( $out[ $key ], $source_lang, $source_lang );
			foreach ( (array) $target_langs as $target_lang ) {
				if ( empty( $out[ $key ][ $target_lang ] ) && '' !== (string) $source ) {
					$out[ $key ][ $target_lang ] = $this->translate_text( $source, $source_lang, $target_lang );
				}
			}
		}
		return $out;
	}
}

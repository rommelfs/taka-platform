<?php
/**
 * Translation service contract for future automated providers.
 */

defined( 'ABSPATH' ) || exit;

interface TAKA_Platform_Translation_Service_Interface {
	public function translate_text( $text, $source_lang, $target_lang );
	public function translate_fields( $fields, $source_lang, $target_langs );
}

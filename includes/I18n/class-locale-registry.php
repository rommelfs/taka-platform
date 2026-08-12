<?php
/**
 * Central ISO locale registry used by country and language fields.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Locale_Registry {
	/** ISO 639-1 languages available for source and spoken-language fields. */
	public static function language_labels() {
		return array(
			'aa' => 'Afar', 'ab' => 'Abkhazian', 'ae' => 'Avestan', 'af' => 'Afrikaans', 'ak' => 'Akan',
			'am' => 'Amharic', 'an' => 'Aragonese', 'ar' => 'Arabic', 'as' => 'Assamese', 'av' => 'Avaric',
			'ay' => 'Aymara', 'az' => 'Azerbaijani', 'ba' => 'Bashkir', 'be' => 'Belarusian', 'bg' => 'Bulgarian',
			'bh' => 'Bihari languages', 'bi' => 'Bislama', 'bm' => 'Bambara', 'bn' => 'Bengali', 'bo' => 'Tibetan',
			'br' => 'Breton', 'bs' => 'Bosnian', 'ca' => 'Catalan', 'ce' => 'Chechen', 'ch' => 'Chamorro',
			'co' => 'Corsican', 'cr' => 'Cree', 'cs' => 'Czech', 'cu' => 'Church Slavic', 'cv' => 'Chuvash',
			'cy' => 'Welsh', 'da' => 'Danish', 'de' => 'Deutsch', 'dv' => 'Divehi', 'dz' => 'Dzongkha',
			'ee' => 'Ewe', 'el' => 'Greek', 'en' => 'English', 'eo' => 'Esperanto', 'es' => 'Español',
			'et' => 'Estonian', 'eu' => 'Basque', 'fa' => 'Persian', 'ff' => 'Fulah', 'fi' => 'Suomi',
			'fj' => 'Fijian', 'fo' => 'Faroese', 'fr' => 'Français', 'fy' => 'Western Frisian', 'ga' => 'Irish',
			'gd' => 'Gaelic', 'gl' => 'Galician', 'gn' => 'Guarani', 'gu' => 'Gujarati', 'gv' => 'Manx',
			'ha' => 'Hausa', 'he' => 'Hebrew', 'hi' => 'Hindi', 'ho' => 'Hiri Motu', 'hr' => 'Croatian',
			'ht' => 'Haitian Creole', 'hu' => 'Hungarian', 'hy' => 'Armenian', 'hz' => 'Herero', 'ia' => 'Interlingua',
			'id' => 'Indonesian', 'ie' => 'Interlingue', 'ig' => 'Igbo', 'ii' => 'Sichuan Yi', 'ik' => 'Inupiaq',
			'io' => 'Ido', 'is' => 'Icelandic', 'it' => 'Italiano', 'iu' => 'Inuktitut', 'ja' => '日本語',
			'jv' => 'Javanese', 'ka' => 'Georgian', 'kg' => 'Kongo', 'ki' => 'Kikuyu', 'kj' => 'Kuanyama',
			'kk' => 'Kazakh', 'kl' => 'Kalaallisut', 'km' => 'Central Khmer', 'kn' => 'Kannada', 'ko' => 'Korean',
			'kr' => 'Kanuri', 'ks' => 'Kashmiri', 'ku' => 'Kurdish', 'kv' => 'Komi', 'kw' => 'Cornish',
			'ky' => 'Kirghiz', 'la' => 'Latin', 'lb' => 'Lëtzebuergesch', 'lg' => 'Ganda', 'li' => 'Limburgan',
			'ln' => 'Lingala', 'lo' => 'Lao', 'lt' => 'Lithuanian', 'lu' => 'Luba-Katanga', 'lv' => 'Latvian',
			'mg' => 'Malagasy', 'mh' => 'Marshallese', 'mi' => 'Māori', 'mk' => 'Macedonian', 'ml' => 'Malayalam',
			'mn' => 'Mongolian', 'mr' => 'Marathi', 'ms' => 'Malay', 'mt' => 'Maltese', 'my' => 'Burmese',
			'na' => 'Nauru', 'nb' => 'Norwegian Bokmål', 'nd' => 'North Ndebele', 'ne' => 'Nepali', 'ng' => 'Ndonga',
			'nl' => 'Nederlands', 'nn' => 'Norwegian Nynorsk', 'no' => 'Norwegian', 'nr' => 'South Ndebele', 'nv' => 'Navajo',
			'ny' => 'Chichewa', 'oc' => 'Occitan', 'oj' => 'Ojibwa', 'om' => 'Oromo', 'or' => 'Odia',
			'os' => 'Ossetian', 'pa' => 'Punjabi', 'pi' => 'Pali', 'pl' => 'Polish', 'ps' => 'Pashto',
			'pt' => 'Português', 'qu' => 'Quechua', 'rm' => 'Romansh', 'rn' => 'Rundi', 'ro' => 'Romanian',
			'ru' => 'Russian', 'rw' => 'Kinyarwanda', 'sa' => 'Sanskrit', 'sc' => 'Sardinian', 'sd' => 'Sindhi',
			'se' => 'Northern Sami', 'sg' => 'Sango', 'si' => 'Sinhala', 'sk' => 'Slovak', 'sl' => 'Slovenian',
			'sm' => 'Samoan', 'sn' => 'Shona', 'so' => 'Somali', 'sq' => 'Albanian', 'sr' => 'Serbian',
			'ss' => 'Swati', 'st' => 'Southern Sotho', 'su' => 'Sundanese', 'sv' => 'Swedish', 'sw' => 'Swahili',
			'ta' => 'Tamil', 'te' => 'Telugu', 'tg' => 'Tajik', 'th' => 'Thai', 'ti' => 'Tigrinya',
			'tk' => 'Turkmen', 'tl' => 'Tagalog', 'tn' => 'Tswana', 'to' => 'Tonga', 'tr' => 'Turkish',
			'ts' => 'Tsonga', 'tt' => 'Tatar', 'tw' => 'Twi', 'ty' => 'Tahitian', 'ug' => 'Uighur',
			'uk' => 'Ukrainian', 'ur' => 'Urdu', 'uz' => 'Uzbek', 've' => 'Venda', 'vi' => 'Vietnamese',
			'vo' => 'Volapük', 'wa' => 'Walloon', 'wo' => 'Wolof', 'xh' => 'Xhosa', 'yi' => 'Yiddish',
			'yo' => 'Yoruba', 'za' => 'Zhuang', 'zh' => 'Chinese', 'zu' => 'Zulu',
		);
	}

	/** Default public website languages; administrators can enable more. */
	public static function default_website_languages() {
		return array( 'de', 'en', 'nl', 'fr', 'lb', 'fi', 'it', 'ja' );
	}

	public static function sanitize_language_codes( $languages ) {
		$available = self::language_labels();
		$out = array();
		foreach ( (array) $languages as $language ) {
			$language = sanitize_key( (string) $language );
			if ( isset( $available[ $language ] ) ) { $out[] = $language; }
		}
		return array_values( array_unique( $out ) );
	}
}

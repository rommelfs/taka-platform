<?php
/** Event-local wall times and timezone-aware instants shared by editors and exports. */
defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Event_Time {
	public static function valid_timezone( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) { return false; }
		try { new DateTimeZone( $value ); return true; } catch ( Exception $error ) { return false; }
	}

	/** Existing wall times stay unchanged; only their interpretation gets a timezone. */
	public static function timezone( $event ) {
		$value = $event['timezone'] ?? '';
		if ( self::valid_timezone( $value ) ) { return $value; }
		$country = TAKA_Platform_Data::timezone_for_country( ! empty( $event['country_code'] ) ? $event['country_code'] : ( $event['country'] ?? '' ) );
		if ( self::valid_timezone( $country ) ) { return $country; }
		return function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC';
	}

	/** Strict parsing prevents invalid dates and DST gaps from silently moving a session. */
	public static function date_time( $date, $time, $timezone ) {
		if ( ! self::valid_timezone( $timezone ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) || ! preg_match( '/^\d{2}:\d{2}$/', (string) $time ) ) { return null; }
		$value = $date . ' ' . $time;
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $value, new DateTimeZone( $timezone ) );
		return $parsed && $parsed->format( 'Y-m-d H:i' ) === $value ? $parsed : null;
	}

	/** A lower end time means the session ends on the following local calendar day. */
	public static function range( $event, $item ) {
		$zone = self::timezone( $event );
		$date = $item['date_start'] ?? ( $item['date'] ?? ( $event['date_start'] ?? '' ) );
		$start = self::date_time( $date, $item['time_start'] ?? '', $zone );
		$end_date = $item['end_date'] ?? $date;
		if ( $start && $end_date === $date && ! empty( $item['time_end'] ) && $item['time_end'] < $item['time_start'] ) {
			$end_date = $start->modify( '+1 day' )->format( 'Y-m-d' );
		}
		return array( $start, self::date_time( $end_date, $item['time_end'] ?? '', $zone ) );
	}

	public static function range_label( $event, $item ) {
		$label = implode( '–', array_filter( array( $item['time_start'] ?? '', $item['time_end'] ?? '' ) ) );
		if ( '' === $label ) { return ''; }
		list( $start, $end ) = self::range( $event, $item );
		$offset = $start ? ', UTC' . $start->format( 'P' ) : '';
		if ( $start && $end && $start->format( 'P' ) !== $end->format( 'P' ) ) { $offset .= ' / UTC' . $end->format( 'P' ); }
		return $label . ' (' . self::timezone( $event ) . $offset . ')';
	}

	/** Shared native select; keep legacy aliases/offsets selectable without rewriting data. */
	public static function select( $name, $value, $automatic = true ) {
		$options = DateTimeZone::listIdentifiers();
		if ( '' !== $value && ! in_array( $value, $options, true ) ) { array_unshift( $options, $value ); }
		$html = '<select class="widefat" name="' . esc_attr( $name ) . '">';
		if ( $automatic ) { $html .= '<option value="">' . esc_html( taka_tour_translate( 'event.automatic_timezone', 'Automatic (event country, then website timezone)' ) ) . '</option>'; }
		foreach ( $options as $zone ) {
			$html .= '<option value="' . esc_attr( $zone ) . '"' . selected( $value, $zone, false ) . '>' . esc_html( $zone ) . '</option>';
		}
		return $html . '</select>';
	}

	public static function editor_help() {
		return __( 'All dates, program times and doors-open times use this timezone. Fukuoka uses Asia/Tokyo. Changing the timezone does not convert existing times: enter 21:00 with Asia/Tokyo for a 21:00 seminar in Japan.', 'taka-platform' );
	}
}

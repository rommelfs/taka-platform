<?php
/**
 * ICS calendar export provider.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_ICS_Provider implements TAKA_Platform_Event_Export_Provider_Interface {
	public function key() { return 'ics'; }
	public function label() { return __( 'ICS calendar feed', 'taka-platform' ); }
	public function content_type() { return 'text/calendar; charset=utf-8'; }
	public function file_extension() { return 'ics'; }

	public function export( $events ) {
		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//TAKA Platform//Events Export//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
		);
		foreach ( $events as $event ) {
			list( $start_date, $end_date ) = TAKA_Platform_Event_Time::range( $event, array(
				'date' => $event['start_date'] ?? '',
				'end_date' => $event['end_date'] ?? ( $event['start_date'] ?? '' ),
				'time_start' => ! empty( $event['start_time'] ) ? $event['start_time'] : '00:00',
				'time_end' => ! empty( $event['end_time'] ) ? $event['end_time'] : '00:00',
			) );
			if ( ! $start_date ) { continue; }
			$start = $start_date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' );
			$end = $end_date && ! empty( $event['end_time'] ) ? $end_date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' ) : '';
			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:' . $this->escape( ( $event['external_id'] ?? $event['taka_event_id'] ?? uniqid( 'taka-', true ) ) . '@taka-platform' );
			$lines[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' );
			$lines[] = 'DTSTART:' . $start;
			if ( '' !== $end ) { $lines[] = 'DTEND:' . $end; }
			$lines[] = 'SUMMARY:' . $this->escape( $event['title'] ?? '' );
			$lines[] = 'DESCRIPTION:' . $this->escape( wp_strip_all_tags( $event['description'] ?? '' ) );
			if ( ! empty( $event['event_url'] ) ) { $lines[] = 'URL:' . esc_url_raw( $event['event_url'] ); }
			if ( ! empty( $event['venue']['address'] ) ) { $lines[] = 'LOCATION:' . $this->escape( $event['venue']['address'] ); }
			$lines[] = 'END:VEVENT';
		}
		$lines[] = 'END:VCALENDAR';
		return implode( "\r\n", $lines ) . "\r\n";
	}

	private function escape( $value ) {
		$value = str_replace( array( '\\', ';', ',', "\r", "\n" ), array( '\\\\', '\;', '\,', '', '\n' ), (string) $value );
		return $value;
	}
}

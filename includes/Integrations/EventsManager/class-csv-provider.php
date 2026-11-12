<?php
/**
 * Generic CSV event export provider.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_CSV_Provider implements TAKA_Platform_Event_Export_Provider_Interface {
	public function key() { return 'csv'; }
	public function label() { return __( 'CSV export', 'taka-platform' ); }
	public function content_type() { return 'text/csv; charset=utf-8'; }
	public function file_extension() { return 'csv'; }

	public function export( $events ) {
		$columns = array(
			'taka_event_id',
			'external_id',
			'title',
			'subtitle',
			'description',
			'source_language',
			'start_date',
			'start_time',
			'end_date',
			'end_time',
			'venue_name',
			'venue_city',
			'venue_country',
			'organizers',
			'ticket_mode',
			'ticket_provider',
			'ticket_url',
			'ticket_door_price',
			'ticket_door_note',
			'event_url',
			'image_url',
			'timezone',
		);
		return self::csv_from_rows( $columns, $events );
	}

	protected static function csv_from_rows( $columns, $events, $mapper = null ) {
		$handle = fopen( 'php://temp', 'r+' );
		fputcsv( $handle, $columns );
		foreach ( $events as $event ) {
			$row = is_callable( $mapper ) ? $mapper( $event ) : $event;
			$values = array();
			foreach ( $columns as $column ) {
				$value = $row[ $column ] ?? '';
				if ( is_array( $value ) ) {
					$value = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				}
				$values[] = (string) $value;
			}
			fputcsv( $handle, $values );
		}
		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle );
		return (string) $csv;
	}
}

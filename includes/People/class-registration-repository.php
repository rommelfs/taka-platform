<?php
/**
 * WordPress-backed repository for private event registrations.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_People_Registration_Repository {
	const REGISTRATION_META = '_taka_registration';
	const PERSON_ID_META = '_taka_registration_person_id';
	const EVENT_ID_META = '_taka_registration_event_id';
	const ORDER_ID_META = '_taka_registration_order_id';
	const UNIQUE_KEY_META = '_taka_registration_unique_key';

	public function find_by_id( $registration_id ) {
		$post = get_post( absint( $registration_id ) );
		if ( ! $post || TAKA_PLATFORM_CPT_REGISTRATION !== $post->post_type ) {
			return null;
		}
		return $this->registration_from_post( $post );
	}

	public function find_existing( $registration ) {
		$key = TAKA_People_Registration::unique_key( $registration );
		if ( '' === $key ) {
			return null;
		}
		$posts = get_posts(
			array(
				'post_type'        => TAKA_PLATFORM_CPT_REGISTRATION,
				'post_status'      => 'private',
				'posts_per_page'   => 1,
				'meta_key'         => self::UNIQUE_KEY_META,
				'meta_value'       => $key,
				'suppress_filters' => true,
			)
		);
		return empty( $posts ) ? null : $this->registration_from_post( $posts[0] );
	}

	public function save( $registration ) {
		$data = TAKA_People_Registration::normalize( $registration );
		if ( ! $data['person_id'] || ! $data['order_id'] ) {
			return new WP_Error( 'taka_registration_missing_links', __( 'Registration needs a person and an order.', 'taka-platform' ) );
		}

		$existing = $this->find_existing( $data );
		if ( $existing ) {
			$data = $this->preserve_operational_fields( $data, $existing );
			$data['id'] = absint( $existing['id'] ?? 0 );
			$data['created_at'] = (string) ( $existing['created_at'] ?? '' );
		}

		$now = current_time( 'mysql' );
		if ( '' === $data['created_at'] ) {
			$data['created_at'] = $now;
		}
		$data['updated_at'] = $now;

		$post_data = array(
			'post_type'   => TAKA_PLATFORM_CPT_REGISTRATION,
			'post_status' => 'private',
			'post_title'  => sanitize_text_field( $this->title_for_registration( $data ) ),
		);

		if ( ! empty( $data['id'] ) ) {
			$post_data['ID'] = absint( $data['id'] );
			$result = wp_update_post( $post_data, true );
			$registration_id = absint( $data['id'] );
		} else {
			$result = wp_insert_post( $post_data, true );
			$registration_id = is_wp_error( $result ) ? 0 : absint( $result );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data['id'] = $registration_id;
		update_post_meta( $registration_id, self::REGISTRATION_META, $data );
		update_post_meta( $registration_id, self::PERSON_ID_META, (string) absint( $data['person_id'] ) );
		update_post_meta( $registration_id, self::EVENT_ID_META, (string) absint( $data['event_id'] ) );
		update_post_meta( $registration_id, self::ORDER_ID_META, (string) absint( $data['order_id'] ) );
		update_post_meta( $registration_id, self::UNIQUE_KEY_META, TAKA_People_Registration::unique_key( $data ) );
		return $data;
	}

	public function query( $args = array() ) {
		$query = array(
			'post_type'        => TAKA_PLATFORM_CPT_REGISTRATION,
			'post_status'      => 'private',
			'posts_per_page'   => ( isset( $args['per_page'] ) && -1 === (int) $args['per_page'] ) ? -1 : absint( $args['per_page'] ?? 100 ),
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => true,
		);

		$meta_query = array();
		if ( ! empty( $args['person_id'] ) ) {
			$meta_query[] = array( 'key' => self::PERSON_ID_META, 'value' => (string) absint( $args['person_id'] ) );
		}
		if ( ! empty( $args['event_id'] ) ) {
			$meta_query[] = array( 'key' => self::EVENT_ID_META, 'value' => (string) absint( $args['event_id'] ) );
		}
		if ( ! empty( $args['order_id'] ) ) {
			$meta_query[] = array( 'key' => self::ORDER_ID_META, 'value' => (string) absint( $args['order_id'] ) );
		}
		if ( ! empty( $meta_query ) ) {
			$query['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		return array_values( array_filter( array_map( array( $this, 'registration_from_post' ), get_posts( $query ) ) ) );
	}

	private function registration_from_post( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		$data = get_post_meta( $post->ID, self::REGISTRATION_META, true );
		$data = is_array( $data ) ? $data : array();
		$data['id'] = absint( $post->ID );
		return TAKA_People_Registration::normalize( $data );
	}

	private function title_for_registration( $registration ) {
		$parts = array_filter(
			array(
				$registration['event_title'] ?? '',
				$registration['order_number'] ?? '',
				$registration['person_id'] ? 'Person #' . absint( $registration['person_id'] ) : '',
			)
		);
		return implode( ' - ', $parts ) ?: __( 'Registration', 'taka-platform' );
	}

	private function preserve_operational_fields( $incoming, $existing ) {
		$incoming = TAKA_People_Registration::normalize( $incoming );
		$existing = TAKA_People_Registration::normalize( $existing );

		foreach ( array( 'validation_token', 'checked_in_at', 'checked_in_by', 'internal_notes', 'attendance_sessions', 'operations_timeline' ) as $field ) {
			$incoming_empty = empty( $incoming[ $field ] );
			if ( $incoming_empty && ! empty( $existing[ $field ] ) ) {
				$incoming[ $field ] = $existing[ $field ];
			}
		}

		if ( ! empty( $existing['walk_in'] ) && '0' !== (string) $existing['walk_in'] ) {
			$incoming['walk_in'] = '1';
		}
		if ( 'checked_in' === (string) ( $existing['checkin_status'] ?? '' ) && 'not_checked_in' === (string) ( $incoming['checkin_status'] ?? '' ) ) {
			$incoming['checkin_status'] = 'checked_in';
			$incoming['attendance_state'] = 'checked_in';
		}
		if ( in_array( (string) ( $existing['attendance_state'] ?? '' ), array( 'checked_in', 'checked_out', 'no_show' ), true ) && 'registered' === (string) ( $incoming['attendance_state'] ?? '' ) ) {
			$incoming['attendance_state'] = (string) $existing['attendance_state'];
		}

		return $incoming;
	}
}

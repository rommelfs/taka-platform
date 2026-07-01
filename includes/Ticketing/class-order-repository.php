<?php
/**
 * WordPress-backed native ticketing order repository.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Ticketing_Order_Repository implements TAKA_Ticketing_Order_Repository_Interface {
	const ORDER_META          = '_taka_ticketing_order';
	const TOKEN_META          = '_taka_ticketing_public_token';
	const EVENT_ID_META       = '_taka_ticketing_event_id';
	const TICKET_TYPE_ID_META = '_taka_ticketing_ticket_type_id';
	const PROMOTION_ID_META   = '_taka_ticketing_promotion_id';
	const PROMOTION_CODE_META = '_taka_ticketing_promotion_code';
	const BUYER_EMAIL_META    = '_taka_ticketing_buyer_email';

	public function find_by_id( $order_id ) {
		$post = get_post( absint( $order_id ) );
		if ( ! $post || TAKA_PLATFORM_CPT_TICKET_ORDER !== $post->post_type ) {
			return null;
		}
		return $this->order_from_post( $post );
	}

	public function find_by_public_token( $token ) {
		$token = sanitize_text_field( $token );
		if ( '' === $token ) {
			return null;
		}
		$posts = get_posts(
			array(
				'post_type'      => TAKA_PLATFORM_CPT_TICKET_ORDER,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => self::TOKEN_META,
				'meta_value'     => $token,
				'suppress_filters' => true,
			)
		);
		return empty( $posts ) ? null : $this->order_from_post( $posts[0] );
	}

	public function find_by_event( $event_id, $args = array() ) {
		$args['event_id'] = absint( $event_id );
		return $this->query( $args );
	}

	public function query( $args = array() ) {
		$query = array(
			'post_type'      => TAKA_PLATFORM_CPT_TICKET_ORDER,
			'post_status'    => 'any',
			'posts_per_page' => ( isset( $args['per_page'] ) && -1 === (int) $args['per_page'] ) ? -1 : absint( $args['per_page'] ?? 50 ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'suppress_filters' => true,
		);
		if ( ! empty( $args['event_id'] ) ) {
			$query['meta_key'] = self::EVENT_ID_META;
			$query['meta_value'] = (string) absint( $args['event_id'] );
		}

		return array_values( array_filter( array_map( array( $this, 'order_from_post' ), get_posts( $query ) ) ) );
	}

	public function save( TAKA_Ticketing_Order $order ) {
		$data = $order->to_array();
		$order_id = absint( $data['id'] ?? 0 );
		$post_data = array(
			'post_type'   => TAKA_PLATFORM_CPT_TICKET_ORDER,
			'post_status' => 'private',
			'post_title'  => sanitize_text_field( $data['order_number'] ?? __( 'Ticket order', 'taka-platform' ) ),
		);

		if ( $order_id ) {
			$post_data['ID'] = $order_id;
			$result = wp_update_post( $post_data, true );
		} else {
			$result = wp_insert_post( $post_data, true );
			$order_id = is_wp_error( $result ) ? 0 : absint( $result );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data['id'] = $order_id;
		$data['updated_at'] = current_time( 'mysql' );
		if ( empty( $data['created_at'] ) ) {
			$data['created_at'] = get_post_time( 'Y-m-d H:i:s', false, $order_id );
		}

		$this->store_meta( $order_id, $data );
		return new TAKA_Ticketing_Order( $data );
	}

	public function count_reserved_for_ticket( $event_id, $ticket_type_id ) {
		$orders = $this->find_by_event( absint( $event_id ), array( 'per_page' => -1 ) );
		$count = 0;
		foreach ( $orders as $order ) {
			$data = $order->to_array();
			if ( (string) ( $data['ticket_type_id'] ?? '' ) !== (string) $ticket_type_id ) {
				continue;
			}
			if ( 'cancelled' === (string) ( $data['order_status'] ?? '' ) ) {
				continue;
			}
			$count++;
		}
		return $count;
	}

	public function delete_order( $order_id ) {
		return (bool) wp_delete_post( absint( $order_id ), true );
	}

	private function order_from_post( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		$data = get_post_meta( $post->ID, self::ORDER_META, true );
		$data = is_array( $data ) ? $data : array();
		$data['id'] = absint( $post->ID );
		$data['created_at'] = $data['created_at'] ?? get_post_time( 'Y-m-d H:i:s', false, $post );
		return new TAKA_Ticketing_Order( $data );
	}

	private function store_meta( $order_id, $data ) {
		update_post_meta( $order_id, self::ORDER_META, $data );
		update_post_meta( $order_id, self::TOKEN_META, sanitize_text_field( $data['public_token'] ?? '' ) );
		update_post_meta( $order_id, self::EVENT_ID_META, (string) absint( $data['event_id'] ?? 0 ) );
		update_post_meta( $order_id, self::TICKET_TYPE_ID_META, sanitize_key( $data['ticket_type_id'] ?? '' ) );
		update_post_meta( $order_id, self::PROMOTION_ID_META, (string) absint( $data['applied_promotion_id'] ?? 0 ) );
		update_post_meta( $order_id, self::PROMOTION_CODE_META, sanitize_text_field( $data['applied_voucher_code'] ?? '' ) );
		$buyer = is_array( $data['buyer'] ?? null ) ? $data['buyer'] : array();
		update_post_meta( $order_id, self::BUYER_EMAIL_META, sanitize_email( $buyer['email'] ?? '' ) );
	}
}

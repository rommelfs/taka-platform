<?php
/**
 * WordPress-backed promotion repository for native ticketing.
 *
 * Promotions are currently stored as private posts plus structured meta. The
 * repository boundary is intentional so later order/promotion tables can be
 * introduced without changing checkout or admin callers.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Ticketing_Promotion_Repository {
	const PROMOTION_META = '_taka_ticketing_promotion';
	const CODE_META      = '_taka_ticketing_promotion_code';

	public function find_by_id( $promotion_id ) {
		$post = get_post( absint( $promotion_id ) );
		if ( ! $post || TAKA_PLATFORM_CPT_TICKET_PROMOTION !== $post->post_type ) {
			return null;
		}
		return $this->promotion_from_post( $post );
	}

	public function find_by_code( $code ) {
		$code = TAKA_Ticketing_Promotion::normalize_code( $code );
		if ( '' === $code ) {
			return null;
		}

		$posts = get_posts(
			array(
				'post_type'        => TAKA_PLATFORM_CPT_TICKET_PROMOTION,
				'post_status'      => 'private',
				'posts_per_page'   => 1,
				'meta_key'         => self::CODE_META,
				'meta_value'       => $code,
				'suppress_filters' => true,
			)
		);

		return empty( $posts ) ? null : $this->promotion_from_post( $posts[0] );
	}

	public function query( $args = array() ) {
		$query = array(
			'post_type'        => TAKA_PLATFORM_CPT_TICKET_PROMOTION,
			'post_status'      => 'private',
			'posts_per_page'   => ( isset( $args['per_page'] ) && -1 === (int) $args['per_page'] ) ? -1 : absint( $args['per_page'] ?? 100 ),
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => true,
		);

		return array_values( array_filter( array_map( array( $this, 'promotion_from_post' ), get_posts( $query ) ) ) );
	}

	public function save( $promotion ) {
		$data = TAKA_Ticketing_Promotion::normalize( $promotion );
		if ( '' === $data['code'] ) {
			return new WP_Error( 'taka_ticketing_promotion_code', __( 'Promotion code is required.', 'taka-platform' ) );
		}
		if ( '' === $data['title'] ) {
			$data['title'] = $data['code'];
		}
		if ( empty( $data['benefits'] ) ) {
			return new WP_Error( 'taka_ticketing_promotion_benefits', __( 'Choose at least one promotion benefit.', 'taka-platform' ) );
		}

		$existing = $this->find_by_code( $data['code'] );
		if ( $existing && absint( $existing['id'] ?? 0 ) !== absint( $data['id'] ?? 0 ) ) {
			return new WP_Error( 'taka_ticketing_promotion_duplicate', __( 'A promotion with this code already exists.', 'taka-platform' ) );
		}

		$post_data = array(
			'post_type'   => TAKA_PLATFORM_CPT_TICKET_PROMOTION,
			'post_status' => 'private',
			'post_title'  => sanitize_text_field( $data['title'] ),
		);

		if ( ! empty( $data['id'] ) ) {
			$post_data['ID'] = absint( $data['id'] );
			$result = wp_update_post( $post_data, true );
			$promotion_id = absint( $data['id'] );
		} else {
			$result = wp_insert_post( $post_data, true );
			$promotion_id = is_wp_error( $result ) ? 0 : absint( $result );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data['id'] = $promotion_id;
		update_post_meta( $promotion_id, self::PROMOTION_META, $data );
		update_post_meta( $promotion_id, self::CODE_META, $data['code'] );

		return $data;
	}

	public function delete( $promotion_id ) {
		return (bool) wp_delete_post( absint( $promotion_id ), true );
	}

	public function count_uses( $promotion_id ) {
		$promotion_id = absint( $promotion_id );
		if ( ! $promotion_id ) {
			return 0;
		}
		$count = 0;
		foreach ( TAKA_Ticketing_Module::order_repository()->query( array( 'per_page' => -1 ) ) as $order ) {
			$data = $order->to_array();
			if ( absint( $data['applied_promotion_id'] ?? 0 ) !== $promotion_id ) {
				continue;
			}
			if ( 'cancelled' === (string) ( $data['order_status'] ?? '' ) ) {
				continue;
			}
			$count++;
		}
		return $count;
	}

	public function count_uses_for_email( $promotion_id, $email ) {
		$promotion_id = absint( $promotion_id );
		$email = sanitize_email( $email );
		if ( ! $promotion_id || '' === $email ) {
			return 0;
		}
		$count = 0;
		foreach ( TAKA_Ticketing_Module::order_repository()->query( array( 'per_page' => -1 ) ) as $order ) {
			$data = $order->to_array();
			$buyer = is_array( $data['buyer'] ?? null ) ? $data['buyer'] : array();
			if ( absint( $data['applied_promotion_id'] ?? 0 ) !== $promotion_id ) {
				continue;
			}
			if ( 'cancelled' === (string) ( $data['order_status'] ?? '' ) ) {
				continue;
			}
			if ( strtolower( sanitize_email( $buyer['email'] ?? '' ) ) !== strtolower( $email ) ) {
				continue;
			}
			$count++;
		}
		return $count;
	}

	public function export_promotions() {
		return array_values( array_map( array( $this, 'export_promotion' ), $this->query( array( 'per_page' => -1 ) ) ) );
	}

	public function import_promotions( $items, $mode = 'update', $dry_run = false ) {
		$summary = array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array() );
		foreach ( (array) $items as $item ) {
			if ( ! is_array( $item ) ) {
				$summary['skipped']++;
				continue;
			}
			$item = TAKA_Ticketing_Promotion::normalize( $item );
			if ( '' === $item['code'] ) {
				$summary['skipped']++;
				continue;
			}
			$existing = $this->find_by_code( $item['code'] );
			if ( $existing && 'missing' === $mode ) {
				$summary['skipped']++;
				continue;
			}
			if ( $existing ) {
				$item['id'] = absint( $existing['id'] ?? 0 );
			}
			if ( $dry_run ) {
				$summary[ $existing ? 'updated' : 'created' ]++;
				continue;
			}
			$result = $this->save( $item );
			if ( is_wp_error( $result ) ) {
				$summary['errors'][] = $result->get_error_message();
				continue;
			}
			$summary[ $existing ? 'updated' : 'created' ]++;
		}
		return $summary;
	}

	private function promotion_from_post( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		$data = get_post_meta( $post->ID, self::PROMOTION_META, true );
		$data = is_array( $data ) ? $data : array();
		$data['id'] = absint( $post->ID );
		if ( '' === trim( (string) ( $data['title'] ?? '' ) ) ) {
			$data['title'] = get_the_title( $post );
		}
		return TAKA_Ticketing_Promotion::normalize( $data );
	}

	private function export_promotion( $promotion ) {
		unset( $promotion['id'] );
		return $promotion;
	}
}

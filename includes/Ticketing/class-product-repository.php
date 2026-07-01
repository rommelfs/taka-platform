<?php
/**
 * WordPress-backed ticketing product repository.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Ticketing_Product_Repository {
	const PRODUCT_META = '_taka_ticketing_product';
	const PRODUCT_ID_META = '_taka_ticketing_product_id';
	const EVENT_ID_META = '_taka_ticketing_product_event_id';

	public function find_by_id( $product_id ) {
		$post = get_post( absint( $product_id ) );
		if ( ! $post || TAKA_PLATFORM_CPT_TICKETING_PRODUCT !== $post->post_type ) {
			return null;
		}
		return $this->product_from_post( $post );
	}

	public function find_by_product_id( $product_id ) {
		$product_id = TAKA_Ticketing_Product::normalize_product_id( $product_id );
		if ( '' === $product_id ) {
			return null;
		}
		$posts = get_posts(
			array(
				'post_type'        => TAKA_PLATFORM_CPT_TICKETING_PRODUCT,
				'post_status'      => 'private',
				'posts_per_page'   => 1,
				'meta_key'         => self::PRODUCT_ID_META,
				'meta_value'       => $product_id,
				'suppress_filters' => true,
			)
		);
		return empty( $posts ) ? null : $this->product_from_post( $posts[0] );
	}

	public function query( $args = array() ) {
		$query = array(
			'post_type'        => TAKA_PLATFORM_CPT_TICKETING_PRODUCT,
			'post_status'      => 'private',
			'posts_per_page'   => ( isset( $args['per_page'] ) && -1 === (int) $args['per_page'] ) ? -1 : absint( $args['per_page'] ?? 100 ),
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => true,
		);
		if ( ! empty( $args['event_id'] ) ) {
			$query['meta_key'] = self::EVENT_ID_META;
			$query['meta_value'] = (string) absint( $args['event_id'] );
		}
		return array_values( array_filter( array_map( array( $this, 'product_from_post' ), get_posts( $query ) ) ) );
	}

	public function checkout_add_ons_for_event( $event_id ) {
		$event_id = absint( $event_id );
		$products = $this->query( array( 'per_page' => -1 ) );
		$products = array_filter(
			$products,
			static function ( $product ) use ( $event_id ) {
				if ( '1' !== (string) ( $product['visible_in_checkout'] ?? '1' ) ) {
					return false;
				}
				if ( '1' !== (string) ( $product['requires_event_ticket'] ?? '0' ) ) {
					return false;
				}
				if ( absint( $product['related_event_id'] ?? 0 ) !== $event_id ) {
					return false;
				}
				return in_array( (string) ( $product['type'] ?? '' ), array( 'add_on', 'meal', 'party', 'merch', 'other' ), true );
			}
		);
		usort(
			$products,
			static function ( $a, $b ) {
				return ( (int) ( $a['sort_order'] ?? 0 ) <=> (int) ( $b['sort_order'] ?? 0 ) ) ?: strcmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) );
			}
		);
		return array_values( $products );
	}

	public function save( $product ) {
		$data = TAKA_Ticketing_Product::normalize( $product );
		if ( '' === $data['title'] ) {
			return new WP_Error( 'taka_ticketing_product_title', __( 'Product title is required.', 'taka-platform' ) );
		}
		if ( '' === $data['product_id'] ) {
			$data['product_id'] = TAKA_Ticketing_Product::normalize_product_id( $data['title'] );
		}
		$existing = $this->find_by_product_id( $data['product_id'] );
		if ( $existing && absint( $existing['id'] ?? 0 ) !== absint( $data['id'] ?? 0 ) ) {
			return new WP_Error( 'taka_ticketing_product_duplicate', __( 'A product with this ID already exists.', 'taka-platform' ) );
		}

		$post_data = array(
			'post_type'   => TAKA_PLATFORM_CPT_TICKETING_PRODUCT,
			'post_status' => 'private',
			'post_title'  => sanitize_text_field( $data['title'] ),
		);

		if ( ! empty( $data['id'] ) ) {
			$post_data['ID'] = absint( $data['id'] );
			$result = wp_update_post( $post_data, true );
			$product_id = absint( $data['id'] );
		} else {
			$result = wp_insert_post( $post_data, true );
			$product_id = is_wp_error( $result ) ? 0 : absint( $result );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data['id'] = $product_id;
		update_post_meta( $product_id, self::PRODUCT_META, $data );
		update_post_meta( $product_id, self::PRODUCT_ID_META, $data['product_id'] );
		update_post_meta( $product_id, self::EVENT_ID_META, (string) absint( $data['related_event_id'] ?? 0 ) );
		return $data;
	}

	public function delete( $product_id ) {
		return (bool) wp_delete_post( absint( $product_id ), true );
	}

	public function availability( $product ) {
		$product = TAKA_Ticketing_Product::normalize( $product );
		$available = 'active' === (string) ( $product['status'] ?? 'active' );
		$reason = '';
		if ( ! $available ) {
			$reason = 'sold_out' === (string) ( $product['status'] ?? '' ) ? TAKA_Ticketing_Module::text( 'ticketing.sold_out', 'Sold out' ) : TAKA_Ticketing_Module::text( 'ticketing.unavailable', 'Unavailable' );
		}

		$now = current_time( 'timestamp' );
		$start = $this->sale_timestamp( $product['sale_start_date'] ?? '', $product['sale_start_time'] ?? '00:00' );
		$end = $this->sale_timestamp( $product['sale_end_date'] ?? '', $product['sale_end_time'] ?? '23:59' );
		if ( $available && $start && $now < $start ) {
			$available = false;
			$reason = TAKA_Ticketing_Module::text( 'ticketing.sales_not_started', 'Sales have not started yet.' );
		}
		if ( $available && $end && $now > $end ) {
			$available = false;
			$reason = TAKA_Ticketing_Module::text( 'ticketing.sales_ended', 'Sales have ended.' );
		}

		$capacity = '' === trim( (string) ( $product['capacity'] ?? '' ) ) ? null : absint( $product['capacity'] );
		$reserved = $this->count_reserved_for_product( $product['product_id'] ?? '' );
		$remaining = null === $capacity ? null : max( 0, $capacity - $reserved );
		if ( $available && null !== $remaining && $remaining <= 0 ) {
			$available = false;
			$reason = TAKA_Ticketing_Module::text( 'ticketing.sold_out', 'Sold out' );
		}

		return array( 'available' => $available, 'capacity' => $capacity, 'reserved' => $reserved, 'remaining' => $remaining, 'reason' => $reason );
	}

	public function count_reserved_for_product( $product_id ) {
		$product_id = TAKA_Ticketing_Product::normalize_product_id( $product_id );
		if ( '' === $product_id ) {
			return 0;
		}
		$count = 0;
		foreach ( TAKA_Ticketing_Module::order_repository()->query( array( 'per_page' => -1 ) ) as $order ) {
			$data = $order->to_array();
			if ( 'cancelled' === (string) ( $data['order_status'] ?? '' ) ) {
				continue;
			}
			foreach ( (array) ( $data['line_items'] ?? array() ) as $item ) {
				if ( 'product' !== (string) ( $item['item_type'] ?? '' ) ) {
					continue;
				}
				if ( (string) ( $item['product_id'] ?? '' ) !== $product_id ) {
					continue;
				}
				$count += max( 1, absint( $item['quantity'] ?? 1 ) );
			}
		}
		return $count;
	}

	public function export_products() {
		return array_values( array_map( array( $this, 'export_product' ), $this->query( array( 'per_page' => -1 ) ) ) );
	}

	public function import_products( $items, $mode = 'update', $dry_run = false ) {
		$summary = array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array() );
		foreach ( (array) $items as $item ) {
			if ( ! is_array( $item ) ) {
				$summary['skipped']++;
				continue;
			}
			$item = TAKA_Ticketing_Product::normalize( $item );
			if ( '' === $item['product_id'] ) {
				$summary['skipped']++;
				continue;
			}
			$existing = $this->find_by_product_id( $item['product_id'] );
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

	private function product_from_post( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		$data = get_post_meta( $post->ID, self::PRODUCT_META, true );
		$data = is_array( $data ) ? $data : array();
		$data['id'] = absint( $post->ID );
		if ( '' === trim( (string) ( $data['title'] ?? '' ) ) ) {
			$data['title'] = get_the_title( $post );
		}
		return TAKA_Ticketing_Product::normalize( $data );
	}

	private function export_product( $product ) {
		unset( $product['id'] );
		return $product;
	}

	private function sale_timestamp( $date, $time ) {
		$date = trim( (string) $date );
		if ( '' === $date ) {
			return 0;
		}
		$time = preg_match( '/^\d{2}:\d{2}$/', (string) $time ) ? (string) $time : '00:00';
		return strtotime( $date . ' ' . $time );
	}
}

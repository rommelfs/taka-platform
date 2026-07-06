<?php
/**
 * Ticket artifacts for native TAKA Ticketing.
 *
 * The service stores stable, tokenized ticket metadata on orders and renders
 * email attachments from that data. Future Wallet providers should hook into
 * `taka_ticketing_wallet_links` instead of generating separate ticket state.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Ticketing_QR_Code {
	public static function svg( $text, $label = 'QR-Code' ) {
		$modules = self::modules( $text );
		return empty( $modules ) ? '' : self::svg_from_modules( $modules, $label );
	}

	public static function modules( $text ) {
		$bytes = self::utf8_bytes( $text );
		if ( count( $bytes ) > 106 ) {
			return array();
		}

		$size = 37;
		$matrix = self::make_matrix( $size );
		$data = self::data_codewords( $bytes );
		$all_codewords = array_merge( $data, self::reed_solomon_remainder( $data, 26 ) );
		$bits = array();
		foreach ( $all_codewords as $codeword ) {
			self::append_bits( $bits, $codeword, 8 );
		}

		self::draw_function_patterns( $matrix );
		self::place_data( $matrix, $bits );
		self::draw_format_bits( $matrix, 0 );

		return $matrix['modules'];
	}

	private static function utf8_bytes( $text ) {
		$bytes = unpack( 'C*', (string) $text );
		return $bytes ? array_values( $bytes ) : array();
	}

	private static function append_bits( &$bits, $value, $length ) {
		for ( $i = $length - 1; $i >= 0; $i-- ) {
			$bits[] = ( $value >> $i ) & 1;
		}
	}

	private static function data_codewords( $bytes ) {
		$bits = array();
		self::append_bits( $bits, 4, 4 );
		self::append_bits( $bits, count( $bytes ), 8 );
		foreach ( $bytes as $byte ) {
			self::append_bits( $bits, $byte, 8 );
		}

		$capacity = 108 * 8;
		for ( $i = 0; $i < 4 && count( $bits ) < $capacity; $i++ ) {
			$bits[] = 0;
		}
		while ( count( $bits ) % 8 ) {
			$bits[] = 0;
		}

		$codewords = array();
		for ( $i = 0; $i < count( $bits ); $i += 8 ) {
			$codeword = 0;
			for ( $j = 0; $j < 8; $j++ ) {
				$codeword = ( $codeword << 1 ) | ( $bits[ $i + $j ] ?? 0 );
			}
			$codewords[] = $codeword;
		}
		for ( $pad = 0; count( $codewords ) < 108; $pad++ ) {
			$codewords[] = 0 === $pad % 2 ? 0xEC : 0x11;
		}
		return $codewords;
	}

	private static $qr_tables = null;

	private static function gf_tables() {
		if ( null !== self::$qr_tables ) {
			return self::$qr_tables;
		}

		$exp = array();
		$log = array();
		$value = 1;
		for ( $i = 0; $i < 255; $i++ ) {
			$exp[ $i ] = $value;
			$log[ $value ] = $i;
			$value <<= 1;
			if ( $value & 0x100 ) {
				$value ^= 0x11D;
			}
		}
		for ( $i = 255; $i < 512; $i++ ) {
			$exp[ $i ] = $exp[ $i - 255 ];
		}
		self::$qr_tables = array( 'exp' => $exp, 'log' => $log );
		return self::$qr_tables;
	}

	private static function gf_multiply( $a, $b ) {
		if ( 0 === $a || 0 === $b ) {
			return 0;
		}
		$tables = self::gf_tables();
		return $tables['exp'][ $tables['log'][ $a ] + $tables['log'][ $b ] ];
	}

	private static function reed_solomon_divisor( $degree ) {
		$tables = self::gf_tables();
		$result = array_fill( 0, $degree, 0 );
		$result[ $degree - 1 ] = 1;
		for ( $i = 0; $i < $degree; $i++ ) {
			$root = $tables['exp'][ $i ];
			for ( $j = 0; $j < $degree; $j++ ) {
				$result[ $j ] = self::gf_multiply( $result[ $j ], $root );
				if ( $j + 1 < $degree ) {
					$result[ $j ] ^= $result[ $j + 1 ];
				}
			}
		}
		return $result;
	}

	private static function reed_solomon_remainder( $data, $degree ) {
		$divisor = self::reed_solomon_divisor( $degree );
		$result = array_fill( 0, $degree, 0 );
		foreach ( $data as $byte ) {
			$factor = $byte ^ array_shift( $result );
			$result[] = 0;
			for ( $i = 0; $i < $degree; $i++ ) {
				$result[ $i ] ^= self::gf_multiply( $divisor[ $i ], $factor );
			}
		}
		return $result;
	}

	private static function make_matrix( $size ) {
		$modules = array();
		$reserved = array();
		for ( $row = 0; $row < $size; $row++ ) {
			$modules[ $row ] = array_fill( 0, $size, false );
			$reserved[ $row ] = array_fill( 0, $size, false );
		}
		return array( 'modules' => $modules, 'reserved' => $reserved );
	}

	private static function set_module( &$matrix, $row, $col, $dark ) {
		$size = count( $matrix['modules'] );
		if ( $row < 0 || $col < 0 || $row >= $size || $col >= $size ) {
			return;
		}
		$matrix['modules'][ $row ][ $col ] = (bool) $dark;
		$matrix['reserved'][ $row ][ $col ] = true;
	}

	private static function draw_function_patterns( &$matrix ) {
		$size = count( $matrix['modules'] );
		self::draw_finder( $matrix, 0, 0 );
		self::draw_finder( $matrix, $size - 7, 0 );
		self::draw_finder( $matrix, 0, $size - 7 );
		for ( $i = 8; $i < $size - 8; $i++ ) {
			self::set_module( $matrix, 6, $i, 0 === $i % 2 );
			self::set_module( $matrix, $i, 6, 0 === $i % 2 );
		}
		self::draw_alignment( $matrix, 30, 30 );
		self::set_module( $matrix, $size - 8, 8, true );
		self::draw_format_bits( $matrix, 0 );
	}

	private static function draw_finder( &$matrix, $row, $col ) {
		for ( $dy = -1; $dy <= 7; $dy++ ) {
			for ( $dx = -1; $dx <= 7; $dx++ ) {
				$dark = $dy >= 0 && $dy <= 6 && $dx >= 0 && $dx <= 6 && ( 0 === $dy || 6 === $dy || 0 === $dx || 6 === $dx || ( $dy >= 2 && $dy <= 4 && $dx >= 2 && $dx <= 4 ) );
				self::set_module( $matrix, $row + $dy, $col + $dx, $dark );
			}
		}
	}

	private static function draw_alignment( &$matrix, $row, $col ) {
		for ( $dy = -2; $dy <= 2; $dy++ ) {
			for ( $dx = -2; $dx <= 2; $dx++ ) {
				self::set_module( $matrix, $row + $dy, $col + $dx, 1 !== max( abs( $dx ), abs( $dy ) ) );
			}
		}
	}

	private static function draw_format_bits( &$matrix, $mask ) {
		$size = count( $matrix['modules'] );
		$bits = self::format_bits( $mask );
		for ( $i = 0; $i <= 5; $i++ ) {
			self::set_module( $matrix, 8, $i, self::get_bit( $bits, $i ) );
		}
		self::set_module( $matrix, 8, 7, self::get_bit( $bits, 6 ) );
		self::set_module( $matrix, 8, 8, self::get_bit( $bits, 7 ) );
		self::set_module( $matrix, 7, 8, self::get_bit( $bits, 8 ) );
		for ( $i = 9; $i < 15; $i++ ) {
			self::set_module( $matrix, 14 - $i, 8, self::get_bit( $bits, $i ) );
		}
		for ( $i = 0; $i < 8; $i++ ) {
			self::set_module( $matrix, $size - 1 - $i, 8, self::get_bit( $bits, $i ) );
		}
		for ( $i = 8; $i < 15; $i++ ) {
			self::set_module( $matrix, 8, $size - 15 + $i, self::get_bit( $bits, $i ) );
		}
		self::set_module( $matrix, $size - 8, 8, true );
	}

	private static function format_bits( $mask ) {
		$data = ( 1 << 3 ) | $mask;
		$bits = $data << 10;
		for ( $i = 14; $i >= 10; $i-- ) {
			if ( ( $bits >> $i ) & 1 ) {
				$bits ^= 0x537 << ( $i - 10 );
			}
		}
		return ( ( $data << 10 ) | ( $bits & 0x3FF ) ) ^ 0x5412;
	}

	private static function get_bit( $value, $index ) {
		return 0 !== ( ( $value >> $index ) & 1 );
	}

	private static function place_data( &$matrix, $bits ) {
		$size = count( $matrix['modules'] );
		$bit_index = 0;
		$upward = true;
		for ( $right = $size - 1; $right >= 1; $right -= 2 ) {
			if ( 6 === $right ) {
				$right--;
			}
			for ( $vert = 0; $vert < $size; $vert++ ) {
				$row = $upward ? $size - 1 - $vert : $vert;
				for ( $j = 0; $j < 2; $j++ ) {
					$col = $right - $j;
					if ( empty( $matrix['reserved'][ $row ][ $col ] ) ) {
						$bit = $bit_index < count( $bits ) ? $bits[ $bit_index++ ] : 0;
						if ( 0 === ( $row + $col ) % 2 ) {
							$bit ^= 1;
						}
						$matrix['modules'][ $row ][ $col ] = (bool) $bit;
					}
				}
			}
			$upward = ! $upward;
		}
	}

	private static function svg_from_modules( $modules, $label ) {
		$quiet = 4;
		$size = count( $modules );
		$path = '';
		for ( $row = 0; $row < $size; $row++ ) {
			for ( $col = 0; $col < $size; $col++ ) {
				if ( ! empty( $modules[ $row ][ $col ] ) ) {
					$path .= 'M' . ( $col + $quiet ) . ' ' . ( $row + $quiet ) . 'h1v1h-1z';
				}
			}
		}
		$dimension = $size + $quiet * 2;
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . esc_attr( (string) $dimension ) . ' ' . esc_attr( (string) $dimension ) . '" role="img" aria-label="' . esc_attr( $label ) . '" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#fff"/><path fill="#111" d="' . esc_attr( $path ) . '"/></svg>';
	}
}

class TAKA_Ticketing_PDF_Renderer {
	public static function from_html( $html, $fallback_title, $sections = array() ) {
		self::load_optional_libraries();
		if ( class_exists( '\Dompdf\Dompdf' ) ) {
			$options_class = '\Dompdf\Options';
			$options = class_exists( $options_class ) ? new $options_class() : null;
			if ( $options && method_exists( $options, 'set' ) ) {
				$options->set( 'isRemoteEnabled', false );
				$options->set( 'defaultFont', 'DejaVu Sans' );
			}
			$dompdf_class = '\Dompdf\Dompdf';
			$dompdf = $options ? new $dompdf_class( $options ) : new $dompdf_class();
			$dompdf->loadHtml( self::pdf_html( $html ), 'UTF-8' );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();
			return $dompdf->output();
		}
		if ( class_exists( '\Mpdf\Mpdf' ) ) {
			$mpdf_class = '\Mpdf\Mpdf';
			$mpdf = new $mpdf_class( array( 'mode' => 'utf-8', 'format' => 'A4' ) );
			$mpdf->WriteHTML( self::pdf_html( $html ) );
			return $mpdf->Output( '', 'S' );
		}
		return self::native_pdf( $fallback_title, $sections );
	}

	private static function load_optional_libraries() {
		if ( defined( 'TAKA_PLATFORM_PLUGIN_DIR' ) ) {
			$autoload = trailingslashit( TAKA_PLATFORM_PLUGIN_DIR ) . 'vendor/autoload.php';
			if ( file_exists( $autoload ) ) {
				require_once $autoload;
			}
		}
	}

	private static function pdf_html( $html ) {
		return preg_replace( '/<head>/i', '<head><meta charset="utf-8">', (string) $html, 1 );
	}

	private static function native_pdf( $title, $sections ) {
		$pages = array();
		$content = '';
		$y = 800;
		$left = 48;
		$line_height = 15;
		self::native_add_text( $content, $left, $y, $title, 18, true );
		$y -= 32;

		foreach ( (array) $sections as $section ) {
			$heading = (string) ( $section['heading'] ?? '' );
			if ( '' !== $heading ) {
				self::native_ensure_space( $pages, $content, $y, 40 );
				self::native_add_text( $content, $left, $y, $heading, 13, true );
				$y -= 20;
			}
			foreach ( (array) ( $section['lines'] ?? array() ) as $line ) {
				foreach ( self::wrap_text( $line, 92 ) as $wrapped ) {
					self::native_ensure_space( $pages, $content, $y, $line_height );
					self::native_add_text( $content, $left, $y, $wrapped, 10, false );
					$y -= $line_height;
				}
			}
			if ( ! empty( $section['qr_payload'] ) ) {
				self::native_ensure_space( $pages, $content, $y, 175 );
				self::native_add_qr( $content, $left, $y - 150, $section['qr_payload'], 3.2 );
				$y -= 170;
			}
			$y -= 8;
		}
		$pages[] = $content;
		return self::build_pdf( $pages );
	}

	private static function native_ensure_space( &$pages, &$content, &$y, $needed ) {
		if ( $y - $needed > 48 ) {
			return;
		}
		$pages[] = $content;
		$content = '';
		$y = 800;
	}

	private static function native_add_text( &$content, $x, $y, $text, $size, $bold ) {
		$font = $bold ? 'F2' : 'F1';
		$content .= "BT /{$font} " . (float) $size . " Tf 1 0 0 1 " . (float) $x . ' ' . (float) $y . ' Tm ' . self::pdf_string( $text ) . " Tj ET\n";
	}

	private static function native_add_qr( &$content, $x, $y, $payload, $module_size ) {
		$modules = TAKA_Ticketing_QR_Code::modules( $payload );
		if ( empty( $modules ) ) {
			self::native_add_text( $content, $x, $y + 72, $payload, 8, false );
			return;
		}
		$quiet = 4;
		$dimension = ( count( $modules ) + ( $quiet * 2 ) ) * $module_size;
		$content .= "1 1 1 rg {$x} {$y} {$dimension} {$dimension} re f\n0 0 0 rg\n";
		foreach ( $modules as $row => $cols ) {
			foreach ( $cols as $col => $dark ) {
				if ( ! $dark ) {
					continue;
				}
				$rx = $x + ( ( $col + $quiet ) * $module_size );
				$ry = $y + $dimension - ( ( $row + $quiet + 1 ) * $module_size );
				$content .= sprintf( "%.3F %.3F %.3F %.3F re f\n", $rx, $ry, $module_size, $module_size );
			}
		}
	}

	private static function wrap_text( $text, $length ) {
		$text = preg_replace( '/\s+/', ' ', trim( wp_strip_all_tags( (string) $text ) ) );
		if ( '' === $text ) {
			return array( '' );
		}
		return explode( "\n", wordwrap( $text, $length, "\n", true ) );
	}

	private static function pdf_string( $text ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
		if ( function_exists( 'iconv' ) ) {
			$encoded = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text );
			if ( false !== $encoded ) {
				$text = $encoded;
			}
		}
		return '(' . strtr( $text, array( '\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => ' ', "\n" => ' ' ) ) . ')';
	}

	private static function build_pdf( $page_streams ) {
		$objects = array();
		$objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objects[] = '';
		$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
		$page_ids = array();
		foreach ( $page_streams as $stream ) {
			$content_id = count( $objects ) + 1;
			$objects[] = '<< /Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . "endstream";
			$page_id = count( $objects ) + 1;
			$objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $content_id . ' 0 R >>';
			$page_ids[] = $page_id;
		}
		$objects[1] = '<< /Type /Pages /Kids [' . implode( ' ', array_map( static function ( $id ) { return $id . ' 0 R'; }, $page_ids ) ) . '] /Count ' . count( $page_ids ) . ' >>';
		$pdf = "%PDF-1.4\n";
		$offsets = array( 0 );
		foreach ( $objects as $index => $object ) {
			$offsets[] = strlen( $pdf );
			$pdf .= ( $index + 1 ) . " 0 obj\n" . $object . "\nendobj\n";
		}
		$xref = strlen( $pdf );
		$pdf .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
		for ( $i = 1; $i <= count( $objects ); $i++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
		}
		$pdf .= "trailer\n<< /Size " . ( count( $objects ) + 1 ) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
		return $pdf;
	}
}

class TAKA_Ticketing_Ticket_Artifact_Service {
	const VERSION = 2;

	public static function ensure_order_artifacts( TAKA_Ticketing_Order $order, $persist = true ) {
		$data = $order->to_array();
		if ( empty( $data['id'] ) ) {
			return $order;
		}

		$dir = self::order_artifact_dir( $data );
		if ( '' === $dir || ! wp_mkdir_p( $dir ) ) {
			return $order;
		}
		self::protect_artifact_root();

		$tickets = self::build_tickets( $data, $dir );
		$invoice_html_path = self::invoice_html_path( $data, $dir );
		$invoice_path = self::invoice_path( $data, $dir );
		$invoice_html = self::invoice_html( $data, $tickets );
		self::write_file( $invoice_html_path, $invoice_html );
		self::write_file( $invoice_path, TAKA_Ticketing_PDF_Renderer::from_html( $invoice_html, self::document_title( $data, 'invoice' ), self::invoice_pdf_sections( $data, $tickets ) ) );

		foreach ( $tickets as $index => $ticket ) {
			if ( empty( $ticket['qr_svg'] ) ) {
				$tickets[ $index ]['qr_svg'] = TAKA_Ticketing_QR_Code::svg( $ticket['payload'], __( 'Ticket QR code', 'taka-platform' ) );
			}
			if ( ! empty( $tickets[ $index ]['qr_svg_path'] ) ) {
				self::write_file( $tickets[ $index ]['qr_svg_path'], $tickets[ $index ]['qr_svg'] );
			}
			$ticket_html = self::ticket_html( $data, $tickets[ $index ] );
			if ( ! empty( $tickets[ $index ]['html_path'] ) ) {
				self::write_file( $tickets[ $index ]['html_path'], $ticket_html );
			}
			if ( ! empty( $tickets[ $index ]['path'] ) ) {
				self::write_file( $tickets[ $index ]['path'], TAKA_Ticketing_PDF_Renderer::from_html( $ticket_html, self::document_title( $data, 'ticket' ), self::ticket_pdf_sections( $data, array( $tickets[ $index ] ) ) ) );
			}
			unset( $tickets[ $index ]['qr_svg'] );
		}
		$ticket_bundle_path = self::ticket_bundle_path( $data, $dir );
		self::write_file( $ticket_bundle_path, TAKA_Ticketing_PDF_Renderer::from_html( self::ticket_bundle_html( $data, $tickets ), self::document_title( $data, 'ticket' ), self::ticket_pdf_sections( $data, $tickets ) ) );

		$data['ticket_artifacts'] = array(
			'version'            => self::VERSION,
			'generated_at'       => current_time( 'mysql' ),
			'invoice_path'       => $invoice_path,
			'invoice_html_path'  => $invoice_html_path,
			'ticket_bundle_path' => $ticket_bundle_path,
			'tickets'            => $tickets,
		);
		$data['tickets'] = $tickets;

		if ( ! $persist || ! class_exists( 'TAKA_Ticketing_Module' ) ) {
			return new TAKA_Ticketing_Order( $data );
		}

		$saved = TAKA_Ticketing_Module::order_repository()->save( new TAKA_Ticketing_Order( $data ) );
		return is_wp_error( $saved ) ? new TAKA_Ticketing_Order( $data ) : $saved;
	}

	public static function buyer_attachments( TAKA_Ticketing_Order $order ) {
		$artifacts = self::artifacts_from_order( $order );
		$attachments = array();
		if ( ! empty( $artifacts['invoice_path'] ) && file_exists( $artifacts['invoice_path'] ) ) {
			$attachments[] = $artifacts['invoice_path'];
		}
		if ( ! empty( $artifacts['ticket_bundle_path'] ) && file_exists( $artifacts['ticket_bundle_path'] ) ) {
			$attachments[] = $artifacts['ticket_bundle_path'];
		}
		return array_values( array_unique( $attachments ) );
	}

	public static function document_path( TAKA_Ticketing_Order $order, $document ) {
		$artifacts = self::artifacts_from_order( $order );
		$key = 'invoice' === sanitize_key( $document ) ? 'invoice_path' : 'ticket_bundle_path';
		$path = (string) ( $artifacts[ $key ] ?? '' );
		return ( '' !== $path && file_exists( $path ) ) ? $path : '';
	}

	public static function recipient_attachment_map( TAKA_Ticketing_Order $order ) {
		$artifacts = self::artifacts_from_order( $order );
		$map = array();
		foreach ( (array) ( $artifacts['tickets'] ?? array() ) as $ticket ) {
			$email = sanitize_email( $ticket['recipient_email'] ?? '' );
			$path = (string) ( $ticket['path'] ?? '' );
			if ( '' === $email || '' === $path || ! file_exists( $path ) ) {
				continue;
			}
			if ( ! isset( $map[ $email ] ) ) {
				$map[ $email ] = array();
			}
			$map[ $email ][] = $path;
		}
		return $map;
	}

	public static function registration_from_ticket_payload( $payload ) {
		$ticket = self::find_ticket_by_payload( $payload );
		if ( ! $ticket || ! class_exists( 'TAKA_People_Module' ) ) {
			return null;
		}

		$registration_id = absint( $ticket['registration_id'] ?? 0 );
		if ( ! $registration_id ) {
			$order = TAKA_Ticketing_Module::order_repository()->find_by_id( absint( $ticket['order_id'] ?? 0 ) );
			$ids = $order ? array_values( array_filter( array_map( 'absint', (array) $order->get( 'registration_ids', array() ) ) ) ) : array();
			$registration_id = absint( $ids[0] ?? 0 );
		}

		return $registration_id ? TAKA_People_Module::registration_repository()->find_by_id( $registration_id ) : null;
	}

	public static function find_ticket_by_payload( $payload ) {
		$payload = trim( sanitize_text_field( $payload ) );
		if ( ! preg_match( '/^TAKA-TICKET:(\d+):([A-Za-z0-9_-]+):([A-Za-z0-9]+)/', $payload, $matches ) || ! class_exists( 'TAKA_Ticketing_Module' ) ) {
			return null;
		}

		$order = TAKA_Ticketing_Module::order_repository()->find_by_id( absint( $matches[1] ) );
		if ( ! $order ) {
			return null;
		}
		$data = $order->to_array();
		if ( in_array( (string) ( $data['order_status'] ?? '' ), array( 'cancelled' ), true ) ) {
			return null;
		}

		$ticket_id = sanitize_text_field( $matches[2] );
		$token = sanitize_text_field( $matches[3] );
		foreach ( (array) ( $data['ticket_artifacts']['tickets'] ?? $data['tickets'] ?? array() ) as $ticket ) {
			if ( (string) ( $ticket['ticket_id'] ?? '' ) !== $ticket_id ) {
				continue;
			}
			if ( ! hash_equals( (string) ( $ticket['validation_token'] ?? '' ), $token ) ) {
				return null;
			}
			$ticket['order_id'] = absint( $data['id'] ?? 0 );
			return $ticket;
		}
		return null;
	}

	private static function artifacts_from_order( TAKA_Ticketing_Order $order ) {
		$data = $order->to_array();
		$artifacts = is_array( $data['ticket_artifacts'] ?? null ) ? $data['ticket_artifacts'] : array();
		if ( empty( $artifacts['tickets'] ) || empty( $artifacts['invoice_path'] ) || empty( $artifacts['ticket_bundle_path'] ) || absint( $artifacts['version'] ?? 0 ) < self::VERSION ) {
			$order = self::ensure_order_artifacts( $order, true );
			$data = $order->to_array();
			$artifacts = is_array( $data['ticket_artifacts'] ?? null ) ? $data['ticket_artifacts'] : array();
		}
		return $artifacts;
	}

	private static function build_tickets( $data, $dir ) {
		$existing = array();
		foreach ( (array) ( $data['ticket_artifacts']['tickets'] ?? $data['tickets'] ?? array() ) as $ticket ) {
			$key = ( isset( $ticket['line_item_index'] ) ? absint( $ticket['line_item_index'] ) : -1 ) . ':' . absint( $ticket['sequence'] ?? 0 );
			$existing[ $key ] = is_array( $ticket ) ? $ticket : array();
		}

		$tickets = array();
		$registration_ids = array_values( array_filter( array_map( 'absint', (array) ( $data['registration_ids'] ?? array() ) ) ) );
		foreach ( (array) ( $data['line_items'] ?? array() ) as $line_index => $item ) {
			if ( 'discount' === (string) ( $item['item_type'] ?? '' ) ) {
				continue;
			}
			$quantity = max( 1, absint( $item['quantity'] ?? 1 ) );
			for ( $sequence = 1; $sequence <= $quantity; $sequence++ ) {
				$key = absint( $line_index ) . ':' . $sequence;
				$ticket = $existing[ $key ] ?? array();
				$ticket_id = sanitize_text_field( $ticket['ticket_id'] ?? self::generate_ticket_id( $data, $line_index, $sequence ) );
				$token = sanitize_text_field( $ticket['validation_token'] ?? wp_generate_password( 20, false, false ) );
				$payload = 'TAKA-TICKET:' . absint( $data['id'] ?? 0 ) . ':' . $ticket_id . ':' . $token;
				$recipient = self::recipient_for_line_item( $data, $item, $sequence );
				$file_base = sanitize_file_name( strtolower( $ticket_id ) );
				$ticket_dir = trailingslashit( $dir ) . 'tickets/' . $file_base;

				$tickets[] = array(
					'ticket_id'        => $ticket_id,
					'validation_token' => $token,
					'payload'          => $payload,
					'line_item_index'  => absint( $line_index ),
					'sequence'         => $sequence,
					'item_type'        => sanitize_key( $item['item_type'] ?? '' ),
					'title'            => sanitize_text_field( $item['title'] ?? '' ),
					'product_id'       => TAKA_Ticketing_Product::normalize_product_id( $item['product_id'] ?? '' ),
					'ticket_type_id'   => sanitize_key( $item['ticket_type_id'] ?? '' ),
					'related_event_id' => absint( $item['related_event_id'] ?? ( $data['event_id'] ?? 0 ) ),
					'registration_id'  => absint( $ticket['registration_id'] ?? self::registration_id_for_ticket( $registration_ids, $item, $sequence ) ),
					'recipient_email'  => $recipient['email'],
					'recipient_name'   => $recipient['name'],
					'status'           => sanitize_key( $ticket['status'] ?? 'valid' ),
					'path'             => trailingslashit( $ticket_dir ) . 'Ticket.pdf',
					'html_path'        => trailingslashit( $ticket_dir ) . 'ticket.html',
					'qr_svg_path'      => trailingslashit( $ticket_dir ) . 'ticket.svg',
					'wallet_payload'   => self::wallet_payload( $data, $item, $ticket_id, $payload ),
					'created_at'       => sanitize_text_field( $ticket['created_at'] ?? current_time( 'mysql' ) ),
				);
			}
		}
		return $tickets;
	}

	private static function registration_id_for_ticket( $registration_ids, $item, $sequence ) {
		if ( 'ticket' !== (string) ( $item['item_type'] ?? '' ) ) {
			return absint( $registration_ids[0] ?? 0 );
		}
		return absint( $registration_ids[ max( 0, absint( $sequence ) - 1 ) ] ?? ( $registration_ids[0] ?? 0 ) );
	}

	private static function recipient_for_line_item( $data, $item, $sequence ) {
		$buyer = is_array( $data['buyer'] ?? null ) ? $data['buyer'] : array();
		$participant = is_array( $data['participant'] ?? null ) ? $data['participant'] : array();
		$participants = is_array( $data['participants'] ?? null ) ? array_values( $data['participants'] ) : array();
		if ( 'ticket' === (string) ( $item['item_type'] ?? '' ) && ! empty( $participants[ max( 0, absint( $sequence ) - 1 ) ] ) && is_array( $participants[ max( 0, absint( $sequence ) - 1 ) ] ) ) {
			$person = $participants[ max( 0, absint( $sequence ) - 1 ) ];
			$email = sanitize_email( $person['email'] ?? '' );
			if ( '' === $email ) {
				$email = sanitize_email( $buyer['email'] ?? '' );
			}
			return array( 'email' => $email, 'name' => self::person_name( $person ) );
		}
		$recipient_emails = self::normalize_emails( $item['recipient_emails'] ?? array() );
		$email = sanitize_email( $recipient_emails[ max( 0, $sequence - 1 ) ] ?? '' );

		if ( '' === $email && 'ticket' === (string) ( $item['item_type'] ?? '' ) ) {
			$email = sanitize_email( $participant['email'] ?? '' );
		}
		if ( '' === $email ) {
			$email = sanitize_email( $buyer['email'] ?? '' );
		}

		$name = self::person_name( $buyer );
		if ( '' !== $email && 0 === strcasecmp( $email, sanitize_email( $participant['email'] ?? '' ) ) ) {
			$name = self::person_name( $participant );
		}
		return array( 'email' => $email, 'name' => $name );
	}

	private static function normalize_emails( $value ) {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,;]+/', $value );
		}
		$emails = array();
		foreach ( (array) $value as $email ) {
			$email = sanitize_email( $email );
			if ( '' !== $email && is_email( $email ) ) {
				$emails[] = $email;
			}
		}
		return array_values( array_unique( $emails ) );
	}

	private static function person_name( $person ) {
		$person = is_array( $person ) ? $person : array();
		return trim( sanitize_text_field( ( $person['first_name'] ?? '' ) . ' ' . ( $person['last_name'] ?? '' ) ) );
	}

	private static function generate_ticket_id( $data, $line_index, $sequence ) {
		$seed = absint( $data['id'] ?? 0 ) . '|' . sanitize_text_field( $data['public_token'] ?? '' ) . '|' . absint( $line_index ) . '|' . absint( $sequence );
		return 'TK' . absint( $data['id'] ?? 0 ) . '-' . strtoupper( substr( preg_replace( '/[^A-Za-z0-9]/', '', wp_hash( $seed ) ), 0, 8 ) ) . '-' . absint( $sequence );
	}

	private static function wallet_payload( $data, $item, $ticket_id, $payload ) {
		return array(
			'format'       => 'taka_wallet_ticket_v1',
			'serial'       => $ticket_id,
			'event'        => sanitize_text_field( $data['event_title'] ?? '' ),
			'item'         => sanitize_text_field( $item['title'] ?? '' ),
			'order_number' => sanitize_text_field( $data['order_number'] ?? '' ),
			'qr_payload'   => $payload,
		);
	}

	private static function invoice_path( $data, $dir ) {
		return trailingslashit( $dir ) . 'Rechnung.pdf';
	}

	private static function invoice_html_path( $data, $dir ) {
		return trailingslashit( $dir ) . 'invoice-' . sanitize_file_name( (string) ( $data['order_number'] ?? $data['id'] ?? 'order' ) ) . '.html';
	}

	private static function ticket_bundle_path( $data, $dir ) {
		return trailingslashit( $dir ) . 'Ticket.pdf';
	}

	private static function document_title( $data, $document ) {
		$lang = sanitize_key( $data['language'] ?? TAKA_Platform_Data::platform_fallback_language() );
		return 'invoice' === $document
			? TAKA_Ticketing_Module::text( 'ticketing.booking_confirmation_invoice', 'Booking confirmation / invoice', $lang )
			: TAKA_Ticketing_Module::text( 'ticketing.ticket', 'Ticket', $lang );
	}

	private static function invoice_html( $data, $tickets ) {
		$lang = sanitize_key( $data['language'] ?? TAKA_Platform_Data::platform_fallback_language() );
		$line_items = is_array( $data['line_items'] ?? null ) ? $data['line_items'] : array();
		$buyer = is_array( $data['buyer'] ?? null ) ? $data['buyer'] : array();
		$organizer = is_array( $data['billing_organizer'] ?? null ) ? $data['billing_organizer'] : array();
		$provider = TAKA_Ticketing_Module::payment_provider( $data['payment_method'] ?? '' );
		$instructions = $provider ? $provider->get_public_instructions( $data ) : array();
		$title = TAKA_Ticketing_Module::text( 'ticketing.booking_confirmation_invoice', 'Booking confirmation / invoice', $lang );
		$html = self::html_header( $title );
		$html .= '<h1>' . esc_html( $title ) . '</h1>';
		$html .= '<p><strong>' . esc_html( TAKA_Ticketing_Module::text( 'ticketing.order_number', 'Order number', $lang ) ) . ':</strong> ' . esc_html( $data['order_number'] ?? '' ) . '</p>';
		$html .= '<p><strong>' . esc_html( TAKA_Ticketing_Module::text( 'ticketing.event', 'Event', $lang ) ) . ':</strong> ' . esc_html( $data['event_title'] ?? '' ) . '</p>';
		$html .= '<p><strong>' . esc_html( TAKA_Ticketing_Module::text( 'ticketing.buyer', 'Buyer', $lang ) ) . ':</strong> ' . esc_html( self::person_name( $buyer ) . ( ! empty( $buyer['email'] ) ? ' <' . $buyer['email'] . '>' : '' ) ) . '</p>';
		$html .= self::organizer_invoice_html( $organizer, $lang );
		$html .= '<table><thead><tr><th>' . esc_html( TAKA_Ticketing_Module::text( 'ticketing.item', 'Item', $lang ) ) . '</th><th>' . esc_html( TAKA_Ticketing_Module::text( 'ticketing.quantity', 'Quantity', $lang ) ) . '</th><th>' . esc_html( TAKA_Ticketing_Module::text( 'ticketing.total', 'Total', $lang ) ) . '</th></tr></thead><tbody>';
		foreach ( $line_items as $item ) {
			$html .= '<tr><td>' . esc_html( $item['title'] ?? '' ) . '</td><td>' . esc_html( (string) max( 1, absint( $item['quantity'] ?? 1 ) ) ) . '</td><td>' . esc_html( TAKA_Ticketing_Module::format_money( $item['total_price'] ?? '0', $item['currency'] ?? ( $data['currency'] ?? 'EUR' ) ) ) . '</td></tr>';
		}
		$html .= '</tbody></table>';
		$html .= '<p><strong>' . esc_html( TAKA_Ticketing_Module::text( 'ticketing.final_amount', 'Final amount', $lang ) ) . ':</strong> ' . esc_html( TAKA_Ticketing_Module::format_money( $data['amount'] ?? '0', $data['currency'] ?? 'EUR' ) ) . '</p>';
		$html .= '<p><strong>' . esc_html( TAKA_Ticketing_Module::text( 'ticketing.payment_method', 'Payment method', $lang ) ) . ':</strong> ' . esc_html( TAKA_Ticketing_Module::payment_method_label( $data['payment_method'] ?? '', $lang ) ) . '</p>';
		if ( 'bank_transfer' === (string) ( $data['payment_method'] ?? '' ) ) {
			$html .= self::bank_transfer_html( $instructions, $lang );
		}
		$html .= '<p><strong>' . esc_html( TAKA_Ticketing_Module::text( 'ticketing.tickets', 'Tickets', $lang ) ) . ':</strong> ' . esc_html( (string) count( $tickets ) ) . '</p>';
		return $html . self::html_footer();
	}

	private static function ticket_html( $data, $ticket ) {
		$lang = sanitize_key( $data['language'] ?? TAKA_Platform_Data::platform_fallback_language() );
		$title = TAKA_Ticketing_Module::text( 'ticketing.ticket', 'Ticket', $lang );
		$wallet_links = apply_filters( 'taka_ticketing_wallet_links', array(), $data, $ticket );
		$html = self::html_header( $title );
		$html .= '<h1>' . esc_html( $title ) . '</h1>';
		$html .= '<h2>' . esc_html( $ticket['title'] ?? '' ) . '</h2>';
		if ( '' !== trim( (string) ( $data['event_title'] ?? '' ) ) ) {
			$html .= '<p><strong>' . esc_html( TAKA_Ticketing_Module::text( 'ticketing.event', 'Event', $lang ) ) . ':</strong> ' . esc_html( $data['event_title'] ) . '</p>';
		}
		$html .= self::event_details_html( $data, $lang );
		$html .= '<p><strong>' . esc_html( TAKA_Ticketing_Module::text( 'ticketing.order_number', 'Order number', $lang ) ) . ':</strong> ' . esc_html( $data['order_number'] ?? '' ) . '</p>';
		if ( '' !== trim( (string) ( $ticket['recipient_name'] ?? '' ) ) ) {
			$html .= '<p><strong>' . esc_html( TAKA_Ticketing_Module::text( 'ticketing.recipient', 'Recipient', $lang ) ) . ':</strong> ' . esc_html( $ticket['recipient_name'] ) . '</p>';
		}
		$html .= '<div class="qr">' . ( $ticket['qr_svg'] ?? '' ) . '</div>';
		$html .= '<p><code>' . esc_html( $ticket['payload'] ?? '' ) . '</code></p>';
		if ( ! empty( $wallet_links ) && is_array( $wallet_links ) ) {
			$html .= '<p><strong>' . esc_html( TAKA_Ticketing_Module::text( 'ticketing.wallet', 'Wallet', $lang ) ) . ':</strong> ';
			$links = array();
			foreach ( $wallet_links as $label => $url ) {
				$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
			}
			$html .= implode( ' ', $links ) . '</p>';
		}
		return $html . self::html_footer();
	}

	private static function ticket_bundle_html( $data, $tickets ) {
		$lang = sanitize_key( $data['language'] ?? TAKA_Platform_Data::platform_fallback_language() );
		$title = TAKA_Ticketing_Module::text( 'ticketing.ticket', 'Ticket', $lang );
		$html = self::html_header( $title );
		$html .= '<h1>' . esc_html( $title ) . '</h1>';
		foreach ( $tickets as $ticket ) {
			$ticket['qr_svg'] = TAKA_Ticketing_QR_Code::svg( $ticket['payload'], __( 'Ticket QR code', 'taka-platform' ) );
			$html .= '<section class="ticket-page">' . self::ticket_html_body( $data, $ticket ) . '</section>';
		}
		return $html . self::html_footer();
	}

	private static function ticket_html_body( $data, $ticket ) {
		$lang = sanitize_key( $data['language'] ?? TAKA_Platform_Data::platform_fallback_language() );
		$html = '<h2>' . esc_html( $ticket['title'] ?? '' ) . '</h2>';
		if ( '' !== trim( (string) ( $data['event_title'] ?? '' ) ) ) {
			$html .= '<p><strong>' . esc_html( TAKA_Ticketing_Module::text( 'ticketing.event', 'Event', $lang ) ) . ':</strong> ' . esc_html( $data['event_title'] ) . '</p>';
		}
		$html .= self::event_details_html( $data, $lang );
		$html .= '<p><strong>' . esc_html( TAKA_Ticketing_Module::text( 'ticketing.order_number', 'Order number', $lang ) ) . ':</strong> ' . esc_html( $data['order_number'] ?? '' ) . '</p>';
		if ( '' !== trim( (string) ( $ticket['recipient_name'] ?? '' ) ) ) {
			$html .= '<p><strong>' . esc_html( TAKA_Ticketing_Module::text( 'ticketing.recipient', 'Recipient', $lang ) ) . ':</strong> ' . esc_html( $ticket['recipient_name'] ) . '</p>';
		}
		$html .= '<div class="qr">' . ( $ticket['qr_svg'] ?? '' ) . '</div>';
		$html .= '<p><code>' . esc_html( $ticket['payload'] ?? '' ) . '</code></p>';
		return $html;
	}

	private static function organizer_invoice_html( $organizer, $lang ) {
		$lines = self::organizer_invoice_lines( $organizer, $lang );
		if ( empty( $lines ) ) {
			return '';
		}
		$html = '<section><h2>' . esc_html( TAKA_Ticketing_Module::text( 'ticketing.billing_organizer', 'Billing organizer', $lang ) ) . '</h2>';
		foreach ( $lines as $line ) {
			$html .= '<p>' . esc_html( $line ) . '</p>';
		}
		return $html . '</section>';
	}

	private static function bank_transfer_html( $instructions, $lang ) {
		$html = '<section><h2>' . esc_html( TAKA_Ticketing_Module::text( 'ticketing.bank_transfer_instructions', 'Bank transfer instructions', $lang ) ) . '</h2>';
		foreach ( self::bank_transfer_lines( $instructions, $lang ) as $line ) {
			$html .= '<p>' . esc_html( $line ) . '</p>';
		}
		return $html . '</section>';
	}

	private static function event_details_html( $data, $lang ) {
		$lines = self::event_detail_lines( $data, $lang );
		if ( empty( $lines ) ) {
			return '';
		}
		$html = '<section><h3>' . esc_html( TAKA_Ticketing_Module::text( 'ticketing.event_details', 'Event details', $lang ) ) . '</h3>';
		foreach ( $lines as $line ) {
			$html .= '<p>' . esc_html( $line ) . '</p>';
		}
		return $html . '</section>';
	}

	private static function invoice_pdf_sections( $data, $tickets ) {
		$lang = sanitize_key( $data['language'] ?? TAKA_Platform_Data::platform_fallback_language() );
		$buyer = is_array( $data['buyer'] ?? null ) ? $data['buyer'] : array();
		$sections = array(
			array(
				'heading' => TAKA_Ticketing_Module::text( 'ticketing.order', 'Order', $lang ),
				'lines'   => array_filter(
					array(
						TAKA_Ticketing_Module::text( 'ticketing.order_number', 'Order number', $lang ) . ': ' . ( $data['order_number'] ?? '' ),
						TAKA_Ticketing_Module::text( 'ticketing.event', 'Event', $lang ) . ': ' . ( $data['event_title'] ?? '' ),
						TAKA_Ticketing_Module::text( 'ticketing.buyer', 'Buyer', $lang ) . ': ' . self::person_name( $buyer ) . ( ! empty( $buyer['email'] ) ? ' <' . $buyer['email'] . '>' : '' ),
					)
				),
			),
			array(
				'heading' => TAKA_Ticketing_Module::text( 'ticketing.billing_organizer', 'Billing organizer', $lang ),
				'lines'   => self::organizer_invoice_lines( $data['billing_organizer'] ?? array(), $lang ),
			),
			array(
				'heading' => TAKA_Ticketing_Module::text( 'ticketing.order_items', 'Order items', $lang ),
				'lines'   => self::line_item_lines( $data ),
			),
			array(
				'heading' => TAKA_Ticketing_Module::text( 'ticketing.payment', 'Payment', $lang ),
				'lines'   => array(
					TAKA_Ticketing_Module::text( 'ticketing.final_amount', 'Final amount', $lang ) . ': ' . TAKA_Ticketing_Module::format_money( $data['amount'] ?? '0', $data['currency'] ?? 'EUR' ),
					TAKA_Ticketing_Module::text( 'ticketing.payment_method', 'Payment method', $lang ) . ': ' . TAKA_Ticketing_Module::payment_method_label( $data['payment_method'] ?? '', $lang ),
					TAKA_Ticketing_Module::text( 'ticketing.tickets', 'Tickets', $lang ) . ': ' . count( $tickets ),
				),
			),
		);
		if ( 'bank_transfer' === (string) ( $data['payment_method'] ?? '' ) ) {
			$provider = TAKA_Ticketing_Module::payment_provider( 'bank_transfer' );
			$sections[] = array(
				'heading' => TAKA_Ticketing_Module::text( 'ticketing.bank_transfer_instructions', 'Bank transfer instructions', $lang ),
				'lines'   => $provider ? self::bank_transfer_lines( $provider->get_public_instructions( $data ), $lang ) : array(),
			);
		}
		return $sections;
	}

	private static function ticket_pdf_sections( $data, $tickets ) {
		$lang = sanitize_key( $data['language'] ?? TAKA_Platform_Data::platform_fallback_language() );
		$sections = array();
		foreach ( $tickets as $ticket ) {
			$lines = array_merge(
				array_filter(
					array(
						TAKA_Ticketing_Module::text( 'ticketing.ticket', 'Ticket', $lang ) . ': ' . ( $ticket['title'] ?? '' ),
						TAKA_Ticketing_Module::text( 'ticketing.event', 'Event', $lang ) . ': ' . ( $data['event_title'] ?? '' ),
					)
				),
				self::event_detail_lines( $data, $lang ),
				array_filter(
					array(
						TAKA_Ticketing_Module::text( 'ticketing.order_number', 'Order number', $lang ) . ': ' . ( $data['order_number'] ?? '' ),
						TAKA_Ticketing_Module::text( 'ticketing.recipient', 'Recipient', $lang ) . ': ' . ( $ticket['recipient_name'] ?? '' ),
						TAKA_Ticketing_Module::text( 'ticketing.ticket_id', 'Ticket ID', $lang ) . ': ' . ( $ticket['ticket_id'] ?? '' ),
					)
				)
			);
			$sections[] = array(
				'heading'    => trim( ( $ticket['ticket_id'] ?? '' ) . ' ' . ( $ticket['title'] ?? '' ) ),
				'lines'      => $lines,
				'qr_payload' => $ticket['payload'] ?? '',
			);
		}
		return $sections;
	}

	private static function line_item_lines( $data ) {
		$lines = array();
		foreach ( (array) ( $data['line_items'] ?? array() ) as $item ) {
			$lines[] = sanitize_text_field( $item['title'] ?? '' ) . ' x ' . max( 1, absint( $item['quantity'] ?? 1 ) ) . ' - ' . TAKA_Ticketing_Module::format_money( $item['total_price'] ?? '0', $item['currency'] ?? ( $data['currency'] ?? 'EUR' ) );
		}
		return $lines;
	}

	private static function organizer_invoice_lines( $organizer, $lang ) {
		$organizer = is_array( $organizer ) ? $organizer : array();
		$lines = array();
		$name = trim( (string) ( $organizer['organizer_billing_name'] ?? $organizer['organizer_legal_name'] ?? $organizer['organizer_name'] ?? '' ) );
		if ( '' !== $name ) {
			$lines[] = $name;
		}
		foreach ( preg_split( '/\r\n|\r|\n/', (string) ( $organizer['organizer_address'] ?? '' ) ) as $line ) {
			if ( '' !== trim( $line ) ) {
				$lines[] = trim( $line );
			}
		}
		foreach ( array( 'organizer_email' => 'Email', 'organizer_phone' => 'Phone', 'organizer_website' => 'Website', 'organizer_tax_id' => 'VAT ID / tax number' ) as $field => $fallback ) {
			if ( '' !== trim( (string) ( $organizer[ $field ] ?? '' ) ) ) {
				$lines[] = TAKA_Ticketing_Module::text( 'ticketing.' . $field, $fallback, $lang ) . ': ' . $organizer[ $field ];
			}
		}
		return $lines;
	}

	private static function bank_transfer_lines( $instructions, $lang ) {
		$instructions = is_array( $instructions ) ? $instructions : array();
		$lines = array();
		foreach ( array( 'account_holder' => 'Account holder', 'bank_name' => 'Bank name', 'iban' => 'IBAN', 'bic' => 'BIC', 'amount' => 'Amount', 'payment_reference' => 'Payment reference', 'due_date' => 'Payment due date' ) as $field => $fallback ) {
			if ( '' !== trim( (string) ( $instructions[ $field ] ?? '' ) ) ) {
				$lines[] = TAKA_Ticketing_Module::text( 'ticketing.' . $field, $fallback, $lang ) . ': ' . $instructions[ $field ];
			}
		}
		if ( '' !== trim( (string) ( $instructions['instructions'] ?? '' ) ) ) {
			$lines[] = $instructions['instructions'];
		}
		return $lines;
	}

	private static function event_detail_lines( $data, $lang ) {
		$details = is_array( $data['event_details'] ?? null ) ? $data['event_details'] : array();
		if ( empty( $details ) && ! empty( $data['event_id'] ) && class_exists( 'TAKA_Ticketing_Module' ) ) {
			$details = TAKA_Ticketing_Module::event_ticket_details( absint( $data['event_id'] ), $lang );
		}
		$lines = array();
		foreach ( array( 'date' => 'Date', 'start_time' => 'Start time', 'end_time' => 'End time', 'doors_open' => 'Doors open', 'venue_name' => 'Venue', 'venue_address' => 'Address', 'room' => 'Room / dojo / hall' ) as $field => $fallback ) {
			if ( '' !== trim( (string) ( $details[ $field ] ?? '' ) ) ) {
				$lines[] = TAKA_Ticketing_Module::text( 'ticketing.' . $field, $fallback, $lang ) . ': ' . $details[ $field ];
			}
		}
		$schedule = is_array( $details['schedule'] ?? null ) ? $details['schedule'] : array();
		if ( count( $schedule ) > 1 ) {
			foreach ( $schedule as $item ) {
				$time = trim( (string) ( $item['time_start'] ?? '' ) . ( ! empty( $item['time_end'] ) ? ' - ' . $item['time_end'] : '' ) );
				$value = implode( ' ', array_filter( array( $item['date'] ?? '', $time, $item['title'] ?? '' ) ) );
				if ( '' !== trim( $value ) ) {
					$lines[] = TAKA_Ticketing_Module::text( 'event.schedule', 'Schedule', $lang ) . ': ' . $value;
				}
			}
		}
		return $lines;
	}

	private static function html_header( $title ) {
		return '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html( $title ) . '</title><style>body{font-family:DejaVu Sans,Arial,sans-serif;line-height:1.45;color:#1d2327;margin:24px}.qr svg{width:220px;height:220px}.ticket-page{page-break-after:always}table{border-collapse:collapse;width:100%;margin:16px 0}th,td{border:1px solid #ccd0d4;padding:8px;text-align:left}code{font-size:12px;word-break:break-all}</style></head><body>';
	}

	private static function html_footer() {
		return '</body></html>';
	}

	private static function order_artifact_dir( $data ) {
		$root = self::artifact_root();
		if ( '' === $root ) {
			return '';
		}
		$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $data['public_token'] ?? '' ) );
		return trailingslashit( $root ) . 'order-' . absint( $data['id'] ?? 0 ) . '-' . sanitize_file_name( $token ?: wp_generate_password( 12, false, false ) );
	}

	private static function artifact_root() {
		$upload = wp_upload_dir( null, false );
		if ( ! empty( $upload['error'] ) || empty( $upload['basedir'] ) ) {
			return '';
		}
		return trailingslashit( $upload['basedir'] ) . 'taka-ticketing';
	}

	private static function protect_artifact_root() {
		$root = self::artifact_root();
		if ( '' === $root || ! wp_mkdir_p( $root ) ) {
			return;
		}
		self::write_file( trailingslashit( $root ) . 'index.html', '' );
		self::write_file( trailingslashit( $root ) . '.htaccess', "Deny from all\n" );
	}

	private static function write_file( $path, $contents ) {
		if ( '' === (string) $path ) {
			return false;
		}
		$dir = dirname( $path );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( file_exists( $path ) && (string) file_get_contents( $path ) === (string) $contents ) {
			return true;
		}
		return false !== file_put_contents( $path, $contents );
	}
}

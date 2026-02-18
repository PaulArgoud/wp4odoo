<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Food Order Extractor — plugin-specific order data extraction.
 *
 * Strategy-based extractor (like Form_Field_Extractor) that normalizes
 * food order data from different plugins into a common format.
 *
 * Normalized output:
 * - partner_name:  string
 * - partner_email: string
 * - date_order:    string (Y-m-d H:i:s)
 * - amount_total:  float
 * - note:          string (special instructions)
 * - lines:         array of {name, qty, price_unit}
 * - source:        string ('gloriafoood' | 'wppizza')
 *
 * @package WP4Odoo
 * @since   3.7.0
 */
class Food_Order_Extractor {

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Extract order data from GloriaFood (Flavor) order post.
	 *
	 * GloriaFood stores order data as post meta in JSON format
	 * on the 'flavor_order' CPT.
	 *
	 * @param int $post_id GloriaFood order post ID.
	 * @return array<string, mixed> Normalized order data, or empty.
	 */
	public function extract_from_gloriafoood( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post || 'flavor_order' !== $post->post_type ) {
			$this->logger->warning( 'GloriaFood order not found.', [ 'post_id' => $post_id ] );
			return [];
		}

		$order_json = get_post_meta( $post_id, '_flavor_order_data', true );
		$order_data = is_string( $order_json ) ? json_decode( $order_json, true ) : [];

		if ( ! is_array( $order_data ) || empty( $order_data ) ) {
			$order_data = [];
		}

		$client = $order_data['client'] ?? [];
		$items  = $order_data['items'] ?? [];

		$lines = [];
		foreach ( $items as $item ) {
			$name  = $item['name'] ?? '';
			$qty   = (int) ( $item['quantity'] ?? 1 );
			$price = (float) ( $item['price'] ?? 0.0 );

			if ( '' === $name || $qty <= 0 ) {
				continue;
			}

			$lines[] = [
				'name'       => $name,
				'qty'        => $qty,
				'price_unit' => $price,
			];
		}

		return [
			'partner_name'  => (string) ( $client['name'] ?? '' ),
			'partner_email' => (string) ( $client['email'] ?? '' ),
			'date_order'    => $post->post_date_gmt ?: gmdate( 'Y-m-d H:i:s' ),
			'amount_total'  => (float) ( $order_data['total_price'] ?? 0.0 ),
			'note'          => (string) ( $order_data['instructions'] ?? '' ),
			'lines'         => $lines,
			'source'        => 'gloriafoood',
		];
	}

	/**
	 * Extract order data from WPPizza order.
	 *
	 * WPPizza stores orders in a custom table or as options
	 * (depending on version). Reads from the global store.
	 *
	 * @param int $order_id WPPizza order ID.
	 * @return array<string, mixed> Normalized order data, or empty.
	 */
	public function extract_from_wppizza( int $order_id ): array {
		$order = get_option( 'wppizza_order_' . $order_id, [] );
		if ( ! is_array( $order ) || empty( $order ) ) {
			$this->logger->warning( 'WPPizza order not found.', [ 'order_id' => $order_id ] );
			return [];
		}

		$customer = $order['customer'] ?? [];
		$items    = $order['items'] ?? [];

		$lines = [];
		foreach ( $items as $item ) {
			$name  = $item['name'] ?? '';
			$qty   = (int) ( $item['quantity'] ?? 1 );
			$price = (float) ( $item['price'] ?? 0.0 );

			if ( '' === $name || $qty <= 0 ) {
				continue;
			}

			$lines[] = [
				'name'       => $name,
				'qty'        => $qty,
				'price_unit' => $price,
			];
		}

		return [
			'partner_name'  => (string) ( $customer['name'] ?? '' ),
			'partner_email' => (string) ( $customer['email'] ?? '' ),
			'date_order'    => (string) ( $order['date'] ?? gmdate( 'Y-m-d H:i:s' ) ),
			'amount_total'  => (float) ( $order['total'] ?? 0.0 ),
			'note'          => (string) ( $order['notes'] ?? '' ),
			'lines'         => $lines,
			'source'        => 'wppizza',
		];
	}
}

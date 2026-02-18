<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Food Ordering Handler — formats food orders for Odoo POS.
 *
 * Takes normalized order data (from Food_Order_Extractor) and formats
 * it as Odoo pos.order with embedded pos.order.line tuples.
 *
 * @package WP4Odoo
 * @since   3.6.0
 */
class Food_Ordering_Handler {

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
	 * Format normalized order data as an Odoo pos.order.
	 *
	 * @param array<string, mixed> $data       Normalized order data from extractor.
	 * @param int                  $partner_id Odoo partner ID (0 if anonymous).
	 * @return array<string, mixed> Odoo-ready pos.order data, or empty.
	 */
	public function format_pos_order( array $data, int $partner_id ): array {
		$lines = $data['lines'] ?? [];
		if ( empty( $lines ) ) {
			$this->logger->warning( 'No order lines for POS order.' );
			return [];
		}

		$amount_total = (float) ( $data['amount_total'] ?? 0.0 );
		$date_order   = $data['date_order'] ?? gmdate( 'Y-m-d H:i:s' );
		$source       = $data['source'] ?? 'unknown';
		$note         = $data['note'] ?? '';

		$pos_reference = sprintf(
			'WP-%s-%s',
			strtoupper( $source ),
			gmdate( 'YmdHis' )
		);

		// Build pos.order.line One2many tuples.
		$order_lines = [];
		foreach ( $lines as $line ) {
			$line_name = $line['name'] ?? __( 'Food item', 'wp4odoo' );
			$qty       = (float) ( $line['qty'] ?? 1 );
			$price     = (float) ( $line['price_unit'] ?? 0.0 );

			$order_lines[] = [
				0,
				0,
				[
					'full_product_name'   => $line_name,
					'qty'                 => $qty,
					'price_unit'          => $price,
					'price_subtotal'      => $qty * $price,
					'price_subtotal_incl' => $qty * $price,
				],
			];
		}

		$result = [
			'pos_reference' => $pos_reference,
			'date_order'    => $date_order,
			'amount_total'  => $amount_total,
			'lines'         => $order_lines,
		];

		if ( $partner_id > 0 ) {
			$result['partner_id'] = $partner_id;
		}

		if ( '' !== $note ) {
			$result['note'] = $note;
		}

		return $result;
	}
}

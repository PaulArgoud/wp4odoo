<?php
/**
 * Stubs for WooCommerce Returns module dependencies.
 *
 * Provides YITH WooCommerce Return & Warranty and ReturnGO stubs.
 *
 * @package WP4Odoo\Tests\Stubs
 */

// YITH WooCommerce Return & Warranty stub.
if ( ! defined( 'YITH_WRMA_VERSION' ) ) {
	// Not defined by default — tests set it when needed.
}

// ReturnGO stub.
if ( ! defined( 'RETURNGO_VERSION' ) ) {
	// Not defined by default — tests set it when needed.
}

if ( ! function_exists( 'wc_create_refund' ) ) {
	/**
	 * Stub for wc_create_refund.
	 *
	 * @param array $args Refund arguments.
	 * @return WC_Order|WP_Error
	 */
	function wc_create_refund( array $args = [] ) {
		$order_id  = $args['order_id'] ?? 0;
		$amount    = $args['amount'] ?? 0;
		$reason    = $args['reason'] ?? '';
		$refund_id = $GLOBALS['_wc_next_refund_id'] ?? ( $order_id * 10 + 1 );

		$refund = new WC_Order();
		$refund->set_data(
			[
				'id'        => $refund_id,
				'type'      => 'shop_order_refund',
				'parent_id' => $order_id,
				'total'     => '-' . $amount,
				'reason'    => $reason,
			]
		);

		$GLOBALS['_wc_refunds'][ $refund_id ] = $refund;
		return $refund;
	}
}

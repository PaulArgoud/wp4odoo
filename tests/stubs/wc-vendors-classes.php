<?php
/**
 * WC Vendors test stubs.
 *
 * Provides minimal class and function stubs for unit testing
 * the WC Vendors module without the full WC Vendors plugin.
 *
 * @package WP4Odoo\Tests
 */

// ─── Detection ──────────────────────────────────────────

if ( ! defined( 'WCV_PRO_VERSION' ) ) {
	define( 'WCV_PRO_VERSION', '2.2.0' );
}

// ─── Global test stores ─────────────────────────────────

$GLOBALS['_wcv_vendors'] = [];
$GLOBALS['_wcv_orders']  = [];
$GLOBALS['_wcv_payouts'] = [];

// ─── WCV_Vendors class stub ─────────────────────────────

if ( ! class_exists( 'WCV_Vendors' ) ) {
	/**
	 * WC Vendors vendor utility class stub.
	 */
	class WCV_Vendors {

		/**
		 * Get vendor user ID for an order.
		 *
		 * @param int $order_id WC order ID.
		 * @return int Vendor user ID, or 0 if not found.
		 */
		public static function get_vendor_from_order( $order_id ) {
			$order_id = (int) $order_id;
			$order    = $GLOBALS['_wcv_orders'][ $order_id ] ?? null;
			return $order ? (int) ( $order['vendor_id'] ?? 0 ) : 0;
		}

		/**
		 * Check if a user is a vendor.
		 *
		 * @param int $user_id WordPress user ID.
		 * @return bool
		 */
		public static function is_vendor( $user_id ) {
			$user_id = (int) $user_id;
			return isset( $GLOBALS['_wcv_vendors'][ $user_id ] );
		}
	}
}

// ─── WCV_Payout stub ────────────────────────────────────

if ( ! class_exists( 'WCV_Payout' ) ) {
	/**
	 * WC Vendors payout stub.
	 */
	class WCV_Payout {

		/** @var int */
		public int $id = 0;

		/** @var int */
		public int $vendor_id = 0;

		/** @var float */
		public float $amount = 0.0;

		/** @var string */
		public string $date = '2026-01-01 00:00:00';

		/** @var string */
		public string $status = 'pending';

		/** @var string */
		public string $method = 'paypal';
	}
}

<?php
/**
 * PHPStan EDD stubs — namespaced classes and global functions.
 *
 * Separated from phpstan-bootstrap.php because PHP doesn't allow
 * mixing brace-style namespace blocks with non-namespaced code.
 *
 * @package WP4Odoo
 */

namespace EDD\Orders {
	if ( ! class_exists( 'EDD\\Orders\\Order' ) ) {
		class Order {
			public int $id          = 0;
			public float $total     = 0.0;
			public float $subtotal  = 0.0;
			public float $tax       = 0.0;
			public string $date_created = '';
			public string $status   = '';
			public string $email    = '';
			public int $customer_id = 0;
			public string $currency = '';
			public string $gateway  = '';
		}
	}
}

namespace {
	if ( ! function_exists( 'edd_get_download' ) ) {
		/**
		 * @param int $download_id
		 * @return EDD_Download|null
		 */
		function edd_get_download( int $download_id = 0 ): ?EDD_Download {
			return null;
		}
	}

	if ( ! function_exists( 'edd_get_order' ) ) {
		/**
		 * @param int $order_id
		 * @return \EDD\Orders\Order|false
		 */
		function edd_get_order( int $order_id = 0 ) {
			return false;
		}
	}

	if ( ! function_exists( 'edd_update_order_status' ) ) {
		/**
		 * @param int    $order_id
		 * @param string $new_status
		 * @return bool
		 */
		function edd_update_order_status( int $order_id, string $new_status ): bool {
			return true;
		}
	}
}

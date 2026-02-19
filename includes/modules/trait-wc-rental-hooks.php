<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC Rental hook callbacks for push operations.
 *
 * Handles WooCommerce order status changes for rental orders.
 * Runs at priority 20 (after the main WooCommerce module at priority 10)
 * so rental sync doesn't interfere with regular WC order sync.
 *
 * Expects the using class to provide:
 * - should_sync(string $key): bool (from Module_Base)
 * - handler: WC_Rental_Handler
 *
 * @package WP4Odoo
 * @since   3.7.0
 */
trait WC_Rental_Hooks {

	/**
	 * Handle WooCommerce order status change for rental orders.
	 *
	 * Only processes orders that contain at least one rental product.
	 *
	 * @param int    $order_id   WC order ID.
	 * @param string $old_status Previous order status.
	 * @param string $new_status New order status.
	 * @return void
	 */
	public function on_order_status_changed( int $order_id, string $old_status = '', string $new_status = '' ): void {
		if ( ! $this->should_sync( 'sync_rentals' ) ) {
			return;
		}

		if ( $order_id <= 0 ) {
			return;
		}

		$syncable = [ 'processing', 'completed', 'on-hold' ];
		if ( ! in_array( $new_status, $syncable, true ) ) {
			return;
		}

		$settings        = $this->get_settings();
		$rental_meta_key = $settings['rental_meta_key'] ?? '_rental';

		if ( ! $this->handler->order_has_rental_items( $order_id, $rental_meta_key ) ) {
			return;
		}

		$this->push_entity( 'rental', 'sync_rentals', $order_id );
	}
}

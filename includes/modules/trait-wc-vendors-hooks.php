<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC Vendors hook callbacks.
 *
 * Listens to WC Vendors marketplace hooks and enqueues sync jobs for
 * vendors, sub-orders, commissions, and payouts.
 *
 * Composed into WC_Vendors_Module via `use WC_Vendors_Hooks`.
 *
 * @package WP4Odoo
 * @since   3.4.0
 */
trait WC_Vendors_Hooks {

	/**
	 * Register WC Vendors hooks.
	 *
	 * Called from boot() after plugin detection.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		$settings = $this->get_settings();

		if ( ! empty( $settings['sync_vendors'] ) ) {
			add_action( 'wcvendors_shop_settings_saved', $this->safe_callback( [ $this, 'on_vendor_created' ] ), 10, 1 );
			add_action( 'wcvendors_pro_store_settings_saved', $this->safe_callback( [ $this, 'on_vendor_updated' ] ), 10, 2 );
		}

		if ( ! empty( $settings['sync_sub_orders'] ) ) {
			add_action( 'woocommerce_order_status_changed', $this->safe_callback( [ $this, 'on_sub_order_created' ] ), 10, 1 );
		}

		if ( ! empty( $settings['sync_commissions'] ) ) {
			add_action( 'wcvendors_commission_added', $this->safe_callback( [ $this, 'on_commission_sync' ] ), 10, 2 );
		}

		if ( ! empty( $settings['sync_payouts'] ) ) {
			add_action( 'wcvendors_payment_approved', $this->safe_callback( [ $this, 'on_payout_created' ] ), 10, 1 );
		}
	}

	/**
	 * Handle new vendor creation (basic settings saved).
	 *
	 * @param int $user_id Vendor user ID.
	 * @return void
	 */
	public function on_vendor_created( int $user_id ): void {
		if ( ! $this->should_sync( 'sync_vendors' ) ) {
			return;
		}

		$this->push_entity( 'vendor', 'sync_vendors', $user_id );
	}

	/**
	 * Handle vendor profile update (Pro store settings saved).
	 *
	 * @param int   $store_id Vendor user ID.
	 * @param array $data     Updated profile data.
	 * @return void
	 */
	public function on_vendor_updated( int $store_id, array $data = [] ): void {
		if ( ! $this->should_sync( 'sync_vendors' ) ) {
			return;
		}

		$this->push_entity( 'vendor', 'sync_vendors', $store_id );
	}

	/**
	 * Handle sub-order status change.
	 *
	 * Only processes orders that have a WC Vendors vendor assigned.
	 *
	 * @param int $order_id WC order ID.
	 * @return void
	 */
	public function on_sub_order_created( int $order_id ): void {
		if ( ! $this->should_sync( 'sync_sub_orders' ) ) {
			return;
		}

		$vendor_id = \WCV_Vendors::get_vendor_from_order( $order_id );
		if ( ! $vendor_id ) {
			return;
		}

		$this->push_entity( 'sub_order', 'sync_sub_orders', $order_id );
	}

	/**
	 * Handle commission added for a vendor order.
	 *
	 * @param int   $order_id WC order ID.
	 * @param float $amount   Commission amount.
	 * @return void
	 */
	public function on_commission_sync( int $order_id, float $amount = 0.0 ): void {
		if ( ! $this->should_sync( 'sync_commissions' ) ) {
			return;
		}

		$this->push_entity( 'commission', 'sync_commissions', $order_id );
	}

	/**
	 * Handle payout (payment) approval.
	 *
	 * @param int $payout_id WC Vendors payout ID.
	 * @return void
	 */
	public function on_payout_created( int $payout_id ): void {
		if ( ! $this->should_sync( 'sync_payouts' ) ) {
			return;
		}

		$this->push_entity( 'payout', 'sync_payouts', $payout_id );
	}
}

<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Inventory hook callbacks for push operations.
 *
 * Handles WC stock adjustments and optional ATUM multi-location changes.
 * Runs at priority 20 on woocommerce_product_set_stock to avoid
 * interfering with the WC module's stock.quant sync at priority 10.
 *
 * Expects the using class to provide:
 * - is_importing(): bool           (from Module_Base)
 * - push_entity(): void            (from Module_Base)
 * - get_settings(): array          (from Module_Base)
 * - logger: Logger                 (from Module_Base)
 *
 * @package WP4Odoo
 * @since   3.6.0
 */
trait WC_Inventory_Hooks {

	/**
	 * Handle WC stock adjustment event.
	 *
	 * Fires on woocommerce_product_set_stock at priority 20,
	 * after the WC module's stock.quant sync at priority 10.
	 * Creates an Odoo stock.move to trace the individual adjustment.
	 *
	 * @param \WC_Product $product WC product with updated stock.
	 * @return void
	 */
	public function on_inventory_adjustment( $product ): void {
		$product_id = $product->get_id();
		$this->push_entity( 'movement', 'sync_movements', $product_id );
	}

	/**
	 * Handle ATUM stock change event (multi-location).
	 *
	 * Fires on atum/stock_central/after_save_data when ATUM
	 * Multi-Inventory adjusts stock for a product.
	 *
	 * @param int $product_id WC product ID.
	 * @return void
	 */
	public function on_atum_stock_change( int $product_id ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$this->push_entity( 'movement', 'sync_movements', $product_id );
	}
}

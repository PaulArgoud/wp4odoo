<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ShopWP hook callbacks for push operations.
 *
 * Handles ShopWP product sync events via the `save_post_wps_products`
 * hook, which fires when ShopWP creates or updates Shopify product
 * custom post types.
 *
 * Expects the using class to provide:
 * - is_importing(): bool           (from Module_Base)
 * - get_mapping(): ?int            (from Module_Base)
 * - get_settings(): array          (from Module_Base)
 * - logger: Logger                 (from Module_Base)
 *
 * @package WP4Odoo
 * @since   2.7.5
 */
trait ShopWP_Hooks {

	/**
	 * Handle ShopWP product save.
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	public function on_product_save( int $post_id ): void {
		$this->handle_cpt_save( $post_id, 'wps_products', 'sync_products', 'product' );
	}
}

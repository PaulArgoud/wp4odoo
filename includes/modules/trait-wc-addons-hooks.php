<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC Product Add-Ons hook registrations.
 *
 * Registers hooks on WooCommerce product save to detect add-on changes
 * and enqueue sync jobs.
 *
 * @package WP4Odoo
 * @since   3.5.0
 */
trait WC_Addons_Hooks {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {
		$settings = $this->get_settings();

		if ( ! empty( $settings['sync_addons'] ) ) {
			// Hook after WooCommerce module (priority 20) to ensure product is saved first.
			add_action(
				'save_post_product',
				$this->safe_callback( [ $this, 'on_product_save' ] ),
				25,
				1
			);

			// WC Product Add-Ons fires this after saving add-ons.
			add_action(
				'woocommerce_product_addons_update',
				$this->safe_callback( [ $this, 'on_addons_update' ] ),
				10,
				2
			);
		}
	}

	// ─── Callbacks ─────────────────────────────────────────

	/**
	 * Handle product save — check if add-ons changed.
	 *
	 * @param int $post_id Product post ID.
	 * @return void
	 */
	public function on_product_save( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! $this->should_sync( 'sync_addons' ) ) {
			return;
		}

		$addons = $this->handler->load_addons( $post_id );
		if ( empty( $addons ) ) {
			return;
		}

		$this->push_entity( 'addon', 'sync_addons', $post_id );
	}

	/**
	 * Handle add-ons update event (WC Product Add-Ons specific).
	 *
	 * @param int              $product_id Product ID.
	 * @param array<string, mixed> $data       Add-ons data.
	 * @return void
	 */
	public function on_addons_update( int $product_id, array $data = [] ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$this->push_entity( 'addon', 'sync_addons', $product_id );
	}
}

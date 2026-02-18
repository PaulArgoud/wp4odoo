<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Returns hook callbacks for push operations.
 *
 * Handles WC native refund creation, YITH return approvals,
 * and ReturnGO return creation events.
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
trait WC_Returns_Hooks {

	/**
	 * Handle WooCommerce refund creation.
	 *
	 * @param int   $refund_id WC refund ID.
	 * @param array $args      Refund creation arguments.
	 * @return void
	 */
	public function on_refund_created( int $refund_id, array $args ): void {
		$this->push_entity( 'refund', 'sync_refunds', $refund_id );

		// Optionally create return picking.
		$settings = $this->get_settings();
		if ( ! empty( $settings['sync_return_pickings'] ) && ! $this->is_importing() ) {
			$this->push_entity( 'return_picking', 'sync_return_pickings', $refund_id );
		}
	}

	/**
	 * Handle YITH WooCommerce Return & Warranty request approval.
	 *
	 * YITH return requests create a WC refund upon approval.
	 * The refund hook above handles the sync — this hook is for
	 * cases where the return triggers additional return_picking logic.
	 *
	 * @param int $request_id YITH return request ID.
	 * @return void
	 */
	public function on_yith_return_approved( int $request_id ): void {
		if ( $this->is_importing() ) {
			return;
		}

		// YITH stores the refund ID in post meta.
		$refund_id = (int) get_post_meta( $request_id, '_refund_id', true );
		if ( $refund_id <= 0 ) {
			$this->logger->info( 'YITH return approved but no refund ID yet.', [ 'request_id' => $request_id ] );
			return;
		}

		// Push return picking if enabled (refund already pushed by on_refund_created).
		$settings = $this->get_settings();
		if ( ! empty( $settings['sync_return_pickings'] ) ) {
			$this->push_entity( 'return_picking', 'sync_return_pickings', $refund_id );
		}
	}

	/**
	 * Handle ReturnGO return creation.
	 *
	 * ReturnGO fires this hook when a return is fully processed.
	 * The return data includes the WC order ID and refund amount.
	 *
	 * @param array $return_data ReturnGO return data.
	 * @return void
	 */
	public function on_returngo_return( array $return_data ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$refund_id = (int) ( $return_data['refund_id'] ?? 0 );
		if ( $refund_id <= 0 ) {
			$this->logger->info( 'ReturnGO return without refund ID.', [ 'data' => $return_data ] );
			return;
		}

		// Push return picking if enabled (refund already pushed by on_refund_created).
		$settings = $this->get_settings();
		if ( ! empty( $settings['sync_return_pickings'] ) ) {
			$this->push_entity( 'return_picking', 'sync_return_pickings', $refund_id );
		}
	}
}

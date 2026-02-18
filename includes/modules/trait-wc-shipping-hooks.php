<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Shipping hook callbacks for push operations.
 *
 * Handles AST tracking additions, ShipStation ship notifications,
 * Sendcloud parcel status changes, and Packlink tracking updates.
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
trait WC_Shipping_Hooks {

	/**
	 * Handle AST (Advanced Shipment Tracking) tracking added event.
	 *
	 * Fired when a tracking number is added to an order via the AST plugin
	 * or compatible tracking plugins.
	 *
	 * @param int   $order_id      WC order ID.
	 * @param array $tracking_item AST-format tracking item.
	 * @return void
	 */
	public function on_ast_tracking_added( int $order_id, array $tracking_item ): void {
		$this->push_entity( 'shipment', 'sync_tracking_push', $order_id );
	}

	/**
	 * Handle ShipStation ship notification.
	 *
	 * Fired when ShipStation sends a shipment notification via its
	 * WooCommerce integration webhook.
	 *
	 * @param \WC_Order $order    WC order.
	 * @param array     $tracking Tracking data from ShipStation.
	 * @return void
	 */
	public function on_shipstation_shipped( $order, array $tracking ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$order_id = $order->get_id();
		$this->push_entity( 'shipment', 'sync_tracking_push', $order_id );
	}

	/**
	 * Handle Sendcloud parcel status change.
	 *
	 * Fired when a Sendcloud parcel changes status (e.g. "shipped").
	 *
	 * @param int    $order_id WC order ID.
	 * @param string $status   New parcel status.
	 * @param array  $parcel   Sendcloud parcel data.
	 * @return void
	 */
	public function on_sendcloud_status( int $order_id, string $status, array $parcel ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$this->push_entity( 'shipment', 'sync_tracking_push', $order_id );
	}

	/**
	 * Handle Packlink tracking update.
	 *
	 * Fired when Packlink provides a tracking update for an order.
	 *
	 * @param int   $order_id      WC order ID.
	 * @param array $tracking_data Packlink tracking data.
	 * @return void
	 */
	public function on_packlink_tracking( int $order_id, array $tracking_data ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$this->push_entity( 'shipment', 'sync_tracking_push', $order_id );
	}
}

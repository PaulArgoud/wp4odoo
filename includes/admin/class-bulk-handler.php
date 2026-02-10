<?php
declare( strict_types=1 );

namespace WP4Odoo\Admin;

use WP4Odoo\API\Odoo_Client;
use WP4Odoo\Entity_Map_Repository;
use WP4Odoo\Queue_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles bulk product import/export operations.
 *
 * Encapsulates the common pattern: fetch IDs, check mappings, enqueue jobs.
 *
 * @package WP4Odoo
 * @since   1.5.0
 */
final class Bulk_Handler {

	/**
	 * Odoo API client.
	 *
	 * @var Odoo_Client
	 */
	private Odoo_Client $client;

	/**
	 * Constructor.
	 *
	 * @param Odoo_Client $client Odoo API client.
	 */
	public function __construct( Odoo_Client $client ) {
		$this->client = $client;
	}

	/**
	 * Import all products from Odoo into WooCommerce via the queue.
	 *
	 * @return array{enqueued: int, message: string}
	 */
	public function import_products(): array {
		$odoo_ids = $this->client->search( 'product.template', [] );

		if ( empty( $odoo_ids ) ) {
			return [
				'enqueued' => 0,
				'message'  => __( 'No products found in Odoo.', 'wp4odoo' ),
			];
		}

		$enqueued = 0;
		foreach ( $odoo_ids as $odoo_id ) {
			$odoo_id = (int) $odoo_id;
			$wp_id   = Entity_Map_Repository::get_wp_id( 'woocommerce', 'product', $odoo_id );
			$action  = $wp_id ? 'update' : 'create';

			Queue_Manager::pull( 'woocommerce', 'product', $action, $odoo_id, $wp_id, [], 5 );
			++$enqueued;
		}

		return [
			'enqueued' => $enqueued,
			'message'  => sprintf(
				/* translators: %d: number of products enqueued */
				__( '%d product(s) enqueued for import.', 'wp4odoo' ),
				$enqueued
			),
		];
	}

	/**
	 * Export all WooCommerce products to Odoo via the queue.
	 *
	 * @return array{enqueued: int, message: string}
	 */
	public function export_products(): array {
		$product_ids = wc_get_products( [ 'limit' => -1, 'return' => 'ids' ] );

		if ( empty( $product_ids ) ) {
			return [
				'enqueued' => 0,
				'message'  => __( 'No WooCommerce products found.', 'wp4odoo' ),
			];
		}

		$enqueued = 0;
		foreach ( $product_ids as $wp_id ) {
			$wp_id   = (int) $wp_id;
			$odoo_id = Entity_Map_Repository::get_odoo_id( 'woocommerce', 'product', $wp_id );
			$action  = $odoo_id ? 'update' : 'create';

			Queue_Manager::push( 'woocommerce', 'product', $action, $wp_id, $odoo_id ?? 0 );
			++$enqueued;
		}

		return [
			'enqueued' => $enqueued,
			'message'  => sprintf(
				/* translators: %d: number of products enqueued */
				__( '%d product(s) enqueued for export.', 'wp4odoo' ),
				$enqueued
			),
		];
	}
}

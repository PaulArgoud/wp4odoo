<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC Bundle BOM Handler — data access for WC Product Bundles and Composite Products.
 *
 * Loads bundle/composite component data from WooCommerce products and formats
 * them as Odoo Manufacturing BOM (mrp.bom) records with One2many bom_line_ids.
 *
 * Supports both WC Product Bundles (get_bundled_items) and WC Composite Products
 * (get_composite_data). Optional components are excluded from the BOM.
 *
 * Called by WC_Bundle_BOM_Module via its load_wp_data dispatch.
 *
 * @package WP4Odoo
 * @since   3.0.5
 */
class WC_Bundle_BOM_Handler {

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	// ─── Load components ──────────────────────────────────

	/**
	 * Load components from a bundle or composite WC product.
	 *
	 * Returns an array of required (non-optional) components with their
	 * WordPress product ID and quantity. Optional components are excluded
	 * because a manufacturing BOM has no optional parts.
	 *
	 * @param int $product_id WC product ID.
	 * @return array<int, array{wp_product_id: int, quantity: int}> Components, or empty if not a bundle/composite.
	 */
	public function load_bundle_or_composite( int $product_id ): array {
		$product = \wc_get_product( $product_id );
		if ( ! $product ) {
			$this->logger->warning( 'Bundle/composite product not found.', [ 'product_id' => $product_id ] );
			return [];
		}

		$type = $product->get_type();

		if ( 'bundle' === $type && $product instanceof \WC_Product_Bundle ) {
			return $this->load_bundle( $product );
		}

		if ( 'composite' === $type && $product instanceof \WC_Product_Composite ) {
			return $this->load_composite( $product );
		}

		return [];
	}

	/**
	 * Load components from a WC Product Bundle.
	 *
	 * Iterates bundled items, filters out optional ones, and returns
	 * the required components with product ID and quantity.
	 *
	 * @param \WC_Product_Bundle $product Bundle product.
	 * @return array<int, array{wp_product_id: int, quantity: int}>
	 */
	private function load_bundle( \WC_Product_Bundle $product ): array {
		$items      = $product->get_bundled_items();
		$components = [];

		foreach ( $items as $item ) {
			if ( $item->is_optional() ) {
				continue;
			}

			$comp_id = $item->get_product_id();
			if ( $comp_id <= 0 ) {
				continue;
			}

			$components[] = [
				'wp_product_id' => $comp_id,
				'quantity'      => $item->get_quantity( 'min' ),
			];
		}

		return $components;
	}

	/**
	 * Load components from a WC Composite Product.
	 *
	 * Reads composite_data slots, filters out optional ones, and takes
	 * the first product from each slot's query_ids with quantity_min.
	 * This simplification covers the majority of use cases.
	 *
	 * @param \WC_Product_Composite $product Composite product.
	 * @return array<int, array{wp_product_id: int, quantity: int}>
	 */
	private function load_composite( \WC_Product_Composite $product ): array {
		$composite_data = $product->get_composite_data();
		$components     = [];

		foreach ( $composite_data as $slot ) {
			if ( ! empty( $slot['optional'] ) ) {
				continue;
			}

			$query_ids = $slot['query_ids'] ?? [];
			if ( empty( $query_ids ) || ! is_array( $query_ids ) ) {
				continue;
			}

			$first_id = (int) reset( $query_ids );
			if ( $first_id <= 0 ) {
				continue;
			}

			$components[] = [
				'wp_product_id' => $first_id,
				'quantity'      => (int) ( $slot['quantity_min'] ?? 1 ),
			];
		}

		return $components;
	}

	// ─── Format BOM ──────────────────────────────────────

	/**
	 * Format a BOM record for Odoo (mrp.bom).
	 *
	 * Builds the bom_line_ids One2many field using [5,0,0] (clear) + [0,0,{...}]
	 * (create) tuples. The product_tmpl_id and component Odoo IDs must be
	 * resolved by the caller (module).
	 *
	 * @param int    $product_tmpl_id Odoo product.template ID for the parent product.
	 * @param array  $component_lines Array of [odoo_id => int, quantity => int].
	 * @param string $bom_type        BOM type: 'phantom' (kit) or 'normal' (manufacture).
	 * @param int    $product_id      WC product ID (for the reference code).
	 * @return array<string, mixed> Odoo mrp.bom values.
	 */
	public function format_bom( int $product_tmpl_id, array $component_lines, string $bom_type, int $product_id ): array {
		// Clear existing lines, then create new ones.
		$bom_line_ids = [ [ 5, 0, 0 ] ];

		foreach ( $component_lines as $line ) {
			$bom_line_ids[] = [
				0,
				0,
				[
					'product_id'  => $line['odoo_id'],
					'product_qty' => (float) ( $line['quantity'] ?? 1 ),
				],
			];
		}

		return [
			'product_tmpl_id' => $product_tmpl_id,
			'type'            => $bom_type,
			'product_qty'     => 1.0,
			'bom_line_ids'    => $bom_line_ids,
			'code'            => 'WC-' . $product_id,
		];
	}

	// ─── Helpers ──────────────────────────────────────────

	/**
	 * Check whether a WC product is a bundle or composite.
	 *
	 * @param int $product_id WC product ID.
	 * @return bool
	 */
	public function is_bundle_or_composite( int $product_id ): bool {
		$product = \wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		return in_array( $product->get_type(), [ 'bundle', 'composite' ], true );
	}
}

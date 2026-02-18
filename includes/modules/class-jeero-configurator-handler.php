<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Jeero Configurator Handler — data access for configurable products.
 *
 * Loads Jeero Product Configurator rules from WooCommerce product meta
 * and formats them as Odoo Manufacturing BOM (mrp.bom) records with
 * One2many bom_line_ids.
 *
 * Configuration rules are stored in the `_jeero_configuration_rules`
 * post meta as a serialized array of component products and quantities.
 *
 * Called by Jeero_Configurator_Module via its load_wp_data dispatch.
 *
 * @package WP4Odoo
 * @since   3.6.0
 */
class Jeero_Configurator_Handler {

	/**
	 * Post meta key for Jeero configuration rules.
	 *
	 * @var string
	 */
	private const RULES_META_KEY = '_jeero_configuration_rules';

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

	// ─── Load rules ─────────────────────────────────────────

	/**
	 * Load configuration rules for a WooCommerce product.
	 *
	 * Returns an array of required components with their WordPress
	 * product ID and quantity. Only includes components with valid
	 * product IDs.
	 *
	 * @param int $product_id WC product ID.
	 * @return array<int, array{wp_product_id: int, quantity: int}> Components, or empty.
	 */
	public function load_configuration_rules( int $product_id ): array {
		if ( $product_id <= 0 ) {
			return [];
		}

		$rules_raw = get_post_meta( $product_id, self::RULES_META_KEY, true );
		if ( ! is_array( $rules_raw ) || empty( $rules_raw ) ) {
			$this->logger->info( 'No Jeero configuration rules found.', [ 'product_id' => $product_id ] );
			return [];
		}

		$components = [];

		foreach ( $rules_raw as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$comp_id  = (int) ( $rule['product_id'] ?? 0 );
			$quantity = (int) ( $rule['quantity'] ?? 1 );

			if ( $comp_id <= 0 ) {
				continue;
			}

			if ( $quantity <= 0 ) {
				$quantity = 1;
			}

			$components[] = [
				'wp_product_id' => $comp_id,
				'quantity'      => $quantity,
			];
		}

		return $components;
	}

	// ─── Format BOM ─────────────────────────────────────────

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
			'code'            => 'JEERO-' . $product_id,
		];
	}

	// ─── Helpers ────────────────────────────────────────────

	/**
	 * Check whether a WC product is a Jeero configurable product.
	 *
	 * A product is configurable if it has Jeero configuration rules
	 * stored in post meta.
	 *
	 * @param int $product_id WC product ID.
	 * @return bool
	 */
	public function is_configurable_product( int $product_id ): bool {
		if ( $product_id <= 0 ) {
			return false;
		}

		$rules = get_post_meta( $product_id, self::RULES_META_KEY, true );

		return is_array( $rules ) && ! empty( $rules );
	}
}

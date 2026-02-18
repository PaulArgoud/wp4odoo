<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Entity_Map_Repository;
use WP4Odoo\Field_Mapper;
use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Inventory Handler — data access for warehouses, stock locations,
 * and stock movements.
 *
 * Loads WC stock change data, formats stock.move records for Odoo,
 * parses Odoo warehouses/locations/movements back to WC format,
 * and manages cross-module product resolution.
 *
 * @package WP4Odoo
 * @since   3.6.0
 */
class WC_Inventory_Handler {

	/**
	 * Stock.move state mapping.
	 *
	 * @var array<string, string>
	 */
	private const MOVEMENT_STATUS_MAP = [
		'draft'     => 'draft',
		'waiting'   => 'waiting',
		'confirmed' => 'confirmed',
		'assigned'  => 'assigned',
		'done'      => 'done',
		'cancel'    => 'cancel',
	];

	/**
	 * Option key prefix for cached warehouse data.
	 *
	 * @var string
	 */
	private const WAREHOUSE_OPTION_PREFIX = 'wp4odoo_warehouse_';

	/**
	 * Option key prefix for cached location data.
	 *
	 * @var string
	 */
	private const LOCATION_OPTION_PREFIX = 'wp4odoo_location_';

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Closure returning the Odoo_Client (reserved for future API calls).
	 *
	 * @var \Closure
	 * @phpstan-ignore property.onlyWritten
	 */
	private \Closure $client_fn;

	/**
	 * Entity map repository for cross-module lookups.
	 *
	 * @var Entity_Map_Repository
	 */
	private Entity_Map_Repository $entity_map;

	/**
	 * Constructor.
	 *
	 * @param Logger                $logger     Logger instance.
	 * @param \Closure              $client_fn  Client provider closure.
	 * @param Entity_Map_Repository $entity_map Entity map repository.
	 */
	public function __construct( Logger $logger, \Closure $client_fn, Entity_Map_Repository $entity_map ) {
		$this->logger     = $logger;
		$this->client_fn  = $client_fn;
		$this->entity_map = $entity_map;
	}

	// ─── Warehouses (pull-only) ────────────────────────────

	/**
	 * Parse an Odoo stock.warehouse record.
	 *
	 * @param array<string, mixed> $odoo_data Raw Odoo record data.
	 * @return array<string, mixed> Parsed warehouse data.
	 */
	public function parse_warehouse_from_odoo( array $odoo_data ): array {
		return [
			'name'         => $odoo_data['name'] ?? '',
			'code'         => $odoo_data['code'] ?? '',
			'lot_stock_id' => Field_Mapper::many2one_to_id( $odoo_data['lot_stock_id'] ?? 0 ),
		];
	}

	/**
	 * Save a pulled warehouse as a WP option.
	 *
	 * @param array<string, mixed> $data  Parsed warehouse data.
	 * @param int                  $wp_id Existing WP reference ID (0 if creating).
	 * @return int Reference ID.
	 */
	public function save_warehouse( array $data, int $wp_id ): int {
		$ref_id = $wp_id > 0 ? $wp_id : absint( crc32( $data['code'] ?? '' ) );
		update_option( self::WAREHOUSE_OPTION_PREFIX . $ref_id, $data, false );

		$this->logger->info(
			'Saved Odoo warehouse.',
			[
				'ref_id' => $ref_id,
				'name'   => $data['name'] ?? '',
			]
		);

		return $ref_id;
	}

	// ─── Locations (pull-only) ─────────────────────────────

	/**
	 * Parse an Odoo stock.location record.
	 *
	 * @param array<string, mixed> $odoo_data Raw Odoo record data.
	 * @return array<string, mixed> Parsed location data.
	 */
	public function parse_location_from_odoo( array $odoo_data ): array {
		return [
			'name'          => $odoo_data['complete_name'] ?? ( $odoo_data['name'] ?? '' ),
			'location_type' => $odoo_data['usage'] ?? 'internal',
			'parent_id'     => Field_Mapper::many2one_to_id( $odoo_data['location_id'] ?? 0 ),
		];
	}

	/**
	 * Save a pulled stock location as a WP option.
	 *
	 * @param array<string, mixed> $data  Parsed location data.
	 * @param int                  $wp_id Existing WP reference ID (0 if creating).
	 * @return int Reference ID.
	 */
	public function save_location( array $data, int $wp_id ): int {
		$ref_id = $wp_id > 0 ? $wp_id : absint( crc32( $data['name'] ?? '' ) );
		update_option( self::LOCATION_OPTION_PREFIX . $ref_id, $data, false );

		$this->logger->info(
			'Saved Odoo stock location.',
			[
				'ref_id' => $ref_id,
				'name'   => $data['name'] ?? '',
			]
		);

		return $ref_id;
	}

	// ─── Movements (bidi) ──────────────────────────────────

	/**
	 * Load a WC stock change event as an Odoo stock.move.
	 *
	 * Uses the WC product's stock change log to build movement data.
	 *
	 * @param int $product_id WC product ID.
	 * @return array<string, mixed> Odoo-ready stock.move data, or empty.
	 */
	public function load_movement( int $product_id ): array {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			$this->logger->warning( 'WC product not found for movement.', [ 'product_id' => $product_id ] );
			return [];
		}

		// Resolve to Odoo product.
		$odoo_product_id = $this->resolve_product_odoo_id( $product_id );
		if ( ! $odoo_product_id ) {
			$this->logger->warning( 'Cannot resolve Odoo product for movement.', [ 'product_id' => $product_id ] );
			return [];
		}

		$stock_quantity = (float) $product->get_stock_quantity();
		$reference      = sprintf( 'WC-ADJ-%d-%s', $product_id, gmdate( 'Ymd-His' ) );

		return [
			'product_id'      => $odoo_product_id,
			'product_uom_qty' => abs( $stock_quantity ),
			'name'            => sprintf(
				/* translators: %s: WC product name */
				__( 'WC stock adjustment: %s', 'wp4odoo' ),
				$product->get_name()
			),
			'reference'       => $reference,
			'date'            => gmdate( 'Y-m-d H:i:s' ),
			'state'           => 'draft',
		];
	}

	/**
	 * Parse an Odoo stock.move record into WC-compatible format.
	 *
	 * @param array<string, mixed> $odoo_data Raw Odoo record data.
	 * @return array<string, mixed> Parsed movement data.
	 */
	public function parse_movement_from_odoo( array $odoo_data ): array {
		$product_odoo_id = Field_Mapper::many2one_to_id( $odoo_data['product_id'] ?? 0 );
		$quantity        = (float) ( $odoo_data['product_uom_qty'] ?? 0.0 );
		$state           = $odoo_data['state'] ?? 'draft';
		$reference       = $odoo_data['reference'] ?? '';

		// Resolve Odoo product → WC product.
		$wp_product_id = 0;
		if ( $product_odoo_id > 0 ) {
			$wp_product_id = $this->entity_map->get_wp_id( 'woocommerce', 'product', $product_odoo_id );
			if ( ! $wp_product_id ) {
				$wp_product_id = $this->entity_map->get_wp_id( 'woocommerce', 'variant', $product_odoo_id );
			}
		}

		return [
			'product_id'      => $wp_product_id,
			'odoo_product_id' => $product_odoo_id,
			'quantity'        => $quantity,
			'state'           => $this->map_movement_state( (string) $state ),
			'reference'       => $reference,
			'date'            => $odoo_data['date'] ?? '',
			'name'            => $odoo_data['name'] ?? '',
			'source_location' => Field_Mapper::many2one_to_id( $odoo_data['location_id'] ?? 0 ),
			'dest_location'   => Field_Mapper::many2one_to_id( $odoo_data['location_dest_id'] ?? 0 ),
		];
	}

	/**
	 * Save a pulled stock movement — apply stock change to WC product.
	 *
	 * Only applies completed movements (state = done). Adjusts WC stock
	 * based on the movement direction (incoming = increase, outgoing = decrease).
	 *
	 * @param array<string, mixed> $data  Parsed movement data.
	 * @param int                  $wp_id Existing WP reference ID (0 if creating).
	 * @return int Reference ID on success, 0 on failure.
	 */
	public function save_movement( array $data, int $wp_id ): int {
		$product_id = (int) ( $data['product_id'] ?? 0 );
		if ( $product_id <= 0 ) {
			$this->logger->warning( 'Cannot save movement: no WC product resolved.' );
			return 0;
		}

		$state = $data['state'] ?? '';
		if ( 'done' !== $state ) {
			$this->logger->info(
				'Movement not yet done — skipping stock adjustment.',
				[
					'state'     => $state,
					'reference' => $data['reference'] ?? '',
				]
			);
			// Still save the reference for tracking.
			return $wp_id > 0 ? $wp_id : $product_id;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			$this->logger->warning( 'WC product not found for movement save.', [ 'product_id' => $product_id ] );
			return 0;
		}

		$quantity = (float) ( $data['quantity'] ?? 0.0 );
		if ( $quantity <= 0 ) {
			return $wp_id > 0 ? $wp_id : $product_id;
		}

		// Apply stock change.
		$current_stock = (float) $product->get_stock_quantity();
		$product->set_stock_quantity( (int) ( $current_stock + $quantity ) );
		$product->save();

		$this->logger->info(
			'Applied stock movement from Odoo.',
			[
				'product_id' => $product_id,
				'quantity'   => $quantity,
				'reference'  => $data['reference'] ?? '',
			]
		);

		return $wp_id > 0 ? $wp_id : $product_id;
	}

	// ─── Status mapping ────────────────────────────────────

	/**
	 * Map an Odoo stock.move state (identity mapping, exposed for filtering).
	 *
	 * @param string $state Odoo stock.move state.
	 * @return string Mapped state.
	 */
	public function map_movement_state( string $state ): string {
		return Status_Mapper::resolve( $state, self::MOVEMENT_STATUS_MAP, 'wp4odoo_wc_inventory_movement_state_map', 'draft' );
	}

	// ─── Helpers ───────────────────────────────────────────

	/**
	 * Resolve a WC product ID to its Odoo product.product ID.
	 *
	 * Cross-module lookup via entity_map: checks both 'product' and 'variant'
	 * entity types in the WooCommerce module.
	 *
	 * @param int $wp_product_id WC product ID.
	 * @return int Odoo product ID, or 0 if not found.
	 */
	public function resolve_product_odoo_id( int $wp_product_id ): int {
		$odoo_id = $this->entity_map->get_odoo_id( 'woocommerce', 'product', $wp_product_id );
		if ( $odoo_id ) {
			return $odoo_id;
		}

		$odoo_id = $this->entity_map->get_odoo_id( 'woocommerce', 'variant', $wp_product_id );
		return $odoo_id ?: 0;
	}

	/**
	 * Get the default stock location ID from the main warehouse.
	 *
	 * Cached in a WP option for performance.
	 *
	 * @return int Odoo stock location ID, or 0 if unknown.
	 */
	public function get_default_location_id(): int {
		$cached = (int) get_option( 'wp4odoo_default_stock_location_id', 0 );
		if ( $cached > 0 ) {
			return $cached;
		}

		return 0;
	}

	/**
	 * Check if ATUM Multi-Inventory is active.
	 *
	 * @return bool
	 */
	public function has_atum(): bool {
		return defined( 'ATUM_VERSION' );
	}
}

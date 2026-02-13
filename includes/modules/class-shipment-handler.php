<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Field_Mapper;
use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles shipment tracking import from Odoo (stock.picking → WC order meta).
 *
 * Fetches completed outgoing delivery orders from Odoo for a given sale order,
 * extracts carrier and tracking information, and stores it in WooCommerce order
 * metadata using the Advanced Shipment Tracking (AST) format for compatibility.
 *
 * @package WP4Odoo
 * @since   3.0.0
 */
class Shipment_Handler {

	/**
	 * WC order meta key used by the AST plugin.
	 *
	 * @var string
	 */
	private const AST_META_KEY = '_wc_shipment_tracking_items';

	/**
	 * Plugin's own meta key for shipment tracking data.
	 *
	 * @var string
	 */
	private const OWN_META_KEY = '_wp4odoo_shipment_tracking';

	/**
	 * Post meta key for the tracking data hash (change detection).
	 *
	 * @var string
	 */
	private const HASH_META = '_wp4odoo_shipment_hash';

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Closure returning the Odoo_Client.
	 *
	 * @var \Closure
	 */
	private \Closure $client_fn;

	/**
	 * Constructor.
	 *
	 * @param Logger   $logger    Logger instance.
	 * @param \Closure $client_fn Closure returning \WP4Odoo\API\Odoo_Client.
	 */
	public function __construct( Logger $logger, \Closure $client_fn ) {
		$this->logger    = $logger;
		$this->client_fn = $client_fn;
	}

	/**
	 * Pull shipment tracking data from Odoo for a sale order.
	 *
	 * Queries Odoo for outgoing, completed stock.picking records linked
	 * to the sale order, builds tracking items, and saves them to the
	 * WC order metadata.
	 *
	 * @param int $odoo_order_id Odoo sale.order ID.
	 * @param int $wc_order_id   WC order ID.
	 * @return bool True on success (even if no trackable pickings found).
	 */
	public function pull_shipments( int $odoo_order_id, int $wc_order_id ): bool {
		try {
			$client   = ( $this->client_fn )();
			$pickings = $client->search_read(
				'stock.picking',
				[
					[ 'sale_id', '=', $odoo_order_id ],
					[ 'picking_type_code', '=', 'outgoing' ],
					[ 'state', '=', 'done' ],
				],
				[ 'name', 'carrier_tracking_ref', 'carrier_id', 'date_done' ]
			);
		} catch ( \Throwable $e ) {
			$this->logger->warning(
				'Failed to fetch shipments from Odoo.',
				[
					'odoo_order_id' => $odoo_order_id,
					'error'         => $e->getMessage(),
				]
			);
			return false;
		}

		if ( empty( $pickings ) ) {
			$this->logger->info(
				'No completed outgoing pickings for order.',
				[ 'odoo_order_id' => $odoo_order_id ]
			);
			return true;
		}

		$valid_pickings = $this->filter_valid_pickings( $pickings );

		if ( empty( $valid_pickings ) ) {
			return true;
		}

		$tracking_items = [];
		foreach ( $valid_pickings as $picking ) {
			$tracking_items[] = $this->build_tracking_item( $picking );
		}

		return $this->save_tracking( $wc_order_id, $tracking_items );
	}

	/**
	 * Save tracking data to WC order metadata.
	 *
	 * Uses SHA-256 hash for change detection to avoid unnecessary updates.
	 * Stores in both AST-compatible and plugin-specific meta keys.
	 *
	 * @param int   $wc_order_id    WC order ID.
	 * @param array $tracking_items Array of AST-format tracking items.
	 * @return bool True if tracking data was saved (false if unchanged).
	 */
	public function save_tracking( int $wc_order_id, array $tracking_items ): bool {
		$new_hash = $this->generate_tracking_hash( $tracking_items );

		$order = wc_get_order( $wc_order_id );
		if ( ! $order ) {
			$this->logger->warning(
				'Cannot save tracking: WC order not found.',
				[ 'wc_order_id' => $wc_order_id ]
			);
			return false;
		}

		$old_hash = $order->get_meta( self::HASH_META );

		if ( $new_hash === $old_hash ) {
			return false;
		}

		$order->update_meta_data( self::AST_META_KEY, $tracking_items );
		$order->update_meta_data( self::OWN_META_KEY, $tracking_items );
		$order->update_meta_data( self::HASH_META, $new_hash );
		$order->save();

		$this->logger->info(
			'Shipment tracking saved.',
			[
				'wc_order_id'    => $wc_order_id,
				'tracking_count' => count( $tracking_items ),
			]
		);

		return true;
	}

	/**
	 * Build a single tracking item from an Odoo stock.picking record.
	 *
	 * @param array $picking Odoo stock.picking record.
	 * @return array AST-format tracking item.
	 */
	private function build_tracking_item( array $picking ): array {
		$carrier_name = '';
		$carrier_raw  = $picking['carrier_id'] ?? false;
		if ( is_array( $carrier_raw ) || ( is_int( $carrier_raw ) && $carrier_raw > 0 ) ) {
			$carrier_name = Field_Mapper::many2one_to_name( $carrier_raw ) ?? '';
		}

		$date_done = $picking['date_done'] ?? '';
		$timestamp = 0;
		if ( is_string( $date_done ) && '' !== $date_done && 'false' !== $date_done ) {
			$dt = \DateTime::createFromFormat( 'Y-m-d H:i:s', $date_done, new \DateTimeZone( 'UTC' ) );
			if ( false === $dt ) {
				$dt = \DateTime::createFromFormat( 'Y-m-d', $date_done, new \DateTimeZone( 'UTC' ) );
			}
			if ( false !== $dt ) {
				$timestamp = $dt->getTimestamp();
			}
		}

		return [
			'tracking_provider'        => $carrier_name,
			'custom_tracking_provider' => '',
			'custom_tracking_link'     => '',
			'tracking_number'          => (string) ( $picking['carrier_tracking_ref'] ?? '' ),
			'date_shipped'             => (string) $timestamp,
			'status_shipped'           => 1,
		];
	}

	/**
	 * Generate a SHA-256 hash of the tracking items for change detection.
	 *
	 * Sorts by tracking number for deterministic output.
	 *
	 * @param array $tracking_items Array of tracking items.
	 * @return string SHA-256 hash.
	 */
	private function generate_tracking_hash( array $tracking_items ): string {
		$sorted = $tracking_items;
		usort(
			$sorted,
			function ( array $a, array $b ): int {
				return strcmp( $a['tracking_number'] ?? '', $b['tracking_number'] ?? '' );
			}
		);

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Internal hash, not user-facing.
		return hash( 'sha256', serialize( $sorted ) );
	}

	/**
	 * Filter out pickings without a valid tracking reference.
	 *
	 * @param array $pickings Array of Odoo stock.picking records.
	 * @return array Filtered pickings with non-empty carrier_tracking_ref.
	 */
	private function filter_valid_pickings( array $pickings ): array {
		$valid = [];

		foreach ( $pickings as $picking ) {
			$ref = $picking['carrier_tracking_ref'] ?? '';

			if ( is_string( $ref ) && '' !== $ref && 'false' !== $ref ) {
				$valid[] = $picking;
			} else {
				$this->logger->info(
					'Picking without tracking reference, skipping.',
					[ 'picking_name' => $picking['name'] ?? '' ]
				);
			}
		}

		return $valid;
	}
}

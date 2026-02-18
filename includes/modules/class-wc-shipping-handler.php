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
 * WooCommerce Shipping Handler — data access for carriers, tracking,
 * and shipment data.
 *
 * Loads WC shipping method data, extracts tracking info from order meta
 * (AST format), formats shipment data for Odoo stock.picking,
 * parses Odoo pickings back to WC tracking format, and manages
 * cross-module entity resolution (order → sale.order → stock.picking).
 *
 * @package WP4Odoo
 * @since   3.6.0
 */
class WC_Shipping_Handler {

	/**
	 * Stock.picking state → WC status mapping.
	 *
	 * @var array<string, string>
	 */
	private const PICKING_STATUS_MAP = [
		'draft'     => 'pending',
		'waiting'   => 'on-hold',
		'confirmed' => 'processing',
		'assigned'  => 'processing',
		'done'      => 'completed',
		'cancel'    => 'cancelled',
	];

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
	 * @param Logger                $logger    Logger instance.
	 * @param \Closure              $client_fn Client provider closure.
	 * @param Entity_Map_Repository $entity_map Entity map repository.
	 */
	public function __construct( Logger $logger, \Closure $client_fn, Entity_Map_Repository $entity_map ) {
		$this->logger     = $logger;
		$this->client_fn  = $client_fn;
		$this->entity_map = $entity_map;
	}

	// ─── Carriers ──────────────────────────────────────────

	/**
	 * Load a WC shipping method for Odoo delivery.carrier push.
	 *
	 * @param int $method_id WC shipping method instance ID.
	 * @return array<string, mixed> Carrier data, or empty.
	 */
	public function load_carrier( int $method_id ): array {
		$zones = \WC_Shipping_Zones::get_zones();

		foreach ( $zones as $zone_data ) {
			$methods = $zone_data['shipping_methods'] ?? [];
			foreach ( $methods as $method ) {
				if ( (int) $method->get_instance_id() === $method_id ) {
					return [
						'name'          => $method->get_title(),
						'delivery_type' => 'fixed',
						'tracking_url'  => '',
					];
				}
			}
		}

		$this->logger->warning( 'WC shipping method not found.', [ 'method_id' => $method_id ] );
		return [];
	}

	/**
	 * Parse an Odoo delivery.carrier record (for potential pull).
	 *
	 * @param array<string, mixed> $odoo_data Raw Odoo record data.
	 * @return array<string, mixed> Parsed carrier data.
	 */
	public function parse_carrier_from_odoo( array $odoo_data ): array {
		return [
			'name'          => $odoo_data['name'] ?? '',
			'delivery_type' => $odoo_data['delivery_type'] ?? 'fixed',
			'tracking_url'  => $odoo_data['tracking_url'] ?? '',
		];
	}

	// ─── Shipments — push ──────────────────────────────────

	/**
	 * Load shipment tracking data from a WC order for Odoo push.
	 *
	 * Reads AST-format tracking meta and builds an update payload
	 * for the Odoo stock.picking linked to this order.
	 *
	 * @param int $order_id WC order ID.
	 * @return array<string, mixed> Odoo-ready shipment data, or empty.
	 */
	public function load_shipment_from_order( int $order_id ): array {
		$tracking = $this->extract_tracking_from_meta( $order_id );
		if ( empty( $tracking ) ) {
			return [];
		}

		// Resolve the Odoo stock.picking for this order.
		$odoo_order_id = $this->entity_map->get_odoo_id( 'woocommerce', 'order', $order_id );
		if ( ! $odoo_order_id ) {
			$this->logger->warning( 'Cannot push tracking: WC order not synced to Odoo.', [ 'order_id' => $order_id ] );
			return [];
		}

		// Use the first tracking item for the picking update.
		$first = $tracking[0];

		$data = [
			'carrier_tracking_ref' => $first['tracking_number'] ?? '',
			'origin'               => sprintf( 'WC Order #%d', $order_id ),
		];

		// Resolve carrier if we have a name.
		$carrier_name = $first['tracking_provider'] ?? '';
		if ( '' !== $carrier_name ) {
			$carrier_odoo_id = $this->entity_map->get_odoo_id( 'wc_shipping', 'carrier', 0 );
			if ( $carrier_odoo_id ) {
				$data['carrier_id'] = $carrier_odoo_id;
			}
		}

		return $data;
	}

	/**
	 * Extract tracking items from WC order meta (AST format).
	 *
	 * @param int $order_id WC order ID.
	 * @return array<int, array<string, mixed>> Tracking items.
	 */
	public function extract_tracking_from_meta( int $order_id ): array {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return [];
		}

		$tracking = $order->get_meta( self::AST_META_KEY );
		if ( ! is_array( $tracking ) || empty( $tracking ) ) {
			$tracking = $order->get_meta( self::OWN_META_KEY );
		}

		if ( ! is_array( $tracking ) || empty( $tracking ) ) {
			return [];
		}

		return $tracking;
	}

	/**
	 * Resolve the Odoo stock.picking ID for a given order.
	 *
	 * Looks up the WC module's entity map for the shipment mapping.
	 *
	 * @param int $odoo_order_id Odoo sale.order ID.
	 * @return int Odoo stock.picking ID, or 0 if not found.
	 */
	public function resolve_odoo_picking( int $odoo_order_id ): int {
		// The WC module maps shipment entity to stock.picking.
		$picking_id = $this->entity_map->get_odoo_id( 'woocommerce', 'shipment', $odoo_order_id );
		return $picking_id ?: 0;
	}

	// ─── Shipments — pull ──────────────────────────────────

	/**
	 * Parse an Odoo stock.picking record into WC tracking format.
	 *
	 * @param array<string, mixed> $odoo_data Raw Odoo record data.
	 * @return array<string, mixed> WC-compatible tracking data.
	 */
	public function parse_shipment_from_odoo( array $odoo_data ): array {
		$tracking_ref = $odoo_data['carrier_tracking_ref'] ?? '';
		$state        = $odoo_data['state'] ?? 'draft';
		$date_done    = $odoo_data['date_done'] ?? '';
		$origin       = $odoo_data['origin'] ?? '';

		// Extract carrier name from Many2one.
		$carrier_name = '';
		$carrier_raw  = $odoo_data['carrier_id'] ?? false;
		if ( $carrier_raw ) {
			$carrier_name = Field_Mapper::many2one_to_name( $carrier_raw ) ?? '';
		}

		// Extract WC order ID from origin.
		$order_id = 0;
		if ( preg_match( '/WC Order #(\d+)/', $origin, $matches ) ) {
			$order_id = (int) $matches[1];
		}

		return [
			'tracking_number' => (string) $tracking_ref,
			'carrier_name'    => $carrier_name,
			'status'          => $this->map_picking_state( (string) $state ),
			'date_done'       => (string) $date_done,
			'order_id'        => $order_id,
			'origin'          => $origin,
		];
	}

	/**
	 * Save pulled shipment tracking data to WC order meta.
	 *
	 * @param array<string, mixed> $data  Parsed shipment data.
	 * @param int                  $wp_id Existing WP order ID (0 if unknown).
	 * @return int Order ID on success, 0 on failure.
	 */
	public function save_shipment( array $data, int $wp_id ): int {
		$order_id = $wp_id > 0 ? $wp_id : (int) ( $data['order_id'] ?? 0 );
		if ( $order_id <= 0 ) {
			$this->logger->warning( 'Cannot save shipment: no order ID.' );
			return 0;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			$this->logger->warning( 'Cannot save shipment: WC order not found.', [ 'order_id' => $order_id ] );
			return 0;
		}

		$tracking_number = $data['tracking_number'] ?? '';
		if ( '' === $tracking_number ) {
			return $order_id;
		}

		$tracking_item = $this->build_tracking_item(
			$data['carrier_name'] ?? '',
			$tracking_number,
			$data['date_done'] ?? ''
		);

		$existing = $order->get_meta( self::AST_META_KEY );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}

		// Check if tracking number already exists.
		foreach ( $existing as $item ) {
			if ( ( $item['tracking_number'] ?? '' ) === $tracking_number ) {
				return $order_id;
			}
		}

		$existing[] = $tracking_item;
		$order->update_meta_data( self::AST_META_KEY, $existing );
		$order->update_meta_data( self::OWN_META_KEY, $existing );
		$order->save();

		$this->logger->info(
			'Shipment tracking saved from Odoo.',
			[
				'order_id'        => $order_id,
				'tracking_number' => $tracking_number,
			]
		);

		return $order_id;
	}

	// ─── Provider-specific extraction ──────────────────────

	/**
	 * Extract tracking data from ShipStation webhook payload.
	 *
	 * @param array<string, mixed> $data ShipStation shipment data.
	 * @return array{tracking_number: string, carrier_name: string, date_shipped: string}
	 */
	public function extract_from_shipstation( array $data ): array {
		return [
			'tracking_number' => $data['tracking_number'] ?? '',
			'carrier_name'    => $data['carrier_code'] ?? '',
			'date_shipped'    => $data['ship_date'] ?? '',
		];
	}

	/**
	 * Extract tracking data from Sendcloud parcel status change.
	 *
	 * @param array<string, mixed> $parcel Sendcloud parcel data.
	 * @return array{tracking_number: string, carrier_name: string, date_shipped: string}
	 */
	public function extract_from_sendcloud( array $parcel ): array {
		return [
			'tracking_number' => $parcel['tracking_number'] ?? '',
			'carrier_name'    => $parcel['carrier']['code'] ?? '',
			'date_shipped'    => $parcel['date_created'] ?? '',
		];
	}

	/**
	 * Extract tracking data from Packlink tracking update.
	 *
	 * @param array<string, mixed> $tracking_data Packlink tracking data.
	 * @return array{tracking_number: string, carrier_name: string, date_shipped: string}
	 */
	public function extract_from_packlink( array $tracking_data ): array {
		return [
			'tracking_number' => $tracking_data['tracking_number'] ?? $tracking_data['tracking_code'] ?? '',
			'carrier_name'    => $tracking_data['carrier'] ?? '',
			'date_shipped'    => $tracking_data['shipped_date'] ?? '',
		];
	}

	// ─── Status mapping ────────────────────────────────────

	/**
	 * Map an Odoo stock.picking state to a WC-compatible status.
	 *
	 * @param string $state Odoo picking state.
	 * @return string WC-compatible status.
	 */
	public function map_picking_state( string $state ): string {
		return Status_Mapper::resolve( $state, self::PICKING_STATUS_MAP, 'wp4odoo_wc_shipping_picking_status_map', 'pending' );
	}

	// ─── Helpers ───────────────────────────────────────────

	/**
	 * Build a single AST-format tracking item.
	 *
	 * @param string $carrier  Carrier name.
	 * @param string $tracking Tracking number.
	 * @param string $date     Date shipped (Y-m-d or timestamp).
	 * @return array<string, mixed> AST-format tracking item.
	 */
	public function build_tracking_item( string $carrier, string $tracking, string $date ): array {
		$timestamp = 0;
		if ( '' !== $date && 'false' !== $date ) {
			$dt = \DateTime::createFromFormat( 'Y-m-d H:i:s', $date, new \DateTimeZone( 'UTC' ) );
			if ( false === $dt ) {
				$dt = \DateTime::createFromFormat( 'Y-m-d', $date, new \DateTimeZone( 'UTC' ) );
			}
			if ( false !== $dt ) {
				$timestamp = $dt->getTimestamp();
			}
		}

		return [
			'tracking_provider'        => $carrier,
			'custom_tracking_provider' => '',
			'custom_tracking_link'     => '',
			'tracking_number'          => $tracking,
			'date_shipped'             => (string) $timestamp,
			'status_shipped'           => 1,
		];
	}

	/**
	 * Check if Sendcloud plugin is active.
	 *
	 * @return bool
	 */
	public function has_sendcloud(): bool {
		return defined( 'SENDCLOUD_PLUGIN_VERSION' ) || class_exists( 'SendCloud\\Checkout\\Shipping\\SendCloudShipping' );
	}

	/**
	 * Check if Packlink plugin is active.
	 *
	 * @return bool
	 */
	public function has_packlink(): bool {
		return defined( 'PACKLINK_VERSION' ) || class_exists( 'Packlink\\WooCommerce\\Components\\ShippingMethod\\Packlink_Shipping_Method' );
	}
}

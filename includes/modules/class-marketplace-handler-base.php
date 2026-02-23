<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base for marketplace plugin handlers (Dokan, WCFM, WC Vendors).
 *
 * Extracts shared vendor status/order status maps, purchase order line
 * formatting, sub-order loading, company name heuristic, and status
 * mapping from the three concrete marketplace handlers.
 *
 * @package WP4Odoo
 * @since   3.9.1
 */
abstract class Marketplace_Handler_Base {

	/**
	 * Vendor status mapping: marketplace → Odoo-friendly label.
	 *
	 * - enabled (active vendor) → active
	 * - disabled (suspended vendor) → inactive
	 *
	 * @var array<string, string>
	 */
	protected const VENDOR_STATUS_MAP = [
		'enabled'  => 'active',
		'disabled' => 'inactive',
	];

	/**
	 * Sub-order status mapping: WooCommerce order statuses → Odoo purchase.order state.
	 *
	 * @var array<string, string>
	 */
	protected const ORDER_STATUS_MAP = [
		'pending'    => 'draft',
		'processing' => 'purchase',
		'on-hold'    => 'draft',
		'completed'  => 'done',
		'cancelled'  => 'cancel',
		'refunded'   => 'cancel',
		'failed'     => 'cancel',
	];

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	protected Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Get the marketplace display label (e.g. 'Dokan', 'WCFM').
	 *
	 * Used in log messages and warning strings.
	 *
	 * @return string
	 */
	abstract protected function get_marketplace_label(): string;

	/**
	 * Get the hook prefix for Status_Mapper filter names.
	 *
	 * E.g. 'dokan' → 'wp4odoo_dokan_vendor_status_map'.
	 *
	 * @return string
	 */
	abstract protected function get_hook_prefix(): string;

	// ─── Abstract: called by module base ────────────────────

	/**
	 * Load vendor data as Odoo partner fields.
	 *
	 * @param int $user_id WordPress user ID (vendor).
	 * @return array<string, mixed> Partner data, or empty if not found.
	 */
	abstract public function load_vendor( int $user_id ): array;

	/**
	 * Save vendor status from Odoo pull.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $status  Odoo-side status string.
	 * @return bool True on success.
	 */
	abstract public function save_vendor_status( int $user_id, string $status ): bool;

	/**
	 * Get the vendor user ID for a sub-order.
	 *
	 * @param int $order_id WC sub-order ID.
	 * @return int Vendor user ID, or 0 if not found.
	 */
	abstract public function get_vendor_id_for_order( int $order_id ): int;

	// ─── Sub-order ──────────────────────────────────────────

	/**
	 * Load a sub-order as Odoo purchase order fields.
	 *
	 * Resolves the vendor user ID and formats WC order items as
	 * purchase.order line One2many tuples `[(0, 0, {...})]`.
	 *
	 * @param int $order_id   WC sub-order ID.
	 * @param int $partner_id Resolved Odoo partner ID (vendor).
	 * @return array<string, mixed> Purchase order data, or empty if not found.
	 */
	public function load_sub_order( int $order_id, int $partner_id ): array {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			$this->logger->warning( $this->get_marketplace_label() . ' sub-order not found.', [ 'order_id' => $order_id ] );
			return [];
		}

		$date_created = $order->get_date_created();
		$parent_id    = $order->get_parent_id();

		/* translators: 1: sub-order ID, 2: parent order ID */
		$origin = sprintf( __( 'WC #%1$d (sub-order of #%2$d)', 'wp4odoo' ), $order_id, $parent_id );

		$items = $order->get_items();
		$lines = $this->format_purchase_order_lines( $items );

		return [
			'partner_id' => $partner_id,
			'date_order' => $date_created ? $date_created->format( 'Y-m-d' ) : '',
			'origin'     => $origin,
			'order_line' => $lines,
		];
	}

	// ─── Helpers ────────────────────────────────────────────

	/**
	 * Format WC order items as purchase.order line One2many tuples.
	 *
	 * @param array<int, mixed> $items WC order items.
	 * @return array<int, array<int, mixed>> Odoo One2many create tuples.
	 */
	public function format_purchase_order_lines( array $items ): array {
		$lines = [];

		foreach ( $items as $item ) {
			$name = is_object( $item ) && method_exists( $item, 'get_name' )
				? $item->get_name()
				: ( $item['name'] ?? __( 'Product', 'wp4odoo' ) );

			$qty = is_object( $item ) && method_exists( $item, 'get_quantity' )
				? (float) $item->get_quantity()
				: (float) ( $item['qty'] ?? 1 );

			$total = is_object( $item ) && method_exists( $item, 'get_total' )
				? (float) $item->get_total()
				: (float) ( $item['total'] ?? 0.0 );

			$price_unit = $qty > 0 ? $total / $qty : $total;

			$lines[] = [
				0,
				0,
				[
					'name'        => $name,
					'product_qty' => $qty,
					'price_unit'  => $price_unit,
				],
			];
		}

		return $lines;
	}

	/**
	 * Map a vendor status to an Odoo-friendly status string.
	 *
	 * @param string $status Marketplace vendor status.
	 * @return string Odoo-friendly status.
	 */
	public function map_vendor_status_to_odoo( string $status ): string {
		return Status_Mapper::resolve( $status, self::VENDOR_STATUS_MAP, 'wp4odoo_' . $this->get_hook_prefix() . '_vendor_status_map', 'active' );
	}

	/**
	 * Map an order status to an Odoo purchase.order state.
	 *
	 * @param string $status WC order status (without wc- prefix).
	 * @return string Odoo purchase.order state.
	 */
	public function map_order_status_to_odoo( string $status ): string {
		return Status_Mapper::resolve( $status, self::ORDER_STATUS_MAP, 'wp4odoo_' . $this->get_hook_prefix() . '_order_status_map', 'draft' );
	}

	/**
	 * Determine whether a name looks like a company name.
	 *
	 * Simple heuristic: names containing common business suffixes or
	 * more than 3 words are treated as companies.
	 *
	 * @param string $name Name to check.
	 * @return bool True if likely a company name.
	 */
	protected function is_company_name( string $name ): bool {
		$business_suffixes = [ 'LLC', 'Inc', 'Ltd', 'Corp', 'GmbH', 'SAS', 'SARL', 'S.A.', 'Pty' ];

		foreach ( $business_suffixes as $suffix ) {
			if ( stripos( $name, $suffix ) !== false ) {
				return true;
			}
		}

		return str_word_count( $name ) > 3;
	}
}

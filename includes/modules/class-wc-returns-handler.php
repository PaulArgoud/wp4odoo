<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Entity_Map_Repository;
use WP4Odoo\Field_Mapper;
use WP4Odoo\Logger;
use WP4Odoo\Partner_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Returns Handler — data access for refunds, credit notes,
 * and return pickings.
 *
 * Loads WC refund data, formats Odoo credit notes and return pickings,
 * parses Odoo credit notes back to WC refund format, and manages
 * cross-module entity resolution (partner, order, invoice, picking).
 *
 * @package WP4Odoo
 * @since   3.6.0
 */
class WC_Returns_Handler {

	/**
	 * Credit note state → WC refund status mapping.
	 *
	 * @var array<string, string>
	 */
	private const REFUND_STATUS_MAP = [
		'draft'  => 'pending',
		'posted' => 'completed',
		'cancel' => 'cancelled',
	];

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Partner service for customer resolution.
	 *
	 * @var Partner_Service
	 */
	private Partner_Service $partner_service;

	/**
	 * Entity map repository for cross-module lookups.
	 *
	 * @var Entity_Map_Repository
	 */
	private Entity_Map_Repository $entity_map;

	/**
	 * Constructor.
	 *
	 * @param Logger                $logger          Logger instance.
	 * @param Partner_Service       $partner_service Partner service.
	 * @param Entity_Map_Repository $entity_map      Entity map repository.
	 */
	public function __construct( Logger $logger, Partner_Service $partner_service, Entity_Map_Repository $entity_map ) {
		$this->logger          = $logger;
		$this->partner_service = $partner_service;
		$this->entity_map      = $entity_map;
	}

	// ─── Refund — push ─────────────────────────────────────

	/**
	 * Load a WooCommerce refund and format as Odoo credit note.
	 *
	 * @param int $refund_id WC refund ID.
	 * @return array<string, mixed> Odoo-ready credit note data, or empty.
	 */
	public function load_refund( int $refund_id ): array {
		$refund = wc_get_order( $refund_id );
		if ( ! $refund || 'shop_order_refund' !== $refund->get_type() ) {
			$this->logger->warning( 'WC refund not found.', [ 'refund_id' => $refund_id ] );
			return [];
		}

		$parent_order_id = $refund->get_parent_id();
		$parent_order    = wc_get_order( $parent_order_id );
		if ( ! $parent_order ) {
			$this->logger->warning(
				'Parent order not found for refund.',
				[
					'refund_id' => $refund_id,
					'order_id'  => $parent_order_id,
				]
			);
			return [];
		}

		// Resolve customer → partner.
		$partner_id = $this->resolve_partner_id( $parent_order );
		if ( ! $partner_id ) {
			$this->logger->warning( 'Cannot resolve partner for refund.', [ 'refund_id' => $refund_id ] );
			return [];
		}

		$amount     = abs( (float) $refund->get_amount() );
		$date       = $refund->get_date_created();
		$date_str   = $date ? $date->format( 'Y-m-d' ) : gmdate( 'Y-m-d' );
		$ref        = $this->format_refund_ref( $refund_id, $parent_order_id );
		$line_items = $this->get_refund_line_items( $refund );
		$reason     = $refund->get_reason();

		$fallback_name = $reason ?: sprintf(
			/* translators: %d: WC order ID */
			__( 'Refund for order #%d', 'wp4odoo' ),
			$parent_order_id
		);

		// Resolve original invoice for reversed_entry_id.
		$reversed_entry_id = $this->resolve_original_invoice( $parent_order_id );

		return Odoo_Accounting_Formatter::for_credit_note(
			$partner_id,
			$amount,
			$date_str,
			$ref,
			$line_items,
			$fallback_name,
			$reversed_entry_id
		);
	}

	/**
	 * Get refund line items formatted for Odoo credit note.
	 *
	 * @param \WC_Order $refund WC refund order.
	 * @return array<int, array{name: string, quantity: float, price_unit: float}>
	 */
	public function get_refund_line_items( $refund ): array {
		$items = $refund->get_items();
		$lines = [];

		foreach ( $items as $item ) {
			$name     = $item->get_name();
			$quantity = abs( (float) $item->get_quantity() );
			$total    = abs( (float) $item->get_total() );

			if ( $quantity <= 0 ) {
				continue;
			}

			$lines[] = [
				'name'       => $name ?: __( 'Refund item', 'wp4odoo' ),
				'quantity'   => $quantity,
				'price_unit' => $total / $quantity,
			];
		}

		return $lines;
	}

	/**
	 * Resolve the Odoo partner ID for the customer of an order.
	 *
	 * @param \WC_Order $order WC order.
	 * @return int Odoo partner ID, or 0 if not resolved.
	 */
	public function resolve_partner_id( $order ): int {
		$email   = $order->get_billing_email();
		$name    = $order->get_formatted_billing_full_name();
		$user_id = $order->get_customer_id();

		if ( empty( $email ) ) {
			return 0;
		}

		return $this->partner_service->get_or_create( $email, [ 'name' => $name ], $user_id );
	}

	/**
	 * Resolve the original Odoo invoice ID for reversed_entry_id.
	 *
	 * Looks up the WC module's entity map for the order's invoice mapping.
	 *
	 * @param int $order_id WC order ID.
	 * @return int Odoo invoice ID, or 0 if not found.
	 */
	public function resolve_original_invoice( int $order_id ): int {
		// Try to find the invoice synced by the WC module.
		$invoice_id = $this->entity_map->get_odoo_id( 'woocommerce', 'invoice', $order_id );
		if ( $invoice_id ) {
			return $invoice_id;
		}

		// Alternatively, the order itself may have an associated Odoo sale.order
		// that generated an invoice — but we can't resolve that without API call.
		return 0;
	}

	/**
	 * Format a reference string for the credit note.
	 *
	 * @param int $refund_id WC refund ID.
	 * @param int $order_id  WC parent order ID.
	 * @return string
	 */
	public function format_refund_ref( int $refund_id, int $order_id ): string {
		return sprintf( 'WC-REFUND-%d (Order #%d)', $refund_id, $order_id );
	}

	// ─── Refund — pull ─────────────────────────────────────

	/**
	 * Parse an Odoo credit note into WC refund format.
	 *
	 * @param array<string, mixed> $odoo_data Raw Odoo record data.
	 * @return array<string, mixed> WordPress refund data.
	 */
	public function parse_refund_from_odoo( array $odoo_data ): array {
		$amount = abs( (float) ( $odoo_data['amount_total'] ?? 0.0 ) );
		$ref    = $odoo_data['ref'] ?? '';
		$state  = $odoo_data['state'] ?? 'draft';

		// Extract order reference from the ref string.
		$order_id = 0;
		if ( preg_match( '/Order #(\d+)/', $ref, $matches ) ) {
			$order_id = (int) $matches[1];
		}

		// Resolve from reversed_entry_id if available.
		if ( ! $order_id && ! empty( $odoo_data['reversed_entry_id'] ) ) {
			$invoice_odoo_id = Field_Mapper::many2one_to_id( $odoo_data['reversed_entry_id'] );
			if ( $invoice_odoo_id ) {
				$wp_order_id = $this->entity_map->get_wp_id( 'woocommerce', 'invoice', $invoice_odoo_id );
				if ( $wp_order_id ) {
					$order_id = $wp_order_id;
				}
			}
		}

		return [
			'amount'   => $amount,
			'reason'   => $ref,
			'order_id' => $order_id,
			'status'   => $this->map_odoo_state_to_wc( (string) $state ),
		];
	}

	/**
	 * Save a pulled credit note as a WC refund.
	 *
	 * Only creates refunds on existing orders. Uses wc_create_refund().
	 *
	 * @param array<string, mixed> $data  Parsed refund data.
	 * @param int                  $wp_id Existing WP refund ID (0 if creating).
	 * @return int Refund ID on success, 0 on failure.
	 */
	public function save_refund( array $data, int $wp_id ): int {
		$order_id = (int) ( $data['order_id'] ?? 0 );
		if ( $order_id <= 0 ) {
			$this->logger->warning( 'Cannot create refund: no order ID resolved from Odoo credit note.' );
			return 0;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			$this->logger->warning( 'Cannot create refund: WC order not found.', [ 'order_id' => $order_id ] );
			return 0;
		}

		if ( $wp_id > 0 ) {
			// Update existing refund — only status matters.
			$this->logger->info( 'Refund update from Odoo — no changes applied.', [ 'refund_id' => $wp_id ] );
			return $wp_id;
		}

		$amount = (float) ( $data['amount'] ?? 0.0 );
		$reason = (string) ( $data['reason'] ?? '' );

		$refund = wc_create_refund(
			[
				'order_id' => $order_id,
				'amount'   => $amount,
				'reason'   => $reason,
			]
		);

		if ( is_wp_error( $refund ) ) {
			$this->logger->warning(
				'Failed to create WC refund from Odoo credit note.',
				[
					'order_id' => $order_id,
					'error'    => $refund->get_error_message(),
				]
			);
			return 0;
		}

		$refund_id = $refund->get_id();

		$this->logger->info(
			'Created WC refund from Odoo credit note.',
			[
				'refund_id' => $refund_id,
				'order_id'  => $order_id,
				'amount'    => $amount,
			]
		);

		return $refund_id;
	}

	// ─── Return Picking — push ─────────────────────────────

	/**
	 * Load return picking data for a WC refund.
	 *
	 * Builds a stock.picking with incoming type, linking back to the
	 * original outgoing picking via the sale order.
	 *
	 * @param int $refund_id WC refund ID.
	 * @return array<string, mixed> Odoo-ready return picking data, or empty.
	 */
	public function load_return_picking( int $refund_id ): array {
		$refund = wc_get_order( $refund_id );
		if ( ! $refund || 'shop_order_refund' !== $refund->get_type() ) {
			return [];
		}

		$parent_order_id = $refund->get_parent_id();

		// Resolve Odoo sale.order for origin reference.
		$odoo_order_id = $this->entity_map->get_odoo_id( 'woocommerce', 'order', $parent_order_id );
		if ( ! $odoo_order_id ) {
			$this->logger->warning( 'Cannot create return picking: order not synced.', [ 'order_id' => $parent_order_id ] );
			return [];
		}

		$origin = sprintf( 'Return: WC Order #%d', $parent_order_id );

		// Build stock.move lines from refund items.
		$move_lines = [];
		foreach ( $refund->get_items() as $item ) {
			$quantity   = abs( (float) $item->get_quantity() );
			$product_id = $item->get_product_id();

			if ( $quantity <= 0 || $product_id <= 0 ) {
				continue;
			}

			$odoo_product_id = $this->entity_map->get_odoo_id( 'woocommerce', 'product', $product_id );
			if ( ! $odoo_product_id ) {
				$odoo_product_id = $this->entity_map->get_odoo_id( 'woocommerce', 'variant', $product_id );
			}
			if ( ! $odoo_product_id ) {
				continue;
			}

			$move_lines[] = [
				0,
				0,
				[
					'name'            => $item->get_name() ?: __( 'Return item', 'wp4odoo' ),
					'product_id'      => $odoo_product_id,
					'product_uom_qty' => $quantity,
				],
			];
		}

		if ( empty( $move_lines ) ) {
			$this->logger->warning( 'No valid items for return picking.', [ 'refund_id' => $refund_id ] );
			return [];
		}

		return [
			'origin'                   => $origin,
			'move_ids_without_package' => $move_lines,
		];
	}

	// ─── Status mapping ─────────────────────────────────────

	/**
	 * Map an Odoo account.move state to a WC refund status.
	 *
	 * @param string $state Odoo credit note state.
	 * @return string WC-compatible refund status.
	 */
	public function map_odoo_state_to_wc( string $state ): string {
		return Status_Mapper::resolve( $state, self::REFUND_STATUS_MAP, 'wp4odoo_wc_returns_refund_status_map', 'pending' );
	}
}

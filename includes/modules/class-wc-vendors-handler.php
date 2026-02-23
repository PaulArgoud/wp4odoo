<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC Vendors Handler — data access for marketplace vendors, sub-orders,
 * commissions, and payouts.
 *
 * Loads WC Vendors vendor data and formats sub-orders as purchase orders,
 * commissions as vendor bills (`account.move` with `move_type=in_invoice`),
 * and payouts as account payments (`account.payment`).
 *
 * Called by WC_Vendors_Module via its load_wp_data dispatch.
 *
 * @package WP4Odoo
 * @since   3.4.0
 */
class WC_Vendors_Handler extends Marketplace_Handler_Base {

	/**
	 * {@inheritDoc}
	 */
	protected function get_marketplace_label(): string {
		return 'WC Vendors';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_hook_prefix(): string {
		return 'wc_vendors';
	}

	// ─── Vendor ─────────────────────────────────────────────

	/**
	 * Load vendor data as Odoo partner fields.
	 *
	 * Returns data suitable for `res.partner` with `supplier_rank = 1`.
	 *
	 * @param int $user_id WordPress user ID (vendor).
	 * @return array<string, mixed> Partner data, or empty if not found.
	 */
	public function load_vendor( int $user_id ): array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			$this->logger->warning( 'WC Vendors vendor user not found.', [ 'user_id' => $user_id ] );
			return [];
		}

		$store_info = $this->get_vendor_store_info( $user_id );
		$store_name = $store_info['store_name'] ?? '';

		$name         = $store_name ?: $user->display_name;
		$street_parts = array_filter(
			[
				$store_info['address'] ?? '',
			]
		);

		return [
			'store_name'    => $store_name,
			'name'          => $name,
			'email'         => $user->user_email,
			'phone'         => $store_info['phone'] ?? '',
			'street'        => implode( ', ', $street_parts ),
			'city'          => $store_info['city'] ?? '',
			'zip'           => $store_info['postcode'] ?? '',
			'supplier_rank' => 1,
			'is_company'    => $this->is_company_name( $name ),
		];
	}

	/**
	 * Save vendor status from Odoo pull.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $status  Odoo-side status string.
	 * @return bool True on success.
	 */
	public function save_vendor_status( int $user_id, string $status ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			$this->logger->warning( 'Cannot save vendor status — user not found.', [ 'user_id' => $user_id ] );
			return false;
		}

		$enabled = 'active' === $status;
		update_user_meta( $user_id, '_wcv_vendor_status', $enabled ? 'enabled' : 'disabled' );

		$this->logger->info(
			'Updated WC Vendors vendor status from Odoo.',
			[
				'user_id' => $user_id,
				'status'  => $status,
				'enabled' => $enabled,
			]
		);

		return true;
	}

	// ─── Commission ─────────────────────────────────────────

	/**
	 * Load a commission as Odoo vendor bill fields.
	 *
	 * Uses Odoo_Accounting_Formatter::for_vendor_bill() for consistent
	 * formatting with other modules (AffiliateWP, Dokan, etc.).
	 *
	 * @param int   $order_id   WC order ID the commission is for.
	 * @param int   $partner_id Resolved Odoo partner ID (vendor).
	 * @param float $amount     Commission amount.
	 * @return array<string, mixed> Vendor bill data.
	 */
	public function load_commission( int $order_id, int $partner_id, float $amount ): array {
		/* translators: 1: order ID */
		$line_name = sprintf( __( 'Marketplace commission — Order #%d', 'wp4odoo' ), $order_id );

		$order        = wc_get_order( $order_id );
		$date_created = $order ? $order->get_date_created() : null;
		$date         = $date_created ? $date_created->format( 'Y-m-d' ) : gmdate( 'Y-m-d' );

		return Odoo_Accounting_Formatter::for_vendor_bill(
			$partner_id,
			$amount,
			$date,
			'wcvendors-comm-' . $order_id,
			$line_name
		);
	}

	/**
	 * Load a payout as Odoo account.payment fields.
	 *
	 * @param int $payout_id  WC Vendors payout ID.
	 * @param int $partner_id Resolved Odoo partner ID (vendor).
	 * @return array<string, mixed> Payment data, or empty if not found.
	 */
	public function load_payout( int $payout_id, int $partner_id ): array {
		$payout = $GLOBALS['_wcv_payouts'][ $payout_id ] ?? null;
		if ( ! $payout ) {
			$this->logger->warning( 'WC Vendors payout not found.', [ 'payout_id' => $payout_id ] );
			return [];
		}

		$date   = $payout->date ?? gmdate( 'Y-m-d' );
		$date   = substr( $date, 0, 10 );
		$amount = (float) ( $payout->amount ?? 0.0 );

		return [
			'partner_id'   => $partner_id,
			'amount'       => $amount,
			'date'         => $date,
			'ref'          => 'wcvendors-payout-' . $payout_id,
			'payment_type' => 'outbound',
			'partner_type' => 'supplier',
		];
	}

	// ─── Helpers ────────────────────────────────────────────

	/**
	 * Get the vendor user ID for a WC Vendors sub-order.
	 *
	 * @param int $order_id WC sub-order ID.
	 * @return int Vendor user ID, or 0 if not found.
	 */
	public function get_vendor_id_for_order( int $order_id ): int {
		return (int) \WCV_Vendors::get_vendor_from_order( $order_id );
	}

	/**
	 * Get the vendor user ID for a WC Vendors payout.
	 *
	 * @param int $payout_id WC Vendors payout ID.
	 * @return int Vendor user ID, or 0 if not found.
	 */
	public function get_vendor_id_for_payout( int $payout_id ): int {
		$payout = $GLOBALS['_wcv_payouts'][ $payout_id ] ?? null;
		return $payout ? (int) ( $payout->vendor_id ?? 0 ) : 0;
	}

	/**
	 * Get vendor store info from WC Vendors.
	 *
	 * Reads vendor metadata from user_meta: pv_shop_name, _wcv_store_phone,
	 * _wcv_store_address1, _wcv_store_city, _wcv_store_postcode.
	 *
	 * @param int $user_id Vendor user ID.
	 * @return array<string, string> Store info array.
	 */
	private function get_vendor_store_info( int $user_id ): array {
		$store_name = (string) get_user_meta( $user_id, 'pv_shop_name', true );
		$phone      = (string) get_user_meta( $user_id, '_wcv_store_phone', true );
		$address    = (string) get_user_meta( $user_id, '_wcv_store_address1', true );
		$city       = (string) get_user_meta( $user_id, '_wcv_store_city', true );
		$postcode   = (string) get_user_meta( $user_id, '_wcv_store_postcode', true );

		return [
			'store_name' => $store_name,
			'phone'      => $phone,
			'address'    => $address,
			'city'       => $city,
			'postcode'   => $postcode,
		];
	}
}

<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WCFM Marketplace Handler — data access for marketplace vendors,
 * sub-orders, commissions, and payouts.
 *
 * Loads WCFM vendor data and formats sub-orders as purchase orders,
 * commissions as vendor bills (`account.move` with `move_type=in_invoice`),
 * and payouts as account payments (`account.payment`).
 *
 * Uses WCFM API functions and globals (`$WCFM->wcfmmp_vendor`) for data access.
 *
 * Called by WCFM_Module via its load_wp_data dispatch.
 *
 * @package WP4Odoo
 * @since   3.4.0
 */
class WCFM_Handler extends Marketplace_Handler_Base {

	/**
	 * {@inheritDoc}
	 */
	protected function get_marketplace_label(): string {
		return 'WCFM';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_hook_prefix(): string {
		return 'wcfm';
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
			$this->logger->warning( 'WCFM vendor user not found.', [ 'user_id' => $user_id ] );
			return [];
		}

		$store_name = wcfm_get_vendor_store_name( $user_id );
		$store_info = $this->get_vendor_store_info( $user_id );
		$address    = $store_info['address'] ?? [];

		$name         = $store_name ?: $user->display_name;
		$street_parts = array_filter(
			[
				$address['street_1'] ?? '',
			]
		);

		return [
			'store_name'    => $store_name,
			'name'          => $name,
			'email'         => $user->user_email,
			'phone'         => $store_info['phone'] ?? '',
			'street'        => implode( ', ', $street_parts ),
			'city'          => $address['city'] ?? '',
			'zip'           => $address['zip'] ?? '',
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
			$this->logger->warning( 'Cannot save WCFM vendor status — user not found.', [ 'user_id' => $user_id ] );
			return false;
		}

		$enabled = 'active' === $status;
		update_user_meta( $user_id, '_wcfm_vendor_disable', $enabled ? 'no' : 'yes' );

		$this->logger->info(
			'Updated WCFM vendor status from Odoo.',
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
	 * formatting with other modules (AffiliateWP, etc.).
	 *
	 * @param int   $commission_id WCFM commission ID.
	 * @param int   $order_id      WC order ID.
	 * @param int   $partner_id    Resolved Odoo partner ID (vendor).
	 * @param float $amount        Commission amount.
	 * @return array<string, mixed> Vendor bill data.
	 */
	public function load_commission( int $commission_id, int $order_id, int $partner_id, float $amount ): array {
		/* translators: 1: commission ID, 2: order ID */
		$line_name = sprintf( __( 'Marketplace commission #%1$d — Order #%2$d', 'wp4odoo' ), $commission_id, $order_id );

		$order        = wc_get_order( $order_id );
		$date_created = $order ? $order->get_date_created() : null;
		$date         = $date_created ? $date_created->format( 'Y-m-d' ) : gmdate( 'Y-m-d' );

		return Odoo_Accounting_Formatter::for_vendor_bill(
			$partner_id,
			$amount,
			$date,
			'wcfm-comm-' . $commission_id,
			$line_name
		);
	}

	/**
	 * Load a withdrawal as Odoo account.payment fields.
	 *
	 * @param int $withdrawal_id WCFM withdrawal ID.
	 * @param int $partner_id    Resolved Odoo partner ID (vendor).
	 * @return array<string, mixed> Payment data, or empty if not found.
	 */
	public function load_payout( int $withdrawal_id, int $partner_id ): array {
		$withdrawal = wcfm_get_withdrawal( $withdrawal_id );
		if ( ! $withdrawal ) {
			$this->logger->warning( 'WCFM withdrawal not found.', [ 'withdrawal_id' => $withdrawal_id ] );
			return [];
		}

		$date = isset( $withdrawal->date ) ? substr( $withdrawal->date, 0, 10 ) : gmdate( 'Y-m-d' );

		return [
			'partner_id'   => $partner_id,
			'amount'       => (float) $withdrawal->amount,
			'date'         => $date,
			'ref'          => 'wcfm-payout-' . $withdrawal_id,
			'payment_type' => 'outbound',
			'partner_type' => 'supplier',
		];
	}

	// ─── Helpers ────────────────────────────────────────────

	/**
	 * Get the vendor user ID for a WCFM sub-order.
	 *
	 * @param int $order_id WC sub-order ID.
	 * @return int Vendor user ID, or 0 if not found.
	 */
	public function get_vendor_id_for_order( int $order_id ): int {
		return (int) wcfm_get_vendor_id_by_order( $order_id );
	}

	/**
	 * Get the vendor user ID for a WCFM withdrawal.
	 *
	 * @param int $withdrawal_id WCFM withdrawal ID.
	 * @return int Vendor user ID, or 0 if not found.
	 */
	public function get_vendor_id_for_withdrawal( int $withdrawal_id ): int {
		$withdrawal = wcfm_get_withdrawal( $withdrawal_id );
		return $withdrawal && isset( $withdrawal->vendor_id ) ? (int) $withdrawal->vendor_id : 0;
	}

	/**
	 * Get vendor store info from WCFM.
	 *
	 * @param int $user_id Vendor user ID.
	 * @return array<string, mixed> Store info array.
	 */
	private function get_vendor_store_info( int $user_id ): array {
		$store_info = (array) get_user_meta( $user_id, 'wcfmmp_profile_settings', true );

		if ( empty( $store_info ) ) {
			return [
				'store_name' => '',
				'address'    => [],
				'phone'      => '',
			];
		}

		return $store_info;
	}
}

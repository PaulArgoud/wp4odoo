<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AffiliateWP Handler — data access for referrals.
 *
 * Loads AffiliateWP referrals and formats them as Odoo vendor bills
 * (`account.move` with `move_type=in_invoice`). Maps referral statuses
 * to Odoo accounting states.
 *
 * Called by AffiliateWP_Module via its load_wp_data dispatch.
 *
 * @package WP4Odoo
 * @since   3.1.0
 */
class AffiliateWP_Handler {

	/**
	 * Referral status mapping: AffiliateWP → Odoo account.move state.
	 *
	 * - unpaid (confirmed, awaiting payout) → draft
	 * - paid (payout processed) → posted
	 * - rejected (cancelled) → cancel
	 * - pending is not synced (filtered in hooks).
	 *
	 * @var array<string, string>
	 */
	private const REFERRAL_STATUS_MAP = [
		'unpaid'   => 'draft',
		'paid'     => 'posted',
		'rejected' => 'cancel',
		'pending'  => 'draft',
	];

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

	/**
	 * Load a referral as Odoo vendor bill data.
	 *
	 * Returns data pre-formatted for Odoo: includes invoice_line_ids
	 * as One2many tuples [(0, 0, {...})], with move_type=in_invoice.
	 *
	 * @param int $referral_id Referral ID.
	 * @param int $partner_id  Resolved Odoo partner ID (affiliate).
	 * @return array<string, mixed> Vendor bill data, or empty if not found.
	 */
	public function load_referral( int $referral_id, int $partner_id ): array {
		$referral = affwp_get_referral( $referral_id );
		if ( ! $referral ) {
			$this->logger->warning( 'AffiliateWP referral not found.', [ 'referral_id' => $referral_id ] );
			return [];
		}

		$description = $referral->description ?: __( 'Affiliate commission', 'wp4odoo' );
		/* translators: 1: referral ID, 2: referral description */
		$line_name = sprintf( __( 'Commission #%1$d — %2$s', 'wp4odoo' ), $referral_id, $description );

		return Odoo_Accounting_Formatter::for_vendor_bill(
			$partner_id,
			(float) $referral->amount,
			substr( $referral->date, 0, 10 ),
			'affwp-ref-' . $referral_id,
			$line_name
		);
	}

	/**
	 * Map an AffiliateWP referral status to an Odoo account.move state.
	 *
	 * @param string $status AffiliateWP referral status.
	 * @return string Odoo account.move state.
	 */
	public function map_referral_status_to_odoo( string $status ): string {
		return Status_Mapper::resolve( $status, self::REFERRAL_STATUS_MAP, 'wp4odoo_affwp_referral_status_map', 'draft' );
	}
}

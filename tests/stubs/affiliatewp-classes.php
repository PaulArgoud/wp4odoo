<?php
/**
 * AffiliateWP test stubs.
 *
 * Provides minimal class and function stubs for unit testing
 * the AffiliateWP module without the full AffiliateWP plugin.
 *
 * @package WP4Odoo\Tests
 */

// ─── Detection ──────────────────────────────────────────

if ( ! defined( 'AFFILIATEWP_VERSION' ) ) {
	define( 'AFFILIATEWP_VERSION', '2.25.3' );
}

if ( ! function_exists( 'affiliate_wp' ) ) {
	/**
	 * @return stdClass
	 */
	function affiliate_wp() {
		return new stdClass();
	}
}

// ─── Affiliate stub ─────────────────────────────────────

if ( ! class_exists( 'AffWP_Affiliate' ) ) {
	class AffWP_Affiliate {
		public int $affiliate_id   = 0;
		public int $user_id        = 0;
		public string $payment_email = '';
		public string $status      = 'active';
		public string $rate_type   = 'percentage';
		public float $rate         = 20.0;
		public float $earnings     = 0.0;
		public float $unpaid_earnings = 0.0;
		public int $referrals      = 0;
		public int $visits         = 0;
		public string $date_registered = '2026-01-01 00:00:00';
	}
}

// ─── Referral stub ──────────────────────────────────────

if ( ! class_exists( 'AffWP_Referral' ) ) {
	class AffWP_Referral {
		public int $referral_id  = 0;
		public int $affiliate_id = 0;
		public int $visit_id     = 0;
		public float $amount     = 0.0;
		public string $currency  = 'USD';
		public string $status    = 'pending';
		public string $description = '';
		public string $reference = '';
		public string $context   = '';
		public string $campaign  = '';
		public string $date      = '2026-01-01 00:00:00';
		public int $payout_id    = 0;
	}
}

// ─── API function stubs ─────────────────────────────────

if ( ! function_exists( 'affwp_get_affiliate' ) ) {
	/**
	 * Get an affiliate by ID.
	 *
	 * @param int $affiliate_id Affiliate ID.
	 * @return AffWP_Affiliate|false
	 */
	function affwp_get_affiliate( $affiliate_id = 0 ) {
		$affiliate_id = (int) $affiliate_id;
		return $GLOBALS['_affwp_affiliates'][ $affiliate_id ] ?? false;
	}
}

if ( ! function_exists( 'affwp_get_referral' ) ) {
	/**
	 * Get a referral by ID.
	 *
	 * @param int $referral_id Referral ID.
	 * @return AffWP_Referral|false
	 */
	function affwp_get_referral( $referral_id = 0 ) {
		$referral_id = (int) $referral_id;
		return $GLOBALS['_affwp_referrals'][ $referral_id ] ?? false;
	}
}

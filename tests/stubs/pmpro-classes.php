<?php
/**
 * Paid Memberships Pro class stubs for PHPUnit tests.
 *
 * @package WP4Odoo\Tests
 */

// ─── Global stores ──────────────────────────────────────

$GLOBALS['_pmpro_levels'] = [];
$GLOBALS['_pmpro_orders'] = [];

// ─── PMPro_Membership_Level ─────────────────────────────

if ( ! class_exists( 'PMPro_Membership_Level' ) ) {

	/**
	 * PMPro_Membership_Level stub — represents a membership level.
	 */
	class PMPro_Membership_Level {

		/**
		 * Level ID.
		 *
		 * @var int
		 */
		public int $id = 0;

		/**
		 * Level name.
		 *
		 * @var string
		 */
		public string $name = '';

		/**
		 * Level description.
		 *
		 * @var string
		 */
		public string $description = '';

		/**
		 * Initial (one-time) payment amount.
		 *
		 * @var string
		 */
		public string $initial_payment = '0.00';

		/**
		 * Recurring billing amount.
		 *
		 * @var string
		 */
		public string $billing_amount = '0.00';

		/**
		 * Billing cycle number (e.g. 1 = every 1 period).
		 *
		 * @var int
		 */
		public int $cycle_number = 0;

		/**
		 * Billing cycle period (Day, Week, Month, Year).
		 *
		 * @var string
		 */
		public string $cycle_period = '';

		/**
		 * Billing limit (0 = unlimited).
		 *
		 * @var int
		 */
		public int $billing_limit = 0;

		/**
		 * Trial payment amount.
		 *
		 * @var string
		 */
		public string $trial_amount = '0.00';

		/**
		 * Trial period limit.
		 *
		 * @var int
		 */
		public int $trial_limit = 0;

		/**
		 * Expiration number.
		 *
		 * @var int
		 */
		public int $expiration_number = 0;

		/**
		 * Expiration period (Day, Week, Month, Year).
		 *
		 * @var string
		 */
		public string $expiration_period = '';

		/**
		 * Whether signups are allowed.
		 *
		 * @var bool
		 */
		public bool $allow_signups = true;
	}
}

// ─── MemberOrder ────────────────────────────────────────

if ( ! class_exists( 'MemberOrder' ) ) {

	/**
	 * MemberOrder stub — represents a PMPro payment order.
	 */
	class MemberOrder {

		/**
		 * Order ID.
		 *
		 * @var int
		 */
		public int $id = 0;

		/**
		 * Unique order code.
		 *
		 * @var string
		 */
		public string $code = '';

		/**
		 * WordPress user ID.
		 *
		 * @var int
		 */
		public int $user_id = 0;

		/**
		 * Membership level ID.
		 *
		 * @var int
		 */
		public int $membership_id = 0;

		/**
		 * Subtotal before tax.
		 *
		 * @var string
		 */
		public string $subtotal = '0.00';

		/**
		 * Tax amount.
		 *
		 * @var string
		 */
		public string $tax = '0.00';

		/**
		 * Total amount.
		 *
		 * @var string
		 */
		public string $total = '0.00';

		/**
		 * Order status: success, pending, refunded, error, review, token.
		 *
		 * @var string
		 */
		public string $status = '';

		/**
		 * Payment gateway.
		 *
		 * @var string
		 */
		public string $gateway = '';

		/**
		 * Gateway transaction ID.
		 *
		 * @var string
		 */
		public string $payment_transaction_id = '';

		/**
		 * Recurring subscription ID.
		 *
		 * @var string
		 */
		public string $subscription_transaction_id = '';

		/**
		 * Order notes.
		 *
		 * @var string
		 */
		public string $notes = '';

		/**
		 * Order timestamp.
		 *
		 * @var string
		 */
		public string $timestamp = '';

		/**
		 * Constructor — loads from global store.
		 *
		 * @param int $id Order ID.
		 */
		public function __construct( int $id = 0 ) {
			if ( $id > 0 && isset( $GLOBALS['_pmpro_orders'][ $id ] ) ) {
				$data = $GLOBALS['_pmpro_orders'][ $id ];
				foreach ( $data as $key => $value ) {
					if ( property_exists( $this, $key ) ) {
						$this->$key = $value;
					}
				}
			}
		}

		/**
		 * Get the membership level for this order.
		 *
		 * @return PMPro_Membership_Level|false
		 */
		public function getMembershipLevel() {
			return pmpro_getLevel( $this->membership_id );
		}

		/**
		 * Get the user for this order.
		 *
		 * @return \WP_User|false
		 */
		public function getUser() {
			return get_userdata( $this->user_id );
		}
	}
}

// ─── Global functions ───────────────────────────────────

if ( ! function_exists( 'pmpro_getLevel' ) ) {
	/**
	 * Get a membership level by ID.
	 *
	 * @param int $level_id Level ID.
	 * @return PMPro_Membership_Level|false
	 */
	function pmpro_getLevel( int $level_id = 0 ) {
		if ( isset( $GLOBALS['_pmpro_levels'][ $level_id ] ) ) {
			return $GLOBALS['_pmpro_levels'][ $level_id ];
		}
		return false;
	}
}

if ( ! function_exists( 'pmpro_getMembershipLevelForUser' ) ) {
	/**
	 * Get a user's membership level.
	 *
	 * @param int $user_id User ID.
	 * @return PMPro_Membership_Level|false
	 */
	function pmpro_getMembershipLevelForUser( int $user_id = 0 ) {
		return false;
	}
}

if ( ! function_exists( 'pmpro_hasMembershipLevel' ) ) {
	/**
	 * Check if a user has a membership level.
	 *
	 * @param array|int $level_ids Level ID(s).
	 * @param int|null  $user_id   User ID.
	 * @return bool
	 */
	function pmpro_hasMembershipLevel( $level_ids = [], $user_id = null ) {
		return false;
	}
}

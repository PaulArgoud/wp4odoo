<?php
/**
 * Restrict Content Pro class and function stubs for PHPUnit tests.
 *
 * RCP v3.0+ stores memberships and payments in custom DB tables,
 * accessed via object classes (RCP_Membership, RCP_Customer, RCP_Payments).
 *
 * @package WP4Odoo\Tests
 */

// ─── Global stores ──────────────────────────────────────

$GLOBALS['_rcp_levels']      = [];
$GLOBALS['_rcp_payments']    = [];
$GLOBALS['_rcp_memberships'] = [];

// ─── RCP_Membership ────────────────────────────────────

if ( ! class_exists( 'RCP_Membership' ) ) {

	/**
	 * RCP_Membership stub — represents a user membership record.
	 */
	class RCP_Membership {

		/**
		 * Membership ID.
		 *
		 * @var int
		 */
		private int $id = 0;

		/**
		 * Customer ID.
		 *
		 * @var int
		 */
		private int $customer_id = 0;

		/**
		 * Membership level ID (object_id).
		 *
		 * @var int
		 */
		private int $object_id = 0;

		/**
		 * Membership status: active, pending, canceled, expired.
		 *
		 * @var string
		 */
		private string $status = '';

		/**
		 * Creation date.
		 *
		 * @var string
		 */
		private string $created_date = '';

		/**
		 * Expiration date.
		 *
		 * @var string
		 */
		private string $expiration_date = '';

		/**
		 * Initial signup amount.
		 *
		 * @var float
		 */
		private float $initial_amount = 0.0;

		/**
		 * Recurring charge amount.
		 *
		 * @var float
		 */
		private float $recurring_amount = 0.0;

		/**
		 * Number of times billed.
		 *
		 * @var int
		 */
		private int $times_billed = 0;

		/**
		 * Gateway subscription ID (non-empty = recurring).
		 *
		 * @var string
		 */
		private string $gateway_subscription_id = '';

		/**
		 * Constructor — loads from global store.
		 *
		 * @param int $id Membership ID.
		 */
		public function __construct( int $id = 0 ) {
			if ( $id > 0 && isset( $GLOBALS['_rcp_memberships'][ $id ] ) ) {
				$data = $GLOBALS['_rcp_memberships'][ $id ];
				foreach ( $data as $key => $value ) {
					if ( property_exists( $this, $key ) ) {
						$this->$key = $value;
					}
				}
			}
		}

		/**
		 * Get membership ID.
		 *
		 * @return int
		 */
		public function get_id(): int {
			return $this->id;
		}

		/**
		 * Get the customer ID.
		 *
		 * @return int
		 */
		public function get_customer_id(): int {
			return $this->customer_id;
		}

		/**
		 * Get the customer object.
		 *
		 * @return RCP_Customer|false
		 */
		public function get_customer() {
			if ( $this->customer_id <= 0 ) {
				return false;
			}
			return new RCP_Customer( $this->customer_id );
		}

		/**
		 * Get the membership level ID.
		 *
		 * @return int
		 */
		public function get_object_id(): int {
			return $this->object_id;
		}

		/**
		 * Get the membership status.
		 *
		 * @return string
		 */
		public function get_status(): string {
			return $this->status;
		}

		/**
		 * Get the creation date.
		 *
		 * @return string
		 */
		public function get_created_date(): string {
			return $this->created_date;
		}

		/**
		 * Get the expiration date.
		 *
		 * @param bool $formatted Whether to format the date.
		 * @return string
		 */
		public function get_expiration_date( bool $formatted = true ): string {
			return $this->expiration_date;
		}

		/**
		 * Get the initial signup amount.
		 *
		 * @param bool $formatted Whether to format the amount.
		 * @return float
		 */
		public function get_initial_amount( bool $formatted = false ): float {
			return $this->initial_amount;
		}

		/**
		 * Get the recurring charge amount.
		 *
		 * @param bool $formatted Whether to format the amount.
		 * @return float
		 */
		public function get_recurring_amount( bool $formatted = false ): float {
			return $this->recurring_amount;
		}

		/**
		 * Check if the membership is recurring.
		 *
		 * @return bool
		 */
		public function is_recurring(): bool {
			return '' !== $this->gateway_subscription_id;
		}

		/**
		 * Check if the membership is active.
		 *
		 * @return bool
		 */
		public function is_active(): bool {
			return 'active' === $this->status;
		}

		/**
		 * Check if the membership is expired.
		 *
		 * @return bool
		 */
		public function is_expired(): bool {
			return 'expired' === $this->status;
		}

		/**
		 * Get the number of times billed.
		 *
		 * @return int
		 */
		public function get_times_billed(): int {
			return $this->times_billed;
		}

		// ─── Test helpers ──────────────────────────────────

		/**
		 * Set a property value (test helper).
		 *
		 * @param string $key   Property name.
		 * @param mixed  $value Property value.
		 * @return void
		 */
		public function set( string $key, $value ): void {
			if ( property_exists( $this, $key ) ) {
				$this->$key = $value;
			}
		}
	}
}

// ─── RCP_Customer ──────────────────────────────────────

if ( ! class_exists( 'RCP_Customer' ) ) {

	/**
	 * RCP_Customer stub — wraps a WordPress user with RCP customer data.
	 */
	class RCP_Customer {

		/**
		 * Customer ID.
		 *
		 * @var int
		 */
		private int $id = 0;

		/**
		 * WordPress user ID.
		 *
		 * @var int
		 */
		private int $user_id = 0;

		/**
		 * Constructor — loads from global store or sets user_id directly.
		 *
		 * @param int $customer_id Customer ID.
		 */
		public function __construct( int $customer_id = 0 ) {
			$this->id = $customer_id;
			// In tests, the customer_id is used to derive user_id.
			// Default: customer_id = user_id for simplicity.
			$this->user_id = $customer_id;
		}

		/**
		 * Get customer ID.
		 *
		 * @return int
		 */
		public function get_id(): int {
			return $this->id;
		}

		/**
		 * Get the WordPress user ID.
		 *
		 * @return int
		 */
		public function get_user_id(): int {
			return $this->user_id;
		}

		/**
		 * Set a property value (test helper).
		 *
		 * @param string $key   Property name.
		 * @param mixed  $value Property value.
		 * @return void
		 */
		public function set( string $key, $value ): void {
			if ( property_exists( $this, $key ) ) {
				$this->$key = $value;
			}
		}
	}
}

// ─── RCP_Payments ──────────────────────────────────────

if ( ! class_exists( 'RCP_Payments' ) ) {

	/**
	 * RCP_Payments stub — payment record operations.
	 */
	class RCP_Payments {

		/**
		 * Get a payment by ID.
		 *
		 * @param int $payment_id Payment ID.
		 * @return object|null Payment data object, or null if not found.
		 */
		public function get_payment( int $payment_id ) {
			if ( isset( $GLOBALS['_rcp_payments'][ $payment_id ] ) ) {
				return (object) $GLOBALS['_rcp_payments'][ $payment_id ];
			}
			return null;
		}
	}
}

// ─── Global functions ───────────────────────────────────

if ( ! function_exists( 'rcp_get_membership' ) ) {
	/**
	 * Get a membership by ID.
	 *
	 * @param int $membership_id Membership ID.
	 * @return RCP_Membership|false
	 */
	function rcp_get_membership( int $membership_id = 0 ) {
		if ( isset( $GLOBALS['_rcp_memberships'][ $membership_id ] ) ) {
			return new RCP_Membership( $membership_id );
		}
		return false;
	}
}

if ( ! function_exists( 'rcp_get_membership_level' ) ) {
	/**
	 * Get a membership level by ID.
	 *
	 * @param int $level_id Level ID.
	 * @return object|false Level data object, or false if not found.
	 */
	function rcp_get_membership_level( int $level_id = 0 ) {
		if ( isset( $GLOBALS['_rcp_levels'][ $level_id ] ) ) {
			return (object) $GLOBALS['_rcp_levels'][ $level_id ];
		}
		return false;
	}
}

if ( ! function_exists( 'rcp_get_customer_by_user_id' ) ) {
	/**
	 * Get a customer by WordPress user ID.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return RCP_Customer|false
	 */
	function rcp_get_customer_by_user_id( int $user_id = 0 ) {
		if ( $user_id <= 0 ) {
			return false;
		}
		$customer = new RCP_Customer( $user_id );
		$customer->set( 'user_id', $user_id );
		return $customer;
	}
}

<?php
/**
 * MemberPress class stubs for PHPUnit tests.
 *
 * @package WP4Odoo\Tests
 */

// ─── Global stores ──────────────────────────────────────

$GLOBALS['_mepr_transactions']  = [];
$GLOBALS['_mepr_subscriptions'] = [];

// ─── MeprProduct (membership plan) ─────────────────────

if ( ! class_exists( 'MeprProduct' ) ) {

	/**
	 * MeprProduct stub — extends WP Post type 'memberpressproduct'.
	 */
	class MeprProduct {

		/**
		 * Post ID.
		 *
		 * @var int
		 */
		public int $ID = 0;

		/**
		 * Plan title (from post_title).
		 *
		 * @var string
		 */
		public string $post_title = '';

		/**
		 * Plan price.
		 *
		 * @var string
		 */
		public string $price = '0.00';

		/**
		 * Constructor — loads from WP post store.
		 *
		 * @param int $id Post ID.
		 */
		public function __construct( int $id = 0 ) {
			$this->ID = $id;

			$post = get_post( $id );
			if ( $post && 'memberpressproduct' === ( $post->post_type ?? '' ) ) {
				$this->post_title = $post->post_title ?? '';
				$price            = get_post_meta( $id, '_mepr_product_price', true );
				$this->price      = $price ?: '0.00';
			}
		}

		/**
		 * Get the plan price.
		 *
		 * @return string
		 */
		public function get_price(): string {
			return $this->price;
		}
	}
}

// ─── MeprTransaction ───────────────────────────────────

if ( ! class_exists( 'MeprTransaction' ) ) {

	/**
	 * MeprTransaction stub — represents a payment/transaction.
	 */
	class MeprTransaction {

		/**
		 * Transaction ID.
		 *
		 * @var int
		 */
		public int $id = 0;

		/**
		 * WordPress user ID.
		 *
		 * @var int
		 */
		public int $user_id = 0;

		/**
		 * MemberPress product ID.
		 *
		 * @var int
		 */
		public int $product_id = 0;

		/**
		 * Transaction amount.
		 *
		 * @var float
		 */
		public float $amount = 0.0;

		/**
		 * Total including tax.
		 *
		 * @var float
		 */
		public float $total = 0.0;

		/**
		 * Tax amount.
		 *
		 * @var float
		 */
		public float $tax_amount = 0.0;

		/**
		 * Transaction reference number.
		 *
		 * @var string
		 */
		public string $trans_num = '';

		/**
		 * Creation date.
		 *
		 * @var string
		 */
		public string $created_at = '';

		/**
		 * Transaction status: pending, complete, failed, refunded.
		 *
		 * @var string
		 */
		public string $status = '';

		/**
		 * Related subscription ID (0 if one-time).
		 *
		 * @var int
		 */
		public int $subscription_id = 0;

		/**
		 * Constructor — loads from global store.
		 *
		 * @param int $id Transaction ID.
		 */
		public function __construct( int $id = 0 ) {
			if ( $id > 0 && isset( $GLOBALS['_mepr_transactions'][ $id ] ) ) {
				$data = $GLOBALS['_mepr_transactions'][ $id ];
				foreach ( $data as $key => $value ) {
					if ( property_exists( $this, $key ) ) {
						$this->$key = $value;
					}
				}
			}
		}
	}
}

// ─── MeprSubscription ──────────────────────────────────

if ( ! class_exists( 'MeprSubscription' ) ) {

	/**
	 * MeprSubscription stub — represents a recurring subscription.
	 */
	class MeprSubscription {

		/**
		 * Subscription ID.
		 *
		 * @var int
		 */
		public int $id = 0;

		/**
		 * Gateway subscription ID.
		 *
		 * @var string
		 */
		public string $subscr_id = '';

		/**
		 * WordPress user ID.
		 *
		 * @var int
		 */
		public int $user_id = 0;

		/**
		 * MemberPress product ID.
		 *
		 * @var int
		 */
		public int $product_id = 0;

		/**
		 * Subscription price.
		 *
		 * @var string
		 */
		public string $price = '0.00';

		/**
		 * Billing period.
		 *
		 * @var int
		 */
		public int $period = 1;

		/**
		 * Billing period type (months, weeks, years).
		 *
		 * @var string
		 */
		public string $period_type = 'months';

		/**
		 * Subscription status: active, suspended, cancelled, expired, paused, stopped.
		 *
		 * @var string
		 */
		public string $status = '';

		/**
		 * Creation date.
		 *
		 * @var string
		 */
		public string $created_at = '';

		/**
		 * Constructor — loads from global store.
		 *
		 * @param int $id Subscription ID.
		 */
		public function __construct( int $id = 0 ) {
			if ( $id > 0 && isset( $GLOBALS['_mepr_subscriptions'][ $id ] ) ) {
				$data = $GLOBALS['_mepr_subscriptions'][ $id ];
				foreach ( $data as $key => $value ) {
					if ( property_exists( $this, $key ) ) {
						$this->$key = $value;
					}
				}
			}
		}
	}
}

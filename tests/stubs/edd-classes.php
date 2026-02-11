<?php
/**
 * Easy Digital Downloads class and function stubs for PHPUnit tests.
 *
 * @package WP4Odoo\Tests
 */

// ─── Global classes and test stores ─────────────────────

namespace {

	$GLOBALS['_edd_orders'] = [];

	/**
	 * Main EDD plugin class stub (for class_exists checks).
	 */
	class Easy_Digital_Downloads {
	}

	/**
	 * EDD_Download stub — represents a downloadable product.
	 */
	class EDD_Download {

		/**
		 * Download ID.
		 *
		 * @var int
		 */
		public int $ID = 0;

		/**
		 * Internal data store.
		 *
		 * @var array
		 */
		private array $data = [];

		/**
		 * Constructor.
		 *
		 * @param int $id Download ID.
		 */
		public function __construct( int $id = 0 ) {
			$this->ID = $id;
		}

		/**
		 * Get the download price.
		 *
		 * @return string
		 */
		public function get_price(): string {
			return (string) ( $this->data['price'] ?? '0.00' );
		}

		/**
		 * Get the download ID.
		 *
		 * @return int
		 */
		public function get_ID(): int {
			return $this->ID;
		}

		/**
		 * Set internal data for testing.
		 *
		 * @param array $data Data to set.
		 * @return void
		 */
		public function set_data( array $data ): void {
			$this->data = $data;
		}
	}

	/**
	 * EDD_Customer stub.
	 */
	class EDD_Customer {

		/**
		 * Customer ID.
		 *
		 * @var int
		 */
		public int $id = 0;

		/**
		 * Customer email.
		 *
		 * @var string
		 */
		public string $email = '';

		/**
		 * Customer name.
		 *
		 * @var string
		 */
		public string $name = '';

		/**
		 * WordPress user ID.
		 *
		 * @var int
		 */
		public int $user_id = 0;

		/**
		 * Number of purchases.
		 *
		 * @var int
		 */
		public int $purchase_count = 0;

		/**
		 * Total amount spent.
		 *
		 * @var float
		 */
		public float $purchase_value = 0.0;
	}
}

// ─── EDD 3.0+ namespaced order class ───────────────────

namespace EDD\Orders {

	/**
	 * Order stub (EDD 3.0+ custom tables).
	 */
	class Order {

		/**
		 * Order ID.
		 *
		 * @var int
		 */
		public int $id = 0;

		/**
		 * Order total.
		 *
		 * @var float
		 */
		public float $total = 0.0;

		/**
		 * Order subtotal.
		 *
		 * @var float
		 */
		public float $subtotal = 0.0;

		/**
		 * Tax amount.
		 *
		 * @var float
		 */
		public float $tax = 0.0;

		/**
		 * Date created.
		 *
		 * @var string
		 */
		public string $date_created = '';

		/**
		 * Order status.
		 *
		 * @var string
		 */
		public string $status = 'pending';

		/**
		 * Customer email.
		 *
		 * @var string
		 */
		public string $email = '';

		/**
		 * Customer ID.
		 *
		 * @var int
		 */
		public int $customer_id = 0;

		/**
		 * Currency code.
		 *
		 * @var string
		 */
		public string $currency = 'USD';

		/**
		 * Payment gateway.
		 *
		 * @var string
		 */
		public string $gateway = '';
	}
}

// ─── Global functions (must be in global namespace) ─────

namespace {

	/**
	 * Get an EDD download by ID.
	 *
	 * @param int $id Download ID.
	 * @return EDD_Download|null
	 */
	function edd_get_download( int $id ): ?EDD_Download {
		$post = get_post( $id );
		if ( ! $post || 'download' !== ( $post->post_type ?? '' ) ) {
			return null;
		}

		$download     = new EDD_Download( $id );
		$price        = get_post_meta( $id, 'edd_price', true );
		$download->set_data( [ 'price' => $price ?: '0.00' ] );

		return $download;
	}

	/**
	 * Get an EDD order by ID (EDD 3.0+).
	 *
	 * @param int $id Order ID.
	 * @return \EDD\Orders\Order|false
	 */
	function edd_get_order( int $id ) {
		return $GLOBALS['_edd_orders'][ $id ] ?? false;
	}

	/**
	 * Update an EDD order status.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status   New status.
	 * @return bool
	 */
	function edd_update_order_status( int $order_id, string $status ): bool {
		if ( isset( $GLOBALS['_edd_orders'][ $order_id ] ) ) {
			$GLOBALS['_edd_orders'][ $order_id ]->status = $status;
			return true;
		}
		return false;
	}
}

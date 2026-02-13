<?php
/**
 * WooCommerce Bookings stub classes for unit tests.
 *
 * @package WP4Odoo\Tests
 */

// ─── Global store ───────────────────────────────────────

$GLOBALS['_wc_bookings'] = [];

// ─── WC_Booking ─────────────────────────────────────────

if ( ! class_exists( 'WC_Booking' ) ) {
	/**
	 * Stub for WC_Booking.
	 *
	 * Loads data from $GLOBALS['_wc_bookings'][$id].
	 */
	class WC_Booking {
		/** @var int */
		protected int $id = 0;

		/** @var array<string, mixed> */
		protected array $data = [];

		public function __construct( int $id = 0 ) {
			$this->id = $id;
			if ( isset( $GLOBALS['_wc_bookings'][ $id ] ) ) {
				$this->data = $GLOBALS['_wc_bookings'][ $id ];
			}
		}

		public function get_id(): int {
			return empty( $this->data ) ? 0 : $this->id;
		}

		public function get_product_id(): int {
			return (int) ( $this->data['product_id'] ?? 0 );
		}

		/**
		 * @param string $format Date format.
		 * @return string
		 */
		public function get_start_date( string $format = 'Y-m-d H:i:s' ): string {
			return $this->data['start_date'] ?? '';
		}

		/**
		 * @param string $format Date format.
		 * @return string
		 */
		public function get_end_date( string $format = 'Y-m-d H:i:s' ): string {
			return $this->data['end_date'] ?? '';
		}

		public function get_status(): string {
			return $this->data['status'] ?? '';
		}

		public function is_all_day(): bool {
			return ! empty( $this->data['all_day'] );
		}

		/**
		 * @return array<int, int>
		 */
		public function get_persons(): array {
			return $this->data['persons'] ?? [];
		}

		public function get_persons_total(): int {
			$persons = $this->get_persons();
			return array_sum( $persons );
		}

		public function get_customer_id(): int {
			return (int) ( $this->data['customer_id'] ?? 0 );
		}

		public function get_order_id(): int {
			return (int) ( $this->data['order_id'] ?? 0 );
		}

		public function get_cost(): float {
			return (float) ( $this->data['cost'] ?? 0 );
		}
	}
}

// ─── WC_Product_Booking ─────────────────────────────────

if ( ! class_exists( 'WC_Product_Booking' ) ) {
	/**
	 * Stub for WC_Product_Booking (extends WC_Product).
	 */
	class WC_Product_Booking extends WC_Product {
		public function get_type(): string {
			return 'booking';
		}

		public function get_duration(): int {
			return 1;
		}

		public function get_duration_unit(): string {
			return 'hour';
		}

		public function get_base_cost(): string {
			return '0';
		}
	}
}

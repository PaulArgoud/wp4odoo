<?php
/**
 * Stubs for WooCommerce Shipping module dependencies.
 *
 * Provides ShipStation, Sendcloud, and Packlink stubs.
 *
 * @package WP4Odoo\Tests\Stubs
 */

// ShipStation stub.
if ( ! defined( 'SHIPSTATION_WC_VERSION' ) ) {
	// Not defined by default — tests set it when needed.
}

// Sendcloud stub.
if ( ! defined( 'SENDCLOUD_PLUGIN_VERSION' ) ) {
	// Not defined by default — tests set it when needed.
}

// Packlink stub.
if ( ! defined( 'PACKLINK_VERSION' ) ) {
	// Not defined by default — tests set it when needed.
}

// WC_Shipping_Zones stub.
if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
	/**
	 * Minimal WC_Shipping_Zones stub for testing.
	 */
	class WC_Shipping_Zones {

		/**
		 * Static zones storage.
		 *
		 * @var array
		 */
		private static array $zones = [];

		/**
		 * Get all shipping zones.
		 *
		 * @return array
		 */
		public static function get_zones(): array {
			return $GLOBALS['_wc_shipping_zones'] ?? self::$zones;
		}

		/**
		 * Set zones for testing.
		 *
		 * @param array $zones Zones data.
		 * @return void
		 */
		public static function set_zones( array $zones ): void {
			self::$zones = $zones;
		}
	}
}

// WC_Shipping_Method stub.
if ( ! class_exists( 'WC_Shipping_Method' ) ) {
	/**
	 * Minimal WC_Shipping_Method stub for testing.
	 */
	class WC_Shipping_Method {

		/**
		 * Method data.
		 *
		 * @var array
		 */
		private array $data = [];

		/**
		 * Set method data.
		 *
		 * @param array $data Method data.
		 * @return void
		 */
		public function set_data( array $data ): void {
			$this->data = $data;
		}

		/**
		 * Get instance ID.
		 *
		 * @return int
		 */
		public function get_instance_id(): int {
			return (int) ( $this->data['instance_id'] ?? 0 );
		}

		/**
		 * Get title.
		 *
		 * @return string
		 */
		public function get_title(): string {
			return $this->data['title'] ?? '';
		}

		/**
		 * Get method ID.
		 *
		 * @return string
		 */
		public function get_method_id(): string {
			return $this->data['method_id'] ?? '';
		}
	}
}

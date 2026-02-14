<?php
/**
 * WC Product Bundles / Composite Products class stubs for PHPUnit tests.
 *
 * Provides minimal stubs for WC_Product_Bundle, WC_Bundled_Item,
 * WC_Product_Composite, and the detection classes WC_Bundles
 * and WC_Composite_Products.
 *
 * @package WP4Odoo\Tests
 */

// ─── Detection classes ──────────────────────────────────

if ( ! class_exists( 'WC_Bundles' ) ) {
	class WC_Bundles {}
}

if ( ! class_exists( 'WC_Composite_Products' ) ) {
	class WC_Composite_Products {}
}

// ─── Bundle classes ─────────────────────────────────────

if ( ! class_exists( 'WC_Product_Bundle' ) ) {
	class WC_Product_Bundle extends WC_Product {
		public function get_type(): string { return 'bundle'; }

		/** @return WC_Bundled_Item[] */
		public function get_bundled_items(): array {
			return $GLOBALS['_wc_bundles'][ $this->get_id() ] ?? [];
		}
	}
}

if ( ! class_exists( 'WC_Bundled_Item' ) ) {
	class WC_Bundled_Item {
		private int $product_id;
		private int $quantity;
		private bool $optional;

		public function __construct( int $product_id = 0, int $quantity = 1, bool $optional = false ) {
			$this->product_id = $product_id;
			$this->quantity   = $quantity;
			$this->optional   = $optional;
		}

		public function get_product_id(): int { return $this->product_id; }
		public function get_quantity( string $context = 'min' ): int { return $this->quantity; }
		public function is_optional(): bool { return $this->optional; }
	}
}

// ─── Composite classes ──────────────────────────────────

if ( ! class_exists( 'WC_Product_Composite' ) ) {
	class WC_Product_Composite extends WC_Product {
		public function get_type(): string { return 'composite'; }

		/** @return array<int, array<string, mixed>> */
		public function get_composite_data(): array {
			return $GLOBALS['_wc_composites'][ $this->get_id() ] ?? [];
		}
	}
}

<?php
/**
 * WooCommerce class and function stubs for PHPUnit tests.
 *
 * @package WP4Odoo\Tests
 */

// ─── WooCommerce main class ─────────────────────────────

if ( ! class_exists( 'WooCommerce' ) ) {
	class WooCommerce {
		public string $version = '10.5.0';
	}
}

if ( ! defined( 'WC_VERSION' ) ) {
	define( 'WC_VERSION', '10.5.0' );
}

// ─── WC functions ───────────────────────────────────────

if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( $product_id = 0 ) {
		if ( isset( $GLOBALS['_wc_products'][ $product_id ] ) ) {
			$data = $GLOBALS['_wc_products'][ $product_id ];
			if ( $data instanceof WC_Product ) {
				return $data;
			}
			$product = new WC_Product( $product_id );
			$product->set_data( $data );
			return $product;
		}
		return false;
	}
}

if ( ! function_exists( 'wc_get_products' ) ) {
	function wc_get_products( $args = [] ) {
		return [];
	}
}

if ( ! function_exists( 'wc_update_product_stock' ) ) {
	function wc_update_product_stock( $product_id, $stock_quantity = null ) {
		return true;
	}
}

if ( ! function_exists( 'get_woocommerce_currency' ) ) {
	function get_woocommerce_currency() {
		return 'EUR';
	}
}

if ( ! function_exists( 'wc_get_order' ) ) {
	function wc_get_order( $order_id = 0 ) {
		if ( isset( $GLOBALS['_wc_orders'][ $order_id ] ) ) {
			$data = $GLOBALS['_wc_orders'][ $order_id ];
			if ( $data instanceof WC_Order ) {
				return $data;
			}
			$order = new WC_Order( $order_id );
			$order->set_data( $data );
			return $order;
		}
		return false;
	}
}

// ─── WC classes ─────────────────────────────────────────

if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {
		protected int $id = 0;
		protected array $data = [];
		public function __construct( int $id = 0 ) { $this->id = $id; }
		public function get_id(): int { return $this->id; }
		public function get_name(): string { return $this->data['name'] ?? ''; }
		public function set_name( string $name ): void { $this->data['name'] = $name; }
		public function get_type(): string { return $this->data['type'] ?? 'simple'; }
		public function get_price(): string { return $this->data['price'] ?? ''; }
		public function get_sku(): string { return $this->data['sku'] ?? ''; }
		public function set_sku( string $sku ): void { $this->data['sku'] = $sku; }
		public function get_regular_price(): string { return $this->data['regular_price'] ?? ''; }
		public function set_regular_price( string $price ): void { $this->data['regular_price'] = $price; }
		public function get_sale_price(): string { return $this->data['sale_price'] ?? ''; }
		public function set_sale_price( $price ): void { $this->data['sale_price'] = (string) $price; }
		public function get_stock_quantity(): ?int { return $this->data['stock_quantity'] ?? null; }
		public function set_stock_quantity( ?int $quantity ): void { $this->data['stock_quantity'] = $quantity; }
		public function managing_stock(): bool { return $this->data['manage_stock'] ?? true; }
		public function set_manage_stock( bool $manage ): void { $this->data['manage_stock'] = $manage; }
		public function get_weight(): string { return $this->data['weight'] ?? ''; }
		public function set_weight( string $weight ): void { $this->data['weight'] = $weight; }
		public function get_description(): string { return $this->data['description'] ?? ''; }
		public function set_description( string $description ): void { $this->data['description'] = $description; }
		public function save(): int { return $this->id ?: 1; }
		public function delete( bool $force = false ): bool { return true; }
		/** @param array<string, mixed> $data */
		public function set_data( array $data ): void { $this->data = $data; }
	}
}

if ( ! class_exists( 'WC_Product_Variable' ) ) {
	class WC_Product_Variable extends WC_Product {
		public function __construct( int $id = 0 ) { $this->id = $id; }
		public function set_attributes( $attributes ): void {}
	}
}

if ( ! class_exists( 'WC_Product_Variation' ) ) {
	class WC_Product_Variation extends WC_Product {
		protected int $parent_id = 0;
		protected array $attrs = [];
		public function set_parent_id( int $parent_id ): void { $this->parent_id = $parent_id; }
		public function set_attributes( $attributes ): void { $this->attrs = is_array( $attributes ) ? $attributes : []; }
	}
}

if ( ! class_exists( 'WC_Product_Attribute' ) ) {
	class WC_Product_Attribute {
		private string $name = '';
		private array $options = [];
		private bool $visible = true;
		private bool $variation = false;
		private int $position = 0;
		public function set_name( string $name ): void { $this->name = $name; }
		public function set_options( array $options ): void { $this->options = $options; }
		public function set_visible( bool $visible ): void { $this->visible = $visible; }
		public function set_variation( bool $variation ): void { $this->variation = $variation; }
		public function set_position( int $position ): void { $this->position = $position; }
		public function get_name(): string { return $this->name; }
		public function get_options(): array { return $this->options; }
	}
}

// ─── WC Memberships functions ───────────────────────────

if ( ! function_exists( 'wc_memberships' ) ) {
	function wc_memberships() {
		return new stdClass();
	}
}

if ( ! function_exists( 'wc_memberships_get_user_membership' ) ) {
	function wc_memberships_get_user_membership( $membership_id = 0 ) {
		return $GLOBALS['_wc_memberships'][ $membership_id ] ?? false;
	}
}

if ( ! function_exists( 'wc_memberships_get_membership_plan' ) ) {
	function wc_memberships_get_membership_plan( $plan_id = 0 ) {
		return $GLOBALS['_wc_membership_plans'][ $plan_id ] ?? false;
	}
}

// ─── WC Memberships classes ─────────────────────────────

if ( ! class_exists( 'WC_Memberships_User_Membership' ) ) {
	class WC_Memberships_User_Membership {
		protected int $id = 0;
		protected array $data = [];
		public function __construct( int $id = 0 ) { $this->id = $id; }
		public function get_id(): int { return $this->id; }
		public function get_plan_id(): int { return $this->data['plan_id'] ?? 0; }
		public function get_plan(): ?WC_Memberships_Membership_Plan {
			return $GLOBALS['_wc_membership_plans'][ $this->get_plan_id() ] ?? null;
		}
		public function get_user_id(): int { return $this->data['user_id'] ?? 0; }
		public function get_status(): string { return $this->data['status'] ?? 'wcm-active'; }
		public function get_start_date( string $format = 'Y-m-d H:i:s' ): string { return $this->data['start_date'] ?? ''; }
		public function get_end_date( string $format = 'Y-m-d H:i:s' ): string { return $this->data['end_date'] ?? ''; }
		public function get_cancelled_date( string $format = 'Y-m-d H:i:s' ): string { return $this->data['cancelled_date'] ?? ''; }
		public function get_paused_date( string $format = 'Y-m-d H:i:s' ): string { return $this->data['paused_date'] ?? ''; }
		public function get_order_id(): int { return $this->data['order_id'] ?? 0; }
		public function get_product_id(): int { return $this->data['product_id'] ?? 0; }
		/** @param array<string, mixed> $data */
		public function set_data( array $data ): void { $this->data = $data; }
	}
}

if ( ! class_exists( 'WC_Memberships_Membership_Plan' ) ) {
	class WC_Memberships_Membership_Plan {
		protected int $id = 0;
		protected array $data = [];
		public function __construct( int $id = 0 ) { $this->id = $id; }
		public function get_id(): int { return $this->id; }
		public function get_name(): string { return $this->data['name'] ?? ''; }
		/** @return int[] */
		public function get_product_ids(): array { return $this->data['product_ids'] ?? []; }
		public function get_access_length_amount(): int { return $this->data['access_length_amount'] ?? 0; }
		public function get_access_length_period(): string { return $this->data['access_length_period'] ?? ''; }
		/** @param array<string, mixed> $data */
		public function set_data( array $data ): void { $this->data = $data; }
	}
}

// ─── WC_DateTime ────────────────────────────────────────

if ( ! class_exists( 'WC_DateTime' ) ) {
	class WC_DateTime extends \DateTime {
	}
}

// ─── WC Order ───────────────────────────────────────────

if ( ! class_exists( 'WC_Order' ) ) {
	class WC_Order {
		protected int $id = 0;
		protected array $data = [];
		public function __construct( int $id = 0 ) {
			$this->id = $id;
		}
		public function get_id(): int { return $this->id; }
		public function get_total(): string { return $this->data['total'] ?? '0.00'; }
		public function get_date_created(): ?\WC_DateTime {
			return isset( $this->data['date_created'] ) ? new \WC_DateTime( $this->data['date_created'] ) : null;
		}
		public function get_status(): string { return $this->data['status'] ?? 'pending'; }
		public function set_status( string $status ): void { $this->data['status'] = $status; }
		public function get_billing_email(): string { return $this->data['billing_email'] ?? ''; }
		public function get_formatted_billing_full_name(): string { return $this->data['billing_name'] ?? ''; }
		public function get_customer_id(): int { return $this->data['customer_id'] ?? 0; }
		/**
		 * @param string $type Item type: '' for line items, 'tax', 'shipping'.
		 * @return array
		 */
		public function get_items( string $type = '' ): array {
			if ( 'tax' === $type ) {
				return $this->data['tax_items'] ?? [];
			}
			return $this->data['items'] ?? [];
		}
		/** @return array<int, object> */
		public function get_shipping_methods(): array { return $this->data['shipping_methods'] ?? []; }
		/** @param string $key Meta key. */
		public function update_meta_data( string $key, $value ): void {
			$this->data['meta'][ $key ] = $value;
		}
		/**
		 * @param string $key    Meta key.
		 * @param bool   $single Unused.
		 * @return mixed
		 */
		public function get_meta( string $key, bool $single = true ) {
			return $this->data['meta'][ $key ] ?? '';
		}
		/** @param string $note Note text. */
		public function add_order_note( string $note ): int { return 1; }
		public function save(): int { return $this->id ?: 1; }
		/** @param array<string, mixed> $data */
		public function set_data( array $data ): void { $this->data = $data; }
	}

	/**
	 * Stub for WC_Order_Item (line item).
	 */
	class WC_Order_Item {
		protected array $data = [];
		public function __construct( array $data = [] ) { $this->data = $data; }
		public function get_name(): string { return $this->data['name'] ?? ''; }
		public function get_quantity(): int { return (int) ( $this->data['quantity'] ?? 1 ); }
		public function get_total(): string { return $this->data['total'] ?? '0.00'; }
		public function get_tax_class(): string { return $this->data['tax_class'] ?? ''; }
		public function get_product_id(): int { return (int) ( $this->data['product_id'] ?? 0 ); }
	}

	/**
	 * Stub for WC_Order_Item_Tax.
	 */
	class WC_Order_Item_Tax {
		protected array $data = [];
		public function __construct( array $data = [] ) { $this->data = $data; }
		public function get_rate_id(): int { return (int) ( $this->data['rate_id'] ?? 0 ); }
		public function get_label(): string { return $this->data['label'] ?? ''; }
		public function get_tax_total(): string { return $this->data['tax_total'] ?? '0.00'; }
		public function get_rate_code(): string { return $this->data['rate_code'] ?? ''; }
	}

	/**
	 * Stub for WC_Order_Item_Shipping.
	 */
	class WC_Order_Item_Shipping {
		protected array $data = [];
		public function __construct( array $data = [] ) { $this->data = $data; }
		public function get_method_id(): string { return $this->data['method_id'] ?? ''; }
		public function get_method_title(): string { return $this->data['method_title'] ?? ''; }
		public function get_total(): string { return $this->data['total'] ?? '0.00'; }
	}
}

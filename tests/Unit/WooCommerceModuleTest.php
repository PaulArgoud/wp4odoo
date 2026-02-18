<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Modules\WooCommerce_Module;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WooCommerce_Module.
 *
 * Tests module configuration, entity type declarations, and default settings.
 * WC-specific hook callbacks require full WC stubs and are tested in integration.
 */
class WooCommerceModuleTest extends TestCase {

	private WooCommerce_Module $module;
	private \WP_DB_Stub $wpdb;

	protected function setUp(): void {
		global $wpdb;
		$this->wpdb = new \WP_DB_Stub();
		$wpdb       = $this->wpdb;

		$this->module = new WooCommerce_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );
	}

	// ─── Module Identity ───────────────────────────────────

	public function test_module_id_is_woocommerce(): void {
		$this->assertSame( 'woocommerce', $this->module->get_id() );
	}

	public function test_module_name_is_woocommerce(): void {
		$this->assertSame( 'WooCommerce', $this->module->get_name() );
	}

	public function test_exclusive_group(): void {
		$this->assertSame( 'ecommerce', $this->module->get_exclusive_group() );
	}

	// ─── Odoo Models ───────────────────────────────────────

	public function test_declares_product_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'product.template', $models['product'] );
	}

	public function test_declares_order_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'sale.order', $models['order'] );
	}

	public function test_declares_stock_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'stock.quant', $models['stock'] );
	}

	public function test_declares_variant_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'product.product', $models['variant'] );
	}

	public function test_declares_invoice_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'account.move', $models['invoice'] );
	}

	public function test_declares_pricelist_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'product.pricelist', $models['pricelist'] );
	}

	public function test_declares_shipment_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'stock.picking', $models['shipment'] );
	}

	// ─── Default Settings ──────────────────────────────────

	public function test_default_settings_has_sync_products(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['sync_products'] );
	}

	public function test_default_settings_has_sync_orders(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['sync_orders'] );
	}

	public function test_default_settings_has_sync_stock(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['sync_stock'] );
	}

	public function test_default_settings_has_auto_confirm_orders(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['auto_confirm_orders'] );
	}

	public function test_default_settings_has_sync_pricelists_disabled(): void {
		$settings = $this->module->get_default_settings();
		$this->assertFalse( $settings['sync_pricelists'] );
	}

	public function test_default_settings_has_pricelist_id_zero(): void {
		$settings = $this->module->get_default_settings();
		$this->assertSame( 0, $settings['pricelist_id'] );
	}

	public function test_default_settings_has_sync_shipments_disabled(): void {
		$settings = $this->module->get_default_settings();
		$this->assertFalse( $settings['sync_shipments'] );
	}

	// ─── Settings Fields ───────────────────────────────────

	public function test_settings_fields_exposes_fifteen_fields(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertCount( 15, $fields );
		$this->assertArrayHasKey( 'sync_products', $fields );
		$this->assertArrayHasKey( 'sync_orders', $fields );
		$this->assertArrayHasKey( 'sync_stock', $fields );
		$this->assertArrayHasKey( 'sync_product_images', $fields );
		$this->assertArrayHasKey( 'sync_gallery_images', $fields );
		$this->assertArrayHasKey( 'sync_pricelists', $fields );
		$this->assertArrayHasKey( 'pricelist_id', $fields );
		$this->assertArrayHasKey( 'sync_shipments', $fields );
		$this->assertArrayHasKey( 'sync_taxes', $fields );
		$this->assertArrayHasKey( 'tax_mapping', $fields );
		$this->assertArrayHasKey( 'sync_shipping', $fields );
		$this->assertArrayHasKey( 'shipping_mapping', $fields );
		$this->assertArrayHasKey( 'auto_confirm_orders', $fields );
		$this->assertArrayHasKey( 'convert_currency', $fields );
		$this->assertArrayHasKey( 'sync_translations', $fields );
	}

	// ─── Field Mappings ────────────────────────────────────

	public function test_product_mapping_includes_sku(): void {
		$mapping = $this->module->map_to_odoo( 'product', [ 'sku' => 'ABC123' ] );
		$this->assertSame( 'ABC123', $mapping['default_code'] );
	}

	public function test_product_mapping_includes_regular_price(): void {
		$mapping = $this->module->map_to_odoo( 'product', [ 'regular_price' => '29.99' ] );
		$this->assertSame( '29.99', $mapping['list_price'] );
	}

	public function test_order_mapping_includes_total(): void {
		$mapping = $this->module->map_to_odoo( 'order', [ 'total' => '150.00' ] );
		$this->assertSame( '150.00', $mapping['amount_total'] );
	}

	public function test_invoice_mapping_includes_state(): void {
		$mapping = $this->module->map_to_odoo( 'invoice', [ '_invoice_state' => 'posted' ] );
		$this->assertSame( 'posted', $mapping['state'] );
	}

	public function test_variant_mapping_includes_sku(): void {
		$mapping = $this->module->map_to_odoo( 'variant', [ 'sku' => 'VAR-01' ] );
		$this->assertSame( 'VAR-01', $mapping['default_code'] );
	}

	public function test_variant_mapping_includes_price(): void {
		$mapping = $this->module->map_to_odoo( 'variant', [ 'regular_price' => '39.99' ] );
		$this->assertSame( '39.99', $mapping['lst_price'] );
	}

	public function test_map_from_odoo_variant(): void {
		$odoo_data = [
			'default_code'  => 'VAR-02',
			'lst_price'     => 25.0,
			'qty_available' => 10,
			'weight'        => 0.5,
			'display_name'  => 'Widget [Red, M]',
		];
		$wp_data = $this->module->map_from_odoo( 'variant', $odoo_data );

		$this->assertSame( 'VAR-02', $wp_data['sku'] );
		$this->assertSame( 25.0, $wp_data['regular_price'] );
		$this->assertSame( 10, $wp_data['stock_quantity'] );
		$this->assertSame( 0.5, $wp_data['weight'] );
		$this->assertSame( 'Widget [Red, M]', $wp_data['display_name'] );
	}

	// ─── Reverse Mapping ───────────────────────────────────

	public function test_map_from_odoo_product(): void {
		$odoo_data = [ 'name' => 'Widget', 'default_code' => 'WDG-01', 'list_price' => 19.99 ];
		$wp_data   = $this->module->map_from_odoo( 'product', $odoo_data );

		$this->assertSame( 'Widget', $wp_data['name'] );
		$this->assertSame( 'WDG-01', $wp_data['sku'] );
		$this->assertSame( 19.99, $wp_data['regular_price'] );
	}

	public function test_map_from_odoo_order(): void {
		$odoo_data = [ 'amount_total' => 250.0, 'state' => 'sale' ];
		$wp_data   = $this->module->map_from_odoo( 'order', $odoo_data );

		$this->assertSame( 250.0, $wp_data['total'] );
		$this->assertSame( 'sale', $wp_data['status'] );
	}

	// ─── Dependency Status ────────────────────────────────

	public function test_dependency_status_available_with_woocommerce(): void {
		// WooCommerce class is defined in test stubs.
		$status = $this->module->get_dependency_status();
		$this->assertTrue( $status['available'] );
	}

	public function test_dependency_status_has_empty_notices(): void {
		$status = $this->module->get_dependency_status();
		$this->assertEmpty( $status['notices'] );
	}

	// ─── Boot without WooCommerce ──────────────────────────

	public function test_boot_does_not_crash_without_woocommerce(): void {
		// WooCommerce class does not exist in test env.
		// boot() should return without error.
		$this->module->boot();
		$this->assertTrue( true ); // No exception thrown.
	}

	// ─── Sync Direction ─────────────────────────────────

	public function test_sync_direction(): void {
		$this->assertSame( 'bidirectional', $this->module->get_sync_direction() );
	}

	// ─── Tax Mapping ──────────────────────────────────────

	public function test_map_to_odoo_applies_tax_mapping(): void {
		// Set settings with tax mapping enabled.
		update_option( 'wp4odoo_module_woocommerce', [
			'sync_taxes'  => true,
			'tax_mapping' => [ 'standard' => 5 ],
		] );

		$wp_data = [
			'line_items' => [
				[ 'name' => 'Widget', 'quantity' => 1, 'total' => '10.00', 'tax_class' => '', 'product_id' => 1 ],
			],
		];

		// Simulate parent map_to_odoo returning order_line with a One2many tuple.
		$mapped = $this->module->map_to_odoo( 'order', $wp_data );

		// The parent map_to_odoo builds order_line. Since we can't easily mock it,
		// test the apply_tax_mapping method directly via reflection.
		$ref    = new \ReflectionMethod( $this->module, 'apply_tax_mapping' );
		$ref->setAccessible( true );

		$input = [
			'order_line' => [
				[ 0, 0, [ 'name' => 'Widget', 'product_uom_qty' => 1, 'price_unit' => 10.0 ] ],
			],
		];

		$result = $ref->invoke( $this->module, $input, $wp_data, [
			'sync_taxes'  => true,
			'tax_mapping' => [ 'standard' => 5 ],
		] );

		$this->assertSame( [ [ 6, 0, [ 5 ] ] ], $result['order_line'][0][2]['tax_id'] );
	}

	public function test_map_to_odoo_skips_tax_when_disabled(): void {
		$ref   = new \ReflectionMethod( $this->module, 'apply_tax_mapping' );
		$ref->setAccessible( true );

		$input = [
			'order_line' => [
				[ 0, 0, [ 'name' => 'Widget' ] ],
			],
		];

		$result = $ref->invoke( $this->module, $input, [ 'line_items' => [ [ 'tax_class' => '' ] ] ], [
			'sync_taxes'  => false,
			'tax_mapping' => [ 'standard' => 5 ],
		] );

		$this->assertArrayNotHasKey( 'tax_id', $result['order_line'][0][2] );
	}

	// ─── Shipping Mapping ─────────────────────────────────

	public function test_map_to_odoo_applies_shipping_mapping(): void {
		$ref   = new \ReflectionMethod( $this->module, 'apply_shipping_mapping' );
		$ref->setAccessible( true );

		$result = $ref->invoke( $this->module, [], [
			'shipping_methods' => [
				[ 'method_id' => 'flat_rate', 'method_title' => 'Flat Rate', 'total' => '5.00' ],
			],
		], [
			'sync_shipping'    => true,
			'shipping_mapping' => [ 'flat_rate' => 12 ],
		] );

		$this->assertSame( 12, $result['carrier_id'] );
	}

	public function test_map_to_odoo_skips_shipping_when_disabled(): void {
		$ref   = new \ReflectionMethod( $this->module, 'apply_shipping_mapping' );
		$ref->setAccessible( true );

		$result = $ref->invoke( $this->module, [], [
			'shipping_methods' => [
				[ 'method_id' => 'flat_rate' ],
			],
		], [
			'sync_shipping'    => false,
			'shipping_mapping' => [ 'flat_rate' => 12 ],
		] );

		$this->assertArrayNotHasKey( 'carrier_id', $result );
	}
}

<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Logger;
use WP4Odoo\Modules\WC_Bundle_BOM_Handler;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WC_Bundle_BOM_Handler.
 *
 * Tests bundle loading, composite loading, BOM formatting,
 * optional component filtering, and type detection.
 */
class WCBundleBomHandlerTest extends TestCase {

	private WC_Bundle_BOM_Handler $handler;

	protected function setUp(): void {
		global $wpdb;
		$wpdb = new \WP_DB_Stub();

		$GLOBALS['_wp_options']    = [];
		$GLOBALS['_wc_bundles']    = [];
		$GLOBALS['_wc_composites'] = [];

		$this->handler = new WC_Bundle_BOM_Handler( new Logger( 'test' ) );
	}

	// ─── load_bundle_or_composite — Bundle ──────────────

	public function test_load_bundle_returns_components(): void {
		$bundle = new \WC_Product_Bundle( 42 );
		$GLOBALS['_wc_products'][42] = $bundle;
		$GLOBALS['_wc_bundles'][42]  = [
			new \WC_Bundled_Item( 10, 2, false ),
			new \WC_Bundled_Item( 20, 3, false ),
		];

		$result = $this->handler->load_bundle_or_composite( 42 );

		$this->assertCount( 2, $result );
		$this->assertSame( 10, $result[0]['wp_product_id'] );
		$this->assertSame( 2, $result[0]['quantity'] );
		$this->assertSame( 20, $result[1]['wp_product_id'] );
		$this->assertSame( 3, $result[1]['quantity'] );
	}

	public function test_load_bundle_filters_optional_items(): void {
		$bundle = new \WC_Product_Bundle( 42 );
		$GLOBALS['_wc_products'][42] = $bundle;
		$GLOBALS['_wc_bundles'][42]  = [
			new \WC_Bundled_Item( 10, 2, false ),
			new \WC_Bundled_Item( 20, 1, true ),  // Optional — excluded.
			new \WC_Bundled_Item( 30, 1, false ),
		];

		$result = $this->handler->load_bundle_or_composite( 42 );

		$this->assertCount( 2, $result );
		$this->assertSame( 10, $result[0]['wp_product_id'] );
		$this->assertSame( 30, $result[1]['wp_product_id'] );
	}

	public function test_load_bundle_empty_for_no_items(): void {
		$bundle = new \WC_Product_Bundle( 42 );
		$GLOBALS['_wc_products'][42] = $bundle;
		$GLOBALS['_wc_bundles'][42]  = [];

		$result = $this->handler->load_bundle_or_composite( 42 );

		$this->assertEmpty( $result );
	}

	public function test_load_bundle_empty_for_nonexistent_product(): void {
		$result = $this->handler->load_bundle_or_composite( 999 );
		$this->assertEmpty( $result );
	}

	public function test_load_bundle_empty_for_simple_product(): void {
		$GLOBALS['_wc_products'][42] = [ 'type' => 'simple', 'name' => 'Widget' ];

		$result = $this->handler->load_bundle_or_composite( 42 );

		$this->assertEmpty( $result );
	}

	public function test_load_bundle_skips_zero_product_id(): void {
		$bundle = new \WC_Product_Bundle( 42 );
		$GLOBALS['_wc_products'][42] = $bundle;
		$GLOBALS['_wc_bundles'][42]  = [
			new \WC_Bundled_Item( 0, 1, false ),
			new \WC_Bundled_Item( 10, 2, false ),
		];

		$result = $this->handler->load_bundle_or_composite( 42 );

		$this->assertCount( 1, $result );
		$this->assertSame( 10, $result[0]['wp_product_id'] );
	}

	// ─── load_bundle_or_composite — Composite ───────────

	public function test_load_composite_returns_components(): void {
		$composite = new \WC_Product_Composite( 42 );
		$GLOBALS['_wc_products'][42]    = $composite;
		$GLOBALS['_wc_composites'][42]  = [
			[
				'query_ids'    => [ 10, 11 ],
				'quantity_min' => 2,
				'optional'     => false,
			],
			[
				'query_ids'    => [ 20 ],
				'quantity_min' => 1,
				'optional'     => false,
			],
		];

		$result = $this->handler->load_bundle_or_composite( 42 );

		$this->assertCount( 2, $result );
		// Takes first product from each slot.
		$this->assertSame( 10, $result[0]['wp_product_id'] );
		$this->assertSame( 2, $result[0]['quantity'] );
		$this->assertSame( 20, $result[1]['wp_product_id'] );
		$this->assertSame( 1, $result[1]['quantity'] );
	}

	public function test_load_composite_filters_optional_slots(): void {
		$composite = new \WC_Product_Composite( 42 );
		$GLOBALS['_wc_products'][42]    = $composite;
		$GLOBALS['_wc_composites'][42]  = [
			[
				'query_ids'    => [ 10 ],
				'quantity_min' => 1,
				'optional'     => false,
			],
			[
				'query_ids'    => [ 20 ],
				'quantity_min' => 1,
				'optional'     => true,  // Optional — excluded.
			],
		];

		$result = $this->handler->load_bundle_or_composite( 42 );

		$this->assertCount( 1, $result );
		$this->assertSame( 10, $result[0]['wp_product_id'] );
	}

	public function test_load_composite_skips_empty_query_ids(): void {
		$composite = new \WC_Product_Composite( 42 );
		$GLOBALS['_wc_products'][42]    = $composite;
		$GLOBALS['_wc_composites'][42]  = [
			[
				'query_ids'    => [],
				'quantity_min' => 1,
				'optional'     => false,
			],
		];

		$result = $this->handler->load_bundle_or_composite( 42 );

		$this->assertEmpty( $result );
	}

	public function test_load_composite_defaults_quantity_to_one(): void {
		$composite = new \WC_Product_Composite( 42 );
		$GLOBALS['_wc_products'][42]    = $composite;
		$GLOBALS['_wc_composites'][42]  = [
			[
				'query_ids' => [ 10 ],
				'optional'  => false,
				// quantity_min missing — defaults to 1.
			],
		];

		$result = $this->handler->load_bundle_or_composite( 42 );

		$this->assertCount( 1, $result );
		$this->assertSame( 1, $result[0]['quantity'] );
	}

	// ─── format_bom ─────────────────────────────────────

	public function test_format_bom_structure(): void {
		$lines = [
			[ 'odoo_id' => 5, 'quantity' => 2 ],
			[ 'odoo_id' => 8, 'quantity' => 1 ],
		];

		$result = $this->handler->format_bom( 100, $lines, 'phantom', 42 );

		$this->assertSame( 100, $result['product_tmpl_id'] );
		$this->assertSame( 'phantom', $result['type'] );
		$this->assertSame( 1.0, $result['product_qty'] );
		$this->assertSame( 'WC-42', $result['code'] );
	}

	public function test_format_bom_line_ids_starts_with_clear(): void {
		$lines = [
			[ 'odoo_id' => 5, 'quantity' => 2 ],
		];

		$result = $this->handler->format_bom( 100, $lines, 'phantom', 42 );

		// First tuple is the clear command [5, 0, 0].
		$this->assertSame( [ 5, 0, 0 ], $result['bom_line_ids'][0] );
	}

	public function test_format_bom_line_ids_creates_component_tuples(): void {
		$lines = [
			[ 'odoo_id' => 5, 'quantity' => 2 ],
			[ 'odoo_id' => 8, 'quantity' => 3 ],
		];

		$result = $this->handler->format_bom( 100, $lines, 'normal', 42 );

		// [clear] + 2 creates = 3 tuples.
		$this->assertCount( 3, $result['bom_line_ids'] );

		// First create: [0, 0, {product_id: 5, product_qty: 2.0}].
		$this->assertSame( 0, $result['bom_line_ids'][1][0] );
		$this->assertSame( 0, $result['bom_line_ids'][1][1] );
		$this->assertSame( 5, $result['bom_line_ids'][1][2]['product_id'] );
		$this->assertSame( 2.0, $result['bom_line_ids'][1][2]['product_qty'] );

		// Second create.
		$this->assertSame( 8, $result['bom_line_ids'][2][2]['product_id'] );
		$this->assertSame( 3.0, $result['bom_line_ids'][2][2]['product_qty'] );
	}

	public function test_format_bom_uses_normal_type(): void {
		$result = $this->handler->format_bom( 100, [], 'normal', 42 );

		$this->assertSame( 'normal', $result['type'] );
	}

	public function test_format_bom_empty_lines_only_has_clear(): void {
		$result = $this->handler->format_bom( 100, [], 'phantom', 42 );

		$this->assertCount( 1, $result['bom_line_ids'] );
		$this->assertSame( [ 5, 0, 0 ], $result['bom_line_ids'][0] );
	}

	// ─── is_bundle_or_composite ─────────────────────────

	public function test_is_bundle_returns_true_for_bundle(): void {
		$bundle = new \WC_Product_Bundle( 42 );
		$GLOBALS['_wc_products'][42] = $bundle;

		$this->assertTrue( $this->handler->is_bundle_or_composite( 42 ) );
	}

	public function test_is_composite_returns_true(): void {
		$composite = new \WC_Product_Composite( 42 );
		$GLOBALS['_wc_products'][42] = $composite;

		$this->assertTrue( $this->handler->is_bundle_or_composite( 42 ) );
	}

	public function test_is_bundle_returns_false_for_simple(): void {
		$GLOBALS['_wc_products'][42] = [ 'type' => 'simple', 'name' => 'Widget' ];

		$this->assertFalse( $this->handler->is_bundle_or_composite( 42 ) );
	}

	public function test_is_bundle_returns_false_for_nonexistent(): void {
		$this->assertFalse( $this->handler->is_bundle_or_composite( 999 ) );
	}
}

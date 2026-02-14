<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Modules\WC_Bundle_BOM_Module;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WC_Bundle_BOM_Module.
 *
 * Tests module configuration, entity type declarations, default settings,
 * settings fields, dependency status, sync direction, and push overrides.
 */
class WCBundleBomModuleTest extends TestCase {

	private WC_Bundle_BOM_Module $module;

	protected function setUp(): void {
		global $wpdb;
		$wpdb = new \WP_DB_Stub();

		$GLOBALS['_wp_options']    = [];
		$GLOBALS['_wp_transients'] = [];
		$GLOBALS['_wp_posts']      = [];
		$GLOBALS['_wp_post_meta']  = [];
		$GLOBALS['_wc_bundles']    = [];
		$GLOBALS['_wc_composites'] = [];

		$this->module = new WC_Bundle_BOM_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );
	}

	// ─── Module Identity ──────────────────────────────────

	public function test_module_id_is_wc_bundle_bom(): void {
		$this->assertSame( 'wc_bundle_bom', $this->module->get_id() );
	}

	public function test_module_name(): void {
		$this->assertSame( 'WC Product Bundles BOM', $this->module->get_name() );
	}

	public function test_sync_direction_is_wp_to_odoo(): void {
		$this->assertSame( 'wp_to_odoo', $this->module->get_sync_direction() );
	}

	public function test_no_exclusive_group(): void {
		$this->assertSame( '', $this->module->get_exclusive_group() );
	}

	// ─── Odoo Models ──────────────────────────────────────

	public function test_declares_bom_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'mrp.bom', $models['bom'] );
	}

	public function test_declares_exactly_one_entity_type(): void {
		$models = $this->module->get_odoo_models();
		$this->assertCount( 1, $models );
	}

	// ─── Default Settings ─────────────────────────────────

	public function test_default_settings_has_sync_bundles(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['sync_bundles'] );
	}

	public function test_default_settings_has_bom_type_phantom(): void {
		$settings = $this->module->get_default_settings();
		$this->assertSame( 'phantom', $settings['bom_type'] );
	}

	public function test_default_settings_has_exactly_two_keys(): void {
		$settings = $this->module->get_default_settings();
		$this->assertCount( 2, $settings );
	}

	// ─── Settings Fields ──────────────────────────────────

	public function test_settings_fields_exposes_sync_bundles(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( 'sync_bundles', $fields );
		$this->assertSame( 'checkbox', $fields['sync_bundles']['type'] );
	}

	public function test_settings_fields_exposes_bom_type(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( 'bom_type', $fields );
		$this->assertSame( 'select', $fields['bom_type']['type'] );
	}

	public function test_settings_fields_bom_type_has_two_options(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertCount( 2, $fields['bom_type']['options'] );
		$this->assertArrayHasKey( 'phantom', $fields['bom_type']['options'] );
		$this->assertArrayHasKey( 'normal', $fields['bom_type']['options'] );
	}

	public function test_settings_fields_has_exactly_two_fields(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertCount( 2, $fields );
	}

	// ─── Mapping (via map_to_odoo) ────────────────────────

	public function test_map_to_odoo_maps_product_tmpl_id(): void {
		$mapped = $this->module->map_to_odoo( 'bom', [ 'product_tmpl_id' => 100 ] );
		$this->assertSame( 100, $mapped['product_tmpl_id'] );
	}

	public function test_map_to_odoo_maps_type(): void {
		$mapped = $this->module->map_to_odoo( 'bom', [ 'type' => 'phantom' ] );
		$this->assertSame( 'phantom', $mapped['type'] );
	}

	public function test_map_to_odoo_maps_product_qty(): void {
		$mapped = $this->module->map_to_odoo( 'bom', [ 'product_qty' => 1.0 ] );
		$this->assertSame( 1.0, $mapped['product_qty'] );
	}

	public function test_map_to_odoo_maps_bom_line_ids(): void {
		$lines  = [ [ 5, 0, 0 ], [ 0, 0, [ 'product_id' => 5, 'product_qty' => 2.0 ] ] ];
		$mapped = $this->module->map_to_odoo( 'bom', [ 'bom_line_ids' => $lines ] );
		$this->assertSame( $lines, $mapped['bom_line_ids'] );
	}

	public function test_map_to_odoo_maps_code(): void {
		$mapped = $this->module->map_to_odoo( 'bom', [ 'code' => 'WC-42' ] );
		$this->assertSame( 'WC-42', $mapped['code'] );
	}

	public function test_map_to_odoo_has_five_mapped_fields(): void {
		$data = [
			'product_tmpl_id' => 100,
			'type'            => 'phantom',
			'product_qty'     => 1.0,
			'bom_line_ids'    => [ [ 5, 0, 0 ] ],
			'code'            => 'WC-42',
		];
		$mapped = $this->module->map_to_odoo( 'bom', $data );
		$this->assertCount( 5, $mapped );
	}

	// ─── Dependency Status ────────────────────────────────

	public function test_dependency_available_when_bundles_class_exists(): void {
		$status = $this->module->get_dependency_status();
		// WC_Bundles is defined in test stubs.
		$this->assertTrue( $status['available'] );
	}

	// ─── Push Override ────────────────────────────────────

	public function test_push_returns_success_for_delete_action(): void {
		$result = $this->module->push_to_odoo( 'bom', 'delete', 42, 0 );
		$this->assertTrue( $result->succeeded() );
	}

	// ─── map_to_odoo passthrough ──────────────────────────

	public function test_map_to_odoo_preserves_bom_line_ids(): void {
		$data = [
			'product_tmpl_id' => 10,
			'type'            => 'phantom',
			'product_qty'     => 1.0,
			'bom_line_ids'    => [ [ 5, 0, 0 ], [ 0, 0, [ 'product_id' => 5, 'product_qty' => 2.0 ] ] ],
			'code'            => 'WC-42',
		];

		$mapped = $this->module->map_to_odoo( 'bom', $data );

		$this->assertSame( $data['bom_line_ids'], $mapped['bom_line_ids'] );
		$this->assertSame( 'phantom', $mapped['type'] );
		$this->assertSame( 10, $mapped['product_tmpl_id'] );
		$this->assertSame( 'WC-42', $mapped['code'] );
	}

	// ─── Handler accessor ─────────────────────────────────

	public function test_get_handler_returns_handler_instance(): void {
		$handler = $this->module->get_handler();
		$this->assertInstanceOf( \WP4Odoo\Modules\WC_Bundle_BOM_Handler::class, $handler );
	}
}

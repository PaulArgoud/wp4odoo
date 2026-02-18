<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Modules\Jeero_Configurator_Module;
use WP4Odoo\Modules\Jeero_Configurator_Handler;
use WP4Odoo\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Jeero_Configurator_Module, Jeero_Configurator_Handler,
 * and Jeero_Configurator_Hooks.
 *
 * Tests module configuration, handler data loading/formatting, hook guard
 * logic, push overrides, dedup domains, and mapping identity.
 */
class JeeroConfiguratorModuleTest extends TestCase {

	private Jeero_Configurator_Module $module;
	private Jeero_Configurator_Handler $handler;
	private \WP_DB_Stub $wpdb;

	protected function setUp(): void {
		global $wpdb;
		$this->wpdb = new \WP_DB_Stub();
		$wpdb       = $this->wpdb;

		$GLOBALS['_wp_options']    = [];
		$GLOBALS['_wp_transients'] = [];
		$GLOBALS['_wp_posts']      = [];
		$GLOBALS['_wp_post_meta']  = [];
		$GLOBALS['_wc_bundles']    = [];
		$GLOBALS['_wc_composites'] = [];
		$GLOBALS['_jeero_configs'] = [];

		$this->module  = new Jeero_Configurator_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );
		$this->handler = new Jeero_Configurator_Handler( new Logger( 'jeero_configurator', wp4odoo_test_settings() ) );
	}

	// ─── Module Identity ──────────────────────────────────

	public function test_module_id_is_jeero_configurator(): void {
		$this->assertSame( 'jeero_configurator', $this->module->get_id() );
	}

	public function test_module_name(): void {
		$this->assertSame( 'Jeero Configurator', $this->module->get_name() );
	}

	public function test_no_exclusive_group(): void {
		$this->assertSame( '', $this->module->get_exclusive_group() );
	}

	public function test_sync_direction_is_wp_to_odoo(): void {
		$this->assertSame( 'wp_to_odoo', $this->module->get_sync_direction() );
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

	// ─── Required Modules ─────────────────────────────────

	public function test_requires_woocommerce_module(): void {
		$this->assertSame( [ 'woocommerce' ], $this->module->get_required_modules() );
	}

	// ─── Default Settings ─────────────────────────────────

	public function test_default_settings_has_sync_configurables(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['sync_configurables'] );
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

	public function test_settings_fields_exposes_sync_configurables(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( 'sync_configurables', $fields );
		$this->assertSame( 'checkbox', $fields['sync_configurables']['type'] );
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

	// ─── Dependency Status ────────────────────────────────

	public function test_dependency_available_when_jeero_defined(): void {
		$status = $this->module->get_dependency_status();
		// JEERO_VERSION is defined in test stubs.
		$this->assertTrue( $status['available'] );
	}

	// ─── Boot ─────────────────────────────────────────────

	public function test_boot_does_not_crash(): void {
		$this->module->boot();
		$this->assertTrue( true );
	}

	// ─── Handler: load_configuration_rules ────────────────

	public function test_load_rules_returns_components_for_product_with_rules(): void {
		$GLOBALS['_wp_post_meta'][42]['_jeero_configuration_rules'] = [
			[ 'product_id' => 10, 'quantity' => 2 ],
			[ 'product_id' => 20, 'quantity' => 3 ],
		];

		$rules = $this->handler->load_configuration_rules( 42 );

		$this->assertCount( 2, $rules );
		$this->assertSame( 10, $rules[0]['wp_product_id'] );
		$this->assertSame( 2, $rules[0]['quantity'] );
		$this->assertSame( 20, $rules[1]['wp_product_id'] );
		$this->assertSame( 3, $rules[1]['quantity'] );
	}

	public function test_load_rules_returns_empty_for_product_without_rules(): void {
		$rules = $this->handler->load_configuration_rules( 42 );
		$this->assertSame( [], $rules );
	}

	public function test_load_rules_returns_empty_for_zero_product_id(): void {
		$rules = $this->handler->load_configuration_rules( 0 );
		$this->assertSame( [], $rules );
	}

	public function test_load_rules_skips_rules_with_zero_product_id(): void {
		$GLOBALS['_wp_post_meta'][42]['_jeero_configuration_rules'] = [
			[ 'product_id' => 0, 'quantity' => 1 ],
			[ 'product_id' => 10, 'quantity' => 2 ],
		];

		$rules = $this->handler->load_configuration_rules( 42 );

		$this->assertCount( 1, $rules );
		$this->assertSame( 10, $rules[0]['wp_product_id'] );
	}

	public function test_load_rules_defaults_quantity_to_one_when_missing(): void {
		$GLOBALS['_wp_post_meta'][42]['_jeero_configuration_rules'] = [
			[ 'product_id' => 10 ],
		];

		$rules = $this->handler->load_configuration_rules( 42 );

		$this->assertCount( 1, $rules );
		$this->assertSame( 1, $rules[0]['quantity'] );
	}

	// ─── Handler: format_bom ──────────────────────────────

	public function test_format_bom_product_tmpl_id_correct(): void {
		$bom = $this->handler->format_bom( 100, [ [ 'odoo_id' => 5, 'quantity' => 2 ] ], 'phantom', 42 );
		$this->assertSame( 100, $bom['product_tmpl_id'] );
	}

	public function test_format_bom_type_correct(): void {
		$bom = $this->handler->format_bom( 100, [ [ 'odoo_id' => 5, 'quantity' => 2 ] ], 'normal', 42 );
		$this->assertSame( 'normal', $bom['type'] );
	}

	public function test_format_bom_product_qty_is_one(): void {
		$bom = $this->handler->format_bom( 100, [ [ 'odoo_id' => 5, 'quantity' => 2 ] ], 'phantom', 42 );
		$this->assertSame( 1.0, $bom['product_qty'] );
	}

	public function test_format_bom_code_starts_with_jeero_prefix(): void {
		$bom = $this->handler->format_bom( 100, [ [ 'odoo_id' => 5, 'quantity' => 2 ] ], 'phantom', 42 );
		$this->assertStringStartsWith( 'JEERO-', $bom['code'] );
		$this->assertSame( 'JEERO-42', $bom['code'] );
	}

	public function test_format_bom_lines_start_with_clear_tuple(): void {
		$bom = $this->handler->format_bom( 100, [ [ 'odoo_id' => 5, 'quantity' => 2 ] ], 'phantom', 42 );
		$this->assertSame( [ 5, 0, 0 ], $bom['bom_line_ids'][0] );
	}

	public function test_format_bom_lines_has_correct_count(): void {
		$components = [
			[ 'odoo_id' => 5, 'quantity' => 2 ],
			[ 'odoo_id' => 8, 'quantity' => 1 ],
		];
		$bom = $this->handler->format_bom( 100, $components, 'phantom', 42 );
		// 1 clear tuple + 2 component lines.
		$this->assertCount( 3, $bom['bom_line_ids'] );
	}

	public function test_format_bom_each_line_has_correct_product_and_qty(): void {
		$components = [
			[ 'odoo_id' => 5, 'quantity' => 2 ],
			[ 'odoo_id' => 8, 'quantity' => 3 ],
		];
		$bom = $this->handler->format_bom( 100, $components, 'phantom', 42 );

		// First component line (index 1, after clear tuple).
		$this->assertSame( 0, $bom['bom_line_ids'][1][0] );
		$this->assertSame( 0, $bom['bom_line_ids'][1][1] );
		$this->assertSame( 5, $bom['bom_line_ids'][1][2]['product_id'] );
		$this->assertSame( 2.0, $bom['bom_line_ids'][1][2]['product_qty'] );

		// Second component line (index 2).
		$this->assertSame( 0, $bom['bom_line_ids'][2][0] );
		$this->assertSame( 0, $bom['bom_line_ids'][2][1] );
		$this->assertSame( 8, $bom['bom_line_ids'][2][2]['product_id'] );
		$this->assertSame( 3.0, $bom['bom_line_ids'][2][2]['product_qty'] );
	}

	// ─── Handler: is_configurable_product ─────────────────

	public function test_is_configurable_true_when_product_has_rules(): void {
		$GLOBALS['_wp_post_meta'][42]['_jeero_configuration_rules'] = [
			[ 'product_id' => 10, 'quantity' => 1 ],
		];

		$this->assertTrue( $this->handler->is_configurable_product( 42 ) );
	}

	public function test_is_configurable_false_when_product_has_no_rules(): void {
		$this->assertFalse( $this->handler->is_configurable_product( 42 ) );
	}

	public function test_is_configurable_false_for_zero_id(): void {
		$this->assertFalse( $this->handler->is_configurable_product( 0 ) );
	}

	// ─── Hooks: on_configurable_save ──────────────────────

	public function test_on_configurable_save_enqueues_push_when_configurable_and_sync_enabled(): void {
		$GLOBALS['_wp_options']['wp4odoo_module_jeero_configurator_settings'] = [
			'sync_configurables' => true,
			'bom_type'           => 'phantom',
		];

		$GLOBALS['_wp_posts'][42] = (object) [
			'ID'        => 42,
			'post_type' => 'product',
		];

		$GLOBALS['_wp_post_meta'][42]['_jeero_configuration_rules'] = [
			[ 'product_id' => 10, 'quantity' => 1 ],
		];

		$this->module->on_configurable_save( 42 );

		$this->assertQueueContains( 'jeero_configurator', 'bom', 'create', 42 );
	}

	public function test_on_configurable_save_skips_when_sync_disabled(): void {
		$GLOBALS['_wp_options']['wp4odoo_module_jeero_configurator_settings'] = [
			'sync_configurables' => false,
			'bom_type'           => 'phantom',
		];

		$GLOBALS['_wp_posts'][42] = (object) [
			'ID'        => 42,
			'post_type' => 'product',
		];

		$GLOBALS['_wp_post_meta'][42]['_jeero_configuration_rules'] = [
			[ 'product_id' => 10, 'quantity' => 1 ],
		];

		$this->module->on_configurable_save( 42 );

		$this->assertQueueEmpty();
	}

	public function test_on_configurable_save_skips_non_product_post_type(): void {
		$GLOBALS['_wp_options']['wp4odoo_module_jeero_configurator_settings'] = [
			'sync_configurables' => true,
			'bom_type'           => 'phantom',
		];

		$GLOBALS['_wp_posts'][42] = (object) [
			'ID'        => 42,
			'post_type' => 'post',
		];

		$this->module->on_configurable_save( 42 );

		$this->assertQueueEmpty();
	}

	public function test_on_configurable_save_skips_non_configurable_product(): void {
		$GLOBALS['_wp_options']['wp4odoo_module_jeero_configurator_settings'] = [
			'sync_configurables' => true,
			'bom_type'           => 'phantom',
		];

		$GLOBALS['_wp_posts'][42] = (object) [
			'ID'        => 42,
			'post_type' => 'product',
		];

		// No configuration rules in post meta.

		$this->module->on_configurable_save( 42 );

		$this->assertQueueEmpty();
	}

	// ─── Push: bom ────────────────────────────────────────

	public function test_push_skips_when_mrp_bom_not_available(): void {
		// Set transient to indicate model not available.
		$GLOBALS['_wp_transients']['wp4odoo_has_mrp_bom'] = 0;

		$result = $this->module->push_to_odoo( 'bom', 'create', 42 );

		$this->assertTrue( $result->succeeded() );
	}

	public function test_push_returns_transient_failure_when_components_not_synced(): void {
		// Set transient to indicate model is available.
		$GLOBALS['_wp_transients']['wp4odoo_has_mrp_bom'] = 1;

		$GLOBALS['_wp_post_meta'][42]['_jeero_configuration_rules'] = [
			[ 'product_id' => 10, 'quantity' => 1 ],
		];

		// No entity map entries — components are not synced.
		$result = $this->module->push_to_odoo( 'bom', 'create', 42 );

		$this->assertFalse( $result->succeeded() );
	}

	// ─── Dedup Domains ────────────────────────────────────

	public function test_dedup_bom_by_code(): void {
		$method = new \ReflectionMethod( $this->module, 'get_dedup_domain' );

		$domain = $method->invoke( $this->module, 'bom', [ 'code' => 'JEERO-42' ] );

		$this->assertSame( [ [ 'code', '=', 'JEERO-42' ] ], $domain );
	}

	public function test_dedup_bom_by_product_tmpl_id_when_no_code(): void {
		$method = new \ReflectionMethod( $this->module, 'get_dedup_domain' );

		$domain = $method->invoke( $this->module, 'bom', [ 'product_tmpl_id' => 100 ] );

		$this->assertSame( [ [ 'product_tmpl_id', '=', 100 ] ], $domain );
	}

	public function test_dedup_empty_when_neither_code_nor_product_tmpl_id(): void {
		$method = new \ReflectionMethod( $this->module, 'get_dedup_domain' );

		$domain = $method->invoke( $this->module, 'bom', [] );

		$this->assertSame( [], $domain );
	}

	// ─── Mapping ──────────────────────────────────────────

	public function test_map_to_odoo_passes_through_identity_fields(): void {
		$data = [
			'product_tmpl_id' => 100,
			'type'            => 'phantom',
			'product_qty'     => 1.0,
			'bom_line_ids'    => [ [ 5, 0, 0 ], [ 0, 0, [ 'product_id' => 5, 'product_qty' => 2.0 ] ] ],
			'code'            => 'JEERO-42',
		];

		$mapped = $this->module->map_to_odoo( 'bom', $data );

		$this->assertSame( 100, $mapped['product_tmpl_id'] );
		$this->assertSame( 'phantom', $mapped['type'] );
		$this->assertSame( 1.0, $mapped['product_qty'] );
		$this->assertSame( $data['bom_line_ids'], $mapped['bom_line_ids'] );
		$this->assertSame( 'JEERO-42', $mapped['code'] );
		$this->assertCount( 5, $mapped );
	}

	// ─── Handler Accessor ─────────────────────────────────

	public function test_get_handler_returns_handler_instance(): void {
		$handler = $this->module->get_handler();
		$this->assertInstanceOf( Jeero_Configurator_Handler::class, $handler );
	}

	// ─── Helpers ──────────────────────────────────────────

	private function assertQueueContains( string $module, string $entity, string $action, int $wp_id ): void {
		$inserts = array_filter( $this->wpdb->calls, fn( $c ) => 'insert' === $c['method'] );
		foreach ( $inserts as $call ) {
			$data = $call['args'][1] ?? [];
			if ( ( $data['module'] ?? '' ) === $module
				&& ( $data['entity_type'] ?? '' ) === $entity
				&& ( $data['action'] ?? '' ) === $action
				&& ( $data['wp_id'] ?? 0 ) === $wp_id ) {
				$this->assertTrue( true );
				return;
			}
		}
		$this->fail( "Queue does not contain [{$module}, {$entity}, {$action}, {$wp_id}]" );
	}

	private function assertQueueEmpty(): void {
		$inserts = array_filter(
			$this->wpdb->calls,
			fn( $c ) => 'insert' === $c['method'] && str_contains( $c['args'][0] ?? '', 'sync_queue' )
		);
		$this->assertEmpty( $inserts, 'Queue should be empty.' );
	}
}

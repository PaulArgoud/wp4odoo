<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Modules\PMPro_Module;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PMPro_Module.
 *
 * Tests module configuration, entity type declarations, field mappings,
 * and default settings.
 */
class PMProModuleTest extends TestCase {

	private PMPro_Module $module;
	private \WP_DB_Stub $wpdb;

	protected function setUp(): void {
		global $wpdb;
		$this->wpdb = new \WP_DB_Stub();
		$wpdb       = $this->wpdb;

		$GLOBALS['_wp_options']   = [];
		$GLOBALS['_pmpro_levels'] = [];
		$GLOBALS['_pmpro_orders'] = [];

		$this->module = new PMPro_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );
	}

	// ─── Module Identity ───────────────────────────────────

	public function test_module_id_is_pmpro(): void {
		$this->assertSame( 'pmpro', $this->module->get_id() );
	}

	public function test_module_name_is_paid_memberships_pro(): void {
		$this->assertSame( 'Paid Memberships Pro', $this->module->get_name() );
	}

	public function test_exclusive_group(): void {
		$this->assertSame( 'memberships', $this->module->get_exclusive_group() );
	}

	public function test_exclusive_priority(): void {
		$this->assertSame( 15, $this->module->get_exclusive_priority() );
	}

	public function test_sync_direction_is_wp_to_odoo(): void {
		$this->assertSame( 'wp_to_odoo', $this->module->get_sync_direction() );
	}

	// ─── Odoo Models ───────────────────────────────────────

	public function test_declares_level_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'product.product', $models['level'] );
	}

	public function test_declares_order_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'account.move', $models['order'] );
	}

	public function test_declares_membership_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'membership.membership_line', $models['membership'] );
	}

	public function test_declares_exactly_three_entity_types(): void {
		$models = $this->module->get_odoo_models();
		$this->assertCount( 3, $models );
	}

	// ─── Default Settings ──────────────────────────────────

	public function test_default_settings_has_sync_levels(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['sync_levels'] );
	}

	public function test_default_settings_has_sync_orders(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['sync_orders'] );
	}

	public function test_default_settings_has_sync_memberships(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['sync_memberships'] );
	}

	public function test_default_settings_has_auto_post_invoices(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['auto_post_invoices'] );
	}

	public function test_default_settings_has_exactly_four_keys(): void {
		$settings = $this->module->get_default_settings();
		$this->assertCount( 4, $settings );
	}

	// ─── Settings Fields ───────────────────────────────────

	public function test_settings_fields_exposes_sync_levels(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( 'sync_levels', $fields );
		$this->assertSame( 'checkbox', $fields['sync_levels']['type'] );
	}

	public function test_settings_fields_exposes_sync_orders(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( 'sync_orders', $fields );
		$this->assertSame( 'checkbox', $fields['sync_orders']['type'] );
	}

	public function test_settings_fields_exposes_sync_memberships(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( 'sync_memberships', $fields );
		$this->assertSame( 'checkbox', $fields['sync_memberships']['type'] );
	}

	public function test_settings_fields_exposes_auto_post_invoices(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( 'auto_post_invoices', $fields );
		$this->assertSame( 'checkbox', $fields['auto_post_invoices']['type'] );
	}

	// ─── Field Mappings: Level ─────────────────────────────

	public function test_level_mapping_includes_name(): void {
		$odoo = $this->module->map_to_odoo( 'level', [ 'level_name' => 'Gold' ] );
		$this->assertSame( 'Gold', $odoo['name'] );
	}

	public function test_level_mapping_includes_membership_flag(): void {
		$odoo = $this->module->map_to_odoo( 'level', [ 'membership' => true ] );
		$this->assertTrue( $odoo['membership'] );
	}

	public function test_level_mapping_includes_list_price(): void {
		$odoo = $this->module->map_to_odoo( 'level', [ 'list_price' => 29.99 ] );
		$this->assertSame( 29.99, $odoo['list_price'] );
	}

	public function test_level_mapping_includes_type(): void {
		$odoo = $this->module->map_to_odoo( 'level', [ 'type' => 'service' ] );
		$this->assertSame( 'service', $odoo['type'] );
	}

	// ─── Field Mappings: Order ─────────────────────────────

	public function test_order_mapping_includes_move_type(): void {
		$odoo = $this->module->map_to_odoo( 'order', [ 'move_type' => 'out_invoice' ] );
		$this->assertSame( 'out_invoice', $odoo['move_type'] );
	}

	public function test_order_mapping_includes_partner_id(): void {
		$odoo = $this->module->map_to_odoo( 'order', [ 'partner_id' => 42 ] );
		$this->assertSame( 42, $odoo['partner_id'] );
	}

	public function test_order_mapping_includes_invoice_line_ids(): void {
		$lines = [ [ 0, 0, [ 'product_id' => 5, 'quantity' => 1, 'price_unit' => 29.99 ] ] ];
		$odoo  = $this->module->map_to_odoo( 'order', [ 'invoice_line_ids' => $lines ] );
		$this->assertSame( $lines, $odoo['invoice_line_ids'] );
	}

	public function test_order_mapping_includes_ref(): void {
		$odoo = $this->module->map_to_odoo( 'order', [ 'ref' => 'PMPRO-123' ] );
		$this->assertSame( 'PMPRO-123', $odoo['ref'] );
	}

	// ─── Field Mappings: Membership ────────────────────────

	public function test_membership_mapping_includes_partner_id(): void {
		$odoo = $this->module->map_to_odoo( 'membership', [ 'partner_id' => 42 ] );
		$this->assertSame( 42, $odoo['partner_id'] );
	}

	public function test_membership_mapping_includes_membership_id(): void {
		$odoo = $this->module->map_to_odoo( 'membership', [ 'membership_id' => 5 ] );
		$this->assertSame( 5, $odoo['membership_id'] );
	}

	public function test_membership_mapping_includes_state(): void {
		$odoo = $this->module->map_to_odoo( 'membership', [ 'state' => 'paid' ] );
		$this->assertSame( 'paid', $odoo['state'] );
	}

	public function test_membership_mapping_includes_dates(): void {
		$odoo = $this->module->map_to_odoo( 'membership', [
			'date_from' => '2026-01-01',
			'date_to'   => '2027-01-01',
		] );
		$this->assertSame( '2026-01-01', $odoo['date_from'] );
		$this->assertSame( '2027-01-01', $odoo['date_to'] );
	}

	public function test_membership_mapping_includes_member_price(): void {
		$odoo = $this->module->map_to_odoo( 'membership', [ 'member_price' => 29.99 ] );
		$this->assertSame( 29.99, $odoo['member_price'] );
	}

	// ─── Dependency Status ─────────────────────────────────

	public function test_dependency_unavailable_without_pmpro(): void {
		// PMPRO_VERSION is not defined in our test bootstrap.
		$status = $this->module->get_dependency_status();
		$this->assertFalse( $status['available'] );
	}

	public function test_dependency_has_warning_without_pmpro(): void {
		$status = $this->module->get_dependency_status();
		$this->assertNotEmpty( $status['notices'] );
		$this->assertSame( 'warning', $status['notices'][0]['type'] );
	}

	// ─── Boot Guard ────────────────────────────────────────

	public function test_boot_does_not_crash_without_pmpro(): void {
		$this->module->boot();
		$this->assertTrue( true ); // No exception thrown.
	}
}

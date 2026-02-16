<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Module_Base;
use PHPUnit\Framework\TestCase;

/**
 * @since 3.4.0
 */
abstract class MembershipModuleTestBase extends TestCase {

	protected Module_Base $module;

	abstract protected function get_module_id(): string;

	abstract protected function get_module_name(): string;

	abstract protected function get_exclusive_priority(): int;

	abstract protected function get_level_entity(): string;

	abstract protected function get_order_entity(): string;

	abstract protected function get_membership_entity(): string;

	abstract protected function get_level_name_field(): string;

	abstract protected function get_ref_prefix(): string;

	abstract protected function get_sync_level_key(): string;

	abstract protected function get_sync_order_key(): string;

	abstract protected function get_sync_membership_key(): string;

	// ─── Module Identity ───────────────────────────────────

	public function test_module_id(): void {
		$this->assertSame( $this->get_module_id(), $this->module->get_id() );
	}

	public function test_module_name(): void {
		$this->assertSame( $this->get_module_name(), $this->module->get_name() );
	}

	public function test_exclusive_group(): void {
		$this->assertSame( 'memberships', $this->module->get_exclusive_group() );
	}

	public function test_exclusive_priority(): void {
		$this->assertSame( $this->get_exclusive_priority(), $this->module->get_exclusive_priority() );
	}

	public function test_sync_direction_is_wp_to_odoo(): void {
		$this->assertSame( 'wp_to_odoo', $this->module->get_sync_direction() );
	}

	// ─── Odoo Models ───────────────────────────────────────

	public function test_declares_level_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'product.product', $models[ $this->get_level_entity() ] );
	}

	public function test_declares_order_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'account.move', $models[ $this->get_order_entity() ] );
	}

	public function test_declares_membership_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'membership.membership_line', $models[ $this->get_membership_entity() ] );
	}

	public function test_declares_exactly_three_entity_types(): void {
		$models = $this->module->get_odoo_models();
		$this->assertCount( 3, $models );
	}

	// ─── Default Settings ──────────────────────────────────

	public function test_default_settings_has_sync_levels(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings[ $this->get_sync_level_key() ] );
	}

	public function test_default_settings_has_sync_orders(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings[ $this->get_sync_order_key() ] );
	}

	public function test_default_settings_has_sync_memberships(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings[ $this->get_sync_membership_key() ] );
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
		$this->assertArrayHasKey( $this->get_sync_level_key(), $fields );
		$this->assertSame( 'checkbox', $fields[ $this->get_sync_level_key() ]['type'] );
	}

	public function test_settings_fields_exposes_sync_orders(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( $this->get_sync_order_key(), $fields );
		$this->assertSame( 'checkbox', $fields[ $this->get_sync_order_key() ]['type'] );
	}

	public function test_settings_fields_exposes_sync_memberships(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( $this->get_sync_membership_key(), $fields );
		$this->assertSame( 'checkbox', $fields[ $this->get_sync_membership_key() ]['type'] );
	}

	public function test_settings_fields_exposes_auto_post_invoices(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( 'auto_post_invoices', $fields );
		$this->assertSame( 'checkbox', $fields['auto_post_invoices']['type'] );
	}

	// ─── Field Mappings: Level ─────────────────────────────

	public function test_level_mapping_includes_name(): void {
		$odoo = $this->module->map_to_odoo( $this->get_level_entity(), [ $this->get_level_name_field() => 'Gold' ] );
		$this->assertSame( 'Gold', $odoo['name'] );
	}

	public function test_level_mapping_includes_membership_flag(): void {
		$odoo = $this->module->map_to_odoo( $this->get_level_entity(), [ 'membership' => true ] );
		$this->assertTrue( $odoo['membership'] );
	}

	public function test_level_mapping_includes_list_price(): void {
		$odoo = $this->module->map_to_odoo( $this->get_level_entity(), [ 'list_price' => 29.99 ] );
		$this->assertSame( 29.99, $odoo['list_price'] );
	}

	public function test_level_mapping_includes_type(): void {
		$odoo = $this->module->map_to_odoo( $this->get_level_entity(), [ 'type' => 'service' ] );
		$this->assertSame( 'service', $odoo['type'] );
	}

	// ─── Field Mappings: Order ─────────────────────────────

	public function test_order_mapping_includes_move_type(): void {
		$odoo = $this->module->map_to_odoo( $this->get_order_entity(), [ 'move_type' => 'out_invoice' ] );
		$this->assertSame( 'out_invoice', $odoo['move_type'] );
	}

	public function test_order_mapping_includes_partner_id(): void {
		$odoo = $this->module->map_to_odoo( $this->get_order_entity(), [ 'partner_id' => 42 ] );
		$this->assertSame( 42, $odoo['partner_id'] );
	}

	public function test_order_mapping_includes_invoice_line_ids(): void {
		$lines = [ [ 0, 0, [ 'product_id' => 5, 'quantity' => 1, 'price_unit' => 29.99 ] ] ];
		$odoo  = $this->module->map_to_odoo( $this->get_order_entity(), [ 'invoice_line_ids' => $lines ] );
		$this->assertSame( $lines, $odoo['invoice_line_ids'] );
	}

	public function test_order_mapping_includes_ref(): void {
		$ref  = $this->get_ref_prefix() . '123';
		$odoo = $this->module->map_to_odoo( $this->get_order_entity(), [ 'ref' => $ref ] );
		$this->assertSame( $ref, $odoo['ref'] );
	}

	// ─── Field Mappings: Membership ────────────────────────

	public function test_membership_mapping_includes_partner_id(): void {
		$odoo = $this->module->map_to_odoo( $this->get_membership_entity(), [ 'partner_id' => 42 ] );
		$this->assertSame( 42, $odoo['partner_id'] );
	}

	public function test_membership_mapping_includes_membership_id(): void {
		$odoo = $this->module->map_to_odoo( $this->get_membership_entity(), [ 'membership_id' => 5 ] );
		$this->assertSame( 5, $odoo['membership_id'] );
	}

	public function test_membership_mapping_includes_state(): void {
		$odoo = $this->module->map_to_odoo( $this->get_membership_entity(), [ 'state' => 'paid' ] );
		$this->assertSame( 'paid', $odoo['state'] );
	}

	public function test_membership_mapping_includes_dates(): void {
		$odoo = $this->module->map_to_odoo( $this->get_membership_entity(), [
			'date_from' => '2026-01-01',
			'date_to'   => '2027-01-01',
		] );
		$this->assertSame( '2026-01-01', $odoo['date_from'] );
		$this->assertSame( '2027-01-01', $odoo['date_to'] );
	}

	public function test_membership_mapping_includes_member_price(): void {
		$odoo = $this->module->map_to_odoo( $this->get_membership_entity(), [ 'member_price' => 29.99 ] );
		$this->assertSame( 29.99, $odoo['member_price'] );
	}

	// ─── Boot Guard ────────────────────────────────────────

	public function test_boot_does_not_crash(): void {
		$this->module->boot();
		$this->assertTrue( true );
	}
}

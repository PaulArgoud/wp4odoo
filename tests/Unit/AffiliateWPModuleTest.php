<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Modules\AffiliateWP_Module;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AffiliateWP_Module.
 *
 * Tests module configuration, entity type declarations, default settings,
 * settings fields, dependency status, sync direction, field mappings,
 * and push overrides.
 */
class AffiliateWPModuleTest extends TestCase {

	private AffiliateWP_Module $module;

	protected function setUp(): void {
		global $wpdb;
		$wpdb = new \WP_DB_Stub();

		$GLOBALS['_wp_options']       = [];
		$GLOBALS['_wp_transients']    = [];
		$GLOBALS['_wp_posts']         = [];
		$GLOBALS['_wp_post_meta']     = [];
		$GLOBALS['_wp_users']         = [];
		$GLOBALS['_wp_user_meta']     = [];
		$GLOBALS['_affwp_affiliates'] = [];
		$GLOBALS['_affwp_referrals']  = [];

		$this->module = new AffiliateWP_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );
	}

	// ─── Module Identity ──────────────────────────────────

	public function test_module_id_is_affiliatewp(): void {
		$this->assertSame( 'affiliatewp', $this->module->get_id() );
	}

	public function test_module_name(): void {
		$this->assertSame( 'AffiliateWP', $this->module->get_name() );
	}

	public function test_sync_direction_is_wp_to_odoo(): void {
		$this->assertSame( 'wp_to_odoo', $this->module->get_sync_direction() );
	}

	public function test_no_exclusive_group(): void {
		$this->assertSame( '', $this->module->get_exclusive_group() );
	}

	// ─── Odoo Models ──────────────────────────────────────

	public function test_declares_affiliate_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'res.partner', $models['affiliate'] );
	}

	public function test_declares_referral_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'account.move', $models['referral'] );
	}

	public function test_declares_exactly_two_entity_types(): void {
		$models = $this->module->get_odoo_models();
		$this->assertCount( 2, $models );
	}

	// ─── Default Settings ─────────────────────────────────

	public function test_default_settings_has_sync_affiliates(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['sync_affiliates'] );
	}

	public function test_default_settings_has_sync_referrals(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['sync_referrals'] );
	}

	public function test_default_settings_has_auto_post_bills_disabled(): void {
		$settings = $this->module->get_default_settings();
		$this->assertFalse( $settings['auto_post_bills'] );
	}

	public function test_default_settings_has_exactly_three_keys(): void {
		$settings = $this->module->get_default_settings();
		$this->assertCount( 3, $settings );
	}

	// ─── Settings Fields ──────────────────────────────────

	public function test_settings_fields_exposes_sync_affiliates(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( 'sync_affiliates', $fields );
		$this->assertSame( 'checkbox', $fields['sync_affiliates']['type'] );
	}

	public function test_settings_fields_exposes_sync_referrals(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( 'sync_referrals', $fields );
		$this->assertSame( 'checkbox', $fields['sync_referrals']['type'] );
	}

	public function test_settings_fields_exposes_auto_post_bills(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( 'auto_post_bills', $fields );
		$this->assertSame( 'checkbox', $fields['auto_post_bills']['type'] );
	}

	public function test_settings_fields_has_exactly_three_fields(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertCount( 3, $fields );
	}

	// ─── Mapping (via map_to_odoo) ────────────────────────

	public function test_map_to_odoo_affiliate_maps_name(): void {
		$mapped = $this->module->map_to_odoo( 'affiliate', [ 'name' => 'John Doe' ] );
		$this->assertSame( 'John Doe', $mapped['name'] );
	}

	public function test_map_to_odoo_affiliate_maps_email(): void {
		$mapped = $this->module->map_to_odoo( 'affiliate', [ 'email' => 'john@example.com' ] );
		$this->assertSame( 'john@example.com', $mapped['email'] );
	}

	public function test_map_to_odoo_affiliate_maps_phone(): void {
		$mapped = $this->module->map_to_odoo( 'affiliate', [ 'phone' => '+33612345678' ] );
		$this->assertSame( '+33612345678', $mapped['phone'] );
	}

	public function test_map_to_odoo_affiliate_has_three_fields(): void {
		$data   = [ 'name' => 'Test', 'email' => 'a@b.com', 'phone' => '123' ];
		$mapped = $this->module->map_to_odoo( 'affiliate', $data );
		$this->assertCount( 3, $mapped );
	}

	public function test_map_to_odoo_referral_maps_move_type(): void {
		$mapped = $this->module->map_to_odoo( 'referral', [ 'move_type' => 'in_invoice' ] );
		$this->assertSame( 'in_invoice', $mapped['move_type'] );
	}

	public function test_map_to_odoo_referral_maps_partner_id(): void {
		$mapped = $this->module->map_to_odoo( 'referral', [ 'partner_id' => 42 ] );
		$this->assertSame( 42, $mapped['partner_id'] );
	}

	public function test_map_to_odoo_referral_maps_invoice_line_ids(): void {
		$lines  = [ [ 0, 0, [ 'name' => 'Commission', 'quantity' => 1, 'price_unit' => 25.0 ] ] ];
		$mapped = $this->module->map_to_odoo( 'referral', [ 'invoice_line_ids' => $lines ] );
		$this->assertSame( $lines, $mapped['invoice_line_ids'] );
	}

	public function test_map_to_odoo_referral_has_five_fields(): void {
		$data = [
			'move_type'        => 'in_invoice',
			'partner_id'       => 42,
			'invoice_date'     => '2026-02-14',
			'ref'              => 'affwp-ref-1',
			'invoice_line_ids' => [ [ 0, 0, [] ] ],
		];
		$mapped = $this->module->map_to_odoo( 'referral', $data );
		$this->assertCount( 5, $mapped );
	}

	// ─── Dependency Status ────────────────────────────────

	public function test_dependency_available_when_affiliate_wp_exists(): void {
		$status = $this->module->get_dependency_status();
		// affiliate_wp() is defined in test stubs.
		$this->assertTrue( $status['available'] );
	}

	// ─── Push Override ────────────────────────────────────

	public function test_push_returns_success_for_delete_action(): void {
		$result = $this->module->push_to_odoo( 'affiliate', 'delete', 42, 0 );
		$this->assertTrue( $result->succeeded() );
	}

	// ─── Handler accessor ─────────────────────────────────

	public function test_get_handler_returns_handler_instance(): void {
		$handler = $this->module->get_handler();
		$this->assertInstanceOf( \WP4Odoo\Modules\AffiliateWP_Handler::class, $handler );
	}
}

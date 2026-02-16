<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Modules\PMPro_Module;

class PMProModuleTest extends MembershipModuleTestBase {

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

	protected function get_module_id(): string {
		return 'pmpro';
	}

	protected function get_module_name(): string {
		return 'Paid Memberships Pro';
	}

	protected function get_exclusive_priority(): int {
		return 15;
	}

	protected function get_level_entity(): string {
		return 'level';
	}

	protected function get_order_entity(): string {
		return 'order';
	}

	protected function get_membership_entity(): string {
		return 'membership';
	}

	protected function get_level_name_field(): string {
		return 'level_name';
	}

	protected function get_ref_prefix(): string {
		return 'PMPRO-';
	}

	protected function get_sync_level_key(): string {
		return 'sync_levels';
	}

	protected function get_sync_order_key(): string {
		return 'sync_orders';
	}

	protected function get_sync_membership_key(): string {
		return 'sync_memberships';
	}

	// ─── Dependency Status ─────────────────────────────────

	public function test_dependency_unavailable_without_pmpro(): void {
		$status = $this->module->get_dependency_status();
		$this->assertFalse( $status['available'] );
	}

	public function test_dependency_has_warning_without_pmpro(): void {
		$status = $this->module->get_dependency_status();
		$this->assertNotEmpty( $status['notices'] );
		$this->assertSame( 'warning', $status['notices'][0]['type'] );
	}
}

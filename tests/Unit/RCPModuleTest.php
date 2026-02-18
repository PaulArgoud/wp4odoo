<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Modules\RCP_Module;

class RCPModuleTest extends MembershipModuleTestBase {

	protected function setUp(): void {
		global $wpdb;
		$wpdb = new \WP_DB_Stub();

		$GLOBALS['_wp_options']      = [];
		$GLOBALS['_rcp_levels']      = [];
		$GLOBALS['_rcp_payments']    = [];
		$GLOBALS['_rcp_memberships'] = [];

		$this->module = new RCP_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );
	}

	protected function get_module_id(): string {
		return 'rcp';
	}

	protected function get_module_name(): string {
		return 'Restrict Content Pro';
	}

	protected function get_level_entity(): string {
		return 'level';
	}

	protected function get_order_entity(): string {
		return 'payment';
	}

	protected function get_membership_entity(): string {
		return 'membership';
	}

	protected function get_level_name_field(): string {
		return 'level_name';
	}

	protected function get_ref_prefix(): string {
		return 'RCP-';
	}

	protected function get_sync_level_key(): string {
		return 'sync_levels';
	}

	protected function get_sync_order_key(): string {
		return 'sync_payments';
	}

	protected function get_sync_membership_key(): string {
		return 'sync_memberships';
	}

	// ─── Dependency Status ─────────────────────────────────

	public function test_dependency_available_with_rcp(): void {
		$status = $this->module->get_dependency_status();
		$this->assertTrue( $status['available'] );
	}
}

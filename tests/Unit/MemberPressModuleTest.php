<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Modules\MemberPress_Module;

class MemberPressModuleTest extends MembershipModuleTestBase {

	private \WP_DB_Stub $wpdb;

	protected function setUp(): void {
		global $wpdb;
		$this->wpdb = new \WP_DB_Stub();
		$wpdb       = $this->wpdb;

		$GLOBALS['_wp_options']          = [];
		$GLOBALS['_mepr_transactions']   = [];
		$GLOBALS['_mepr_subscriptions']  = [];

		$this->module = new MemberPress_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );
	}

	protected function get_module_id(): string {
		return 'memberpress';
	}

	protected function get_module_name(): string {
		return 'MemberPress';
	}

	protected function get_level_entity(): string {
		return 'plan';
	}

	protected function get_order_entity(): string {
		return 'transaction';
	}

	protected function get_membership_entity(): string {
		return 'subscription';
	}

	protected function get_level_name_field(): string {
		return 'plan_name';
	}

	protected function get_ref_prefix(): string {
		return 'mp-txn-';
	}

	protected function get_sync_level_key(): string {
		return 'sync_plans';
	}

	protected function get_sync_order_key(): string {
		return 'sync_transactions';
	}

	protected function get_sync_membership_key(): string {
		return 'sync_subscriptions';
	}

	// ─── Dependency Status ────────────────────────────────

	public function test_dependency_available_with_memberpress(): void {
		$status = $this->module->get_dependency_status();
		$this->assertTrue( $status['available'] );
	}

	public function test_dependency_has_empty_notices(): void {
		$status = $this->module->get_dependency_status();
		$this->assertEmpty( $status['notices'] );
	}
}

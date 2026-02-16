<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Modules\LifterLMS_Module;

class LifterLMSModuleTest extends LMSModuleTestBase {

	protected function setUp(): void {
		global $wpdb;
		$this->wpdb = new \WP_DB_Stub();
		$wpdb       = $this->wpdb;

		$GLOBALS['_wp_options']       = [];
		$GLOBALS['_llms_orders']      = [];
		$GLOBALS['_llms_enrollments'] = [];

		$this->module = new LifterLMS_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );
	}

	protected function get_module_id(): string {
		return 'lifterlms';
	}

	protected function get_module_name(): string {
		return 'LifterLMS';
	}

	protected function get_order_entity(): string {
		return 'order';
	}

	protected function get_sync_order_key(): string {
		return 'sync_orders';
	}

	// ─── Odoo Models ───────────────────────────────────────

	public function test_declares_membership_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'product.product', $models['membership'] );
	}

	public function test_declares_exactly_four_entity_types(): void {
		$models = $this->module->get_odoo_models();
		$this->assertCount( 4, $models );
	}

	// ─── Default Settings ──────────────────────────────────

	public function test_default_settings_has_sync_memberships(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['sync_memberships'] );
	}

	public function test_default_settings_has_exactly_seven_keys(): void {
		$settings = $this->module->get_default_settings();
		$this->assertCount( 7, $settings );
	}

	// ─── Settings Fields ───────────────────────────────────

	public function test_settings_fields_exposes_sync_memberships(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( 'sync_memberships', $fields );
		$this->assertSame( 'checkbox', $fields['sync_memberships']['type'] );
	}

	// ─── Field Mappings: Membership ───────────────────────

	public function test_membership_mapping_includes_name(): void {
		$odoo = $this->module->map_to_odoo( 'membership', [ 'title' => 'Pro Access' ] );
		$this->assertSame( 'Pro Access', $odoo['name'] );
	}

	public function test_membership_mapping_includes_list_price(): void {
		$odoo = $this->module->map_to_odoo( 'membership', [ 'list_price' => 99.0 ] );
		$this->assertSame( 99.0, $odoo['list_price'] );
	}

	// ─── Field Mappings: Enrollment ───────────────────────

	public function test_enrollment_mapping_includes_date_order(): void {
		$odoo = $this->module->map_to_odoo( 'enrollment', [ 'date_order' => '2026-02-01' ] );
		$this->assertSame( '2026-02-01', $odoo['date_order'] );
	}

	// ─── Dependency Status ─────────────────────────────────

	public function test_dependency_unavailable_without_lifterlms(): void {
		$status = $this->module->get_dependency_status();
		$this->assertFalse( $status['available'] );
	}

	public function test_dependency_has_warning_without_lifterlms(): void {
		$status = $this->module->get_dependency_status();
		$this->assertNotEmpty( $status['notices'] );
		$this->assertSame( 'warning', $status['notices'][0]['type'] );
	}

	// ─── Pull Settings ──────────────────────────────────

	public function test_default_settings_has_pull_courses(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['pull_courses'] );
	}

	public function test_default_settings_has_pull_memberships(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['pull_memberships'] );
	}

	public function test_settings_fields_exposes_pull_courses(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( 'pull_courses', $fields );
		$this->assertSame( 'checkbox', $fields['pull_courses']['type'] );
	}

	public function test_settings_fields_exposes_pull_memberships(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( 'pull_memberships', $fields );
		$this->assertSame( 'checkbox', $fields['pull_memberships']['type'] );
	}

	// ─── Pull: delete ───────────────────────────────────

	public function test_pull_course_delete_removes_post(): void {
		$GLOBALS['_wp_posts'][50] = (object) [
			'post_type'    => 'llms_course',
			'post_title'   => 'Course to delete',
			'post_content' => '',
		];

		$result = $this->module->pull_from_odoo( 'course', 'delete', 100, 50 );
		$this->assertTrue( $result->succeeded() );
	}

	public function test_pull_membership_delete_removes_post(): void {
		$GLOBALS['_wp_posts'][60] = (object) [
			'post_type'    => 'llms_membership',
			'post_title'   => 'Membership to delete',
			'post_content' => '',
		];

		$result = $this->module->pull_from_odoo( 'membership', 'delete', 200, 60 );
		$this->assertTrue( $result->succeeded() );
	}

	// ─── map_from_odoo ──────────────────────────────────

	public function test_map_from_odoo_membership(): void {
		$odoo_data = [
			'name'             => 'Pulled Membership',
			'description_sale' => 'Membership from Odoo',
			'list_price'       => 99.0,
		];

		$wp_data = $this->module->map_from_odoo( 'membership', $odoo_data );

		$this->assertSame( 'Pulled Membership', $wp_data['title'] );
		$this->assertSame( 'Membership from Odoo', $wp_data['description'] );
		$this->assertSame( 99.0, $wp_data['list_price'] );
	}
}

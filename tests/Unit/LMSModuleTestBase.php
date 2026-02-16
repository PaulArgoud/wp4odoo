<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Module_Base;
use PHPUnit\Framework\TestCase;

/**
 * @since 3.4.0
 */
abstract class LMSModuleTestBase extends TestCase {

	protected Module_Base $module;
	protected \WP_DB_Stub $wpdb;

	abstract protected function get_module_id(): string;

	abstract protected function get_module_name(): string;

	abstract protected function get_order_entity(): string;

	abstract protected function get_sync_order_key(): string;

	// ─── Module Identity ───────────────────────────────────

	public function test_module_id(): void {
		$this->assertSame( $this->get_module_id(), $this->module->get_id() );
	}

	public function test_module_name(): void {
		$this->assertSame( $this->get_module_name(), $this->module->get_name() );
	}

	public function test_no_exclusive_group(): void {
		$this->assertSame( '', $this->module->get_exclusive_group() );
	}

	public function test_sync_direction_is_bidirectional(): void {
		$this->assertSame( 'bidirectional', $this->module->get_sync_direction() );
	}

	// ─── Odoo Models ───────────────────────────────────────

	public function test_declares_course_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'product.product', $models['course'] );
	}

	public function test_declares_order_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'account.move', $models[ $this->get_order_entity() ] );
	}

	public function test_declares_enrollment_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'sale.order', $models['enrollment'] );
	}

	// ─── Default Settings ──────────────────────────────────

	public function test_default_settings_has_sync_courses(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['sync_courses'] );
	}

	public function test_default_settings_has_sync_orders(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings[ $this->get_sync_order_key() ] );
	}

	public function test_default_settings_has_sync_enrollments(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['sync_enrollments'] );
	}

	public function test_default_settings_has_auto_post_invoices(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['auto_post_invoices'] );
	}

	// ─── Settings Fields ───────────────────────────────────

	public function test_settings_fields_exposes_sync_courses(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( 'sync_courses', $fields );
		$this->assertSame( 'checkbox', $fields['sync_courses']['type'] );
	}

	public function test_settings_fields_exposes_sync_orders(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( $this->get_sync_order_key(), $fields );
		$this->assertSame( 'checkbox', $fields[ $this->get_sync_order_key() ]['type'] );
	}

	public function test_settings_fields_exposes_sync_enrollments(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( 'sync_enrollments', $fields );
		$this->assertSame( 'checkbox', $fields['sync_enrollments']['type'] );
	}

	public function test_settings_fields_exposes_auto_post_invoices(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( 'auto_post_invoices', $fields );
		$this->assertSame( 'checkbox', $fields['auto_post_invoices']['type'] );
	}

	// ─── Field Mappings: Course ────────────────────────────

	public function test_course_mapping_includes_name(): void {
		$odoo = $this->module->map_to_odoo( 'course', [ 'title' => 'PHP Basics' ] );
		$this->assertSame( 'PHP Basics', $odoo['name'] );
	}

	public function test_course_mapping_includes_list_price(): void {
		$odoo = $this->module->map_to_odoo( 'course', [ 'list_price' => 49.99 ] );
		$this->assertSame( 49.99, $odoo['list_price'] );
	}

	public function test_course_mapping_includes_type(): void {
		$odoo = $this->module->map_to_odoo( 'course', [ 'type' => 'service' ] );
		$this->assertSame( 'service', $odoo['type'] );
	}

	public function test_course_mapping_includes_description(): void {
		$odoo = $this->module->map_to_odoo( 'course', [ 'description' => 'Learn PHP' ] );
		$this->assertSame( 'Learn PHP', $odoo['description_sale'] );
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
		$lines = [ [ 0, 0, [ 'product_id' => 5, 'quantity' => 1, 'price_unit' => 49.99 ] ] ];
		$odoo  = $this->module->map_to_odoo( $this->get_order_entity(), [ 'invoice_line_ids' => $lines ] );
		$this->assertSame( $lines, $odoo['invoice_line_ids'] );
	}

	// ─── Field Mappings: Enrollment ────────────────────────

	public function test_enrollment_mapping_includes_partner_id(): void {
		$odoo = $this->module->map_to_odoo( 'enrollment', [ 'partner_id' => 42 ] );
		$this->assertSame( 42, $odoo['partner_id'] );
	}

	public function test_enrollment_mapping_includes_order_line(): void {
		$lines = [ [ 0, 0, [ 'product_id' => 5, 'quantity' => 1 ] ] ];
		$odoo  = $this->module->map_to_odoo( 'enrollment', [ 'order_line' => $lines ] );
		$this->assertSame( $lines, $odoo['order_line'] );
	}

	public function test_enrollment_mapping_includes_state(): void {
		$odoo = $this->module->map_to_odoo( 'enrollment', [ 'state' => 'sale' ] );
		$this->assertSame( 'sale', $odoo['state'] );
	}

	// ─── Pull: order/enrollment skipped ───────────────────

	public function test_pull_order_skipped(): void {
		$result = $this->module->pull_from_odoo( $this->get_order_entity(), 'create', 100, 0 );
		$this->assertTrue( $result->succeeded() );
		$this->assertNull( $result->get_entity_id() );
	}

	public function test_pull_enrollment_skipped(): void {
		$result = $this->module->pull_from_odoo( 'enrollment', 'create', 200, 0 );
		$this->assertTrue( $result->succeeded() );
		$this->assertNull( $result->get_entity_id() );
	}

	// ─── map_from_odoo: Course ─────────────────────────────

	public function test_map_from_odoo_course(): void {
		$odoo_data = [
			'name'             => 'Pulled Course',
			'description_sale' => 'From Odoo',
			'list_price'       => 79.99,
		];

		$wp_data = $this->module->map_from_odoo( 'course', $odoo_data );

		$this->assertSame( 'Pulled Course', $wp_data['title'] );
		$this->assertSame( 'From Odoo', $wp_data['description'] );
		$this->assertSame( 79.99, $wp_data['list_price'] );
	}

	// ─── Boot Guard ────────────────────────────────────────

	public function test_boot_does_not_crash(): void {
		$this->module->boot();
		$this->assertTrue( true );
	}

	// ─── Helpers ───────────────────────────────────────────

	protected function create_post( int $id, string $post_type, string $title, string $content = '', int $author = 0, string $date_gmt = '' ): void {
		$GLOBALS['_wp_posts'][ $id ] = (object) [
			'ID'            => $id,
			'post_type'     => $post_type,
			'post_title'    => $title,
			'post_content'  => $content,
			'post_status'   => 'publish',
			'post_author'   => $author,
			'post_date_gmt' => $date_gmt ?: '2026-01-01 00:00:00',
		];
	}

	protected function create_user( int $id, string $email, string $display_name ): void {
		$user                = new \stdClass();
		$user->ID            = $id;
		$user->user_email    = $email;
		$user->display_name  = $display_name;
		$user->first_name    = explode( ' ', $display_name )[0] ?? '';
		$user->last_name     = explode( ' ', $display_name )[1] ?? '';

		$GLOBALS['_wp_users'][ $id ] = $user;
	}

	protected function assertQueueContains( string $module, string $entity, string $action, int $wp_id ): void {
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

	protected function assertQueueEmpty(): void {
		$inserts = array_filter(
			$this->wpdb->calls,
			fn( $c ) => 'insert' === $c['method'] && str_contains( $c['args'][0] ?? '', 'sync_queue' )
		);
		$this->assertEmpty( $inserts, 'Queue should be empty.' );
	}
}

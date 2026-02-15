<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Modules\TutorLMS_Module;
use WP4Odoo\Modules\TutorLMS_Handler;
use WP4Odoo\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TutorLMS_Module, TutorLMS_Handler, and TutorLMS_Hooks.
 *
 * Tests module configuration, handler data loading, invoice/order formatting,
 * and hook guard logic.
 */
class TutorLMSModuleTest extends TestCase {

	private TutorLMS_Module $module;
	private TutorLMS_Handler $handler;
	private \WP_DB_Stub $wpdb;

	protected function setUp(): void {
		global $wpdb;
		$this->wpdb = new \WP_DB_Stub();
		$wpdb       = $this->wpdb;

		$GLOBALS['_wp_options']   = [];
		$GLOBALS['_wp_posts']     = [];
		$GLOBALS['_wp_post_meta'] = [];
		$GLOBALS['_wp_users']     = [];
		$GLOBALS['_wp_user_meta'] = [];

		$this->wpdb->insert_id = 1;

		$this->module  = new TutorLMS_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );
		$this->handler = new TutorLMS_Handler( new Logger( 'tutorlms', wp4odoo_test_settings() ) );
	}

	// ─── Module Identity ───────────────────────────────────

	public function test_module_id_is_tutorlms(): void {
		$this->assertSame( 'tutorlms', $this->module->get_id() );
	}

	public function test_module_name_is_tutorlms(): void {
		$this->assertSame( 'TutorLMS', $this->module->get_name() );
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
		$this->assertSame( 'account.move', $models['order'] );
	}

	public function test_declares_enrollment_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'sale.order', $models['enrollment'] );
	}

	public function test_declares_exactly_three_entity_types(): void {
		$models = $this->module->get_odoo_models();
		$this->assertCount( 3, $models );
	}

	// ─── Default Settings ──────────────────────────────────

	public function test_default_settings_has_sync_courses(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['sync_courses'] );
	}

	public function test_default_settings_has_sync_orders(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['sync_orders'] );
	}

	public function test_default_settings_has_sync_enrollments(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['sync_enrollments'] );
	}

	public function test_default_settings_has_auto_post_invoices(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['auto_post_invoices'] );
	}

	public function test_default_settings_has_pull_courses(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['pull_courses'] );
	}

	public function test_default_settings_has_exactly_five_keys(): void {
		$settings = $this->module->get_default_settings();
		$this->assertCount( 5, $settings );
	}

	// ─── Settings Fields ───────────────────────────────────

	public function test_settings_fields_exposes_sync_courses(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( 'sync_courses', $fields );
		$this->assertSame( 'checkbox', $fields['sync_courses']['type'] );
	}

	public function test_settings_fields_exposes_sync_orders(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( 'sync_orders', $fields );
		$this->assertSame( 'checkbox', $fields['sync_orders']['type'] );
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

	public function test_settings_fields_exposes_pull_courses(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( 'pull_courses', $fields );
		$this->assertSame( 'checkbox', $fields['pull_courses']['type'] );
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
		$odoo = $this->module->map_to_odoo( 'order', [ 'move_type' => 'out_invoice' ] );
		$this->assertSame( 'out_invoice', $odoo['move_type'] );
	}

	public function test_order_mapping_includes_partner_id(): void {
		$odoo = $this->module->map_to_odoo( 'order', [ 'partner_id' => 42 ] );
		$this->assertSame( 42, $odoo['partner_id'] );
	}

	public function test_order_mapping_includes_invoice_line_ids(): void {
		$lines = [ [ 0, 0, [ 'product_id' => 5, 'quantity' => 1, 'price_unit' => 49.99 ] ] ];
		$odoo  = $this->module->map_to_odoo( 'order', [ 'invoice_line_ids' => $lines ] );
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

	// ─── Dependency Status ────────────────────────────────

	public function test_dependency_available_with_tutor_version(): void {
		$status = $this->module->get_dependency_status();
		$this->assertTrue( $status['available'] );
	}

	// ─── Boot Guard ───────────────────────────────────────

	public function test_boot_does_not_crash(): void {
		$this->module->boot();
		$this->assertTrue( true );
	}

	// ─── Handler: load_course ─────────────────────────────

	public function test_load_course_returns_data_for_valid_course(): void {
		$this->create_post( 100, 'courses', 'PHP Basics', 'Learn PHP from scratch' );
		$GLOBALS['_wp_post_meta'][100] = [
			'_tutor_course_price' => '49.99',
		];

		$data = $this->handler->load_course( 100 );

		$this->assertSame( 'PHP Basics', $data['title'] );
		$this->assertSame( 49.99, $data['list_price'] );
		$this->assertSame( 'service', $data['type'] );
	}

	public function test_load_course_returns_empty_for_nonexistent(): void {
		$data = $this->handler->load_course( 999 );
		$this->assertSame( [], $data );
	}

	public function test_load_course_returns_empty_for_wrong_post_type(): void {
		$this->create_post( 100, 'post', 'Not a course' );

		$data = $this->handler->load_course( 100 );
		$this->assertSame( [], $data );
	}

	public function test_load_course_strips_html_from_description(): void {
		$this->create_post( 100, 'courses', 'Test', '<p>Hello <strong>world</strong></p>' );

		$data = $this->handler->load_course( 100 );
		$this->assertSame( 'Hello world', $data['description'] );
	}

	// ─── Handler: load_order ─────────────────────────────

	public function test_load_order_returns_data_for_valid_order(): void {
		$this->create_post( 300, 'tutor_order', 'Order', '', 5, '2026-01-15 10:00:00' );
		$GLOBALS['_wp_post_meta'][300] = [
			'_tutor_order_user_id'   => [ '5' ],
			'_tutor_order_total'     => [ '49.99' ],
			'_tutor_order_currency'  => [ 'EUR' ],
			'_tutor_order_course_id' => [ '100' ],
		];

		$data = $this->handler->load_order( 300 );

		$this->assertSame( 5, $data['user_id'] );
		$this->assertSame( 49.99, $data['amount'] );
		$this->assertSame( 'EUR', $data['currency'] );
		$this->assertSame( 100, $data['course_id'] );
	}

	public function test_load_order_returns_empty_for_nonexistent(): void {
		$data = $this->handler->load_order( 999 );
		$this->assertSame( [], $data );
	}

	// ─── Handler: load_enrollment ─────────────────────────

	public function test_load_enrollment_returns_data_for_valid_enrollment(): void {
		$this->create_user( 5, 'student@example.com', 'John Student' );
		$this->create_post( 100, 'courses', 'PHP Basics' );

		$data = $this->handler->load_enrollment( 5, 100 );

		$this->assertSame( 5, $data['user_id'] );
		$this->assertSame( 100, $data['course_id'] );
		$this->assertSame( 'student@example.com', $data['user_email'] );
		$this->assertSame( 'John Student', $data['user_name'] );
	}

	public function test_load_enrollment_returns_empty_for_missing_user(): void {
		$this->create_post( 100, 'courses', 'PHP Basics' );

		$data = $this->handler->load_enrollment( 999, 100 );
		$this->assertSame( [], $data );
	}

	public function test_load_enrollment_returns_empty_for_missing_course(): void {
		$this->create_user( 5, 'student@example.com', 'John Student' );

		$data = $this->handler->load_enrollment( 5, 999 );
		$this->assertSame( [], $data );
	}

	// ─── Handler: format_invoice ──────────────────────────

	public function test_format_invoice_returns_correct_structure(): void {
		$data = [
			'course_id'  => 100,
			'amount'     => 49.99,
			'created_at' => '2026-01-15 10:00:00',
			'order_id'   => 300,
		];

		$this->create_post( 100, 'courses', 'PHP Basics' );

		$invoice = $this->handler->format_invoice( $data, 42, 10, false );

		$this->assertSame( 'out_invoice', $invoice['move_type'] );
		$this->assertSame( 10, $invoice['partner_id'] );
		$this->assertSame( '2026-01-15', $invoice['invoice_date'] );
		$this->assertSame( 'TUTOR-ORD-300', $invoice['ref'] );
		$this->assertArrayNotHasKey( '_auto_validate', $invoice );
	}

	public function test_format_invoice_includes_line_items(): void {
		$data = [
			'course_id'  => 100,
			'amount'     => 49.99,
			'created_at' => '2026-01-15 10:00:00',
			'order_id'   => 300,
		];

		$this->create_post( 100, 'courses', 'PHP Basics' );

		$invoice = $this->handler->format_invoice( $data, 42, 10, false );
		$line    = $invoice['invoice_line_ids'][0];

		$this->assertSame( 0, $line[0] );
		$this->assertSame( 0, $line[1] );
		$this->assertSame( 42, $line[2]['product_id'] );
		$this->assertSame( 1, $line[2]['quantity'] );
		$this->assertSame( 49.99, $line[2]['price_unit'] );
		$this->assertSame( 'PHP Basics', $line[2]['name'] );
	}

	public function test_format_invoice_auto_post_flag(): void {
		$data = [
			'course_id'  => 100,
			'amount'     => 49.99,
			'created_at' => '2026-01-15 10:00:00',
			'order_id'   => 300,
		];

		$invoice = $this->handler->format_invoice( $data, 42, 10, true );
		$this->assertTrue( $invoice['_auto_validate'] );
	}

	// ─── Handler: format_sale_order ───────────────────────

	public function test_format_sale_order_returns_correct_structure(): void {
		$order = $this->handler->format_sale_order( 42, 10, '2026-01-15', 'PHP Basics' );

		$this->assertSame( 10, $order['partner_id'] );
		$this->assertSame( '2026-01-15', $order['date_order'] );
		$this->assertSame( 'sale', $order['state'] );
	}

	public function test_format_sale_order_includes_order_line(): void {
		$order = $this->handler->format_sale_order( 42, 10, '2026-01-15', 'PHP Basics' );
		$line  = $order['order_line'][0];

		$this->assertSame( 0, $line[0] );
		$this->assertSame( 0, $line[1] );
		$this->assertSame( 42, $line[2]['product_id'] );
		$this->assertSame( 1, $line[2]['quantity'] );
		$this->assertSame( 'PHP Basics', $line[2]['name'] );
	}

	public function test_format_sale_order_fallback_name(): void {
		$order = $this->handler->format_sale_order( 42, 10, '2026-01-15' );
		$line  = $order['order_line'][0];

		$this->assertSame( 'TutorLMS enrollment', $line[2]['name'] );
	}

	// ─── Hooks: on_course_save ────────────────────────────

	public function test_on_course_save_enqueues_create(): void {
		$this->create_post( 100, 'courses', 'PHP Basics' );
		$GLOBALS['_wp_options']['wp4odoo_module_tutorlms_settings'] = [ 'sync_courses' => true ];

		$this->module->on_course_save( 100 );

		$this->assertQueueContains( 'tutorlms', 'course', 'create', 100 );
	}

	public function test_on_course_save_skips_when_importing(): void {
		$this->create_post( 100, 'courses', 'PHP Basics' );
		$GLOBALS['_wp_options']['wp4odoo_module_tutorlms_settings'] = [ 'sync_courses' => true ];

		// Simulate importing.
		$reflection = new \ReflectionClass( \WP4Odoo\Module_Base::class );
		$prop       = $reflection->getProperty( 'importing' );
		$prop->setValue( null, [ 'tutorlms' => true ] );

		$this->module->on_course_save( 100 );

		$this->assertQueueEmpty();

		// Clean up.
		$prop->setValue( null, [] );
	}

	public function test_on_course_save_skips_wrong_post_type(): void {
		$this->create_post( 100, 'post', 'Not a course' );
		$GLOBALS['_wp_options']['wp4odoo_module_tutorlms_settings'] = [ 'sync_courses' => true ];

		$this->module->on_course_save( 100 );

		$this->assertQueueEmpty();
	}

	public function test_on_course_save_skips_when_sync_disabled(): void {
		$this->create_post( 100, 'courses', 'PHP Basics' );
		$GLOBALS['_wp_options']['wp4odoo_module_tutorlms_settings'] = [ 'sync_courses' => false ];

		$this->module->on_course_save( 100 );

		$this->assertQueueEmpty();
	}

	// ─── Hooks: on_order_placed ──────────────────────────

	public function test_on_order_placed_enqueues_create(): void {
		$GLOBALS['_wp_options']['wp4odoo_module_tutorlms_settings'] = [ 'sync_orders' => true ];

		$this->module->on_order_placed( 300 );

		$this->assertQueueContains( 'tutorlms', 'order', 'create', 300 );
	}

	public function test_on_order_placed_skips_when_sync_disabled(): void {
		$GLOBALS['_wp_options']['wp4odoo_module_tutorlms_settings'] = [ 'sync_orders' => false ];

		$this->module->on_order_placed( 300 );

		$this->assertQueueEmpty();
	}

	public function test_on_order_placed_skips_when_importing(): void {
		$GLOBALS['_wp_options']['wp4odoo_module_tutorlms_settings'] = [ 'sync_orders' => true ];

		$reflection = new \ReflectionClass( \WP4Odoo\Module_Base::class );
		$prop       = $reflection->getProperty( 'importing' );
		$prop->setValue( null, [ 'tutorlms' => true ] );

		$this->module->on_order_placed( 300 );

		$this->assertQueueEmpty();

		$prop->setValue( null, [] );
	}

	// ─── Hooks: on_enrollment ────────────────────────────

	public function test_on_enrollment_enqueues_create(): void {
		$GLOBALS['_wp_options']['wp4odoo_module_tutorlms_settings'] = [ 'sync_enrollments' => true ];

		$this->module->on_enrollment( 100, 5 );

		$expected_id = 5 * 1_000_000 + 100; // 5000100
		$this->assertQueueContains( 'tutorlms', 'enrollment', 'create', $expected_id );
	}

	public function test_on_enrollment_skips_when_sync_disabled(): void {
		$GLOBALS['_wp_options']['wp4odoo_module_tutorlms_settings'] = [ 'sync_enrollments' => false ];

		$this->module->on_enrollment( 100, 5 );

		$this->assertQueueEmpty();
	}

	public function test_on_enrollment_synthetic_id_is_correct(): void {
		$GLOBALS['_wp_options']['wp4odoo_module_tutorlms_settings'] = [ 'sync_enrollments' => true ];

		$this->module->on_enrollment( 350, 42 );

		$expected_id = 42 * 1_000_000 + 350; // 42000350
		$this->assertQueueContains( 'tutorlms', 'enrollment', 'create', $expected_id );
	}

	// ─── Hooks: on_enrollment_cancel ──────────────────────

	public function test_on_enrollment_cancel_enqueues_delete(): void {
		$GLOBALS['_wp_options']['wp4odoo_module_tutorlms_settings'] = [ 'sync_enrollments' => true ];

		$this->module->on_enrollment_cancel( 100, 5 );

		$expected_id = 5 * 1_000_000 + 100;
		$this->assertQueueContains( 'tutorlms', 'enrollment', 'delete', $expected_id );
	}

	public function test_on_enrollment_cancel_skips_when_sync_disabled(): void {
		$GLOBALS['_wp_options']['wp4odoo_module_tutorlms_settings'] = [ 'sync_enrollments' => false ];

		$this->module->on_enrollment_cancel( 100, 5 );

		$this->assertQueueEmpty();
	}

	// ─── Pull settings ──────────────────────────────────

	public function test_settings_fields_count(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertCount( 5, $fields );
	}

	// ─── Pull: order/enrollment skipped ───────────────

	public function test_pull_order_skipped(): void {
		$result = $this->module->pull_from_odoo( 'order', 'create', 100, 0 );
		$this->assertTrue( $result->succeeded() );
		$this->assertNull( $result->get_entity_id() );
	}

	public function test_pull_enrollment_skipped(): void {
		$result = $this->module->pull_from_odoo( 'enrollment', 'create', 200, 0 );
		$this->assertTrue( $result->succeeded() );
		$this->assertNull( $result->get_entity_id() );
	}

	// ─── Pull: delete ───────────────────────────────────

	public function test_pull_course_delete_removes_post(): void {
		$this->create_post( 50, 'courses', 'Course to delete' );

		$result = $this->module->pull_from_odoo( 'course', 'delete', 100, 50 );
		$this->assertTrue( $result->succeeded() );
	}

	// ─── map_from_odoo ──────────────────────────────────

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

	// ─── Translatable Fields ──────────────────────────────

	public function test_translatable_fields_for_course(): void {
		$method = new \ReflectionMethod( $this->module, 'get_translatable_fields' );

		$fields = $method->invoke( $this->module, 'course' );

		$this->assertSame(
			[ 'name' => 'post_title', 'description_sale' => 'post_content' ],
			$fields
		);
	}

	public function test_translatable_fields_empty_for_order(): void {
		$method = new \ReflectionMethod( $this->module, 'get_translatable_fields' );

		$this->assertSame( [], $method->invoke( $this->module, 'order' ) );
	}

	public function test_translatable_fields_empty_for_enrollment(): void {
		$method = new \ReflectionMethod( $this->module, 'get_translatable_fields' );

		$this->assertSame( [], $method->invoke( $this->module, 'enrollment' ) );
	}

	// ─── Helpers ───────────────────────────────────────────

	private function create_post( int $id, string $post_type, string $title, string $content = '', int $author = 0, string $date_gmt = '' ): void {
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

	private function create_user( int $id, string $email, string $display_name ): void {
		$user                = new \stdClass();
		$user->ID            = $id;
		$user->user_email    = $email;
		$user->display_name  = $display_name;
		$user->first_name    = explode( ' ', $display_name )[0] ?? '';
		$user->last_name     = explode( ' ', $display_name )[1] ?? '';

		$GLOBALS['_wp_users'][ $id ] = $user;
	}

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

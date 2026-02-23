<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Module_Base;
use WP4Odoo\Modules\Events_Calendar_Module;

/**
 * Unit tests for Events_Calendar_Module.
 *
 * Tests module identity, Odoo models, settings, field mappings,
 * dependency status, boot guard, and bidirectional pull support.
 */
class EventsCalendarModuleTest extends EventsModuleTestBase {

	protected function get_module_id(): string {
		return 'events_calendar';
	}

	protected function get_module_name(): string {
		return 'The Events Calendar';
	}

	protected function get_attendance_entity(): string {
		return 'attendee';
	}

	protected function create_module(): Module_Base {
		return new Events_Calendar_Module(
			wp4odoo_test_client_provider(),
			wp4odoo_test_entity_map(),
			wp4odoo_test_settings()
		);
	}

	protected function setUp(): void {
		global $wpdb;
		$wpdb = new \WP_DB_Stub();

		$GLOBALS['_wp_options']       = [];
		$GLOBALS['_wp_transients']    = [];
		$GLOBALS['_wp_posts']         = [];
		$GLOBALS['_wp_post_meta']     = [];
		$GLOBALS['_tribe_events']     = [];
		$GLOBALS['_tribe_tickets']    = [];
		$GLOBALS['_tribe_attendees']  = [];

		$this->module = new Events_Calendar_Module(
			wp4odoo_test_client_provider(),
			wp4odoo_test_entity_map(),
			wp4odoo_test_settings()
		);
	}

	// ─── Module identity (module-specific) ──────────────

	public function test_exclusive_group_is_events(): void {
		$this->assertSame( 'events', $this->module->get_exclusive_group() );
	}

	// ─── Odoo models (module-specific) ──────────────────

	public function test_declares_ticket_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'product.product', $models['ticket'] );
	}

	public function test_declares_attendee_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'event.registration', $models['attendee'] );
	}

	public function test_declares_three_entity_types(): void {
		$this->assertCount( 3, $this->module->get_odoo_models() );
	}

	// ─── Default settings (module-specific) ─────────────

	public function test_sync_tickets_enabled_by_default(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['sync_tickets'] );
	}

	public function test_sync_attendees_enabled_by_default(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['sync_attendees'] );
	}

	public function test_pull_events_enabled_by_default(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['pull_events'] );
	}

	public function test_pull_tickets_enabled_by_default(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['pull_tickets'] );
	}

	public function test_default_settings_count(): void {
		$this->assertCount( 5, $this->module->get_default_settings() );
	}

	// ─── Settings fields (module-specific) ──────────────

	public function test_settings_fields_count(): void {
		$this->assertCount( 5, $this->module->get_settings_fields() );
	}

	// ─── Field mappings: ticket ─────────────────────────

	public function test_ticket_mapping_includes_name(): void {
		$odoo = $this->module->map_to_odoo( 'ticket', [ 'name' => 'VIP' ] );
		$this->assertSame( 'VIP', $odoo['name'] );
	}

	public function test_ticket_mapping_includes_list_price(): void {
		$odoo = $this->module->map_to_odoo( 'ticket', [ 'list_price' => 25.0 ] );
		$this->assertSame( 25.0, $odoo['list_price'] );
	}

	public function test_ticket_mapping_includes_service_type(): void {
		$odoo = $this->module->map_to_odoo( 'ticket', [ 'type' => 'service' ] );
		$this->assertSame( 'service', $odoo['type'] );
	}

	// ─── Pull: delete ───────────────────────────────────

	public function test_pull_event_delete_removes_post(): void {
		$GLOBALS['_wp_posts'][50] = (object) [
			'post_type'    => 'tribe_events',
			'post_title'   => 'Event to delete',
			'post_content' => '',
		];

		$result = $this->module->pull_from_odoo( 'event', 'delete', 100, 50 );
		$this->assertTrue( $result->succeeded() );
	}

	public function test_pull_ticket_delete_removes_post(): void {
		$GLOBALS['_wp_posts'][60] = (object) [
			'post_type'  => 'tribe_rsvp_tickets',
			'post_title' => 'Ticket to delete',
		];

		$result = $this->module->pull_from_odoo( 'ticket', 'delete', 200, 60 );
		$this->assertTrue( $result->succeeded() );
	}

	// ─── map_from_odoo (module-specific) ────────────────

	public function test_map_from_odoo_ticket_uses_parent_mapping(): void {
		$odoo_data = [
			'name'       => 'Pulled Ticket',
			'list_price' => 30.0,
			'type'       => 'service',
		];

		$wp_data = $this->module->map_from_odoo( 'ticket', $odoo_data );

		$this->assertSame( 'Pulled Ticket', $wp_data['name'] );
		$this->assertSame( 30.0, $wp_data['list_price'] );
	}

	// ─── Translatable Fields ────────────────────────────

	public function test_translatable_fields_for_event(): void {
		$method = new \ReflectionMethod( $this->module, 'get_translatable_fields' );

		$fields = $method->invoke( $this->module, 'event' );

		$this->assertSame(
			[ 'name' => 'post_title', 'description' => 'post_content' ],
			$fields
		);
	}

	public function test_translatable_fields_empty_for_ticket(): void {
		$method = new \ReflectionMethod( $this->module, 'get_translatable_fields' );

		$this->assertSame( [], $method->invoke( $this->module, 'ticket' ) );
	}

	public function test_translatable_fields_empty_for_attendee(): void {
		$method = new \ReflectionMethod( $this->module, 'get_translatable_fields' );

		$this->assertSame( [], $method->invoke( $this->module, 'attendee' ) );
	}
}

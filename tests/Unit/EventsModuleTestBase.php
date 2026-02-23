<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Module_Base;
use PHPUnit\Framework\TestCase;

/**
 * Shared tests for all three event modules (Events Calendar, MEC, FooEvents).
 *
 * Covers: module identity, event Odoo model, sync_events default,
 * settings field structure, event/attendance field mappings, attendance
 * pull skip, map_from_odoo dual-model parsing, dependency, and boot guard.
 *
 * @since 3.9.1
 */
abstract class EventsModuleTestBase extends TestCase {

	protected Module_Base $module;

	abstract protected function get_module_id(): string;

	abstract protected function get_module_name(): string;

	abstract protected function get_attendance_entity(): string;

	/**
	 * Create a fresh module instance (needed for transient-dependent tests).
	 *
	 * @return Module_Base
	 */
	abstract protected function create_module(): Module_Base;

	// ─── Module Identity ───────────────────────────────────

	public function test_module_id(): void {
		$this->assertSame( $this->get_module_id(), $this->module->get_id() );
	}

	public function test_module_name(): void {
		$this->assertSame( $this->get_module_name(), $this->module->get_name() );
	}

	public function test_sync_direction_is_bidirectional(): void {
		$this->assertSame( 'bidirectional', $this->module->get_sync_direction() );
	}

	// ─── Odoo Models ───────────────────────────────────────

	public function test_declares_event_model(): void {
		$models = $this->module->get_odoo_models();
		$this->assertSame( 'event.event', $models['event'] );
	}

	// ─── Default Settings ──────────────────────────────────

	public function test_sync_events_enabled_by_default(): void {
		$settings = $this->module->get_default_settings();
		$this->assertTrue( $settings['sync_events'] );
	}

	// ─── Settings Fields ───────────────────────────────────

	public function test_settings_fields_have_labels(): void {
		$fields = $this->module->get_settings_fields();
		foreach ( $fields as $field ) {
			$this->assertArrayHasKey( 'label', $field );
			$this->assertNotEmpty( $field['label'] );
		}
	}

	public function test_settings_fields_are_checkboxes(): void {
		$fields = $this->module->get_settings_fields();
		foreach ( $fields as $field ) {
			$this->assertSame( 'checkbox', $field['type'] );
		}
	}

	// ─── Field Mappings: Event ─────────────────────────────

	public function test_event_mapping_passes_name(): void {
		$odoo = $this->module->map_to_odoo( 'event', [ 'name' => 'Conference' ] );
		$this->assertSame( 'Conference', $odoo['name'] );
	}

	public function test_event_mapping_passes_date_begin(): void {
		$odoo = $this->module->map_to_odoo( 'event', [ 'date_begin' => '2026-06-15 09:00:00' ] );
		$this->assertSame( '2026-06-15 09:00:00', $odoo['date_begin'] );
	}

	public function test_event_mapping_passes_description(): void {
		$odoo = $this->module->map_to_odoo( 'event', [ 'description' => 'Details' ] );
		$this->assertSame( 'Details', $odoo['description'] );
	}

	// ─── Field Mappings: Attendance ────────────────────────

	public function test_attendance_mapping_passes_event_id(): void {
		$odoo = $this->module->map_to_odoo( $this->get_attendance_entity(), [ 'event_id' => 100 ] );
		$this->assertSame( 100, $odoo['event_id'] );
	}

	public function test_attendance_mapping_passes_partner_id(): void {
		$odoo = $this->module->map_to_odoo( $this->get_attendance_entity(), [ 'partner_id' => 200 ] );
		$this->assertSame( 200, $odoo['partner_id'] );
	}

	public function test_attendance_mapping_passes_email(): void {
		$odoo = $this->module->map_to_odoo( $this->get_attendance_entity(), [ 'email' => 'john@example.com' ] );
		$this->assertSame( 'john@example.com', $odoo['email'] );
	}

	// ─── Dependency Status ─────────────────────────────────

	public function test_dependency_available(): void {
		$status = $this->module->get_dependency_status();
		$this->assertTrue( $status['available'] );
	}

	public function test_dependency_has_empty_notices(): void {
		$status = $this->module->get_dependency_status();
		$this->assertEmpty( $status['notices'] );
	}

	// ─── Pull: Attendance Skipped ──────────────────────────

	public function test_pull_attendance_skipped(): void {
		$result = $this->module->pull_from_odoo( $this->get_attendance_entity(), 'update', 100, 0 );
		$this->assertTrue( $result->succeeded() );
		$this->assertNull( $result->get_entity_id() );
	}

	public function test_pull_attendance_create_skipped(): void {
		$result = $this->module->pull_from_odoo( $this->get_attendance_entity(), 'create', 200, 0 );
		$this->assertTrue( $result->succeeded() );
		$this->assertNull( $result->get_entity_id() );
	}

	// ─── map_from_odoo ─────────────────────────────────────

	public function test_map_from_odoo_event_parses_calendar_fields(): void {
		$odoo_data = [
			'name'        => 'Pulled Conference',
			'start'       => '2026-08-01 09:00:00',
			'stop'        => '2026-08-01 17:00:00',
			'allday'      => false,
			'description' => 'From Odoo',
		];

		$wp_data = $this->module->map_from_odoo( 'event', $odoo_data );

		$this->assertSame( 'Pulled Conference', $wp_data['name'] );
		$this->assertSame( '2026-08-01 09:00:00', $wp_data['start_date'] );
		$this->assertSame( '2026-08-01 17:00:00', $wp_data['end_date'] );
		$this->assertFalse( $wp_data['all_day'] );
	}

	public function test_map_from_odoo_event_with_event_model(): void {
		$GLOBALS['_wp_transients']['wp4odoo_has_event_event'] = 1;

		$module = $this->create_module();

		$odoo_data = [
			'name'        => 'Pulled Conference',
			'date_begin'  => '2026-08-01 09:00:00',
			'date_end'    => '2026-08-01 17:00:00',
			'date_tz'     => 'Europe/Paris',
			'description' => 'From Odoo',
		];

		$wp_data = $module->map_from_odoo( 'event', $odoo_data );

		$this->assertSame( 'Pulled Conference', $wp_data['name'] );
		$this->assertSame( '2026-08-01 09:00:00', $wp_data['start_date'] );
		$this->assertSame( '2026-08-01 17:00:00', $wp_data['end_date'] );
		$this->assertSame( 'Europe/Paris', $wp_data['timezone'] );
	}

	// ─── Boot Guard ────────────────────────────────────────

	public function test_boot_does_not_throw(): void {
		$this->module->boot();
		$this->assertTrue( true );
	}
}

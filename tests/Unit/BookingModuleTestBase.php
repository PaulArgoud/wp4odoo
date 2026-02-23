<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Module_Base;
use PHPUnit\Framework\TestCase;

/**
 * Shared tests for all six booking/appointment modules.
 *
 * Covers: module identity, Odoo models, default settings structure,
 * settings fields structure, and boot guard.
 *
 * @since 3.9.1
 */
abstract class BookingModuleTestBase extends TestCase {

	protected Module_Base $module;

	abstract protected function get_module_id(): string;

	abstract protected function get_module_name(): string;

	abstract protected function get_booking_entity(): string;

	abstract protected function get_sync_service_key(): string;

	abstract protected function get_sync_booking_key(): string;

	// ─── Module Identity ───────────────────────────────────

	public function test_module_id(): void {
		$ref = new \ReflectionProperty( $this->module, 'id' );
		$this->assertSame( $this->get_module_id(), $ref->getValue( $this->module ) );
	}

	public function test_module_name(): void {
		$ref = new \ReflectionProperty( $this->module, 'name' );
		$this->assertSame( $this->get_module_name(), $ref->getValue( $this->module ) );
	}

	public function test_exclusive_group_is_empty(): void {
		$this->assertSame( '', $this->module->get_exclusive_group() );
	}

	public function test_sync_direction_is_bidirectional(): void {
		$this->assertSame( 'bidirectional', $this->module->get_sync_direction() );
	}

	// ─── Odoo Models ───────────────────────────────────────

	public function test_declares_service_model(): void {
		$ref = new \ReflectionProperty( $this->module, 'odoo_models' );
		$this->assertSame( 'product.product', $ref->getValue( $this->module )['service'] );
	}

	public function test_declares_booking_model(): void {
		$ref = new \ReflectionProperty( $this->module, 'odoo_models' );
		$this->assertSame( 'calendar.event', $ref->getValue( $this->module )[ $this->get_booking_entity() ] );
	}

	public function test_declares_exactly_two_entity_types(): void {
		$ref = new \ReflectionProperty( $this->module, 'odoo_models' );
		$this->assertCount( 2, $ref->getValue( $this->module ) );
	}

	// ─── Default Settings ──────────────────────────────────

	public function test_default_settings_has_sync_services(): void {
		$this->assertTrue( $this->module->get_default_settings()[ $this->get_sync_service_key() ] );
	}

	public function test_default_settings_has_sync_bookings(): void {
		$this->assertTrue( $this->module->get_default_settings()[ $this->get_sync_booking_key() ] );
	}

	public function test_default_settings_has_pull_services(): void {
		$this->assertTrue( $this->module->get_default_settings()['pull_services'] );
	}

	public function test_default_settings_has_exactly_three_keys(): void {
		$this->assertCount( 3, $this->module->get_default_settings() );
	}

	// ─── Settings Fields ───────────────────────────────────

	public function test_settings_fields_has_sync_services(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( $this->get_sync_service_key(), $fields );
		$this->assertSame( 'checkbox', $fields[ $this->get_sync_service_key() ]['type'] );
	}

	public function test_settings_fields_has_sync_bookings(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( $this->get_sync_booking_key(), $fields );
		$this->assertSame( 'checkbox', $fields[ $this->get_sync_booking_key() ]['type'] );
	}

	public function test_settings_fields_has_pull_services(): void {
		$fields = $this->module->get_settings_fields();
		$this->assertArrayHasKey( 'pull_services', $fields );
		$this->assertSame( 'checkbox', $fields['pull_services']['type'] );
	}

	public function test_settings_fields_has_exactly_three_entries(): void {
		$this->assertCount( 3, $this->module->get_settings_fields() );
	}

	// ─── Boot Guard ────────────────────────────────────────

	public function test_boot_does_not_crash(): void {
		$this->module->boot();
		$this->assertTrue( true );
	}
}

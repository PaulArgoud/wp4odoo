<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Queue_Job;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Queue_Job readonly value object.
 */
class QueueJobTest extends TestCase {

	// ─── Constructor ──────────────────────────────────────

	public function test_constructor_sets_all_properties(): void {
		$job = new Queue_Job(
			id:             42,
			correlation_id: 'abc-123',
			module:         'woocommerce',
			direction:      'wp_to_odoo',
			entity_type:    'product',
			wp_id:          10,
			odoo_id:        20,
			action:         'create',
			payload:        '{"sku":"ABC"}',
			priority:       3,
			status:         'pending',
			attempts:       1,
			max_attempts:   5,
			error_message:  'Connection timeout',
			scheduled_at:   '2025-06-15 12:00:00',
			processed_at:   '2025-06-15 11:00:00',
			created_at:     '2025-06-15 10:00:00',
		);

		$this->assertSame( 42, $job->id );
		$this->assertSame( 'abc-123', $job->correlation_id );
		$this->assertSame( 'woocommerce', $job->module );
		$this->assertSame( 'wp_to_odoo', $job->direction );
		$this->assertSame( 'product', $job->entity_type );
		$this->assertSame( 10, $job->wp_id );
		$this->assertSame( 20, $job->odoo_id );
		$this->assertSame( 'create', $job->action );
		$this->assertSame( '{"sku":"ABC"}', $job->payload );
		$this->assertSame( 3, $job->priority );
		$this->assertSame( 'pending', $job->status );
		$this->assertSame( 1, $job->attempts );
		$this->assertSame( 5, $job->max_attempts );
		$this->assertSame( 'Connection timeout', $job->error_message );
		$this->assertSame( '2025-06-15 12:00:00', $job->scheduled_at );
		$this->assertSame( '2025-06-15 11:00:00', $job->processed_at );
		$this->assertSame( '2025-06-15 10:00:00', $job->created_at );
	}

	public function test_nullable_properties_accept_null(): void {
		$job = new Queue_Job(
			id:             1,
			correlation_id: null,
			module:         'crm',
			direction:      'wp_to_odoo',
			entity_type:    'contact',
			wp_id:          5,
			odoo_id:        0,
			action:         'create',
			payload:        null,
			priority:       5,
			status:         'pending',
			attempts:       0,
			max_attempts:   3,
			error_message:  null,
			scheduled_at:   null,
			processed_at:   null,
			created_at:     '2025-01-01 00:00:00',
		);

		$this->assertNull( $job->correlation_id );
		$this->assertNull( $job->payload );
		$this->assertNull( $job->error_message );
		$this->assertNull( $job->scheduled_at );
		$this->assertNull( $job->processed_at );
	}

	// ─── from_row() ───────────────────────────────────────

	public function test_from_row_converts_all_fields(): void {
		$row = (object) [
			'id'             => '42',
			'correlation_id' => 'uuid-abc',
			'module'         => 'sales',
			'direction'      => 'odoo_to_wp',
			'entity_type'    => 'order',
			'wp_id'          => '100',
			'odoo_id'        => '200',
			'action'         => 'update',
			'payload'        => '{"total":50}',
			'priority'       => '2',
			'status'         => 'processing',
			'attempts'       => '2',
			'max_attempts'   => '5',
			'error_message'  => 'Timeout',
			'scheduled_at'   => '2025-06-15 14:00:00',
			'processed_at'   => '2025-06-15 13:00:00',
			'created_at'     => '2025-06-15 12:00:00',
		];

		$job = Queue_Job::from_row( $row );

		$this->assertSame( 42, $job->id );
		$this->assertSame( 'uuid-abc', $job->correlation_id );
		$this->assertSame( 'sales', $job->module );
		$this->assertSame( 'odoo_to_wp', $job->direction );
		$this->assertSame( 'order', $job->entity_type );
		$this->assertSame( 100, $job->wp_id );
		$this->assertSame( 200, $job->odoo_id );
		$this->assertSame( 'update', $job->action );
		$this->assertSame( '{"total":50}', $job->payload );
		$this->assertSame( 2, $job->priority );
		$this->assertSame( 'processing', $job->status );
		$this->assertSame( 2, $job->attempts );
		$this->assertSame( 5, $job->max_attempts );
		$this->assertSame( 'Timeout', $job->error_message );
		$this->assertSame( '2025-06-15 14:00:00', $job->scheduled_at );
		$this->assertSame( '2025-06-15 13:00:00', $job->processed_at );
		$this->assertSame( '2025-06-15 12:00:00', $job->created_at );
	}

	public function test_from_row_casts_string_numbers_to_int(): void {
		$row = (object) [
			'id'           => '99',
			'module'       => 'crm',
			'direction'    => 'wp_to_odoo',
			'entity_type'  => 'contact',
			'wp_id'        => '42',
			'odoo_id'      => '0',
			'action'       => 'create',
			'priority'     => '5',
			'status'       => 'pending',
			'attempts'     => '0',
			'max_attempts' => '3',
			'created_at'   => '2025-01-01 00:00:00',
		];

		$job = Queue_Job::from_row( $row );

		$this->assertSame( 99, $job->id );
		$this->assertSame( 42, $job->wp_id );
		$this->assertSame( 0, $job->odoo_id );
		$this->assertSame( 5, $job->priority );
		$this->assertSame( 0, $job->attempts );
		$this->assertSame( 3, $job->max_attempts );
	}

	public function test_from_row_handles_null_optional_fields(): void {
		$row = (object) [
			'id'           => '1',
			'module'       => 'crm',
			'direction'    => 'wp_to_odoo',
			'entity_type'  => 'contact',
			'wp_id'        => '10',
			'odoo_id'      => '0',
			'action'       => 'create',
			'priority'     => '5',
			'status'       => 'pending',
			'attempts'     => '0',
			'max_attempts' => '3',
			'created_at'   => '2025-01-01 00:00:00',
		];

		$job = Queue_Job::from_row( $row );

		$this->assertNull( $job->correlation_id );
		$this->assertNull( $job->payload );
		$this->assertNull( $job->error_message );
		$this->assertNull( $job->scheduled_at );
		$this->assertNull( $job->processed_at );
	}

	public function test_from_row_defaults_missing_required_fields(): void {
		$row = (object) [];

		$job = Queue_Job::from_row( $row );

		$this->assertSame( 0, $job->id );
		$this->assertSame( '', $job->module );
		$this->assertSame( 'wp_to_odoo', $job->direction );
		$this->assertSame( '', $job->entity_type );
		$this->assertSame( 0, $job->wp_id );
		$this->assertSame( 0, $job->odoo_id );
		$this->assertSame( 'update', $job->action );
		$this->assertSame( 5, $job->priority );
		$this->assertSame( 'pending', $job->status );
		$this->assertSame( 0, $job->attempts );
		$this->assertSame( 3, $job->max_attempts );
		$this->assertSame( '', $job->created_at );
	}

	// ─── Readonly enforcement ─────────────────────────────

	public function test_readonly_properties_are_immutable(): void {
		$job = Queue_Job::from_row( (object) [
			'id'           => '1',
			'module'       => 'crm',
			'direction'    => 'wp_to_odoo',
			'entity_type'  => 'contact',
			'wp_id'        => '10',
			'odoo_id'      => '0',
			'action'       => 'create',
			'status'       => 'pending',
			'attempts'     => '0',
			'max_attempts' => '3',
			'created_at'   => '2025-01-01 00:00:00',
		] );

		$this->expectException( \Error::class );
		$this->expectExceptionMessage( 'readonly' );

		// @phpstan-ignore-next-line -- intentional: testing readonly enforcement.
		$job->status = 'failed';
	}

	// ─── Typical Sync_Engine row ──────────────────────────

	public function test_from_row_with_typical_sync_engine_row(): void {
		// Simulates the stdClass shape returned by $wpdb->get_results()
		// in tests like SyncEngineTest.
		$row = (object) [
			'id'             => 1,
			'correlation_id' => null,
			'module'         => 'test',
			'direction'      => 'wp_to_odoo',
			'entity_type'    => 'product',
			'action'         => 'create',
			'wp_id'          => 10,
			'odoo_id'        => 0,
			'payload'        => '{}',
			'priority'       => 5,
			'status'         => 'pending',
			'attempts'       => 0,
			'max_attempts'   => 3,
			'error_message'  => null,
			'scheduled_at'   => null,
			'processed_at'   => null,
			'created_at'     => '2025-01-01 00:00:00',
		];

		$job = Queue_Job::from_row( $row );

		$this->assertSame( 1, $job->id );
		$this->assertSame( 'test', $job->module );
		$this->assertSame( 'wp_to_odoo', $job->direction );
		$this->assertSame( 'product', $job->entity_type );
		$this->assertSame( 'create', $job->action );
		$this->assertSame( 10, $job->wp_id );
		$this->assertSame( 0, $job->odoo_id );
		$this->assertSame( '{}', $job->payload );
		$this->assertSame( 0, $job->attempts );
		$this->assertSame( 3, $job->max_attempts );
	}
}

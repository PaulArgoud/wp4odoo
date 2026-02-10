<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Sync_Queue_Repository;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Sync_Queue_Repository.
 *
 * Tests validation, deduplication, and data assembly logic.
 */
class SyncQueueRepositoryTest extends TestCase {

	private \WP_DB_Stub $wpdb;

	protected function setUp(): void {
		global $wpdb;
		$this->wpdb = new \WP_DB_Stub();
		$wpdb       = $this->wpdb;
	}

	// ─── enqueue() — Validation ────────────────────────────

	public function test_enqueue_returns_false_for_empty_module(): void {
		$result = Sync_Queue_Repository::enqueue( [
			'module'      => '',
			'entity_type' => 'contact',
		] );

		$this->assertFalse( $result );
	}

	public function test_enqueue_returns_false_for_empty_entity_type(): void {
		$result = Sync_Queue_Repository::enqueue( [
			'module'      => 'crm',
			'entity_type' => '',
		] );

		$this->assertFalse( $result );
	}

	// ─── enqueue() — Defaults ──────────────────────────────

	public function test_enqueue_defaults_direction_to_wp_to_odoo(): void {
		$this->wpdb->insert_id = 1;

		Sync_Queue_Repository::enqueue( [
			'module'      => 'crm',
			'entity_type' => 'contact',
			'wp_id'       => 10,
		] );

		$insert = $this->get_last_call( 'insert' );
		$this->assertSame( 'wp_to_odoo', $insert['args'][1]['direction'] );
	}

	public function test_enqueue_accepts_odoo_to_wp_direction(): void {
		$this->wpdb->insert_id = 1;

		Sync_Queue_Repository::enqueue( [
			'module'      => 'crm',
			'entity_type' => 'contact',
			'direction'   => 'odoo_to_wp',
			'odoo_id'     => 5,
		] );

		$insert = $this->get_last_call( 'insert' );
		$this->assertSame( 'odoo_to_wp', $insert['args'][1]['direction'] );
	}

	public function test_enqueue_defaults_action_to_update(): void {
		$this->wpdb->insert_id = 1;

		Sync_Queue_Repository::enqueue( [
			'module'      => 'crm',
			'entity_type' => 'contact',
			'wp_id'       => 10,
		] );

		$insert = $this->get_last_call( 'insert' );
		$this->assertSame( 'update', $insert['args'][1]['action'] );
	}

	public function test_enqueue_accepts_valid_action(): void {
		$this->wpdb->insert_id = 1;

		Sync_Queue_Repository::enqueue( [
			'module'      => 'crm',
			'entity_type' => 'contact',
			'wp_id'       => 10,
			'action'      => 'delete',
		] );

		$insert = $this->get_last_call( 'insert' );
		$this->assertSame( 'delete', $insert['args'][1]['action'] );
	}

	public function test_enqueue_defaults_priority_to_5(): void {
		$this->wpdb->insert_id = 1;

		Sync_Queue_Repository::enqueue( [
			'module'      => 'crm',
			'entity_type' => 'contact',
			'wp_id'       => 10,
		] );

		$insert = $this->get_last_call( 'insert' );
		$this->assertSame( 5, $insert['args'][1]['priority'] );
	}

	// ─── enqueue() — Deduplication ─────────────────────────

	public function test_enqueue_updates_existing_pending_job(): void {
		$this->wpdb->get_var_return = '99';

		$result = Sync_Queue_Repository::enqueue( [
			'module'      => 'crm',
			'entity_type' => 'contact',
			'wp_id'       => 10,
			'action'      => 'create',
		] );

		$this->assertSame( 99, $result );
		$this->assertNotEmpty( $this->get_calls( 'update' ), 'Should call update for dedup' );
		$this->assertEmpty( $this->get_calls( 'insert' ), 'Should not insert when deduplicating' );
	}

	public function test_enqueue_inserts_new_job_when_no_existing(): void {
		$this->wpdb->get_var_return = null;
		$this->wpdb->insert_id     = 42;

		$result = Sync_Queue_Repository::enqueue( [
			'module'      => 'crm',
			'entity_type' => 'contact',
			'wp_id'       => 10,
			'action'      => 'create',
		] );

		$this->assertSame( 42, $result );
		$this->assertNotEmpty( $this->get_calls( 'insert' ), 'Should insert new job' );
	}

	public function test_enqueue_returns_false_on_insert_failure(): void {
		$this->wpdb->get_var_return = null;
		$this->wpdb->insert_id     = 0;

		$result = Sync_Queue_Repository::enqueue( [
			'module'      => 'crm',
			'entity_type' => 'contact',
			'wp_id'       => 10,
		] );

		$this->assertFalse( $result );
	}

	public function test_enqueue_includes_wp_id_in_insert_data(): void {
		$this->wpdb->insert_id = 1;

		Sync_Queue_Repository::enqueue( [
			'module'      => 'crm',
			'entity_type' => 'contact',
			'wp_id'       => 77,
		] );

		$insert = $this->get_last_call( 'insert' );
		$this->assertSame( 77, $insert['args'][1]['wp_id'] );
	}

	public function test_enqueue_includes_odoo_id_in_insert_data(): void {
		$this->wpdb->insert_id = 1;

		Sync_Queue_Repository::enqueue( [
			'module'      => 'sales',
			'entity_type' => 'order',
			'direction'   => 'odoo_to_wp',
			'odoo_id'     => 55,
		] );

		$insert = $this->get_last_call( 'insert' );
		$this->assertSame( 55, $insert['args'][1]['odoo_id'] );
	}

	public function test_enqueue_sets_status_to_pending(): void {
		$this->wpdb->insert_id = 1;

		Sync_Queue_Repository::enqueue( [
			'module'      => 'crm',
			'entity_type' => 'contact',
			'wp_id'       => 10,
		] );

		$insert = $this->get_last_call( 'insert' );
		$this->assertSame( 'pending', $insert['args'][1]['status'] );
	}

	public function test_enqueue_encodes_payload_as_json(): void {
		$this->wpdb->insert_id = 1;

		Sync_Queue_Repository::enqueue( [
			'module'      => 'crm',
			'entity_type' => 'contact',
			'wp_id'       => 10,
			'payload'     => [ 'email' => 'test@example.com' ],
		] );

		$insert  = $this->get_last_call( 'insert' );
		$payload = $insert['args'][1]['payload'];
		$this->assertSame( '{"email":"test@example.com"}', $payload );
	}

	// ─── cancel() ──────────────────────────────────────────

	public function test_cancel_returns_true_when_deleted(): void {
		$this->wpdb->delete_return = 1;
		$this->assertTrue( Sync_Queue_Repository::cancel( 42 ) );
	}

	public function test_cancel_returns_false_when_not_found(): void {
		$this->wpdb->delete_return = 0;
		$this->assertFalse( Sync_Queue_Repository::cancel( 999 ) );
	}

	// ─── Helpers ───────────────────────────────────────────

	/**
	 * Get the last call matching a method name.
	 *
	 * @return array{method: string, args: array}|null
	 */
	private function get_last_call( string $method ): ?array {
		$calls = $this->get_calls( $method );
		return $calls ? end( $calls ) : null;
	}

	/**
	 * Get all calls matching a method name.
	 *
	 * @return array<int, array{method: string, args: array}>
	 */
	private function get_calls( string $method ): array {
		return array_values(
			array_filter( $this->wpdb->calls, fn( $c ) => $c['method'] === $method )
		);
	}
}

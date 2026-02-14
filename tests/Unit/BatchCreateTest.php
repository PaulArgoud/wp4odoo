<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Module_Base;
use WP4Odoo\Sync_Result;
use WP4Odoo\Sync_Engine;
use WP4Odoo\Error_Type;
use PHPUnit\Framework\TestCase;

/**
 * Module stub for batch create testing.
 *
 * Overrides push_to_odoo() and push_batch_creates() to record calls.
 */
class Batch_Mock_Module extends Module_Base {

	/** @var array<int, array> */
	public array $batch_calls = [];

	/** @var array<int, array> */
	public array $individual_calls = [];

	/** @var Sync_Result|null */
	public ?Sync_Result $push_result = null;

	/** @var array<int, Sync_Result>|null */
	public ?array $batch_results = null;

	/** @var \Throwable|null */
	public ?\Throwable $batch_throws = null;

	public function __construct( string $id = 'batch_test' ) {
		parent::__construct( $id, 'Batch Test', wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );
	}

	public function boot(): void {}

	public function get_default_settings(): array {
		return [];
	}

	public function push_to_odoo( string $entity_type, string $action, int $wp_id, int $odoo_id = 0, array $payload = [] ): Sync_Result {
		$this->individual_calls[] = compact( 'entity_type', 'action', 'wp_id' );
		return $this->push_result ?? Sync_Result::success( $wp_id + 1000 );
	}

	public function pull_from_odoo( string $entity_type, string $action, int $odoo_id, int $wp_id = 0, array $payload = [] ): Sync_Result {
		return Sync_Result::success();
	}

	public function push_batch_creates( string $entity_type, array $items ): array {
		$this->batch_calls[] = [ 'entity_type' => $entity_type, 'items' => $items ];

		if ( $this->batch_throws ) {
			throw $this->batch_throws;
		}

		if ( null !== $this->batch_results ) {
			return $this->batch_results;
		}

		// Default: return success for each item.
		$results = [];
		foreach ( $items as $item ) {
			$results[ $item['wp_id'] ] = Sync_Result::success( $item['wp_id'] + 1000 );
		}
		return $results;
	}
}

/**
 * Unit tests for Point 2 — Batch API (push_batch_creates + Sync_Engine grouping).
 */
class BatchCreateTest extends TestCase {

	private \WP_DB_Stub $wpdb;

	protected function setUp(): void {
		global $wpdb;
		$this->wpdb = new \WP_DB_Stub();
		$wpdb       = $this->wpdb;

		$GLOBALS['_wp_options']    = [];
		$GLOBALS['_wp_transients'] = [];
		$GLOBALS['_wp_mail_calls'] = [];

		\WP4Odoo_Plugin::reset_instance();
	}

	protected function tearDown(): void {
		\WP4Odoo_Plugin::reset_instance();
	}

	// ─── Sync_Engine: batch grouping ────────────────────────

	public function test_engine_batches_multiple_creates_for_same_module_entity(): void {
		$this->wpdb->get_var_return = '1';

		$module = new Batch_Mock_Module( 'test' );
		\WP4Odoo_Plugin::instance()->register_module( 'test', $module );

		// 3 create jobs for same module+entity → should batch.
		$jobs = [];
		for ( $i = 1; $i <= 3; $i++ ) {
			$jobs[] = (object) [
				'id'           => $i,
				'module'       => 'test',
				'direction'    => 'wp_to_odoo',
				'entity_type'  => 'product',
				'action'       => 'create',
				'wp_id'        => $i * 10,
				'odoo_id'      => 0,
				'payload'      => '{}',
				'attempts'     => 0,
				'max_attempts' => 3,
			];
		}

		$this->wpdb->get_results_return = $jobs;

		$engine    = new Sync_Engine( wp4odoo_test_module_resolver(), wp4odoo_test_queue_repo(), wp4odoo_test_settings() );
		$processed = $engine->process_queue();

		$this->assertSame( 3, $processed );
		$this->assertCount( 1, $module->batch_calls, 'Should make exactly 1 batch call' );
		$this->assertCount( 3, $module->batch_calls[0]['items'], 'Batch should contain 3 items' );
		$this->assertEmpty( $module->individual_calls, 'No individual push calls' );
	}

	public function test_engine_does_not_batch_single_create(): void {
		$this->wpdb->get_var_return = '1';

		$module = new Batch_Mock_Module( 'test' );
		$module->push_result = Sync_Result::success( 999 );
		\WP4Odoo_Plugin::instance()->register_module( 'test', $module );

		// Only 1 create → goes through individual loop.
		$job = (object) [
			'id'           => 1,
			'module'       => 'test',
			'direction'    => 'wp_to_odoo',
			'entity_type'  => 'product',
			'action'       => 'create',
			'wp_id'        => 10,
			'odoo_id'      => 0,
			'payload'      => '{}',
			'attempts'     => 0,
			'max_attempts' => 3,
		];

		$this->wpdb->get_results_return = [ $job ];

		$engine    = new Sync_Engine( wp4odoo_test_module_resolver(), wp4odoo_test_queue_repo(), wp4odoo_test_settings() );
		$processed = $engine->process_queue();

		$this->assertSame( 1, $processed );
		$this->assertEmpty( $module->batch_calls, 'Should not batch single create' );
		$this->assertCount( 1, $module->individual_calls, 'Should process individually' );
	}

	public function test_engine_does_not_batch_update_jobs(): void {
		$this->wpdb->get_var_return = '1';

		$module = new Batch_Mock_Module( 'test' );
		$module->push_result = Sync_Result::success( 42 );
		\WP4Odoo_Plugin::instance()->register_module( 'test', $module );

		// 3 update jobs → no batching.
		$jobs = [];
		for ( $i = 1; $i <= 3; $i++ ) {
			$jobs[] = (object) [
				'id'           => $i,
				'module'       => 'test',
				'direction'    => 'wp_to_odoo',
				'entity_type'  => 'product',
				'action'       => 'update',
				'wp_id'        => $i * 10,
				'odoo_id'      => $i,
				'payload'      => '{}',
				'attempts'     => 0,
				'max_attempts' => 3,
			];
		}

		$this->wpdb->get_results_return = $jobs;

		$engine    = new Sync_Engine( wp4odoo_test_module_resolver(), wp4odoo_test_queue_repo(), wp4odoo_test_settings() );
		$processed = $engine->process_queue();

		$this->assertSame( 3, $processed );
		$this->assertEmpty( $module->batch_calls, 'Updates should not be batched' );
		$this->assertCount( 3, $module->individual_calls );
	}

	public function test_engine_batches_separate_groups_for_different_entities(): void {
		$this->wpdb->get_var_return = '1';

		$module = new Batch_Mock_Module( 'test' );
		\WP4Odoo_Plugin::instance()->register_module( 'test', $module );

		// 2 product creates + 2 order creates → 2 separate batches.
		$jobs = [];
		for ( $i = 1; $i <= 2; $i++ ) {
			$jobs[] = (object) [
				'id'           => $i,
				'module'       => 'test',
				'direction'    => 'wp_to_odoo',
				'entity_type'  => 'product',
				'action'       => 'create',
				'wp_id'        => $i * 10,
				'odoo_id'      => 0,
				'payload'      => '{}',
				'attempts'     => 0,
				'max_attempts' => 3,
			];
		}
		for ( $i = 3; $i <= 4; $i++ ) {
			$jobs[] = (object) [
				'id'           => $i,
				'module'       => 'test',
				'direction'    => 'wp_to_odoo',
				'entity_type'  => 'order',
				'action'       => 'create',
				'wp_id'        => $i * 10,
				'odoo_id'      => 0,
				'payload'      => '{}',
				'attempts'     => 0,
				'max_attempts' => 3,
			];
		}

		$this->wpdb->get_results_return = $jobs;

		$engine    = new Sync_Engine( wp4odoo_test_module_resolver(), wp4odoo_test_queue_repo(), wp4odoo_test_settings() );
		$processed = $engine->process_queue();

		$this->assertSame( 4, $processed );
		$this->assertCount( 2, $module->batch_calls, 'Should make 2 separate batch calls' );
	}

	public function test_engine_mixed_creates_and_updates_processed_correctly(): void {
		$this->wpdb->get_var_return = '1';

		$module = new Batch_Mock_Module( 'test' );
		$module->push_result = Sync_Result::success( 42 );
		\WP4Odoo_Plugin::instance()->register_module( 'test', $module );

		// 2 creates + 1 update → batch the creates, individual for update.
		$jobs = [
			(object) [
				'id' => 1, 'module' => 'test', 'direction' => 'wp_to_odoo',
				'entity_type' => 'product', 'action' => 'create',
				'wp_id' => 10, 'odoo_id' => 0, 'payload' => '{}',
				'attempts' => 0, 'max_attempts' => 3,
			],
			(object) [
				'id' => 2, 'module' => 'test', 'direction' => 'wp_to_odoo',
				'entity_type' => 'product', 'action' => 'create',
				'wp_id' => 20, 'odoo_id' => 0, 'payload' => '{}',
				'attempts' => 0, 'max_attempts' => 3,
			],
			(object) [
				'id' => 3, 'module' => 'test', 'direction' => 'wp_to_odoo',
				'entity_type' => 'product', 'action' => 'update',
				'wp_id' => 30, 'odoo_id' => 99, 'payload' => '{}',
				'attempts' => 0, 'max_attempts' => 3,
			],
		];

		$this->wpdb->get_results_return = $jobs;

		$engine    = new Sync_Engine( wp4odoo_test_module_resolver(), wp4odoo_test_queue_repo(), wp4odoo_test_settings() );
		$processed = $engine->process_queue();

		$this->assertSame( 3, $processed );
		$this->assertCount( 1, $module->batch_calls, 'Creates batched' );
		$this->assertCount( 1, $module->individual_calls, 'Update processed individually' );
	}

	public function test_engine_does_not_batch_pull_jobs(): void {
		$this->wpdb->get_var_return = '1';

		$module = new Batch_Mock_Module( 'test' );
		\WP4Odoo_Plugin::instance()->register_module( 'test', $module );

		// 3 pull creates → not batched (batch only applies to wp_to_odoo).
		$jobs = [];
		for ( $i = 1; $i <= 3; $i++ ) {
			$jobs[] = (object) [
				'id'           => $i,
				'module'       => 'test',
				'direction'    => 'odoo_to_wp',
				'entity_type'  => 'product',
				'action'       => 'create',
				'wp_id'        => 0,
				'odoo_id'      => $i * 10,
				'payload'      => '{}',
				'attempts'     => 0,
				'max_attempts' => 3,
			];
		}

		$this->wpdb->get_results_return = $jobs;

		$engine    = new Sync_Engine( wp4odoo_test_module_resolver(), wp4odoo_test_queue_repo(), wp4odoo_test_settings() );
		$processed = $engine->process_queue();

		$this->assertSame( 3, $processed );
		$this->assertEmpty( $module->batch_calls, 'Pull jobs should not be batched' );
	}

	public function test_engine_skips_batch_in_dry_run(): void {
		$this->wpdb->get_var_return = '1';

		$module = new Batch_Mock_Module( 'test' );
		\WP4Odoo_Plugin::instance()->register_module( 'test', $module );

		$jobs = [];
		for ( $i = 1; $i <= 3; $i++ ) {
			$jobs[] = (object) [
				'id'           => $i,
				'module'       => 'test',
				'direction'    => 'wp_to_odoo',
				'entity_type'  => 'product',
				'action'       => 'create',
				'wp_id'        => $i * 10,
				'odoo_id'      => 0,
				'payload'      => '{}',
				'attempts'     => 0,
				'max_attempts' => 3,
			];
		}

		$this->wpdb->get_results_return = $jobs;

		$engine = new Sync_Engine( wp4odoo_test_module_resolver(), wp4odoo_test_queue_repo(), wp4odoo_test_settings() );
		$engine->set_dry_run( true );
		$processed = $engine->process_queue();

		$this->assertSame( 3, $processed );
		$this->assertEmpty( $module->batch_calls, 'Batch skipped in dry-run' );
		$this->assertEmpty( $module->individual_calls, 'No individual push in dry-run' );
	}

	// ─── Batch failure → fallback ───────────────────────────

	public function test_engine_handles_batch_failure_per_job(): void {
		$this->wpdb->get_var_return = '1';

		$module = new Batch_Mock_Module( 'test' );
		// Return mixed results: one success, one failure.
		$module->batch_results = [
			10 => Sync_Result::success( 1010 ),
			20 => Sync_Result::failure( 'Bad data.', Error_Type::Permanent ),
		];
		\WP4Odoo_Plugin::instance()->register_module( 'test', $module );

		$jobs = [
			(object) [
				'id' => 1, 'module' => 'test', 'direction' => 'wp_to_odoo',
				'entity_type' => 'product', 'action' => 'create',
				'wp_id' => 10, 'odoo_id' => 0, 'payload' => '{}',
				'attempts' => 0, 'max_attempts' => 3,
			],
			(object) [
				'id' => 2, 'module' => 'test', 'direction' => 'wp_to_odoo',
				'entity_type' => 'product', 'action' => 'create',
				'wp_id' => 20, 'odoo_id' => 0, 'payload' => '{}',
				'attempts' => 0, 'max_attempts' => 3,
			],
		];

		$this->wpdb->get_results_return = $jobs;

		$engine    = new Sync_Engine( wp4odoo_test_module_resolver(), wp4odoo_test_queue_repo(), wp4odoo_test_settings() );
		$processed = $engine->process_queue();

		// Only 1 succeeded.
		$this->assertSame( 1, $processed );
	}
}

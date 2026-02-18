<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Module_Circuit_Breaker;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Module_Circuit_Breaker.
 *
 * Verifies per-module circuit state transitions, isolation between modules,
 * recovery delay, and state management.
 *
 * @package WP4Odoo\Tests\Unit
 * @since   3.6.0
 */
class ModuleCircuitBreakerTest extends TestCase {

	private Module_Circuit_Breaker $breaker;

	protected function setUp(): void {
		global $wpdb;
		$wpdb = new \WP_DB_Stub();

		$GLOBALS['_wp_options'] = [];

		$logger        = new \WP4Odoo\Logger( 'test' );
		$this->breaker = new Module_Circuit_Breaker( $logger );
	}

	// ─── Default state ──────────────────────────────────

	public function test_module_available_by_default(): void {
		$this->assertTrue( $this->breaker->is_module_available( 'crm' ) );
	}

	public function test_get_open_modules_empty_by_default(): void {
		$this->assertEmpty( $this->breaker->get_open_modules() );
	}

	// ─── Failure tracking ───────────────────────────────

	public function test_single_failure_does_not_open_module(): void {
		$this->breaker->record_module_batch( 'crm', 0, 10 );

		$this->assertTrue( $this->breaker->is_module_available( 'crm' ) );
	}

	public function test_four_failures_below_threshold_keeps_module_available(): void {
		for ( $i = 0; $i < 4; $i++ ) {
			$this->breaker->record_module_batch( 'crm', 0, 10 );
		}

		$this->assertTrue( $this->breaker->is_module_available( 'crm' ) );
	}

	public function test_module_opens_after_five_consecutive_high_failure_batches(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->breaker->record_module_batch( 'crm', 1, 9 );
		}

		$this->assertFalse( $this->breaker->is_module_available( 'crm' ) );
	}

	public function test_module_opens_at_exact_threshold(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->breaker->record_module_batch( 'crm', 2, 8 ); // 80% failure ratio.
		}

		$this->assertFalse( $this->breaker->is_module_available( 'crm' ) );
	}

	// ─── Module isolation ───────────────────────────────

	public function test_open_module_does_not_affect_other_modules(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->breaker->record_module_batch( 'crm', 0, 10 );
		}

		$this->assertFalse( $this->breaker->is_module_available( 'crm' ) );
		$this->assertTrue( $this->breaker->is_module_available( 'woocommerce' ) );
		$this->assertTrue( $this->breaker->is_module_available( 'givewp' ) );
	}

	public function test_multiple_modules_can_be_open_simultaneously(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->breaker->record_module_batch( 'crm', 0, 10 );
			$this->breaker->record_module_batch( 'edd', 0, 10 );
		}

		$this->assertFalse( $this->breaker->is_module_available( 'crm' ) );
		$this->assertFalse( $this->breaker->is_module_available( 'edd' ) );

		$open = $this->breaker->get_open_modules();
		$this->assertCount( 2, $open );
		$this->assertArrayHasKey( 'crm', $open );
		$this->assertArrayHasKey( 'edd', $open );
	}

	// ─── Recovery ───────────────────────────────────────

	public function test_success_below_ratio_resets_failure_counter(): void {
		for ( $i = 0; $i < 4; $i++ ) {
			$this->breaker->record_module_batch( 'crm', 0, 10 );
		}

		// A healthy batch resets the counter.
		$this->breaker->record_module_batch( 'crm', 8, 2 );

		// Four more failures should not open (counter was reset).
		for ( $i = 0; $i < 4; $i++ ) {
			$this->breaker->record_module_batch( 'crm', 0, 10 );
		}

		$this->assertTrue( $this->breaker->is_module_available( 'crm' ) );
	}

	public function test_success_closes_open_module(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->breaker->record_module_batch( 'crm', 0, 10 );
		}
		$this->assertFalse( $this->breaker->is_module_available( 'crm' ) );

		// A healthy batch closes the module.
		$this->breaker->record_module_batch( 'crm', 8, 2 );
		$this->assertTrue( $this->breaker->is_module_available( 'crm' ) );
	}

	public function test_half_open_allows_probe_after_recovery_delay(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->breaker->record_module_batch( 'crm', 0, 10 );
		}
		$this->assertFalse( $this->breaker->is_module_available( 'crm' ) );

		// Simulate recovery delay elapsed by backdating opened_at.
		$states        = get_option( Module_Circuit_Breaker::OPT_MODULE_CB_STATES, [] );
		$states['crm']['opened_at'] = time() - 601;
		update_option( Module_Circuit_Breaker::OPT_MODULE_CB_STATES, $states, false );

		// Force reload by creating a new instance.
		$breaker = new Module_Circuit_Breaker( new \WP4Odoo\Logger( 'test' ) );
		$this->assertTrue( $breaker->is_module_available( 'crm' ) );
	}

	// ─── Reset ──────────────────────────────────────────

	public function test_reset_module_closes_open_module(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->breaker->record_module_batch( 'crm', 0, 10 );
		}
		$this->assertFalse( $this->breaker->is_module_available( 'crm' ) );

		$this->breaker->reset_module( 'crm' );
		$this->assertTrue( $this->breaker->is_module_available( 'crm' ) );
	}

	public function test_reset_module_does_not_affect_other_modules(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->breaker->record_module_batch( 'crm', 0, 10 );
			$this->breaker->record_module_batch( 'edd', 0, 10 );
		}

		$this->breaker->reset_module( 'crm' );

		$this->assertTrue( $this->breaker->is_module_available( 'crm' ) );
		$this->assertFalse( $this->breaker->is_module_available( 'edd' ) );
	}

	public function test_reset_nonexistent_module_is_noop(): void {
		$this->breaker->reset_module( 'nonexistent' );
		$this->assertTrue( $this->breaker->is_module_available( 'nonexistent' ) );
	}

	// ─── get_open_modules() ─────────────────────────────

	public function test_get_open_modules_returns_only_open_modules(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->breaker->record_module_batch( 'crm', 0, 10 );
		}
		// woocommerce has failures but not enough to open.
		for ( $i = 0; $i < 3; $i++ ) {
			$this->breaker->record_module_batch( 'woocommerce', 0, 10 );
		}

		$open = $this->breaker->get_open_modules();
		$this->assertCount( 1, $open );
		$this->assertArrayHasKey( 'crm', $open );
	}

	// ─── Stale state cleanup ────────────────────────────

	public function test_stale_state_older_than_two_hours_is_auto_cleaned(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->breaker->record_module_batch( 'crm', 0, 10 );
		}
		$this->assertFalse( $this->breaker->is_module_available( 'crm' ) );

		// Simulate >2h elapsed.
		$states        = get_option( Module_Circuit_Breaker::OPT_MODULE_CB_STATES, [] );
		$states['crm']['opened_at'] = time() - ( 2 * HOUR_IN_SECONDS + 1 );
		update_option( Module_Circuit_Breaker::OPT_MODULE_CB_STATES, $states, false );

		// Force reload.
		$breaker = new Module_Circuit_Breaker( new \WP4Odoo\Logger( 'test' ) );
		$this->assertTrue( $breaker->is_module_available( 'crm' ) );
	}

	// ─── Ratio-based detection ──────────────────────────

	public function test_batch_below_failure_ratio_resets_counter(): void {
		for ( $i = 0; $i < 4; $i++ ) {
			$this->breaker->record_module_batch( 'crm', 1, 9 ); // 90% failure.
		}

		// Batch with 70% failure (below 80% threshold) = success.
		$this->breaker->record_module_batch( 'crm', 3, 7 );

		// Counter was reset, so 4 more failures should not open.
		for ( $i = 0; $i < 4; $i++ ) {
			$this->breaker->record_module_batch( 'crm', 1, 9 );
		}

		$this->assertTrue( $this->breaker->is_module_available( 'crm' ) );
	}

	public function test_empty_batch_is_ignored(): void {
		$this->breaker->record_module_batch( 'crm', 0, 0 );
		$this->assertTrue( $this->breaker->is_module_available( 'crm' ) );
		$this->assertEmpty( $this->breaker->get_open_modules() );
	}

	// ─── State persistence ──────────────────────────────

	public function test_state_persisted_to_wp_options(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->breaker->record_module_batch( 'crm', 0, 10 );
		}

		$stored = get_option( Module_Circuit_Breaker::OPT_MODULE_CB_STATES, [] );
		$this->assertArrayHasKey( 'crm', $stored );
		$this->assertGreaterThanOrEqual( 5, $stored['crm']['failures'] );
		$this->assertGreaterThan( 0, $stored['crm']['opened_at'] );
	}

	public function test_state_deleted_from_options_when_all_modules_closed(): void {
		$this->breaker->record_module_batch( 'crm', 0, 10 );
		$this->breaker->record_module_batch( 'crm', 8, 2 ); // Success resets.

		$stored = get_option( Module_Circuit_Breaker::OPT_MODULE_CB_STATES, 'DELETED' );
		$this->assertSame( 'DELETED', $stored );
	}
}

<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Advisory_Lock;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Advisory_Lock.
 *
 * Uses WP_DB_Stub to verify GET_LOCK/RELEASE_LOCK queries
 * and state tracking (is_held, acquire, release).
 */
class AdvisoryLockTest extends TestCase {

	private \WP_DB_Stub $wpdb;

	protected function setUp(): void {
		global $wpdb;
		$this->wpdb = new \WP_DB_Stub();
		$wpdb       = $this->wpdb;
	}

	// ─── Constructor ──────────────────────────────────────

	public function test_constructor_sets_name_and_default_timeout(): void {
		$lock = new Advisory_Lock( 'wp4odoo_test_lock' );

		$this->assertSame( 'wp4odoo_test_lock', $lock->get_name() );
		$this->assertFalse( $lock->is_held() );
	}

	// ─── acquire() ────────────────────────────────────────

	public function test_acquire_returns_true_on_success(): void {
		$this->wpdb->lock_return = '1';

		$lock = new Advisory_Lock( 'wp4odoo_test_lock' );

		$this->assertTrue( $lock->acquire() );
		$this->assertTrue( $lock->is_held() );
	}

	public function test_acquire_returns_false_on_timeout(): void {
		$this->wpdb->lock_return = '0';

		$lock = new Advisory_Lock( 'wp4odoo_test_lock' );

		$this->assertFalse( $lock->acquire() );
		$this->assertFalse( $lock->is_held() );
	}

	public function test_acquire_returns_false_on_empty_string(): void {
		// Simulates GET_LOCK returning an unexpected value (neither '0' nor '1').
		$this->wpdb->lock_return = '';

		$lock = new Advisory_Lock( 'wp4odoo_test_lock' );

		$this->assertFalse( $lock->acquire() );
		$this->assertFalse( $lock->is_held() );
	}

	public function test_acquire_sends_correct_query(): void {
		$this->wpdb->lock_return = '1';

		$lock = new Advisory_Lock( 'wp4odoo_test_lock', 10 );
		$lock->acquire();

		$prepare_calls = array_values(
			array_filter( $this->wpdb->calls, fn( $c ) => 'prepare' === $c['method'] )
		);

		$this->assertNotEmpty( $prepare_calls );

		$found = false;
		foreach ( $prepare_calls as $call ) {
			if ( str_contains( $call['args'][0], 'GET_LOCK' ) ) {
				$this->assertSame( 'wp4odoo_test_lock', $call['args'][1] );
				$this->assertSame( 10, $call['args'][2] );
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, 'GET_LOCK query should be sent with correct name and timeout.' );
	}

	// ─── release() ────────────────────────────────────────

	public function test_release_sends_query_when_held(): void {
		$this->wpdb->lock_return = '1';

		$lock = new Advisory_Lock( 'wp4odoo_test_lock' );
		$lock->acquire();

		$this->wpdb->calls = [];

		$lock->release();

		$this->assertFalse( $lock->is_held() );

		$prepare_calls = array_values(
			array_filter( $this->wpdb->calls, fn( $c ) => 'prepare' === $c['method'] )
		);

		$found = false;
		foreach ( $prepare_calls as $call ) {
			if ( str_contains( $call['args'][0], 'RELEASE_LOCK' ) ) {
				$this->assertSame( 'wp4odoo_test_lock', $call['args'][1] );
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, 'RELEASE_LOCK query should be sent when lock is held.' );
	}

	public function test_release_is_noop_when_not_held(): void {
		$lock = new Advisory_Lock( 'wp4odoo_test_lock' );

		$this->wpdb->calls = [];

		$lock->release();

		$this->assertFalse( $lock->is_held() );

		// No queries should be sent.
		$query_calls = array_filter(
			$this->wpdb->calls,
			fn( $c ) => 'query' === $c['method'] || 'get_var' === $c['method']
		);
		$this->assertEmpty( $query_calls, 'No DB calls when releasing an unheld lock.' );
	}

	public function test_release_clears_held_state(): void {
		$this->wpdb->lock_return = '1';

		$lock = new Advisory_Lock( 'wp4odoo_test_lock' );
		$lock->acquire();
		$this->assertTrue( $lock->is_held() );

		$lock->release();
		$this->assertFalse( $lock->is_held() );
	}

	public function test_double_release_is_safe(): void {
		$this->wpdb->lock_return = '1';

		$lock = new Advisory_Lock( 'wp4odoo_test_lock' );
		$lock->acquire();

		$lock->release();
		$this->wpdb->calls = [];

		// Second release should be a no-op.
		$lock->release();

		$query_calls = array_filter(
			$this->wpdb->calls,
			fn( $c ) => 'query' === $c['method'] || 'get_var' === $c['method']
		);
		$this->assertEmpty( $query_calls, 'Second release should not send any queries.' );
	}

	// ─── is_held() ────────────────────────────────────────

	public function test_is_held_returns_false_initially(): void {
		$lock = new Advisory_Lock( 'wp4odoo_test_lock' );
		$this->assertFalse( $lock->is_held() );
	}

	public function test_is_held_returns_true_after_acquire(): void {
		$this->wpdb->lock_return = '1';

		$lock = new Advisory_Lock( 'wp4odoo_test_lock' );
		$lock->acquire();

		$this->assertTrue( $lock->is_held() );
	}

	public function test_is_held_returns_false_after_failed_acquire(): void {
		$this->wpdb->lock_return = '0';

		$lock = new Advisory_Lock( 'wp4odoo_test_lock' );
		$lock->acquire();

		$this->assertFalse( $lock->is_held() );
	}

	// ─── get_name() ───────────────────────────────────────

	public function test_get_name_returns_constructor_name(): void {
		$lock = new Advisory_Lock( 'wp4odoo_sync_woocommerce' );
		$this->assertSame( 'wp4odoo_sync_woocommerce', $lock->get_name() );
	}

	// ─── Custom timeout ───────────────────────────────────

	public function test_custom_timeout_is_passed_to_query(): void {
		$this->wpdb->lock_return = '1';

		$lock = new Advisory_Lock( 'wp4odoo_test_lock', 30 );
		$lock->acquire();

		$prepare_calls = array_values(
			array_filter( $this->wpdb->calls, fn( $c ) => 'prepare' === $c['method'] )
		);

		$found = false;
		foreach ( $prepare_calls as $call ) {
			if ( str_contains( $call['args'][0], 'GET_LOCK' ) ) {
				$this->assertSame( 30, $call['args'][2] );
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, 'Custom timeout should be passed to GET_LOCK.' );
	}

	// ─── Acquire-release cycle ────────────────────────────

	public function test_full_acquire_release_cycle(): void {
		$this->wpdb->lock_return = '1';

		$lock = new Advisory_Lock( 'wp4odoo_cycle_test' );

		$this->assertFalse( $lock->is_held() );

		$acquired = $lock->acquire();
		$this->assertTrue( $acquired );
		$this->assertTrue( $lock->is_held() );

		$lock->release();
		$this->assertFalse( $lock->is_held() );

		// Can re-acquire after release.
		$acquired2 = $lock->acquire();
		$this->assertTrue( $acquired2 );
		$this->assertTrue( $lock->is_held() );

		$lock->release();
		$this->assertFalse( $lock->is_held() );
	}
}

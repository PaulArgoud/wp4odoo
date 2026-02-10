<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\CPT_Helper;
use WP4Odoo\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CPT_Helper.
 *
 * Tests CPT registration, post loading (with type check), and saving
 * (new + update, Many2one resolution, error handling).
 */
class CPTHelperTest extends TestCase {

	private \WP_DB_Stub $wpdb;

	protected function setUp(): void {
		global $wpdb;
		$this->wpdb = new \WP_DB_Stub();
		$wpdb       = $this->wpdb;

		$GLOBALS['_wp_options'] = [];
		$GLOBALS['_wp_posts']   = [];
	}

	// ─── register ─────────────────────────────────────────────

	public function test_register_does_not_error(): void {
		CPT_Helper::register( 'wp4odoo_test', [ 'name' => 'Test' ] );
		$this->assertTrue( true ); // No exception thrown.
	}

	// ─── load ─────────────────────────────────────────────────

	public function test_load_returns_empty_for_nonexistent_post(): void {
		$data = CPT_Helper::load( 999, 'wp4odoo_order', [ '_total' => '_total' ] );
		$this->assertEmpty( $data );
	}

	public function test_load_returns_data_for_matching_post(): void {
		$GLOBALS['_wp_posts'][1] = (object) [
			'ID'         => 1,
			'post_type'  => 'wp4odoo_order',
			'post_title' => 'ORD-001',
		];

		$data = CPT_Helper::load( 1, 'wp4odoo_order', [ '_order_total' => '_order_total' ] );

		$this->assertSame( 'ORD-001', $data['post_title'] );
		$this->assertArrayHasKey( '_order_total', $data );
	}

	public function test_load_returns_empty_for_wrong_post_type(): void {
		$GLOBALS['_wp_posts'][2] = (object) [
			'ID'         => 2,
			'post_type'  => 'wp4odoo_invoice',
			'post_title' => 'INV-001',
		];

		$data = CPT_Helper::load( 2, 'wp4odoo_order', [ '_total' => '_total' ] );
		$this->assertEmpty( $data );
	}

	public function test_load_with_empty_meta_fields(): void {
		$GLOBALS['_wp_posts'][3] = (object) [
			'ID'         => 3,
			'post_type'  => 'wp4odoo_order',
			'post_title' => 'ORD-002',
		];

		$data = CPT_Helper::load( 3, 'wp4odoo_order', [] );

		$this->assertSame( 'ORD-002', $data['post_title'] );
		$this->assertCount( 1, $data ); // Only post_title.
	}

	// ─── save (new post) ─────────────────────────────────────

	public function test_save_creates_new_post(): void {
		$data = [ 'post_title' => 'ORD-100' ];

		$post_id = CPT_Helper::save( $data, 0, 'wp4odoo_order', [ '_total' => '_total' ], 'Order' );

		$this->assertGreaterThan( 0, $post_id );
	}

	public function test_save_uses_default_title_when_missing(): void {
		$data = [ '_order_total' => '100.00' ];

		$post_id = CPT_Helper::save( $data, 0, 'wp4odoo_order', [ '_order_total' => '_order_total' ], 'Order' );

		$this->assertGreaterThan( 0, $post_id );
	}

	// ─── save (update) ───────────────────────────────────────

	public function test_save_updates_existing_post(): void {
		$data = [ 'post_title' => 'ORD-200' ];

		$post_id = CPT_Helper::save( $data, 42, 'wp4odoo_order', [], 'Order' );

		$this->assertSame( 42, $post_id );
	}

	// ─── save (Many2one resolution) ──────────────────────────

	public function test_save_resolves_partner_id_many2one(): void {
		$data = [
			'post_title'          => 'ORD-300',
			'_wp4odoo_partner_id' => [ 42, 'John Doe' ],
		];

		$post_id = CPT_Helper::save(
			$data,
			0,
			'wp4odoo_order',
			[ '_wp4odoo_partner_id' => '_wp4odoo_partner_id' ],
			'Order'
		);

		$this->assertGreaterThan( 0, $post_id );
	}

	public function test_save_handles_scalar_partner_id(): void {
		$data = [
			'post_title'          => 'ORD-400',
			'_wp4odoo_partner_id' => 42,
		];

		$post_id = CPT_Helper::save(
			$data,
			0,
			'wp4odoo_order',
			[ '_wp4odoo_partner_id' => '_wp4odoo_partner_id' ],
			'Order'
		);

		$this->assertGreaterThan( 0, $post_id );
	}

	// ─── save (with logger) ─────────────────────────────────

	public function test_save_with_logger_succeeds(): void {
		$logger = new Logger( 'test' );
		$data   = [ 'post_title' => 'ORD-500' ];

		$post_id = CPT_Helper::save( $data, 0, 'wp4odoo_order', [], 'Order', $logger );

		$this->assertGreaterThan( 0, $post_id );
	}
}

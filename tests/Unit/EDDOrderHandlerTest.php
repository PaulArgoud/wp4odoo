<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Modules\EDD_Order_Handler;
use WP4Odoo\Logger;
use WP4Odoo\Partner_Service;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EDD_Order_Handler.
 *
 * Tests load/save operations and EDD ↔ Odoo status mapping.
 */
class EDDOrderHandlerTest extends TestCase {

	private EDD_Order_Handler $handler;
	private \WP_DB_Stub $wpdb;

	protected function setUp(): void {
		global $wpdb;
		$this->wpdb = new \WP_DB_Stub();
		$wpdb       = $this->wpdb;

		$GLOBALS['_wp_options']                         = [];
		$GLOBALS['_wp_options']['wp4odoo_log_settings'] = [ 'enabled' => true, 'level' => 'debug' ];
		$GLOBALS['_edd_orders']                         = [];

		$logger          = new Logger( 'test' );
		$partner_service = $this->createMock( Partner_Service::class );

		$this->handler = new EDD_Order_Handler( $logger, $partner_service );
	}

	protected function tearDown(): void {
		$GLOBALS['_edd_orders'] = [];
	}

	// ─── Load ────────────────────────────────────────────────

	public function test_load_returns_empty_when_order_not_found(): void {
		$this->assertSame( [], $this->handler->load( 999 ) );
	}

	public function test_load_returns_data_for_valid_order(): void {
		$order               = new \EDD\Orders\Order();
		$order->id           = 42;
		$order->total        = 99.50;
		$order->date_created = '2026-01-15 10:30:00';
		$order->status       = 'complete';
		$order->email        = 'buyer@example.com';
		$order->currency     = 'EUR';

		$GLOBALS['_edd_orders'][42] = $order;

		$result = $this->handler->load( 42 );

		$this->assertSame( '99.5', $result['total'] );
		$this->assertSame( '2026-01-15 10:30:00', $result['date_created'] );
		$this->assertSame( 'sale', $result['status'] ); // Mapped: complete → sale.
	}

	// ─── Save ────────────────────────────────────────────────

	public function test_save_returns_zero_when_wp_id_zero(): void {
		$this->assertSame( 0, $this->handler->save( [ 'status' => 'sale' ], 0 ) );
	}

	public function test_save_returns_zero_when_order_not_found(): void {
		$this->assertSame( 0, $this->handler->save( [ 'status' => 'sale' ], 42 ) );
	}

	public function test_save_updates_order_status(): void {
		$order         = new \EDD\Orders\Order();
		$order->id     = 50;
		$order->status = 'pending';

		$GLOBALS['_edd_orders'][50] = $order;

		$result = $this->handler->save( [ 'status' => 'sale' ], 50 );

		$this->assertSame( 50, $result );
		$this->assertSame( 'complete', $GLOBALS['_edd_orders'][50]->status );
	}

	// ─── EDD → Odoo Status Mapping ─────────────────────────

	public function test_map_edd_pending_to_draft(): void {
		$this->assertSame( 'draft', $this->handler->map_edd_status_to_odoo( 'pending' ) );
	}

	public function test_map_edd_complete_to_sale(): void {
		$this->assertSame( 'sale', $this->handler->map_edd_status_to_odoo( 'complete' ) );
	}

	public function test_map_edd_failed_to_cancel(): void {
		$this->assertSame( 'cancel', $this->handler->map_edd_status_to_odoo( 'failed' ) );
	}

	public function test_map_edd_refunded_to_cancel(): void {
		$this->assertSame( 'cancel', $this->handler->map_edd_status_to_odoo( 'refunded' ) );
	}

	public function test_map_edd_abandoned_to_cancel(): void {
		$this->assertSame( 'cancel', $this->handler->map_edd_status_to_odoo( 'abandoned' ) );
	}

	public function test_map_edd_revoked_to_cancel(): void {
		$this->assertSame( 'cancel', $this->handler->map_edd_status_to_odoo( 'revoked' ) );
	}

	public function test_map_unknown_edd_status_to_draft(): void {
		$this->assertSame( 'draft', $this->handler->map_edd_status_to_odoo( 'nonexistent' ) );
	}

	// ─── Odoo → EDD Status Mapping ─────────────────────────

	public function test_map_odoo_draft_to_pending(): void {
		$this->assertSame( 'pending', $this->handler->map_odoo_status_to_edd( 'draft' ) );
	}

	public function test_map_odoo_sale_to_complete(): void {
		$this->assertSame( 'complete', $this->handler->map_odoo_status_to_edd( 'sale' ) );
	}

	public function test_map_odoo_done_to_complete(): void {
		$this->assertSame( 'complete', $this->handler->map_odoo_status_to_edd( 'done' ) );
	}

	public function test_map_odoo_cancel_to_failed(): void {
		$this->assertSame( 'failed', $this->handler->map_odoo_status_to_edd( 'cancel' ) );
	}

	public function test_map_unknown_odoo_status_to_pending(): void {
		$this->assertSame( 'pending', $this->handler->map_odoo_status_to_edd( 'nonexistent' ) );
	}
}

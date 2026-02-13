<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Logger;
use WP4Odoo\Modules\Shipment_Handler;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Shipment_Handler.
 *
 * Tests shipment tracking pull, AST format, change detection, and edge cases.
 * Uses WC class stubs from bootstrap.php.
 */
class ShipmentHandlerTest extends TestCase {

	private Shipment_Handler $handler;
	private \WP_DB_Stub $wpdb;
	private object $stub_client;

	protected function setUp(): void {
		global $wpdb;
		$this->wpdb = new \WP_DB_Stub();
		$wpdb       = $this->wpdb;

		$GLOBALS['_wp_post_meta'] = [];
		$GLOBALS['_wp_options']   = [];
		$GLOBALS['_wc_orders']    = [];

		$this->stub_client = new class {
			/** @var array<int, array<string, mixed>> */
			public array $search_read_return = [];

			/** @return array<int, array<string, mixed>> */
			public function search_read( string $model, array $domain, array $fields = [] ): array {
				return $this->search_read_return;
			}

			/** @return array<int, array<string, mixed>> */
			public function read( string $model, array $ids, array $fields = [] ): array {
				return [];
			}
		};

		$logger = new Logger( 'woocommerce' );

		$this->handler = new Shipment_Handler(
			$logger,
			fn() => $this->stub_client
		);
	}

	// ─── Instantiation ──────────────────────────────────────

	public function test_can_be_instantiated(): void {
		$this->assertInstanceOf( Shipment_Handler::class, $this->handler );
	}

	// ─── pull_shipments ──────────────────────────────────────

	public function test_pull_returns_true_with_no_pickings(): void {
		$this->stub_client->search_read_return = [];
		$GLOBALS['_wc_orders'][100] = [ 'status' => 'completed' ];

		$this->assertTrue( $this->handler->pull_shipments( 50, 100 ) );
	}

	public function test_pull_returns_false_on_odoo_error(): void {
		$handler = new Shipment_Handler(
			new Logger( 'woocommerce' ),
			fn() => throw new \RuntimeException( 'Connection failed' )
		);

		$this->assertFalse( $handler->pull_shipments( 50, 100 ) );
	}

	public function test_pull_filters_pickings_without_tracking_ref(): void {
		$this->stub_client->search_read_return = [
			[
				'name'                 => 'WH/OUT/00001',
				'carrier_tracking_ref' => false,
				'carrier_id'           => [ 1, 'FedEx' ],
				'date_done'            => '2024-06-15 10:00:00',
			],
		];
		$GLOBALS['_wc_orders'][100] = [ 'status' => 'completed' ];

		// No valid pickings → returns true but no tracking saved.
		$this->assertTrue( $this->handler->pull_shipments( 50, 100 ) );
	}

	public function test_pull_saves_tracking_for_valid_pickings(): void {
		$this->stub_client->search_read_return = [
			[
				'name'                 => 'WH/OUT/00001',
				'carrier_tracking_ref' => 'TRACK123',
				'carrier_id'           => [ 1, 'FedEx' ],
				'date_done'            => '2024-06-15 10:00:00',
			],
		];

		$order = new \WC_Order( 100 );
		$GLOBALS['_wc_orders'][100] = $order;

		$result = $this->handler->pull_shipments( 50, 100 );

		$this->assertTrue( $result );

		// Verify AST meta was saved.
		$tracking = $order->get_meta( '_wc_shipment_tracking_items' );
		$this->assertIsArray( $tracking );
		$this->assertCount( 1, $tracking );
		$this->assertSame( 'TRACK123', $tracking[0]['tracking_number'] );
		$this->assertSame( 'FedEx', $tracking[0]['tracking_provider'] );
	}

	public function test_pull_saves_multiple_pickings(): void {
		$this->stub_client->search_read_return = [
			[
				'name'                 => 'WH/OUT/00001',
				'carrier_tracking_ref' => 'TRACK-A',
				'carrier_id'           => [ 1, 'UPS' ],
				'date_done'            => '2024-06-15 10:00:00',
			],
			[
				'name'                 => 'WH/OUT/00002',
				'carrier_tracking_ref' => 'TRACK-B',
				'carrier_id'           => [ 2, 'DHL' ],
				'date_done'            => '2024-06-16 14:00:00',
			],
		];

		$order = new \WC_Order( 100 );
		$GLOBALS['_wc_orders'][100] = $order;

		$this->assertTrue( $this->handler->pull_shipments( 50, 100 ) );

		$tracking = $order->get_meta( '_wc_shipment_tracking_items' );
		$this->assertCount( 2, $tracking );
	}

	// ─── save_tracking ───────────────────────────────────────

	public function test_save_stores_in_ast_meta(): void {
		$order = new \WC_Order( 100 );
		$GLOBALS['_wc_orders'][100] = $order;

		$items = [
			[
				'tracking_provider'        => 'FedEx',
				'custom_tracking_provider' => '',
				'custom_tracking_link'     => '',
				'tracking_number'          => 'ABC123',
				'date_shipped'             => '1718445600',
				'status_shipped'           => 1,
			],
		];

		$result = $this->handler->save_tracking( 100, $items );

		$this->assertTrue( $result );
		$this->assertSame( $items, $order->get_meta( '_wc_shipment_tracking_items' ) );
	}

	public function test_save_stores_in_own_meta(): void {
		$order = new \WC_Order( 100 );
		$GLOBALS['_wc_orders'][100] = $order;

		$items = [
			[
				'tracking_provider'        => 'DHL',
				'custom_tracking_provider' => '',
				'custom_tracking_link'     => '',
				'tracking_number'          => 'DHL789',
				'date_shipped'             => '1718445600',
				'status_shipped'           => 1,
			],
		];

		$this->handler->save_tracking( 100, $items );

		$this->assertSame( $items, $order->get_meta( '_wp4odoo_shipment_tracking' ) );
	}

	public function test_save_stores_hash_meta(): void {
		$order = new \WC_Order( 100 );
		$GLOBALS['_wc_orders'][100] = $order;

		$items = [
			[
				'tracking_provider'        => 'FedEx',
				'custom_tracking_provider' => '',
				'custom_tracking_link'     => '',
				'tracking_number'          => 'ABC123',
				'date_shipped'             => '1718445600',
				'status_shipped'           => 1,
			],
		];

		$this->handler->save_tracking( 100, $items );

		$hash = $order->get_meta( '_wp4odoo_shipment_hash' );
		$this->assertNotEmpty( $hash );
		$this->assertSame( 64, strlen( $hash ) ); // SHA-256 hex length.
	}

	public function test_save_returns_false_when_hash_unchanged(): void {
		$order = new \WC_Order( 100 );
		$GLOBALS['_wc_orders'][100] = $order;

		$items = [
			[
				'tracking_provider'        => 'FedEx',
				'custom_tracking_provider' => '',
				'custom_tracking_link'     => '',
				'tracking_number'          => 'ABC123',
				'date_shipped'             => '1718445600',
				'status_shipped'           => 1,
			],
		];

		// First save.
		$this->assertTrue( $this->handler->save_tracking( 100, $items ) );

		// Second save with same data → unchanged.
		$this->assertFalse( $this->handler->save_tracking( 100, $items ) );
	}

	public function test_save_returns_true_when_hash_changed(): void {
		$order = new \WC_Order( 100 );
		$GLOBALS['_wc_orders'][100] = $order;

		$items_v1 = [
			[
				'tracking_provider'        => 'FedEx',
				'custom_tracking_provider' => '',
				'custom_tracking_link'     => '',
				'tracking_number'          => 'ABC123',
				'date_shipped'             => '1718445600',
				'status_shipped'           => 1,
			],
		];

		$items_v2 = [
			[
				'tracking_provider'        => 'FedEx',
				'custom_tracking_provider' => '',
				'custom_tracking_link'     => '',
				'tracking_number'          => 'ABC123',
				'date_shipped'             => '1718445600',
				'status_shipped'           => 1,
			],
			[
				'tracking_provider'        => 'UPS',
				'custom_tracking_provider' => '',
				'custom_tracking_link'     => '',
				'tracking_number'          => 'UPS456',
				'date_shipped'             => '1718532000',
				'status_shipped'           => 1,
			],
		];

		$this->assertTrue( $this->handler->save_tracking( 100, $items_v1 ) );
		$this->assertTrue( $this->handler->save_tracking( 100, $items_v2 ) );
	}

	public function test_save_returns_false_for_missing_order(): void {
		$this->assertFalse( $this->handler->save_tracking( 999, [] ) );
	}

	// ─── Tracking item format ────────────────────────────────

	public function test_tracking_item_extracts_carrier_from_many2one(): void {
		$this->stub_client->search_read_return = [
			[
				'name'                 => 'WH/OUT/00001',
				'carrier_tracking_ref' => 'TRACK-XYZ',
				'carrier_id'           => [ 5, 'Colissimo' ],
				'date_done'            => '2024-06-15 10:30:00',
			],
		];

		$order = new \WC_Order( 100 );
		$GLOBALS['_wc_orders'][100] = $order;

		$this->handler->pull_shipments( 50, 100 );

		$tracking = $order->get_meta( '_wc_shipment_tracking_items' );
		$this->assertSame( 'Colissimo', $tracking[0]['tracking_provider'] );
	}

	public function test_tracking_item_handles_missing_carrier(): void {
		$this->stub_client->search_read_return = [
			[
				'name'                 => 'WH/OUT/00001',
				'carrier_tracking_ref' => 'TRACK-ABC',
				'carrier_id'           => false,
				'date_done'            => '2024-06-15 10:00:00',
			],
		];

		$order = new \WC_Order( 100 );
		$GLOBALS['_wc_orders'][100] = $order;

		$this->handler->pull_shipments( 50, 100 );

		$tracking = $order->get_meta( '_wc_shipment_tracking_items' );
		$this->assertSame( '', $tracking[0]['tracking_provider'] );
	}

	public function test_tracking_item_converts_date_to_timestamp(): void {
		$this->stub_client->search_read_return = [
			[
				'name'                 => 'WH/OUT/00001',
				'carrier_tracking_ref' => 'TRACK-DATE',
				'carrier_id'           => false,
				'date_done'            => '2024-06-15 10:00:00',
			],
		];

		$order = new \WC_Order( 100 );
		$GLOBALS['_wc_orders'][100] = $order;

		$this->handler->pull_shipments( 50, 100 );

		$tracking  = $order->get_meta( '_wc_shipment_tracking_items' );
		$timestamp = (int) $tracking[0]['date_shipped'];
		$this->assertGreaterThan( 0, $timestamp );

		// 2024-06-15 10:00:00 UTC = 1718445600.
		$this->assertSame( 1718445600, $timestamp );
	}

	public function test_tracking_item_handles_missing_date(): void {
		$this->stub_client->search_read_return = [
			[
				'name'                 => 'WH/OUT/00001',
				'carrier_tracking_ref' => 'TRACK-NODATE',
				'carrier_id'           => false,
				'date_done'            => false,
			],
		];

		$order = new \WC_Order( 100 );
		$GLOBALS['_wc_orders'][100] = $order;

		$this->handler->pull_shipments( 50, 100 );

		$tracking = $order->get_meta( '_wc_shipment_tracking_items' );
		$this->assertSame( '0', $tracking[0]['date_shipped'] );
	}

	public function test_tracking_item_has_correct_ast_format(): void {
		$this->stub_client->search_read_return = [
			[
				'name'                 => 'WH/OUT/00001',
				'carrier_tracking_ref' => 'FDX-999',
				'carrier_id'           => [ 1, 'FedEx' ],
				'date_done'            => '2024-01-01 12:00:00',
			],
		];

		$order = new \WC_Order( 100 );
		$GLOBALS['_wc_orders'][100] = $order;

		$this->handler->pull_shipments( 50, 100 );

		$tracking = $order->get_meta( '_wc_shipment_tracking_items' );
		$item     = $tracking[0];

		// Verify all AST keys exist.
		$this->assertArrayHasKey( 'tracking_provider', $item );
		$this->assertArrayHasKey( 'custom_tracking_provider', $item );
		$this->assertArrayHasKey( 'custom_tracking_link', $item );
		$this->assertArrayHasKey( 'tracking_number', $item );
		$this->assertArrayHasKey( 'date_shipped', $item );
		$this->assertArrayHasKey( 'status_shipped', $item );

		$this->assertSame( 'FedEx', $item['tracking_provider'] );
		$this->assertSame( '', $item['custom_tracking_provider'] );
		$this->assertSame( '', $item['custom_tracking_link'] );
		$this->assertSame( 'FDX-999', $item['tracking_number'] );
		$this->assertSame( 1, $item['status_shipped'] );
	}
}

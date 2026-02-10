<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Modules\Invoice_Helper;
use WP4Odoo\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Invoice_Helper.
 *
 * Tests the shared invoice CPT helpers used by Sales and WooCommerce modules.
 */
class InvoiceHelperTest extends TestCase {

	private \WP_DB_Stub $wpdb;

	protected function setUp(): void {
		global $wpdb;
		$this->wpdb = new \WP_DB_Stub();
		$wpdb       = $this->wpdb;

		$GLOBALS['_wp_options'] = [];
		$GLOBALS['_wp_posts']   = [];
	}

	// ─── INVOICE_META constant ────────────────────────────

	public function test_invoice_meta_has_six_fields(): void {
		$this->assertCount( 6, Invoice_Helper::INVOICE_META );
	}

	public function test_invoice_meta_contains_total(): void {
		$this->assertArrayHasKey( '_invoice_total', Invoice_Helper::INVOICE_META );
	}

	public function test_invoice_meta_contains_date(): void {
		$this->assertArrayHasKey( '_invoice_date', Invoice_Helper::INVOICE_META );
	}

	public function test_invoice_meta_contains_state(): void {
		$this->assertArrayHasKey( '_invoice_state', Invoice_Helper::INVOICE_META );
	}

	public function test_invoice_meta_contains_payment_state(): void {
		$this->assertArrayHasKey( '_payment_state', Invoice_Helper::INVOICE_META );
	}

	public function test_invoice_meta_contains_partner_id(): void {
		$this->assertArrayHasKey( '_wp4odoo_partner_id', Invoice_Helper::INVOICE_META );
	}

	public function test_invoice_meta_contains_currency(): void {
		$this->assertArrayHasKey( '_invoice_currency', Invoice_Helper::INVOICE_META );
	}

	// ─── register_cpt ─────────────────────────────────────

	public function test_register_cpt_does_not_error(): void {
		Invoice_Helper::register_cpt();
		$this->assertTrue( true ); // No exception thrown.
	}

	// ─── load ─────────────────────────────────────────────

	public function test_load_returns_empty_for_nonexistent_post(): void {
		$data = Invoice_Helper::load( 999 );
		$this->assertEmpty( $data );
	}

	public function test_load_returns_data_for_invoice_post(): void {
		$GLOBALS['_wp_posts'][1] = (object) [
			'ID'         => 1,
			'post_type'  => 'wp4odoo_invoice',
			'post_title' => 'INV-001',
		];

		$data = Invoice_Helper::load( 1 );

		$this->assertSame( 'INV-001', $data['post_title'] );
	}

	public function test_load_returns_empty_for_wrong_post_type(): void {
		$GLOBALS['_wp_posts'][2] = (object) [
			'ID'         => 2,
			'post_type'  => 'wp4odoo_order',
			'post_title' => 'ORD-001',
		];

		$data = Invoice_Helper::load( 2 );

		$this->assertEmpty( $data );
	}

	// ─── save ─────────────────────────────────────────────

	public function test_save_creates_new_invoice_post(): void {
		$data = [
			'post_title'     => 'INV-002',
			'_invoice_total' => '250.00',
			'_invoice_state' => 'draft',
		];

		$logger = new Logger( 'test' );
		$result = Invoice_Helper::save( $data, 0, $logger );

		$this->assertGreaterThan( 0, $result );
	}

	public function test_save_updates_existing_invoice_post(): void {
		$data = [
			'post_title'     => 'INV-003',
			'_invoice_total' => '300.00',
		];

		$logger = new Logger( 'test' );
		$result = Invoice_Helper::save( $data, 42, $logger );

		$this->assertSame( 42, $result );
	}

	public function test_save_resolves_currency_many2one(): void {
		$data = [
			'post_title'        => 'INV-004',
			'_invoice_currency' => [ 1, 'EUR' ],
		];

		$logger  = new Logger( 'test' );
		$post_id = Invoice_Helper::save( $data, 0, $logger );

		// save() should not error when given a Many2one array for currency.
		$this->assertGreaterThan( 0, $post_id );
	}

	public function test_save_handles_scalar_currency(): void {
		$data = [
			'post_title'        => 'INV-005',
			'_invoice_currency' => 'USD',
		];

		$logger  = new Logger( 'test' );
		$post_id = Invoice_Helper::save( $data, 0, $logger );

		$this->assertGreaterThan( 0, $post_id );
	}

	public function test_save_resolves_partner_id_many2one(): void {
		$data = [
			'post_title'          => 'INV-006',
			'_wp4odoo_partner_id' => [ 42, 'John Doe' ],
		];

		$logger  = new Logger( 'test' );
		$post_id = Invoice_Helper::save( $data, 0, $logger );

		$this->assertGreaterThan( 0, $post_id );
	}

	public function test_save_uses_default_title_when_missing(): void {
		$data = [
			'_invoice_total' => '100.00',
		];

		$logger  = new Logger( 'test' );
		$post_id = Invoice_Helper::save( $data, 0, $logger );

		// Should succeed even without post_title — CPT_Helper uses 'Invoice' fallback.
		$this->assertGreaterThan( 0, $post_id );
	}
}

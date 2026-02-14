<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Modules\Odoo_Accounting_Formatter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Odoo_Accounting_Formatter.
 *
 * Tests static formatting methods for donation model,
 * account.move (customer invoice), and vendor bill.
 */
class OdooAccountingFormatterTest extends TestCase {

	// ─── for_donation_model ─────────────────────────────

	public function test_for_donation_model_has_correct_structure(): void {
		$result = Odoo_Accounting_Formatter::for_donation_model( 10, 20, 50.0, '2026-01-15', 'DON-001' );

		$this->assertSame( 10, $result['partner_id'] );
		$this->assertSame( '2026-01-15', $result['donation_date'] );
		$this->assertSame( 'DON-001', $result['payment_ref'] );
		$this->assertArrayHasKey( 'line_ids', $result );
	}

	public function test_for_donation_model_line_has_product_id(): void {
		$result = Odoo_Accounting_Formatter::for_donation_model( 10, 20, 50.0, '2026-01-15', 'DON-001' );

		$line = $result['line_ids'][0][2];
		$this->assertSame( 20, $line['product_id'] );
		$this->assertSame( 1, $line['quantity'] );
		$this->assertSame( 50.0, $line['unit_price'] );
	}

	public function test_for_donation_model_uses_one2many_tuple(): void {
		$result = Odoo_Accounting_Formatter::for_donation_model( 10, 20, 50.0, '2026-01-15', 'DON-001' );

		$tuple = $result['line_ids'][0];
		$this->assertSame( 0, $tuple[0] );
		$this->assertSame( 0, $tuple[1] );
		$this->assertIsArray( $tuple[2] );
	}

	// ─── for_account_move ───────────────────────────────

	public function test_for_account_move_has_out_invoice_type(): void {
		$result = Odoo_Accounting_Formatter::for_account_move( 10, 20, 100.0, '2026-02-01', 'INV-001', 'Test line' );

		$this->assertSame( 'out_invoice', $result['move_type'] );
	}

	public function test_for_account_move_has_correct_structure(): void {
		$result = Odoo_Accounting_Formatter::for_account_move( 10, 20, 100.0, '2026-02-01', 'INV-001', 'Test line' );

		$this->assertSame( 10, $result['partner_id'] );
		$this->assertSame( '2026-02-01', $result['invoice_date'] );
		$this->assertSame( 'INV-001', $result['ref'] );
		$this->assertArrayHasKey( 'invoice_line_ids', $result );
	}

	public function test_for_account_move_line_has_product_id(): void {
		$result = Odoo_Accounting_Formatter::for_account_move( 10, 20, 100.0, '2026-02-01', 'INV-001', 'Test line' );

		$line = $result['invoice_line_ids'][0][2];
		$this->assertSame( 20, $line['product_id'] );
		$this->assertSame( 100.0, $line['price_unit'] );
		$this->assertSame( 'Test line', $line['name'] );
	}

	public function test_for_account_move_uses_fallback_name(): void {
		$result = Odoo_Accounting_Formatter::for_account_move( 10, 20, 100.0, '2026-02-01', 'INV-001', '', 'Fallback' );

		$line = $result['invoice_line_ids'][0][2];
		$this->assertSame( 'Fallback', $line['name'] );
	}

	// ─── for_vendor_bill ────────────────────────────────

	public function test_for_vendor_bill_has_in_invoice_type(): void {
		$result = Odoo_Accounting_Formatter::for_vendor_bill( 10, 25.50, '2026-02-14', 'affwp-ref-42', 'Commission #42' );

		$this->assertSame( 'in_invoice', $result['move_type'] );
	}

	public function test_for_vendor_bill_has_correct_structure(): void {
		$result = Odoo_Accounting_Formatter::for_vendor_bill( 10, 25.50, '2026-02-14', 'affwp-ref-42', 'Commission #42' );

		$this->assertSame( 10, $result['partner_id'] );
		$this->assertSame( '2026-02-14', $result['invoice_date'] );
		$this->assertSame( 'affwp-ref-42', $result['ref'] );
		$this->assertArrayHasKey( 'invoice_line_ids', $result );
	}

	public function test_for_vendor_bill_line_has_no_product_id(): void {
		$result = Odoo_Accounting_Formatter::for_vendor_bill( 10, 25.50, '2026-02-14', 'affwp-ref-42', 'Commission #42' );

		$line = $result['invoice_line_ids'][0][2];
		$this->assertArrayNotHasKey( 'product_id', $line );
	}

	public function test_for_vendor_bill_line_has_correct_amount(): void {
		$result = Odoo_Accounting_Formatter::for_vendor_bill( 10, 99.99, '2026-03-01', 'affwp-ref-7', 'Commission #7' );

		$line = $result['invoice_line_ids'][0][2];
		$this->assertSame( 99.99, $line['price_unit'] );
		$this->assertSame( 1, $line['quantity'] );
		$this->assertSame( 'Commission #7', $line['name'] );
	}

	public function test_for_vendor_bill_uses_one2many_tuple(): void {
		$result = Odoo_Accounting_Formatter::for_vendor_bill( 10, 25.50, '2026-02-14', 'affwp-ref-42', 'Commission #42' );

		$tuple = $result['invoice_line_ids'][0];
		$this->assertSame( 0, $tuple[0] );
		$this->assertSame( 0, $tuple[1] );
		$this->assertIsArray( $tuple[2] );
	}

	public function test_for_vendor_bill_has_exactly_one_line(): void {
		$result = Odoo_Accounting_Formatter::for_vendor_bill( 10, 25.50, '2026-02-14', 'affwp-ref-42', 'Commission #42' );

		$this->assertCount( 1, $result['invoice_line_ids'] );
	}
}

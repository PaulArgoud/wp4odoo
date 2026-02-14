<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Logger;
use WP4Odoo\Modules\AffiliateWP_Handler;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AffiliateWP_Handler.
 *
 * Tests referral loading, vendor bill formatting,
 * and referral status mapping.
 */
class AffiliateWPHandlerTest extends TestCase {

	private AffiliateWP_Handler $handler;

	protected function setUp(): void {
		global $wpdb;
		$wpdb = new \WP_DB_Stub();

		$GLOBALS['_wp_options']       = [];
		$GLOBALS['_affwp_affiliates'] = [];
		$GLOBALS['_affwp_referrals']  = [];

		$this->handler = new AffiliateWP_Handler( new Logger( 'test' ) );
	}

	// ─── load_referral ──────────────────────────────────────

	public function test_load_referral_returns_vendor_bill_structure(): void {
		$referral                = new \AffWP_Referral();
		$referral->referral_id   = 42;
		$referral->affiliate_id  = 5;
		$referral->amount        = 25.50;
		$referral->date          = '2026-02-14 10:30:00';
		$referral->description   = 'Product purchase';
		$referral->status        = 'unpaid';
		$GLOBALS['_affwp_referrals'][42] = $referral;

		$result = $this->handler->load_referral( 42, 100 );

		$this->assertSame( 'in_invoice', $result['move_type'] );
		$this->assertSame( 100, $result['partner_id'] );
		$this->assertSame( '2026-02-14', $result['invoice_date'] );
		$this->assertSame( 'affwp-ref-42', $result['ref'] );
		$this->assertArrayHasKey( 'invoice_line_ids', $result );
	}

	public function test_load_referral_has_correct_line_amount(): void {
		$referral                = new \AffWP_Referral();
		$referral->referral_id   = 42;
		$referral->amount        = 50.00;
		$referral->date          = '2026-01-15 08:00:00';
		$referral->description   = 'Sale';
		$GLOBALS['_affwp_referrals'][42] = $referral;

		$result = $this->handler->load_referral( 42, 200 );

		$line = $result['invoice_line_ids'][0][2];
		$this->assertSame( 50.00, $line['price_unit'] );
		$this->assertSame( 1, $line['quantity'] );
	}

	public function test_load_referral_line_name_contains_description(): void {
		$referral                = new \AffWP_Referral();
		$referral->referral_id   = 7;
		$referral->amount        = 10.0;
		$referral->date          = '2026-03-01 12:00:00';
		$referral->description   = 'Widget order';
		$GLOBALS['_affwp_referrals'][7] = $referral;

		$result = $this->handler->load_referral( 7, 50 );

		$line_name = $result['invoice_line_ids'][0][2]['name'];
		$this->assertStringContainsString( 'Widget order', $line_name );
		$this->assertStringContainsString( '#7', $line_name );
	}

	public function test_load_referral_uses_fallback_description(): void {
		$referral                = new \AffWP_Referral();
		$referral->referral_id   = 10;
		$referral->amount        = 5.0;
		$referral->date          = '2026-04-01 00:00:00';
		$referral->description   = '';
		$GLOBALS['_affwp_referrals'][10] = $referral;

		$result = $this->handler->load_referral( 10, 50 );

		$line_name = $result['invoice_line_ids'][0][2]['name'];
		$this->assertStringContainsString( '#10', $line_name );
		$this->assertNotEmpty( $line_name );
	}

	public function test_load_referral_returns_empty_for_nonexistent(): void {
		$result = $this->handler->load_referral( 999, 100 );
		$this->assertEmpty( $result );
	}

	public function test_load_referral_has_no_product_id_in_line(): void {
		$referral                = new \AffWP_Referral();
		$referral->referral_id   = 1;
		$referral->amount        = 10.0;
		$referral->date          = '2026-01-01 00:00:00';
		$referral->description   = 'Test';
		$GLOBALS['_affwp_referrals'][1] = $referral;

		$result = $this->handler->load_referral( 1, 50 );

		$line = $result['invoice_line_ids'][0][2];
		$this->assertArrayNotHasKey( 'product_id', $line );
	}

	public function test_load_referral_one2many_tuple_format(): void {
		$referral                = new \AffWP_Referral();
		$referral->referral_id   = 1;
		$referral->amount        = 10.0;
		$referral->date          = '2026-01-01 00:00:00';
		$referral->description   = 'Test';
		$GLOBALS['_affwp_referrals'][1] = $referral;

		$result = $this->handler->load_referral( 1, 50 );

		// One2many create tuple: [0, 0, {...}]
		$tuple = $result['invoice_line_ids'][0];
		$this->assertSame( 0, $tuple[0] );
		$this->assertSame( 0, $tuple[1] );
		$this->assertIsArray( $tuple[2] );
	}

	public function test_load_referral_has_exactly_one_line(): void {
		$referral                = new \AffWP_Referral();
		$referral->referral_id   = 1;
		$referral->amount        = 10.0;
		$referral->date          = '2026-01-01 00:00:00';
		$referral->description   = 'Test';
		$GLOBALS['_affwp_referrals'][1] = $referral;

		$result = $this->handler->load_referral( 1, 50 );

		$this->assertCount( 1, $result['invoice_line_ids'] );
	}

	// ─── map_referral_status_to_odoo ────────────────────────

	public function test_map_status_unpaid_to_draft(): void {
		$this->assertSame( 'draft', $this->handler->map_referral_status_to_odoo( 'unpaid' ) );
	}

	public function test_map_status_paid_to_posted(): void {
		$this->assertSame( 'posted', $this->handler->map_referral_status_to_odoo( 'paid' ) );
	}

	public function test_map_status_rejected_to_cancel(): void {
		$this->assertSame( 'cancel', $this->handler->map_referral_status_to_odoo( 'rejected' ) );
	}

	public function test_map_status_pending_to_draft(): void {
		$this->assertSame( 'draft', $this->handler->map_referral_status_to_odoo( 'pending' ) );
	}

	public function test_map_status_unknown_to_draft_default(): void {
		$this->assertSame( 'draft', $this->handler->map_referral_status_to_odoo( 'unknown_status' ) );
	}
}

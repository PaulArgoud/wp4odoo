<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Module_Base;
use WP4Odoo\Sync_Result;
use WP4Odoo\Modules\CRM_Module;
use WP4Odoo\Modules\WooCommerce_Module;
use WP4Odoo\Modules\EDD_Module;
use WP4Odoo\Modules\Forms_Module;
use WP4Odoo\Modules\Memberships_Module;
use WP4Odoo\Modules\LearnDash_Module;
use WP4Odoo\Modules\LifterLMS_Module;
use WP4Odoo\Modules\WC_Subscriptions_Module;
use WP4Odoo\Modules\Events_Calendar_Module;
use WP4Odoo\Modules\Sprout_Invoices_Module;
use WP4Odoo\Modules\WP_Invoice_Module;
use WP4Odoo\Modules\Crowdfunding_Module;
use WP4Odoo\Modules\Ecwid_Module;
use WP4Odoo\Modules\ShopWP_Module;
use WP4Odoo\Modules\Job_Manager_Module;
use WP4Odoo\Modules\AffiliateWP_Module;
use WP4Odoo\Modules\WPRM_Module;
use WP4Odoo\Modules\WC_Bundle_BOM_Module;
use WP4Odoo\Modules\GiveWP_Module;
use WP4Odoo\Modules\Bookly_Module;
use WP4Odoo\Modules\MemberPress_Module;
use WP4Odoo\Modules\Awesome_Support_Module;
use WP4Odoo\Modules\Amelia_Module;
use WP4Odoo\Modules\Charitable_Module;
use WP4Odoo\Modules\SimplePay_Module;
use WP4Odoo\Modules\PMPro_Module;
use WP4Odoo\Modules\RCP_Module;
use PHPUnit\Framework\TestCase;

/**
 * Concrete stub that exposes get_dedup_domain() for testing.
 */
class Dedup_Testable_Module extends Module_Base {

	/** @var array */
	public array $dedup_override = [];

	public function __construct() {
		parent::__construct( 'dedup_test', 'Dedup Test', wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );
	}

	public function boot(): void {}

	public function get_default_settings(): array {
		return [];
	}

	/**
	 * Expose get_dedup_domain() for direct testing.
	 */
	public function test_get_dedup_domain( string $entity_type, array $odoo_values ): array {
		return $this->get_dedup_domain( $entity_type, $odoo_values );
	}

	protected function get_dedup_domain( string $entity_type, array $odoo_values ): array {
		return $this->dedup_override;
	}
}

/**
 * Unit tests for Point 1 — Idempotent Creates (search-before-create).
 */
class DedupDomainTest extends TestCase {

	private \WP_DB_Stub $wpdb;

	protected function setUp(): void {
		global $wpdb;
		$this->wpdb = new \WP_DB_Stub();
		$wpdb       = $this->wpdb;
		$GLOBALS['_wp_options']    = [];
		$GLOBALS['_wp_transients'] = [];
		$GLOBALS['_wp_options']['wp4odoo_log_settings'] = [ 'enabled' => true, 'level' => 'debug' ];
	}

	// ─── Base class default ─────────────────────────────────

	public function test_default_get_dedup_domain_returns_empty(): void {
		$module = new Dedup_Testable_Module();
		$module->dedup_override = []; // Simulates default Module_Base behavior.

		$result = $module->test_get_dedup_domain( 'anything', [ 'name' => 'Test' ] );
		$this->assertSame( [], $result );
	}

	// ─── CRM module dedup by email ──────────────────────────

	public function test_crm_dedup_domain_returns_email_filter_for_contacts(): void {
		$module = new CRM_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'contact', [ 'email' => 'alice@example.com', 'name' => 'Alice' ] );

		$this->assertSame( [ [ 'email', '=', 'alice@example.com' ] ], $result );
	}

	public function test_crm_dedup_domain_returns_empty_when_no_email(): void {
		$module = new CRM_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'contact', [ 'name' => 'No Email' ] );

		$this->assertSame( [], $result );
	}

	public function test_crm_dedup_domain_returns_empty_for_lead_entity(): void {
		$module = new CRM_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'lead', [ 'email' => 'lead@example.com' ] );

		$this->assertSame( [], $result );
	}

	// ─── WooCommerce module dedup by SKU ────────────────────

	public function test_wc_dedup_domain_returns_sku_filter_for_products(): void {
		$module = new WooCommerce_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'product', [ 'default_code' => 'WC-001', 'name' => 'Widget' ] );

		$this->assertSame( [ [ 'default_code', '=', 'WC-001' ] ], $result );
	}

	public function test_wc_dedup_domain_returns_empty_when_no_sku(): void {
		$module = new WooCommerce_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'product', [ 'name' => 'No SKU' ] );

		$this->assertSame( [], $result );
	}

	public function test_wc_dedup_domain_returns_empty_for_order_entity(): void {
		$module = new WooCommerce_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'order', [ 'default_code' => 'SKU-123' ] );

		$this->assertSame( [], $result );
	}

	// ─── EDD module dedup ──────────────────────────────────

	public function test_edd_dedup_domain_returns_name_for_downloads(): void {
		$module = new EDD_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'download', [ 'name' => 'My Plugin' ] );

		$this->assertSame( [ [ 'name', '=', 'My Plugin' ] ], $result );
	}

	public function test_edd_dedup_domain_returns_ref_for_invoices(): void {
		$module = new EDD_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'invoice', [ 'ref' => 'EDD-100' ] );

		$this->assertSame( [ [ 'ref', '=', 'EDD-100' ] ], $result );
	}

	public function test_edd_dedup_domain_returns_empty_for_order(): void {
		$module = new EDD_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'order', [ 'name' => 'Test' ] );

		$this->assertSame( [], $result );
	}

	public function test_edd_dedup_domain_returns_empty_when_no_name(): void {
		$module = new EDD_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'download', [ 'description' => 'No Name' ] );

		$this->assertSame( [], $result );
	}

	// ─── Forms module dedup by email_from ───────────────────

	public function test_forms_dedup_domain_returns_email_for_leads(): void {
		$module = new Forms_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'lead', [ 'email_from' => 'test@example.com', 'contact_name' => 'Test' ] );

		$this->assertSame( [ [ 'email_from', '=', 'test@example.com' ] ], $result );
	}

	public function test_forms_dedup_domain_returns_empty_when_no_email(): void {
		$module = new Forms_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'lead', [ 'contact_name' => 'No Email' ] );

		$this->assertSame( [], $result );
	}

	// ─── WC Memberships module dedup by name ────────────────

	public function test_memberships_dedup_domain_returns_name_for_plans(): void {
		$module = new Memberships_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'plan', [ 'name' => 'Gold Plan' ] );

		$this->assertSame( [ [ 'name', '=', 'Gold Plan' ] ], $result );
	}

	public function test_memberships_dedup_domain_returns_empty_for_membership(): void {
		$module = new Memberships_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'membership', [ 'name' => 'Whatever' ] );

		$this->assertSame( [], $result );
	}

	// ─── LearnDash module dedup ─────────────────────────────

	public function test_learndash_dedup_domain_returns_name_for_courses(): void {
		$module = new LearnDash_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'course', [ 'name' => 'PHP Basics' ] );

		$this->assertSame( [ [ 'name', '=', 'PHP Basics' ] ], $result );
	}

	public function test_learndash_dedup_domain_returns_name_for_groups(): void {
		$module = new LearnDash_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'group', [ 'name' => 'Team A' ] );

		$this->assertSame( [ [ 'name', '=', 'Team A' ] ], $result );
	}

	public function test_learndash_dedup_domain_returns_ref_for_transactions(): void {
		$module = new LearnDash_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'transaction', [ 'ref' => 'LD-TXN-42' ] );

		$this->assertSame( [ [ 'ref', '=', 'LD-TXN-42' ] ], $result );
	}

	public function test_learndash_dedup_domain_returns_empty_for_enrollments(): void {
		$module = new LearnDash_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'enrollment', [ 'name' => 'Test' ] );

		$this->assertSame( [], $result );
	}

	// ─── LifterLMS module dedup ─────────────────────────────

	public function test_lifterlms_dedup_domain_returns_name_for_courses(): void {
		$module = new LifterLMS_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'course', [ 'name' => 'Yoga 101' ] );

		$this->assertSame( [ [ 'name', '=', 'Yoga 101' ] ], $result );
	}

	public function test_lifterlms_dedup_domain_returns_name_for_memberships(): void {
		$module = new LifterLMS_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'membership', [ 'name' => 'Premium' ] );

		$this->assertSame( [ [ 'name', '=', 'Premium' ] ], $result );
	}

	public function test_lifterlms_dedup_domain_returns_ref_for_orders(): void {
		$module = new LifterLMS_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'order', [ 'ref' => 'LLMS-ORD-55' ] );

		$this->assertSame( [ [ 'ref', '=', 'LLMS-ORD-55' ] ], $result );
	}

	public function test_lifterlms_dedup_domain_returns_empty_for_enrollments(): void {
		$module = new LifterLMS_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'enrollment', [ 'name' => 'Test' ] );

		$this->assertSame( [], $result );
	}

	// ─── WC Subscriptions module dedup ──────────────────────

	public function test_wc_subscriptions_dedup_domain_returns_name_for_products(): void {
		$module = new WC_Subscriptions_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'product', [ 'name' => 'Monthly Plan' ] );

		$this->assertSame( [ [ 'name', '=', 'Monthly Plan' ] ], $result );
	}

	public function test_wc_subscriptions_dedup_domain_returns_ref_for_renewals(): void {
		$module = new WC_Subscriptions_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'renewal', [ 'ref' => 'WCS-REN-77' ] );

		$this->assertSame( [ [ 'ref', '=', 'WCS-REN-77' ] ], $result );
	}

	public function test_wc_subscriptions_dedup_domain_returns_empty_for_subscription(): void {
		$module = new WC_Subscriptions_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'subscription', [ 'name' => 'Test' ] );

		$this->assertSame( [], $result );
	}

	// ─── Events Calendar module dedup ───────────────────────

	public function test_events_calendar_dedup_domain_returns_name_for_events(): void {
		$module = new Events_Calendar_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'event', [ 'name' => 'Annual Gala' ] );

		$this->assertSame( [ [ 'name', '=', 'Annual Gala' ] ], $result );
	}

	public function test_events_calendar_dedup_domain_returns_name_for_tickets(): void {
		$module = new Events_Calendar_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'ticket', [ 'name' => 'VIP Ticket' ] );

		$this->assertSame( [ [ 'name', '=', 'VIP Ticket' ] ], $result );
	}

	public function test_events_calendar_dedup_domain_returns_compound_for_attendees(): void {
		$module = new Events_Calendar_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'attendee', [ 'email' => 'guest@example.com', 'event_id' => 42 ] );

		$this->assertSame(
			[
				[ 'email', '=', 'guest@example.com' ],
				[ 'event_id', '=', 42 ],
			],
			$result
		);
	}

	public function test_events_calendar_dedup_domain_returns_empty_when_attendee_missing_email(): void {
		$module = new Events_Calendar_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'attendee', [ 'event_id' => 42 ] );

		$this->assertSame( [], $result );
	}

	public function test_events_calendar_dedup_domain_returns_empty_when_attendee_missing_event_id(): void {
		$module = new Events_Calendar_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'attendee', [ 'email' => 'guest@example.com' ] );

		$this->assertSame( [], $result );
	}

	// ─── Sprout Invoices module dedup ────────────────────────

	public function test_sprout_invoices_dedup_domain_returns_ref_for_invoices(): void {
		$module = new Sprout_Invoices_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'invoice', [ 'ref' => 'SI-INV-99' ] );

		$this->assertSame( [ [ 'ref', '=', 'SI-INV-99' ] ], $result );
	}

	public function test_sprout_invoices_dedup_domain_returns_ref_for_payments(): void {
		$module = new Sprout_Invoices_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'payment', [ 'ref' => 'SI-PAY-10' ] );

		$this->assertSame( [ [ 'ref', '=', 'SI-PAY-10' ] ], $result );
	}

	public function test_sprout_invoices_dedup_domain_returns_empty_when_no_ref(): void {
		$module = new Sprout_Invoices_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'invoice', [ 'name' => 'No Ref' ] );

		$this->assertSame( [], $result );
	}

	// ─── WP-Invoice module dedup ────────────────────────────

	public function test_wp_invoice_dedup_domain_returns_ref_for_invoices(): void {
		$module = new WP_Invoice_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'invoice', [ 'ref' => 'WPI-300' ] );

		$this->assertSame( [ [ 'ref', '=', 'WPI-300' ] ], $result );
	}

	public function test_wp_invoice_dedup_domain_returns_empty_when_no_ref(): void {
		$module = new WP_Invoice_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'invoice', [ 'name' => 'No Ref' ] );

		$this->assertSame( [], $result );
	}

	// ─── Crowdfunding module dedup ──────────────────────────

	public function test_crowdfunding_dedup_domain_returns_name_for_campaigns(): void {
		$module = new Crowdfunding_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'campaign', [ 'name' => 'Save the Whales' ] );

		$this->assertSame( [ [ 'name', '=', 'Save the Whales' ] ], $result );
	}

	public function test_crowdfunding_dedup_domain_returns_empty_when_no_name(): void {
		$module = new Crowdfunding_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'campaign', [ 'description' => 'No Name' ] );

		$this->assertSame( [], $result );
	}

	// ─── Ecwid module dedup ─────────────────────────────────

	public function test_ecwid_dedup_domain_returns_sku_for_products(): void {
		$module = new Ecwid_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'product', [ 'default_code' => 'ECW-001', 'name' => 'Widget' ] );

		$this->assertSame( [ [ 'default_code', '=', 'ECW-001' ] ], $result );
	}

	public function test_ecwid_dedup_domain_returns_ref_for_orders(): void {
		$module = new Ecwid_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'order', [ 'client_order_ref' => 'ECW-ORD-50' ] );

		$this->assertSame( [ [ 'client_order_ref', '=', 'ECW-ORD-50' ] ], $result );
	}

	public function test_ecwid_dedup_domain_returns_empty_when_no_sku(): void {
		$module = new Ecwid_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'product', [ 'name' => 'No SKU' ] );

		$this->assertSame( [], $result );
	}

	// ─── ShopWP module dedup ────────────────────────────────

	public function test_shopwp_dedup_domain_returns_sku_for_products(): void {
		$module = new ShopWP_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'product', [ 'default_code' => 'SHOP-001' ] );

		$this->assertSame( [ [ 'default_code', '=', 'SHOP-001' ] ], $result );
	}

	public function test_shopwp_dedup_domain_returns_empty_when_no_sku(): void {
		$module = new ShopWP_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'product', [ 'name' => 'No SKU' ] );

		$this->assertSame( [], $result );
	}

	// ─── Job Manager module dedup ───────────────────────────

	public function test_job_manager_dedup_domain_returns_name_for_jobs(): void {
		$module = new Job_Manager_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'job', [ 'name' => 'Senior Developer' ] );

		$this->assertSame( [ [ 'name', '=', 'Senior Developer' ] ], $result );
	}

	public function test_job_manager_dedup_domain_returns_empty_when_no_name(): void {
		$module = new Job_Manager_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'job', [ 'description' => 'No Name' ] );

		$this->assertSame( [], $result );
	}

	// ─── AffiliateWP module dedup ───────────────────────────

	public function test_affiliatewp_dedup_domain_returns_email_for_affiliates(): void {
		$module = new AffiliateWP_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'affiliate', [ 'email' => 'aff@example.com', 'name' => 'Affiliate' ] );

		$this->assertSame( [ [ 'email', '=', 'aff@example.com' ] ], $result );
	}

	public function test_affiliatewp_dedup_domain_returns_ref_for_referrals(): void {
		$module = new AffiliateWP_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'referral', [ 'ref' => 'AFFWP-REF-12' ] );

		$this->assertSame( [ [ 'ref', '=', 'AFFWP-REF-12' ] ], $result );
	}

	public function test_affiliatewp_dedup_domain_returns_empty_when_no_email(): void {
		$module = new AffiliateWP_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'affiliate', [ 'name' => 'No Email' ] );

		$this->assertSame( [], $result );
	}

	// ─── WPRM module dedup ──────────────────────────────────

	public function test_wprm_dedup_domain_returns_name_for_recipes(): void {
		$module = new WPRM_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'recipe', [ 'name' => 'Banana Bread' ] );

		$this->assertSame( [ [ 'name', '=', 'Banana Bread' ] ], $result );
	}

	public function test_wprm_dedup_domain_returns_empty_when_no_name(): void {
		$module = new WPRM_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'recipe', [ 'description' => 'No Name' ] );

		$this->assertSame( [], $result );
	}

	// ─── WC Bundle BOM module dedup ─────────────────────────

	public function test_wc_bundle_bom_dedup_domain_returns_code_for_boms(): void {
		$module = new WC_Bundle_BOM_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'bom', [ 'code' => 'BOM-001', 'product_tmpl_id' => 5 ] );

		$this->assertSame( [ [ 'code', '=', 'BOM-001' ] ], $result );
	}

	public function test_wc_bundle_bom_dedup_domain_falls_back_to_product_tmpl_id(): void {
		$module = new WC_Bundle_BOM_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'bom', [ 'product_tmpl_id' => 5 ] );

		$this->assertSame( [ [ 'product_tmpl_id', '=', 5 ] ], $result );
	}

	public function test_wc_bundle_bom_dedup_domain_returns_empty_when_no_code_or_tmpl(): void {
		$module = new WC_Bundle_BOM_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'bom', [ 'type' => 'phantom' ] );

		$this->assertSame( [], $result );
	}

	public function test_wc_bundle_bom_dedup_domain_returns_empty_for_non_bom(): void {
		$module = new WC_Bundle_BOM_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'other', [ 'code' => 'BOM-001' ] );

		$this->assertSame( [], $result );
	}

	// ─── GiveWP module (via Dual_Accounting_Module_Base) ────

	public function test_givewp_dedup_domain_returns_name_for_forms(): void {
		$module = new GiveWP_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'form', [ 'name' => 'General Fund' ] );

		$this->assertSame( [ [ 'name', '=', 'General Fund' ] ], $result );
	}

	public function test_givewp_dedup_domain_returns_payment_ref_for_donations(): void {
		$module = new GiveWP_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'donation', [ 'payment_ref' => 'GIVE-DON-88' ] );

		$this->assertSame( [ [ 'payment_ref', '=', 'GIVE-DON-88' ] ], $result );
	}

	public function test_givewp_dedup_domain_falls_back_to_ref_for_donations(): void {
		$module = new GiveWP_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'donation', [ 'ref' => 'GIVE-INV-88' ] );

		$this->assertSame( [ [ 'ref', '=', 'GIVE-INV-88' ] ], $result );
	}

	public function test_givewp_dedup_domain_returns_empty_for_donation_without_ref(): void {
		$module = new GiveWP_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'donation', [ 'partner_id' => 5 ] );

		$this->assertSame( [], $result );
	}

	// ─── Bookly module (via Booking_Module_Base) ────────────

	public function test_bookly_dedup_domain_returns_name_for_services(): void {
		$module = new Bookly_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'service', [ 'name' => 'Haircut' ] );

		$this->assertSame( [ [ 'name', '=', 'Haircut' ] ], $result );
	}

	public function test_bookly_dedup_domain_returns_name_for_bookings(): void {
		$module = new Bookly_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'booking', [ 'name' => 'Haircut \u2014 John' ] );

		$this->assertSame( [ [ 'name', '=', 'Haircut \u2014 John' ] ], $result );
	}

	public function test_bookly_dedup_domain_returns_empty_for_unknown_entity(): void {
		$module = new Bookly_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'unknown', [ 'name' => 'Test' ] );

		$this->assertSame( [], $result );
	}

	// ─── MemberPress module (via Membership_Module_Base) ────

	public function test_memberpress_dedup_domain_returns_name_for_plans(): void {
		$module = new MemberPress_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'plan', [ 'name' => 'Business Plan' ] );

		$this->assertSame( [ [ 'name', '=', 'Business Plan' ] ], $result );
	}

	public function test_memberpress_dedup_domain_returns_ref_for_transactions(): void {
		$module = new MemberPress_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'transaction', [ 'ref' => 'MEPR-TXN-5' ] );

		$this->assertSame( [ [ 'ref', '=', 'MEPR-TXN-5' ] ], $result );
	}

	public function test_memberpress_dedup_domain_returns_empty_for_subscription(): void {
		$module = new MemberPress_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'subscription', [ 'name' => 'Test' ] );

		$this->assertSame( [], $result );
	}

	// ─── Awesome Support module (via Helpdesk_Module_Base) ──

	public function test_awesome_support_dedup_domain_returns_name_for_tickets(): void {
		$module = new Awesome_Support_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'ticket', [ 'name' => 'Login Issue' ] );

		$this->assertSame( [ [ 'name', '=', 'Login Issue' ] ], $result );
	}

	public function test_awesome_support_dedup_domain_returns_empty_when_no_name(): void {
		$module = new Awesome_Support_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'ticket', [ 'description' => 'No Name' ] );

		$this->assertSame( [], $result );
	}

	// ─── Amelia module (via Booking_Module_Base) ────────────

	public function test_amelia_dedup_domain_returns_name_for_services(): void {
		$module = new Amelia_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'service', [ 'name' => 'Massage' ] );

		$this->assertSame( [ [ 'name', '=', 'Massage' ] ], $result );
	}

	public function test_amelia_dedup_domain_returns_name_for_appointments(): void {
		$module = new Amelia_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'appointment', [ 'name' => 'Massage \u2014 Alice' ] );

		$this->assertSame( [ [ 'name', '=', 'Massage \u2014 Alice' ] ], $result );
	}

	// ─── Charitable module (via Dual_Accounting_Module_Base) ─

	public function test_charitable_dedup_domain_returns_name_for_campaigns(): void {
		$module = new Charitable_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'campaign', [ 'name' => 'Disaster Relief' ] );

		$this->assertSame( [ [ 'name', '=', 'Disaster Relief' ] ], $result );
	}

	public function test_charitable_dedup_domain_returns_payment_ref_for_donations(): void {
		$module = new Charitable_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'donation', [ 'payment_ref' => 'CHR-DON-1' ] );

		$this->assertSame( [ [ 'payment_ref', '=', 'CHR-DON-1' ] ], $result );
	}

	// ─── SimplePay module (via Dual_Accounting_Module_Base) ──

	public function test_simplepay_dedup_domain_returns_name_for_forms(): void {
		$module = new SimplePay_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'form', [ 'name' => 'Donation Form' ] );

		$this->assertSame( [ [ 'name', '=', 'Donation Form' ] ], $result );
	}

	public function test_simplepay_dedup_domain_returns_ref_for_payments(): void {
		$module = new SimplePay_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'payment', [ 'ref' => 'SPAY-PI-1' ] );

		$this->assertSame( [ [ 'ref', '=', 'SPAY-PI-1' ] ], $result );
	}

	// ─── PMPro module (via Membership_Module_Base) ──────────

	public function test_pmpro_dedup_domain_returns_name_for_levels(): void {
		$module = new PMPro_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'level', [ 'name' => 'Silver Level' ] );

		$this->assertSame( [ [ 'name', '=', 'Silver Level' ] ], $result );
	}

	public function test_pmpro_dedup_domain_returns_ref_for_orders(): void {
		$module = new PMPro_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'order', [ 'ref' => 'PMPRO-ORD-7' ] );

		$this->assertSame( [ [ 'ref', '=', 'PMPRO-ORD-7' ] ], $result );
	}

	// ─── RCP module (via Membership_Module_Base) ────────────

	public function test_rcp_dedup_domain_returns_name_for_levels(): void {
		$module = new RCP_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'level', [ 'name' => 'Pro Access' ] );

		$this->assertSame( [ [ 'name', '=', 'Pro Access' ] ], $result );
	}

	public function test_rcp_dedup_domain_returns_ref_for_payments(): void {
		$module = new RCP_Module( wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		$ref    = new \ReflectionMethod( $module, 'get_dedup_domain' );
		$ref->setAccessible( true );
		$result = $ref->invoke( $module, 'payment', [ 'ref' => 'RCP-PAY-3' ] );

		$this->assertSame( [ [ 'ref', '=', 'RCP-PAY-3' ] ], $result );
	}
}

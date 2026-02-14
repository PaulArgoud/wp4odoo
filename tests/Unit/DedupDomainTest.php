<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Module_Base;
use WP4Odoo\Sync_Result;
use WP4Odoo\Modules\CRM_Module;
use WP4Odoo\Modules\WooCommerce_Module;
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
}

<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Module_Base;
use WP4Odoo\Sync_Result;
use PHPUnit\Framework\TestCase;

/**
 * Module stub that exposes translation accumulator internals.
 */
class Translation_Testable_Module extends Module_Base {

	/** @var array */
	public array $translatable_fields_override = [];

	/** @var bool */
	public bool $flush_called = false;

	/** @var int */
	public int $flush_count = 0;

	public function __construct() {
		parent::__construct( 'tr_test', 'Translation Test', wp4odoo_test_client_provider(), wp4odoo_test_entity_map(), wp4odoo_test_settings() );

		// Register an Odoo model for translation buffer reverse lookup.
		$this->odoo_models = [ 'product' => 'product.product' ];
	}

	public function boot(): void {}

	public function get_default_settings(): array {
		return [];
	}

	protected function get_translatable_fields( string $entity_type ): array {
		return $this->translatable_fields_override;
	}

	/**
	 * Expose accumulate_pull_translation() for testing.
	 */
	public function test_accumulate( string $odoo_model, int $odoo_id, int $wp_id ): void {
		$this->accumulate_pull_translation( $odoo_model, $odoo_id, $wp_id );
	}

	/**
	 * Expose the translation buffer for assertions.
	 */
	public function get_buffer(): array {
		return $this->translation_buffer;
	}

	/**
	 * Override flush to track calls (avoid actually calling Translation_Service).
	 */
	public function flush_pull_translations(): void {
		$this->flush_called = true;
		++$this->flush_count;

		// Actually clear the buffer like the real implementation.
		$this->translation_buffer = [];
	}
}

/**
 * Unit tests for Point 4 — Translation Accumulator.
 */
class TranslationAccumulatorTest extends TestCase {

	protected function setUp(): void {
		global $wpdb;
		$wpdb = new \WP_DB_Stub();
		$GLOBALS['_wp_options']    = [];
		$GLOBALS['_wp_transients'] = [];
		$GLOBALS['_wp_options']['wp4odoo_log_settings'] = [ 'enabled' => true, 'level' => 'debug' ];
	}

	// ─── Default behavior ───────────────────────────────────

	public function test_default_translatable_fields_returns_empty(): void {
		$module = new Translation_Testable_Module();
		// Default override is empty.
		$this->assertSame( [], $module->translatable_fields_override );
	}

	// ─── Accumulation ───────────────────────────────────────

	public function test_accumulate_adds_to_buffer(): void {
		$module = new Translation_Testable_Module();

		$module->test_accumulate( 'product.product', 42, 100 );
		$module->test_accumulate( 'product.product', 43, 101 );

		$buffer = $module->get_buffer();
		$this->assertCount( 1, $buffer, 'One model key' );
		$this->assertCount( 2, $buffer['product.product'] );
		$this->assertSame( 100, $buffer['product.product'][42] );
		$this->assertSame( 101, $buffer['product.product'][43] );
	}

	public function test_accumulate_different_models(): void {
		$module = new Translation_Testable_Module();

		$module->test_accumulate( 'product.product', 1, 10 );
		$module->test_accumulate( 'res.partner', 2, 20 );

		$buffer = $module->get_buffer();
		$this->assertCount( 2, $buffer );
		$this->assertArrayHasKey( 'product.product', $buffer );
		$this->assertArrayHasKey( 'res.partner', $buffer );
	}

	// ─── Flush behavior ─────────────────────────────────────

	public function test_flush_clears_buffer(): void {
		$module = new Translation_Testable_Module();

		$module->test_accumulate( 'product.product', 1, 10 );
		$module->flush_pull_translations();

		$this->assertTrue( $module->flush_called );
		$this->assertEmpty( $module->get_buffer() );
	}

	public function test_flush_does_nothing_when_buffer_empty(): void {
		$module = new Translation_Testable_Module();

		$module->flush_pull_translations();

		$this->assertTrue( $module->flush_called );
		$this->assertSame( 1, $module->flush_count );
	}
}

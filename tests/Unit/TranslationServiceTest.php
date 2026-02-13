<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\API\Odoo_Client;
use WP4Odoo\I18n\Translation_Service;
use WP4Odoo\I18n\WPML_Adapter;
use WP4Odoo\I18n\Polylang_Adapter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Translation_Service.
 *
 * Tests adapter detection, locale mapping, Odoo version detection,
 * and the dual-path translation push.
 *
 * @covers \WP4Odoo\I18n\Translation_Service
 */
class TranslationServiceTest extends TestCase {

	private Translation_Service $service;
	private Odoo_Client $client;
	private MockTransport $transport;

	protected function setUp(): void {
		global $wpdb;
		$wpdb = new \WP_DB_Stub();

		$GLOBALS['_wp_options']    = [];
		$GLOBALS['_wp_transients'] = [];
		$GLOBALS['_wp_filters']    = [];

		// Enable logging so Logger doesn't short-circuit.
		$GLOBALS['_wp_options']['wp4odoo_log_settings'] = [
			'enabled' => true,
			'level'   => 'debug',
		];

		$this->client    = new Odoo_Client();
		$this->transport = new MockTransport();

		// Inject mock transport via reflection.
		$ref = new \ReflectionClass( $this->client );

		$tp = $ref->getProperty( 'transport' );
		$tp->setAccessible( true );
		$tp->setValue( $this->client, $this->transport );

		$cp = $ref->getProperty( 'connected' );
		$cp->setAccessible( true );
		$cp->setValue( $this->client, true );

		$client = $this->client;
		$this->service = new Translation_Service( fn() => $client );
	}

	// ─── Adapter detection ──────────────────────────────────

	public function test_is_available_true_with_wpml(): void {
		// ICL_SITEPRESS_VERSION and SitePress are defined in stubs.
		$this->assertTrue( $this->service->is_available() );
	}

	public function test_get_adapter_returns_wpml_by_default(): void {
		$adapter = $this->service->get_adapter();
		$this->assertInstanceOf( WPML_Adapter::class, $adapter );
	}

	public function test_get_adapter_returns_same_instance(): void {
		$adapter1 = $this->service->get_adapter();
		$adapter2 = $this->service->get_adapter();
		$this->assertSame( $adapter1, $adapter2 );
	}

	// ─── Locale mapping ─────────────────────────────────────

	public function test_wp_to_odoo_locale_known_french(): void {
		$this->assertSame( 'fr_FR', $this->service->wp_to_odoo_locale( 'fr' ) );
	}

	public function test_wp_to_odoo_locale_known_spanish(): void {
		$this->assertSame( 'es_ES', $this->service->wp_to_odoo_locale( 'es' ) );
	}

	public function test_wp_to_odoo_locale_known_german(): void {
		$this->assertSame( 'de_DE', $this->service->wp_to_odoo_locale( 'de' ) );
	}

	public function test_wp_to_odoo_locale_known_english(): void {
		$this->assertSame( 'en_US', $this->service->wp_to_odoo_locale( 'en' ) );
	}

	public function test_wp_to_odoo_locale_known_arabic(): void {
		$this->assertSame( 'ar_001', $this->service->wp_to_odoo_locale( 'ar' ) );
	}

	public function test_wp_to_odoo_locale_unknown_falls_back(): void {
		// Unknown language code → fallback format.
		$this->assertSame( 'xx_XX', $this->service->wp_to_odoo_locale( 'xx' ) );
	}

	public function test_wp_to_odoo_locale_trims_long_codes(): void {
		// 'fr_FR' → takes first 2 chars 'fr' → 'fr_FR'.
		$this->assertSame( 'fr_FR', $this->service->wp_to_odoo_locale( 'fr_FR' ) );
	}

	public function test_wp_to_odoo_locale_filterable(): void {
		$GLOBALS['_wp_filters']['wp4odoo_odoo_locale'] = function ( $locale, $wp_lang ) {
			if ( 'pt' === $wp_lang ) {
				return 'pt_PT'; // Override pt_BR → pt_PT.
			}
			return $locale;
		};

		$this->assertSame( 'pt_PT', $this->service->wp_to_odoo_locale( 'pt' ) );
	}

	// ─── Odoo version detection ─────────────────────────────

	public function test_has_ir_translation_true(): void {
		$this->transport->return_value = 1; // search_count returns 1.

		$this->assertTrue( $this->service->has_ir_translation() );

		// Verify ir.model probe call.
		$this->assertCount( 1, $this->transport->calls );
		$this->assertSame( 'ir.model', $this->transport->calls[0]['model'] );
		$this->assertSame( 'search_count', $this->transport->calls[0]['method'] );
	}

	public function test_has_ir_translation_false(): void {
		$this->transport->return_value = 0; // search_count returns 0.

		$this->assertFalse( $this->service->has_ir_translation() );
	}

	public function test_has_ir_translation_cached_in_memory(): void {
		$this->transport->return_value = 1;

		$this->service->has_ir_translation();
		$this->service->has_ir_translation();

		// Should only call the API once (cached in memory).
		$this->assertCount( 1, $this->transport->calls );
	}

	public function test_has_ir_translation_cached_via_transient(): void {
		// Pre-set the transient.
		$GLOBALS['_wp_transients']['wp4odoo_has_ir_translation'] = 1;

		$this->assertTrue( $this->service->has_ir_translation() );
		$this->assertCount( 0, $this->transport->calls );
	}

	public function test_has_ir_translation_false_on_exception(): void {
		$this->transport->throw = new \RuntimeException( 'Connection failed' );

		$this->assertFalse( $this->service->has_ir_translation() );
	}

	// ─── Push via context (Odoo 16+) ────────────────────────

	public function test_push_translation_via_context(): void {
		// Odoo 16+: no ir.translation model.
		$this->transport->return_value = 0; // search_count for ir.model.
		$this->service->has_ir_translation(); // Cache the result.

		// Reset calls after detection.
		$this->transport->calls = [];
		$this->transport->return_value = true; // write succeeds.

		$this->service->push_translation(
			'product.product',
			42,
			[ 'name' => 'Produit', 'description_sale' => 'Description FR' ],
			'fr'
		);

		// Should call write with context.
		$this->assertCount( 1, $this->transport->calls );
		$call = $this->transport->calls[0];
		$this->assertSame( 'product.product', $call['model'] );
		$this->assertSame( 'write', $call['method'] );
		$this->assertSame( [ [ 42 ], [ 'name' => 'Produit', 'description_sale' => 'Description FR' ] ], $call['args'] );
		$this->assertSame( [ 'context' => [ 'lang' => 'fr_FR' ] ], $call['kwargs'] );
	}

	// ─── Push via ir.translation (Odoo 14-15) ──────────────

	public function test_push_translation_via_ir_translation(): void {
		// Odoo 14-15: ir.translation exists.
		$this->transport->return_value = 1; // search_count for ir.model.
		$this->service->has_ir_translation(); // Cache: true.

		// Reset calls and set return values.
		$this->transport->calls = [];

		// For each field: search returns empty (no existing translation), then create.
		$call_count = 0;
		$original_transport = $this->transport;

		// search for 'name' → empty, create 'name'.
		// search for 'description_sale' → empty, create 'description_sale'.
		$this->transport->return_value = []; // search returns empty.

		$this->service->push_translation(
			'product.product',
			42,
			[ 'name' => 'Produit' ],
			'fr'
		);

		// Should have: search (for name) + create (for name) = 2 calls.
		$this->assertCount( 2, $this->transport->calls );

		// First call: search for existing translation.
		$search_call = $this->transport->calls[0];
		$this->assertSame( 'ir.translation', $search_call['model'] );
		$this->assertSame( 'search', $search_call['method'] );

		// Second call: create new translation.
		$create_call = $this->transport->calls[1];
		$this->assertSame( 'ir.translation', $create_call['model'] );
		$this->assertSame( 'create', $create_call['method'] );
	}

	// ─── Edge cases ─────────────────────────────────────────

	public function test_push_translation_skips_empty_values(): void {
		$this->transport->return_value = 0;
		$this->service->has_ir_translation();
		$this->transport->calls = [];

		$this->service->push_translation( 'product.product', 42, [], 'fr' );

		$this->assertCount( 0, $this->transport->calls );
	}

	public function test_push_translation_skips_zero_id(): void {
		$this->transport->return_value = 0;
		$this->service->has_ir_translation();
		$this->transport->calls = [];

		$this->service->push_translation( 'product.product', 0, [ 'name' => 'Test' ], 'fr' );

		$this->assertCount( 0, $this->transport->calls );
	}
}

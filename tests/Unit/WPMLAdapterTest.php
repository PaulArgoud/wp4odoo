<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\I18n\WPML_Adapter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WPML_Adapter.
 *
 * Tests WPML filter-based API integration for translation detection.
 *
 * @covers \WP4Odoo\I18n\WPML_Adapter
 */
class WPMLAdapterTest extends TestCase {

	private WPML_Adapter $adapter;

	protected function setUp(): void {
		global $wpdb;
		$wpdb = new \WP_DB_Stub();

		$GLOBALS['_wp_options']    = [];
		$GLOBALS['_wp_posts']      = [];
		$GLOBALS['_wp_filters']    = [];
		$GLOBALS['_wpml_trids']    = [];
		$GLOBALS['_wpml_trid_translations'] = [];
		$GLOBALS['_wpml_originals']         = [];
		$GLOBALS['_wpml_default_lang']      = 'en';
		$GLOBALS['_post_languages']         = [];

		$this->adapter = new WPML_Adapter();

		// Register WPML filter stubs.
		$this->register_wpml_filters();
	}

	/**
	 * Create a post object for the _wp_posts global store.
	 *
	 * @param string $post_type Post type.
	 * @return object
	 */
	private function make_post( string $post_type = 'product' ): object {
		$post = new \stdClass();
		$post->post_type = $post_type;
		$post->post_status = 'publish';
		$post->post_title  = '';
		return $post;
	}

	private function register_wpml_filters(): void {
		// Default language.
		$GLOBALS['_wp_filters']['wpml_default_language'] = function ( $default ) {
			return $GLOBALS['_wpml_default_lang'] ?? 'en';
		};

		// Active languages.
		$GLOBALS['_wp_filters']['wpml_active_languages'] = function ( $default ) {
			return [
				'en' => [ 'code' => 'en', 'native_name' => 'English' ],
				'fr' => [ 'code' => 'fr', 'native_name' => 'Français' ],
				'es' => [ 'code' => 'es', 'native_name' => 'Español' ],
			];
		};

		// Post language details.
		$GLOBALS['_wp_filters']['wpml_post_language_details'] = function ( $default, $post_id ) {
			$lang = $GLOBALS['_post_languages'][ $post_id ] ?? '';
			return $lang ? [ 'language_code' => $lang ] : null;
		};

		// Element trid.
		$GLOBALS['_wp_filters']['wpml_element_trid'] = function ( $default, $post_id ) {
			return $GLOBALS['_wpml_trids'][ $post_id ] ?? null;
		};

		// Element translations.
		$GLOBALS['_wp_filters']['wpml_get_element_translations'] = function ( $default, $trid ) {
			return $GLOBALS['_wpml_trid_translations'][ $trid ] ?? [];
		};

		// Object ID (original in language).
		$GLOBALS['_wp_filters']['wpml_object_id'] = function ( $post_id, $post_type, $return_original, $lang ) {
			$default_lang = $GLOBALS['_wpml_default_lang'] ?? 'en';
			if ( $lang === $default_lang ) {
				return $GLOBALS['_wpml_originals'][ $post_id ] ?? $post_id;
			}
			return $post_id;
		};
	}

	// ─── get_default_language ───────────────────────────────

	public function test_get_default_language(): void {
		$GLOBALS['_wpml_default_lang'] = 'en';
		$this->assertSame( 'en', $this->adapter->get_default_language() );
	}

	public function test_get_default_language_french(): void {
		$GLOBALS['_wpml_default_lang'] = 'fr';
		$this->assertSame( 'fr', $this->adapter->get_default_language() );
	}

	// ─── get_active_languages ───────────────────────────────

	public function test_get_active_languages(): void {
		$langs = $this->adapter->get_active_languages();
		$this->assertSame( [ 'en', 'fr', 'es' ], $langs );
	}

	// ─── get_post_language ──────────────────────────────────

	public function test_get_post_language(): void {
		$GLOBALS['_post_languages'][10] = 'fr';
		$this->assertSame( 'fr', $this->adapter->get_post_language( 10 ) );
	}

	public function test_get_post_language_empty_for_unknown(): void {
		$this->assertSame( '', $this->adapter->get_post_language( 999 ) );
	}

	// ─── get_translations ───────────────────────────────────

	public function test_get_translations_returns_translations(): void {
		$GLOBALS['_wpml_default_lang'] = 'en';
		$GLOBALS['_wp_posts'][10] = $this->make_post( 'product' );

		$GLOBALS['_wpml_trids'][10] = 42;
		$GLOBALS['_wpml_trid_translations'][42] = [
			(object) [ 'language_code' => 'en', 'element_id' => 10 ],
			(object) [ 'language_code' => 'fr', 'element_id' => 20 ],
			(object) [ 'language_code' => 'es', 'element_id' => 30 ],
		];

		$translations = $this->adapter->get_translations( 10 );

		$this->assertSame( [ 'fr' => 20, 'es' => 30 ], $translations );
	}

	public function test_get_translations_excludes_original(): void {
		$GLOBALS['_wpml_default_lang'] = 'en';
		$GLOBALS['_wp_posts'][10] = $this->make_post( 'product' );

		$GLOBALS['_wpml_trids'][10] = 42;
		$GLOBALS['_wpml_trid_translations'][42] = [
			(object) [ 'language_code' => 'en', 'element_id' => 10 ],
			(object) [ 'language_code' => 'fr', 'element_id' => 20 ],
		];

		$translations = $this->adapter->get_translations( 10 );

		$this->assertArrayNotHasKey( 'en', $translations );
		$this->assertSame( [ 'fr' => 20 ], $translations );
	}

	public function test_get_translations_empty_for_no_translations(): void {
		$GLOBALS['_wp_posts'][10] = $this->make_post( 'product' );
		$GLOBALS['_wpml_trids'][10] = 99;
		$GLOBALS['_wpml_trid_translations'][99] = [];

		$this->assertSame( [], $this->adapter->get_translations( 10 ) );
	}

	// ─── get_original_post_id ───────────────────────────────

	public function test_get_original_post_id_for_translation(): void {
		$GLOBALS['_wpml_default_lang'] = 'en';
		$GLOBALS['_wp_posts'][20] = $this->make_post( 'product' );
		$GLOBALS['_wpml_originals'][20] = 10;

		$this->assertSame( 10, $this->adapter->get_original_post_id( 20 ) );
	}

	public function test_get_original_post_id_returns_self_for_original(): void {
		$GLOBALS['_wpml_default_lang'] = 'en';
		$GLOBALS['_wp_posts'][10] = $this->make_post( 'product' );

		$this->assertSame( 10, $this->adapter->get_original_post_id( 10 ) );
	}

	// ─── is_translation ─────────────────────────────────────

	public function test_is_translation_true(): void {
		$GLOBALS['_wpml_default_lang'] = 'en';
		$GLOBALS['_wp_posts'][20] = $this->make_post( 'product' );
		$GLOBALS['_wpml_originals'][20] = 10;

		$this->assertTrue( $this->adapter->is_translation( 20 ) );
	}

	public function test_is_translation_false_for_original(): void {
		$GLOBALS['_wpml_default_lang'] = 'en';
		$GLOBALS['_wp_posts'][10] = $this->make_post( 'product' );

		$this->assertFalse( $this->adapter->is_translation( 10 ) );
	}
}

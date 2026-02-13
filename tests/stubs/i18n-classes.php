<?php
/**
 * WPML + Polylang stubs for unit tests.
 *
 * Provides constants, classes, and function stubs for both
 * translation plugins so that Translation_Service and adapters
 * can be tested without a real WordPress environment.
 *
 * @package WP4Odoo\Tests\Stubs
 */

// ─── Global test stores ─────────────────────────────────

$GLOBALS['_wpml_translations'] = [];
$GLOBALS['_pll_translations']  = [];
$GLOBALS['_pll_languages']     = [ 'en', 'fr', 'es' ];
$GLOBALS['_wpml_default_lang'] = 'en';
$GLOBALS['_pll_default_lang']  = 'en';
$GLOBALS['_post_languages']    = [];

// ─── WPML constants and classes ─────────────────────────

if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
	define( 'ICL_SITEPRESS_VERSION', '4.7.0' );
}

if ( ! class_exists( 'SitePress' ) ) {
	class SitePress {
		public function get_default_language(): string {
			return $GLOBALS['_wpml_default_lang'] ?? 'en';
		}
	}
}

// ─── Polylang constants and functions ───────────────────

if ( ! defined( 'POLYLANG_VERSION' ) ) {
	define( 'POLYLANG_VERSION', '3.6.0' );
}

if ( ! function_exists( 'pll_default_language' ) ) {
	/**
	 * Get the default language code.
	 *
	 * @return string
	 */
	function pll_default_language(): string {
		return $GLOBALS['_pll_default_lang'] ?? 'en';
	}
}

if ( ! function_exists( 'pll_languages_list' ) ) {
	/**
	 * Get list of active language codes.
	 *
	 * @param array $args Optional arguments.
	 * @return array<int, string>
	 */
	function pll_languages_list( array $args = [] ): array {
		return $GLOBALS['_pll_languages'] ?? [ 'en', 'fr', 'es' ];
	}
}

if ( ! function_exists( 'pll_get_post_translations' ) ) {
	/**
	 * Get translation post IDs for a given post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, int> Language code => post ID.
	 */
	function pll_get_post_translations( int $post_id ): array {
		return $GLOBALS['_pll_translations'][ $post_id ] ?? [];
	}
}

if ( ! function_exists( 'pll_get_post_language' ) ) {
	/**
	 * Get the language code for a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $field   Field to return (default 'slug').
	 * @return string|false
	 */
	function pll_get_post_language( int $post_id, string $field = 'slug' ) {
		return $GLOBALS['_post_languages'][ $post_id ] ?? false;
	}
}

if ( ! function_exists( 'pll_get_post' ) ) {
	/**
	 * Get the post ID in a given language.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $lang    Language code.
	 * @return int|false
	 */
	function pll_get_post( int $post_id, string $lang = '' ) {
		$translations = $GLOBALS['_pll_translations'][ $post_id ] ?? [];
		if ( isset( $translations[ $lang ] ) ) {
			return $translations[ $lang ];
		}

		// Reverse lookup: check if this post_id is a translation.
		foreach ( $GLOBALS['_pll_translations'] as $original_id => $trans ) {
			if ( isset( $trans[ $lang ] ) && in_array( $post_id, $trans, true ) ) {
				return $trans[ $lang ];
			}
			// If post_id is the original and lang is the default, return itself.
			if ( $original_id === $post_id && $lang === pll_default_language() ) {
				return $post_id;
			}
		}

		return false;
	}
}

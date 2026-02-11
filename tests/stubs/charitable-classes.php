<?php
/**
 * WP Charitable class stubs for unit testing.
 *
 * Minimal stubs — WP Charitable donation data is accessed via standard
 * get_post() + get_post_meta() (already stubbed in wp-functions.php).
 *
 * @package WP4Odoo\Tests
 */

// ─── Detection class ────────────────────────────────────

if ( ! class_exists( 'Charitable' ) ) {
	class Charitable {
		/** @return static */
		public static function instance(): self {
			return new self();
		}
	}
}

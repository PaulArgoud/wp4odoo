<?php
/**
 * GiveWP class and function stubs for unit testing.
 *
 * Minimal stubs — GiveWP donation data is accessed via standard
 * get_post() + get_post_meta() (already stubbed in wp-functions.php).
 *
 * @package WP4Odoo\Tests
 */

// ─── Detection constants ────────────────────────────────

if ( ! defined( 'GIVE_VERSION' ) ) {
	define( 'GIVE_VERSION', '3.0.0' );
}

// ─── Give singleton ─────────────────────────────────────

if ( ! class_exists( 'Give' ) ) {

	/**
	 * Stub for the GiveWP main class.
	 */
	class Give {

		/**
		 * Get the singleton instance.
		 *
		 * @return self
		 */
		public static function instance(): self {
			return new self();
		}
	}
}

// ─── give() helper function ─────────────────────────────

if ( ! function_exists( 'give' ) ) {
	/**
	 * Get the GiveWP singleton.
	 *
	 * @return Give
	 */
	function give(): Give {
		return Give::instance();
	}
}

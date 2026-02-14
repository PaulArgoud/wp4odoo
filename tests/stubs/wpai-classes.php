<?php
/**
 * WP All Import test stubs.
 *
 * Provides PMXI_VERSION constant and wp_all_import_get_import_id()
 * function so that WP_All_Import_Module can be tested without the
 * full WP All Import plugin.
 *
 * @package WP4Odoo\Tests
 */

if ( ! defined( 'PMXI_VERSION' ) ) {
	define( 'PMXI_VERSION', '4.8.0' );
}

if ( ! function_exists( 'wp_all_import_get_import_id' ) ) {
	/**
	 * Get the current WP All Import import ID.
	 *
	 * @return int
	 */
	function wp_all_import_get_import_id(): int {
		return $GLOBALS['_wpai_import_id'] ?? 0;
	}
}

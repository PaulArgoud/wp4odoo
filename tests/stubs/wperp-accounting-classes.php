<?php
/**
 * WP ERP Accounting stubs for unit testing.
 *
 * WP ERP Accounting shares the WPERP_VERSION constant with the main WP ERP plugin.
 * This stub provides accounting-specific function definitions needed by tests.
 *
 * @package WP4Odoo\Tests
 */

// WPERP_VERSION is defined in wperp-classes.php — no duplication needed.

if ( ! function_exists( 'erp_acct_get_dashboard_overview' ) ) {

	/**
	 * Stub for WP ERP Accounting dashboard overview function.
	 *
	 * Used by the module to detect if the Accounting sub-module is active.
	 *
	 * @return array<string, mixed>
	 */
	function erp_acct_get_dashboard_overview(): array {
		return [];
	}
}

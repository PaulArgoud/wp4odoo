<?php
/**
 * Mailchimp for WP (MC4WP) class and function stubs for PHPUnit tests.
 *
 * @package WP4Odoo\Tests
 */

// ─── Global constants ───────────────────────────────────

if ( ! defined( 'MC4WP_VERSION' ) ) {
	define( 'MC4WP_VERSION', '4.9.0' );
}

// ─── Global test stores ─────────────────────────────────

$GLOBALS['_mc4wp_subscribers'] = [];
$GLOBALS['_mc4wp_lists']       = [];

// ─── MC4WP_Form class stub ──────────────────────────────

if ( ! class_exists( 'MC4WP_Form' ) ) {
	/**
	 * MC4WP Form stub.
	 */
	class MC4WP_Form {

		/**
		 * Form ID.
		 *
		 * @var int
		 */
		public int $id = 0;
	}
}

// ─── MC4WP API stub ─────────────────────────────────────

if ( ! function_exists( 'mc4wp_get_api_v3' ) ) {
	/**
	 * Get MC4WP API v3 instance stub.
	 *
	 * @return stdClass API instance with get_lists method reading from globals.
	 */
	function mc4wp_get_api_v3() {
		$api = new stdClass();

		$api->get_lists = function () {
			return array_values( $GLOBALS['_mc4wp_lists'] );
		};

		return $api;
	}
}

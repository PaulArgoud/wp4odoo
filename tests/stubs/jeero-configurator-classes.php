<?php
/**
 * Jeero Product Configurator class stubs for PHPUnit tests.
 *
 * Provides minimal stubs for Jeero_Product_Configurator detection class,
 * JEERO_VERSION constant, and configuration rule storage via $GLOBALS.
 *
 * @package WP4Odoo\Tests
 */

// ─── Constants ─────────────────────────────────────────────

if ( ! defined( 'JEERO_VERSION' ) ) {
	define( 'JEERO_VERSION', '1.2.0' );
}

// ─── Detection class ───────────────────────────────────────

if ( ! class_exists( 'Jeero_Product_Configurator' ) ) {
	/**
	 * Stub for Jeero Product Configurator detection.
	 */
	class Jeero_Product_Configurator {}
}

// ─── Configuration rule storage ────────────────────────────

if ( ! isset( $GLOBALS['_jeero_configs'] ) ) {
	$GLOBALS['_jeero_configs'] = [];
}

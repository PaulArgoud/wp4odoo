<?php
/**
 * WooCommerce Product Add-Ons stub for unit tests.
 *
 * Defines classes and constants for official WC Product Add-Ons,
 * ThemeHigh Product Add-Ons (THWEPO), and PPOM.
 *
 * @package WP4Odoo\Tests\Stubs
 */

if ( ! defined( 'WC_PRODUCT_ADDONS_VERSION' ) ) {
	define( 'WC_PRODUCT_ADDONS_VERSION', '6.9.0' );
}

if ( ! class_exists( 'WC_Product_Addons' ) ) {
	// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
	class WC_Product_Addons {
		public const VERSION = '6.9.0';
	}
	// phpcs:enable
}

if ( ! defined( 'THWEPO_VERSION' ) ) {
	define( 'THWEPO_VERSION', '3.0.0' );
}

if ( ! defined( 'PPOM_VERSION' ) ) {
	define( 'PPOM_VERSION', '32.0.0' );
}

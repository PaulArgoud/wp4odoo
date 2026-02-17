<?php
/**
 * JetEngine (Crocoblock) stub for unit tests.
 *
 * Defines the JET_ENGINE_VERSION constant and minimal Jet_Engine class
 * used for plugin detection.
 *
 * @package WP4Odoo\Tests\Stubs
 */

if ( ! defined( 'JET_ENGINE_VERSION' ) ) {
	define( 'JET_ENGINE_VERSION', '3.4.0' );
}

if ( ! class_exists( 'Jet_Engine' ) ) {
	// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
	class Jet_Engine {
		private static ?Jet_Engine $instance = null;
		public static function instance(): self {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}
	}
	// phpcs:enable
}

<?php
/**
 * JetAppointments stub for unit tests.
 *
 * Defines the JET_APB_VERSION constant and minimal class stubs
 * used for plugin detection.
 *
 * @package WP4Odoo\Tests\Stubs
 */

// ─── Global constants ───────────────────────────────────

namespace {

	if ( ! defined( 'JET_APB_VERSION' ) ) {
		define( 'JET_APB_VERSION', '2.1.0' );
	}
}

// ─── JET_APB namespace stubs ────────────────────────────

namespace JET_APB {

	if ( ! class_exists( 'JET_APB\Plugin' ) ) {
		// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
		class Plugin {
			/** @var Plugin|null */
			private static ?Plugin $instance = null;
			public static function instance(): self {
				if ( null === self::$instance ) {
					self::$instance = new self();
				}
				return self::$instance;
			}
		}
		// phpcs:enable
	}
}

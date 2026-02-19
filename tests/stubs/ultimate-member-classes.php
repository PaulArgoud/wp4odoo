<?php
/**
 * Ultimate Member class and function stubs for PHPUnit tests.
 *
 * @package WP4Odoo\Tests
 */

// ─── Global constants ───────────────────────────────────

if ( ! defined( 'UM_VERSION' ) ) {
	define( 'UM_VERSION', '2.8.0' );
}

// ─── UM class stub ──────────────────────────────────────

if ( ! class_exists( 'UM' ) ) {
	/**
	 * Ultimate Member singleton stub.
	 */
	class UM {
		private static ?self $instance = null;

		public static function instance(): self {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}
	}
}

// ─── Global stores ──────────────────────────────────────

$GLOBALS['_um_roles'] = [
	'um_member'    => 'Member',
	'um_moderator' => 'Moderator',
	'um_admin'     => 'Admin',
];

<?php
/**
 * SupportCandy stubs for unit testing.
 *
 * @package WP4Odoo\Tests
 */

// Detection constant — SupportCandy uses this constant.
if ( ! defined( 'STARTER_STARTER_VERSION' ) ) {
	define( 'STARTER_STARTER_VERSION', '3.2.8' );
}

// Global test store for custom table simulation.
$GLOBALS['_supportcandy_tickets']    = [];
$GLOBALS['_supportcandy_ticketmeta'] = [];

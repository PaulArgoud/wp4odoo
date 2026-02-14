<?php
/**
 * SupportCandy stubs for unit testing.
 *
 * @package WP4Odoo\Tests
 */

// Detection constant — SupportCandy uses this constant.
if ( ! defined( 'WPSC_VERSION' ) ) {
	define( 'WPSC_VERSION', '3.2.8' );
}

// Global test store for custom table simulation.
$GLOBALS['_supportcandy_tickets']    = [];
$GLOBALS['_supportcandy_ticketmeta'] = [];

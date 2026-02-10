<?php
/**
 * Plugin uninstall handler.
 *
 * Removes all plugin data: database tables, options, and cron events.
 * Only runs when the plugin is deleted through the WordPress admin.
 *
 * @package WP4Odoo
 * @since   1.0.2
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wp4odoo_sync_queue" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wp4odoo_entity_map" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wp4odoo_logs" );

// Remove all plugin options.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'odoo\_wpc\_%'" );

// Clear scheduled cron events.
wp_clear_scheduled_hook( 'wp4odoo_scheduled_sync' );

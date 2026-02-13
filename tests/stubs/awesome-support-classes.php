<?php
/**
 * Awesome Support stubs for unit testing.
 *
 * @package WP4Odoo\Tests
 */

// Detection constant.
if ( ! defined( 'WPAS_VERSION' ) ) {
	define( 'WPAS_VERSION', '6.2.6' );
}

// Global test store.
$GLOBALS['_wpas_tickets'] = [];

if ( ! function_exists( 'wpas_insert_ticket' ) ) {
	/**
	 * @param array  $data Ticket data.
	 * @param int    $user_id User ID.
	 * @param string $status Status.
	 * @return int|WP_Error
	 */
	function wpas_insert_ticket( array $data = [], int $user_id = 0, string $status = 'queued' ) {
		return 0;
	}
}

if ( ! function_exists( 'wpas_update_ticket_status' ) ) {
	/**
	 * @param int    $ticket_id Ticket ID.
	 * @param string $new_status New status.
	 * @return bool
	 */
	function wpas_update_ticket_status( int $ticket_id, string $new_status ): bool {
		$GLOBALS['_wp_post_meta'][ $ticket_id ]['_wpas_status'] = $new_status;
		return true;
	}
}

if ( ! function_exists( 'wpas_get_ticket_status' ) ) {
	/**
	 * @param int $ticket_id Ticket ID.
	 * @return string
	 */
	function wpas_get_ticket_status( int $ticket_id ): string {
		return $GLOBALS['_wp_post_meta'][ $ticket_id ]['_wpas_status'] ?? 'open';
	}
}

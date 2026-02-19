<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fluent Support Handler — data access for Fluent Support tickets.
 *
 * Fluent Support stores tickets in a custom table (fluentSupport_tickets).
 * Access is via its Eloquent-like model API or via global helper stores
 * (test environment).
 *
 * @package WP4Odoo
 * @since   3.7.0
 */
class Fluent_Support_Handler extends Helpdesk_Handler_Base {

	/**
	 * Load a Fluent Support ticket by ID.
	 *
	 * @param int $ticket_id Ticket ID.
	 * @return array<string, mixed> Ticket data, or empty if not found.
	 */
	public function load_ticket( int $ticket_id ): array {
		if ( $ticket_id <= 0 ) {
			return [];
		}

		$ticket = $GLOBALS['_fluent_support_tickets'][ $ticket_id ] ?? null;

		if ( null === $ticket || ! is_array( $ticket ) ) {
			$this->logger->warning( 'Fluent Support ticket not found.', [ 'ticket_id' => $ticket_id ] );
			return [];
		}

		$status   = $ticket['status'] ?? 'new';
		$priority = $ticket['priority'] ?? 'normal';

		return [
			'name'        => (string) ( $ticket['title'] ?? '' ),
			'description' => (string) ( $ticket['content'] ?? '' ),
			'_user_id'    => (int) ( $ticket['customer_id'] ?? 0 ),
			'_wp_status'  => $status,
			'priority'    => $this->map_priority( $this->normalize_priority( $priority ) ),
		];
	}

	/**
	 * Save a ticket status from Odoo pull.
	 *
	 * @param int    $ticket_id Ticket ID.
	 * @param string $wp_status Target WP status ('open' or 'closed').
	 * @return bool True on success.
	 */
	public function save_ticket_status( int $ticket_id, string $wp_status ): bool {
		if ( $ticket_id <= 0 ) {
			return false;
		}

		if ( ! isset( $GLOBALS['_fluent_support_tickets'][ $ticket_id ] ) ) {
			return false;
		}

		$GLOBALS['_fluent_support_tickets'][ $ticket_id ]['status'] = $wp_status;

		return true;
	}

	/**
	 * Normalize Fluent Support priority to standard mapping.
	 *
	 * Fluent Support uses 'normal', 'medium', 'critical'.
	 * We map these to the Helpdesk_Handler_Base PRIORITY_MAP keys.
	 *
	 * @param string $priority Fluent Support priority.
	 * @return string Normalized priority key (low|medium|high|urgent).
	 */
	private function normalize_priority( string $priority ): string {
		return match ( strtolower( $priority ) ) {
			'normal'   => 'low',
			'medium'   => 'medium',
			'critical' => 'urgent',
			default    => 'low',
		};
	}
}

<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SupportCandy hooks for ticket sync.
 *
 * Registers WordPress hooks for SupportCandy ticket lifecycle events
 * and enqueues sync jobs when tickets are created or their status changes.
 *
 * Composed into SupportCandy_Module via `use`.
 *
 * @package WP4Odoo
 * @since   3.0.0
 */
trait SupportCandy_Hooks {

	/**
	 * Register SupportCandy hooks.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {
		$settings = $this->get_settings();

		if ( ! empty( $settings['sync_tickets'] ) ) {
			\add_action( 'wpsc_after_create_ticket', [ $this, 'on_ticket_created' ], 10, 1 );
			\add_action( 'wpsc_set_ticket_status', [ $this, 'on_ticket_status_changed' ], 10, 2 );
		}
	}

	/**
	 * Handle ticket creation.
	 *
	 * @param mixed $ticket Ticket object or array.
	 * @return void
	 */
	public function on_ticket_created( $ticket ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$ticket_id = $this->extract_ticket_id( $ticket );
		if ( $ticket_id <= 0 ) {
			return;
		}

		$settings = $this->get_settings();
		if ( empty( $settings['sync_tickets'] ) ) {
			return;
		}

		$this->enqueue_push( 'ticket', $ticket_id );
	}

	/**
	 * Handle ticket status change.
	 *
	 * @param mixed  $ticket    Ticket object or array.
	 * @param string $new_status New status.
	 * @return void
	 */
	public function on_ticket_status_changed( $ticket, string $new_status = '' ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$ticket_id = $this->extract_ticket_id( $ticket );
		if ( $ticket_id <= 0 ) {
			return;
		}

		$settings = $this->get_settings();
		if ( empty( $settings['sync_tickets'] ) ) {
			return;
		}

		$this->enqueue_push( 'ticket', $ticket_id );
	}

	/**
	 * Extract a ticket ID from various input types.
	 *
	 * SupportCandy hooks pass ticket data in different formats.
	 *
	 * @param mixed $ticket Ticket data (object, array, or int).
	 * @return int Ticket ID, or 0 if not found.
	 */
	private function extract_ticket_id( $ticket ): int {
		if ( is_int( $ticket ) ) {
			return $ticket;
		}

		if ( is_object( $ticket ) && isset( $ticket->id ) ) {
			return (int) $ticket->id;
		}

		if ( is_array( $ticket ) && isset( $ticket['id'] ) ) {
			return (int) $ticket['id'];
		}

		return 0;
	}
}

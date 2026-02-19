<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fluent Support hooks for ticket sync.
 *
 * Registers WordPress hooks for Fluent Support ticket lifecycle events
 * and enqueues sync jobs when tickets are created or their status changes.
 *
 * Composed into Fluent_Support_Module via `use`.
 *
 * @package WP4Odoo
 * @since   3.7.0
 */
trait Fluent_Support_Hooks {

	/**
	 * Register Fluent Support hooks.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {
		$settings = $this->get_settings();

		if ( ! empty( $settings['sync_tickets'] ) ) {
			\add_action( 'fluent_support/ticket_created', $this->safe_callback( [ $this, 'on_ticket_created' ] ), 10, 2 );
			\add_action( 'fluent_support/ticket_closed', $this->safe_callback( [ $this, 'on_ticket_status_updated' ] ), 10, 2 );
			\add_action( 'fluent_support/ticket_reopened', $this->safe_callback( [ $this, 'on_ticket_status_updated' ] ), 10, 2 );
		}
	}

	/**
	 * Handle ticket creation.
	 *
	 * @param mixed $ticket Fluent Support ticket object or data.
	 * @param mixed $customer Customer data.
	 * @return void
	 */
	public function on_ticket_created( mixed $ticket, mixed $customer = null ): void {
		if ( ! $this->should_sync( 'sync_tickets' ) ) {
			return;
		}

		$ticket_id = $this->extract_ticket_id( $ticket );
		if ( $ticket_id <= 0 ) {
			return;
		}

		$this->enqueue_push( 'ticket', $ticket_id );
	}

	/**
	 * Handle ticket status change (close or reopen).
	 *
	 * @param mixed $ticket Fluent Support ticket object or data.
	 * @param mixed $person Person who changed the status.
	 * @return void
	 */
	public function on_ticket_status_updated( mixed $ticket, mixed $person = null ): void {
		if ( ! $this->should_sync( 'sync_tickets' ) ) {
			return;
		}

		$ticket_id = $this->extract_ticket_id( $ticket );
		if ( $ticket_id <= 0 ) {
			return;
		}

		$this->enqueue_push( 'ticket', $ticket_id );
	}

	/**
	 * Extract ticket ID from various input types.
	 *
	 * Fluent Support may pass an object with ->id, an array with 'id',
	 * or an integer directly.
	 *
	 * @param mixed $ticket Ticket input.
	 * @return int Ticket ID, or 0 if not extractable.
	 */
	private function extract_ticket_id( mixed $ticket ): int {
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

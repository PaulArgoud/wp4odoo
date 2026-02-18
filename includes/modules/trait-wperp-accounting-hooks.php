<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Queue_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP ERP Accounting hook callbacks for push operations.
 *
 * Handles accounting events via WP ERP action hooks for invoices,
 * bills, expenses, and journal entries.
 *
 * Expects the using class to provide:
 * - should_sync(): bool           (from Module_Base)
 * - push_entity(): void           (from Module_Helpers)
 * - get_mapping(): ?int           (from Module_Base)
 * - safe_callback(): \Closure     (from Module_Base)
 * - is_importing(): bool          (from Module_Base)
 * - $id: string                   (from Module_Base)
 *
 * @package WP4Odoo
 * @since   3.7.0
 */
trait WPERP_Accounting_Hooks {

	/**
	 * Register WP ERP Accounting hooks.
	 *
	 * Called from boot() after plugin detection.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		$settings = $this->get_settings();

		if ( ! empty( $settings['sync_journal_entries'] ) ) {
			add_action( 'erp_acct_new_journal', $this->safe_callback( [ $this, 'on_journal_entry_new' ] ), 10, 2 );
			add_action( 'erp_acct_new_invoice', $this->safe_callback( [ $this, 'on_invoice_new' ] ), 10, 2 );
			add_action( 'erp_acct_update_invoice', $this->safe_callback( [ $this, 'on_invoice_update' ] ), 10, 2 );
			add_action( 'erp_acct_new_bill', $this->safe_callback( [ $this, 'on_bill_new' ] ), 10, 2 );
			add_action( 'erp_acct_new_expense', $this->safe_callback( [ $this, 'on_expense_new' ] ), 10, 2 );
		}
	}

	// ─── Journal Entry callbacks ──────────────────────────

	/**
	 * Handle new journal entry.
	 *
	 * @param int                  $journal_id Journal ID.
	 * @param array<string, mixed> $data       Journal data.
	 * @return void
	 */
	public function on_journal_entry_new( int $journal_id, array $data = [] ): void {
		if ( $journal_id <= 0 ) {
			return;
		}

		$this->push_entity( 'journal_entry', 'sync_journal_entries', $journal_id );
	}

	/**
	 * Handle new invoice creation.
	 *
	 * @param int                  $invoice_id Invoice ID.
	 * @param array<string, mixed> $data       Invoice data.
	 * @return void
	 */
	public function on_invoice_new( int $invoice_id, array $data = [] ): void {
		if ( $invoice_id <= 0 ) {
			return;
		}

		$this->push_entity( 'journal_entry', 'sync_journal_entries', $invoice_id );
	}

	/**
	 * Handle invoice update.
	 *
	 * @param int                  $invoice_id Invoice ID.
	 * @param array<string, mixed> $data       Invoice data.
	 * @return void
	 */
	public function on_invoice_update( int $invoice_id, array $data = [] ): void {
		if ( $invoice_id <= 0 ) {
			return;
		}

		$this->push_entity( 'journal_entry', 'sync_journal_entries', $invoice_id );
	}

	/**
	 * Handle new bill creation.
	 *
	 * @param int                  $bill_id Bill ID.
	 * @param array<string, mixed> $data    Bill data.
	 * @return void
	 */
	public function on_bill_new( int $bill_id, array $data = [] ): void {
		if ( $bill_id <= 0 ) {
			return;
		}

		$this->push_entity( 'journal_entry', 'sync_journal_entries', $bill_id );
	}

	/**
	 * Handle new expense creation.
	 *
	 * @param int                  $expense_id Expense ID.
	 * @param array<string, mixed> $data       Expense data.
	 * @return void
	 */
	public function on_expense_new( int $expense_id, array $data = [] ): void {
		if ( $expense_id <= 0 ) {
			return;
		}

		$this->push_entity( 'journal_entry', 'sync_journal_entries', $expense_id );
	}
}

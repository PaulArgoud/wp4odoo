<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP ERP Accounting Handler — data access for WP ERP Accounting tables.
 *
 * WP ERP Accounting stores journal entries in {prefix}erp_acct_journals +
 * {prefix}erp_acct_ledger_details and chart of accounts in
 * {prefix}erp_acct_chart_of_accounts. This handler queries them
 * via $wpdb since WP ERP does not use WordPress CPTs for accounting entities.
 *
 * @package WP4Odoo
 * @since   3.6.0
 */
class WPERP_Accounting_Handler {

	/**
	 * WP ERP invoice status → Odoo account.move state mapping.
	 *
	 * @var array<string, string>
	 */
	private const INVOICE_STATUS_MAP = [
		'draft'            => 'draft',
		'awaiting_payment' => 'posted',
		'paid'             => 'posted',
		'overdue'          => 'posted',
		'void'             => 'cancel',
	];

	/**
	 * Odoo account.move state → WP ERP status mapping.
	 *
	 * @var array<string, string>
	 */
	private const ODOO_STATE_MAP = [
		'draft'  => 'draft',
		'posted' => 'awaiting_payment',
		'cancel' => 'void',
	];

	/**
	 * WP ERP account type → Odoo account type mapping.
	 *
	 * @var array<string, string>
	 */
	private const ACCOUNT_TYPE_MAP = [
		'asset'         => 'asset_current',
		'liability'     => 'liability_current',
		'equity'        => 'equity',
		'income'        => 'income',
		'expense'       => 'expense',
		'cost_of_goods' => 'expense_direct_cost',
		'other_income'  => 'income_other',
		'other_expense' => 'expense',
	];

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	// ─── Journal Entries (bidi) ──────────────────────────

	/**
	 * Load a journal entry from WP ERP's erp_acct_journals table.
	 *
	 * Returns data pre-formatted for Odoo account.move fields.
	 *
	 * @param int $journal_id WP ERP journal ID (erp_acct_journals.id).
	 * @return array<string, mixed> Journal entry data, or empty if not found.
	 */
	public function load_journal_entry( int $journal_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_acct_journals';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$journal_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			$this->logger->warning( 'WP ERP journal entry not found.', [ 'journal_id' => $journal_id ] );
			return [];
		}

		$ref    = sprintf( 'WPERP-JE-%d', $journal_id );
		$date   = (string) ( $row['trn_date'] ?? gmdate( 'Y-m-d' ) );
		$status = (string) ( $row['status'] ?? 'draft' );

		// Load ledger details for journal lines.
		$lines = $this->load_ledger_details( $journal_id );

		$result = [
			'move_type' => 'entry',
			'ref'       => $ref,
			'date'      => substr( $date, 0, 10 ),
			'state'     => $this->map_invoice_status_to_odoo( $status ),
		];

		if ( ! empty( $lines ) ) {
			$result['invoice_line_ids'] = $lines;
		}

		return $result;
	}

	/**
	 * Load ledger detail lines for a journal entry.
	 *
	 * @param int $journal_id Journal entry ID.
	 * @return array<int, array<int, mixed>> One2many tuples, or empty.
	 */
	private function load_ledger_details( int $journal_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_acct_ledger_details';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE trn_no = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$journal_id
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return [];
		}

		$lines = [];
		foreach ( $rows as $row ) {
			$debit  = (float) ( $row['debit'] ?? 0.0 );
			$credit = (float) ( $row['credit'] ?? 0.0 );

			$lines[] = [
				0,
				0,
				[
					'name'      => (string) ( $row['particulars'] ?? __( 'Journal line', 'wp4odoo' ) ),
					'debit'     => $debit,
					'credit'    => $credit,
					'ledger_id' => (int) ( $row['ledger_id'] ?? 0 ),
				],
			];
		}

		return $lines;
	}

	/**
	 * Parse Odoo account.move data into WP ERP journal entry format.
	 *
	 * @param array<string, mixed> $odoo_data Odoo record data.
	 * @return array<string, mixed> WP ERP journal entry data.
	 */
	public function parse_journal_entry_from_odoo( array $odoo_data ): array {
		$state = (string) ( $odoo_data['state'] ?? 'draft' );

		return [
			'ref'      => (string) ( $odoo_data['ref'] ?? '' ),
			'trn_date' => (string) ( $odoo_data['date'] ?? '' ),
			'status'   => $this->map_invoice_status_from_odoo( $state ),
		];
	}

	/**
	 * Save a journal entry to WP ERP's erp_acct_journals table.
	 *
	 * @param array<string, mixed> $data  Journal entry data.
	 * @param int                  $wp_id Existing entry ID (0 to create).
	 * @return int Entry ID, or 0 on failure.
	 */
	public function save_journal_entry( array $data, int $wp_id = 0 ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_acct_journals';
		$row   = [
			'trn_date' => $data['trn_date'] ?? gmdate( 'Y-m-d' ),
			'status'   => $data['status'] ?? 'draft',
		];

		if ( ! empty( $data['ref'] ) ) {
			$row['ref'] = $data['ref'];
		}

		if ( $wp_id > 0 ) {
			$result = $wpdb->update( $table, $row, [ 'id' => $wp_id ] );
			return false !== $result ? $wp_id : 0;
		}

		$result = $wpdb->insert( $table, $row );
		return false !== $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Delete a journal entry from WP ERP's erp_acct_journals table.
	 *
	 * @param int $journal_id Journal entry ID.
	 * @return bool True on success.
	 */
	public function delete_journal_entry( int $journal_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_acct_journals';
		return false !== $wpdb->delete( $table, [ 'id' => $journal_id ] );
	}

	// ─── Chart of Accounts (bidi) ───────────────────────

	/**
	 * Load a chart of accounts entry.
	 *
	 * @param int $account_id WP ERP chart of accounts ID.
	 * @return array<string, mixed> Account data, or empty.
	 */
	public function load_chart_account( int $account_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_acct_chart_of_accounts';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$account_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			$this->logger->warning( 'WP ERP chart account not found.', [ 'account_id' => $account_id ] );
			return [];
		}

		$type = (string) ( $row['type'] ?? 'asset' );

		return [
			'name'         => (string) ( $row['name'] ?? '' ),
			'code'         => (string) ( $row['code'] ?? '' ),
			'account_type' => $this->map_account_type_to_odoo( $type ),
		];
	}

	/**
	 * Parse Odoo account.account data into WP ERP format.
	 *
	 * @param array<string, mixed> $odoo_data Odoo record data.
	 * @return array<string, mixed> WP ERP chart account data.
	 */
	public function parse_chart_account_from_odoo( array $odoo_data ): array {
		return [
			'name' => (string) ( $odoo_data['name'] ?? '' ),
			'code' => (string) ( $odoo_data['code'] ?? '' ),
			'type' => (string) ( $odoo_data['account_type'] ?? 'asset_current' ),
		];
	}

	/**
	 * Save a chart account to WP ERP.
	 *
	 * @param array<string, mixed> $data  Account data.
	 * @param int                  $wp_id Existing ID (0 to create).
	 * @return int Account ID, or 0 on failure.
	 */
	public function save_chart_account( array $data, int $wp_id = 0 ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_acct_chart_of_accounts';
		$row   = [
			'name' => $data['name'] ?? '',
			'code' => $data['code'] ?? '',
			'type' => $data['type'] ?? 'asset',
		];

		if ( $wp_id > 0 ) {
			$result = $wpdb->update( $table, $row, [ 'id' => $wp_id ] );
			return false !== $result ? $wp_id : 0;
		}

		$result = $wpdb->insert( $table, $row );
		return false !== $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Delete a chart account.
	 *
	 * @param int $account_id Account ID.
	 * @return bool True on success.
	 */
	public function delete_chart_account( int $account_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_acct_chart_of_accounts';
		return false !== $wpdb->delete( $table, [ 'id' => $account_id ] );
	}

	// ─── Journals (bidi) ────────────────────────────────

	/**
	 * Load a journal (type-level entity).
	 *
	 * WP ERP doesn't have a dedicated journals table — we derive journal
	 * information from ledger categories. The ID maps to a synthetic reference.
	 *
	 * @param int $journal_id Synthetic journal ID.
	 * @return array<string, mixed> Journal data, or empty.
	 */
	public function load_journal( int $journal_id ): array {
		$option = get_option( 'wp4odoo_erp_journal_' . $journal_id, [] );
		if ( ! is_array( $option ) || empty( $option ) ) {
			$this->logger->warning( 'WP ERP journal not found.', [ 'journal_id' => $journal_id ] );
			return [];
		}

		return [
			'name' => (string) ( $option['name'] ?? '' ),
			'type' => (string) ( $option['type'] ?? 'general' ),
			'code' => (string) ( $option['code'] ?? '' ),
		];
	}

	/**
	 * Parse Odoo account.journal data into WP ERP format.
	 *
	 * @param array<string, mixed> $odoo_data Odoo record data.
	 * @return array<string, mixed> Journal data.
	 */
	public function parse_journal_from_odoo( array $odoo_data ): array {
		return [
			'name' => (string) ( $odoo_data['name'] ?? '' ),
			'type' => (string) ( $odoo_data['type'] ?? 'general' ),
			'code' => (string) ( $odoo_data['code'] ?? '' ),
		];
	}

	/**
	 * Save a journal as a WP option.
	 *
	 * @param array<string, mixed> $data  Journal data.
	 * @param int                  $wp_id Existing ID (0 to create).
	 * @return int Reference ID.
	 */
	public function save_journal( array $data, int $wp_id = 0 ): int {
		$ref_id = $wp_id > 0 ? $wp_id : absint( crc32( $data['code'] ?? $data['name'] ?? '' ) );
		update_option( 'wp4odoo_erp_journal_' . $ref_id, $data, false );

		$this->logger->info(
			'Saved WP ERP journal.',
			[
				'ref_id' => $ref_id,
				'name'   => $data['name'] ?? '',
			]
		);

		return $ref_id;
	}

	/**
	 * Delete a journal option.
	 *
	 * @param int $journal_id Journal reference ID.
	 * @return bool True on success.
	 */
	public function delete_journal( int $journal_id ): bool {
		return delete_option( 'wp4odoo_erp_journal_' . $journal_id );
	}

	// ─── Status mapping ─────────────────────────────────

	/**
	 * Map WP ERP invoice status to Odoo account.move state.
	 *
	 * @param string $status WP ERP status.
	 * @return string Odoo state.
	 */
	public function map_invoice_status_to_odoo( string $status ): string {
		return Status_Mapper::resolve( $status, self::INVOICE_STATUS_MAP, 'wp4odoo_wperp_acct_invoice_status_map', 'draft' );
	}

	/**
	 * Map Odoo account.move state to WP ERP status.
	 *
	 * @param string $state Odoo state.
	 * @return string WP ERP status.
	 */
	public function map_invoice_status_from_odoo( string $state ): string {
		return Status_Mapper::resolve( $state, self::ODOO_STATE_MAP, 'wp4odoo_wperp_acct_odoo_state_map', 'draft' );
	}

	/**
	 * Map WP ERP account type to Odoo account type.
	 *
	 * @param string $type WP ERP account type.
	 * @return string Odoo account type.
	 */
	public function map_account_type_to_odoo( string $type ): string {
		return Status_Mapper::resolve( $type, self::ACCOUNT_TYPE_MAP, 'wp4odoo_wperp_acct_account_type_map', 'asset_current' );
	}
}

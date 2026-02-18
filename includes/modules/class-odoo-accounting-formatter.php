<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared data formatting for dual Odoo accounting models.
 *
 * Provides static methods to format payment/donation data for either
 * the OCA donation.donation model or core account.move (invoice).
 *
 * Used by GiveWP_Handler, Charitable_Handler, SimplePay_Handler,
 * and AffiliateWP_Handler.
 *
 * @package WP4Odoo
 * @since   2.0.0
 */
final class Odoo_Accounting_Formatter {

	/**
	 * Format data for OCA donation.donation model.
	 *
	 * @param int    $partner_id Odoo partner ID.
	 * @param int    $product_id Odoo product ID (form/campaign).
	 * @param float  $amount     Amount.
	 * @param string $date       Date (Y-m-d).
	 * @param string $ref        Payment reference.
	 * @return array<string, mixed>
	 */
	public static function for_donation_model( int $partner_id, int $product_id, float $amount, string $date, string $ref ): array {
		return [
			'partner_id'    => $partner_id,
			'donation_date' => $date,
			'payment_ref'   => $ref,
			'line_ids'      => [
				[
					0,
					0,
					[
						'product_id' => $product_id,
						'quantity'   => 1,
						'unit_price' => $amount,
					],
				],
			],
		];
	}

	/**
	 * Format data for core account.move model (invoice).
	 *
	 * @param int    $partner_id    Odoo partner ID.
	 * @param int    $product_id    Odoo product ID (form/campaign).
	 * @param float  $amount        Amount.
	 * @param string $date          Date (Y-m-d).
	 * @param string $ref           Payment reference.
	 * @param string $line_name     Invoice line name (title of form/campaign).
	 * @param string $fallback_name Default line name if $line_name is empty.
	 * @return array<string, mixed>
	 */
	public static function for_account_move( int $partner_id, int $product_id, float $amount, string $date, string $ref, string $line_name, string $fallback_name = '' ): array {
		$line_data = [
			'product_id' => $product_id,
			'quantity'   => 1,
			'price_unit' => $amount,
			'name'       => $line_name ?: $fallback_name,
		];

		/**
		 * Filter invoice line data before sending to Odoo.
		 *
		 * Use this to inject tax_ids, analytic accounts, or other
		 * Odoo fields. Example adding a tax:
		 *
		 *     add_filter( 'wp4odoo_invoice_line_data', function( $line ) {
		 *         $line['tax_ids'] = [ [ 6, 0, [ 1 ] ] ]; // Many2many command.
		 *         return $line;
		 *     } );
		 *
		 * @since 2.9.0
		 *
		 * @param array  $line_data  Invoice line data (product_id, quantity, price_unit, name).
		 * @param int    $partner_id Odoo partner ID.
		 * @param string $ref        Payment reference.
		 */
		$line_data = apply_filters( 'wp4odoo_invoice_line_data', $line_data, $partner_id, $ref );

		return [
			'move_type'        => 'out_invoice',
			'partner_id'       => $partner_id,
			'invoice_date'     => $date,
			'ref'              => $ref,
			'invoice_line_ids' => [
				[ 0, 0, $line_data ],
			],
		];
	}

	/**
	 * Build Odoo invoice_line_ids One2many tuples from a normalized item list.
	 *
	 * Each item should have 'name', 'quantity', and 'price_unit' keys.
	 * Falls back to a single line with $fallback_name and $total when
	 * the item list produces no lines.
	 *
	 * Used by WP_Invoice_Handler and Sprout_Invoices_Handler to avoid
	 * duplicating the same line-building algorithm.
	 *
	 * @since 3.1.0
	 *
	 * @param array<int, array{name: string, quantity: float, price_unit: float}> $items         Normalized line items.
	 * @param string                                                               $fallback_name Fallback line name when no items.
	 * @param float                                                                $total         Invoice total for fallback line.
	 * @return array<int, array<int, mixed>> Odoo One2many create tuples.
	 */
	public static function build_invoice_lines( array $items, string $fallback_name, float $total ): array {
		$lines = [];

		foreach ( $items as $item ) {
			if ( '' === $item['name'] ) {
				continue;
			}

			$lines[] = [
				0,
				0,
				[
					'name'       => $item['name'],
					'quantity'   => $item['quantity'],
					'price_unit' => $item['price_unit'],
				],
			];
		}

		if ( empty( $lines ) && $total > 0 ) {
			$lines[] = [
				0,
				0,
				[
					'name'       => $fallback_name,
					'quantity'   => 1.0,
					'price_unit' => $total,
				],
			];
		}

		return $lines;
	}

	/**
	 * Format data for vendor bill (account.move with in_invoice).
	 *
	 * Unlike customer invoices (out_invoice), vendor bills represent
	 * money owed TO a partner (e.g. affiliate commissions). No product_id
	 * is required — lines use name + price_unit only.
	 *
	 * @since 3.1.0
	 *
	 * @param int    $partner_id Odoo partner ID (vendor/affiliate).
	 * @param float  $amount     Amount.
	 * @param string $date       Date (Y-m-d).
	 * @param string $ref        Bill reference.
	 * @param string $line_name  Line description.
	 * @return array<string, mixed>
	 */
	public static function for_vendor_bill( int $partner_id, float $amount, string $date, string $ref, string $line_name ): array {
		$line_data = [
			'name'       => $line_name,
			'quantity'   => 1,
			'price_unit' => $amount,
		];

		/**
		 * Filter vendor bill line data before sending to Odoo.
		 *
		 * Use this to inject tax_ids, analytic accounts, or expense
		 * account IDs. Example:
		 *
		 *     add_filter( 'wp4odoo_vendor_bill_line_data', function( $line ) {
		 *         $line['account_id'] = 42; // Commission expense account.
		 *         return $line;
		 *     } );
		 *
		 * @since 3.1.0
		 *
		 * @param array  $line_data  Bill line data (name, quantity, price_unit).
		 * @param int    $partner_id Odoo partner ID.
		 * @param string $ref        Bill reference.
		 */
		$line_data = apply_filters( 'wp4odoo_vendor_bill_line_data', $line_data, $partner_id, $ref );

		return [
			'move_type'        => 'in_invoice',
			'partner_id'       => $partner_id,
			'invoice_date'     => $date,
			'ref'              => $ref,
			'invoice_line_ids' => [
				[ 0, 0, $line_data ],
			],
		];
	}

	/**
	 * Format data for credit note (customer refund).
	 *
	 * Creates an account.move with move_type='out_refund', optionally
	 * linked to the original invoice via reversed_entry_id.
	 *
	 * @since 3.6.0
	 *
	 * @param int    $partner_id        Odoo partner ID.
	 * @param float  $amount            Refund amount.
	 * @param string $date              Date (Y-m-d).
	 * @param string $ref               Reference (e.g. "WC Refund #123 (Order #456)").
	 * @param array  $line_items        Line items [{name, quantity, price_unit}, ...].
	 * @param string $fallback_name     Fallback line name if no items.
	 * @param int    $reversed_entry_id Original invoice Odoo ID (0 if unknown).
	 * @return array<string, mixed>
	 */
	public static function for_credit_note( int $partner_id, float $amount, string $date, string $ref, array $line_items, string $fallback_name, int $reversed_entry_id = 0 ): array {
		$data = [
			'move_type'        => 'out_refund',
			'partner_id'       => $partner_id,
			'invoice_date'     => $date,
			'ref'              => $ref,
			'invoice_line_ids' => self::build_invoice_lines( $line_items, $fallback_name, $amount ),
		];

		if ( $reversed_entry_id > 0 ) {
			$data['reversed_entry_id'] = $reversed_entry_id;
		}

		/**
		 * Filter credit note data before sending to Odoo.
		 *
		 * @since 3.6.0
		 *
		 * @param array  $data       Credit note data.
		 * @param int    $partner_id Odoo partner ID.
		 * @param string $ref        Refund reference.
		 */
		return apply_filters( 'wp4odoo_credit_note_data', $data, $partner_id, $ref );
	}

	/**
	 * Auto-post or auto-validate a record in Odoo.
	 *
	 * Centralizes the post/validate logic for all accounting models:
	 * - `donation.donation` → calls `validate`
	 * - `account.move` (and any other model) → calls `action_post`
	 *
	 * @since 3.1.0
	 *
	 * @param \WP4Odoo\API\Odoo_Client $client  Odoo API client.
	 * @param string                    $model   Odoo model name.
	 * @param int                       $odoo_id Odoo record ID.
	 * @param \WP4Odoo\Logger           $logger  Logger instance.
	 * @return bool True on success.
	 */
	public static function auto_post( $client, string $model, int $odoo_id, $logger ): bool {
		$method = 'donation.donation' === $model ? 'validate' : 'action_post';

		try {
			$client->execute( $model, $method, [ [ $odoo_id ] ] );
			$logger->info(
				'Auto-posted record in Odoo.',
				[
					'model'   => $model,
					'method'  => $method,
					'odoo_id' => $odoo_id,
				]
			);
			return true;
		} catch ( \Exception $e ) {
			$logger->warning(
				'Could not auto-post record.',
				[
					'model'   => $model,
					'odoo_id' => $odoo_id,
					'error'   => $e->getMessage(),
				]
			);
			return false;
		}
	}
}

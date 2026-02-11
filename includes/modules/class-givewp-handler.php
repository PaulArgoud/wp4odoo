<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GiveWP Handler — data access for donation forms and donations.
 *
 * Loads GiveWP entities and formats donation data for the target
 * Odoo model (either OCA `donation.donation` or core `account.move`).
 *
 * Called by GiveWP_Module via its load_wp_data dispatch.
 *
 * @package WP4Odoo
 * @since   2.0.0
 */
class GiveWP_Handler {

	/**
	 * Donation status mapping: GiveWP → Odoo.
	 *
	 * @var array<string, string>
	 */
	private const DONATION_STATUS_MAP = [
		'publish'  => 'completed',
		'refunded' => 'refunded',
		'pending'  => 'pending',
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

	// ─── Load form ─────────────────────────────────────────

	/**
	 * Load a GiveWP donation form as a service product.
	 *
	 * @param int $post_id GiveWP form post ID.
	 * @return array<string, mixed> Form data for field mapping, or empty if not found.
	 */
	public function load_form( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post || 'give_forms' !== $post->post_type ) {
			$this->logger->warning( 'GiveWP form not found.', [ 'post_id' => $post_id ] );
			return [];
		}

		return [
			'form_name'  => $post->post_title,
			'list_price' => 0.0,
			'type'       => 'service',
		];
	}

	// ─── Load donation ─────────────────────────────────────

	/**
	 * Load a GiveWP donation, formatted for the target Odoo model.
	 *
	 * When $use_donation_model is true, returns data for OCA donation.donation.
	 * Otherwise returns data for core account.move (invoice).
	 *
	 * @param int  $payment_id         GiveWP payment ID.
	 * @param int  $partner_id         Resolved Odoo partner ID.
	 * @param int  $form_odoo_id       Resolved Odoo product.product ID for the form.
	 * @param bool $use_donation_model True for OCA donation.donation, false for account.move.
	 * @return array<string, mixed> Donation data, or empty if not found.
	 */
	public function load_donation( int $payment_id, int $partner_id, int $form_odoo_id, bool $use_donation_model ): array {
		$post = get_post( $payment_id );
		if ( ! $post || 'give_payment' !== $post->post_type ) {
			$this->logger->warning( 'GiveWP donation not found.', [ 'payment_id' => $payment_id ] );
			return [];
		}

		$amount     = (float) get_post_meta( $payment_id, '_give_payment_total', true );
		$form_title = (string) get_post_meta( $payment_id, '_give_payment_form_title', true );
		$date       = substr( $post->post_date, 0, 10 );
		$ref        = 'give-payment-' . $payment_id;

		if ( $use_donation_model ) {
			return $this->format_for_donation_model( $partner_id, $form_odoo_id, $amount, $date, $ref );
		}

		return $this->format_for_account_move( $partner_id, $form_odoo_id, $amount, $date, $ref, $form_title );
	}

	/**
	 * Format donation data for OCA donation.donation model.
	 *
	 * @param int    $partner_id   Odoo partner ID.
	 * @param int    $form_odoo_id Odoo product ID.
	 * @param float  $amount       Donation amount.
	 * @param string $date         Donation date (Y-m-d).
	 * @param string $ref          Payment reference.
	 * @return array<string, mixed>
	 */
	private function format_for_donation_model( int $partner_id, int $form_odoo_id, float $amount, string $date, string $ref ): array {
		return [
			'partner_id'    => $partner_id,
			'donation_date' => $date,
			'payment_ref'   => $ref,
			'line_ids'      => [
				[
					0,
					0,
					[
						'product_id' => $form_odoo_id,
						'quantity'   => 1,
						'unit_price' => $amount,
					],
				],
			],
		];
	}

	/**
	 * Format donation data for core account.move model (invoice).
	 *
	 * @param int    $partner_id   Odoo partner ID.
	 * @param int    $form_odoo_id Odoo product ID.
	 * @param float  $amount       Donation amount.
	 * @param string $date         Donation date (Y-m-d).
	 * @param string $ref          Payment reference.
	 * @param string $form_title   Form title (for invoice line name fallback).
	 * @return array<string, mixed>
	 */
	private function format_for_account_move( int $partner_id, int $form_odoo_id, float $amount, string $date, string $ref, string $form_title ): array {
		return [
			'move_type'        => 'out_invoice',
			'partner_id'       => $partner_id,
			'invoice_date'     => $date,
			'ref'              => $ref,
			'invoice_line_ids' => [
				[
					0,
					0,
					[
						'product_id' => $form_odoo_id,
						'quantity'   => 1,
						'price_unit' => $amount,
						'name'       => $form_title ?: __( 'Donation', 'wp4odoo' ),
					],
				],
			],
		];
	}

	// ─── Status mapping ────────────────────────────────────

	/**
	 * Map a GiveWP donation status to an Odoo status string.
	 *
	 * @param string $status GiveWP payment status.
	 * @return string Odoo status.
	 */
	public function map_donation_status( string $status ): string {
		$map = apply_filters( 'wp4odoo_givewp_donation_status_map', self::DONATION_STATUS_MAP );

		return $map[ $status ] ?? 'draft';
	}
}

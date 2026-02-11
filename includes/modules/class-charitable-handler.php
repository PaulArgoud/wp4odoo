<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Charitable Handler — data access for campaigns and donations.
 *
 * Loads WP Charitable entities and formats donation data for the target
 * Odoo model (either OCA `donation.donation` or core `account.move`).
 *
 * Called by Charitable_Module via its load_wp_data dispatch.
 *
 * @package WP4Odoo
 * @since   2.0.0
 */
class Charitable_Handler {

	/**
	 * Donation status mapping: WP Charitable → Odoo.
	 *
	 * @var array<string, string>
	 */
	private const DONATION_STATUS_MAP = [
		'charitable-completed'   => 'completed',
		'charitable-pending'     => 'pending',
		'charitable-failed'      => 'draft',
		'charitable-refunded'    => 'refunded',
		'charitable-cancelled'   => 'draft',
		'charitable-preapproval' => 'pending',
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

	// ─── Load campaign ────────────────────────────────────

	/**
	 * Load a WP Charitable campaign as a service product.
	 *
	 * @param int $post_id Charitable campaign post ID.
	 * @return array<string, mixed> Campaign data for field mapping, or empty if not found.
	 */
	public function load_campaign( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post || 'campaign' !== $post->post_type ) {
			$this->logger->warning( 'Charitable campaign not found.', [ 'post_id' => $post_id ] );
			return [];
		}

		return [
			'form_name'  => $post->post_title,
			'list_price' => 0.0,
			'type'       => 'service',
		];
	}

	// ─── Load donation ────────────────────────────────────

	/**
	 * Load a WP Charitable donation, formatted for the target Odoo model.
	 *
	 * When $use_donation_model is true, returns data for OCA donation.donation.
	 * Otherwise returns data for core account.move (invoice).
	 *
	 * @param int  $donation_id        Charitable donation ID.
	 * @param int  $partner_id         Resolved Odoo partner ID.
	 * @param int  $campaign_odoo_id   Resolved Odoo product.product ID for the campaign.
	 * @param bool $use_donation_model True for OCA donation.donation, false for account.move.
	 * @return array<string, mixed> Donation data, or empty if not found.
	 */
	public function load_donation( int $donation_id, int $partner_id, int $campaign_odoo_id, bool $use_donation_model ): array {
		$post = get_post( $donation_id );
		if ( ! $post || 'donation' !== $post->post_type ) {
			$this->logger->warning( 'Charitable donation not found.', [ 'donation_id' => $donation_id ] );
			return [];
		}

		$amount = (float) get_post_meta( $donation_id, '_charitable_donation_amount', true );

		// Resolve campaign title for invoice line name.
		$campaign_id    = (int) get_post_meta( $donation_id, '_charitable_campaign_id', true );
		$campaign_post  = $campaign_id > 0 ? get_post( $campaign_id ) : null;
		$campaign_title = $campaign_post ? $campaign_post->post_title : '';

		$date = substr( $post->post_date, 0, 10 );
		$ref  = 'charitable-donation-' . $donation_id;

		if ( $use_donation_model ) {
			return $this->format_for_donation_model( $partner_id, $campaign_odoo_id, $amount, $date, $ref );
		}

		return $this->format_for_account_move( $partner_id, $campaign_odoo_id, $amount, $date, $ref, $campaign_title );
	}

	/**
	 * Format donation data for OCA donation.donation model.
	 *
	 * @param int    $partner_id      Odoo partner ID.
	 * @param int    $campaign_odoo_id Odoo product ID.
	 * @param float  $amount          Donation amount.
	 * @param string $date            Donation date (Y-m-d).
	 * @param string $ref             Payment reference.
	 * @return array<string, mixed>
	 */
	private function format_for_donation_model( int $partner_id, int $campaign_odoo_id, float $amount, string $date, string $ref ): array {
		return [
			'partner_id'    => $partner_id,
			'donation_date' => $date,
			'payment_ref'   => $ref,
			'line_ids'      => [
				[
					0,
					0,
					[
						'product_id' => $campaign_odoo_id,
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
	 * @param int    $partner_id      Odoo partner ID.
	 * @param int    $campaign_odoo_id Odoo product ID.
	 * @param float  $amount          Donation amount.
	 * @param string $date            Donation date (Y-m-d).
	 * @param string $ref             Payment reference.
	 * @param string $campaign_title  Campaign title (for invoice line name fallback).
	 * @return array<string, mixed>
	 */
	private function format_for_account_move( int $partner_id, int $campaign_odoo_id, float $amount, string $date, string $ref, string $campaign_title ): array {
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
						'product_id' => $campaign_odoo_id,
						'quantity'   => 1,
						'price_unit' => $amount,
						'name'       => $campaign_title ?: __( 'Donation', 'wp4odoo' ),
					],
				],
			],
		];
	}

	// ─── Status mapping ───────────────────────────────────

	/**
	 * Map a WP Charitable donation status to an Odoo status string.
	 *
	 * @param string $status Charitable donation status.
	 * @return string Odoo status.
	 */
	public function map_donation_status( string $status ): string {
		$map = apply_filters( 'wp4odoo_charitable_donation_status_map', self::DONATION_STATUS_MAP );

		return $map[ $status ] ?? 'draft';
	}
}

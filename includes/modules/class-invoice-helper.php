<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\CPT_Helper;
use WP4Odoo\Field_Mapper;
use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared invoice CPT helpers for Sales and WooCommerce modules.
 *
 * Extracts the duplicated invoice CPT logic (registration, loading,
 * saving with currency resolution) into a single shared class.
 *
 * @package WP4Odoo
 * @since   1.9.10
 */
final class Invoice_Helper {

	/**
	 * Invoice meta fields: data key => post meta key.
	 *
	 * @var array<string, string>
	 */
	public const INVOICE_META = [
		'_invoice_total'      => '_invoice_total',
		'_invoice_date'       => '_invoice_date',
		'_invoice_state'      => '_invoice_state',
		'_payment_state'      => '_payment_state',
		'_wp4odoo_partner_id' => '_wp4odoo_partner_id',
		'_invoice_currency'   => '_invoice_currency',
	];

	/**
	 * Register the wp4odoo_invoice custom post type.
	 *
	 * @return void
	 */
	public static function register_cpt(): void {
		CPT_Helper::register(
			'wp4odoo_invoice',
			[
				'name'               => __( 'Invoices', 'wp4odoo' ),
				'singular_name'      => __( 'Invoice', 'wp4odoo' ),
				'add_new_item'       => __( 'Add New Invoice', 'wp4odoo' ),
				'edit_item'          => __( 'Edit Invoice', 'wp4odoo' ),
				'view_item'          => __( 'View Invoice', 'wp4odoo' ),
				'search_items'       => __( 'Search Invoices', 'wp4odoo' ),
				'not_found'          => __( 'No invoices found.', 'wp4odoo' ),
				'not_found_in_trash' => __( 'No invoices found in Trash.', 'wp4odoo' ),
			]
		);
	}

	/**
	 * Load invoice data from the wp4odoo_invoice CPT.
	 *
	 * @param int $wp_id Post ID.
	 * @return array<string, mixed>
	 */
	public static function load( int $wp_id ): array {
		return CPT_Helper::load( $wp_id, 'wp4odoo_invoice', self::INVOICE_META );
	}

	/**
	 * Save invoice data as a wp4odoo_invoice CPT post.
	 *
	 * Resolves currency_id Many2one before delegating to CPT_Helper.
	 *
	 * @param array<string, mixed> $data   Mapped invoice data.
	 * @param int                  $wp_id  Existing post ID (0 to create).
	 * @param Logger               $logger Logger instance.
	 * @return int Post ID or 0 on failure.
	 */
	public static function save( array $data, int $wp_id, Logger $logger ): int {
		// Resolve currency_id Many2one to code string.
		if ( isset( $data['_invoice_currency'] ) ) {
			$data['_invoice_currency'] = Field_Mapper::many2one_to_name( $data['_invoice_currency'] ) ?? '';
		}
		return CPT_Helper::save( $data, $wp_id, 'wp4odoo_invoice', self::INVOICE_META, __( 'Invoice', 'wp4odoo' ), $logger );
	}
}

<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FluentCRM data handler — loads, saves, and parses FluentCRM entities.
 *
 * Accesses FluentCRM data via $wpdb custom table queries:
 * - {prefix}fc_subscribers — subscriber records
 * - {prefix}fc_lists — mailing lists
 * - {prefix}fc_tags — tags
 * - {prefix}fc_subscriber_pivot — M2M subscriber↔list/tag
 *
 * @package WP4Odoo
 * @since   3.2.0
 */
class FluentCRM_Handler {

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

	// ─── Load methods ────────────────────────────────────────

	/**
	 * Load a FluentCRM subscriber by ID.
	 *
	 * @param int $id Subscriber ID.
	 * @return array Subscriber data or empty array if not found.
	 */
	public function load_subscriber( int $id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'fc_subscribers';
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! $row ) {
			$this->logger->warning( 'FluentCRM subscriber not found.', [ 'id' => $id ] );
			return [];
		}

		// Load list IDs from pivot table.
		$pivot_table = $wpdb->prefix . 'fc_subscriber_pivot';
		$list_ids    = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT object_id FROM {$pivot_table} WHERE subscriber_id = %d AND object_type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id,
				'FluentCrm\App\Models\Lists'
			)
		);

		return [
			'id'         => (int) $row['id'],
			'email'      => $row['email'] ?? '',
			'first_name' => $row['first_name'] ?? '',
			'last_name'  => $row['last_name'] ?? '',
			'status'     => $row['status'] ?? 'subscribed',
			'list_ids'   => array_map( 'intval', $list_ids ),
		];
	}

	/**
	 * Load a FluentCRM mailing list by ID.
	 *
	 * @param int $id List ID.
	 * @return array List data or empty array if not found.
	 */
	public function load_list( int $id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'fc_lists';
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! $row ) {
			$this->logger->warning( 'FluentCRM list not found.', [ 'id' => $id ] );
			return [];
		}

		return [
			'id'          => (int) $row['id'],
			'title'       => $row['title'] ?? '',
			'description' => $row['description'] ?? '',
		];
	}

	/**
	 * Load a FluentCRM tag by ID.
	 *
	 * @param int $id Tag ID.
	 * @return array Tag data or empty array if not found.
	 */
	public function load_tag( int $id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'fc_tags';
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! $row ) {
			$this->logger->warning( 'FluentCRM tag not found.', [ 'id' => $id ] );
			return [];
		}

		return [
			'id'    => (int) $row['id'],
			'title' => $row['title'] ?? '',
		];
	}

	// ─── Parse from Odoo ─────────────────────────────────────

	/**
	 * Parse subscriber data from Odoo mailing.contact record.
	 *
	 * Splits the Odoo name field into first_name and last_name.
	 *
	 * @param array $odoo_data Odoo record data.
	 * @return array WordPress-compatible subscriber data.
	 */
	public function parse_subscriber_from_odoo( array $odoo_data ): array {
		$name_parts = explode( ' ', $odoo_data['name'] ?? '', 2 );

		return [
			'email'      => $odoo_data['email'] ?? '',
			'first_name' => $name_parts[0],
			'last_name'  => $name_parts[1] ?? '',
			'status'     => $odoo_data['x_status'] ?? 'subscribed',
		];
	}

	/**
	 * Parse list data from Odoo mailing.list record.
	 *
	 * @param array $odoo_data Odoo record data.
	 * @return array WordPress-compatible list data.
	 */
	public function parse_list_from_odoo( array $odoo_data ): array {
		return [
			'title'       => $odoo_data['name'] ?? '',
			'description' => $odoo_data['x_description'] ?? '',
		];
	}

	// ─── Save methods (pull) ─────────────────────────────────

	/**
	 * Save a subscriber to the FluentCRM subscribers table.
	 *
	 * Finds existing subscriber by email for upsert behavior.
	 *
	 * @param array $data Subscriber data.
	 * @return int The subscriber ID (0 on failure).
	 */
	public function save_subscriber( array $data ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'fc_subscribers';

		// Find existing by email.
		$existing = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE email = %s", $data['email'] ?? '' ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$values = [
			'email'      => sanitize_email( $data['email'] ?? '' ),
			'first_name' => sanitize_text_field( $data['first_name'] ?? '' ),
			'last_name'  => sanitize_text_field( $data['last_name'] ?? '' ),
			'status'     => sanitize_key( $data['status'] ?? 'subscribed' ),
		];

		if ( $existing ) {
			$wpdb->update( $table, $values, [ 'id' => (int) $existing ] );
			return (int) $existing;
		}

		$wpdb->insert( $table, $values );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Save a mailing list to the FluentCRM lists table.
	 *
	 * Finds existing list by title for upsert behavior.
	 *
	 * @param array $data List data.
	 * @return int The list ID (0 on failure).
	 */
	public function save_list( array $data ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'fc_lists';

		// Find existing by title.
		$existing = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE title = %s", $data['title'] ?? '' ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$values = [
			'title'       => sanitize_text_field( $data['title'] ?? '' ),
			'description' => sanitize_text_field( $data['description'] ?? '' ),
		];

		if ( $existing ) {
			$wpdb->update( $table, $values, [ 'id' => (int) $existing ] );
			return (int) $existing;
		}

		$wpdb->insert( $table, $values );
		return (int) $wpdb->insert_id;
	}
}

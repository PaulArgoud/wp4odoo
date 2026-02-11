<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bookly Handler — data access for Bookly booking tables.
 *
 * Bookly stores all data in its own tables ({prefix}bookly_services,
 * {prefix}bookly_appointments, {prefix}bookly_customer_appointments,
 * {prefix}bookly_customers). This handler queries them via $wpdb since
 * Bookly does not use WordPress CPTs.
 *
 * Provides both batch queries (for polling) and individual queries
 * (for push_to_odoo / load_wp_data).
 *
 * Called by Bookly_Module via its load_wp_data dispatch and poll().
 *
 * @package WP4Odoo
 * @since   2.0.0
 */
class Bookly_Handler {

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

	// ─── Batch queries (for polling) ────────────────────────

	/**
	 * Get all Bookly services.
	 *
	 * @return array<int, array{id: int, title: string, info: string, price: float, duration: int}>
	 */
	public function get_all_services(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'bookly_services';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
		$rows = $wpdb->get_results( "SELECT id, title, info, price, duration FROM {$table}", ARRAY_A );

		if ( ! $rows ) {
			return [];
		}

		$services = [];
		foreach ( $rows as $row ) {
			$services[] = [
				'id'       => (int) $row['id'],
				'title'    => $row['title'] ?? '',
				'info'     => $row['info'] ?? '',
				'price'    => (float) ( $row['price'] ?? 0 ),
				'duration' => (int) ( $row['duration'] ?? 0 ),
			];
		}

		return $services;
	}

	/**
	 * Get all active bookings (customer_appointments with approved/done status).
	 *
	 * Joins bookly_customer_appointments with bookly_appointments to get
	 * appointment details. Returns only bookings with syncable statuses.
	 *
	 * @return array<int, array{id: int, appointment_id: int, customer_id: int, status: string, service_id: int, start_date: string, end_date: string, internal_note: string}>
	 */
	public function get_active_bookings(): array {
		global $wpdb;

		$ca_table  = $wpdb->prefix . 'bookly_customer_appointments';
		$apt_table = $wpdb->prefix . 'bookly_appointments';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from $wpdb->prefix.
		$rows = $wpdb->get_results(
			'SELECT ca.id, ca.appointment_id, ca.customer_id, ca.status, ' .
			'a.service_id, a.start_date, a.end_date, a.internal_note ' .
			"FROM {$ca_table} ca " . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"INNER JOIN {$apt_table} a ON ca.appointment_id = a.id " . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"WHERE ca.status IN ('approved', 'done')",
			ARRAY_A
		);

		if ( ! $rows ) {
			return [];
		}

		$bookings = [];
		foreach ( $rows as $row ) {
			$bookings[] = [
				'id'             => (int) $row['id'],
				'appointment_id' => (int) ( $row['appointment_id'] ?? 0 ),
				'customer_id'    => (int) ( $row['customer_id'] ?? 0 ),
				'status'         => $row['status'] ?? '',
				'service_id'     => (int) ( $row['service_id'] ?? 0 ),
				'start_date'     => $row['start_date'] ?? '',
				'end_date'       => $row['end_date'] ?? '',
				'internal_note'  => $row['internal_note'] ?? '',
			];
		}

		return $bookings;
	}

	// ─── Individual queries ─────────────────────────────────

	/**
	 * Load a Bookly service by ID.
	 *
	 * @param int $service_id Bookly service ID.
	 * @return array<string, mixed> Service data, or empty if not found.
	 */
	public function load_service( int $service_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'bookly_services';
		$row   = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
			$wpdb->prepare( "SELECT id, title, info, price, duration FROM {$table} WHERE id = %d", $service_id ),
			ARRAY_A
		);

		if ( ! $row ) {
			$this->logger->warning( 'Bookly service not found.', [ 'service_id' => $service_id ] );
			return [];
		}

		return [
			'title'    => $row['title'] ?? '',
			'info'     => $row['info'] ?? '',
			'price'    => (float) ( $row['price'] ?? 0 ),
			'duration' => (int) ( $row['duration'] ?? 0 ),
		];
	}

	/**
	 * Load a Bookly booking (customer_appointment + appointment) by ID.
	 *
	 * @param int $ca_id Bookly customer_appointment ID.
	 * @return array<string, mixed> Booking data, or empty if not found.
	 */
	public function load_booking( int $ca_id ): array {
		global $wpdb;

		$ca_table  = $wpdb->prefix . 'bookly_customer_appointments';
		$apt_table = $wpdb->prefix . 'bookly_appointments';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT ca.id, ca.appointment_id, ca.customer_id, ca.status, ' .
				'a.service_id, a.start_date, a.end_date, a.internal_note ' .
				"FROM {$ca_table} ca " . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
				"INNER JOIN {$apt_table} a ON ca.appointment_id = a.id " . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
				'WHERE ca.id = %d',
				$ca_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			$this->logger->warning( 'Bookly booking not found.', [ 'ca_id' => $ca_id ] );
			return [];
		}

		return [
			'id'             => (int) $row['id'],
			'appointment_id' => (int) ( $row['appointment_id'] ?? 0 ),
			'customer_id'    => (int) ( $row['customer_id'] ?? 0 ),
			'status'         => $row['status'] ?? '',
			'service_id'     => (int) ( $row['service_id'] ?? 0 ),
			'start_date'     => $row['start_date'] ?? '',
			'end_date'       => $row['end_date'] ?? '',
			'internal_note'  => $row['internal_note'] ?? '',
		];
	}

	/**
	 * Get customer data from the Bookly customers table.
	 *
	 * @param int $customer_id Bookly customer ID.
	 * @return array{email: string, first_name: string, last_name: string, full_name: string}|array{} Customer data, or empty if not found.
	 */
	public function get_customer_data( int $customer_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'bookly_customers';
		$row   = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
			$wpdb->prepare( "SELECT email, first_name, last_name, full_name FROM {$table} WHERE id = %d", $customer_id ),
			ARRAY_A
		);

		if ( ! $row ) {
			return [];
		}

		return [
			'email'      => $row['email'] ?? '',
			'first_name' => $row['first_name'] ?? '',
			'last_name'  => $row['last_name'] ?? '',
			'full_name'  => $row['full_name'] ?? '',
		];
	}

	/**
	 * Extract service_id from a customer_appointment ID.
	 *
	 * Used by ensure_service_synced() to determine which service needs
	 * to be pushed before the booking.
	 *
	 * @param int $ca_id Bookly customer_appointment ID.
	 * @return int Service ID, or 0 if not found.
	 */
	public function get_service_id_for_booking( int $ca_id ): int {
		global $wpdb;

		$ca_table  = $wpdb->prefix . 'bookly_customer_appointments';
		$apt_table = $wpdb->prefix . 'bookly_appointments';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT a.service_id FROM {$ca_table} ca " . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
				"INNER JOIN {$apt_table} a ON ca.appointment_id = a.id " . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
				'WHERE ca.id = %d',
				$ca_id
			)
		);
	}
}

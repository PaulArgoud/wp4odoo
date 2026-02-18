<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Field_Mapper;
use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JetBooking Handler — reads/writes booking and service data.
 *
 * JetBooking stores bookings in a custom table `jet_apartment_bookings`
 * (columns: booking_id, apartment_id, check_in_date, check_out_date,
 * status, user_id). Services are a configurable CPT.
 *
 * @package WP4Odoo
 * @since   3.5.0
 */
class Jet_Booking_Handler {

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Cached service CPT slug.
	 *
	 * @var string
	 */
	private string $service_cpt = '';

	/**
	 * Constructor.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	// ─── Service data access ───────────────────────────────

	/**
	 * Load a service by post ID.
	 *
	 * @param int $service_id WordPress post ID of the service.
	 * @return array<string, mixed> Service data, or empty if not found.
	 */
	public function load_service( int $service_id ): array {
		$post = get_post( $service_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return [];
		}

		$price = (float) get_post_meta( $service_id, '_apartment_price', true );

		return [
			'name'        => $post->post_title,
			'description' => $post->post_content,
			'price'       => $price,
		];
	}

	/**
	 * Parse Odoo product data into service format for pull.
	 *
	 * @param array<string, mixed> $odoo_data Odoo record data.
	 * @return array<string, mixed> Service data for saving.
	 */
	public function parse_service_from_odoo( array $odoo_data ): array {
		return [
			'name'        => (string) ( $odoo_data['name'] ?? '' ),
			'description' => Field_Mapper::html_to_text( (string) ( $odoo_data['description_sale'] ?? '' ) ),
			'price'       => (float) ( $odoo_data['list_price'] ?? 0 ),
		];
	}

	/**
	 * Save a service to WordPress.
	 *
	 * @param array<string, mixed> $data  Service data.
	 * @param int                  $wp_id Existing post ID (0 to create).
	 * @return int Post ID, or 0 on failure.
	 */
	public function save_service( array $data, int $wp_id ): int {
		$post_data = [
			'post_title'   => $data['name'] ?? '',
			'post_content' => $data['description'] ?? '',
			'post_type'    => $this->get_service_cpt(),
			'post_status'  => 'publish',
		];

		if ( $wp_id > 0 ) {
			$post_data['ID'] = $wp_id;
			$result          = wp_update_post( $post_data );
		} else {
			$result = wp_insert_post( $post_data );
		}

		if ( 0 === $result ) {
			$this->logger->warning( 'Failed to save JetBooking service.', [ 'wp_id' => $wp_id ] );
			return 0;
		}

		$post_id = (int) $result;

		if ( isset( $data['price'] ) ) {
			update_post_meta( $post_id, '_apartment_price', (string) $data['price'] );
		}

		return $post_id;
	}

	/**
	 * Delete a service from WordPress.
	 *
	 * @param int $service_id Service post ID.
	 * @return bool True on success.
	 */
	public function delete_service( int $service_id ): bool {
		$result = wp_delete_post( $service_id, true );
		return false !== $result && null !== $result;
	}

	// ─── Booking data access ──────────────────────────────

	/**
	 * Extract booking fields for Booking_Module_Base.
	 *
	 * Reads from JetBooking's custom `jet_apartment_bookings` table.
	 * Returns the standardized array that Booking_Module_Base expects:
	 * service_id, customer_email, customer_name, service_name, start, stop, description.
	 *
	 * @param int $booking_id Booking row ID.
	 * @return array<string, mixed> Booking fields, or empty if not found.
	 */
	public function extract_booking_fields( int $booking_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'jet_apartment_bookings';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT apartment_id, check_in_date, check_out_date, status, user_id FROM {$table} WHERE booking_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$booking_id
			)
		);

		if ( ! $row ) {
			return [];
		}

		$service_id = (int) ( $row->apartment_id ?? 0 );
		$user_id    = (int) ( $row->user_id ?? 0 );

		// Resolve service name.
		$service_name = '';
		if ( $service_id > 0 ) {
			$service_post = get_post( $service_id );
			if ( $service_post ) {
				$service_name = $service_post->post_title;
			}
		}

		// Resolve customer info from WordPress user.
		$email = '';
		$name  = '';
		if ( $user_id > 0 ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$email = $user->user_email;
				$name  = trim( $user->first_name . ' ' . $user->last_name );
				if ( '' === $name ) {
					$name = $user->display_name;
				}
			}
		}

		// Convert timestamps to Odoo datetime format.
		$start_dt = $this->normalize_datetime( (string) ( $row->check_in_date ?? '' ) );
		$end_dt   = $this->normalize_datetime( (string) ( $row->check_out_date ?? '' ) );

		return [
			'service_id'     => $service_id,
			'customer_email' => $email,
			'customer_name'  => $name,
			'service_name'   => $service_name,
			'start'          => $start_dt,
			'stop'           => $end_dt,
			'description'    => (string) ( $row->status ?? '' ),
		];
	}

	/**
	 * Get the service (apartment) ID for a booking.
	 *
	 * @param int $booking_id Booking row ID.
	 * @return int Service post ID, or 0 if not found.
	 */
	public function get_service_id( int $booking_id ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'jet_apartment_bookings';

		$apartment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT apartment_id FROM {$table} WHERE booking_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$booking_id
			)
		);

		return null !== $apartment_id ? (int) $apartment_id : 0;
	}

	// ─── Service CPT resolution ────────────────────────────

	/**
	 * Get the service CPT slug used by JetBooking.
	 *
	 * JetBooking allows configuring the booking instance post type. Falls back
	 * to 'jet-abaf-apartment' if the option is not set.
	 *
	 * @return string CPT slug.
	 */
	public function get_service_cpt(): string {
		if ( '' !== $this->service_cpt ) {
			return $this->service_cpt;
		}

		$settings = get_option( 'jet_abaf_settings', [] );

		if ( is_array( $settings ) && ! empty( $settings['apartment_post_type'] ) ) {
			$this->service_cpt = sanitize_key( $settings['apartment_post_type'] );
		} else {
			$this->service_cpt = 'jet-abaf-apartment';
		}

		return $this->service_cpt;
	}

	// ─── Helpers ───────────────────────────────────────────

	/**
	 * Normalize a datetime value to Odoo format.
	 *
	 * JetBooking may store dates as Unix timestamps or as
	 * 'Y-m-d H:i:s' strings.
	 *
	 * @param string $value Raw datetime value.
	 * @return string Odoo datetime format 'Y-m-d H:i:s', or empty.
	 */
	private function normalize_datetime( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		// If numeric, treat as Unix timestamp.
		if ( ctype_digit( $value ) ) {
			return gmdate( 'Y-m-d H:i:s', (int) $value );
		}

		// Already in expected format.
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $value ) ) {
			return substr( $value, 0, 19 );
		}

		// Try parsing date-only format (JetBooking often stores as Y-m-d).
		$dt = \DateTime::createFromFormat( 'Y-m-d', $value );
		if ( false !== $dt ) {
			return $dt->format( 'Y-m-d' ) . ' 00:00:00';
		}

		return $value;
	}
}

<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Field_Mapper;
use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JetAppointments Handler — reads/writes appointment and service data.
 *
 * JetAppointments stores appointments as a CPT `jet-appointment` with
 * meta fields. Services are a separate CPT (configurable, typically
 * `jet-service` or a custom post type set in plugin settings).
 *
 * @package WP4Odoo
 * @since   3.5.0
 */
class Jet_Appointments_Handler {

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

		$price = (float) get_post_meta( $service_id, '_app_price', true );

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
			$this->logger->warning( 'Failed to save JetAppointments service.', [ 'wp_id' => $wp_id ] );
			return 0;
		}

		$post_id = (int) $result;

		if ( isset( $data['price'] ) ) {
			update_post_meta( $post_id, '_app_price', (string) $data['price'] );
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

	// ─── Appointment data access ───────────────────────────

	/**
	 * Extract booking fields for Booking_Module_Base.
	 *
	 * Returns the standardized array that Booking_Module_Base expects:
	 * service_id, customer_email, customer_name, service_name, start, stop, description.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return array<string, mixed> Booking fields, or empty if not found.
	 */
	public function extract_booking_fields( int $appointment_id ): array {
		$post = get_post( $appointment_id );

		if ( ! $post ) {
			return [];
		}

		$service_id = (int) get_post_meta( $appointment_id, '_appointment_service_id', true );
		$start      = (string) get_post_meta( $appointment_id, '_appointment_date', true );
		$end        = (string) get_post_meta( $appointment_id, '_appointment_end_date', true );
		$email      = (string) get_post_meta( $appointment_id, '_appointment_user_email', true );
		$name       = (string) get_post_meta( $appointment_id, '_appointment_user_name', true );
		$notes      = (string) get_post_meta( $appointment_id, '_appointment_notes', true );

		// Resolve service name.
		$service_name = '';
		if ( $service_id > 0 ) {
			$service_post = get_post( $service_id );
			if ( $service_post ) {
				$service_name = $service_post->post_title;
			}
		}

		// Convert timestamps to Odoo datetime format if numeric.
		$start_dt = $this->normalize_datetime( $start );
		$end_dt   = $this->normalize_datetime( $end );

		return [
			'service_id'     => $service_id,
			'customer_email' => $email,
			'customer_name'  => $name,
			'service_name'   => $service_name,
			'start'          => $start_dt,
			'stop'           => $end_dt,
			'description'    => $notes,
		];
	}

	/**
	 * Get the service ID for an appointment.
	 *
	 * @param int $appointment_id Appointment post ID.
	 * @return int Service post ID, or 0 if not found.
	 */
	public function get_service_id( int $appointment_id ): int {
		return (int) get_post_meta( $appointment_id, '_appointment_service_id', true );
	}

	// ─── Service CPT resolution ────────────────────────────

	/**
	 * Get the service CPT slug used by JetAppointments.
	 *
	 * JetAppointments allows configuring the service post type. Falls back
	 * to 'jet-service' if the option is not set.
	 *
	 * @return string CPT slug.
	 */
	public function get_service_cpt(): string {
		if ( '' !== $this->service_cpt ) {
			return $this->service_cpt;
		}

		$settings = get_option( 'jet_apb_settings', [] );

		if ( is_array( $settings ) && ! empty( $settings['services_cpt'] ) ) {
			$this->service_cpt = sanitize_key( $settings['services_cpt'] );
		} else {
			$this->service_cpt = 'jet-service';
		}

		return $this->service_cpt;
	}

	// ─── Helpers ───────────────────────────────────────────

	/**
	 * Normalize a datetime value to Odoo format.
	 *
	 * JetAppointments may store dates as Unix timestamps or as
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

		// Try parsing.
		$dt = \DateTime::createFromFormat( 'Y-m-d H:i', $value );
		if ( false !== $dt ) {
			return $dt->format( 'Y-m-d H:i:s' );
		}

		return $value;
	}
}

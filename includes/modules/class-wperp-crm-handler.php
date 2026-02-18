<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP ERP CRM Handler — data access for WP ERP CRM tables.
 *
 * WP ERP CRM stores contacts in {prefix}erp_peoples and activities
 * in {prefix}erp_crm_customer_activities. This handler queries them
 * via $wpdb since WP ERP does not use WordPress CPTs for CRM entities.
 *
 * Called by WPERP_CRM_Module via its load_wp_data / save_wp_data dispatch.
 *
 * @package WP4Odoo
 * @since   3.5.0
 */
class WPERP_CRM_Handler {

	/**
	 * WP ERP life stage → Odoo crm.lead type mapping.
	 *
	 * subscriber/lead → lead, opportunity/customer → opportunity.
	 *
	 * @var array<string, string>
	 */
	private const LIFE_STAGE_MAP = [
		'subscriber'  => 'lead',
		'lead'        => 'lead',
		'opportunity' => 'opportunity',
		'customer'    => 'opportunity',
	];

	/**
	 * WP ERP activity type → Odoo mail.activity.type name mapping.
	 *
	 * @var array<string, string>
	 */
	private const ACTIVITY_TYPE_LABELS = [
		'log_activity' => 'To-Do',
		'email'        => 'Email',
		'call'         => 'Phone Call',
		'meeting'      => 'Meeting',
		'sms'          => 'Upload Document',
		'tasks'        => 'To-Do',
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

	// ─── Lead (contact) data access ────────────────────────

	/**
	 * Load a CRM contact from WP ERP's erp_peoples table.
	 *
	 * Returns data pre-formatted for crm.lead fields.
	 *
	 * @param int $contact_id WP ERP contact ID (erp_peoples.id).
	 * @return array<string, mixed> Lead data for Odoo, or empty if not found.
	 */
	public function load_lead( int $contact_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_peoples';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$contact_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			$this->logger->warning( 'WP ERP CRM contact not found.', [ 'contact_id' => $contact_id ] );
			return [];
		}

		$contact_name = trim( ( $row['first_name'] ?? '' ) . ' ' . ( $row['last_name'] ?? '' ) );
		$company      = (string) ( $row['company'] ?? '' );
		$life_stage   = (string) ( $row['life_stage'] ?? 'subscriber' );

		// Use company as lead name, or "Contact: {name}" as fallback.
		$lead_name = '' !== $company ? $company : sprintf(
			/* translators: %s: contact person name */
			__( 'Contact: %s', 'wp4odoo' ),
			$contact_name
		);

		return [
			'name'         => $lead_name,
			'contact_name' => $contact_name,
			'email_from'   => (string) ( $row['email'] ?? '' ),
			'phone'        => (string) ( $row['phone'] ?? '' ),
			'mobile'       => (string) ( $row['mobile'] ?? '' ),
			'website'      => (string) ( $row['website'] ?? '' ),
			'street'       => (string) ( $row['street_1'] ?? '' ),
			'street2'      => (string) ( $row['street_2'] ?? '' ),
			'city'         => (string) ( $row['city'] ?? '' ),
			'zip'          => (string) ( $row['postal_code'] ?? '' ),
			'description'  => (string) ( $row['notes'] ?? '' ),
			'type'         => $this->map_life_stage_to_odoo( $life_stage ),
		];
	}

	/**
	 * Map WP ERP life stage to Odoo crm.lead type.
	 *
	 * @param string $life_stage WP ERP life stage (subscriber, lead, opportunity, customer).
	 * @return string Odoo crm.lead type ('lead' or 'opportunity').
	 */
	public function map_life_stage_to_odoo( string $life_stage ): string {
		return Status_Mapper::resolve(
			$life_stage,
			self::LIFE_STAGE_MAP,
			'wp4odoo_wperp_crm_life_stage_map',
			'lead'
		);
	}

	// ─── Activity data access ──────────────────────────────

	/**
	 * Load an activity from WP ERP's erp_crm_customer_activities table.
	 *
	 * Returns raw activity data. The module enriches with Odoo-specific
	 * fields (res_model, res_id, activity_type_id).
	 *
	 * @param int $activity_id WP ERP CRM activity ID.
	 * @return array<string, mixed> Activity data, or empty if not found.
	 */
	public function load_activity( int $activity_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_crm_customer_activities';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$activity_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			$this->logger->warning( 'WP ERP CRM activity not found.', [ 'activity_id' => $activity_id ] );
			return [];
		}

		$type = (string) ( $row['type'] ?? 'log_activity' );

		return [
			'contact_id'         => (int) ( $row['user_id'] ?? 0 ),
			'activity_type_name' => $this->get_activity_type_label( $type ),
			'summary'            => (string) ( $row['email_subject'] ?? '' ),
			'note'               => (string) ( $row['message'] ?? '' ),
			'date_deadline'      => (string) ( $row['start_date'] ?? '' ),
		];
	}

	/**
	 * Get the contact (erp_peoples) ID for an activity.
	 *
	 * @param int $activity_id WP ERP CRM activity ID.
	 * @return int Contact ID, or 0 if not found.
	 */
	public function get_contact_id_for_activity( int $activity_id ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_crm_customer_activities';
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$table} WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$activity_id
			)
		);

		return null !== $value ? (int) $value : 0;
	}

	/**
	 * Get the Odoo mail.activity.type label for a WP ERP activity type.
	 *
	 * @param string $type WP ERP activity type (log_activity, email, call, etc.).
	 * @return string Odoo activity type name.
	 */
	public function get_activity_type_label( string $type ): string {
		return self::ACTIVITY_TYPE_LABELS[ $type ] ?? 'To-Do';
	}

	// ─── Pull: parse from Odoo ─────────────────────────────

	/**
	 * Parse Odoo crm.lead data into WP ERP contact format.
	 *
	 * @param array<string, mixed> $odoo_data Odoo record data.
	 * @return array<string, mixed> WP ERP contact data.
	 */
	public function parse_lead_from_odoo( array $odoo_data ): array {
		$contact_name = (string) ( $odoo_data['contact_name'] ?? '' );
		$parts        = $this->split_name( $contact_name );

		$odoo_type = (string) ( $odoo_data['type'] ?? 'lead' );

		return [
			'first_name'  => $parts[0],
			'last_name'   => $parts[1],
			'company'     => (string) ( $odoo_data['name'] ?? '' ),
			'email'       => (string) ( $odoo_data['email_from'] ?? '' ),
			'phone'       => (string) ( $odoo_data['phone'] ?? '' ),
			'mobile'      => (string) ( $odoo_data['mobile'] ?? '' ),
			'website'     => (string) ( $odoo_data['website'] ?? '' ),
			'street_1'    => (string) ( $odoo_data['street'] ?? '' ),
			'street_2'    => (string) ( $odoo_data['street2'] ?? '' ),
			'city'        => (string) ( $odoo_data['city'] ?? '' ),
			'postal_code' => (string) ( $odoo_data['zip'] ?? '' ),
			'notes'       => (string) ( $odoo_data['description'] ?? '' ),
			'life_stage'  => 'opportunity' === $odoo_type ? 'opportunity' : 'lead',
		];
	}

	// ─── Pull: save / delete ───────────────────────────────

	/**
	 * Save a contact to WP ERP's erp_peoples table.
	 *
	 * @param array<string, mixed> $data  Contact data.
	 * @param int                  $wp_id Existing contact ID (0 to create).
	 * @return int Contact ID, or 0 on failure.
	 */
	public function save_lead( array $data, int $wp_id = 0 ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_peoples';
		$row   = [
			'first_name'  => $data['first_name'] ?? '',
			'last_name'   => $data['last_name'] ?? '',
			'company'     => $data['company'] ?? '',
			'email'       => $data['email'] ?? '',
			'phone'       => $data['phone'] ?? '',
			'mobile'      => $data['mobile'] ?? '',
			'website'     => $data['website'] ?? '',
			'street_1'    => $data['street_1'] ?? '',
			'street_2'    => $data['street_2'] ?? '',
			'city'        => $data['city'] ?? '',
			'postal_code' => $data['postal_code'] ?? '',
			'notes'       => $data['notes'] ?? '',
			'life_stage'  => $data['life_stage'] ?? 'subscriber',
		];

		if ( $wp_id > 0 ) {
			$result = $wpdb->update( $table, $row, [ 'id' => $wp_id ] );
			return false !== $result ? $wp_id : 0;
		}

		$result = $wpdb->insert( $table, $row );
		return false !== $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Delete a contact from WP ERP's erp_peoples table.
	 *
	 * @param int $contact_id Contact ID.
	 * @return bool True on success.
	 */
	public function delete_lead( int $contact_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_peoples';
		return false !== $wpdb->delete( $table, [ 'id' => $contact_id ] );
	}

	// ─── Helpers ───────────────────────────────────────────

	/**
	 * Split a full name into first and last parts.
	 *
	 * @param string $name Full name.
	 * @return array{0: string, 1: string} [first, last].
	 */
	private function split_name( string $name ): array {
		$parts = preg_split( '/\s+/', trim( $name ), 2 );
		if ( ! is_array( $parts ) ) {
			return [ '', '' ];
		}

		return [
			$parts[0] ?? '',
			$parts[1] ?? '',
		];
	}
}

<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ultimate Member data handler — loads, saves, and formats UM entities.
 *
 * Accesses Ultimate Member data via:
 * - WordPress user API (get_userdata, wp_update_user)
 * - Ultimate Member API (um_user, um_get_user_meta)
 * - WordPress roles API (get_role, WP_Roles)
 *
 * @package WP4Odoo
 * @since   3.7.0
 */
class Ultimate_Member_Handler {

	/**
	 * UM profile fields to sync with Odoo.
	 *
	 * Keys are Odoo-friendly identifiers; values are UM field keys.
	 *
	 * @var array<string, string>
	 */
	private const UM_FIELDS = [
		'phone'   => 'phone_number',
		'company' => 'company',
		'country' => 'country',
		'city'    => 'city',
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

	// ─── Load methods ────────────────────────────────────────

	/**
	 * Load an Ultimate Member profile (WP user + UM fields) by user ID.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<string, mixed> Profile data or empty array if not found.
	 */
	public function load_profile( int $user_id ): array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			$this->logger->warning( 'Ultimate Member profile not found.', [ 'user_id' => $user_id ] );
			return [];
		}

		$data = [
			'user_id'      => $user_id,
			'user_email'   => $user->user_email,
			'display_name' => $user->display_name,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'description'  => $user->description,
			'user_url'     => $user->user_url,
		];

		foreach ( self::UM_FIELDS as $key => $um_key ) {
			$data[ $key ] = (string) get_user_meta( $user_id, $um_key, true );
		}

		return $data;
	}

	/**
	 * Load an Ultimate Member role by its synthetic ID.
	 *
	 * Roles are stored as WordPress user roles. The synthetic ID is
	 * `absint( crc32( $role_slug ) )` for entity map compatibility.
	 *
	 * @param int $role_id Synthetic role ID (crc32 of slug).
	 * @return array<string, mixed> Role data or empty array if not found.
	 */
	public function load_role( int $role_id ): array {
		$roles = $GLOBALS['_um_roles'] ?? [];

		foreach ( $roles as $slug => $label ) {
			if ( absint( crc32( $slug ) ) === $role_id ) {
				return [
					'slug' => $slug,
					'name' => $label,
				];
			}
		}

		$this->logger->warning( 'Ultimate Member role not found.', [ 'role_id' => $role_id ] );
		return [];
	}

	/**
	 * Get the primary UM role for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string Role slug, or empty string if not found.
	 */
	public function get_user_role( int $user_id ): string {
		return (string) get_user_meta( $user_id, 'role', true );
	}

	// ─── Save methods (pull) ─────────────────────────────────

	/**
	 * Save profile data to a WordPress user + UM fields.
	 *
	 * @param array<string, mixed> $data    Profile data.
	 * @param int                  $user_id WordPress user ID.
	 * @return int User ID on success, 0 on failure.
	 */
	public function save_profile( array $data, int $user_id ): int {
		if ( $user_id <= 0 ) {
			$this->logger->warning( 'Cannot save UM profile without user ID.', [ 'data' => $data ] );
			return 0;
		}

		$userdata = [
			'ID'          => $user_id,
			'first_name'  => $data['first_name'] ?? '',
			'last_name'   => $data['last_name'] ?? '',
			'description' => $data['description'] ?? '',
			'user_url'    => $data['user_url'] ?? '',
		];

		$result = wp_update_user( $userdata );
		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'Failed to update WP user for UM profile.',
				[
					'user_id' => $user_id,
					'error'   => $result->get_error_message(),
				]
			);
			return 0;
		}

		foreach ( self::UM_FIELDS as $key => $um_key ) {
			if ( isset( $data[ $key ] ) ) {
				update_user_meta( $user_id, $um_key, $data[ $key ] );
			}
		}

		return $user_id;
	}

	// ─── Format methods (push to Odoo) ───────────────────────

	/**
	 * Format profile data as Odoo res.partner values.
	 *
	 * @param array<string, mixed> $profile       Profile data from load_profile().
	 * @param array<int>           $role_odoo_ids Odoo category IDs for the user's roles.
	 * @return array<string, mixed> Odoo-compatible field values.
	 */
	public function format_partner( array $profile, array $role_odoo_ids = [] ): array {
		$first = $profile['first_name'] ?? '';
		$last  = $profile['last_name'] ?? '';
		$name  = trim( $first . ' ' . $last );

		if ( '' === $name ) {
			$name = $profile['display_name'] ?? '';
		}

		$values = [
			'name'    => $name,
			'email'   => $profile['user_email'] ?? '',
			'phone'   => $profile['phone'] ?? '',
			'comment' => $profile['description'] ?? '',
			'website' => $profile['user_url'] ?? '',
		];

		$company = $profile['company'] ?? '';
		if ( '' !== $company ) {
			$values['company_name'] = $company;
		}

		$city = $profile['city'] ?? '';
		if ( '' !== $city ) {
			$values['city'] = $city;
		}

		if ( ! empty( $role_odoo_ids ) ) {
			$values['category_id'] = [ [ 6, 0, $role_odoo_ids ] ];
		}

		return $values;
	}

	/**
	 * Format role data as Odoo res.partner.category values.
	 *
	 * @param array<string, mixed> $role Role data from load_role().
	 * @return array<string, string> Odoo-compatible field values.
	 */
	public function format_category( array $role ): array {
		return [
			'name' => $role['name'] ?? '',
		];
	}

	// ─── Parse methods (pull from Odoo) ──────────────────────

	/**
	 * Parse profile data from an Odoo res.partner record.
	 *
	 * Splits the Odoo name field into first_name and last_name.
	 *
	 * @param array<string, mixed> $odoo_data Odoo record data.
	 * @return array<string, string> WordPress-compatible profile data.
	 */
	public function parse_profile_from_odoo( array $odoo_data ): array {
		$name_parts = explode( ' ', $odoo_data['name'] ?? '', 2 );

		return [
			'first_name'  => $name_parts[0],
			'last_name'   => $name_parts[1] ?? '',
			'user_email'  => $odoo_data['email'] ?? '',
			'phone'       => $odoo_data['phone'] ?? '',
			'description' => $odoo_data['comment'] ?? '',
			'user_url'    => $odoo_data['website'] ?? '',
		];
	}
}

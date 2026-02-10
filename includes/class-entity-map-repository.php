<?php
declare( strict_types=1 );

namespace WP4Odoo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repository for the wp4odoo_entity_map table.
 *
 * Centralizes all database operations on the entity mapping table
 * that links WordPress entities to Odoo records.
 *
 * @package WP4Odoo
 * @since   1.2.0
 */
class Entity_Map_Repository {

	/**
	 * Get the Odoo ID mapped to a WordPress entity.
	 *
	 * @param string $module      Module identifier.
	 * @param string $entity_type Entity type.
	 * @param int    $wp_id       WordPress ID.
	 * @return int|null The Odoo ID, or null if not mapped.
	 */
	public static function get_odoo_id( string $module, string $entity_type, int $wp_id ): ?int {
		global $wpdb;

		$table = $wpdb->prefix . 'wp4odoo_entity_map';

		$odoo_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT odoo_id FROM {$table} WHERE module = %s AND entity_type = %s AND wp_id = %d LIMIT 1",
				$module,
				$entity_type,
				$wp_id
			)
		);

		return null !== $odoo_id ? (int) $odoo_id : null;
	}

	/**
	 * Get the WordPress ID mapped to an Odoo entity.
	 *
	 * @param string $module      Module identifier.
	 * @param string $entity_type Entity type.
	 * @param int    $odoo_id     Odoo ID.
	 * @return int|null The WordPress ID, or null if not mapped.
	 */
	public static function get_wp_id( string $module, string $entity_type, int $odoo_id ): ?int {
		global $wpdb;

		$table = $wpdb->prefix . 'wp4odoo_entity_map';

		$wp_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT wp_id FROM {$table} WHERE module = %s AND entity_type = %s AND odoo_id = %d LIMIT 1",
				$module,
				$entity_type,
				$odoo_id
			)
		);

		return null !== $wp_id ? (int) $wp_id : null;
	}

	/**
	 * Save a mapping between a WordPress entity and an Odoo record.
	 *
	 * Uses REPLACE INTO to insert or update the mapping.
	 *
	 * @param string $module      Module identifier.
	 * @param string $entity_type Entity type.
	 * @param int    $wp_id       WordPress ID.
	 * @param int    $odoo_id     Odoo ID.
	 * @param string $odoo_model  Odoo model name (e.g. 'res.partner').
	 * @param string $sync_hash   SHA-256 hash of synced data.
	 * @return bool True on success.
	 */
	public static function save( string $module, string $entity_type, int $wp_id, int $odoo_id, string $odoo_model, string $sync_hash = '' ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'wp4odoo_entity_map';

		$result = $wpdb->replace(
			$table,
			[
				'module'         => $module,
				'entity_type'    => $entity_type,
				'wp_id'          => $wp_id,
				'odoo_id'        => $odoo_id,
				'odoo_model'     => $odoo_model,
				'sync_hash'      => $sync_hash,
				'last_synced_at' => current_time( 'mysql', true ),
			]
		);

		return false !== $result;
	}

	/**
	 * Remove a mapping.
	 *
	 * @param string $module      Module identifier.
	 * @param string $entity_type Entity type.
	 * @param int    $wp_id       WordPress ID.
	 * @return bool True if a mapping was deleted.
	 */
	public static function remove( string $module, string $entity_type, int $wp_id ): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'wp4odoo_entity_map';
		$deleted = $wpdb->delete(
			$table,
			[
				'module'      => $module,
				'entity_type' => $entity_type,
				'wp_id'       => $wp_id,
			],
			[ '%s', '%s', '%d' ]
		);

		return $deleted > 0;
	}
}

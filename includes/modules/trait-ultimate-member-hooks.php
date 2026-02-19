<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Queue_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ultimate Member hook callbacks for profile and role sync.
 *
 * Handles profile updates, user registration, user deletion,
 * and role changes.
 *
 * Expects the using class to provide:
 * - should_sync(string $key): bool (from Module_Base)
 * - get_mapping(string $type, int $id): ?int (from Module_Base)
 *
 * @package WP4Odoo
 * @since   3.7.0
 */
trait Ultimate_Member_Hooks {

	/**
	 * Enqueue a profile sync job when a UM profile is updated.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function on_profile_updated( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		$this->push_entity( 'profile', 'sync_profiles', $user_id );
	}

	/**
	 * Enqueue a profile create job when a user completes registration.
	 *
	 * @param int   $user_id User ID.
	 * @param array $args    Registration arguments.
	 * @return void
	 */
	public function on_registration_complete( int $user_id, array $args = [] ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		$this->push_entity( 'profile', 'sync_profiles', $user_id );
	}

	/**
	 * Handle user deletion: push a delete job for the mapped profile.
	 *
	 * @param int $user_id The user ID being deleted.
	 * @return void
	 */
	public function on_user_delete( int $user_id ): void {
		if ( ! $this->should_sync( 'sync_profiles' ) ) {
			return;
		}

		$odoo_id = $this->get_mapping( 'profile', $user_id );
		if ( ! $odoo_id ) {
			return;
		}

		Queue_Manager::push( 'ultimate_member', 'profile', 'delete', $user_id, $odoo_id );
	}

	/**
	 * Re-push a profile when the user's role changes.
	 *
	 * Also enqueues the role itself (as a category) if not yet synced.
	 *
	 * @param int    $user_id  WordPress user ID.
	 * @param string $new_role New role slug.
	 * @return void
	 */
	public function on_role_changed( int $user_id, string $new_role = '' ): void {
		if ( ! $this->should_sync( 'sync_roles' ) ) {
			return;
		}

		if ( $user_id <= 0 ) {
			return;
		}

		// Enqueue the role as category.
		if ( '' !== $new_role ) {
			$role_wp_id = absint( crc32( $new_role ) );
			Queue_Manager::push( 'ultimate_member', 'role', 'create', $role_wp_id );
		}

		// Re-push profile to update category_id.
		if ( $this->should_sync( 'sync_profiles' ) ) {
			$odoo_id = $this->get_mapping( 'profile', $user_id ) ?? 0;
			if ( $odoo_id > 0 ) {
				Queue_Manager::push( 'ultimate_member', 'profile', 'update', $user_id, $odoo_id );
			}
		}
	}
}

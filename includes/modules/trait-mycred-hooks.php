<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * myCRED hook callbacks for push operations.
 *
 * Extracted from MyCRED_Module for single responsibility.
 * Handles point balance changes and badge assignments.
 *
 * Expects the using class to provide:
 * - is_importing(): bool           (from Module_Base)
 * - get_mapping(): ?int            (from Module_Base)
 * - get_settings(): array          (from Module_Base)
 * - push_entity(): void            (from Module_Helpers)
 * - logger: Logger                 (from Module_Base)
 *
 * @package WP4Odoo
 * @since   3.6.0
 */
trait MyCRED_Hooks {

	/**
	 * Handle point balance change for a user.
	 *
	 * Fired on mycred_update_user_balance hook.
	 *
	 * @param int    $user_id     WordPress user ID.
	 * @param int    $amount      Points changed.
	 * @param string $points_type Points type slug.
	 * @param string $reference   Log reference.
	 * @return void
	 */
	public function on_points_change( int $user_id, int $amount = 0, string $points_type = 'mycred_default', string $reference = '' ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		// Skip changes triggered by our own sync to avoid loops.
		if ( 'odoo_sync' === $reference ) {
			return;
		}

		$this->push_entity( 'points', 'sync_points', $user_id );
	}

	/**
	 * Handle badge earned by a user.
	 *
	 * Enqueues the badge type (post) for push.
	 *
	 * @param int $user_id  WordPress user ID.
	 * @param int $badge_id Badge post ID.
	 * @return void
	 */
	public function on_badge_earned( int $user_id, int $badge_id ): void {
		if ( $badge_id <= 0 ) {
			return;
		}

		$this->push_entity( 'badge', 'sync_badges', $badge_id );
	}
}

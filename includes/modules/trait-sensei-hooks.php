<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Queue_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sensei hook callbacks for push operations.
 *
 * Handles course saves, order completion, and enrollment events
 * via Sensei LMS action hooks.
 *
 * Expects the using class to provide:
 * - is_importing(): bool           (from Module_Base)
 * - get_mapping(): ?int            (from Module_Base)
 * - get_settings(): array          (from Module_Base)
 * - logger: Logger                 (from Module_Base)
 *
 * @package WP4Odoo
 * @since   3.7.0
 */
trait Sensei_Hooks {

	/**
	 * Handle Sensei course save.
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	public function on_course_save( int $post_id ): void {
		$this->handle_cpt_save( $post_id, 'course', 'sync_courses', 'course' );
	}

	/**
	 * Handle Sensei order completion.
	 *
	 * Triggered when a course status is updated (e.g. enrollment via purchase).
	 *
	 * @param int $order_id Sensei order post ID.
	 * @return void
	 */
	public function on_order_completed( int $order_id ): void {
		$this->push_entity( 'order', 'sync_orders', $order_id );
	}

	/**
	 * Handle Sensei course enrollment.
	 *
	 * Uses a synthetic WP ID: user_id * 1_000_000 + course_id.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id Sensei course ID.
	 * @return void
	 */
	public function on_enrollment( int $user_id, int $course_id ): void {
		if ( ! $this->should_sync( 'sync_enrollments' ) ) {
			return;
		}

		$synthetic_id = self::encode_synthetic_id( $user_id, $course_id );
		Queue_Manager::push( 'sensei', 'enrollment', 'create', $synthetic_id );
	}
}

<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Queue_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LearnPress hook callbacks for push operations.
 *
 * Handles course saves, order completion, and enrollment events
 * via LearnPress action hooks.
 *
 * Expects the using class to provide:
 * - is_importing(): bool           (from Module_Base)
 * - get_mapping(): ?int            (from Module_Base)
 * - get_settings(): array          (from Module_Base)
 * - logger: Logger                 (from Module_Base)
 *
 * @package WP4Odoo
 * @since   3.6.0
 */
trait LearnPress_Hooks {

	/**
	 * Handle LearnPress course save.
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	public function on_course_save( int $post_id ): void {
		$this->handle_cpt_save( $post_id, 'lp_course', 'sync_courses', 'course' );
	}

	/**
	 * Handle LearnPress order completion.
	 *
	 * @param int $order_id LearnPress order post ID.
	 * @return void
	 */
	public function on_order_completed( int $order_id ): void {
		$this->push_entity( 'order', 'sync_orders', $order_id );
	}

	/**
	 * Handle LearnPress course enrollment.
	 *
	 * Uses a synthetic WP ID: user_id * 1_000_000 + course_id.
	 *
	 * @param int $order_id  Order ID (context).
	 * @param int $course_id LearnPress course ID.
	 * @param int $user_id   WordPress user ID.
	 * @return void
	 */
	public function on_enrollment( int $order_id, int $course_id, int $user_id ): void {
		if ( ! $this->should_sync( 'sync_enrollments' ) ) {
			return;
		}

		$synthetic_id = self::encode_synthetic_id( $user_id, $course_id );
		Queue_Manager::push( 'learnpress', 'enrollment', 'create', $synthetic_id );
	}
}

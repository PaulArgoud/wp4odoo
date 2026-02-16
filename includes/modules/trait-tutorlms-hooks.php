<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Queue_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TutorLMS hook callbacks for push operations.
 *
 * Extracted from TutorLMS_Module for single responsibility.
 * Handles course saves, order placement, enrollment creation,
 * and enrollment cancellation.
 *
 * Expects the using class to provide:
 * - is_importing(): bool           (from Module_Base)
 * - get_mapping(): ?int            (from Module_Base)
 * - get_settings(): array          (from Module_Base)
 * - logger: Logger                 (from Module_Base)
 *
 * @package WP4Odoo
 * @since   3.2.0
 */
trait TutorLMS_Hooks {

	/**
	 * Handle TutorLMS course save.
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	public function on_course_save( int $post_id ): void {
		$this->handle_cpt_save( $post_id, 'courses', 'sync_courses', 'course' );
	}

	/**
	 * Handle TutorLMS order placement.
	 *
	 * @param int $order_id Order post ID.
	 * @return void
	 */
	public function on_order_placed( int $order_id ): void {
		$this->push_entity( 'order', 'sync_orders', $order_id );
	}

	/**
	 * Handle TutorLMS enrollment (course access granted).
	 *
	 * Uses a synthetic WP ID: user_id * 1_000_000 + course_id.
	 * This fits in a standard INT and is unique per user+course pair.
	 *
	 * @param int $course_id TutorLMS course ID.
	 * @param int $user_id   WordPress user ID.
	 * @return void
	 */
	public function on_enrollment( int $course_id, int $user_id ): void {
		$synthetic_id = self::encode_synthetic_id( $user_id, $course_id );
		$this->push_entity( 'enrollment', 'sync_enrollments', $synthetic_id );
	}

	/**
	 * Handle TutorLMS enrollment cancellation (course access revoked).
	 *
	 * @param int $course_id TutorLMS course ID.
	 * @param int $user_id   WordPress user ID.
	 * @return void
	 */
	public function on_enrollment_cancel( int $course_id, int $user_id ): void {
		if ( ! $this->should_sync( 'sync_enrollments' ) ) {
			return;
		}

		$synthetic_id = self::encode_synthetic_id( $user_id, $course_id );
		$odoo_id      = $this->get_mapping( 'enrollment', $synthetic_id ) ?? 0;
		Queue_Manager::push( 'tutorlms', 'enrollment', 'delete', $synthetic_id, $odoo_id );
	}
}

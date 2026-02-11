<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Queue_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LifterLMS hook callbacks for push operations.
 *
 * Extracted from LifterLMS_Module for single responsibility.
 * Handles course saves, membership saves, order completion,
 * and enrollment changes.
 *
 * Expects the using class to provide:
 * - is_importing(): bool           (from Module_Base)
 * - get_mapping(): ?int            (from Module_Base)
 * - get_settings(): array          (from Module_Base)
 * - logger: Logger                 (from Module_Base)
 *
 * @package WP4Odoo
 * @since   2.6.5
 */
trait LifterLMS_Hooks {

	/**
	 * Handle LifterLMS course save.
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	public function on_course_save( int $post_id ): void {
		if ( $this->is_importing() ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( 'llms_course' !== get_post_type( $post_id ) ) {
			return;
		}

		$settings = $this->get_settings();
		if ( empty( $settings['sync_courses'] ) ) {
			return;
		}

		$odoo_id = $this->get_mapping( 'course', $post_id ) ?? 0;
		$action  = $odoo_id ? 'update' : 'create';

		Queue_Manager::push( 'lifterlms', 'course', $action, $post_id, $odoo_id );
	}

	/**
	 * Handle LifterLMS membership save.
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	public function on_membership_save( int $post_id ): void {
		if ( $this->is_importing() ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( 'llms_membership' !== get_post_type( $post_id ) ) {
			return;
		}

		$settings = $this->get_settings();
		if ( empty( $settings['sync_memberships'] ) ) {
			return;
		}

		$odoo_id = $this->get_mapping( 'membership', $post_id ) ?? 0;
		$action  = $odoo_id ? 'update' : 'create';

		Queue_Manager::push( 'lifterlms', 'membership', $action, $post_id, $odoo_id );
	}

	/**
	 * Handle LifterLMS order completion.
	 *
	 * Triggered when an order transitions to completed/active status.
	 *
	 * @param int $order_id Order post ID.
	 * @return void
	 */
	public function on_order_completed( int $order_id ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$settings = $this->get_settings();
		if ( empty( $settings['sync_orders'] ) ) {
			return;
		}

		$odoo_id = $this->get_mapping( 'order', $order_id ) ?? 0;
		$action  = $odoo_id ? 'update' : 'create';

		Queue_Manager::push( 'lifterlms', 'order', $action, $order_id, $odoo_id );
	}

	/**
	 * Handle LifterLMS course enrollment.
	 *
	 * Uses a synthetic WP ID: user_id * 1_000_000 + course_id.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id LifterLMS course ID.
	 * @return void
	 */
	public function on_enrollment( int $user_id, int $course_id ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$settings = $this->get_settings();
		if ( empty( $settings['sync_enrollments'] ) ) {
			return;
		}

		$synthetic_id = $user_id * 1_000_000 + $course_id;

		Queue_Manager::push( 'lifterlms', 'enrollment', 'create', $synthetic_id );
	}

	/**
	 * Handle LifterLMS course unenrollment.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id LifterLMS course ID.
	 * @return void
	 */
	public function on_unenrollment( int $user_id, int $course_id ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$settings = $this->get_settings();
		if ( empty( $settings['sync_enrollments'] ) ) {
			return;
		}

		$synthetic_id = $user_id * 1_000_000 + $course_id;
		$odoo_id      = $this->get_mapping( 'enrollment', $synthetic_id ) ?? 0;

		if ( ! $odoo_id ) {
			return;
		}

		Queue_Manager::push( 'lifterlms', 'enrollment', 'delete', $synthetic_id, $odoo_id );
	}
}

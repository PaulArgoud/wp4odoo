<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Queue_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LearnDash hook callbacks for push operations.
 *
 * Extracted from LearnDash_Module for single responsibility.
 * Handles course saves, group saves, transaction creation,
 * and enrollment changes.
 *
 * Expects the using class to provide:
 * - is_importing(): bool           (from Module_Base)
 * - get_mapping(): ?int            (from Module_Base)
 * - get_settings(): array          (from Module_Base)
 * - logger: Logger                 (from Module_Base)
 *
 * @package WP4Odoo
 * @since   2.6.0
 */
trait LearnDash_Hooks {

	/**
	 * Handle LearnDash course save.
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

		if ( 'sfwd-courses' !== get_post_type( $post_id ) ) {
			return;
		}

		$settings = $this->get_settings();
		if ( empty( $settings['sync_courses'] ) ) {
			return;
		}

		$odoo_id = $this->get_mapping( 'course', $post_id ) ?? 0;
		$action  = $odoo_id ? 'update' : 'create';

		Queue_Manager::push( 'learndash', 'course', $action, $post_id, $odoo_id );
	}

	/**
	 * Handle LearnDash group save.
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	public function on_group_save( int $post_id ): void {
		if ( $this->is_importing() ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( 'groups' !== get_post_type( $post_id ) ) {
			return;
		}

		$settings = $this->get_settings();
		if ( empty( $settings['sync_groups'] ) ) {
			return;
		}

		$odoo_id = $this->get_mapping( 'group', $post_id ) ?? 0;
		$action  = $odoo_id ? 'update' : 'create';

		Queue_Manager::push( 'learndash', 'group', $action, $post_id, $odoo_id );
	}

	/**
	 * Handle LearnDash transaction creation.
	 *
	 * @param int $transaction_id Transaction post ID.
	 * @return void
	 */
	public function on_transaction_created( int $transaction_id ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$settings = $this->get_settings();
		if ( empty( $settings['sync_transactions'] ) ) {
			return;
		}

		Queue_Manager::push( 'learndash', 'transaction', 'create', $transaction_id );
	}

	/**
	 * Handle LearnDash enrollment change (course access granted or revoked).
	 *
	 * Uses a synthetic WP ID: user_id * 1_000_000 + course_id.
	 * This fits in a standard INT and is unique per user+course pair.
	 *
	 * @param int   $user_id            WordPress user ID.
	 * @param int   $course_id          LearnDash course ID.
	 * @param array $course_access_list Full list of user's course access.
	 * @param bool  $remove             Whether access is being removed.
	 * @return void
	 */
	public function on_enrollment_change( int $user_id, int $course_id, array $course_access_list, bool $remove ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$settings = $this->get_settings();
		if ( empty( $settings['sync_enrollments'] ) ) {
			return;
		}

		$synthetic_id = $user_id * 1_000_000 + $course_id;
		$action       = $remove ? 'delete' : 'create';

		$odoo_id = 0;
		if ( $remove ) {
			$odoo_id = $this->get_mapping( 'enrollment', $synthetic_id ) ?? 0;
		}

		Queue_Manager::push( 'learndash', 'enrollment', $action, $synthetic_id, $odoo_id );
	}
}

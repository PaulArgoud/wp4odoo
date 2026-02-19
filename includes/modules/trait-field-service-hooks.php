<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Queue_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field Service hook callbacks for push operations.
 *
 * Extracted from Field_Service_Module for single responsibility.
 * Handles CPT save and delete events for field service task sync.
 *
 * Expects the using class to provide:
 * - should_sync(string): bool     (from Module_Base)
 * - is_importing(): bool          (from Module_Base)
 * - get_mapping(string, int): ?int (from Module_Base)
 * - id: string                    (from Module_Base)
 *
 * @package WP4Odoo
 * @since   3.7.0
 */
trait Field_Service_Hooks {

	/**
	 * Handle field service task CPT save.
	 *
	 * Validates post type, skips revisions/autosaves, then enqueues
	 * a push to Odoo.
	 *
	 * @param int $post_id The saved post ID.
	 * @return void
	 */
	public function on_task_save( int $post_id ): void {
		if ( ! $this->should_sync( 'sync_tasks' ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( get_post_type( $post_id ) !== Field_Service_Module::CPT ) {
			return;
		}

		$this->enqueue_push( 'task', $post_id );
	}

	/**
	 * Handle field service task CPT deletion.
	 *
	 * Validates post type and checks for an existing mapping before
	 * enqueuing a delete action.
	 *
	 * @param int $post_id The post ID being deleted.
	 * @return void
	 */
	public function on_task_delete( int $post_id ): void {
		if ( $this->is_importing() ) {
			return;
		}

		if ( get_post_type( $post_id ) !== Field_Service_Module::CPT ) {
			return;
		}

		$odoo_id = $this->get_mapping( 'task', $post_id );
		if ( $odoo_id ) {
			Queue_Manager::push( $this->id, 'task', 'delete', $post_id, $odoo_id );
		}
	}
}

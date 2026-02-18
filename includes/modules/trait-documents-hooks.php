<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Queue_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Documents hook callbacks for push operations.
 *
 * Extracted from Documents_Module for single responsibility.
 * Handles document save/delete and folder save events.
 *
 * Expects the using class to provide:
 * - should_sync(string): bool     (from Module_Base)
 * - is_importing(): bool          (from Module_Base)
 * - get_mapping(string, int): ?int (from Module_Base)
 * - push_entity(string, string, int): void (from Module_Helpers)
 * - logger: Logger                (from Module_Base)
 * - id: string                    (from Module_Base)
 *
 * @package WP4Odoo
 * @since   3.6.0
 */
trait Documents_Hooks {

	/**
	 * Handle document post save.
	 *
	 * Fired on save_post_document and save_post_wpdmpro hooks.
	 *
	 * @param int $post_id The saved post ID.
	 * @return void
	 */
	public function on_document_save( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		if ( ! in_array( $post_type, [ 'document', 'wpdmpro' ], true ) ) {
			return;
		}

		$this->push_entity( 'document', 'sync_documents', $post_id );
	}

	/**
	 * Handle document post deletion.
	 *
	 * Fired on before_delete_post hook.
	 *
	 * @param int $post_id The post ID being deleted.
	 * @return void
	 */
	public function on_document_delete( int $post_id ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		if ( ! in_array( $post_type, [ 'document', 'wpdmpro' ], true ) ) {
			return;
		}

		$odoo_id = $this->get_mapping( 'document', $post_id );
		if ( $odoo_id ) {
			Queue_Manager::push( $this->id, 'document', 'delete', $post_id, $odoo_id );
		}
	}

	/**
	 * Handle folder taxonomy term save.
	 *
	 * Fired on created_document_category and edited_document_category hooks.
	 *
	 * @param int $term_id The saved term ID.
	 * @return void
	 */
	public function on_folder_saved( int $term_id ): void {
		if ( $term_id <= 0 ) {
			return;
		}

		$this->push_entity( 'folder', 'sync_folders', $term_id );
	}
}

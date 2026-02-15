<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Queue_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Knowledge hook callbacks for push operations.
 *
 * Extracted from Knowledge_Module for single responsibility.
 * Handles post save and delete events for Knowledge article sync.
 *
 * Expects the using class to provide:
 * - should_sync(string): bool     (from Module_Base)
 * - is_importing(): bool          (from Module_Base)
 * - get_mapping(string, int): ?int (from Module_Base)
 * - get_settings(): array         (from Module_Base)
 * - push_entity(string, string, int): void (from Module_Helpers)
 * - logger: Logger                (from Module_Base)
 *
 * @package WP4Odoo
 * @since   3.2.0
 */
trait Knowledge_Hooks {

	/**
	 * Handle article post save.
	 *
	 * Validates post type matches settings, checks optional category filter,
	 * then enqueues a push to Odoo.
	 *
	 * @param int $post_id The saved post ID.
	 * @return void
	 */
	public function on_article_save( int $post_id ): void {
		if ( ! $this->should_sync( 'sync_articles' ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$settings  = $this->get_settings();
		$post_type = $settings['post_type'] ?? 'post';

		if ( get_post_type( $post_id ) !== $post_type ) {
			return;
		}

		// Optional category filter.
		$filter = trim( $settings['category_filter'] ?? '' );
		if ( '' !== $filter && ! $this->post_matches_category_filter( $post_id, $filter ) ) {
			return;
		}

		$this->enqueue_push( 'article', $post_id );
	}

	/**
	 * Handle article post deletion.
	 *
	 * Validates post type matches settings and checks for an existing mapping
	 * before enqueuing a delete action.
	 *
	 * @param int $post_id The post ID being deleted.
	 * @return void
	 */
	public function on_article_delete( int $post_id ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$settings  = $this->get_settings();
		$post_type = $settings['post_type'] ?? 'post';

		if ( get_post_type( $post_id ) !== $post_type ) {
			return;
		}

		$odoo_id = $this->get_mapping( 'article', $post_id );
		if ( $odoo_id ) {
			Queue_Manager::push( $this->id, 'article', 'delete', $post_id, $odoo_id );
		}
	}

	/**
	 * Check if a post's categories match the configured filter.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $filter  Comma-separated category slugs.
	 * @return bool True if the post has at least one matching category.
	 */
	private function post_matches_category_filter( int $post_id, string $filter ): bool {
		$allowed_slugs = array_map( 'trim', explode( ',', $filter ) );
		$allowed_slugs = array_filter( $allowed_slugs );

		if ( empty( $allowed_slugs ) ) {
			return true;
		}

		$categories = get_the_category( $post_id );
		if ( empty( $categories ) ) {
			return false;
		}

		foreach ( $categories as $category ) {
			if ( in_array( $category->slug, $allowed_slugs, true ) ) {
				return true;
			}
		}

		return false;
	}
}

<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JetEngine hook registrations.
 *
 * Dynamically registers save_post and delete_post hooks for each
 * configured CPT mapping. The hooks are registered at boot time
 * based on the cpt_mappings settings.
 *
 * @package WP4Odoo
 * @since   3.5.0
 */
trait JetEngine_Hooks {

	/**
	 * Register dynamic hooks for all configured CPT mappings.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {
		if ( ! defined( 'JET_ENGINE_VERSION' ) && ! class_exists( 'Jet_Engine' ) ) {
			return;
		}

		$mappings = $this->get_cpt_mappings();

		foreach ( $mappings as $mapping ) {
			$cpt_slug    = $mapping['cpt_slug'];
			$entity_type = $mapping['entity_type'];

			// Register save hook for this CPT.
			add_action(
				"save_post_{$cpt_slug}",
				$this->safe_callback(
					function ( int $post_id ) use ( $entity_type ): void {
						$this->on_cpt_save( $post_id, $entity_type );
					}
				),
				10,
				1
			);

			// Register delete hook — fires before deletion.
			add_action(
				'before_delete_post',
				$this->safe_callback(
					function ( int $post_id ) use ( $cpt_slug, $entity_type ): void {
						$this->on_cpt_delete( $post_id, $cpt_slug, $entity_type );
					}
				),
				10,
				1
			);
		}
	}

	// ─── Callbacks ─────────────────────────────────────────

	/**
	 * Handle CPT save for a mapped entity type.
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $entity_type Entity type from mapping.
	 * @return void
	 */
	private function on_cpt_save( int $post_id, string $entity_type ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( $this->is_importing() ) {
			return;
		}

		$this->push_entity( $entity_type, 'sync_' . $entity_type, $post_id );
	}

	/**
	 * Handle CPT delete for a mapped entity type.
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $cpt_slug    CPT slug to match against.
	 * @param string $entity_type Entity type from mapping.
	 * @return void
	 */
	private function on_cpt_delete( int $post_id, string $cpt_slug, string $entity_type ): void {
		$post = get_post( $post_id );
		if ( ! $post || $cpt_slug !== $post->post_type ) {
			return;
		}

		if ( $this->is_importing() ) {
			return;
		}

		$odoo_id = $this->entity_map()->get_odoo_id( $this->get_id(), $entity_type, $post_id );
		if ( $odoo_id ) {
			$this->enqueue_push( $entity_type, $post_id );
		}
	}
}

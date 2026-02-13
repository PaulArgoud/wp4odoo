<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Recipe Maker hook callbacks for push operations.
 *
 * Extracted from WPRM_Module for single responsibility.
 * Handles recipe save events.
 *
 * Expects the using class to provide:
 * - is_importing(): bool           (from Module_Base)
 * - get_mapping(): ?int            (from Module_Base)
 * - get_settings(): array          (from Module_Base)
 * - logger: Logger                 (from Module_Base)
 *
 * @package WP4Odoo
 * @since   2.0.0
 */
trait WPRM_Hooks {

	/**
	 * Handle WP Recipe Maker recipe save.
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	public function on_recipe_save( int $post_id ): void {
		$this->handle_cpt_save( $post_id, 'wprm_recipe', 'sync_recipes', 'recipe' );
	}
}

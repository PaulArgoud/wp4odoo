<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Jeero Configurator hook callbacks for push operations.
 *
 * Extracted from Jeero_Configurator_Module for single responsibility.
 * Handles product save events, filtering for configurable products.
 *
 * Expects the using class to provide:
 * - is_importing(): bool                       (from Module_Base)
 * - get_mapping(): ?int                        (from Module_Base)
 * - get_settings(): array                      (from Module_Base)
 * - get_handler(): Jeero_Configurator_Handler  (from Jeero_Configurator_Module)
 *
 * @package WP4Odoo
 * @since   3.6.0
 */
trait Jeero_Configurator_Hooks {

	/**
	 * Handle WC product save — filter for Jeero configurable products.
	 *
	 * @param int $post_id The product post ID.
	 * @return void
	 */
	public function on_configurable_save( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( 'product' !== get_post_type( $post_id ) ) {
			return;
		}

		if ( ! $this->get_handler()->is_configurable_product( $post_id ) ) {
			return;
		}

		$this->push_entity( 'bom', 'sync_configurables', $post_id );
	}
}

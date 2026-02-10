<?php
declare( strict_types=1 );

namespace WP4Odoo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages module registration, mutual exclusivity, and lifecycle.
 *
 * Extracted from WP4Odoo_Plugin for SRP.
 *
 * @package WP4Odoo
 * @since   1.5.0
 */
class Module_Registry {

	/**
	 * Registered modules.
	 *
	 * @var array<string, Module_Base>
	 */
	private array $modules = [];

	/**
	 * Plugin instance (for third-party hook compatibility).
	 *
	 * @var \WP4Odoo_Plugin
	 */
	private \WP4Odoo_Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param \WP4Odoo_Plugin $plugin Plugin instance.
	 */
	public function __construct( \WP4Odoo_Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register all built-in modules with mutual exclusivity rules.
	 *
	 * WooCommerce and Sales modules are mutually exclusive: when WooCommerce
	 * is active and the woocommerce module is enabled, Sales_Module is not loaded.
	 *
	 * @return void
	 */
	public function register_all(): void {
		$this->register( 'crm', new Modules\CRM_Module() );

		$wc_active  = class_exists( 'WooCommerce' );
		$wc_enabled = get_option( 'wp4odoo_module_woocommerce_enabled', false );

		if ( $wc_active && $wc_enabled ) {
			$this->register( 'woocommerce', new Modules\WooCommerce_Module() );
		} else {
			$this->register( 'sales', new Modules\Sales_Module() );

			if ( $wc_active ) {
				// Register WC module (disabled) so it appears in admin UI.
				$this->register( 'woocommerce', new Modules\WooCommerce_Module() );
			}
		}

		// Memberships module (requires WooCommerce + WC Memberships).
		if ( $wc_active ) {
			$this->register( 'memberships', new Modules\Memberships_Module() );
		}

		// Allow third-party modules.
		do_action( 'wp4odoo_register_modules', $this->plugin );
	}

	/**
	 * Register a single module. Boots it if enabled.
	 *
	 * @param string      $id     Module identifier.
	 * @param Module_Base $module Module instance.
	 * @return void
	 */
	public function register( string $id, Module_Base $module ): void {
		$this->modules[ $id ] = $module;

		$enabled = get_option( 'wp4odoo_module_' . $id . '_enabled', false );
		if ( $enabled ) {
			$module->boot();
		}
	}

	/**
	 * Get a registered module.
	 *
	 * @param string $id Module identifier.
	 * @return Module_Base|null
	 */
	public function get( string $id ): ?Module_Base {
		return $this->modules[ $id ] ?? null;
	}

	/**
	 * Get all registered modules.
	 *
	 * @return array<string, Module_Base>
	 */
	public function all(): array {
		return $this->modules;
	}
}

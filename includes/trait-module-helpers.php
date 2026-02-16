<?php
declare( strict_types=1 );

namespace WP4Odoo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared helper methods for Module_Base.
 *
 * Composition trait that aggregates all sub-traits into a single
 * `use Module_Helpers;` for Module_Base. This maintains backward
 * compatibility: Module_Base only needs to use this one trait
 * and gets all helper methods.
 *
 * Sub-traits:
 * - Partner_Helpers    — Partner resolution (partner_service, resolve_partner_*)
 * - Accounting_Helpers — Invoice auto-posting (auto_post_invoice)
 * - Dependency_Helpers — Entity dependency resolution, plugin checks, Odoo model probing
 * - Sync_Helpers       — Synthetic IDs, push_entity, translation service, field resolution, utilities
 *
 * @package WP4Odoo
 * @since   2.8.0
 */
trait Module_Helpers {
	use Partner_Helpers;
	use Accounting_Helpers;
	use Dependency_Helpers;
	use Sync_Helpers;
}

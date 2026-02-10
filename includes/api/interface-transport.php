<?php
declare( strict_types=1 );

namespace WP4Odoo\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transport interface for Odoo RPC communication.
 *
 * Defines the contract that JSON-RPC and XML-RPC transports must fulfill.
 *
 * @package WP4Odoo
 * @since   1.0.0
 */
interface Transport {

	/**
	 * Authenticate against Odoo and retrieve the user ID.
	 *
	 * @param string $username The Odoo login.
	 * @return int The authenticated user ID (uid).
	 * @throws \RuntimeException On authentication failure.
	 */
	public function authenticate( string $username ): int;

	/**
	 * Execute a method on an Odoo model via execute_kw.
	 *
	 * @param string $model  Odoo model name (e.g., 'res.partner').
	 * @param string $method Method name (e.g., 'search_read').
	 * @param array  $args   Positional arguments.
	 * @param array  $kwargs Keyword arguments.
	 * @return mixed The Odoo response result.
	 * @throws \RuntimeException On RPC error or if not authenticated.
	 */
	public function execute_kw( string $model, string $method, array $args = [], array $kwargs = [] ): mixed;

	/**
	 * Get the authenticated user ID.
	 *
	 * @return int|null
	 */
	public function get_uid(): ?int;
}

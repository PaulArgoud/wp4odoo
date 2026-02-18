<?php
declare( strict_types=1 );

namespace WP4Odoo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Multisite / network settings for shared Odoo connections.
 *
 * Manages network-level connection settings, per-site company_id
 * mapping, and effective connection resolution (local → network
 * fallback).
 *
 * Expects the using class to provide:
 * - get_connection(): array   (method)
 * - DEFAULTS_CONNECTION       (constant)
 *
 * @package WP4Odoo
 * @since   3.6.0
 */
trait Network_Settings {

	/**
	 * Network option key for shared connection settings.
	 */
	public const OPT_NETWORK_CONNECTION = 'wp4odoo_network_connection';

	/**
	 * Network option key for site → company_id mapping.
	 */
	public const OPT_NETWORK_SITE_COMPANIES = 'wp4odoo_network_site_companies';

	/**
	 * Get the effective connection settings.
	 *
	 * In multisite, falls back to the network-level connection if the
	 * current site has no local connection configured. Also applies
	 * the site's company_id from the network mapping.
	 *
	 * In single-site, this is equivalent to get_connection().
	 *
	 * @return array
	 */
	public function get_effective_connection(): array {
		$local = $this->get_connection();

		if ( ! is_multisite() ) {
			return $local;
		}

		// If local site has a configured URL, use the local connection.
		if ( ! empty( $local['url'] ) ) {
			// Apply network company_id if local doesn't have one.
			if ( 0 === (int) $local['company_id'] ) {
				$local['company_id'] = $this->get_site_company_id();
			}
			return $local;
		}

		// Fall back to network connection.
		$network = $this->get_network_connection();
		if ( empty( $network['url'] ) ) {
			return $local; // No network connection either.
		}

		$merged = array_merge( self::DEFAULTS_CONNECTION, $network );

		// Apply the site's company_id from the network mapping.
		$site_company = $this->get_site_company_id();
		if ( $site_company > 0 ) {
			$merged['company_id'] = $site_company;
		}

		return $merged;
	}

	/**
	 * Get network-level connection settings.
	 *
	 * @return array
	 */
	public function get_network_connection(): array {
		if ( ! is_multisite() ) {
			return [];
		}

		$stored = get_site_option( self::OPT_NETWORK_CONNECTION, [] );
		return is_array( $stored ) ? $stored : [];
	}

	/**
	 * Save network-level connection settings.
	 *
	 * @param array $data Connection settings.
	 * @return bool
	 */
	public function save_network_connection( array $data ): bool {
		return update_site_option( self::OPT_NETWORK_CONNECTION, $data );
	}

	/**
	 * Get the Odoo company_id assigned to the current site.
	 *
	 * @return int Company ID (0 if not assigned).
	 */
	public function get_site_company_id(): int {
		if ( ! is_multisite() ) {
			$conn = $this->get_connection();
			return (int) ( $conn['company_id'] ?? 0 );
		}

		$mapping = get_site_option( self::OPT_NETWORK_SITE_COMPANIES, [] );
		$blog_id = (string) get_current_blog_id();

		return (int) ( $mapping[ $blog_id ] ?? 0 );
	}

	/**
	 * Get the site → company_id mapping for the network.
	 *
	 * @return array<int, int> Blog ID → Company ID.
	 */
	public function get_network_site_companies(): array {
		$stored = get_site_option( self::OPT_NETWORK_SITE_COMPANIES, [] );
		return is_array( $stored ) ? $stored : [];
	}

	/**
	 * Save the site → company_id mapping for the network.
	 *
	 * @param array<int, int> $mapping Blog ID → Company ID.
	 * @return bool
	 */
	public function save_network_site_companies( array $mapping ): bool {
		return update_site_option( self::OPT_NETWORK_SITE_COMPANIES, $mapping );
	}

	/**
	 * Check whether the current site uses the network connection.
	 *
	 * @return bool
	 */
	public function is_using_network_connection(): bool {
		if ( ! is_multisite() ) {
			return false;
		}

		$local = $this->get_connection();
		return empty( $local['url'] ) && ! empty( $this->get_network_connection()['url'] ?? '' );
	}
}

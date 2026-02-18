<?php
declare( strict_types=1 );

namespace WP4Odoo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reusable validation primitives for settings data.
 *
 * Centralises the enum, range-clamp, and string checks that were
 * duplicated across Settings_Repository read and write methods.
 *
 * @package WP4Odoo
 * @since   3.5.0
 */
class Settings_Validator {

	/**
	 * Validate a value against a list of allowed strings.
	 *
	 * @param mixed           $value   Value to validate.
	 * @param array<string>   $valid   Allowed values.
	 * @param string          $default Fallback when $value is not in $valid.
	 * @return string
	 */
	public static function enum( mixed $value, array $valid, string $default ): string {
		return in_array( $value, $valid, true ) ? (string) $value : $default;
	}

	/**
	 * Clamp an integer value to a [min, max] range.
	 *
	 * @param mixed $value Value to clamp (cast to int).
	 * @param int   $min   Minimum allowed value.
	 * @param int   $max   Maximum allowed value.
	 * @return int
	 */
	public static function clamp( mixed $value, int $min, int $max ): int {
		return max( $min, min( $max, (int) $value ) );
	}

	/**
	 * Return the string if non-empty, or empty string otherwise.
	 *
	 * @param mixed $value Value to validate.
	 * @return string The trimmed string, or '' if invalid/empty.
	 */
	public static function non_empty_string( mixed $value ): string {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Check if a URL is safe (not pointing to private/internal networks).
	 *
	 * Rejects localhost, private IPv4/IPv6, link-local, and metadata
	 * service addresses to prevent SSRF attacks.
	 *
	 * @param string $url The URL to validate.
	 * @return bool True if the URL is safe.
	 */
	public static function is_safe_url( string $url ): bool {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( empty( $host ) ) {
			return false;
		}

		$lower_host = strtolower( $host );
		if ( in_array( $lower_host, [ 'localhost', '127.0.0.1', '::1', '0.0.0.0' ], true ) ) {
			return false;
		}

		if ( str_ends_with( $lower_host, '.local' ) || str_ends_with( $lower_host, '.internal' ) ) {
			return false;
		}

		$ip = gethostbyname( $host );
		if ( $ip === $host ) {
			return false;
		}

		return false !== filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	}
}
